<?php

declare(strict_types=1);

namespace VeloForum\Service;

/**
 * Buduje wielopoziomowe drzewo: Kategoria -> Dział -> Podkategorie (rekurencyjnie),
 * wyznacza ścieżkę okruszków (breadcrumbs) oraz agreguje liczniki tematów/postów i ostatni wpis z podkategorii.
 */
final class ForumTreeService
{
    /**
     * @param array<int, array<string, mixed>> $flatForums
     * @return array<int, array<string, mixed>>
     */
    public function buildTree(array $flatForums): array
    {
        $byId = [];
        foreach ($flatForums as $row) {
            $row['children'] = [];
            $byId[(int) $row['id']] = $row;
        }

        $roots = [];
        foreach ($byId as $id => &$node) {
            $parentId = $node['parent_id'] !== null ? (int) $node['parent_id'] : null;
            if ($parentId !== null && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        foreach ($roots as &$root) {
            $this->aggregateNode($root);
        }
        unset($root);

        return $roots;
    }

    /**
     * Zwraca bezpośrednie podkategorie danego działu z już zagregowanymi licznikami z niższych poziomów.
     *
     * @param array<int, array<string, mixed>> $flatForums
     * @return array<int, array<string, mixed>>
     */
    public function childrenOfNode(array $flatForums, int $forumId): array
    {
        $roots = $this->buildTree($flatForums);
        $found = $this->findInTree($roots, $forumId);
        return $found['children'] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $flatForums
     * @return array<int, array<string, mixed>>
     */
    public function buildBreadcrumbs(array $flatForums, int $forumId): array
    {
        $byId = [];
        foreach ($flatForums as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $trail = [];
        $currentId = $forumId;
        $guard = 0;

        while ($currentId !== null && isset($byId[$currentId]) && $guard < 16) {
            array_unshift($trail, $byId[$currentId]);
            $parent = $byId[$currentId]['parent_id'];
            $currentId = $parent !== null ? (int) $parent : null;
            $guard++;
        }

        return $trail;
    }

    private function aggregateNode(array &$node): void
    {
        $totalTopics = (int) ($node['topics_count'] ?? 0);
        $totalPosts = (int) ($node['posts_count'] ?? 0);
        $bestLastPostAt = (int) ($node['last_post_at'] ?? 0);

        foreach ($node['children'] as &$child) {
            $this->aggregateNode($child);
            $totalTopics += (int) ($child['topics_count'] ?? 0);
            $totalPosts += (int) ($child['posts_count'] ?? 0);

            $childLastAt = (int) ($child['last_post_at'] ?? 0);
            $childHasTopic = !empty($child['last_topic_id']) && !empty($child['last_topic_title']);
            $nodeHasTopic = !empty($node['last_topic_id']) && !empty($node['last_topic_title']);

            if ($childHasTopic && (!$nodeHasTopic || $childLastAt > $bestLastPostAt)) {
                $bestLastPostAt = $childLastAt;
                $node['last_topic_id'] = $child['last_topic_id'];
                $node['last_topic_title'] = $child['last_topic_title'];
                $node['last_topic_slug'] = $child['last_topic_slug'] ?? 'temat';
                $node['last_post_at'] = $child['last_post_at'] ?? null;
                $node['last_poster_id'] = $child['last_poster_id'] ?? null;
                $node['last_poster_name'] = $child['last_poster_name'] ?? null;
                $node['last_poster_slug'] = $child['last_poster_slug'] ?? 'profil';
                $node['last_poster_avatar'] = $child['last_poster_avatar'] ?? null;
                $node['last_poster_color'] = $child['last_poster_color'] ?? null;
                $node['last_poster_prefix'] = $child['last_poster_prefix'] ?? null;
                $node['last_poster_suffix'] = $child['last_poster_suffix'] ?? null;
                $node['last_poster_group'] = $child['last_poster_group'] ?? null;
            }
        }
        unset($child);

        $node['topics_count'] = $totalTopics;
        $node['posts_count'] = $totalPosts;
    }

    /** @param array<int, array<string, mixed>> $nodes */
    private function findInTree(array $nodes, int $forumId): ?array
    {
        foreach ($nodes as $node) {
            if ((int) $node['id'] === $forumId) {
                return $node;
            }
            if (!empty($node['children'])) {
                $match = $this->findInTree($node['children'], $forumId);
                if ($match !== null) {
                    return $match;
                }
            }
        }
        return null;
    }
}

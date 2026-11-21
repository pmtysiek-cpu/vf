<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class ReactionRepository
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $defsCache = null;

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function allDefinitions(): array
    {
        if ($this->defsCache !== null) {
            return $this->defsCache;
        }
        $this->defsCache = $this->db->query('SELECT * FROM vf_reactions ORDER BY position ASC, id ASC')->fetchAll();
        return $this->defsCache;
    }

    /**
     * @param array<int, int> $postIds
     * @return array<int, array{counts: array<int, int>, user_reaction: int|null}>
     */
    public function forPosts(array $postIds, ?int $currentMemberId): array
    {
        if (empty($postIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT post_id, reaction_id, COUNT(*) AS cnt
             FROM vf_post_reactions
             WHERE post_id IN ({$placeholders})
             GROUP BY post_id, reaction_id"
        );
        $stmt->execute(array_values($postIds));

        $result = [];
        while ($row = $stmt->fetch()) {
            $pid = (int) $row['post_id'];
            $rid = (int) $row['reaction_id'];
            $result[$pid]['counts'][$rid] = (int) $row['cnt'];
        }

        if ($currentMemberId !== null) {
            $userStmt = $this->db->prepare(
                "SELECT post_id, reaction_id
                 FROM vf_post_reactions
                 WHERE member_id = ? AND post_id IN ({$placeholders})"
            );
            $userStmt->execute(array_merge([$currentMemberId], array_values($postIds)));
            while ($row = $userStmt->fetch()) {
                $result[(int) $row['post_id']]['user_reaction'] = (int) $row['reaction_id'];
            }
        }

        return $result;
    }

    /**
     * @return array{action: 'added'|'switched'|'removed', post_author_id: int|null, reaction_label: string}
     */
    public function toggle(int $postId, int $memberId, int $reactionId): array
    {
        $defStmt = $this->db->prepare('SELECT id, label, points FROM vf_reactions WHERE id = :id LIMIT 1');
        $defStmt->execute(['id' => $reactionId]);
        $reaction = $defStmt->fetch();
        if (!$reaction) {
            throw new \InvalidArgumentException('Nieznany typ reakcji.');
        }

        $postStmt = $this->db->prepare('SELECT author_id FROM vf_posts WHERE id = :pid LIMIT 1');
        $postStmt->execute(['pid' => $postId]);
        $post = $postStmt->fetch();
        $authorId = $post && $post['author_id'] ? (int) $post['author_id'] : null;

        $this->db->beginTransaction();
        try {
            $existingStmt = $this->db->prepare(
                'SELECT pr.id, pr.reaction_id, r.points
                 FROM vf_post_reactions pr
                 INNER JOIN vf_reactions r ON r.id = pr.reaction_id
                 WHERE pr.post_id = :pid AND pr.member_id = :mid
                 LIMIT 1'
            );
            $existingStmt->execute(['pid' => $postId, 'mid' => $memberId]);
            $existing = $existingStmt->fetch();

            $newPoints = (int) $reaction['points'];
            $action = 'added';

            if ($existing) {
                $oldPoints = (int) $existing['points'];
                if ((int) $existing['reaction_id'] === $reactionId) {
                    $this->db->prepare('DELETE FROM vf_post_reactions WHERE id = :id')
                        ->execute(['id' => (int) $existing['id']]);
                    if ($authorId !== null) {
                        $this->db->prepare('UPDATE vf_members SET reputation_points = reputation_points - :pts WHERE id = :uid')
                            ->execute(['pts' => $oldPoints, 'uid' => $authorId]);
                    }
                    $action = 'removed';
                } else {
                    $this->db->prepare('UPDATE vf_post_reactions SET reaction_id = :rid, created_at = :at WHERE id = :id')
                        ->execute(['rid' => $reactionId, 'at' => time(), 'id' => (int) $existing['id']]);
                    if ($authorId !== null) {
                        $delta = $newPoints - $oldPoints;
                        $this->db->prepare('UPDATE vf_members SET reputation_points = reputation_points + :pts WHERE id = :uid')
                            ->execute(['pts' => $delta, 'uid' => $authorId]);
                    }
                    $action = 'switched';
                }
            } else {
                $this->db->prepare(
                    'INSERT INTO vf_post_reactions (post_id, member_id, reaction_id, created_at)
                     VALUES (:pid, :mid, :rid, :at)'
                )->execute([
                    'pid' => $postId,
                    'mid' => $memberId,
                    'rid' => $reactionId,
                    'at'  => time(),
                ]);
                if ($authorId !== null) {
                    $this->db->prepare('UPDATE vf_members SET reputation_points = reputation_points + :pts WHERE id = :uid')
                        ->execute(['pts' => $newPoints, 'uid' => $authorId]);
                }
            }

            $this->db->commit();
            return [
                'action'         => $action,
                'post_author_id' => $authorId,
                'reaction_label' => (string) $reaction['label'],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function reactorsForPost(int $postId): array
    {
        $stmt = $this->db->prepare(
            'SELECT pr.member_id, m.username, m.slug AS member_slug,
                    g.color_hex AS group_color, g.name AS group_name,
                    r.code, r.label
             FROM vf_post_reactions pr
             INNER JOIN vf_members m ON m.id = pr.member_id
             LEFT JOIN vf_groups g ON g.id = m.primary_group_id
             INNER JOIN vf_reactions r ON r.id = pr.reaction_id
             WHERE pr.post_id = :pid
             ORDER BY pr.created_at DESC
             LIMIT 40'
        );
        $stmt->execute(['pid' => $postId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentCommunityPulse(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            'SELECT pr.created_at, m.id AS actor_id, m.username AS actor_name, m.slug AS actor_slug,
                    g.color_hex AS actor_group_color, g.prefix_html AS actor_group_prefix,
                    g.suffix_html AS actor_group_suffix, g.name AS actor_group_name,
                    r.code AS reaction_code, r.label AS reaction_label,
                    p.id AS post_id, t.id AS topic_id, t.title AS topic_title, t.slug AS topic_slug
             FROM vf_post_reactions pr
             INNER JOIN vf_members m ON m.id = pr.member_id
             LEFT JOIN vf_groups g ON g.id = m.primary_group_id
             INNER JOIN vf_reactions r ON r.id = pr.reaction_id
             INNER JOIN vf_posts p ON p.id = pr.post_id
             INNER JOIN vf_topics t ON t.id = p.topic_id
             WHERE p.is_approved = 1 AND t.is_approved = 1
             ORDER BY pr.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAllReactions(): int
    {
        try {
            return (int) $this->db->query('SELECT COUNT(*) FROM vf_post_reactions')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}

<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class MemberRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function stats(): array
    {
        $counts = $this->db->query(
            'SELECT
                (SELECT COUNT(*) FROM vf_members) AS members,
                (SELECT COUNT(*) FROM vf_topics WHERE is_approved = 1) AS topics,
                (SELECT COUNT(*) FROM vf_posts WHERE is_approved = 1) AS posts'
        )->fetch() ?: ['members' => 0, 'topics' => 0, 'posts' => 0];
        $newest = $this->db->query(
            'SELECT m.id, m.username, m.slug, m.joined_at,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             ORDER BY m.id DESC
             LIMIT 1'
        )->fetch();

        return [
            'members' => (int) ($counts['members'] ?? 0),
            'topics'  => (int) ($counts['topics'] ?? 0),
            'posts'   => (int) ($counts['posts'] ?? 0),
            'newest'  => $newest ?: null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function onlineMembers(int $limit = 12, int $windowSeconds = 1200): array
    {
        $cutoff = time() - $windowSeconds;
        $stmt = $this->db->prepare(
            'SELECT m.id, m.username, m.slug, m.avatar_url, m.joined_at, m.last_activity_at,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.is_banned = 0
               AND m.last_activity_at IS NOT NULL
               AND m.last_activity_at >= :cutoff
             ORDER BY m.last_activity_at DESC, m.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('cutoff', $cutoff, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countOnline(int $windowSeconds = 1200): int
    {
        $cutoff = time() - $windowSeconds;
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM vf_members WHERE is_banned = 0 AND last_activity_at IS NOT NULL AND last_activity_at >= :cutoff'
        );
        $stmt->execute(['cutoff' => $cutoff]);
        return (int) $stmt->fetchColumn();
    }

    public function findProfileById(int $memberId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.username, m.slug, m.avatar_url, m.cover_url, m.music_url, m.chat_muted_until,
                    m.member_title, m.bio, m.signature,
                    m.custom_fields, m.posts_count, m.reputation_points, m.warning_points,
                    m.joined_at, m.last_activity_at, m.is_banned,
                    m.primary_group_id, m.secondary_group_ids,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix,
                    g.is_admin, g.is_moderator,
                    COALESCE(g.can_set_profile_cover, 0) AS can_set_profile_cover,
                    COALESCE(g.can_set_profile_music, 0) AS can_set_profile_music
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $memberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function hoverCardById(int $memberId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.username, m.slug, m.avatar_url, m.cover_url, m.member_title,
                    m.posts_count, m.reputation_points, m.joined_at, m.last_activity_at, m.is_banned,
                    (SELECT COUNT(*) FROM vf_topics t WHERE t.author_id = m.id AND t.is_approved = 1) AS topics_count,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $memberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function searchByPrefix(string $query, int $limit = 8): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            $stmt = $this->db->prepare(
                'SELECT m.id, m.username, m.slug, m.avatar_url, m.member_title,
                        g.name AS group_name, g.color_hex AS group_color,
                        g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                 FROM vf_members m
                 INNER JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE m.is_banned = 0
                 ORDER BY m.reputation_points DESC, m.posts_count DESC
                 LIMIT :limit'
            );
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $like = str_replace(['%', '_'], ['\\%', '\\_'], $trimmed) . '%';
        $stmt = $this->db->prepare(
            'SELECT m.id, m.username, m.slug, m.avatar_url, m.member_title,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.is_banned = 0 AND m.username LIKE :prefix
             ORDER BY m.posts_count DESC
             LIMIT :limit'
        );
        $stmt->bindValue('prefix', $like);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param string[] $usernames
     * @return array<int, array<string, mixed>>
     */
    public function findByUsernames(array $usernames): array
    {
        $clean = array_values(array_unique(array_filter(array_map('trim', $usernames))));
        if ($clean === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clean), '?'));
        $stmt = $this->db->prepare(
            "SELECT m.id, m.username, m.slug, m.primary_group_id,
                    g.color_hex AS group_color, g.name AS group_name
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.username IN ({$placeholders}) AND m.is_banned = 0"
        );
        $stmt->execute($clean);
        return $stmt->fetchAll();
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    public function findBriefWithGroupsByIds(array $ids): array
    {
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($clean === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clean), '?'));
        $stmt = $this->db->prepare(
            "SELECT m.id, m.username, m.slug, m.primary_group_id,
                    g.color_hex AS group_color, g.name AS group_name
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE m.id IN ({$placeholders}) AND m.is_banned = 0"
        );
        $stmt->execute($clean);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function listAllMembers(string $search = '', string $sort = 'reputation', int $limit = 50): array
    {
        $orderBy = match ($sort) {
            'posts'  => 'm.posts_count DESC, m.reputation_points DESC',
            'newest' => 'm.joined_at DESC, m.id DESC',
            default  => 'm.reputation_points DESC, m.posts_count DESC',
        };

        $trimmed = trim($search);
        if ($trimmed !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $trimmed) . '%';
            $stmt = $this->db->prepare(
                "SELECT m.id, m.username, m.slug, m.avatar_url, m.cover_url, m.member_title,
                        m.posts_count, m.reputation_points, m.joined_at, m.last_activity_at, m.is_banned,
                        g.name AS group_name, g.color_hex AS group_color,
                        g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                 FROM vf_members m
                 INNER JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE m.username LIKE :q1 OR m.member_title LIKE :q2
                 ORDER BY {$orderBy}
                 LIMIT :limit"
            );
            $stmt->bindValue('q1', $like);
            $stmt->bindValue('q2', $like);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            "SELECT m.id, m.username, m.slug, m.avatar_url, m.cover_url, m.member_title,
                    m.posts_count, m.reputation_points, m.joined_at, m.last_activity_at, m.is_banned,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             ORDER BY {$orderBy}
             LIMIT :limit"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param int[] $memberIds
     * @return array<int, int>
     */
    public function topicsCountMap(array $memberIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT author_id, COUNT(*) AS cnt
             FROM vf_topics
             WHERE is_approved = 1 AND author_id IN ({$placeholders})
             GROUP BY author_id"
        );
        $stmt->execute($ids);
        $map = [];
        while ($row = $stmt->fetch()) {
            $map[(int) $row['author_id']] = (int) $row['cnt'];
        }
        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function leaderboard(string $metric = 'reputation', string $period = 'all', int $limit = 5): array
    {
        $metric = in_array($metric, ['reputation', 'posts', 'topics'], true) ? $metric : 'reputation';
        $period = in_array($period, ['week', 'month', 'year', 'all'], true) ? $period : 'all';
        $safeLimit = max(1, min(20, $limit));

        $cutoff = match ($period) {
            'week'  => time() - 7 * 86400,
            'month' => time() - 30 * 86400,
            'year'  => time() - 365 * 86400,
            default => null,
        };

        if ($metric === 'posts') {
            if ($cutoff === null) {
                $stmt = $this->db->prepare(
                    'SELECT m.id, m.username, m.slug, m.avatar_url,
                            m.posts_count AS score,
                            g.name AS group_name, g.color_hex AS group_color,
                            g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                     FROM vf_members m
                     INNER JOIN vf_groups g ON g.id = m.primary_group_id
                     WHERE m.is_banned = 0 AND m.posts_count > 0
                     ORDER BY m.posts_count DESC, m.reputation_points DESC, m.id ASC
                     LIMIT :lim'
                );
                $stmt->bindValue('lim', $safeLimit, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll();
            }

            $stmt = $this->db->prepare(
                'SELECT m.id, m.username, m.slug, m.avatar_url,
                        COUNT(p.id) AS score,
                        g.name AS group_name, g.color_hex AS group_color,
                        g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                 FROM vf_posts p
                 INNER JOIN vf_members m ON m.id = p.author_id
                 INNER JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE p.is_approved = 1 AND p.created_at >= :cutoff AND m.is_banned = 0
                 GROUP BY m.id, m.username, m.slug, m.avatar_url, g.name, g.color_hex, g.prefix_html, g.suffix_html
                 HAVING score > 0
                 ORDER BY score DESC, m.posts_count DESC, m.id ASC
                 LIMIT :lim'
            );
            $stmt->bindValue('cutoff', $cutoff, PDO::PARAM_INT);
            $stmt->bindValue('lim', $safeLimit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        if ($metric === 'topics') {
            $whereCutoff = $cutoff !== null ? ' AND t.created_at >= :cutoff' : '';
            $stmt = $this->db->prepare(
                "SELECT m.id, m.username, m.slug, m.avatar_url,
                        COUNT(t.id) AS score,
                        g.name AS group_name, g.color_hex AS group_color,
                        g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                 FROM vf_topics t
                 INNER JOIN vf_members m ON m.id = t.author_id
                 INNER JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE t.is_approved = 1{$whereCutoff} AND m.is_banned = 0
                 GROUP BY m.id, m.username, m.slug, m.avatar_url, g.name, g.color_hex, g.prefix_html, g.suffix_html
                 HAVING score > 0
                 ORDER BY score DESC, m.posts_count DESC, m.id ASC
                 LIMIT :lim"
            );
            if ($cutoff !== null) {
                $stmt->bindValue('cutoff', $cutoff, PDO::PARAM_INT);
            }
            $stmt->bindValue('lim', $safeLimit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        // metric === 'reputation'
        if ($cutoff === null) {
            $stmt = $this->db->prepare(
                'SELECT m.id, m.username, m.slug, m.avatar_url,
                        m.reputation_points AS score,
                        g.name AS group_name, g.color_hex AS group_color,
                        g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                 FROM vf_members m
                 INNER JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE m.is_banned = 0 AND m.reputation_points > 0
                 ORDER BY m.reputation_points DESC, m.posts_count DESC, m.id ASC
                 LIMIT :lim'
            );
            $stmt->bindValue('lim', $safeLimit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT m.id, m.username, m.slug, m.avatar_url,
                    COALESCE(SUM(r.points), 0) AS score,
                    g.name AS group_name, g.color_hex AS group_color,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
             FROM vf_post_reactions pr
             INNER JOIN vf_reactions r ON r.id = pr.reaction_id
             INNER JOIN vf_posts p ON p.id = pr.post_id
             INNER JOIN vf_members m ON m.id = p.author_id
             INNER JOIN vf_groups g ON g.id = m.primary_group_id
             WHERE p.is_approved = 1 AND pr.created_at >= :cutoff AND m.is_banned = 0
             GROUP BY m.id, m.username, m.slug, m.avatar_url, g.name, g.color_hex, g.prefix_html, g.suffix_html
             HAVING score > 0
             ORDER BY score DESC, m.reputation_points DESC, m.id ASC
             LIMIT :lim'
        );
        $stmt->bindValue('cutoff', $cutoff, PDO::PARAM_INT);
        $stmt->bindValue('lim', $safeLimit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countTopicsByMember(int $memberId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM vf_topics WHERE author_id = :id');
        $stmt->execute(['id' => $memberId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentTopicsByMember(int $memberId, int $limit = 12): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, f.name AS forum_name, f.slug AS forum_slug
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             WHERE t.author_id = :id
             ORDER BY t.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('id', $memberId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function recentPostsByMember(int $memberId, int $limit = 12): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.topic_id, p.content_html, p.is_first_post, p.created_at,
                    t.title AS topic_title, t.slug AS topic_slug,
                    f.id AS forum_id, f.name AS forum_name, f.slug AS forum_slug
             FROM vf_posts p
             INNER JOIN vf_topics t ON t.id = p.topic_id
             INNER JOIN vf_forums f ON f.id = t.forum_id
             WHERE p.author_id = :id AND p.is_approved = 1
             ORDER BY p.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('id', $memberId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function reactionBreakdownForMember(int $memberId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id, r.code, r.label, r.points, COALESCE(agg.total, 0) AS total
             FROM vf_reactions r
             LEFT JOIN (
                 SELECT pr.reaction_id, COUNT(*) AS total
                 FROM vf_posts p
                 INNER JOIN vf_post_reactions pr ON pr.post_id = p.id
                 WHERE p.author_id = :id AND p.is_approved = 1
                 GROUP BY pr.reaction_id
             ) agg ON agg.reaction_id = r.id
             ORDER BY total DESC, r.id ASC'
        );
        $stmt->execute(['id' => $memberId]);
        return $stmt->fetchAll();
    }

    /** @param array<string, string>|null $customFields */
    public function updateOwnProfile(
        int $memberId,
        string $memberTitle,
        string $bio,
        string $signature,
        ?string $avatarUrl,
        ?array $customFields = null,
        ?string $coverUrl = null,
        bool $updateCover = false,
        ?string $musicUrl = null,
        bool $updateMusic = false
    ): void {
        $sql = 'UPDATE vf_members
                SET member_title = :member_title,
                    bio = :bio,
                    signature = :signature,
                    avatar_url = :avatar_url';
        $params = [
            'id'           => $memberId,
            'member_title' => mb_substr(trim($memberTitle), 0, 120),
            'bio'          => mb_substr(trim($bio), 0, 1200),
            'signature'    => mb_substr(trim($signature), 0, 400),
            'avatar_url'   => $avatarUrl !== null && trim($avatarUrl) !== '' ? mb_substr(trim($avatarUrl), 0, 500) : null,
        ];

        if ($updateCover) {
            $sql .= ', cover_url = :cover_url';
            $params['cover_url'] = $coverUrl !== null && trim($coverUrl) !== '' ? mb_substr(trim($coverUrl), 0, 500) : null;
        }

        if ($updateMusic) {
            $sql .= ', music_url = :music_url';
            $params['music_url'] = $musicUrl !== null && trim($musicUrl) !== '' ? mb_substr(trim($musicUrl), 0, 500) : null;
        }

        if ($customFields !== null) {
            $cleanFields = [];
            foreach ($customFields as $k => $v) {
                $key = preg_replace('/[^a-z0-9_]+/i', '', (string) $k) ?? '';
                $val = trim(mb_substr((string) $v, 0, 200));
                if ($key !== '' && $val !== '') {
                    $cleanFields[$key] = $val;
                }
            }
            $sql .= ', custom_fields = :custom_fields';
            $params['custom_fields'] = $cleanFields !== [] ? json_encode($cleanFields, JSON_UNESCAPED_UNICODE) : null;
        }

        $sql .= ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }
}

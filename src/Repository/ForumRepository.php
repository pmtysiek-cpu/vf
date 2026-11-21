<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class ForumRepository
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $allOrderedCache = null;

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function allOrdered(): array
    {
        if ($this->allOrderedCache !== null) {
            return $this->allOrderedCache;
        }
        $sql = 'SELECT f.*,
                       t.id AS last_topic_id,
                       t.title AS last_topic_title,
                       t.slug AS last_topic_slug,
                       t.last_post_at AS last_post_at,
                       COALESCE(NULLIF(t.last_poster_id, 0), lmn.id) AS last_poster_id,
                       COALESCE(lm.username, lmn.username, t.last_poster_name) AS last_poster_name,
                       COALESCE(lm.slug, lmn.slug) AS last_poster_slug,
                       COALESCE(lm.avatar_url, lmn.avatar_url) AS last_poster_avatar,
                       lg.color_hex AS last_poster_color,
                       lg.prefix_html AS last_poster_prefix,
                       lg.suffix_html AS last_poster_suffix,
                       lg.name AS last_poster_group
                FROM vf_forums f
                LEFT JOIN vf_topics t ON t.id = f.last_topic_id
                LEFT JOIN vf_members lm ON lm.id = t.last_poster_id
                LEFT JOIN vf_members lmn ON (t.last_poster_id IS NULL OR t.last_poster_id = 0) AND lmn.username = t.last_poster_name
                LEFT JOIN vf_groups lg ON lg.id = COALESCE(lm.primary_group_id, lmn.primary_group_id)
                ORDER BY f.position ASC, f.id ASC';
        $this->allOrderedCache = $this->db->query($sql)->fetchAll();
        return $this->allOrderedCache;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM vf_forums WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function childrenOf(int $parentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*,
                    t.id AS last_topic_id,
                    t.title AS last_topic_title,
                    t.slug AS last_topic_slug,
                    t.last_post_at AS last_post_at,
                    COALESCE(NULLIF(t.last_poster_id, 0), lmn.id) AS last_poster_id,
                    COALESCE(lm.username, lmn.username, t.last_poster_name) AS last_poster_name,
                    COALESCE(lm.slug, lmn.slug) AS last_poster_slug,
                    COALESCE(lm.avatar_url, lmn.avatar_url) AS last_poster_avatar,
                    lg.color_hex AS last_poster_color,
                    lg.prefix_html AS last_poster_prefix,
                    lg.suffix_html AS last_poster_suffix,
                    lg.name AS last_poster_group
             FROM vf_forums f
             LEFT JOIN vf_topics t ON t.id = f.last_topic_id
             LEFT JOIN vf_members lm ON lm.id = t.last_poster_id
             LEFT JOIN vf_members lmn ON (t.last_poster_id IS NULL OR t.last_poster_id = 0) AND lmn.username = t.last_poster_name
             LEFT JOIN vf_groups lg ON lg.id = COALESCE(lm.primary_group_id, lmn.primary_group_id)
             WHERE f.parent_id = :parent_id
             ORDER BY f.position ASC, f.id ASC'
        );
        $stmt->execute(['parent_id' => $parentId]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function searchForums(string $query, int $limit = 4): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            $stmt = $this->db->prepare(
                'SELECT id, name, slug, topics_count
                 FROM vf_forums
                 WHERE is_category = 0
                 ORDER BY topics_count DESC, id ASC
                 LIMIT :limit'
            );
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $trimmed) . '%';
        $stmt = $this->db->prepare(
            'SELECT id, name, slug, topics_count
             FROM vf_forums
             WHERE is_category = 0 AND name LIKE :q
             ORDER BY topics_count DESC, id ASC
             LIMIT :limit'
        );
        $stmt->bindValue('q', $like);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function isFollowing(int $forumId, int $memberId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM vf_follows WHERE target_type = "forum" AND target_id = :fid AND member_id = :mid LIMIT 1'
        );
        $stmt->execute(['fid' => $forumId, 'mid' => $memberId]);
        return (bool) $stmt->fetchColumn();
    }

    public function followCount(int $forumId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM vf_follows WHERE target_type = "forum" AND target_id = :fid'
        );
        $stmt->execute(['fid' => $forumId]);
        return (int) $stmt->fetchColumn();
    }

    public function toggleFollow(int $forumId, int $memberId): bool
    {
        if ($this->isFollowing($forumId, $memberId)) {
            $this->db->prepare(
                'DELETE FROM vf_follows WHERE target_type = "forum" AND target_id = :fid AND member_id = :mid'
            )->execute(['fid' => $forumId, 'mid' => $memberId]);
            return false;
        }
        $this->db->prepare(
            'INSERT INTO vf_follows (target_type, target_id, member_id, created_at) VALUES ("forum", :fid, :mid, :at)'
        )->execute(['fid' => $forumId, 'mid' => $memberId, 'at' => time()]);
        return true;
    }

    /** @return int[] */
    public function followerIds(int $forumId): array
    {
        $stmt = $this->db->prepare(
            'SELECT member_id FROM vf_follows WHERE target_type = "forum" AND target_id = :fid'
        );
        $stmt->execute(['fid' => $forumId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

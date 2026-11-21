<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class ModRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function createReport(
        int $postId,
        int $topicId,
        int $reporterId,
        string $reporterName,
        string $category,
        string $reason
    ): int {
        $allowedCategories = ['spam', 'harassment', 'offtopic', 'other'];
        $safeCategory = in_array($category, $allowedCategories, true) ? $category : 'other';

        $stmt = $this->db->prepare(
            'INSERT INTO vf_reports
             (post_id, topic_id, reporter_id, reporter_name, category, reason, status, created_at)
             VALUES (:pid, :tid, :rid, :rname, :cat, :reason, "open", :at)'
        );
        $stmt->execute([
            'pid'    => $postId,
            'tid'    => $topicId,
            'rid'    => $reporterId,
            'rname'  => $reporterName,
            'cat'    => $safeCategory,
            'reason' => mb_substr(trim($reason), 0, 500),
            'at'     => time(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param int[]|null $allowedForumIds null = globalny dostęp, array = tylko nadzorowane działy
     * @return array<int, array<string, mixed>>
     */
    public function listReports(?string $status = null, int $limit = 60, ?array $allowedForumIds = null): array
    {
        if ($allowedForumIds === []) {
            return [];
        }

        $sql = 'SELECT r.*, t.title AS topic_title, t.slug AS topic_slug, t.forum_id AS forum_id,
                       p.content_html AS post_html,
                       COALESCE(NULLIF(p.author_id, 0), pmn.id) AS post_author_id,
                       COALESCE(pm.username, pmn.username, p.author_name) AS post_author_name,
                       p.is_approved AS post_is_approved,
                       COALESCE(rm.slug, rmn.slug) AS reporter_slug,
                       rg.color_hex AS reporter_color, rg.prefix_html AS reporter_prefix,
                       rg.suffix_html AS reporter_suffix, rg.name AS reporter_group,
                       COALESCE(pm.slug, pmn.slug) AS post_author_slug,
                       pg.color_hex AS post_author_color, pg.prefix_html AS post_author_prefix,
                       pg.suffix_html AS post_author_suffix, pg.name AS post_author_group
                FROM vf_reports r
                INNER JOIN vf_topics t ON t.id = r.topic_id
                INNER JOIN vf_posts p ON p.id = r.post_id
                LEFT JOIN vf_members rm ON rm.id = r.reporter_id
                LEFT JOIN vf_members rmn ON (r.reporter_id IS NULL OR r.reporter_id = 0) AND rmn.username = r.reporter_name
                LEFT JOIN vf_groups rg ON rg.id = COALESCE(rm.primary_group_id, rmn.primary_group_id)
                LEFT JOIN vf_members pm ON pm.id = p.author_id
                LEFT JOIN vf_members pmn ON (p.author_id IS NULL OR p.author_id = 0) AND pmn.username = p.author_name
                LEFT JOIN vf_groups pg ON pg.id = COALESCE(pm.primary_group_id, pmn.primary_group_id)';

        $where = [];
        $params = [];
        if ($status !== null && in_array($status, ['open', 'resolved', 'dismissed'], true)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($allowedForumIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedForumIds), '?'));
            $where[] = "t.forum_id IN ({$placeholders})";
            foreach ($allowedForumIds as $fid) {
                $params[] = (int) $fid;
            }
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY FIELD(r.status, "open", "resolved", "dismissed"), r.created_at DESC LIMIT ?';

        $stmt = $this->db->prepare($sql);
        $pos = 1;
        foreach ($params as $val) {
            $stmt->bindValue($pos++, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue($pos, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findReportById(int $reportId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM vf_reports WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $reportId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function resolveReport(
        int $reportId,
        string $status,
        int $moderatorId,
        string $moderatorName,
        string $note
    ): void {
        $safeStatus = in_array($status, ['resolved', 'dismissed'], true) ? $status : 'resolved';
        $this->db->prepare(
            'UPDATE vf_reports
             SET status = :status, handled_by_id = :mid, handled_by_name = :mname,
                 resolution_note = :note, resolved_at = :at
             WHERE id = :id'
        )->execute([
            'status' => $safeStatus,
            'mid'    => $moderatorId,
            'mname'  => $moderatorName,
            'note'   => mb_substr(trim($note), 0, 250),
            'at'     => time(),
            'id'     => $reportId,
        ]);
    }

    /**
     * @param int[]|null $allowedForumIds
     * @return array<int, array<string, mixed>>
     */
    public function hiddenPosts(int $limit = 50, ?array $allowedForumIds = null): array
    {
        if ($allowedForumIds === []) {
            return [];
        }
        $forumFilter = '';
        if ($allowedForumIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedForumIds), '?'));
            $forumFilter = " AND t.forum_id IN ({$placeholders})";
        }
        $stmt = $this->db->prepare(
            "SELECT p.*, t.title AS topic_title, t.slug AS topic_slug, t.forum_id AS forum_id,
                    COALESCE(NULLIF(p.author_id, 0), mn.id) AS author_id,
                    COALESCE(m.username, mn.username, p.author_name) AS author_name,
                    COALESCE(m.slug, mn.slug) AS author_slug,
                    g.color_hex AS author_group_color, g.prefix_html AS author_group_prefix,
                    g.suffix_html AS author_group_suffix, g.name AS author_group_name
             FROM vf_posts p
             INNER JOIN vf_topics t ON t.id = p.topic_id
             LEFT JOIN vf_members m ON m.id = p.author_id
             LEFT JOIN vf_members mn ON (p.author_id IS NULL OR p.author_id = 0) AND mn.username = p.author_name
             LEFT JOIN vf_groups g ON g.id = COALESCE(m.primary_group_id, mn.primary_group_id)
             WHERE p.is_approved = 0{$forumFilter}
             ORDER BY p.created_at DESC
             LIMIT ?"
        );
        $pos = 1;
        if ($allowedForumIds !== null) {
            foreach ($allowedForumIds as $fid) {
                $stmt->bindValue($pos++, (int) $fid, PDO::PARAM_INT);
            }
        }
        $stmt->bindValue($pos, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @param int[]|null $allowedForumIds
     * @return array<int, array<string, mixed>>
     */
    public function recentTopicsForMod(int $limit = 40, ?array $allowedForumIds = null): array
    {
        if ($allowedForumIds === []) {
            return [];
        }
        $where = '';
        if ($allowedForumIds !== null) {
            $placeholders = implode(',', array_fill(0, count($allowedForumIds), '?'));
            $where = " WHERE t.forum_id IN ({$placeholders})";
        }
        $stmt = $this->db->prepare(
            "SELECT t.*, f.name AS forum_name,
                    COALESCE(NULLIF(t.author_id, 0), amn.id) AS author_id,
                    COALESCE(am.username, amn.username, t.author_name) AS author_name,
                    COALESCE(am.slug, amn.slug) AS author_slug,
                    ag.color_hex AS author_group_color, ag.prefix_html AS author_group_prefix,
                    ag.suffix_html AS author_group_suffix, ag.name AS author_group_name
             FROM vf_topics t
             INNER JOIN vf_forums f ON f.id = t.forum_id
             LEFT JOIN vf_members am ON am.id = t.author_id
             LEFT JOIN vf_members amn ON (t.author_id IS NULL OR t.author_id = 0) AND amn.username = t.author_name
             LEFT JOIN vf_groups ag ON ag.id = COALESCE(am.primary_group_id, amn.primary_group_id){$where}
             ORDER BY t.last_post_at DESC
             LIMIT ?"
        );
        $pos = 1;
        if ($allowedForumIds !== null) {
            foreach ($allowedForumIds as $fid) {
                $stmt->bindValue($pos++, (int) $fid, PDO::PARAM_INT);
            }
        }
        $stmt->bindValue($pos, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function issueWarning(
        int $memberId,
        int $points,
        bool $applyBan,
        string $banReason,
        ?int $bannedUntil
    ): void {
        $stmt = $this->db->prepare('SELECT warning_points FROM vf_members WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $memberId]);
        $current = (int) $stmt->fetchColumn();
        $nextPoints = max(0, min(10, $current + $points));

        if ($applyBan) {
            $this->db->prepare(
                'UPDATE vf_members
                 SET warning_points = :wp, is_banned = 1, ban_reason = :reason, banned_until = :until
                 WHERE id = :id'
            )->execute([
                'wp'     => $nextPoints,
                'reason' => mb_substr(trim($banReason), 0, 255),
                'until'  => $bannedUntil,
                'id'     => $memberId,
            ]);
        } else {
            $this->db->prepare('UPDATE vf_members SET warning_points = :wp WHERE id = :id')
                ->execute(['wp' => $nextPoints, 'id' => $memberId]);
        }
    }

    public function clearSanctions(int $memberId): void
    {
        $this->db->prepare(
            'UPDATE vf_members
             SET warning_points = 0, is_banned = 0, ban_reason = NULL, banned_until = NULL
             WHERE id = :id'
        )->execute(['id' => $memberId]);
    }

    public function logAction(
        int $moderatorId,
        string $moderatorName,
        string $action,
        string $targetType,
        int $targetId,
        string $details
    ): void {
        $this->db->prepare(
            'INSERT INTO vf_mod_logs
             (moderator_id, moderator_name, action, target_type, target_id, details, created_at)
             VALUES (:mid, :mname, :act, :ttype, :tid, :det, :at)'
        )->execute([
            'mid'   => $moderatorId,
            'mname' => $moderatorName,
            'act'   => mb_substr($action, 0, 64),
            'ttype' => $targetType,
            'tid'   => $targetId,
            'det'   => mb_substr($details, 0, 255),
            'at'    => time(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recentLogs(int $limit = 60): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*,
                    COALESCE(m.slug, mn.slug) AS moderator_slug,
                    g.color_hex AS moderator_color, g.prefix_html AS moderator_prefix,
                    g.suffix_html AS moderator_suffix, g.name AS moderator_group
             FROM vf_mod_logs l
             LEFT JOIN vf_members m ON m.id = l.moderator_id
             LEFT JOIN vf_members mn ON (l.moderator_id IS NULL OR l.moderator_id = 0) AND mn.username = l.moderator_name
             LEFT JOIN vf_groups g ON g.id = COALESCE(m.primary_group_id, mn.primary_group_id)
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

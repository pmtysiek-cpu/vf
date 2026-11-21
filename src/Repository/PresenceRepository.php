<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

/**
 * Zarządza obecnością użytkowników i gości w czasie rzeczywistym (strona główna, działy, konkretne tematy).
 */
final class PresenceRepository
{
    public const ACTIVE_WINDOW_SECONDS = 1200;

    public function __construct(private readonly PDO $db)
    {
    }

    public function resolveSessionKey(): string
    {
        $sid = session_id();
        if ($sid === '') {
            $sid = bin2hex(random_bytes(16));
        }
        return hash('sha256', $sid);
    }

    public function touch(
        string $sessionKey,
        ?array $user,
        string $pageType = 'home',
        ?int $topicId = null,
        ?int $forumId = null
    ): void {
        $now = time();
        $cleanType = preg_replace('/[^a-z0-9_-]/i', '', $pageType) ?: 'home';
        $cleanType = mb_substr($cleanType, 0, 24);
        $tid = ($topicId !== null && $topicId > 0) ? $topicId : null;
        $fid = ($forumId !== null && $forumId > 0) ? $forumId : null;

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO vf_presence
                 (session_key, member_id, username, slug, avatar_url, group_color, group_name, page_type, topic_id, forum_id, updated_at)
                 VALUES (:sk, :mid, :uname, :slug, :av, :gcol, :gname, :ptype, :tid, :fid, :now)
                 ON DUPLICATE KEY UPDATE
                   member_id = VALUES(member_id),
                   username = VALUES(username),
                   slug = VALUES(slug),
                   avatar_url = VALUES(avatar_url),
                   group_color = VALUES(group_color),
                   group_name = VALUES(group_name),
                   page_type = VALUES(page_type),
                   topic_id = VALUES(topic_id),
                   forum_id = VALUES(forum_id),
                   updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'sk'    => $sessionKey,
                'mid'   => $user ? (int) $user['id'] : null,
                'uname' => $user ? mb_substr((string) $user['username'], 0, 120) : null,
                'slug'  => $user ? mb_substr((string) $user['slug'], 0, 160) : null,
                'av'    => $user && !empty($user['avatar_url']) ? mb_substr((string) $user['avatar_url'], 0, 500) : null,
                'gcol'  => $user ? (string) ($user['color_hex'] ?? '#2563eb') : null,
                'gname' => $user ? mb_substr((string) ($user['group_name'] ?? ''), 0, 120) : null,
                'ptype' => $cleanType,
                'tid'   => $tid,
                'fid'   => $fid,
                'now'   => $now,
            ]);

            if ($user) {
                $lastSync = (int) ($_SESSION['_vf_presence_member_ts'] ?? 0);
                if (($now - $lastSync) >= 20) {
                    $this->db->prepare('UPDATE vf_members SET last_activity_at = :now WHERE id = :id')
                        ->execute(['now' => $now, 'id' => (int) $user['id']]);
                    $_SESSION['_vf_presence_member_ts'] = $now;
                }
            }

            if (mt_rand(1, 15) === 1) {
                $this->pruneExpired(self::ACTIVE_WINDOW_SECONDS);
            }
        } catch (\Throwable) {
            // Nie przerywamy żądania jeśli tabela obecności jest w trakcie aktualizacji
        }
    }

    public function leave(string $sessionKey): void
    {
        try {
            $this->db->prepare('DELETE FROM vf_presence WHERE session_key = :sk')
                ->execute(['sk' => $sessionKey]);
        } catch (\Throwable) {
            // Ignorujemy
        }
    }

    public function pruneExpired(int $windowSeconds = self::ACTIVE_WINDOW_SECONDS): void
    {
        $cutoff = time() - $windowSeconds;
        $this->db->prepare('DELETE FROM vf_presence WHERE updated_at < :cutoff')
            ->execute(['cutoff' => $cutoff]);
    }

    /**
     * @return array{
     *   online_members: array<int, array<string, mixed>>,
     *   online_members_count: int,
     *   online_guests_count: int,
     *   topic_members: array<int, array<string, mixed>>,
     *   topic_guests_count: int
     * }
     */
    public function activeSnapshot(?int $topicId = null, int $windowSeconds = self::ACTIVE_WINDOW_SECONDS, int $limit = 16): array
    {
        $cutoff = time() - $windowSeconds;
        $onlineMembers = [];
        $onlineGuestsCount = 0;
        $topicMembers = [];
        $topicGuestsCount = 0;

        try {
            $stmt = $this->db->prepare(
                'SELECT p.member_id AS id,
                        COALESCE(pm.username, p.username) AS username,
                        COALESCE(pm.slug, p.slug) AS slug,
                        COALESCE(pm.avatar_url, p.avatar_url) AS avatar_url,
                        COALESCE(pg.color_hex, p.group_color) AS group_color,
                        COALESCE(pg.name, p.group_name) AS group_name,
                        COALESCE(pg.prefix_html, \'\') AS group_prefix,
                        COALESCE(pg.suffix_html, \'\') AS group_suffix,
                        p.page_type, p.topic_id, p.forum_id, MAX(p.updated_at) AS last_seen,
                        t.title AS topic_title, t.slug AS topic_slug
                 FROM vf_presence p
                 LEFT JOIN vf_members pm ON pm.id = p.member_id
                 LEFT JOIN vf_groups pg ON pg.id = pm.primary_group_id
                 LEFT JOIN vf_topics t ON t.id = p.topic_id
                 WHERE p.updated_at >= :cutoff AND p.member_id IS NOT NULL
                   AND (pm.id IS NULL OR pm.is_banned = 0)
                 GROUP BY p.member_id, username, slug, avatar_url, group_color, group_name, group_prefix, group_suffix, p.page_type, p.topic_id, p.forum_id, t.title, t.slug
                 ORDER BY last_seen DESC
                 LIMIT :lim'
            );
            $stmt->bindValue('cutoff', $cutoff, PDO::PARAM_INT);
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            $seenIds = [];
            foreach ($rows as $row) {
                $mid = (int) ($row['id'] ?? 0);
                if ($mid <= 0 || isset($seenIds[$mid])) {
                    continue;
                }
                $seenIds[$mid] = true;
                $onlineMembers[] = [
                    'id'           => $mid,
                    'username'     => (string) $row['username'],
                    'slug'         => (string) $row['slug'],
                    'avatar_url'   => $row['avatar_url'],
                    'group_color'  => (string) ($row['group_color'] ?: '#2563eb'),
                    'group_name'   => (string) ($row['group_name'] ?? ''),
                    'group_prefix' => (string) ($row['group_prefix'] ?? ''),
                    'group_suffix' => (string) ($row['group_suffix'] ?? ''),
                    'page_type'    => (string) $row['page_type'],
                    'topic_id'     => $row['topic_id'] !== null ? (int) $row['topic_id'] : null,
                    'topic_title'  => $row['topic_title'] !== null ? (string) $row['topic_title'] : null,
                    'last_seen'    => (int) $row['last_seen'],
                ];
            }

            // Fallback z vf_members.last_activity_at dla sesji jeszcze przed pierwszym pingiem JS
            $mStmt = $this->db->prepare(
                'SELECT m.id, m.username, m.slug, m.avatar_url, m.last_activity_at,
                        g.color_hex AS group_color, g.name AS group_name,
                        g.prefix_html AS group_prefix, g.suffix_html AS group_suffix
                 FROM vf_members m
                 INNER JOIN vf_groups g ON g.id = m.primary_group_id
                 WHERE m.is_banned = 0 AND m.last_activity_at IS NOT NULL AND m.last_activity_at >= :cutoff
                 ORDER BY m.last_activity_at DESC
                 LIMIT :lim'
            );
            $mStmt->bindValue('cutoff', $cutoff, PDO::PARAM_INT);
            $mStmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $mStmt->execute();
            foreach ($mStmt->fetchAll() as $mRow) {
                $mid = (int) ($mRow['id'] ?? 0);
                if ($mid <= 0 || isset($seenIds[$mid])) {
                    continue;
                }
                $seenIds[$mid] = true;
                $onlineMembers[] = [
                    'id'           => $mid,
                    'username'     => (string) $mRow['username'],
                    'slug'         => (string) $mRow['slug'],
                    'avatar_url'   => $mRow['avatar_url'],
                    'group_color'  => (string) ($mRow['group_color'] ?: '#2563eb'),
                    'group_name'   => (string) ($mRow['group_name'] ?? ''),
                    'group_prefix' => (string) ($mRow['group_prefix'] ?? ''),
                    'group_suffix' => (string) ($mRow['group_suffix'] ?? ''),
                    'page_type'    => 'home',
                    'topic_id'     => null,
                    'topic_title'  => null,
                    'last_seen'    => (int) $mRow['last_activity_at'],
                ];
            }

            $gStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM vf_presence WHERE updated_at >= :cutoff AND member_id IS NULL'
            );
            $gStmt->execute(['cutoff' => $cutoff]);
            $onlineGuestsCount = (int) $gStmt->fetchColumn();

            if ($topicId !== null && $topicId > 0) {
                $tStmt = $this->db->prepare(
                    'SELECT p.member_id AS id,
                            COALESCE(pm.username, p.username) AS username,
                            COALESCE(pm.slug, p.slug) AS slug,
                            COALESCE(pm.avatar_url, p.avatar_url) AS avatar_url,
                            COALESCE(pg.color_hex, p.group_color) AS group_color,
                            COALESCE(pg.name, p.group_name) AS group_name,
                            COALESCE(pg.prefix_html, \'\') AS group_prefix,
                            COALESCE(pg.suffix_html, \'\') AS group_suffix,
                            MAX(p.updated_at) AS last_seen
                     FROM vf_presence p
                     LEFT JOIN vf_members pm ON pm.id = p.member_id
                     LEFT JOIN vf_groups pg ON pg.id = pm.primary_group_id
                     WHERE p.topic_id = :tid AND p.updated_at >= :cutoff AND p.member_id IS NOT NULL
                       AND (pm.id IS NULL OR pm.is_banned = 0)
                     GROUP BY p.member_id, username, slug, avatar_url, group_color, group_name, group_prefix, group_suffix
                     ORDER BY last_seen DESC
                     LIMIT 20'
                );
                $tStmt->execute(['tid' => $topicId, 'cutoff' => $cutoff]);
                foreach ($tStmt->fetchAll() as $tRow) {
                    $topicMembers[] = [
                        'id'           => (int) $tRow['id'],
                        'username'     => (string) $tRow['username'],
                        'slug'         => (string) $tRow['slug'],
                        'avatar_url'   => $tRow['avatar_url'],
                        'group_color'  => (string) ($tRow['group_color'] ?: '#2563eb'),
                        'group_name'   => (string) ($tRow['group_name'] ?? ''),
                        'group_prefix' => (string) ($tRow['group_prefix'] ?? ''),
                        'group_suffix' => (string) ($tRow['group_suffix'] ?? ''),
                        'last_seen'    => (int) $tRow['last_seen'],
                    ];
                }

                $tgStmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM vf_presence WHERE topic_id = :tid AND updated_at >= :cutoff AND member_id IS NULL'
                );
                $tgStmt->execute(['tid' => $topicId, 'cutoff' => $cutoff]);
                $topicGuestsCount = (int) $tgStmt->fetchColumn();
            }
        } catch (\Throwable) {
            // Bezpieczny fallback
        }

        return [
            'online_members'       => array_slice($onlineMembers, 0, $limit),
            'online_members_count' => count($onlineMembers),
            'online_guests_count'  => $onlineGuestsCount,
            'topic_members'        => $topicMembers,
            'topic_guests_count'   => $topicGuestsCount,
        ];
    }
}

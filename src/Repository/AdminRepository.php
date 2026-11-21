<?php

declare(strict_types=1);

namespace VeloForum\Repository;

use PDO;

final class AdminRepository
{
    /** @var array<string, string>|null */
    private ?array $settingsCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $activeAnnouncementsCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $prefixesCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $profileFieldsCache = null;

    public function __construct(private readonly PDO $db)
    {
    }

    public function dashboardStats(): array
    {
        return [
            'members'   => (int) $this->db->query('SELECT COUNT(*) FROM vf_members')->fetchColumn(),
            'banned'    => (int) $this->db->query('SELECT COUNT(*) FROM vf_members WHERE is_banned = 1')->fetchColumn(),
            'forums'    => (int) $this->db->query('SELECT COUNT(*) FROM vf_forums')->fetchColumn(),
            'topics'    => (int) $this->db->query('SELECT COUNT(*) FROM vf_topics')->fetchColumn(),
            'posts'     => (int) $this->db->query('SELECT COUNT(*) FROM vf_posts')->fetchColumn(),
            'reactions' => (int) $this->db->query('SELECT COUNT(*) FROM vf_post_reactions')->fetchColumn(),
        ];
    }

    /** @return array<string, string> */
    public function settings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }
        try {
            $rows = $this->db->query('SELECT setting_key, setting_value FROM vf_settings')->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r['setting_key']] = (string) $r['setting_value'];
            }
            $this->settingsCache = $out;
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public function saveSetting(string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO vf_settings (setting_key, setting_value, updated_at)
             VALUES (:k, :v, :at)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)'
        );
        $stmt->execute(['k' => $key, 'v' => $value, 'at' => time()]);
        $this->settingsCache = null;
    }

    /** @return array<int, array<string, mixed>> */
    public function migrationLogs(int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT * FROM vf_migration_logs ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function saveForum(
        ?int $id,
        ?int $parentId,
        bool $isCategory,
        string $name,
        string $description,
        string $seoTitle,
        string $seoDescription,
        int $position,
        string $redirectUrl = '',
        bool $isLocked = false,
        bool $allowPolls = true,
        bool $allowBestAnswer = true,
        bool $requireApproval = false
    ): int {
        $slug = $this->slugify($name);
        $depth = 0;
        $parentPath = '';

        if ($id !== null && $id > 0 && $parentId !== null && $parentId > 0) {
            if ($parentId === $id || $this->wouldCreateCycle($id, $parentId)) {
                $existingRow = $this->db->prepare('SELECT parent_id FROM vf_forums WHERE id = :id LIMIT 1');
                $existingRow->execute(['id' => $id]);
                $prevParent = $existingRow->fetchColumn();
                $parentId = $prevParent !== false && $prevParent !== null ? (int) $prevParent : null;
            }
        }

        if ($parentId !== null && $parentId > 0) {
            $pStmt = $this->db->prepare('SELECT materialized_path, depth FROM vf_forums WHERE id = :pid LIMIT 1');
            $pStmt->execute(['pid' => $parentId]);
            $parent = $pStmt->fetch();
            if ($parent) {
                $depth = (int) $parent['depth'] + 1;
                $parentPath = (string) $parent['materialized_path'];
            } else {
                $parentId = null;
            }
        } else {
            $parentId = null;
        }

        $safeRedirect = trim($redirectUrl);
        if ($safeRedirect !== '' && !preg_match('#^(https?://|/)#i', $safeRedirect)) {
            $safeRedirect = '';
        }

        if ($id !== null && $id > 0) {
            $path = $parentPath !== '' ? "{$parentPath}/{$id}" : (string) $id;
            $stmt = $this->db->prepare(
                'UPDATE vf_forums
                 SET parent_id = :pid, materialized_path = :path, depth = :depth, is_category = :cat,
                     name = :name, slug = :slug, description = :desc, seo_title = :stitle,
                     seo_description = :sdesc, position = :pos, redirect_url = :redir,
                     is_locked = :locked, allow_polls = :polls, allow_best_answer = :best,
                     require_approval = :req_app
                 WHERE id = :id'
            );
            $stmt->execute([
                'pid'     => $parentId,
                'path'    => $path,
                'depth'   => $depth,
                'cat'     => $isCategory ? 1 : 0,
                'name'    => $name,
                'slug'    => $slug,
                'desc'    => $description,
                'stitle'  => $seoTitle !== '' ? $seoTitle : null,
                'sdesc'   => $seoDescription !== '' ? $seoDescription : null,
                'pos'     => $position,
                'redir'   => $safeRedirect !== '' ? $safeRedirect : null,
                'locked'  => $isLocked ? 1 : 0,
                'polls'   => $allowPolls ? 1 : 0,
                'best'    => $allowBestAnswer ? 1 : 0,
                'req_app' => $requireApproval ? 1 : 0,
                'id'      => $id,
            ]);
            $this->recomputeAllForumPaths();
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO vf_forums
             (parent_id, materialized_path, depth, is_category, name, slug, description, seo_title, seo_description, position,
              redirect_url, is_locked, allow_polls, allow_best_answer, require_approval)
             VALUES (:pid, "", :depth, :cat, :name, :slug, :desc, :stitle, :sdesc, :pos, :redir, :locked, :polls, :best, :req_app)'
        );
        $stmt->execute([
            'pid'     => $parentId,
            'depth'   => $depth,
            'cat'     => $isCategory ? 1 : 0,
            'name'    => $name,
            'slug'    => $slug,
            'desc'    => $description,
            'stitle'  => $seoTitle !== '' ? $seoTitle : null,
            'sdesc'   => $seoDescription !== '' ? $seoDescription : null,
            'pos'     => $position,
            'redir'   => $safeRedirect !== '' ? $safeRedirect : null,
            'locked'  => $isLocked ? 1 : 0,
            'polls'   => $allowPolls ? 1 : 0,
            'best'    => $allowBestAnswer ? 1 : 0,
            'req_app' => $requireApproval ? 1 : 0,
        ]);
        $newId = (int) $this->db->lastInsertId();
        $path = $parentPath !== '' ? "{$parentPath}/{$newId}" : (string) $newId;
        $this->db->prepare('UPDATE vf_forums SET materialized_path = :path WHERE id = :id')
            ->execute(['path' => $path, 'id' => $newId]);

        return $newId;
    }

    public function reorderForum(int $id, string $direction): void
    {
        $rowStmt = $this->db->prepare('SELECT id, parent_id, position FROM vf_forums WHERE id = :id LIMIT 1');
        $rowStmt->execute(['id' => $id]);
        $target = $rowStmt->fetch();
        if (!$target) {
            return;
        }

        $parentId = $target['parent_id'] !== null ? (int) $target['parent_id'] : null;
        if ($parentId === null) {
            $sibStmt = $this->db->query('SELECT id, position FROM vf_forums WHERE parent_id IS NULL ORDER BY position ASC, id ASC');
        } else {
            $sibStmt = $this->db->prepare('SELECT id, position FROM vf_forums WHERE parent_id = :pid ORDER BY position ASC, id ASC');
            $sibStmt->execute(['pid' => $parentId]);
        }
        $siblings = $sibStmt->fetchAll();
        $idx = null;
        foreach ($siblings as $i => $s) {
            if ((int) $s['id'] === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return;
        }

        $swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
        if (!isset($siblings[$swapIdx])) {
            return;
        }

        $tmp = $siblings[$idx];
        $siblings[$idx] = $siblings[$swapIdx];
        $siblings[$swapIdx] = $tmp;

        $upd = $this->db->prepare('UPDATE vf_forums SET position = :pos WHERE id = :id');
        foreach ($siblings as $pos => $s) {
            $upd->execute(['pos' => $pos + 1, 'id' => (int) $s['id']]);
        }
    }

    public function deleteForum(int $id): void
    {
        $this->db->prepare('DELETE FROM vf_forums WHERE id = :id')->execute(['id' => $id]);
    }

    private function wouldCreateCycle(int $forumId, int $candidateParentId): bool
    {
        $rows = $this->db->query('SELECT id, parent_id FROM vf_forums')->fetchAll();
        $parentMap = [];
        foreach ($rows as $r) {
            $parentMap[(int) $r['id']] = $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
        }
        $curr = $candidateParentId;
        $guard = 0;
        while ($curr !== null && $guard < 32) {
            if ($curr === $forumId) {
                return true;
            }
            $curr = $parentMap[$curr] ?? null;
            $guard++;
        }
        return false;
    }

    private function recomputeAllForumPaths(): void
    {
        $rows = $this->db->query('SELECT id, parent_id FROM vf_forums')->fetchAll();
        $parentMap = [];
        foreach ($rows as $r) {
            $parentMap[(int) $r['id']] = $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
        }
        $upd = $this->db->prepare('UPDATE vf_forums SET materialized_path = :path, depth = :depth WHERE id = :id');
        foreach (array_keys($parentMap) as $fid) {
            $chain = [];
            $curr = $fid;
            $visited = [];
            while ($curr !== null && !isset($visited[$curr]) && isset($parentMap[$curr]) && count($chain) < 16) {
                $visited[$curr] = true;
                array_unshift($chain, (string) $curr);
                $curr = $parentMap[$curr];
            }
            $upd->execute([
                'path'  => implode('/', $chain),
                'depth' => max(0, count($chain) - 1),
                'id'    => $fid,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listMembers(string $query = '', int $limit = 50, string $filter = 'all'): array
    {
        $where = [];
        $params = [];
        if ($query !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            $where[] = '(m.username LIKE :q1 OR m.email LIKE :q2)';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if ($filter === 'staff') {
            $where[] = '(g.is_admin = 1 OR g.is_moderator = 1)';
        } elseif ($filter === 'warned') {
            $where[] = 'm.warning_points > 0';
        } elseif ($filter === 'banned') {
            $where[] = 'm.is_banned = 1';
        }

        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->db->prepare(
            "SELECT m.*, g.name AS group_name, g.color_hex,
                    g.prefix_html AS group_prefix, g.suffix_html AS group_suffix,
                    g.is_admin AS group_is_admin, g.is_moderator AS group_is_moderator
             FROM vf_members m
             INNER JOIN vf_groups g ON g.id = m.primary_group_id{$whereSql}
             ORDER BY m.id DESC
             LIMIT :limit"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array{all: int, staff: int, warned: int, banned: int} */
    public function memberStatusCounts(): array
    {
        return [
            'all'    => (int) $this->db->query('SELECT COUNT(*) FROM vf_members')->fetchColumn(),
            'staff'  => (int) $this->db->query('SELECT COUNT(*) FROM vf_members m INNER JOIN vf_groups g ON g.id = m.primary_group_id WHERE g.is_admin = 1 OR g.is_moderator = 1')->fetchColumn(),
            'warned' => (int) $this->db->query('SELECT COUNT(*) FROM vf_members WHERE warning_points > 0')->fetchColumn(),
            'banned' => (int) $this->db->query('SELECT COUNT(*) FROM vf_members WHERE is_banned = 1')->fetchColumn(),
        ];
    }

    public function findMemberById(int $memberId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM vf_members WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $memberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<int, int> $secondaryGroupIds
     * @param array<string, string>|null $customFields
     */
    public function updateMemberAdvanced(
        int $memberId,
        string $username,
        string $email,
        int $groupId,
        array $secondaryGroupIds,
        string $memberTitle,
        string $avatarUrl,
        string $bio,
        string $signature,
        int $postsCount,
        int $reputationPoints,
        int $warningPoints,
        bool $isBanned,
        string $banReason,
        ?int $bannedUntil,
        ?string $newPlainPassword = null,
        ?array $customFields = null,
        ?string $coverUrl = null,
        ?string $musicUrl = null
    ): void {
        $sql = 'UPDATE vf_members
                SET username = :uname, slug = :slug, email = :email,
                    primary_group_id = :gid, secondary_group_ids = :sgids,
                    member_title = :title, avatar_url = :avatar, bio = :bio, signature = :sig,
                    posts_count = :posts, reputation_points = :rep, warning_points = :warn,
                    is_banned = :banned, ban_reason = :breason, banned_until = :buntil';

        $params = [
            'uname'   => $username,
            'slug'    => $this->slugify($username),
            'email'   => $email,
            'gid'     => $groupId,
            'sgids'   => json_encode(array_values(array_unique(array_map('intval', $secondaryGroupIds)))),
            'title'   => $memberTitle !== '' ? $memberTitle : null,
            'avatar'  => $avatarUrl !== '' ? $avatarUrl : null,
            'bio'     => $bio !== '' ? $bio : null,
            'sig'     => $signature !== '' ? $signature : null,
            'posts'   => max(0, $postsCount),
            'rep'     => $reputationPoints,
            'warn'    => max(0, min(10, $warningPoints)),
            'banned'  => $isBanned ? 1 : 0,
            'breason' => $isBanned && $banReason !== '' ? $banReason : null,
            'buntil'  => $isBanned ? $bannedUntil : null,
            'id'      => $memberId,
        ];

        if ($coverUrl !== null) {
            $sql .= ', cover_url = :cover';
            $params['cover'] = trim($coverUrl) !== '' ? mb_substr(trim($coverUrl), 0, 500) : null;
        }
        if ($musicUrl !== null) {
            $sql .= ', music_url = :music';
            $params['music'] = trim($musicUrl) !== '' ? mb_substr(trim($musicUrl), 0, 500) : null;
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
            $sql .= ', custom_fields = :cfields';
            $params['cfields'] = $cleanFields !== [] ? json_encode($cleanFields, JSON_UNESCAPED_UNICODE) : null;
        }

        if ($newPlainPassword !== null && $newPlainPassword !== '') {
            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
            $algoName = defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt';
            $sql .= ', password_hash = :phash, legacy_salt = NULL, legacy_algo = "' . $algoName . '"';
            $params['phash'] = password_hash($newPlainPassword, $algo);
        }

        $sql .= ' WHERE id = :id';
        $this->db->prepare($sql)->execute($params);
    }

    public function createMember(
        string $username,
        string $email,
        string $plainPassword,
        int $groupId,
        string $memberTitle
    ): int {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $algoName = defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt';
        $stmt = $this->db->prepare(
            'INSERT INTO vf_members (username, slug, email, password_hash, legacy_algo, primary_group_id, member_title, joined_at)
             VALUES (:uname, :slug, :email, :phash, :algo, :gid, :title, :joined)'
        );
        $stmt->execute([
            'uname'  => $username,
            'slug'   => $this->slugify($username),
            'email'  => $email,
            'phash'  => password_hash($plainPassword, $algo),
            'algo'   => $algoName,
            'gid'    => $groupId,
            'title'  => $memberTitle !== '' ? $memberTitle : null,
            'joined' => time(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<string, mixed>> */
    public function listGroups(): array
    {
        return $this->db->query('SELECT * FROM vf_groups ORDER BY id ASC')->fetchAll();
    }

    public function saveGroup(
        ?int $id,
        string $name,
        string $colorHex,
        bool $isAdmin,
        bool $isModerator,
        bool $canPost,
        string $description = '',
        string $prefixHtml = '',
        string $suffixHtml = '',
        bool $canCreatePolls = true,
        bool $canEditOwn = true,
        bool $canReport = true,
        bool $bypassFlood = false,
        int $floodSeconds = 30,
        bool $showInLegend = true,
        bool $canSetProfileCover = false,
        bool $canSetProfileMusic = false,
        bool $canUseChat = true,
        bool $canModerateChat = false
    ): int {
        if ($id !== null && $id > 0) {
            $this->db->prepare(
                'UPDATE vf_groups
                 SET name = :name, description = :descr, prefix_html = :prefix, suffix_html = :suffix,
                     color_hex = :color, show_in_legend = :legend, is_admin = :admin, is_moderator = :mod, can_post = :post,
                     can_create_polls = :polls, can_edit_own = :edit, can_report = :report,
                     can_set_profile_cover = :cover, can_set_profile_music = :music,
                     can_use_chat = :chat_use, can_moderate_chat = :chat_mod,
                     bypass_flood = :bflood, flood_seconds = :fsec
                 WHERE id = :id'
            )->execute([
                'name'     => $name,
                'descr'    => $description !== '' ? $description : null,
                'prefix'   => $prefixHtml,
                'suffix'   => $suffixHtml,
                'color'    => $colorHex,
                'legend'   => $showInLegend ? 1 : 0,
                'admin'    => $isAdmin ? 1 : 0,
                'mod'      => $isModerator ? 1 : 0,
                'post'     => $canPost ? 1 : 0,
                'polls'    => $canCreatePolls ? 1 : 0,
                'edit'     => $canEditOwn ? 1 : 0,
                'report'   => $canReport ? 1 : 0,
                'cover'    => $canSetProfileCover ? 1 : 0,
                'music'    => $canSetProfileMusic ? 1 : 0,
                'chat_use' => $canUseChat ? 1 : 0,
                'chat_mod' => $canModerateChat ? 1 : 0,
                'bflood'   => $bypassFlood ? 1 : 0,
                'fsec'     => max(0, $floodSeconds),
                'id'       => $id,
            ]);
            return $id;
        }

        $this->db->prepare(
            'INSERT INTO vf_groups
             (name, description, prefix_html, suffix_html, color_hex, show_in_legend, is_admin, is_moderator, can_post,
              can_create_polls, can_edit_own, can_report, can_set_profile_cover, can_set_profile_music,
              can_use_chat, can_moderate_chat, bypass_flood, flood_seconds, created_at)
             VALUES (:name, :descr, :prefix, :suffix, :color, :legend, :admin, :mod, :post, :polls, :edit, :report, :cover, :music, :chat_use, :chat_mod, :bflood, :fsec, :at)'
        )->execute([
            'name'     => $name,
            'descr'    => $description !== '' ? $description : null,
            'prefix'   => $prefixHtml,
            'suffix'   => $suffixHtml,
            'color'    => $colorHex,
            'legend'   => $showInLegend ? 1 : 0,
            'admin'    => $isAdmin ? 1 : 0,
            'mod'      => $isModerator ? 1 : 0,
            'post'     => $canPost ? 1 : 0,
            'polls'    => $canCreatePolls ? 1 : 0,
            'edit'     => $canEditOwn ? 1 : 0,
            'report'   => $canReport ? 1 : 0,
            'cover'    => $canSetProfileCover ? 1 : 0,
            'music'    => $canSetProfileMusic ? 1 : 0,
            'chat_use' => $canUseChat ? 1 : 0,
            'chat_mod' => $canModerateChat ? 1 : 0,
            'bflood'   => $bypassFlood ? 1 : 0,
            'fsec'     => max(0, $floodSeconds),
            'at'       => time(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function permissionsMatrix(): array
    {
        $rows = $this->db->query('SELECT * FROM vf_forum_permissions')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['forum_id']][(int) $r['group_id']] = $r;
        }
        return $out;
    }

    /**
     * @param array<int, array{can_view: bool, can_read: bool, can_create_topic: bool, can_reply: bool, can_moderate: bool}> $permsByGroup
     */
    public function saveForumPermissions(int $forumId, array $permsByGroup, bool $cascadeChildren = false): void
    {
        $targetForumIds = [$forumId];
        if ($cascadeChildren) {
            $fStmt = $this->db->prepare('SELECT materialized_path FROM vf_forums WHERE id = :id LIMIT 1');
            $fStmt->execute(['id' => $forumId]);
            $path = (string) ($fStmt->fetchColumn() ?: $forumId);
            $cStmt = $this->db->prepare('SELECT id FROM vf_forums WHERE materialized_path LIKE :prefix');
            $cStmt->execute(['prefix' => $path . '/%']);
            foreach ($cStmt->fetchAll() as $c) {
                $targetForumIds[] = (int) $c['id'];
            }
        }

        $upsert = $this->db->prepare(
            'INSERT INTO vf_forum_permissions
             (forum_id, group_id, can_view, can_read, can_create_topic, can_reply, can_moderate)
             VALUES (:fid, :gid, :v, :r, :t, :rep, :mod)
             ON DUPLICATE KEY UPDATE
               can_view = VALUES(can_view),
               can_read = VALUES(can_read),
               can_create_topic = VALUES(can_create_topic),
               can_reply = VALUES(can_reply),
               can_moderate = VALUES(can_moderate)'
        );

        $this->db->beginTransaction();
        try {
            foreach (array_unique($targetForumIds) as $fid) {
                foreach ($permsByGroup as $gid => $p) {
                    $upsert->execute([
                        'fid' => $fid,
                        'gid' => (int) $gid,
                        'v'   => !empty($p['can_view']) ? 1 : 0,
                        'r'   => !empty($p['can_read']) ? 1 : 0,
                        't'   => !empty($p['can_create_topic']) ? 1 : 0,
                        'rep' => !empty($p['can_reply']) ? 1 : 0,
                        'mod' => !empty($p['can_moderate']) ? 1 : 0,
                    ]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<int, array{can_view: bool, can_read: bool, can_create_topic: bool, can_reply: bool, can_moderate: bool}> $permsByForum
     */
    public function saveGroupPermissionsMatrix(int $groupId, array $permsByForum, bool $cascadeModeration = true): void
    {
        $allForums = $this->db->query('SELECT id, materialized_path FROM vf_forums')->fetchAll();
        $pathMap = [];
        foreach ($allForums as $f) {
            $pathMap[(int) $f['id']] = (string) $f['materialized_path'];
        }

        if ($cascadeModeration) {
            foreach ($permsByForum as $fid => $perm) {
                if (!empty($perm['can_moderate']) && isset($pathMap[$fid])) {
                    $prefix = $pathMap[$fid] . '/';
                    foreach ($pathMap as $candId => $candPath) {
                        if ($candId !== $fid && str_starts_with($candPath, $prefix)) {
                            if (!isset($permsByForum[$candId])) {
                                $permsByForum[$candId] = [
                                    'can_view'         => true,
                                    'can_read'         => true,
                                    'can_create_topic' => true,
                                    'can_reply'        => true,
                                    'can_moderate'     => true,
                                ];
                            } else {
                                $permsByForum[$candId]['can_moderate'] = true;
                            }
                        }
                    }
                }
            }
        }

        $upsert = $this->db->prepare(
            'INSERT INTO vf_forum_permissions
             (forum_id, group_id, can_view, can_read, can_create_topic, can_reply, can_moderate)
             VALUES (:fid, :gid, :v, :r, :t, :rep, :mod)
             ON DUPLICATE KEY UPDATE
               can_view = VALUES(can_view),
               can_read = VALUES(can_read),
               can_create_topic = VALUES(can_create_topic),
               can_reply = VALUES(can_reply),
               can_moderate = VALUES(can_moderate)'
        );

        $this->db->beginTransaction();
        try {
            foreach ($permsByForum as $fid => $p) {
                $upsert->execute([
                    'fid' => (int) $fid,
                    'gid' => $groupId,
                    'v'   => !empty($p['can_view']) ? 1 : 0,
                    'r'   => !empty($p['can_read']) ? 1 : 0,
                    't'   => !empty($p['can_create_topic']) ? 1 : 0,
                    'rep' => !empty($p['can_reply']) ? 1 : 0,
                    'mod' => !empty($p['can_moderate']) ? 1 : 0,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateReaction(int $id, string $label, int $points): void
    {
        $this->db->prepare('UPDATE vf_reactions SET label = :label, points = :pts WHERE id = :id')
            ->execute(['label' => $label, 'pts' => $points, 'id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listAnnouncements(): array
    {
        try {
            return $this->db->query('SELECT * FROM vf_announcements ORDER BY id DESC')->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function activeAnnouncements(): array
    {
        if ($this->activeAnnouncementsCache !== null) {
            return $this->activeAnnouncementsCache;
        }
        try {
            $this->activeAnnouncementsCache = $this->db->query('SELECT * FROM vf_announcements WHERE is_active = 1 ORDER BY id DESC')->fetchAll();
            return $this->activeAnnouncementsCache;
        } catch (\Throwable) {
            return [];
        }
    }

    public function saveAnnouncement(
        ?int $id,
        string $title,
        string $content,
        string $tone,
        string $placement,
        string $linkLabel,
        string $linkUrl,
        bool $isActive = true
    ): int {
        $safeTone = in_array($tone, ['info', 'success', 'warning'], true) ? $tone : 'info';
        $safePlace = in_array($placement, ['home', 'all'], true) ? $placement : 'home';
        $safeUrl = trim($linkUrl);
        if ($safeUrl !== '' && !preg_match('#^(https?://|/|\#)#i', $safeUrl)) {
            $safeUrl = '';
        }
        $this->activeAnnouncementsCache = null;

        if ($id !== null && $id > 0) {
            $this->db->prepare(
                'UPDATE vf_announcements
                 SET title = :title, content = :content, tone = :tone, placement = :placement,
                     link_label = :ll, link_url = :lu, is_active = :active
                 WHERE id = :id'
            )->execute([
                'title'     => mb_substr(trim($title), 0, 160),
                'content'   => mb_substr(trim($content), 0, 600),
                'tone'      => $safeTone,
                'placement' => $safePlace,
                'll'        => trim($linkLabel) !== '' ? mb_substr(trim($linkLabel), 0, 80) : null,
                'lu'        => $safeUrl !== '' ? mb_substr($safeUrl, 0, 255) : null,
                'active'    => $isActive ? 1 : 0,
                'id'        => $id,
            ]);
            return $id;
        }

        $this->db->prepare(
            'INSERT INTO vf_announcements (title, content, tone, placement, link_label, link_url, is_active, created_at)
             VALUES (:title, :content, :tone, :placement, :ll, :lu, :active, :at)'
        )->execute([
            'title'     => mb_substr(trim($title), 0, 160),
            'content'   => mb_substr(trim($content), 0, 600),
            'tone'      => $safeTone,
            'placement' => $safePlace,
            'll'        => trim($linkLabel) !== '' ? mb_substr(trim($linkLabel), 0, 80) : null,
            'lu'        => $safeUrl !== '' ? mb_substr($safeUrl, 0, 255) : null,
            'active'    => $isActive ? 1 : 0,
            'at'        => time(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function toggleAnnouncement(int $id): void
    {
        $this->activeAnnouncementsCache = null;
        $this->db->prepare('UPDATE vf_announcements SET is_active = 1 - is_active WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function deleteAnnouncement(int $id): void
    {
        $this->activeAnnouncementsCache = null;
        $this->db->prepare('DELETE FROM vf_announcements WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPrefixes(): array
    {
        if ($this->prefixesCache !== null) {
            return $this->prefixesCache;
        }
        try {
            $this->prefixesCache = $this->db->query('SELECT * FROM vf_topic_prefixes ORDER BY position ASC, id ASC')->fetchAll();
            return $this->prefixesCache;
        } catch (\Throwable) {
            return [];
        }
    }

    public function savePrefix(?int $id, string $label, string $colorHex, int $position = 0): int
    {
        $safeColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($colorHex)) ? trim($colorHex) : '#2563eb';
        $safeLabel = mb_substr(trim($label), 0, 48);
        $this->prefixesCache = null;

        if ($id !== null && $id > 0) {
            $this->db->prepare(
                'UPDATE vf_topic_prefixes SET label = :lbl, color_hex = :col, position = :pos WHERE id = :id'
            )->execute([
                'lbl' => $safeLabel,
                'col' => $safeColor,
                'pos' => $position,
                'id'  => $id,
            ]);
            return $id;
        }

        $nextPos = $position > 0
            ? $position
            : ((int) $this->db->query('SELECT COALESCE(MAX(position), 0) FROM vf_topic_prefixes')->fetchColumn()) + 1;

        $this->db->prepare(
            'INSERT INTO vf_topic_prefixes (label, color_hex, position) VALUES (:lbl, :col, :pos)'
        )->execute([
            'lbl' => $safeLabel,
            'col' => $safeColor,
            'pos' => $nextPos,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deletePrefix(int $id): void
    {
        $this->prefixesCache = null;
        $this->db->prepare('UPDATE vf_topics SET prefix_id = NULL WHERE prefix_id = :id')->execute(['id' => $id]);
        $this->db->prepare('DELETE FROM vf_topic_prefixes WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listProfileFields(): array
    {
        if ($this->profileFieldsCache !== null) {
            return $this->profileFieldsCache;
        }
        try {
            $this->profileFieldsCache = $this->db->query('SELECT * FROM vf_profile_fields ORDER BY position ASC, id ASC')->fetchAll();
            return $this->profileFieldsCache;
        } catch (\Throwable) {
            return [];
        }
    }

    public function saveProfileField(
        ?int $id,
        string $fieldKey,
        string $label,
        string $placeholder,
        bool $showInPost,
        int $position = 0
    ): int {
        $cleanKey = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim($fieldKey)) ?? '');
        $cleanKey = trim($cleanKey, '_');
        if ($cleanKey === '') {
            $cleanKey = 'pole_' . substr((string) time(), -4);
        }
        $cleanKey = mb_substr($cleanKey, 0, 48);
        $this->profileFieldsCache = null;

        if ($id !== null && $id > 0) {
            $this->db->prepare(
                'UPDATE vf_profile_fields
                 SET field_key = :fkey, label = :lbl, placeholder = :ph, show_in_post = :sip, position = :pos
                 WHERE id = :id'
            )->execute([
                'fkey' => $cleanKey,
                'lbl'  => mb_substr(trim($label), 0, 80),
                'ph'   => trim($placeholder) !== '' ? mb_substr(trim($placeholder), 0, 120) : null,
                'sip'  => $showInPost ? 1 : 0,
                'pos'  => $position,
                'id'   => $id,
            ]);
            return $id;
        }

        $nextPos = $position > 0
            ? $position
            : ((int) $this->db->query('SELECT COALESCE(MAX(position), 0) FROM vf_profile_fields')->fetchColumn()) + 1;

        $this->db->prepare(
            'INSERT INTO vf_profile_fields (field_key, label, placeholder, show_in_post, position)
             VALUES (:fkey, :lbl, :ph, :sip, :pos)'
        )->execute([
            'fkey' => $cleanKey,
            'lbl'  => mb_substr(trim($label), 0, 80),
            'ph'   => trim($placeholder) !== '' ? mb_substr(trim($placeholder), 0, 120) : null,
            'sip'  => $showInPost ? 1 : 0,
            'pos'  => $nextPos,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteProfileField(int $id): void
    {
        $this->profileFieldsCache = null;
        $this->db->prepare('DELETE FROM vf_profile_fields WHERE id = :id')->execute(['id' => $id]);
    }

    public function recountAllCounters(): void
    {
        $this->db->exec(
            'UPDATE vf_topics t
             LEFT JOIN (
                 SELECT topic_id,
                        COUNT(*) AS total_posts,
                        MIN(id) AS first_pid,
                        MAX(id) AS last_pid,
                        MAX(created_at) AS max_created
                 FROM vf_posts
                 WHERE is_approved = 1
                 GROUP BY topic_id
             ) ps ON ps.topic_id = t.id
             SET t.posts_count = GREATEST(1, COALESCE(ps.total_posts, 0)),
                 t.first_post_id = COALESCE(t.first_post_id, ps.first_pid),
                 t.last_post_id = COALESCE(ps.last_pid, t.last_post_id),
                 t.last_post_at = GREATEST(t.last_post_at, COALESCE(ps.max_created, 0))'
        );

        $this->db->exec(
            'UPDATE vf_forums f
             LEFT JOIN (
                 SELECT forum_id,
                        COUNT(*) AS total_topics,
                        COALESCE(SUM(posts_count), 0) AS total_posts
                 FROM vf_topics
                 WHERE is_approved = 1
                 GROUP BY forum_id
             ) ts ON ts.forum_id = f.id
             SET f.topics_count = COALESCE(ts.total_topics, 0),
                 f.posts_count = COALESCE(ts.total_posts, 0),
                 f.last_topic_id = (
                     SELECT t.id FROM vf_topics t
                     WHERE t.forum_id = f.id AND t.is_approved = 1
                     ORDER BY t.last_post_at DESC, t.id DESC
                     LIMIT 1
                 )'
        );

        $this->db->exec(
            'UPDATE vf_forums f
             LEFT JOIN vf_topics t ON t.id = f.last_topic_id
             SET f.last_post_at = t.last_post_at,
                 f.last_poster_id = t.last_poster_id,
                 f.last_poster_name = t.last_poster_name'
        );

        $this->db->exec(
            'UPDATE vf_members m
             LEFT JOIN (
                 SELECT author_id, COUNT(*) AS total_posts
                 FROM vf_posts
                 WHERE is_approved = 1 AND author_id IS NOT NULL
                 GROUP BY author_id
             ) mp ON mp.author_id = m.id
             SET m.posts_count = COALESCE(mp.total_posts, 0)'
        );
    }

    public function prunePresence(int $windowSeconds = 1200): int
    {
        try {
            $cutoff = time() - $windowSeconds;
            $stmt = $this->db->prepare('DELETE FROM vf_presence WHERE updated_at < :cutoff');
            $stmt->execute(['cutoff' => $cutoff]);
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{files: int, total_bytes: int, active_presence: int, unread_reports: int} */
    public function systemHealthStats(string $publicRoot): array
    {
        $files = 0;
        $bytes = 0;
        $uploadsDir = rtrim($publicRoot, '/') . '/uploads';
        if (is_dir($uploadsDir)) {
            try {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($uploadsDir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $file) {
                    if ($file instanceof \SplFileInfo && $file->isFile()) {
                        $files++;
                        $bytes += (int) $file->getSize();
                    }
                }
            } catch (\Throwable) {
                // Ignorujemy
            }
        }
        $activePresence = 0;
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM vf_presence WHERE updated_at >= :cutoff');
            $stmt->execute(['cutoff' => time() - 1200]);
            $activePresence = (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            // Ignorujemy
        }
        $openReports = 0;
        try {
            $openReports = (int) $this->db->query("SELECT COUNT(*) FROM vf_reports WHERE status = 'open'")->fetchColumn();
        } catch (\Throwable) {
            // Ignorujemy
        }
        return [
            'files'           => $files,
            'total_bytes'     => $bytes,
            'active_presence' => $activePresence,
            'open_reports'    => $openReports,
        ];
    }

    /** @return array<string, mixed> */
    public function exportStructureJson(): array
    {
        $forums = $this->db->query(
            'SELECT id, parent_id, is_category, name, slug, description, position, topics_count, posts_count
             FROM vf_forums ORDER BY position ASC, id ASC'
        )->fetchAll();
        $groups = $this->db->query(
            'SELECT id, name, color_hex, show_in_legend, is_admin, is_moderator, can_post, can_create_polls,
                    can_set_profile_cover, can_set_profile_music, can_use_chat, can_moderate_chat
             FROM vf_groups ORDER BY id ASC'
        )->fetchAll();
        $prefixes = $this->listPrefixes();
        return [
            'exported_at'    => gmdate('c'),
            'schema_version' => '3.7',
            'forums'         => $forums,
            'groups'         => $groups,
            'topic_prefixes' => $prefixes,
            'settings'       => $this->settings(),
        ];
    }

    private function slugify(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? 'dzial';
        return trim($slug, '-') ?: 'dzial';
    }
}

<?php

declare(strict_types=1);

namespace VeloForum\Migration;

use PDO;

/**
 * Pełny migrator z Invision Community 5 / IPS 4 -> VeloForum PHP 8.3+.
 * Dynamicznie wykrywa kolumny w bazie źródłowej i zachowuje relacje ID:
 *  - core_groups / core_admin_permission_rows -> vf_groups
 *  - core_members      -> vf_members (z zachowaniem $2y$ bcrypt oraz legacy_salt)
 *  - core_sys_lang_words -> nazwy/opisy działów i podkategorii
 *  - forums_forums     -> vf_forums (pełna hierarchia parent_id + materialized_path)
 *  - forums_topics     -> vf_topics
 *  - forums_posts      -> vf_posts
 */
final class Ic5Migrator
{
    private const BATCH_SIZE = 1000;
    private readonly string $prefix;

    public function __construct(
        private readonly PDO $sourceIc5,
        private readonly PDO $targetVf,
        string $prefix = ''
    ) {
        $this->prefix = str_replace('`', '', trim($prefix));
    }

    public function audit(): array
    {
        $p = $this->prefix;
        return [
            'groups'      => $this->safeCount("SELECT COUNT(*) FROM `{$p}core_groups`"),
            'members'     => $this->safeCount("SELECT COUNT(*) FROM `{$p}core_members`"),
            'forums'      => $this->safeCount("SELECT COUNT(*) FROM `{$p}forums_forums`"),
            'topics'      => $this->safeCount("SELECT COUNT(*) FROM `{$p}forums_topics`"),
            'posts'       => $this->safeCount("SELECT COUNT(*) FROM `{$p}forums_posts`"),
            'reactions'   => $this->safeCount("SELECT COUNT(*) FROM `{$p}core_reputation_index` WHERE app = 'forums' AND type = 'pid'"),
            'permissions' => $this->safeCount("SELECT COUNT(*) FROM `{$p}core_permission_index` WHERE app = 'forums' AND perm_type = 'forum'"),
            'moderators'  => $this->safeCount("SELECT COUNT(*) FROM `{$p}core_moderators` WHERE type = 'g'"),
        ];
    }

    public function run(callable $progress = null): array
    {
        $log = $progress ?? static function (string $msg): void {};
        $report = [
            'groups'           => 0,
            'members'          => 0,
            'forums'           => 0,
            'topics'           => 0,
            'posts'            => 0,
            'reactions'        => 0,
            'permissions'      => 0,
            'orphans_repaired' => 0,
        ];

        $stage = 'inicjalizacja';
        $this->targetVf->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $stage = '1/9 grupy (core_groups)';
            $log('1/9 Migracja grup użytkowników (core_groups -> vf_groups)...');
            $report['groups'] = $this->migrateGroups();

            $stage = '2/9 tłumaczenia (core_sys_lang_words)';
            $log('2/9 Wczytywanie tłumaczeń nazw działów (core_sys_lang_words)...');
            $forumLabels = $this->loadForumLangWords();

            $stage = '3/9 użytkownicy (core_members)';
            $log('3/9 Migracja użytkowników, haseł bcrypt/IPS3 i grup dodatkowych (core_members -> vf_members)...');
            $report['members'] = $this->migrateMembers();

            $stage = '4/9 działy i kategorie (forums_forums)';
            $log('4/9 Migracja struktury kategorii, działów i podkategorii (forums_forums -> vf_forums)...');
            $report['forums'] = $this->migrateForums($forumLabels);

            $stage = '5/9 uprawnienia (core_permission_index)';
            $log('5/9 Migracja macierzy uprawnień działów i moderatorów kategorii (core_permission_index + core_moderators)...');
            $report['permissions'] = $this->migratePermissions();

            $stage = '6/9 tematy (forums_topics)';
            $log('6/9 Migracja tematów w paczkach (forums_topics -> vf_topics)...');
            $report['topics'] = $this->migrateTopics();

            $stage = '7/9 posty (forums_posts)';
            $log('7/9 Migracja postów w paczkach (forums_posts -> vf_posts)...');
            $report['posts'] = $this->migratePosts();

            $stage = '8/9 reakcje (core_reputation_index)';
            $log('8/9 Migracja reakcji i punktów reputacji (core_reputation_index -> vf_post_reactions)...');
            $report['reactions'] = $this->migrateReactions();

            $stage = '9/9 synchronizacja relacji';
            $log('9/9 Weryfikacja relacji, naprawa osieroconych kluczy i synchronizacja liczników...');
            $report['orphans_repaired'] = $this->repairAndSyncPostMigration();

            $this->recordLog('completed', $report);
        } catch (\Throwable $e) {
            if ($this->targetVf->inTransaction()) {
                $this->targetVf->rollBack();
            }
            $wrapped = new \RuntimeException("[Etap {$stage}] {$e->getMessage()}", 0, $e);
            try {
                $this->recordLog('failed', $report, $wrapped->getMessage());
            } catch (\Throwable) {
                // Nie przesłaniamy właściwego wyjątku migracji
            }
            throw $wrapped;
        } finally {
            try {
                $this->targetVf->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Throwable) {
                // Ignorujemy
            }
        }

        return $report;
    }

    private function migrateGroups(): int
    {
        $p = $this->prefix;
        $table = "{$p}core_groups";
        $cols = $this->requireTableColumns($table, 'core_groups');
        $selectCols = $this->buildSelectList($cols, [
            'g_id',
            'g_access_cp',
            'g_is_supmod',
            'g_bitoptions',
            'prefix',
            'suffix',
            'g_name',
        ]);

        $rows = $this->sourceIc5->query(
            "SELECT {$selectCols} FROM `{$table}` ORDER BY g_id ASC"
        )->fetchAll();

        $langWords = $this->loadGroupLangWords();
        $adminGroupIds = $this->loadAdminGroupIds();
        $supmodGroupIds = $this->loadSuperModGroupIds();

        $stmt = $this->targetVf->prepare(
            'INSERT INTO vf_groups (id, name, prefix_html, suffix_html, color_hex, is_admin, is_moderator, can_post, created_at)
             VALUES (:id, :name, :prefix, :suffix, :color, :admin, :mod, 1, :created)
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               prefix_html = VALUES(prefix_html),
               suffix_html = VALUES(suffix_html),
               is_admin = VALUES(is_admin),
               is_moderator = VALUES(is_moderator)'
        );

        $count = 0;
        $now = time();
        foreach ($rows as $row) {
            $gid = (int) $row['g_id'];
            $fallbackName = !empty($row['g_name']) ? (string) $row['g_name'] : "Grupa #{$gid}";
            $name = mb_substr($langWords[$gid] ?? $fallbackName, 0, 120);
            $prefix = mb_substr((string) ($row['prefix'] ?? ''), 0, 255);
            $suffix = mb_substr((string) ($row['suffix'] ?? ''), 0, 255);
            $color = '#475569';
            if (preg_match('/#([0-9a-fA-F]{3,6})/', $prefix, $m)) {
                $color = '#' . $m[1];
            }

            $isAdmin = ((int) ($row['g_access_cp'] ?? 0) === 1 || isset($adminGroupIds[$gid])) ? 1 : 0;
            $isMod = ($isAdmin === 1 || (int) ($row['g_is_supmod'] ?? 0) === 1 || isset($supmodGroupIds[$gid])) ? 1 : 0;

            $stmt->execute([
                'id'      => $gid,
                'name'    => $name,
                'prefix'  => $prefix,
                'suffix'  => $suffix,
                'color'   => $color,
                'admin'   => $isAdmin,
                'mod'     => $isMod,
                'created' => $now,
            ]);
            $count++;
        }

        return $count;
    }

    private function migrateMembers(): int
    {
        $p = $this->prefix;
        $table = "{$p}core_members";
        $cols = $this->requireTableColumns($table, 'core_members');
        $total = (int) $this->sourceIc5->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

        $selectCols = $this->buildSelectList($cols, [
            'member_id',
            'name',
            'members_seo_name',
            'email',
            'members_pass_hash',
            'members_pass_salt',
            'member_group_id',
            'mgroup_others',
            'pp_main_photo',
            'member_title',
            'member_posts',
            'pp_reputation_points',
            'joined',
            'last_activity',
            'temp_ban',
        ]);

        $insert = $this->targetVf->prepare(
            'INSERT INTO vf_members
             (id, username, slug, email, password_hash, legacy_salt, legacy_algo, primary_group_id, secondary_group_ids,
              avatar_url, member_title, posts_count, reputation_points, joined_at, last_activity_at, is_banned)
             VALUES
             (:id, :username, :slug, :email, :hash, :salt, :algo, :group_id, :sec_groups,
              :avatar, :title, :posts, :rep, :joined, :activity, :banned)
             ON DUPLICATE KEY UPDATE
               username = VALUES(username),
               email = VALUES(email),
               password_hash = VALUES(password_hash),
               legacy_salt = VALUES(legacy_salt),
               legacy_algo = VALUES(legacy_algo),
               primary_group_id = VALUES(primary_group_id),
               secondary_group_ids = VALUES(secondary_group_ids),
               posts_count = VALUES(posts_count)'
        );

        $seenUsernames = [];
        $seenEmails = [];
        $migrated = 0;

        for ($offset = 0; $offset < $total; $offset += self::BATCH_SIZE) {
            $stmt = $this->sourceIc5->prepare(
                "SELECT {$selectCols}
                 FROM `{$table}`
                 ORDER BY member_id ASC
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue('limit', self::BATCH_SIZE, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $this->targetVf->beginTransaction();
            try {
                while ($row = $stmt->fetch()) {
                    $mid = (int) $row['member_id'];
                    $passHash = (string) ($row['members_pass_hash'] ?? '');
                    $passSalt = isset($row['members_pass_salt']) && $row['members_pass_salt'] !== null
                        ? (string) $row['members_pass_salt']
                        : null;
                    $algo = str_starts_with($passHash, '$2') ? 'bcrypt' : 'ips3_md5';

                    $secRaw = trim((string) ($row['mgroup_others'] ?? ''));
                    $secIds = $secRaw !== ''
                        ? array_values(array_filter(array_map('intval', explode(',', $secRaw))))
                        : [];

                    $username = mb_substr(trim((string) ($row['name'] ?? '')), 0, 110);
                    if ($username === '') {
                        $username = 'User_' . $mid;
                    }
                    $uKey = mb_strtolower($username);
                    if (isset($seenUsernames[$uKey]) && $seenUsernames[$uKey] !== $mid) {
                        $username = mb_substr($username, 0, 100) . '_' . $mid;
                        $uKey = mb_strtolower($username);
                    }
                    $seenUsernames[$uKey] = $mid;

                    $email = mb_substr(trim((string) ($row['email'] ?? '')), 0, 180);
                    if ($email === '') {
                        $email = 'migrated_' . $mid . '@invalid.local';
                    }
                    $eKey = mb_strtolower($email);
                    if (isset($seenEmails[$eKey]) && $seenEmails[$eKey] !== $mid) {
                        $email = $mid . '+' . mb_substr($email, 0, 170);
                        $eKey = mb_strtolower($email);
                    }
                    $seenEmails[$eKey] = $mid;

                    $insert->execute([
                        'id'         => $mid,
                        'username'   => $username,
                        'slug'       => mb_substr((string) ($row['members_seo_name'] ?: 'user-' . $mid), 0, 160),
                        'email'      => $email,
                        'hash'       => $passHash,
                        'salt'       => $algo === 'ips3_md5' ? mb_substr((string) $passSalt, 0, 64) : null,
                        'algo'       => $algo,
                        'group_id'   => max(1, (int) ($row['member_group_id'] ?? 3)),
                        'sec_groups' => json_encode($secIds, JSON_THROW_ON_ERROR),
                        'avatar'     => !empty($row['pp_main_photo']) ? mb_substr((string) $row['pp_main_photo'], 0, 512) : null,
                        'title'      => !empty($row['member_title']) ? mb_substr((string) $row['member_title'], 0, 120) : null,
                        'posts'      => max(0, (int) ($row['member_posts'] ?? 0)),
                        'rep'        => (int) ($row['pp_reputation_points'] ?? 0),
                        'joined'     => max(0, (int) ($row['joined'] ?? time())),
                        'activity'   => !empty($row['last_activity']) ? (int) $row['last_activity'] : null,
                        'banned'     => (int) ($row['temp_ban'] ?? 0) === -1 ? 1 : 0,
                    ]);
                    $migrated++;
                }
                $this->targetVf->commit();
            } catch (\Throwable $e) {
                if ($this->targetVf->inTransaction()) {
                    $this->targetVf->rollBack();
                }
                throw $e;
            }
        }

        return $migrated;
    }

    private function migrateForums(array $forumLabels): int
    {
        $p = $this->prefix;
        $table = "{$p}forums_forums";
        $cols = $this->requireTableColumns($table, 'forums_forums');
        $selectCols = $this->buildSelectList($cols, [
            'id',
            'parent_id',
            'position',
            'name',
            'name_seo',
            'description',
            'topics',
            'posts',
            'last_post',
            'last_poster_id',
            'last_poster_name',
            'redirect_url',
            'can_view_others',
            'sub_can_post',
        ]);

        $rows = $this->sourceIc5->query(
            "SELECT {$selectCols} FROM `{$table}` ORDER BY parent_id ASC, position ASC, id ASC"
        )->fetchAll();

        $byId = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $rawParent = (int) $row['parent_id'];
            $row['norm_parent'] = $rawParent <= 0 ? null : $rawParent;
            $byId[$id] = $row;
        }

        $computeMeta = static function (int $id) use (&$byId): array {
            $chain = [];
            $curr = $id;
            $guard = 0;
            while ($curr !== null && isset($byId[$curr]) && $guard < 16) {
                array_unshift($chain, (string) $curr);
                $curr = $byId[$curr]['norm_parent'];
                $guard++;
            }
            return [
                'path'  => implode('/', $chain),
                'depth' => max(0, count($chain) - 1),
            ];
        };

        $stmt = $this->targetVf->prepare(
            'INSERT INTO vf_forums
             (id, parent_id, materialized_path, depth, is_category, name, slug, description, position,
              topics_count, posts_count, last_post_at, last_poster_id, last_poster_name, redirect_url, can_view_others)
             VALUES
             (:id, :parent_id, :path, :depth, :is_cat, :name, :slug, :desc, :pos,
              :topics, :posts, :last_post, :last_uid, :last_uname, :redirect, :view_others)
             ON DUPLICATE KEY UPDATE
               parent_id = VALUES(parent_id),
               materialized_path = VALUES(materialized_path),
               depth = VALUES(depth),
               name = VALUES(name),
               description = VALUES(description),
               redirect_url = VALUES(redirect_url),
               position = VALUES(position)'
        );

        $count = 0;
        foreach ($byId as $id => $row) {
            $meta = $computeMeta($id);
            $labels = $forumLabels[$id] ?? [
                'name' => !empty($row['name']) ? (string) $row['name'] : "Dział #{$id}",
                'desc' => !empty($row['description']) ? $this->cleanDescriptionHtml((string) $row['description']) : '',
            ];
            $isCategory = ($row['norm_parent'] === null && (int) ($row['sub_can_post'] ?? 1) === 0) ? 1 : 0;

            $stmt->execute([
                'id'          => $id,
                'parent_id'   => $row['norm_parent'],
                'path'        => mb_substr($meta['path'], 0, 255),
                'depth'       => $meta['depth'],
                'is_cat'      => $isCategory,
                'name'        => mb_substr($labels['name'], 0, 255),
                'slug'        => mb_substr((string) ($row['name_seo'] ?: 'forum-' . $id), 0, 255),
                'desc'        => $labels['desc'],
                'pos'         => (int) ($row['position'] ?? 0),
                'topics'      => max(0, (int) ($row['topics'] ?? 0)),
                'posts'       => max(0, (int) ($row['posts'] ?? 0)),
                'last_post'   => !empty($row['last_post']) ? (int) $row['last_post'] : null,
                'last_uid'    => !empty($row['last_poster_id']) ? (int) $row['last_poster_id'] : null,
                'last_uname'  => !empty($row['last_poster_name']) ? mb_substr((string) $row['last_poster_name'], 0, 120) : null,
                'redirect'    => !empty($row['redirect_url']) ? mb_substr((string) $row['redirect_url'], 0, 512) : null,
                'view_others' => (int) ($row['can_view_others'] ?? 1) === 1 ? 1 : 0,
            ]);
            $count++;
        }

        return $count;
    }

    private function migratePermissions(): int
    {
        $p = $this->prefix;
        $groupRows = $this->targetVf->query('SELECT id, is_admin, is_moderator, can_post FROM vf_groups')->fetchAll();
        $forumRows = $this->targetVf->query('SELECT id, parent_id, materialized_path FROM vf_forums')->fetchAll();
        if ($groupRows === [] || $forumRows === []) {
            return 0;
        }

        $groupIds = array_map(static fn (array $g): int => (int) $g['id'], $groupRows);
        $forumsById = [];
        foreach ($forumRows as $f) {
            $forumsById[(int) $f['id']] = $f;
        }

        $matrix = [];
        try {
            $permRows = $this->sourceIc5->query(
                "SELECT perm_type_id, perm_view, perm_2, perm_3, perm_4
                 FROM `{$p}core_permission_index`
                 WHERE app = 'forums' AND perm_type = 'forum'"
            )->fetchAll();

            $parseSet = static function (?string $raw) use ($groupIds): array {
                $val = trim((string) $raw);
                if ($val === '*') {
                    return array_fill_keys($groupIds, true);
                }
                if ($val === '') {
                    return [];
                }
                $set = [];
                foreach (explode(',', $val) as $tok) {
                    $gid = (int) trim($tok);
                    if ($gid > 0) {
                        $set[$gid] = true;
                    }
                }
                return $set;
            };

            foreach ($permRows as $row) {
                $fid = (int) $row['perm_type_id'];
                if (!isset($forumsById[$fid])) {
                    continue;
                }
                $viewSet = $parseSet($row['perm_view'] ?? '');
                $readSet = $parseSet($row['perm_2'] ?? '');
                $replySet = $parseSet($row['perm_3'] ?? '');
                $topicSet = $parseSet($row['perm_4'] ?? '');

                foreach ($groupIds as $gid) {
                    $matrix[$fid][$gid] = [
                        'can_view'         => isset($viewSet[$gid]) ? 1 : 0,
                        'can_read'         => isset($readSet[$gid]) ? 1 : 0,
                        'can_create_topic' => isset($topicSet[$gid]) ? 1 : 0,
                        'can_reply'        => isset($replySet[$gid]) ? 1 : 0,
                        'can_moderate'     => 0,
                    ];
                }
            }
        } catch (\Throwable) {
            // Źródłowa baza może nie zawierać tabeli core_permission_index
        }

        try {
            $modRows = $this->sourceIc5->query(
                "SELECT id, forums FROM `{$p}core_moderators` WHERE type = 'g'"
            )->fetchAll();

            foreach ($modRows as $mRow) {
                $gid = (int) ($mRow['id'] ?? 0);
                if ($gid <= 0) {
                    continue;
                }
                $rawForums = trim((string) ($mRow['forums'] ?? ''));
                $targetForumIds = [];
                if ($rawForums === '*' || $rawForums === '-1') {
                    $targetForumIds = array_keys($forumsById);
                } else {
                    foreach (explode(',', $rawForums) as $tok) {
                        $fid = (int) trim($tok);
                        if ($fid > 0 && isset($forumsById[$fid])) {
                            $targetForumIds[] = $fid;
                        }
                    }
                }

                foreach ($targetForumIds as $fid) {
                    $prefixPath = (string) ($forumsById[$fid]['materialized_path'] ?? (string) $fid);
                    foreach ($forumsById as $candId => $candRow) {
                        $candPath = (string) ($candRow['materialized_path'] ?? (string) $candId);
                        if ($candId === $fid || str_starts_with($candPath, $prefixPath . '/')) {
                            if (!isset($matrix[$candId][$gid])) {
                                $matrix[$candId][$gid] = [
                                    'can_view'         => 1,
                                    'can_read'         => 1,
                                    'can_create_topic' => 1,
                                    'can_reply'        => 1,
                                    'can_moderate'     => 1,
                                ];
                            } else {
                                $matrix[$candId][$gid]['can_view'] = 1;
                                $matrix[$candId][$gid]['can_read'] = 1;
                                $matrix[$candId][$gid]['can_moderate'] = 1;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Źródłowa baza może nie zawierać tabeli core_moderators
        }

        if ($matrix === []) {
            return 0;
        }

        $stmt = $this->targetVf->prepare(
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

        $saved = 0;
        $this->targetVf->beginTransaction();
        try {
            foreach ($matrix as $fid => $byGroup) {
                foreach ($byGroup as $gid => $perm) {
                    $stmt->execute([
                        'fid' => $fid,
                        'gid' => $gid,
                        'v'   => $perm['can_view'],
                        'r'   => $perm['can_read'],
                        't'   => $perm['can_create_topic'],
                        'rep' => $perm['can_reply'],
                        'mod' => $perm['can_moderate'],
                    ]);
                    $saved++;
                }
            }
            $this->targetVf->commit();
        } catch (\Throwable $e) {
            if ($this->targetVf->inTransaction()) {
                $this->targetVf->rollBack();
            }
            throw $e;
        }

        return $saved;
    }

    private function migrateTopics(): int
    {
        $p = $this->prefix;
        $table = "{$p}forums_topics";
        $cols = $this->requireTableColumns($table, 'forums_topics');
        $total = (int) $this->sourceIc5->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

        $selectCols = $this->buildSelectList($cols, [
            'tid',
            'forum_id',
            'starter_id',
            'starter_name',
            'title',
            'title_seo',
            'posts',
            'views',
            'pinned',
            'state',
            'approved',
            'topic_firstpost',
            'topic_answered_pid',
            'start_date',
            'last_post',
            'last_poster_id',
            'last_poster_name',
        ]);

        $insert = $this->targetVf->prepare(
            'INSERT INTO vf_topics
             (id, forum_id, author_id, author_name, title, slug, posts_count, views_count,
              is_pinned, is_locked, is_approved, first_post_id, best_answer_post_id,
              created_at, last_post_at, last_poster_id, last_poster_name)
             VALUES
             (:id, :forum_id, :author_id, :author_name, :title, :slug, :posts, :views,
              :pinned, :locked, :approved, :first_pid, :best_pid,
              :created, :last_post, :last_uid, :last_uname)
             ON DUPLICATE KEY UPDATE
               forum_id = VALUES(forum_id),
               title = VALUES(title),
               posts_count = VALUES(posts_count),
               is_pinned = VALUES(is_pinned),
               is_locked = VALUES(is_locked)'
        );

        $migrated = 0;
        for ($offset = 0; $offset < $total; $offset += self::BATCH_SIZE) {
            $stmt = $this->sourceIc5->prepare(
                "SELECT {$selectCols}
                 FROM `{$table}`
                 ORDER BY tid ASC
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue('limit', self::BATCH_SIZE, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $this->targetVf->beginTransaction();
            try {
                while ($row = $stmt->fetch()) {
                    $tid = (int) $row['tid'];
                    $starterId = (int) ($row['starter_id'] ?? 0);
                    $lastPosterId = (int) ($row['last_poster_id'] ?? 0);
                    $authorName = mb_substr((string) ($row['starter_name'] ?: 'Gość'), 0, 120);
                    $lastName = mb_substr((string) ($row['last_poster_name'] ?: $authorName), 0, 120);

                    $insert->execute([
                        'id'          => $tid,
                        'forum_id'    => max(1, (int) ($row['forum_id'] ?? 1)),
                        'author_id'   => $starterId > 0 ? $starterId : null,
                        'author_name' => $authorName,
                        'title'       => mb_substr((string) ($row['title'] ?: "Temat #{$tid}"), 0, 255),
                        'slug'        => mb_substr((string) ($row['title_seo'] ?: 'temat-' . $tid), 0, 255),
                        'posts'       => max(1, (int) ($row['posts'] ?? 1)),
                        'views'       => max(0, (int) ($row['views'] ?? 0)),
                        'pinned'      => (int) ($row['pinned'] ?? 0) === 1 ? 1 : 0,
                        'locked'      => strtolower((string) ($row['state'] ?? 'open')) === 'closed' ? 1 : 0,
                        'approved'    => (int) ($row['approved'] ?? 1) === 1 ? 1 : 0,
                        'first_pid'   => !empty($row['topic_firstpost']) ? (int) $row['topic_firstpost'] : null,
                        'best_pid'    => !empty($row['topic_answered_pid']) ? (int) $row['topic_answered_pid'] : null,
                        'created'     => max(0, (int) ($row['start_date'] ?? time())),
                        'last_post'   => max(0, (int) ($row['last_post'] ?? $row['start_date'] ?? time())),
                        'last_uid'    => $lastPosterId > 0 ? $lastPosterId : null,
                        'last_uname'  => $lastName,
                    ]);
                    $migrated++;
                }
                $this->targetVf->commit();
            } catch (\Throwable $e) {
                if ($this->targetVf->inTransaction()) {
                    $this->targetVf->rollBack();
                }
                throw $e;
            }
        }

        return $migrated;
    }

    private function migratePosts(): int
    {
        $p = $this->prefix;
        $table = "{$p}forums_posts";
        $cols = $this->requireTableColumns($table, 'forums_posts');
        $total = (int) $this->sourceIc5->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

        $selectCols = $this->buildSelectList($cols, [
            'pid',
            'topic_id',
            'author_id',
            'author_name',
            'post',
            'new_topic',
            'queued',
            'ip_address',
            'post_date',
            'edit_time',
            'edit_name',
        ]);

        $insert = $this->targetVf->prepare(
            'INSERT INTO vf_posts
             (id, topic_id, author_id, author_name, content_html, is_first_post, is_approved,
              ip_address, created_at, edited_at, edited_by_name)
             VALUES
             (:id, :topic_id, :author_id, :author_name, :html, :first, :approved,
              :ip, :created, :edited_at, :edited_by)
             ON DUPLICATE KEY UPDATE
               content_html = VALUES(content_html),
               edited_at = VALUES(edited_at)'
        );

        $migrated = 0;
        for ($offset = 0; $offset < $total; $offset += self::BATCH_SIZE) {
            $stmt = $this->sourceIc5->prepare(
                "SELECT {$selectCols}
                 FROM `{$table}`
                 ORDER BY pid ASC
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue('limit', self::BATCH_SIZE, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $this->targetVf->beginTransaction();
            try {
                while ($row = $stmt->fetch()) {
                    $authorId = (int) ($row['author_id'] ?? 0);

                    $insert->execute([
                        'id'          => (int) $row['pid'],
                        'topic_id'    => (int) $row['topic_id'],
                        'author_id'   => $authorId > 0 ? $authorId : null,
                        'author_name' => mb_substr((string) ($row['author_name'] ?: 'Gość'), 0, 120),
                        'html'        => (string) ($row['post'] ?? ''),
                        'first'       => (int) ($row['new_topic'] ?? 0) === 1 ? 1 : 0,
                        'approved'    => (int) ($row['queued'] ?? 0) === 0 ? 1 : 0,
                        'ip'          => mb_substr((string) ($row['ip_address'] ?: '0.0.0.0'), 0, 45),
                        'created'     => max(0, (int) ($row['post_date'] ?? time())),
                        'edited_at'   => !empty($row['edit_time']) ? (int) $row['edit_time'] : null,
                        'edited_by'   => !empty($row['edit_name']) ? mb_substr((string) $row['edit_name'], 0, 120) : null,
                    ]);
                    $migrated++;
                }
                $this->targetVf->commit();
            } catch (\Throwable $e) {
                if ($this->targetVf->inTransaction()) {
                    $this->targetVf->rollBack();
                }
                throw $e;
            }
        }

        return $migrated;
    }

    private function migrateReactions(): int
    {
        $p = $this->prefix;
        $table = "{$p}core_reputation_index";
        $cols = $this->getTableColumns($this->sourceIc5, $table);
        if ($cols === []) {
            return 0;
        }

        $where = (isset($cols['app']) && isset($cols['type']))
            ? "WHERE app = 'forums' AND type = 'pid'"
            : '';

        $total = (int) $this->sourceIc5->query("SELECT COUNT(*) FROM `{$table}` {$where}")->fetchColumn();
        if ($total === 0) {
            return 0;
        }

        $selectCols = $this->buildSelectList($cols, [
            'id',
            'type_id',
            'member_id',
            'reaction',
            'rep_rating',
            'rep_date',
        ]);
        $orderBy = isset($cols['id']) ? 'ORDER BY id ASC' : '';

        $insert = $this->targetVf->prepare(
            'INSERT IGNORE INTO vf_post_reactions (post_id, member_id, reaction_id, created_at)
             VALUES (:pid, :mid, :rid, :at)'
        );

        $migrated = 0;
        for ($offset = 0; $offset < $total; $offset += self::BATCH_SIZE) {
            $stmt = $this->sourceIc5->prepare(
                "SELECT {$selectCols}
                 FROM `{$table}`
                 {$where}
                 {$orderBy}
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue('limit', self::BATCH_SIZE, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $this->targetVf->beginTransaction();
            try {
                while ($row = $stmt->fetch()) {
                    $postId = (int) ($row['type_id'] ?? 0);
                    $memberId = (int) ($row['member_id'] ?? 0);
                    if ($postId <= 0 || $memberId <= 0) {
                        continue;
                    }
                    $rawReaction = (int) ($row['reaction'] ?? $row['rep_rating'] ?? 1);
                    $reactionId = ($rawReaction >= 1 && $rawReaction <= 4) ? $rawReaction : 1;

                    $insert->execute([
                        'pid' => $postId,
                        'mid' => $memberId,
                        'rid' => $reactionId,
                        'at'  => max(0, (int) ($row['rep_date'] ?? time())),
                    ]);
                    $migrated++;
                }
                $this->targetVf->commit();
            } catch (\Throwable $e) {
                if ($this->targetVf->inTransaction()) {
                    $this->targetVf->rollBack();
                }
                throw $e;
            }
        }

        return $migrated;
    }

    private function repairAndSyncPostMigration(): int
    {
        $repaired = 0;

        $orphanedTopics = $this->targetVf->exec(
            'DELETE t FROM vf_topics t LEFT JOIN vf_forums f ON f.id = t.forum_id WHERE f.id IS NULL'
        );
        $orphanedPosts = $this->targetVf->exec(
            'DELETE p FROM vf_posts p LEFT JOIN vf_topics t ON t.id = p.topic_id WHERE t.id IS NULL'
        );
        $repaired += (int) $orphanedTopics + (int) $orphanedPosts;

        $nullAuthors = $this->targetVf->exec(
            'UPDATE vf_posts p LEFT JOIN vf_members m ON m.id = p.author_id
             SET p.author_id = NULL WHERE p.author_id IS NOT NULL AND m.id IS NULL'
        );
        $repaired += (int) $nullAuthors;

        // Przeliczamy dokładną liczbę postów w tematach (w IC5 pole posts często liczy tylko odpowiedzi bez pierwszego posta)
        $this->targetVf->exec(
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

        // Przeliczamy dokładną liczbę tematów, postów i ostatni temat w każdym dziale
        $this->targetVf->exec(
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

        $this->targetVf->exec(
            'UPDATE vf_forums f
             LEFT JOIN vf_topics t ON t.id = f.last_topic_id
             SET f.last_post_at = t.last_post_at,
                 f.last_poster_id = t.last_poster_id,
                 f.last_poster_name = t.last_poster_name'
        );

        return $repaired;
    }

    /** @return array<int, array{name: string, desc: string}> */
    private function loadForumLangWords(): array
    {
        $p = $this->prefix;
        $map = [];
        try {
            $rows = $this->sourceIc5->query(
                "SELECT word_key, COALESCE(NULLIF(word_custom, ''), word_default) AS val
                 FROM `{$p}core_sys_lang_words`
                 WHERE word_key LIKE 'forums_forum_%'
                 ORDER BY lang_id ASC"
            )->fetchAll();

            foreach ($rows as $row) {
                $key = (string) $row['word_key'];
                $val = (string) $row['val'];
                if (preg_match('/^forums_forum_(\d+)$/', $key, $m)) {
                    $id = (int) $m[1];
                    $map[$id]['name'] = $val;
                    $map[$id]['desc'] ??= '';
                } elseif (preg_match('/^forums_forum_(\d+)_desc$/', $key, $m)) {
                    $id = (int) $m[1];
                    $map[$id]['name'] ??= "Dział #{$id}";
                    $map[$id]['desc'] = $this->cleanDescriptionHtml($val);
                }
            }
        } catch (\Throwable) {
            // Brak tabeli tłumaczeń lub inne nazwy kolumn — użyjemy nazw z forums_forums
        }

        return $map;
    }

    private function cleanDescriptionHtml(string $html): string
    {
        $spaced = preg_replace('/<(?:br\s*\/?|\/p|\/div|\/li|\/tr|\/h[1-6])>/i', ' ', $html) ?? $html;
        $decoded = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }

    /** @return array<int, string> */
    private function loadGroupLangWords(): array
    {
        $p = $this->prefix;
        $map = [];
        try {
            $rows = $this->sourceIc5->query(
                "SELECT word_key, COALESCE(NULLIF(word_custom, ''), word_default) AS val
                 FROM `{$p}core_sys_lang_words`
                 WHERE word_key LIKE 'core_group_%'
                 ORDER BY lang_id ASC"
            )->fetchAll();

            foreach ($rows as $row) {
                if (preg_match('/^core_group_(\d+)$/', (string) $row['word_key'], $m)) {
                    $map[(int) $m[1]] = (string) $row['val'];
                }
            }
        } catch (\Throwable) {
            // Fallback do nazw domyślnych
        }
        return $map;
    }

    /** @return array<int, bool> */
    private function loadAdminGroupIds(): array
    {
        $p = $this->prefix;
        $ids = [];
        try {
            $rows = $this->sourceIc5->query(
                "SELECT row_id FROM `{$p}core_admin_permission_rows` WHERE row_id_type = 'group'"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $gid) {
                $id = (int) $gid;
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        } catch (\Throwable) {
            // Ignorujemy
        }
        return $ids;
    }

    /** @return array<int, bool> */
    private function loadSuperModGroupIds(): array
    {
        $p = $this->prefix;
        $ids = [];
        try {
            $rows = $this->sourceIc5->query(
                "SELECT id FROM `{$p}core_moderators` WHERE type = 'g' AND (forums = '*' OR forums = '-1')"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $gid) {
                $id = (int) $gid;
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        } catch (\Throwable) {
            // Ignorujemy
        }
        return $ids;
    }

    /** @return array<string, true> */
    private function getTableColumns(PDO $db, string $table): array
    {
        try {
            $cols = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
            $out = [];
            foreach ($cols as $c) {
                $out[strtolower((string) $c)] = true;
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, true> */
    private function requireTableColumns(string $table, string $baseName): array
    {
        $cols = $this->getTableColumns($this->sourceIc5, $table);
        if ($cols === []) {
            $all = [];
            try {
                $all = $this->sourceIc5->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            } catch (\Throwable) {
                // Ignorujemy
            }
            $hint = '';
            foreach ($all as $t) {
                $str = (string) $t;
                if (str_ends_with(strtolower($str), strtolower($baseName))) {
                    $detectedPrefix = substr($str, 0, strlen($str) - strlen($baseName));
                    $hint = $detectedPrefix !== ''
                        ? " Wykryto prefiks tabel w bazie IC5: „{$detectedPrefix}” — wpisz go w polu Prefiks tabel."
                        : '';
                    break;
                }
            }
            throw new \RuntimeException(
                "W bazie źródłowej IC5 nie znaleziono tabeli `{$table}`. Sprawdź nazwę bazy i pole Prefiks tabel.{$hint}"
            );
        }
        return $cols;
    }

    /** @param array<string, true> $available
     *  @param string[] $candidates */
    private function buildSelectList(array $available, array $candidates): string
    {
        $parts = [];
        foreach ($candidates as $col) {
            if (isset($available[strtolower($col)])) {
                $parts[] = "`{$col}`";
            } else {
                $parts[] = "NULL AS `{$col}`";
            }
        }
        return implode(', ', $parts);
    }

    private function safeCount(string $sql): int
    {
        try {
            return (int) $this->sourceIc5->query($sql)->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function recordLog(string $status, array $report, ?string $notes = null): void
    {
        $stmt = $this->targetVf->prepare(
            'INSERT INTO vf_migration_logs
             (source_engine, status, groups_migrated, members_migrated, forums_migrated,
              topics_migrated, posts_migrated, reactions_migrated, permissions_migrated, orphans_repaired, notes, executed_at)
             VALUES (:engine, :status, :g, :m, :f, :t, :p, :r, :perm, :o, :notes, :at)'
        );
        $stmt->execute([
            'engine' => 'invision_community_5',
            'status' => $status,
            'g'      => $report['groups'],
            'm'      => $report['members'],
            'f'      => $report['forums'],
            't'      => $report['topics'],
            'p'      => $report['posts'],
            'r'      => $report['reactions'],
            'perm'   => $report['permissions'] ?? 0,
            'o'      => $report['orphans_repaired'],
            'notes'  => $notes,
            'at'     => time(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace VeloForum\Core;

use PDO;

final class Database
{
    private static ?PDO $targetConnection = null;

    public static function connect(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        return new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], $options);
    }

    public static function target(array $cfg): PDO
    {
        if (self::$targetConnection === null) {
            self::$targetConnection = self::connect($cfg);
        }
        return self::$targetConnection;
    }

    /**
     * Weryfikuje obecność bazowych tabel (automatycznie importuje database/schema.sql, jeśli brakuje tabel)
     * oraz uzupełnia nowe tabele/kolumny przy aktualizacji plików przez FTP.
     */
    public static function ensureCompatibleSchema(PDO $db, ?string $schemaSqlPath = null): void
    {
        $targetVersion = '3.7';

        if (
            session_status() === PHP_SESSION_ACTIVE
            && ($_SESSION['_vf_schema_ver'] ?? '') === $targetVersion
            && (time() - (int) ($_SESSION['_vf_schema_ts'] ?? 0)) < 300
        ) {
            return;
        }

        try {
            $verStmt = $db->prepare("SELECT setting_value FROM vf_settings WHERE setting_key = 'schema_version' LIMIT 1");
            $verStmt->execute();
            if ((string) $verStmt->fetchColumn() === $targetVersion) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['_vf_schema_ver'] = $targetVersion;
                    $_SESSION['_vf_schema_ts'] = time();
                }
                return;
            }
        } catch (\Throwable) {
            // Brak tabeli vf_settings lub świeża instalacja — przechodzimy do pełnej weryfikacji
        }

        $requiredCore = ['vf_groups', 'vf_members', 'vf_forums', 'vf_topics', 'vf_posts'];
        $findMissingCore = static function () use ($db, $requiredCore): array {
            $missing = [];
            foreach ($requiredCore as $table) {
                try {
                    $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
                } catch (\Throwable) {
                    $missing[] = $table;
                }
            }
            return $missing;
        };

        $missing = $findMissingCore();
        $autoImportError = '';
        if ($missing !== [] && $schemaSqlPath !== null && is_file($schemaSqlPath) && is_readable($schemaSqlPath)) {
            try {
                $rawSql = (string) file_get_contents($schemaSqlPath);
                foreach (self::splitSqlStatements($rawSql) as $stmt) {
                    $db->exec($stmt);
                }
            } catch (\Throwable $e) {
                $autoImportError = $e->getMessage();
            }
            $missing = $findMissingCore();
        }

        if ($missing !== []) {
            $activeDb = '';
            $tablesPreview = '';
            try {
                $activeDb = (string) ($db->query('SELECT DATABASE()')->fetchColumn() ?: '');
                $allTables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                $tablesPreview = $allTables !== []
                    ? implode(', ', array_slice(array_map('strval', $allTables), 0, 20))
                    : '(brak tabel w bazie)';
            } catch (\Throwable) {
                // Pomijamy błędy inspekcji
            }

            $detail = 'Brakuje tabel: ' . implode(', ', $missing) . '.';
            if ($activeDb !== '') {
                $detail .= ' Połączona baza: ' . $activeDb . '.';
            }
            if ($tablesPreview !== '') {
                $detail .= ' Znalezione tabele: ' . $tablesPreview . '.';
            }
            if ($autoImportError !== '') {
                $detail .= ' Błąd auto-importu: ' . $autoImportError;
            }
            throw new \RuntimeException('SCHEMA_MISSING: ' . $detail);
        }

        $ddl = [
            "CREATE TABLE IF NOT EXISTS `vf_settings` (
              `setting_key` VARCHAR(64) NOT NULL,
              `setting_value` TEXT NOT NULL,
              `updated_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_announcements` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `title` VARCHAR(160) NOT NULL,
              `content` VARCHAR(600) NOT NULL,
              `tone` ENUM('info', 'success', 'warning') NOT NULL DEFAULT 'info',
              `placement` ENUM('home', 'all') NOT NULL DEFAULT 'home',
              `link_label` VARCHAR(80) NULL DEFAULT NULL,
              `link_url` VARCHAR(255) NULL DEFAULT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_topic_prefixes` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `label` VARCHAR(48) NOT NULL,
              `color_hex` VARCHAR(16) NOT NULL DEFAULT '#2563eb',
              `position` INT NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_profile_fields` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `field_key` VARCHAR(48) NOT NULL,
              `label` VARCHAR(80) NOT NULL,
              `placeholder` VARCHAR(120) NULL DEFAULT NULL,
              `show_in_post` TINYINT(1) NOT NULL DEFAULT 0,
              `position` INT NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_vf_pf_key` (`field_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_forum_permissions` (
              `forum_id` INT UNSIGNED NOT NULL,
              `group_id` INT UNSIGNED NOT NULL,
              `can_view` TINYINT(1) NOT NULL DEFAULT 1,
              `can_read` TINYINT(1) NOT NULL DEFAULT 1,
              `can_create_topic` TINYINT(1) NOT NULL DEFAULT 1,
              `can_reply` TINYINT(1) NOT NULL DEFAULT 1,
              `can_moderate` TINYINT(1) NOT NULL DEFAULT 0,
              PRIMARY KEY (`forum_id`, `group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_follows` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `target_type` ENUM('forum', 'topic') NOT NULL,
              `target_id` BIGINT UNSIGNED NOT NULL,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_vf_follow` (`target_type`, `target_id`, `member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_topic_bookmarks` (
              `topic_id` BIGINT UNSIGNED NOT NULL,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`topic_id`, `member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_post_mentions` (
              `post_id` BIGINT UNSIGNED NOT NULL,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`post_id`, `member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_topic_tags` (
              `topic_id` BIGINT UNSIGNED NOT NULL,
              `tag_slug` VARCHAR(64) NOT NULL,
              `tag_label` VARCHAR(64) NOT NULL,
              PRIMARY KEY (`topic_id`, `tag_slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_reports` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `post_id` BIGINT UNSIGNED NOT NULL,
              `topic_id` BIGINT UNSIGNED NOT NULL,
              `reporter_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `reporter_name` VARCHAR(120) NOT NULL,
              `category` ENUM('spam', 'harassment', 'offtopic', 'other') NOT NULL DEFAULT 'other',
              `reason` VARCHAR(500) NOT NULL,
              `status` ENUM('open', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
              `handled_by_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `handled_by_name` VARCHAR(120) NULL DEFAULT NULL,
              `resolution_note` VARCHAR(255) NULL DEFAULT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              `resolved_at` INT UNSIGNED NULL DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_mod_logs` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `moderator_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `moderator_name` VARCHAR(120) NOT NULL,
              `action` VARCHAR(64) NOT NULL,
              `target_type` ENUM('topic', 'post', 'member', 'report') NOT NULL,
              `target_id` BIGINT UNSIGNED NOT NULL,
              `details` VARCHAR(255) NOT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_polls` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `topic_id` BIGINT UNSIGNED NOT NULL,
              `question` VARCHAR(255) NOT NULL,
              `allows_multiple` TINYINT(1) NOT NULL DEFAULT 0,
              `closes_at` INT UNSIGNED NULL DEFAULT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_vf_polls_topic` (`topic_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_poll_options` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `poll_id` BIGINT UNSIGNED NOT NULL,
              `label` VARCHAR(180) NOT NULL,
              `votes_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `position` INT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_poll_votes` (
              `poll_id` BIGINT UNSIGNED NOT NULL,
              `option_id` BIGINT UNSIGNED NOT NULL,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `voted_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`poll_id`, `member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_migration_logs` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `source_engine` VARCHAR(64) NOT NULL DEFAULT 'invision_community_5',
              `status` ENUM('started', 'completed', 'failed') NOT NULL,
              `groups_migrated` INT UNSIGNED NOT NULL DEFAULT 0,
              `members_migrated` INT UNSIGNED NOT NULL DEFAULT 0,
              `forums_migrated` INT UNSIGNED NOT NULL DEFAULT 0,
              `topics_migrated` INT UNSIGNED NOT NULL DEFAULT 0,
              `posts_migrated` INT UNSIGNED NOT NULL DEFAULT 0,
              `reactions_migrated` INT UNSIGNED NOT NULL DEFAULT 0,
              `permissions_migrated` INT UNSIGNED NOT NULL DEFAULT 0,
              `orphans_repaired` INT UNSIGNED NOT NULL DEFAULT 0,
              `notes` TEXT NULL,
              `executed_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_presence` (
              `session_key` VARCHAR(80) NOT NULL,
              `member_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `username` VARCHAR(120) NULL DEFAULT NULL,
              `slug` VARCHAR(160) NULL DEFAULT NULL,
              `avatar_url` VARCHAR(512) NULL DEFAULT NULL,
              `group_color` VARCHAR(16) NULL DEFAULT NULL,
              `group_name` VARCHAR(120) NULL DEFAULT NULL,
              `page_type` VARCHAR(24) NOT NULL DEFAULT 'home',
              `topic_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `forum_id` INT UNSIGNED NULL DEFAULT NULL,
              `updated_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`session_key`),
              KEY `idx_vf_presence_updated` (`updated_at`, `member_id`),
              KEY `idx_vf_presence_topic` (`topic_id`, `updated_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_attachments` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `post_id` BIGINT UNSIGNED NOT NULL,
              `topic_id` BIGINT UNSIGNED NOT NULL,
              `uploader_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `original_name` VARCHAR(190) NOT NULL,
              `stored_path` VARCHAR(255) NOT NULL,
              `mime_type` VARCHAR(120) NOT NULL,
              `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
              `is_image` TINYINT(1) NOT NULL DEFAULT 0,
              `downloads_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_vf_att_post` (`post_id`),
              KEY `idx_vf_att_topic` (`topic_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_conversations` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `subject` VARCHAR(190) NOT NULL,
              `starter_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `last_message_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `last_message_at` INT UNSIGNED NOT NULL DEFAULT 0,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_vf_conv_last_at` (`last_message_at` DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_conversation_participants` (
              `conversation_id` BIGINT UNSIGNED NOT NULL,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `last_read_at` INT UNSIGNED NOT NULL DEFAULT 0,
              `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
              `joined_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`conversation_id`, `member_id`),
              KEY `idx_vf_cp_member` (`member_id`, `is_deleted`, `last_read_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_messages` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `conversation_id` BIGINT UNSIGNED NOT NULL,
              `sender_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `sender_name` VARCHAR(120) NOT NULL,
              `content_html` MEDIUMTEXT NOT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_vf_msg_conv_created` (`conversation_id`, `created_at` ASC, `id` ASC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_achievements` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `code` VARCHAR(64) NOT NULL,
              `title` VARCHAR(120) NOT NULL,
              `description` VARCHAR(320) NOT NULL DEFAULT '',
              `icon` VARCHAR(32) NOT NULL DEFAULT 'trophy',
              `color_hex` VARCHAR(16) NOT NULL DEFAULT '#2563eb',
              `rule_type` ENUM('manual', 'posts_count', 'topics_count', 'reputation_points', 'solved_count') NOT NULL DEFAULT 'manual',
              `rule_threshold` INT UNSIGNED NOT NULL DEFAULT 1,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `position` INT NOT NULL DEFAULT 0,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_vf_ach_code` (`code`),
              KEY `idx_vf_ach_active_pos` (`is_active`, `position`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_member_achievements` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `achievement_id` INT UNSIGNED NOT NULL,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `awarded_by_id` BIGINT UNSIGNED NULL DEFAULT NULL,
              `awarded_by_name` VARCHAR(120) NULL DEFAULT NULL,
              `note` VARCHAR(255) NULL DEFAULT NULL,
              `awarded_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_vf_ma_member_ach` (`achievement_id`, `member_id`),
              KEY `idx_vf_ma_member` (`member_id`, `awarded_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_chat_messages` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `username` VARCHAR(120) NOT NULL,
              `body_html` TEXT NOT NULL,
              `attachment_url` VARCHAR(512) NULL DEFAULT NULL,
              `is_action` TINYINT(1) NOT NULL DEFAULT 0,
              `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
              `reply_to_username` VARCHAR(120) NULL DEFAULT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_vf_chat_created` (`created_at` DESC, `id` DESC),
              KEY `idx_vf_chat_pinned` (`is_pinned`, `id` DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `vf_chat_reactions` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `message_id` BIGINT UNSIGNED NOT NULL,
              `member_id` BIGINT UNSIGNED NOT NULL,
              `emoji` VARCHAR(16) NOT NULL,
              `created_at` INT UNSIGNED NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_vf_chat_react` (`message_id`, `member_id`, `emoji`),
              KEY `idx_vf_chat_react_msg` (`message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($ddl as $sql) {
            try {
                $db->exec($sql);
            } catch (\Throwable) {
                // Pomijamy jeśli tabela już istnieje lub brak uprawnień DDL
            }
        }

        $ensureColumn = static function (string $table, string $column, string $definition) use ($db): bool {
            try {
                $colCheck = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE :col");
                $colCheck->execute(['col' => $column]);
                if (!$colCheck->fetch()) {
                    try {
                        $db->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
                    } catch (\Throwable) {
                        $withoutAfter = preg_replace('/\s+AFTER\s+`[^`]+`\s*$/i', '', $definition) ?? $definition;
                        $db->exec("ALTER TABLE `{$table}` ADD COLUMN {$withoutAfter}");
                    }
                    return true;
                }
            } catch (\Throwable) {
                // Ignorujemy jeśli kolumna już istnieje
            }
            return false;
        };

        $ensureColumn('vf_topics', 'prefix_id', '`prefix_id` INT UNSIGNED NULL DEFAULT NULL AFTER `forum_id`');
        $addedFeatured = $ensureColumn('vf_topics', 'is_featured', '`is_featured` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_pinned`');
        if ($addedFeatured) {
            try {
                $db->exec('UPDATE `vf_topics` SET `is_featured` = 1 WHERE `is_pinned` = 1');
            } catch (\Throwable) {
            }
        }
        $ensureColumn('vf_members', 'avatar_url', '`avatar_url` VARCHAR(512) NULL DEFAULT NULL AFTER `secondary_group_ids`');
        $ensureColumn('vf_members', 'cover_url', '`cover_url` VARCHAR(512) NULL DEFAULT NULL AFTER `avatar_url`');
        $ensureColumn('vf_members', 'music_url', '`music_url` VARCHAR(512) NULL DEFAULT NULL AFTER `cover_url`');
        $ensureColumn('vf_members', 'chat_muted_until', '`chat_muted_until` INT UNSIGNED NULL DEFAULT NULL AFTER `banned_until`');
        $ensureColumn('vf_members', 'custom_fields', '`custom_fields` JSON NULL AFTER `signature`');
        $ensureColumn('vf_members', 'bio', '`bio` TEXT NULL AFTER `member_title`');
        $ensureColumn('vf_members', 'signature', '`signature` VARCHAR(400) NULL DEFAULT NULL AFTER `bio`');
        $ensureColumn('vf_members', 'warning_points', '`warning_points` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `reputation_points`');
        $ensureColumn('vf_members', 'ban_reason', '`ban_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `is_banned`');
        $ensureColumn('vf_members', 'banned_until', '`banned_until` INT UNSIGNED NULL DEFAULT NULL AFTER `ban_reason`');
        $ensureColumn('vf_groups', 'description', '`description` TEXT NULL AFTER `name`');
        $ensureColumn('vf_groups', 'show_in_legend', '`show_in_legend` TINYINT(1) NOT NULL DEFAULT 1 AFTER `color_hex`');
        $ensureColumn('vf_groups', 'can_create_polls', '`can_create_polls` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_post`');
        $ensureColumn('vf_groups', 'can_edit_own', '`can_edit_own` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_create_polls`');
        $ensureColumn('vf_groups', 'can_report', '`can_report` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_edit_own`');

        if ($ensureColumn('vf_groups', 'can_set_profile_cover', '`can_set_profile_cover` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_report`')) {
            try {
                $db->exec("UPDATE `vf_groups` SET `can_set_profile_cover` = 1 WHERE `is_admin` = 1 OR `is_moderator` = 1");
            } catch (\Throwable) {
            }
        }
        if ($ensureColumn('vf_groups', 'can_set_profile_music', '`can_set_profile_music` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_set_profile_cover`')) {
            try {
                $db->exec("UPDATE `vf_groups` SET `can_set_profile_music` = 1 WHERE `is_admin` = 1 OR `is_moderator` = 1");
            } catch (\Throwable) {
            }
        }
        if ($ensureColumn('vf_groups', 'can_use_chat', '`can_use_chat` TINYINT(1) NOT NULL DEFAULT 1 AFTER `can_set_profile_music`')) {
            try {
                $db->exec("UPDATE `vf_groups` SET `can_use_chat` = 0 WHERE `id` IN (1, 2)");
            } catch (\Throwable) {
            }
        }
        if ($ensureColumn('vf_groups', 'can_moderate_chat', '`can_moderate_chat` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_use_chat`')) {
            try {
                $db->exec("UPDATE `vf_groups` SET `can_moderate_chat` = 1 WHERE `is_admin` = 1 OR `is_moderator` = 1");
            } catch (\Throwable) {
            }
        }

        $ensureColumn('vf_groups', 'bypass_flood', '`bypass_flood` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_moderate_chat`');
        $ensureColumn('vf_groups', 'flood_seconds', '`flood_seconds` INT UNSIGNED NOT NULL DEFAULT 30 AFTER `bypass_flood`');
        $ensureColumn('vf_forums', 'is_locked', '`is_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_view_others`');
        $ensureColumn('vf_forums', 'allow_polls', '`allow_polls` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_locked`');
        $ensureColumn('vf_forums', 'allow_best_answer', '`allow_best_answer` TINYINT(1) NOT NULL DEFAULT 1 AFTER `allow_polls`');
        $ensureColumn('vf_forums', 'require_approval', '`require_approval` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allow_best_answer`');
        $ensureColumn('vf_posts', 'mod_note', '`mod_note` TEXT NULL DEFAULT NULL AFTER `edited_by_name`');
        $ensureColumn('vf_posts', 'mod_note_author_id', '`mod_note_author_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `mod_note`');
        $ensureColumn('vf_posts', 'mod_note_author', '`mod_note_author` VARCHAR(120) NULL DEFAULT NULL AFTER `mod_note_author_id`');
        $ensureColumn('vf_posts', 'mod_note_at', '`mod_note_at` INT UNSIGNED NULL DEFAULT NULL AFTER `mod_note_author`');

        try {
            $achCount = (int) $db->query('SELECT COUNT(*) FROM `vf_achievements`')->fetchColumn();
            if ($achCount === 0) {
                $nowAch = time();
                $seedStmt = $db->prepare(
                    'INSERT IGNORE INTO `vf_achievements`
                     (`code`, `title`, `description`, `icon`, `color_hex`, `rule_type`, `rule_threshold`, `is_active`, `position`, `created_at`)
                     VALUES (:code, :title, :descr, :icon, :color, :rtype, :rthresh, 1, :pos, :at)'
                );
                $defaultAchievements = [
                    ['first-steps', 'Pierwsze kroki', 'Opublikowano pierwszy wpis na forum.', 'star', '#2563eb', 'posts_count', 1, 1],
                    ['active-voice', 'Aktywny dyskutant', 'Opublikowano co najmniej 50 odpowiedzi i postów.', 'flame', '#ea580c', 'posts_count', 50, 2],
                    ['topic-creator', 'Kreator dyskusji', 'Założono minimum 5 wartościowych tematów.', 'zap', '#7c3aed', 'topics_count', 5, 3],
                    ['authority', 'Autorytet społeczności', 'Zdobyto minimum 25 punktów reputacji.', 'trophy', '#d97706', 'reputation_points', 25, 4],
                    ['problem-solver', 'Mistrz rozwiązań', 'Co najmniej 3 odpowiedzi oznaczone jako najlepsze.', 'shield', '#059669', 'solved_count', 3, 5],
                    ['community-pioneer', 'Pionier społeczności', 'Wyróżnienie nadawane ręcznie przez administrację za szczególny wkład.', 'crown', '#dc2626', 'manual', 1, 6],
                ];
                foreach ($defaultAchievements as $ach) {
                    $seedStmt->execute([
                        'code'    => $ach[0],
                        'title'   => $ach[1],
                        'descr'   => $ach[2],
                        'icon'    => $ach[3],
                        'color'   => $ach[4],
                        'rtype'   => $ach[5],
                        'rthresh' => $ach[6],
                        'pos'     => $ach[7],
                        'at'      => $nowAch,
                    ]);
                }
            }
        } catch (\Throwable) {
            // Ignorujemy błąd seedowania osiągnięć
        }

        $ensureIndex = static function (string $table, string $indexName, string $columnsSql) use ($db): void {
            try {
                $stmt = $db->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = :k");
                $stmt->execute(['k' => $indexName]);
                if (!$stmt->fetch()) {
                    $db->exec("CREATE INDEX `{$indexName}` ON `{$table}` ({$columnsSql})");
                }
            } catch (\Throwable) {
                // Pomijamy brak uprawnień lub istniejący indeks
            }
        };

        try {
            $nowChat = time();
            $chatDefaults = [
                'chat_enabled'         => '1',
                'chat_title'           => 'Czat społeczności',
                'chat_announcement'    => 'Witaj na czacie na żywo! Używaj @nick aby oznaczyć, /me dla akcji lub dodawaj GIF-y z Klipy.com.',
                'chat_position'        => 'top',
                'chat_max_length'      => '400',
                'chat_flood_seconds'   => '3',
                'chat_allow_guests'    => '1',
                'chat_allow_gif'       => '1',
                'chat_allow_reactions' => '1',
            ];
            $setStmt = $db->prepare('INSERT IGNORE INTO `vf_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES (:k, :v, :at)');
            foreach ($chatDefaults as $ck => $cv) {
                $setStmt->execute(['k' => $ck, 'v' => $cv, 'at' => $nowChat]);
            }
        } catch (\Throwable) {
            // Ignorujemy
        }

        $ensureIndex('vf_topics', 'idx_vf_topics_approved_last', '`is_approved`, `last_post_at`');
        $ensureIndex('vf_topics', 'idx_vf_topics_pinned_last', '`is_approved`, `is_pinned`, `last_post_at`');
        $ensureIndex('vf_topics', 'idx_vf_topics_featured', '`is_approved`, `is_featured`, `last_post_at`');
        $ensureIndex('vf_posts', 'idx_vf_posts_approved_created', '`is_approved`, `created_at`');
        $ensureIndex('vf_post_reactions', 'idx_vf_pr_created', '`created_at`');
        $ensureIndex('vf_members', 'idx_vf_members_rep', '`is_banned`, `reputation_points`');
        $ensureIndex('vf_members', 'idx_vf_members_activity', '`is_banned`, `last_activity_at`');
        $ensureIndex('vf_chat_messages', 'idx_vf_chat_created', '`created_at` DESC, `id` DESC');
        $ensureIndex('vf_chat_messages', 'idx_vf_chat_pinned', '`is_pinned`, `id` DESC');

        try {
            $hasTopics = ((int) $db->query('SELECT COUNT(*) FROM vf_topics')->fetchColumn()) > 0;
            if ($hasTopics) {
                $db->exec(
                    'UPDATE vf_topics t
                     LEFT JOIN (
                         SELECT topic_id, COUNT(*) AS total_posts, MIN(id) AS first_pid, MAX(id) AS last_pid, MAX(created_at) AS max_created
                         FROM vf_posts WHERE is_approved = 1 GROUP BY topic_id
                     ) ps ON ps.topic_id = t.id
                     SET t.posts_count = GREATEST(1, COALESCE(ps.total_posts, 0)),
                         t.first_post_id = COALESCE(t.first_post_id, ps.first_pid),
                         t.last_post_id = COALESCE(ps.last_pid, t.last_post_id),
                         t.last_post_at = GREATEST(t.last_post_at, COALESCE(ps.max_created, 0))'
                );
                $db->exec(
                    'UPDATE vf_forums f
                     LEFT JOIN (
                         SELECT forum_id, COUNT(*) AS total_topics, COALESCE(SUM(posts_count), 0) AS total_posts
                         FROM vf_topics WHERE is_approved = 1 GROUP BY forum_id
                     ) ts ON ts.forum_id = f.id
                     SET f.topics_count = COALESCE(ts.total_topics, 0),
                         f.posts_count = COALESCE(ts.total_posts, 0),
                         f.last_topic_id = (
                             SELECT t.id FROM vf_topics t
                             WHERE t.forum_id = f.id AND t.is_approved = 1
                             ORDER BY t.last_post_at DESC, t.id DESC LIMIT 1
                         )'
                );
                $db->exec(
                    'UPDATE vf_forums f
                     LEFT JOIN vf_topics t ON t.id = f.last_topic_id
                     SET f.last_post_at = t.last_post_at,
                         f.last_poster_id = t.last_poster_id,
                         f.last_poster_name = t.last_poster_name'
                );
            }
        } catch (\Throwable) {
            // Ignorujemy jeśli synchronizacja liczników nie powiodła się
        }

        try {
            $now = time();
            $db->prepare(
                "INSERT IGNORE INTO vf_settings (setting_key, setting_value, updated_at) VALUES ('schema_version', :ver, :at)"
            )->execute(['ver' => $targetVersion, 'at' => $now]);
            $db->prepare(
                "UPDATE vf_settings SET setting_value = :ver, updated_at = :at WHERE setting_key = 'schema_version'"
            )->execute(['ver' => $targetVersion, 'at' => $now]);
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['_vf_schema_ver'] = $targetVersion;
                $_SESSION['_vf_schema_ts'] = $now;
            }
        } catch (\Throwable) {
            // Ignorujemy jeśli zapis wersji się nie powiódł
        }
    }

    /** @return array<int, string> */
    private static function splitSqlStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($ch === '-' && $next === '-') {
                    while ($i < $len && $sql[$i] !== "\n") {
                        $i++;
                    }
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $i += 2;
                    while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                        $i++;
                    }
                    $i++;
                    continue;
                }
                if ($ch === ';') {
                    $trimmed = trim($buffer);
                    if ($trimmed !== '') {
                        $statements[] = $trimmed;
                    }
                    $buffer = '';
                    continue;
                }
            }

            if ($ch === '\\' && ($inSingle || $inDouble)) {
                $buffer .= $ch;
                if ($i + 1 < $len) {
                    $buffer .= $sql[++$i];
                }
                continue;
            }

            if ($ch === "'" && !$inDouble && !$inBacktick) {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && !$inBacktick) {
                $inDouble = !$inDouble;
            } elseif ($ch === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            $buffer .= $ch;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}

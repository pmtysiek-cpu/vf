-- ============================================================================
-- VeloForum PHP 8.3+ — Pełny schemat relacyjny MySQL 8.0 / MariaDB 10.11
-- Projektowany z myślą o 100% zgodności migracji z Invision Community 5
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `vf_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `prefix_html` VARCHAR(255) NOT NULL DEFAULT '',
  `suffix_html` VARCHAR(255) NOT NULL DEFAULT '',
  `color_hex` VARCHAR(16) NOT NULL DEFAULT '#475569',
  `show_in_legend` TINYINT(1) NOT NULL DEFAULT 1,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `is_moderator` TINYINT(1) NOT NULL DEFAULT 0,
  `can_post` TINYINT(1) NOT NULL DEFAULT 1,
  `can_create_polls` TINYINT(1) NOT NULL DEFAULT 1,
  `can_edit_own` TINYINT(1) NOT NULL DEFAULT 1,
  `can_report` TINYINT(1) NOT NULL DEFAULT 1,
  `can_set_profile_cover` TINYINT(1) NOT NULL DEFAULT 0,
  `can_set_profile_music` TINYINT(1) NOT NULL DEFAULT 0,
  `can_use_chat` TINYINT(1) NOT NULL DEFAULT 1,
  `can_moderate_chat` TINYINT(1) NOT NULL DEFAULT 0,
  `bypass_flood` TINYINT(1) NOT NULL DEFAULT 0,
  `flood_seconds` INT UNSIGNED NOT NULL DEFAULT 30,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `legacy_salt` VARCHAR(64) NULL DEFAULT NULL,
  `legacy_algo` ENUM('bcrypt', 'ips3_md5', 'argon2id') NOT NULL DEFAULT 'bcrypt',
  `primary_group_id` INT UNSIGNED NOT NULL DEFAULT 3,
  `secondary_group_ids` JSON NULL,
  `avatar_url` VARCHAR(512) NULL DEFAULT NULL,
  `cover_url` VARCHAR(512) NULL DEFAULT NULL,
  `music_url` VARCHAR(512) NULL DEFAULT NULL,
  `chat_muted_until` INT UNSIGNED NULL DEFAULT NULL,
  `member_title` VARCHAR(120) NULL DEFAULT NULL,
  `bio` TEXT NULL,
  `signature` VARCHAR(400) NULL DEFAULT NULL,
  `custom_fields` JSON NULL,
  `posts_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `reputation_points` INT NOT NULL DEFAULT 0,
  `warning_points` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `joined_at` INT UNSIGNED NOT NULL,
  `last_activity_at` INT UNSIGNED NULL DEFAULT NULL,
  `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
  `ban_reason` VARCHAR(255) NULL DEFAULT NULL,
  `banned_until` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vf_members_username` (`username`),
  UNIQUE KEY `uniq_vf_members_email` (`email`),
  KEY `idx_vf_members_group` (`primary_group_id`),
  CONSTRAINT `fk_vf_members_group`
    FOREIGN KEY (`primary_group_id`) REFERENCES `vf_groups` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Samoreferencyjna tabela kategorii, działów i zagnieżdżonych podkategorii
CREATE TABLE IF NOT EXISTS `vf_forums` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL DEFAULT NULL,
  `materialized_path` VARCHAR(255) NOT NULL DEFAULT '',
  `depth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `is_category` TINYINT(1) NOT NULL DEFAULT 0,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `seo_title` VARCHAR(255) NULL DEFAULT NULL,
  `seo_description` VARCHAR(320) NULL DEFAULT NULL,
  `position` INT NOT NULL DEFAULT 0,
  `topics_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `posts_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_topic_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `last_post_at` INT UNSIGNED NULL DEFAULT NULL,
  `last_poster_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `last_poster_name` VARCHAR(120) NULL DEFAULT NULL,
  `redirect_url` VARCHAR(512) NULL DEFAULT NULL,
  `can_view_others` TINYINT(1) NOT NULL DEFAULT 1,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_polls` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_best_answer` TINYINT(1) NOT NULL DEFAULT 1,
  `require_approval` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_vf_forums_parent` (`parent_id`, `position`),
  KEY `idx_vf_forums_path` (`materialized_path`),
  CONSTRAINT `fk_vf_forums_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `vf_forums` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_topics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `forum_id` INT UNSIGNED NOT NULL,
  `prefix_id` INT UNSIGNED NULL DEFAULT NULL,
  `author_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `author_name` VARCHAR(120) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `posts_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
  `first_post_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `last_post_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `best_answer_post_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `last_post_at` INT UNSIGNED NOT NULL,
  `last_poster_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `last_poster_name` VARCHAR(120) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vf_topics_forum_sort` (`forum_id`, `is_approved`, `is_pinned`, `last_post_at`),
  KEY `idx_vf_topics_featured` (`is_approved`, `is_featured`, `last_post_at`),
  KEY `idx_vf_topics_prefix` (`prefix_id`),
  KEY `idx_vf_topics_author` (`author_id`),
  CONSTRAINT `fk_vf_topics_forum`
    FOREIGN KEY (`forum_id`) REFERENCES `vf_forums` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_topics_author`
    FOREIGN KEY (`author_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_id` BIGINT UNSIGNED NOT NULL,
  `author_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `author_name` VARCHAR(120) NOT NULL,
  `content_html` MEDIUMTEXT NOT NULL,
  `is_first_post` TINYINT(1) NOT NULL DEFAULT 0,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '0.0.0.0',
  `created_at` INT UNSIGNED NOT NULL,
  `edited_at` INT UNSIGNED NULL DEFAULT NULL,
  `edited_by_name` VARCHAR(120) NULL DEFAULT NULL,
  `mod_note` TEXT NULL DEFAULT NULL,
  `mod_note_author_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `mod_note_author` VARCHAR(120) NULL DEFAULT NULL,
  `mod_note_at` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vf_posts_topic_date` (`topic_id`, `is_approved`, `created_at`),
  KEY `idx_vf_posts_author` (`author_id`),
  CONSTRAINT `fk_vf_posts_topic`
    FOREIGN KEY (`topic_id`) REFERENCES `vf_topics` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_posts_author`
    FOREIGN KEY (`author_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_reactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL,
  `label` VARCHAR(64) NOT NULL,
  `points` TINYINT NOT NULL DEFAULT 1,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vf_reactions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_post_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `reaction_id` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vf_post_member_reaction` (`post_id`, `member_id`),
  KEY `idx_vf_post_reactions_post` (`post_id`),
  CONSTRAINT `fk_vf_pr_post`
    FOREIGN KEY (`post_id`) REFERENCES `vf_posts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_pr_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_pr_reaction`
    FOREIGN KEY (`reaction_id`) REFERENCES `vf_reactions` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_id` BIGINT UNSIGNED NOT NULL,
  `actor_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `actor_name` VARCHAR(120) NOT NULL,
  `type` ENUM('reply', 'reaction', 'mention', 'system') NOT NULL DEFAULT 'reply',
  `topic_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `post_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `message` VARCHAR(255) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vf_notif_recipient_read` (`recipient_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_vf_notif_recipient`
    FOREIGN KEY (`recipient_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_notif_actor`
    FOREIGN KEY (`actor_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_vf_notif_topic`
    FOREIGN KEY (`topic_id`) REFERENCES `vf_topics` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_notif_post`
    FOREIGN KEY (`post_id`) REFERENCES `vf_posts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_forum_permissions` (
  `forum_id` INT UNSIGNED NOT NULL,
  `group_id` INT UNSIGNED NOT NULL,
  `can_view` TINYINT(1) NOT NULL DEFAULT 1,
  `can_read` TINYINT(1) NOT NULL DEFAULT 1,
  `can_create_topic` TINYINT(1) NOT NULL DEFAULT 1,
  `can_reply` TINYINT(1) NOT NULL DEFAULT 1,
  `can_moderate` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`forum_id`, `group_id`),
  CONSTRAINT `fk_vf_perm_forum`
    FOREIGN KEY (`forum_id`) REFERENCES `vf_forums` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_perm_group`
    FOREIGN KEY (`group_id`) REFERENCES `vf_groups` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_follows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `target_type` ENUM('forum', 'topic') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vf_follow` (`target_type`, `target_id`, `member_id`),
  KEY `idx_vf_follow_target` (`target_type`, `target_id`),
  CONSTRAINT `fk_vf_follow_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_migration_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_settings` (
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_polls` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_id` BIGINT UNSIGNED NOT NULL,
  `question` VARCHAR(255) NOT NULL,
  `allows_multiple` TINYINT(1) NOT NULL DEFAULT 0,
  `closes_at` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vf_polls_topic` (`topic_id`),
  CONSTRAINT `fk_vf_polls_topic`
    FOREIGN KEY (`topic_id`) REFERENCES `vf_topics` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_poll_options` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `poll_id` BIGINT UNSIGNED NOT NULL,
  `label` VARCHAR(180) NOT NULL,
  `votes_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `position` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_vf_poll_opt_poll`
    FOREIGN KEY (`poll_id`) REFERENCES `vf_polls` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_poll_votes` (
  `poll_id` BIGINT UNSIGNED NOT NULL,
  `option_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `voted_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`poll_id`, `member_id`),
  CONSTRAINT `fk_vf_pv_poll`
    FOREIGN KEY (`poll_id`) REFERENCES `vf_polls` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_pv_option`
    FOREIGN KEY (`option_id`) REFERENCES `vf_poll_options` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_pv_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_topic_bookmarks` (
  `topic_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`topic_id`, `member_id`),
  CONSTRAINT `fk_vf_tb_topic`
    FOREIGN KEY (`topic_id`) REFERENCES `vf_topics` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_tb_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_post_mentions` (
  `post_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`post_id`, `member_id`),
  KEY `idx_vf_pm_member` (`member_id`),
  CONSTRAINT `fk_vf_pm_post`
    FOREIGN KEY (`post_id`) REFERENCES `vf_posts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_pm_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_topic_tags` (
  `topic_id` BIGINT UNSIGNED NOT NULL,
  `tag_slug` VARCHAR(64) NOT NULL,
  `tag_label` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`topic_id`, `tag_slug`),
  KEY `idx_vf_tt_slug` (`tag_slug`),
  CONSTRAINT `fk_vf_tt_topic`
    FOREIGN KEY (`topic_id`) REFERENCES `vf_topics` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_reports` (
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
  PRIMARY KEY (`id`),
  KEY `idx_vf_reports_status` (`status`, `created_at`),
  CONSTRAINT `fk_vf_rep_post`
    FOREIGN KEY (`post_id`) REFERENCES `vf_posts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_rep_topic`
    FOREIGN KEY (`topic_id`) REFERENCES `vf_topics` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_mod_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `moderator_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `moderator_name` VARCHAR(120) NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `target_type` ENUM('topic', 'post', 'member', 'report') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `details` VARCHAR(255) NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vf_mod_logs_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(160) NOT NULL,
  `content` VARCHAR(600) NOT NULL,
  `tone` ENUM('info', 'success', 'warning') NOT NULL DEFAULT 'info',
  `placement` ENUM('home', 'all') NOT NULL DEFAULT 'home',
  `link_label` VARCHAR(80) NULL DEFAULT NULL,
  `link_url` VARCHAR(255) NULL DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vf_ann_active` (`is_active`, `placement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_topic_prefixes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(48) NOT NULL,
  `color_hex` VARCHAR(16) NOT NULL DEFAULT '#2563eb',
  `position` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_vf_tp_pos` (`position`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_profile_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `field_key` VARCHAR(48) NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `placeholder` VARCHAR(120) NULL DEFAULT NULL,
  `show_in_post` TINYINT(1) NOT NULL DEFAULT 0,
  `position` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vf_pf_key` (`field_key`),
  KEY `idx_vf_pf_pos` (`position`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_presence` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_attachments` (
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
  KEY `idx_vf_att_topic` (`topic_id`),
  CONSTRAINT `fk_vf_att_post`
    FOREIGN KEY (`post_id`) REFERENCES `vf_posts` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_att_topic`
    FOREIGN KEY (`topic_id`) REFERENCES `vf_topics` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `subject` VARCHAR(190) NOT NULL,
  `starter_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `last_message_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `last_message_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vf_conv_last_at` (`last_message_at` DESC),
  CONSTRAINT `fk_vf_conv_starter`
    FOREIGN KEY (`starter_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_conversation_participants` (
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `last_read_at` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `joined_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`conversation_id`, `member_id`),
  KEY `idx_vf_cp_member` (`member_id`, `is_deleted`, `last_read_at`),
  CONSTRAINT `fk_vf_cp_conv`
    FOREIGN KEY (`conversation_id`) REFERENCES `vf_conversations` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_cp_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `sender_name` VARCHAR(120) NOT NULL,
  `content_html` MEDIUMTEXT NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vf_msg_conv_created` (`conversation_id`, `created_at` ASC, `id` ASC),
  CONSTRAINT `fk_vf_msg_conv`
    FOREIGN KEY (`conversation_id`) REFERENCES `vf_conversations` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_msg_sender`
    FOREIGN KEY (`sender_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_achievements` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_member_achievements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `achievement_id` INT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `awarded_by_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `awarded_by_name` VARCHAR(120) NULL DEFAULT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `awarded_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vf_ma_member_ach` (`achievement_id`, `member_id`),
  KEY `idx_vf_ma_member` (`member_id`, `awarded_at`),
  CONSTRAINT `fk_vf_ma_ach`
    FOREIGN KEY (`achievement_id`) REFERENCES `vf_achievements` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_ma_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_chat_messages` (
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
  KEY `idx_vf_chat_pinned` (`is_pinned`, `id` DESC),
  CONSTRAINT `fk_vf_chat_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vf_chat_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `emoji` VARCHAR(16) NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vf_chat_react` (`message_id`, `member_id`, `emoji`),
  KEY `idx_vf_chat_react_msg` (`message_id`),
  CONSTRAINT `fk_vf_cr_msg`
    FOREIGN KEY (`message_id`) REFERENCES `vf_chat_messages` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_vf_cr_member`
    FOREIGN KEY (`member_id`) REFERENCES `vf_members` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `vf_groups` (`id`, `name`, `description`, `color_hex`, `show_in_legend`, `is_admin`, `is_moderator`, `can_post`, `can_create_polls`, `can_edit_own`, `can_report`, `can_set_profile_cover`, `can_set_profile_music`, `can_use_chat`, `can_moderate_chat`, `bypass_flood`, `flood_seconds`, `created_at`) VALUES
  (1, 'Oczekujący', 'Konta oczekujące na pełną aktywację.', '#64748b', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 60, UNIX_TIMESTAMP()),
  (2, 'Goście', 'Niezalogowani odwiedzający forum.', '#94a3b8', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 60, UNIX_TIMESTAMP()),
  (3, 'Użytkownicy', 'Standardowi zarejestrowani członkowie społeczności.', '#2563eb', 1, 0, 0, 1, 1, 1, 1, 0, 0, 1, 0, 0, 30, UNIX_TIMESTAMP()),
  (4, 'Administratorzy', 'Pełny dostęp do ACP i wszystkich działów.', '#dc2626', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, UNIX_TIMESTAMP()),
  (6, 'Moderatorzy', 'Globalna moderacja wątków i zgłoszeń.', '#059669', 1, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, UNIX_TIMESTAMP());

INSERT IGNORE INTO `vf_achievements` (`id`, `code`, `title`, `description`, `icon`, `color_hex`, `rule_type`, `rule_threshold`, `is_active`, `position`, `created_at`) VALUES
  (1, 'first-steps', 'Pierwsze kroki', 'Opublikowano pierwszy wpis na forum.', 'star', '#2563eb', 'posts_count', 1, 1, 1, UNIX_TIMESTAMP()),
  (2, 'active-voice', 'Aktywny dyskutant', 'Opublikowano co najmniej 50 odpowiedzi i postów.', 'flame', '#ea580c', 'posts_count', 50, 1, 2, UNIX_TIMESTAMP()),
  (3, 'topic-creator', 'Kreator dyskusji', 'Założono minimum 5 wartościowych tematów.', 'zap', '#7c3aed', 'topics_count', 5, 1, 3, UNIX_TIMESTAMP()),
  (4, 'authority', 'Autorytet społeczności', 'Zdobyto minimum 25 punktów reputacji.', 'trophy', '#d97706', 'reputation_points', 25, 1, 4, UNIX_TIMESTAMP()),
  (5, 'problem-solver', 'Mistrz rozwiązań', 'Co najmniej 3 odpowiedzi oznaczone jako najlepsze.', 'shield', '#059669', 'solved_count', 3, 1, 5, UNIX_TIMESTAMP()),
  (6, 'community-pioneer', 'Pionier społeczności', 'Wyróżnienie nadawane ręcznie przez administrację za szczególny wkład.', 'crown', '#dc2626', 'manual', 1, 1, 6, UNIX_TIMESTAMP());

INSERT IGNORE INTO `vf_reactions` (`id`, `code`, `label`, `points`, `position`) VALUES
  (1, 'like', 'Lubię to', 1, 1),
  (2, 'thanks', 'Dzięki', 1, 2),
  (3, 'helpful', 'Pomocne', 2, 3),
  (4, 'insightful', 'Cenne', 1, 4);

INSERT IGNORE INTO `vf_topic_prefixes` (`id`, `label`, `color_hex`, `position`) VALUES
  (1, 'Poradnik', '#2563eb', 1),
  (2, 'Ważne', '#dc2626', 2),
  (3, 'Dyskusja', '#059669', 3),
  (4, 'Rozwiązane', '#7c3aed', 4);

INSERT IGNORE INTO `vf_profile_fields` (`id`, `field_key`, `label`, `placeholder`, `show_in_post`, `position`) VALUES
  (1, 'location', 'Lokalizacja', 'np. Warszawa', 1, 1),
  (2, 'github', 'GitHub / WWW', 'https://github.com/nick', 0, 2),
  (3, 'specialty', 'Specjalizacja', 'np. PHP 8.3 / UI', 1, 3);

INSERT IGNORE INTO `vf_announcements` (`id`, `title`, `content`, `tone`, `placement`, `link_label`, `link_url`, `is_active`, `created_at`) VALUES
  (1, 'Studio Wyglądu i Modułów VeloForum', 'Administrator może dostosować paletę akcentu, układ działów, widżety boczne, prefiksy tematów oraz własne pola profilu w ACP.', 'info', 'home', 'Otwórz ACP', '/admin?tab=customize', 1, UNIX_TIMESTAMP());

INSERT IGNORE INTO `vf_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
  ('site_name', 'VeloForum', UNIX_TIMESTAMP()),
  ('site_description', 'Nowoczesne forum dyskusyjne społeczności oparte na PHP 8.3 i MySQL 8.0.', UNIX_TIMESTAMP()),
  ('registration_enabled', '1', UNIX_TIMESTAMP()),
  ('topics_per_page', '25', UNIX_TIMESTAMP()),
  ('posts_per_page', '20', UNIX_TIMESTAMP()),
  ('accent_color', 'blue', UNIX_TIMESTAMP()),
  ('forum_layout', 'list', UNIX_TIMESTAMP()),
  ('sidebar_position', 'right', UNIX_TIMESTAMP()),
  ('hero_enabled', '1', UNIX_TIMESTAMP()),
  ('hero_title', 'Witaj w społeczności VeloForum', UNIX_TIMESTAMP()),
  ('hero_subtitle', 'Buduj wartościowe dyskusje, zdobywaj reputację i konfiguruj moduły forum w panelu administracyjnym.', UNIX_TIMESTAMP()),
  ('hero_style', 'dark-gradient', UNIX_TIMESTAMP()),
  ('sidebar_widgets_json', '[{"id":"custom","label":"Blok własny HTML/CTA","enabled":true},{"id":"activity","label":"Ostatnia aktywność","enabled":true},{"id":"reputation","label":"Liderzy reputacji","enabled":true},{"id":"tags","label":"Popularne tagi","enabled":true},{"id":"reactions","label":"Statystyka reakcji","enabled":true},{"id":"stats","label":"Statystyki forum","enabled":true}]', UNIX_TIMESTAMP()),
  ('custom_widget_title', 'Szybki start społeczności', UNIX_TIMESTAMP()),
  ('custom_widget_body', 'Poznaj członków społeczności, uzupełnij swój profil oraz konfiguruj układ forum w ACP.', UNIX_TIMESTAMP()),
  ('custom_widget_link_label', 'Lista członków', UNIX_TIMESTAMP()),
  ('custom_widget_link_url', '/members', UNIX_TIMESTAMP()),
  ('klipy_api_key', '', UNIX_TIMESTAMP()),
  ('chat_enabled', '1', UNIX_TIMESTAMP()),
  ('chat_title', 'Czat społeczności', UNIX_TIMESTAMP()),
  ('chat_announcement', 'Witaj na czacie na żywo! Używaj @nick aby oznaczyć, /me dla akcji lub dodawaj GIF-y z Klipy.com.', UNIX_TIMESTAMP()),
  ('chat_position', 'top', UNIX_TIMESTAMP()),
  ('chat_max_length', '400', UNIX_TIMESTAMP()),
  ('chat_flood_seconds', '3', UNIX_TIMESTAMP()),
  ('chat_allow_guests', '1', UNIX_TIMESTAMP()),
  ('chat_allow_gif', '1', UNIX_TIMESTAMP()),
  ('chat_allow_reactions', '1', UNIX_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;

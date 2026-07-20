SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `teams` (
    `id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `subdomain` VARCHAR(255) NULL,
    `avatar_url` TEXT NULL,
    `color` VARCHAR(32) NULL,
    `default_user_role` VARCHAR(32) NOT NULL DEFAULT 'member',
    `preferences` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_teams_subdomain` (`subdomain`),
    KEY `idx_teams_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `team_domains` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_team_domains_name` (`name`),
    KEY `idx_team_domains_team` (`team_id`),
    CONSTRAINT `fk_team_domains_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `email` VARCHAR(320) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `role` VARCHAR(32) NOT NULL DEFAULT 'member',
    `state` VARCHAR(32) NOT NULL DEFAULT 'active',
    `avatar_url` TEXT NULL,
    `language` VARCHAR(16) NOT NULL DEFAULT 'en_US',
    `preferences` JSON NULL,
    `last_active_at` DATETIME(6) NULL,
    `suspended_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_team_email` (`team_id`, `email`),
    KEY `idx_users_team_state` (`team_id`, `state`),
    KEY `idx_users_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_users_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `authentication_providers` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `provider_id` VARCHAR(255) NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `settings` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_auth_provider_team_provider` (`team_id`, `provider_id`),
    CONSTRAINT `fk_auth_provider_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_authentications` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `authentication_provider_id` CHAR(36) NULL,
    `provider_id` VARCHAR(255) NOT NULL,
    `scopes` JSON NULL,
    `access_token` TEXT NULL,
    `refresh_token` TEXT NULL,
    `expires_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_auth_provider` (`provider_id`, `user_id`),
    KEY `idx_user_auth_user` (`user_id`),
    CONSTRAINT `fk_user_auth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_auth_provider` FOREIGN KEY (`authentication_provider_id`) REFERENCES `authentication_providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collections` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `permission` VARCHAR(32) NOT NULL DEFAULT 'read_write',
    `color` VARCHAR(32) NULL,
    `icon` VARCHAR(255) NULL,
    `document_structure` JSON NULL,
    `created_by_id` CHAR(36) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `archived_at` DATETIME(6) NULL,
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_collections_team` (`team_id`),
    KEY `idx_collections_archived` (`team_id`, `archived_at`),
    CONSTRAINT `fk_collections_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_collections_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `templates` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `title` TEXT NULL,
    `text` LONGTEXT NULL,
    `content` JSON NULL,
    `icon` VARCHAR(255) NULL,
    `created_by_id` CHAR(36) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_templates_team` (`team_id`),
    CONSTRAINT `fk_templates_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_templates_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documents` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `collection_id` CHAR(36) NULL,
    `parent_document_id` CHAR(36) NULL,
    `template_id` CHAR(36) NULL,
    `title` TEXT NOT NULL,
    `text` LONGTEXT NULL,
    `content` JSON NULL,
    `url_id` VARCHAR(64) NOT NULL,
    `index_key` VARCHAR(255) NULL,
    `icon` VARCHAR(255) NULL,
    `color` VARCHAR(32) NULL,
    `created_by_id` CHAR(36) NULL,
    `updated_by_id` CHAR(36) NULL,
    `revision_number` INT UNSIGNED NOT NULL DEFAULT 0,
    `published_at` DATETIME(6) NULL,
    `archived_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_documents_team_url_id` (`team_id`, `url_id`),
    KEY `idx_documents_collection_parent` (`collection_id`, `parent_document_id`),
    KEY `idx_documents_team_status` (`team_id`, `published_at`, `archived_at`, `deleted_at`),
    KEY `idx_documents_updated_at` (`updated_at`),
    FULLTEXT KEY `ft_documents_title_text` (`title`, `text`),
    CONSTRAINT `fk_documents_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_documents_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_documents_parent` FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_documents_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_documents_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_documents_updater` FOREIGN KEY (`updated_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `revisions` (
    `id` CHAR(36) NOT NULL,
    `document_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `title` TEXT NOT NULL,
    `text` LONGTEXT NULL,
    `content` JSON NULL,
    `version` INT UNSIGNED NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_revisions_document_version` (`document_id`, `version`),
    KEY `idx_revisions_created_at` (`document_id`, `created_at`),
    CONSTRAINT `fk_revisions_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_revisions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groups` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `external_id` VARCHAR(255) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_groups_team_name` (`team_id`, `name`),
    CONSTRAINT `fk_groups_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_users` (
    `group_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`group_id`, `user_id`),
    KEY `idx_group_users_user` (`user_id`),
    CONSTRAINT `fk_group_users_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_group_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_memberships` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `collection_id` CHAR(36) NULL,
    `document_id` CHAR(36) NULL,
    `permission` VARCHAR(32) NOT NULL,
    `source_id` CHAR(36) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_membership_collection` (`user_id`, `collection_id`),
    UNIQUE KEY `uq_user_membership_document` (`user_id`, `document_id`),
    KEY `idx_user_memberships_document` (`document_id`),
    CONSTRAINT `fk_user_memberships_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_memberships_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_memberships_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_user_membership_target` CHECK ((`collection_id` IS NOT NULL) <> (`document_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_memberships` (
    `id` CHAR(36) NOT NULL,
    `group_id` CHAR(36) NOT NULL,
    `collection_id` CHAR(36) NULL,
    `document_id` CHAR(36) NULL,
    `permission` VARCHAR(32) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_group_membership_collection` (`group_id`, `collection_id`),
    UNIQUE KEY `uq_group_membership_document` (`group_id`, `document_id`),
    KEY `idx_group_memberships_document` (`document_id`),
    CONSTRAINT `fk_group_memberships_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_group_memberships_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_group_memberships_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_group_membership_target` CHECK ((`collection_id` IS NOT NULL) <> (`document_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comments` (
    `id` CHAR(36) NOT NULL,
    `document_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `parent_comment_id` CHAR(36) NULL,
    `data` JSON NOT NULL,
    `resolved_at` DATETIME(6) NULL,
    `resolved_by_id` CHAR(36) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_comments_document` (`document_id`, `created_at`),
    CONSTRAINT `fk_comments_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_resolver` FOREIGN KEY (`resolved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reactions` (
    `id` CHAR(36) NOT NULL,
    `comment_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `emoji` VARCHAR(64) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reaction_user_emoji` (`comment_id`, `user_id`, `emoji`),
    CONSTRAINT `fk_reactions_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `relationships` (
    `id` CHAR(36) NOT NULL,
    `source_document_id` CHAR(36) NOT NULL,
    `target_document_id` CHAR(36) NOT NULL,
    `type` VARCHAR(64) NOT NULL DEFAULT 'backlink',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_relationship` (`source_document_id`, `target_document_id`, `type`),
    KEY `idx_relationship_target` (`target_document_id`),
    CONSTRAINT `fk_relationship_source` FOREIGN KEY (`source_document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_relationship_target` FOREIGN KEY (`target_document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shares` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `document_id` CHAR(36) NULL,
    `collection_id` CHAR(36) NULL,
    `created_by_id` CHAR(36) NULL,
    `url_id` VARCHAR(64) NOT NULL,
    `published` TINYINT(1) NOT NULL DEFAULT 1,
    `include_child_documents` TINYINT(1) NOT NULL DEFAULT 1,
    `allow_indexing` TINYINT(1) NOT NULL DEFAULT 0,
    `last_accessed_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `revoked_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_shares_url_id` (`url_id`),
    KEY `idx_shares_document` (`document_id`),
    CONSTRAINT `fk_shares_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shares_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shares_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shares_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_share_target` CHECK ((`collection_id` IS NOT NULL) <> (`document_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `share_subscriptions` (
    `id` CHAR(36) NOT NULL,
    `share_id` CHAR(36) NOT NULL,
    `email` VARCHAR(320) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_share_subscription` (`share_id`, `email`),
    CONSTRAINT `fk_share_subscriptions_share` FOREIGN KEY (`share_id`) REFERENCES `shares` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stars` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `document_id` CHAR(36) NULL,
    `collection_id` CHAR(36) NULL,
    `index_key` VARCHAR(255) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_star_document` (`user_id`, `document_id`),
    UNIQUE KEY `uq_star_collection` (`user_id`, `collection_id`),
    CONSTRAINT `fk_stars_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stars_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stars_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_star_target` CHECK ((`collection_id` IS NOT NULL) <> (`document_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `views` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `ip` VARCHAR(45) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_views_document_user` (`document_id`, `user_id`),
    KEY `idx_views_document_created` (`document_id`, `created_at`),
    CONSTRAINT `fk_views_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_views_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pins` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `collection_id` CHAR(36) NULL,
    `document_id` CHAR(36) NOT NULL,
    `created_by_id` CHAR(36) NULL,
    `index_key` VARCHAR(255) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pin_scope_document` (`team_id`, `collection_id`, `document_id`),
    CONSTRAINT `fk_pins_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pins_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pins_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pins_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attachments` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `document_id` CHAR(36) NULL,
    `key_path` TEXT NOT NULL,
    `name` VARCHAR(1024) NOT NULL,
    `content_type` VARCHAR(255) NOT NULL,
    `size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `acl` VARCHAR(32) NOT NULL DEFAULT 'private',
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_attachments_document` (`document_id`),
    CONSTRAINT `fk_attachments_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_attachments_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `secret_hash` CHAR(64) NOT NULL,
    `scope` JSON NULL,
    `last_active_at` DATETIME(6) NULL,
    `expires_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `revoked_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_api_keys_secret_hash` (`secret_hash`),
    KEY `idx_api_keys_user` (`user_id`),
    CONSTRAINT `fk_api_keys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `team_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `actor_ip` VARCHAR(45) NULL,
    `name` VARCHAR(255) NOT NULL,
    `model_id` CHAR(36) NULL,
    `document_id` CHAR(36) NULL,
    `collection_id` CHAR(36) NULL,
    `data` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_events_team_created` (`team_id`, `created_at`),
    KEY `idx_events_name_created` (`name`, `created_at`),
    CONSTRAINT `fk_events_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_events_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_events_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `actor_id` CHAR(36) NULL,
    `event_id` BIGINT UNSIGNED NULL,
    `document_id` CHAR(36) NULL,
    `comment_id` CHAR(36) NULL,
    `type` VARCHAR(100) NOT NULL,
    `data` JSON NULL,
    `viewed_at` DATETIME(6) NULL,
    `archived_at` DATETIME(6) NULL,
    `emailed_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_unread` (`user_id`, `viewed_at`, `created_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_notifications_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_notifications_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `document_id` CHAR(36) NULL,
    `event_type` VARCHAR(100) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_subscription` (`user_id`, `document_id`, `event_type`),
    CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_subscriptions_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_insights` (
    `id` CHAR(36) NOT NULL,
    `document_id` CHAR(36) NOT NULL,
    `type` VARCHAR(64) NOT NULL,
    `value` DECIMAL(20,6) NULL,
    `data` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_document_insight` (`document_id`, `type`),
    CONSTRAINT `fk_document_insights_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `search_queries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `team_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `query` TEXT NOT NULL,
    `source` VARCHAR(64) NULL,
    `results` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_search_queries_team_created` (`team_id`, `created_at`),
    CONSTRAINT `fk_search_queries_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_search_queries_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `integrations` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `type` VARCHAR(100) NOT NULL,
    `service` VARCHAR(100) NULL,
    `settings` JSON NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_integrations_team_type` (`team_id`, `type`),
    CONSTRAINT `fk_integrations_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_integrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `integration_authentications` (
    `id` CHAR(36) NOT NULL,
    `integration_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `access_token` TEXT NULL,
    `refresh_token` TEXT NULL,
    `scopes` JSON NULL,
    `expires_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_integration_auth_integration` (`integration_id`),
    CONSTRAINT `fk_integration_auth_integration` FOREIGN KEY (`integration_id`) REFERENCES `integrations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_integration_auth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `imports` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `service` VARCHAR(100) NOT NULL,
    `state` VARCHAR(32) NOT NULL DEFAULT 'created',
    `error` TEXT NULL,
    `metadata` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_imports_team_state` (`team_id`, `state`),
    CONSTRAINT `fk_imports_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_imports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `import_tasks` (
    `id` CHAR(36) NOT NULL,
    `import_id` CHAR(36) NOT NULL,
    `parent_id` CHAR(36) NULL,
    `state` VARCHAR(32) NOT NULL DEFAULT 'created',
    `name` VARCHAR(1024) NULL,
    `data` JSON NULL,
    `error` TEXT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_import_tasks_import_state` (`import_id`, `state`),
    CONSTRAINT `fk_import_tasks_import` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_import_tasks_parent` FOREIGN KEY (`parent_id`) REFERENCES `import_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `file_operations` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NULL,
    `type` VARCHAR(64) NOT NULL,
    `format` VARCHAR(64) NULL,
    `state` VARCHAR(32) NOT NULL DEFAULT 'creating',
    `key_path` TEXT NULL,
    `size` BIGINT UNSIGNED NULL,
    `error` TEXT NULL,
    `options` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_file_operations_team_state` (`team_id`, `state`),
    CONSTRAINT `fk_file_operations_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_file_operations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_clients` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `client_id` VARCHAR(255) NOT NULL,
    `client_secret_hash` CHAR(64) NOT NULL,
    `redirect_uris` JSON NOT NULL,
    `scopes` JSON NULL,
    `created_by_id` CHAR(36) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_oauth_clients_client_id` (`client_id`),
    CONSTRAINT `fk_oauth_clients_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oauth_clients_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_authorization_codes` (
    `id` CHAR(36) NOT NULL,
    `oauth_client_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `code_hash` CHAR(64) NOT NULL,
    `redirect_uri` TEXT NOT NULL,
    `scopes` JSON NULL,
    `expires_at` DATETIME(6) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `used_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_oauth_codes_hash` (`code_hash`),
    CONSTRAINT `fk_oauth_codes_client` FOREIGN KEY (`oauth_client_id`) REFERENCES `oauth_clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oauth_codes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oauth_authentications` (
    `id` CHAR(36) NOT NULL,
    `oauth_client_id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `access_token_hash` CHAR(64) NOT NULL,
    `refresh_token_hash` CHAR(64) NULL,
    `scopes` JSON NULL,
    `expires_at` DATETIME(6) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `revoked_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_oauth_access_hash` (`access_token_hash`),
    UNIQUE KEY `uq_oauth_refresh_hash` (`refresh_token_hash`),
    CONSTRAINT `fk_oauth_auth_client` FOREIGN KEY (`oauth_client_id`) REFERENCES `oauth_clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oauth_auth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_subscriptions` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `created_by_id` CHAR(36) NULL,
    `name` VARCHAR(255) NOT NULL,
    `url` TEXT NOT NULL,
    `secret` VARCHAR(255) NULL,
    `events` JSON NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_webhook_subscriptions_team` (`team_id`),
    CONSTRAINT `fk_webhook_subscriptions_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_webhook_subscriptions_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_deliveries` (
    `id` CHAR(36) NOT NULL,
    `webhook_subscription_id` CHAR(36) NOT NULL,
    `event_id` BIGINT UNSIGNED NULL,
    `request_body` JSON NULL,
    `response_body` LONGTEXT NULL,
    `response_status` SMALLINT UNSIGNED NULL,
    `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_webhook_deliveries_subscription_created` (`webhook_subscription_id`, `created_at`),
    CONSTRAINT `fk_webhook_deliveries_subscription` FOREIGN KEY (`webhook_subscription_id`) REFERENCES `webhook_subscriptions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_webhook_deliveries_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `emojis` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `created_by_id` CHAR(36) NULL,
    `name` VARCHAR(100) NOT NULL,
    `url` TEXT NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_emojis_team_name` (`team_id`, `name`),
    CONSTRAINT `fk_emojis_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_emojis_creator` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_passkeys` (
    `id` CHAR(36) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `credential_id` VARBINARY(1024) NOT NULL,
    `public_key` BLOB NOT NULL,
    `counter` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `transports` JSON NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `last_used_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_passkeys_credential` (`credential_id`(255)),
    KEY `idx_user_passkeys_user` (`user_id`),
    CONSTRAINT `fk_user_passkeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `access_requests` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `requester_id` CHAR(36) NOT NULL,
    `document_id` CHAR(36) NULL,
    `collection_id` CHAR(36) NULL,
    `permission` VARCHAR(32) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `reviewer_id` CHAR(36) NULL,
    `reviewed_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_access_requests_team_status` (`team_id`, `status`),
    CONSTRAINT `fk_access_requests_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_access_requests_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_access_requests_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_access_requests_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_access_requests_collection` FOREIGN KEY (`collection_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_access_request_target` CHECK ((`collection_id` IS NOT NULL) <> (`document_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `external_groups` (
    `id` CHAR(36) NOT NULL,
    `team_id` CHAR(36) NOT NULL,
    `group_id` CHAR(36) NULL,
    `provider_id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_external_groups_team_provider` (`team_id`, `provider_id`),
    CONSTRAINT `fk_external_groups_team` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_external_groups_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` CHAR(64) NOT NULL,
    `user_id` CHAR(36) NOT NULL,
    `payload` LONGBLOB NULL,
    `ip` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `expires_at` DATETIME(6) NOT NULL,
    `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`id`),
    KEY `idx_sessions_user` (`user_id`),
    KEY `idx_sessions_expires` (`expires_at`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

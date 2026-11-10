SET NAMES utf8mb4;
SET TIME_ZONE = '+03:00';

CREATE TABLE IF NOT EXISTS `bot_users` (
    `vk_id` BIGINT UNSIGNED NOT NULL,
    `first_name` VARCHAR(64) NOT NULL DEFAULT 'Игрок',
    `last_name` VARCHAR(64) NOT NULL DEFAULT '',
    `balance` BIGINT NOT NULL DEFAULT 1500,
    `country_id` INT UNSIGNED DEFAULT NULL,
    `last_bonus` DATETIME DEFAULT NULL,
    `is_superadmin` TINYINT(1) NOT NULL DEFAULT 0,
    `total_messages` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_chars` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_stickers` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`vk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bot_chats` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(128) NOT NULL DEFAULT 'Беседа',
    `owner_vk_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `unity_id` INT UNSIGNED DEFAULT NULL,
    `rules_text` TEXT DEFAULT NULL,
    `games_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `silence_until` DATETIME DEFAULT NULL,
    `total_messages` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`peer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bot_chat_members` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `vk_id` BIGINT UNSIGNED NOT NULL,
    `nickname` VARCHAR(64) DEFAULT NULL,
    `emoji` VARCHAR(10) DEFAULT NULL,
    `role_priority` INT NOT NULL DEFAULT 0,
    `gm_granted_by` BIGINT UNSIGNED DEFAULT NULL,
    `gm_min_priority` INT NOT NULL DEFAULT 0,
    `mention_opt_in` TINYINT(1) NOT NULL DEFAULT 1,
    `messages_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `chars_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `stickers_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_active_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`peer_id`, `vk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bot_chat_roles` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `priority` INT NOT NULL,
    `role_name` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`peer_id`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bot_cmd_permissions` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `cmd_name` VARCHAR(32) NOT NULL,
    `min_priority` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`peer_id`, `cmd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bot_punishments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `target_vk_id` BIGINT UNSIGNED NOT NULL,
    `actor_vk_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('warn','mute','ban','gban','kick') NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bot_countries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `president_vk_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `bot_countries` (`id`, `name`, `president_vk_id`) VALUES (1, 'Россия', 0), (2, 'Беларусь', 0);

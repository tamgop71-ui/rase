SET NAMES utf8mb4;
SET TIME_ZONE = '+03:00';

-- 1. Глобальные профили пользователей
CREATE TABLE IF NOT EXISTS `bot_users` (
    `vk_id` BIGINT UNSIGNED NOT NULL,
    `first_name` VARCHAR(64) NOT NULL DEFAULT 'Игрок',
    `last_name` VARCHAR(64) NOT NULL DEFAULT '',
    `global_nick` VARCHAR(64) DEFAULT NULL,
    `balance` BIGINT NOT NULL DEFAULT 1500,
    `bank_balance` BIGINT NOT NULL DEFAULT 0,
    `country_id` INT UNSIGNED DEFAULT NULL,
    `spouse_vk_id` BIGINT UNSIGNED DEFAULT NULL,
    `marriage_date` DATETIME DEFAULT NULL,
    `last_bonus` DATETIME DEFAULT NULL,
    `last_work` DATETIME DEFAULT NULL,
    `last_rob` DATETIME DEFAULT NULL,
    `daily_given_amount` BIGINT NOT NULL DEFAULT 0,
    `daily_given_date` DATE DEFAULT NULL,
    `is_superadmin` TINYINT(1) NOT NULL DEFAULT 0,
    `is_vip` TINYINT(1) NOT NULL DEFAULT 0,
    `reputation` INT NOT NULL DEFAULT 0,
    `total_messages` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_chars` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_stickers` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`vk_id`),
    KEY `idx_balance` (`balance`),
    KEY `idx_country` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Беседы и их настройки
CREATE TABLE IF NOT EXISTS `bot_chats` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(128) NOT NULL DEFAULT 'Беседа',
    `owner_vk_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `unity_id` INT UNSIGNED DEFAULT NULL,
    `rules_text` TEXT DEFAULT NULL,
    `welcome_text` TEXT DEFAULT NULL,
    `games_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `silence_until` DATETIME DEFAULT NULL,
    `silence_action` VARCHAR(16) NOT NULL DEFAULT 'delete',
    `automod_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `antiflood_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `mentions_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `listen_target_vk_id` BIGINT UNSIGNED DEFAULT NULL,
    `listen_until` DATETIME DEFAULT NULL,
    `total_messages` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`peer_id`),
    KEY `idx_unity` (`unity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Участники бесед
CREATE TABLE IF NOT EXISTS `bot_chat_members` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `vk_id` BIGINT UNSIGNED NOT NULL,
    `nickname` VARCHAR(64) DEFAULT NULL,
    `emoji` VARCHAR(16) DEFAULT NULL,
    `role_priority` INT NOT NULL DEFAULT 0,
    `gm_granted_by` BIGINT UNSIGNED DEFAULT NULL,
    `gm_min_priority` INT NOT NULL DEFAULT 0,
    `mention_opt_in` TINYINT(1) NOT NULL DEFAULT 1,
    `messages_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `chars_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `stickers_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_active_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`peer_id`, `vk_id`),
    KEY `idx_role` (`peer_id`, `role_priority`),
    KEY `idx_nick` (`peer_id`, `nickname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Кастомные роли бесед
CREATE TABLE IF NOT EXISTS `bot_chat_roles` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `priority` INT NOT NULL,
    `role_name` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`peer_id`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Права на команды (/editcmd)
CREATE TABLE IF NOT EXISTS `bot_cmd_permissions` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `cmd_name` VARCHAR(32) NOT NULL,
    `min_priority` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`peer_id`, `cmd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Наказания
CREATE TABLE IF NOT EXISTS `bot_punishments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `unity_id` INT UNSIGNED DEFAULT NULL,
    `target_vk_id` BIGINT UNSIGNED NOT NULL,
    `actor_vk_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('warn','mute','ban','gban','kick') NOT NULL,
    `reason` VARCHAR(255) NOT NULL DEFAULT 'не указана',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_target_chat` (`peer_id`, `target_vk_id`, `type`, `is_active`),
    KEY `idx_target_all` (`target_vk_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Объединения бесед
CREATE TABLE IF NOT EXISTS `bot_unities` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `owner_vk_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Государства / Страны
CREATE TABLE IF NOT EXISTS `bot_countries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `president_vk_id` BIGINT UNSIGNED NOT NULL,
    `treasury` BIGINT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Предложения брака
CREATE TABLE IF NOT EXISTS `bot_marriage_proposals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `from_vk_id` BIGINT UNSIGNED NOT NULL,
    `to_vk_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_pair` (`from_vk_id`, `to_vk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Репорты / Тикеты
CREATE TABLE IF NOT EXISTS `bot_reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `from_vk_id` BIGINT UNSIGNED NOT NULL,
    `text` TEXT NOT NULL,
    `status` ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    `handled_by` BIGINT UNSIGNED DEFAULT NULL,
    `response_text` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Автоуведомления
CREATE TABLE IF NOT EXISTS `bot_timers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `interval_hours` INT UNSIGNED NOT NULL DEFAULT 2,
    `daily_time` VARCHAR(5) DEFAULT NULL,
    `message_text` TEXT NOT NULL,
    `last_sent_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Системные настройки
CREATE TABLE IF NOT EXISTS `bot_settings` (
    `setting_key` VARCHAR(64) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Заметки бесед (!заметки / !заметка)
CREATE TABLE IF NOT EXISTS `bot_notes` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `note_key` VARCHAR(32) NOT NULL,
    `note_text` TEXT NOT NULL,
    `author_vk_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`peer_id`, `note_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Кэш последних сообщений (для команды !clear / !очистить)
CREATE TABLE IF NOT EXISTS `bot_recent_messages` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `cmid` INT UNSIGNED NOT NULL,
    `from_vk_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`peer_id`, `cmid`),
    KEY `idx_peer_user` (`peer_id`, `from_vk_id`, `cmid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Безопасное добавление новых колонок при обновлении со старых версий (без потери данных)
DROP PROCEDURE IF EXISTS `bot_safe_migrate`;
DELIMITER $$
CREATE PROCEDURE `bot_safe_migrate`()
BEGIN
    -- bot_users новые колонки
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_users' AND COLUMN_NAME='bank_balance') THEN
        ALTER TABLE `bot_users` ADD COLUMN `bank_balance` BIGINT NOT NULL DEFAULT 0 AFTER `balance`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_users' AND COLUMN_NAME='last_work') THEN
        ALTER TABLE `bot_users` ADD COLUMN `last_work` DATETIME DEFAULT NULL AFTER `last_bonus`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_users' AND COLUMN_NAME='last_rob') THEN
        ALTER TABLE `bot_users` ADD COLUMN `last_rob` DATETIME DEFAULT NULL AFTER `last_work`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_users' AND COLUMN_NAME='is_vip') THEN
        ALTER TABLE `bot_users` ADD COLUMN `is_vip` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_superadmin`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_users' AND COLUMN_NAME='reputation') THEN
        ALTER TABLE `bot_users` ADD COLUMN `reputation` INT NOT NULL DEFAULT 0 AFTER `is_vip`;
    END IF;

    -- bot_chats новые колонки
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_chats' AND COLUMN_NAME='silence_action') THEN
        ALTER TABLE `bot_chats` ADD COLUMN `silence_action` VARCHAR(16) NOT NULL DEFAULT 'delete' AFTER `silence_until`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_chats' AND COLUMN_NAME='antiflood_enabled') THEN
        ALTER TABLE `bot_chats` ADD COLUMN `antiflood_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `automod_enabled`;
    END IF;

    -- bot_chat_members новые колонки
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bot_chat_members' AND COLUMN_NAME='emoji') THEN
        ALTER TABLE `bot_chat_members` ADD COLUMN `emoji` VARCHAR(16) DEFAULT NULL AFTER `nickname`;
    END IF;
END$$
DELIMITER ;
CALL `bot_safe_migrate`();
DROP PROCEDURE IF EXISTS `bot_safe_migrate`;

-- Базовые страны и настройки
INSERT IGNORE INTO `bot_countries` (`id`, `name`, `president_vk_id`) VALUES
(1, 'Россия', 0), (2, 'Беларусь', 0), (3, 'Казахстан', 0), (4, 'Япония', 0), (5, 'Германия', 0), (6, 'США', 0);

INSERT IGNORE INTO `bot_settings` (`setting_key`, `setting_value`) VALUES
('log_peer_id', '0'),
('report_notify_peer_id', '0'),
('report_notify_enabled', '1');

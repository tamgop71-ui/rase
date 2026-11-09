SET NAMES utf8mb4;
SET TIME_ZONE = '+03:00';

-- 1. Глобальная таблица пользователей бота (Экономика, Браки, Гражданство, Супер-доступ)
CREATE TABLE IF NOT EXISTS `bot_users` (
    `vk_id` BIGINT UNSIGNED NOT NULL,
    `first_name` VARCHAR(64) NOT NULL DEFAULT 'Игрок',
    `last_name` VARCHAR(64) NOT NULL DEFAULT '',
    `global_nick` VARCHAR(64) DEFAULT NULL,
    `balance` BIGINT NOT NULL DEFAULT 1500,
    `country_id` INT UNSIGNED DEFAULT NULL,
    `spouse_vk_id` BIGINT UNSIGNED DEFAULT NULL,
    `marriage_date` DATETIME DEFAULT NULL,
    `last_bonus` DATETIME DEFAULT NULL,
    `daily_given_amount` BIGINT NOT NULL DEFAULT 0,
    `daily_given_date` DATE DEFAULT NULL,
    `is_superadmin` TINYINT(1) NOT NULL DEFAULT 0,
    `total_messages` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_chars` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_stickers` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`vk_id`),
    KEY `idx_balance` (`balance`),
    KEY `idx_country` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Таблица бесед и их настроек
CREATE TABLE IF NOT EXISTS `bot_chats` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(128) NOT NULL DEFAULT 'Беседа',
    `owner_vk_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `unity_id` INT UNSIGNED DEFAULT NULL,
    `rules_text` TEXT DEFAULT NULL,
    `welcome_text` TEXT DEFAULT NULL,
    `games_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `silence_until` DATETIME DEFAULT NULL,
    `automod_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `mentions_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `listen_target_vk_id` BIGINT UNSIGNED DEFAULT NULL,
    `listen_until` DATETIME DEFAULT NULL,
    `total_messages` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`peer_id`),
    KEY `idx_unity` (`unity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Участники конкретных бесед (Никнеймы, Роли, Иммунитет, Статистика в чате)
CREATE TABLE IF NOT EXISTS `bot_chat_members` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `vk_id` BIGINT UNSIGNED NOT NULL,
    `nickname` VARCHAR(64) DEFAULT NULL,
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

-- 4. Кастомные названия ролей по приоритетам в беседах
CREATE TABLE IF NOT EXISTS `bot_chat_roles` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `priority` INT NOT NULL,
    `role_name` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`peer_id`, `priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Кастомные минимальные приоритеты для команд в беседах (/editcmd)
CREATE TABLE IF NOT EXISTS `bot_cmd_permissions` (
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `cmd_name` VARCHAR(32) NOT NULL,
    `min_priority` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`peer_id`, `cmd_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Наказания (Варны, Муты, Баны, Кики и Глобальные баны)
CREATE TABLE IF NOT EXISTS `bot_punishments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL COMMENT '0 = Глобально',
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

-- 7. Объединения бесед (Unities / Сетки бесед)
CREATE TABLE IF NOT EXISTS `bot_unities` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `owner_vk_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Государства / Страны (!гражданство, !страны)
CREATE TABLE IF NOT EXISTS `bot_countries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `president_vk_id` BIGINT UNSIGNED NOT NULL,
    `treasury` BIGINT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Предложения брака (!брак)
CREATE TABLE IF NOT EXISTS `bot_marriage_proposals` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL,
    `from_vk_id` BIGINT UNSIGNED NOT NULL,
    `to_vk_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_pair` (`from_vk_id`, `to_vk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Тикеты / Репорты (/report, !reports, !getreport)
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

-- 11. Автоуведомления по таймеру (!timeuved и !gtimeuvedg)
CREATE TABLE IF NOT EXISTS `bot_timers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `peer_id` BIGINT UNSIGNED NOT NULL COMMENT '0 = Все беседы бота',
    `interval_hours` INT UNSIGNED NOT NULL DEFAULT 2,
    `daily_time` VARCHAR(5) DEFAULT NULL COMMENT 'Формат ЧЧ:ММ',
    `message_text` TEXT NOT NULL,
    `last_sent_at` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Системные настройки бота (Лог-чат, уведомления о репортах)
CREATE TABLE IF NOT EXISTS `bot_settings` (
    `setting_key` VARCHAR(64) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Базовые страны по умолчанию
INSERT IGNORE INTO `bot_countries` (`id`, `name`, `president_vk_id`) VALUES
(1, 'Россия', 0),
(2, 'Беларусь', 0),
(3, 'Казахстан', 0),
(4, 'Япония', 0),
(5, 'Германия', 0),
(6, 'США', 0);

-- Базовые системные настройки
INSERT IGNORE INTO `bot_settings` (`setting_key`, `setting_value`) VALUES
('log_peer_id', '0'),
('report_notify_peer_id', '0'),
('report_notify_enabled', '1');

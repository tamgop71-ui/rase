SET NAMES utf8mb4;
SET TIME_ZONE = '+03:00';

-- 1. Таблица пользователей и администрации бота
CREATE TABLE IF NOT EXISTS `bot_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vk_id` BIGINT UNSIGNED NOT NULL,
    `nickname` VARCHAR(64) NOT NULL DEFAULT 'Не указан',
    `role_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-Игрок, 1-Модер, 2-Админ, 3-Руководство',
    `warns` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `messages_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_vk_id` (`vk_id`),
    KEY `idx_role_level` (`role_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Таблица логов действий (кто, кому и что выдал)
CREATE TABLE IF NOT EXISTS `bot_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_vk_id` BIGINT UNSIGNED NOT NULL,
    `target_vk_id` BIGINT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(32) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_actor` (`actor_vk_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Выдача себе максимальных прав Руководства (Замените 123456789 на свой цифровой VK ID!)
INSERT INTO `bot_users` (`vk_id`, `nickname`, `role_level`)
VALUES (123456789, 'Основатель', 3)
ON DUPLICATE KEY UPDATE `role_level` = 3;

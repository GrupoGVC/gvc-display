-- ============================================================
--  GVC Signage — Schema MySQL v3.0
--  Execute uma única vez no phpMyAdmin ou MySQL Workbench
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Administradores
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(180) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','viewer') NOT NULL DEFAULT 'admin',
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grupos de TVs
CREATE TABLE IF NOT EXISTS `groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Playlists
CREATE TABLE IF NOT EXISTS `playlists` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(180) NOT NULL,
  `is_default`  TINYINT(1)   NOT NULL DEFAULT 0,
  `loop`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dispositivos (TVs)
CREATE TABLE IF NOT EXISTS `devices` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(120) NOT NULL,
  `location`     VARCHAR(180) DEFAULT NULL,
  `group_id`     INT UNSIGNED DEFAULT NULL,
  `token`        CHAR(64)     NOT NULL UNIQUE,
  `playlist_id`  INT UNSIGNED DEFAULT NULL,
  `status`       ENUM('online','offline') NOT NULL DEFAULT 'offline',
  `last_ping`    DATETIME     DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_dev_group`    FOREIGN KEY (`group_id`)    REFERENCES `groups`    (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dev_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mídias
CREATE TABLE IF NOT EXISTS `media` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename`    VARCHAR(255) NOT NULL,
  `original`    VARCHAR(255) NOT NULL,
  `type`        ENUM('image','video') NOT NULL,
  `mime`        VARCHAR(80)  NOT NULL,
  `size`        INT UNSIGNED NOT NULL DEFAULT 0,
  `url`         VARCHAR(600) NOT NULL,
  `thumb_url`   VARCHAR(600) DEFAULT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens de playlist
CREATE TABLE IF NOT EXISTS `playlist_items` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `playlist_id` INT UNSIGNED  NOT NULL,
  `media_id`    INT UNSIGNED  DEFAULT NULL,
  `type`        ENUM('image','video','page') NOT NULL,
  `url`         VARCHAR(600)  DEFAULT NULL,
  `duration`    SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pl_sort` (`playlist_id`, `sort_order`),
  CONSTRAINT `fk_item_pl`    FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_media` FOREIGN KEY (`media_id`)    REFERENCES `media`     (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agendamentos
CREATE TABLE IF NOT EXISTS `schedules` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `playlist_id`    INT UNSIGNED NOT NULL,
  `target_type`    ENUM('all','group','device') NOT NULL DEFAULT 'all',
  `target_id`      INT UNSIGNED DEFAULT NULL,
  `starts_at`      DATETIME     NOT NULL,
  `ends_at`        DATETIME     NOT NULL,
  `repeat_weekly`  TINYINT(1)   NOT NULL DEFAULT 0,
  `weekdays`       VARCHAR(20)  DEFAULT NULL,
  `active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_sched` (`active`, `starts_at`, `ends_at`),
  CONSTRAINT `fk_sched_pl` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Códigos de pareamento
CREATE TABLE IF NOT EXISTS `pairing_codes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       CHAR(6)      NOT NULL UNIQUE,
  `device_id`  INT UNSIGNED DEFAULT NULL,
  `token`      CHAR(64)     DEFAULT NULL,
  `paired`     TINYINT(1)   NOT NULL DEFAULT 0,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de atividade
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(120) NOT NULL,
  `entity`     VARCHAR(60)  DEFAULT NULL,
  `entity_id`  INT UNSIGNED DEFAULT NULL,
  `detail`     TEXT         DEFAULT NULL,
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_log` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Usuário admin inicial ────────────────────────────────────
-- Senha: admin123  |  Troque imediatamente após o 1º login!
-- Hash gerado com: password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('Administrador GVC', 'admin@gvc.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin');

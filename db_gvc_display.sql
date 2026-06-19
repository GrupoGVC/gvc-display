-- =========================================================
-- GVC Display - Banco MySQL/MariaDB compatível com Workbench
-- Stack: PHP + JavaScript + MySQL + API MVC
-- Banco: db_gvc_display
-- Login padrão: admin@gvc.com / admin123
-- ATENÇÃO: este script apaga e recria o banco do zero.
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS db_gvc_display;

CREATE DATABASE db_gvc_display
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_gvc_display;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- Usuários administrativos
-- =========================================================
CREATE TABLE users (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_users_email (email),
  KEY idx_users_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Grupos de TVs
-- =========================================================
CREATE TABLE `groups` (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  description  TEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_groups_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Playlists
-- =========================================================
CREATE TABLE playlists (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(150) NOT NULL,
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_playlists_name (name),
  KEY idx_playlists_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Dispositivos / TVs
-- =========================================================
CREATE TABLE devices (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  location     VARCHAR(200) NULL,
  slug         VARCHAR(100) NOT NULL,
  token        VARCHAR(64) NOT NULL,
  group_id     INT UNSIGNED NULL,
  playlist_id  INT UNSIGNED NULL,
  status       ENUM('online','offline') NOT NULL DEFAULT 'offline',
  last_ping    DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_devices_slug (slug),
  UNIQUE KEY uk_devices_token (token),
  KEY idx_devices_group_id (group_id),
  KEY idx_devices_playlist_id (playlist_id),
  KEY idx_devices_status (status),
  KEY idx_devices_last_ping (last_ping),

  CONSTRAINT fk_devices_group
    FOREIGN KEY (group_id) REFERENCES `groups` (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,

  CONSTRAINT fk_devices_playlist
    FOREIGN KEY (playlist_id) REFERENCES playlists (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Biblioteca de mídia
-- =========================================================
CREATE TABLE media (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  original_name  VARCHAR(255) NOT NULL,
  type           ENUM('image','video') NOT NULL,
  url            VARCHAR(500) NOT NULL,
  size           INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_media_type (type),
  KEY idx_media_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Itens de playlist
-- =========================================================
CREATE TABLE playlist_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  playlist_id  INT UNSIGNED NOT NULL,
  type         ENUM('image','video','page') NOT NULL DEFAULT 'image',
  url          TEXT NOT NULL,
  duration     SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  media_id     INT UNSIGNED NULL,
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_playlist_items_playlist_id (playlist_id),
  KEY idx_playlist_items_media_id (media_id),
  KEY idx_playlist_items_sort (playlist_id, sort_order),

  CONSTRAINT fk_playlist_items_playlist
    FOREIGN KEY (playlist_id) REFERENCES playlists (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT fk_playlist_items_media
    FOREIGN KEY (media_id) REFERENCES media (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Agendamentos
-- target_type:
--   all    => todas as TVs
--   group  => target_id aponta para groups.id
--   device => target_id aponta para devices.id
-- target_id e polimorfico, por isso nao tem FK direta.
-- =========================================================
CREATE TABLE schedules (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  playlist_id    INT UNSIGNED NOT NULL,
  target_type    ENUM('all','group','device') NOT NULL DEFAULT 'all',
  target_id      INT UNSIGNED NULL,
  starts_at      DATETIME NOT NULL,
  ends_at        DATETIME NOT NULL,
  repeat_weekly  TINYINT(1) NOT NULL DEFAULT 0,
  weekdays       JSON NULL,
  active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_schedules_playlist_id (playlist_id),
  KEY idx_schedules_active_period (active, starts_at, ends_at),
  KEY idx_schedules_target (target_type, target_id),

  CONSTRAINT fk_schedules_playlist
    FOREIGN KEY (playlist_id) REFERENCES playlists (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Codigos de pareamento
-- =========================================================
CREATE TABLE pairing_codes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id   INT UNSIGNED NOT NULL,
  code        CHAR(6) NOT NULL,
  expires_at  DATETIME NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uk_pairing_codes_code (code),
  KEY idx_pairing_codes_device_id (device_id),
  KEY idx_pairing_codes_expires_at (expires_at),

  CONSTRAINT fk_pairing_codes_device
    FOREIGN KEY (device_id) REFERENCES devices (id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Logs de atividade
-- =========================================================
CREATE TABLE activity_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(50) NOT NULL,
  detail      VARCHAR(500) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_activity_logs_user_id (user_id),
  KEY idx_activity_logs_action (action),
  KEY idx_activity_logs_created_at (created_at),

  CONSTRAINT fk_activity_logs_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Controle de versao para atualizacao automatica das TVs
-- A TV consulta essa versao no heartbeat. Se mudar, recarrega conteudo.
-- =========================================================
CREATE TABLE content_versions (
  name        VARCHAR(50) NOT NULL,
  version     BIGINT UNSIGNED NOT NULL DEFAULT 1,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Seeds iniciais
-- Login padrao:
--   E-mail: admin@gvc.com
--   Senha:  admin123
-- Hash validado com password_verify('admin123', hash) no PHP.
-- =========================================================
INSERT INTO users (name, email, password_hash, active) VALUES
  ('Administrador', 'admin@gvc.com', '$2y$12$xiecU2bdD1HlsLWu7SEQ.eVCU6HK1liAFH0OfrQ2RXoXGFW/Q.M/.', 1);

INSERT INTO playlists (id, name, is_default) VALUES
  (1, 'Padrao', 1);

ALTER TABLE playlists AUTO_INCREMENT = 2;

INSERT INTO content_versions (name, version) VALUES
  ('player_content', 1),
  ('devices', 1),
  ('schedules', 1);

-- =========================================================
-- Views uteis para o painel/admin
-- Workbench/MySQL: usar DROP VIEW + CREATE VIEW evita problema de versao.
-- =========================================================
DROP VIEW IF EXISTS vw_devices_full;

CREATE VIEW vw_devices_full AS
SELECT
  d.id,
  d.name,
  d.location,
  d.slug,
  d.token,
  d.group_id,
  g.name AS group_name,
  d.playlist_id,
  p.name AS playlist_name,
  d.status,
  d.last_ping,
  d.created_at,
  d.updated_at
FROM devices d
LEFT JOIN `groups` g ON g.id = d.group_id
LEFT JOIN playlists p ON p.id = d.playlist_id;

DROP VIEW IF EXISTS vw_playlist_items_full;

CREATE VIEW vw_playlist_items_full AS
SELECT
  i.id,
  i.playlist_id,
  i.type,
  i.url,
  i.duration,
  i.media_id,
  m.original_name AS media_name,
  m.url AS media_url,
  m.type AS media_type,
  i.sort_order,
  i.created_at,
  i.updated_at
FROM playlist_items i
LEFT JOIN media m ON m.id = i.media_id;

-- =========================================================
-- Procedure para incrementar versao
-- Pode ser usada pelos triggers e, se necessario, pelo PHP.
-- Se usar estes triggers, nao precisa incrementar versao tambem no PHP.
-- =========================================================
DELIMITER $$

DROP PROCEDURE IF EXISTS bump_content_version $$

CREATE PROCEDURE bump_content_version(IN p_name VARCHAR(50))
BEGIN
  INSERT INTO content_versions (name, version)
  VALUES (p_name, 1)
  ON DUPLICATE KEY UPDATE
    version = version + 1,
    updated_at = NOW();
END $$

-- =========================================================
-- Triggers de atualizacao automatica
-- Observacao: update de heartbeat/status/last_ping em devices nao forca
-- refresh do player, pois o trigger so considera campos estruturais.
-- =========================================================

DROP TRIGGER IF EXISTS trg_groups_ai $$
CREATE TRIGGER trg_groups_ai
AFTER INSERT ON `groups`
FOR EACH ROW
BEGIN
  CALL bump_content_version('devices');
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_groups_au $$
CREATE TRIGGER trg_groups_au
AFTER UPDATE ON `groups`
FOR EACH ROW
BEGIN
  CALL bump_content_version('devices');
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_groups_ad $$
CREATE TRIGGER trg_groups_ad
AFTER DELETE ON `groups`
FOR EACH ROW
BEGIN
  CALL bump_content_version('devices');
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_devices_ai $$
CREATE TRIGGER trg_devices_ai
AFTER INSERT ON devices
FOR EACH ROW
BEGIN
  CALL bump_content_version('devices');
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_devices_au $$
CREATE TRIGGER trg_devices_au
AFTER UPDATE ON devices
FOR EACH ROW
BEGIN
  IF NOT (OLD.name <=> NEW.name)
     OR NOT (OLD.location <=> NEW.location)
     OR NOT (OLD.slug <=> NEW.slug)
     OR NOT (OLD.token <=> NEW.token)
     OR NOT (OLD.group_id <=> NEW.group_id)
     OR NOT (OLD.playlist_id <=> NEW.playlist_id) THEN
    CALL bump_content_version('devices');
    CALL bump_content_version('player_content');
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_devices_ad $$
CREATE TRIGGER trg_devices_ad
AFTER DELETE ON devices
FOR EACH ROW
BEGIN
  CALL bump_content_version('devices');
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_playlists_ai $$
CREATE TRIGGER trg_playlists_ai
AFTER INSERT ON playlists
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_playlists_au $$
CREATE TRIGGER trg_playlists_au
AFTER UPDATE ON playlists
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_playlists_ad $$
CREATE TRIGGER trg_playlists_ad
AFTER DELETE ON playlists
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_playlist_items_ai $$
CREATE TRIGGER trg_playlist_items_ai
AFTER INSERT ON playlist_items
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_playlist_items_au $$
CREATE TRIGGER trg_playlist_items_au
AFTER UPDATE ON playlist_items
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_playlist_items_ad $$
CREATE TRIGGER trg_playlist_items_ad
AFTER DELETE ON playlist_items
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_media_ai $$
CREATE TRIGGER trg_media_ai
AFTER INSERT ON media
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_media_au $$
CREATE TRIGGER trg_media_au
AFTER UPDATE ON media
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_media_ad $$
CREATE TRIGGER trg_media_ad
AFTER DELETE ON media
FOR EACH ROW
BEGIN
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_schedules_ai $$
CREATE TRIGGER trg_schedules_ai
AFTER INSERT ON schedules
FOR EACH ROW
BEGIN
  CALL bump_content_version('schedules');
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_schedules_au $$
CREATE TRIGGER trg_schedules_au
AFTER UPDATE ON schedules
FOR EACH ROW
BEGIN
  CALL bump_content_version('schedules');
  CALL bump_content_version('player_content');
END $$

DROP TRIGGER IF EXISTS trg_schedules_ad $$
CREATE TRIGGER trg_schedules_ad
AFTER DELETE ON schedules
FOR EACH ROW
BEGIN
  CALL bump_content_version('schedules');
  CALL bump_content_version('player_content');
END $$

DELIMITER ;

-- =========================================================
-- Validacao final
-- =========================================================
SELECT 'Banco db_gvc_display criado com sucesso.' AS status;
SELECT email, active, created_at FROM users;
SELECT name, version, updated_at FROM content_versions ORDER BY name;

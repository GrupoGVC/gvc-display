CREATE DATABASE IF NOT EXISTS db_gvc_display
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_gvc_display;

-- Usuários admin
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Grupos de TVs
CREATE TABLE IF NOT EXISTS `groups` (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Dispositivos (TVs)
CREATE TABLE IF NOT EXISTS devices (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  location    VARCHAR(200),
  slug        VARCHAR(100) NOT NULL UNIQUE,
  token       VARCHAR(64)  NOT NULL UNIQUE,
  group_id    INT UNSIGNED REFERENCES `groups`(id),
  playlist_id INT UNSIGNED,
  status      ENUM('online','offline') DEFAULT 'offline',
  last_ping   DATETIME,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Playlists
CREATE TABLE IF NOT EXISTS playlists (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  is_default TINYINT(1)   DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Itens de playlist
CREATE TABLE IF NOT EXISTS playlist_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  playlist_id INT UNSIGNED NOT NULL REFERENCES playlists(id),
  type        ENUM('image','video','page') DEFAULT 'image',
  url         TEXT NOT NULL,
  duration    SMALLINT UNSIGNED DEFAULT 10,
  media_id    INT UNSIGNED,
  sort_order  SMALLINT UNSIGNED DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Biblioteca de mídia
CREATE TABLE IF NOT EXISTS media (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  original_name VARCHAR(255) NOT NULL,
  type          ENUM('image','video') NOT NULL,
  url           VARCHAR(500) NOT NULL,
  size          INT UNSIGNED DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Agendamentos
CREATE TABLE IF NOT EXISTS schedules (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  playlist_id   INT UNSIGNED NOT NULL REFERENCES playlists(id),
  target_type   ENUM('all','group','device') DEFAULT 'all',
  target_id     INT UNSIGNED,
  starts_at     DATETIME NOT NULL,
  ends_at       DATETIME NOT NULL,
  repeat_weekly TINYINT(1) DEFAULT 0,
  weekdays      JSON,
  active        TINYINT(1) DEFAULT 1,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Códigos de pareamento
CREATE TABLE IF NOT EXISTS pairing_codes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id  INT UNSIGNED NOT NULL REFERENCES devices(id),
  code       CHAR(6) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Log de atividades
CREATE TABLE IF NOT EXISTS activity_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED,
  action     VARCHAR(50) NOT NULL,
  detail     VARCHAR(500),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ── Seed: usuário admin padrão ─────────────────────────────────
-- Senha: admin123  (troque imediatamente em produção)
-- Hash gerado com: password_hash('admin123', PASSWORD_BCRYPT)
INSERT IGNORE INTO users (name, email, password_hash) VALUES
  ('Administrador', 'admin@gvc.com', '$2y$10$TKh8H1.PfBEaI4RBhJbCOu2MNbDBx8f1RQOOB2kTTLGhiUMVKAJBm');

-- ── Playlist padrão ────────────────────────────────────────────
INSERT IGNORE INTO playlists (id, name, is_default) VALUES (1, 'Padrão', 1);

-- Last.fm Sync Panel — Schema bazy danych
-- Uruchom ten plik raz przez phpMyAdmin lub MySQL CLI

SET NAMES utf8mb4;
SET time_zone = '+01:00';

-- ─── KONFIGURACJA ────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `config` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `api_key`    VARCHAR(64)  NOT NULL,
  `api_secret` VARCHAR(64)  NOT NULL,
  `a_user`     VARCHAR(64)  NOT NULL,
  `a_sk`       VARCHAR(64)  NOT NULL,
  `b_user`     VARCHAR(64)  NOT NULL,
  `b_sk`       VARCHAR(64)  NOT NULL,
  `saved_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `saved_by`   VARCHAR(32)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── CYKLE SYNCHRONIZACJI ────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `sync_runs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `ran_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `np_a`       TINYINT(1)   NOT NULL DEFAULT 0,
  `np_b`       TINYINT(1)   NOT NULL DEFAULT 0,
  `synced_a2b` INT          NOT NULL DEFAULT 0,
  `synced_b2a` INT          NOT NULL DEFAULT 0,
  `status`     VARCHAR(16)  NOT NULL DEFAULT 'ok',
  `error_msg`  TEXT         DEFAULT NULL,
  INDEX `idx_ran_at` (`ran_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── ZSYNCHRONIZOWANE SCROBLE ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `scrobbles` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `run_id`     INT          NOT NULL,
  `direction`  ENUM('a2b','b2a') NOT NULL,
  `artist`     VARCHAR(255) NOT NULL,
  `track`      VARCHAR(255) NOT NULL,
  `album`      VARCHAR(255) DEFAULT NULL,
  `scrobbled_at` DATETIME   NOT NULL,
  `synced_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_direction`    (`direction`),
  INDEX `idx_scrobbled_at` (`scrobbled_at`),
  INDEX `idx_artist`       (`artist`),
  FOREIGN KEY (`run_id`) REFERENCES `sync_runs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── LOGI ────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `logs` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `logged_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `side`       VARCHAR(16)  NOT NULL,
  `message`    TEXT         NOT NULL,
  `type`       VARCHAR(16)  DEFAULT '',
  INDEX `idx_logged_at` (`logged_at`),
  INDEX `idx_side`      (`side`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── UŻYTKOWNICY PANELU ──────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `panel_users` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `username`     VARCHAR(64)  NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,
  `lastfm_user`  VARCHAR(64)  DEFAULT NULL,
  `role`         ENUM('admin','user') NOT NULL DEFAULT 'user',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login`   DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── DOMYŚLNI UŻYTKOWNICY PANELU ─────────────────────────────────────────────
-- Hasła: admin=admin123, surprice=music123
-- ZMIEŃ HASŁA po pierwszym logowaniu!

INSERT INTO `panel_users` (`username`, `password`, `lastfm_user`, `role`) VALUES
('admin',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL,        'admin'),
('sykstus',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sykstus666','user'),
('surprice', '$2y$10$TKh8H1.PnD5f2zNAm5VO5.EONPEdm3Uo/k.gFsV0KwS', 'surprice_', 'user');

-- Hasła: sykstus=music123, surprice=music456
-- WYGENERUJ WŁASNE przez: php -r "echo password_hash('TWOJE_HASLO', PASSWORD_BCRYPT);"

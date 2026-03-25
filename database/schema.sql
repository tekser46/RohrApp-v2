-- ═══════════════════════════════════════════════
-- RohrApp+ v2 — Database Schema
-- ═══════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. users ──
CREATE TABLE IF NOT EXISTS users (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    email             VARCHAR(200) UNIQUE NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    role              ENUM('admin','user') DEFAULT 'user',
    is_active         TINYINT(1) DEFAULT 1,
    email_verified_at DATETIME NULL,
    remember_token    VARCHAR(100) NULL,
    last_login_at     DATETIME NULL,
    last_login_ip     VARCHAR(45) NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- ── 2. user_profiles ──
CREATE TABLE IF NOT EXISTS user_profiles (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL UNIQUE,
    first_name        VARCHAR(100),
    last_name         VARCHAR(100),
    company_name      VARCHAR(200),
    phone             VARCHAR(50),
    address_street    VARCHAR(300),
    address_city      VARCHAR(100),
    address_zip       VARCHAR(20),
    address_country   VARCHAR(100) DEFAULT 'Deutschland',
    billing_street    VARCHAR(300),
    billing_city      VARCHAR(100),
    billing_zip       VARCHAR(20),
    billing_country   VARCHAR(100),
    avatar_path       VARCHAR(500) NULL,
    company_logo_path VARCHAR(500) NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 3. packages ──
CREATE TABLE IF NOT EXISTS packages (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    slug                VARCHAR(50) UNIQUE NOT NULL,
    name                VARCHAR(100) NOT NULL,
    description         TEXT,
    price_monthly       DECIMAL(10,2) DEFAULT 0.00,
    features            JSON,
    max_sipgate_numbers INT DEFAULT 0,
    max_websites        INT DEFAULT 0,
    has_email_inbox     TINYINT(1) DEFAULT 0,
    has_call_logs       TINYINT(1) DEFAULT 0,
    has_messages        TINYINT(1) DEFAULT 0,
    sort_order          INT DEFAULT 0,
    is_active           TINYINT(1) DEFAULT 1,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- ── 4. user_licenses ──
CREATE TABLE IF NOT EXISTS user_licenses (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    package_id   INT NOT NULL,
    license_key  VARCHAR(64) UNIQUE,
    status       ENUM('active','expired','suspended','trial') DEFAULT 'trial',
    starts_at    DATETIME,
    expires_at   DATETIME NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_key (license_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ── 5. license_upgrade_requests ──
CREATE TABLE IF NOT EXISTS license_upgrade_requests (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    user_id              INT NOT NULL,
    current_package_id   INT NOT NULL,
    requested_package_id INT NOT NULL,
    status               ENUM('pending','approved','rejected') DEFAULT 'pending',
    user_message         TEXT NULL,
    admin_note           TEXT NULL,
    reviewed_by          INT NULL,
    reviewed_at          DATETIME NULL,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (current_package_id) REFERENCES packages(id) ON DELETE RESTRICT,
    FOREIGN KEY (requested_package_id) REFERENCES packages(id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 6. email_settings ──
CREATE TABLE IF NOT EXISTS email_settings (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT NOT NULL UNIQUE,
    email_address           VARCHAR(200),
    imap_host               VARCHAR(200),
    imap_port               INT DEFAULT 993,
    imap_username           VARCHAR(200),
    imap_password_encrypted TEXT,
    imap_encryption         ENUM('ssl','tls','none') DEFAULT 'ssl',
    smtp_host               VARCHAR(200) NULL,
    smtp_port               INT DEFAULT 587,
    smtp_username           VARCHAR(200) NULL,
    smtp_password_encrypted TEXT NULL,
    smtp_encryption         ENUM('ssl','tls','none') DEFAULT 'tls',
    is_verified             TINYINT(1) DEFAULT 0,
    last_sync_at            DATETIME NULL,
    last_sync_uid           VARCHAR(100) NULL,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 7. email_cache ──
CREATE TABLE IF NOT EXISTS email_cache (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    message_uid     VARCHAR(100) NOT NULL,
    message_id      VARCHAR(500),
    from_address    VARCHAR(300),
    from_name       VARCHAR(200),
    to_address      VARCHAR(300),
    subject         VARCHAR(500),
    body_preview    VARCHAR(500),
    is_read         TINYINT(1) DEFAULT 0,
    is_starred      TINYINT(1) DEFAULT 0,
    has_attachments TINYINT(1) DEFAULT 0,
    folder          VARCHAR(100) DEFAULT 'INBOX',
    color           VARCHAR(20) NULL,
    mail_date       DATETIME,
    fetched_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_user_uid (user_id, message_uid),
    INDEX idx_user_date (user_id, mail_date DESC),
    INDEX idx_user_folder (user_id, folder),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Migration: Add folder and color columns if not exists ──
-- Run this if table already exists:
-- ALTER TABLE email_cache ADD COLUMN folder VARCHAR(100) DEFAULT 'INBOX' AFTER has_attachments;
-- ALTER TABLE email_cache ADD COLUMN color VARCHAR(20) NULL AFTER folder;
-- ALTER TABLE email_cache ADD INDEX idx_user_folder (user_id, folder);

-- ── 8. websites ──
CREATE TABLE IF NOT EXISTS websites (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    domain      VARCHAR(200) NOT NULL,
    name        VARCHAR(200),
    api_key     VARCHAR(64) UNIQUE NOT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_apikey (api_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 9. contact_messages ──
CREATE TABLE IF NOT EXISTS contact_messages (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    website_id    INT NOT NULL,
    user_id       INT NOT NULL,
    sender_name   VARCHAR(200),
    sender_email  VARCHAR(200),
    sender_phone  VARCHAR(50),
    subject       VARCHAR(300),
    message       TEXT,
    status        ENUM('unread','read','archived') DEFAULT 'unread',
    ip_address    VARCHAR(45),
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    INDEX idx_website (website_id),
    INDEX idx_created (created_at DESC),
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 10. sipgate_settings ──
CREATE TABLE IF NOT EXISTS sipgate_settings (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL UNIQUE,
    is_enabled     TINYINT(1) DEFAULT 0,
    webhook_token  VARCHAR(64) UNIQUE NOT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_token (webhook_token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 11. sipgate_numbers ──
CREATE TABLE IF NOT EXISTS sipgate_numbers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    sipgate_settings_id INT NOT NULL,
    number              VARCHAR(50) NOT NULL,
    label               VARCHAR(200),
    block_name          VARCHAR(200) NULL,
    is_blocked          TINYINT(1) DEFAULT 0,
    is_active           TINYINT(1) DEFAULT 1,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_settings (sipgate_settings_id),
    INDEX idx_number (number),
    FOREIGN KEY (sipgate_settings_id) REFERENCES sipgate_settings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 12. call_logs ──
CREATE TABLE IF NOT EXISTS call_logs (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NULL,
    sipgate_call_id   VARCHAR(100) UNIQUE,
    direction         ENUM('in','out'),
    from_number       VARCHAR(50),
    to_number         VARCHAR(50),
    caller_name       VARCHAR(200) NULL,
    status            ENUM('ringing','answered','missed','busy','hangup') DEFAULT 'ringing',
    category          ENUM('none','falsch','auftrag') DEFAULT 'none',
    started_at        DATETIME,
    answered_at       DATETIME NULL,
    ended_at          DATETIME NULL,
    duration          INT DEFAULT 0,
    matched_number_id INT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, started_at DESC),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_from (from_number),
    INDEX idx_to (to_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (matched_number_id) REFERENCES sipgate_numbers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 12b. auftraege ──
CREATE TABLE IF NOT EXISTS auftraege (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL,
    call_log_id       INT NULL,
    customer_name     VARCHAR(200) NOT NULL DEFAULT '',
    customer_address  VARCHAR(300) DEFAULT '',
    customer_plz      VARCHAR(10) DEFAULT '',
    customer_city     VARCHAR(100) DEFAULT '',
    customer_phone    VARCHAR(50) DEFAULT '',
    job_type          ENUM('Hauptleitung','Kueche','Bad','Keller','Toilette','Sonstiges') DEFAULT 'Sonstiges',
    notes             TEXT NULL,
    status            ENUM('offen','in_bearbeitung','erledigt','storniert') DEFAULT 'offen',
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (call_log_id) REFERENCES call_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 13. webhook_logs ──
CREATE TABLE IF NOT EXISTS webhook_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    webhook_token   VARCHAR(64),
    event_type      VARCHAR(50),
    payload         JSON,
    ip_address      VARCHAR(45),
    processed       TINYINT(1) DEFAULT 0,
    matched_user_id INT NULL,
    call_log_id     INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (webhook_token),
    INDEX idx_processed (processed),
    INDEX idx_created (created_at DESC),
    FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (call_log_id) REFERENCES call_logs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 14. activity_logs ──
CREATE TABLE IF NOT EXISTS activity_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NULL,
    action       VARCHAR(100),
    target_type  VARCHAR(50) NULL,
    target_id    INT NULL,
    details      JSON NULL,
    ip_address   VARCHAR(45),
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at DESC),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 15. password_resets ──
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    token      VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 16. login_attempts ──
CREATE TABLE IF NOT EXISTS login_attempts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ip_address      VARCHAR(45) NOT NULL,
    email           VARCHAR(200) NULL,
    attempt_count   INT DEFAULT 1,
    locked_until    DATETIME NULL,
    last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ── 17. uploaded_files ──
CREATE TABLE IF NOT EXISTS uploaded_files (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    file_name     VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    file_path     VARCHAR(500) NOT NULL,
    file_type     VARCHAR(50),
    file_size     INT DEFAULT 0,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

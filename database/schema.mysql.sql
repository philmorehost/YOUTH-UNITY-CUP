-- Youth Unity Cup initial schema (MySQL 8.0+ / MariaDB 10.5+)
-- This file is applied by the installer; existing tables are not dropped.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempt_identifier_time (identifier, occurred_at),
    KEY idx_login_attempt_ip_time (ip_address, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    identifier VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    outcome ENUM('success', 'failed', 'blocked') NOT NULL,
    details VARCHAR(160) NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_history_time (occurred_at),
    KEY idx_login_history_user_time (user_id, occurred_at),
    KEY idx_login_history_ip_time (ip_address, occurred_at),
    CONSTRAINT fk_login_history_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_blocks (
    ip_address VARCHAR(45) NOT NULL,
    blocked_until DATETIME NOT NULL,
    reason VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ip_address),
    KEY idx_ip_blocks_expiry (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    event_key VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT 'unknown',
    context_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_event_time (event_key, created_at),
    KEY idx_audit_user_time (user_id, created_at),
    CONSTRAINT fk_audit_log_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_key VARCHAR(80) NOT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body_text MEDIUMTEXT NOT NULL,
    status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_outbox_status_next (status, next_attempt_at, id),
    KEY idx_outbox_event_time (event_key, created_at),
    KEY idx_outbox_recipient_time (recipient_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(80) NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL DEFAULT '',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payment_provider VARCHAR(30) NOT NULL DEFAULT '',
    provider_reference VARCHAR(120) NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_transactions_reference (reference),
    UNIQUE KEY uq_transactions_provider_reference (provider_reference),
    KEY idx_transactions_status_time (status, created_at),
    KEY idx_transactions_user_time (user_id, created_at),
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(60) NOT NULL,
    name VARCHAR(140) NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',
    price_kobo BIGINT UNSIGNED NOT NULL,
    stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_products_sku (sku),
    KEY idx_shop_products_status_stock (status, stock_quantity),
    KEY idx_shop_products_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(80) NOT NULL,
    transaction_id BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(140) NOT NULL,
    customer_email VARCHAR(190) NOT NULL,
    customer_phone VARCHAR(40) NOT NULL,
    fulfillment_notes VARCHAR(500) NOT NULL DEFAULT '',
    checkout_url VARCHAR(2048) NULL,
    provider_amount_kobo VARCHAR(30) NULL,
    provider_currency CHAR(3) NULL,
    payment_review_reason VARCHAR(120) NULL,
    total_kobo BIGINT UNSIGNED NOT NULL,
    stock_reserved TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('pending_payment', 'paid', 'paid_needs_review', 'processing', 'fulfilled', 'payment_failed', 'cancelled') NOT NULL DEFAULT 'pending_payment',
    reservation_expires_at DATETIME NOT NULL,
    paid_at DATETIME NULL,
    fulfilled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_orders_reference (reference),
    UNIQUE KEY uq_shop_orders_transaction (transaction_id),
    KEY idx_shop_orders_status_time (status, created_at),
    KEY idx_shop_orders_email_time (customer_email, created_at),
    KEY idx_shop_orders_reservation (status, reservation_expires_at),
    CONSTRAINT fk_shop_orders_transaction FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    sku_snapshot VARCHAR(60) NOT NULL,
    product_name VARCHAR(140) NOT NULL,
    unit_price_kobo BIGINT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    line_total_kobo BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shop_order_items_order (order_id),
    KEY idx_shop_order_items_product (product_id),
    CONSTRAINT fk_shop_order_items_order FOREIGN KEY (order_id) REFERENCES shop_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_shop_order_items_product FOREIGN KEY (product_id) REFERENCES shop_products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user_expiry (user_id, expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    zone VARCHAR(50) NOT NULL,
    group_name VARCHAR(20) NULL,
    contact_email VARCHAR(190) NOT NULL DEFAULT '',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_zone_name (zone, name),
    KEY idx_teams_status_zone (status, zone),
    KEY idx_teams_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(140) NOT NULL,
    zone VARCHAR(50) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 700,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_venues_zone_name (zone, name),
    KEY idx_venues_status_zone (status, zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fixtures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    home_team_id BIGINT UNSIGNED NOT NULL,
    away_team_id BIGINT UNSIGNED NOT NULL,
    venue_id BIGINT UNSIGNED NULL,
    stage VARCHAR(60) NOT NULL DEFAULT 'Group stage',
    kickoff_at DATETIME NOT NULL,
    status ENUM('scheduled', 'live', 'completed', 'postponed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    home_score TINYINT UNSIGNED NULL,
    away_score TINYINT UNSIGNED NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fixtures_kickoff (kickoff_at),
    KEY idx_fixtures_status_kickoff (status, kickoff_at),
    KEY idx_fixtures_venue (venue_id),
    CONSTRAINT fk_fixtures_home_team FOREIGN KEY (home_team_id) REFERENCES teams (id) ON DELETE RESTRICT,
    CONSTRAINT fk_fixtures_away_team FOREIGN KEY (away_team_id) REFERENCES teams (id) ON DELETE RESTRICT,
    CONSTRAINT fk_fixtures_venue FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(32) NOT NULL,
    full_name VARCHAR(140) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    category ENUM('player', 'team_official', 'vendor', 'volunteer', 'community') NOT NULL,
    age TINYINT UNSIGNED NULL,
    zone VARCHAR(50) NOT NULL DEFAULT '',
    team_name VARCHAR(120) NOT NULL DEFAULT '',
    details TEXT NULL,
    status ENUM('new', 'reviewing', 'approved', 'rejected') NOT NULL DEFAULT 'new',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registrations_reference (reference),
    KEY idx_registrations_status_date (status, submitted_at),
    KEY idx_registrations_email_date (email, submitted_at),
    CONSTRAINT fk_registrations_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

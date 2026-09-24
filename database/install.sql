-- Nora Panel - database schema
-- Import once into an EMPTY database (cPanel > phpMyAdmin > select database > Import).
-- Works on MySQL 5.7+/8.x and MariaDB 10.3+.

SET NAMES utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(14, 4) NOT NULL DEFAULT 0,
    api_key CHAR(64) NOT NULL UNIQUE,
    referral_code VARCHAR(16) NOT NULL UNIQUE,
    referred_by INT UNSIGNED NULL,
    affiliate_balance DECIMAL(14, 4) NOT NULL DEFAULT 0,
    status ENUM('active', 'banned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    KEY idx_users_referred_by (referred_by),
    CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMM providers that speak the standard "API v2" (POST key + action),
-- e.g. MoreThanPanel, Crescitaly and most other SMM panels.
CREATE TABLE providers (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    balance DECIMAL(14, 4) NULL,
    currency VARCHAR(10) NULL,
    balance_checked_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- rate and cost are per 1000 units (the SMM industry standard)
CREATE TABLE services (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    provider_service_id VARCHAR(50) NOT NULL,
    backup_provider_id INT UNSIGNED NULL,
    backup_provider_service_id VARCHAR(50) NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(190) NOT NULL DEFAULT 'Other',
    type VARCHAR(50) NOT NULL DEFAULT 'Default',
    description TEXT NULL,
    rate DECIMAL(14, 4) NOT NULL,
    cost DECIMAL(14, 4) NOT NULL DEFAULT 0,
    min_quantity INT UNSIGNED NOT NULL DEFAULT 10,
    max_quantity INT UNSIGNED NOT NULL DEFAULT 10000,
    refill TINYINT(1) NOT NULL DEFAULT 0,
    cancel TINYINT(1) NOT NULL DEFAULT 0,
    dripfeed TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_services_category (category),
    CONSTRAINT fk_services_provider FOREIGN KEY (provider_id) REFERENCES providers(id),
    CONSTRAINT fk_services_backup FOREIGN KEY (backup_provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: pending | processing | in_progress | completed | partial | canceled
CREATE TABLE orders (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NULL,
    provider_order_id VARCHAR(50) NULL,
    link VARCHAR(500) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    runs INT UNSIGNED NULL,
    run_interval INT UNSIGNED NULL,
    extra TEXT NULL,
    charge DECIMAL(14, 4) NOT NULL,
    cost DECIMAL(14, 4) NOT NULL DEFAULT 0,
    refunded DECIMAL(14, 4) NOT NULL DEFAULT 0,
    start_count INT NULL,
    remains INT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    error VARCHAR(255) NULL,
    via_api TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_user (user_id),
    KEY idx_orders_status (status),
    KEY idx_orders_created (created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_orders_service FOREIGN KEY (service_id) REFERENCES services(id),
    CONSTRAINT fk_orders_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refill requests sent to the provider for orders with a refill guarantee
CREATE TABLE refills (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NOT NULL,
    provider_refill_id VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_refills_order (order_id),
    KEY idx_refills_status (status),
    CONSTRAINT fk_refills_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_refills_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_logs (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    message VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order_logs_order (order_id),
    CONSTRAINT fk_order_logs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deposit requests (crypto / bank transfer), approved manually by an admin
CREATE TABLE payments (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14, 4) NOT NULL,
    method VARCHAR(20) NOT NULL,
    reference VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    UNIQUE KEY uq_payments_reference (method, reference),
    KEY idx_payments_user (user_id),
    KEY idx_payments_status (status),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every balance change (ledger)
CREATE TABLE transactions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14, 4) NOT NULL,
    type VARCHAR(20) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_transactions_user (user_id),
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE affiliate_earnings (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    referrer_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL UNIQUE,
    amount DECIMAL(14, 4) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_affiliate_referrer (referrer_id),
    CONSTRAINT fk_affiliate_referrer FOREIGN KEY (referrer_id) REFERENCES users(id),
    CONSTRAINT fk_affiliate_order FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    scope VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts (ip, scope, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Providers (add your API keys in Admin > Providers)
INSERT INTO providers (name, api_url, api_key, is_active) VALUES
('MoreThanPanel', 'https://morethanpanel.com/api/v2', '', 1),
('Crescitaly', 'https://crescitaly.com/api/v2', '', 0);

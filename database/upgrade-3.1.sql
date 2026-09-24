-- Only needed if you imported install.sql BEFORE order types / drip-feed / refill / password reset were added.
-- New installs: just import install.sql.

ALTER TABLE services ADD COLUMN type VARCHAR(50) NOT NULL DEFAULT 'Default' AFTER category;
ALTER TABLE services ADD COLUMN dripfeed TINYINT(1) NOT NULL DEFAULT 0 AFTER cancel;

ALTER TABLE orders ADD COLUMN runs INT UNSIGNED NULL AFTER quantity;
ALTER TABLE orders ADD COLUMN run_interval INT UNSIGNED NULL AFTER runs;
ALTER TABLE orders ADD COLUMN extra TEXT NULL AFTER run_interval;

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

CREATE TABLE password_resets (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enhanced Nora Social Media Panel Database Schema
-- Multi-Provider Support (Crescitaly, Panel.com, Socioboard, SMM.com)

-- Drop old tables if upgrading (BACKUP FIRST!)
-- DROP TABLE IF EXISTS services;
-- DROP TABLE IF EXISTS orders;

-- Services table - now with provider support
ALTER TABLE services ADD COLUMN provider VARCHAR(50) DEFAULT 'crescitaly' AFTER crescitaly_id;
ALTER TABLE services ADD COLUMN provider_service_id VARCHAR(100) AFTER provider;
ALTER TABLE services ADD COLUMN provider_rate DECIMAL(10, 6) DEFAULT 0 AFTER price;
ALTER TABLE services ADD COLUMN our_margin DECIMAL(10, 4) DEFAULT 0 AFTER provider_rate;

-- Create provider_accounts table for storing API keys
CREATE TABLE IF NOT EXISTS provider_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_name VARCHAR(50) UNIQUE NOT NULL,
    api_key VARCHAR(500) NOT NULL,
    api_secret VARCHAR(500),
    additional_config JSON,
    is_active TINYINT DEFAULT 1,
    balance DECIMAL(10, 2) DEFAULT 0,
    last_balance_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table enhancement
ALTER TABLE orders ADD COLUMN provider VARCHAR(50) DEFAULT 'crescitaly' AFTER crescitaly_order_id;
ALTER TABLE orders ADD COLUMN provider_order_id VARCHAR(100) AFTER provider;
ALTER TABLE orders ADD COLUMN provider_status VARCHAR(50) AFTER crescitaly_order_id;
ALTER TABLE orders ADD COLUMN cost_to_provider DECIMAL(10, 4) AFTER total_price;
ALTER TABLE orders ADD COLUMN our_profit DECIMAL(10, 4) AFTER cost_to_provider;

-- Create order_logs table for tracking
CREATE TABLE IF NOT EXISTS order_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    provider VARCHAR(50),
    action VARCHAR(100),
    status VARCHAR(50),
    response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Services table upgrade SQL (run if upgrading)
CREATE TABLE IF NOT EXISTS services_v2 AS
SELECT *,
       NULL AS provider,
       NULL AS provider_service_id,
       0 AS provider_rate,
       0 AS our_margin
FROM services;

-- Provider performance tracking
CREATE TABLE IF NOT EXISTS provider_performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    total_orders INT DEFAULT 0,
    successful_orders INT DEFAULT 0,
    failed_orders INT DEFAULT 0,
    average_speed INT DEFAULT 0,
    success_rate DECIMAL(5, 2) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert provider accounts (configure with your API keys)
INSERT INTO provider_accounts (provider_name, api_key, is_active) VALUES 
('crescitaly', 'your_crescitaly_api_key_here', 1),
('panelcom', 'your_panelcom_api_key_here', 1),
('socioboard', 'your_socioboard_api_key_here', 1),
('smmcom', 'your_smmcom_api_key_here', 1)
ON DUPLICATE KEY UPDATE
api_key = VALUES(api_key);

-- Initialize provider performance
INSERT IGNORE INTO provider_performance (provider, total_orders, successful_orders, failed_orders) VALUES 
('crescitaly', 0, 0, 0),
('panelcom', 0, 0, 0),
('socioboard', 0, 0, 0),
('smmcom', 0, 0, 0);

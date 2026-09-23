-- Reseller and Affiliate System Tables

-- Resellers table
CREATE TABLE IF NOT EXISTS resellers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    website VARCHAR(255),
    commission_percent DECIMAL(5, 2) NOT NULL DEFAULT 15,
    api_key VARCHAR(100) UNIQUE NOT NULL,
    total_referrals INT DEFAULT 0,
    total_earnings DECIMAL(10, 2) DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (api_key),
    INDEX (user_id)
);

-- Referral commissions table
CREATE TABLE IF NOT EXISTS referral_commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reseller_id INT NOT NULL,
    order_id INT NOT NULL,
    commission_percent DECIMAL(5, 2) NOT NULL,
    order_amount DECIMAL(10, 4) NOT NULL,
    commission_amount DECIMAL(10, 4) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- pending, approved, paid
    payment_method VARCHAR(50),
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX (reseller_id),
    INDEX (status),
    INDEX (created_at)
);

-- Reseller monthly payouts
CREATE TABLE IF NOT EXISTS reseller_payouts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reseller_id INT NOT NULL,
    payout_month DATE NOT NULL,
    total_commissions DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    payment_details JSON,
    status VARCHAR(50) DEFAULT 'pending', -- pending, approved, completed, failed
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id),
    INDEX (reseller_id),
    INDEX (status),
    UNIQUE KEY (reseller_id, payout_month)
);

-- Add referrer_id column to orders table
ALTER TABLE orders ADD COLUMN referrer_id INT DEFAULT NULL;
ALTER TABLE orders ADD FOREIGN KEY (referrer_id) REFERENCES resellers(id);
ALTER TABLE orders ADD INDEX (referrer_id);

-- Reseller dashboard stats view
CREATE OR REPLACE VIEW reseller_stats AS
SELECT 
    r.id,
    r.user_id,
    r.name,
    COUNT(DISTINCT o.id) as total_orders,
    SUM(o.total_price) as total_sales,
    SUM(rc.commission_amount) as total_commissions_earned,
    COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
    COUNT(DISTINCT CASE WHEN o.status = 'failed' THEN o.id END) as failed_orders,
    ROUND((COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) / COUNT(DISTINCT o.id) * 100), 2) as success_rate
FROM resellers r
LEFT JOIN orders o ON o.referrer_id = r.id
LEFT JOIN referral_commissions rc ON rc.order_id = o.id AND rc.reseller_id = r.id
GROUP BY r.id, r.user_id, r.name;

-- Insert commission records for existing referrals
-- This trigger runs when a new order is placed with a referrer
CREATE TRIGGER create_referral_commission AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    IF NEW.referrer_id IS NOT NULL THEN
        INSERT INTO referral_commissions (
            reseller_id, 
            order_id, 
            commission_percent, 
            order_amount, 
            commission_amount,
            status
        )
        SELECT 
            NEW.referrer_id,
            NEW.id,
            r.commission_percent,
            NEW.total_price,
            NEW.total_price * (r.commission_percent / 100),
            'pending'
        FROM resellers r
        WHERE r.id = NEW.referrer_id;
        
        UPDATE resellers 
        SET total_referrals = total_referrals + 1
        WHERE id = NEW.referrer_id;
    END IF;
END;

-- Update commission to paid when order completes
CREATE TRIGGER update_commission_on_completion AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE referral_commissions 
        SET status = 'approved'
        WHERE order_id = NEW.id AND status = 'pending';
    END IF;
    
    IF NEW.status = 'failed' AND OLD.status != 'failed' THEN
        UPDATE referral_commissions 
        SET status = 'cancelled'
        WHERE order_id = NEW.id;
    END IF;
END;

-- Sample queries for reseller analytics
-- Get top resellers
SELECT 
    r.name,
    COUNT(o.id) as orders,
    SUM(o.total_price) as sales,
    SUM(rc.commission_amount) as commissions
FROM resellers r
LEFT JOIN orders o ON o.referrer_id = r.id
LEFT JOIN referral_commissions rc ON rc.order_id = o.id
WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY r.id
ORDER BY sales DESC
LIMIT 10;

-- Get pending payouts for a reseller
SELECT 
    r.id,
    r.name,
    SUM(rc.commission_amount) as total_commissions,
    COUNT(rc.id) as commission_count
FROM resellers r
JOIN referral_commissions rc ON rc.reseller_id = r.id
WHERE rc.status = 'approved' AND rc.paid_at IS NULL
GROUP BY r.id;

-- Add MoreThanPanel (https://morethanpanel.com) as a provider
-- Run AFTER upgrade-db-providers.sql

INSERT INTO provider_accounts (provider_name, api_key, is_active) VALUES
('morethanpanel', 'your_morethanpanel_api_key_here', 1)
ON DUPLICATE KEY UPDATE
api_key = VALUES(api_key);

INSERT INTO provider_performance (provider, total_orders, successful_orders, failed_orders)
SELECT 'morethanpanel', 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM provider_performance WHERE provider = 'morethanpanel');

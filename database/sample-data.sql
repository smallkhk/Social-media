-- NORA SOCIAL MEDIA PANEL - SAMPLE DATA
-- Run this after creating the database structure

-- Insert test admin
INSERT INTO admin_users (email, password, role) 
VALUES ('admin@nora.local', '$2y$10$N9qo8uLOickgx2ZMRZoMye', 'admin');
-- Password: admin123

-- Insert test bank accounts
INSERT INTO bank_accounts (bank_name, account_number, account_name, is_active) VALUES 
('GTBank', '0123456789', 'Nora Social Media Panel', 1),
('Access Bank', '9876543210', 'Nora SMM Services', 1),
('First Bank', '0555555555', 'Nora Digital Solutions', 1);

-- Insert popular social media services
INSERT INTO services (platform, service_name, description, price, min_quantity, max_quantity, crescitaly_id, category, is_active) VALUES 

-- INSTAGRAM SERVICES
('Instagram', 'Instagram Followers [Drip Feed]', 'High quality real followers', 0.0005, 10, 50000, '1', 'followers', 1),
('Instagram', 'Instagram Followers [Instant]', 'Instant followers delivery', 0.0008, 10, 100000, '2', 'followers', 1),
('Instagram', 'Instagram Likes [High Retention]', 'Long lasting likes from real accounts', 0.00008, 10, 50000, '3', 'likes', 1),
('Instagram', 'Instagram Likes [Fast]', 'Quick delivery likes', 0.00012, 10, 100000, '4', 'likes', 1),
('Instagram', 'Instagram Comments [Real]', 'Relevant comments from active users', 0.001, 5, 1000, '5', 'comments', 1),
('Instagram', 'Instagram Views [Reels]', 'Views for Instagram Reels', 0.00002, 50, 50000, '6', 'views', 1),
('Instagram', 'Instagram Saves', 'Save engagements on posts', 0.0003, 10, 10000, '7', 'saves', 1),
('Instagram', 'Instagram Story Views', 'Views on Instagram Stories', 0.00001, 100, 100000, '8', 'story-views', 1),

-- TIKTOK SERVICES
('TikTok', 'TikTok Followers [Real]', 'Real and active TikTok followers', 0.0003, 10, 100000, '9', 'followers', 1),
('TikTok', 'TikTok Likes [Instant]', 'Instant likes on your videos', 0.00005, 10, 1000000, '10', 'likes', 1),
('TikTok', 'TikTok Views [Drip]', 'Gradual views delivery', 0.00002, 100, 500000, '11', 'views', 1),
('TikTok', 'TikTok Comments [Real]', 'Real user comments', 0.0005, 5, 1000, '12', 'comments', 1),
('TikTok', 'TikTok Shares', 'Video shares from real accounts', 0.001, 5, 5000, '13', 'shares', 1),

-- YOUTUBE SERVICES
('YouTube', 'YouTube Subscribers [Real]', 'Real YouTube channel subscribers', 0.01, 10, 10000, '14', 'subscribers', 1),
('YouTube', 'YouTube Views [Fast]', 'Quick video views', 0.00002, 100, 500000, '15', 'views', 1),
('YouTube', 'YouTube Likes', 'Real likes on videos', 0.0001, 10, 50000, '16', 'likes', 1),
('YouTube', 'YouTube Comments [Custom]', 'Custom comments from real users', 0.002, 5, 500, '17', 'comments', 1),
('YouTube', 'YouTube Watch Time', 'Boost your watch time hours', 0.0008, 100, 100000, '18', 'watch-time', 1),

-- TWITTER SERVICES
('Twitter', 'Twitter Followers [Real]', 'Real Twitter followers', 0.001, 10, 50000, '19', 'followers', 1),
('Twitter', 'Twitter Likes [Fast]', 'Instant likes on tweets', 0.00008, 10, 100000, '20', 'likes', 1),
('Twitter', 'Twitter Retweets', 'Real retweets on your tweets', 0.0003, 5, 50000, '21', 'retweets', 1),
('Twitter', 'Twitter Replies [Custom]', 'Custom replies to tweets', 0.0005, 5, 1000, '22', 'replies', 1),

-- SPOTIFY SERVICES
('Spotify', 'Spotify Followers [Artist]', 'Artist profile followers', 0.005, 10, 50000, '23', 'followers', 1),
('Spotify', 'Spotify Plays [Monthly]', 'Monthly listener boost', 0.00005, 100, 500000, '24', 'plays', 1),
('Spotify', 'Spotify Saves [Custom]', 'Real saves on playlist/track', 0.0008, 10, 50000, '25', 'saves', 1),

-- TELEGRAM SERVICES
('Telegram', 'Telegram Members [Real]', 'Real active Telegram members', 0.0008, 10, 50000, '26', 'members', 1),
('Telegram', 'Telegram Views [Channel]', 'Views on channel posts', 0.00003, 100, 100000, '27', 'views', 1),

-- INSERT SAMPLE CATEGORIES (for dropdowns)
INSERT IGNORE INTO services (platform, service_name, category) 
SELECT DISTINCT platform, platform, category FROM services;

-- Note: Replace crescitaly_id values with actual IDs from your Crescitaly account
-- These are placeholder examples only

-- To test: Create a test user
-- Username: testuser
-- Email: test@example.com
-- Password: Test@1234
-- Then use the dashboard to browse services

-- INSERT INTO users (email, username, password, balance, api_key, status) VALUES 
-- ('test@example.com', 'testuser', '$2y$10$N9qo8uLOickgx2ZMRZoMye', 100.00, 'sample_api_key_12345', 'active');
-- Password: Test@1234

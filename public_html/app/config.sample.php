<?php
// Copy this file to config.php (same folder) and fill in your values.
// Payment, email/SMTP, support, affiliate and sign-up options below are only starting values:
// change them in Admin > Settings (saved values there override this file).
// app/ is blocked from web access by app/.htaccess.

// Database (cPanel > MySQL Databases). On cPanel the names are prefixed: cpaneluser_dbname
define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_nora');
define('DB_USER', 'cpaneluser_nora');
define('DB_PASS', 'change-me');

// Site
define('SITE_NAME', 'Nora Panel');
define('SITE_URL', 'https://yourdomain.com'); // no trailing slash; include sub-folder if installed in one
define('CURRENCY_SIGN', '$');
define('TIMEZONE', 'UTC');

// Secret for the cron URL (only needed if you run the cron via wget/curl instead of php)
// Generate one: any 40+ random characters
define('CRON_TOKEN', 'change-this-to-a-long-random-string');

// Sender for password-reset emails. Create this mailbox in cPanel > Email Accounts so mail isn't marked as spam.
define('MAIL_FROM', 'no-reply@yourdomain.com');

// Deposits (approved manually in Admin > Payments)
define('MIN_DEPOSIT', 5);
define('CRYPTO_ENABLED', true);
define('CRYPTO_WALLET', 'your-usdt-wallet-address');
define('CRYPTO_NETWORK', 'USDT (BEP-20 / BSC)'); // shown to customers, e.g. 'USDT (TRC-20)'
define('BANK_ENABLED', true);
define('BANK_DETAILS', "Bank: Your Bank\nAccount name: Your Business\nAccount number: 0000000000");
define('BANK_RATE_NOTE', ''); // e.g. '1 USD = 1500 NGN' (shown to customers)

// Affiliate program: % of each completed order paid to the referrer (0 disables)
define('AFFILIATE_PERCENT', 5);
define('AFFILIATE_MIN_TRANSFER', 1);

// Allow new sign-ups
define('REGISTRATION_OPEN', true);

// Show PHP errors on screen (keep false on a live site; errors go to the cPanel error_log)
define('DEBUG', false);

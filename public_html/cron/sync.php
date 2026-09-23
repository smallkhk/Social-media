<?php
// Checks open orders with the providers, refunds canceled/partial orders and updates provider balances.
//
// cPanel > Cron Jobs, every 5 minutes (recommended):
//   /usr/local/bin/php /home/CPANELUSER/public_html/cron/sync.php >/dev/null 2>&1
// or, if you prefer a URL:
//   wget -q -O /dev/null "https://yourdomain.com/cron/sync.php?token=YOUR_CRON_TOKEN"
define('SKIP_CSRF', true);
require __DIR__ . '/../app/bootstrap.php';

if (!IS_CLI) {
    $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
    if (CRON_TOKEN === '' || str_starts_with(CRON_TOKEN, 'change-this') || !hash_equals(CRON_TOKEN, $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json');
}

// Don't let two runs overlap
$lock = fopen(sys_get_temp_dir() . '/nora-sync-' . md5(__DIR__) . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(json_encode(['status' => 'already running']) . PHP_EOL);
}
set_time_limit(280);

$stats = sync_orders();
update_provider_balances();

echo json_encode(['status' => 'ok', 'time' => date('c')] + $stats) . PHP_EOL;

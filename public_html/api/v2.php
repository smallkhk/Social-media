<?php
// Standard SMM API v2 for your customers: POST https://yourdomain.com/api/v2  (key, action, ...)
define('SKIP_CSRF', true);
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json');

function api_out(array $data): never
{
    echo json_encode($data);
    exit;
}

$in = $_POST + $_GET;
$key = is_string($in['key'] ?? null) ? $in['key'] : '';
$action = is_string($in['action'] ?? null) ? $in['action'] : '';

if ($key === '') {
    api_out(['error' => 'Invalid API key']);
}
if (too_many_attempts('api')) {
    api_out(['error' => 'Too many invalid requests, try again later']);
}
$user = row("SELECT * FROM users WHERE api_key = ? AND status = 'active'", [$key]);
if (!$user) {
    record_attempt('api');
    api_out(['error' => 'Invalid API key']);
}

switch ($action) {
    case 'services':
        $out = [];
        foreach (all('SELECT * FROM services WHERE is_active = 1 ORDER BY category, id') as $s) {
            $out[] = [
                'service' => (int)$s['id'],
                'name' => $s['name'],
                'type' => order_type($s['type'])['name'] ?? 'Default',
                'category' => $s['category'],
                'rate' => number_format((float)$s['rate'], 4, '.', ''),
                'min' => (string)$s['min_quantity'],
                'max' => (string)$s['max_quantity'],
                'dripfeed' => (bool)$s['dripfeed'],
                'refill' => (bool)$s['refill'],
                'cancel' => false,
            ];
        }
        api_out($out);

    case 'add':
        $result = place_order((int)$user['id'], (int)($in['service'] ?? 0), $in, true);
        api_out($result['ok'] ? ['order' => $result['order_id']] : ['error' => $result['error']]);

    case 'status':
        $format = fn(array $o) => [
            'charge' => number_format((float)$o['charge'] - (float)$o['refunded'], 4, '.', ''),
            'start_count' => (string)($o['start_count'] ?? '0'),
            'status' => ['pending' => 'Pending', 'processing' => 'Processing', 'in_progress' => 'In progress',
                'completed' => 'Completed', 'partial' => 'Partial', 'canceled' => 'Canceled'][$o['status']] ?? 'Pending',
            'remains' => (string)($o['remains'] ?? $o['quantity']),
            'currency' => 'USD',
        ];
        if (isset($in['orders'])) {
            $ids = array_slice(array_filter(array_map('intval', explode(',', (string)$in['orders']))), 0, 100);
            $out = [];
            foreach ($ids as $id) {
                $o = row('SELECT * FROM orders WHERE id = ? AND user_id = ?', [$id, $user['id']]);
                $out[$id] = $o ? $format($o) : ['error' => 'Incorrect order ID'];
            }
            api_out($out);
        }
        $o = row('SELECT * FROM orders WHERE id = ? AND user_id = ?', [(int)($in['order'] ?? 0), $user['id']]);
        api_out($o ? $format($o) : ['error' => 'Incorrect order ID']);

    case 'refill':
        if (isset($in['orders'])) {
            $out = [];
            foreach (array_slice(array_filter(array_map('intval', explode(',', (string)$in['orders']))), 0, 100) as $id) {
                $r = request_refill((int)$user['id'], $id);
                $out[] = ['order' => $id, 'refill' => $r['ok'] ? $r['refill_id'] : ['error' => $r['error']]];
            }
            api_out($out);
        }
        $r = request_refill((int)$user['id'], (int)($in['order'] ?? 0));
        api_out($r['ok'] ? ['refill' => (string)$r['refill_id']] : ['error' => $r['error']]);

    case 'refill_status':
        $label = fn(string $st) => ['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed',
            'rejected' => 'Rejected', 'canceled' => 'Canceled'][$st] ?? 'Error';
        if (isset($in['refills'])) {
            $out = [];
            foreach (array_slice(array_filter(array_map('intval', explode(',', (string)$in['refills']))), 0, 100) as $id) {
                $st = val('SELECT status FROM refills WHERE id = ? AND user_id = ?', [$id, $user['id']]);
                $out[] = ['refill' => $id, 'status' => $st ? $label($st) : ['error' => 'Refill not found']];
            }
            api_out($out);
        }
        $st = val('SELECT status FROM refills WHERE id = ? AND user_id = ?', [(int)($in['refill'] ?? 0), $user['id']]);
        api_out($st ? ['status' => $label($st)] : ['error' => 'Refill not found']);

    case 'balance':
        api_out(['balance' => number_format((float)$user['balance'], 4, '.', ''), 'currency' => 'USD']);

    default:
        api_out(['error' => 'Incorrect request']);
}

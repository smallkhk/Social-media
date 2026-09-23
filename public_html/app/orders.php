<?php
declare(strict_types=1);

const OPEN_STATUSES = ['pending', 'processing', 'in_progress'];

function order_log(int $orderId, ?int $providerId, string $action, string $message = ''): void
{
    q('INSERT INTO order_logs (order_id, provider_id, action, message) VALUES (?, ?, ?, ?)',
        [$orderId, $providerId, $action, mb_substr($message, 0, 1000)]);
}

function order_charge(array $service, int $quantity): float
{
    return round((float)$service['rate'] * $quantity / 1000, 4);
}

/**
 * Charge the user, then send the order to the service's provider (and its backup
 * provider if the first one refuses). Refunds automatically if both fail.
 *
 * @return array{ok: bool, order_id?: int, error?: string}
 */
function place_order(int $userId, int $serviceId, string $link, int $quantity, bool $viaApi = false): array
{
    $service = row('SELECT * FROM services WHERE id = ? AND is_active = 1', [$serviceId]);
    if (!$service) {
        return ['ok' => false, 'error' => 'Service not found'];
    }
    $link = trim($link);
    if ($link === '' || mb_strlen($link) > 500 || preg_match('/\s/', $link)) {
        return ['ok' => false, 'error' => 'Enter a valid link'];
    }
    if ($quantity < (int)$service['min_quantity'] || $quantity > (int)$service['max_quantity']) {
        return ['ok' => false, 'error' => "Quantity must be between {$service['min_quantity']} and {$service['max_quantity']}"];
    }
    $charge = order_charge($service, $quantity);
    if ($charge <= 0) {
        return ['ok' => false, 'error' => 'This service has no price set'];
    }

    // Take the money first, atomically, so two parallel orders can't overspend the balance
    $orderId = transaction(function () use ($userId, $service, $link, $quantity, $charge, $viaApi) {
        $taken = q("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ? AND status = 'active'",
            [$charge, $userId, $charge])->rowCount();
        if (!$taken) {
            return null;
        }
        q('INSERT INTO orders (user_id, service_id, link, quantity, charge, status, via_api) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $service['id'], $link, $quantity, $charge, 'pending', $viaApi ? 1 : 0]);
        $id = (int)db()->lastInsertId();
        q('INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)',
            [$userId, -$charge, 'order', "Order #$id"]);
        return $id;
    });
    if ($orderId === null) {
        return ['ok' => false, 'error' => 'Not enough balance. Please add funds.'];
    }

    $routes = [[(int)$service['provider_id'], (string)$service['provider_service_id']]];
    if ($service['backup_provider_id'] && $service['backup_provider_service_id'] !== null && $service['backup_provider_service_id'] !== '') {
        $routes[] = [(int)$service['backup_provider_id'], (string)$service['backup_provider_service_id']];
    }

    $lastError = 'No provider available';
    foreach ($routes as [$providerId, $providerServiceId]) {
        $provider = row('SELECT * FROM providers WHERE id = ? AND is_active = 1', [$providerId]);
        if (!$provider) {
            order_log($orderId, $providerId, 'skip', 'Provider inactive');
            continue;
        }
        $result = SmmProvider::fromRow($provider)->addOrder($providerServiceId, $link, $quantity);
        if (!empty($result['order'])) {
            $cost = round((float)$service['cost'] * $quantity / 1000, 4);
            if ($providerId !== (int)$service['provider_id']) {
                $cost = 0; // backup cost unknown until the provider reports its charge
            }
            q("UPDATE orders SET provider_id = ?, provider_order_id = ?, cost = ?, status = 'processing' WHERE id = ?",
                [$providerId, (string)$result['order'], $cost, $orderId]);
            order_log($orderId, $providerId, 'placed', 'Provider order ' . $result['order']);
            return ['ok' => true, 'order_id' => $orderId];
        }
        $lastError = $result['error'] ?? 'Unknown provider response';
        order_log($orderId, $providerId, 'place_failed', $lastError);
    }

    refund_order($orderId, null, 'canceled', 'Could not be placed: ' . $lastError);
    error_log("Order #$orderId could not be placed: $lastError");
    return ['ok' => false, 'error' => 'The order could not be placed right now and your balance was refunded. Please try again later.'];
}

/**
 * Close an order and refund the user. $amount null = full refund.
 * Safe to call twice: only an open order is refunded.
 */
function refund_order(int $orderId, ?float $amount, string $newStatus, string $note = ''): bool
{
    return transaction(function () use ($orderId, $amount, $newStatus, $note) {
        $order = row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$order || !in_array($order['status'], OPEN_STATUSES, true)) {
            return false;
        }
        $refund = $amount === null ? (float)$order['charge'] : min(max(0, round($amount, 4)), (float)$order['charge']);
        q('UPDATE orders SET status = ?, refunded = ?, error = ? WHERE id = ?',
            [$newStatus, $refund, $note !== '' ? mb_substr($note, 0, 255) : $order['error'], $orderId]);
        if ($refund > 0) {
            credit_user((int)$order['user_id'], $refund, 'refund', "Refund for order #$orderId");
        }
        order_log($orderId, $order['provider_id'] ? (int)$order['provider_id'] : null, 'refund', money($refund, 4) . ' ' . $note);
        if ($newStatus === 'partial') {
            pay_affiliate($orderId);
        }
        return true;
    });
}

function complete_order(int $orderId): void
{
    transaction(function () use ($orderId) {
        $changed = q("UPDATE orders SET status = 'completed', remains = 0 WHERE id = ? AND status IN ('pending','processing','in_progress')",
            [$orderId])->rowCount();
        if ($changed) {
            pay_affiliate($orderId);
        }
    });
}

/** Credit the referrer's affiliate balance once per finished order (call inside a transaction). */
function pay_affiliate(int $orderId): void
{
    if (AFFILIATE_PERCENT <= 0) {
        return;
    }
    $o = row('SELECT o.charge, o.refunded, u.referred_by FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$orderId]);
    if (!$o || !$o['referred_by']) {
        return;
    }
    $amount = round(((float)$o['charge'] - (float)$o['refunded']) * AFFILIATE_PERCENT / 100, 4);
    if ($amount <= 0) {
        return;
    }
    $inserted = q('INSERT IGNORE INTO affiliate_earnings (referrer_id, order_id, amount) VALUES (?, ?, ?)',
        [$o['referred_by'], $orderId, $amount])->rowCount();
    if ($inserted) {
        q('UPDATE users SET affiliate_balance = affiliate_balance + ? WHERE id = ?', [$amount, $o['referred_by']]);
    }
}

/** Apply one provider status response ({status, remains, start_count, charge}) to an order. */
function apply_provider_status(array $order, array $info): string
{
    $orderId = (int)$order['id'];
    if (isset($info['error'])) {
        order_log($orderId, (int)$order['provider_id'], 'status_error', (string)$info['error']);
        return 'error';
    }
    $status = strtolower(trim((string)($info['status'] ?? '')));
    $remains = isset($info['remains']) && is_numeric($info['remains']) ? max(0, (int)$info['remains']) : null;
    $start = isset($info['start_count']) && is_numeric($info['start_count']) ? (int)$info['start_count'] : null;
    $cost = isset($info['charge']) && is_numeric($info['charge']) ? (float)$info['charge'] : null;

    q('UPDATE orders SET remains = COALESCE(?, remains), start_count = COALESCE(?, start_count), cost = COALESCE(?, cost) WHERE id = ?',
        [$remains, $start, $cost, $orderId]);

    switch ($status) {
        case 'completed':
            complete_order($orderId);
            return 'completed';
        case 'partial':
            $qty = max(1, (int)$order['quantity']);
            $refund = (float)$order['charge'] * min((int)$remains, $qty) / $qty;
            refund_order($orderId, $refund, 'partial', "Partial: $remains not delivered");
            return 'partial';
        case 'canceled':
        case 'cancelled':
        case 'refunded':
            refund_order($orderId, null, 'canceled', 'Canceled by provider');
            return 'canceled';
        case 'in progress':
            q("UPDATE orders SET status = 'in_progress' WHERE id = ? AND status IN ('pending','processing')", [$orderId]);
            return 'in_progress';
        case 'pending':
        case 'processing':
            return $status;
        default:
            order_log($orderId, (int)$order['provider_id'], 'status_unknown', $status);
            return 'unknown';
    }
}

/** Check open orders with their providers (100 per request). Used by cron and admin. */
function sync_orders(int $limit = 500): array
{
    $stats = ['checked' => 0, 'completed' => 0, 'partial' => 0, 'canceled' => 0, 'errors' => 0];

    // Orders whose placement was interrupted (e.g. the request crashed) are refunded
    foreach (all("SELECT id FROM orders WHERE status = 'pending' AND provider_order_id IS NULL AND created_at < NOW() - INTERVAL 15 MINUTE") as $o) {
        refund_order((int)$o['id'], null, 'canceled', 'Placement was interrupted');
        $stats['canceled']++;
    }

    $orders = all("SELECT * FROM orders WHERE status IN ('pending','processing','in_progress') AND provider_order_id IS NOT NULL
                   ORDER BY updated_at ASC LIMIT " . (int)$limit);
    $byProvider = [];
    foreach ($orders as $o) {
        $byProvider[(int)$o['provider_id']][] = $o;
    }

    foreach ($byProvider as $providerId => $providerOrders) {
        $api = SmmProvider::byId($providerId);
        if (!$api) {
            continue;
        }
        foreach (array_chunk($providerOrders, 100) as $chunk) {
            $ids = array_map(fn($o) => (string)$o['provider_order_id'], $chunk);
            $response = $api->multiStatus($ids);
            if (isset($response['error']) && !isset($response[$ids[0]])) {
                $stats['errors'] += count($chunk);
                error_log("Status sync failed for provider #$providerId: {$response['error']}");
                continue;
            }
            foreach ($chunk as $o) {
                $stats['checked']++;
                $info = $response[$o['provider_order_id']] ?? ['error' => 'Missing in provider response'];
                $result = apply_provider_status($o, is_array($info) ? $info : ['error' => (string)$info]);
                if (isset($stats[$result])) {
                    $stats[$result]++;
                } elseif ($result === 'error') {
                    $stats['errors']++;
                }
                // bump updated_at so the oldest-checked orders go first next run
                q('UPDATE orders SET updated_at = NOW() WHERE id = ?', [$o['id']]);
            }
        }
    }
    return $stats;
}

function update_provider_balances(): void
{
    foreach (all('SELECT * FROM providers WHERE is_active = 1') as $p) {
        $r = SmmProvider::fromRow($p)->balance();
        if (isset($r['balance']) && is_numeric($r['balance'])) {
            q('UPDATE providers SET balance = ?, currency = ?, balance_checked_at = NOW() WHERE id = ?',
                [$r['balance'], $r['currency'] ?? null, $p['id']]);
        }
    }
}

function status_label(string $status): string
{
    return [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'partial' => 'Partial',
        'canceled' => 'Canceled',
    ][$status] ?? ucfirst($status);
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(status_label($status)) . '</span>';
}

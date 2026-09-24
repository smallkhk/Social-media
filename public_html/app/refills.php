<?php
declare(strict_types=1);

const REFILL_FINAL = ['completed', 'rejected', 'error', 'canceled'];

/** Why an order can't be refilled right now, or null if it can. */
function refill_block_reason(array $order): ?string
{
    if (!in_array($order['status'], ['completed', 'partial'], true)) {
        return 'Only completed orders can be refilled';
    }
    if (empty($order['service_refill'])) {
        return 'This service has no refill guarantee';
    }
    if (!$order['provider_order_id']) {
        return 'This order has no provider reference';
    }
    $last = row('SELECT status, created_at FROM refills WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
    if ($last && !in_array($last['status'], REFILL_FINAL, true)) {
        return 'A refill for this order is already in progress';
    }
    if ($last && strtotime($last['created_at']) > time() - 86400) {
        return 'You can request one refill per order every 24 hours';
    }
    return null;
}

/** @return array{ok: bool, refill_id?: int, error?: string} */
function request_refill(int $userId, int $orderId): array
{
    $order = row('SELECT o.*, s.refill AS service_refill FROM orders o JOIN services s ON s.id = o.service_id WHERE o.id = ? AND o.user_id = ?',
        [$orderId, $userId]);
    if (!$order) {
        return ['ok' => false, 'error' => 'Incorrect order ID'];
    }
    if ($reason = refill_block_reason($order)) {
        return ['ok' => false, 'error' => $reason];
    }
    $api = SmmProvider::byId((int)$order['provider_id']);
    $result = $api ? $api->refill((string)$order['provider_order_id']) : ['error' => 'Provider missing'];
    if (!isset($result['refill']) || !is_scalar($result['refill'])) {
        $error = $result['error'] ?? (is_array($result['refill'] ?? null) ? ($result['refill']['error'] ?? 'Refused') : 'Refused');
        order_log($orderId, (int)$order['provider_id'], 'refill_failed', (string)$error);
        return ['ok' => false, 'error' => 'Refill not available: ' . $error];
    }
    q('INSERT INTO refills (order_id, user_id, provider_id, provider_refill_id) VALUES (?, ?, ?, ?)',
        [$orderId, $userId, $order['provider_id'], (string)$result['refill']]);
    $id = (int)db()->lastInsertId();
    order_log($orderId, (int)$order['provider_id'], 'refill', 'Provider refill ' . $result['refill']);
    return ['ok' => true, 'refill_id' => $id];
}

function normalize_refill_status(string $status): string
{
    $s = strtolower(trim($status));
    return match ($s) {
        'completed' => 'completed',
        'rejected' => 'rejected',
        'canceled', 'cancelled' => 'canceled',
        'in progress', 'processing' => 'in_progress',
        'pending', 'awaiting' => 'pending',
        default => $s === '' ? 'pending' : 'error',
    };
}

/** Update open refills from the providers (called by cron). */
function sync_refills(): int
{
    $updated = 0;
    $open = all("SELECT * FROM refills WHERE status IN ('pending','in_progress') ORDER BY updated_at LIMIT 500");
    $byProvider = [];
    foreach ($open as $r) {
        $byProvider[(int)$r['provider_id']][] = $r;
    }
    foreach ($byProvider as $providerId => $refills) {
        $api = SmmProvider::byId($providerId);
        if (!$api) {
            continue;
        }
        foreach (array_chunk($refills, 100) as $chunk) {
            $response = $api->refillStatuses(array_map(fn($r) => (string)$r['provider_refill_id'], $chunk));
            if (isset($response['error'])) {
                error_log("Refill sync failed for provider #$providerId: {$response['error']}");
                continue;
            }
            // Response is a list of {refill, status}; some panels key it by refill id instead
            $statuses = [];
            foreach ($response as $k => $item) {
                if (is_array($item) && isset($item['refill'])) {
                    $statuses[(string)$item['refill']] = $item['status'] ?? null;
                } elseif (is_array($item) && isset($item['status'])) {
                    $statuses[(string)$k] = $item['status'];
                }
            }
            foreach ($chunk as $r) {
                $raw = $statuses[(string)$r['provider_refill_id']] ?? null;
                if ($raw === null) {
                    continue;
                }
                $status = is_string($raw) ? normalize_refill_status($raw) : 'error';
                q('UPDATE refills SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $r['id']]);
                if ($status !== $r['status']) {
                    $updated++;
                }
            }
        }
    }
    return $updated;
}

function refill_label(string $status): string
{
    return ['pending' => 'Refill pending', 'in_progress' => 'Refilling', 'completed' => 'Refilled',
        'rejected' => 'Refill rejected', 'canceled' => 'Refill canceled', 'error' => 'Refill error'][$status] ?? $status;
}

<?php
declare(strict_types=1);

/**
 * Automatic USDT deposits on BSC (BEP-20) and TRON (TRC-20).
 *
 * Each deposit request gets a unique amount (e.g. 10.37). A payment is credited automatically only
 * when the blockchain shows a confirmed USDT transfer of exactly that amount to our wallet, made
 * after the request was created. That stops anyone claiming another customer's public transaction.
 * TRC-20 payments are found automatically; BEP-20 needs the customer to paste the transaction hash.
 */

const CRYPTO_NETWORKS = [
    'usdt_bsc' => ['label' => 'USDT BEP-20 (BSC)', 'short' => 'BEP-20', 'explorer' => 'https://bscscan.com/tx/'],
    'usdt_trc' => ['label' => 'USDT TRC-20 (TRON)', 'short' => 'TRC-20', 'explorer' => 'https://tronscan.org/#/transaction/'],
];
const USDT_BSC_CONTRACT = '55d398326f99059ff775485246999027b3197955'; // 18 decimals
const USDT_TRC_CONTRACT = 'a614f803b6fd780986a42c78ec9c7f77e6ded13c'; // TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t, 6 decimals
const ERC20_TRANSFER_TOPIC = 'ddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
const BSC_CONFIRMATIONS = 15;
const INVOICE_MINUTES = 120;   // time to pay before a request expires
const MAX_AUTO_CHECKS = 40;    // ~3 hours of cron checks before handing a stuck transaction to the admin

/** Networks switched on in Admin > Settings and with a valid wallet. */
function crypto_networks(): array
{
    $on = [];
    if (cfg('USDT_BSC_ENABLED') && bsc_address_valid((string)cfg('USDT_BSC_WALLET'))) {
        $on['usdt_bsc'] = CRYPTO_NETWORKS['usdt_bsc'] + ['wallet' => (string)cfg('USDT_BSC_WALLET')];
    }
    if (cfg('USDT_TRC_ENABLED') && tron_address_hex((string)cfg('USDT_TRC_WALLET')) !== null) {
        $on['usdt_trc'] = CRYPTO_NETWORKS['usdt_trc'] + ['wallet' => (string)cfg('USDT_TRC_WALLET')];
    }
    return $on;
}

function payment_label(string $method): string
{
    return CRYPTO_NETWORKS[$method]['label'] ?? ['bank' => 'Bank transfer', 'crypto' => 'Crypto', 'admin' => 'Admin'][$method] ?? ucfirst($method);
}

function is_invoice_placeholder(string $reference): bool
{
    return str_starts_with($reference, 'INV-');
}

// ---------------------------------------------------------------- number / address helpers

/** Arbitrary-size hex to decimal string (no bcmath/gmp needed). */
function hex_to_dec(string $hex): string
{
    $hex = strtolower($hex);
    if (str_starts_with($hex, '0x')) {
        $hex = substr($hex, 2);
    }
    $digits = [0]; // base 1e7, little endian
    foreach (str_split($hex !== '' ? $hex : '0') as $c) {
        $carry = hexdec($c);
        foreach ($digits as $i => $d) {
            $v = $d * 16 + $carry;
            $digits[$i] = $v % 10000000;
            $carry = intdiv($v, 10000000);
        }
        while ($carry > 0) {
            $digits[] = $carry % 10000000;
            $carry = intdiv($carry, 10000000);
        }
    }
    $out = (string)array_pop($digits);
    foreach (array_reverse($digits) as $d) {
        $out .= str_pad((string)$d, 7, '0', STR_PAD_LEFT);
    }
    return ltrim($out, '0') ?: '0';
}

/** Token amount from a raw hex/decimal integer and the token's decimals, as a decimal string. */
function token_amount(string $raw, int $decimals): string
{
    $dec = ctype_digit($raw) ? (ltrim($raw, '0') ?: '0') : hex_to_dec($raw);
    $dec = str_pad($dec, $decimals + 1, '0', STR_PAD_LEFT);
    $whole = substr($dec, 0, -$decimals);
    $frac = rtrim(substr($dec, -$decimals), '0');
    return $frac === '' ? $whole : "$whole.$frac";
}

function bsc_address_valid(string $address): bool
{
    return (bool)preg_match('/^0x[0-9a-fA-F]{40}$/', $address);
}

function base58_decode(string $input): ?string
{
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $bytes = [];
    foreach (str_split($input) as $char) {
        $carry = strpos($alphabet, $char);
        if ($carry === false) {
            return null;
        }
        for ($i = count($bytes) - 1; $i >= 0; $i--) {
            $carry += $bytes[$i] * 58;
            $bytes[$i] = $carry & 0xff;
            $carry >>= 8;
        }
        while ($carry > 0) {
            array_unshift($bytes, $carry & 0xff);
            $carry >>= 8;
        }
    }
    $leading = strlen($input) - strlen(ltrim($input, '1'));
    return str_repeat("\0", $leading) . implode('', array_map('chr', $bytes));
}

/** TRON base58 address (T...) to the 20-byte hex used in transfer logs, or null if invalid. */
function tron_address_hex(string $address): ?string
{
    if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
        return null;
    }
    $raw = base58_decode($address);
    if ($raw === null || strlen($raw) !== 25 || $raw[0] !== "\x41") {
        return null;
    }
    $checksum = substr(hash('sha256', hash('sha256', substr($raw, 0, 21), true), true), 0, 4);
    return hash_equals($checksum, substr($raw, 21)) ? bin2hex(substr($raw, 1, 20)) : null;
}

/** Clean a pasted transaction hash; null if it isn't one. */
function normalize_txid(string $network, string $txid): ?string
{
    $txid = strtolower(trim($txid));
    // Accept full explorer links too
    if (preg_match('/([0-9a-f]{64})\/?$/', $txid, $m) && !preg_match('/^(0x)?[0-9a-f]{64}$/', $txid)) {
        $txid = $m[1];
    }
    $hex = str_starts_with($txid, '0x') ? substr($txid, 2) : $txid;
    if (!preg_match('/^[0-9a-f]{64}$/', $hex)) {
        return null;
    }
    return $network === 'usdt_bsc' ? '0x' . $hex : $hex;
}

// ---------------------------------------------------------------- chain lookups

function http_json(string $url, ?array $post = null, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NoraPanel/3.0)',
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers),
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        return ['__error' => "connection failed ($err)"];
    }
    $data = json_decode((string)$body, true);
    return is_array($data) ? $data : ['__error' => "bad response (HTTP $code)"];
}

function bsc_rpc(string $method, array $params): array
{
    $urls = (string)cfg('BSC_RPC_URL') !== '' && defined('BSC_RPC_ONLY')
        ? [(string)cfg('BSC_RPC_URL')]
        : array_unique(array_filter([(string)cfg('BSC_RPC_URL'), 'https://bsc-dataseed.bnbchain.org', 'https://bsc-rpc.publicnode.com']));
    $last = ['__error' => 'no BSC RPC available'];
    foreach ($urls as $url) {
        $r = http_json($url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
        if (array_key_exists('result', $r)) {
            return $r;
        }
        $last = $r;
    }
    return $last;
}

function tron_api(string $path, ?array $post = null): array
{
    $key = (string)cfg('TRONGRID_API_KEY');
    $base = defined('TRON_API_URL') ? TRON_API_URL : 'https://api.trongrid.io';
    return http_json($base . $path, $post, $key !== '' ? ["TRON-PRO-API-KEY: $key"] : []);
}

/**
 * Look a transaction up on chain.
 * @return array{state: string, amount?: string, time?: int, message: string}
 *   state: ok (confirmed USDT transfer to $wallet), pending (not found / not confirmed yet), invalid
 */
function chain_lookup(string $network, string $txid, string $wallet): array
{
    if ($network === 'usdt_bsc') {
        $r = bsc_rpc('eth_getTransactionReceipt', [$txid]);
        if (isset($r['__error'])) {
            return ['state' => 'pending', 'message' => 'BSC network unreachable: ' . $r['__error']];
        }
        $receipt = $r['result'];
        if (!$receipt) {
            return ['state' => 'pending', 'message' => 'Transaction not found on BSC yet'];
        }
        if (($receipt['status'] ?? '') !== '0x1') {
            return ['state' => 'invalid', 'message' => 'This transaction failed on the blockchain'];
        }
        $to = str_pad(strtolower(substr($wallet, 2)), 64, '0', STR_PAD_LEFT);
        $total = '0';
        foreach ($receipt['logs'] ?? [] as $log) {
            if (strtolower(substr((string)($log['address'] ?? ''), 2)) === USDT_BSC_CONTRACT
                && strtolower(substr((string)($log['topics'][0] ?? ''), 2)) === ERC20_TRANSFER_TOPIC
                && strtolower(substr((string)($log['topics'][2] ?? ''), 2)) === $to) {
                $total = (string)((float)$total + (float)token_amount((string)$log['data'], 18));
            }
        }
        if ((float)$total <= 0) {
            return ['state' => 'invalid', 'message' => 'This transaction is not a USDT (BEP-20) payment to our wallet'];
        }
        $head = bsc_rpc('eth_blockNumber', []);
        $confirmations = isset($head['result']) ? hexdec($head['result']) - hexdec($receipt['blockNumber']) : 0;
        if ($confirmations < BSC_CONFIRMATIONS) {
            return ['state' => 'pending', 'message' => "Waiting for confirmations ($confirmations/" . BSC_CONFIRMATIONS . ')'];
        }
        $block = bsc_rpc('eth_getBlockByNumber', [$receipt['blockNumber'], false]);
        $time = isset($block['result']['timestamp']) ? hexdec($block['result']['timestamp']) : time();
        return ['state' => 'ok', 'amount' => $total, 'time' => (int)$time, 'message' => 'Confirmed'];
    }

    // TRON: walletsolidity only returns irreversible (confirmed) transactions
    $walletHex = tron_address_hex($wallet);
    $info = tron_api('/walletsolidity/gettransactioninfobyid', ['value' => $txid]);
    if (isset($info['__error'])) {
        return ['state' => 'pending', 'message' => 'TRON network unreachable: ' . $info['__error']];
    }
    if (empty($info['id'])) {
        $unconfirmed = tron_api('/wallet/gettransactioninfobyid', ['value' => $txid]);
        return ['state' => 'pending', 'message' => !empty($unconfirmed['id']) ? 'Waiting for confirmation' : 'Transaction not found on TRON yet'];
    }
    if (($info['receipt']['result'] ?? '') !== 'SUCCESS' || ($info['result'] ?? '') === 'FAILED') {
        return ['state' => 'invalid', 'message' => 'This transaction failed on the blockchain'];
    }
    $total = '0';
    foreach ($info['log'] ?? [] as $log) {
        if (strtolower((string)($log['address'] ?? '')) === USDT_TRC_CONTRACT
            && strtolower((string)($log['topics'][0] ?? '')) === ERC20_TRANSFER_TOPIC
            && substr(strtolower((string)($log['topics'][2] ?? '')), -40) === $walletHex) {
            $total = (string)((float)$total + (float)token_amount((string)$log['data'], 6));
        }
    }
    if ((float)$total <= 0) {
        return ['state' => 'invalid', 'message' => 'This transaction is not a USDT (TRC-20) payment to our wallet'];
    }
    return ['state' => 'ok', 'amount' => $total, 'time' => (int)(($info['blockTimeStamp'] ?? 0) / 1000), 'message' => 'Confirmed'];
}

// ---------------------------------------------------------------- deposit requests

/** Create a deposit request with a unique amount (base amount + random cents). */
function create_crypto_invoice(int $userId, string $network, float $amount): array
{
    $base = floor($amount);
    $taken = array_map(fn($a) => number_format((float)$a, 2, '.', ''), array_column(all(
        "SELECT amount FROM payments WHERE method = ? AND status = 'pending' AND created_at > NOW() - INTERVAL 3 DAY", [$network]), 'amount'));
    $unique = null;
    for ($extra = 0; $extra < 20 && $unique === null; $extra++) {
        $cents = range(1, 99);
        shuffle($cents);
        foreach ($cents as $c) {
            $candidate = number_format($base + $extra + $c / 100, 2, '.', '');
            if (!in_array($candidate, $taken, true)) {
                $unique = $candidate;
                break;
            }
        }
    }
    if ($unique === null) {
        return ['ok' => false, 'error' => 'Too many open deposits right now, please try again later.'];
    }
    q("INSERT INTO payments (user_id, amount, method, reference, status) VALUES (?, ?, ?, ?, 'pending')",
        [$userId, $unique, $network, 'INV-' . bin2hex(random_bytes(8))]);
    return ['ok' => true, 'id' => (int)db()->lastInsertId(), 'amount' => $unique];
}

/** Mark a deposit approved and credit the user once (safe to call twice). */
function approve_payment(int $paymentId, float $amount, string $note, ?string $txid = null): bool
{
    return transaction(function () use ($paymentId, $amount, $note, $txid) {
        $p = row("SELECT * FROM payments WHERE id = ? AND status = 'pending' FOR UPDATE", [$paymentId]);
        if (!$p || $amount <= 0) {
            return false;
        }
        q("UPDATE payments SET status = 'approved', amount = ?, admin_note = ?, reference = COALESCE(?, reference), needs_review = 0, processed_at = NOW() WHERE id = ?",
            [$amount, $note !== '' ? mb_substr($note, 0, 255) : null, $txid, $paymentId]);
        credit_user((int)$p['user_id'], $amount, 'deposit', 'Deposit #' . $paymentId . ' (' . payment_label($p['method']) . ')');
        return true;
    });
}

/**
 * Check a pending crypto deposit that has a transaction hash, and credit it when it matches.
 * @return array{state: string, message: string}  state: approved | pending | review | invalid
 */
function verify_crypto_payment(array $p): array
{
    $networks = crypto_networks() + CRYPTO_NETWORKS;
    $wallet = (string)cfg($p['method'] === 'usdt_bsc' ? 'USDT_BSC_WALLET' : 'USDT_TRC_WALLET');
    $result = chain_lookup($p['method'], $p['reference'], $wallet);
    q('UPDATE payments SET check_count = check_count + 1, checked_at = NOW() WHERE id = ?', [$p['id']]);

    if ($result['state'] === 'pending') {
        if ((int)$p['check_count'] + 1 >= MAX_AUTO_CHECKS) {
            q("UPDATE payments SET needs_review = 1, admin_note = ? WHERE id = ?", ['Still unconfirmed after many checks: ' . $result['message'], $p['id']]);
            return ['state' => 'review', 'message' => 'We could not confirm this transaction automatically. Our team will check it.'];
        }
        q('UPDATE payments SET admin_note = ? WHERE id = ?', [$result['message'], $p['id']]);
        return ['state' => 'pending', 'message' => $result['message']];
    }
    if ($result['state'] === 'invalid') {
        // Let the customer paste the right hash instead
        q('UPDATE payments SET reference = ?, admin_note = ? WHERE id = ?', ['INV-' . bin2hex(random_bytes(8)), $result['message'], $p['id']]);
        return ['state' => 'invalid', 'message' => $result['message'] . '. Check the transaction hash and try again.'];
    }

    $received = (float)$result['amount'];
    if ($result['time'] < strtotime($p['created_at']) - 600) {
        q('UPDATE payments SET reference = ?, admin_note = ? WHERE id = ?', ['INV-' . bin2hex(random_bytes(8)), 'Transaction is older than this deposit request', $p['id']]);
        return ['state' => 'invalid', 'message' => 'That transaction was made before this deposit request, so it can\'t be used for it.'];
    }
    if (abs($received - (float)$p['amount']) >= 0.005) {
        q('UPDATE payments SET needs_review = 1, admin_note = ? WHERE id = ?',
            ["Received $received USDT but the request was for " . number_format((float)$p['amount'], 2) . ' USDT', $p['id']]);
        return ['state' => 'review', 'message' => "We received $received USDT instead of the exact amount. Our team will review and credit it."];
    }
    approve_payment((int)$p['id'], $received, 'Auto-confirmed on ' . ($networks[$p['method']]['short'] ?? 'chain'));
    return ['state' => 'approved', 'message' => money($received) . ' has been added to your balance.'];
}

/** Find TRC-20 payments for open requests without a hash (exact amount, after the request). */
function scan_tron_deposits(): int
{
    $walletHex = tron_address_hex((string)cfg('USDT_TRC_WALLET'));
    if (!cfg('USDT_TRC_ENABLED') || $walletHex === null) {
        return 0;
    }
    $open = all("SELECT * FROM payments WHERE method = 'usdt_trc' AND status = 'pending' AND needs_review = 0 AND reference LIKE 'INV-%'");
    if (!$open) {
        return 0;
    }
    $since = min(array_map(fn($p) => strtotime($p['created_at']), $open)) - 600;
    $data = tron_api('/v1/accounts/' . urlencode((string)cfg('USDT_TRC_WALLET')) . '/transactions/trc20?only_to=true&only_confirmed=true&limit=200'
        . '&contract_address=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t&min_timestamp=' . ($since * 1000));
    if (empty($data['data']) || !is_array($data['data'])) {
        return 0;
    }
    $credited = 0;
    foreach ($data['data'] as $tx) {
        $txid = strtolower((string)($tx['transaction_id'] ?? ''));
        $amount = (float)token_amount((string)($tx['value'] ?? '0'), 6);
        $time = (int)(($tx['block_timestamp'] ?? 0) / 1000);
        if ($txid === '' || val("SELECT id FROM payments WHERE method = 'usdt_trc' AND reference = ?", [$txid])) {
            continue;
        }
        foreach ($open as $i => $p) {
            if (abs($amount - (float)$p['amount']) < 0.005 && $time >= strtotime($p['created_at']) - 600) {
                if (approve_payment((int)$p['id'], $amount, 'Auto-detected on TRC-20', $txid)) {
                    $credited++;
                }
                unset($open[$i]);
                break;
            }
        }
    }
    return $credited;
}

/** Cron: expire unpaid requests, find TRC-20 payments, verify pasted hashes. */
function sync_deposits(): array
{
    $stats = ['expired' => 0, 'credited' => 0, 'checked' => 0];
    $stats['expired'] = q("UPDATE payments SET status = 'rejected', admin_note = 'Expired - no payment received', processed_at = NOW()
        WHERE status = 'pending' AND method IN ('usdt_bsc','usdt_trc') AND reference LIKE 'INV-%' AND needs_review = 0
        AND created_at < NOW() - INTERVAL " . (INVOICE_MINUTES + 60) . ' MINUTE')->rowCount();
    $stats['credited'] += scan_tron_deposits();
    foreach (all("SELECT * FROM payments WHERE status = 'pending' AND method IN ('usdt_bsc','usdt_trc') AND needs_review = 0
                  AND reference NOT LIKE 'INV-%' ORDER BY checked_at IS NOT NULL, checked_at LIMIT 50") as $p) {
        $stats['checked']++;
        if (verify_crypto_payment($p)['state'] === 'approved') {
            $stats['credited']++;
        }
    }
    return $stats;
}

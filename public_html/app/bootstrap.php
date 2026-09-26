<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/config.php')) {
    // Not installed yet: send the browser to the web installer
    if (PHP_SAPI !== 'cli' && is_file(dirname(__DIR__) . '/install.php')) {
        $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $base = $docRoot !== '' ? str_replace('\\', '/', substr(dirname(__DIR__), strlen($docRoot))) : '';
        header('Location: ' . rtrim($base, '/') . '/install.php');
        exit;
    }
    http_response_code(500);
    exit('Not installed: open install.php in your browser (or copy app/config.sample.php to app/config.php).');
}
require __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set(TIMEZONE);

require __DIR__ . '/settings.php';
require __DIR__ . '/SmmProvider.php';
require __DIR__ . '/order_types.php';
require __DIR__ . '/orders.php';
require __DIR__ . '/refills.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/layout.php';

const IS_CLI = PHP_SAPI === 'cli';

// ---------------------------------------------------------------- database

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Database connection failed. Check DB_* settings in app/config.php.');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Run $fn inside a DB transaction; rolls back and rethrows on error. */
function transaction(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Add (or subtract, when negative) money from a user's balance and record it in the ledger. */
function credit_user(int $userId, float $amount, string $type, string $description): void
{
    q('UPDATE users SET balance = balance + ? WHERE id = ?', [$amount, $userId]);
    q('INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)',
        [$userId, $amount, $type, mb_substr($description, 0, 255)]);
}

// ---------------------------------------------------------------- output / urls

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function money($amount, int $decimals = 2): string
{
    return CURRENCY_SIGN . number_format((float)$amount, $decimals);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function random_code(int $length): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

// ---------------------------------------------------------------- session / csrf

if (!IS_CLI) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('nora_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('SKIP_CSRF')) {
        $sent = $_POST['_csrf'] ?? '';
        if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            http_response_code(419);
            exit('Your session expired. Go back, refresh the page and try again.');
        }
    }
}

function csrf_field(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="_csrf" value="' . e($_SESSION['csrf']) . '">';
}

// ---------------------------------------------------------------- auth

function too_many_attempts(string $scope): bool
{
    q('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
    $n = (int)val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND scope = ? AND created_at > NOW() - INTERVAL 15 MINUTE',
        [client_ip(), $scope]);
    return $n >= 10;
}

function record_attempt(string $scope): void
{
    q('INSERT INTO login_attempts (ip, scope) VALUES (?, ?)', [client_ip(), $scope]);
}

function clear_attempts(string $scope): void
{
    q('DELETE FROM login_attempts WHERE ip = ? AND scope = ?', [client_ip(), $scope]);
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $user = row("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['user_id']]);
            if (!$user) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function require_user(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function current_admin(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = empty($_SESSION['admin_id']) ? null : row('SELECT * FROM admins WHERE id = ?', [$_SESSION['admin_id']]);
    }
    return $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('admin/login.php');
    }
    return $admin;
}

function login_session(string $key, int $id): void
{
    session_regenerate_id(true);
    $_SESSION[$key] = $id;
}

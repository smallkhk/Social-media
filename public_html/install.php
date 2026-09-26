<?php
// Nora Panel web installer: creates the tables, writes app/config.php and the admin account.
// It locks itself once app/config.php exists (and deletes itself if the server allows).
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

const APP_DIR = __DIR__ . '/app';

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function field(string $name, string $default = ''): string
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? trim($_POST[$name]) : $default;
}

/** Fill config.sample.php with the given values (keeps all comments and defaults). */
function build_config(array $values): string
{
    $config = (string)file_get_contents(APP_DIR . '/config.sample.php');
    foreach ($values as $key => $value) {
        $config = preg_replace_callback(
            "/define\\('" . preg_quote($key, '/') . "',\\s*.*?\\);/",
            fn() => "define('$key', " . var_export($value, true) . ');',
            $config,
            1
        );
    }
    return $config;
}

$installed = is_file(APP_DIR . '/config.php');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'yourdomain.com'));
$basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/install.php'))), '/');
$guessUrl = 'https://' . $host . $basePath;
$mailDomain = preg_replace('/^www\./', '', explode(':', $host)[0]);

// Requirements
$checks = [
    'PHP 8.1 or newer (you have ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'pdo_mysql extension' => extension_loaded('pdo_mysql'),
    'curl extension' => extension_loaded('curl'),
    'mbstring extension' => extension_loaded('mbstring'),
    'app/ folder is writable' => is_writable(APP_DIR),
];
$requirementsOk = !in_array(false, $checks, true);

$errors = [];
$done = null;

if (!$installed && $requirementsOk && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $db = ['host' => field('db_host', 'localhost'), 'name' => field('db_name'), 'user' => field('db_user'), 'pass' => $_POST['db_pass'] ?? ''];
    $siteUrl = rtrim(field('site_url', $guessUrl), '/');
    $adminEmail = strtolower(field('admin_email'));
    $adminPass = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';

    if ($db['name'] === '' || $db['user'] === '') {
        $errors[] = 'Enter the database name and user from cPanel > MySQL Databases.';
    }
    if (!filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter a valid site address, like https://yourdomain.com';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid admin email.';
    }
    if (strlen($adminPass) < 10) {
        $errors[] = 'Admin password must be at least 10 characters.';
    }
    $bscWallet = field('bsc_wallet');
    $trcWallet = field('trc_wallet');
    if ($bscWallet !== '' && !preg_match('/^0x[0-9a-fA-F]{40}$/', $bscWallet)) {
        $errors[] = 'The USDT BEP-20 address must start with 0x followed by 40 characters.';
    }
    if ($trcWallet !== '' && !preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $trcWallet)) {
        $errors[] = 'The USDT TRC-20 address must start with T and have 34 characters.';
    }

    $pdo = null;
    if (!$errors) {
        try {
            $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['user'], (string)$db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            $errors[] = 'Could not connect to the database: ' . $e->getMessage()
                . '. Check the names include your cPanel prefix (e.g. cpaneluser_nora) and that the user was added to the database with ALL PRIVILEGES.';
        }
    }

    if ($pdo && !$errors) {
        $existing = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if ($existing) {
            $errors[] = 'This database already has Nora Panel tables. Use an empty database, or empty this one in phpMyAdmin first.';
        }
    }

    if ($pdo && !$errors) {
        try {
            $sql = (string)file_get_contents(APP_DIR . '/install.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
            }

            $pdo->prepare('INSERT INTO admins (email, password) VALUES (?, ?)')
                ->execute([$adminEmail, password_hash($adminPass, PASSWORD_DEFAULT)]);
            $setting = $pdo->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)');
            foreach (['USDT_BSC' => $bscWallet, 'USDT_TRC' => $trcWallet] as $prefix => $wallet) {
                $setting->execute([$prefix . '_WALLET', $wallet]);
                $setting->execute([$prefix . '_ENABLED', $wallet !== '' ? '1' : '0']);
            }
            if (field('mtp_key') !== '') {
                $pdo->prepare("UPDATE providers SET api_key = ? WHERE name = 'MoreThanPanel'")->execute([field('mtp_key')]);
            }

            $cronToken = bin2hex(random_bytes(24));
            $config = build_config([
                'DB_HOST' => $db['host'],
                'DB_NAME' => $db['name'],
                'DB_USER' => $db['user'],
                'DB_PASS' => (string)$db['pass'],
                'SITE_NAME' => field('site_name', 'Nora Panel') ?: 'Nora Panel',
                'SITE_URL' => $siteUrl,
                'CRON_TOKEN' => $cronToken,
                'MAIL_FROM' => field('mail_from') ?: 'no-reply@' . $mailDomain,
                'CRYPTO_ENABLED' => false,
                'BANK_ENABLED' => field('bank_details') !== '',
                'BANK_DETAILS' => str_replace("\r\n", "\n", field('bank_details')),
            ]);
            if (@file_put_contents(APP_DIR . '/config.php', $config) === false) {
                throw new RuntimeException('Could not write app/config.php - check folder permissions (755) in File Manager.');
            }
            @chmod(APP_DIR . '/config.php', 0640);

            $done = [
                'admin' => $siteUrl . '/admin/',
                'site' => $siteUrl . '/',
                'cron' => '/usr/local/bin/php ' . __DIR__ . '/cron/sync.php >/dev/null 2>&1',
                'cron_url' => 'wget -q -O /dev/null "' . $siteUrl . '/cron/sync.php?token=' . $cronToken . '"',
                'mtp' => field('mtp_key') !== '',
                'deleted' => @unlink(__FILE__),
            ];
        } catch (Throwable $e) {
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Install Nora Panel</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>.container{max-width:760px} fieldset{border:1px solid var(--border);border-radius:10px;padding:16px 18px;margin-bottom:18px} legend{font-weight:700;padding:0 6px} .ok{color:var(--ok)} .bad{color:var(--bad)}</style>
</head>
<body>
<main class="container">
    <h1>Install Nora Panel</h1>

<?php if ($done): ?>
    <div class="alert alert-success">Installed successfully.</div>
    <div class="card">
        <h2>Last 3 steps</h2>
        <p><strong>1. Cron job</strong> - cPanel &rarr; Cron Jobs &rarr; "Once Per Five Minutes" &rarr; paste this command:</p>
        <div class="code" style="margin:8px 0 4px"><?= h($done['cron']) ?></div>
        <p class="help" style="margin-bottom:14px">If that doesn't run on your plan, use this instead: <span class="code" style="display:block;margin-top:4px"><?= h($done['cron_url']) ?></span></p>
        <p><strong>2. SSL</strong> - cPanel &rarr; SSL/TLS Status &rarr; Run AutoSSL (the site only works over https).</p>
        <p style="margin-top:14px"><strong>3. Services</strong> - log in to the admin area &rarr; Providers
            <?= $done['mtp'] ? '&rarr; Import services' : '&rarr; Edit MoreThanPanel, paste your API key &rarr; Import services' ?>.</p>
        <?php if (!$done['deleted']): ?>
            <div class="alert alert-info" style="margin-top:14px">For tidiness, delete <strong>install.php</strong> in File Manager. (It is already locked and can't be run again.)</div>
        <?php endif; ?>
        <p style="margin-top:18px"><a class="btn" href="<?= h($done['admin']) ?>">Open admin area</a> <a class="btn btn-light" href="<?= h($done['site']) ?>">Open site</a></p>
    </div>

<?php elseif ($installed): ?>
    <div class="alert alert-info">Nora Panel is already installed. This installer is locked. You can delete install.php.</div>
    <p><a class="btn" href="admin/">Go to admin</a></p>

<?php else: ?>
    <div class="card">
        <h2>Requirements</h2>
        <?php foreach ($checks as $label => $ok): ?>
            <div class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '&#10003;' : '&#10007;' ?> <?= h($label) ?></div>
        <?php endforeach; ?>
        <?php if (!$requirementsOk): ?>
            <p class="help" style="margin-top:10px">Fix this first: cPanel &rarr; Select PHP Version &rarr; choose 8.1+ and tick pdo_mysql, curl, mbstring. Then reload this page.</p>
        <?php endif; ?>
    </div>

    <?php if ($requirementsOk): ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= h($err) ?></div><?php endforeach; ?>
    <form method="post" autocomplete="off">
        <fieldset>
            <legend>Database</legend>
            <p class="help" style="margin-bottom:12px">Create it first: cPanel &rarr; MySQL Databases &rarr; create a database and a user, then "Add User To Database" with ALL PRIVILEGES.</p>
            <div class="form-row">
                <div class="form-group"><label>Database name</label><input type="text" name="db_name" value="<?= h(field('db_name')) ?>" placeholder="cpaneluser_nora" required></div>
                <div class="form-group"><label>Database user</label><input type="text" name="db_user" value="<?= h(field('db_user')) ?>" placeholder="cpaneluser_nora" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Database password</label><input type="password" name="db_pass"></div>
                <div class="form-group"><label>Database host</label><input type="text" name="db_host" value="<?= h(field('db_host', 'localhost')) ?>"></div>
            </div>
        </fieldset>
        <fieldset>
            <legend>Site</legend>
            <div class="form-row">
                <div class="form-group"><label>Site name</label><input type="text" name="site_name" value="<?= h(field('site_name', 'Nora Panel')) ?>"></div>
                <div class="form-group"><label>Site address</label><input type="url" name="site_url" value="<?= h(field('site_url', $guessUrl)) ?>" required></div>
            </div>
        </fieldset>
        <fieldset>
            <legend>Your admin login</legend>
            <div class="form-row">
                <div class="form-group"><label>Admin email</label><input type="email" name="admin_email" value="<?= h(field('admin_email')) ?>" required></div>
                <div class="form-group"><label>Admin password (10+ characters)</label><input type="password" name="admin_password" minlength="10" required></div>
            </div>
        </fieldset>
        <fieldset>
            <legend>MoreThanPanel</legend>
            <div class="form-group"><label>API key (optional, can be added later)</label><input type="text" name="mtp_key" value="<?= h(field('mtp_key')) ?>" placeholder="From morethanpanel.com &rarr; API page"></div>
        </fieldset>
        <fieldset>
            <legend>How customers pay you (optional, editable later in Admin &rarr; Settings)</legend>
            <p class="help" style="margin-bottom:10px">USDT deposits are confirmed on the blockchain and credited automatically. Fill in one or both.</p>
            <div class="form-group"><label>USDT BEP-20 (BSC) address</label><input type="text" name="bsc_wallet" value="<?= h(field('bsc_wallet')) ?>" placeholder="0x..."></div>
            <div class="form-group"><label>USDT TRC-20 (TRON) address</label><input type="text" name="trc_wallet" value="<?= h(field('trc_wallet')) ?>" placeholder="T..."></div>
            <div class="form-group"><label>Bank transfer details (leave empty to disable)</label>
                <textarea name="bank_details" rows="3" placeholder="Bank: ...&#10;Account name: ...&#10;Account number: ..."><?= h(field('bank_details')) ?></textarea></div>
            <div class="form-group"><label>Email sender for password resets</label><input type="email" name="mail_from" value="<?= h(field('mail_from', 'no-reply@' . $mailDomain)) ?>">
                <div class="help">Create this mailbox in cPanel &rarr; Email Accounts so emails don't go to spam.</div></div>
        </fieldset>
        <button class="btn btn-block">Install</button>
    </form>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>

<?php
require __DIR__ . '/../app/bootstrap.php';
$admin = require_admin();

$text = ['SITE_NAME', 'USDT_BSC_WALLET', 'USDT_TRC_WALLET', 'TRONGRID_API_KEY', 'BSC_RPC_URL', 'BANK_DETAILS', 'BANK_RATE_NOTE', 'MAIL_FROM',
    'SMTP_HOST', 'SMTP_USER', 'SUPPORT_EMAIL', 'SUPPORT_WHATSAPP', 'SUPPORT_TELEGRAM', 'SUPPORT_NOTE'];
$numbers = ['MIN_DEPOSIT', 'AFFILIATE_PERCENT', 'AFFILIATE_MIN_TRANSFER', 'SMTP_PORT'];
$flags = ['USDT_BSC_ENABLED', 'USDT_TRC_ENABLED', 'BANK_ENABLED', 'REGISTRATION_OPEN'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'test_mail') {
        $to = post('test_to') ?: $admin['email'];
        $error = mail_send($to, 'Test email from ' . cfg('SITE_NAME'), "It works! Emails from your panel (password resets, ticket replies) will be delivered like this one.\n");
        flash($error ? 'error' : 'success', $error ? "Test email failed: $error" : "Test email sent to $to. Check the inbox (and spam folder).");
        redirect('admin/settings.php#email');
    }

    $values = [];
    foreach ($text as $key) {
        $values[$key] = str_replace("\r\n", "\n", post($key));
    }
    foreach ($numbers as $key) {
        $values[$key] = max(0, (float)post($key));
    }
    foreach ($flags as $key) {
        $values[$key] = isset($_POST[$key]);
    }
    $values['SMTP_PORT'] = (int)$values['SMTP_PORT'];
    $values['SMTP_SECURE'] = in_array(post('SMTP_SECURE'), ['ssl', 'tls', 'none'], true) ? post('SMTP_SECURE') : 'ssl';
    if (post('SMTP_PASS') !== '') {
        $values['SMTP_PASS'] = post('SMTP_PASS');
    }
    if (isset($_POST['smtp_clear_pass'])) {
        $values['SMTP_PASS'] = '';
    }

    if ($values['SITE_NAME'] === '') {
        flash('error', 'Site name cannot be empty.');
    } elseif ($values['MAIL_FROM'] !== '' && !filter_var($values['MAIL_FROM'], FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Sender email is not a valid address.');
    } elseif ($values['SUPPORT_EMAIL'] !== '' && !filter_var($values['SUPPORT_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Support email is not a valid address.');
    } elseif ($values['USDT_BSC_ENABLED'] && !bsc_address_valid($values['USDT_BSC_WALLET'])) {
        flash('error', 'The BEP-20 (BSC) address must start with 0x followed by 40 characters. Copy it again from your wallet.');
    } elseif ($values['USDT_TRC_ENABLED'] && tron_address_hex($values['USDT_TRC_WALLET']) === null) {
        flash('error', 'The TRC-20 (TRON) address is not valid (it starts with T and has 34 characters). Copy it again from your wallet.');
    } elseif ($values['BSC_RPC_URL'] !== '' && !preg_match('#^https?://#', $values['BSC_RPC_URL'])) {
        flash('error', 'The BSC RPC address must start with https://');
    } elseif ($values['BANK_ENABLED'] && $values['BANK_DETAILS'] === '') {
        flash('error', 'Enter bank details or switch bank transfer off.');
    } else {
        save_settings($values);
        flash('success', 'Settings saved.');
    }
    redirect('admin/settings.php');
}

$v = fn(string $key) => cfg($key);

page_header('Settings', 'admin');
?>
<h1>Settings</h1>
<form method="post">
<?= csrf_field() ?>
<div class="grid-2">
<div>
    <div class="card" id="payments">
        <h2>Payments (Add funds page)</h2>
        <p class="help" style="margin-bottom:10px">USDT deposits are checked on the blockchain and credited automatically.
            Use addresses from a wallet you control (Trust Wallet, MetaMask, TronLink, or an exchange deposit address that accepts that network).</p>
        <label class="checkbox"><input type="checkbox" name="USDT_BSC_ENABLED" <?= $v('USDT_BSC_ENABLED') ? 'checked' : '' ?>> Accept USDT BEP-20 (BSC)</label>
        <div class="form-group" style="margin-top:6px"><input type="text" name="USDT_BSC_WALLET" value="<?= e($v('USDT_BSC_WALLET')) ?>" placeholder="0x... your BSC address"></div>
        <label class="checkbox"><input type="checkbox" name="USDT_TRC_ENABLED" <?= $v('USDT_TRC_ENABLED') ? 'checked' : '' ?>> Accept USDT TRC-20 (TRON)</label>
        <div class="form-group" style="margin-top:6px"><input type="text" name="USDT_TRC_WALLET" value="<?= e($v('USDT_TRC_WALLET')) ?>" placeholder="T... your TRON address"></div>
        <details style="margin-bottom:14px"><summary class="help" style="cursor:pointer">Advanced (optional)</summary>
            <div class="form-group" style="margin-top:8px"><label>TronGrid API key</label><input type="text" name="TRONGRID_API_KEY" value="<?= e($v('TRONGRID_API_KEY')) ?>" autocomplete="off">
                <div class="help">Free at trongrid.io. Recommended once you get many TRC-20 deposits (avoids rate limits).</div></div>
            <div class="form-group"><label>BSC RPC address</label><input type="url" name="BSC_RPC_URL" value="<?= e($v('BSC_RPC_URL')) ?>" placeholder="https://bsc-dataseed.bnbchain.org">
                <div class="help">Leave empty to use public BSC nodes.</div></div>
        </details>
        <label class="checkbox"><input type="checkbox" name="BANK_ENABLED" <?= $v('BANK_ENABLED') ? 'checked' : '' ?>> Accept bank transfer</label>
        <div class="form-group" style="margin-top:10px"><label>Bank details</label><textarea name="BANK_DETAILS" rows="4" placeholder="Bank: ...&#10;Account name: ...&#10;Account number: ..."><?= e($v('BANK_DETAILS')) ?></textarea></div>
        <div class="form-group"><label>Exchange rate note (optional)</label><input type="text" name="BANK_RATE_NOTE" value="<?= e($v('BANK_RATE_NOTE')) ?>" placeholder="1 USD = 1500 NGN"></div>
        <div class="form-group"><label>Minimum deposit (USD)</label><input type="number" name="MIN_DEPOSIT" step="0.01" min="0" value="<?= e($v('MIN_DEPOSIT')) ?>"></div>
    </div>

    <div class="card" id="support">
        <h2>Support contacts</h2>
        <p class="help" style="margin-bottom:10px">Shown on the customer Support page next to the ticket system. Leave empty to hide.</p>
        <div class="form-group"><label>Support email</label><input type="email" name="SUPPORT_EMAIL" value="<?= e($v('SUPPORT_EMAIL')) ?>" placeholder="support@yourdomain.com"></div>
        <div class="form-group"><label>WhatsApp number</label><input type="text" name="SUPPORT_WHATSAPP" value="<?= e($v('SUPPORT_WHATSAPP')) ?>" placeholder="+2348012345678"></div>
        <div class="form-group"><label>Telegram username</label><input type="text" name="SUPPORT_TELEGRAM" value="<?= e($v('SUPPORT_TELEGRAM')) ?>" placeholder="@yourname"></div>
        <div class="form-group"><label>Note (e.g. working hours)</label><textarea name="SUPPORT_NOTE" rows="2"><?= e($v('SUPPORT_NOTE')) ?></textarea></div>
    </div>
</div>
<div>
    <div class="card" id="email">
        <h2>Email</h2>
        <div class="form-group"><label>Sender address</label><input type="email" name="MAIL_FROM" value="<?= e($v('MAIL_FROM')) ?>" placeholder="no-reply@yourdomain.com">
            <div class="help">Create this mailbox in cPanel &rarr; Email Accounts.</div></div>
        <p class="help" style="margin:4px 0 12px">Leave <strong>SMTP host</strong> empty to send with the server's built-in mail (fine on Namecheap).
            For better delivery use SMTP: on Namecheap host <code>mail.yourdomain.com</code>, port <code>465</code>, SSL, and the mailbox's email + password.</p>
        <div class="form-row">
            <div class="form-group"><label>SMTP host</label><input type="text" name="SMTP_HOST" value="<?= e($v('SMTP_HOST')) ?>" placeholder="mail.yourdomain.com"></div>
            <div class="form-group"><label>Port</label><input type="number" name="SMTP_PORT" value="<?= e($v('SMTP_PORT')) ?>"></div>
            <div class="form-group"><label>Security</label><select name="SMTP_SECURE">
                <?php foreach (['ssl' => 'SSL (465)', 'tls' => 'STARTTLS (587)', 'none' => 'None'] as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $v('SMTP_SECURE') === $k ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?></select></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>SMTP username</label><input type="text" name="SMTP_USER" value="<?= e($v('SMTP_USER')) ?>" autocomplete="off" placeholder="no-reply@yourdomain.com"></div>
            <div class="form-group"><label>SMTP password</label><input type="password" name="SMTP_PASS" autocomplete="new-password" placeholder="<?= $v('SMTP_PASS') !== '' ? 'Saved - leave blank to keep' : '' ?>">
                <?php if ($v('SMTP_PASS') !== ''): ?><label class="checkbox help"><input type="checkbox" name="smtp_clear_pass"> remove saved password</label><?php endif; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>General</h2>
        <div class="form-group"><label>Site name</label><input type="text" name="SITE_NAME" value="<?= e($v('SITE_NAME')) ?>" required></div>
        <label class="checkbox"><input type="checkbox" name="REGISTRATION_OPEN" <?= $v('REGISTRATION_OPEN') ? 'checked' : '' ?>> Allow new sign-ups</label>
        <h2 style="margin-top:18px">Affiliate program</h2>
        <div class="form-row">
            <div class="form-group"><label>Commission % (0 = off)</label><input type="number" name="AFFILIATE_PERCENT" step="0.1" min="0" value="<?= e($v('AFFILIATE_PERCENT')) ?>"></div>
            <div class="form-group"><label>Min. transfer (USD)</label><input type="number" name="AFFILIATE_MIN_TRANSFER" step="0.01" min="0" value="<?= e($v('AFFILIATE_MIN_TRANSFER')) ?>"></div>
        </div>
    </div>
    <button class="btn btn-block">Save settings</button>
</div>
</div>
</form>

<div class="card" style="margin-top:20px">
    <h2>Send a test email</h2>
    <p class="help" style="margin-bottom:10px">Save your email settings first, then test them here.</p>
    <form method="post" class="filters">
        <?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
        <input type="email" name="test_to" value="<?= e($admin['email']) ?>" style="min-width:260px">
        <button class="btn btn-sm">Send test email</button>
    </form>
</div>
<?php page_footer();

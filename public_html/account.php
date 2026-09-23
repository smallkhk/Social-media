<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'password') {
        $new = post('new_password');
        if (!password_verify(post('current_password'), $user['password'])) {
            flash('error', 'Current password is wrong.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== post('confirm_password')) {
            flash('error', 'New passwords do not match.');
        } else {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'api_key') {
        q('UPDATE users SET api_key = ? WHERE id = ?', [bin2hex(random_bytes(32)), $user['id']]);
        flash('success', 'New API key generated. The old key no longer works.');
    }
    redirect('account.php');
}

page_header('Account');
?>
<h1>Account</h1>
<div class="grid-2">
<div class="card">
    <h2>Profile</h2>
    <p>Username: <strong><?= e($user['username']) ?></strong></p>
    <p>Email: <strong><?= e($user['email']) ?></strong></p>
    <p>Member since: <?= e(date('Y-m-d', strtotime($user['created_at']))) ?></p>

    <h2 style="margin-top:24px">Change password</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="form-group"><label>Current password</label><input type="password" name="current_password" required></div>
        <div class="form-group"><label>New password</label><input type="password" name="new_password" required minlength="8"></div>
        <div class="form-group"><label>Confirm new password</label><input type="password" name="confirm_password" required minlength="8"></div>
        <button class="btn">Change password</button>
    </form>
</div>
<div class="card">
    <h2>API access</h2>
    <p class="muted">Resell our services from your own panel with the standard SMM API v2.</p>
    <label style="margin-top:12px">API URL</label>
    <div class="code"><?= e(url('api/v2')) ?></div>
    <label style="margin-top:12px">Your API key</label>
    <div class="code"><?= e($user['api_key']) ?></div>
    <form method="post" style="margin-top:10px" onsubmit="return confirm('Generate a new key? The current key stops working.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="api_key">
        <button class="btn btn-light btn-sm">Generate new key</button>
    </form>
    <h2 style="margin-top:20px">Actions</h2>
    <div class="table-wrap"><table>
        <tr><th>action</th><th>Parameters</th><th>Returns</th></tr>
        <tr><td>services</td><td>-</td><td>service list with rate per 1000</td></tr>
        <tr><td>add</td><td>service, link, quantity</td><td>{"order": 123}</td></tr>
        <tr><td>status</td><td>order <em>or</em> orders (comma separated, max 100)</td><td>charge, start_count, status, remains, currency</td></tr>
        <tr><td>balance</td><td>-</td><td>{"balance": "10.00", "currency": "USD"}</td></tr>
    </table></div>
    <p class="help" style="margin-top:8px">Send every request as POST with <code>key</code> and <code>action</code>.</p>
</div>
</div>
<?php page_footer();

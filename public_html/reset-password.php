<?php
require __DIR__ . '/app/bootstrap.php';

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$user = password_reset_user($token);

$error = '';
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = post('password');
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== post('password_confirm')) {
        $error = 'Passwords do not match.';
    } else {
        transaction(function () use ($user, $password) {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            q('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
        });
        clear_attempts('user');
        flash('success', 'Password changed. You can log in now.');
        redirect('login.php');
    }
}

page_header('Choose a new password', 'auth');
?>
<div class="auth-card">
    <h1>New password</h1>
    <?php if (!$user): ?>
        <div class="alert alert-error">This reset link is invalid or has expired.</div>
        <p class="foot"><a href="<?= e(url('forgot-password.php')) ?>">Request a new link</a></p>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <p class="muted" style="margin-bottom:14px">Account: <?= e($user['username']) ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-group"><label for="password">New password</label><input type="password" id="password" name="password" required minlength="8" autofocus></div>
            <div class="form-group"><label for="password_confirm">Confirm password</label><input type="password" id="password_confirm" name="password_confirm" required minlength="8"></div>
            <button class="btn btn-block">Save password</button>
        </form>
    <?php endif; ?>
</div>
<?php page_footer();

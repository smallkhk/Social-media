<?php
require __DIR__ . '/app/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$sent = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (too_many_attempts('reset')) {
        $error = 'Too many requests. Try again in 15 minutes.';
    } else {
        record_attempt('reset');
        $user = row("SELECT * FROM users WHERE email = ? AND status = 'active'", [strtolower(post('email'))]);
        if ($user) {
            send_password_reset($user);
        }
        // Same answer whether or not the email exists, so accounts can't be discovered
        $sent = true;
    }
}

page_header('Forgot password', 'auth');
?>
<div class="auth-card">
    <h1>Reset password</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($sent): ?>
        <div class="alert alert-success">If that email has an account, a reset link is on its way. It works for 1 hour. Check your spam folder too.</div>
    <?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Account email</label>
            <input type="email" id="email" name="email" required autofocus>
        </div>
        <button class="btn btn-block">Send reset link</button>
    </form>
    <?php endif; ?>
    <p class="foot"><a href="<?= e(url('login.php')) ?>">Back to log in</a></p>
</div>
<?php page_footer();

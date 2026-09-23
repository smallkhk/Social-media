<?php
require __DIR__ . '/app/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = post('login');
    $password = post('password');

    if (too_many_attempts('user')) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $user = row('SELECT * FROM users WHERE email = ? OR username = ?', [$login, $login]);
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'This account is suspended.';
            } else {
                clear_attempts('user');
                login_session('user_id', (int)$user['id']);
                q('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
                redirect('dashboard.php');
            }
        } else {
            record_attempt('user');
            $error = 'Wrong email/username or password.';
        }
    }
}

page_header('Log in', 'auth');
?>
<div class="auth-card">
    <h1><?= e(SITE_NAME) ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="login">Email or username</label>
            <input type="text" id="login" name="login" value="<?= e(post('login')) ?>" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button class="btn btn-block">Log in</button>
    </form>
    <?php if (REGISTRATION_OPEN): ?>
        <p class="foot">No account? <a href="<?= e(url('register.php')) ?>">Sign up</a></p>
    <?php endif; ?>
</div>
<?php page_footer();

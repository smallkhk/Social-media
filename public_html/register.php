<?php
require __DIR__ . '/app/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}
if (isset($_GET['ref']) && is_string($_GET['ref'])) {
    $_SESSION['ref'] = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($_GET['ref'])), 0, 16);
}

$error = '';
if (!cfg('REGISTRATION_OPEN')) {
    $error = 'Registration is currently closed.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $email = strtolower(post('email'));
    $password = post('password');

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $error = 'Username must be 3-30 letters, numbers or underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== post('password_confirm')) {
        $error = 'Passwords do not match.';
    } elseif (too_many_attempts('register')) {
        $error = 'Too many sign-ups from your network. Try again later.';
    } elseif (row('SELECT id FROM users WHERE email = ? OR username = ?', [$email, $username])) {
        $error = 'That email or username is already registered.';
    } else {
        $referrer = !empty($_SESSION['ref']) ? val('SELECT id FROM users WHERE referral_code = ?', [$_SESSION['ref']]) : null;
        do {
            $code = random_code(8);
        } while (val('SELECT id FROM users WHERE referral_code = ?', [$code]));

        q('INSERT INTO users (username, email, password, api_key, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, ?)', [
            $username, $email, password_hash($password, PASSWORD_DEFAULT), bin2hex(random_bytes(32)), $code, $referrer,
        ]);
        $newUserId = (int)db()->lastInsertId();
        record_attempt('register');
        unset($_SESSION['ref']);
        login_session('user_id', $newUserId);
        flash('success', 'Welcome! Add funds to your wallet to place your first order.');
        redirect('dashboard.php');
    }
}

page_header('Sign up', 'auth');
?>
<div class="auth-card">
    <h1>Create account</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (cfg('REGISTRATION_OPEN')): ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e(post('username')) ?>" required pattern="[A-Za-z0-9_]{3,30}">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e(post('email')) ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
        </div>
        <button class="btn btn-block">Sign up</button>
    </form>
    <?php endif; ?>
    <p class="foot">Already have an account? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
</div>
<?php page_footer();

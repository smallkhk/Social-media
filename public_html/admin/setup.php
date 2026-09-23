<?php
// One-time: creates the first admin account. Locked as soon as an admin exists.
require __DIR__ . '/../app/bootstrap.php';

if ((int)val('SELECT COUNT(*) FROM admins') > 0) {
    redirect('admin/login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(post('email'));
    $password = post('password');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email.';
    } elseif (strlen($password) < 10) {
        $error = 'Use at least 10 characters for the admin password.';
    } elseif ($password !== post('password_confirm')) {
        $error = 'Passwords do not match.';
    } else {
        q('INSERT INTO admins (email, password) VALUES (?, ?)', [$email, password_hash($password, PASSWORD_DEFAULT)]);
        login_session('admin_id', (int)db()->lastInsertId());
        flash('success', 'Admin account created. Next: add your provider API key.');
        redirect('admin/providers.php');
    }
}

page_header('Admin setup', 'auth');
?>
<div class="auth-card">
    <h1>Create admin account</h1>
    <p class="muted" style="margin-bottom:14px">This page only works once, while no admin exists.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label>Admin email</label><input type="email" name="email" value="<?= e(post('email')) ?>" required></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required minlength="10"></div>
        <div class="form-group"><label>Confirm password</label><input type="password" name="password_confirm" required minlength="10"></div>
        <button class="btn btn-block">Create admin</button>
    </form>
</div>
<?php page_footer();

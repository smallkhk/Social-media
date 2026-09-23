<?php
require __DIR__ . '/../app/bootstrap.php';

if ((int)val('SELECT COUNT(*) FROM admins') === 0) {
    redirect('admin/setup.php');
}
if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (too_many_attempts('admin')) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $admin = row('SELECT * FROM admins WHERE email = ?', [strtolower(post('email'))]);
        if ($admin && password_verify(post('password'), $admin['password'])) {
            clear_attempts('admin');
            login_session('admin_id', (int)$admin['id']);
            q('UPDATE admins SET last_login = NOW() WHERE id = ?', [$admin['id']]);
            redirect('admin/index.php');
        }
        record_attempt('admin');
        $error = 'Wrong email or password.';
    }
}

page_header('Admin login', 'auth');
?>
<div class="auth-card">
    <h1>Admin login</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e(post('email')) ?>" required autofocus></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn btn-block">Log in</button>
    </form>
</div>
<?php page_footer();

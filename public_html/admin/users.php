<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    $action = post('action');
    $user = row('SELECT * FROM users WHERE id = ?', [$id]);

    if ($user && $action === 'balance') {
        $amount = round((float)post('amount'), 4);
        $note = post('note') ?: 'Adjustment by admin';
        if ($amount == 0.0) {
            flash('error', 'Enter a non-zero amount (negative to deduct).');
        } else {
            transaction(fn() => credit_user($id, $amount, 'admin', $note));
            flash('success', "Balance of {$user['username']} changed by " . money($amount, 4) . '.');
        }
    } elseif ($user && $action === 'status') {
        q('UPDATE users SET status = ? WHERE id = ?', [$user['status'] === 'active' ? 'banned' : 'active', $id]);
        flash('success', "User {$user['username']} " . ($user['status'] === 'active' ? 'banned' : 'unbanned') . '.');
    } elseif ($user && $action === 'password') {
        $new = post('password');
        if (strlen($new) < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } else {
            q('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
            flash('success', "Password for {$user['username']} changed.");
        }
    }
    redirect('admin/users.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'page', 'view']))));
}

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$where = $search !== '' ? 'WHERE u.username LIKE ? OR u.email LIKE ? OR u.id = ?' : '';
$params = $search !== '' ? ['%' . $search . '%', '%' . $search . '%', (int)$search] : [];
$perPage = 50;
$total = (int)val("SELECT COUNT(*) FROM users u $where", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$users = all("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS orders,
                     (SELECT COALESCE(SUM(o.charge - o.refunded), 0) FROM orders o WHERE o.user_id = u.id) AS spent
              FROM users u $where ORDER BY u.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

$view = isset($_GET['view']) ? row('SELECT * FROM users WHERE id = ?', [(int)$_GET['view']]) : null;
$ledger = $view ? all('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$view['id']]) : [];

page_header('Users', 'admin');
?>
<h1>Users</h1>
<?php if ($view): ?>
<div class="card">
    <h2><?= e($view['username']) ?> <small>(<?= e($view['email']) ?>)</small></h2>
    <p>Balance: <strong><?= money($view['balance'], 4) ?></strong> &middot; Affiliate: <?= money($view['affiliate_balance'], 4) ?> &middot; Joined <?= e($view['created_at']) ?> &middot; Last login <?= e($view['last_login'] ?? 'never') ?></p>
    <div class="grid-2" style="margin-top:14px">
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$view['id'] ?>"><input type="hidden" name="action" value="balance">
            <label>Add / deduct balance</label>
            <div class="filters"><input type="number" step="0.01" name="amount" placeholder="10 or -10" style="width:130px" required>
                <input type="text" name="note" placeholder="Reason (shown to user)"><button class="btn btn-sm">Apply</button></div>
        </form>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$view['id'] ?>"><input type="hidden" name="action" value="password">
            <label>Set new password</label>
            <div class="filters"><input type="text" name="password" minlength="8" required autocomplete="off"><button class="btn btn-sm">Set</button></div>
        </form>
    </div>
    <h2 style="margin-top:14px">Balance history</h2>
    <div class="table-wrap"><table>
        <tr><th>Date</th><th>Type</th><th>Description</th><th class="num">Amount</th></tr>
        <?php foreach ($ledger as $t): ?>
            <tr><td><?= e($t['created_at']) ?></td><td><?= e($t['type']) ?></td><td><?= e($t['description']) ?></td><td class="num"><?= money($t['amount'], 4) ?></td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php endif; ?>
<div class="card">
    <form method="get" class="filters">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Username, email or ID"><button class="btn btn-sm">Search</button>
    </form>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>User</th><th class="num">Balance</th><th class="num">Orders</th><th class="num">Spent</th><th>Joined</th><th>Status</th><th></th></tr>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= e($u['username']) ?><div class="help"><?= e($u['email']) ?></div></td>
            <td class="num"><?= money($u['balance']) ?></td>
            <td class="num"><?= (int)$u['orders'] ?></td>
            <td class="num"><?= money($u['spent']) ?></td>
            <td><?= e(date('Y-m-d', strtotime($u['created_at']))) ?></td>
            <td><span class="badge badge-<?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm btn-light" href="?<?= e(http_build_query(['view' => $u['id'], 'q' => $search, 'page' => $page])) ?>">Manage</a>
                <form method="post" class="inline" onsubmit="return confirm('Change status?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="action" value="status">
                    <button class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-danger' : 'btn-ok' ?>"><?= $u['status'] === 'active' ? 'Ban' : 'Unban' ?></button></form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pagination($page, $pages, $search !== '' ? ['q' => $search] : []) ?>
</div>
<?php page_footer();

<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

$days = in_array((int)($_GET['days'] ?? 30), [1, 7, 30, 90, 365], true) ? (int)($_GET['days'] ?? 30) : 30;

$totals = row("SELECT COUNT(*) AS orders,
                      COALESCE(SUM(charge - refunded), 0) AS revenue,
                      COALESCE(SUM(CASE WHEN status IN ('canceled') THEN 0 ELSE cost END), 0) AS cost
               FROM orders WHERE created_at >= NOW() - INTERVAL $days DAY");
$byProvider = all("SELECT COALESCE(p.name, '(not placed)') AS provider, COUNT(*) AS orders,
                          SUM(o.status = 'completed') AS completed, SUM(o.status = 'canceled') AS canceled,
                          COALESCE(SUM(o.charge - o.refunded), 0) AS revenue,
                          COALESCE(SUM(CASE WHEN o.status = 'canceled' THEN 0 ELSE o.cost END), 0) AS cost
                   FROM orders o LEFT JOIN providers p ON p.id = o.provider_id
                   WHERE o.created_at >= NOW() - INTERVAL $days DAY GROUP BY p.id, p.name ORDER BY revenue DESC");
$topServices = all("SELECT s.name, COUNT(*) AS orders, COALESCE(SUM(o.charge - o.refunded), 0) AS revenue
                    FROM orders o JOIN services s ON s.id = o.service_id
                    WHERE o.created_at >= NOW() - INTERVAL $days DAY GROUP BY s.id, s.name ORDER BY revenue DESC LIMIT 10");
$pendingPayments = (int)val("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
$openOrders = (int)val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing','in_progress')");
$users = (int)val('SELECT COUNT(*) FROM users');
$userBalances = (float)val('SELECT COALESCE(SUM(balance), 0) FROM users');
$providers = all('SELECT * FROM providers WHERE is_active = 1 ORDER BY name');
$lastSync = val("SELECT MIN(updated_at) FROM orders WHERE status IN ('pending','processing','in_progress')");

page_header('Admin dashboard', 'admin');
?>
<h1>Dashboard</h1>
<?php if ($pendingPayments): ?>
    <div class="alert alert-info"><?= $pendingPayments ?> deposit(s) waiting for review. <a href="<?= e(url('admin/payments.php')) ?>">Review now</a></div>
<?php endif; ?>
<?php if ($openTicketCount = (int)val("SELECT COUNT(*) FROM tickets WHERE status = 'open'")): ?>
    <div class="alert alert-info"><?= $openTicketCount ?> support ticket(s) waiting for a reply. <a href="<?= e(url('admin/tickets.php?status=open')) ?>">Answer now</a></div>
<?php endif; ?>
<?php if (cfg('CRYPTO_ENABLED') && in_array(cfg('CRYPTO_WALLET'), ['', 'your-usdt-wallet-address'], true)): ?>
    <div class="alert alert-error">Your crypto wallet address isn't set. <a href="<?= e(url('admin/settings.php')) ?>">Add it in Settings</a></div>
<?php endif; ?>
<?php foreach ($providers as $p): ?>
    <?php if ($p['api_key'] === ''): ?>
        <div class="alert alert-error">Provider "<?= e($p['name']) ?>" has no API key. <a href="<?= e(url('admin/providers.php?edit=' . (int)$p['id'])) ?>">Add it</a></div>
    <?php endif; ?>
<?php endforeach; ?>

<div class="filters">
    <?php foreach ([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '1 year'] as $d => $label): ?>
        <a class="btn btn-sm <?= $d === $days ? '' : 'btn-light' ?>" href="?days=<?= $d ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>
<div class="grid">
    <div class="stat"><div class="label">Revenue</div><div class="value"><?= money($totals['revenue']) ?></div></div>
    <div class="stat"><div class="label">Provider cost</div><div class="value"><?= money($totals['cost']) ?></div></div>
    <div class="stat"><div class="label">Profit</div><div class="value"><?= money($totals['revenue'] - $totals['cost']) ?></div></div>
    <div class="stat"><div class="label">Orders</div><div class="value"><?= (int)$totals['orders'] ?></div></div>
</div>
<div class="grid">
    <div class="stat"><div class="label">Open orders</div><div class="value"><?= $openOrders ?></div></div>
    <div class="stat"><div class="label">Users</div><div class="value"><?= $users ?></div></div>
    <div class="stat"><div class="label">Customer balances (owed)</div><div class="value"><?= money($userBalances) ?></div></div>
    <?php foreach ($providers as $p): ?>
        <div class="stat"><div class="label"><?= e($p['name']) ?> balance</div>
            <div class="value"><?= $p['balance'] !== null ? e(number_format((float)$p['balance'], 2) . ' ' . $p['currency']) : '-' ?></div>
            <?php if ($p['balance_checked_at']): ?><div class="help">checked <?= e($p['balance_checked_at']) ?></div><?php endif; ?></div>
    <?php endforeach; ?>
</div>
<?php if ($openOrders && $lastSync && strtotime($lastSync) < time() - 1800): ?>
    <div class="alert alert-error">Open orders haven't been checked for over 30 minutes. Is the cron job set up? (see DEPLOY-NAMECHEAP.md)</div>
<?php endif; ?>

<div class="grid-2">
<div class="card">
    <h2>By provider</h2>
    <div class="table-wrap"><table>
        <tr><th>Provider</th><th class="num">Orders</th><th class="num">Done</th><th class="num">Canceled</th><th class="num">Revenue</th><th class="num">Profit</th></tr>
        <?php foreach ($byProvider as $r): ?>
        <tr><td><?= e($r['provider']) ?></td><td class="num"><?= (int)$r['orders'] ?></td><td class="num"><?= (int)$r['completed'] ?></td>
            <td class="num"><?= (int)$r['canceled'] ?></td><td class="num"><?= money($r['revenue']) ?></td><td class="num"><?= money($r['revenue'] - $r['cost']) ?></td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
<div class="card">
    <h2>Top services</h2>
    <div class="table-wrap"><table>
        <tr><th>Service</th><th class="num">Orders</th><th class="num">Revenue</th></tr>
        <?php foreach ($topServices as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int)$r['orders'] ?></td><td class="num"><?= money($r['revenue']) ?></td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
</div>
<?php page_footer();

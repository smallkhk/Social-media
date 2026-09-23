<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

$stats = row("SELECT COUNT(*) AS total,
                     SUM(status IN ('pending','processing','in_progress')) AS active,
                     SUM(status = 'completed') AS completed,
                     COALESCE(SUM(charge - refunded), 0) AS spent
              FROM orders WHERE user_id = ?", [$user['id']]);
$recent = all('SELECT o.*, s.name AS service_name FROM orders o JOIN services s ON s.id = o.service_id
               WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 5', [$user['id']]);

page_header('Dashboard');
?>
<h1>Hi, <?= e($user['username']) ?></h1>
<div class="grid">
    <div class="stat"><div class="label">Balance</div><div class="value"><?= money($user['balance']) ?></div><a href="<?= e(url('wallet.php')) ?>">Add funds &rarr;</a></div>
    <div class="stat"><div class="label">Total spent</div><div class="value"><?= money($stats['spent']) ?></div></div>
    <div class="stat"><div class="label">Active orders</div><div class="value"><?= (int)$stats['active'] ?></div></div>
    <div class="stat"><div class="label">Completed orders</div><div class="value"><?= (int)$stats['completed'] ?></div></div>
</div>

<div class="card">
    <h2>Recent orders</h2>
    <?php if (!$recent): ?>
        <p class="muted">No orders yet. <a href="<?= e(url('new-order.php')) ?>">Place your first order</a>.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Service</th><th>Link</th><th class="num">Qty</th><th class="num">Charge</th><th>Status</th></tr>
        <?php foreach ($recent as $o): ?>
        <tr>
            <td><?= (int)$o['id'] ?></td>
            <td><?= e($o['service_name']) ?></td>
            <td class="break"><?= e($o['link']) ?></td>
            <td class="num"><?= number_format((int)$o['quantity']) ?></td>
            <td class="num"><?= money($o['charge'], 4) ?></td>
            <td><?= status_badge($o['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <p style="margin-top:10px"><a href="<?= e(url('orders.php')) ?>">All orders &rarr;</a></p>
    <?php endif; ?>
</div>
<?php page_footer();

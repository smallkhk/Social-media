<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = request_refill((int)$user['id'], (int)post('order'));
    flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Refill requested for order #' . (int)post('order') . '.' : $result['error']);
    redirect('orders.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'page']))));
}

$statuses = ['pending', 'processing', 'in_progress', 'completed', 'partial', 'canceled'];
$status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';

$where = 'o.user_id = ?';
$params = [$user['id']];
if ($status) {
    $where .= ' AND o.status = ?';
    $params[] = $status;
}

$perPage = 25;
$total = (int)val("SELECT COUNT(*) FROM orders o WHERE $where", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$orders = all("SELECT o.*, s.name AS service_name, s.refill AS service_refill,
                      (SELECT r.status FROM refills r WHERE r.order_id = o.id ORDER BY r.id DESC LIMIT 1) AS refill_status
               FROM orders o JOIN services s ON s.id = o.service_id
               WHERE $where ORDER BY o.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

page_header('Orders');
?>
<h1>Orders</h1>
<div class="card">
    <div class="filters">
        <a class="btn btn-sm <?= $status ? 'btn-light' : '' ?>" href="?">All</a>
        <?php foreach ($statuses as $s): ?>
            <a class="btn btn-sm <?= $status === $s ? '' : 'btn-light' ?>" href="?status=<?= e($s) ?>"><?= e(status_label($s)) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (!$orders): ?>
        <p class="muted">No orders found.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Date</th><th>Service</th><th>Link</th><th class="num">Qty</th><th class="num">Start</th><th class="num">Remains</th><th class="num">Charge</th><th>Status</th></tr>
        <?php foreach ($orders as $o): ?>
        <tr>
            <td><?= (int)$o['id'] ?></td>
            <td><?= e(date('Y-m-d H:i', strtotime($o['created_at']))) ?></td>
            <td><?= e($o['service_name']) ?></td>
            <td class="break"><?= e($o['link']) ?><?= order_extra_html($o) ?></td>
            <td class="num"><?= number_format((int)$o['quantity']) ?><?php if ($o['runs']): ?><div class="help">x <?= (int)$o['runs'] ?> runs, every <?= (int)$o['run_interval'] ?> min</div><?php endif; ?></td>
            <td class="num"><?= $o['start_count'] !== null ? number_format((int)$o['start_count']) : '-' ?></td>
            <td class="num"><?= $o['remains'] !== null ? number_format((int)$o['remains']) : '-' ?></td>
            <td class="num"><?= money($o['charge'], 4) ?>
                <?php if ((float)$o['refunded'] > 0): ?><div class="help">refunded <?= money($o['refunded'], 4) ?></div><?php endif; ?></td>
            <td><?= status_badge($o['status']) ?>
                <?php if ($o['refill_status']): ?><div class="help"><?= e(refill_label($o['refill_status'])) ?></div><?php endif; ?>
                <?php if ($o['service_refill'] && in_array($o['status'], ['completed', 'partial'], true)): ?>
                    <form method="post" style="margin-top:4px"><?= csrf_field() ?><input type="hidden" name="order" value="<?= (int)$o['id'] ?>">
                        <button class="btn btn-sm btn-light">Refill</button></form>
                <?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pagination($page, $pages, $status ? ['status' => $status] : []) ?>
    <?php endif; ?>
</div>
<?php page_footer();

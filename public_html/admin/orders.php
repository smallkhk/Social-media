<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order = row('SELECT * FROM orders WHERE id = ?', [(int)post('id')]);
    $action = post('action');
    if ($order && $action === 'refresh') {
        if (!$order['provider_order_id']) {
            flash('error', 'This order was never sent to a provider.');
        } else {
            $api = SmmProvider::byId((int)$order['provider_id']);
            $info = $api ? $api->status((string)$order['provider_order_id']) : ['error' => 'Provider missing'];
            $result = apply_provider_status($order, $info);
            flash($result === 'error' ? 'error' : 'success', $result === 'error' ? 'Provider error: ' . ($info['error'] ?? '') : "Order #{$order['id']}: " . status_label($result));
        }
    } elseif ($order && $action === 'refund') {
        $ok = refund_order((int)$order['id'], null, 'canceled', 'Canceled and refunded by admin');
        flash($ok ? 'success' : 'error', $ok ? "Order #{$order['id']} refunded." : 'Only open orders can be refunded.');
    } elseif ($order && $action === 'complete') {
        complete_order((int)$order['id']);
        flash('success', "Order #{$order['id']} marked completed.");
    } elseif ($action === 'sync') {
        $stats = sync_orders();
        flash('success', 'Sync done: ' . json_encode($stats));
    }
    redirect('admin/orders.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'q', 'page']))));
}

$statuses = ['pending', 'processing', 'in_progress', 'completed', 'partial', 'canceled'];
$where = ['1=1'];
$params = [];
if (in_array($_GET['status'] ?? '', $statuses, true)) {
    $where[] = 'o.status = ?';
    $params[] = $_GET['status'];
}
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if ($search !== '') {
    $where[] = '(o.id = ? OR o.provider_order_id = ? OR u.username = ? OR o.link LIKE ?)';
    array_push($params, (int)$search, $search, $search, '%' . $search . '%');
}
$whereSql = implode(' AND ', $where);
$perPage = 50;
$total = (int)val("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id WHERE $whereSql", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$orders = all("SELECT o.*, u.username, s.name AS service_name, p.name AS provider_name
               FROM orders o JOIN users u ON u.id = o.user_id JOIN services s ON s.id = o.service_id LEFT JOIN providers p ON p.id = o.provider_id
               WHERE $whereSql ORDER BY o.id DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

$logs = [];
if (isset($_GET['log'])) {
    $logs = all('SELECT l.*, p.name AS provider_name FROM order_logs l LEFT JOIN providers p ON p.id = l.provider_id WHERE l.order_id = ? ORDER BY l.id', [(int)$_GET['log']]);
}

page_header('Orders', 'admin');
?>
<h1>Orders</h1>
<?php if (isset($_GET['log'])): ?>
<div class="card">
    <h2>Log for order #<?= (int)$_GET['log'] ?></h2>
    <div class="table-wrap"><table>
        <tr><th>Time</th><th>Provider</th><th>Action</th><th>Message</th></tr>
        <?php foreach ($logs as $l): ?>
            <tr><td><?= e($l['created_at']) ?></td><td><?= e($l['provider_name']) ?></td><td><?= e($l['action']) ?></td><td class="break"><?= e($l['message']) ?></td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php endif; ?>
<div class="card">
    <form method="get" class="filters">
        <select name="status"><option value="">All statuses</option>
            <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?></select>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Order ID, provider ID, username, link">
        <button class="btn btn-sm">Filter</button>
    </form>
    <form method="post" style="margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="action" value="sync">
        <button class="btn btn-sm btn-light">Check all open orders now</button> <span class="help">The cron job does this every 5 minutes.</span></form>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>User</th><th>Service</th><th>Link</th><th class="num">Qty</th><th class="num">Charge</th><th class="num">Cost</th><th>Provider</th><th>Status</th><th></th></tr>
        <?php foreach ($orders as $o): $open = in_array($o['status'], OPEN_STATUSES, true); ?>
        <tr>
            <td><?= (int)$o['id'] ?><div class="help"><?= e(date('m-d H:i', strtotime($o['created_at']))) ?></div></td>
            <td><?= e($o['username']) ?><?= $o['via_api'] ? ' <span class="badge">API</span>' : '' ?></td>
            <td><?= e($o['service_name']) ?></td>
            <td class="break" style="max-width:220px"><?= e($o['link']) ?></td>
            <td class="num"><?= number_format((int)$o['quantity']) ?><?php if ($o['remains'] !== null && $o['status'] !== 'completed'): ?><div class="help"><?= (int)$o['remains'] ?> left</div><?php endif; ?></td>
            <td class="num"><?= money($o['charge'], 4) ?><?php if ((float)$o['refunded'] > 0): ?><div class="help">-<?= money($o['refunded'], 4) ?></div><?php endif; ?></td>
            <td class="num"><?= money($o['cost'], 4) ?></td>
            <td><?= e($o['provider_name'] ?? '-') ?><div class="help"><?= e($o['provider_order_id'] ?? '') ?></div></td>
            <td><?= status_badge($o['status']) ?><?php if ($o['error']): ?><div class="help"><?= e($o['error']) ?></div><?php endif; ?></td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm btn-light" href="?<?= e(http_build_query(['log' => $o['id']] + array_intersect_key($_GET, array_flip(['status', 'q', 'page'])))) ?>">Log</a>
                <?php if ($open): ?>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="action" value="refresh"><button class="btn btn-sm btn-light">Check</button></form>
                    <form method="post" class="inline" onsubmit="return confirm('Mark as completed?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-sm btn-ok">Done</button></form>
                    <form method="post" class="inline" onsubmit="return confirm('Cancel and refund the full charge? (Cancel it at the provider too.)')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="action" value="refund"><button class="btn btn-sm btn-danger">Refund</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pagination($page, $pages, array_intersect_key($_GET, array_flip(['status', 'q']))) ?>
</div>
<?php page_footer();

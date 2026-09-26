<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    $action = post('action');
    $note = mb_substr(post('note'), 0, 255);

    if ($action === 'approve') {
        $ok = approve_payment($id, round((float)post('amount'), 4), $note !== '' ? $note : 'Approved by admin');
        flash($ok ? 'success' : 'error', $ok ? "Deposit #$id approved and balance credited." : 'Deposit already processed or invalid amount.');
    } elseif ($action === 'recheck') {
        $p = row("SELECT * FROM payments WHERE id = ? AND status = 'pending'", [$id]);
        if ($p && isset(CRYPTO_NETWORKS[$p['method']]) && !is_invoice_placeholder($p['reference'])) {
            q('UPDATE payments SET needs_review = 0, check_count = 0 WHERE id = ?', [$id]);
            $r = verify_crypto_payment(['needs_review' => 0, 'check_count' => 0] + $p);
            flash($r['state'] === 'approved' ? 'success' : 'info', "Deposit #$id: " . $r['message']);
        }
    } elseif ($action === 'reject') {
        $n = q("UPDATE payments SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ? AND status = 'pending'", [$note ?: 'Payment not found', $id])->rowCount();
        flash($n ? 'success' : 'error', $n ? "Deposit #$id rejected." : 'Deposit already processed.');
    }
    redirect('admin/payments.php');
}

$pendingAll = all("SELECT p.*, u.username, u.email FROM payments p JOIN users u ON u.id = p.user_id WHERE p.status = 'pending' ORDER BY p.needs_review DESC, p.id");
// Crypto requests the customer hasn't paid yet are handled automatically; list them separately
$pending = array_filter($pendingAll, fn($p) => !isset(CRYPTO_NETWORKS[$p['method']]) || $p['needs_review'] || !is_invoice_placeholder($p['reference']));
$awaiting = array_filter($pendingAll, fn($p) => isset(CRYPTO_NETWORKS[$p['method']]) && !$p['needs_review'] && is_invoice_placeholder($p['reference']));
$ref = function (array $p): string {
    if (is_invoice_placeholder($p['reference'])) {
        return '<span class="muted">no transaction yet</span>';
    }
    $net = CRYPTO_NETWORKS[$p['method']] ?? null;
    return $net ? '<a href="' . e($net['explorer'] . $p['reference']) . '" target="_blank" rel="noopener">' . e($p['reference']) . '</a>' : e($p['reference']);
};
$history = all("SELECT p.*, u.username FROM payments p JOIN users u ON u.id = p.user_id WHERE p.status <> 'pending' ORDER BY p.id DESC LIMIT 100");

page_header('Payments', 'admin');
?>
<h1>Payments</h1>
<div class="card">
    <h2>Pending (<?= count($pending) ?>)</h2>
    <p class="help" style="margin-bottom:10px">USDT deposits are confirmed automatically; you only need to act on bank transfers and USDT payments flagged <strong>Review</strong>
        (wrong amount or unconfirmed for hours). Check your wallet / bank before approving; you can correct the amount.</p>
    <?php if (!$pending): ?><p class="muted">Nothing to review.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Date</th><th>User</th><th>Method</th><th>Reference</th><th>Amount / note</th><th></th></tr>
        <?php foreach ($pending as $p): ?>
        <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= e($p['created_at']) ?></td>
            <td><?= e($p['username']) ?><div class="help"><?= e($p['email']) ?></div></td>
            <td><?= e(payment_label($p['method'])) ?>
                <?php if ($p['needs_review']): ?><div><span class="badge badge-canceled">Review</span></div>
                <?php elseif (isset(CRYPTO_NETWORKS[$p['method']])): ?><div><span class="badge badge-pending">Checking</span></div><?php endif; ?></td>
            <td class="break" style="max-width:260px"><?= $ref($p) ?><?php if ($p['admin_note']): ?><div class="help"><?= e($p['admin_note']) ?></div><?php endif; ?></td>
            <td colspan="2">
                <form method="post" class="filters" style="margin:0">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="number" name="amount" step="0.01" min="0.01" value="<?= e(number_format((float)$p['amount'], 2, '.', '')) ?>" style="width:110px">
                    <input type="text" name="note" placeholder="Note (optional)" style="width:160px">
                    <button class="btn btn-sm btn-ok" name="action" value="approve">Approve</button>
                    <button class="btn btn-sm btn-danger" name="action" value="reject" onclick="return confirm('Reject this deposit?')">Reject</button>
                    <?php if (isset(CRYPTO_NETWORKS[$p['method']]) && !is_invoice_placeholder($p['reference'])): ?>
                        <button class="btn btn-sm btn-light" name="action" value="recheck">Re-check chain</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
<?php if ($awaiting): ?>
<div class="card">
    <h2>Waiting for customer payment (<?= count($awaiting) ?>)</h2>
    <p class="help" style="margin-bottom:10px">Open USDT requests. They are credited automatically when paid and expire after <?= INVOICE_MINUTES ?> minutes.</p>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Created</th><th>User</th><th>Network</th><th class="num">Exact amount</th></tr>
        <?php foreach ($awaiting as $p): ?>
            <tr><td><?= (int)$p['id'] ?></td><td><?= e($p['created_at']) ?></td><td><?= e($p['username']) ?></td><td><?= e(payment_label($p['method'])) ?></td><td class="num"><?= e(number_format((float)$p['amount'], 2)) ?> USDT</td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php endif; ?>
<div class="card">
    <h2>History</h2>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Date</th><th>User</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th>Status</th></tr>
        <?php foreach ($history as $p): ?>
        <tr>
            <td><?= (int)$p['id'] ?></td><td><?= e($p['created_at']) ?></td><td><?= e($p['username']) ?></td><td><?= e(payment_label($p['method'])) ?></td>
            <td class="break" style="max-width:260px"><?= $ref($p) ?><?php if ($p['admin_note']): ?><div class="help"><?= e($p['admin_note']) ?></div><?php endif; ?></td>
            <td class="num"><?= money($p['amount']) ?></td><td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php page_footer();

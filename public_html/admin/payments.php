<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    $action = post('action');
    $note = mb_substr(post('note'), 0, 255);

    if ($action === 'approve') {
        $amount = round((float)post('amount'), 4);
        $ok = transaction(function () use ($id, $amount, $note) {
            $p = row("SELECT * FROM payments WHERE id = ? AND status = 'pending' FOR UPDATE", [$id]);
            if (!$p || $amount <= 0) {
                return false;
            }
            q("UPDATE payments SET status = 'approved', amount = ?, admin_note = ?, processed_at = NOW() WHERE id = ?", [$amount, $note ?: null, $id]);
            credit_user((int)$p['user_id'], $amount, 'deposit', 'Deposit #' . $id . ' (' . $p['method'] . ')');
            return true;
        });
        flash($ok ? 'success' : 'error', $ok ? "Deposit #$id approved and balance credited." : 'Deposit already processed or invalid amount.');
    } elseif ($action === 'reject') {
        $n = q("UPDATE payments SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ? AND status = 'pending'", [$note ?: 'Payment not found', $id])->rowCount();
        flash($n ? 'success' : 'error', $n ? "Deposit #$id rejected." : 'Deposit already processed.');
    }
    redirect('admin/payments.php');
}

$pending = all("SELECT p.*, u.username, u.email FROM payments p JOIN users u ON u.id = p.user_id WHERE p.status = 'pending' ORDER BY p.id");
$history = all("SELECT p.*, u.username FROM payments p JOIN users u ON u.id = p.user_id WHERE p.status <> 'pending' ORDER BY p.id DESC LIMIT 100");

page_header('Payments', 'admin');
?>
<h1>Payments</h1>
<div class="card">
    <h2>Waiting for review (<?= count($pending) ?>)</h2>
    <p class="help" style="margin-bottom:10px">Check your wallet / bank for the reference before approving. You can correct the amount if the customer sent a different sum.</p>
    <?php if (!$pending): ?><p class="muted">Nothing to review.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Date</th><th>User</th><th>Method</th><th>Reference</th><th>Amount / note</th><th></th></tr>
        <?php foreach ($pending as $p): ?>
        <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= e($p['created_at']) ?></td>
            <td><?= e($p['username']) ?><div class="help"><?= e($p['email']) ?></div></td>
            <td><?= e(ucfirst($p['method'])) ?></td>
            <td class="break" style="max-width:260px"><?= e($p['reference']) ?></td>
            <td colspan="2">
                <form method="post" class="filters" style="margin:0">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="number" name="amount" step="0.01" min="0.01" value="<?= e(number_format((float)$p['amount'], 2, '.', '')) ?>" style="width:110px">
                    <input type="text" name="note" placeholder="Note (optional)" style="width:160px">
                    <button class="btn btn-sm btn-ok" name="action" value="approve">Approve</button>
                    <button class="btn btn-sm btn-danger" name="action" value="reject" onclick="return confirm('Reject this deposit?')">Reject</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
<div class="card">
    <h2>History</h2>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Date</th><th>User</th><th>Method</th><th>Reference</th><th class="num">Amount</th><th>Status</th></tr>
        <?php foreach ($history as $p): ?>
        <tr>
            <td><?= (int)$p['id'] ?></td><td><?= e($p['created_at']) ?></td><td><?= e($p['username']) ?></td><td><?= e(ucfirst($p['method'])) ?></td>
            <td class="break" style="max-width:260px"><?= e($p['reference']) ?><?php if ($p['admin_note']): ?><div class="help"><?= e($p['admin_note']) ?></div><?php endif; ?></td>
            <td class="num"><?= money($p['amount']) ?></td><td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php page_footer();

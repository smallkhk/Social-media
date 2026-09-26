<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

$ticket = isset($_GET['id']) ? row('SELECT t.*, u.username, u.email, u.balance FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?', [(int)$_GET['id']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ticket) {
    $action = post('action');
    if ($action === 'reply') {
        $message = post('message');
        if (mb_strlen($message) < 2) {
            flash('error', 'Write a reply first.');
        } else {
            q('INSERT INTO ticket_messages (ticket_id, from_admin, message) VALUES (?, 1, ?)', [$ticket['id'], mb_substr($message, 0, 5000)]);
            $status = isset($_POST['close']) ? 'closed' : 'answered';
            q('UPDATE tickets SET status = ? WHERE id = ?', [$status, $ticket['id']]);
            send_mail($ticket['email'], "Reply to your ticket #{$ticket['id']}: {$ticket['subject']}",
                "Hi {$ticket['username']},\n\nWe replied to your support ticket:\n\n$message\n\n"
                . 'View or answer it here: ' . url('support.php?id=' . $ticket['id']) . "\n");
            flash('success', 'Reply sent' . ($status === 'closed' ? ' and ticket closed' : '') . '. The customer was emailed.');
        }
    } elseif (in_array($action, ['close', 'reopen'], true)) {
        q('UPDATE tickets SET status = ? WHERE id = ?', [$action === 'close' ? 'closed' : 'open', $ticket['id']]);
    }
    redirect('admin/tickets.php?id=' . $ticket['id']);
}

$filter = in_array($_GET['status'] ?? '', ['open', 'answered', 'closed'], true) ? $_GET['status'] : '';
$tickets = all('SELECT t.*, u.username FROM tickets t JOIN users u ON u.id = t.user_id'
    . ($filter ? ' WHERE t.status = ?' : '') . " ORDER BY FIELD(t.status, 'open', 'answered', 'closed'), t.updated_at DESC LIMIT 200",
    $filter ? [$filter] : []);
$messages = $ticket ? all('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id', [$ticket['id']]) : [];
$labels = ['open' => 'Needs reply', 'answered' => 'Answered', 'closed' => 'Closed'];
$badge = ['open' => 'pending', 'answered' => 'completed', 'closed' => 'canceled'];

page_header('Tickets', 'admin');
?>
<h1>Support tickets</h1>
<?php if ($ticket): ?>
<div class="card">
    <p style="margin-bottom:12px"><a href="<?= e(url('admin/tickets.php')) ?>">&larr; All tickets</a></p>
    <h2>#<?= (int)$ticket['id'] ?> <?= e($ticket['subject']) ?> <span class="badge badge-<?= e($badge[$ticket['status']]) ?>"><?= e($labels[$ticket['status']]) ?></span></h2>
    <p class="help">From <a href="<?= e(url('admin/users.php?view=' . (int)$ticket['user_id'])) ?>"><?= e($ticket['username']) ?></a> (<?= e($ticket['email']) ?>, balance <?= money($ticket['balance']) ?>)
        <?php if ($ticket['order_id']): ?> &middot; <a href="<?= e(url('admin/orders.php?q=' . (int)$ticket['order_id'])) ?>">order #<?= (int)$ticket['order_id'] ?></a><?php endif; ?></p>
    <div class="thread">
        <?php foreach ($messages as $m): ?>
            <div class="msg <?= $m['from_admin'] ? 'msg-user' : 'msg-admin' ?>">
                <div class="msg-head"><?= $m['from_admin'] ? 'You (support)' : e($ticket['username']) ?> &middot; <?= e(date('Y-m-d H:i', strtotime($m['created_at']))) ?></div>
                <?= nl2br(e($m['message'])) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" style="margin-top:14px">
        <?= csrf_field() ?><input type="hidden" name="action" value="reply">
        <div class="form-group"><label>Reply</label><textarea name="message" rows="4" required></textarea></div>
        <button class="btn">Send reply</button>
        <label class="checkbox" style="display:inline-flex;margin-left:12px"><input type="checkbox" name="close"> and close the ticket</label>
    </form>
    <form method="post" style="margin-top:10px"><?= csrf_field() ?>
        <?php if ($ticket['status'] === 'closed'): ?>
            <input type="hidden" name="action" value="reopen"><button class="btn btn-sm btn-light">Reopen</button>
        <?php else: ?>
            <input type="hidden" name="action" value="close"><button class="btn btn-sm btn-light">Close without reply</button>
        <?php endif; ?>
    </form>
</div>
<?php else: ?>
<div class="card">
    <div class="filters">
        <a class="btn btn-sm <?= $filter ? 'btn-light' : '' ?>" href="?">All</a>
        <?php foreach ($labels as $k => $label): ?><a class="btn btn-sm <?= $filter === $k ? '' : 'btn-light' ?>" href="?status=<?= $k ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
    <?php if (!$tickets): ?><p class="muted">No tickets.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>User</th><th>Subject</th><th>Status</th><th>Updated</th></tr>
        <?php foreach ($tickets as $t): ?>
        <tr>
            <td>#<?= (int)$t['id'] ?></td>
            <td><?= e($t['username']) ?></td>
            <td><a href="?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a><?= $t['order_id'] ? '<div class="help">order #' . (int)$t['order_id'] . '</div>' : '' ?></td>
            <td><span class="badge badge-<?= e($badge[$t['status']]) ?>"><?= e($labels[$t['status']]) ?></span></td>
            <td><?= e($t['updated_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php page_footer();

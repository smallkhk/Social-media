<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

$ticket = null;
if (isset($_GET['id'])) {
    $ticket = row('SELECT * FROM tickets WHERE id = ? AND user_id = ?', [(int)$_GET['id'], $user['id']]);
    if (!$ticket) {
        redirect('support.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = post('message');
    $action = post('action');

    if ($action === 'new') {
        $subject = mb_substr(post('subject'), 0, 190);
        $orderId = (int)post('order_id') ?: null;
        if ($orderId && !val('SELECT id FROM orders WHERE id = ? AND user_id = ?', [$orderId, $user['id']])) {
            flash('error', "Order #$orderId is not one of your orders.");
        } elseif ($subject === '' || mb_strlen($message) < 5) {
            flash('error', 'Enter a subject and describe the problem.');
        } elseif ((int)val("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status <> 'closed'", [$user['id']]) >= 10) {
            flash('error', 'You have 10 open tickets. Please wait for replies or close some first.');
        } else {
            $id = transaction(function () use ($user, $subject, $orderId, $message) {
                q('INSERT INTO tickets (user_id, subject, order_id) VALUES (?, ?, ?)', [$user['id'], $subject, $orderId]);
                $id = (int)db()->lastInsertId();
                q('INSERT INTO ticket_messages (ticket_id, message) VALUES (?, ?)', [$id, mb_substr($message, 0, 5000)]);
                return $id;
            });
            flash('success', "Ticket #$id created. We'll reply here as soon as possible.");
            redirect('support.php?id=' . $id);
        }
        redirect('support.php');
    }

    if ($ticket && $action === 'reply') {
        if (mb_strlen($message) < 2) {
            flash('error', 'Write a message first.');
        } else {
            q('INSERT INTO ticket_messages (ticket_id, message) VALUES (?, ?)', [$ticket['id'], mb_substr($message, 0, 5000)]);
            q("UPDATE tickets SET status = 'open' WHERE id = ?", [$ticket['id']]);
            flash('success', 'Reply sent.');
        }
    } elseif ($ticket && $action === 'close') {
        q("UPDATE tickets SET status = 'closed' WHERE id = ?", [$ticket['id']]);
        flash('success', 'Ticket closed.');
    }
    redirect('support.php?id=' . ($ticket['id'] ?? ''));
}

$tickets = all('SELECT t.*, (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS messages
                FROM tickets t WHERE t.user_id = ? ORDER BY t.updated_at DESC LIMIT 50', [$user['id']]);
$messages = $ticket ? all('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id', [$ticket['id']]) : [];
$ticketLabels = ['open' => 'Waiting for reply', 'answered' => 'Answered', 'closed' => 'Closed'];
$ticketBadge = ['open' => 'pending', 'answered' => 'completed', 'closed' => 'canceled'];

$contacts = array_filter([
    'Email' => cfg('SUPPORT_EMAIL') ? ['mailto:' . cfg('SUPPORT_EMAIL'), cfg('SUPPORT_EMAIL')] : null,
    'WhatsApp' => cfg('SUPPORT_WHATSAPP') ? ['https://wa.me/' . preg_replace('/\D/', '', (string)cfg('SUPPORT_WHATSAPP')), cfg('SUPPORT_WHATSAPP')] : null,
    'Telegram' => cfg('SUPPORT_TELEGRAM') ? ['https://t.me/' . ltrim((string)cfg('SUPPORT_TELEGRAM'), '@'), '@' . ltrim((string)cfg('SUPPORT_TELEGRAM'), '@')] : null,
]);

page_header('Support');
?>
<h1>Support</h1>
<?php if ($ticket): ?>
<div class="card">
    <p style="margin-bottom:12px"><a href="<?= e(url('support.php')) ?>">&larr; All tickets</a></p>
    <h2>#<?= (int)$ticket['id'] ?> <?= e($ticket['subject']) ?>
        <span class="badge badge-<?= e($ticketBadge[$ticket['status']]) ?>"><?= e($ticketLabels[$ticket['status']]) ?></span></h2>
    <?php if ($ticket['order_id']): ?><p class="help">About order #<?= (int)$ticket['order_id'] ?></p><?php endif; ?>
    <div class="thread">
        <?php foreach ($messages as $m): ?>
            <div class="msg <?= $m['from_admin'] ? 'msg-admin' : 'msg-user' ?>">
                <div class="msg-head"><?= $m['from_admin'] ? 'Support' : 'You' ?> &middot; <?= e(date('Y-m-d H:i', strtotime($m['created_at']))) ?></div>
                <?= nl2br(e($m['message'])) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <form method="post" style="margin-top:14px">
        <?= csrf_field() ?><input type="hidden" name="action" value="reply">
        <div class="form-group"><label>Your reply</label><textarea name="message" rows="4" required></textarea></div>
        <button class="btn">Send reply</button>
    </form>
    <?php if ($ticket['status'] !== 'closed'): ?>
        <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="close"><button class="btn btn-sm btn-light">Close ticket (problem solved)</button></form>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="grid-2">
    <div class="card">
        <h2>Open a ticket</h2>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="new">
            <div class="form-group"><label>Subject</label>
                <select name="subject" required>
                    <option value="">Choose a topic</option>
                    <?php foreach (['Order problem', 'Refill request', 'Payment / deposit', 'Cancel an order', 'API', 'Other'] as $topic): ?>
                        <option <?= post('subject') === $topic ? 'selected' : '' ?>><?= e($topic) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>Order ID (optional)</label><input type="number" name="order_id" min="1" value="<?= e($_GET['order'] ?? '') ?>"></div>
            <div class="form-group"><label>Message</label><textarea name="message" rows="5" required minlength="5" placeholder="Tell us what happened"></textarea></div>
            <button class="btn btn-block">Submit ticket</button>
        </form>
    </div>
    <div>
        <?php if ($contacts || cfg('SUPPORT_NOTE')): ?>
        <div class="card">
            <h2>Contact us</h2>
            <?php foreach ($contacts as $label => [$href, $text]): ?>
                <p style="margin-bottom:6px"><strong><?= e($label) ?>:</strong> <a href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($text) ?></a></p>
            <?php endforeach; ?>
            <?php if (cfg('SUPPORT_NOTE')): ?><p class="muted" style="margin-top:8px"><?= nl2br(e(cfg('SUPPORT_NOTE'))) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="card">
            <h2>Your tickets</h2>
            <?php if (!$tickets): ?><p class="muted">No tickets yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <tr><th>ID</th><th>Subject</th><th>Status</th><th>Updated</th></tr>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>#<?= (int)$t['id'] ?></td>
                    <td><a href="?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a><?= $t['order_id'] ? '<div class="help">order #' . (int)$t['order_id'] . '</div>' : '' ?></td>
                    <td><span class="badge badge-<?= e($ticketBadge[$t['status']]) ?>"><?= e($ticketLabels[$t['status']]) ?></span></td>
                    <td><?= e(date('Y-m-d H:i', strtotime($t['updated_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
            </table></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php page_footer();

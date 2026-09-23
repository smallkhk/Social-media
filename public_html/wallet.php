<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

$methods = [];
if (CRYPTO_ENABLED) {
    $methods['crypto'] = 'Crypto (' . CRYPTO_NETWORK . ')';
}
if (BANK_ENABLED) {
    $methods['bank'] = 'Bank transfer';
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = post('method');
    $amount = round((float)post('amount'), 2);
    $reference = post('reference');

    if (!isset($methods[$method])) {
        $error = 'Choose a payment method.';
    } elseif ($amount < MIN_DEPOSIT || $amount > 100000) {
        $error = 'Minimum deposit is ' . money(MIN_DEPOSIT) . '.';
    } elseif (strlen($reference) < 4 || strlen($reference) > 255) {
        $error = $method === 'crypto' ? 'Paste the transaction hash (TXID).' : 'Enter your transfer reference.';
    } elseif ((int)val("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'pending'", [$user['id']]) >= 5) {
        $error = 'You already have 5 deposits waiting for review.';
    } elseif (val('SELECT id FROM payments WHERE method = ? AND reference = ?', [$method, $reference])) {
        $error = 'This reference was already submitted.';
    } else {
        q('INSERT INTO payments (user_id, amount, method, reference) VALUES (?, ?, ?, ?)',
            [$user['id'], $amount, $method, $reference]);
        flash('success', 'Deposit submitted. Your balance is updated as soon as the payment is confirmed.');
        redirect('wallet.php');
    }
}

$payments = all('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$user['id']]);
$transactions = all('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30', [$user['id']]);

page_header('Add funds');
?>
<h1>Add funds</h1>
<div class="grid-2">
<div class="card">
    <div class="stat" style="margin-bottom:16px"><div class="label">Current balance</div><div class="value"><?= money($user['balance']) ?></div></div>
    <?php if (!$methods): ?>
        <p class="muted">No payment methods are enabled. Please contact support.</p>
    <?php else: ?>
    <h2>1. Send the payment</h2>
    <?php if (CRYPTO_ENABLED): ?>
        <label>Crypto - <?= e(CRYPTO_NETWORK) ?></label>
        <div class="code" style="margin-bottom:6px"><?= e(CRYPTO_WALLET) ?></div>
        <p class="help" style="margin-bottom:14px">Send only on this network. Payments on the wrong network are lost.</p>
    <?php endif; ?>
    <?php if (BANK_ENABLED): ?>
        <label>Bank transfer</label>
        <div class="code" style="margin-bottom:6px"><?= e(BANK_DETAILS) ?></div>
        <p class="help" style="margin-bottom:14px">Use your username <strong><?= e($user['username']) ?></strong> as the transfer note.<?= BANK_RATE_NOTE ? ' Rate: ' . e(BANK_RATE_NOTE) : '' ?></p>
    <?php endif; ?>

    <h2>2. Tell us about it</h2>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="method">Method</label>
            <select id="method" name="method" required>
                <?php foreach ($methods as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= post('method') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="amount">Amount (USD)</label>
            <input type="number" id="amount" name="amount" step="0.01" min="<?= e(MIN_DEPOSIT) ?>" value="<?= e(post('amount')) ?>" required>
        </div>
        <div class="form-group">
            <label for="reference">Transaction hash / transfer reference</label>
            <input type="text" id="reference" name="reference" value="<?= e(post('reference')) ?>" required maxlength="255">
        </div>
        <button class="btn btn-block">Submit deposit</button>
    </form>
    <?php endif; ?>
</div>
<div>
    <div class="card">
        <h2>Deposits</h2>
        <?php if (!$payments): ?><p class="muted">No deposits yet.</p><?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Date</th><th>Method</th><th class="num">Amount</th><th>Status</th></tr>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= e(date('Y-m-d', strtotime($p['created_at']))) ?></td>
                <td><?= e(ucfirst($p['method'])) ?><div class="help break"><?= e($p['reference']) ?></div>
                    <?php if ($p['admin_note']): ?><div class="help"><?= e($p['admin_note']) ?></div><?php endif; ?></td>
                <td class="num"><?= money($p['amount']) ?></td>
                <td><span class="badge badge-<?= e($p['status']) ?>"><?= e(ucfirst($p['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Balance history</h2>
        <?php if (!$transactions): ?><p class="muted">No activity yet.</p><?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Date</th><th>Description</th><th class="num">Amount</th></tr>
            <?php foreach ($transactions as $t): ?>
            <tr>
                <td><?= e(date('Y-m-d H:i', strtotime($t['created_at']))) ?></td>
                <td><?= e($t['description']) ?></td>
                <td class="num" style="color:<?= $t['amount'] < 0 ? 'var(--bad)' : 'var(--ok)' ?>"><?= ($t['amount'] < 0 ? '-' : '+') . money(abs((float)$t['amount']), 4) ?></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
        <?php endif; ?>
    </div>
</div>
</div>
<?php page_footer();

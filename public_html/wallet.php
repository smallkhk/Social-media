<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

$crypto = crypto_networks();
$bankOn = (bool)cfg('BANK_ENABLED') && (string)cfg('BANK_DETAILS') !== '';
$openCount = fn() => (int)val("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'pending'", [$user['id']]);

// The deposit request being viewed (crypto)
$invoice = null;
if (isset($_GET['invoice'])) {
    $invoice = row("SELECT * FROM payments WHERE id = ? AND user_id = ? AND method IN ('usdt_bsc','usdt_trc')", [(int)$_GET['invoice'], $user['id']]);
    if (!$invoice) {
        redirect('wallet.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $amount = round((float)post('amount'), 2);

    if ($action === 'crypto') {
        $network = post('network');
        if (!isset($crypto[$network])) {
            flash('error', 'Choose a network.');
        } elseif ($amount < cfg('MIN_DEPOSIT') || $amount > 100000) {
            flash('error', 'Minimum deposit is ' . money(cfg('MIN_DEPOSIT')) . '.');
        } elseif ($openCount() >= 5) {
            flash('error', 'You already have 5 open deposits. Finish or cancel one first.');
        } else {
            $r = create_crypto_invoice((int)$user['id'], $network, $amount);
            if ($r['ok']) {
                redirect('wallet.php?invoice=' . $r['id']);
            }
            flash('error', $r['error']);
        }
        redirect('wallet.php');
    }

    if ($action === 'bank' && $bankOn) {
        $reference = post('reference');
        if ($amount < cfg('MIN_DEPOSIT') || $amount > 100000) {
            flash('error', 'Minimum deposit is ' . money(cfg('MIN_DEPOSIT')) . '.');
        } elseif (strlen($reference) < 4 || strlen($reference) > 255) {
            flash('error', 'Enter your transfer reference.');
        } elseif ($openCount() >= 5) {
            flash('error', 'You already have 5 deposits waiting for review.');
        } elseif (val("SELECT id FROM payments WHERE method = 'bank' AND reference = ?", [$reference])) {
            flash('error', 'This reference was already submitted.');
        } else {
            q("INSERT INTO payments (user_id, amount, method, reference) VALUES (?, ?, 'bank', ?)", [$user['id'], $amount, $reference]);
            flash('success', 'Bank transfer submitted. Your balance is updated once we confirm it.');
        }
        redirect('wallet.php');
    }

    if ($invoice && $invoice['status'] === 'pending') {
        if ($action === 'txid' && !$invoice['needs_review']) {
            $txid = normalize_txid($invoice['method'], post('txid'));
            if ($txid === null) {
                flash('error', 'That doesn\'t look like a transaction hash. Copy it from your wallet or the block explorer.');
            } elseif (val('SELECT id FROM payments WHERE method = ? AND reference = ? AND id <> ?', [$invoice['method'], $txid, $invoice['id']])) {
                flash('error', 'This transaction was already used for another deposit.');
            } else {
                q('UPDATE payments SET reference = ?, check_count = 0 WHERE id = ?', [$txid, $invoice['id']]);
                $invoice['reference'] = $txid;
                $invoice['check_count'] = 0;
                $r = verify_crypto_payment($invoice);
                flash(['approved' => 'success', 'invalid' => 'error'][$r['state']] ?? 'info', $r['message']);
            }
        } elseif ($action === 'check' && !$invoice['needs_review']) {
            if ($invoice['checked_at'] && strtotime($invoice['checked_at']) > time() - 15) {
                flash('info', 'Checked a moment ago. Please wait a few seconds.');
            } elseif (is_invoice_placeholder($invoice['reference'])) {
                q('UPDATE payments SET checked_at = NOW() WHERE id = ?', [$invoice['id']]);
                if ($invoice['method'] === 'usdt_trc' && scan_tron_deposits() && val("SELECT status FROM payments WHERE id = ?", [$invoice['id']]) === 'approved') {
                    flash('success', 'Payment received! Your balance has been updated.');
                } else {
                    flash('info', $invoice['method'] === 'usdt_trc'
                        ? 'No confirmed payment yet. TRON confirms in about a minute; you can also paste the transaction hash.'
                        : 'Paste the transaction hash (TXID) of your payment so we can find it.');
                }
            } else {
                $r = verify_crypto_payment($invoice);
                flash(['approved' => 'success', 'invalid' => 'error'][$r['state']] ?? 'info', $r['message']);
            }
        } elseif ($action === 'cancel' && is_invoice_placeholder($invoice['reference'])) {
            q("UPDATE payments SET status = 'rejected', admin_note = 'Canceled by customer', processed_at = NOW() WHERE id = ? AND status = 'pending'", [$invoice['id']]);
            flash('success', 'Deposit request canceled.');
            redirect('wallet.php');
        }
    }
    redirect('wallet.php' . ($invoice ? '?invoice=' . $invoice['id'] : ''));
}

$payments = all('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 20', [$user['id']]);
$transactions = all('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30', [$user['id']]);
$user = row('SELECT * FROM users WHERE id = ?', [$user['id']]);

page_header('Add funds');
?>
<h1>Add funds</h1>
<div class="grid-2">
<div>
<?php if ($invoice): $net = CRYPTO_NETWORKS[$invoice['method']]; $wallet = (string)cfg($invoice['method'] === 'usdt_bsc' ? 'USDT_BSC_WALLET' : 'USDT_TRC_WALLET');
    $expires = strtotime($invoice['created_at']) + INVOICE_MINUTES * 60; $hasTx = !is_invoice_placeholder($invoice['reference']); ?>
    <div class="card">
        <p style="margin-bottom:10px"><a href="<?= e(url('wallet.php')) ?>">&larr; Add funds</a></p>
        <h2>Deposit #<?= (int)$invoice['id'] ?> - <?= e($net['label']) ?></h2>
        <?php if ($invoice['status'] === 'approved'): ?>
            <div class="alert alert-success">Paid. <?= money($invoice['amount']) ?> was added to your balance.</div>
        <?php elseif ($invoice['status'] === 'rejected'): ?>
            <div class="alert alert-error"><?= e($invoice['admin_note'] ?: 'This deposit request is closed.') ?> If you already paid, open a ticket in Support with your transaction hash.</div>
        <?php elseif ($invoice['needs_review']): ?>
            <div class="alert alert-info">Our team is reviewing this payment and will credit it shortly. <?= e($invoice['admin_note']) ?></div>
        <?php else: ?>
            <p>Send <strong>exactly</strong> this amount:</p>
            <div class="pay-amount"><span id="pay-amount"><?= e(number_format((float)$invoice['amount'], 2, '.', '')) ?></span> USDT
                <button type="button" class="btn btn-sm btn-light copy" data-copy="pay-amount">Copy</button></div>
            <p class="help" style="margin-bottom:12px">The cents identify your payment. Sending a different amount delays it for manual review.
                Make sure the amount that <em>arrives</em> is exact (some exchanges subtract a fee).</p>
            <p>To this <strong><?= e($net['short']) ?></strong> address:</p>
            <div class="code" style="margin:6px 0"><span id="pay-wallet"><?= e($wallet) ?></span></div>
            <button type="button" class="btn btn-sm btn-light copy" data-copy="pay-wallet">Copy address</button>
            <div id="qr" style="margin:14px 0"></div>
            <div class="alert alert-error" style="margin-top:6px">Only send USDT on the <strong><?= e($net['label']) ?></strong> network. Other coins or networks are lost.</div>
            <?php if (!$hasTx): ?>
                <p class="help">Pay within <strong id="countdown" data-expires="<?= $expires ?>"></strong>.</p>
            <?php endif; ?>

            <h2 style="margin-top:18px"><?= $hasTx ? 'Checking your payment' : 'After paying' ?></h2>
            <?php if ($hasTx): ?>
                <p>Transaction: <a class="break" href="<?= e($net['explorer'] . $invoice['reference']) ?>" target="_blank" rel="noopener"><?= e($invoice['reference']) ?></a></p>
                <p class="help"><?= e($invoice['admin_note'] ?: 'Waiting for the blockchain.') ?> This page refreshes by itself.</p>
            <?php elseif ($invoice['method'] === 'usdt_trc'): ?>
                <p class="help">TRC-20 payments are detected automatically within a few minutes - you can close this page. Or paste the transaction hash to speed it up:</p>
            <?php else: ?>
                <p class="help">Paste the transaction hash (TXID) from your wallet or BscScan:</p>
            <?php endif; ?>
            <?php if (!$hasTx): ?>
            <form method="post" class="filters" style="margin-top:8px">
                <?= csrf_field() ?><input type="hidden" name="action" value="txid">
                <input type="text" name="txid" placeholder="<?= $invoice['method'] === 'usdt_bsc' ? '0x...' : 'Transaction hash' ?>" style="flex:1;min-width:220px" required>
                <button class="btn btn-sm">Submit</button>
            </form>
            <?php endif; ?>
            <form method="post" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-ok" name="action" value="check">I've paid - check now</button>
                <?php if (!$hasTx): ?><button class="btn btn-sm btn-light" name="action" value="cancel" onclick="return confirm('Cancel this deposit request?')">Cancel</button><?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="stat" style="margin-bottom:16px"><div class="label">Current balance</div><div class="value"><?= money($user['balance']) ?></div></div>
        <?php if (!$crypto && !$bankOn): ?>
            <p class="muted">No payment methods are enabled yet. Please contact support.</p>
        <?php endif; ?>
        <?php if ($crypto): ?>
            <h2>Pay with USDT <span class="badge badge-completed">instant</span></h2>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="crypto">
                <div class="form-group"><label>Network</label>
                    <div class="platforms" style="margin:0">
                        <?php $first = true; foreach ($crypto as $key => $net): ?>
                            <label class="platform"><input type="radio" name="network" value="<?= e($key) ?>" <?= $first ? 'checked' : '' ?>> <?= e($net['label']) ?></label>
                        <?php $first = false; endforeach; ?>
                    </div></div>
                <div class="form-group"><label>Amount (USD)</label><input type="number" name="amount" step="0.01" min="<?= e(cfg('MIN_DEPOSIT')) ?>" required placeholder="Minimum <?= e(cfg('MIN_DEPOSIT')) ?>"></div>
                <button class="btn btn-block">Continue</button>
                <p class="help" style="margin-top:8px">You'll get an exact amount and address. Your balance is credited automatically once the payment is confirmed on the blockchain.</p>
            </form>
        <?php endif; ?>
        <?php if ($bankOn): ?>
            <h2 style="margin-top:22px">Bank transfer</h2>
            <div class="code" style="margin-bottom:6px"><?= e(cfg('BANK_DETAILS')) ?></div>
            <p class="help" style="margin-bottom:10px">Use your username <strong><?= e($user['username']) ?></strong> as the transfer note.<?= cfg('BANK_RATE_NOTE') ? ' Rate: ' . e(cfg('BANK_RATE_NOTE')) : '' ?> Bank transfers are credited after we check them.</p>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="bank">
                <div class="form-row">
                    <div class="form-group"><label>Amount (USD)</label><input type="number" name="amount" step="0.01" min="<?= e(cfg('MIN_DEPOSIT')) ?>" required></div>
                    <div class="form-group"><label>Transfer reference</label><input type="text" name="reference" required maxlength="255"></div>
                </div>
                <button class="btn btn-light">Submit bank transfer</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
<div>
    <div class="card">
        <h2>Deposits</h2>
        <?php if (!$payments): ?><p class="muted">No deposits yet.</p><?php else: ?>
        <div class="table-wrap"><table>
            <tr><th>Date</th><th>Method</th><th class="num">Amount</th><th>Status</th></tr>
            <?php foreach ($payments as $p): $isCrypto = isset(CRYPTO_NETWORKS[$p['method']]); ?>
            <tr>
                <td><?= e(date('Y-m-d H:i', strtotime($p['created_at']))) ?></td>
                <td><?php if ($isCrypto): ?><a href="?invoice=<?= (int)$p['id'] ?>"><?= e(payment_label($p['method'])) ?></a><?php else: ?><?= e(payment_label($p['method'])) ?><?php endif; ?>
                    <?php if (!is_invoice_placeholder($p['reference'])): ?><div class="help break"><?= e($p['reference']) ?></div><?php endif; ?></td>
                <td class="num"><?= money($p['amount']) ?></td>
                <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status'] === 'pending' ? ($p['needs_review'] ? 'In review' : ($isCrypto ? 'Awaiting payment' : 'Pending')) : ucfirst($p['status'])) ?></span></td>
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
<?php if ($invoice && $invoice['status'] === 'pending' && !$invoice['needs_review']): ?>
<script src="<?= e(url('assets/qrcode.min.js')) ?>"></script>
<script>
(function () {
    document.querySelectorAll('.copy').forEach(function (b) {
        b.addEventListener('click', function () {
            var text = document.getElementById(b.dataset.copy).textContent.trim();
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
                var old = b.textContent; b.textContent = 'Copied'; setTimeout(function () { b.textContent = old; }, 1500);
            }, function () { window.prompt('Copy:', text); });
        });
    });
    var qr = document.getElementById('qr');
    if (qr && window.QRCode) new QRCode(qr, { text: document.getElementById('pay-wallet').textContent.trim(), width: 160, height: 160 });
    var cd = document.getElementById('countdown');
    if (cd) {
        var tick = function () {
            var left = Math.max(0, cd.dataset.expires * 1000 - Date.now());
            cd.textContent = left ? Math.floor(left / 60000) + ' min ' + Math.floor(left % 60000 / 1000) + ' s' : 'expired - if you already paid, press "check now"';
        };
        tick(); setInterval(tick, 1000);
    }
    // Refresh while we wait for the blockchain
    setTimeout(function () { location.reload(); }, 45000);
})();
</script>
<?php endif; ?>
<?php page_footer();

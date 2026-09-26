<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

if (cfg('AFFILIATE_PERCENT') <= 0) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moved = transaction(function () use ($user) {
        $u = row('SELECT affiliate_balance FROM users WHERE id = ? FOR UPDATE', [$user['id']]);
        $amount = round((float)$u['affiliate_balance'], 4);
        if ($amount < cfg('AFFILIATE_MIN_TRANSFER')) {
            return 0.0;
        }
        q('UPDATE users SET affiliate_balance = affiliate_balance - ? WHERE id = ?', [$amount, $user['id']]);
        credit_user((int)$user['id'], $amount, 'affiliate', 'Affiliate earnings transfer');
        return $amount;
    });
    if ($moved > 0) {
        flash('success', money($moved, 4) . ' moved to your balance.');
    } else {
        flash('error', 'You need at least ' . money(cfg('AFFILIATE_MIN_TRANSFER')) . ' in affiliate earnings to transfer.');
    }
    redirect('affiliate.php');
}

$referrals = (int)val('SELECT COUNT(*) FROM users WHERE referred_by = ?', [$user['id']]);
$earned = (float)val('SELECT COALESCE(SUM(amount), 0) FROM affiliate_earnings WHERE referrer_id = ?', [$user['id']]);
$recent = all('SELECT a.amount, a.created_at, a.order_id FROM affiliate_earnings a WHERE a.referrer_id = ? ORDER BY a.id DESC LIMIT 20', [$user['id']]);
$link = url('register.php?ref=' . $user['referral_code']);

page_header('Affiliate');
?>
<h1>Affiliate program</h1>
<div class="card">
    <p>Earn <strong><?= e(cfg('AFFILIATE_PERCENT')) ?>%</strong> of every completed order placed by people who sign up with your link.</p>
    <label style="margin-top:12px">Your referral link</label>
    <div class="code"><?= e($link) ?></div>
</div>
<div class="grid">
    <div class="stat"><div class="label">Referrals</div><div class="value"><?= $referrals ?></div></div>
    <div class="stat"><div class="label">Total earned</div><div class="value"><?= money($earned) ?></div></div>
    <div class="stat"><div class="label">Available</div><div class="value"><?= money($user['affiliate_balance'], 4) ?></div>
        <form method="post" style="margin-top:8px"><?= csrf_field() ?><button class="btn btn-sm">Move to balance</button></form></div>
</div>
<div class="card">
    <h2>Recent earnings</h2>
    <?php if (!$recent): ?><p class="muted">Nothing yet. Share your link to start earning.</p><?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>Date</th><th>Order</th><th class="num">Commission</th></tr>
        <?php foreach ($recent as $r): ?>
            <tr><td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td><td>#<?= (int)$r['order_id'] ?></td><td class="num"><?= money($r['amount'], 4) ?></td></tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
<?php page_footer();

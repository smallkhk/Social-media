<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

$services = all('SELECT id, name, category, description, rate, min_quantity, max_quantity
                 FROM services WHERE is_active = 1 ORDER BY category, rate, name');

$selected = (int)($_POST['service'] ?? $_GET['service'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = place_order((int)$user['id'], $selected, post('link'), (int)post('quantity'));
    if ($result['ok']) {
        flash('success', "Order #{$result['order_id']} placed.");
        redirect('orders.php');
    }
    $error = $result['error'];
}

$js = [];
foreach ($services as $s) {
    $js[$s['id']] = ['rate' => (float)$s['rate'], 'min' => (int)$s['min_quantity'], 'max' => (int)$s['max_quantity'], 'desc' => (string)$s['description']];
}

page_header('New order');
?>
<h1>New order</h1>
<div class="grid-2">
<div class="card">
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$services): ?>
        <p class="muted">No services available yet.</p>
    <?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="service">Service</label>
            <select id="service" name="service" required>
                <option value="">Choose a service</option>
                <?php $cat = null; foreach ($services as $s): ?>
                    <?php if ($s['category'] !== $cat): ?>
                        <?= $cat !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($s['category']) ?>">
                    <?php $cat = $s['category']; endif; ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $selected === (int)$s['id'] ? 'selected' : '' ?>>
                        #<?= (int)$s['id'] ?> <?= e($s['name']) ?> - <?= money($s['rate'], 4) ?> / 1000
                    </option>
                <?php endforeach; ?>
                </optgroup>
            </select>
            <div class="help" id="desc"></div>
        </div>
        <div class="form-group">
            <label for="link">Link</label>
            <input type="text" id="link" name="link" value="<?= e(post('link')) ?>" placeholder="https://..." required maxlength="500">
        </div>
        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" value="<?= e(post('quantity')) ?>" required min="1">
            <div class="help" id="limits"></div>
        </div>
        <div class="price-box">Charge: <strong id="charge"><?= e(CURRENCY_SIGN) ?>0.0000</strong></div>
        <p class="help" style="margin-bottom:12px">Your balance: <?= money($user['balance']) ?></p>
        <button class="btn btn-block">Place order</button>
    </form>
    <?php endif; ?>
</div>
<div class="card">
    <h2>Tips</h2>
    <p class="muted">Make sure the account or post is public, and don't place a second order for the same link until the first one finishes.</p>
    <p class="muted" style="margin-top:10px">If an order can't be delivered in full, the undelivered part is refunded to your balance automatically.</p>
</div>
</div>
<script>
(function () {
    var services = <?= json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var sel = document.getElementById('service'), qty = document.getElementById('quantity');
    var symbol = <?= json_encode(CURRENCY_SIGN) ?>;
    if (!sel) return;
    function update() {
        var s = services[sel.value];
        document.getElementById('desc').textContent = s ? s.desc : '';
        document.getElementById('limits').textContent = s ? 'Min ' + s.min + ' - Max ' + s.max : '';
        if (s) { qty.min = s.min; qty.max = s.max; }
        var q = parseInt(qty.value, 10) || 0;
        document.getElementById('charge').textContent = symbol + (s ? (s.rate * q / 1000) : 0).toFixed(4);
    }
    sel.addEventListener('change', update);
    qty.addEventListener('input', update);
    update();
})();
</script>
<?php page_footer();

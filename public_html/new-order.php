<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

$services = all('SELECT id, name, category, type, description, rate, min_quantity, max_quantity, dripfeed
                 FROM services WHERE is_active = 1 ORDER BY category, rate, name');

$selected = (int)($_POST['service'] ?? $_GET['service'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (!isset($_POST['dripfeed'])) {
        unset($input['runs'], $input['interval']);
    }
    $result = place_order((int)$user['id'], $selected, $input);
    if ($result['ok']) {
        flash('success', "Order #{$result['order_id']} placed.");
        redirect('orders.php');
    }
    $error = $result['error'];
}

$js = [];
foreach ($services as $s) {
    $type = order_type($s['type']) ?? ORDER_TYPES['Default'];
    $js[$s['id']] = [
        'rate' => (float)$s['rate'], 'min' => (int)$s['min_quantity'], 'max' => (int)$s['max_quantity'],
        'desc' => (string)$s['description'], 'qty' => $type['qty'], 'fields' => array_keys($type['fields']),
        'drip' => (bool)$s['dripfeed'],
    ];
}
$allFields = [];
foreach (ORDER_TYPES as $t) {
    $allFields += $t['fields'];
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
    <form method="post" id="order-form">
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
                        #<?= (int)$s['id'] ?> <?= e($s['name']) ?> - <?= money($s['rate'], 4) ?><?= (order_type($s['type'])['qty'] ?? '') === 'none' ? '' : ' / 1000' ?>
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

        <?php foreach ($allFields as $field => $kind): [$label, $help] = ORDER_FIELD_LABELS[$field]; ?>
        <div class="form-group type-field" data-field="<?= e($field) ?>" hidden>
            <label for="f-<?= e($field) ?>"><?= e($label) ?></label>
            <?php if ($kind === 'list'): ?>
                <textarea id="f-<?= e($field) ?>" name="<?= e($field) ?>" rows="5"><?= e(post($field)) ?></textarea>
            <?php else: ?>
                <input type="<?= $kind === 'number' ? 'number' : 'text' ?>" id="f-<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e(post($field)) ?>" <?= $kind === 'number' ? 'min="1"' : '' ?>>
            <?php endif; ?>
            <div class="help"><?= e($help) ?></div>
        </div>
        <?php endforeach; ?>

        <div class="form-group" id="qty-group">
            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" value="<?= e(post('quantity')) ?>" min="1">
            <div class="help" id="limits"></div>
        </div>

        <div id="drip-group" hidden>
            <label class="checkbox"><input type="checkbox" id="dripfeed" name="dripfeed" <?= isset($_POST['dripfeed']) ? 'checked' : '' ?>> Drip-feed (deliver in several runs)</label>
            <div class="form-row" id="drip-fields" hidden style="margin-top:10px">
                <div class="form-group"><label for="runs">Runs</label><input type="number" id="runs" name="runs" min="2" max="1000" value="<?= e(post('runs')) ?>"></div>
                <div class="form-group"><label for="interval">Interval (minutes)</label><input type="number" id="interval" name="interval" min="1" max="10080" value="<?= e(post('interval')) ?>"></div>
            </div>
            <div class="help" id="drip-total"></div>
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
    <p class="muted" style="margin-top:10px">Services with a refill guarantee can be refilled from the Orders page if the count drops.</p>
</div>
</div>
<script>
(function () {
    var services = <?= json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var symbol = <?= json_encode(CURRENCY_SIGN) ?>;
    var sel = document.getElementById('service');
    if (!sel) return;
    var qty = document.getElementById('quantity');
    function $(id) { return document.getElementById(id); }
    function lines(field) {
        var el = $('f-' + field);
        return el ? el.value.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; }).length : 0;
    }
    function update() {
        var s = services[sel.value];
        $('desc').textContent = s ? s.desc : '';
        document.querySelectorAll('.type-field').forEach(function (g) {
            var on = !!s && s.fields.indexOf(g.dataset.field) !== -1;
            g.hidden = !on;
            g.querySelector('input,textarea').required = on;
        });
        var mode = s ? s.qty : 'input';
        $('qty-group').hidden = mode !== 'input';
        qty.required = mode === 'input';
        if (s) { qty.min = s.min; qty.max = s.max; }
        $('limits').textContent = s ? 'Min ' + s.min + ' - Max ' + s.max : '';

        var drip = !!s && s.drip && mode === 'input';
        $('drip-group').hidden = !drip;
        var dripOn = drip && $('dripfeed').checked;
        $('drip-fields').hidden = !dripOn;
        $('runs').required = $('interval').required = dripOn;

        var q = mode === 'input' ? (parseInt(qty.value, 10) || 0) : mode === 'none' ? 1 : lines(mode);
        var runs = dripOn ? (parseInt($('runs').value, 10) || 1) : 1;
        $('drip-total').textContent = dripOn ? 'Total: ' + (q * runs) + ' (' + q + ' x ' + runs + ' runs)' : '';
        var charge = !s ? 0 : mode === 'none' ? s.rate : s.rate * q * runs / 1000;
        $('charge').textContent = symbol + charge.toFixed(4) + (s && mode !== 'input' && mode !== 'none' ? ' (' + q + ' ' + mode + ')' : '');
    }
    $('order-form').addEventListener('input', update);
    $('order-form').addEventListener('change', update);
    update();
})();
</script>
<?php page_footer();

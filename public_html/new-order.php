<?php
require __DIR__ . '/app/bootstrap.php';
$user = require_user();

$services = all('SELECT id, name, category, type, description, rate, min_quantity, max_quantity, dripfeed, refill
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

// Platform buttons, detected from the category/service name (first match wins)
$platforms = [
    'instagram' => ['Instagram', '#e1306c', '/instagram|\\big\\b|reels?\\b/i'],
    'facebook' => ['Facebook', '#1877f2', '/facebook|\\bfb\\b/i'],
    'youtube' => ['YouTube', '#ff0000', '/youtube|\\byt\\b/i'],
    'spotify' => ['Spotify', '#1db954', '/spotify/i'],
    'tiktok' => ['TikTok', '#111111', '/tik ?tok/i'],
    'telegram' => ['Telegram', '#229ed9', '/telegram/i'],
    'twitter' => ['X/Twitter', '#000000', '/(?i:twitter|x\\.com|tweet)|\\bX\\b/'],
    'reddit' => ['Reddit', '#ff4500', '/reddit/i'],
];
function service_platform(array $s, array $platforms): string
{
    foreach ([$s['category'], $s['name']] as $text) {
        foreach ($platforms as $key => [, , $pattern]) {
            if (preg_match($pattern, (string)$text)) {
                return $key;
            }
        }
    }
    return 'other';
}

$js = [];
$counts = ['all' => 0];
foreach ($services as $s) {
    $type = order_type($s['type']) ?? ORDER_TYPES['Default'];
    $platform = service_platform($s, $platforms);
    $counts[$platform] = ($counts[$platform] ?? 0) + 1;
    $counts['all']++;
    $js[] = [
        'id' => (int)$s['id'], 'name' => (string)$s['name'], 'cat' => (string)$s['category'], 'platform' => $platform,
        'rate' => (float)$s['rate'], 'min' => (int)$s['min_quantity'], 'max' => (int)$s['max_quantity'],
        'desc' => (string)$s['description'], 'qty' => $type['qty'], 'fields' => array_keys($type['fields']),
        'drip' => (bool)$s['dripfeed'], 'refill' => (bool)$s['refill'], 'type' => $type['name'],
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
        <label>Platform</label>
        <div class="platforms">
            <button type="button" class="platform active" data-platform="all"><span class="dot" style="background:#5b5bd6"></span>Everything</button>
            <?php foreach ($platforms as $key => [$label, $color]): if (empty($counts[$key])) continue; ?>
                <button type="button" class="platform" data-platform="<?= e($key) ?>"><span class="dot" style="background:<?= e($color) ?>"></span><?= e($label) ?></button>
            <?php endforeach; ?>
            <?php if (!empty($counts['other'])): ?>
                <button type="button" class="platform" data-platform="other"><span class="dot" style="background:#9aa0b4"></span>Other</button>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <input type="search" id="search" placeholder="&#128269; Search services, categories or ID" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category"></select>
        </div>
        <div class="form-group">
            <label for="service">Service</label>
            <select id="service" name="service" required></select>
            <div class="help" id="no-results" hidden>No services match. Try another platform or search.</div>
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
<div>
<div class="card" id="info" hidden>
    <h2>Service info</h2>
    <div class="info-grid">
        <div><div class="label">Service ID</div><div id="i-id"></div></div>
        <div><div class="label" id="i-rate-label">Price / 1000</div><div id="i-rate"></div></div>
        <div><div class="label">Min - Max</div><div id="i-limits"></div></div>
        <div><div class="label">Guarantee</div><div id="i-refill"></div></div>
    </div>
    <div class="label" style="margin-top:14px">Description</div>
    <div id="i-desc" class="info-desc"></div>
</div>
<div class="card">
    <h2>Tips</h2>
    <p class="muted">Make sure the account or post is public, and don't place a second order for the same link until the first one finishes.</p>
    <p class="muted" style="margin-top:10px">If an order can't be delivered in full, the undelivered part is refunded to your balance automatically.</p>
    <p class="muted" style="margin-top:10px">Services with a refill guarantee can be refilled from the Orders page if the count drops.</p>
</div>
</div>
</div>
<script>
(function () {
    var services = <?= json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var symbol = <?= json_encode(CURRENCY_SIGN) ?>;
    var selected = <?= (int)$selected ?>;
    var sel = document.getElementById('service');
    if (!sel) return;
    var byId = {};
    services.forEach(function (s) { byId[s.id] = s; });
    var qty = document.getElementById('quantity');
    var catSel = document.getElementById('category');
    var search = document.getElementById('search');
    var platform = 'all';
    function $(id) { return document.getElementById(id); }
    function money(n) { return symbol + n.toFixed(4); }
    function lines(field) {
        var el = $('f-' + field);
        return el ? el.value.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; }).length : 0;
    }
    function matching() {
        var term = search.value.trim().toLowerCase();
        return services.filter(function (s) {
            return (platform === 'all' || s.platform === platform)
                && (!term || (s.id + ' ' + s.name + ' ' + s.cat).toLowerCase().indexOf(term) !== -1);
        });
    }
    function option(value, text) {
        var o = document.createElement('option');
        o.value = value; o.textContent = text;
        return o;
    }
    // Rebuild the category list; keepCat keeps the current category when it is still available
    function fillCategories(keepCat) {
        var list = matching(), cats = [];
        list.forEach(function (s) { if (cats.indexOf(s.cat) === -1) cats.push(s.cat); });
        var current = keepCat || catSel.value;
        catSel.innerHTML = '';
        cats.forEach(function (c) { catSel.appendChild(option(c, c)); });
        if (cats.indexOf(current) !== -1) catSel.value = current;
        $('no-results').hidden = cats.length > 0;
        fillServices();
    }
    function fillServices(keepService) {
        var current = keepService || +sel.value;
        var list = matching().filter(function (s) { return s.cat === catSel.value; });
        sel.innerHTML = '';
        list.forEach(function (s) {
            var per = s.qty === 'none' ? '' : ' per 1000';
            sel.appendChild(option(s.id, s.id + ' - ' + s.name + ' - ' + money(s.rate) + per));
        });
        if (list.some(function (s) { return s.id === current; })) sel.value = current;
        update();
    }
    function update() {
        var s = byId[sel.value];
        $('info').hidden = !s;
        if (s) {
            $('i-id').textContent = s.id;
            $('i-rate-label').textContent = s.qty === 'none' ? 'Price per package' : 'Price / 1000';
            $('i-rate').textContent = money(s.rate);
            $('i-limits').textContent = s.qty === 'none' ? 'Package' : s.min.toLocaleString() + ' - ' + s.max.toLocaleString();
            $('i-refill').textContent = s.refill ? 'Refill available' : 'No refill';
            $('i-desc').textContent = s.desc || 'No description.';
        }
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
        $('charge').textContent = money(charge) + (s && mode !== 'input' && mode !== 'none' ? ' (' + q + ' ' + mode + ')' : '');
    }

    document.querySelectorAll('.platform').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.platform').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            platform = b.dataset.platform;
            fillCategories();
        });
    });
    search.addEventListener('input', function () { fillCategories(); });
    search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    catSel.addEventListener('change', function () { fillServices(); });
    sel.addEventListener('change', update);
    $('order-form').addEventListener('input', function (e) { if (e.target !== search) update(); });
    $('order-form').addEventListener('change', function (e) { if (e.target !== catSel && e.target !== search) update(); });

    // Start on the service from the link / the one just submitted
    fillCategories(byId[selected] ? byId[selected].cat : null);
    if (byId[selected]) fillServices(selected);
})();
</script>
<?php page_footer();

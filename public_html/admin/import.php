<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

$provider = row('SELECT * FROM providers WHERE id = ?', [(int)($_GET['provider'] ?? $_POST['provider'] ?? 0)]);
if (!$provider) {
    redirect('admin/providers.php');
}
$pid = (int)$provider['id'];

/** Provider service list, cached for 10 minutes so paging/filtering doesn't hammer the API. */
function provider_catalog(array $provider, bool $refresh = false): array
{
    $file = sys_get_temp_dir() . '/nora-catalog-' . md5(DB_NAME . $provider['id'] . $provider['api_url']) . '.json';
    if (!$refresh && is_file($file) && filemtime($file) > time() - 600) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }
    $data = SmmProvider::fromRow($provider)->services();
    if (!isset($data['error'])) {
        file_put_contents($file, json_encode($data));
    }
    return $data;
}

$catalog = provider_catalog($provider, isset($_GET['refresh']));
$error = $catalog['error'] ?? null;
$list = $error ? [] : array_values(array_filter($catalog, 'is_array'));

$existing = [];
foreach (all('SELECT id, provider_service_id, rate FROM services WHERE provider_id = ?', [$pid]) as $s) {
    $existing[(string)$s['provider_service_id']] = $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $markup = max(0, (float)post('markup', '50'));
    $selected = array_map('strval', (array)($_POST['ids'] ?? []));
    $byId = [];
    foreach ($list as $s) {
        $byId[(string)$s['service']] = $s;
    }
    $added = $updated = $skipped = 0;
    foreach ($selected as $sid) {
        $s = $byId[$sid] ?? null;
        if (!$s || strcasecmp((string)($s['type'] ?? 'Default'), 'Default') !== 0) {
            $skipped++;
            continue;
        }
        $cost = (float)$s['rate'];
        $rate = round($cost * (1 + $markup / 100), 4);
        $fields = [
            mb_substr((string)$s['name'], 0, 255), mb_substr((string)($s['category'] ?? 'Other'), 0, 190) ?: 'Other',
            $cost, $rate, max(1, (int)$s['min']), max(1, (int)$s['max']), !empty($s['refill']) ? 1 : 0, !empty($s['cancel']) ? 1 : 0,
        ];
        if (isset($existing[$sid])) {
            q('UPDATE services SET name = ?, category = ?, cost = ?, rate = ?, min_quantity = ?, max_quantity = ?, refill = ?, cancel = ? WHERE id = ?',
                array_merge($fields, [$existing[$sid]['id']]));
            $updated++;
        } else {
            q('INSERT INTO services (name, category, cost, rate, min_quantity, max_quantity, refill, cancel, provider_id, provider_service_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($fields, [$pid, $sid]));
            $added++;
        }
    }
    flash('success', "Imported: $added new, $updated updated" . ($skipped ? ", $skipped skipped (only 'Default' type services are supported)" : '') . '.');
    redirect('admin/import.php?provider=' . $pid . '&category=' . urlencode((string)($_GET['category'] ?? '')));
}

$categories = array_values(array_unique(array_map(fn($s) => (string)($s['category'] ?? 'Other'), $list)));
$category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$shown = array_filter($list, fn($s) => ($category === '' || (string)($s['category'] ?? 'Other') === $category)
    && ($search === '' || stripos($s['service'] . ' ' . $s['name'], $search) !== false));
$total = count($shown);
$shown = array_slice($shown, 0, 500);

page_header('Import services', 'admin');
?>
<h1>Import services from <?= e($provider['name']) ?></h1>
<?php if ($error): ?>
    <div class="alert alert-error">Could not load services: <?= e($error) ?>. <a href="<?= e(url('admin/providers.php?edit=' . $pid)) ?>">Check the API key</a></div>
<?php else: ?>
<div class="card">
    <form method="get" class="filters">
        <input type="hidden" name="provider" value="<?= $pid ?>">
        <select name="category">
            <option value="">All categories (<?= count($list) ?> services)</option>
            <?php foreach ($categories as $c): ?><option <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name or ID">
        <button class="btn btn-sm">Filter</button>
        <a class="btn btn-sm btn-light" href="?provider=<?= $pid ?>&refresh=1">Refresh list</a>
    </form>
    <form method="post" action="?provider=<?= $pid ?>&category=<?= e(urlencode($category)) ?>">
        <?= csrf_field() ?>
        <div class="filters">
            <label class="checkbox"><input type="checkbox" id="all"> Select all shown</label>
            <label class="checkbox">Markup % <input type="number" name="markup" value="50" min="0" step="1" style="width:90px"></label>
            <button class="btn btn-sm btn-ok">Import / update selected</button>
        </div>
        <p class="help" style="margin-bottom:10px">Your price = provider rate + markup. Services you already imported are updated with the latest provider rate. Showing <?= count($shown) ?> of <?= $total ?>.</p>
        <div class="table-wrap"><table>
            <tr><th></th><th>ID</th><th>Name</th><th>Category</th><th>Type</th><th class="num">Rate / 1000</th><th class="num">Min</th><th class="num">Max</th><th></th></tr>
            <?php foreach ($shown as $s): $sid = (string)$s['service']; $isDefault = strcasecmp((string)($s['type'] ?? 'Default'), 'Default') === 0; ?>
            <tr>
                <td><?php if ($isDefault): ?><input type="checkbox" name="ids[]" value="<?= e($sid) ?>" class="pick"><?php endif; ?></td>
                <td><?= e($sid) ?></td>
                <td><?= e($s['name']) ?></td>
                <td><?= e($s['category'] ?? '') ?></td>
                <td><?= e($s['type'] ?? 'Default') ?></td>
                <td class="num"><?= e($s['rate']) ?></td>
                <td class="num"><?= e($s['min']) ?></td>
                <td class="num"><?= e($s['max']) ?></td>
                <td><?= isset($existing[$sid]) ? '<span class="badge badge-active">imported</span>' : '' ?></td>
            </tr>
            <?php endforeach; ?>
        </table></div>
    </form>
</div>
<script>
document.getElementById('all').addEventListener('change', function () {
    var on = this.checked;
    document.querySelectorAll('.pick').forEach(function (c) { c.checked = on; });
});
</script>
<?php endif; ?>
<?php page_footer();

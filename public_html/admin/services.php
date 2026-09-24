<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

$providers = all('SELECT id, name FROM providers ORDER BY name');
$providerNames = array_column($providers, 'name', 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'save') {
        $backup = (int)post('backup_provider_id') ?: null;
        $data = [
            post('name'), post('category') ?: 'Other', post('description'), round((float)post('rate'), 4), round((float)post('cost'), 4),
            max(1, (int)post('min_quantity')), max(1, (int)post('max_quantity')), (int)post('provider_id'), post('provider_service_id'),
            $backup, $backup ? post('backup_provider_service_id') : null, isset($_POST['refill']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0,
            order_type(post('type'))['name'] ?? 'Default', isset($_POST['dripfeed']) ? 1 : 0,
        ];
        if ($data[0] === '' || $data[3] <= 0 || !isset($providerNames[$data[7]]) || $data[8] === '' || $data[5] > $data[6]
            || ($backup && (!isset($providerNames[$backup]) || $data[10] === ''))) {
            flash('error', 'Fill in name, price, provider and provider service ID (and min must not exceed max).');
            redirect('admin/services.php?edit=' . ($id ?: 'new'));
        }
        if ($id) {
            q('UPDATE services SET name = ?, category = ?, description = ?, rate = ?, cost = ?, min_quantity = ?, max_quantity = ?, provider_id = ?,
               provider_service_id = ?, backup_provider_id = ?, backup_provider_service_id = ?, refill = ?, is_active = ?, type = ?, dripfeed = ? WHERE id = ?', array_merge($data, [$id]));
        } else {
            q('INSERT INTO services (name, category, description, rate, cost, min_quantity, max_quantity, provider_id, provider_service_id,
               backup_provider_id, backup_provider_service_id, refill, is_active, type, dripfeed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $data);
        }
        flash('success', 'Service saved.');
    } elseif ($action === 'toggle') {
        q('UPDATE services SET is_active = 1 - is_active WHERE id = ?', [$id]);
    } elseif ($action === 'delete') {
        if (val('SELECT COUNT(*) FROM orders WHERE service_id = ?', [$id])) {
            q('UPDATE services SET is_active = 0 WHERE id = ?', [$id]);
            flash('info', 'This service has orders, so it was disabled instead of deleted.');
        } else {
            q('DELETE FROM services WHERE id = ?', [$id]);
            flash('success', 'Service deleted.');
        }
    } elseif ($action === 'markup') {
        $pct = max(0, (float)post('markup'));
        $pp = (int)post('markup_provider');
        $n = q('UPDATE services SET rate = ROUND(cost * ?, 4) WHERE cost > 0' . ($pp ? ' AND provider_id = ?' : ''),
            $pp ? [1 + $pct / 100, $pp] : [1 + $pct / 100])->rowCount();
        flash('success', "Set price = cost + $pct% on $n service(s).");
    }
    redirect('admin/services.php?' . http_build_query(array_intersect_key($_GET, array_flip(['provider', 'category', 'q', 'page']))));
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $_GET['edit'] === 'new'
        ? ['id' => 0, 'name' => '', 'category' => '', 'description' => '', 'rate' => '', 'cost' => '', 'min_quantity' => 10, 'max_quantity' => 10000,
           'provider_id' => 0, 'provider_service_id' => '', 'backup_provider_id' => null, 'backup_provider_service_id' => '', 'refill' => 0, 'is_active' => 1, 'type' => 'Default', 'dripfeed' => 0]
        : row('SELECT * FROM services WHERE id = ?', [(int)$_GET['edit']]);
}

$where = ['1=1'];
$params = [];
if ((int)($_GET['provider'] ?? 0)) {
    $where[] = 'provider_id = ?';
    $params[] = (int)$_GET['provider'];
}
if (is_string($_GET['category'] ?? null) && $_GET['category'] !== '') {
    $where[] = 'category = ?';
    $params[] = $_GET['category'];
}
if (is_string($_GET['q'] ?? null) && trim($_GET['q']) !== '') {
    $where[] = '(name LIKE ? OR id = ? OR provider_service_id = ?)';
    array_push($params, '%' . trim($_GET['q']) . '%', (int)$_GET['q'], trim($_GET['q']));
}
$whereSql = implode(' AND ', $where);
$perPage = 100;
$total = (int)val("SELECT COUNT(*) FROM services WHERE $whereSql", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$services = all("SELECT * FROM services WHERE $whereSql ORDER BY category, rate LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$categories = array_column(all('SELECT DISTINCT category FROM services ORDER BY category'), 'category');

page_header('Services', 'admin');
?>
<h1>Services</h1>
<?php if ($edit): ?>
<div class="card">
    <h2><?= $edit['id'] ? 'Edit service #' . (int)$edit['id'] : 'Add service' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <div class="form-row">
            <div class="form-group"><label>Name</label><input type="text" name="name" value="<?= e($edit['name']) ?>" required></div>
            <div class="form-group"><label>Category</label><input type="text" name="category" value="<?= e($edit['category']) ?>" list="cats"></div>
            <div class="form-group"><label>Type</label><select name="type">
                <?php foreach (array_keys(ORDER_TYPES) as $t): ?><option <?= strcasecmp($t, $edit['type']) === 0 ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
            </select><div class="help">Must match the provider's service type</div></div>
        </div>
        <datalist id="cats"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        <div class="form-group"><label>Description (shown to customers)</label><textarea name="description" rows="2"><?= e($edit['description']) ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label>Your price / 1000 <small>(per package for Package types)</small></label><input type="number" step="0.0001" min="0.0001" name="rate" value="<?= e($edit['rate']) ?>" required></div>
            <div class="form-group"><label>Provider cost / 1000</label><input type="number" step="0.0001" min="0" name="cost" value="<?= e($edit['cost']) ?>"></div>
            <div class="form-group"><label>Min</label><input type="number" name="min_quantity" value="<?= (int)$edit['min_quantity'] ?>" min="1"></div>
            <div class="form-group"><label>Max</label><input type="number" name="max_quantity" value="<?= (int)$edit['max_quantity'] ?>" min="1"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Provider</label><select name="provider_id" required>
                <?php foreach ($providers as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$edit['provider_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label>Provider service ID</label><input type="text" name="provider_service_id" value="<?= e($edit['provider_service_id']) ?>" required></div>
            <div class="form-group"><label>Backup provider (optional)</label><select name="backup_provider_id">
                <option value="">None</option>
                <?php foreach ($providers as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$edit['backup_provider_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label>Backup service ID</label><input type="text" name="backup_provider_service_id" value="<?= e($edit['backup_provider_service_id']) ?>"></div>
        </div>
        <p class="help" style="margin-bottom:10px">If the main provider refuses an order (out of balance, service down), it is sent to the backup provider's equivalent service.</p>
        <label class="checkbox"><input type="checkbox" name="refill" <?= $edit['refill'] ? 'checked' : '' ?>> Refill guarantee (customers get a Refill button)</label>
        <label class="checkbox"><input type="checkbox" name="dripfeed" <?= $edit['dripfeed'] ? 'checked' : '' ?>> Drip-feed allowed (Default type only)</label>
        <label class="checkbox"><input type="checkbox" name="is_active" <?= $edit['is_active'] ? 'checked' : '' ?>> Active</label>
        <p style="margin-top:14px"><button class="btn">Save</button> <a class="btn btn-light" href="<?= e(url('admin/services.php')) ?>">Cancel</a></p>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Bulk pricing</h2>
    <form method="post" class="filters" onsubmit="return confirm('Reprice these services?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="markup">
        <label class="checkbox">Price = provider cost + <input type="number" name="markup" value="50" min="0" step="1" style="width:90px"> %</label>
        <select name="markup_provider"><option value="0">All providers</option>
            <?php foreach ($providers as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm">Apply</button>
    </form>
</div>

<div class="card">
    <form method="get" class="filters">
        <select name="provider"><option value="">All providers</option>
            <?php foreach ($providers as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)($_GET['provider'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
        <select name="category"><option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option <?= ($_GET['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
        <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search">
        <button class="btn btn-sm">Filter</button>
        <a class="btn btn-sm btn-ok" href="?edit=new">+ Add manually</a>
    </form>
    <?php if (!$services): ?>
        <p class="muted">No services. Import them from <a href="<?= e(url('admin/providers.php')) ?>">Providers</a>.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <tr><th>ID</th><th>Name</th><th>Provider</th><th class="num">Price</th><th class="num">Cost</th><th class="num">Margin</th><th class="num">Min/Max</th><th>Status</th><th></th></tr>
        <?php foreach ($services as $s): $margin = $s['rate'] > 0 && $s['cost'] > 0 ? ($s['rate'] - $s['cost']) / $s['rate'] * 100 : null; ?>
        <tr>
            <td><?= (int)$s['id'] ?></td>
            <td><?= e($s['name']) ?><div class="help"><?= e($s['category']) ?> &middot; <?= e($s['type']) ?><?= $s['dripfeed'] ? ' &middot; drip-feed' : '' ?><?= $s['refill'] ? ' &middot; refill' : '' ?></div></td>
            <td><?= e($providerNames[$s['provider_id']] ?? '?') ?> #<?= e($s['provider_service_id']) ?>
                <?php if ($s['backup_provider_id']): ?><div class="help">backup: <?= e($providerNames[$s['backup_provider_id']] ?? '?') ?> #<?= e($s['backup_provider_service_id']) ?></div><?php endif; ?></td>
            <td class="num"><?= money($s['rate'], 4) ?></td>
            <td class="num"><?= money($s['cost'], 4) ?></td>
            <td class="num" style="color:<?= $margin !== null && $margin < 10 ? 'var(--bad)' : 'inherit' ?>"><?= $margin !== null ? number_format($margin, 1) . '%' : '-' ?></td>
            <td class="num"><?= number_format((int)$s['min_quantity']) ?> / <?= number_format((int)$s['max_quantity']) ?></td>
            <td><?= $s['is_active'] ? '<span class="badge badge-active">On</span>' : '<span class="badge">Off</span>' ?></td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm btn-light" href="?edit=<?= (int)$s['id'] ?>">Edit</a>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-sm btn-light"><?= $s['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                <form method="post" class="inline" onsubmit="return confirm('Delete this service?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-sm btn-danger">Delete</button></form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pagination($page, $pages, array_intersect_key($_GET, array_flip(['provider', 'category', 'q']))) ?>
    <?php endif; ?>
</div>
<?php page_footer();

<?php
require __DIR__ . '/app/bootstrap.php';
require_user();

$categories = array_column(all('SELECT DISTINCT category FROM services WHERE is_active = 1 ORDER BY category'), 'category');
$category = is_string($_GET['category'] ?? null) && in_array($_GET['category'], $categories, true) ? $_GET['category'] : '';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';

$where = 'is_active = 1';
$params = [];
if ($category !== '') {
    $where .= ' AND category = ?';
    $params[] = $category;
}
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR category LIKE ? OR id = ?)';
    array_push($params, '%' . $search . '%', '%' . $search . '%', (int)$search);
}
$perPage = 200;
$total = (int)val("SELECT COUNT(*) FROM services WHERE $where", $params);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$services = all("SELECT id, name, category, description, rate, min_quantity, max_quantity, refill
                 FROM services WHERE $where ORDER BY category, rate, name LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);
$query = array_filter(['category' => $category, 'q' => $search], 'strlen');

page_header('Services');
?>
<h1>Services</h1>
<div class="card">
    <form method="get" class="filters">
        <select name="category" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name or ID" style="width:auto;min-width:240px">
        <button class="btn btn-sm">Search</button>
        <?php if ($query): ?><a class="btn btn-sm btn-light" href="?">Clear</a><?php endif; ?>
    </form>
    <p class="help" style="margin-bottom:10px"><?= number_format($total) ?> services<?= $pages > 1 ? " - page $page of $pages" : '' ?></p>
    <?php if (!$services): ?>
        <p class="muted"><?= $query ? 'No services match.' : 'No services available yet.' ?></p>
    <?php else: ?>
    <div class="table-wrap"><table id="services">
        <tr><th>ID</th><th>Service</th><th class="num">Price / 1000</th><th class="num">Min</th><th class="num">Max</th><th></th></tr>
        <?php $cat = null; foreach ($services as $s): ?>
            <?php if ($s['category'] !== $cat): $cat = $s['category']; ?>
                <tr class="category-row"><td colspan="6"><?= e($cat) ?></td></tr>
            <?php endif; ?>
            <tr>
                <td><?= (int)$s['id'] ?></td>
                <td><?= e($s['name']) ?><?= $s['refill'] ? ' <span class="badge badge-completed">Refill</span>' : '' ?>
                    <?php if ($s['description']): ?><div class="help"><?= nl2br(e($s['description'])) ?></div><?php endif; ?></td>
                <td class="num"><?= money($s['rate'], 4) ?></td>
                <td class="num"><?= number_format((int)$s['min_quantity']) ?></td>
                <td class="num"><?= number_format((int)$s['max_quantity']) ?></td>
                <td><a class="btn btn-sm" href="<?= e(url('new-order.php?service=' . (int)$s['id'])) ?>">Order</a></td>
            </tr>
        <?php endforeach; ?>
    </table></div>
    <?= pagination($page, $pages, $query) ?>
    <?php endif; ?>
</div>
<?php page_footer();

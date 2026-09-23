<?php
require __DIR__ . '/app/bootstrap.php';
require_user();

$services = all('SELECT id, name, category, description, rate, min_quantity, max_quantity, refill
                 FROM services WHERE is_active = 1 ORDER BY category, rate, name');

page_header('Services');
?>
<h1>Services</h1>
<div class="card">
    <div class="filters">
        <input type="text" id="search" placeholder="Search services..." style="min-width:260px">
    </div>
    <?php if (!$services): ?>
        <p class="muted">No services available yet.</p>
    <?php else: ?>
    <div class="table-wrap"><table id="services">
        <tr><th>ID</th><th>Service</th><th class="num">Price / 1000</th><th class="num">Min</th><th class="num">Max</th><th></th></tr>
        <?php $cat = null; foreach ($services as $s): ?>
            <?php if ($s['category'] !== $cat): $cat = $s['category']; ?>
                <tr class="category-row"><td colspan="6"><?= e($cat) ?></td></tr>
            <?php endif; ?>
            <tr class="service-row" data-search="<?= e(strtolower($s['id'] . ' ' . $s['name'] . ' ' . $s['category'])) ?>">
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
    <?php endif; ?>
</div>
<script>
document.getElementById('search').addEventListener('input', function () {
    var term = this.value.toLowerCase();
    document.querySelectorAll('.service-row').forEach(function (tr) {
        tr.style.display = tr.dataset.search.indexOf(term) === -1 ? 'none' : '';
    });
});
</script>
<?php page_footer();

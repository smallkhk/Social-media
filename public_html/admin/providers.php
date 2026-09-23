<?php
require __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    $id = (int)post('id');

    if ($action === 'save') {
        $name = post('name');
        $apiUrl = post('api_url');
        $apiKey = post('api_key');
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '' || !filter_var($apiUrl, FILTER_VALIDATE_URL) || !str_starts_with($apiUrl, 'https://')) {
            flash('error', 'Enter a name and an https:// API URL.');
            redirect('admin/providers.php' . ($id ? "?edit=$id" : '?edit=new'));
        }
        if ($id) {
            if ($apiKey === '') {
                q('UPDATE providers SET name = ?, api_url = ?, is_active = ? WHERE id = ?', [$name, $apiUrl, $active, $id]);
            } else {
                q('UPDATE providers SET name = ?, api_url = ?, api_key = ?, is_active = ? WHERE id = ?', [$name, $apiUrl, $apiKey, $active, $id]);
            }
        } else {
            q('INSERT INTO providers (name, api_url, api_key, is_active) VALUES (?, ?, ?, ?)', [$name, $apiUrl, $apiKey, $active]);
            $id = (int)db()->lastInsertId();
        }
        // Test the key straight away
        $p = row('SELECT * FROM providers WHERE id = ?', [$id]);
        $r = SmmProvider::fromRow($p)->balance();
        if (isset($r['balance'])) {
            q('UPDATE providers SET balance = ?, currency = ?, balance_checked_at = NOW() WHERE id = ?', [$r['balance'], $r['currency'] ?? null, $id]);
            flash('success', "Saved. Connection OK - balance {$r['balance']} " . ($r['currency'] ?? ''));
        } else {
            flash('error', 'Saved, but the connection test failed: ' . ($r['error'] ?? 'unknown error'));
        }
    } elseif ($action === 'delete') {
        $used = (int)val('SELECT COUNT(*) FROM services WHERE provider_id = ?', [$id]) + (int)val('SELECT COUNT(*) FROM orders WHERE provider_id = ?', [$id]);
        if ($used) {
            flash('error', 'This provider has services or orders. Deactivate it instead.');
        } else {
            q('DELETE FROM providers WHERE id = ?', [$id]);
            flash('success', 'Provider deleted.');
        }
    }
    redirect('admin/providers.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $_GET['edit'] === 'new' ? ['id' => 0, 'name' => '', 'api_url' => 'https://', 'api_key' => '', 'is_active' => 1]
        : row('SELECT * FROM providers WHERE id = ?', [(int)$_GET['edit']]);
}
$providers = all('SELECT p.*, (SELECT COUNT(*) FROM services s WHERE s.provider_id = p.id) AS services FROM providers p ORDER BY p.name');

page_header('Providers', 'admin');
?>
<h1>Providers</h1>
<?php if ($edit): ?>
<div class="card">
    <h2><?= $edit['id'] ? 'Edit ' . e($edit['name']) : 'Add provider' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <div class="form-row">
            <div class="form-group"><label>Name</label><input type="text" name="name" value="<?= e($edit['name']) ?>" required></div>
            <div class="form-group"><label>API URL</label><input type="url" name="api_url" value="<?= e($edit['api_url']) ?>" required>
                <div class="help">MoreThanPanel: https://morethanpanel.com/api/v2</div></div>
        </div>
        <div class="form-group"><label>API key</label>
            <input type="text" name="api_key" value="" placeholder="<?= $edit['api_key'] !== '' ? 'Saved - leave blank to keep' : 'Paste the API key from the provider account page' ?>" autocomplete="off">
        </div>
        <label class="checkbox"><input type="checkbox" name="is_active" <?= $edit['is_active'] ? 'checked' : '' ?>> Active</label>
        <p style="margin-top:14px"><button class="btn">Save &amp; test connection</button> <a class="btn btn-light" href="<?= e(url('admin/providers.php')) ?>">Cancel</a></p>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <p style="margin-bottom:14px"><a class="btn btn-sm" href="?edit=new">+ Add provider</a>
        <span class="help">Any SMM panel with the standard API v2 works (MoreThanPanel, Crescitaly, and most others).</span></p>
    <div class="table-wrap"><table>
        <tr><th>Name</th><th>API URL</th><th>Key</th><th class="num">Balance</th><th class="num">Services</th><th>Status</th><th></th></tr>
        <?php foreach ($providers as $p): ?>
        <tr>
            <td><strong><?= e($p['name']) ?></strong></td>
            <td class="break"><?= e($p['api_url']) ?></td>
            <td><?= $p['api_key'] !== '' ? 'set' : '<span class="badge badge-canceled">missing</span>' ?></td>
            <td class="num"><?= $p['balance'] !== null ? e(number_format((float)$p['balance'], 2) . ' ' . $p['currency']) : '-' ?></td>
            <td class="num"><?= (int)$p['services'] ?></td>
            <td><?= $p['is_active'] ? '<span class="badge badge-active">Active</span>' : '<span class="badge">Off</span>' ?></td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm btn-light" href="?edit=<?= (int)$p['id'] ?>">Edit</a>
                <a class="btn btn-sm" href="<?= e(url('admin/import.php?provider=' . (int)$p['id'])) ?>">Import services</a>
                <form method="post" class="inline" onsubmit="return confirm('Delete this provider?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php page_footer();

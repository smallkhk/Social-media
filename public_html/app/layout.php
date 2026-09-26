<?php
declare(strict_types=1);

/** @param string $area 'user' | 'admin' | 'auth' */
function page_header(string $title, string $area = 'user'): void
{
    $user = $area === 'user' ? current_user() : null;
    $links = [];
    if ($area === 'user') {
        $links = [
            'dashboard.php' => 'Dashboard',
            'new-order.php' => 'New order',
            'services.php' => 'Services',
            'orders.php' => 'Orders',
            'wallet.php' => 'Add funds',
            'affiliate.php' => 'Affiliate',
            'support.php' => 'Support',
            'account.php' => 'Account',
        ];
        if (cfg('AFFILIATE_PERCENT') <= 0) {
            unset($links['affiliate.php']);
        }
    } elseif ($area === 'admin') {
        $links = [
            'admin/index.php' => 'Dashboard',
            'admin/orders.php' => 'Orders',
            'admin/payments.php' => 'Payments',
            'admin/users.php' => 'Users',
            'admin/services.php' => 'Services',
            'admin/providers.php' => 'Providers',
            'admin/tickets.php' => 'Tickets',
            'admin/settings.php' => 'Settings',
        ];
        $openTickets = (int)val("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
        if ($openTickets) {
            $links['admin/tickets.php'] .= " ($openTickets)";
        }
    }
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> - <?= e(cfg('SITE_NAME')) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>?v=5">
</head>
<body class="area-<?= e($area) ?>">
<?php if ($area !== 'auth'): ?>
<header class="topbar">
    <a class="brand" href="<?= e(url($area === 'admin' ? 'admin/index.php' : 'dashboard.php')) ?>"><?= e(cfg('SITE_NAME')) ?><?= $area === 'admin' ? ' <small>Admin</small>' : '' ?></a>
    <input type="checkbox" id="nav-toggle" hidden>
    <label for="nav-toggle" class="nav-toggle" aria-label="Menu">&#9776;</label>
    <nav>
        <?php foreach ($links as $href => $label): ?>
            <a href="<?= e(url($href)) ?>" class="<?= basename($href) === $current ? 'active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($user): ?>
            <span class="nav-balance"><?= money($user['balance']) ?></span>
        <?php endif; ?>
        <a href="<?= e(url($area === 'admin' ? 'admin/logout.php' : 'logout.php')) ?>" class="nav-logout">Log out</a>
    </nav>
</header>
<?php endif; ?>
<main class="<?= $area === 'auth' ? 'auth-box' : 'container' ?>">
<?php foreach (take_flashes() as [$type, $message]): ?>
    <div class="alert alert-<?= e($type) ?>"><?= e($message) ?></div>
<?php endforeach; ?>
<?php
}

function page_footer(): void
{
    ?>
</main>
</body>
</html>
<?php
}

function pagination(int $page, int $pages, array $query = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="pagination">';
    for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++) {
        $qs = http_build_query(['page' => $i] + $query);
        $html .= $i === $page ? "<span>$i</span>" : '<a href="?' . e($qs) . '">' . $i . '</a>';
    }
    return $html . '</div>';
}

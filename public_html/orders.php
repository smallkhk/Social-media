<?php
require 'config.php';
check_auth();

$user = get_user();
$page = (int)($_GET['page'] ?? 1);
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Get total orders
$total = $conn->query("SELECT COUNT(*) as count FROM orders WHERE user_id = {$user['id']}")->fetch_assoc();
$total_pages = ceil($total['count'] / ITEMS_PER_PAGE);

// Get orders
$orders = $conn->query("
    SELECT o.*, s.service_name, s.platform 
    FROM orders o 
    JOIN services s ON o.service_id = s.id 
    WHERE o.user_id = {$user['id']} 
    ORDER BY o.created_at DESC 
    LIMIT $offset, " . ITEMS_PER_PAGE
);

$order_list = [];
while ($order = $orders->fetch_assoc()) {
    $order_list[] = $order;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
        }
        .navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h2 {
            color: #667eea;
            font-size: 24px;
        }
        .nav-links {
            display: flex;
            gap: 30px;
        }
        .nav-links a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
        }
        .nav-links a:hover {
            color: #667eea;
        }
        .btn-logout {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .header {
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            font-size: 28px;
        }
        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .status-pending_payment {
            background: #e2e3e5;
            color: #383d41;
        }
        .order-id {
            font-weight: 600;
            color: #667eea;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
        }
        .pagination a:hover {
            background: #f8f9fa;
        }
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .empty {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><?php echo SITE_NAME; ?></h2>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="services.php">Buy Services</a>
            <a href="orders.php">My Orders</a>
            <a href="wallet.php">Wallet</a>
            <a href="account.php">Account</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <h1>📋 My Orders</h1>
        </div>

        <div class="section">
            <?php if (count($order_list) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Service</th>
                        <th>Platform</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_list as $order): ?>
                    <tr>
                        <td><span class="order-id">#<?php echo $order['id']; ?></span></td>
                        <td><?php echo htmlspecialchars($order['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['platform']); ?></td>
                        <td><?php echo $order['quantity']; ?></td>
                        <td>$<?php echo number_format($order['total_price'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($order['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="orders.php?page=1">First</a>
                    <a href="orders.php?page=<?php echo $page - 1; ?>">Prev</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="orders.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="orders.php?page=<?php echo $page + 1; ?>">Next</a>
                    <a href="orders.php?page=<?php echo $total_pages; ?>">Last</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty">
                <p>No orders yet</p>
                <a href="services.php">Start ordering →</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php
require 'config.php';
check_auth();

$user = get_user();

// Get user's recent orders
$orders = $conn->query("
    SELECT o.*, s.service_name, s.platform 
    FROM orders o 
    JOIN services s ON o.service_id = s.id 
    WHERE o.user_id = {$user['id']} 
    ORDER BY o.created_at DESC 
    LIMIT 5
");

$recent_orders = [];
if ($orders) {
    while ($row = $orders->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// Get services stats
$stats = $conn->query("
    SELECT COUNT(*) as total_orders, SUM(total_price) as total_spent 
    FROM orders 
    WHERE user_id = {$user['id']}
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
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
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            transition: color 0.3s;
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
        .welcome {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .welcome h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .welcome p {
            color: #666;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 28px;
            color: #667eea;
            font-weight: 700;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            color: #555;
            font-weight: 600;
            border-bottom: 1px solid #ddd;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        .empty-state p {
            margin-bottom: 20px;
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
        <div class="welcome">
            <h1>Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <p>Your account balance: <strong>$<?php echo number_format($user['balance'], 2); ?></strong></p>
            <a href="services.php" class="btn-primary">Buy Services</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Spent</div>
                <div class="stat-value">$<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Account Balance</div>
                <div class="stat-value">$<?php echo number_format($user['balance'], 2); ?></div>
            </div>
        </div>

        <?php if (count($recent_orders) > 0): ?>
        <div class="section">
            <h2>Recent Orders</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Platform</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['service_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['platform']); ?></td>
                        <td><?php echo $order['quantity']; ?></td>
                        <td>$<?php echo number_format($order['total_price'], 2); ?></td>
                        <td>
                            <span class="status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="section">
            <div class="empty-state">
                <p>No orders yet</p>
                <a href="services.php" class="btn-primary">Start Ordering</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

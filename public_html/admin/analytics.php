<?php
require 'config.php';

// Simple admin auth
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    if (($_POST['admin_password'] ?? '') !== 'changeme123') {
        http_response_code(401);
        die('Unauthorized');
    }
    $_SESSION['admin'] = true;
}

// Get date range from URL
$days = (int)($_GET['days'] ?? 30);
$start_date = date('Y-m-d H:i:s', strtotime("-$days days"));

// Overall stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders,
        SUM(total_price) as total_revenue,
        SUM(cost_to_provider) as total_cost,
        SUM(our_profit) as total_profit
    FROM orders
    WHERE created_at >= '$start_date'
")->fetch_assoc();

// Revenue by provider
$revenue_by_provider = $conn->query("
    SELECT 
        provider,
        COUNT(*) as orders,
        SUM(total_price) as revenue,
        SUM(cost_to_provider) as cost,
        SUM(our_profit) as profit,
        ROUND(COUNT(*) / (SELECT COUNT(*) FROM orders WHERE created_at >= '$start_date') * 100, 2) as percentage
    FROM orders
    WHERE created_at >= '$start_date'
    GROUP BY provider
    ORDER BY profit DESC
");

// Top services by revenue
$top_services = $conn->query("
    SELECT 
        s.service_name,
        s.platform,
        COUNT(o.id) as orders,
        SUM(o.total_price) as revenue,
        SUM(o.our_profit) as profit
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.created_at >= '$start_date'
    GROUP BY o.service_id
    ORDER BY revenue DESC
    LIMIT 10
");

// Daily revenue trend
$daily_trend = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as orders,
        SUM(our_profit) as profit,
        SUM(total_price) as revenue
    FROM orders
    WHERE created_at >= '$start_date'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");

// Platform breakdown
$platform_stats = $conn->query("
    SELECT 
        s.platform,
        COUNT(o.id) as orders,
        SUM(o.total_price) as revenue,
        SUM(o.our_profit) as profit
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.created_at >= '$start_date'
    GROUP BY s.platform
    ORDER BY revenue DESC
");

// Collect data for charts
$daily_data = array();
while ($row = $daily_trend->fetch_assoc()) {
    $daily_data[] = $row;
}

$provider_data = array();
while ($row = $revenue_by_provider->fetch_assoc()) {
    $provider_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            color: #333;
        }
        .date-filter {
            display: flex;
            gap: 10px;
        }
        .date-filter a {
            padding: 8px 15px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            cursor: pointer;
        }
        .date-filter a.active {
            background: #764ba2;
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
            font-size: 32px;
            color: #667eea;
            font-weight: 700;
        }
        .stat-secondary {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .chart-container h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .chart-wrapper {
            position: relative;
            height: 400px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .profit-positive {
            color: #28a745;
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 1200px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📊 Analytics Dashboard</h1>
                <p style="color: #999;">Performance metrics for the last <?php echo $days; ?> days</p>
            </div>
            <div class="date-filter">
                <a href="?days=7" class="<?php echo $days == 7 ? 'active' : ''; ?>">7 Days</a>
                <a href="?days=30" class="<?php echo $days == 30 ? 'active' : ''; ?>">30 Days</a>
                <a href="?days=90" class="<?php echo $days == 90 ? 'active' : ''; ?>">90 Days</a>
                <a href="?days=365" class="<?php echo $days == 365 ? 'active' : ''; ?>">1 Year</a>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-secondary"><?php echo $stats['total_orders'] ?? 0; ?> orders</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Cost</div>
                <div class="stat-value">$<?php echo number_format($stats['total_cost'] ?? 0, 2); ?></div>
                <div class="stat-secondary">Provider fees</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Profit</div>
                <div class="stat-value profit-positive">$<?php echo number_format($stats['total_profit'] ?? 0, 2); ?></div>
                <div class="stat-secondary">
                    <?php 
                    $profit_margin = ($stats['total_revenue'] > 0) ? ($stats['total_profit'] / $stats['total_revenue'] * 100) : 0;
                    echo round($profit_margin, 2) . '% margin';
                    ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Success Rate</div>
                <div class="stat-value">
                    <?php 
                    $success_rate = ($stats['total_orders'] > 0) ? ($stats['completed_orders'] / $stats['total_orders'] * 100) : 0;
                    echo round($success_rate, 1) . '%';
                    ?>
                </div>
                <div class="stat-secondary"><?php echo $stats['completed_orders'] ?? 0; ?> completed</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="two-col">
            <div class="chart-container">
                <h2>Revenue Trend</h2>
                <div class="chart-wrapper">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h2>Profit by Provider</h2>
                <div class="chart-wrapper">
                    <canvas id="providerChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Revenue by Provider Table -->
        <div class="chart-container">
            <h2>Revenue by Provider</h2>
            <table>
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                        <th>Cost</th>
                        <th>Profit</th>
                        <th>Margin</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $revenue_by_provider = $conn->query("
                        SELECT 
                            provider,
                            COUNT(*) as orders,
                            SUM(total_price) as revenue,
                            SUM(cost_to_provider) as cost,
                            SUM(our_profit) as profit,
                            ROUND(COUNT(*) / (SELECT COUNT(*) FROM orders WHERE created_at >= '$start_date') * 100, 2) as percentage
                        FROM orders
                        WHERE created_at >= '$start_date'
                        GROUP BY provider
                        ORDER BY profit DESC
                    ");
                    
                    while ($row = $revenue_by_provider->fetch_assoc()): 
                        $margin = ($row['revenue'] > 0) ? ($row['profit'] / $row['revenue'] * 100) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo ucfirst($row['provider']); ?></strong></td>
                        <td><?php echo $row['orders']; ?></td>
                        <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                        <td>$<?php echo number_format($row['cost'], 2); ?></td>
                        <td class="profit-positive">$<?php echo number_format($row['profit'], 2); ?></td>
                        <td><?php echo round($margin, 1); ?>%</td>
                        <td><span class="badge badge-info"><?php echo $row['percentage']; ?>%</span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Top Services -->
        <div class="chart-container">
            <h2>Top 10 Services by Revenue</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Platform</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                        <th>Profit</th>
                        <th>Avg Profit/Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $top_services = $conn->query("
                        SELECT 
                            s.service_name,
                            s.platform,
                            COUNT(o.id) as orders,
                            SUM(o.total_price) as revenue,
                            SUM(o.our_profit) as profit
                        FROM orders o
                        JOIN services s ON o.service_id = s.id
                        WHERE o.created_at >= '$start_date'
                        GROUP BY o.service_id
                        ORDER BY revenue DESC
                        LIMIT 10
                    ");
                    
                    while ($row = $top_services->fetch_assoc()): 
                        $avg_profit = $row['orders'] > 0 ? $row['profit'] / $row['orders'] : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                        <td><span class="badge badge-success"><?php echo ucfirst($row['platform']); ?></span></td>
                        <td><?php echo $row['orders']; ?></td>
                        <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                        <td class="profit-positive">$<?php echo number_format($row['profit'], 2); ?></td>
                        <td>$<?php echo number_format($avg_profit, 4); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Platform Breakdown -->
        <div class="chart-container">
            <h2>Orders by Platform</h2>
            <table>
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                        <th>Profit</th>
                        <th>Profit/Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $platform_stats = $conn->query("
                        SELECT 
                            s.platform,
                            COUNT(o.id) as orders,
                            SUM(o.total_price) as revenue,
                            SUM(o.our_profit) as profit
                        FROM orders o
                        JOIN services s ON o.service_id = s.id
                        WHERE o.created_at >= '$start_date'
                        GROUP BY s.platform
                        ORDER BY revenue DESC
                    ");
                    
                    while ($row = $platform_stats->fetch_assoc()): 
                        $avg = $row['orders'] > 0 ? $row['profit'] / $row['orders'] : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo ucfirst($row['platform']); ?></strong></td>
                        <td><?php echo $row['orders']; ?></td>
                        <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                        <td class="profit-positive">$<?php echo number_format($row['profit'], 2); ?></td>
                        <td>$<?php echo number_format($avg, 4); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Revenue Trend Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = {
            labels: <?php echo json_encode(array_column($daily_data, 'date')); ?>,
            datasets: [{
                label: 'Daily Profit',
                data: <?php echo json_encode(array_column($daily_data, 'profit')); ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        };
        new Chart(revenueCtx, {
            type: 'line',
            data: revenueData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });

        // Provider Profit Chart
        const providerCtx = document.getElementById('providerChart').getContext('2d');
        const providerData = {
            labels: <?php echo json_encode(array_column($provider_data, 'provider')); ?>,
            datasets: [{
                label: 'Profit',
                data: <?php echo json_encode(array_column($provider_data, 'profit')); ?>,
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#28a745',
                    '#dc3545'
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        };
        new Chart(providerCtx, {
            type: 'doughnut',
            data: providerData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>

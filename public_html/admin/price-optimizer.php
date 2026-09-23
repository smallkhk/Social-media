<?php
require '../config.php';

session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    if (($_POST['admin_password'] ?? '') !== 'changeme123') {
        http_response_code(401);
        die('Unauthorized');
    }
    $_SESSION['admin'] = true;
}

$message = '';
$error = '';

// Apply auto-pricing strategy
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'apply_strategy') {
    $strategy = sanitize($_POST['strategy'] ?? '');
    $margin_percent = (float)($_POST['margin_percent'] ?? 25);
    $min_margin = (float)($_POST['min_margin'] ?? 0.0001);
    
    if ($strategy === 'cheapest_provider') {
        // Find cheapest provider per service and use that rate + margin
        $update_count = 0;
        
        $services = $conn->query("SELECT DISTINCT s.id, s.service_name FROM services s");
        while ($service = $services->fetch_assoc()) {
            $service_id = $service['id'];
            
            // Get cheapest rate for this service
            $cheapest = $conn->query("
                SELECT provider, provider_rate 
                FROM services 
                WHERE service_name LIKE '%{$service['service_name']}%'
                AND provider_rate > 0
                ORDER BY provider_rate ASC 
                LIMIT 1
            ")->fetch_assoc();
            
            if ($cheapest) {
                $new_price = $cheapest['provider_rate'] * (1 + ($margin_percent / 100));
                $new_price = max($new_price, $cheapest['provider_rate'] + $min_margin);
                
                $conn->query("
                    UPDATE services 
                    SET price = $new_price,
                        our_margin = " . ($new_price - $cheapest['provider_rate']) . "
                    WHERE id = $service_id
                ");
                $update_count++;
            }
        }
        $message = "Applied cheapest provider strategy to $update_count services!";
    }
    
    elseif ($strategy === 'margin_percentage') {
        // Apply percentage margin to all services
        $update_count = $conn->query("
            UPDATE services 
            SET price = provider_rate * (1 + $margin_percent/100),
                our_margin = (provider_rate * (1 + $margin_percent/100)) - provider_rate
            WHERE provider_rate > 0
        ")->affected_rows;
        
        $message = "Applied $margin_percent% margin to $update_count services!";
    }
    
    elseif ($strategy === 'competitive') {
        // Set price based on market average + margin
        $services = $conn->query("
            SELECT id, service_name, provider_rate 
            FROM services 
            WHERE provider_rate > 0
            GROUP BY service_name
        ");
        
        $update_count = 0;
        while ($service = $services->fetch_assoc()) {
            // Get average rate for this service
            $avg = $conn->query("
                SELECT AVG(provider_rate) as avg_rate 
                FROM services 
                WHERE service_name = '{$service['service_name']}'
            ")->fetch_assoc()['avg_rate'];
            
            $new_price = ($avg + $service['provider_rate']) / 2 * (1 + ($margin_percent / 100));
            
            $conn->query("
                UPDATE services 
                SET price = $new_price,
                    our_margin = $new_price - {$service['provider_rate']}
                WHERE id = {$service['id']}
            ");
            $update_count++;
        }
        $message = "Applied competitive pricing to $update_count services!";
    }
    
    elseif ($strategy === 'platform_based') {
        // Different margins for different platforms
        $margins = array(
            'instagram' => 50,
            'tiktok' => 40,
            'youtube' => 60,
            'twitter' => 35,
            'facebook' => 30,
            'telegram' => 45,
            'spotify' => 70
        );
        
        $update_count = 0;
        foreach ($margins as $platform => $margin) {
            $affected = $conn->query("
                UPDATE services 
                SET price = provider_rate * (1 + $margin/100),
                    our_margin = (provider_rate * (1 + $margin/100)) - provider_rate
                WHERE LOWER(platform) = '$platform' AND provider_rate > 0
            ")->affected_rows;
            $update_count += $affected;
        }
        $message = "Applied platform-based pricing to $update_count services!";
    }
}

// Get current pricing stats
$pricing_stats = $conn->query("
    SELECT 
        COUNT(*) as total_services,
        AVG(our_margin) as avg_margin,
        MIN(price) as min_price,
        MAX(price) as max_price,
        AVG(price) as avg_price,
        (SELECT SUM(our_margin * min_quantity) FROM services) as potential_daily_profit
    FROM services 
    WHERE is_active = 1
")->fetch_assoc();

// Get services with low margins
$low_margin_services = $conn->query("
    SELECT 
        service_name, 
        platform, 
        provider,
        provider_rate, 
        price, 
        our_margin,
        (our_margin / price * 100) as margin_percent
    FROM services 
    WHERE is_active = 1
    AND (our_margin < 0.0001 OR our_margin / price < 0.15)
    ORDER BY margin_percent ASC
    LIMIT 20
");

// Get services with high potential
$high_potential_services = $conn->query("
    SELECT 
        service_name,
        platform,
        COUNT(*) as sales_count,
        SUM(our_profit) as total_profit,
        AVG(our_margin) as avg_margin,
        (AVG(our_margin) / AVG(price) * 100) as margin_percent
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY o.service_id
    HAVING sales_count >= 5
    ORDER BY total_profit DESC
    LIMIT 15
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Optimizer</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
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
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .strategy-box {
            background: #f8f9fa;
            border: 2px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .strategy-box:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        .strategy-box h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .strategy-box p {
            color: #666;
            font-size: 13px;
            line-height: 1.6;
        }
        .strategy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            color: #555;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 13px;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 13px;
        }
        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #764ba2;
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
        .warning {
            color: #dc3545;
            font-weight: 600;
        }
        .success {
            color: #28a745;
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #d1ecf1;
            color: #0c5460;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Pricing Optimizer</h1>
            <p>Automatically optimize prices to maximize profit across all services</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Current Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Active Services</div>
                <div class="stat-value"><?php echo $pricing_stats['total_services']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Margin</div>
                <div class="stat-value">$<?php echo number_format($pricing_stats['avg_margin'], 6); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Price</div>
                <div class="stat-value">$<?php echo number_format($pricing_stats['avg_price'], 6); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Daily Profit Potential</div>
                <div class="stat-value success">$<?php echo number_format($pricing_stats['potential_daily_profit'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- Pricing Strategies -->
        <div class="card">
            <h2>🎯 Auto-Pricing Strategies</h2>
            <p style="color: #666; margin-bottom: 20px;">Choose a strategy to automatically adjust prices across your services</p>

            <div class="strategy-grid">
                <!-- Cheapest Provider Strategy -->
                <form method="POST" onsubmit="return confirm('Apply cheapest provider strategy?')">
                    <input type="hidden" name="action" value="apply_strategy">
                    <input type="hidden" name="strategy" value="cheapest_provider">
                    <div class="strategy-box">
                        <h3>💹 Use Cheapest Provider</h3>
                        <p>Find the cheapest provider for each service and add margin on top. Best for maximum profit.</p>
                        
                        <div class="form-group" style="margin-top: 15px;">
                            <label>Margin %</label>
                            <input type="number" name="margin_percent" value="25" min="5" max="200" step="5">
                        </div>
                        
                        <div class="form-group">
                            <label>Minimum Margin ($)</label>
                            <input type="number" name="min_margin" value="0.0001" min="0.00001" step="0.00001">
                        </div>
                        
                        <button type="submit" class="btn">Apply Strategy</button>
                    </div>
                </form>

                <!-- Percentage Margin Strategy -->
                <form method="POST" onsubmit="return confirm('Apply percentage margin strategy?')">
                    <input type="hidden" name="action" value="apply_strategy">
                    <input type="hidden" name="strategy" value="margin_percentage">
                    <div class="strategy-box">
                        <h3>📊 Percentage Margin</h3>
                        <p>Add a consistent percentage margin to all services based on their cost. Simple and scalable.</p>
                        
                        <div class="form-group" style="margin-top: 15px;">
                            <label>Margin %</label>
                            <input type="number" name="margin_percent" value="35" min="5" max="200" step="5">
                        </div>
                        
                        <button type="submit" class="btn">Apply Strategy</button>
                    </div>
                </form>

                <!-- Competitive Strategy -->
                <form method="POST" onsubmit="return confirm('Apply competitive pricing?')">
                    <input type="hidden" name="action" value="apply_strategy">
                    <input type="hidden" name="strategy" value="competitive">
                    <div class="strategy-box">
                        <h3>⚖️ Competitive Pricing</h3>
                        <p>Price based on average of all providers for same service + margin. Stays competitive.</p>
                        
                        <div class="form-group" style="margin-top: 15px;">
                            <label>Margin %</label>
                            <input type="number" name="margin_percent" value="30" min="5" max="200" step="5">
                        </div>
                        
                        <button type="submit" class="btn">Apply Strategy</button>
                    </div>
                </form>

                <!-- Platform-Based Strategy -->
                <form method="POST" onsubmit="return confirm('Apply platform-based pricing?')">
                    <input type="hidden" name="action" value="apply_strategy">
                    <input type="hidden" name="strategy" value="platform_based">
                    <div class="strategy-box">
                        <h3>🎯 Platform-Based</h3>
                        <p>Different margins for each platform (Instagram 50%, TikTok 40%, etc). Maximizes per-platform profit.</p>
                        
                        <button type="submit" class="btn" style="width: 100%; margin-top: 30px;">Apply Strategy</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Low Margin Services -->
        <div class="card">
            <h2>⚠️ Services with Low Margins (Increase Price!)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Platform</th>
                        <th>Provider</th>
                        <th>Cost</th>
                        <th>Price</th>
                        <th>Margin</th>
                        <th>Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 0;
                    while ($service = $low_margin_services->fetch_assoc()): 
                        $count++;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                        <td><span class="badge"><?php echo ucfirst($service['platform']); ?></span></td>
                        <td><?php echo ucfirst($service['provider']); ?></td>
                        <td>$<?php echo number_format($service['provider_rate'], 6); ?></td>
                        <td>$<?php echo number_format($service['price'], 6); ?></td>
                        <td class="warning">$<?php echo number_format($service['our_margin'], 6); ?></td>
                        <td class="warning"><?php echo number_format($service['margin_percent'], 1); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($count == 0): ?>
                    <tr><td colspan="7" style="text-align: center; color: #999;">✓ All services have healthy margins!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- High Potential Services -->
        <div class="card">
            <h2>🚀 High-Potential Services (Best Sellers)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Platform</th>
                        <th>Sales (30d)</th>
                        <th>Total Profit</th>
                        <th>Avg Margin</th>
                        <th>Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 0;
                    while ($service = $high_potential_services->fetch_assoc()): 
                        $count++;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                        <td><span class="badge"><?php echo ucfirst($service['platform']); ?></span></td>
                        <td><?php echo $service['sales_count']; ?></td>
                        <td class="success">$<?php echo number_format($service['total_profit'], 2); ?></td>
                        <td>$<?php echo number_format($service['avg_margin'], 6); ?></td>
                        <td><?php echo number_format($service['margin_percent'], 1); ?>%</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Recommendations -->
        <div class="card alert alert-info">
            <strong>💡 Recommendations:</strong>
            <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                <li>Start with <strong>"Cheapest Provider"</strong> strategy for maximum profit</li>
                <li>Review low-margin services monthly and increase prices</li>
                <li>Monitor high-potential services - they're your profit drivers</li>
                <li>Use 25-50% margin for most services, 10-20% for high-volume services</li>
                <li>Consider market competition when pricing</li>
            </ul>
        </div>
    </div>
</body>
</html>

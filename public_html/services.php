<?php
require '../config.php';

// Admin authentication (implement proper auth later)
session_start();
if (!isset($_SESSION['admin'])) {
    // Simple password check - implement proper auth
    if ($_POST['admin_password'] ?? '' !== 'changeme123') {
        http_response_code(401);
        die('Unauthorized');
    }
    $_SESSION['admin'] = true;
}

$message = '';
$error = '';

// Add new service
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_service') {
    $platform = sanitize($_POST['platform'] ?? '');
    $service_name = sanitize($_POST['service_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $provider = sanitize($_POST['provider'] ?? '');
    $provider_service_id = sanitize($_POST['provider_service_id'] ?? '');
    $provider_rate = (float)($_POST['provider_rate'] ?? 0);
    $our_price = (float)($_POST['our_price'] ?? 0);
    $our_margin = $our_price - $provider_rate;
    $min_qty = (int)($_POST['min_quantity'] ?? 10);
    $max_qty = (int)($_POST['max_quantity'] ?? 10000);
    $category = sanitize($_POST['category'] ?? '');
    
    if ($platform && $service_name && $provider && $provider_service_id) {
        $sql = "INSERT INTO services 
                (platform, service_name, description, provider, provider_service_id, 
                 provider_rate, price, our_margin, min_quantity, max_quantity, category, is_active)
                VALUES 
                ('$platform', '$service_name', '$description', '$provider', '$provider_service_id',
                 $provider_rate, $our_price, $our_margin, $min_qty, $max_qty, '$category', 1)";
        
        if ($conn->query($sql)) {
            $message = "Service added successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    } else {
        $error = "Please fill all required fields";
    }
}

// Update service
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_service') {
    $service_id = (int)$_POST['service_id'];
    $platform = sanitize($_POST['platform'] ?? '');
    $service_name = sanitize($_POST['service_name'] ?? '');
    $provider_rate = (float)($_POST['provider_rate'] ?? 0);
    $our_price = (float)($_POST['our_price'] ?? 0);
    $our_margin = $our_price - $provider_rate;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $conn->query("
        UPDATE services 
        SET provider_rate = $provider_rate,
            price = $our_price,
            our_margin = $our_margin,
            is_active = $is_active
        WHERE id = $service_id
    ");
    
    $message = "Service updated!";
}

// Delete service
if ($_GET['delete'] ?? false) {
    $service_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM services WHERE id = $service_id");
    $message = "Service deleted!";
}

// Get services
$provider = $_GET['provider'] ?? 'all';
$platform = $_GET['platform'] ?? 'all';

$where = "WHERE 1=1";
if ($provider !== 'all') {
    $provider = sanitize($provider);
    $where .= " AND provider = '$provider'";
}
if ($platform !== 'all') {
    $platform = sanitize($platform);
    $where .= " AND platform = '$platform'";
}

$services = $conn->query("SELECT * FROM services $where ORDER BY platform, service_name");
$service_list = [];
while ($svc = $services->fetch_assoc()) {
    $service_list[] = $svc;
}

// Get stats
$total_services = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'];
$active_services = $conn->query("SELECT COUNT(*) as count FROM services WHERE is_active = 1")->fetch_assoc()['count'];
$total_profit = $conn->query("SELECT SUM(our_margin) as total FROM services")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Services Management</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 28px;
            color: #667eea;
            font-weight: 700;
            margin: 10px 0;
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
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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
        input,
        select,
        textarea {
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
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-danger {
            background: #f44336;
        }
        .btn-danger:hover {
            background: #da190b;
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
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-green {
            background: #d4edda;
            color: #155724;
        }
        .badge-red {
            background: #f8d7da;
            color: #721c24;
        }
        .margin-positive {
            color: #28a745;
            font-weight: 600;
        }
        .margin-negative {
            color: #dc3545;
            font-weight: 600;
        }
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filters select {
            flex: 1;
            min-width: 150px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Admin - Services Management</h1>
            <p>Add, update, and manage SMM services from multiple providers</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-label">Total Services</div>
                <div class="stat-value"><?php echo $total_services; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Active Services</div>
                <div class="stat-value"><?php echo $active_services; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Margin per Unit</div>
                <div class="stat-value">$<?php echo number_format($total_profit ?? 0, 6); ?></div>
            </div>
        </div>

        <!-- Add Service Form -->
        <div class="card">
            <h2>➕ Add New Service</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_service">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Platform *</label>
                        <select name="platform" required>
                            <option value="">Select Platform</option>
                            <option value="Instagram">Instagram</option>
                            <option value="TikTok">TikTok</option>
                            <option value="YouTube">YouTube</option>
                            <option value="Twitter">Twitter</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Telegram">Telegram</option>
                            <option value="Spotify">Spotify</option>
                            <option value="LinkedIn">LinkedIn</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Service Name *</label>
                        <input type="text" name="service_name" placeholder="e.g., Instagram Followers" required>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" placeholder="e.g., followers, likes, views">
                    </div>

                    <div class="form-group">
                        <label>Provider *</label>
                        <select name="provider" required>
                            <option value="">Select Provider</option>
                            <option value="crescitaly">Crescitaly</option>
                            <option value="panelcom">Panel.com</option>
                            <option value="socioboard">Socioboard</option>
                            <option value="smmcom">SMM.com</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Provider Service ID *</label>
                        <input type="text" name="provider_service_id" placeholder="From provider API" required>
                    </div>

                    <div class="form-group">
                        <label>Provider Rate (cost) *</label>
                        <input type="number" step="0.000001" name="provider_rate" placeholder="0.0005" required>
                    </div>

                    <div class="form-group">
                        <label>Your Price *</label>
                        <input type="number" step="0.000001" name="our_price" placeholder="0.001" required>
                    </div>

                    <div class="form-group">
                        <label>Min Quantity</label>
                        <input type="number" name="min_quantity" value="10">
                    </div>

                    <div class="form-group">
                        <label>Max Quantity</label>
                        <input type="number" name="max_quantity" value="10000">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Service description"></textarea>
                </div>

                <button type="submit" class="btn">Add Service</button>
            </form>
        </div>

        <!-- Services List -->
        <div class="card">
            <h2>📋 Services List</h2>

            <div class="filters">
                <select onchange="window.location.href='?provider=' + this.value + '&platform=<?php echo $_GET['platform'] ?? 'all'; ?>'">
                    <option value="all">All Providers</option>
                    <option value="crescitaly">Crescitaly</option>
                    <option value="panelcom">Panel.com</option>
                    <option value="socioboard">Socioboard</option>
                    <option value="smmcom">SMM.com</option>
                </select>

                <select onchange="window.location.href='?provider=<?php echo $_GET['provider'] ?? 'all'; ?>&platform=' + this.value">
                    <option value="all">All Platforms</option>
                    <option value="Instagram">Instagram</option>
                    <option value="TikTok">TikTok</option>
                    <option value="YouTube">YouTube</option>
                    <option value="Twitter">Twitter</option>
                </select>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Service</th>
                        <th>Provider</th>
                        <th>Provider Rate</th>
                        <th>Your Price</th>
                        <th>Margin</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($service_list as $svc): ?>
                    <tr>
                        <td><?php echo ucfirst($svc['platform']); ?></td>
                        <td><?php echo htmlspecialchars($svc['service_name']); ?></td>
                        <td><span class="badge badge-green"><?php echo ucfirst($svc['provider']); ?></span></td>
                        <td>$<?php echo number_format($svc['provider_rate'], 6); ?></td>
                        <td>$<?php echo number_format($svc['price'], 6); ?></td>
                        <td>
                            <span class="<?php echo ($svc['our_margin'] > 0) ? 'margin-positive' : 'margin-negative'; ?>">
                                $<?php echo number_format($svc['our_margin'], 6); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $svc['is_active'] ? 'badge-green' : 'badge-red'; ?>">
                                <?php echo $svc['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-small" onclick="editService(<?php echo $svc['id']; ?>)">Edit</button>
                            <a href="?delete=<?php echo $svc['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Delete?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function editService(id) {
            alert('Edit feature coming soon. ID: ' + id);
            // TODO: Implement inline edit or modal
        }
    </script>
</body>
</html>

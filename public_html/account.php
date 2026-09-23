<?php
require 'config.php';
check_auth();

$user = get_user();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $wallet = sanitize($_POST['wallet_address'] ?? '');
        
        if (!empty($wallet)) {
            $conn->query("UPDATE users SET wallet_address = '$wallet' WHERE id = {$user['id']}");
            $success = 'Profile updated successfully!';
            $user['wallet_address'] = $wallet;
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'All password fields required';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            $hashed = password_hash($new, PASSWORD_BCRYPT);
            $conn->query("UPDATE users SET password = '$hashed' WHERE id = {$user['id']}");
            $success = 'Password changed successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - <?php echo SITE_NAME; ?></title>
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
            max-width: 800px;
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
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
        .info-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .info-item {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        .info-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 16px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #555;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-save:hover {
            transform: translateY(-2px);
        }
        .api-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .api-key {
            font-family: monospace;
            word-break: break-all;
            color: #333;
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
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
            <h1>⚙️ Account Settings</h1>
        </div>

        <!-- Account Info -->
        <div class="card">
            <h2>Account Information</h2>
            
            <div class="info-group">
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value">
                        <span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 3px;">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Member Since</div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Wallet Address -->
        <div class="card">
            <h2>💰 Wallet Address</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>Default Wallet Address (for withdrawals)</label>
                    <input type="text" name="wallet_address" 
                           value="<?php echo htmlspecialchars($user['wallet_address'] ?? ''); ?>"
                           placeholder="0x...">
                </div>
                
                <button type="submit" class="btn-save">Save Wallet Address</button>
            </form>
        </div>

        <!-- API Key -->
        <div class="card">
            <h2>🔑 API Key</h2>
            
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                Use this API key to integrate with our panel programmatically.
            </p>
            
            <div class="api-section">
                <div class="info-label">Your API Key</div>
                <div class="api-key" id="apiKey"><?php echo $user['api_key']; ?></div>
                <button class="btn-save" onclick="copyApiKey()" style="width: 100%;">
                    📋 Copy API Key
                </button>
            </div>
            
            <p style="color: #999; font-size: 12px;">
                <strong>⚠️ Keep this key secure!</strong> Do not share it with anyone.
            </p>
        </div>

        <!-- Change Password -->
        <div class="card">
            <h2>🔐 Change Password</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn-save">Update Password</button>
            </form>
        </div>
    </div>

    <script>
        function copyApiKey() {
            const key = document.getElementById('apiKey').textContent;
            navigator.clipboard.writeText(key).then(() => {
                alert('API Key copied to clipboard!');
            });
        }
    </script>
</body>
</html>

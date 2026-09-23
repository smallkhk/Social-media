<?php
require 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('/login.php');
}

$user = get_user();
$message = '';
$error = '';

// Create reseller account for current user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'become_reseller') {
    $reseller_name = sanitize($_POST['reseller_name'] ?? '');
    $reseller_url = sanitize($_POST['reseller_url'] ?? '');
    $commission = (float)($_POST['commission'] ?? 0);
    
    if (!$reseller_name || !$commission) {
        $error = 'Please fill all fields';
    } elseif ($commission < 5 || $commission > 100) {
        $error = 'Commission must be between 5% and 100%';
    } else {
        // Check if already a reseller
        $check = $conn->query("SELECT id FROM resellers WHERE user_id = {$user['id']}")->fetch_assoc();
        
        if ($check) {
            $error = 'You are already a reseller';
        } else {
            $reseller_key = generate_api_key();
            $sql = "INSERT INTO resellers (user_id, name, website, commission_percent, api_key, is_active)
                    VALUES ({$user['id']}, '$reseller_name', '$reseller_url', $commission, '$reseller_key', 1)";
            
            if ($conn->query($sql)) {
                $message = 'You are now a reseller! Your API key: ' . $reseller_key;
            } else {
                $error = 'Error: ' . $conn->error;
            }
        }
    }
}

// Get reseller info
$reseller = $conn->query("SELECT * FROM resellers WHERE user_id = {$user['id']}")->fetch_assoc();

// Get reseller referrals
if ($reseller) {
    $referrals = $conn->query("
        SELECT COUNT(*) as total_referrals, SUM(o.total_price) as total_sales
        FROM orders o
        WHERE o.referrer_id = {$reseller['id']}
    ")->fetch_assoc();
    
    $referral_earnings = $conn->query("
        SELECT SUM(commission_amount) as total_commission
        FROM referral_commissions
        WHERE reseller_id = {$reseller['id']}
    ")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Program</title>
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
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
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
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 10px 0;
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
        input[type="email"],
        input[type="number"],
        input[type="url"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
            margin: 15px 0;
            font-size: 13px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h2>Nora Reseller Program</h2>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="reseller.php">Reseller</a>
            <a href="account.php">Account</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <h1>💼 Reseller & Affiliate Program</h1>
            <p>Become a reseller and earn commissions on every order your referrals place</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$reseller): ?>
        <!-- Become Reseller Form -->
        <div class="card">
            <h2>🚀 Become a Reseller</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Join our reseller program and earn commissions. You get a unique referral link to share with customers.
                When they buy through your link, you earn a commission on each order.
            </p>

            <div class="info-box">
                <strong>💡 How it works:</strong><br>
                1. Sign up as a reseller (set your commission rate)<br>
                2. Share your unique reseller link<br>
                3. Earn commission on every order from your referrals<br>
                4. Get paid monthly via bank transfer or crypto
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="become_reseller">

                <div class="form-group">
                    <label>Reseller Name/Company *</label>
                    <input type="text" name="reseller_name" placeholder="Your business name" required>
                </div>

                <div class="form-group">
                    <label>Website (optional)</label>
                    <input type="url" name="reseller_url" placeholder="https://your-site.com">
                </div>

                <div class="form-group">
                    <label>Commission Rate (%) *</label>
                    <input type="number" name="commission" min="5" max="100" value="15" step="1" required>
                    <small style="color: #999;">15-30% is typical. This is what customers pay extra when ordering through you.</small>
                </div>

                <button type="submit" class="btn">Become Reseller</button>
            </form>
        </div>

        <?php else: ?>
        <!-- Reseller Dashboard -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Total Referrals</div>
                <div class="stat-value"><?php echo $referrals['total_referrals'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Sales</div>
                <div class="stat-value">$<?php echo number_format($referrals['total_sales'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Earnings</div>
                <div class="stat-value">$<?php echo number_format($referral_earnings['total_commission'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Commission Rate</div>
                <div class="stat-value"><?php echo $reseller['commission_percent']; ?>%</div>
            </div>
        </div>

        <!-- Reseller Info -->
        <div class="card">
            <h2>✓ You are a Reseller!</h2>
            
            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <strong style="color: #155724;">Status: <span class="badge">Active</span></strong>
            </div>

            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Reseller Name</td>
                    <td><?php echo htmlspecialchars($reseller['name']); ?></td>
                </tr>
                <tr>
                    <td>Website</td>
                    <td><?php echo $reseller['website'] ? htmlspecialchars($reseller['website']) : 'Not set'; ?></td>
                </tr>
                <tr>
                    <td>Commission Rate</td>
                    <td><?php echo $reseller['commission_percent']; ?>%</td>
                </tr>
                <tr>
                    <td>API Key</td>
                    <td style="font-family: monospace; word-break: break-all;"><?php echo $reseller['api_key']; ?></td>
                </tr>
                <tr>
                    <td>Created</td>
                    <td><?php echo date('M d, Y', strtotime($reseller['created_at'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- Referral Link -->
        <div class="card">
            <h2>🔗 Your Referral Link</h2>
            <p style="color: #666; margin-bottom: 15px;">Share this link with others to start earning commissions</p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; word-break: break-all; font-family: monospace; color: #333;">
                <?php echo SITE_URL; ?>/signup?ref=<?php echo $reseller['api_key']; ?>
            </div>
            
            <button onclick="copyLink()" class="btn" style="margin-top: 15px;">📋 Copy Link</button>

            <div class="info-box">
                <strong>Share your link on:</strong>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Your website or blog</li>
                    <li>Social media (Facebook, Twitter, Instagram)</li>
                    <li>Email to friends and contacts</li>
                    <li>YouTube description (if you have a channel)</li>
                    <li>Reddit, forums, and communities</li>
                </ul>
            </div>
        </div>

        <!-- Commission Structure -->
        <div class="card">
            <h2>💰 How Commissions Work</h2>
            
            <table>
                <tr>
                    <th>Scenario</th>
                    <th>Customer Pays</th>
                    <th>Nora Gets</th>
                    <th>You Earn</th>
                </tr>
                <tr>
                    <td>$10 service (0% commission)</td>
                    <td>$10</td>
                    <td>$10</td>
                    <td>$0</td>
                </tr>
                <tr>
                    <td>$10 service (15% commission)</td>
                    <td>$11.50</td>
                    <td>$10</td>
                    <td>$1.50</td>
                </tr>
                <tr>
                    <td>$100 order (15% commission)</td>
                    <td>$115</td>
                    <td>$100</td>
                    <td>$15</td>
                </tr>
                <tr>
                    <td>$500 order (15% commission)</td>
                    <td>$575</td>
                    <td>$500</td>
                    <td>$75</td>
                </tr>
            </table>

            <div class="info-box" style="margin-top: 20px;">
                <strong>Payment:</strong> Commissions are paid out monthly via your chosen payment method (Bank Transfer or Crypto)
            </div>
        </div>

        <!-- Promotional Materials -->
        <div class="card">
            <h2>📢 Promotional Materials</h2>
            
            <div style="margin-bottom: 20px;">
                <h3 style="color: #333; margin-bottom: 10px;">Email Template</h3>
                <textarea readonly style="width: 100%; height: 150px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace; font-size: 12px;">
Subject: Best SMM Services - Get Real Followers & Likes

Hi there!

I've been using <?php echo SITE_NAME; ?> for my social media needs and it's amazing! 

Get real followers, likes, views for:
- Instagram
- TikTok
- YouTube
- Twitter & more

Sign up here: <?php echo SITE_URL; ?>/signup?ref=<?php echo $reseller['api_key']; ?>

Trusted by thousands. Check it out!
                </textarea>
                <button onclick="copyText(this)" class="btn" style="margin-top: 10px;">Copy Email</button>
            </div>

            <div>
                <h3 style="color: #333; margin-bottom: 10px;">Twitter/Social Post</h3>
                <textarea readonly style="width: 100%; height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace; font-size: 12px;">
Just discovered <?php echo SITE_NAME; ?> - best SMM panel for real growth! Get Instagram followers, TikTok views, YouTube subscribers and more. Zero bots. Real delivery. Check it out: <?php echo SITE_URL; ?>/signup?ref=<?php echo $reseller['api_key']; ?>
                </textarea>
                <button onclick="copyText(this)" class="btn" style="margin-top: 10px;">Copy Post</button>
            </div>
        </div>

        <?php endif; ?>

        <!-- General Info -->
        <div class="card alert alert-info">
            <strong>📌 Important Information:</strong>
            <ul style="margin-left: 20px; margin-top: 10px; line-height: 1.8;">
                <li><strong>No Hidden Fees:</strong> You only pay for what you order, commission is added to customer price</li>
                <li><strong>No Minimums:</strong> Start earning from your first referral</li>
                <li><strong>Track in Real-Time:</strong> Monitor all referrals and earnings in your dashboard</li>
                <li><strong>Fast Payouts:</strong> Get paid monthly, every month</li>
                <li><strong>Lifetime Commissions:</strong> Earn on every order from your referrals</li>
            </ul>
        </div>
    </div>

    <script>
        function copyLink() {
            const link = document.querySelector('div[style*="font-family: monospace"]').textContent;
            navigator.clipboard.writeText(link).then(() => {
                alert('Link copied!');
            });
        }

        function copyText(btn) {
            const textarea = btn.previousElementSibling;
            navigator.clipboard.writeText(textarea.value).then(() => {
                alert('Copied to clipboard!');
            });
        }
    </script>
</body>
</html>

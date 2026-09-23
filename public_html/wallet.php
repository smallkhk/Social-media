<?php
require 'config.php';
check_auth();

$user = get_user();

// Get payment history
$payments = $conn->query("
    SELECT * FROM payments 
    WHERE user_id = {$user['id']} 
    ORDER BY created_at DESC 
    LIMIT 10
");

$payment_list = [];
while ($payment = $payments->fetch_assoc()) {
    $payment_list[] = $payment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet - <?php echo SITE_NAME; ?></title>
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
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            text-align: center;
        }
        .balance-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .balance-amount {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .balance-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: white;
            color: #667eea;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .payment-method {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .payment-method:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .method-info h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .method-info p {
            color: #999;
            font-size: 13px;
        }
        .btn-topup {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn-topup:hover {
            background: #764ba2;
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
            border-bottom: 2px solid #ddd;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
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
        .empty {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            .balance-actions {
                flex-direction: column;
            }
            .btn-primary {
                width: 100%;
            }
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
        <div class="balance-card">
            <div class="balance-label">💰 Account Balance</div>
            <div class="balance-amount">$<?php echo number_format($user['balance'], 2); ?></div>
            <div class="balance-actions">
                <button class="btn-primary" onclick="document.getElementById('topup-crypto').scrollIntoView({behavior: 'smooth'})">
                    💳 Top Up with Crypto
                </button>
                <button class="btn-primary" onclick="document.getElementById('topup-bank').scrollIntoView({behavior: 'smooth'})">
                    🏦 Top Up via Bank
                </button>
            </div>
        </div>

        <div class="grid-2">
            <div class="card" id="topup-crypto">
                <h2>💰 Top Up with USDT</h2>
                
                <div class="payment-method">
                    <div class="method-info">
                        <h3>Send USDT (BSC)</h3>
                        <p>Binance Smart Chain - USDT</p>
                    </div>
                    <a href="#" onclick="alert('Copy wallet: ' + '<?php echo USDT_WALLET; ?>\n\nSend USDT and funds will be added automatically'); return false;" class="btn-topup">
                        → Send
                    </a>
                </div>

                <div class="payment-method">
                    <div class="method-info">
                        <h3>Send USDT (TRC-20)</h3>
                        <p>Tron Network - USDT</p>
                    </div>
                    <a href="#" onclick="alert('Copy wallet: ' + '<?php echo USDT_WALLET; ?>\n\nSend USDT and funds will be added automatically'); return false;" class="btn-topup">
                        → Send
                    </a>
                </div>

                <div style="background: #f0f7ff; border-left: 4px solid #667eea; padding: 15px; border-radius: 5px; margin-top: 15px; font-size: 13px; color: #333;">
                    <strong>Wallet:</strong> <?php echo USDT_WALLET; ?><br>
                    <strong>Min:</strong> $1 | <strong>Auto-credited in:</strong> 1-5 minutes
                </div>
            </div>

            <div class="card" id="topup-bank">
                <h2>🏦 Top Up via Bank</h2>
                
                <form method="POST" style="display: none;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">Amount (USD)</label>
                        <input type="number" step="0.01" min="1" placeholder="e.g., 50" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <button type="submit" class="btn-topup" style="width: 100%;">
                        Request Bank Details
                    </button>
                </form>

                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 5px; font-size: 13px; color: #856404;">
                    <strong>How it works:</strong>
                    <ol style="margin-left: 20px; margin-top: 10px;">
                        <li>Send NGN to our bank account</li>
                        <li>Include your reference code</li>
                        <li>Funds added within 1-2 hours</li>
                    </ol>
                </div>

                <a href="mailto:admin@nora.com" style="display: block; margin-top: 15px; padding: 10px; background: #667eea; color: white; border-radius: 5px; text-align: center; text-decoration: none; font-weight: 600;">
                    📧 Contact Admin for Bank Details
                </a>
            </div>
        </div>

        <div class="card">
            <h2>💳 Payment History</h2>
            <?php if (count($payment_list) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_list as $payment): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo ucfirst($payment['method']); ?></td>
                        <td>
                            <span class="status-<?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty">
                <p>No payments yet</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

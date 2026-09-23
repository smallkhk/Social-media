<?php
require 'config.php';
check_auth();

$user = get_user();

$service_id = sanitize($_GET['service_id'] ?? '');
$quantity = (int)($_GET['quantity'] ?? 0);
$total = (float)($_GET['total'] ?? 0);
$target = sanitize($_GET['target'] ?? '');

if (!$service_id || !$quantity || !$total) {
    redirect('/services.php');
}

// Convert to NGN (example: $1 = 1200 NGN)
$ngn_amount = $total * 1200;

// Create pending order
$sql = "INSERT INTO orders (user_id, service_id, quantity, target_url, total_price, status) 
        VALUES ({$user['id']}, $service_id, $quantity, '$target', $total, 'pending_payment')";
$conn->query($sql);
$order_id = $conn->insert_id;

// Generate unique bank reference
$bank_ref = 'BK' . strtoupper(substr(md5($order_id . time()), 0, 10)));

// Create pending payment
$sql = "INSERT INTO payments (user_id, amount, method, status, bank_ref, method_details) 
        VALUES ({$user['id']}, $total, 'bank', 'pending', '$bank_ref', 
        JSON_OBJECT('ngn_amount', $ngn_amount, 'ref', '$bank_ref'))";
$conn->query($sql);
$payment_id = $conn->insert_id;

// Get bank accounts from database
$banks = $conn->query("SELECT * FROM bank_accounts WHERE is_active = 1");
$bank_list = [];
if ($banks) {
    while ($bank = $banks->fetch_assoc()) {
        $bank_list[] = $bank;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Transfer - <?php echo SITE_NAME; ?></title>
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
        .nav-links a {
            text-decoration: none;
            color: #555;
            margin: 0 20px;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .amount-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .amount-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .amount-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .amount-ngn {
            font-size: 16px;
            opacity: 0.9;
        }
        .reference-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .reference-label {
            color: #856404;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .reference-code {
            background: white;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 18px;
            font-weight: 700;
            color: #ffc107;
            word-break: break-all;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .copy-ref-btn {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        .copy-ref-btn:hover {
            background: #e0a800;
        }
        .instructions {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .instructions h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .instructions ol {
            margin-left: 20px;
            line-height: 2;
            color: #555;
        }
        .bank-details {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .bank-details h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
        }
        .bank-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .bank-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .bank-item:last-child {
            border-bottom: none;
        }
        .bank-label {
            color: #999;
            font-weight: 500;
        }
        .bank-value {
            color: #333;
            font-weight: 700;
            word-break: break-all;
            text-align: right;
            flex: 1;
            margin-left: 20px;
        }
        .note {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }
        .timer {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #856404;
            text-align: center;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><?php echo SITE_NAME; ?></h2>
    </div>

    <div class="container">
        <div class="header">
            <h1>🏦 Bank Transfer Payment</h1>
            <p>Order #<?php echo $order_id; ?></p>
        </div>

        <div class="amount-box">
            <div class="amount-label">Amount Required</div>
            <div class="amount-value">₦<?php echo number_format($ngn_amount, 2); ?></div>
            <div class="amount-ngn">USD $<?php echo $total; ?></div>
        </div>

        <div class="reference-box">
            <div class="reference-label">Payment Reference (Use this in description)</div>
            <div class="reference-code">
                <span id="refCode"><?php echo $bank_ref; ?></span>
                <button class="copy-ref-btn" onclick="copyRef()">📋 Copy</button>
            </div>
        </div>

        <div class="instructions">
            <h3>📋 Instructions:</h3>
            <ol>
                <li>Copy the payment reference code above</li>
                <li>Go to your bank app or online banking</li>
                <li>Transfer ₦<?php echo number_format($ngn_amount, 2); ?> to any account below</li>
                <li><strong>Important:</strong> Include the reference code in the payment description/remark</li>
                <li>Submit the payment proof in your orders page</li>
            </ol>
        </div>

        <div class="bank-details">
            <h3>Bank Account Details</h3>
            
            <?php if (count($bank_list) > 0): ?>
                <?php foreach ($bank_list as $bank): ?>
                <div class="bank-card">
                    <div class="bank-item">
                        <span class="bank-label">Bank Name:</span>
                        <span class="bank-value"><?php echo htmlspecialchars($bank['bank_name']); ?></span>
                    </div>
                    <div class="bank-item">
                        <span class="bank-label">Account Name:</span>
                        <span class="bank-value"><?php echo htmlspecialchars($bank['account_name']); ?></span>
                    </div>
                    <div class="bank-item">
                        <span class="bank-label">Account Number:</span>
                        <span class="bank-value"><?php echo htmlspecialchars($bank['account_number']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bank-card">
                    <p style="color: #999;">No bank accounts configured. Contact admin.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="timer" id="timer">
            ⏰ Payment expires in: <span id="countdown">1 hour</span>
        </div>

        <div class="note">
            <strong>⚠️ Important:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <li>Always include the payment reference in the transfer description</li>
                <li>Your payment will be verified and confirmed within 5-10 minutes</li>
                <li>Once confirmed, your order will be processed automatically</li>
                <li>If payment is not received within 1 hour, the order will be cancelled</li>
            </ul>
        </div>
    </div>

    <script>
        function copyRef() {
            const ref = document.getElementById('refCode').textContent;
            navigator.clipboard.writeText(ref).then(() => {
                alert('Reference copied!');
            });
        }

        // Countdown timer
        let timeLeft = 3600; // 1 hour in seconds
        setInterval(() => {
            timeLeft--;
            const hours = Math.floor(timeLeft / 3600);
            const minutes = Math.floor((timeLeft % 3600) / 60);
            const seconds = timeLeft % 60;
            document.getElementById('countdown').textContent = 
                (hours > 0 ? hours + 'h ' : '') + minutes + 'm ' + seconds + 's';
            
            if (timeLeft <= 0) {
                location.href = 'orders.php';
            }
        }, 1000);
    </script>
</body>
</html>

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

// Create pending order
$sql = "INSERT INTO orders (user_id, service_id, quantity, target_url, total_price, status) 
        VALUES ({$user['id']}, $service_id, $quantity, '$target', $total, 'pending_payment')";
$conn->query($sql);
$order_id = $conn->insert_id;

// Create pending payment
$sql = "INSERT INTO payments (user_id, amount, method, status) 
        VALUES ({$user['id']}, $total, 'crypto', 'pending')";
$conn->query($sql);
$payment_id = $conn->insert_id;

// Generate QR code URL (using qr-server)
$wallet = USDT_WALLET;
$network = NETWORK;
$qr_text = "ethereum:" . $wallet . "?amount=" . $total;
if ($network == 'tron') {
    $qr_text = "tron:" . $wallet . "?amount=" . $total;
}

$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_text);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Payment - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .header p {
            color: #999;
            font-size: 14px;
        }
        .alert {
            background: #f0f7ff;
            border: 1px solid #b3d9ff;
            color: #0066cc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .amount-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 25px;
        }
        .amount-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .amount-value {
            font-size: 32px;
            font-weight: 700;
        }
        .amount-unit {
            font-size: 16px;
            opacity: 0.9;
        }
        .qr-section {
            text-align: center;
            margin-bottom: 25px;
        }
        .qr-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .qr-code {
            background: white;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            display: inline-block;
        }
        .qr-code img {
            width: 250px;
            height: 250px;
        }
        .wallet-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .wallet-label {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .wallet-address {
            word-break: break-all;
            color: #333;
            font-family: monospace;
            font-size: 12px;
            margin-bottom: 12px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .copy-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
            transition: background 0.3s;
        }
        .copy-btn:hover {
            background: #764ba2;
        }
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }
        .instructions h4 {
            margin-bottom: 10px;
            font-size: 14px;
        }
        .instructions ol {
            margin-left: 20px;
        }
        .instructions li {
            margin-bottom: 8px;
        }
        .network-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 10px 0;
        }
        .note {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            font-size: 13px;
            color: #333;
            line-height: 1.6;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 15px;
        }
        .order-id {
            color: #999;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Send USDT Payment</h1>
            <p>Order #<?php echo $order_id; ?></p>
        </div>

        <div class="status-pending">🕐 Payment Pending</div>

        <div class="alert">
            <strong>Network:</strong> <?php echo strtoupper(NETWORK); ?> (<?php echo NETWORK == 'bsc' ? 'Binance Smart Chain' : 'Tron'; ?>)
        </div>

        <div class="amount-box">
            <div class="amount-label">Amount Required</div>
            <div class="amount-value"><?php echo $total; ?></div>
            <div class="amount-unit">USDT</div>
        </div>

        <div class="qr-section">
            <h3>Scan QR Code or Copy Address</h3>
            <div class="qr-code">
                <img src="<?php echo $qr_url; ?>" alt="Payment QR Code">
            </div>
        </div>

        <div class="wallet-section">
            <div class="wallet-label">Wallet Address</div>
            <div class="wallet-address" id="walletAddr"><?php echo USDT_WALLET; ?></div>
            <button class="copy-btn" onclick="copyToClipboard()">📋 Copy Address</button>
        </div>

        <div class="instructions">
            <h4>📋 Payment Instructions:</h4>
            <ol>
                <li>Open your wallet (MetaMask, TrustWallet, etc.)</li>
                <li>Make sure you're on <?php echo strtoupper(NETWORK); ?> network</li>
                <li>Send <strong><?php echo $total; ?> USDT</strong> to the wallet address above</li>
                <li>Wait for confirmation (usually 1-5 minutes)</li>
                <li>Your order will be processed automatically</li>
            </ol>
        </div>

        <div class="note">
            <strong>⚠️ Important:</strong> Send EXACTLY <?php echo $total; ?> USDT on the <?php echo strtoupper(NETWORK); ?> network only. 
            Sending from other networks may result in loss of funds. Your order will be automatically confirmed once payment is received.
        </div>

        <div class="order-id">
            Order ID: <strong><?php echo $order_id; ?></strong>
            | Payment ID: <strong><?php echo $payment_id; ?></strong>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const text = document.getElementById('walletAddr').textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('Wallet address copied!');
            });
        }

        // Auto-refresh to check payment status every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

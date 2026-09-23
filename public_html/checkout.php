<?php
require 'config.php';
check_auth();

$user = get_user();

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['service_id'])) {
    redirect('/services.php');
}

$service_id = sanitize($_POST['service_id'] ?? '');
$quantity = (int)($_POST['quantity'] ?? 0);
$service_name = sanitize($_POST['service_name'] ?? '');
$price = (float)($_POST['price'] ?? 0);

if ($quantity < 1) {
    redirect('/services.php');
}

$total_price = $quantity * $price;

// Get service details
$service = $conn->query("SELECT * FROM services WHERE id = $service_id")->fetch_assoc();

if (!$service) {
    redirect('/services.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo SITE_NAME; ?></title>
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
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .order-summary {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            height: fit-content;
        }
        .order-summary h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            color: #666;
        }
        .summary-item.total {
            padding: 20px 0;
            border-bottom: none;
            font-weight: 700;
            font-size: 18px;
            color: #667eea;
        }
        .payment-methods {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .payment-methods h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        .payment-option {
            display: flex;
            align-items: center;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .payment-option:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        .payment-option input[type="radio"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            cursor: pointer;
        }
        .payment-info {
            flex: 1;
        }
        .payment-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .payment-desc {
            font-size: 14px;
            color: #999;
        }
        .payment-icon {
            font-size: 28px;
            margin-right: 15px;
        }
        form {
            margin-top: 20px;
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
        .btn-checkout {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        .btn-checkout:hover {
            transform: translateY(-2px);
        }
        .btn-back {
            background: #ddd;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
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
        <a href="services.php" class="btn-back">← Back to Services</a>

        <div class="checkout-grid">
            <div class="order-summary">
                <h2>Order Summary</h2>
                
                <div class="summary-item">
                    <span>Service:</span>
                    <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                </div>
                
                <div class="summary-item">
                    <span>Platform:</span>
                    <strong><?php echo htmlspecialchars($service['platform']); ?></strong>
                </div>
                
                <div class="summary-item">
                    <span>Quantity:</span>
                    <strong><?php echo $quantity; ?></strong>
                </div>
                
                <div class="summary-item">
                    <span>Unit Price:</span>
                    <strong>$<?php echo number_format($price, 4); ?></strong>
                </div>
                
                <div class="summary-item total">
                    <span>Total:</span>
                    <span>$<?php echo number_format($total_price, 2); ?></span>
                </div>

                <div style="margin-top: 20px; padding: 15px; background: #f0f7ff; border-radius: 5px; color: #333; font-size: 14px;">
                    <strong>Your Balance:</strong> $<?php echo number_format($user['balance'], 2); ?>
                </div>
            </div>

            <div class="payment-methods">
                <h2>Payment Method</h2>
                
                <form method="POST" id="paymentForm">
                    <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
                    <input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
                    <input type="hidden" name="total_price" value="<?php echo $total_price; ?>">

                    <!-- USDT/Crypto Payment -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="crypto" required>
                        <div class="payment-icon">💰</div>
                        <div class="payment-info">
                            <div class="payment-title">USDT (Crypto)</div>
                            <div class="payment-desc">Pay with BSC USDT or TRC-20 USDT</div>
                        </div>
                    </label>

                    <!-- Bank Transfer -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="bank" required>
                        <div class="payment-icon">🏦</div>
                        <div class="payment-info">
                            <div class="payment-title">Bank Transfer</div>
                            <div class="payment-desc">Local bank transfer (NGN)</div>
                        </div>
                    </label>

                    <!-- Account Balance -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="balance" required>
                        <div class="payment-icon">💳</div>
                        <div class="payment-info">
                            <div class="payment-title">Account Balance</div>
                            <div class="payment-desc">Use your wallet balance</div>
                        </div>
                    </label>

                    <!-- Target URL (required for order) -->
                    <div class="form-group" style="margin-top: 25px;">
                        <label>Target URL/Username *</label>
                        <input type="text" name="target_url" placeholder="e.g., https://instagram.com/yourprofile or @username" required>
                    </div>

                    <button type="submit" class="btn-checkout">Proceed to Payment</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const method = document.querySelector('input[name="payment_method"]:checked').value;
            const target = document.querySelector('input[name="target_url"]').value.trim();
            
            if (!target) {
                e.preventDefault();
                alert('Please enter target URL/username');
                return;
            }

            if (method === 'crypto') {
                e.preventDefault();
                window.location.href = 'pay-crypto.php?' + new URLSearchParams({
                    service_id: <?php echo $service_id; ?>,
                    quantity: <?php echo $quantity; ?>,
                    total: <?php echo $total_price; ?>,
                    target: target
                });
            } else if (method === 'bank') {
                e.preventDefault();
                window.location.href = 'pay-bank.php?' + new URLSearchParams({
                    service_id: <?php echo $service_id; ?>,
                    quantity: <?php echo $quantity; ?>,
                    total: <?php echo $total_price; ?>,
                    target: target
                });
            } else if (method === 'balance') {
                this.action = 'process-balance-payment.php';
            }
        });
    </script>
</body>
</html>

<?php
require 'config.php';
require 'providers.php';
check_auth();

$user = get_user();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    redirect('/services.php');
}

$service_id = sanitize($_POST['service_id'] ?? '');
$quantity = (int)($_POST['quantity'] ?? 0);
$total_price = (float)($_POST['total_price'] ?? 0);
$target_url = sanitize($_POST['target_url'] ?? '');

if (!$service_id || !$quantity || !$total_price || !$target_url) {
    redirect('/checkout.php');
}

// Check balance
if ($user['balance'] < $total_price) {
    $_SESSION['error'] = 'Insufficient balance. Please top up your wallet.';
    redirect('/wallet.php');
}

// Get service details with provider info
$service = $conn->query("
    SELECT s.*, p.provider_name, p.api_key
    FROM services s
    LEFT JOIN provider_accounts p ON s.provider = p.provider_name
    WHERE s.id = $service_id
")->fetch_assoc();

if (!$service) {
    redirect('/services.php');
}

// Deduct balance
$new_balance = $user['balance'] - $total_price;
$conn->query("UPDATE users SET balance = $new_balance WHERE id = {$user['id']}");

// Create order
$sql = "INSERT INTO orders (user_id, service_id, quantity, target_url, total_price, status, provider) 
        VALUES ({$user['id']}, $service_id, $quantity, '$target_url', $total_price, 'pending', '{$service['provider']}')";
$conn->query($sql);
$order_id = $conn->insert_id;

// Create payment record
$sql = "INSERT INTO payments (user_id, amount, method, status) 
        VALUES ({$user['id']}, $total_price, 'balance', 'completed')";
$conn->query($sql);

// Get list of providers to try (in order of preference)
$enabled_providers = $conn->query("
    SELECT provider_name, api_key 
    FROM provider_accounts 
    WHERE is_active = 1 
    ORDER BY 
        CASE 
            WHEN provider_name = '{$service['provider']}' THEN 0
            ELSE 1 
        END,
        (SELECT success_rate FROM provider_performance WHERE provider = provider_accounts.provider_name DESC LIMIT 1)
    LIMIT 5
");

$provider_list = [];
while ($prov = $enabled_providers->fetch_assoc()) {
    $provider_list[] = $prov;
}

$order_placed = false;
$provider_used = null;
$provider_order_id = null;

// Try to place order on each provider
foreach ($provider_list as $prov) {
    $provider_name = $prov['provider_name'];
    
    // Log attempt
    $conn->query("
        INSERT INTO order_logs (order_id, provider, action, status)
        VALUES ($order_id, '$provider_name', 'place_order', 'attempting')
    ");

    // Determine service ID for this provider
    $service_id_for_provider = $service[$provider_name . '_id'] ?? $service['provider_service_id'];
    
    if (!$service_id_for_provider) {
        continue; // Skip if no service ID for this provider
    }

    // Place order on provider
    $result = place_order_on_provider(
        $provider_name,
        $service_id_for_provider,
        $target_url,
        $quantity
    );

    if (!isset($result['error'])) {
        // Success!
        $provider_order_id = $result['order'] ?? $result['order_id'] ?? $result['id'];
        $provider_used = $provider_name;
        
        // Update order with provider details
        $conn->query("
            UPDATE orders 
            SET provider = '$provider_name',
                provider_order_id = '$provider_order_id',
                status = 'processing',
                cost_to_provider = " . ($quantity * $service['provider_rate']) . ",
                our_profit = " . ($total_price - ($quantity * $service['provider_rate'])) . "
            WHERE id = $order_id
        ");

        // Log success
        $conn->query("
            INSERT INTO order_logs (order_id, provider, action, status, response)
            VALUES ($order_id, '$provider_name', 'place_order', 'success', '$provider_order_id')
        ");

        $order_placed = true;
        break;
    } else {
        // Log error
        $error_msg = $conn->real_escape_string($result['error']);
        $conn->query("
            INSERT INTO order_logs (order_id, provider, action, status, response)
            VALUES ($order_id, '$provider_name', 'place_order', 'failed', '$error_msg')
        ");
    }
}

if ($order_placed) {
    $_SESSION['success'] = 'Order placed successfully! Order ID: ' . $order_id . ' (Provider: ' . ucfirst($provider_used) . ')';
    
    // Update provider performance
    $conn->query("
        UPDATE provider_performance 
        SET total_orders = total_orders + 1,
            successful_orders = successful_orders + 1
        WHERE provider = '$provider_used'
    ");
    
    redirect('/orders.php?id=' . $order_id);
} else {
    // All providers failed - mark order as failed and refund balance
    $conn->query("UPDATE orders SET status = 'failed' WHERE id = $order_id");
    $conn->query("UPDATE users SET balance = balance + $total_price WHERE id = {$user['id']}");
    
    $_SESSION['error'] = 'Unable to place order on any provider. Balance has been refunded.';
    redirect('/orders.php');
}
?>

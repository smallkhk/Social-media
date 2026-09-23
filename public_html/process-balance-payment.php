<?php
require 'config.php';
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

// Deduct balance
$new_balance = $user['balance'] - $total_price;
$conn->query("UPDATE users SET balance = $new_balance WHERE id = {$user['id']}");

// Create order
$sql = "INSERT INTO orders (user_id, service_id, quantity, target_url, total_price, status) 
        VALUES ({$user['id']}, $service_id, $quantity, '$target_url', $total_price, 'pending')";
$conn->query($sql);
$order_id = $conn->insert_id;

// Create payment record
$sql = "INSERT INTO payments (user_id, amount, method, status) 
        VALUES ({$user['id']}, $total_price, 'balance', 'completed')";
$conn->query($sql);

// Call Crescitaly API to place order
$service = $conn->query("SELECT crescitaly_id FROM services WHERE id = $service_id")->fetch_assoc();

if ($service['crescitaly_id']) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => CRESCITALY_API_URL . '/add',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'key' => CRESCITALY_API_KEY,
            'service' => $service['crescitaly_id'],
            'link' => $target_url,
            'quantity' => $quantity
        )),
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    
    $result = json_decode($response, true);
    
    if (isset($result['order'])) {
        $conn->query("UPDATE orders SET crescitaly_order_id = '{$result['order']}' WHERE id = $order_id");
        $conn->query("UPDATE orders SET status = 'processing' WHERE id = $order_id");
    }
}

$_SESSION['success'] = 'Order placed successfully! Order ID: ' . $order_id;
redirect('/orders.php?id=' . $order_id);
?>

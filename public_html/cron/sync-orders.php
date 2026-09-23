<?php
// Auto-sync order status from all providers
// Run this via cron: 0 */5 * * * curl https://yourdomain.com/cron/sync-orders.php

require '../config.php';
require '../providers.php';

// Verify this is a cron call
if ($_SERVER['HTTP_X_CRON_TOKEN'] !== 'your-cron-secret-token') {
    if (!isset($_GET['token']) || $_GET['token'] !== 'your-cron-secret-token') {
        http_response_code(403);
        die('Unauthorized');
    }
}

$sync_log = array();

// Get all pending/processing orders
$orders = $conn->query("
    SELECT * FROM orders 
    WHERE status IN ('pending', 'processing', 'pending_payment')
    AND provider IS NOT NULL
    AND provider_order_id IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 100
");

$updated_count = 0;
$error_count = 0;

while ($order = $orders->fetch_assoc()) {
    $order_id = $order['id'];
    $provider = $order['provider'];
    $provider_order_id = $order['provider_order_id'];
    
    // Check status on provider
    $result = check_provider_order_status($provider, $provider_order_id);
    
    if (isset($result['status'])) {
        $new_status = map_provider_status($provider, $result['status']);
        
        // Update order if status changed
        if ($new_status !== $order['provider_status']) {
            $conn->query("
                UPDATE orders 
                SET provider_status = '$new_status'
                WHERE id = $order_id
            ");
            
            // If completed, update main status
            if ($new_status === 'completed' || $new_status === 'finished') {
                $conn->query("UPDATE orders SET status = 'completed' WHERE id = $order_id");
                
                // Update provider performance
                $conn->query("
                    UPDATE provider_performance 
                    SET successful_orders = successful_orders + 1
                    WHERE provider = '$provider'
                ");
            } elseif ($new_status === 'failed' || $new_status === 'error') {
                $conn->query("UPDATE orders SET status = 'failed' WHERE id = $order_id");
                
                // Refund user
                $user_id = $order['user_id'];
                $total_price = $order['total_price'];
                $conn->query("UPDATE users SET balance = balance + $total_price WHERE id = $user_id");
                
                // Update provider performance
                $conn->query("
                    UPDATE provider_performance 
                    SET failed_orders = failed_orders + 1
                    WHERE provider = '$provider'
                ");
                
                $error_count++;
            }
            
            // Log the sync
            $conn->query("
                INSERT INTO order_logs (order_id, provider, action, status, response)
                VALUES ($order_id, '$provider', 'status_sync', '$new_status', '$result[status]')
            ");
            
            $updated_count++;
        }
    } else {
        $error_count++;
        $sync_log[] = "Order $order_id on $provider: Failed to get status";
    }
}

// Update provider balance
foreach (['crescitaly', 'panelcom', 'socioboard', 'smmcom'] as $provider) {
    $balance = get_provider_balance($provider);
    if ($balance !== false) {
        $conn->query("
            UPDATE provider_accounts 
            SET balance = $balance, last_balance_check = NOW()
            WHERE provider_name = '$provider'
        ");
    }
}

// Return sync status
echo json_encode(array(
    'status' => 'success',
    'updated_orders' => $updated_count,
    'errors' => $error_count,
    'timestamp' => date('Y-m-d H:i:s'),
    'log' => $sync_log
));

/**
 * Map provider status to universal status
 */
function map_provider_status($provider, $status) {
    $status = strtolower($status);
    
    // Crescitaly status mapping
    if ($provider === 'crescitaly') {
        $map = array(
            'pending' => 'pending',
            'processing' => 'processing',
            'completed' => 'completed',
            'failed' => 'failed',
            'partial' => 'processing'
        );
    }
    // Panel.com status mapping
    elseif ($provider === 'panelcom') {
        $map = array(
            'new' => 'pending',
            'processing' => 'processing',
            'success' => 'completed',
            'error' => 'failed',
            'completed' => 'completed'
        );
    }
    // Socioboard status mapping
    elseif ($provider === 'socioboard') {
        $map = array(
            'pending' => 'pending',
            'in_progress' => 'processing',
            'completed' => 'completed',
            'cancelled' => 'failed',
            'finished' => 'completed'
        );
    }
    // SMM.com status mapping
    elseif ($provider === 'smmcom') {
        $map = array(
            'pending' => 'pending',
            'processing' => 'processing',
            'completed' => 'completed',
            'error' => 'failed',
            'failed' => 'failed'
        );
    }
    
    return $map[$status] ?? $status;
}
?>

<?php
// Nora Social Media Panel - Configuration
// Copy to your hosting and update with your credentials

define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'nora_smm_panel');

define('SITE_NAME', 'Nora Social Media Panel');
define('SITE_URL', 'https://yourdomain.com');

// Crescitaly API
define('CRESCITALY_API_KEY', 'your_crescitaly_api_key');
define('CRESCITALY_API_URL', 'https://crescitaly.com/api');
define('CRESCITALY_RESELLER_ID', 'your_reseller_id');

// MoreThanPanel API (https://morethanpanel.com/api)
define('MORETHANPANEL_API_KEY', 'your_morethanpanel_api_key');
define('MORETHANPANEL_API_URL', 'https://morethanpanel.com/api/v2');

// Crypto Payment - BSC USDT (TRC-20 also supported)
define('CRYPTO_ENABLED', true);
define('USDT_WALLET', 'your_bsc_usdt_wallet_address');
define('USDT_CONTRACT', '0x55d398326f99059fF775485246999027B3197955'); // BSC USDT
define('USDT_DECIMALS', 18);
define('NETWORK', 'bsc'); // or 'tron' for TRC-20

// Bank Transfer
define('BANK_TRANSFER_ENABLED', true);

// Admin panel
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// Session timeout
define('SESSION_TIMEOUT', 3600 * 24); // 24 hours

// JWT Secret for API
define('JWT_SECRET', 'your_jwt_secret_key_change_this');

// Pagination
define('ITEMS_PER_PAGE', 10);

// Error reporting
define('DEBUG', false);

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die(json_encode(['error' => 'Database connection failed']));
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}

session_start();

// Helper functions
function response($data, $code = 200) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitize($input) {
    global $conn;
    return $conn->real_escape_string(strip_tags($input));
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function check_auth() {
    if (!is_logged_in()) {
        redirect('/login.php');
    }
}

function get_user() {
    global $conn;
    if (!is_logged_in()) return null;
    
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT * FROM users WHERE id = $user_id");
    return $result->fetch_assoc();
}

function generate_api_key() {
    return bin2hex(random_bytes(32));
}
?>

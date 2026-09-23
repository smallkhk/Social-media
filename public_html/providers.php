<?php
// Multi-Provider SMM Integration
// Supports: Crescitaly, Panel.com, Socioboard, SMM.com

// Provider configurations
define('PROVIDERS', array(
    'crescitaly' => array(
        'name' => 'Crescitaly',
        'api_url' => 'https://crescitaly.com/api',
        'enabled' => true
    ),
    'panelcom' => array(
        'name' => 'Panel.com',
        'api_url' => 'https://panel.com/api',
        'enabled' => true
    ),
    'socioboard' => array(
        'name' => 'Socioboard',
        'api_url' => 'https://api.socioboard.com',
        'enabled' => true
    ),
    'smmcom' => array(
        'name' => 'SMM.com',
        'api_url' => 'https://api.smm.com',
        'enabled' => true
    )
));

// Provider API Keys (configure in admin panel or here)
$provider_keys = array(
    'crescitaly_key' => getenv('CRESCITALY_API_KEY') ?: '',
    'panelcom_key' => getenv('PANELCOM_API_KEY') ?: '',
    'socioboard_key' => getenv('SOCIOBOARD_API_KEY') ?: '',
    'smmcom_key' => getenv('SMMCOM_API_KEY') ?: ''
);

/**
 * Get provider configuration
 */
function get_provider($provider_name) {
    return PROVIDERS[$provider_name] ?? null;
}

/**
 * Get all enabled providers
 */
function get_enabled_providers() {
    $enabled = array();
    foreach (PROVIDERS as $key => $provider) {
        if ($provider['enabled']) {
            $enabled[$key] = $provider;
        }
    }
    return $enabled;
}

/**
 * Call Crescitaly API
 */
function crescitaly_api_call($action, $params) {
    $api_key = getenv('CRESCITALY_API_KEY') ?: CRESCITALY_API_KEY;
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => CRESCITALY_API_URL . '/' . $action,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array_merge($params, array('key' => $api_key))),
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        CURLOPT_TIMEOUT => 30
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return array('error' => $err);
    }
    
    return json_decode($response, true);
}

/**
 * Call Panel.com API
 */
function panelcom_api_call($action, $params) {
    $api_key = getenv('PANELCOM_API_KEY') ?: '';
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.panel.com/v1/' . $action,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ),
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_TIMEOUT => 30
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return array('error' => $err);
    }
    
    return json_decode($response, true);
}

/**
 * Call Socioboard API
 */
function socioboard_api_call($action, $params) {
    $api_key = getenv('SOCIOBOARD_API_KEY') ?: '';
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.socioboard.com/' . $action,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-API-Key: ' . $api_key
        ),
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_TIMEOUT => 30
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return array('error' => $err);
    }
    
    return json_decode($response, true);
}

/**
 * Call SMM.com API
 */
function smmcom_api_call($action, $params) {
    $api_key = getenv('SMMCOM_API_KEY') ?: '';
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.smm.com/' . $action,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
        ),
        CURLOPT_POSTFIELDS => http_build_query(array_merge($params, array('api_token' => $api_key))),
        CURLOPT_TIMEOUT => 30
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return array('error' => $err);
    }
    
    return json_decode($response, true);
}

/**
 * Universal API caller - routes to correct provider
 */
function call_provider_api($provider, $action, $params) {
    switch($provider) {
        case 'crescitaly':
            return crescitaly_api_call($action, $params);
        case 'panelcom':
            return panelcom_api_call($action, $params);
        case 'socioboard':
            return socioboard_api_call($action, $params);
        case 'smmcom':
            return smmcom_api_call($action, $params);
        default:
            return array('error' => 'Unknown provider: ' . $provider);
    }
}

/**
 * Place order on provider with fallback
 */
function place_order_on_provider($provider, $service_id, $target, $quantity) {
    $params = array(
        'service' => $service_id,
        'link' => $target,
        'quantity' => $quantity
    );
    
    $result = call_provider_api($provider, 'add', $params);
    
    if (isset($result['error'])) {
        // Try next provider if available
        return array('error' => $result['error'], 'provider' => $provider);
    }
    
    return array_merge($result, array('provider' => $provider));
}

/**
 * Get provider balance
 */
function get_provider_balance($provider) {
    $result = call_provider_api($provider, 'balance', array());
    return $result['balance'] ?? 0;
}

/**
 * Check order status from provider
 */
function check_provider_order_status($provider, $order_id) {
    $result = call_provider_api($provider, 'status', array('order_id' => $order_id));
    return $result;
}

?>

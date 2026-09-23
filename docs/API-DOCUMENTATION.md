# 🔌 NORA PANEL - REST API DOCUMENTATION

## Base URL
```
https://yourdomain.com/api/v1
```

## Authentication

All API requests require an API key in the header:

```
Authorization: Bearer YOUR_API_KEY
```

Get your API key from: `https://yourdomain.com/account.php`

---

## 1. Services API

### List All Services

```bash
GET /services
```

**Query Parameters:**
- `platform` (optional): Filter by platform (instagram, tiktok, youtube, etc)
- `limit` (optional, default: 100): Max results
- `offset` (optional, default: 0): Pagination offset

**Response:**
```json
{
    "status": "success",
    "count": 150,
    "services": [
        {
            "id": 1,
            "service_name": "Instagram Followers",
            "platform": "instagram",
            "provider": "crescitaly",
            "price": 0.001,
            "min_quantity": 10,
            "max_quantity": 10000,
            "category": "followers"
        }
    ]
}
```

### Get Service Details

```bash
GET /services/{service_id}
```

**Response:**
```json
{
    "status": "success",
    "service": {
        "id": 1,
        "service_name": "Instagram Followers",
        "platform": "instagram",
        "provider": "crescitaly",
        "price": 0.001,
        "min_quantity": 10,
        "max_quantity": 10000,
        "provider_rate": 0.0005,
        "our_margin": 0.0005
    }
}
```

---

## 2. Orders API

### Place Order

```bash
POST /orders
```

**Request Body:**
```json
{
    "service_id": 1,
    "quantity": 100,
    "target": "https://instagram.com/username"
}
```

**Response:**
```json
{
    "status": "success",
    "order": {
        "id": 12345,
        "service_id": 1,
        "quantity": 100,
        "target": "https://instagram.com/username",
        "total_price": 0.10,
        "status": "pending",
        "provider": "crescitaly",
        "provider_order_id": "ABC123",
        "created_at": "2024-01-15T10:30:00Z"
    }
}
```

### Check Order Status

```bash
GET /orders/{order_id}
```

**Response:**
```json
{
    "status": "success",
    "order": {
        "id": 12345,
        "service_id": 1,
        "status": "processing",
        "progress": 45,
        "provider": "crescitaly",
        "provider_status": "in-progress"
    }
}
```

### Get All Orders

```bash
GET /orders
```

**Query Parameters:**
- `status` (optional): Filter by status (pending, processing, completed, failed)
- `limit` (optional, default: 50)
- `offset` (optional, default: 0)

**Response:**
```json
{
    "status": "success",
    "count": 150,
    "orders": [
        {
            "id": 12345,
            "service_id": 1,
            "status": "completed",
            "total_price": 0.10,
            "created_at": "2024-01-15T10:30:00Z"
        }
    ]
}
```

### Cancel Order

```bash
POST /orders/{order_id}/cancel
```

**Response:**
```json
{
    "status": "success",
    "message": "Order cancelled and refunded",
    "refund_amount": 0.10
}
```

---

## 3. Account API

### Get Account Info

```bash
GET /account
```

**Response:**
```json
{
    "status": "success",
    "account": {
        "id": 1,
        "email": "user@example.com",
        "username": "john_doe",
        "balance": 150.50,
        "total_orders": 245,
        "total_spent": 1250.75,
        "api_key": "sk_live_abc123xyz..."
    }
}
```

### Add Balance

```bash
POST /account/balance/add
```

**Request Body:**
```json
{
    "amount": 50.00,
    "payment_method": "crypto" // or "bank_transfer"
}
```

**Response:**
```json
{
    "status": "success",
    "payment": {
        "id": "PAY123",
        "amount": 50.00,
        "method": "crypto",
        "status": "pending",
        "wallet_address": "0x123...",
        "expires_at": "2024-01-16T10:30:00Z"
    }
}
```

### Get Balance

```bash
GET /account/balance
```

**Response:**
```json
{
    "status": "success",
    "balance": 150.50,
    "currency": "USD"
}
```

---

## 4. Analytics API

### Get Order Statistics

```bash
GET /analytics/orders
```

**Query Parameters:**
- `days` (optional, default: 30): Lookback period

**Response:**
```json
{
    "status": "success",
    "stats": {
        "total_orders": 245,
        "completed": 240,
        "failed": 5,
        "total_spent": 1250.75,
        "success_rate": 98.0,
        "average_order_value": 5.10
    }
}
```

### Get Platform Statistics

```bash
GET /analytics/platforms
```

**Response:**
```json
{
    "status": "success",
    "platforms": [
        {
            "platform": "instagram",
            "orders": 120,
            "spent": 500.00,
            "success_rate": 99.0
        },
        {
            "platform": "tiktok",
            "orders": 80,
            "spent": 300.00,
            "success_rate": 97.0
        }
    ]
}
```

---

## 5. Reseller API

### Get Reseller Info

```bash
GET /reseller
```

**Response:**
```json
{
    "status": "success",
    "reseller": {
        "id": 1,
        "name": "My Reseller Business",
        "commission_percent": 15,
        "api_key": "res_abc123...",
        "referral_link": "https://yourdomain.com/signup?ref=res_abc123",
        "total_referrals": 25,
        "total_commissions": 125.50
    }
}
```

### Get Reseller Commissions

```bash
GET /reseller/commissions
```

**Query Parameters:**
- `status` (optional): pending, approved, paid
- `limit` (optional, default: 50)

**Response:**
```json
{
    "status": "success",
    "commissions": [
        {
            "id": 1,
            "order_id": 12345,
            "commission_percent": 15,
            "order_amount": 10.00,
            "commission_amount": 1.50,
            "status": "approved"
        }
    ]
}
```

### Get Reseller Stats

```bash
GET /reseller/stats
```

**Response:**
```json
{
    "status": "success",
    "stats": {
        "total_referrals": 25,
        "total_sales": 250.00,
        "total_commissions": 125.50,
        "this_month": 50.00,
        "pending_payout": 25.00
    }
}
```

---

## 6. Webhook API

### Register Webhook

```bash
POST /webhooks
```

**Request Body:**
```json
{
    "url": "https://your-app.com/webhook",
    "events": ["order.created", "order.completed", "order.failed"]
}
```

**Response:**
```json
{
    "status": "success",
    "webhook": {
        "id": "wh_123",
        "url": "https://your-app.com/webhook",
        "events": ["order.created", "order.completed", "order.failed"],
        "secret": "whsec_abc123xyz"
    }
}
```

### Webhook Events

#### order.created
```json
{
    "event": "order.created",
    "data": {
        "id": 12345,
        "service_id": 1,
        "quantity": 100,
        "target": "https://instagram.com/username",
        "total_price": 0.10,
        "status": "pending",
        "created_at": "2024-01-15T10:30:00Z"
    }
}
```

#### order.completed
```json
{
    "event": "order.completed",
    "data": {
        "id": 12345,
        "status": "completed",
        "completed_at": "2024-01-15T14:30:00Z"
    }
}
```

#### order.failed
```json
{
    "event": "order.failed",
    "data": {
        "id": 12345,
        "status": "failed",
        "reason": "Service unavailable on provider",
        "refund_amount": 0.10
    }
}
```

---

## Error Handling

### Success Response
```json
{
    "status": "success",
    "data": { ... }
}
```

### Error Response
```json
{
    "status": "error",
    "code": "INSUFFICIENT_BALANCE",
    "message": "Your account balance is too low for this order",
    "details": {
        "required": 0.50,
        "available": 0.30
    }
}
```

### Common Error Codes
- `INVALID_API_KEY` - API key is missing or invalid
- `INSUFFICIENT_BALANCE` - Account doesn't have enough balance
- `SERVICE_NOT_FOUND` - Service ID doesn't exist
- `QUANTITY_INVALID` - Quantity is outside min/max range
- `TARGET_INVALID` - Target URL is not valid
- `RATE_LIMITED` - Too many requests (100/minute)
- `SERVER_ERROR` - Internal server error

---

## Rate Limits

- **Standard**: 100 requests/minute
- **Reseller**: 500 requests/minute
- **Enterprise**: Unlimited

---

## Code Examples

### JavaScript (Node.js)

```javascript
const API_KEY = 'your_api_key_here';
const BASE_URL = 'https://yourdomain.com/api/v1';

// Get services
async function getServices() {
    const response = await fetch(`${BASE_URL}/services`, {
        headers: {
            'Authorization': `Bearer ${API_KEY}`,
            'Content-Type': 'application/json'
        }
    });
    return await response.json();
}

// Place order
async function placeOrder(serviceId, quantity, target) {
    const response = await fetch(`${BASE_URL}/orders`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${API_KEY}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            service_id: serviceId,
            quantity: quantity,
            target: target
        })
    });
    return await response.json();
}

// Check order status
async function checkOrderStatus(orderId) {
    const response = await fetch(`${BASE_URL}/orders/${orderId}`, {
        headers: {
            'Authorization': `Bearer ${API_KEY}`
        }
    });
    return await response.json();
}

// Usage
getServices().then(data => console.log(data));
placeOrder(1, 100, 'https://instagram.com/username').then(data => console.log(data));
```

### Python

```python
import requests

API_KEY = 'your_api_key_here'
BASE_URL = 'https://yourdomain.com/api/v1'

headers = {
    'Authorization': f'Bearer {API_KEY}',
    'Content-Type': 'application/json'
}

# Get services
def get_services():
    response = requests.get(f'{BASE_URL}/services', headers=headers)
    return response.json()

# Place order
def place_order(service_id, quantity, target):
    data = {
        'service_id': service_id,
        'quantity': quantity,
        'target': target
    }
    response = requests.post(f'{BASE_URL}/orders', headers=headers, json=data)
    return response.json()

# Check status
def check_order_status(order_id):
    response = requests.get(f'{BASE_URL}/orders/{order_id}', headers=headers)
    return response.json()

# Usage
print(get_services())
print(place_order(1, 100, 'https://instagram.com/username'))
```

### PHP

```php
<?php
$API_KEY = 'your_api_key_here';
$BASE_URL = 'https://yourdomain.com/api/v1';

$headers = array(
    'Authorization: Bearer ' . $API_KEY,
    'Content-Type: application/json'
);

// Get services
function get_services() {
    global $BASE_URL, $headers;
    $ch = curl_init($BASE_URL . '/services');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Place order
function place_order($service_id, $quantity, $target) {
    global $BASE_URL, $headers;
    $data = array(
        'service_id' => $service_id,
        'quantity' => $quantity,
        'target' => $target
    );
    
    $ch = curl_init($BASE_URL . '/orders');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Usage
echo json_encode(get_services());
echo json_encode(place_order(1, 100, 'https://instagram.com/username'));
?>
```

---

## Support

For API support:
- Email: api-support@yourdomain.com
- Discord: https://discord.gg/your-server
- Documentation: https://yourdomain.com/api/docs

---

Last updated: January 2024

# Nora Panel V2 - Multi-Provider SMM System

## Overview
PHP/MySQL SMM reseller panel with 5 providers: Crescitaly, Panel.com, Socioboard, SMM.com, MoreThanPanel. Auto-routing, failover, real-time sync, affiliate system.

## Providers

| Provider | URL | Services | Best For |
|---|---|---|---|
| Crescitaly | crescitaly.com | 2500+ | Reliability |
| Panel.com | panel.com | 3000+ | Cheapest rates |
| Socioboard | socioboard.com | 2000+ | API quality |
| SMM.com | smm.com | 2200+ | Drip feed |
| MoreThanPanel | morethanpanel.com | — | Standard API v2, refill + cancel |

## Architecture

### Core Files
- **providers.php** - Multi-provider API layer with provider routing
- **process-multi-provider-payment.php** - Order processing with failover logic
- **cron/sync-orders.php** - Auto-sync status every 5 min, auto-refund fails

### Admin Panels
- **admin/providers.php** - Manage API keys, view performance
- **admin/services.php** - Add/edit services, set prices
- **admin/analytics.php** - Charts, revenue, profit breakdown
- **admin/price-optimizer.php** - Auto-pricing strategies

### Reseller System
- **reseller.php** - Affiliate dashboard, commission tracking
- Database: resellers, referral_commissions, reseller_payouts tables

### Database
- **nora-panel-db.sql** - Base schema
- **upgrade-db-providers.sql** - Provider tables, order_logs, performance tracking
- **upgrade-db-reseller.sql** - Affiliate/commission system
- **upgrade-db-morethanpanel.sql** - Adds MoreThanPanel to an existing install

## Key Functions

### providers.php
```php
call_provider_api($provider, $action, $params)  // Route API call
place_order_on_provider($provider, $service_id, $target, $qty)  // Place with failover
get_provider_balance($provider)  // Check balance
check_provider_order_status($provider, $order_id)  // Get status
```

### process-multi-provider-payment.php
1. Deduct customer balance
2. Try provider 1 (configured)
3. If fails → Try provider 2 (best success rate)
4. If fails → Try provider 3
5. If fails → Try provider 4
6. All fail → Refund + log failure
7. Success → Update stats

### cron/sync-orders.php
- Runs every 5 min
- Checks all pending/processing orders
- Updates status from each provider
- Auto-refunds failed orders
- Updates provider performance metrics

## Order Flow

```
POST /process-multi-provider-payment.php
    ↓
Check balance + deduct
    ↓
Try providers in order
    ↓
Success? → Log order_id + provider + status
    ↓
Every 5min: cron/sync-orders.php checks status
    ↓
Status changed? Update DB + send notifications
    ↓
Failed? Refund + update stats
```

## Database Schema

### Main Tables
- **users** - Email, password (bcrypt), balance, wallet_address
- **services** - platform, service_name, price, provider, provider_service_id, provider_rate, our_margin
- **orders** - user_id, service_id, quantity, target_url, total_price, status, provider, provider_order_id
- **provider_accounts** - provider_name, api_key, api_secret, balance, last_balance_check
- **order_logs** - order_id, provider, action, status, response (for debugging)
- **provider_performance** - provider, total_orders, successful_orders, failed_orders, success_rate

### Reseller Tables
- **resellers** - user_id, name, commission_percent, api_key
- **referral_commissions** - reseller_id, order_id, commission_amount, status
- **reseller_payouts** - reseller_id, payout_month, total_commissions, status

## API Endpoints (REST)

```
GET  /api/v1/services
GET  /api/v1/services/{id}
POST /api/v1/orders
GET  /api/v1/orders/{id}
GET  /api/v1/orders
GET  /api/v1/account
GET  /api/v1/account/balance
POST /api/v1/account/balance/add
GET  /api/v1/analytics/orders
GET  /api/v1/analytics/platforms
GET  /api/v1/reseller
GET  /api/v1/reseller/commissions
GET  /api/v1/reseller/stats
```

Auth: `Authorization: Bearer {api_key}`

## Admin Passwords
- All admin panels use hardcoded check: `$_POST['admin_password'] !== 'changeme123'`
- Change before launch

## Pricing Strategies

1. **Cheapest Provider** - Find cheapest per service, add margin
2. **Percentage Margin** - Fixed % markup on all
3. **Competitive** - Average of all providers + margin
4. **Platform-Based** - Different margins per platform (IG 50%, TikTok 40%, etc)

## Affiliate System

- User becomes reseller: sets commission % (5-100%)
- Gets unique referral link: `/signup?ref={api_key}`
- Commission on every order from referral
- Tracked in referral_commissions table
- Monthly payout via reseller_payouts

## Setup Checklist

- [ ] Import all SQL files (base + upgrade-db-providers + upgrade-db-reseller)
- [ ] Update config.php (DB credentials, API keys)
- [ ] Upload public_html/* to hosting
- [ ] Change admin passwords
- [ ] Get API keys for all 4 providers
- [ ] Add keys in admin/providers.php
- [ ] Setup cron: `*/5 * * * * curl "https://yourdomain.com/cron/sync-orders.php?token=SECRET"`
- [ ] Enable HTTPS
- [ ] Test order placement

## File Sizes

- providers.php: ~6KB
- process-multi-provider-payment.php: ~5KB
- cron/sync-orders.php: ~5KB
- admin/providers.php: ~15KB
- admin/services.php: ~16KB
- admin/analytics.php: ~18KB
- admin/price-optimizer.php: ~21KB
- reseller.php: ~17KB
- Database schemas: ~10KB total

**Total: ~120KB code + documentation**

## Config Values

```php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'nora_user');
define('DB_PASS', 'password');
define('DB_NAME', 'nora_panel');
define('CRESCITALY_API_KEY', '...');  // From crescitaly.com
define('PANELCOM_API_KEY', '...');    // From panel.com
define('SOCIOBOARD_API_KEY', '...'); // From socioboard.com
define('SMMCOM_API_KEY', '...');      // From smm.com
define('MORETHANPANEL_API_KEY', '...'); // From morethanpanel.com/api
define('USDT_WALLET', '0x...');       // Your BSC wallet
define('USDT_NETWORK', 'BSC');        // or 'TRC20'
define('SITE_URL', 'https://yourdomain.com');
define('SITE_NAME', 'Nora Panel');
define('JWT_SECRET', 'long_random_string');
define('DEBUG', false);
```

## Error Handling

### Order Failures
- Logged in order_logs table with provider_name, error message
- Auto-refunds customer balance
- Updates provider_performance failed_orders count
- Retries disabled provider on next order

### Missing API Key
- Skips provider in routing
- Tries next provider
- Logs skip in order_logs

### Cron Failures
- Checks last_balance_check timestamp in provider_accounts
- Manual sync via: `curl https://yourdomain.com/cron/sync-orders.php?token=SECRET`

## Profit Model

**Direct:** Customer Price - Provider Cost = Your Profit

**Affiliate:** 
- Customer pays: Your Price × (1 + Reseller Commission%)
- You keep: Your Price
- Reseller gets: Commission $

**Example:**
- Service costs provider: $0.0005
- You set price: $0.001 (100% margin)
- Reseller commission: 15%
- Customer pays: $0.00115
- You profit: $0.0005
- Reseller profit: $0.00015

## Security

- Passwords: `password_hash()` with PASSWORD_BCRYPT
- Input: `mysqli::real_escape_string()` + `strip_tags()`
- Sessions: PHP native, 24h timeout
- .htaccess: Blocks config.php, blocks dot files
- API keys: `bin2hex(random_bytes(32))`
- HTTPS: Required for production

## Monitoring Queries

```sql
-- Orders by provider last 30 days
SELECT provider, COUNT(*) as count, SUM(our_profit) as profit
FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY provider ORDER BY profit DESC;

-- Failed orders
SELECT * FROM order_logs WHERE status = 'failed' ORDER BY created_at DESC LIMIT 20;

-- Provider performance
SELECT * FROM provider_performance ORDER BY success_rate DESC;

-- Top resellers
SELECT r.name, COUNT(o.id) as orders, SUM(rc.commission_amount) as earned
FROM resellers r
LEFT JOIN orders o ON o.referrer_id = r.id
LEFT JOIN referral_commissions rc ON rc.order_id = o.id
GROUP BY r.id ORDER BY earned DESC;
```

## Scaling

- Handles: 1000+ orders/day per instance
- Add providers: Edit PROVIDERS constant in providers.php
- Load balance: Add more servers behind Nginx
- Cache: Redis for provider balances, order status
- Queue: Move cron to message queue for real-time sync

## Deployment

- Shared hosting (cPanel): PHP 7.4+, MySQL 5.7+
- VPS (EC2): Same, with PM2 for Node alternatives
- Domain: HTTPS required, DNS A record to hosting

---

**Version:** 2.0 (Multi-Provider)  
**Last Updated:** January 2024  
**Status:** Production Ready

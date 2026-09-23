# 🔄 NORA PANEL - MULTI-PROVIDER INTEGRATION GUIDE

## Overview

Nora Panel now supports **4 major SMM providers**:
- ✅ **Crescitaly** - 2500+ services, reliable
- ✅ **Panel.com** - 3000+ services, cheap rates
- ✅ **Socioboard** - Great API, analytics included
- ✅ **SMM.com** - Drip feed support, bulk discounts

The system intelligently routes orders to the best-performing provider with automatic failover.

---

## 🚀 SETUP (Step by Step)

### Step 1: Upgrade Database
```bash
# SSH into your server or run in phpMyAdmin
mysql -u your_user -p your_database < upgrade-db-providers.sql

# Or in phpMyAdmin: Import file > choose upgrade-db-providers.sql
```

### Step 2: Get API Keys from Providers

#### Crescitaly
1. Go to https://crescitaly.com
2. Register/Login
3. Dashboard → API Settings
4. Copy API Key

#### Panel.com
1. Go to https://panel.com
2. Register/Login
3. Account Settings → API
4. Copy API Key

#### Socioboard
1. Go to https://socioboard.com
2. Register/Login
3. Settings → API Keys
4. Create new key + copy

#### SMM.com
1. Go to https://smm.com
2. Register/Login
3. Account → API Settings
4. Copy API Token

#### MoreThanPanel
1. Go to https://morethanpanel.com
2. Register/Login
3. Account → API (docs: https://morethanpanel.com/api)
4. Copy API Key
5. Import `database/upgrade-db-morethanpanel.sql` if you already ran `upgrade-db-providers.sql` earlier

MoreThanPanel uses the standard SMM API v2 (`POST https://morethanpanel.com/api/v2` with `key` + `action`).
Supported in `providers.php`: `services`, `add` (incl. drip-feed `runs`/`interval`), `status`,
`refill`, `cancel`, `balance`. Use `get_provider_services('morethanpanel')` to list service IDs and
rates for `provider_service_id` / `provider_rate`. `Partial` orders refund the customer for the
undelivered `remains`; `Canceled` orders are fully refunded by `cron/sync-orders.php`.

### Step 3: Enter API Keys in Admin Panel

1. Upload `admin/providers.php` to your hosting
2. Visit: `https://yourdomain.com/admin/providers.php`
3. For each provider:
   - Paste API Key
   - Check "Active" to enable
   - Save Changes

---

## 📊 How It Works

### Provider Selection Algorithm

When an order is placed:

1. **Get preferred provider** - Based on service configuration
2. **Check if active** - Only use enabled providers
3. **Route to best performer**:
   - Try preferred provider first
   - If fails, try next by success rate
   - Continue until success or all fail
4. **Automatic refund** - If all providers fail

### Performance Tracking

System tracks for each provider:
- ✅ Success rate %
- 📈 Total orders placed
- ⏱ Average completion speed
- 💰 Total profit generated

---

## 💰 PRICING STRATEGY (Example)

```
Provider Rate vs Your Price = Your Profit

Example:
├─ Crescitaly Instagram Followers: $0.0005 per unit
│  ├─ You charge: $0.001 per unit
│  └─ Your profit: $0.0005 per unit (100% margin)
│
├─ Panel.com Instagram Followers: $0.0003 per unit  
│  ├─ You charge: $0.001 per unit
│  └─ Your profit: $0.0007 per unit (233% margin!)
│
└─ Socioboard Instagram Followers: $0.0008 per unit
   ├─ You charge: $0.001 per unit
   └─ Your profit: $0.0002 per unit (25% margin)
```

**Strategy:**
1. Get rates from all providers
2. Set your price slightly above cheapest
3. When order comes in, use cheapest provider
4. Maximize profit per order

### Setting Provider Rates in Database

```sql
-- Update service with provider-specific rates
UPDATE services 
SET provider_rate = 0.0003,  -- What you pay provider
    our_margin = 0.0007      -- What you keep
WHERE provider = 'panelcom' 
AND service_name = 'Instagram Followers';
```

---

## 🔐 Security

### Protect Admin Panel

Edit `admin/providers.php` and change:
```php
if ($_POST['admin_password'] !== 'changeme123') {
    // Change to strong password!
}
```

Or better - implement proper admin auth:
```php
require 'config.php';
check_admin_auth(); // Create this function with sessions
```

### Store API Keys Securely

```php
// Don't hardcode in config.php, use environment variables instead:
// Add to .env or cPanel environment:
CRESCITALY_API_KEY=your_key_here
PANELCOM_API_KEY=your_key_here
MORETHANPANEL_API_KEY=your_key_here
```

### Cron Job Protection

Edit `cron/sync-orders.php`:
```php
$valid_tokens = array('your-long-random-token-here');
if (!in_array($_GET['token'] ?? '', $valid_tokens)) {
    die('Unauthorized');
}
```

---

## ⚙️ CRON JOBS SETUP

### Check Order Status Every 5 Minutes

**Via cPanel:**
1. cPanel → Cron Jobs
2. Add cron job:
   ```
   0 */5 * * * curl "https://yourdomain.com/cron/sync-orders.php?token=your-cron-secret-token"
   ```

**Via Linux Command Line:**
```bash
# Edit crontab
crontab -e

# Add line:
*/5 * * * * curl -s "https://yourdomain.com/cron/sync-orders.php?token=your-secret" >> /var/log/nora-sync.log
```

---

## 📈 MONITORING & OPTIMIZATION

### View Performance Dashboard

Visit: `https://yourdomain.com/admin/providers.php`

Shows:
- Success rate by provider
- Number of orders completed
- Profit generated
- Failed orders

### Optimize Provider Selection

```sql
-- Find best performing provider
SELECT provider, 
       COUNT(*) as total_orders,
       SUM(our_profit) as total_profit,
       success_rate
FROM provider_performance
ORDER BY success_rate DESC, total_profit DESC
LIMIT 1;
```

### Adjust Pricing Based on Provider Cost

```sql
-- If Panel.com is cheaper than Crescitaly
UPDATE services 
SET provider = 'panelcom'
WHERE provider = 'crescitaly'
AND provider_rate > 0.0003;  -- Only if cheaper
```

---

## 🐛 TROUBLESHOOTING

### "Order Failed on All Providers"

**Check:**
1. Are API keys correct?
2. Do services exist on provider?
3. Is provider active?
4. Check `order_logs` table for error details

```sql
SELECT * FROM order_logs WHERE order_id = 123 ORDER BY created_at DESC;
```

### Order Stuck in "Processing"

**Check:**
1. Is cron job running? (Check last sync time)
2. Is provider_order_id correct?
3. Check provider's dashboard - manual status update

```sql
-- Manually update stuck order
UPDATE orders 
SET provider_status = 'completed', status = 'completed'
WHERE id = 123;
```

### Low Success Rate on Provider

**Options:**
1. Disable provider temporarily
2. Use provider only for cheap services
3. Contact provider support
4. Switch to different provider

```sql
-- Disable underperforming provider
UPDATE provider_accounts 
SET is_active = 0 
WHERE provider_name = 'socioboard';
```

---

## 💡 BEST PRACTICES

### 1. Start with One Provider

- Deploy with Crescitaly only
- Get familiar with order flow
- Add other providers later

### 2. Monitor First Week

- Check all orders manually
- Verify deliveries
- Confirm profit calculations

### 3. Gradually Expand

- Add Panel.com (best for Instagram)
- Add Socioboard (best API support)
- Add SMM.com (best for TikTok)

### 4. Price Competitively

```
Your Price = Cheapest Provider Rate + Margin (20-50%)

Example:
├─ Cheapest rate: $0.0003
├─ Your margin: 50%
└─ Your price: $0.00045
```

### 5. Balance Orders

- Don't use one provider exclusively
- Spread load across providers
- Reduces risk if one goes down

---

## 🎯 RECOMMENDED SETUP

### For Maximum Profit:
```
1. Get rates from all 4 providers
2. For each service:
   - Use cheapest provider as primary
   - Set others as fallback
3. Monitor success rates weekly
4. Adjust pricing based on costs
```

### For Reliability:
```
1. Prefer providers with 95%+ success rate
2. Use auto-failover for all orders
3. Check status every 5 minutes
4. Quick support response time
```

### For Scalability:
```
1. Start with 2-3 providers
2. Load balance orders evenly
3. Keep API keys in environment variables
4. Use database for audit trail
```

---

## 📊 SAMPLE PROVIDER COMPARISON

| Feature | Crescitaly | Panel.com | Socioboard | SMM.com |
|---------|-----------|----------|-----------|---------|
| Services | 2500+ | 3000+ | 2000+ | 2200+ |
| Avg Rate | $0.0005 | $0.0003 | $0.0006 | $0.0004 |
| Drip Feed | Yes | Yes | No | Yes |
| API | Good | Good | Excellent | Good |
| Support | Fast | Slow | Fast | Medium |
| Success Rate | 95% | 92% | 97% | 93% |

---

## 🚀 NEXT LEVEL

### Implement Advanced Features:

1. **Smart Routing** - Choose provider based on service type
2. **A/B Testing** - Compare providers on same service
3. **Dynamic Pricing** - Adjust prices based on provider costs
4. **Bulk Discounts** - Offer discounts for high volume
5. **Provider Ratings** - Show customers which provider handled their order
6. **Automatic Failover** - Seamlessly switch providers
7. **Load Balancing** - Distribute orders to fastest providers
8. **Analytics** - Dashboard showing profit by provider/service

---

## 📝 SAMPLE QUERIES

### Get most profitable provider:
```sql
SELECT provider, SUM(our_profit) as total_profit
FROM orders
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY provider
ORDER BY total_profit DESC;
```

### Find failed orders:
```sql
SELECT * FROM orders WHERE status = 'failed' ORDER BY created_at DESC;
```

### Check provider balance:
```sql
SELECT * FROM provider_accounts WHERE is_active = 1;
```

### Get order details with logs:
```sql
SELECT o.*, ol.provider, ol.status, ol.response
FROM orders o
LEFT JOIN order_logs ol ON o.id = ol.order_id
WHERE o.id = 123
ORDER BY ol.created_at DESC;
```

---

## ✅ DEPLOYMENT CHECKLIST

- [ ] Run `upgrade-db-providers.sql`
- [ ] Upload `providers.php` to root
- [ ] Upload `admin/providers.php` to admin folder
- [ ] Upload `cron/sync-orders.php` to cron folder
- [ ] Get API keys from all 4 providers
- [ ] Enter API keys in admin panel
- [ ] Set up cron job to sync orders
- [ ] Test with small order
- [ ] Verify order placed on provider
- [ ] Check order sync from cron
- [ ] Monitor success rates
- [ ] Adjust pricing for profit
- [ ] Go live!

---

Good luck! 🚀 You now have a powerful multi-provider SMM panel!

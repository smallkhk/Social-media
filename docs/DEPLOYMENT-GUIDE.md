# 🚀 NORA SOCIAL MEDIA PANEL - DEPLOYMENT GUIDE

## Quick Start (Shared Hosting - Hostinger/Namecheap cPanel)

### 1. DATABASE SETUP
1. Go to **cPanel** → **phpMyAdmin**
2. Create new database: `nora_smm_panel`
3. Import the SQL file: `nora-panel-db.sql`
4. Create database user with full permissions

### 2. FILE UPLOAD
1. Download all PHP files to your computer
2. Connect via FTP (FileZilla recommended)
   - Host: ftp.yourdomain.com
   - Username: cPanel username
   - Password: cPanel password
   - Port: 21
3. Upload all files to `public_html/` folder

### 3. CONFIGURE APPLICATION
1. Edit `config.php` with your settings:
   ```php
   DB_HOST: localhost
   DB_USER: your_database_user
   DB_PASS: your_database_password
   DB_NAME: nora_smm_panel
   SITE_URL: https://yourdomain.com
   USDT_WALLET: your_wallet_address
   ```

2. Add Crescitaly API credentials:
   ```php
   CRESCITALY_API_KEY: get_from_crescitaly.com
   CRESCITALY_RESELLER_ID: your_reseller_id
   ```

3. Add Bank Accounts via phpMyAdmin:
   - Insert rows in `bank_accounts` table:
   ```sql
   INSERT INTO bank_accounts (bank_name, account_number, account_name, is_active) 
   VALUES ('GTBank', '1234567890', 'Nora SMM Panel', 1);
   ```

### 4. ADD SERVICES
Option A - Manual (phpMyAdmin):
```sql
INSERT INTO services (platform, service_name, description, price, min_quantity, max_quantity, crescitaly_id, category) 
VALUES 
('Instagram', 'Instagram Followers', 'High Quality Followers', 0.0005, 10, 10000, '1', 'followers'),
('Instagram', 'Instagram Likes', 'Real Engagement', 0.00008, 10, 50000, '2', 'likes'),
('TikTok', 'TikTok Views', 'Instant Views', 0.00002, 100, 100000, '3', 'views');
```

Option B - Create admin panel (optional):
Create `admin/add-service.php` for easier management

### 5. ADMIN SETUP
Add yourself as admin:
```sql
INSERT INTO admin_users (email, password, role) 
VALUES ('admin@yourdomain.com', 'hashed_password', 'admin');
```

### 6. VERIFY INSTALLATION
- Visit: https://yourdomain.com/register.php
- Create test account
- Test login
- Test buy flow (no actual charge)

---

## FILE STRUCTURE

```
public_html/
├── config.php                      # Main configuration (EDIT THIS!)
├── login.php                       # Login page
├── register.php                    # Registration page
├── logout.php                      # Logout handler
├── dashboard.php                   # Main dashboard
├── services.php                    # Browse services
├── checkout.php                    # Checkout page
├── pay-crypto.php                  # Crypto payment page
├── pay-bank.php                    # Bank transfer page
├── process-balance-payment.php     # Process account balance payment
├── orders.php                      # View orders
├── wallet.php                      # Wallet & top-up
├── account.php                     # Account settings
└── nora-panel-db.sql              # Database schema
```

---

## PAYMENT INTEGRATION

### CRYPTO (USDT - BSC/TRC-20)
1. Create wallet: https://metamask.io or https://www.trust-wallet.com
2. Get BSC/Tron USDT address
3. Add to `config.php`:
   ```php
   USDT_WALLET = '0x...your_wallet...';
   NETWORK = 'bsc'; // or 'tron'
   ```
4. Monitor payments manually or use blockchain API

### BANK TRANSFER
1. Add bank accounts in `bank_accounts` table
2. Update config.php with bank details
3. Create admin panel to approve payments

### AUTOMATIC WEBHOOK (For Production)
Create `webhooks/crypto-payment.php` for automatic crypto confirmations:
```php
<?php
// Listen for crypto transfers from blockchain
// Mark payment as completed
// Deduct from user balance
// Place order on Crescitaly
?>
```

---

## CRESCITALY INTEGRATION

### Connect API
1. Register at https://crescitaly.com
2. Get API Key from settings
3. Add to config.php
4. Test with sample order

### Auto-Place Orders
Edit `process-balance-payment.php`:
```php
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://crescitaly.com/api/add',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'key' => CRESCITALY_API_KEY,
        'service' => $service['crescitaly_id'],
        'link' => $target_url,
        'quantity' => $quantity
    ])
));
```

---

## SECURITY CHECKLIST

- [ ] Change all default passwords
- [ ] Set `DEBUG = false` in config.php
- [ ] Update `SESSION_TIMEOUT` as needed
- [ ] Enable HTTPS (free Let's Encrypt in cPanel)
- [ ] Create `.htaccess` to block direct access to config.php:
  ```
  <FilesMatch "^config\.php$">
    Order allow,deny
    Deny from all
  </FilesMatch>
  ```
- [ ] Disable directory listing in cPanel
- [ ] Set file permissions: 644 for PHP, 755 for directories
- [ ] Regular database backups via cPanel

---

## CRON JOBS (Optional)

### Check Payment Status
Create `cron/check-payments.php`:
```bash
0 */5 * * * curl https://yourdomain.com/cron/check-payments.php
```

### Update Order Status from Crescitaly
Create `cron/sync-orders.php`:
```bash
0 * * * * curl https://yourdomain.com/cron/sync-orders.php
```

---

## TROUBLESHOOTING

**Database Connection Error**
- Verify database credentials in config.php
- Ensure user has all privileges
- Check if database server is running

**Payment Not Processing**
- Verify API keys are correct
- Check payment table in database
- Ensure crypto wallet is correct

**Orders Not Placed**
- Verify Crescitaly API key
- Check service crescitaly_id mapping
- Check curl is enabled in PHP

**Sessions Not Working**
- Check session.save_path in PHP
- Ensure /tmp directory exists
- Verify PHP session settings

---

## MAINTENANCE

### Weekly
- Check payment confirmations
- Review new user registrations
- Monitor server resources

### Monthly
- Update service prices
- Review Crescitaly rates
- Backup database
- Update exchange rates for crypto

---

## CUSTOMIZATION

### Change Colors
Edit CSS in each `.php` file - search for `#667eea` and `#764ba2`

### Add Logo
Replace site name in navbar with:
```html
<img src="logo.png" style="height: 40px;">
```

### Custom Domain
1. Update DNS records in Namecheap
2. Add domain to cPanel
3. Update `SITE_URL` in config.php

### Email Notifications
Add to `config.php`:
```php
use PHPMailer\PHPMailer\PHPMailer;

function send_email($to, $subject, $body) {
    $mail = new PHPMailer();
    $mail->Host = 'smtp.gmail.com';
    $mail->Username = 'your@gmail.com';
    $mail->Password = 'app_password';
    // ... send email
}
```

---

## FEATURES INCLUDED

✅ User Registration & Login
✅ Multiple Payment Methods (Crypto + Bank)
✅ Crescitaly API Integration
✅ Order Management
✅ Wallet System
✅ Service Browsing
✅ Admin Panel Ready
✅ API Key for Developers
✅ Transaction History
✅ Responsive Design
✅ SSL Ready
✅ Mobile Friendly

---

## NEXT STEPS

1. Deploy to shared hosting
2. Test all payment methods
3. Add real services from Crescitaly
4. Customize branding
5. Promote to users
6. Monitor for issues
7. Scale up

---

## SUPPORT

For issues:
1. Check error logs in cPanel
2. Review database entries
3. Test API connections
4. Check payment confirmations

---

## LICENSE & CUSTOMIZATION

This panel is fully customizable. 
Extend with:
- SMS integrations
- Email notifications
- Affiliate system
- API for resellers
- Mobile app
- Multiple currencies
- More payment gateways

Good luck! 🚀

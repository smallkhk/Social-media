# ⚡ NORA SOCIAL MEDIA PANEL - QUICK START

## 📋 Pre-Deployment Checklist

### Requirements
- [ ] PHP 7.4+ (check with hosting provider)
- [ ] MySQL 5.7+
- [ ] HTTPS enabled (SSL certificate)
- [ ] Domain name (yourdomain.com)
- [ ] FTP/SFTP access
- [ ] cPanel access (if using Hostinger/Namecheap)

---

## 🚀 DEPLOYMENT IN 10 MINUTES

### Step 1: Database Setup (2 min)
```
1. Login to cPanel
2. Go to phpMyAdmin
3. Create new database: nora_smm_panel
4. Click "Import" tab
5. Upload nora-panel-db.sql
6. Click "Import"
```

### Step 2: Upload Files (2 min)
```
1. Download all .php files to your computer
2. Open FileZilla (or cPanel File Manager)
3. Connect to FTP
4. Upload all files to public_html/
```

### Step 3: Configuration (2 min)
```
1. Download config.php
2. Edit with notepad/VSCode
3. Fill in your database credentials
4. Fill in wallet addresses
5. Fill in Crescitaly API key
6. Upload back to public_html/
```

### Step 4: Add Services (2 min)
```
1. Login to phpMyAdmin → nora_smm_panel
2. Go to "services" table
3. Click "Insert" 
4. Add services (or import sample-data.sql)
```

### Step 5: Test (2 min)
```
1. Visit https://yourdomain.com/register.php
2. Create test account
3. Login
4. Go to Services page
5. Everything working? ✅
```

---

## 📝 REQUIRED CONFIGURATION

### config.php
```php
DB_HOST = localhost
DB_USER = your_db_user_here
DB_PASS = your_db_password_here
DB_NAME = nora_smm_panel

SITE_URL = https://yourdomain.com

USDT_WALLET = 0x1234567890abcdef... (your wallet)
NETWORK = bsc (or tron)

CRESCITALY_API_KEY = your_api_key_here
CRESCITALY_RESELLER_ID = your_reseller_id

ADMIN_EMAIL = admin@yourdomain.com
JWT_SECRET = change_this_to_something_random
```

### Bank Accounts
Add via phpMyAdmin → bank_accounts table:
```sql
INSERT INTO bank_accounts VALUES 
(NULL, 'GTBank', '0123456789', 'Nora Panel', 1),
(NULL, 'Access Bank', '9876543210', 'Nora Admin', 1);
```

---

## ✅ VERIFICATION

- [ ] Can register: https://yourdomain.com/register.php
- [ ] Can login: https://yourdomain.com/login.php  
- [ ] Dashboard loads: https://yourdomain.com/dashboard.php
- [ ] Can view services: https://yourdomain.com/services.php
- [ ] Wallet page works: https://yourdomain.com/wallet.php
- [ ] Account page works: https://yourdomain.com/account.php

---

## 🔐 SECURITY SETUP (5 min)

After deployment:

1. **Upload .htaccess** (blocks sensitive files)
2. **Update config.php**:
   - Set `DEBUG = false`
   - Change `JWT_SECRET`
3. **Enable HTTPS**:
   - cPanel → AutoSSL or Let's Encrypt
4. **Disable Directory Listing**:
   - cPanel → File Manager → Preferences
5. **Set File Permissions**:
   - PHP files: 644
   - Folders: 755

---

## 💰 PAYMENT SETUP

### Crypto (USDT)
1. Create MetaMask wallet
2. Get BSC USDT address
3. Add to config.php
4. Monitor payments manually OR set up webhook

### Bank Transfer
1. Add bank account details in database
2. Create admin panel to approve transfers
3. Or create approval form for users

### Balance Payments
- Users top up wallet directly
- Then use balance to purchase services

---

## 📊 AFTER DEPLOYMENT

### Daily Tasks
- Check new user registrations
- Monitor payment confirmations
- Process orders

### Weekly Tasks
- Update service prices from Crescitaly
- Review payment history
- Check order statuses

### Monthly Tasks
- Backup database (cPanel Backups)
- Review analytics
- Update exchange rates (crypto/fiat)
- Promote services

---

## 🆘 QUICK TROUBLESHOOTING

**White screen of death?**
- Check error logs in cPanel
- Verify database credentials
- Ensure all files were uploaded

**Can't login?**
- Database connection issue
- Check config.php credentials
- Verify users table exists

**Services not showing?**
- Services table might be empty
- Import sample-data.sql
- Check "is_active" field = 1

**Payments not working?**
- Verify wallet address
- Check Crescitaly API key
- Ensure required PHP extensions

---

## 📞 FILE STRUCTURE OVERVIEW

| File | Purpose |
|------|---------|
| config.php | Main configuration file |
| register.php | User registration |
| login.php | User login |
| dashboard.php | Main dashboard |
| services.php | Browse services |
| checkout.php | Checkout page |
| pay-crypto.php | Crypto payment |
| pay-bank.php | Bank payment |
| orders.php | View orders |
| wallet.php | Wallet & top-up |
| account.php | Account settings |
| nora-panel-db.sql | Database schema |
| sample-data.sql | Test data |
| DEPLOYMENT-GUIDE.md | Full guide |

---

## 🎯 NEXT STEPS

1. ✅ Deploy to shared hosting
2. ✅ Add your Crescitaly services
3. ✅ Set up payment methods
4. ✅ Test complete flow
5. ✅ Create admin panel (optional)
6. ✅ Customize branding
7. ✅ Launch & promote
8. ✅ Monitor & scale

---

## 💡 TIPS

- Test everything on test account first
- Keep config.php safe (don't share)
- Use HTTPS for all pages
- Regularly backup database
- Monitor payment confirmations
- Keep Crescitaly API key secret
- Test all payment methods

---

## 🎉 YOU'RE READY!

Once deployed, your panel will:
- Accept user registrations
- Process crypto payments (USDT BSC/TRC-20)
- Process bank transfers (NGN)
- Use account balance
- Place orders on Crescitaly
- Display order status
- Manage user accounts

Start promoting and earning! 🚀

---

For detailed info, see DEPLOYMENT-GUIDE.md

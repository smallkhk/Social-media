# Deploying Nora Panel on Namecheap (cPanel shared hosting)

About 20 minutes. You need: a Namecheap hosting plan with cPanel, your domain pointed at it, and a MoreThanPanel account with some balance.

## 1. Set the PHP version

cPanel → **Select PHP Version** (or "MultiPHP Manager").
Pick **PHP 8.1 or newer** (8.2 recommended) for your domain.
The extensions `pdo_mysql`, `curl` and `mbstring` must be ticked (they are by default).

## 2. Create the database

cPanel → **MySQL Databases**:

1. *Create New Database*: e.g. `nora` → cPanel names it `cpaneluser_nora`.
2. *Add New User*: e.g. `nora` with a strong password → `cpaneluser_nora`.
3. *Add User To Database*: pick both, tick **ALL PRIVILEGES**, save.

Write down the full database name, user name and password.

## 3. Import the tables

cPanel → **phpMyAdmin** → click your database on the left → **Import** tab →
choose `install.sql` (in this repo's `database/` folder) → **Import** (or "Go").
You should see 10 tables.

## 4. Upload the files

cPanel → **File Manager** → open `public_html` (or your domain's folder, for an addon domain).

1. Upload `nora-panel-cpanel.zip` (or zip the contents of this repo's `public_html/` folder yourself).
2. Right-click it → **Extract**. The files (`index.php`, `app/`, `admin/`, …) must sit **directly** inside `public_html`,
   not in `public_html/public_html`. Delete the zip afterwards.
3. Make sure hidden files are shown (File Manager → Settings → *Show Hidden Files*) and that `.htaccess` and `app/.htaccess` are there.

## 5. Configure

In File Manager open `public_html/app/`, copy `config.sample.php` to **`config.php`** and edit it:

| Setting | What to put |
|---|---|
| `DB_NAME` / `DB_USER` / `DB_PASS` | the values from step 2 (with the `cpaneluser_` prefix) |
| `SITE_URL` | `https://yourdomain.com` (no trailing slash) |
| `CRON_TOKEN` | any long random string |
| `CRYPTO_WALLET` / `CRYPTO_NETWORK` | your USDT address and its network |
| `BANK_DETAILS` | your bank account text, or set `BANK_ENABLED` to `false` |
| `AFFILIATE_PERCENT` | commission for referrers, `0` to turn the program off |

`app/` is blocked from the web by `app/.htaccess`, so the passwords in `config.php` cannot be downloaded.

## 6. SSL

cPanel → **SSL/TLS Status** → *Run AutoSSL* (Namecheap includes a free certificate).
The site forces HTTPS, so do this before opening it. If the site loops or won't load before SSL is ready,
temporarily comment out the three "Force HTTPS" lines in `public_html/.htaccess`.

## 7. Create your admin account

Open `https://yourdomain.com/admin/` → you are sent to the one-time setup page → create the admin login.
(The setup page locks itself once an admin exists.)

## 8. Connect MoreThanPanel

1. On morethanpanel.com log in → **API** page → copy your API key.
2. Your panel → **Admin → Providers** → *Edit* MoreThanPanel → paste the key → **Save & test connection**.
   You should see "Connection OK - balance …".
3. Click **Import services**, filter by category, tick the services you want, set your markup % and import.
   Your selling price = MoreThanPanel's rate + markup. Re-importing later updates prices.

Any other SMM panel with the standard API v2 can be added the same way (**+ Add provider**).
Crescitaly is pre-added but switched off; add its key if you use it.
In **Admin → Services** you can set a *backup provider* per service: if the main provider refuses an order, it goes to the backup.

## 9. Cron job (order status + refunds)

cPanel → **Cron Jobs** → *Add New Cron Job*:

- Common settings: **Once Per Five Minutes** (`*/5 * * * *`)
- Command (replace `cpaneluser` with your cPanel username):

```
/usr/local/bin/php /home/cpaneluser/public_html/cron/sync.php >/dev/null 2>&1
```

This checks open orders with the providers, marks them completed/partial/canceled, refunds customers for anything not
delivered, pays affiliate commission and updates provider balances.
The admin dashboard warns you if orders haven't been checked for 30 minutes.

If the PHP command doesn't work on your plan, use the URL form instead:

```
wget -q -O /dev/null "https://yourdomain.com/cron/sync.php?token=YOUR_CRON_TOKEN"
```

## 10. Test it

1. Register a customer account at `https://yourdomain.com/register.php`.
2. Submit a small deposit in **Add funds**, then approve it in **Admin → Payments** (check your wallet/bank first).
3. Place a small order and watch it in **Admin → Orders** (the *Log* button shows the provider's replies).

## Daily running

- **Admin → Payments**: approve deposits after you see the money arrive. Nothing is credited automatically.
- **Keep MoreThanPanel topped up.** If your provider balance runs out, orders are refused and customers are refunded automatically.
- **Backups**: cPanel → Backup → download a database backup regularly.
- **Errors** are written to `public_html/error_log` (viewable in File Manager). Set `DEBUG` to `true` in `config.php` only while troubleshooting.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Setup needed: copy app/config.sample.php…" | Step 5 not done: `app/config.php` is missing |
| "Database connection failed" | Check `DB_NAME`/`DB_USER`/`DB_PASS` include the `cpaneluser_` prefix, and the user was added to the database with all privileges |
| 500 error on every page | PHP version below 8.1 (step 1), or `.htaccess` edited incorrectly |
| Links/CSS go to the wrong address | `SITE_URL` must exactly match the address you open, including `https://` and `www.` if you use it |
| "Your session expired" on forms | Refresh the page and try again; also check `SITE_URL` matches the domain you're using |
| Orders stay "Processing" forever | Cron job not running (step 9). Test by opening the cron URL with your token in a browser |
| Provider test says "Invalid API key" | Re-copy the key from the provider's API page |

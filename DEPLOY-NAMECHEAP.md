# Installing Nora Panel on Namecheap (cPanel)

Everything is in **one file: `nora-panel.zip`**. About 10 minutes.

## 1. Create an empty database (cPanel → MySQL Databases)

1. *Create New Database*, e.g. `nora` (cPanel calls it `cpaneluser_nora`).
2. *Add New User* with a strong password (e.g. `cpaneluser_nora`).
3. *Add User To Database* → pick both → tick **ALL PRIVILEGES** → save.

Keep the database name, user and password handy.

## 2. Turn on SSL (cPanel → SSL/TLS Status → Run AutoSSL)

The panel only runs over `https://`. Namecheap's certificate is free.

## 3. Upload and extract the zip (cPanel → File Manager)

Open `public_html`, upload **`nora-panel.zip`**, right-click it → **Extract**, then delete the zip.
`index.php`, `install.php`, `app/`, `admin/`… must sit **directly** in `public_html`.

## 4. Run the installer

Open **`https://yourdomain.com/install.php`** and fill in the form:

- Database name, user and password from step 1
- Your admin email and password
- Your MoreThanPanel API key (from morethanpanel.com → API page), optional, can be added later
- Your USDT wallet / bank details, optional

Click **Install**. It creates the tables, writes `app/config.php`, creates your admin login and then locks/deletes itself.
If a requirement shows a red ✗, go to cPanel → **Select PHP Version**, choose **8.1 or newer**, tick `pdo_mysql`, `curl`, `mbstring`, and reload.

## 5. Add the cron job (cPanel → Cron Jobs)

The installer shows the exact command. Choose **Once Per Five Minutes** and paste it. It looks like:

```
/usr/local/bin/php /home/cpaneluser/public_html/cron/sync.php >/dev/null 2>&1
```

It updates order statuses, refunds canceled/partial orders and tracks refills. Without it, orders stay "Processing".

## 6. Import your services

`https://yourdomain.com/admin/` → **Providers** → (paste the MoreThanPanel key if you skipped it) → **Import services** →
tick services, choose your markup %, import. Your price = MoreThanPanel's rate + markup.

## 7. Admin → Settings

Everything you'll want to change later is here, no file editing:

- **Payments:** your USDT BEP-20 (BSC) and/or TRC-20 (TRON) address, bank details, minimum deposit.
  USDT deposits are credited **automatically**: the customer gets an exact amount (e.g. 25.37 USDT), and the panel
  confirms the transfer on the blockchain (TRC-20 is found by itself; for BEP-20 the customer pastes the transaction hash).
  A wrong amount or a transfer that stays unconfirmed is flagged **Review** in Admin → Payments. Bank transfers are approved by you.
- **Email:** sender address and SMTP. Create the mailbox in cPanel → Email Accounts, then for SMTP use
  host `mail.yourdomain.com`, port `465`, SSL, and that mailbox's address + password. Press **Send test email** to check.
- **Support contacts:** email, WhatsApp, Telegram and a note, shown on the customer Support page
- **General:** site name, open/close sign-ups, affiliate commission

Customers open tickets from **Support**; you answer them in **Admin → Tickets** (they get an email when you reply).

## Test before opening to customers

1. Register a customer account, submit a small deposit, approve it in Admin → Payments.
2. Place the cheapest possible order. Admin → Orders → **Log** should show a MoreThanPanel order number.
3. Within ~10 minutes the status should update by itself (proves the cron works).
4. Try "Forgot password" and check the email arrives.

## Daily running

- **Admin → Payments:** approve deposits once you've seen the money arrive (nothing is credited automatically).
- Keep your MoreThanPanel balance topped up. If it runs out, orders are refused and customers are refunded automatically.
- Download a database backup now and then (cPanel → Backup).
- Errors are logged in `public_html/error_log`.

## Troubleshooting

| Problem | Fix |
|---|---|
| Browser says the site can't be reached / redirect loop | SSL isn't active yet (step 2). Wait for AutoSSL, then retry |
| Installer: "Could not connect to the database" | Use the full names with the `cpaneluser_` prefix, and check the user was added to the database with ALL PRIVILEGES |
| Installer: "already has Nora Panel tables" | Use an empty database, or drop the tables in phpMyAdmin |
| 500 error on every page | PHP version below 8.1 (cPanel → Select PHP Version) |
| Links go to the wrong address | Fix `SITE_URL` in `app/config.php` (must match exactly, including `www.` if you use it) |
| Orders stay "Processing" | Cron job missing or wrong path (step 5) |
| Provider test says "Invalid API key" | Re-copy the key from MoreThanPanel's API page |
| Reset emails don't arrive | Check spam; create the sender mailbox; cPanel → Email Deliverability should be green |

## Upgrading an install made before September 2026

If you installed an earlier version (without order types/refills/password reset), import `database/upgrade-3.1.sql`
in phpMyAdmin once, upload the new files over the old ones (keep your `app/config.php`), and add
`define('MAIL_FROM', 'no-reply@yourdomain.com');` to `app/config.php`. Don't run `install.php` on an existing install.

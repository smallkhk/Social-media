# Nora Panel

An SMM reseller panel in plain PHP + MySQL, built to run on ordinary cPanel shared hosting (e.g. Namecheap).
Customers top up a balance and order social media services; orders are forwarded to your provider
(MoreThanPanel by default) through the standard SMM API v2.

**Setup guide: [DEPLOY-NAMECHEAP.md](DEPLOY-NAMECHEAP.md)**

## Features

- **Customers**: sign up, browse services, place orders with a live price, order history, deposit requests
  (crypto / bank transfer), balance history, API key, affiliate link.
- **Providers**: any number of SMM API v2 providers (MoreThanPanel, Crescitaly, …). One-click service import with markup,
  per-service backup provider (failover), connection test, balance tracking.
- **Automatic sync** (cron, every 5 min): order status, start count and remains; full refund on *Canceled*,
  proportional refund on *Partial*; affiliate commission on finished orders.
- **Admin**: dashboard (revenue, cost, profit, per-provider and top services), orders (check, complete, refund, provider log),
  deposit approval, users (balance adjust, ban, password reset), services (edit, bulk markup), providers.
- **Reseller API** for your customers at `https://yourdomain.com/api/v2` (`services`, `add`, `status`, `balance`),
  compatible with other SMM panels.

## Security

PDO prepared statements everywhere, CSRF tokens on every form, escaped output, bcrypt passwords, session fixation
protection, login rate limiting, atomic balance deduction (no double spending), idempotent refunds and deposit approval,
secrets kept in `app/` which is blocked from the web.

## Layout

```
database/install.sql     tables (import once with phpMyAdmin)
public_html/             upload the contents of this folder to your cPanel public_html
  app/                   config, database helpers, provider client, order logic (not web-accessible)
  admin/                 admin area (first visit creates the admin account)
  api/v2.php             customer API
  cron/sync.php          cron job
```

Requires PHP 8.1+ with pdo_mysql, curl and mbstring, and MySQL 5.7+ / MariaDB 10.3+.

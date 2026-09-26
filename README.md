# Nora Panel

An SMM reseller panel in plain PHP + MySQL, built to run on ordinary cPanel shared hosting (e.g. Namecheap).
Customers top up a balance and order social media services; orders are forwarded to your provider
(MoreThanPanel by default) through the standard SMM API v2.

**Setup guide: [DEPLOY-NAMECHEAP.md](DEPLOY-NAMECHEAP.md)**: upload one zip, open `install.php`, fill in one form.

## Features

- **Customers**: sign up, forgot-password email, browse services, place orders with a live price, order history,
  refill button for services with a refill guarantee, automatic USDT deposits (BEP-20 / TRC-20, confirmed on-chain),
  bank transfer deposits, support tickets, balance history,
  API key, affiliate link.
- **All SMM order types** except Subscriptions: Default (with optional drip-feed), Package, SEO, Custom Comments,
  Custom Comments Package, Mentions (+ with Hashtags, Custom List, Hashtag), Comment Likes, Comment Replies, Poll,
  Invites from Groups.
- **Providers**: any number of SMM API v2 providers (MoreThanPanel, Crescitaly, …). One-click service import with markup,
  per-service backup provider (failover), connection test, balance tracking.
- **Automatic sync** (cron, every 5 min): order status, start count and remains; full refund on *Canceled*,
  proportional refund on *Partial*; refill status; affiliate commission on finished orders.
- **Admin**: dashboard (revenue, cost, profit, per-provider and top services), orders (check, complete, refund, provider log),
  deposit approval, users (balance adjust, ban, password reset), services (edit, bulk markup), providers.
- **Reseller API** for your customers at `https://yourdomain.com/api/v2`,
  compatible with other SMM panels (`services`, `add` with every order type and drip-feed, `status`, `refill`,
  `refill_status`, `balance`).

## Security

PDO prepared statements everywhere, CSRF tokens on every form, escaped output, bcrypt passwords, session fixation
protection, login rate limiting, atomic balance deduction (no double spending), idempotent refunds and deposit approval,
secrets kept in `app/` which is blocked from the web.

## Layout

```
database/upgrade-3.1.sql only for installs made before order types/refill/password reset
public_html/             upload the contents of this folder to your cPanel public_html
  install.php            web installer (creates tables, config and admin; locks itself afterwards)
  app/install.sql        database schema used by the installer
  app/                   config, database helpers, provider client, order logic (not web-accessible)
  admin/                 admin area (first visit creates the admin account)
  api/v2.php             customer API
  cron/sync.php          cron job
```

Requires PHP 8.1+ with pdo_mysql, curl and mbstring, and MySQL 5.7+ / MariaDB 10.3+.

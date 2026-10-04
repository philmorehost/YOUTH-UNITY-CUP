# Youth Unity Cup

A vanilla PHP platform for the Youth Unity Cup tournament website. The existing public-facing site and Elementor templates remain in place; clean PHP routes add the four-stage setup wizard, protected administration, MySQL-backed teams/venues/fixtures/results/registrations, a configurable official merchandise shop with PayHub checkout, site and security settings, password recovery, audit history, and an SMTP notification outbox.

## Installer stages

1. **Welcome & system checks** — checks PHP 8.1+, required extensions, and protected folder permissions; verifies the submitted project key server-side against the PMH License Manager API over HTTPS.
2. **Database & schema** — connects to an existing MySQL database and creates the tables with static, non-destructive `CREATE TABLE IF NOT EXISTS` statements. It seeds the ten venue-zone names already in the site; team names and match schedules remain admin-managed rather than being invented. Application queries use PDO prepared statements.
3. **Admin & email** — creates the first super-admin with PHP password hashing and optionally configures SMTP delivery.
4. **Completion** — provides the sign-in route and a first-login checklist.

The verification step is intentionally transparent: the installer labels the verification service, links to its API documentation, and explains that the submitted key and current domain are sent from the server. No license key is bundled in the repository or shipped to browser-side JavaScript. Runtime configuration is written as readable PHP in `config/local.php` with restrictive file permissions; it is not encoded or disguised. Keep `config/` and `storage/` outside the public document root.

## Requirements

- PHP 8.1 or later
- MySQL 8.0+ or MariaDB 10.5+
- PHP extensions: `pdo`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`, and `session`
- HTTPS for production
- `config/` and `storage/` writable by the PHP process, preferably on a filesystem accessible only to the application account

The hosting provider should create an empty database and grant the installer/migration account permission to create and alter tables, indexes, and foreign keys. The database name may contain letters, numbers, and underscores.

## Run locally

From the repository root, point PHP's built-in web server at the public-only document root:

```sh
php -S 0.0.0.0:8080 -t public
```

Open `http://localhost:8080/`. The first request redirects to `/install`. License verification requires a valid key assigned to the host name used for setup and server-side cURL access to `https://manager.pmhserver.name.ng/api.php`.

## Production deployment

1. Configure the web-server document root as this repository's `public/` directory. Do not expose `app/`, `config/`, `database/`, `storage/`, `views/`, or `bin/` through the web server.
2. Enable HTTPS and the Apache rewrite module if deploying with the supplied `public/.htaccess`.
3. Create an empty MySQL/MariaDB database and a least-privilege database account.
4. Visit `/install`, complete each stage, and sign in at `/admin/login`.
5. For an already-installed site upgrading from the original installer schema, back up the database and run `php /absolute/path/to/YOUTH-UNITY-CUP/bin/migrate-schema.php` once with the configured database account before using the new tournament or shop routes. The migration uses non-destructive `CREATE TABLE IF NOT EXISTS` statements, adds missing transaction recipient/provider columns and the unique provider-reference index, creates the shop catalog/order tables, and adds order verification and inventory-reservation fields when absent.
6. Configure the PayHub secret and webhook/return URLs as described in **PayHub shop setup** below before opening `/shop` for real orders.
7. Confirm SMTP delivery from the dashboard's **Send a test notification** action. Without SMTP, mail remains in the database outbox and is not silently discarded.
8. Schedule the outbox worker (for example once per minute), running as the same OS account that serves PHP, to retry temporary SMTP failures:

   ```sh
   php /absolute/path/to/YOUTH-UNITY-CUP/bin/send-notifications.php
   ```

The worker processes up to 50 queued notifications per run. Each message is persisted before delivery, retries use backoff, and a message is marked failed after five attempts. SMTP settings and payment secrets are stored in `config/local.php`; restrict that file to the application user and back it up securely.

## PayHub shop setup

1. Create products, set NGN prices, and enter available stock under **Admin → Shop products**. Product names, SKU, unit price, and quantity are revalidated server-side; order lines retain price/name snapshots.
2. In **Admin → Settings → PayHub shop payments**, enter the PayHub secret key from the merchant dashboard. It is written only to the protected `config/local.php`, is never rendered into a page or JavaScript, and a blank field keeps the existing value. Use the remove-key checkbox to disable new checkout.
3. In the PayHub merchant dashboard, register the HTTPS webhook endpoint `https://<your-domain>/payments/payhub/webhook`. PayHub signs the raw JSON body with HMAC-SHA256 in `X-Payhub-Signature`; the application validates this before asking PayHub's verify endpoint for the authoritative transaction record.
4. If the PayHub dashboard supports a browser return/callback URL, set it to `https://<your-domain>/shop/return`. The page accepts the PayHub `ref`/`reference` query value or the current browser's recent order session. Customers can also return to the shop and use **Check your recent order status**.
5. Orders reserve stock for 20 minutes. Expired or administratively cancelled unpaid orders release the reservation. An order is marked paid only after server-side verification confirms the exact expected kobo amount and `NGN` currency; fulfillment remains an administrator action. Late payments, mismatches, and payments after a reservation was closed are held in `paid_needs_review` for an administrator. For a late payment whose stock was released, the order cannot be approved for processing until the required stock can be reserved again.

The integration uses `https://merchant.payhub.com.ng/api/transaction/initialize` and `/transaction/verify/:reference`. The PayHub secret remains server-side. No PayHub credentials or MySQL service are available in the development workspace, so live payment and real webhook delivery have not been exercised here; configure and test with your PayHub account before accepting real orders.

## What is included

- Clean-path front controller and a small PSR-4-style autoloader
- `/admin/teams`, `/admin/venues`, `/admin/fixtures`, `/admin/products`, and `/admin/orders` management with public `/teams`, `/fixtures`, `/results`, `/venues`, and `/shop` pages
- Public `/registration` application form, administrator review workflow, and status emails
- Admin settings for site title/contact, time zone, SMTP delivery, server-side PayHub secret key, login-attempt thresholds, and IP-block duration; searchable, paginated `/admin/activity` audit history
- CSRF-protected, four-stage installer
- Version/extension/permission checks
- MySQL schema for admins, login history, IP throttling, audit events, notifications, transactions, PayHub provider references, password-reset tokens, settings, shop products/orders/order-item snapshots, teams, venues, fixtures, and public registrations
- Argon/Bcrypt-compatible PHP password hashing (`PASSWORD_DEFAULT`)
- Session ID rotation, secure/HTTP-only/SameSite cookies, configurable temporary IP throttling, and an admin page to review or release active blocks
- Successful login and security-threshold email alerts; audit/history records for all sign-in outcomes; one-time email password resets
- Admin workflows to manage product SKUs, NGN prices and available stock; review shop orders, flag payment mismatches, and progress verified orders through fulfillment
- Public `/shop` catalog and checkout; PayHub server-side initialization, authoritative status/amount/currency verification, signed webhook reconciliation, 20-minute inventory reservations, and order-status return page
- Admin workflows to record non-gateway transaction events and queue notifications; public registration confirmation, review-status, and verified shop-payment notifications
- SMTP over STARTTLS, implicit TLS, or an explicitly selected unencrypted transport; TLS peer checks are enabled
- Public website fallback served from the existing `youth-unity-cup-site.html`

## Configuration and security notes

- Never commit `config/local.php`, `storage/install.pending.json`, SMTP credentials, the PayHub secret key, database credentials, or project/license keys. These runtime paths are ignored by Git.
- Use a strong, unique administrator password of at least 12 characters. The installer never displays the password after account creation.
- The license verification API is called server-side, with a short timeout and TLS certificate verification. API failures are shown to the installer rather than concealed.
- SMTP secrets are stored in the protected local configuration file. Prefer an SMTP account limited to application mail and use STARTTLS or SSL/TLS.
- A failed SMTP connection leaves messages in `notification_outbox`; the dashboard shows recent delivery status and the CLI worker can retry them.
- Login attempt history includes the source IP address. Configure trusted reverse proxies to set `REMOTE_ADDR` correctly; the application deliberately does not trust arbitrary `X-Forwarded-For` values.
- The existing transaction ledger still supports audited manual records for non-shop activity. Shop transactions are PayHub-managed and cannot be marked paid from the admin ledger; order fulfillment is handled separately.
- Review privacy and retention requirements before storing participant data or using the transaction table in production.

## Validation before release

Run PHP's syntax checker on every PHP file, `php tests/payhub-security.php`, and `php tests/shop-template-smoke.php`; complete the installer against a disposable MySQL database, verify both a successful SMTP test and the queued/retry path, and exercise PayHub initialization, a signed webhook, and the payment-return verification before production deployment. Never test schema changes against the live tournament database.

# Youth Unity Cup

A vanilla PHP, MVC-style platform for the Youth Unity Cup tournament website. Clean routes serve a sport-inspired public site with teams, group standings, squad profiles, fixtures, results, venues and registration, plus protected administration, a configurable official shop with PayHub inline checkout, managed homepage hero media, site/security settings, password recovery, audit history and an SMTP notification outbox.

## Installer stages

1. **Welcome & system checks** — checks PHP 8.1+, required extensions, and protected folder permissions; verifies the submitted project key server-side against the PMH License Manager API over HTTPS.
2. **Database & schema** — connects to an existing MySQL database and creates the tables with static, non-destructive `CREATE TABLE IF NOT EXISTS` statements. Installed sites also compare their tables against the canonical schema at request startup and auto-create missing declared tables without dropping existing data. It seeds the ten venue-zone names already in the site; live team names and schedules remain admin-managed. A separate, reversible Demo mode provides a clearly labelled 16-team sample tournament. Application queries use PDO prepared statements.
3. **Admin & email** — creates the first super-admin with PHP password hashing and optionally configures SMTP delivery.
4. **Completion** — provides the sign-in route and a first-login checklist.

The verification step is intentionally transparent: the installer labels the verification service, links to its API documentation, and explains that the submitted key and current domain are sent from the server. No license key is bundled in the repository or shipped to browser-side JavaScript. Runtime configuration is written as readable PHP in `config/local.php` with restrictive file permissions; it is not encoded or disguised. Keep `config/` and `storage/` outside the public document root.

## Requirements

- PHP 8.1 or later
- MySQL 8.0+ or MariaDB 10.5+
- PHP extensions: `pdo`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `json`, `session`, `fileinfo`, and GD with WebP support
- For hero and product-image uploads, set `upload_max_filesize` to at least `26M`, `post_max_size` above that (for example `28M`), and a memory limit suitable for GD image processing
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
5. For an already-installed site, missing tables listed in `database/schema.mysql.sql` are created automatically on the next application request. The runtime database account must have permission to create tables for this repair to work. Back up the database and run `php /absolute/path/to/YOUTH-UNITY-CUP/bin/migrate-schema.php` after deploying upgrades that add or change existing columns or indexes—including the product-image field—before using those features. The additive CLI updater does not drop existing live data.
6. Configure the PayHub public and secret keys and webhook endpoint as described in **PayHub shop setup** below before opening `/shop` for real orders.
7. Confirm SMTP delivery from the dashboard's **Send a test notification** action. Without SMTP, mail remains in the database outbox and is not silently discarded.
8. Schedule the outbox worker (for example once per minute), running as the same OS account that serves PHP, to retry temporary SMTP failures:

   ```sh
   php /absolute/path/to/YOUTH-UNITY-CUP/bin/send-notifications.php
   ```

The worker processes up to 50 queued notifications per run. Each message is persisted before delivery, retries use backoff, and a message is marked failed after five attempts. SMTP settings and payment secrets are stored in `config/local.php`; restrict that file to the application user and back it up securely.

System email is sent as a multipart plain-text/HTML message. The responsive HTML notification design uses Youth Unity Cup navy-and-lime matchday branding; the plain-text alternative remains available to email clients that do not display HTML.

## PayHub shop setup

1. Create products, set NGN prices, enter available stock, and optionally upload a product image under **Admin → Shop products**. Product images may be JPEG, PNG, or WebP up to 8 MB; the server re-encodes them as WebP in protected storage. Product names, SKU, unit price, and quantity are revalidated server-side; order lines retain price/name snapshots.
2. In **Admin → Settings → PayHub shop payments**, enter both the PayHub **public** key and **secret** key from the merchant dashboard. The secret is saved only in protected `config/local.php` and never reaches a browser; the public key is delivered only to the inline-checkout page. Blank fields keep saved values; use the separate remove-key controls to clear either key. Both keys are required to open new inline checkouts.
3. Register the HTTPS webhook endpoint `https://<your-domain>/payments/payhub/webhook` in the PayHub merchant dashboard. PayHub specifies `X-Payhub-Signature` as the HMAC-SHA256 hex digest of the exact raw JSON request body using the secret key. The endpoint caps payloads, verifies the raw-body signature before JSON parsing, maps only the signed reference to a local order, and then calls PayHub's verify API. The webhook body is a notification, not payment proof.
4. The shop loads `https://merchant.payhub.com.ng/inline.js` and opens `PayhubPop.setup` with the server-created amount in kobo, customer email, and a cryptographically random order-bound reference. The browser callback is used only to return the customer to `/shop/return`; it is never trusted as evidence of payment. The server verifies the reference, successful status, exact expected amount in kobo, and `NGN` currency with PayHub's authoritative verification endpoint before marking an order paid. Keep the HTTPS webhook enabled for customers who close the browser before returning.
5. Orders reserve stock for 20 minutes. Expired or administratively cancelled unpaid orders release the reservation. Fulfillment remains an administrator action. Late payments, mismatches, and payments after a reservation was closed are held in `paid_needs_review`; if stock was released, an administrator must reserve stock again before approving processing.

The integration uses PayHub's inline checkout and `https://merchant.payhub.com.ng/api/transaction/verify/:reference`; it does not use a browser redirect as proof of payment. No PayHub credentials or MySQL service are available in the development workspace, so live payment and real webhook delivery have not been exercised here. Configure and test with your merchant account before accepting real orders.

The `/admin/orders` page catches shop-database query failures, logs the PDO SQLSTATE, and shows a one-time migration hint when tables or columns appear to be missing. If the page still fails after migration, inspect the configured PHP error log; the live database error must be diagnosed from that SQLSTATE and database state.

## Homepage hero management

Use **Admin → Homepage hero** to choose the default sports artwork, upload an image, add a YouTube link, or upload a short MP4/WebM loop. The settings reuse the existing `system_settings` table; uploads are validated, stored under protected `storage/hero/` (outside the public document root), and served through a narrow allow-listed route with byte-range support for video playback. Images are decoded, stripped of metadata, resized when oversized, and re-encoded as WebP. Uploads are limited to 10 MB for images and 25 MB for videos. MP4 files must have fast-start metadata (`moov` atom before media data); use web-optimized, muted 1080p clips for quick mobile starts. Uploaded videos autoplay muted, loop, and play inline. YouTube links are restricted to supported YouTube hosts and use a privacy-enhanced looping embed with autoplay muted.

## Live stream and Watch live

Use **Admin → Watch live** to enable or disable the landing-page button, set a broadcast title, and save an HTTPS YouTube or TikTok LIVE link. The settings reuse the existing `system_settings` table. Public/unlisted YouTube video-ID and channel-ID links are embedded responsively and start muted with autoplay requested; viewers can unmute in the player. Browser autoplay policies and YouTube embed permissions still apply. YouTube handle links can open externally.

TikTok does not provide an official embeddable player for TikTok LIVE, so TikTok broadcasts appear as a secure external Watch on TikTok link instead of an in-page autoplay player. The admin form explains this behavior. The landing page only shows Watch live while a valid broadcast is enabled.

## Product images

Product images can be uploaded while the site is in Production under **Admin → Shop products**. JPEG, PNG, and WebP uploads are re-encoded, stripped of metadata, stored under protected `storage/shop-products/`, and served through a strict filename allow-list. On an existing installation, back up the configured database and run `php /absolute/path/to/YOUTH-UNITY-CUP/bin/migrate-schema.php` once before the first product-image upload.

## Demo and Production mode

The Admin dashboard includes an explicit **Switch to Demo** / **Switch to Production** control. Switching to Demo takes a transactional database snapshot of the live `teams`, `team_players`, `venues`, and `fixtures` rows, then installs 16 sample teams across Groups A–D, 11 sample player profiles per team, eight sample community venues, and 24 round-robin group fixtures (including sample scores). The public shop and admin product manager display the same preview-only collection of football gear with illustrative prices, stock, and local illustrations. These sample products are held in memory and are read-only; the live product catalog and stock are not changed. Public pages are visibly labelled Demo; live registrations, PayHub checkout, and non-tournament admin writes are paused.

Switching back to Production restores the exact saved tournament rows and IDs from the snapshot in one transaction, then removes the snapshot only after the restore succeeds. Registration, transaction/payment, shop, admin-account, login, and security-history tables are never cleared by this switch. Keep a normal database backup before deploying column/index migrations or enabling the mode control. Missing tables are self-created from the canonical schema; use `bin/migrate-schema.php` for additive changes to existing columns or indexes.

## What is included

- Clean-path front controller and a small PSR-4-style autoloader
- `/admin/teams`, `/admin/players`, `/admin/venues`, `/admin/fixtures`, `/admin/registrations`, `/admin/transactions`, `/admin/products`, `/admin/orders`, `/admin/homepage-hero`, `/admin/live-stream`, and `/admin/security` management with public `/teams`, `/team?id=<id>`, `/fixtures`, `/results`, `/venues`, and `/shop` pages
- Four-group standings, match-centred fixtures/results, and team profile pages with squad numbers, player details, secure HTTPS headshot links, and local illustrated portrait fallbacks
- Reversible Admin → Demo/Production control: atomically snapshots and restores only tournament content (teams, player rosters, venues, fixtures). Registrations, payment/shop records, admin accounts and security history are not deleted; live registration and checkout are paused during Demo mode.
- Instant client-side admin search across teams, venues, fixtures/scores, registrations, transactions, shop products/orders, and blocked IPs; record controls are CSRF-protected and critical deletes require confirmation
- Safe management rules: PayHub payment data stays server-verified; admin-created shop orders use the same atomic stock reservation and inline checkout path as public orders, while later edits are limited to customer/fulfillment details; closed orders and manual transactions are archived with their audit/payment history retained
- Public `/registration` application form, administrator review workflow, and status emails
- Admin settings for site title/contact, time zone, SMTP delivery, server-side PayHub secret/public keys, login-attempt thresholds, and IP-block duration; searchable, paginated `/admin/activity` audit history
- CSRF-protected, four-stage installer
- Version/extension/permission checks, including `fileinfo` and GD WebP support for secure hero and shop-product image uploads
- Canonical MySQL schema for admins, login history, IP throttling, audit events, notifications, transactions, PayHub provider references, password-reset tokens, settings, shop products/orders/order-item snapshots, teams/players, venues, fixtures, and public registrations; missing declared tables are auto-created without dropping existing tables
- Argon/Bcrypt-compatible PHP password hashing (`PASSWORD_DEFAULT`)
- Session ID rotation, secure/HTTP-only/SameSite cookies, configurable temporary IP throttling, and an admin page to review or release active blocks
- Successful login and security-threshold email alerts; audit/history records for all sign-in outcomes; one-time email password resets
- Admin workflows to manage product SKUs, NGN prices, protected product-image uploads and available stock; review shop orders, flag payment mismatches, and progress verified orders through fulfillment
- Public `/shop` catalog and PayHub inline checkout; authoritative server-side status/reference/amount/currency verification, raw-body HMAC-signed webhook reconciliation, 20-minute inventory reservations, and order-status return page
- Admin workflows to record non-gateway transaction events and queue notifications; public registration confirmation, review-status, and verified shop-payment notifications
- SMTP over STARTTLS, implicit TLS, or an explicitly selected unencrypted transport; TLS peer checks are enabled
- Responsive sports-notification emails with multipart plain-text and HTML alternatives, matchday branding, and safe content escaping
- Responsive Youth Unity Cup landing page with admin-managed hero media, live YouTube autoplay player or TikTok LIVE link, and an automatically updating local-time year display

## Configuration and security notes

- Never commit `config/local.php`, `storage/install.pending.json`, SMTP credentials, the PayHub secret key, database credentials, uploaded hero media, or project/license keys. These runtime paths are ignored by Git; PayHub's public key is not a secret but is stored in the local configuration for checkout setup.
- Use a strong, unique administrator password of at least 12 characters. The installer never displays the password after account creation.
- The license verification API is called server-side, with a short timeout and TLS certificate verification. API failures are shown to the installer rather than concealed.
- SMTP secrets are stored in the protected local configuration file. Prefer an SMTP account limited to application mail and use STARTTLS or SSL/TLS.
- A failed SMTP connection leaves messages in `notification_outbox`; the dashboard shows recent delivery status and the CLI worker can retry them.
- Login attempt history includes the source IP address. Configure trusted reverse proxies to set `REMOTE_ADDR` correctly; the application deliberately does not trust arbitrary `X-Forwarded-For` values.
- The existing transaction ledger still supports audited manual records for non-shop activity. Shop transactions are PayHub-managed and cannot be marked paid from the admin ledger; order fulfillment is handled separately.
- Review privacy and retention requirements before storing participant data or using the transaction table in production.

## Validation before release

Run PHP's syntax checker on every PHP file, `php tests/schema-self-heal-smoke.php`, `php tests/payhub-security.php`, `php tests/hero-media-security.php`, `php tests/notification-email-smoke.php`, `php tests/live-stream-smoke.php`, `php tests/product-images-smoke.php`, `php tests/shop-template-smoke.php`, `php tests/admin-management-smoke.php`, and `php tests/public-tournament-smoke.php`; complete the installer against a disposable MySQL database, verify Demo → Production snapshot restoration on test data, test the responsive HTML email through SMTP and the queued/retry path, verify muted YouTube autoplay with an enabled public embed and the TikTok external-watch fallback, and exercise PayHub inline checkout, a signed webhook, and server-side payment-return verification before production deployment. Test hero and product-image uploads, image replacement/retention, and browser rendering on the production-like web server. Never test schema changes or mode switching against the live tournament database.

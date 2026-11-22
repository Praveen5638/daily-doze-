# Deployment & Handover Guide

Target: shared hosting / cPanel on Linux + Apache 2.4, PHP 8.2+, MySQL 8.0+, HTTPS.
This guide assumes the project is uploaded as `~/daily-dose-export` with the domain document root pointed at `~/daily-dose-export/public`.

---

## 1. Pre-flight checklist

| Item | Requirement | Checked |
|---|---|---|
| Domain | `dailydoseexport.com` pointing to the hosting account | ☐ |
| PHP | 8.2+ (8.3 recommended), extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `json`, `session` | ☐ |
| MySQL | 8.0+, database + dedicated user created | ☐ |
| SSL | Valid certificate issued (AutoSSL / Let's Encrypt) | ☐ |
| Email | Mailbox or SMTP relay for order/enquiry notifications | ☐ |
| Secrets | `.env` prepared locally, never committed to Git | ☐ |
| Content | Prices, stock, address, PIN, GSTIN/FSSAI (if applicable), legal pages reviewed | ☐ |

## 2. Upload the application

1. Upload the project **outside** the web root where possible, e.g. `/home/<cpanel-user>/daily-dose-export/`.
2. In cPanel → **Domains** (or **Addon Domains**), set the document root of `dailydoseexport.com` to:

   ```
   /home/<cpanel-user>/daily-dose-export/public
   ```

   This is the preferred layout: `app/`, `database/`, `storage/`, `docs/` and `.env` sit one level above the web root and are unreachable from the browser.

3. If the host cannot change the document root, point it at the project root instead. The included root `.htaccess` **denies** `app/`, `database/`, `storage/`, `docs/`, `vendor/`, `.env`, `.env.example`, `composer.*`, `README.md` and `.gitignore`, and rewrites requests into `public/`. This is a fallback, not the preferred setup — verify it after upload:

   ```
   https://dailydoseexport.com/.env            → 403
   https://dailydoseexport.com/app/config/config.php → 403
   https://dailydoseexport.com/storage/logs/app.log  → 403
   ```

4. `public/.htaccess` provides:
   - clean URLs (`/products/{slug}`, `/category/{slug}`, `/pages/{slug}`, `/sitemap.xml`),
   - `ErrorDocument 404 /404.php`,
   - security headers (`nosniff`, `SAMEORIGIN`, `Referrer-Policy`),
   - denial of dotfiles and `.bak/.sql/.log/.ini/.env` files,
   - 30-day caching for images and 7-day caching for CSS/JS.

   Ensure `mod_rewrite`, `mod_headers` and `mod_expires` are enabled (`AllowOverride All`).

## 3. Create `.env`

Create `.env` in the project root:

```ini
APP_ENV=production
APP_DEBUG=0
APP_URL=https://dailydoseexport.com
APP_TIMEZONE=Asia/Kolkata

DB_HOST=localhost
DB_PORT=3306
DB_NAME=<cpanel_db_name>
DB_USER=<cpanel_db_user>
DB_PASS=<strong-password>

MAIL_TRANSPORT=smtp
MAIL_HOST=mail.dailydoseexport.com
MAIL_PORT=587
MAIL_USER=care@dailydoseexport.com
MAIL_PASS=<smtp-password>
MAIL_SECURE=tls
MAIL_FROM=care@dailydoseexport.com
MAIL_FROM_NAME=Daily Dose Export

RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
```

Rules:
- `APP_DEBUG=0` in production — debug output must never be visible publicly.
- Permissions: `chmod 600 .env` (owner-readable only).
- Keep `MAIL_TRANSPORT=log` until SMTP is verified; mail is then written to `storage/logs/mail.log` and nothing is sent externally.
- Leave Razorpay keys blank to keep online payment disabled.

## 4. Import the database

Via phpMyAdmin or SSH:

```bash
mysql -u <db_user> -p <db_name> < database/schema.sql
mysql -u <db_user> -p <db_name> < database/seed.sql
```

- `schema.sql` — 35 tables.
- `seed.sql` — roles/permissions, default admin, website settings, CMS pages (legal pages as drafts), product catalogue **with `NULL` prices and zero stock**.
- `demo-prices.sql` — **local QA only**. Do **not** import sample prices, stock or reviews into production; enter real values in the admin panel.

Collation must be `utf8mb4_unicode_ci` for Hindi search aliases (e.g. हल्दी).

## 5. First login and hardening

1. Open `https://dailydoseexport.com/admin/login.php`.
2. Log in with `admin@dailydoseexport.com` / `ChangeMe@DDE2026` (see `database/seed.sql`).
3. **Immediately** change the password and, ideally, the admin email: Admin → **Staff**.
4. Create individual staff accounts per role instead of sharing the Super Admin login:

   | Role | Intended for | Permissions |
   |---|---|---|
   | Super Admin | Owner / technical lead | Everything incl. staff & settings |
   | Admin | Day-to-day manager | All operational areas + settings, no staff |
   | Order Manager | Dispatch desk | Orders, customers, enquiries, reviews, reports |
   | Inventory Manager | Warehouse | Products, categories, inventory, reports |
   | Content Manager | Marketing | CMS/pages/banners and review moderation |

5. Import the real catalogue: products, variants (price, MRP, SKU, pack size), stock and images. Uploaded images must be JPG/PNG/WebP, ≤ 5 MB; files are stored in `public/uploads/<area>/YYYY/MM/`.

## 6. Writable paths

| Path | Purpose | Permission |
|---|---|---|
| `storage/` | logs, backups, invoices | `755` dirs / `644` files (PHP user must write) |
| `storage/logs/` | `app.log`, `php-error.log`, `mail.log` | writable |
| `public/uploads/` | admin-uploaded images | writable |

Do not make `storage/` web-accessible; it contains `Require all denied`.

## 7. HTTPS, headers and sessions

- Force HTTPS at the host level (cPanel → Domains → Force HTTPS Redirect) or via `mod_rewrite`.
- The app sets `Strict-Transport-Security` automatically when the request is HTTPS (`IS_HTTPS`), plus CSP, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and `X-Content-Type-Options`.
- Session cookies: `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, strict mode, ID rotation on login/logout.
- `APP_URL` **must** be the final HTTPS origin. It drives canonicals, sitemap URLs, emails and links.

## 8. Email

`MAIL_TRANSPORT` options:

| Value | Behaviour |
|---|---|
| `log` | Writes the full email to `storage/logs/mail.log`; sends nothing. Use for staging. |
| `mail` | PHP `mail()` — depends on server configuration; least reliable. |
| `smtp` | SMTP via PHPMailer **if `vendor/autoload.php` provides it** (composer-installed). If PHPMailer is missing or sending fails, the message is written to `storage/logs/mail.log` and nothing is sent externally. |

For SMTP:
1. Upload PHPMailer into `vendor/` (e.g. `composer require phpmailer/phpmailer --no-dev` locally, upload `vendor/`), or use a server SMB relay — but never place credentials in public files.
2. Set `MAIL_HOST/PORT/USER/PASS/SECURE` in `.env`. Port `587` + `tls` or `465` + `ssl`.
3. Add SPF, DKIM and DMARC DNS records for the sending domain to protect deliverability.
4. Set Admin → Settings → **System → Admin notification email** for new-order/enquiry alerts (falls back to contact email / SMTP from-address).
5. Send a test order and confirm delivery; check `storage/logs/app.log` for `SMTP send failed` entries.

Customer-facing emails cover order placed/paid/status changes. The admin gets in-app notifications for new orders, payments received, refunds due, new enquiries/messages, reviews awaiting moderation and account-deletion requests; new orders, enquiries and contact messages also send an email to the admin notification address. Low stock is surfaced on the dashboard and Inventory screen rather than as a notification.

## 9. Payments (Razorpay)

Online payment is **off by default** (`razorpay_enabled=0`, `cod_enabled=0` in seed). To enable:

1. Complete Razorpay KYC and obtain keys from the Razorpay dashboard.
2. Put the **Key Secret** and **Webhook Secret** only in `.env`:

   ```ini
   RAZORPAY_KEY_ID=rzp_live_xxxxxxxx
   RAZORPAY_KEY_SECRET=<secret>
   RAZORPAY_WEBHOOK_SECRET=<webhook-secret>
   ```

3. In Admin → Settings → **Payments**, paste the **Key ID** (public `rzp_…`) and tick *Razorpay enabled* only after the webhook is live.
4. Configure the webhook in Razorpay → Settings → Webhooks:

   ```
   URL:    https://dailydoseexport.com/api/index.php?action=payment.webhook
   Events: payment.captured, order.paid
   Secret: same value as RAZORPAY_WEBHOOK_SECRET
   ```

   Signature is verified as `HMAC-SHA256(raw body, secret)` against `X-Razorpay-Signature`. Other events are acknowledged and ignored. Webhook handling is idempotent — the order is marked paid once, with the payment recorded in `payments`.

5. Test in Razorpay **test mode** first. A successful payment moves the order to `paid` and emails the customer; a failed payment leaves it `pending`/`unpaid`.
6. COD is toggled separately under Settings → **Commerce** (`COD enabled`, optional `COD fee`). Only enable it when the business is ready to fulfil COD orders.

Never paste secrets into CMS fields, product descriptions, source files or support messages. The admin UI never displays stored secrets.

## 10. Legal and business settings to complete

Admin → Settings:

- **Contact** — support phone (`+91 84299 33125`), WhatsApp (`918429933125`), public email, full address incl. PIN, map link, default WhatsApp message.
- **Commerce** — currency, tax %, flat shipping, free-shipping threshold, shipping note, COD toggle/fee.
- **Legal** — registered entity, GSTIN, FSSAI (only if verified; each is displayed only when filled).
- **General / Home / About** — announcement, hero copy, newsletter copy, mission/vision/values/story (currently seeded with approved business copy).
- **SEO** — default meta description; OG image.
- **Social** — Instagram/Facebook/YouTube/X URLs.
- **System** — admin notification email, reviews switch, maintenance mode.

Then Admin → **Content / Pages**: review each legal page (Privacy, Terms, Shipping, Returns, Cancellation, Cookie) and **publish** once legally approved. They are intentionally seeded as drafts.

## 11. Backups

| What | How | Frequency |
|---|---|---|
| Database | cPanel backup or `mysqldump --single-transaction` to `storage/backups/` | Daily |
| Uploads | `public/uploads/` archive | Daily/weekly |
| `.env` | Secure offline copy (never in the repo or web root backup set shared publicly) | On change |
| Code | Release tarball per deployment | Per release |

Restore drill: import the dump into a staging DB, copy uploads, point a staging `.env` at it, verify admin login and a test order. Backup **before** any upgrade.

## 12. Going live — smoke checklist

1. `https://dailydoseexport.com/` loads over HTTPS with no mixed content and no debug output.
2. `/shop.php` lists published products; filters, sort, pagination and search (try “haldi”, “हल्दी”, “khajoor”) work.
3. Product page: variants, add to cart, wishlist (logged in), JSON-LD present.
4. Guest checkout: COD (if enabled) and/or Razorpay test payment; order email received; stock decremented.
5. `/track-order.php` finds the order by number + email.
6. Account: register, login, addresses, wishlist, reviews, orders, deletion request flow.
7. Contact, Wholesale, Export enquiry forms submit and appear in Admin → Enquiries.
8. Admin: update order status, record COD payment, adjust stock, edit a product/CMS page — verify audit rows and emails.
9. `/sitemap.xml` returns valid XML with production URLs; `robots.txt` points to it; 404 page renders.
10. `https://dailydoseexport.com/admin/` requires login; direct access to `/.env`, `/app/…`, `/storage/…` returns 403.
11. `storage/logs/php-error.log` and `app.log` are empty of new production errors.
12. Submit sitemap in Google Search Console / Bing Webmaster; verify the domain property.

## 13. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Blank page / 500 | PHP version, missing extension, unreadable `.env` | Check `storage/logs/php-error.log`; run `php -l`; verify PHP 8.2+ and permissions |
| All pages 404 except home | `mod_rewrite`/`AllowOverride` disabled | Enable rewrite or set docroot to `public/` |
| Clean product URLs 404 | `public/.htaccess` missing/not honoured | Confirm the file uploaded; test `/product.php?slug=…` |
| “Could not connect to database” | Wrong `.env` credentials or DB host (cPanel uses `localhost`) | Fix `.env`, verify user grants |
| Login loops back to login | Cookie domain/HTTPS mismatch, `APP_URL` wrong | Set final HTTPS `APP_URL`; clear cookies |
| No emails | Transport misconfigured / PHPMailer absent | Read `mail.log` and `app.log`; fix SMTP; upload `vendor/` |
| Payment not confirmed | Webhook secret or URL wrong | Re-enter secret in `.env`; verify URL/events; check `app.log` |
| Uploads fail | `public/uploads/` not writable | `chmod -R 775 public/uploads` (owner = PHP user) |
| 503 “We are updating” | Maintenance mode on | Admin → Settings → System → disable |
| Hindi search misses | DB collation not `utf8mb4` | Recreate DB as `utf8mb4_unicode_ci`, re-import |

## 14. Updating the site

1. Back up DB and `public/uploads/`.
2. Upload changed files (never overwrite `.env` or `storage/`).
3. If the schema changed, apply the migration script for that release.
4. Clear the browser cache / CDN; verify the smoke checklist above.
5. Keep `storage/logs/` under observation for 24 hours.

---

Handover artefacts: this guide, [ADMIN-GUIDE.md](ADMIN-GUIDE.md), [QA-REPORT.md](QA-REPORT.md), [BLUEPRINT.md](BLUEPRINT.md), plus the `database/` scripts and `.env.example`.

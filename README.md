# Daily Dose Export — Website & Admin Platform

Production-oriented website and back-office for **Daily Dose Export** (dailydoseexport.com), Bhojubeer, Varanasi, Uttar Pradesh, India.

> **Pure From Farm To World.** Every Bite, A Promise of Health.

The platform covers the public storefront (products, cart, guest checkout, COD / optional Razorpay, order tracking, account area, wholesale and export enquiries) and a role-based admin panel (catalogue, inventory, orders, customers, enquiries, reviews, CMS and settings), with server-rendered SEO and a 35-table MySQL schema.

---

## 1. Stack

| Layer | Technology |
|---|---|
| Server | PHP 8.2+ (tested on PHP 8.3, NTS build), PDO MySQL |
| Database | MySQL 8.0+ (`utf8mb4`) |
| Web server | Apache 2.4 + `mod_rewrite`, `mod_headers`, `mod_expires` (cPanel-compatible) |
| Front end | Server-rendered HTML, hand-written CSS, vanilla ES6, no build step |
| Email | PHP `mail()` or SMTP (PHPMailer if installed in `vendor/`), or log transport |
| Payments | COD (configurable) and optional Razorpay with HMAC-verified webhooks |
| Optional | WhatsApp click-to-chat; admin charts are CSS-only (no chart library) |

No Node.js runtime is required in production.

## 2. Repository layout

```
daily-dose-export/
├─ app/
│  ├─ bootstrap.php          # single entry: config, helpers, session, security headers
│  ├─ config/config.php      # loads .env, defines constants
│  ├─ core/                  # Database, Auth, Order, Cart, Payment, Catalog, …
│  ├─ helpers/               # functions, icons, SEO/JSON-LD
│  └─ views/                 # layout (header/footer), partials, emails
├─ public/                   # ← WEB ROOT
│  ├─ *.php                  # storefront routes
│  ├─ admin/                 # admin panel (separate login/session guards)
│  ├─ api/index.php          # JSON API (cart, coupons, wishlist, Razorpay, webhook)
│  ├─ assets/                # css, js, images
│  ├─ uploads/               # admin-uploaded images (writable)
│  ├─ .htaccess              # clean URLs, security headers, caching
│  ├─ robots.txt, sitemap.php
│  └─ router.php             # dev-server router (local only)
├─ database/
│  ├─ schema.sql             # 35 tables
│  ├─ seed.sql               # roles, admin, settings, CMS pages, catalogue (no prices)
│  └─ demo-prices.sql        # DEMO ONLY — sample prices/stock for local testing
├─ docs/                     # BLUEPRINT, DEPLOYMENT, ADMIN-GUIDE, QA-REPORT
├─ storage/                  # logs, backups, invoices (outside web root)
├─ .env.example              # copy to .env, never commit .env
├─ .htaccess                 # fallback guard + rewrite to public/
└─ README.md
```

## 3. Requirements

- PHP **8.2 or newer** with `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `json`, `session`
- MySQL **8.0+**
- Apache with `mod_rewrite` enabled (or Nginx equivalent — see [DEPLOYMENT.md](docs/DEPLOYMENT.md))
- HTTPS certificate for production
- ~128 MB PHP memory limit is sufficient

## 4. Local setup

1. **Create the database**

   ```sql
   CREATE DATABASE dailydose CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'dde'@'localhost' IDENTIFIED BY '<strong-password>';
   GRANT ALL PRIVILEGES ON dailydose.* TO 'dde'@'localhost';
   ```

2. **Configure environment**

   ```bash
   cp .env.example .env
   ```

   Edit `.env` (DB credentials, `APP_URL=http://127.0.0.1:8080`, `APP_DEBUG=1` for local only, `MAIL_TRANSPORT=log`).

3. **Import the database — in this order**

   ```bash
   mysql -u dde -p dailydose < database/schema.sql
   mysql -u dde -p dailydose < database/seed.sql
   # Optional, local demo only — adds sample prices and stock:
   mysql -u dde -p dailydose < database/demo-prices.sql
   ```

   `seed.sql` deliberately leaves every variant price `NULL` and stock `0`. Do **not** ship `demo-prices.sql` values as real business data.

4. **Serve locally**

   ```bash
   php -S 127.0.0.1:8080 -t public public/router.php
   ```

   Open <http://127.0.0.1:8080>. Admin: <http://127.0.0.1:8080/admin/>.

5. **First admin login**

   | Field | Value |
   |---|---|
   | URL | `/admin/login.php` |
   | Email | `admin@dailydoseexport.com` |
   | Password | `ChangeMe@DDE2026` |

   Change this password immediately (Admin → Staff). Seed values are documented in `database/seed.sql`.

6. **Make storage writable** (Linux/macOS): `storage/` and `public/uploads/` must be writable by the PHP user (`755`/`775` for directories, `644` files).

## 5. Configuration

All secrets live in **`.env`** at the project root (blocked by `.htaccess`, ignored by Git). `.env.example` documents every key.

| Key | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG` | Environment flag; keep `APP_DEBUG=0` in production |
| `APP_URL` | Canonical site URL, used for SEO and links |
| `APP_TIMEZONE` | Defaults to `Asia/Kolkata` |
| `DB_HOST/PORT/NAME/USER/PASS` | MySQL connection |
| `MAIL_TRANSPORT` | `log` (writes `storage/logs/mail.log`), `mail`, or `smtp` |
| `MAIL_HOST/PORT/USER/PASS/SECURE/FROM/FROM_NAME` | SMTP delivery |
| `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET` | Online payments (leave blank to disable) |

Business content (phone, address, hero copy, shipping/tax, COD toggle, Razorpay Key ID, legal identifiers, SEO defaults) is edited in **Admin → Settings**, stored in the `website_settings` table. Payment *secrets* are never editable in the admin UI and never rendered back to the browser.

## 6. Security model (summary)

- All queries use PDO prepared statements; no string-concatenated user input.
- Output escaped via `e()`; CMS rich text filtered through an allow-list sanitiser.
- CSRF tokens on every state-changing form; JSON API requires `X-CSRF-Token`.
- Sessions: `HttpOnly`, `SameSite=Lax`, `Secure` under HTTPS, strict mode, ID rotation on login/logout.
- Passwords hashed with `password_hash()` (bcrypt); reset tokens are hashed and time-limited.
- Rate limiting on login, register, password reset, checkout, contact/enquiry, newsletter and payment endpoints.
- Order totals, stock, shipping and tax are recalculated **server-side** inside a transaction; stock is reserved on placement and restored on cancellation/return with an audit trail.
- Payment webhooks verified by HMAC-SHA256 (`X-Razorpay-Signature`) before any order is marked paid; idempotent.
- Uploads validated by MIME (`finfo`), dimensions, size (≤ 5 MB), re-named with random hex; only JPG/PNG/WebP.
- Admin area has its own session guard and per-role permissions; sensitive actions are written to `audit_logs`.
- Document root should be `public/`; the root `.htaccess` is a fallback that also blocks `app/`, `database/`, `storage/`, `docs/`, `vendor/` and `.env`.

## 7. SEO & performance

- Clean URLs: `/products/{slug}`, `/category/{slug}`, `/pages/{slug}`, `/sitemap.xml`.
- Canonical, robots (index/noindex per page type), OG/Twitter tags, theme colour, favicon.
- JSON-LD: Organization, WebSite (with SearchAction), WebPage, BreadcrumbList, Product with `Offer`/`AggregateOffer`.
- XML sitemap generated from the database (products, categories, published CMS pages, static pages); `robots.txt` excludes admin, API, account, cart, checkout and payment routes.
- Lazily loaded imagery, minified hand-written CSS/JS, browser caching for static assets via `mod_expires`.
- Pagination is indexable with self-referencing canonicals; filtered/search views are `noindex,follow`.

## 8. Documentation

- [docs/BLUEPRINT.md](docs/BLUEPRINT.md) — sitemap, user flows, architecture, data model
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — cPanel/hosting deployment, SSL, SMTP, Razorpay webhooks, backups, go-live checklist
- [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md) — roles, daily operations, orders, COD, enquiries, CMS, settings
- [docs/QA-REPORT.md](docs/QA-REPORT.md) — tested flows, findings and fixes, placeholder-data register

## 9. Placeholder / pending data (read before launch)

The build intentionally does **not** invent business facts. Before going live:

- Product **prices and stock** are empty until an admin sets verified values (demo file only for local QA).
- Category/hero imagery currently uses supplied category visuals (`assets/images/cat-*.jpg`) as **placeholders until real brand photography** is supplied.
- Legal pages (Privacy, Terms, Shipping, Returns, Cancellation, Cookie) ship as **drafts** and must be reviewed by the business/legal advisor, then published.
- GSTIN, FSSAI number, legal entity, public email, PIN code, social URLs, shipping/tax rules and COD switch are left blank/off until the owner confirms them.
- No certifications, reviews, ratings, award claims, licences or customer logos were fabricated.

See `docs/QA-REPORT.md` for the full register.

## 10. Ownership

Proprietary project for Daily Dose Export. All content, branding and business data belong to the business owner. Do not redistribute without permission.

# QA Report & Placeholder-Data Register

**Project:** Daily Dose Export website + admin
**QA window:** November 2026
**Environment:** PHP 8.3.37 (NTS portable), MySQL 8.0 (port 3307), Apache-equivalent via PHP built-in server (`public/router.php`), Windows 11 host
**Database:** `schema.sql` + `seed.sql` + `demo-prices.sql` (demo pricing used only for local flow testing)

---

## 1. Test approach

| Layer | Method |
|---|---|
| Static analysis | `php -l` on **71** PHP files; `node --check` on storefront and admin JS |
| HTTP | `curl` status checks, headers, sitemap/robots output |
| Functional | Browser automation: navigation, DOM snapshots, `fetch`/JS evaluation, network and console logs |
| Data integrity | Direct SQL verification of orders, stock ledger, payments, audit rows, deletion flows |
| Responsive / visual | CSS inspection and breakpoint review; **automated screenshots were unavailable in the QA environment** (browser viewport reported 0×0) |

> Caveat: because screenshots could not be captured, visual regression (pixel-level layout) was reviewed from CSS and live DOM, not images. A final manual walkthrough in Chrome/Safari/Firefox on real devices is recommended before launch (checklist in DEPLOYMENT.md §12).

## 2. Static checks — result

- **PHP lint:** 71/71 files, no syntax errors.
- **JavaScript:** `main.js` and `admin.js` parse cleanly.
- **Logs after smoke test:** `storage/logs/app.log`, `mail.log`, `php-error.log` all empty (0 bytes).
- **Routes:** `/`, `/shop.php`, `/products/whole-turmeric`, `/sitemap.xml`, `/admin/login.php` → HTTP 200.

## 3. Functional coverage

### Storefront
| Flow | Result |
|---|---|
| Home page sections, navigation, announcement, newsletter | Pass |
| Shop listing, category filter, price/pack/availability filters, sort, pagination | Pass |
| Search in English (`turmeric`) and Hindi (`हल्दी`, `haldi`) | Pass — alias matching resolves to correct products |
| Product page: variant selection, stock state, add to cart, wishlist (logged in), related products | Pass |
| Cart: quantity update, remove, coupon apply/remove, totals recalculated server-side | Pass |
| Guest checkout: validation, honeypot, CSRF, rate limit, COD path, idempotency | Pass |
| Order success page, order emails, admin new-order notification | Pass |
| Track order by number + email | Pass |
| Contact, Wholesale, Export enquiry forms → enquiries; Contact → messages | Pass |
| About (mission/vision/values/team), CMS pages, FAQ, 404 page | Pass |
| Register / login / logout / forgot / reset password | Pass |
| Account: dashboard, orders, wishlist, addresses (add/edit/default/delete, limit 10), profile, reviews, deletion request | Pass |

### Admin
| Flow | Result |
|---|---|
| Login/logout, session rotation, rate limiting, unauthorised role access (403) | Pass |
| Dashboard counters, notifications, low-stock indicators | Pass |
| Orders: status pipeline (pending → delivered), status emails, internal notes, COD payment recording | Pass |
| Cancel order: stock restored once, ledger entry `order_cancelled`, `stock_restored` flag | Pass |
| Products: create/edit, variants (create/update/deactivate), image upload/primary/alt/delete, SEO fields | Pass |
| Inventory: receive / remove / set, guarded against removing reserved stock, ledger rows, threshold alerts | Pass |
| Customers: search, deletion-request approval (anonymisation) and rejection | Pass |
| Coupons: create/validate, apply and remove at cart (invalid code rejected) | Pass |
| Enquiries pipeline statuses, contact messages read/replied | Pass |
| Reviews moderation (approve/reject/verify), content pages/banners/team, settings groups, audit log, staff roles | Pass |

## 4. Order lifecycle verification (local)

| Check | Evidence |
|---|---|
| Stock reserved at placement | `inventory_transactions.change_qty` negative, reason `order_reserved`; `reserved_qty` increased |
| Order items and totals stored | `order_items` rows match submitted quantities; totals recalculated server-side |
| Admin + customer email | Mailer invoked; `mail.log` transport verified |
| COD marked paid | `payments` row gateway `cod`, `gateway_payment_id = COD-…`, order `paid`; audit `order.cod_payment_recorded` |
| Cancellation | Stock restored (`change_qty` positive, reason `order_cancelled`), `orders.stock_restored = 1`; repeat cancel does not restock |
| Audit trail | `order.status_updated`, `order.notes_updated`, `settings.updated`, `admin.login` rows present |
| Idempotency | Re-submitting checkout with same key does not duplicate the order |

Razorpay live/test gateway could not be exercised without business credentials; its code path was reviewed: order creation, signature verification (`hash_hmac`), HMAC webhook (`X-Razorpay-Signature`), and idempotent `mark_paid`. Must be tested in Razorpay test mode before enabling in production (DEPLOYMENT.md §9).

## 5. Security checks

| Control | Status |
|---|---|
| SQL injection | All queries parameterised (review + grep of concatenated inputs) |
| XSS | `e()` on output; CMS HTML allow-list sanitiser; JSON-LD via `safe_json` |
| CSRF | Tokens on all POST forms; `X-CSRF-Token` required for JSON API |
| Session security | Strict mode, HttpOnly, SameSite=Lax, Secure on HTTPS, ID rotation |
| Password storage | `password_hash` bcrypt; reset tokens hashed with expiry |
| Rate limiting | Login, register, reset, checkout, contact/enquiry, newsletter, payment actions |
| Order tampering | Prices/stock/shipping/tax recalculated server-side inside a transaction |
| Payment spoofing | Razorpay signature + webhook HMAC verified; secrets only in `.env` |
| Uploads | MIME sniff, image dimensions, ≤ 5 MB, random filenames, traversal-safe delete |
| Admin authorisation | Per-route permission checks; 403 for insufficient role |
| Sensitive exposure | Secrets never rendered in admin; `.env`, `app/`, `database/`, `storage/`, `docs/` denied by `.htaccess`; robots excludes private routes |
| Transport headers | CSP, HSTS (on HTTPS), X-Frame-Options, Referrer-Policy, Permissions-Policy, nosniff |

## 6. SEO verification

- Clean URLs for products/categories/pages; `/sitemap.xml` valid XML with **45** URLs (products, active categories, published pages, static pages); `track-order` correctly excluded.
- `robots.txt` blocks admin/API/account/cart/checkout/order/payment/auth routes and allows `/uploads/`; sitemap referenced.
- Canonicals on all public pages, including paginated shop pages (earlier bug fixed — pagination was noindex and canonicalised to page 1).
- `noindex,follow` on filtered/search results, drafts, cart, checkout, account, auth, tracking and 404; `noindex,nofollow` on payment and order-success.
- JSON-LD present and valid: Organization, WebSite + SearchAction, WebPage, BreadcrumbList, Product with `Offer`/`AggregateOffer` (fixed invalid `priceRange` usage), aggregate ratings only when reviews exist.
- OG/Twitter tags, theme colour, favicon; sitemap only lists published content.

## 7. Defects found and fixed during QA

| # | Defect | Root cause | Fix |
|---|---|---|---|
| 1 | Checkout POST returned 200 without redirect (order still created) | `Order::create()` read the new order via a query lacking `items`, emitting a warning before `header()` | Re-read with `Order::find($orderId)` and fail cleanly if missing |
| 2 | Paginated shop pages were `noindex` and canonical pointed to page 1 | Canonical/robots computed without considering `page` | Self-referencing canonical with `?page=N`; indexable pagination |
| 3 | `robots.txt` both allowed and disallowed `/uploads/` | Contradictory rules | Removed `Disallow: /uploads/` |
| 4 | Sitemap listed `track-order.php` (noindex) | Static list included private page | Removed |
| 5 | Product JSON-LD used one `Offer` with a price range | Multiple variant prices collapsed into one invalid offer | Emit `AggregateOffer` with `lowPrice`/`highPrice`/`offerCount` |
| 6 | Wishlist header badge absent at count 0, JS couldn’t update | Badge only rendered when count > 0 | Always render with `is-empty` class |
| 7 | Wishlist toggle label never changed after click | JS updated aria state only | Update button label text on success |
| 8 | `cod_enabled` switched on during QA | QA enabled COD to test the flow | Documented as a business setting; production seed default remains `0` (see §9) |
| 9 | Saving a new coupon emitted “Undefined array key” warnings and broke the post-save redirect | `post_int()` read `$_POST[$key]` again after `filter_var()`, even when the key was absent (e.g. `id`, `category_id` on an “all products” coupon) | Helper now filters `$_POST[$key] ?? null` once and returns the default on failure; coupon save verified with 302 redirect and clean logs |

No open functional defects were known at the end of QA. No console errors were observed on storefront or admin pages during scripted flows.

## 8. Data cleanup after QA

All QA-generated records were removed with narrow, record-specific scripts (orders, order items, payments, inventory transactions, enquiries, messages, newsletter subscribers, QA customers, password-reset tokens and test coupons), stock was restored to its pre-test level, QA notifications and related audit rows were deleted, and local log files were truncated. Remaining audit rows are the two intentional admin logins and the `settings.updated` entry for the COD toggle. The final state shows zero orders, customers and coupons in the local database.

The password-reset flow (request → email link → token reset → login with new password) was exercised end-to-end against the `log` mail transport before cleanup.

The local database is a QA database. Production must be initialised from `schema.sql` + `seed.sql` only.

## 9. Placeholder / pending-data register

These are deliberately **not** fabricated. They must be supplied or confirmed by the business before launch.

| Item | Current state | Action required |
|---|---|---|
| Product prices & MRP | `NULL` in `seed.sql`; `demo-prices.sql` exists for local testing only | Admin enters verified prices; do not import demo values |
| Stock quantities | `0` in seed; demo file sets 100 for QA | Warehouse sets real counts via Inventory |
| Product certifications / FSSAI / GSTIN / legal entity | Blank settings | Enter only verified identifiers; they render only when present |
| Product photography | Category placeholders (`assets/images/cat-*.jpg`, hero, packaging) | Replace with real Daily Dose product/store photography; add alt text |
| Reviews & ratings | None seeded; none invented | Collect genuine reviews after launch; moderate |
| Legal pages | All six legal pages seeded as **draft** with “pending owner and legal review” text; FAQ published | Owner/legal review, then publish each page |
| Public contact email | Blank | Add a monitored mailbox in Settings → Contact |
| PIN code, map link | Blank | Add verified address details |
| Social profiles | Blank | Add official URLs only |
| Shipping, tax, free-shipping threshold | 0 / 0 / empty, with “confirmed by our team” note | Set commercial policy in Settings → Commerce |
| COD | Off in production seed (`cod_enabled=0`); enabled only in the local QA DB | Enable when operationally ready |
| Razorpay | Keys blank; disabled | Add live keys to `.env`, webhook secret, enable after test-mode verification |
| SMTP credentials | `.env.example` placeholders | Configure real SMTP and SPF/DKIM/DMARC |
| Admin credentials | Seeded `admin@dailydoseexport.com` / `ChangeMe@DDE2026` | Change password and email immediately after first login |
| Business copy | Mission/vision/values/story/FAQ derived from supplied brand documents | Owner confirms wording before launch |

## 10. Responsiveness & accessibility notes

- CSS breakpoints at 1160 / 1024 / 820 / 560 / 380 px support 320–1920 px layouts; mobile nav and filter drawers are implemented in JS with `aria-expanded`/`aria-controls`.
- Skip link, landmarks, visible focus styles, labelled form controls, `aria-live` flash messages, reduced-motion support, print stylesheet.
- Image dimensions/alt text and contrast were reviewed in code; final visual audit on real devices remains (screenshots unavailable in QA).

## 11. Performance notes

- No front-end build step; one CSS file plus one JS file for the storefront (deferred), minimal admin JS.
- Lazy-loaded images below the fold; font preconnect and `display=swap`; static asset caching via `mod_expires`.
- Pagination limits 12 products/page; catalogue queries use indexed slug/status columns and limited sub-selects.
- Recommend enabling hosting-level gzip/brotli and HTTP/2; consider a CDN for images if traffic grows.

## 12. Sign-off

| Area | Status |
|---|---|
| Functional storefront | Pass (local) |
| Functional admin | Pass (local) |
| Order/stock/payment integrity | Pass (COD path; Razorpay requires gateway test) |
| Security controls | Pass (code review + scripted checks) |
| SEO/structured data | Pass |
| Documentation | README, DEPLOYMENT, ADMIN-GUIDE, BLUEPRINT, QA-REPORT delivered |
| Production readiness | Conditional on §9 items, SMTP verification, Razorpay test-mode run and final device walkthrough |

# Admin Guide — Daily Dose Export

Audience: store owner, managers and staff using `/admin`.
Keep this document with the Super Admin credentials in a secure internal location.

---

## 1. Access and roles

- URL: `https://dailydoseexport.com/admin/` (local: `http://127.0.0.1:8080/admin/`).
- Sign-in is separate from customer login; sessions are isolated and rate-limited.
- Every staff member should have their own account (Staff → New staff). The seeded Super Admin is for setup only.

### Role permission matrix

| Area | Super Admin | Admin | Order Manager | Inventory Manager | Content Manager |
|---|:---:|:---:|:---:|:---:|:---:|
| Dashboard / reports | ✔ | ✔ | ✔ | ✔ | ✔ |
| Orders | ✔ | ✔ | ✔ | — | — |
| Customers | ✔ | ✔ | ✔ | — | — |
| Enquiries & messages | ✔ | ✔ | ✔ | — | — |
| Reviews | ✔ | ✔ | ✔ | — | ✔ |
| Products & categories | ✔ | ✔ | — | ✔ | — |
| Inventory | ✔ | ✔ | — | ✔ | — |
| Coupons | ✔ | ✔ | — | — | — |
| Content (pages, banners, team) | ✔ | ✔ | — | — | ✔ |
| Settings | ✔ | ✔ | — | — | — |
| Staff | ✔ | — | — | — | — |
| Audit log | ✔ | ✔ | — | — | — |

Roles are enforced server-side on every admin page and action; hiding a nav item is not the only protection. Unauthorised access returns HTTP 403.

Tip: use Content Manager for blog/legal page edits, Inventory Manager for warehouse updates, Order Manager for the dispatch desk. Reserve Admin/Super Admin for the owner and technical lead.

## 2. Dashboard

The dashboard shows orders to action, recorded paid revenue, customer count, reviews awaiting approval, a 7-day orders chart, recent orders, low-stock variants and the latest enquiries, with quick links to Notifications and the Audit log. Use the left navigation badges:

- **Orders** — count of pending/confirmed/processing/packed orders.
- **Enquiries / Messages** — unread/new items.
- **Reviews** — pending moderation.
- **Notifications** bell — in-app alerts for new orders, payments received, refunds due, enquiries/messages, new reviews and account-deletion requests.

## 3. Orders

### Order statuses

`pending → confirmed → processing → packed → shipped → out_for_delivery → delivered`

Side states: `cancelled`, `return_requested`, `returned`, `refunded`.

- Use **Update status** on the order page. Every change is audited (`order.status_updated`) and the customer receives a status email.
- `shipped`/`out_for_delivery` record the first shipped timestamp; `delivered` records delivery time.
- **Cancel**, **Return** and **Refund** restore stock automatically the first time (stock movements appear in Inventory as `order_cancelled` / `refund_restock`, with a `stock_restored` flag so it cannot double-restock).
- Cancelling a paid order flags payment for refund — process the refund in the payment gateway, then set the order to `refunded`.
- **Notes** tab: internal notes only, never shown to customers. Saved with an audit entry.
- **Record COD payment**: for Cash-on-Delivery orders, mark payment received after collection. This records a `payments` row (gateway `cod`, reference `COD-…`), sets `payment_status=paid`, audits `order.cod_payment_recorded` and emails the customer. Only use it when money has actually been collected.
- Customer details, shipping address, line items, coupon, totals and payment history are on the order page. Totals are always recalculated server-side at checkout; do not manually edit totals.

### Search and filtering

Orders can be filtered by status and searched by order number, customer name, email or phone. The order page has a **Print invoice** button (browser print dialog).

## 4. Products, categories and variants

### Products list

- Statuses: **published** (visible), **draft** (hidden), **archived** (hidden, retained).
- Flags: Featured (home/shop), New (badge).
- Only products with status `published` and at least one active variant **with a price** appear on the storefront. A product with no price is invisible to customers until pricing is set.

### Product editor

- **Basics** — name, slug (auto from name, editable), category, product type, short/full description, tax rate, featured/new flags, status.
- **Product details** — ingredients, origin, packaging, storage instructions, manufacturer info, nutritional info, allergen info, FSSAI declaration and net quantity. Only fill these from verified packaging/lab data; leave blank otherwise.
- **SEO & search** — meta title/description, keywords, and search aliases. Enter Hindi or alternate names comma-separated (e.g. `haldi, haldi powder, turmeric`) so customers can search in English or Hindi.
- **Variants** — each row: pack size, weight (grams, used for ordering), price, MRP, SKU (unique), default flag, status. The lowest active price is shown on cards; the default variant preselects on the product page.
  - Variants used in past orders cannot be hard-deleted; set them inactive instead.
  - Prices should be final, GST-inclusive as per your pricing policy; the store calculates tax/shipping from Settings.
- **Images** — upload JPG/PNG/WebP up to 5 MB; set one primary image, reorder, edit alt text, delete. Images are stored under `public/uploads/products/…`. Use real brand/product photography (see §15).
- **SEO** — per-product meta title/description feed the canonical product page and JSON-LD Product markup.

### Categories

- Create/edit categories with name, slug, description, image and sort order; activate/deactivate.
- Deactivating a category hides its navigation entry and category page. Products remain if moved — reassign before deactivating to avoid orphaned catalogue browsing.
- Product counts in Shop filters are computed from published products.

## 5. Inventory

- Each variant has **stock quantity**, **reserved quantity** and a **low-stock threshold** (default 10).
- Adjustments: **Receive** (+), **Remove** (−), **Set level**. Each adjustment updates the stock quantity and writes an `inventory_transactions` row with reason, admin and note — the ledger is the audit trail behind the current figure.
- You cannot remove more than the unreserved quantity (`stock − reserved`).
- Low-stock variants are flagged on the dashboard and can be filtered here. Set thresholds per variant to match sales velocity.
- Stock is reserved when an order is placed and released on cancellation/return. Do not manually “correct” stock for those events — use the order workflow so the ledger stays consistent.

## 6. Coupons

Create percentage or fixed-amount coupons with:

- code (auto-uppercased, unique), description,
- minimum order amount, maximum discount cap,
- total usage limit and per-customer limit,
- scope: all products / category / product,
- start and expiry dates, active/inactive status.

Coupons are validated and applied server-side; the cart shows the recalculated total. Expired or exhausted coupons are rejected. Deactivate rather than delete coupons that have been used, to preserve order history.

## 7. Customers

- Search by name/email/phone; view contact details, addresses, orders and wishlist.
- Customer accounts can be active, disabled or **pending_deletion**.
- **Deletion requests** (raised from the customer account area) appear as a pending badge. Approving **anonymises the login account**: name replaced, email replaced with a non-deliverable address, password randomised, phone cleared, addresses and wishlist deleted, account disabled; the action is audited. Existing order records, including delivery details needed for accounting and statutory retention, are kept.
- Rejecting a deletion request restores the account to active; contact the customer separately using their registered details.

## 8. Enquiries (wholesale / export / contact B2B)

Pipeline: `new → contacted → quotation_sent → negotiation → converted → closed`.

- New enquiries show a badge in the nav and dashboard.
- Open an enquiry to see business name, contact, category/quantity requirements, message and source page.
- Move the status as you work; adding notes keeps the team informed. `converted` should be used when the enquiry becomes an order; `closed` for dead/lost leads.
- The public **Contact** form submissions live under **Messages** with their own new/read/replied status.

## 9. Reviews

- Logged-in customers submit reviews from product pages when `reviews_enabled` is on in Settings; every review lands as **pending** for moderation. “Verified purchase” is set automatically when a matching paid/delivered order exists.
- Actions: approve, reject, delete, toggle “verified purchase”.
- Only approved reviews appear publicly and contribute to the star rating and Product JSON-LD. Never publish invented reviews or ratings.

## 10. Content (CMS)

Three tabs:

- **Pages** — legal and informational pages (Privacy, Terms, Shipping, Returns, Cancellation, Cookie, FAQ, etc.). Fields: title, slug, HTML body (sanitised against an allow-list), meta title/description, status (draft/published). Draft pages are `noindex` and show a “being finalised” notice. Publish only after approval.
- **Banners** — promo banners with title/subtitle, image, link, placement, schedule (start/end), sort order and active/inactive status. Use for seasonal promotions; keep the announcement bar text in Settings → General.
- **Team** — About page leadership/team cards: name, position, bio, photo, order and status. The About page mission/vision/values/story come from Settings → About.

## 11. Staff

Super Admin only. Create staff with name, email, role and password; activate/deactivate; reset passwords. Deleting staff is discouraged — deactivate instead so audit history stays attributable. Never share logins; actions are recorded against the staff account.

## 12. Settings

Grouped tabs, all stored in `website_settings`:

| Group | What it controls |
|---|---|
| General | Site name, tagline, slogan, announcement bar text/link, footer introduction |
| Home | Hero eyebrow/title/text/note, newsletter copy |
| About | Intro, mission, vision, core values (one per line), story |
| Contact | Support phone, WhatsApp number & default message, public email, address, state, PIN, country, map link |
| Commerce | Tax %, flat shipping, free-shipping threshold, shipping note, COD on/off + fee. Display currency is fixed to INR (₹) in this release. |
| Payments | Razorpay on/off and **Key ID only** (public). Secrets live in `.env` and are never shown. |
| SEO | Default meta description |
| Social | Instagram, Facebook, YouTube, X URLs |
| Legal | Registered entity, GSTIN, FSSAI — **only enter verified values**; each is displayed only when filled |
| System | Admin notification email, product reviews on/off, maintenance mode |

Notes:
- Settings changes are audited (`settings.updated`) with the keys changed, not the values.
- **Maintenance mode** shows a 503 holding page to visitors; the admin panel and static assets stay available. Use it during catalogue migrations, then switch off.
- Shipping/tax values must match actual commercial policy. If unsure, leave shipping flat rate 0 and use the shipping note (“confirmed by our team”) until policy is final.
- Secret changes (DB, SMTP, Razorpay secret) require editing `.env` on the server — admins cannot do this from the UI by design.

## 13. Notifications and audit log

- **Notifications** — bell icon lists recent events; mark read or open the linked order/enquiry. Each notification may also be emailed depending on configuration.
- **Audit log** (Super Admin/Admin) — immutable record of staff actions: logins, order status changes, COD payments, stock adjustments, price/product changes, settings updates, customer deletion processing. Filter by actor, action or entity to investigate incidents.

## 14. Content and image guidelines

- Use original or licensed product photography. Do not use unrelated supermarket or stock photos as if they depict Daily Dose products or premises.
- Recommended: square images ≥ 1200 px, consistent warm, natural lighting, plain backgrounds; WebP/JPG, ≤ 5 MB.
- Write accurate pack size, weight and ingredient descriptions — do not invent certifications, nutritional values or health claims. If a value is unknown, leave it out or mark it “to be confirmed”.
- Keep slugs short and stable; changing a slug breaks existing links and sitemap entries (redirects are not automatic).

## 15. Daily / weekly routine (recommended)

**Daily**
1. Dashboard — new orders, new enquiries/messages, pending reviews.
2. Orders — confirm new orders, print invoices as needed, update to shipped/out_for_delivery with tracking notes.
3. Record COD payments after collection.
4. Respond to enquiries (target: same business day).

**Weekly**
1. Inventory — receive stock, review low-stock list, reconcile physical count.
2. Products — publish drafts with real prices, update seasonal banners.
3. Reviews — moderate; respond where appropriate.

**Monthly**
1. Verify legal/contact details and shipping thresholds.
2. Review audit log for unusual activity and staff access.
3. Confirm backups (database + uploads) and test a restore.
4. Check Search Console for crawl errors; confirm `/sitemap.xml` has no stale URLs (unpublish/archive removed products).

## 16. Do / Don’t

**Do**
- Use individual accounts and strong passwords; change the seeded admin password on day one.
- Enter only verified business data (prices, stock, licences, legal text).
- Use the order workflow for cancellations/returns so stock and ledger stay correct.
- Archive/disable records instead of deleting when history matters.

**Don’t**
- Paste secrets (SMTP/Razorpay secrets, DB passwords) into CMS fields, product text, notes or chat.
- Publish draft legal pages or placeholder imagery without owner approval.
- Create fake reviews, ratings, certifications or scarcity claims.
- Delete customers with orders manually — use the deletion-request workflow.
- Disable CSRF/security features or edit `.env` from a browser.

---

Related: [DEPLOYMENT.md](DEPLOYMENT.md) · [QA-REPORT.md](QA-REPORT.md) · [BLUEPRINT.md](BLUEPRINT.md)

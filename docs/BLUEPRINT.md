# Daily Dose Export — Project Blueprint

**Domain:** dailydoseexport.com · **Tagline:** Pure From Farm To World · **Slogan:** Every Bite, A Promise of Health.
**Location:** Bhojubeer, Varanasi (Banaras), Uttar Pradesh, India · **Phone:** +91 84299 33125

---

## 1. Sitemap

| Area | URL | Purpose |
|---|---|---|
| Home | `/` | Brand story, categories, featured products, journey, millets, packaging, reviews, wholesale CTA, newsletter |
| About | `/about` | Company, philosophy, mission, vision, values, leadership (CMS-managed) |
| Shop | `/shop` + `/category/{slug}` | Catalogue with category, price, pack size, availability, sort filters; live search |
| Product | `/products/{slug}` | Gallery, variants, specs, ingredients, storage, nutrition, reviews, related products |
| Cart | `/cart` | Server-priced cart, coupon, shipping estimate |
| Checkout | `/checkout` | Customer → address → review → payment (COD / Razorpay) |
| Order success | `/order-success?order=…&token=…` | Confirmation, next steps, invoice link |
| Track order | `/track-order` | Order number + email/phone lookup, scoped to that order only |
| Contact | `/contact` | Contact form, address, phone, WhatsApp, map link |
| Wholesale | `/wholesale` | Bulk enquiry form and buyer information |
| Export | `/export-enquiry` | International enquiry form (country, destination port, packaging) |
| Account | `/account` | Profile, addresses, orders, wishlist, reviews, password, deletion request |
| Auth | `/login`, `/register`, `/forgot-password`, `/reset-password` | Customer authentication |
| CMS pages | `/pages/{slug}` | Privacy, Terms, Shipping, Returns & Refunds, Cancellation, Cookie policy |
| Search API | `/api?action=…` | JSON endpoints (cart, coupon, wishlist, newsletter, contact, reviews, payment) |
| Admin | `/admin` | Dashboard, products, categories, orders, customers, inventory, coupons, enquiries, reviews, content, staff, settings, audit log |
| SEO | `/sitemap.xml`, `/robots.txt`, `/404` | Indexing and error handling |

## 2. User flows

- **Retail customer:** browse → search/filter → product → choose variant → cart → apply coupon → guest or account checkout → COD or Razorpay → confirmation → track.
- **Registered customer:** register/login → saved cart merged → addresses and wishlist → checkout → order history → verified review after delivery.
- **B2B buyer:** wholesale/export page → enquiry (no retail order required) → admin pipeline: New → Contacted → Quotation sent → Negotiation → Converted / Closed.
- **Admin:** role-based login → dashboard → manage catalogue/inventory/orders/enquiries/content → audit trail.

## 3. Architecture

- **Frontend:** semantic HTML5, custom CSS (design tokens, Grid/Flexbox), vanilla ES6 modules-free JS, Fetch API, progressive enhancement. Google Fonts (Playfair Display + Inter) with local fallback.
- **Backend:** PHP 8.2+ core, PDO/MySQL 8, sessions, REST-style JSON endpoints in `public/api`. No Node build step; cPanel/Apache compatible.
- **Layout:** `public/` is the document root. `app/`, `database/`, `storage/`, `.env` live outside the web root. `.htaccess` files deny access if the server is misconfigured.
- **Separation:** config → core services (DB, Auth, Cart, Orders, Catalog, Mailer, Uploader, RateLimiter) → thin controllers (public/admin pages) → views/partials. Prices, stock and discounts are always recalculated server-side.
- **Money:** `DECIMAL(10,2)`, INR default, tax and shipping configurable in admin. No invented prices seeded in production data — variants can be priced by admin; unpriced variants show “Price on request”.
- **Inventory:** reserved (decremented) inside the order transaction with `SELECT … FOR UPDATE`; every change written to `inventory_transactions`; cancellation/refund restores stock once.
- **Payments:** Razorpay Orders API created server-side; signature verified server-side; order marked paid only after verification. COD is admin-toggleable.
- **Email:** PHPMailer when installed via Composer, otherwise PHP `mail()`; in development all mail is logged to `storage/logs/mail.log` when SMTP is not configured.

## 4. Design system

| Token | Value | Use |
|---|---|---|
| Forest | `#173F32` | Footer, dark sections, headings |
| Green | `#245A43` | Primary actions, links |
| Cream | `#F8F5EC` | Page background |
| Warm white | `#FFFDF8` | Cards, header |
| Earth | `#9A7046` | Secondary accents, labels |
| Gold | `#C69B51` | Highlights, rules, badges |
| Ink | `#252B27` | Body text |
| Muted | `#737A73` | Secondary text |

- **Type:** Playfair Display 500–700 for headings, Inter 400–600 for body; base 16px, 1.65 line-height, clamp() display sizes.
- **Components:** 8px radius cards, hairline `#E5DFD0` borders, 2px focus rings, solid buttons (no gradients), subtle 200ms transitions, editorial photography, generous whitespace.
- **Icons:** inline SVG (no icon font dependency). WhatsApp floating action, sticky mobile add-to-cart.
- **Accessibility:** WCAG AA contrast, 44px touch targets, visible focus, skip link, labelled inputs, reduced-motion support.
- **Imagery:** generated brand placeholder photography is committed for development. Replace with real Daily Dose packaging/product photography before launch; never present stock supermarket photos as the brand’s own.

## 5. Security controls

PDO prepared statements; output escaping via `e()`; CSRF tokens on every POST and JSON mutation; `password_hash`/`password_verify`; session regeneration; HttpOnly/SameSite/Secure cookies; login and form rate limiting; brute-force lockout; role/permission checks; audit log; upload MIME/extension/size validation with randomised names; PHP execution disabled in uploads; secure headers; no secrets in source (`.env`); HTTPS/HSTS ready; generic error pages; server-side totals; signed payment verification; transactional orders; unique idempotency key per checkout.

## 6. SEO plan

Clean slugs and canonical URLs, unique titles/descriptions, semantic landmarks, single H1 per page, breadcrumbs, Organisation/WebSite/SearchAction JSON-LD site-wide, Product + Offer JSON-LD only for real priced variants, truthful LocalBusiness data from settings, OG/Twitter cards, XML sitemap, robots.txt, 404 with correct status, internal linking, descriptive alt text, lazy loading and responsive images, mobile-first Core Web Vitals.

## 7. Data that must be supplied by the business before launch

These are intentionally not invented. The site renders honest fallbacks until they are configured:

1. Verified retail prices, MRP, pack sizes, stock levels and SKUs for every variant (admin → Products).
2. FSSAI licence number, GSTIN, legal entity name and manufacturer/packer address — only if verified (admin → Settings / product records).
3. Ingredient lists, allergen declarations, nutrition tables and net quantities from approved packaging.
4. Authentic product and packaging photography, logo files, leadership photos and bios.
5. Razorpay account keys, SMTP mailbox credentials, WhatsApp business number.
6. Shipping rates/zones, COD availability, GST rate policy, courier partners and delivery SLAs.
7. Legal policies reviewed by the owner (currently editable placeholders marked “Pending owner review”).
8. Genuine customer reviews and testimonials (seeded database contains none; the UI hides the section until approved reviews exist).

## 8. Delivery phases

1. Blueprint, schema, seed (this document + `database/`).
2. Core services, security, layout.
3. Storefront, cart, checkout, payments, accounts, enquiries.
4. Admin portal and CMS.
5. SEO, performance, responsive QA.
6. Deployment docs, admin guide, local QA sign-off.

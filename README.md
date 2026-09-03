# Meghali's Silk — Core API

Laravel 12 backend for the Meghali's Silk React storefront and admin console, served under
`https://core.meghalisilk.in/api/v1`. The contract (routes, payloads, response shapes, business
cascades) is defined by [`api-creation-guide.md`](api-creation-guide.md); the companion Postman
collection is [`postman-api-collection.json`](postman-api-collection.json).

## Stack

* PHP 8.2+, Laravel 12, MySQL 8 (SQLite in-memory for the test suite)
* Laravel Sanctum personal access tokens — two independent sessions:
  customer tokens carry the `customer` ability, admin tokens the `admin` ability (every `/admin/*` route).
* Every success response is `{ "success": true, "data": … }`; every error is `{ "message": "…", "errors"?: {…} }`.
* camelCase JSON keys, ISO-8601 UTC dates with milliseconds (`2026-01-15T10:30:00.000Z`), integer INR amounts.

## Getting started

```bash
composer install
cp .env.example .env            # set DB_*, CORS_ALLOWED_ORIGINS, ADMIN_EMAIL / ADMIN_PASSWORD
php artisan key:generate
php artisan migrate
php artisan db:seed             # imports db.json with the original ids + guarantees an admin account
php artisan serve               # http://localhost:8000/api/v1
```

Point the storefront at it with `REACT_APP_API_URL=http://localhost:8000/api/v1` and
`REACT_APP_USE_MOCK_API=false`. The seeded admin is `admin@store.com` / `admin123` — change it before go-live.

Run the tests and the code style check:

```bash
php artisan test
vendor/bin/pint
```

## Layout

| Path | Purpose |
| --- | --- |
| `routes/api.php` | All 110 endpoints under `/api/v1` (public, customer, admin groups) |
| `app/Http/Controllers/Api/V1/{Auth,Storefront,Admin}` | Thin controllers; every write goes through a Form Request |
| `app/Http/Requests` | Validation rules (guide §16) |
| `app/Http/Resources` | camelCase response shapes (guide §15) |
| `app/Services` | Business cascades: order pricing/placement, cancellation, refunds, returns, wallet ledger, coupons, inventory, settings |
| `app/Http/Middleware` | `EnsureTokenAbility` (401 on wrong scope), `EnsureAccountActive`, `ForceJsonResponse` |
| `database/migrations` | 28 tables in FK order (guide §11 / §36) |
| `database/seeders/DbJsonImportSeeder.php` | One-off `db.json` import with the clean-ups from guide §37 |
| `tests/Feature/Api` | Feature tests covering auth, catalogue, cart/wishlist, the order/refund/return cascades and every admin module |

## Decisions taken for the "BACKEND DECISION REQUIRED" items

| Topic | Decision |
| --- | --- |
| Online orders without a gateway (§22.3) | Option A: created as `paymentStatus: "pending"`; the admin's "Mark as Paid" captures the payment. `STORE_TRUST_CLIENT_PAYMENT_STATUS=true` switches to mock parity. |
| Shipping method not sent by checkout (§42.2) | The client's `shippingAmount` is accepted only when it equals the cost of an active method for the subtotal, otherwise 422. |
| Order / return / refund numbers | Server generated: `ORD-YYYYMMDD-NNNN`, `RET-YYYYMMDD-NNNN`, `REF-YYYYMMDD-XXXX`. |
| Price drift between cart and checkout | 422 `Prices have changed, please review your cart.` |
| Password minimum | 8 characters for registration and password change. |
| Wrong-scope / revoked / expired token | 401 so the frontend drops the stale session. |
| Token lifetime | 30 days, 90 with "Remember me" (`STORE_TOKEN_TTL_*`). |
| Dashboard `totalRevenue` | Mock parity (all orders); `STORE_REVENUE_EXCLUDES_CANCELLED=true` excludes cancelled/refunded. |
| Shiprocket proxy endpoints | `501 Shiprocket integration is not enabled.` |
| Product rating | Recomputed from approved reviews on every review event; seed values kept until then. |
| Seed data quality (§42.10) | Product 1's corrupted prices restored from its wishlist snapshot, dangling product ids dropped, FAQ test text stripped, wallet balance recomputed from the ledger. |
| Deletes of absent cart/wishlist rows | 404 (as the endpoint spec states). |

## Environment

See `.env.example`. Beyond the standard Laravel keys: `CORS_ALLOWED_ORIGINS` (exact storefront origins),
`STORE_TOKEN_TTL_DAYS`, `STORE_TOKEN_TTL_REMEMBER_DAYS`, `STORE_TRUST_CLIENT_PAYMENT_STATUS`,
`STORE_REVENUE_EXCLUDES_CANCELLED`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, and the server-side-only gateway keys.

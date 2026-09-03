# Meghali's Silk — Laravel API Development Guide

> **Audience:** the Laravel + MySQL developer building the production backend for the Meghali's Silk
> React storefront and admin console, to be hosted at **`https://core.meghalisilk.in`**.
>
> **Source of truth:** this guide was produced by reading every file in the repository
> (`src/services/api.js`, `src/services/baseURL.js`, every context, page and component, `server.js`,
> `db.json`, `package.json`, `.env*`). Nothing below is invented. Where the codebase does not settle a
> point it is marked **`BACKEND DECISION REQUIRED`** or **`FRONTEND BEHAVIOR NOT FOUND`**. Where the
> frontend would have to change it is marked **`FRONTEND CHANGE REQUIRED`**.
>
> **Companion file:** `backend-developer-guideline/postman-api-collection.json` (Postman v2.1) contains
> every endpoint in this guide with realistic bodies and test scripts.
>
> **Older documents:** the repository root also contains `00_BACKEND_README_AND_CONVENTIONS.md` … `04_AUTH_ERRORS_AND_EDGE_CASES.md`.
> They were written against an earlier revision of the app (before the admin-managed hero section, FAQs,
> banners CRUD, social links and settings sections were added, and with a different seed). **This guide
> supersedes them.** Where they disagree, follow this guide.

---

## Table of contents

1. Executive Summary
2. Existing Frontend Architecture
3. Technology Stack
4. Current JSON Server Architecture
5. Environment Configuration
6. API Base URL Architecture
7. Complete API Inventory
8. Authentication
9. Authorization & Roles
10. Database Architecture
11. Database Tables
12. Relationships
13. API Endpoint Specifications
14. Request Payload Specifications
15. Response Specifications
16. Validation Rules
17. Pagination
18. Search
19. Filtering
20. Sorting
21. File Uploads
22. Payments
23. Shipping
24. Orders
25. Cart
26. Wishlist
27. Reviews
28. Coupons
29. Admin APIs
30. Dashboard / Statistics APIs
31. Error Handling
32. Security Requirements
33. CORS
34. API Versioning
35. Laravel Architecture Recommendations
36. Database Migration
37. db.json Data Migration
38. Production Deployment
39. Domain Configuration
40. Postman Testing
41. Frontend Integration
42. Known Issues / Backend Decisions Required
43. Frontend Changes Required, If Any
44. Final Acceptance Criteria

---

## 1. Executive Summary

The frontend is a **Create React App** storefront + admin console that today talks to a **JSON Server**
mock backed by `db.json`. Every network call in the application goes through **one file**,
`src/services/api.js`, which already contains a **dual code path**:

```js
if (IS_MOCK_API) { /* JSON Server: GET /users?email=…, PATCH /orders/1 … */ }
else             { /* Laravel:     POST /auth/login, PATCH /admin/orders/1 … */ }
```

The **non-mock (`else`) branch is the exact Laravel contract** the frontend expects: path, verb, body
and how the response is consumed. The mock branch reveals the data shapes and the business cascades
that the mock performs client-side and that **Laravel must perform server-side** (payment record on
order create, coupon `usedCount`, store-credit wallet ledger, refund lifecycle, restock, audit timeline).

The production switch is two environment variables and nothing else:

```env
REACT_APP_API_URL=https://core.meghalisilk.in/api/v1
REACT_APP_USE_MOCK_API=false
```

Headline facts established from the code:

| Topic | Finding |
| --- | --- |
| Integration point | `apiService` in `src/services/api.js` is the only place Axios is used. No `fetch`, no other Axios instance anywhere in `src/`. |
| Base URL | Axios `baseURL = REACT_APP_API_URL`. **`/api/v1` is part of the env var**, every path in this guide is relative to it. |
| Envelope | Every Laravel success response must be `{ "success": true, "data": … }`; `extractData()` unwraps it. |
| Auth | Bearer tokens. Two independent sessions: customer (`/auth/login` → `data.token` + `data.user`) and admin (`/admin/auth/login` → `data.token` + `data.admin`). Requests whose URL contains `/admin/` carry the admin token. |
| Endpoints | **110** distinct Laravel endpoints are referenced by `api.js` (23 public, 24 customer-authenticated, 63 admin). 15 of them are defined in `api.js` but never called by any screen today; they are still specified so the service layer works unchanged. |
| Field naming | **camelCase everywhere** (request and response). The only snake_case fields are the three auth password fields (`password_confirmation`, `current_password`, `password`). |
| IDs | Top-level ids are JSON **numbers**. Product variant ids are **strings** (`"v1"`, `"v-1712…-123"`). |
| Dates | ISO-8601 UTC with milliseconds and `Z`: `2026-01-15T10:30:00.000Z`. |
| Money | Whole INR integers as JSON numbers. |
| Lists | Every list is consumed as a **plain array** and filtered / sorted / paginated **client-side**. No server pagination exists today. |
| Uploads | **None.** Every image is a URL typed into a text field (product images, category image, hero slide image/video, review photos are seed-only). No `<input type="file">`, no `FormData`, no multipart anywhere. |
| Payments | **No gateway is integrated.** Checkout's card/UPI/net-banking fields are decorative; the frontend stamps `paymentStatus:"paid"` itself for non-COD orders. Razorpay only appears in `.env` comments and in `settings.payment.razorpayEnabled:false`. |
| Shipping | Shipping methods are admin-managed flat/free rates. Tracking numbers are typed manually. Two Shiprocket admin endpoints exist in `api.js` but no screen calls them; `settings.shipping.shiprocketEnabled` is `false`. |

---

## 2. Existing Frontend Architecture

### 2.1 Routing (`src/App.js`)

Storefront (`/*`, wrapped in `Header`, `Footer`, `BottomNav`, `DealsConfigProvider`, `FaqProvider`):

| Route | Page | API calls (via `apiService`) |
| --- | --- | --- |
| `/` | `Home` | `categories.getAll`, `products.getFeatured(8)`, `products.getTrending(8)`, `products.getAll` |
| `/products` | `Products` | `products.getAll`, `categories.getAll` (all filtering/sorting/paging client-side) |
| `/products/:slug` | `ProductDetails` | `products.getBySlug` / `products.getById` (legacy numeric), `categories.getById`, `products.getReviews`, `products.getRelated`, `products.getFrequentlyBoughtTogether`, `shipping.getMethods` |
| `/checkout` | `Checkout` | `shipping.getMethods`, `wallet.getBalance`, `coupons.validate`, `orders.create` (via `OrderContext`) |
| `/order-confirmation/:orderNumber` | `OrderConfirmation` | `orders.getByOrderNumber` |
| `/orders` | `OrderHistory` | `orders.getByUserId`, `reviews.getMine`, `reviews.submit`, `orders.cancel` |
| `/profile` | `Profile` | `orders.getByUserId`, `reviews.getMine`, `wallet.getBalance`, `wallet.getTransactions`, `auth.updateUser` (via `AuthContext`), `auth.changePassword` |
| `/wishlist` | `Wishlist` | `products.getRelated`, `products.getFeatured` (+ `WishlistContext`) |
| `/special-offers` | `SpecialOffers` | `products.getAll`, `categories.getAll`, `coupons.getActive` (+ `DealsConfigContext`) |
| `/help` | `HelpCenter` | `FaqContext` only |
| `/support` | `Support` | `leads.createContact` |
| `/about`, `/privacy`, `/terms`, `/cookies`, `/refund` | static | `StoreSettingsContext` only |

Admin (`/admin`, MUI, guarded by `AdminLayout` → redirects to `/admin` when no admin session):

| Route | Page |
| --- | --- |
| `/admin` | `AdminLogin` |
| `/admin/dashboard` | `AdminDashboard` |
| `/admin/products` | `AdminProducts` |
| `/admin/categories` | `AdminCategories` |
| `/admin/orders` | `AdminOrders` |
| `/admin/returns` | `AdminReturns` |
| `/admin/payments` | `AdminPayments` |
| `/admin/users` | `AdminUsers` |
| `/admin/shipping` | `AdminShipping` |
| `/admin/coupons` | `AdminCoupons` |
| `/admin/special-offers` | `AdminSpecialOffers` |
| `/admin/hero-section` | `AdminHeroSection` |
| `/admin/faqs` | `AdminFaqs` |
| `/admin/reviews` | `AdminReviews` |
| `/admin/leads` | `AdminLeads` |
| `/admin/settings` | `AdminSettings` |

Shared components with API calls: `Header` (`categories.getAll`), `SidebarMenu` (`categories.getAll`),
`SearchModal` (`products.getAll`, `categories.getAll`, `products.getTrending`), `HeroSection`
(`hero.getConfig`, `banners.getAll`, `categories.getAll`), `CartDrawer` (`coupons.validate`), `Footer`
and `Newsletter` (`leads.createNewsletter`), `AdminLayout` (`admin.getOrders`, `admin.getLeads` for the
notification bell).

### 2.2 Context providers (`src/context/`)

| Context | Backend dependency |
| --- | --- |
| `AuthContext` | `auth.login`, `auth.register`, `auth.logout`, `auth.updateUser`. Persists `user` + `token` via `utils/authStorage.js` (sessionStorage by default, localStorage when "Remember me"). Restores the session on load only when **both** `user` and `token` exist. |
| `AdminContext` | `admin.login`, `admin.logout`. Persists `admin` + `adminToken` in **sessionStorage only**. |
| `CartContext` | Guest cart lives in `localStorage("cart")`. On login: `cart.getCart` → merge with local. Then, 600 ms after every change, the **whole server cart is replaced**: `cart.getCart` → `cart.removeFromCart(row.id)` for every row → `cart.addToCart(line)` for every local line. |
| `WishlistContext` | Guest wishlist in `localStorage("wishlist")`. On login: `wishlist.get`, then `wishlist.add` for guest-only rows. `wishlist.add` / `wishlist.remove` on toggle; `wishlist.remove` for each row on "Clear all". |
| `OrderContext` | `orders.getByUserId` on login; `orders.create` from checkout. **Generates `orderNumber` client-side** (`ORD-<base36 ts>-<base36 rand>`) and stamps `userId`, `createdAt`, `updatedAt`, `paymentStatus`, `fulfillmentStatus`, `shippingStatus`. |
| `StoreSettingsContext` | `settings.get` on mount, on window focus and on the `store-settings:updated` event. Reads `store`, `payment`, `social`. |
| `DealsConfigContext` | `deals.getConfig` on mount and window focus. |
| `FaqContext` | `faqs.getAll` on mount, focus and `faqs:updated`. Falls back to built-in FAQs when the list is empty. |
| `ThemeContext` | No API. `localStorage("theme")`. |

### 2.3 Service layer shape

`apiService` exposes namespaces: `auth`, `products`, `categories`, `banners`, `hero`, `cart`, `orders`,
`wallet`, `reviews`, `returns`, `coupons`, `wishlist`, `shipping`, `settings`, `faqs`, `deals`, `leads`,
`admin`. Section 7 maps every method to its Laravel endpoint.

---

## 3. Technology Stack

| Layer | Technology | Version (from `package.json`) |
| --- | --- | --- |
| Framework | React | `^18.2.0` |
| Build | Create React App (`react-scripts`) | `5.0.1` |
| Routing | React Router DOM | `^6.20.1` |
| HTTP client | Axios | `^1.6.2` |
| Mock backend | JSON Server | `^0.17.4` (run through `server.js`) |
| UI (admin) | Material UI | `^5.14.20` + `@mui/icons-material ^5.14.19`, `@iconify/react` |
| Styling | Emotion, CSS Modules | `^11.11` |
| Motion | Framer Motion | `^10.16.16` |
| Dialogs / toasts | SweetAlert2 | `^11.10.1` |
| Dev tooling | Concurrently | `^8.2.2` |

Scripts: `npm start`, `npm run build`, `npm test`, `npm run eject`, `npm run server` (`node server.js`),
`npm run dev` (`concurrently "npm start" "npm run server"`).

**This is NOT Vite.** Environment variables are `REACT_APP_*` and are embedded at build time.

Target backend: **Laravel (latest LTS) + MySQL 8**, Sanctum tokens, Eloquent, migrations, Form
Requests, API Resources, policies/middleware, DB transactions.

---

## 4. Current JSON Server Architecture

### 4.1 `server.js`

`server.js` wraps stock JSON Server (`jsonServer.create()`, `jsonServer.router(db.json)`,
`jsonServer.defaults()`) and changes exactly one thing:

* **`DELETE /:resource/:id` is overridden** to remove only the addressed row and write through to
  `db.json`, returning `200 {}` (or `404 {}` when the row does not exist). Stock JSON Server's
  "dependent cascade" scan (`getRemovable`) is disabled because it crashed on `null` foreign keys and
  would silently cascade-delete dependents.
* Port `3001` (or `JSON_SERVER_PORT`), database file `db.json` (or `JSON_SERVER_DB`).
* No authentication, no custom routes, no request transformation, no CORS customization beyond JSON
  Server defaults (`Access-Control-Allow-Origin: *`), no id generation beyond JSON Server's own
  auto-increment.

**Migration requirements derived from `server.js`:**

1. `DELETE` must never cascade-delete dependants. Enforce referential integrity by **blocking** the
   delete (see category delete → `409`).
2. `DELETE` returns `200` on success and `404` when the row is absent.

### 4.2 JSON Server conventions the mock branch relies on (mock-only, do NOT replicate)

`?field=value` equality filters, `?q=` full-text, `_sort`/`_order`, `PUT` full replace vs `PATCH` merge,
auto-increment numeric ids, singleton objects (`/settings`, `/heroConfig`, `/dealsConfig`). The Laravel
branch never sends any of these; it uses the explicit endpoints in Section 13.

### 4.3 `db.json` collections (current seed)

| Collection | Type | Rows | Notes |
| --- | --- | --- | --- |
| `banners` | array | 6 | hero slides |
| `heroConfig` | object | 1 | hero section singleton |
| `faqs` | array | 8 | |
| `users` | array | 4 | **plain-text passwords** in seed |
| `admins` | array | 1 | `admin@store.com` / `admin123`, role `super_admin` |
| `categories` | array | 3 | flat (all `parentId: null`) but the code supports a tree |
| `products` | array | 6 | ids 1, 6, 10, 13, 16, 19 (non-contiguous) |
| `cart` | array | 1 | |
| `orders` | array | 11 | two `orderNumber` formats |
| `returns` | array | 4 | |
| `payments` | array | 9 | |
| `refunds` | array | 7 | ledger |
| `shipping_methods` | array | 5 | |
| `coupons` | array | 7 | |
| `reviews` | array | 8 | ids non-contiguous |
| `wishlist` | array | 3 | |
| `leads` | array | 6 | contact + newsletter |
| `settings` | object | 1 | six sections |
| `walletTransactions` | array | 4 | |
| `dealsConfig` | object | 1 | |

Full per-collection field analysis is in Section 11 and the migration mapping in Section 37.

---

## 5. Environment Configuration

### 5.1 Frontend (Create React App, `REACT_APP_*`)

Current development (`.env`):

```env
REACT_APP_API_URL=http://localhost:3001
REACT_APP_USE_MOCK_API=true
REACT_APP_NAME=Meghali's Silk
REACT_APP_VERSION=1.0.0
REACT_APP_ENABLE_ANALYTICS=false
GENERATE_SOURCEMAP=true
```

Current `.env.production` (points at an old Cloudways host):

```env
REACT_APP_API_URL=https://phplaravel-780646-5827390.cloudwaysapps.com/api/v1
REACT_APP_USE_MOCK_API=false
```

**Intended final production configuration** (do not change the files as part of this task; this is the
target):

```env
REACT_APP_API_URL=https://core.meghalisilk.in/api/v1
REACT_APP_USE_MOCK_API=false
REACT_APP_NAME=Meghali's Silk
REACT_APP_VERSION=1.0.0
REACT_APP_ENABLE_ANALYTICS=true
GENERATE_SOURCEMAP=false
```

Only `REACT_APP_API_URL`, `REACT_APP_USE_MOCK_API` and `REACT_APP_NAME` are read by the code
(`baseURL.js`, `utils/constants.js`). `REACT_APP_RAZORPAY_KEY_ID`, `REACT_APP_SHIPROCKET_EMAIL` and
`REACT_APP_ENABLE_ANALYTICS` appear only as comments / are unused. **Never put backend credentials in
`REACT_APP_*` variables** — they are compiled into the public bundle.

### 5.2 Backend (Laravel `.env`) — never exposed to the frontend

```env
APP_NAME="Meghali's Silk API"
APP_ENV=production
APP_KEY=base64:…                      # php artisan key:generate
APP_DEBUG=false
APP_URL=https://core.meghalisilk.in
APP_TIMEZONE=UTC                      # serialize dates in UTC; store timezone stays in settings.store.timezone

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=meghali_silk
DB_USERNAME=meghali_api
DB_PASSWORD=…                         # strong, unique

SESSION_DRIVER=file                   # not used by the API (token auth) but required by Laravel
CACHE_STORE=file
QUEUE_CONNECTION=database             # for mail / low-stock alerts if implemented

SANCTUM_TOKEN_EXPIRATION=            # BACKEND DECISION REQUIRED: minutes, empty = no expiry

# CORS — BACKEND DEPLOYMENT CONFIGURATION REQUIRED (final storefront origin)
CORS_ALLOWED_ORIGINS=https://www.meghalisilk.in,https://meghalisilk.in

MAIL_MAILER=smtp                      # only if notifications are implemented
MAIL_HOST=…
MAIL_PORT=587
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=care@meghalisilk.com

# Gateways — not used by the current frontend (see Sections 22–23). Keep server-side only.
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
SHIPROCKET_EMAIL=
SHIPROCKET_PASSWORD=
```

---

## 6. API Base URL Architecture

Established from `src/services/baseURL.js` and `src/services/api.js`:

| Question | Answer (from code) |
| --- | --- |
| How is `REACT_APP_API_URL` read? | `getBaseURL()`: if `REACT_APP_USE_MOCK_API === "true"` → `http://localhost:3001`; else if `REACT_APP_API_URL` is set → that value verbatim; else in `development` → mock URL; else mock URL. |
| Is there an Axios instance? | Yes, one: `axios.create({ baseURL: BASE_URL, headers: { "Content-Type": "application/json", Accept: "application/json" }, timeout: 30000 })`. |
| Is `/api/v1` in the base URL? | **Yes — it is part of `REACT_APP_API_URL`.** `API_VERSION = "v1"` is exported but never used to build URLs. |
| Do endpoint paths begin with `/`? | Yes (`"/auth/login"`, `"/admin/orders/1"`). Axios joins `baseURL + path`, so `https://core.meghalisilk.in/api/v1` + `/auth/login` → `https://core.meghalisilk.in/api/v1/auth/login`. **The base URL must not end with a trailing slash** or Axios will produce `/api/v1//auth/login`. |
| Are headers added automatically? | `Content-Type: application/json`, `Accept: application/json` on every request. |
| Is the token attached automatically? | Yes. Request interceptor: if `config.url` includes `"/admin/"` → `Authorization: Bearer <sessionStorage.adminToken>`; otherwise `Bearer <authStorage.get("token")>` (sessionStorage first, then localStorage). If no token is stored no header is sent. |
| Mock vs production code paths? | Different. `IS_MOCK_API` is `true` when `REACT_APP_USE_MOCK_API === "true"` **or** the resolved base URL equals `http://localhost:3001`. Production uses the `else` branches documented here. |
| Central error handling? | Response interceptor: on **401** (except when the URL contains `/auth/login`) it clears the matching session (`admin`+`adminToken` for `/admin/` URLs, `user`+`token` otherwise). `>= 500` is logged. Errors are re-thrown; pages call `getErrorMessage(error)` which reads `error.response.data.message`, then the first value of `error.response.data.errors`, then `error.message`. |
| Response transformation? | `extractData(response)`: if `response.data` is an object with a `success` key → returns `response.data.data`; otherwise returns `response.data` as-is. `extractMeta(response)` returns `response.data.meta` (defined, never called). |

**Final decision:** the Laravel API is served under `https://core.meghalisilk.in/api/v1`, all routes in
this guide are relative to that prefix, and every success body is wrapped as
`{ "success": true, "data": … }`.

---

## 7. Complete API Inventory

Every Laravel-branch call in `src/services/api.js`, with the `apiService` method that issues it, its
auth scope, the screen(s) that consume it, and whether any screen calls it today.

Legend — Auth: **P** public, **C** customer Bearer token, **A** admin Bearer token.
"Called by" = files that invoke the method. "defined only" = present in `api.js` but no screen calls it
(implement anyway; keep the service layer intact).

### 7.1 Customer / public

| # | Method & path | `apiService` method | Auth | Called by |
| --- | --- | --- | --- | --- |
| 1 | `POST /auth/login` | `auth.login` | P | `AuthContext` ← `AuthModal` |
| 2 | `POST /auth/register` | `auth.register` | P | `AuthContext` ← `AuthModal` |
| 3 | `POST /auth/logout` | `auth.logout` | C | `AuthContext` |
| 4 | `GET /auth/user` | `auth.getUser` | C | defined only |
| 5 | `PUT /auth/user` | `auth.updateUser` | C | `AuthContext.updateUser` ← `Profile` |
| 6 | `PUT /auth/password` | `auth.changePassword` | C | `Profile` |
| 7 | `GET /products` | `products.getAll` (and `products.search` with `?search=`) | P | `Home`, `Products`, `SearchModal`, `SpecialOffers`, `AdminReviews`, `products.getRelated`, `products.getFrequentlyBoughtTogether` |
| 8 | `GET /products/{id}` | `products.getById` | P | `ProductDetails` (legacy numeric URL) |
| 9 | `GET /products/slug/{slug}` | `products.getBySlug` | P | `ProductDetails` |
| 10 | `GET /products/featured?limit=` | `products.getFeatured` | P | `Home`, `Wishlist` |
| 11 | `GET /products/trending?limit=` | `products.getTrending` | P | `Home`, `SearchModal` |
| 12 | `GET /products/category/{categoryId}` | `products.getByCategory` | P | defined only |
| 13 | `GET /products/{productId}/reviews` | `products.getReviews` | P | `ProductDetails` |
| 14 | `POST /products/{productId}/reviews` | `reviews.submit` | C | `OrderHistory` (ReviewModal) |
| 15 | `GET /categories` | `categories.getAll` | P | `Header`, `SidebarMenu`, `SearchModal`, `HeroSection`, `Home`, `Products`, `ProductDetails`, `SpecialOffers` |
| 16 | `GET /categories/{id}` | `categories.getById` | P | `ProductDetails` |
| 17 | `GET /categories/slug/{slug}` | `categories.getBySlug` | P | defined only |
| 18 | `GET /banners` | `banners.getAll` | P | `HeroSection` |
| 19 | `GET /hero/config` | `hero.getConfig` | P | `HeroSection` |
| 20 | `GET /cart` | `cart.getCart` | C | `CartContext` |
| 21 | `POST /cart` | `cart.addToCart` | C | `CartContext` |
| 22 | `PATCH /cart/{id}` | `cart.updateCartItem` | C | defined only |
| 23 | `DELETE /cart/{id}` | `cart.removeFromCart` | C | `CartContext` |
| 24 | `DELETE /cart` | `cart.clearCart` | C | defined only |
| 25 | `POST /orders` | `orders.create` | C | `OrderContext` ← `Checkout` |
| 26 | `GET /orders` | `orders.getByUserId` | C | `OrderContext`, `OrderHistory`, `Profile` |
| 27 | `GET /orders/{id}` | `orders.getById` | C | defined only |
| 28 | `GET /orders/number/{orderNumber}` | `orders.getByOrderNumber` | C | `OrderConfirmation` |
| 29 | `POST /orders/{id}/cancel` | `orders.cancel` | C | `OrderHistory` |
| 30 | `GET /wallet/balance` | `wallet.getBalance` | C | `Checkout`, `Profile` |
| 31 | `GET /wallet/transactions` | `wallet.getTransactions` | C | `Profile` |
| 32 | `GET /reviews/mine` | `reviews.getMine` | C | `OrderHistory`, `Profile` |
| 33 | `POST /returns` | `returns.create` | C | defined only |
| 34 | `GET /returns` | `returns.getByUserId` | C | defined only |
| 35 | `GET /returns/{id}` | `returns.getById` | C | defined only |
| 36 | `GET /coupons` | `coupons.getActive` | P | `SpecialOffers` |
| 37 | `POST /coupons/validate` | `coupons.validate` | P (userId in body) | `Checkout`, `CartDrawer` |
| 38 | `GET /wishlist` | `wishlist.get` | C | `WishlistContext` |
| 39 | `POST /wishlist` | `wishlist.add` | C | `WishlistContext` |
| 40 | `DELETE /wishlist/{id}` | `wishlist.remove` | C | `WishlistContext` |
| 41 | `GET /shipping/methods` | `shipping.getMethods` | P | `Checkout`, `ProductDetails` |
| 42 | `GET /settings` | `settings.get` | P | `StoreSettingsContext`, `AdminOrders` (invoice) |
| 43 | `GET /faqs` | `faqs.getAll` | P | `FaqContext` |
| 44 | `GET /deals/config` | `deals.getConfig` | P | `DealsConfigContext` |
| 45 | `POST /leads/contact` | `leads.createContact` | P | `Support` |
| 46 | `POST /leads/newsletter` | `leads.createNewsletter` | P | `Footer`, `Newsletter` |

### 7.2 Admin (all require the admin token; URL contains `/admin/`)

| # | Method & path | `apiService.admin` method | Called by |
| --- | --- | --- | --- |
| 47 | `POST /admin/auth/login` | `login` | `AdminContext` ← `AdminLogin` (public) |
| 48 | `POST /admin/auth/logout` | `logout` | `AdminContext` |
| 49 | `GET /admin/dashboard/stats` | `getDashboardStats` | `AdminDashboard` |
| 50 | `GET /admin/products` | `getProducts` | `AdminProducts`, `AdminDashboard`, `AdminFaqs`, `AdminSpecialOffers` |
| 51 | `GET /admin/products/{id}` | `getProduct` | defined only |
| 52 | `POST /admin/products` | `createProduct` | `AdminProducts` |
| 53 | `PUT /admin/products/{id}` | `updateProduct` | `AdminProducts` |
| 54 | `DELETE /admin/products/{id}` | `deleteProduct` | `AdminProducts` |
| 55 | `GET /admin/categories` | `getCategories` | `AdminCategories`, `AdminProducts`, `AdminSettings` |
| 56 | `POST /admin/categories` | `createCategory` | `AdminCategories` |
| 57 | `PUT /admin/categories/{id}` | `updateCategory` | `AdminCategories` |
| 58 | `DELETE /admin/categories/{id}` | `deleteCategory` | `AdminCategories` |
| 59 | `GET /admin/orders` (`?userId=`) | `getOrders` | `AdminOrders`, `AdminDashboard`, `AdminReturns`, `AdminUsers`, `AdminLayout` |
| 60 | `GET /admin/orders/{id}` | `getOrder` | `AdminReturns` |
| 61 | `PATCH /admin/orders/{id}` | `updateOrder` | `AdminOrders` |
| 62 | `POST /admin/orders/{id}/cancel` | `cancelOrder` | `AdminOrders` |
| 63 | `POST /admin/orders/{id}/refund/initiate` | `initiateOrderRefund` | `AdminOrders` |
| 64 | `POST /admin/orders/{id}/refund/complete` | `completeOrderRefund` | `AdminOrders` |
| 65 | `POST /admin/orders/{id}/refund/fail` | `failOrderRefund` | `AdminOrders` |
| 66 | `GET /admin/returns` | `getReturns` | `AdminReturns` |
| 67 | `GET /admin/returns/{id}` | `getReturn` | defined only |
| 68 | `POST /admin/returns` | `createReturn` | `AdminReturns` |
| 69 | `PATCH /admin/returns/{id}` | `updateReturn` (+ `scheduleReturnPickup`, `markReturnInTransit`) | `AdminReturns` |
| 70 | `GET /admin/payments` (`?orderId=`) | `getPayments` | `AdminPayments`, `AdminOrders` |
| 71 | `GET /admin/payments/{id}` | `getPayment` | defined only |
| 72 | `POST /admin/payments/{id}/refund` | `issueRefund` | `AdminPayments` |
| 73 | `GET /admin/refunds` | `getRefunds` | `AdminPayments` |
| 74 | `GET /admin/shipping-methods` | `getShippingMethods` | `AdminShipping` |
| 75 | `POST /admin/shipping-methods` | `createShippingMethod` | `AdminShipping` |
| 76 | `PUT /admin/shipping-methods/{id}` | `updateShippingMethod` | `AdminShipping` |
| 77 | `DELETE /admin/shipping-methods/{id}` | `deleteShippingMethod` | `AdminShipping` |
| 78 | `POST /admin/shipping/shiprocket/order` | `shiprocketCreateOrder` | defined only (no mock branch) |
| 79 | `GET /admin/shipping/shiprocket/track/{trackingNumber}` | `shiprocketTrack` | defined only (no mock branch) |
| 80 | `GET /admin/coupons` | `getCoupons` | `AdminCoupons`, `AdminSpecialOffers` |
| 81 | `POST /admin/coupons` | `createCoupon` | `AdminCoupons` |
| 82 | `PUT /admin/coupons/{id}` | `updateCoupon` | `AdminCoupons` |
| 83 | `DELETE /admin/coupons/{id}` | `deleteCoupon` | `AdminCoupons` |
| 84 | `GET /admin/reviews` | `getReviews` | `AdminReviews` |
| 85 | `POST /admin/reviews` | `createReview` | `AdminReviews` |
| 86 | `PATCH /admin/reviews/{id}` | `updateReview` | `AdminReviews` |
| 87 | `DELETE /admin/reviews/{id}` | `deleteReview` | `AdminReviews` |
| 88 | `GET /admin/users` | `getUsers` | `AdminUsers` |
| 89 | `GET /admin/users/{id}` | `getUser` | defined only |
| 90 | `PATCH /admin/users/{id}` | `updateUser` | `AdminUsers` |
| 91 | `GET /admin/leads` | `getLeads` | `AdminLeads`, `AdminLayout` |
| 92 | `GET /admin/leads/{id}` | `getLead` | defined only |
| 93 | `PATCH /admin/leads/{id}` | `updateLead` | `AdminLeads` |
| 94 | `DELETE /admin/leads/{id}` | `deleteLead` | `AdminLeads` |
| 95 | `GET /admin/settings` | `getSettings` | `AdminSettings`, `AdminShipping` |
| 96 | `PATCH /admin/settings/{section}` | `updateSettings` | `AdminSettings` (`store`, `payment`, `social`), `AdminShipping` (`shipping`) |
| 97 | `GET /admin/deals/config` | `getDealsConfig` | `AdminSpecialOffers` |
| 98 | `PUT /admin/deals/config` | `updateDealsConfig` | `AdminSpecialOffers` |
| 99 | `GET /admin/hero/config` | `getHeroConfig` | `AdminHeroSection`, `AdminSettings` |
| 100 | `PUT /admin/hero/config` | `updateHeroConfig` | `AdminHeroSection` |
| 101 | `GET /admin/banners` | `getBanners` | `AdminHeroSection`, `AdminSettings` |
| 102 | `POST /admin/banners` | `createBanner` | `AdminHeroSection` |
| 103 | `PUT /admin/banners/{id}` | `updateBanner` | `AdminHeroSection` |
| 104 | `DELETE /admin/banners/{id}` | `deleteBanner` | `AdminHeroSection` |
| 105 | `PUT /admin/banners/reorder` | `reorderBanners` | `AdminHeroSection` |
| 106 | `GET /admin/faqs` | `getFaqs` | `AdminFaqs`, `AdminSettings` |
| 107 | `POST /admin/faqs` | `createFaq` | `AdminFaqs` |
| 108 | `PUT /admin/faqs/{id}` | `updateFaq` | `AdminFaqs` |
| 109 | `DELETE /admin/faqs/{id}` | `deleteFaq` | `AdminFaqs` |
| 110 | `PUT /admin/faqs/reorder` | `reorderFaqs` | `AdminFaqs` |

**Totals:** 110 endpoints — 23 public (incl. both logins and `POST /coupons/validate`), 24
customer-authenticated, 63 admin. 15 are "defined only".

> **Route ordering note:** `PUT /admin/banners/reorder` and `PUT /admin/faqs/reorder` must be
> registered **before** `PUT /admin/banners/{id}` / `PUT /admin/faqs/{id}`, or constrain `{id}` with
> `->whereNumber('id')`. Likewise `GET /products/featured`, `/products/trending`, `/products/slug/{slug}`
> and `/products/category/{id}` must precede `GET /products/{id}`, and `GET /orders/number/{orderNumber}`
> must precede `GET /orders/{id}`.

---

## 8. Authentication

### 8.1 What the frontend expects (established from `api.js`, `authStorage.js`, `AuthContext.js`, `AdminContext.js`, `AuthModal.js`, `AdminLogin.js`)

Two **independent** sessions exist side by side, possibly in the same browser tab.

| | Customer | Admin |
| --- | --- | --- |
| Login request | `POST /auth/login` body `{ "email", "password", "remember": true\|false }` | `POST /admin/auth/login` body `{ "email", "password" }` (email trimmed) |
| Login response | `{ "success": true, "data": { "token": "<opaque string>", "user": { …safe user… } } }` | `{ "success": true, "data": { "token": "<opaque string>", "admin": { …safe admin… } } }` |
| Token storage | `authStorage.set("token", data.token, remember)` → **sessionStorage** by default, **localStorage** when `remember` is true (the other store is cleared). `user` JSON stored the same way by `AuthContext`. | `sessionStorage.adminToken`, `sessionStorage.admin` (JSON). Never localStorage. |
| Header | `Authorization: Bearer <token>` on every request whose URL does **not** contain `/admin/` | `Authorization: Bearer <adminToken>` on every request whose URL contains `/admin/` |
| Session restore | On page load only when **both** `user` and `token` exist | On page load only when **both** `admin` and `adminToken` exist |
| Logout | `POST /auth/logout` (fire-and-forget; storage cleared in `finally`) | `POST /admin/auth/logout` (same) |
| Failed login | `data.user` null/absent → "Invalid email or password"; a thrown error → `getErrorMessage()` text. **A 401 on `/auth/login` does not clear any session.** | `data.admin` null/absent → "Invalid admin credentials"; a thrown error → its message |
| 401 elsewhere | Interceptor removes `user` + `token`; page-level guards then show the sign-in prompt | Interceptor removes `admin` + `adminToken`; `AdminLayout` redirects to `/admin` |
| Token expiry behaviour | **FRONTEND BEHAVIOR NOT FOUND** for refresh — there is no refresh endpoint and no expiry check. An expired token simply produces 401 → session cleared → user signs in again. |
| Deactivated account | Mock throws "This account has been deactivated. Please contact support if you think this is a mistake." (`isActive === false`). Laravel must reject login for `isActive:false` users/admins with that message (`403` recommended, any 4xx with `message` works). |

The **user object** the frontend stores and reads (`AuthContext`, `Profile`, `Checkout`, `Header`):

```json
{
  "id": 1,
  "email": "user@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "phone": "+91 9876543210",
  "avatar": null,
  "addresses": [
    { "id": 1, "label": "Home", "firstName": "John", "lastName": "Doe", "phone": "+91 9876543210",
      "addressLine1": "123 Main Street", "addressLine2": "Apt 4B", "city": "Mumbai", "state": "Maharashtra",
      "postalCode": "400001", "country": "India", "isDefault": true }
  ],
  "isActive": true,
  "storeCredit": 0,
  "createdAt": "2025-01-15T10:00:00.000Z",
  "updatedAt": "2025-01-15T10:00:00.000Z"
}
```

Never include `password`. The **admin object**: `{ id, email, firstName, lastName, role, isActive, createdAt }`
(the audit timeline actor name is built from `firstName lastName`, falling back to `email`).

### 8.2 Recommended Laravel implementation: **Sanctum personal access tokens**

The contract is an opaque Bearer string returned in `data.token` with server-side revocation on logout.
Sanctum satisfies it directly; JWT is acceptable only if it reproduces exactly the same contract.

* Two Eloquent models with `HasApiTokens`: `User` (table `users`) and `Admin` (table `admins`).
* Two guards in `config/auth.php`: `customer` (provider `users`) and `admin` (provider `admins`). Since
  Sanctum resolves the token's owner from `personal_access_tokens.tokenable_type`, the simplest robust
  approach is: issue customer tokens with ability `customer`, admin tokens with ability `admin`, and
  protect routes with `auth:sanctum` + `ability:customer` / `ability:admin` middleware. A customer
  token presented on `/admin/*` must return **401** (the frontend treats 401 as "session is gone";
  403 would leave a dead session in storage — see Section 42).
* `POST /auth/login`: validate `email`+`password`; `Hash::check`; reject `isActive === false` with
  `403 { "message": "This account has been deactivated. Please contact support if you think this is a mistake." }`;
  on success `$user->createToken('customer', ['customer'])->plainTextToken`.
  `remember` may lengthen the token expiry (**BACKEND DECISION REQUIRED** — the frontend only uses
  it to pick the storage).
* `POST /auth/logout` / `POST /admin/auth/logout`: `$request->user()->currentAccessToken()->delete()`.
  Return `{ "success": true, "data": null }`.
* Passwords hashed with bcrypt (`Hash::make`). The seed passwords in `db.json` are plain text and must
  be hashed during migration (Section 37).
* Rate-limit both login routes (Section 32).

---

## 9. Authorization & Roles

Established from the code:

| Actor | How identified | What it may do |
| --- | --- | --- |
| Guest | no token | Everything marked **P** in Section 7: browse catalogue, categories, banners, hero config, coupons list, coupon validation, shipping methods, public settings, FAQs, deals config, submit contact/newsletter leads, register, login. Cart and wishlist are local-only for guests. |
| Customer | customer token | Own cart, wishlist, orders (create/list/lookup by number/cancel), wallet, own reviews (submit + list), profile update, password change. **Every customer read must be scoped to the token owner** — the Laravel branch never sends `userId` for these (`GET /orders`, `GET /cart`, `GET /wishlist`, `GET /wallet/*`, `GET /reviews/mine`). |
| Admin | admin token | Everything under `/admin/*`. |

**Roles:** the `admins` seed carries `role: "super_admin"`. **No screen reads `role`** and no route is
gated by it — every authenticated admin can do everything. `BACKEND DECISION REQUIRED`: keep the
`role` column (string) and treat every admin as full-access for parity; finer-grained roles can be
added later without frontend impact as long as the login response keeps returning `role`.

Ownership rules the server must enforce (the mock does not):

* `GET /orders/{id}`, `GET /orders/number/{orderNumber}`, `POST /orders/{id}/cancel` → order must belong
  to the token's user (404 otherwise).
* `DELETE /cart/{id}`, `PATCH /cart/{id}`, `DELETE /wishlist/{id}` → row must belong to the user.
* `POST /products/{productId}/reviews` → user must have a **delivered, not cancelled/returned/refunded**
  order containing the product (the storefront only shows the button in that state; the server must
  enforce it — `403` with a message).
* `PUT /auth/user` may change `firstName`, `lastName`, `phone`, `addresses` only. `email`, `isActive`,
  `storeCredit` are never client-writable (Profile shows email read-only: "Email cannot be changed").
* `PATCH /admin/users/{id}` only writes `isActive` today; do not expose password or email changes.

---

## 10. Database Architecture

### 10.1 Design principles applied

1. **The API response shape is fixed by the frontend (camelCase JSON);** the storage design is free.
   Use API Resources to map `snake_case` columns to the documented camelCase keys.
2. **Normalise what is genuinely relational** (order items, variants, addresses, history events, wallet
   ledger, refund ledger, FAQ↔product targeting, related products). **Keep as JSON only what is a
   snapshot or a free-form config** (order address snapshots, `pendingRefund`/`recall` stubs, gateway
   response, settings sections, hero/deals config).
3. **Integer money** (`INT UNSIGNED`/`BIGINT`) in whole rupees, serialized as numbers. Rating is
   `DECIMAL(2,1)`.
4. **Timestamps** stored as `DATETIME(3)` UTC, serialized as `2026-01-15T10:30:00.000Z`.
5. **No cascade deletes across business entities** (orders, payments, returns, refunds, reviews are
   history). Use `RESTRICT` or `SET NULL`. Cascade only true children (order_items, variants, images,
   history rows, cart/wishlist rows, tokens).
6. **Soft deletes** on `products`, `categories`, `users` (recommended so historical orders keep
   resolving); hard delete for cart/wishlist/banners/faqs/leads/shipping_methods/coupons.

### 10.2 Table list (28 tables)

| # | Table | Replaces `db.json` |
| --- | --- | --- |
| 1 | `users` | `users` (minus `addresses`) |
| 2 | `user_addresses` | `users[].addresses[]` |
| 3 | `admins` | `admins` |
| 4 | `personal_access_tokens` | (Sanctum) |
| 5 | `categories` | `categories` |
| 6 | `products` | `products` (scalar fields) |
| 7 | `product_images` | `products[].images[]` |
| 8 | `product_variants` | `products[].variants[]` |
| 9 | `product_links` | `products[].relatedProductIds[]`, `products[].frequentlyBoughtTogetherIds[]` |
| 10 | `cart_items` | `cart` |
| 11 | `wishlist_items` | `wishlist` |
| 12 | `orders` | `orders` (scalar + address snapshots + refund/recall stubs) |
| 13 | `order_items` | `orders[].items[]` |
| 14 | `order_status_history` | `orders[].statusHistory[]` |
| 15 | `payments` | `payments` |
| 16 | `payment_refunds` | `payments[].refunds[]` |
| 17 | `refunds` | `refunds` |
| 18 | `returns` | `returns` |
| 19 | `return_items` | `returns[].items[]` |
| 20 | `return_status_history` | `returns[].statusHistory[]` |
| 21 | `wallet_transactions` | `walletTransactions` |
| 22 | `coupons` | `coupons` |
| 23 | `reviews` | `reviews` |
| 24 | `shipping_methods` | `shipping_methods` |
| 25 | `leads` | `leads` |
| 26 | `banners` | `banners` |
| 27 | `faqs` + `faq_product` (pivot) | `faqs` (+ `productIds[]`) |
| 28 | `settings` (key/JSON) | `settings.*`, `heroConfig`, `dealsConfig` |

---

## 11. Database Tables

Column notation: `name TYPE [NULL] [DEFAULT]` — required means NOT NULL. Every table has `id BIGINT
UNSIGNED AUTO_INCREMENT PRIMARY KEY` unless stated. `created_at`/`updated_at` are `DATETIME(3) NULL`
(Laravel-managed) and serialize as `createdAt`/`updatedAt`.

### 11.1 `users`  ← `db.json.users`

Purpose: customer accounts. Seed: 4 rows.

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| email | VARCHAR(191) | no | | `email` — **UNIQUE** |
| password | VARCHAR(255) | no | | never serialized |
| first_name | VARCHAR(100) | no | | `firstName` |
| last_name | VARCHAR(100) | no | | `lastName` |
| phone | VARCHAR(30) | yes | NULL | `phone` (register sends `""` when blank → store NULL, serialize `""`/`null` — frontend uses `user.phone || ""`) |
| avatar | VARCHAR(500) | yes | NULL | `avatar` (always `null` in seed; no upload exists) |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| store_credit | INT UNSIGNED | no | 0 | `storeCredit` — **denormalised cache** of the wallet ledger balance; recompute from `wallet_transactions` on every ledger write |
| remember_token | VARCHAR(100) | yes | | (Laravel default, unused) |
| created_at / updated_at | DATETIME(3) | yes | | `createdAt` / `updatedAt` |
| deleted_at | DATETIME(3) | yes | NULL | soft delete |

Indexes: UNIQUE(email), INDEX(is_active).

### 11.2 `user_addresses`  ← `users[].addresses[]`

Purpose: saved addresses, serialized as `user.addresses[]` in the exact order stored.

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| user_id | BIGINT UNSIGNED FK→users.id ON DELETE CASCADE | no | | — |
| label | VARCHAR(50) | no | 'Home' | `label` (`Home`/`Work`/`Other` in UI, free text accepted) |
| first_name | VARCHAR(100) | no | | `firstName` |
| last_name | VARCHAR(100) | no | | `lastName` |
| phone | VARCHAR(30) | no | | `phone` |
| address_line1 | VARCHAR(255) | no | | `addressLine1` |
| address_line2 | VARCHAR(255) | yes | NULL | `addressLine2` (serialize `""` when null) |
| city | VARCHAR(100) | no | | `city` |
| state | VARCHAR(100) | no | | `state` |
| postal_code | VARCHAR(20) | no | | `postalCode` |
| country | VARCHAR(100) | no | 'India' | `country` |
| is_default | TINYINT(1) | no | 0 | `isDefault` |
| sort_order | INT | no | 0 | (array position) |

Index: (user_id, sort_order). **Exactly one** `is_default` per user when any address exists.

**Id reconciliation rule for `PUT /auth/user { addresses: [...] }`:** the frontend sends the whole array.
Existing rows carry the numeric `id` the API returned earlier; **new rows carry a client-generated
base-36 string id** (`generateId()` → e.g. `"m0x8kz3a9f1"`). For each incoming entry: if `id` matches
one of this user's existing address ids → update it; otherwise insert (ignore the client id). Delete
rows not present in the array. Return the user with the server ids.

### 11.3 `admins`  ← `db.json.admins`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| email | VARCHAR(191) UNIQUE | no | | `email` |
| password | VARCHAR(255) | no | | never serialized |
| first_name | VARCHAR(100) | no | | `firstName` |
| last_name | VARCHAR(100) | no | | `lastName` |
| role | VARCHAR(50) | no | 'admin' | `role` (seed: `super_admin`) |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| created_at / updated_at | DATETIME(3) | yes | | `createdAt` / `updatedAt` |

### 11.4 `personal_access_tokens` — standard Sanctum migration. Index `tokenable_type, tokenable_id`.

### 11.5 `categories`  ← `db.json.categories`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| name | VARCHAR(150) | no | | `name` |
| slug | VARCHAR(191) UNIQUE | no | | `slug` |
| description | TEXT | yes | NULL | `description` (serialize `""` when null) |
| image | VARCHAR(500) | yes | NULL | `image` (URL text) |
| parent_id | BIGINT UNSIGNED FK→categories.id ON DELETE RESTRICT | yes | NULL | `parentId` |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| sort_order | INT | no | 0 | `sortOrder` |
| show_in_main_menu | TINYINT(1) | no | 0 | `showInMainMenu` |
| menu_order | INT | no | 0 | `menuOrder` |
| created_at / updated_at | DATETIME(3) | yes | | |
| deleted_at | DATETIME(3) | yes | | soft delete |

Indexes: UNIQUE(slug), INDEX(parent_id), INDEX(is_active, sort_order). Reject a parent that is the
category itself or one of its descendants (cycle guard mirrors `AdminCategories`).

### 11.6 `products`  ← `db.json.products` (scalar part)

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| name | VARCHAR(255) | no | | `name` |
| slug | VARCHAR(191) UNIQUE | no | | `slug` |
| sku | VARCHAR(100) | yes | NULL | `sku` (serialize `""` when null) |
| short_description | TEXT | yes | NULL | `shortDescription` |
| description | LONGTEXT | yes | NULL | `description` |
| category_id | BIGINT UNSIGNED FK→categories.id ON DELETE RESTRICT | yes | NULL | `categoryId` |
| brand | VARCHAR(150) | yes | NULL | `brand` |
| price | INT UNSIGNED | no | 0 | `price` |
| compare_price | INT UNSIGNED | no | 0 | `comparePrice` |
| cost_price | INT UNSIGNED | no | 0 | `costPrice` — **admin only**; strip from public responses (`BACKEND DECISION`: recommended) |
| stock | INT | no | 0 | `stock` |
| low_stock_threshold | INT | no | 10 | `lowStockThreshold` |
| weight | DECIMAL(8,3) | no | 0 | `weight` (kg) |
| dim_length / dim_width / dim_height | DECIMAL(8,2) | yes | NULL | `dimensions: { length, width, height }` or `null` when all null (admin sends `null` for an all-zero box) |
| tags | JSON | no | '[]' | `tags` (array of strings; free-form, searched client-side) |
| featured | TINYINT(1) | no | 0 | `featured` |
| trending | TINYINT(1) | no | 0 | `trending` |
| hot | TINYINT(1) | no | 0 | `hot` |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| rating | DECIMAL(2,1) | no | 0.0 | `rating` — **server-computed** from approved reviews (see Section 27) |
| total_reviews | INT UNSIGNED | no | 0 | `totalReviews` — server-computed |
| meta_title | VARCHAR(255) | yes | NULL | `metaTitle` |
| meta_description | TEXT | yes | NULL | `metaDescription` |
| created_at / updated_at | DATETIME(3) | yes | | |
| deleted_at | DATETIME(3) | yes | | soft delete |

Indexes: UNIQUE(slug), INDEX(category_id), INDEX(is_active), INDEX(featured), INDEX(trending),
INDEX(sku), FULLTEXT(name, short_description, brand) optional.

Optional PDP fields read defensively by `ProductDetails.js` but **absent from `db.json` and from the
admin form**: `features[]`, `highlights[]`, `specifications{}`, `specs{}`, `attributes{}`, `weaveType`,
`origin`/`originRegion`, `craftTime`, `occasion`/`occasions`, `fabricAndCraft`/`craftStory`, `faqs[]`,
`image`. **FRONTEND BEHAVIOR NOT FOUND** for writing them; omit them from the API (the page renders
without them). Do not add columns unless the admin form is extended.

### 11.7 `product_images`  ← `products[].images[]`

| Column | Type | Null | JSON |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | — |
| product_id | FK→products.id ON DELETE CASCADE | no | — |
| url | VARCHAR(1000) | no | element of `images[]` |
| sort_order | INT | no | array position |

Serialized as `images: ["https://…", …]` in `sort_order`. Admin sends the full array on create/update
(replace all).

### 11.8 `product_variants`  ← `products[].variants[]`

| Column | Type | Null | JSON key |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | — (internal) |
| product_id | FK→products.id ON DELETE CASCADE | no | — |
| variant_key | VARCHAR(64) | no | **`id`** — the string the frontend uses (`"v1"`, `"v-1712345678901-123"`); UNIQUE (product_id, variant_key) |
| name | VARCHAR(150) | no | `name` |
| price | INT UNSIGNED | no | `price` |
| stock | INT | no | `stock` |
| sku | VARCHAR(100) | yes | `sku` (serialize `""`) |
| attributes | JSON | yes | `attributes` (e.g. `{ "Fabric": "Muga Silk", "Color": "Natural Gold" }`) — present in seed wishlist snapshots, rendered by `VariantSelector`; **not editable in the admin form today** |
| swatch_hex | VARCHAR(9) | yes | `swatchHex` |
| sort_order | INT | no | array position |

Serialize `id` = `variant_key`. Cart lines, order items, return items and restock all match on this
string. Admin sends the full array (replace all, matched by `id`).

### 11.9 `product_links`  ← `relatedProductIds[]`, `frequentlyBoughtTogetherIds[]`

| Column | Type | Null |
| --- | --- | --- |
| product_id | FK→products.id ON DELETE CASCADE | no |
| linked_product_id | FK→products.id ON DELETE CASCADE | no |
| type | ENUM('related','fbt') | no |
| sort_order | INT | no |

PK (product_id, linked_product_id, type). Serialized as ordered arrays of numeric ids
(`relatedProductIds`, `frequentlyBoughtTogetherIds`). Seed rows reference ids (28, 34, 35, 36) that no
longer exist in `products` — drop dangling ids during migration. **FRONTEND BEHAVIOR NOT FOUND** for
editing these in the admin (only read); serialize them, accept them on `POST/PUT /admin/products` if
present.

### 11.10 `cart_items`  ← `db.json.cart`

| Column | Type | Null | JSON key |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | `id` |
| user_id | FK→users.id ON DELETE CASCADE | no | `userId` |
| product_id | FK→products.id ON DELETE CASCADE | no | `productId` |
| variant_key | VARCHAR(64) | yes | `variantId` |
| quantity | INT UNSIGNED | no | `quantity` |
| created_at / updated_at | DATETIME(3) | yes | |

UNIQUE (user_id, product_id, variant_key). On read, **hydrate** `variantName`, `name`, `image`
(`images[0]`), `price` (variant price or product price), `comparePrice`, `currency` (`"INR"`), `stock`
(variant stock or product stock) from the product so the response matches the shape the frontend posts
(Section 25). The frontend sends those snapshot fields on `POST /cart`; the server may ignore them.

### 11.11 `wishlist_items`  ← `db.json.wishlist`

| Column | Type | Null | JSON key |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | `id` |
| user_id | FK→users.id ON DELETE CASCADE | no | `userId` |
| product_id | FK→products.id ON DELETE CASCADE | no | `productId` |
| created_at | DATETIME(3) | no | `createdAt` (frontend maps to `addedAt`) |

UNIQUE (user_id, product_id). Serialize with the nested `product` (Section 26).

### 11.12 `orders`  ← `db.json.orders`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| order_number | VARCHAR(40) UNIQUE | no | | `orderNumber` — **server-generated** |
| user_id | FK→users.id ON DELETE SET NULL | yes | | `userId` |
| coupon_id | FK→coupons.id ON DELETE SET NULL | yes | NULL | — |
| coupon_code | VARCHAR(50) | yes | NULL | `couponCode` |
| coupon_restored | TINYINT(1) | no | 0 | `couponRestored` |
| subtotal | INT UNSIGNED | no | | `subtotal` |
| discount_amount | INT UNSIGNED | no | 0 | `discountAmount` |
| shipping_amount | INT UNSIGNED | no | 0 | `shippingAmount` |
| shipping_method_id | FK→shipping_methods.id ON DELETE SET NULL | yes | NULL | — (**not sent by the frontend**, see Section 42) |
| tax_amount | INT UNSIGNED | no | 0 | `taxAmount` |
| cod_fee | INT UNSIGNED | no | 0 | `codFee` |
| total | INT UNSIGNED | no | | `total` |
| store_credit_used | INT UNSIGNED | no | 0 | `storeCreditUsed` |
| store_credit_returned | TINYINT(1) | no | 0 | `storeCreditReturned` |
| amount_payable | INT UNSIGNED | no | | `amountPayable` |
| payment_method | VARCHAR(20) | no | | `paymentMethod`: `card` \| `upi` \| `net_banking` \| `wallet` \| `cod` \| `store_credit` |
| payment_status | VARCHAR(20) | no | 'pending' | `paymentStatus`: `pending` \| `paid` \| `partially_paid` \| `partially_refunded` \| `refunded` \| `failed` \| `voided` |
| fulfillment_status | VARCHAR(25) | no | 'unfulfilled' | `fulfillmentStatus`: `unfulfilled` \| `partially_fulfilled` \| `fulfilled` \| `returned` \| `cancelled` |
| shipping_status | VARCHAR(20) | no | 'pending' | `shippingStatus`: `pending` \| `shipped` \| `delivered` \| `recalled` |
| tracking_number | VARCHAR(100) | yes | NULL | `trackingNumber` |
| tracking_url | VARCHAR(1000) | yes | NULL | `trackingUrl` |
| shiprocket_order_id | VARCHAR(100) | yes | NULL | `shiprocketOrderId` |
| notes | TEXT | yes | NULL | `notes` (serialize `""`) |
| shipping_address | JSON | no | | `shippingAddress` (snapshot object, Section 15) |
| billing_address | JSON | no | | `billingAddress` (snapshot) |
| cancel_reason | TEXT | yes | NULL | `cancelReason` |
| cancelled_at | DATETIME(3) | yes | NULL | `cancelledAt` |
| delivered_at | DATETIME(3) | yes | NULL | `deliveredAt` |
| refund_status | VARCHAR(20) | yes | NULL | `refundStatus`: `processing` \| `completed` \| `failed` \| absent |
| refund_method | VARCHAR(30) | yes | NULL | `refundMethod`: `original_payment` \| `bank_transfer` \| `upi` \| `store_credit` |
| refunded_amount | INT UNSIGNED | no | 0 | `refundedAmount` |
| refund_completed_at | DATETIME(3) | yes | NULL | `refundCompletedAt` |
| pending_refund | JSON | yes | NULL | `pendingRefund`: `{ amount, method, reason, reference, initiatedAt, by }` or `null` |
| recall | JSON | yes | NULL | `recall`: `{ trackingNumber, trackingUrl, carrier, scheduledAt, by }` or `null` |
| created_at / updated_at | DATETIME(3) | yes | | |

Indexes: UNIQUE(order_number), INDEX(user_id, created_at), INDEX(payment_status), INDEX(fulfillment_status),
INDEX(coupon_code, user_id).

Derived (not stored): `customerEmail`, `customerName` — from the `user` relation, included in
`GET /admin/orders` responses. `statusHistory[]` and `items[]` from child tables.

### 11.13 `order_items`  ← `orders[].items[]`

| Column | Type | Null | JSON key |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | — |
| order_id | FK→orders.id ON DELETE CASCADE | no | — |
| product_id | FK→products.id ON DELETE SET NULL | yes | `productId` |
| variant_key | VARCHAR(64) | yes | `variantId` |
| variant_name | VARCHAR(150) | yes | `variantName` (Order History reorder reads it; checkout sends the name suffixed instead) |
| name | VARCHAR(300) | no | `name` (snapshot, e.g. `"Eri Silk Shawl — Undyed Ivory - Undyed Ivory"`) |
| image | VARCHAR(1000) | yes | `image` |
| sku | VARCHAR(100) | yes | `sku` (serialize `""`) |
| price | INT UNSIGNED | no | `price` |
| quantity | INT UNSIGNED | no | `quantity` |
| subtotal | INT UNSIGNED | no | `subtotal` |

### 11.14 `order_status_history`  ← `orders[].statusHistory[]`

| Column | Type | Null | JSON key |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | — |
| order_id | FK→orders.id ON DELETE CASCADE | no | — |
| at | DATETIME(3) | no | `at` |
| by | VARCHAR(150) | no | `by` (`"Customer"`, `"System"`, or admin display name) |
| action | VARCHAR(255) | no | `action` |
| note | TEXT | yes | `note` (omit key when null — mock only adds `note` when non-empty) |

Serialized in insertion order (oldest first); Admin reverses client-side.

### 11.15 `payments`  ← `db.json.payments`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| order_id | FK→orders.id ON DELETE RESTRICT | no | | `orderId` |
| user_id | FK→users.id ON DELETE SET NULL | yes | | `userId` |
| amount | INT UNSIGNED | no | | `amount` |
| currency | CHAR(3) | no | 'INR' | `currency` |
| payment_method | VARCHAR(20) | no | | `paymentMethod`: `card` \| `upi` \| `net_banking` \| `wallet` \| `cod` \| `store_credit` |
| gateway | VARCHAR(30) | no | | `gateway`: `razorpay` \| `cod` \| `store_credit` |
| transaction_id | VARCHAR(100) | yes | NULL | `transactionId` |
| gateway_order_id | VARCHAR(100) | yes | NULL | `gatewayOrderId` |
| status | VARCHAR(25) | no | 'pending' | `status`: `pending` \| `captured` \| `refund_pending` \| `partially_refunded` \| `refunded` \| `failed` \| `voided` |
| gateway_response | JSON | yes | '{}' | `gatewayResponse` |
| refund_amount | INT UNSIGNED | no | 0 | `refundAmount` (running total) |
| refund_reason | VARCHAR(255) | yes | NULL | `refundReason` |
| pending_refund | JSON | yes | NULL | `pendingRefund`: `{ amount, method, reason, initiatedAt, by }` or `null` |
| store_credit_applied | INT UNSIGNED | no | 0 | `storeCreditApplied` |
| created_at / updated_at | DATETIME(3) | yes | | |

Derived: `orderNumber` (from order). Indexes: INDEX(order_id), INDEX(status), INDEX(transaction_id).

### 11.16 `payment_refunds`  ← `payments[].refunds[]`

| Column | Type | Null | JSON key |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | — |
| payment_id | FK→payments.id ON DELETE CASCADE | no | — |
| ref_key | VARCHAR(40) | no | `id` (seed: `"ref_seed0001"`, generated `"ref_<base36>"`) |
| amount | INT UNSIGNED | no | `amount` |
| reason | VARCHAR(255) | yes | `reason` |
| at | DATETIME(3) | no | `at` |
| by | VARCHAR(150) | no | `by` |

### 11.17 `refunds`  ← `db.json.refunds` (ledger)

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| refund_number | VARCHAR(40) UNIQUE | no | | `refundNumber` (`REF-YYYYMMDD-XXXX`) — server-generated |
| type | VARCHAR(30) | no | | `type`: `order_cancellation` \| `recall_refund` \| `order_refund` \| `return_refund` \| `payment_refund` |
| order_id | FK→orders.id ON DELETE SET NULL | yes | | `orderId` |
| return_id | FK→returns.id ON DELETE SET NULL | yes | | `returnId` |
| payment_id | FK→payments.id ON DELETE SET NULL | yes | | `paymentId` |
| amount | INT UNSIGNED | no | | `amount` |
| method | VARCHAR(30) | no | 'original_payment' | `method` |
| reason | VARCHAR(255) | yes | | `reason` |
| reference | VARCHAR(100) | yes | NULL | `reference` |
| status | VARCHAR(20) | no | 'pending' | `status`: `pending` \| `completed` \| `failed` |
| coupon_restored | TINYINT(1) | no | 0 | `couponRestored` |
| initiated_at | DATETIME(3) | no | | `initiatedAt` |
| settled_at | DATETIME(3) | yes | NULL | `settledAt` |
| by | VARCHAR(150) | no | | `by` |
| created_at / updated_at | DATETIME(3) | yes | | |

Derived: `orderNumber`, `returnNumber`.

### 11.18 `returns`  ← `db.json.returns`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| return_number | VARCHAR(40) UNIQUE | no | | `returnNumber` (`RET-YYYYMMDD-XXXX`) — server-generated |
| order_id | FK→orders.id ON DELETE RESTRICT | no | | `orderId` |
| user_id | FK→users.id ON DELETE SET NULL | yes | | `userId` |
| reason | VARCHAR(40) | no | | `reason`: `defective` \| `wrong_item` \| `not_as_described` \| `size_issue` \| `changed_mind` \| `other` (admin form values) |
| reason_details | TEXT | yes | NULL | `reasonDetails` |
| status | VARCHAR(25) | no | 'requested' | `status`: `requested` \| `approved` \| `pickup_scheduled` \| `in_transit` \| `received` \| `refunded` \| `rejected` |
| reject_reason | TEXT | yes | NULL | `rejectReason` |
| refund_amount | INT UNSIGNED | no | | `refundAmount` (requested, net of coupon share) |
| refund_status | VARCHAR(20) | no | 'pending' | `refundStatus`: `pending` \| `processed` |
| refund_method | VARCHAR(30) | no | 'original_payment' | `refundMethod`: `original_payment` \| `store_credit` \| `bank_transfer` \| `upi` |
| deduction_amount | INT UNSIGNED | no | 0 | `deductionAmount` |
| restocked | TINYINT(1) | no | 0 | `restocked` |
| store_credit_credited | TINYINT(1) | no | 0 | `storeCreditCredited` (idempotency flag) |
| return_tracking_number | VARCHAR(100) | yes | NULL | `returnTrackingNumber` |
| return_tracking_url | VARCHAR(1000) | yes | NULL | `returnTrackingUrl` |
| return_carrier | VARCHAR(100) | yes | NULL | `returnCarrier` |
| pickup_scheduled_at | DATETIME(3) | yes | NULL | `pickupScheduledAt` |
| images | JSON | no | '[]' | `images` (always `[]`; no upload UI) |
| notes | TEXT | yes | NULL | `notes` (serialize `""`) |
| created_at / updated_at | DATETIME(3) | yes | | |

Derived: `orderNumber`. Children: `return_items` (same columns as `order_items` minus image),
`return_status_history` (same as `order_status_history`).

### 11.19 `wallet_transactions`  ← `db.json.walletTransactions`

| Column | Type | Null | JSON key |
| --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | `id` |
| user_id | FK→users.id ON DELETE CASCADE | no | `userId` |
| type | ENUM('credit','debit') | no | `type` |
| amount | INT UNSIGNED | no | `amount` |
| reason | VARCHAR(255) | yes | `reason` |
| order_id | FK→orders.id ON DELETE SET NULL | yes | `orderId` |
| refund_id | FK→refunds.id ON DELETE SET NULL | yes | `refundId` |
| balance_before | INT UNSIGNED | no | `balanceBefore` |
| balance_after | INT UNSIGNED | no | `balanceAfter` |
| created_at | DATETIME(3) | no | `createdAt` |

Derived: `orderNumber`, `refundNumber`. Index (user_id, created_at). **The ledger is the source of truth
for the balance**; `users.store_credit` is a cache.

### 11.20 `coupons`  ← `db.json.coupons`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| code | VARCHAR(50) UNIQUE | no | | `code` (stored upper-case, trimmed; matched case-insensitively) |
| description | VARCHAR(255) | yes | NULL | `description` |
| type | ENUM('percentage','fixed') | no | | `type` |
| value | INT UNSIGNED | no | | `value` (percent or rupees) |
| min_order_amount | INT UNSIGNED | no | 0 | `minOrderAmount` |
| max_discount | INT UNSIGNED | yes | NULL | `maxDiscount` |
| usage_limit | INT UNSIGNED | yes | NULL | `usageLimit` |
| used_count | INT UNSIGNED | no | 0 | `usedCount` — server-owned |
| per_user_limit | INT UNSIGNED | yes | NULL | `perUserLimit` |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| expires_at | DATETIME(3) | yes | NULL | `expiresAt` |
| created_at / updated_at | DATETIME(3) | yes | | |

### 11.21 `reviews`  ← `db.json.reviews`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| product_id | FK→products.id ON DELETE CASCADE | no | | `productId` |
| user_id | FK→users.id ON DELETE SET NULL | yes | NULL | `userId` (null for admin-authored) |
| order_id | FK→orders.id ON DELETE SET NULL | yes | NULL | `orderId` |
| user_name | VARCHAR(150) | no | | `userName` (display name, e.g. `"Priya M."`) |
| rating | TINYINT UNSIGNED | no | | `rating` 1–5 |
| title | VARCHAR(255) | yes | '' | `title` |
| body | TEXT | yes | '' | `body` |
| status | ENUM('pending','approved','rejected') | no | 'pending' | `status` |
| is_verified_purchase | TINYINT(1) | no | 0 | `isVerifiedPurchase` |
| helpful_count | INT UNSIGNED | no | 0 | `helpfulCount` |
| source | VARCHAR(20) | yes | NULL | `source` (`"admin"` for admin-authored; omit otherwise) |
| photos | JSON | yes | NULL | `photos` (URL array; seed only, no upload) |
| created_at / updated_at | DATETIME(3) | yes | | |

UNIQUE (product_id, user_id) where user_id IS NOT NULL (enforce in code: one review per user per
product; a resubmission updates). Derived: `orderNumber`.

### 11.22 `shipping_methods`  ← `db.json.shipping_methods`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| name | VARCHAR(150) | no | | `name` |
| carrier | VARCHAR(100) | yes | NULL | `carrier` (free text; seed: `Shiprocket`, `Dunzo`, …) |
| description | VARCHAR(255) | yes | NULL | `description` |
| rate_type | ENUM('flat','free') | no | 'flat' | `rateType` |
| flat_rate | INT UNSIGNED | no | 0 | `flatRate` |
| free_above | INT UNSIGNED | yes | NULL | `freeAbove` |
| estimated_days | VARCHAR(20) | yes | NULL | `estimatedDays` (string, e.g. `"5-7"`, `"0"` = same day) |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| created_at / updated_at | DATETIME(3) | yes | | |

### 11.23 `leads`  ← `db.json.leads`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| type | ENUM('contact','newsletter') | no | | `type` |
| name | VARCHAR(150) | yes | NULL | `name` |
| email | VARCHAR(191) | no | | `email` |
| phone | VARCHAR(30) | yes | NULL | `phone` |
| order_number | VARCHAR(40) | yes | NULL | `orderNumber` |
| category | VARCHAR(30) | yes | NULL | `category`: `general` \| `order` \| `payment` \| `shipping` \| `returns` \| `product` (values seen in `Support.js` default + seed + `AdminLeads` icons) |
| subject | VARCHAR(255) | yes | NULL | `subject` |
| message | TEXT | yes | NULL | `message` |
| status | VARCHAR(20) | no | | `status`: contact → `new` \| `contacted` \| `resolved` \| `spam`; newsletter → `subscribed` \| `unsubscribed` |
| notes | TEXT | yes | '' | `notes` |
| created_at / updated_at | DATETIME(3) | yes | | |

Index (type, status), INDEX(email). Newsletter: UNIQUE-ish on (type='newsletter', email) enforced in
code (return the same success response for a duplicate — the Footer expects a uniform success).

### 11.24 `banners`  ← `db.json.banners`

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| title | VARCHAR(255) | no | | `title` |
| subtitle | TEXT | yes | '' | `subtitle` |
| eyebrow | VARCHAR(150) | yes | '' | `eyebrow` |
| cta | VARCHAR(100) | yes | '' | `cta` |
| link | VARCHAR(500) | no | '/products' | `link` |
| secondary_cta_label | VARCHAR(100) | yes | '' | `secondaryCtaLabel` |
| secondary_cta_link | VARCHAR(500) | yes | '' | `secondaryCtaLink` |
| background_type | ENUM('gradient','image','video') | no | 'gradient' | `backgroundType` |
| gradient | VARCHAR(500) | yes | | `gradient` (CSS string) |
| image | VARCHAR(1000) | yes | '' | `image` (URL) |
| image_position | VARCHAR(30) | no | 'right center' | `imagePosition`: `right center` \| `center center` \| `left center` \| `center top` \| `center bottom` |
| video_url | VARCHAR(1000) | yes | '' | `videoUrl` |
| video_poster | VARCHAR(1000) | yes | '' | `videoPoster` |
| overlay_opacity | TINYINT UNSIGNED | yes | NULL | `overlayOpacity` 0–100 or `null` (inherit) |
| text_align | ENUM('left','center','right') | no | 'left' | `textAlign` |
| duration_ms | INT UNSIGNED | no | 0 | `durationMs` (0 = inherit; else 1000–60000) |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| sort_order | INT | no | 0 | `sortOrder` |
| created_at / updated_at | DATETIME(3) | yes | | |

Serialize empty strings (not null) for text fields — the normalizer accepts both.

### 11.25 `faqs` + `faq_product`  ← `db.json.faqs`

`faqs`:

| Column | Type | Null | Default | JSON key |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | no | auto | `id` |
| question | VARCHAR(500) | no | | `question` |
| answer | TEXT | no | | `answer` (may contain `{freeShipping}`, `{codSentence}`, `{taxNote}` tokens — store verbatim) |
| placements | JSON | no | '["product","help","home"]' | `placements` (subset of `product`, `help`, `home`) |
| is_active | TINYINT(1) | no | 1 | `isActive` |
| sort_order | INT | no | 0 | `sortOrder` |
| created_at / updated_at | DATETIME(3) | yes | | |

`faq_product` (pivot): `faq_id FK→faqs.id CASCADE`, `product_id FK→products.id CASCADE`, PK both.
Serialized as `productIds: [1, 13]`.

### 11.26 `settings`  ← `db.json.settings.*`, `heroConfig`, `dealsConfig`

| Column | Type | Null |
| --- | --- | --- |
| section | VARCHAR(40) PRIMARY KEY | no |
| data | JSON | no |
| updated_at | DATETIME(3) | yes |

Rows: `store`, `shipping`, `payment`, `notifications`, `seo`, `social`, `hero_config`, `deals_config`.
The exact JSON for each is in Section 15. `PATCH /admin/settings/{section}` **merges** keys into `data`;
`PUT /admin/hero/config` and `PUT /admin/deals/config` **replace** `data`. `shipping.shiprocketPassword`
must be encrypted at rest (`Crypt::encryptString`) and **never returned** by any endpoint.

---

## 12. Relationships

```
users 1──* user_addresses          users 1──* cart_items        users 1──* wishlist_items
users 1──* orders                  users 1──* returns           users 1──* reviews (nullable)
users 1──* wallet_transactions     users 1──* payments (nullable)

categories 1──* categories (parent_id, self)     categories 1──* products (nullable)

products 1──* product_images       products 1──* product_variants
products *──* products (product_links: related / fbt, ordered)
products 1──* reviews              products 1──* cart_items     products 1──* wishlist_items
products 1──* order_items (SET NULL)   products *──* faqs (faq_product)

orders 1──* order_items            orders 1──* order_status_history
orders 1──* payments (1 in practice)   orders 1──* refunds         orders 1──* returns
orders 1──* wallet_transactions    orders *──1 coupons (nullable, by coupon_id + snapshot coupon_code)
orders *──1 shipping_methods (nullable)

payments 1──* payment_refunds      payments 1──* refunds (nullable)
returns 1──* return_items          returns 1──* return_status_history   returns 1──* refunds (nullable)
refunds 1──* wallet_transactions (nullable)

admins 1──* personal_access_tokens (morph)   users 1──* personal_access_tokens (morph)
settings (key/value)   banners (standalone)   shipping_methods (standalone)   leads (standalone)
```

Eloquent relationship names to use (so Resources read naturally): `User::addresses()`,
`Product::images()`, `Product::variants()`, `Product::related()` / `Product::frequentlyBoughtTogether()`
(belongsToMany via `product_links` with `type` scope, ordered by `sort_order`), `Order::items()`,
`Order::statusHistory()`, `Order::payments()`, `Order::user()`, `Return::items()`,
`Return::statusHistory()`, `Payment::refundEntries()` (`payment_refunds`), `Faq::products()`.

Cascade rules (summary): CASCADE only for dependent children listed above and for tokens; RESTRICT
`categories→products` (blocked delete with 409), RESTRICT `orders→payments/returns`; SET NULL for
product references inside orders/returns/reviews/cart snapshots so history survives product deletion.

---

## 13. API Endpoint Specifications

Conventions used in every block:

* **Base:** `https://core.meghalisilk.in/api/v1`. All paths below are relative to it.
* **Headers:** `Accept: application/json`, `Content-Type: application/json` on every request;
  `Authorization: Bearer <token>` where Auth ≠ Public.
* **Success envelope:** `{ "success": true, "data": <payload> }`. `meta` optional and unused.
* **Error envelope:** `{ "message": "...", "errors": { "field": ["..."] } }` (Section 31).
* **Status codes:** 200 for reads/updates/deletes, 201 for creates (the frontend does not check the
  exact 2xx code), 401 unauthenticated, 403 forbidden/ownership, 404 not found, 409 conflict, 422
  validation.
* "Consumers" lists the `apiService` method and the screens.
* Resource shapes (`User`, `Product`, `Order`, …) are defined once in Section 15.

### 13.1 Customer authentication

#### `POST /auth/login` — Public
Purpose: customer sign-in. Body: `{ "email": string, "password": string, "remember": boolean }`.
Validation: email required/email; password required. Reject unknown email or wrong password with
`401 { "message": "Invalid email or password" }`; reject `isActive:false` with `403 { "message": "This account has been deactivated. Please contact support if you think this is a mistake." }`.
Success `200`: `{ "success": true, "data": { "token": "1|abc…", "user": User } }`.
DB: read `users`; insert `personal_access_tokens`. Consumers: `auth.login` ← `AuthContext`/`AuthModal`.
Note: rate-limit (Section 32). `remember` may extend token TTL (`BACKEND DECISION REQUIRED`).

#### `POST /auth/register` — Public
Body: `{ "firstName", "lastName", "email", "phone": "+919876543210" | "", "password", "password_confirmation" }`.
Validation: firstName/lastName required max 100; email required, unique (message: `"An account with this email already exists. Please log in instead."`); phone optional, if present must be `+91` + 10 digits (frontend sends `+91XXXXXXXXXX`); password required min 6, `confirmed`.
Success `201`: `{ "success": true, "data": User }` (no token — the modal switches to the login tab).
DB: insert `users` with `avatar:null`, `isActive:true`, `storeCredit:0`, no addresses.
Consumers: `auth.register` ← `AuthModal`.

#### `POST /auth/logout` — Customer
No body. Revokes the presented token. Success `200 { "success": true, "data": null }`.
Consumers: `auth.logout` ← `AuthContext.logout`, `Profile`.

#### `GET /auth/user` — Customer (defined only)
Success `200 { "success": true, "data": User }`. Consumers: `auth.getUser` (unused).

#### `PUT /auth/user` — Customer
Purpose: profile update **or** address-book replace. Body is one of:
`{ "firstName", "lastName", "phone" }` (Profile > Personal details) or
`{ "addresses": [ Address, … ] }` (add / edit / delete / set-default all send the whole array).
Validation: firstName/lastName required when present; phone optional, Indian mobile (`/^(\+91|0)?[6-9]\d{9}$/` after stripping spaces/dashes) when non-empty; each address: label, firstName, lastName, phone (valid), addressLine1, city, state, postalCode required; addressLine2 optional; country default `"India"`; `isDefault` boolean; exactly one default (server enforces: first entry becomes default when none flagged).
Ignore/forbid `email`, `isActive`, `storeCredit`, `password`.
Success `200 { "success": true, "data": User }` (the frontend merges its own updates into local state and ignores the body except for errors, but returning the user is correct).
DB: update `users`; upsert/delete `user_addresses` (Section 11.2 id reconciliation).
Consumers: `auth.updateUser` ← `AuthContext.updateUser` ← `Profile`.

#### `PUT /auth/password` — Customer
Body: `{ "current_password", "password", "password_confirmation" }` (snake_case — the only place).
Validation: current_password must match (`422 { "message": "Current password is incorrect" }`); password min 8, `confirmed`. (Profile enforces min 8 client-side; AuthModal registration enforces min 6 — `BACKEND DECISION REQUIRED`: recommend min 8 everywhere, which only tightens registration.)
Success `200 { "success": true, "data": { "success": true } }`. DB: update `users.password`; optionally revoke other tokens. Consumers: `auth.changePassword` ← `Profile`.

### 13.2 Products (public)

#### `GET /products` — Public
Purpose: full visible catalogue. Query: `search` (optional; only `products.search()` sends it, and no screen calls that method today). No pagination params are sent.
Returns **all products with `isActive: true`** as a plain array (the frontend additionally filters `isActive !== false`). When `search` is present filter case-insensitively on `name`, `shortDescription`, `brand`, `tags`.
Success `200 { "success": true, "data": [ Product, … ] }`. Eager-load images, variants, links.
Consumers: `products.getAll` ← `Home`, `Products`, `SearchModal`, `SpecialOffers`, `AdminReviews`, `getRelated`, `getFrequentlyBoughtTogether`.
Note: `Products.js` does every filter/sort/page in the browser from this array (Sections 17–20). Return the whole visible catalogue; **do not truncate**.

#### `GET /products/{id}` — Public
Path: numeric id. Returns the product **only if `isActive`** (else `404` — the PDP treats null/404 as not found and the mock hides drafts). Success `200 { "success": true, "data": Product }`.
Consumers: `products.getById` ← `ProductDetails` (legacy `/products/12` URLs; the page then redirects to the slug).

#### `GET /products/slug/{slug}` — Public
Same as above keyed by `slug`. `404` when missing or inactive. Consumers: `products.getBySlug` ← `ProductDetails`.

#### `GET /products/featured?limit=8` — Public
Active products with `featured:true`, ordered by `updated_at` desc (`BACKEND DECISION`: any stable order), limited to `limit` (default 10, max 50). Consumers: `products.getFeatured` ← `Home` (8), `Wishlist`, `SearchModal`.

#### `GET /products/trending?limit=8` — Public
Same with `trending:true`. Consumers: `products.getTrending` ← `Home`, `SearchModal`.

#### `GET /products/category/{categoryId}` — Public (defined only)
Active products whose `categoryId` is the given category **or any descendant** (parent-includes-children rule from `utils/categories.js`). Consumers: `products.getByCategory` (unused).

#### `GET /products/{productId}/reviews` — Public
Approved reviews for the product, newest first. `200 { "success": true, "data": [ Review, … ] }`. Product need not be active (harmless) but must exist (`404`). Consumers: `products.getReviews` ← `ProductDetails`.

#### `POST /products/{productId}/reviews` — Customer
Purpose: purchase-gated create-or-update review. Body: `{ "rating": 1-5, "title": string, "body": string, "orderId": number|null }`.
Rules (from `reviews.submit` mock + `OrderHistory.isReviewable`): the user must have an order containing this product whose derived status is **delivered** (`shippingStatus:"delivered"`, `fulfillmentStatus` not `cancelled`/`returned`, `paymentStatus` not `failed`/`refunded`); otherwise `403 { "message": "You can review this product once it has been delivered." }`. One review per (user, product): if one exists **update** it. Every create/update sets `status:"pending"`, `isVerifiedPurchase:true`, `userName` = `"First L."` (first name + last initial, or first name, or email local part), `orderId`/`orderNumber` from the order.
Success `201`/`200 { "success": true, "data": Review }`. DB: upsert `reviews`; product `rating`/`totalReviews` are recomputed only from **approved** reviews (Section 27).
Consumers: `reviews.submit` ← `OrderHistory` (`ReviewModal`).

### 13.3 Categories (public)

#### `GET /categories` — Public
All categories (the frontend filters `isActive !== false` and sorts by `sortOrder` itself; returning only active ones is also compatible — `BACKEND DECISION`: return active only). Plain array of `Category`. Consumers: `categories.getAll` ← `Header`, `SidebarMenu`, `SearchModal`, `HeroSection`, `Home`, `Products`, `ProductDetails`, `SpecialOffers`.

#### `GET /categories/{id}` — Public
`200 { "success": true, "data": Category }`, `404` when missing. Consumers: `categories.getById` ← `ProductDetails` (breadcrumb/eyebrow).

#### `GET /categories/slug/{slug}` — Public (defined only)
Same by slug. Consumers: `categories.getBySlug` (unused).

### 13.4 Hero section (public)

#### `GET /banners` — Public
All hero slides (the storefront normalises and drops `isActive:false` itself; returning only active rows is also fine — `BACKEND DECISION`: return active rows ordered by `sortOrder`). Array of `Banner`. Consumers: `banners.getAll` ← `HeroSection`.

#### `GET /hero/config` — Public
`200 { "success": true, "data": HeroConfig }` (the stored `hero_config` settings row). Consumers: `hero.getConfig` ← `HeroSection`.

### 13.5 Cart — Customer

#### `GET /cart`
Current user's cart lines, hydrated (Section 25). `200 { "success": true, "data": [ CartItem, … ] }`. Consumers: `cart.getCart` ← `CartContext` (on login and before every sync).

#### `POST /cart`
Body (what `CartContext.replaceApiCart` sends): `{ "productId", "variantId": string|null, "variantName": string|null, "name", "image", "price", "comparePrice", "currency": "INR", "quantity", "stock"?: number, "userId" }`.
Server uses `productId`, `variantId`, `quantity` only (validate product active, variant exists when given, quantity ≥ 1; clamp to stock or reject with 422 — `BACKEND DECISION`: clamp). `userId` must equal the token user (ignore otherwise). Upsert on (user, product, variant) — if the line exists, **set** quantity. Success `201 { "success": true, "data": CartItem }` with the server `id`.
Consumers: `cart.addToCart` ← `CartContext`.

#### `PATCH /cart/{id}` (defined only)
Body: `{ "quantity" }` (any subset of the line). Ownership enforced. `200 { data: CartItem }`. Consumers: `cart.updateCartItem` (unused).

#### `DELETE /cart/{id}`
Ownership enforced. `200 { "success": true, "data": null }`; `404` if absent. Consumers: `cart.removeFromCart` ← `CartContext` (every row, on every sync).

#### `DELETE /cart` (defined only)
Deletes all of the user's lines. `200`. Consumers: `cart.clearCart` (unused — `CartContext.clearCart` empties local state and the sync effect mirrors it by deleting rows one by one).

### 13.6 Orders — Customer

#### `POST /orders`
Purpose: place an order. The full client payload is in Section 14.1. **The server must recompute
every money field, statuses and numbers from its own data** (Section 24) and treat the client values
as display hints. Runs in one transaction:

1. Validate items (product active, variant exists, quantity ≥ 1, stock sufficient), address fields (Section 16), `paymentMethod` ∈ `card|upi|net_banking|wallet|cod|store_credit`.
2. Recompute `subtotal`, coupon discount (revalidate coupon: active, not expired, usage limit, per-user limit, min amount), `shippingAmount` (Section 23), `taxAmount` (Section 24.2), `codFee`, `total`, `storeCreditUsed` (≤ wallet balance and ≤ total), `amountPayable`.
3. Generate `orderNumber` (server-owned; ignore the client's).
4. Insert `orders`, `order_items`; append `order_status_history` `{ by:"Customer", action:"Order placed" }`.
5. Decrement product/variant stock.
6. Insert the `payments` row (Section 22).
7. If `couponCode`: `coupons.used_count += 1`.
8. If `storeCreditUsed > 0`: insert a **debit** `wallet_transactions` row and refresh `users.store_credit`.
9. Clear the user's `cart_items` (`BACKEND DECISION`: the frontend clears its local cart and will mirror the empty cart; clearing server-side is harmless and recommended).

Success `201 { "success": true, "data": Order }` — the frontend reads `order.orderNumber` (or `id`) to navigate to `/order-confirmation/{orderNumber}`.
Errors: `422` for validation/stock/coupon problems with a `message`; `401` when signed out.
Consumers: `orders.create` ← `OrderContext.createOrder` ← `Checkout.placeOrder`.

#### `GET /orders`
Token user's orders, newest first (the pages re-sort anyway), each with `items[]` and `statusHistory[]`. Plain array. Consumers: `orders.getByUserId` ← `OrderContext`, `OrderHistory`, `Profile`.

#### `GET /orders/{id}` (defined only) — ownership enforced, `404` otherwise. Consumers: `orders.getById` (unused).

#### `GET /orders/number/{orderNumber}`
Ownership enforced (`404` when not the user's). `200 { data: Order }`. Consumers: `orders.getByOrderNumber` ← `OrderConfirmation` (also accepts the numeric id as a fallback path param because Checkout navigates with `orderNumber || id`).

#### `POST /orders/{id}/cancel`
Body: `{ "reason": "Cancelled by customer" }` (string, optional; default that text).
Allowed only while the derived status is **processing** (`fulfillmentStatus` ∈ `unfulfilled|partially_fulfilled`, `shippingStatus` not `shipped|delivered`, not already cancelled/returned). Otherwise `409 { "message": "This order can no longer be cancelled." }`.
Runs the shared cancellation cascade (Section 24.4) with options derived from the order: `restock:true`; if `amountPayable > 0` and `paymentStatus ∈ paid|partially_refunded` → `refund:{ method: online ? "original_payment" : "bank_transfer" }`; else if `amountPayable > 0` → `voidPayment:true`. Actor `"Customer"`.
Success `200 { "success": true, "data": Order }` (the page merges the returned order over its row).
Consumers: `orders.cancel` ← `OrderHistory`.

### 13.7 Wallet — Customer

#### `GET /wallet/balance`
`200 { "success": true, "data": { "balance": 3918 } }` (the frontend also accepts a bare number). Balance = Σ credits − Σ debits from `wallet_transactions`, floored at 0. Consumers: `wallet.getBalance` ← `Checkout`, `Profile`.

#### `GET /wallet/transactions`
Newest first, array of `WalletTransaction`. Consumers: `wallet.getTransactions` ← `Profile`.

### 13.8 Reviews — Customer

#### `GET /reviews/mine`
All of the user's reviews, **any status**, array of `Review`. Consumers: `reviews.getMine` ← `OrderHistory` (per-item "your review" chip), `Profile` (count).

### 13.9 Returns — Customer (all defined only)

The storefront has **no return-request form**; a customer asks via Support and the admin records the
return (`POST /admin/returns`). Implement these for service-layer completeness:

* `POST /returns` — body `{ orderId, items:[{productId, variantId, quantity}], reason, reasonDetails, refundMethod }`; server computes `refundAmount` net of coupon share, generates `returnNumber`, `status:"requested"`; `201 { data: Return }`.
* `GET /returns` — the user's returns. * `GET /returns/{id}` — ownership enforced.

### 13.10 Coupons

#### `GET /coupons` — Public
Active coupons for the Special Offers page (the page additionally drops expired/exhausted ones and reads `code`, `type`, `value`, `description`, `minOrderAmount`, `maxDiscount`, `expiresAt`, `usageLimit`, `usedCount`, `perUserLimit`). Return `isActive:true` and not expired. Consumers: `coupons.getActive` ← `SpecialOffers`.

#### `POST /coupons/validate` — Public (optional customer token)
Body: `{ "code": "MUGA500", "orderAmount": 8999, "userId": 3 | null }` (`userId` comes from local storage; **use the token's user when present and ignore the body value**).
Checks in this order, each a `422` with the exact message: unknown/inactive → `"Invalid coupon code"`; `expiresAt < now` → `"Coupon has expired"`; `usageLimit && usedCount >= usageLimit` → `"Coupon usage limit reached"`; `perUserLimit` and the user's non-restored orders with this code ≥ limit → `"You have already used this coupon"` (or `"… this coupon N times"`); `orderAmount < minOrderAmount` → `"Minimum order amount is ₹5,000"`.
Success `200 { "success": true, "data": Coupon }` — Checkout/CartDrawer compute the discount from `type`, `value`, `maxDiscount`, `minOrderAmount`, `code`.
Consumers: `coupons.validate` ← `Checkout`, `CartDrawer`.

### 13.11 Wishlist — Customer

#### `GET /wishlist`
Array of `WishlistItem` (nested `product`, Section 26). Consumers: `wishlist.get` ← `WishlistContext`.

#### `POST /wishlist`
Body: the product snapshot the context builds — `{ "productId", "slug", "name", "image", "brand", "category", "price", "comparePrice", "rating", "totalReviews", "shortDescription", "variants", "stock", "trending", "hot", "addedAt", "userId" }`. Server uses **`productId` only**. If the pair already exists return the existing row (`200`) instead of erroring. Success `201 { data: WishlistItem }` — the context only needs `data.id`.
Consumers: `wishlist.add` ← `WishlistContext`.

#### `DELETE /wishlist/{id}`
Ownership enforced; `200`; `404` when absent. Consumers: `wishlist.remove` ← `WishlistContext`.

### 13.12 Shipping / settings / FAQs / deals (public reads)

#### `GET /shipping/methods` — Public
Active methods only, array of `ShippingMethod`. Checkout auto-selects the first; keep a stable order (`id` asc or a `sortOrder` if you add one). Consumers: `shipping.getMethods` ← `Checkout`, `ProductDetails`.

#### `GET /settings` — Public
`200 { "success": true, "data": { "store": {...}, "payment": {...}, "social": {...} } }` — **only** the public sections (Section 15.14). Never return `shipping.shiprocketPassword`, `notifications.*Email` addresses, or `seo` ids here (`BACKEND DECISION`: `seo` may be public if a future frontend needs it; today nothing reads it). Consumers: `settings.get` ← `StoreSettingsContext`, `AdminOrders` (invoice header).

#### `GET /faqs` — Public
All FAQs (the context filters `isActive` and placements itself) or active only. Array of `Faq`. Consumers: `faqs.getAll` ← `FaqContext`.

#### `GET /deals/config` — Public
`200 { data: DealsConfig }`. Consumers: `deals.getConfig` ← `DealsConfigContext`.

### 13.13 Leads — Public

#### `POST /leads/contact`
Body (from `Support.js`): `{ "name", "email", "phone": "", "orderNumber": "", "category": "general", "subject", "message" }`.
Validation: name required; email required/email; phone optional (Indian mobile when present); subject required; message required min 20 chars (`MESSAGE_MIN` in `Support.js`); category ∈ `general|order|payment|shipping|returns|product` (default `general`); orderNumber optional.
Server sets `type:"contact"`, `status:"new"`, `notes:""`. Success `201 { data: Lead }`.
Rate-limit (Section 32). Consumers: `leads.createContact` ← `Support`.

#### `POST /leads/newsletter`
Body: `{ "email" }`. Validation: required/email. Creates `type:"newsletter"`, `status:"subscribed"`, other fields null. **Duplicate email → return the same `201` success** (the footer must not reveal existing subscriptions). Consumers: `leads.createNewsletter` ← `Footer`, `Newsletter`.

### 13.14 Admin authentication

#### `POST /admin/auth/login` — Public
Body `{ "email", "password" }`. Same rules as customer login against `admins`; `403` for `isActive:false`. Success `200 { "success": true, "data": { "token": "…", "admin": Admin } }`. Consumers: `admin.login` ← `AdminContext` ← `AdminLogin`.

#### `POST /admin/auth/logout` — Admin
Revokes the admin token. `200`. Consumers: `admin.logout`.

### 13.15 Admin dashboard

#### `GET /admin/dashboard/stats` — Admin
`200 { "success": true, "data": { "totalProducts", "totalOrders", "totalRevenue", "totalUsers", "pendingOrders", "pendingReturns", "lowStockProducts", "activeCoupons" } }` computed exactly as the mock does (Section 30). Consumers: `admin.getDashboardStats` ← `AdminDashboard`.

### 13.16 Admin products

* `GET /admin/products` — all products **including drafts** (`isActive:false`) and soft-deleted excluded; full `Product` shape incl. `costPrice`. No query params are sent (the screen filters by name/SKU/brand and category in the browser). Consumers: `admin.getProducts` ← `AdminProducts`, `AdminDashboard`, `AdminFaqs`, `AdminSpecialOffers`.
* `GET /admin/products/{id}` — (defined only) `200 { data: Product }`.
* `POST /admin/products` — body Section 14.3. Validation Section 16. Slug must be unique (the admin de-duplicates client-side but the server must enforce; `422`). Success `201 { data: Product }`. DB: insert product, images, variants, links.
* `PUT /admin/products/{id}` — body: **the whole product** (the screen spreads the original row then its edits, so `rating`, `totalReviews`, `createdAt`, `relatedProductIds`, `frequentlyBoughtTogetherIds`, `id` all arrive). Treat as full replace of editable fields; **ignore** `id`, `rating`, `totalReviews`, `createdAt`, `updatedAt`. Replace images/variants arrays (match variants by `id` string to keep `variant_key` stable). Success `200 { data: Product }`.
* `DELETE /admin/products/{id}` — soft delete; `200 { data: null }`; `404` if absent. `BACKEND DECISION`: also remove `cart_items`/`wishlist_items`/`product_links`/`faq_product` rows (cascade on soft delete must be done in code). Order/return/review history keeps `product_id` (SET NULL on hard delete only).

### 13.17 Admin categories

* `GET /admin/categories` — every category including inactive. Consumers: `AdminCategories`, `AdminProducts`, `AdminSettings`.
* `POST /admin/categories` — body Section 14.4; `201 { data: Category }`.
* `PUT /admin/categories/{id}` — full record (screen spreads the original); ignore `id`/`createdAt`; cycle guard on `parentId`; `200`.
* `DELETE /admin/categories/{id}` — **must refuse** when any category has `parentId = id` or any product has `categoryId = id`: `409 { "message": "Cannot delete this category — 2 subcategories and 5 products still reference it. Reassign or remove them first." }` (wording from `api.js`, singular/plural adjusted). Success `200`.

### 13.18 Admin orders

* `GET /admin/orders` — Query: `userId` (optional; `AdminUsers` passes it). Returns **all** orders (newest first), each with `items`, `statusHistory`, `pendingRefund`, `recall`, plus derived `customerEmail` and `customerName` (from the user; `null` when no user). Consumers: `AdminOrders`, `AdminDashboard`, `AdminReturns`, `AdminUsers`, `AdminLayout`.
* `GET /admin/orders/{id}` — `200 { data: Order }`. Consumers: `AdminReturns` (source order for coupon / full-return context).
* `PATCH /admin/orders/{id}` — partial update. Body: any of `fulfillmentStatus`, `shippingStatus`, `trackingNumber`, `trackingUrl`, `notes`, `deliveredAt`, `paymentStatus`, `shippingAddress` (object), plus optional `event: { "action": string, "note": string }`. Server merges the fields, and when `event` is present appends a `statusHistory` row with `at = now`, `by = "<admin firstName lastName>"`, `action`, `note` (omit `note` key when empty). Exact payloads the screen sends are in Section 14.5. Validation: statuses ∈ their enums; `trackingUrl` must be `http(s)://…` when non-empty; `deliveredAt` ISO date. Success `200 { data: Order }`.
* `POST /admin/orders/{id}/cancel` — body `{ "reason": string (required), "restock": boolean, "refund"?: { "method": "original_payment|bank_transfer|upi|store_credit", "amount"?: number, "reference"?: string }, "voidPayment"?: true, "recall"?: { "trackingNumber": string|null, "trackingUrl": string|null, "carrier"?: string|null } }`. Runs the cascade in Section 24.4 with actor = admin name. Refuse (`409`) when `shippingStatus === "delivered"` or already cancelled/returned. `200 { data: Order }`.
* `POST /admin/orders/{id}/refund/initiate` — body `{ "amount": number > 0, "method", "reason": string (required), "reference": string|null }`. `amount` ≤ payment remaining (`amount − refundAmount`) else `422 { "message": "Refund can't exceed ₹9,619" }`. Effects (Section 24.5): order `refundStatus:"processing"`, `refundMethod`, `pendingRefund` stub, history "Refund initiated"; payment → `refund_pending` with `pendingRefund`; ledger `refunds` row `type:"order_refund"`, `status:"pending"`. `200 { data: Order }`.
* `POST /admin/orders/{id}/refund/complete` — empty body `{}`. Requires `refundStatus === "processing"` (`409` otherwise). Settles the pending refund (Section 24.5). `200 { data: Order }`.
* `POST /admin/orders/{id}/refund/fail` — body `{ "note": string }` (may be empty). Requires `processing`. Marks failed and rolls the payment back. `200 { data: Order }`.

### 13.19 Admin returns

* `GET /admin/returns` — all returns newest first, with `items`, `statusHistory`. No params sent.
* `GET /admin/returns/{id}` — (defined only).
* `POST /admin/returns` — body Section 14.6. Server: verify the order exists and is not cancelled; verify quantities ≤ ordered; recompute `refundAmount` = Σ(price×qty) − proportional coupon share (`min(gross, round(gross/orderSubtotal × orderDiscount))`) and use it (client value is a hint); generate `returnNumber`; set `status:"requested"`, `refundStatus:"pending"`, `deductionAmount:0`, `restocked:false`, tracking fields null, `images:[]`, `notes:""`; history `{ by: admin, action:"Return created", note:"Reason: <reason>" }`. `201 { data: Return }`.
* `PATCH /admin/returns/{id}` — body: any of `status`, `notes`, `rejectReason`, `refundStatus`, `deductionAmount`, `refundMethod`, `returnTrackingNumber`, `returnTrackingUrl`, `returnCarrier`, `pickupScheduledAt`, plus optional `event: { action, note }` and `restock: boolean`. Exact payloads in Section 14.7. Server merges, appends history when `event` is present, and **when the resulting `status === "refunded"` or `refundStatus === "processed"`** runs the return-refund cascade (Section 24.6) and restocks items when `restock:true` (set `restocked:true`). `200 { data: Return }`.

### 13.20 Admin payments & refund ledger

* `GET /admin/payments` — Query `orderId` (optional; `AdminOrders` uses it to find the order's payment). All payments newest first, each with `refunds[]`, `pendingRefund`, `orderNumber`. Consumers: `AdminPayments`, `AdminOrders`.
* `GET /admin/payments/{id}` — (defined only).
* `POST /admin/payments/{id}/refund` — body `{ "amount": number, "reason": string }`. `amount` ≤ `amount − refundAmount` else `422 { "message": "Refund exceeds the remaining ₹1,440" }`. Effects (Section 24.5d): append `payment_refunds` row, `refundAmount += amount`, status `partially_refunded`/`refunded`, `pendingRefund:null`; order `paymentStatus` mirrors, history "Refund issued (₹…)"; ledger row `type:"payment_refund"`, `status:"completed"`. `200 { data: Payment }`.
* `GET /admin/refunds` — all ledger rows newest first with `orderNumber`, `returnNumber`. Consumers: `AdminPayments` (Refunds tab, pending totals).

### 13.21 Admin shipping methods

* `GET /admin/shipping-methods` — all, including inactive.
* `POST /admin/shipping-methods` — body `{ "name", "carrier", "description", "rateType": "flat|free", "flatRate": number, "freeAbove": number|null, "estimatedDays": string, "isActive": boolean }`. `201`.
* `PUT /admin/shipping-methods/{id}` — same body (the form sends exactly these fields, no `id`/`createdAt`). `200`.
* `DELETE /admin/shipping-methods/{id}` — `200`; `404` when absent. Orders keep their `shippingAmount` snapshot.
* `POST /admin/shipping/shiprocket/order` — body `{ "orderId" }`; `GET /admin/shipping/shiprocket/track/{trackingNumber}`. **No screen calls these and no mock exists.** `BACKEND DECISION REQUIRED`: implement as `501 { "message": "Shiprocket integration is not enabled." }` until the integration is commissioned (Section 23).

### 13.22 Admin coupons

* `GET /admin/coupons` — all coupons.
* `POST /admin/coupons` — body Section 14.8 (`usedCount:0` is sent; server owns it). `code` unique (case-insensitive) → `422 { "message": "Coupon code \"MUGA500\" already exists" }`. `201`.
* `PUT /admin/coupons/{id}` — same body **without** `usedCount`/`createdAt` (the mock deliberately uses PATCH so those survive). Treat as update of the sent fields only; never reset `usedCount`. `200`.
* `DELETE /admin/coupons/{id}` — `200`. Orders keep `couponCode` snapshot (`coupon_id` SET NULL).

### 13.23 Admin reviews

* `GET /admin/reviews` — all reviews any status, newest first (no params sent).
* `POST /admin/reviews` — body `{ "productId", "userName", "rating", "title", "body", "isVerifiedPurchase": boolean, "status": "approved|pending|rejected" }`. Server sets `userId:null`, `helpfulCount:0`, `source:"admin"`. `201`.
* `PATCH /admin/reviews/{id}` — body `{ "status" }` (only field the screen sends; accept `title`/`body`/`rating` too). Recompute product rating when status changes. `200`.
* `DELETE /admin/reviews/{id}` — `200`; recompute product rating.

### 13.24 Admin users

* `GET /admin/users` — all customers (`User` shape incl. `addresses`, `storeCredit`; never `password`).
* `GET /admin/users/{id}` — (defined only).
* `PATCH /admin/users/{id}` — body `{ "isActive": boolean }`. Deactivating should also revoke the user's tokens (`BACKEND DECISION`: recommended). `200 { data: User }`.

### 13.25 Admin leads

* `GET /admin/leads` — all leads (screen sorts newest first and filters by type/status/search client-side).
* `GET /admin/leads/{id}` — (defined only).
* `PATCH /admin/leads/{id}` — body `{ "status", "notes" }`; status must be valid for the lead's `type`. `200`.
* `DELETE /admin/leads/{id}` — `200`.

### 13.26 Admin settings, deals, hero, FAQs

* `GET /admin/settings` — the six sections: `store`, `shipping` (**with `shiprocketPassword` omitted or masked**), `payment`, `notifications`, `seo`, `social`. Consumers: `AdminSettings`, `AdminShipping`.
* `PATCH /admin/settings/{section}` — `section` ∈ `store|shipping|payment|notifications|seo|social` (`404` otherwise). Body: partial object of that section; server **merges** keys (missing keys unchanged). Payloads sent today are in Section 14.9. Encrypt `shiprocketPassword` when present. `200 { data: <the merged section> }`.
* `GET /admin/deals/config` / `PUT /admin/deals/config` — body Section 14.10; PUT **replaces** the whole object; validate `hero.title` required, id arrays reference existing products/coupons (drop unknown ids). `200 { data: DealsConfig }`.
* `GET /admin/hero/config` / `PUT /admin/hero/config` — body Section 14.11; replace; validate ranges (Section 16). `200 { data: HeroConfig }`.
* `GET /admin/banners` — all slides ordered by `sortOrder`. `POST /admin/banners` (`201`), `PUT /admin/banners/{id}` (full slide body, Section 14.12), `DELETE /admin/banners/{id}`.
* `PUT /admin/banners/reorder` — body `{ "order": [6, 1, 2, 3, 4, 5] }` (every id, top first). Set each row's `sortOrder` = index. Unknown ids → `422`. `200 { data: [Banner…] }` (or `true`; the screen reloads).
* `GET /admin/faqs`, `POST /admin/faqs` (`201`), `PUT /admin/faqs/{id}`, `DELETE /admin/faqs/{id}` — body Section 14.13. `PUT /admin/faqs/reorder` — `{ "order": [ids] }`, same semantics as banners.

---

## 14. Request Payload Specifications

Exact bodies the frontend sends (copied from the call sites). Fields marked *ignored* are sent but
must be recomputed/owned by the server.

### 14.1 `POST /orders` (from `Checkout.placeOrder` + `OrderContext.createOrder`)

```json
{
  "items": [
    { "productId": 19, "variantId": "v1", "name": "Eri Silk Shawl — Undyed Ivory - Undyed Ivory",
      "image": "https://…/eri_1.png", "sku": "", "price": 4800, "quantity": 1, "subtotal": 4800 }
  ],
  "shippingAddress": { "id": 1, "label": "Home", "firstName": "Bappi", "lastName": "Das", "phone": "+919707112233",
    "addressLine1": "Moutupuri, Barpeta", "addressLine2": "Near BH College", "city": "Howly", "state": "Assam",
    "postalCode": "781316", "country": "India", "isDefault": true },
  "billingAddress": { "…same object as shippingAddress…" : "" },
  "subtotal": 4800,            "discountAmount": 0,      "couponCode": null,
  "shippingAmount": 0,         "taxAmount": 240,         "codFee": 0,
  "total": 5040,               "storeCreditUsed": 0,     "amountPayable": 5040,
  "paymentMethod": "upi",      "paymentStatus": "paid",
  "fulfillmentStatus": "unfulfilled", "shippingStatus": "pending",
  "trackingNumber": null,      "shiprocketOrderId": null, "notes": "",
  "userId": 3,                 "orderNumber": "ORD-MQDVIQCV-30A9",
  "createdAt": "2026-06-14T14:22:33.199Z", "updatedAt": "2026-06-14T14:22:33.199Z"
}
```

* `items[].sku` is always `""` (cart lines carry no SKU) — resolve from the variant/product.
* `items[].name` already contains `" - <variantName>"`; store `variantName` separately from `variantId`.
* `shippingAddress`/`billingAddress` are the same object (billing = shipping). When the customer typed a
  new address it has no `id`/`label`/`isDefault`; when a saved one was picked those keys are present.
* *ignored/recomputed*: every money field, `paymentStatus`, `orderNumber`, `userId`, `createdAt`,
  `updatedAt`, `fulfillmentStatus`, `shippingStatus`, `trackingNumber`, `shiprocketOrderId`.
* **Not sent:** the chosen shipping method id (Section 42).

### 14.2 `PUT /auth/user`

Profile: `{ "firstName": "Bappi", "lastName": "Das", "phone": "9707112233" }` (phone may be `""`).
Addresses: `{ "addresses": [ { "id": 1, "label": "Home", "firstName", "lastName", "phone", "addressLine1", "addressLine2", "city", "state", "postalCode", "country": "India", "isDefault": true }, { "id": "m0x8kz3a9f1", … "isDefault": false } ] }`.

### 14.3 `POST /admin/products` (and the editable part of `PUT`)

```json
{
  "name": "Sualkuchi Muga Mekhela Chador — Natural Gold", "slug": "sualkuchi-muga-mekhela-chador-natural-gold",
  "sku": "MEK-MUG-001", "shortDescription": "…", "description": "…", "categoryId": 1, "brand": "Meghali's Silk",
  "images": ["https://…/muga_1_V1.png", "https://…/muga_1_V2.png"],
  "price": 32500, "comparePrice": 38000, "costPrice": 22400,
  "stock": 9, "lowStockThreshold": 3, "weight": 1.15,
  "dimensions": { "length": 38, "width": 28, "height": 8 },
  "variants": [
    { "id": "v1", "name": "Natural Gold", "price": 32500, "stock": 5, "sku": "MEK-MUG-001-NGD" },
    { "id": "v-1712345678901-123", "name": "Gold with Rust Border", "price": 33500, "stock": 4, "sku": "MEK-MUG-001-GRB" }
  ],
  "tags": ["mekhela chador", "muga", "assam silk"],
  "featured": true, "trending": true, "hot": false, "isActive": true,
  "metaTitle": "…", "metaDescription": "…"
}
```

`categoryId` may be `null`; `dimensions` may be `null`; `variants` may be `[]`. `PUT` additionally
carries `id`, `rating`, `totalReviews`, `createdAt`, `updatedAt`, `relatedProductIds`,
`frequentlyBoughtTogetherIds` from the original row.

### 14.4 `POST /admin/categories` / `PUT`

`{ "name": "Muga Silk", "slug": "muga-silk", "description": "…", "image": "https://…", "parentId": null, "isActive": true, "sortOrder": 1, "showInMainMenu": true, "menuOrder": 1 }`
(`PUT` also carries `id`, `createdAt`, `updatedAt`). The inline menu toggle sends the whole category
with `showInMainMenu`/`menuOrder` changed.

### 14.5 `PATCH /admin/orders/{id}` — the five payloads `AdminOrders` sends

| Action | Body |
| --- | --- |
| Mark fulfilled / update tracking | `{ "fulfillmentStatus": "fulfilled", "shippingStatus": "shipped", "trackingNumber": "SHIP123456789IN"\|null, "trackingUrl": "https://…"\|null, "notes": "…", "event": { "action": "Fulfilled & shipped" \| "Tracking updated", "note": "Tracking SHIP123456789IN · tracking link added" } }` |
| Mark delivered | `{ "shippingStatus": "delivered", "deliveredAt": "2026-06-20T06:24:29.130Z", "notes": "…", "event": { "action": "Marked delivered" } }` |
| Mark paid | `{ "paymentStatus": "paid", "notes": "…", "event": { "action": "Payment marked as paid" } }` — server should also flip the linked payment to `captured` (Section 22). |
| Edit shipping address | `{ "shippingAddress": { …full address object… }, "event": { "action": "Shipping address updated" } }` |
| (alias `updateOrderStatus`) | `{ "fulfillmentStatus": "<status>", "notes": "" }` — defined only |

### 14.6 `POST /admin/returns`

```json
{ "orderId": 11, "orderNumber": "ORD-MQDVIQCV-30A9", "userId": 3,
  "items": [ { "productId": 3, "variantId": "v1", "name": "Banarasi Georgette Silk Saree — Wine - Wine", "sku": "SAR-BAN-003-WIN", "price": 8999, "quantity": 1, "subtotal": 8999 } ],
  "reason": "defective", "reasonDetails": "Left earbud has no sound", "refundAmount": 8999, "refundMethod": "original_payment" }
```

### 14.7 `PATCH /admin/returns/{id}` — payloads `AdminReturns` sends

| Action | Body |
| --- | --- |
| Approve / Receive | `{ "status": "approved" \| "received", "notes": "…", "event": { "action": "Return approved" \| "Items received" }, "restock": false }` |
| Reject | `{ "status": "rejected", "rejectReason": "…", "notes": "…", "event": { "action": "Return rejected", "note": "…" }, "restock": false }` |
| Schedule pickup | `{ "status": "pickup_scheduled", "returnTrackingNumber": "RETN-SR-1029384"\|null, "returnTrackingUrl": "https://…"\|null, "returnCarrier": "Shiprocket"\|null, "pickupScheduledAt": "2026-01-21T11:30:00.000Z", "event": { "action": "Return pickup scheduled", "note": "Return tracking RETN-SR-1029384 · pickup 21/1/2026" }, "restock": false }` |
| In transit | `{ "status": "in_transit", "event": { "action": "Return in transit", "note": "Tracking RETN-SR-1029384" }, "restock": false }` |
| Process refund | `{ "status": "refunded", "refundStatus": "processed", "deductionAmount": 0, "refundMethod": "original_payment", "notes": "…", "event": { "action": "Refund processed (₹8,999)", "note": "₹500 deducted" \| "" }, "restock": true }` |

### 14.8 `POST /admin/coupons` / `PUT`

`{ "code": "MUGA500", "description": "…", "type": "fixed"|"percentage", "value": 500, "minOrderAmount": 5000, "maxDiscount": 500|null, "usageLimit": 1000|null, "perUserLimit": 1|null, "isActive": true, "expiresAt": "2027-03-31T23:59:59.000Z"|null, "usedCount": 0 (POST only), "createdAt"/"updatedAt" (client stamps; ignore) }`

### 14.9 `PATCH /admin/settings/{section}`

| Section | Body sent |
| --- | --- |
| `store` | `{ "name", "tagline", "email", "phone", "address", "currency": "INR", "currencySymbol": "₹", "taxRate": 5, "taxIncluded": false }` |
| `payment` | `{ "codEnabled": true, "codFee": 0, "codMinOrder": 0, "codMaxOrder": 50000 }` |
| `social` | `{ "facebook": "https://…", "instagram": "…", "youtube": "…", "twitter": "…", "whatsapp": "https://wa.me/…" }` (blank string = remove the mark) |
| `shipping` | `{ "shiprocketEnabled": true }` (toggle) or `{ "shiprocketEnabled": true, "shiprocketEmail": "…", "shiprocketPassword": "…" }` (password only when typed) |

### 14.10 `PUT /admin/deals/config`

`{ "enabled": true, "hero": { "tag", "title", "subtitle" }, "timer": { "enabled": true, "endAt": "2026-09-01T11:04:00.000Z" | "", "onExpiry": "endOfDay" | "hide" }, "featuredCouponIds": [2, 3, 7], "dealOfTheDayIds": [1, 13], "featuredProductIds": [1, 6, 13], "updatedAt": "…" }`

### 14.11 `PUT /admin/hero/config`

`{ "enabled", "autoplay", "intervalMs": 1000–60000, "transition": "fade|slide|none", "pauseOnHover", "showControls", "showCounter", "showProgress", "showArrows", "overlayOpacity": 0–100, "heights": { "desktop": { "min", "vh", "max" }, "tablet": {…}, "mobile": {…} }, "secondaryCta": { "enabled", "label", "link" }, "openers": { "enabled", "label", "limit": 1–20 }, "updatedAt": "…" }`

### 14.12 `POST /admin/banners` / `PUT /admin/banners/{id}`

`{ "title", "subtitle", "eyebrow", "cta", "link", "secondaryCtaLabel", "secondaryCtaLink", "backgroundType": "gradient|image|video", "gradient", "image", "imagePosition", "videoUrl", "videoPoster", "overlayOpacity": null|0–100, "textAlign": "left|center|right", "durationMs": 0|1000–60000, "isActive", "sortOrder" }` — `id`, `createdAt`, `updatedAt`, `imageUrl` are stripped by the screen.

### 14.13 `POST /admin/faqs` / `PUT /admin/faqs/{id}`

`{ "question", "answer", "placements": ["product","help","home"], "productIds": [1, 13], "isActive": true, "sortOrder": 3 }` (+ `createdAt`/`updatedAt` stamps to ignore).

---

## 15. Response Specifications

All keys camelCase. All dates `YYYY-MM-DDTHH:mm:ss.sssZ`. All ids numbers except variant `id`
strings and `payments.refunds[].id` strings. Money = integer rupees.

### 15.1 `User`
See Section 8.1. Keys: `id, email, firstName, lastName, phone, avatar, addresses[], isActive, storeCredit, createdAt, updatedAt`.

### 15.2 `Admin`
`{ id, email, firstName, lastName, role, isActive, createdAt }`.

### 15.3 `Product`
```json
{ "id": 1, "name": "…", "slug": "…", "sku": "MEK-MUG-001", "shortDescription": "…", "description": "…",
  "categoryId": 1, "brand": "Meghali's Silk", "images": ["https://…"],
  "price": 32500, "comparePrice": 38000, "costPrice": 22400,
  "stock": 9, "lowStockThreshold": 3, "weight": 1.15, "dimensions": { "length": 38, "width": 28, "height": 8 },
  "variants": [ { "id": "v1", "name": "Natural Gold", "price": 32500, "stock": 5, "sku": "MEK-MUG-001-NGD",
                  "attributes": { "Fabric": "Muga Silk", "Color": "Natural Gold" }, "swatchHex": "#C6A050" } ],
  "tags": ["…"], "featured": true, "trending": true, "hot": false, "isActive": true,
  "rating": 4.8, "totalReviews": 41, "metaTitle": "…", "metaDescription": "…",
  "frequentlyBoughtTogetherIds": [19], "relatedProductIds": [13, 6, 10],
  "createdAt": "…", "updatedAt": "…" }
```
Fields the storefront reads: everything above except `costPrice` (admin only). `attributes`/`swatchHex`
are optional per variant (omit when null). `dimensions` may be `null`.

### 15.4 `Category`
`{ id, name, slug, description, image, parentId, isActive, sortOrder, showInMainMenu, menuOrder, createdAt, updatedAt }`.

### 15.5 `Banner` — all columns of 11.24 in camelCase.

### 15.6 `HeroConfig` — the object in 14.11 plus `updatedAt`.

### 15.7 `CartItem`
`{ "id": 12, "userId": 3, "productId": 19, "variantId": "v1", "variantName": "Undyed Ivory", "name": "Eri Silk Shawl — Undyed Ivory", "image": "https://…", "price": 4800, "comparePrice": 0, "currency": "INR", "quantity": 1, "stock": 16 }`
(`normalizeCartItem` also accepts `product.images[0]`, `variant`, `product.image` aliases — unnecessary if the flat shape is returned.)

### 15.8 `Order`
All columns of 11.12 in camelCase plus `items[]` (`OrderItem`: `productId, variantId, variantName, name, image, sku, price, quantity, subtotal`), `statusHistory[]` (`{ at, by, action, note? }`), `pendingRefund` (object|null), `recall` (object|null), and on admin endpoints `customerEmail`, `customerName`. Keys the customer pages read: `id, orderNumber, items, shippingAddress, subtotal, discountAmount, couponCode, shippingAmount, taxAmount, total, storeCreditUsed, amountPayable, paymentMethod, paymentStatus, fulfillmentStatus, shippingStatus, trackingNumber, trackingUrl, refundStatus, refundMethod, refundedAmount, deliveredAt, createdAt, updatedAt`. Address snapshot object: `{ id?, label?, firstName, lastName, phone, addressLine1, addressLine2, city, state, postalCode, country, isDefault? }`.

### 15.9 `Return` — columns of 11.18 + `orderNumber`, `items[]`, `statusHistory[]`.
### 15.10 `Payment` — columns of 11.15 + `orderNumber`, `refunds[]` (`{ id, amount, reason, at, by }`), `pendingRefund` (object|null).
### 15.11 `Refund` (ledger) — columns of 11.17 + `orderNumber`, `returnNumber`.
### 15.12 `WalletTransaction` — `{ id, userId, type, amount, reason, orderId, orderNumber, refundId, refundNumber, balanceBefore, balanceAfter, createdAt }`.
### 15.13 `Coupon`, `Review` (+ `orderNumber`), `ShippingMethod`, `Lead`, `Faq` (+ `productIds[]`) — columns of their tables in camelCase.

### 15.14 `Settings`

Public `GET /settings`:
```json
{ "store": { "name": "Meghali's Silk", "tagline": "…", "email": "care@meghalisilk.com", "phone": "+91 33 4000 1100", "address": "…",
             "currency": "INR", "currencySymbol": "₹", "timezone": "Asia/Kolkata", "logo": null, "favicon": null, "taxRate": 5, "taxIncluded": false },
  "payment": { "razorpayEnabled": false, "razorpayKeyId": "", "stripeEnabled": false, "stripePublishableKey": "", "codEnabled": true, "codFee": 0, "codMinOrder": 0, "codMaxOrder": 50000 },
  "social": { "facebook": "https://facebook.com/meghalisilk", "instagram": "…", "twitter": "…", "youtube": "…", "whatsapp": "https://wa.me/913340001100" } }
```
Admin `GET /admin/settings` adds `shipping: { shiprocketEnabled, shiprocketEmail, defaultWeight, defaultDimensions: { length, width, height } }` (no password), `notifications: { orderConfirmationEmail, shippingUpdateEmail, adminNewOrderEmail, adminEmail, lowStockAlert, lowStockEmail }`, `seo: { metaTitle, metaDescription, googleAnalyticsId, facebookPixelId }`.

### 15.15 `DealsConfig` — object of 14.10 plus `updatedAt`.

### 15.16 `WishlistItem`
Recommended (relational) form, handled by `normalizeWishlistItem`:
`{ "id": 7, "userId": 3, "productId": 1, "createdAt": "2026-06-14T13:57:44.901Z", "product": Product }`.
Alternative flat form (identical to the POST body plus `id`) is also accepted.

### 15.17 Dashboard stats — Section 30.

---

## 16. Validation Rules

Mirror of the client checks (which cannot be trusted) plus server-only rules. Return `422` with
`{ message, errors }` (Section 31).

| Endpoint | Rules |
| --- | --- |
| `POST /auth/register` | `firstName`,`lastName` required ≤100; `email` required, RFC email, unique; `phone` nullable, `regex:/^\+91[6-9]\d{9}$/`; `password` required, min 6 (recommend 8), `confirmed` |
| `POST /auth/login`, `/admin/auth/login` | `email` required email; `password` required |
| `PUT /auth/user` | `firstName`,`lastName` sometimes/required ≤100; `phone` nullable, Indian mobile; `addresses` array, each: `label` ≤50, `firstName`,`lastName` required, `phone` required Indian mobile, `addressLine1` required ≤255, `addressLine2` nullable, `city`,`state` required ≤100, `postalCode` required ≤20, `country` default India, `isDefault` boolean |
| `PUT /auth/password` | `current_password` required + matches; `password` min 8, `confirmed` |
| `POST /cart` | `productId` exists & active; `variantId` nullable, must exist on product; `quantity` int ≥1 |
| `POST /orders` | `items` array min 1; each `productId` exists/active, `variantId` valid, `quantity` int ≥1 ≤ stock; `shippingAddress` (+ `billingAddress`) object with `firstName`,`lastName`,`phone`,`addressLine1`,`city`,`state`,`postalCode` required (Checkout's `validateAddress`), `country` default India; `paymentMethod` in list; `couponCode` nullable → revalidated; `storeCreditUsed` int ≥0 ≤ balance and ≤ total; COD allowed only when `payment.codEnabled` and `codMinOrder ≤ amountPayable ≤ codMaxOrder` (null = no max) |
| `POST /orders/{id}/cancel` | `reason` nullable string ≤500; state guard (processing only) |
| `POST /products/{id}/reviews` | `rating` int 1–5; `title` ≤120 (`TITLE_MAX` in ReviewModal — confirm), `body` ≤1000 (`BODY_MAX` — confirm); purchase gate |
| `POST /coupons/validate` | `code` required; `orderAmount` numeric ≥0 |
| `POST /wishlist` | `productId` exists & active |
| `POST /leads/contact` | `name` required; `email` required email; `phone` nullable Indian mobile; `subject` required; `message` required min 20; `category` in list; `orderNumber` nullable |
| `POST /leads/newsletter` | `email` required email |
| `POST/PUT /admin/products` | `name` required ≤255; `slug` required, `regex:/^[a-z0-9]+(-[a-z0-9]+)*$/`, unique (ignore self); `price` ≥0 and (>0 or variants non-empty); `comparePrice`,`costPrice` ≥0; `stock`,`lowStockThreshold` int ≥0; `weight` ≥0; `dimensions` nullable object of ≥0 numbers; `categoryId` nullable exists; `images` array of URLs; `variants` array: `id` string required, `name` required, `price` ≥0, `stock` int ≥0, `sku` nullable; `tags` array of strings; booleans |
| `POST/PUT /admin/categories` | `name` required; `slug` unique slug; `parentId` nullable exists, not self/descendant; ints ≥0; booleans |
| `PATCH /admin/orders/{id}` | statuses in enum; `trackingUrl` nullable `url` (http/https); `deliveredAt` nullable ISO date; `shippingAddress` as above (address edit requires `firstName`,`addressLine1`,`city`,`state`,`postalCode` — `AdminOrders.handleAddressSave`); `event.action` required ≤255 when `event` present |
| `POST /admin/orders/{id}/cancel` | `reason` required; `restock` boolean; `refund.method` in `original_payment,bank_transfer,upi,store_credit`; `refund.amount` nullable ≤ remaining; `recall.trackingUrl` nullable url |
| `POST /admin/orders/{id}/refund/initiate` | `amount` numeric >0 ≤ remaining; `method` in list; `reason` required; `reference` nullable ≤100 |
| `POST /admin/returns` | `orderId` exists, not cancelled; `items` min 1, each `quantity` ≥1 ≤ ordered; `reason` in list; `refundMethod` in list |
| `PATCH /admin/returns/{id}` | `status` in enum + legal transition (`requested→approved|rejected`, `approved→pickup_scheduled|received`, `pickup_scheduled→in_transit|received`, `in_transit→received`, `received→refunded`); `deductionAmount` 0 ≤ x ≤ `refundAmount`; `rejectReason` required when rejecting; `returnTrackingUrl` nullable url; `pickupScheduledAt` nullable date |
| `POST /admin/payments/{id}/refund` | `amount` >0 ≤ remaining; `reason` nullable ≤255 |
| `POST/PUT /admin/shipping-methods` | `name` required; `rateType` in `flat,free`; `flatRate` ≥0; `freeAbove` nullable ≥0; `estimatedDays` nullable string ≤20; `isActive` boolean |
| `POST/PUT /admin/coupons` | `code` required ≤50, uppercase, unique case-insensitive; `type` in `percentage,fixed`; `value` >0 (≤100 for percentage); `minOrderAmount` ≥0; `maxDiscount`,`usageLimit`,`perUserLimit` nullable int ≥1; `expiresAt` nullable date |
| `POST /admin/reviews` | `productId` exists; `userName` required; `rating` 1–5; `status` in enum; `isVerifiedPurchase` boolean |
| `PATCH /admin/users/{id}` | `isActive` boolean |
| `PATCH /admin/leads/{id}` | `status` valid for type; `notes` nullable |
| `PATCH /admin/settings/store` | `name` required; `taxRate` 0–100; `currency` 3 letters; `email` email |
| `PATCH /admin/settings/payment` | `codFee`,`codMinOrder`,`codMaxOrder` ≥0 |
| `PATCH /admin/settings/social` | each key nullable url (the admin already normalises to `https://…`) |
| `PUT /admin/hero/config` | `intervalMs` 1000–60000; `transition` in `fade,slide,none`; `overlayOpacity` 0–100; heights: `min` 200–1200, `vh` 20–100, `max` 200–1600 and ≥ min; `openers.limit` 1–20 |
| `POST/PUT /admin/banners` | `title` required; `backgroundType` in enum; `imagePosition` in list; `textAlign` in enum; `overlayOpacity` nullable 0–100; `durationMs` 0 or 1000–60000; `link` required |
| `POST/PUT /admin/faqs` | `question`,`answer` required; `placements` array min 1 of `product,help,home`; `productIds` array of existing ids |
| `PUT /admin/*/reorder` | `order` array of all existing ids, no duplicates |

---

## 17. Pagination

**Finding:** no API call sends pagination parameters and no page reads `meta`. Every list is consumed
as a full array:

* Storefront `/products` paginates in the browser with URL params `page` (1-based) and `per_page`
  (12 | 24 | 48, default 12) — these are **UI state only** and never sent to the API.
* Order History pages 5 per page client-side; Admin Leads uses MUI `TablePagination` (10/25/50)
  client-side; every other admin table renders the full list.
* `extractMeta()` exists in `api.js` but is never called.

**Rule:** every list endpoint must return the **complete working set** in `data`. Do **not** paginate
server-side; a truncated page would silently hide products/orders. If server pagination is introduced
later it requires a **FRONTEND CHANGE** (send `page`/`per_page`, read `meta`), and the recommended
`meta` shape for that day is `{ "current_page", "last_page", "per_page", "total" }`.

Indexes listed in Section 11 keep full-list queries fast at the expected catalogue size (tens to low
thousands of rows). Eager-load relations to avoid N+1.

---

## 18. Search

| Surface | Mechanism | Fields searched (client-side) |
| --- | --- | --- |
| Storefront `/products?search=` | client-side over `GET /products` | `name`, `shortDescription`, `brand`, `category` (unused), `tags[]` — case-insensitive `includes` |
| `SearchModal` | client-side over cached `GET /products` + `GET /categories` | product name/brand/tags/category names |
| Order History | client-side | `orderNumber` / `id` |
| Admin Orders | client-side | `orderNumber`, customer name (`customerName` or billing first+last), `customerEmail`, `trackingNumber` |
| Admin Products | client-side | `name`, `sku`, `brand` |
| Admin Returns | client-side | `returnNumber`, `orderNumber` |
| Admin Payments | client-side | `transactionId`, `orderNumber`; refunds tab: `refundNumber`, `orderNumber`, `returnNumber` |
| Admin Coupons | client-side | `code`, `description` |
| Admin Reviews | client-side | `userName`, `title`, `body` |
| Admin Users | client-side | `firstName`, `lastName`, `email`, `phone` |
| Admin Leads | client-side | `email`, `name`, `subject`, `message` |
| Admin Categories | client-side | `name`, `slug` |

Server-side search parameters that exist in the contract: `GET /products?search=` (defined, unused),
`GET /admin/orders?userId=`, `GET /admin/payments?orderId=`. Implement `search` as case-insensitive
`LIKE %q%` over `name`, `short_description`, `brand`, `tags` (JSON_SEARCH or a generated column).

---

## 19. Filtering

Client-side filters (over full arrays) that the API must make possible by returning the fields:

| Screen | Filters | Fields |
| --- | --- | --- |
| `/products` | category (multi, slug; parent includes descendants via `parentId`), `min_price`/`max_price` (URL, against min variant price), rating ≥ n, discount ≥ n% (from `comparePrice`), in-stock (`stock > 0`), brand, fabric (derived from variant attributes/tags), highlights `featured|trending|hot` (URL `highlight=`) | `categoryId`, `price`, `comparePrice`, `variants[].price`, `rating`, `stock`, `brand`, `tags`, flags |
| Order History | derived status chips All/Processing/Shipped/Delivered/Cancelled | `paymentStatus`, `fulfillmentStatus`, `shippingStatus` |
| Admin Orders | `fulfillmentStatus`, `paymentStatus`, date range on `createdAt` (YYYY-MM-DD, inclusive) | same |
| Admin Returns | `status` | |
| Admin Payments | `status` | |
| Admin Reviews | `status` | |
| Admin Leads | `type`, `status` | |
| Admin Products | `categoryId` | |
| Admin FAQs | placement / active | |

Server-side filter parameters in the contract: `userId` on `GET /admin/orders`, `orderId` on
`GET /admin/payments`, `limit` on featured/trending. Visibility filters the server must apply
unconditionally: storefront product endpoints → `isActive = true`; `GET /shipping/methods` →
`isActive = true`; `GET /coupons` → active & unexpired; `GET /products/{id}/reviews` →
`status = approved`.

---

## 20. Sorting

| Screen | Sort options | Where |
| --- | --- | --- |
| `/products` (`?sort=`) | `relevance` (API order), `price-low`, `price-high`, `newest` (`createdAt`), `rating`, `popularity` (`totalReviews`); aliases `price_asc`, `price_desc` accepted | client |
| Order History / Profile / OrderContext | `createdAt` desc | client |
| Admin Orders | `date_desc` (default), `date_asc`, `total_desc`, `total_asc` | client |
| Admin Leads / Refunds tab / Dashboard recent orders | `createdAt` desc | client |
| Categories (storefront) | `sortOrder` asc; main menu by `menuOrder`, `sortOrder`, `name` | client |
| Banners / FAQs | `sortOrder` asc | client (normalizers) |
| Wallet transactions | newest first | **server** (`GET /wallet/transactions`) |

Recommended default server order: products `id asc` (relevance = catalogue order), orders/returns/
payments/refunds/reviews/leads `created_at desc`, categories `sort_order asc`, banners/faqs
`sort_order asc`, wallet `created_at desc`. No sort parameters are sent by the frontend.

---

## 21. File Uploads

**Finding: the application has no file upload.** Verified by searching `src/` for `type="file"`,
`FormData`, `multipart` and `upload` — no matches in any component. Every image is a **URL string**:

| Image | How it is set | Storage field |
| --- | --- | --- |
| Product images | Admin → Products → "Image URLs (one per line)" textarea | `products.images[]` (Cloudinary URLs in seed) |
| Category image | Admin → Categories → `image` text field | `categories.image` |
| Hero slide image / video / poster | Admin → Hero Section text fields | `banners.image`, `videoUrl`, `videoPoster` |
| User avatar | never set (`avatar: null`); rendered if present | `users.avatar` |
| Store logo / favicon | `settings.store.logo/favicon` are `null`; the logo is a hard-coded Cloudinary URL in the components | — |
| Review photos | seed data only (`reviews[].photos[]`); `ReviewModal` has no photo input | `reviews.photos` |
| Return images | `returns.images` always `[]` | — |

**Therefore no upload endpoint is required for parity.** Validate URL fields as `url` (http/https, ≤
1000 chars) and store them verbatim. If uploads are added later they need a new endpoint
(`POST /admin/uploads`, `multipart/form-data`, MIME + size validation, `storage:link` or S3) **and a
FRONTEND CHANGE**.

---

## 22. Payments

### 22.1 What actually exists in the frontend

* **No payment gateway is integrated.** `Checkout.js` renders card / UPI / net-banking / wallet / COD
  options; the card, UPI and bank inputs are explicitly "Mock fields only — there is no gateway on this
  branch, so nothing here is read, validated or submitted". No Razorpay script, no `REACT_APP_RAZORPAY_KEY_ID`
  usage, no `/payments/*` customer endpoint.
* The frontend sets `paymentStatus` itself: `"paid"` for every non-COD method, `"pending"` for COD,
  `"paid"` + `paymentMethod:"store_credit"` when store credit covers the whole order.
* The mock records a **payment row** for every order (`createPaymentForOrder`) so Admin → Payments and
  the refund cascades work: `gateway:"razorpay"` + fake `transactionId`/`gatewayOrderId` for online,
  `gateway:"cod"` pending for COD, `gateway:"store_credit"` captured for fully-credit orders.
* Admin → Orders has a manual **"Mark as Paid"** (`paymentStatus:"paid"`), and a two-step refund flow.
* `settings.payment.razorpayEnabled` is `false`; the `.env` Razorpay key is commented out.

### 22.2 Server responsibility on `POST /orders` (parity with the mock)

Create one `payments` row inside the order transaction:

| Case | `paymentMethod` | `gateway` | `status` | `transactionId` / `gatewayOrderId` | `amount` |
| --- | --- | --- | --- | --- | --- |
| Fully store credit (`storeCreditUsed > 0 && amountPayable == 0`) | `store_credit` | `store_credit` | `captured` | `wallet_<ref>` / null | `total` |
| COD | `cod` | `cod` | `pending` | null / null | `amountPayable` |
| Online (`card`,`upi`,`net_banking`,`wallet`) | as chosen | `razorpay` | see 22.3 | see 22.3 | `amountPayable` |

Always: `currency:"INR"`, `userId`, `storeCreditApplied = storeCreditUsed`, `gatewayResponse: {}`.

### 22.3 `BACKEND DECISION REQUIRED` — status of online orders without a gateway

The mock stores what the client sends (`paid`). A production server must **not** trust
`paymentStatus` from the client. Two compatible options:

| Option | Behaviour | Frontend impact |
| --- | --- | --- |
| **A (recommended, secure)** | Online orders are created with `paymentStatus:"pending"`, payment `status:"pending"`, `transactionId:null`. Admin uses "Mark as Paid" after off-line confirmation; that action sets payment `captured`. | None in code. Visible difference: the confirmation page and Order History badge read "Processing / Payment pending" instead of "Paid" until the admin marks it. |
| B (parity with the mock) | Server honours the client's `"paid"` for online methods and stamps a placeholder `transactionId` (`pay_MANUAL<ref>`). | None. Not safe for real money — only acceptable while no real payments are collected. |

Whichever is chosen, `"Mark as Paid"` (`PATCH /admin/orders/{id} { paymentStatus:"paid" }`) must also
set the linked payment to `captured` and add a history row.

### 22.4 Razorpay (future)

Real Razorpay checkout requires **FRONTEND CHANGE REQUIRED**: load `checkout.js`, call a new
`POST /payments/razorpay/order` to create a gateway order for `amountPayable`, open the widget with the
public key, then `POST /payments/razorpay/verify { razorpay_order_id, razorpay_payment_id, razorpay_signature }`;
the server verifies the HMAC-SHA256 signature with the secret and only then marks the payment
`captured` and the order `paid`. Add a webhook endpoint for `payment.captured`/`payment.failed`. Keep
`RAZORPAY_KEY_SECRET` server-side only. **Do not build this for parity; document only.**

### 22.5 Refunds — server-side lifecycle (mock cascades that become Laravel logic)

Detailed in Section 24.5. Money is never moved by the API today (no gateway); refunds are recorded
and settled by the admin manually.

---

## 23. Shipping

### 23.1 What exists

* **Shipping methods** are admin-managed rows (`name`, `carrier`, `rateType` `flat|free`, `flatRate`,
  `freeAbove`, `estimatedDays`, `isActive`). Checkout lists active ones, auto-selects the first, and
  computes `shippingCost = 0 if rateType === "free" || (freeAbove && subtotal >= freeAbove) else flatRate`.
* The order stores only `shippingAmount` (the number) — **not** which method was chosen.
* Tracking is manual: admin types `trackingNumber` and `trackingUrl`; "Mark as Fulfilled" sets
  `shippingStatus:"shipped"`; "Mark as Delivered" sets `delivered` + `deliveredAt`. Recall on cancel
  stores return-leg tracking in `orders.recall`. Returns store `returnTrackingNumber/Url/Carrier` and
  `pickupScheduledAt`.
* **Shiprocket is not implemented.** `settings.shipping.shiprocketEnabled` is `false`; Admin → Shipping
  has a Shiprocket card that only saves `shiprocketEnabled`, `shiprocketEmail`, `shiprocketPassword`
  into settings. `api.js` defines `POST /admin/shipping/shiprocket/order` and
  `GET /admin/shipping/shiprocket/track/{trackingNumber}` with no mock branch and **no caller**.
* `orders.shiprocketOrderId` is always `null`.

### 23.2 Server responsibility

* `GET /shipping/methods` → active methods. `POST /orders` → recompute `shippingAmount` (Section 24.2).
* Store `shiprocketPassword` encrypted; never return it.
* Shiprocket proxy endpoints: **`BACKEND DECISION REQUIRED`** — return `501 { message }` (recommended)
  or implement against the Shiprocket API using server-side credentials when `shiprocketEnabled` is
  true. No delivery-status webhook, rate calculation or serviceability check is used by the frontend;
  do not invent them.

---

## 24. Orders

### 24.1 Status model (three independent dimensions + refund lifecycle)

| Field | Values | Set by |
| --- | --- | --- |
| `paymentStatus` | `pending`, `paid`, `partially_paid`, `partially_refunded`, `refunded`, `failed`, `voided` | order create, Mark as Paid, refund settlement, cancel (void) |
| `fulfillmentStatus` | `unfulfilled`, `partially_fulfilled`, `fulfilled`, `returned`, `cancelled` | Mark as Fulfilled, cancel, return refund |
| `shippingStatus` | `pending`, `shipped`, `delivered`, `recalled` | Mark as Fulfilled (`shipped`), Mark as Delivered, recall |
| `refundStatus` | absent, `processing`, `completed`, `failed` | refund initiate / complete / fail |

Storefront derived badge (`deriveOrderStatus`): `returned` if `fulfillmentStatus:returned`; `cancelled` if
`fulfillmentStatus:cancelled` or `paymentStatus ∈ failed|refunded`; `delivered`; `shipped`; else
`processing`. Customer cancel allowed only while `processing`; review allowed only when `delivered`;
return eligibility shown for 7 days after `deliveredAt || updatedAt`.

### 24.2 Money recomputation on `POST /orders` (authoritative formulas from `Checkout.js`)

```
subtotal        = Σ items[i].price × quantity       (price = variant price else product price, from DB)
couponDiscount  = 0 if no coupon
                = min( type=="percentage" ? round(subtotal × value / 100) : value , maxDiscount || ∞ , subtotal )
shippingCost    = 0 if method.rateType == "free" or (method.freeAbove && subtotal >= method.freeAbove) else method.flatRate
taxableBase     = max(0, subtotal − couponDiscount)
taxAmount       = taxIncluded ? round(taxableBase − taxableBase / (1 + taxRate/100)) : round(taxableBase × taxRate / 100)
total           = subtotal − couponDiscount + shippingCost + (taxIncluded ? 0 : taxAmount)
storeCredit     = min( max(0, round(requested)), walletBalance, total )
amountPayable   = max(0, total − storeCredit)
codFee          = paymentMethod == "cod" && codAvailable ? settings.payment.codFee : 0
codAvailable    = codEnabled && amountPayable > 0 && amountPayable >= codMinOrder && (codMaxOrder == null || amountPayable <= codMaxOrder)
order.total     = total + codFee
order.amountPayable = amountPayable + codFee
paymentMethod   = "store_credit" if storeCredit > 0 && amountPayable == 0
```

`taxRate` / `taxIncluded` from `settings.store`; `codFee/codMinOrder/codMaxOrder/codEnabled` from
`settings.payment` (0 max = no maximum). Because the frontend does not send the shipping method id, the
server must determine `shippingCost` — see Section 42 (recommended: accept the client's
`shippingAmount` only if it equals the cost of **some active method** for this subtotal; otherwise `422`).
If the recomputed `total` differs from the client's, `BACKEND DECISION`: reject with `422
{ message: "Prices have changed, please review your cart." }` (recommended) rather than silently
charging a different amount.

### 24.3 Order number

Server-generated, unique. Seed formats: `ORD-YYYYMMDD-NNNN` and `ORD-<8 base36>-<4 base36>`. Recommended:
`ORD-` + `Ymd` + `-` + zero-padded daily sequence (`ORD-20260903-0001`). Ignore the client value.

### 24.4 Cancellation cascade (`performCancel`) — customer and admin resolve to identical state

Inputs: `reason`, `restock`, `refund { amount?, method, reference? }`, `voidPayment`, `recall { trackingNumber, trackingUrl, carrier }`, actor.
In one transaction:

1. Order: `fulfillmentStatus:"cancelled"`, `cancelReason`, `cancelledAt = now`; history `"Order cancelled"` (note = reason).
2. If `recall`: `orders.recall = { trackingNumber, trackingUrl, carrier, scheduledAt: now, by: actor }`, `shippingStatus:"recalled"`; history `"Shipment recall initiated"` (note `"Return tracking <n>"` or `"Parcel recalled to warehouse"`).
3. If `refund`: `amount = refund.amount || max(0, (amountPayable ?? total) − refundedAmount)`; `refundStatus:"processing"`, `refundMethod`, `pendingRefund { amount, method, reason: reason||"Order cancelled", reference, initiatedAt, by }`; history `"Refund initiated"` (`"₹9,619 via original payment — settlement pending"`); payment (if `captured|partially_refunded`) → `status:"refund_pending"`, `pendingRefund { amount, method, reason, initiatedAt, by }`; ledger `refunds` row `type: recall ? "recall_refund" : "order_cancellation"`, `status:"pending"`.
   Else if `voidPayment` **or** `paymentStatus == "pending"`: `paymentStatus:"voided"`; history `"Payment voided"` (note `"No captured payment to refund"` / `"Cash on delivery not collected"`); payment (if `pending`) → `voided`, `refundReason`.
4. If `storeCreditUsed > 0 && !storeCreditReturned && userId`: sum the order's wallet **debits**; credit that amount back (`wallet_transactions` credit, reason `"Store credit returned — <orderNumber> cancelled"`), `storeCreditReturned:true`; history `"Store credit returned"` (`"₹1,000 added back to your wallet"`); if the payment is `store_credit` and `captured|partially_refunded`, append a payment refund of the same amount (Section 24.5d mechanics).
5. If `couponCode && !couponRestored`: `coupons.used_count = max(0, used_count − 1)`; if it was > 0 set `couponRestored:true`; history `"Coupon usage restored"` (`"<CODE> freed for reuse"`).
6. If `restock`: for each item `products.stock += quantity` and the matching variant `stock += quantity`.
7. Return the updated `Order`.

Guards: customer path only while `processing`; admin path refuses `delivered`; both refuse already
`cancelled|returned`.

### 24.5 Refund lifecycle (two-step, async-gateway model)

**a. Initiate** (`/refund/initiate`): as step 3 above with `type:"order_refund"`, actor = admin.

**b. Complete** (`/refund/complete`): `amt = pendingRefund.amount`; find the order's payment;
`remaining = payment.amount − payment.refundAmount`; `settle = min(amt || remaining, remaining)`; if
`settle > 0` append `payment_refunds { id:"ref_<base36>", amount: settle, reason: pendingRefund.reason || "Refund completed", at, by }`,
`payment.refundAmount += settle`, `payment.status = refundAmount >= amount ? "refunded" : "partially_refunded"`,
`payment.pendingRefund = null`, `payment.refundReason`. Order: `refundStatus:"completed"`,
`paymentStatus = payment.status == "refunded" ? "refunded" : "partially_refunded"` (default `"refunded"` when no payment row),
`refundedAmount += amt`, `refundCompletedAt = now`, `pendingRefund:null`; history `"Refund completed"`
(`"₹… via original payment settled to customer"`). Ledger: newest `pending` refund for the order →
`completed`, `settledAt`, `amount = amt`. If method is `store_credit` and `settle > 0`: wallet credit
`"Refund for order <orderNumber>"` linked to the ledger row.

**c. Fail** (`/refund/fail`): order `refundStatus:"failed"`; history `"Refund failed"` (note or
`"Settlement failed — re-initiate the refund"`); payment `refund_pending` → `refundAmount > 0 ? "partially_refunded" : "captured"`,
`pendingRefund:null`; ledger newest pending → `failed`.

**d. Direct payment refund** (`POST /admin/payments/{id}/refund`): reject `amount > remaining`;
append `payment_refunds`, bump `refundAmount`, status `partially_refunded|refunded`, `pendingRefund:null`;
order `paymentStatus` mirrors + history `"Refund issued (₹…)"` (note = reason); ledger row
`type:"payment_refund"`, `status:"completed"`, `settledAt = now`.

### 24.6 Return refund cascade (`reflectReturnRefund`, on `PATCH /admin/returns/{id}` reaching `status:"refunded"` / `refundStatus:"processed"`)

`payable = max(0, refundAmount − deductionAmount)`. Find the order's payment; append a payment refund
of `payable` (reason `"Return <returnNumber>"`) → payment status `refunded|partially_refunded`. If the
order had a coupon, `!couponRestored`, and the return covers **every ordered unit** (Σ return qty ≥ Σ
order qty) → restore coupon usage, `couponRestored:true`, history `"Coupon usage restored"`. Order:
`paymentStatus` = payment outcome (`refunded` when no payment row), `fulfillmentStatus:"returned"`,
history `"Return refund processed (<returnNumber>)"` (`"₹… refunded"`). Ledger row
`type:"return_refund"`, `status:"completed"`, `paymentId`, `returnId`, `couponRestored`. If
`refundMethod == "store_credit"` and `payable > 0` and `!storeCreditCredited`: wallet credit
`"Refund for return <returnNumber>"` linked to the ledger row; `storeCreditCredited:true`. If
`restock:true`: restock items and set `restocked:true`. Return `statusHistory` gets the `event`.

### 24.7 Order fulfilment updates

`PATCH /admin/orders/{id}` merges the sent fields and appends the `event`. "Mark as Paid" must also
capture the payment (`pending → captured`, stamp `transactionId` if null: `manual_<ref>`).

---

## 25. Cart

* Guests keep the cart in `localStorage`; the API is used **only for signed-in users**.
* On login the client merges the server cart into the local one (max quantity per line, distinct lines
  kept) and then **mirrors local → server** after every change: `GET /cart`, `DELETE /cart/{id}` for each
  existing row, `POST /cart` for each local line (600 ms debounce, serialized). Expect bursts of
  delete + create requests; make each idempotent and fast. `BACKEND DECISION` (optional): a bulk
  `PUT /cart` would be nicer but is a **FRONTEND CHANGE** — not required.
* Line identity = (`productId`, `variantId`). `quantity` is clamped to `stock` client-side.
* Server: table 11.10; respond with hydrated `CartItem` rows (Section 15.7). `stock` in the response is
  what gates the quantity stepper.
* Clear the user's cart when an order is placed (recommended; the client also clears locally).

---

## 26. Wishlist

* Guests keep it in `localStorage`; on login the client uploads guest-only rows (`POST /wishlist`) and
  merges server rows; the `id` returned by `POST` is stored and later passed to `DELETE /wishlist/{id}`.
* Server: table 11.11, unique (user, product). Respond in the nested `product` form (Section 15.16) so
  the storefront always shows current price/stock/rating; `normalizeWishlistItem` maps
  `product.images[0]`, `product.slug`, etc. `createdAt` becomes `addedAt`.
* `DELETE` of a row that is already gone → `404` (the context surfaces an error toast and re-adds the
  row locally, so prefer `200` when the row belongs to nobody — `BACKEND DECISION`: return `200` for
  an absent row on wishlist/cart deletes to keep the UI calm; `404` is what the mock returns and is
  also handled).

---

## 27. Reviews

* Customer flow: Order History → "Rate / Edit review" on a delivered item → `POST /products/{id}/reviews`.
  One review per user per product; resubmission updates and returns to `pending`. `GET /reviews/mine`
  drives the "Review pending approval / published / not approved" chip.
* Storefront reads only `approved` reviews (`GET /products/{id}/reviews`); `ReviewsSection` reads
  `userName|name`, `isVerifiedPurchase|verified`, `body|comment|text`, `rating`, `title`, `createdAt`,
  `helpfulCount`, `photos[]`.
* Admin: list all, approve/reject (`PATCH { status }`), delete, and **create** admin-authored reviews
  (`userId:null`, `source:"admin"`, default `approved`).
* `products.rating` / `totalReviews`: **recompute** from approved reviews on every review
  create/update/status change/delete (`rating = round(avg, 1)`, `totalReviews = count`). The seed values
  (e.g. 41 reviews) exceed the seed review rows; after migration recompute or keep seed values —
  `BACKEND DECISION` (recommend: keep seed values until the first review change, then recompute).
* `helpfulCount` has no write endpoint (`FRONTEND BEHAVIOR NOT FOUND`); serialize it, keep 0/seed.

---

## 28. Coupons

* Validation rules and messages: Section 13.10. Codes are stored uppercase/trimmed and matched
  case-insensitively. Discount math: Section 24.2. Per-user limit counts the user's orders with that
  `couponCode` where `couponRestored = false`.
* `usedCount` is server-owned: +1 on order create; −1 (floored at 0) on full cancellation and on a
  **full** return refund; partial returns keep the redemption.
* `GET /coupons` (public) returns active, unexpired coupons for the Special Offers page; the page also
  hides exhausted ones. `dealsConfig.featuredCouponIds` orders them.
* Admin CRUD Section 13.22; never reset `usedCount` on update.

---

## 29. Admin APIs

All `/admin/*` routes: `auth:sanctum` + admin ability. Summary of module → endpoints (details in 13.14–13.26):

| Module (screen) | Reads | Writes |
| --- | --- | --- |
| Login | — | `POST /admin/auth/login`, `POST /admin/auth/logout` |
| Dashboard | `GET /admin/dashboard/stats`, `GET /admin/orders`, `GET /admin/products` | — |
| Products | `GET /admin/products`, `GET /admin/categories` | `POST/PUT/DELETE /admin/products` |
| Categories | `GET /admin/categories` | `POST/PUT/DELETE /admin/categories` (409 when in use) |
| Orders | `GET /admin/orders`, `GET /admin/payments?orderId=`, `GET /settings` (invoice) | `PATCH /admin/orders/{id}`, `POST …/cancel`, `POST …/refund/initiate|complete|fail` |
| Returns | `GET /admin/returns`, `GET /admin/orders`, `GET /admin/orders/{id}` | `POST /admin/returns`, `PATCH /admin/returns/{id}` |
| Payments | `GET /admin/payments`, `GET /admin/refunds` | `POST /admin/payments/{id}/refund` |
| Users | `GET /admin/users`, `GET /admin/orders?userId=` | `PATCH /admin/users/{id}` |
| Shipping | `GET /admin/shipping-methods`, `GET /admin/settings` | `POST/PUT/DELETE /admin/shipping-methods`, `PATCH /admin/settings/shipping` |
| Coupons | `GET /admin/coupons` | `POST/PUT/DELETE /admin/coupons` |
| Special Offers | `GET /admin/deals/config`, `GET /admin/products`, `GET /admin/coupons` | `PUT /admin/deals/config` |
| Hero Section | `GET /admin/hero/config`, `GET /admin/banners` | `PUT /admin/hero/config`, `POST/PUT/DELETE /admin/banners`, `PUT /admin/banners/reorder` |
| FAQs | `GET /admin/faqs`, `GET /admin/products` | `POST/PUT/DELETE /admin/faqs`, `PUT /admin/faqs/reorder` |
| Reviews | `GET /admin/reviews`, `GET /products` | `POST /admin/reviews`, `PATCH/DELETE /admin/reviews/{id}` |
| Leads | `GET /admin/leads` | `PATCH/DELETE /admin/leads/{id}` |
| Settings | `GET /admin/settings`, `GET /admin/categories`, `GET /admin/hero/config`, `GET /admin/banners`, `GET /admin/faqs` | `PATCH /admin/settings/store|payment|social` |
| Layout (bell) | `GET /admin/orders`, `GET /admin/leads` (polled) | — |

The audit actor (`statusHistory[].by`, `pendingRefund.by`, `recall.by`, `refunds.by`,
`payment_refunds.by`) is derived from the admin token: `"<firstName> <lastName>"`, falling back to
`email`, then `"Admin"`. Customer-initiated actions use `"Customer"`; system events `"System"`.

---

## 30. Dashboard / Statistics APIs

`GET /admin/dashboard/stats` must reproduce the mock's arithmetic exactly:

| Key | Computation |
| --- | --- |
| `totalProducts` | count of products (all, incl. drafts — the mock counts every row) |
| `totalOrders` | count of orders |
| `totalRevenue` | Σ `orders.total` over **all** orders (mock does not exclude cancelled/refunded — `BACKEND DECISION`: keep for parity, or exclude cancelled and document) |
| `totalUsers` | count of users |
| `pendingOrders` | count where `fulfillmentStatus == "unfulfilled"` **or** `paymentStatus == "pending"` |
| `pendingReturns` | count of returns where `status ∉ {rejected, refunded}` and `refundStatus ∉ {processed, completed}` |
| `lowStockProducts` | count where `stock <= (lowStockThreshold || 10)` |
| `activeCoupons` | count where `isActive` |

Response: `{ "success": true, "data": { "totalProducts": 6, "totalOrders": 11, "totalRevenue": 123456, "totalUsers": 4, "pendingOrders": 3, "pendingReturns": 1, "lowStockProducts": 2, "activeCoupons": 5 } }`.
The dashboard additionally loads `GET /admin/orders` (top 5 by `createdAt`) and `GET /admin/products`
(low-stock list) itself.

---

## 31. Error Handling

`getErrorMessage()` reads, in order: `error.response.data.message` → first value of
`error.response.data.errors` (first element if an array) → `error.message`. So every error body must be:

```json
{ "message": "Human readable summary", "errors": { "field": ["Specific message"] } }
```

Laravel's default validation response already has this shape. Configure the exception handler so
`ModelNotFoundException` → `404 { "message": "Not found" }`, `AuthenticationException` →
`401 { "message": "Unauthenticated." }`, `AuthorizationException` → `403`, and any unexpected exception →
`500 { "message": "Server error" }` (no stack traces in production).

| Situation | Status | `message` |
| --- | --- | --- |
| Validation | 422 | field messages |
| Bad credentials | 401 | `Invalid email or password` |
| Deactivated account | 403 | `This account has been deactivated. Please contact support if you think this is a mistake.` |
| Missing/invalid/expired token, wrong scope | 401 | `Unauthenticated.` → client drops that session |
| Not owner / not allowed | 403 or 404 | |
| Coupon rejections | 422 | Section 13.10 messages (any of 400/404/422 is treated as an expected rejection) |
| Category in use | 409 | Section 13.17 message |
| Refund exceeds remaining | 422 | `Refund exceeds the remaining ₹1,440` |
| Illegal state transition (cancel delivered, complete a non-processing refund) | 409 | descriptive |
| Duplicate coupon code / slug | 422 | descriptive |
| DELETE of absent row | 404 | `{}` or `{ "message": "Not found" }` |
| Shiprocket endpoints (if not implemented) | 501 | `Shiprocket integration is not enabled.` |

The frontend treats coupon rejections, `CATEGORY_IN_USE` and `REFUND_EXCEEDS` as expected outcomes
and shows `message` verbatim — write them for customers/admins, not developers. Timeout is 30 s.

---

## 32. Security Requirements

1. **HTTPS only** on `core.meghalisilk.in`; redirect HTTP → HTTPS; HSTS. `APP_URL` https.
2. **Authentication:** Sanctum tokens; hash with bcrypt (cost ≥ 10); never log or return passwords;
   revoke on logout; `BACKEND DECISION`: token expiry (e.g. 30 days, 90 with `remember`) and
   `sanctum:prune-expired` scheduled.
3. **Authorization:** admin ability on every `/admin/*` route; ownership checks on every customer
   resource (orders, cart, wishlist, wallet, reviews); deactivated users rejected at login and on
   every request (middleware checks `is_active`).
4. **Mass assignment:** explicit `$fillable`; Form Requests whitelist fields; never write
   `paymentStatus`, money fields, counters, ids, timestamps, `by`, `role`, `isActive` (customer),
   `storeCredit` from client input.
5. **Recompute money server-side** on order create, coupon validation, wallet debit, refunds (never
   trust `total`, `storeCreditUsed`, `refundAmount` from the client).
6. **Validation** on every input (Section 16); URLs validated as `http/https`; strings length-limited;
   enums enforced.
7. **SQL injection:** Eloquent/Query Builder with bindings only; no raw string interpolation.
8. **Rate limiting:** `throttle:5,1` on `/auth/login`, `/admin/auth/login`, `/auth/register`;
   `throttle:10,1` on `/leads/contact`, `/leads/newsletter`, `/coupons/validate`; `throttle:60,1` on the
   rest per IP/user. Return `429 { message }`.
9. **CORS:** Section 33 — never `*` in production.
10. **CSRF:** not applicable to Bearer-token API routes (no cookies). Do not enable Sanctum's SPA
    cookie mode; `api` middleware group only.
11. **Sensitive data:** `shiprocketPassword` encrypted at rest and never serialized; `costPrice`
    only on admin responses; `notifications`/`seo` sections admin-only; password hashes never
    serialized; tokens shown once.
12. **Uploads:** none today. If added: MIME sniffing, size limit, random file names, no execution
    from the upload directory.
13. **Headers:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`,
    `Content-Security-Policy` for any HTML pages (the API itself returns JSON only).
14. **Logging:** daily rotating logs, `LOG_LEVEL=warning`, log auth failures and 5xx with request id;
    never log request bodies containing passwords/tokens.
15. **Errors:** `APP_DEBUG=false`; generic 500 body.
16. **Database:** dedicated MySQL user with least privilege; nightly `mysqldump` backups retained ≥ 30
    days, tested restores; `DB_PASSWORD` only in `.env` (chmod 600), never in the repo.
17. **API abuse:** throttle, `413` on oversized bodies (`post_max_size` 2 MB is ample), reject
    non-JSON `Content-Type` on writes, `Accept: application/json` forced via middleware so errors are
    JSON.
18. **Admin accounts:** no self-registration endpoint (none exists in the frontend); create admins via
    seeder/artisan; strong passwords; change the seed `admin@store.com` / `admin123` before go-live.

---

## 33. CORS

The API and the storefront are on different origins. Configure `config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),   // exact origins, no '*'
'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],
'exposed_headers' => [],
'max_age' => 86400,
'supports_credentials' => false,   // Bearer tokens, no cookies
```

* **BACKEND DEPLOYMENT CONFIGURATION REQUIRED:** the production storefront origin is **not in the
  repository** (only the API domain `core.meghalisilk.in` is known). Set `CORS_ALLOWED_ORIGINS` to the
  exact scheme + host(s) the React build is served from (e.g. `https://www.meghalisilk.in,https://meghalisilk.in`).
* Development: add `http://localhost:3000` (CRA default) in the local `.env` only.
* Pre-flight (`OPTIONS`) must return 204 quickly; Axios sends `Authorization` and `Content-Type:
  application/json`, which trigger pre-flight on every non-GET request.
* Do **not** use `Access-Control-Allow-Origin: *` in production.

---

## 34. API Versioning

**Decision:** all routes live under `/api/v1` because `REACT_APP_API_URL` carries the prefix
(`https://core.meghalisilk.in/api/v1`) and `api.js` paths are relative to it. In `routes/api.php`:

```php
Route::prefix('v1')->group(function () {
    Route::post('auth/login', …);          // → /api/v1/auth/login
    Route::prefix('admin')->middleware(['auth:sanctum', 'ability:admin'])->group(function () { … });
});
```

Breaking changes go to `/api/v2` with a new `REACT_APP_API_URL`; additive changes (new keys, new
endpoints) stay in v1. Never remove or rename a key listed in Section 15 within v1.

---

## 35. Laravel Architecture Recommendations

```
app/
  Models/            User, Admin, UserAddress, Category, Product, ProductImage, ProductVariant, ProductLink,
                     CartItem, WishlistItem, Order, OrderItem, OrderStatusHistory, Payment, PaymentRefund,
                     Refund, ProductReturn (table `returns` — "Return" is reserved-ish, name the class ProductReturn),
                     ReturnItem, ReturnStatusHistory, WalletTransaction, Coupon, Review, ShippingMethod, Lead,
                     Banner, Faq, Setting
  Http/Controllers/Api/V1/
      Auth/{CustomerAuthController, AdminAuthController}
      Storefront/{ProductController, CategoryController, BannerController, HeroController, CartController,
                  OrderController, WalletController, ReviewController, ReturnController, CouponController,
                  WishlistController, ShippingController, SettingsController, FaqController, DealsController,
                  LeadController}
      Admin/{DashboardController, ProductController, CategoryController, OrderController, OrderRefundController,
             ReturnController, PaymentController, RefundController, ShippingMethodController, ShiprocketController,
             CouponController, ReviewController, UserController, LeadController, SettingsController,
             DealsConfigController, HeroConfigController, BannerController, FaqController}
  Http/Requests/     one Form Request per write endpoint (Section 16)
  Http/Resources/    UserResource, AdminResource, ProductResource (+ `forAdmin()` to include costPrice),
                     CategoryResource, BannerResource, CartItemResource, OrderResource, ReturnResource,
                     PaymentResource, RefundResource, WalletTransactionResource, CouponResource, ReviewResource,
                     ShippingMethodResource, LeadResource, FaqResource, WishlistItemResource
  Http/Middleware/   EnsureAccountActive, ForceJsonResponse, AdminAbility
  Services/          OrderPricingService (24.2), OrderPlacementService (13.6), OrderCancellationService (24.4),
                     RefundService (24.5), ReturnRefundService (24.6), WalletService (ledger + cache),
                     CouponService (validate/redeem/restore), InventoryService (decrement/restock),
                     OrderNumberGenerator, ReturnNumberGenerator, RefundNumberGenerator, AuditTrail (history rows)
  Policies/          OrderPolicy, CartItemPolicy, WishlistItemPolicy, ReviewPolicy (ownership)
database/
  migrations/        one per table (Section 11), in FK order
  seeders/           DbJsonImportSeeder (Section 37), AdminSeeder
```

Key conventions:

* **Success envelope** via a base controller helper `respond($data, $status = 200)` →
  `{ success: true, data }`. Resources return camelCase keys explicitly (do not rely on automatic
  case conversion).
* **Date serialization:** base model `serializeDate(DateTimeInterface $d)` →
  `$d->format('Y-m-d\TH:i:s.v\Z')` with app timezone UTC.
* **Money casts:** integer columns; cast `'price' => 'integer'`; never return strings.
* **Booleans:** cast to `boolean` so JSON shows `true/false`, not `1/0`.
* **JSON columns:** `array` casts for `tags`, `placements`, `pending_refund`, `recall`, `gateway_response`,
  address snapshots, settings `data`.
* **Transactions:** every service method that touches more than one table runs in `DB::transaction`
  with `lockForUpdate()` on the order/payment/coupon/product rows it mutates.
* **Audit actor:** an `AuditTrail::actor()` helper reads `auth()->user()` → admin display name or
  `"Customer"`.
* **Route model binding** with `whereNumber` where paths could collide (Section 7 note).
* **N+1:** `with(['images','variants','related','frequentlyBoughtTogether'])` for products;
  `with(['items','statusHistory','user'])` for orders.
* **Tests:** feature tests per endpoint asserting the exact JSON keys in Section 15; a parity test that
  places an order and checks payment row, coupon count, wallet debit and history.

---

## 36. Database Migration

Create migrations in dependency order: `users`, `admins`, `personal_access_tokens`, `user_addresses`,
`categories`, `products`, `product_images`, `product_variants`, `product_links`, `coupons`,
`shipping_methods`, `orders`, `order_items`, `order_status_history`, `payments`, `payment_refunds`,
`returns`, `return_items`, `return_status_history`, `refunds` (after returns and payments; add the
`returns.*` → `refunds` FK afterwards if you want both directions), `wallet_transactions`,
`cart_items`, `wishlist_items`, `reviews`, `leads`, `banners`, `faqs`, `faq_product`, `settings`.

Migration snippets for the non-obvious columns:

```php
// products
$table->decimal('rating', 2, 1)->default(0);
$table->json('tags')->nullable();
$table->decimal('weight', 8, 3)->default(0);
$table->decimal('dim_length', 8, 2)->nullable();   // + dim_width, dim_height
// product_variants
$table->string('variant_key', 64); $table->unique(['product_id', 'variant_key']);
$table->json('attributes')->nullable(); $table->string('swatch_hex', 9)->nullable();
// orders
$table->json('shipping_address'); $table->json('billing_address');
$table->json('pending_refund')->nullable(); $table->json('recall')->nullable();
$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
// settings
$table->string('section', 40)->primary(); $table->json('data'); $table->timestamp('updated_at', 3)->nullable();
// all timestamps
$table->timestamps(3);
```

Use `DATETIME(3)`/`timestamps(3)` everywhere so milliseconds round-trip. Add the indexes from Section 11.

---

## 37. db.json Data Migration

Write a one-off `DbJsonImportSeeder` (or an artisan command) that reads the repository's `db.json`
and inserts in this order, keeping the **original numeric ids** (insert with explicit ids, then reset
auto-increment) so every cross-reference (`orderId`, `productId`, `userId`, `refundId`, …) stays valid.

| db.json | → table(s) | Transformation |
| --- | --- | --- |
| `users[]` | `users`, `user_addresses` | `password` → `Hash::make(plain)`; `addresses[]` → rows (keep `id` as-is when numeric); `storeCredit` → recompute from ledger after import |
| `admins[]` | `admins` | hash password; **change the seed password before go-live** |
| `categories[]` | `categories` | 1:1 (`parentId` → `parent_id`) |
| `products[]` | `products`, `product_images`, `product_variants`, `product_links` | `images[i]` → rows with `sort_order=i`; `variants[]` → rows (`id` → `variant_key`, `attributes`/`swatchHex` when present); `relatedProductIds` → `type:'related'`, `frequentlyBoughtTogetherIds` → `type:'fbt'` — **drop ids that do not exist** (seed references 28, 34, 35, 36); `dimensions` → three columns; `comparePrice`/variant prices in the seed contain obviously corrupted values (e.g. `380000000000`) — `BACKEND DECISION`: import verbatim and let the admin fix them, or clamp; document which |
| `cart[]` | `cart_items` | keep `productId`, `variantId`→`variant_key`, `quantity`, `userId`; drop snapshot fields |
| `wishlist[]` | `wishlist_items` | keep `userId`, `productId`, `addedAt`→`created_at`; drop snapshot; skip rows whose product no longer exists |
| `orders[]` | `orders`, `order_items`, `order_status_history` | `items[]` → rows; `statusHistory[]` → rows in array order; `shippingAddress`/`billingAddress` → JSON; `recall`/`pendingRefund` → JSON or NULL; missing keys (`refundStatus`, `storeCreditUsed`, `codFee`…) → defaults; `orderNumber` kept verbatim (both formats) |
| `returns[]` | `returns`, `return_items`, `return_status_history` | same pattern; `images` → `[]`; `rejectReason` absent → NULL |
| `payments[]` | `payments`, `payment_refunds` | `refunds[]` → rows (`id` → `ref_key`); `pendingRefund` → JSON; `orderNumber` dropped (derived) |
| `refunds[]` | `refunds` | 1:1; `orderNumber`/`returnNumber` dropped (derived) |
| `walletTransactions[]` | `wallet_transactions` | 1:1; `orderNumber`/`refundNumber` dropped; then set `users.store_credit` = ledger balance per user |
| `shipping_methods[]` | `shipping_methods` | 1:1 |
| `coupons[]` | `coupons` | `code` upper-cased; `usedCount` kept |
| `reviews[]` | `reviews` | 1:1; `source` when present; `photos` when present; skip rows whose `productId` does not exist (seed has none dangling) |
| `leads[]` | `leads` | 1:1 |
| `banners[]` | `banners` | 1:1 (`updatedAt` only on some rows → `created_at = updated_at`) |
| `faqs[]` | `faqs`, `faq_product` | `placements` → JSON; `productIds[]` → pivot (skip unknown ids) |
| `settings` | `settings` rows `store`,`shipping`,`payment`,`notifications`,`seo`,`social` | `shipping.shiprocketPassword` → encrypt (empty in seed) |
| `heroConfig` | `settings` row `hero_config` | verbatim (`updatedAt` → row timestamp) |
| `dealsConfig` | `settings` row `deals_config` | verbatim; drop ids of missing products/coupons (seed references products 34, 35, 36) |

Notes:

* Dates in `db.json` are already ISO UTC strings — parse with `Carbon::parse()`.
* Booleans are real JSON booleans; nulls are real nulls.
* The seed `faqs[0].question`/`answer` contain stray test text (`###`, `####bhbhhjh`) — clean by hand.
* `orders` seed `items[].productId` sometimes references products (2, 3) that are not in `products` —
  keep `product_id` NULL (SET NULL semantics) and the snapshot name/sku.
* After import: `ANALYZE TABLE`, recompute `users.store_credit`, verify `Σ payments per order ≤ 1`.

---

## 38. Production Deployment

```
                         HTTPS
                           │
                           ▼
                  React Frontend  (CRA static build: `npm run build` → /build, served by any static host / CDN)
                           │
                           │ Axios REST API  (Bearer tokens, JSON, CORS-restricted)
                           ▼
             https://core.meghalisilk.in
                           │  nginx → php-fpm (Laravel, /api/v1)
                           ▼
                     Laravel API  (Sanctum, Eloquent, services, queues)
                           │
                           ▼
                        MySQL 8
```

| Concern | Recommendation |
| --- | --- |
| Server | Ubuntu LTS, nginx, PHP 8.2+/8.3 (`php-fpm`), Composer, MySQL 8, Supervisor for `queue:work` |
| Domain | `core.meghalisilk.in` → API only; TLS via Let's Encrypt (certbot) with auto-renew |
| Deploy | `git pull` → `composer install --no-dev --optimize-autoloader` → `php artisan migrate --force` → `config:cache`, `route:cache`, `view:clear` → `php artisan optimize`; zero-downtime optional |
| Env | `.env` from a vault, `APP_DEBUG=false`, `APP_ENV=production`; file perms 600 |
| Scheduler | cron `* * * * * php artisan schedule:run` (token pruning, backups) |
| Logging | `LOG_CHANNEL=daily`, retain 14–30 days; ship to a log service if available |
| Backups | nightly `mysqldump --single-transaction` + off-site copy; weekly restore test |
| Monitoring | uptime check on `GET /api/v1/settings`; slow-query log; alert on 5xx rate |
| Frontend build | set `REACT_APP_API_URL=https://core.meghalisilk.in/api/v1`, `REACT_APP_USE_MOCK_API=false`, run `npm run build`; serve `/build` with SPA fallback (`index.html` for every route so `/admin/*` and `/products/*` deep links work) |

---

## 39. Domain Configuration

| Item | Value | Status |
| --- | --- | --- |
| API host | `https://core.meghalisilk.in` | given |
| API base (frontend) | `https://core.meghalisilk.in/api/v1` | decided (Section 6) |
| Storefront origin | e.g. `https://www.meghalisilk.in` | **BACKEND DEPLOYMENT CONFIGURATION REQUIRED** — not in repo |
| Old backend | `https://phplaravel-780646-5827390.cloudwaysapps.com/api/v1` (in `.env.production`) | to be replaced |
| Public assets | Cloudinary (`res.cloudinary.com/v8vrixwq/…`) for logos/product images | external, unchanged |
| Store email/phone | `care@meghalisilk.com`, `+91 33 4000 1100` (from settings) | admin-editable |

DNS: `A`/`AAAA` for `core` → API server. Nginx `server_name core.meghalisilk.in;` with
`try_files $uri /index.php?$query_string;` pointing at `public/`.

---

## 40. Postman Testing

`backend-developer-guideline/postman-api-collection.json` (Postman Collection v2.1):

* Variables: `base_url` (= `https://core.meghalisilk.in/api/v1`; override to `http://localhost:8000/api/v1`
  for local), `token`, `admin_token`, plus ids captured by tests (`product_id`, `category_id`, `order_id`,
  `order_number`, `cart_item_id`, `wishlist_item_id`, `coupon_id`, `review_id`, `return_id`, `payment_id`,
  `shipping_method_id`, `banner_id`, `faq_id`, `lead_id`, `user_id`), and sample credentials
  (`customer_email`, `customer_password`, `admin_email`, `admin_password`).
* Folders in execution order: 01 Authentication (customer) → 02 Admin Authentication → 03 Catalogue:
  Products → 04 Categories → 05 Hero & Banners → 06 Cart → 07 Wishlist → 08 Coupons → 09 Shipping,
  Settings, FAQs, Deals → 10 Orders → 11 Wallet → 12 Reviews → 13 Returns (customer) → 14 Leads →
  15 Admin: Dashboard → 16 Admin: Products → 17 Admin: Categories → 18 Admin: Orders → 19 Admin:
  Returns → 20 Admin: Payments & Refunds → 21 Admin: Shipping → 22 Admin: Coupons → 23 Admin: Reviews
  → 24 Admin: Users → 25 Admin: Leads → 26 Admin: Settings → 27 Admin: Deals Config → 28 Admin: Hero
  & Banners → 29 Admin: FAQs → 30 Logout.
* Auth: collection-level `Bearer {{token}}`; every `/admin/*` request overrides with `Bearer {{admin_token}}`;
  public requests use `noauth`.
* Tests: the two logins store `data.token` (and `data.user` / `data.admin` ids); create requests store
  `data.id`; every request asserts the status code and `success === true` (or `message` on error
  cases); list requests assert an array.
* Run order matters (create before update/delete). Run the whole collection with the Collection Runner
  against a seeded database to smoke-test parity.

---

## 41. Frontend Integration

Once the API is deployed:

1. Build with `REACT_APP_API_URL=https://core.meghalisilk.in/api/v1` and `REACT_APP_USE_MOCK_API=false`
   (in `.env.production`; CRA reads it for `npm run build`). No code change.
2. Verify in the browser console (development only prints it): `[API] Mode: Production API`.
3. Smoke test in this order: `GET /settings` (footer name), categories in the header, `/products`,
   a PDP, register → login, add to cart (watch `GET/DELETE/POST /cart` bursts), wishlist, coupon
   validation, place an order (COD and online), Order Confirmation, Order History → cancel, review
   submit; admin login, dashboard stats, every admin table, order fulfil/deliver/refund cycle, return
   create/approve/receive/refund, hero + FAQ + deals saves, settings save (storefront re-reads on tab
   focus).
4. The service layer remains dual-mode: developers can keep `npm run dev` with JSON Server locally.

---

## 42. Known Issues / Backend Decisions Required

Each item: **Problem / Current behaviour / Impact / Recommended solution / Frontend change?**

1. **Client-controlled `paymentStatus` and no gateway.** Checkout sends `"paid"` for online methods with no payment taken. *Impact:* trusting it lets anyone create "paid" orders. *Recommend:* Option A in Section 22.3 (server sets `pending`; admin marks paid). *Frontend:* none for parity; Razorpay integration later is a frontend change.
2. **Shipping method id is not sent on `POST /orders`** (only `shippingAmount`). *Impact:* server cannot know which method was chosen; cannot recompute shipping exactly. *Recommend:* accept `shippingAmount` if it equals the computed cost of any active method for the subtotal (0 or a `flatRate`), store `shipping_method_id` when unambiguous, else `422`. *Frontend (optional, recommended later):* send `shippingMethodId`.
3. **Client-generated `orderNumber`** (`ORD-<base36>-<base36>`). *Recommend:* ignore and generate `ORD-YYYYMMDD-NNNN`. *Frontend:* none (it reads the returned `orderNumber`).
4. **Client-generated address ids** (base-36 strings) in `PUT /auth/user`. *Recommend:* reconciliation rule in Section 11.2. *Frontend:* none.
5. **Full-replace cart sync** (delete all + recreate on every change). *Impact:* many small requests; brief window with an empty server cart. *Recommend:* fast idempotent endpoints; optional bulk endpoint later. *Frontend:* none required.
6. **Coupon `usedCount` counts orders, not successful payments.** With Option A pending online orders still consume a redemption. *Recommend:* keep parity (increment on create, restore on cancel). *Frontend:* none.
7. **Password minimum differs** (register 6, change-password 8). *Recommend:* 8 server-side for both. *Frontend:* none (registration hint says "Min. 6 characters" — cosmetic).
8. **403 vs 401 on wrong-scope token.** The interceptor only clears a session on 401. *Recommend:* return 401 for an invalid/wrong-scope token so the stale session is dropped. *Frontend:* none.
9. **`totalRevenue` includes cancelled/refunded orders** in the mock. *Recommend:* parity or documented exclusion (Section 30). *Frontend:* none.
10. **Seed data quality:** corrupted prices (`comparePrice: 380000000000`), dangling `relatedProductIds`/`dealsConfig` ids, FAQ test text, order items referencing missing products, plain-text passwords, admin `admin123`. *Recommend:* clean during import (Section 37). *Frontend:* none.
11. **Optional product fields without a writer** (`features`, `specifications`, `weaveType`, …, variant `attributes`/`swatchHex`). *Recommend:* serialize when present, do not add admin fields now. *Frontend:* none.
12. **`GET /settings` exposure.** The singleton holds `shiprocketPassword`, admin emails, analytics ids. *Recommend:* public endpoint returns `store`, `payment`, `social` only. *Frontend:* none (reads exactly those).
13. **`costPrice` on public products.** *Recommend:* strip from storefront responses. *Frontend:* none.
14. **Shiprocket endpoints without callers.** *Recommend:* `501` until commissioned. *Frontend:* none.
15. **Customer return endpoints without callers** (`POST/GET /returns`). *Recommend:* implement minimally (Section 13.9). *Frontend:* none.
16. **Recompute vs. accept client totals** when prices change between cart and checkout. *Recommend:* `422 "Prices have changed…"`. *Frontend:* none (shows the message).
17. **Wishlist/cart delete of an absent row.** Mock returns 404 and the UI shows an error toast + rollback. *Recommend:* `200` when the row is absent for the current user's own list (idempotent delete) — both are handled.
18. **Product rating/totalReviews seed vs. computed.** *Recommend:* keep seed until first review event, then recompute.
19. **Token lifetime / remember me.** *Recommend:* 30 days default, 90 days when `remember:true`; prune expired.
20. **Roles.** Single admin role in practice. *Recommend:* keep `role` column, all admins full access.

---

## 43. Frontend Changes Required, If Any

**None are required** for the switch to `https://core.meghalisilk.in/api/v1`. The dual-mode service
layer already targets every endpoint in this guide, and every response shape above matches what the
components read.

Optional, recommended follow-ups (each labelled **FRONTEND CHANGE REQUIRED** only if adopted):

| Change | Why |
| --- | --- |
| Send `shippingMethodId` in `POST /orders` | lets the server recompute shipping exactly (Issue 2) |
| Stop sending money/status fields the server ignores (`total`, `paymentStatus`, `orderNumber`, `createdAt`) | clarity; harmless today |
| Real Razorpay checkout (create-order + verify endpoints, `checkout.js`) | actual online payments (Section 22.4) |
| Bulk `PUT /cart` sync | fewer requests than delete-all + recreate |
| Server-side pagination for `/products` and admin tables (`page`, `per_page`, `meta`) | only when the catalogue grows beyond a few thousand rows |
| Customer return-request form using `POST /returns` | today returns are opened by the admin from support leads |
| Update `.env.production` to the new API URL | deployment step, not a code change |

---

## 44. Final Acceptance Criteria

Analysis
- [x] Complete codebase analyzed (`src/**`, `server.js`, `db.json`, `package.json`, `.env*`, `public/`)
- [x] db.json completely analyzed (20 collections, every field, enum and reference)
- [x] server.js analyzed (safe non-cascading DELETE only)
- [x] API service layer analyzed (`api.js` 2,797 lines, `baseURL.js`)
- [x] All API calls identified (110 Laravel endpoints; no Axios/fetch outside `api.js`)
- [x] All frontend API dependencies identified (contexts, pages, components, admin layout)

Documentation (this guide)
- [x] Authentication documented (Bearer, two sessions, storage, 401 handling, Sanctum plan)
- [x] Authorization documented (guest / customer / admin; ownership rules)
- [x] Roles documented (`admins.role`, no gating today)
- [x] MySQL schema documented (28 tables, columns, types, nullability, defaults, keys, indexes)
- [x] Relationships documented (ER + cascade rules)
- [x] All endpoints documented (Section 13, 110 endpoints)
- [x] Request payloads documented (Section 14, verbatim from call sites)
- [x] Response structures documented (Section 15)
- [x] Validation documented (Section 16)
- [x] Pagination documented (none server-side; full lists)
- [x] Search documented · [x] Filtering documented · [x] Sorting documented
- [x] File uploads documented (none exist)
- [x] Payment behaviour documented (no gateway; payment row; refund lifecycle; decision)
- [x] Shipping behaviour documented (flat/free methods; manual tracking; Shiprocket not implemented)
- [x] Cart documented · [x] Wishlist documented · [x] Orders documented · [x] Reviews documented · [x] Coupons documented
- [x] Admin functionality documented (16 modules)
- [x] Dashboard statistics documented
- [x] Error handling documented · [x] Security documented · [x] CORS documented · [x] API versioning documented
- [x] Production deployment documented · [x] Domain configuration documented
- [x] db.json migration documented
- [x] Known issues documented · [x] Backend decisions clearly identified (20) · [x] Frontend changes identified (none required)

Postman
- [x] Postman collection created (v2.1, `{{base_url}}`, Bearer variables, tests)
- [x] Postman collection validated (JSON parse, structure, methods, URLs, variables, scripts)
- [x] All endpoints represented in Postman (110/110, cross-checked by script)
- [x] Postman authentication workflow (logins capture `data.token` → `token` / `admin_token`)
- [x] Documentation and Postman cross-checked (every guide endpoint ↔ every collection request)
- [x] Frontend compatibility verified conceptually (each `api.js` Laravel branch ↔ a specified endpoint and shape)

Backend "definition of done" (for the Laravel developer)
- [ ] Every route in Section 7 exists under `/api/v1`, with the auth scope shown
- [ ] Every success response is `{ success:true, data }`; every error is `{ message, errors? }` with the status in Section 31
- [ ] camelCase keys, ISO `…Z` dates with milliseconds, numeric ids, string variant ids, integer INR
- [ ] Nested shapes round-trip: `variants`, `images`, `dimensions`, `items`, `statusHistory`, `addresses`, `refunds`, `pendingRefund`, `recall`, `settings.*`, `heroConfig.*`, `dealsConfig.*`
- [ ] `POST /orders` recomputes money, generates the number, creates the payment row, bumps the coupon, debits the wallet, seeds history — in one transaction
- [ ] Cancellation, refund initiate/complete/fail, payment refund, return refund, restock and coupon restore behave as in Section 24
- [ ] Wallet balance = ledger; `users.storeCredit` cache kept in sync
- [ ] Category delete blocked with 409 when referenced
- [ ] Reorder endpoints persist `sortOrder`; settings PATCH merges; hero/deals PUT replace
- [ ] Storefront reads hide drafts/inactive rows; admin reads include them
- [ ] Sanctum tokens with customer/admin abilities; logout revokes; 401 clears the right session
- [ ] Rate limits, CORS (exact origins), HTTPS, hashed passwords, encrypted Shiprocket password, no `costPrice`/secrets on public endpoints
- [ ] `db.json` imported with original ids and the clean-ups in Section 37
- [ ] Postman collection runs green against the deployed API
- [ ] Storefront + admin behave identically with `REACT_APP_API_URL=https://core.meghalisilk.in/api/v1` and `REACT_APP_USE_MOCK_API=false`

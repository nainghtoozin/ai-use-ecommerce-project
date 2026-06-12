# Project Architecture Audit

**Generated:** 2026-06-12
**Project:** ai-use-ecommerce-project
**Laravel Version:** 12.x
**Frontend:** React 19 + Inertia.js 3 + Tailwind CSS 4

---

## Route Architecture

### Route Loading

Routes are loaded via `bootstrap/app.php` using Laravel 12's `withRouting()` API. The web middleware group appends four global middleware: `IdentifyTenant`, `HandleInertiaRequests`, `CheckUserStatus`, `CheckMaintenanceMode`.

### Route Files

| File | Exists | Purpose |
|------|--------|---------|
| `routes/web.php` | Yes | Main web routes: public, admin, superadmin, storefront, storefront-admin, cart, wishlist, chat, notifications |
| `routes/auth.php` | Yes | Login, register, password reset, email verification (guest + auth groups) |
| `routes/storefront-admin.php` | Yes | `store/{slug}/admin/*` — tenant-scoped admin panel (included from web.php) |
| `routes/console.php` | Yes | Artisan commands: `messages:cleanup`, `subscriptions:process-expired`, `subscriptions:send-expiry-warnings` |
| `routes/channels.php` | Yes | Broadcasting channels for chat and notifications |
| `routes/api.php` | No | Not registered in `bootstrap/app.php` |

### Middleware Aliases

| Alias | Class | Applied To |
|-------|-------|------------|
| `storefront` | `Storefront` | All `store/{slug}/*` routes — resolves tenant from slug |
| `tenant.binding` | `ValidateTenantBinding` | Storefront + admin routes — validates model tenant_id matches current tenant |
| `tenant.access` | `CheckTenantAccess` | Customer area — user.tenant_id must match current tenant |
| `tenant.valid` | `TenantIsValid` | Admin routes — user must have valid tenant_id and tenant record |
| `tenant.active` | `EnsureTenantIsActive` | Admin operations — tenant must be active/trialing with valid subscription |
| `role` | `RoleMiddleware` | Permission gating |
| `check.status` | `CheckUserStatus` | Global web — logs out suspended/banned users |

### Route: `/`

| Property | Value |
|----------|-------|
| **Route file** | `routes/web.php` |
| **Middleware** | `web` group only (IdentifyTenant, HandleInertiaRequests, CheckUserStatus, CheckMaintenanceMode) |
| **Controller** | `ClientController@index` |
| **Inertia page** | `Client/Products/Index` |
| **Purpose** | Root homepage / landing page, acts as public catalog for "default" tenant |

### Route: `/login`

| Property | Value |
|----------|-------|
| **Route file** | `routes/auth.php` (included from web.php) |
| **Middleware** | `web`, `guest` |
| **Controller** | `AuthenticatedSessionController@create` |
| **Inertia page** | `Auth/Login` |
| **Purpose** | SuperAdmin and global login page. Blocks tenant-scoped users with "login through your store URL" error |

### Route: `/register`

| Property | Value |
|----------|-------|
| **Route file** | `routes/auth.php` |
| **Middleware** | `web`, `guest` |
| **Controller** | `RegisteredUserController@create` |
| **Inertia page** | `Storefront/Register` (renders same page as storefront register) |
| **Purpose** | Customer registration. Rejects with "register from a specific store" if no tenant context |

### Route: `/create-store`

| Property | Value |
|----------|-------|
| **Route file** | `routes/web.php` |
| **Middleware** | `web` group only |
| **Controller** | `CreateStoreController@index` |
| **Inertia page** | `Public/CreateStore` |
| **Purpose** | New store registration form for merchants |

### Route: `/store/{store_slug}`

| Property | Value |
|----------|-------|
| **Route file** | `routes/web.php` |
| **Middleware** | `web`, `storefront`, `tenant.binding` |
| **Controller** | `StorefrontController@index` |
| **Inertia page** | `Storefront/Index` |
| **Purpose** | Tenant-specific storefront landing page with product grid |

### Route: `/store/{store_slug}/login`

| Property | Value |
|----------|-------|
| **Route file** | `routes/web.php` |
| **Middleware** | `web`, `storefront` |
| **Controller** | `StorefrontLoginController@create` |
| **Inertia page** | `Storefront/Login` |
| **Purpose** | Tenant-scoped customer/admin login. Validates user belongs to this store's tenant |

### Route: `/store/{store_slug}/register`

| Property | Value |
|----------|-------|
| **Route file** | `routes/web.php` |
| **Middleware** | `web`, `storefront` |
| **Controller** | `RegisteredUserController@create` |
| **Inertia page** | `Storefront/Register` |
| **Purpose** | Tenant-scoped customer registration. Sets tenant_id on user |

### Route: `/store/{store_slug}/admin/*`

| Property | Value |
|----------|-------|
| **Route file** | `routes/storefront-admin.php` |
| **Middleware** | `web`, `storefront`, `auth`, `role:admin`, `tenant.valid`, `tenant.access`, `tenant.binding` (inner operations: `tenant.active`) |
| **Controller** | Multiple admin controllers (AdminProductController, AdminOrderController, etc.) |
| **Inertia pages** | `Admin/*` (AdminDashboard, etc.) |
| **Purpose** | Full tenant-scoped admin panel for store management |

### Route: `/admin/*` (standalone, no store slug)

| Property | Value |
|----------|-------|
| **Route file** | `routes/web.php` |
| **Middleware** | `web`, `auth`, `role:admin`, `tenant.valid`, `tenant.binding` (inner: `tenant.active`) |
| **Controller** | Same admin controllers as storefront-admin, no store prefix |
| **Purpose** | Legacy admin routes — accessed when tenant is resolved via IdentifyTenant (subdomain/session) instead of URL slug |

### Additional Public Routes

| URI | Controller | Page |
|-----|-----------|------|
| `/products` | `ClientController@products` | `Client/Products/Products` |
| `/cart` | `CartController@index` | (Inertia) |
| `/checkout` | `CheckoutController@index` | (Inertia) |
| `/client/*` | `ClientController`, `StaticPagesController` | Static pages (about, contact, faq, privacy, terms) |

### SuperAdmin Routes

| Prefix | Middleware | Purpose |
|--------|-----------|---------|
| `/superadmin/*` | `auth`, `role:superadmin` | Tenant management, plan management, subscription management, impersonation |

---

## Homepage Architecture

**URL:** `/` (root)
**Inertia Page:** `resources/js/Pages/Client/Products/Index.jsx`
**Layout:** `ShopLayout`

```
ShopLayout
├── FlashMessages
├── ShopNavbar
│     └── NotificationBell
├── Client/Products/Index
│     ├── HeroSection
│     │     └── props: { websiteInfo }
│     ├── [optional: when no active filters]
│     │     ├── PromotionBanner
│     │     │     └── props: { banners }
│     │     ├── FeaturedCategories
│     │     │     └── props: { categories }
│     │     ├── FeaturedProducts (Latest Products)
│     │     │     └── props: { products, title, subtitle, onAddToCart, addingId }
│     │     │     └── ProductCard
│     │     ├── FeaturedProducts (Featured Products)
│     │     │     └── same structure
│     │     ├── FeaturedProducts (Best Sellers)
│     │     │     └── same structure
│     │     └── StoreFeatures
│     ├── Filter Bar (search input + category dropdown + sort)
│     ├── InfiniteScroll > ProductCard[]
│     └── BackToTopButton
└── ShopFooter
      └── ContactDrawer
```

**Props received from controller:**
- `products` (paginated, scroll)
- `featuredCategories` — top 6 categories by product count
- `latestProducts` (deferred) — 8 most recent products
- `featuredProducts` (deferred) — 8 products with photos
- `bestsellerProducts` (deferred) — 8 most ordered products
- `promotionBanners` — active promotion banners
- `promotionBanners` — legacy PromotionBanner model banners
- `hasProducts` — boolean for empty state
- `categories`, `searchQuery`, `filters`

**Controller:** `app/Http/Controllers/Client/ClientController.php`

---

## Storefront Architecture

**URL:** `/store/{store_slug}`
**Inertia Page:** `resources/js/Pages/Storefront/Index.jsx`
**Layout:** `ShopLayout`

```
ShopLayout
├── FlashMessages
├── ShopNavbar
│     └── NotificationBell
├── Storefront/Index
│     ├── HeroSection
│     │     └── props: { store, websiteInfo }
│     ├── Filter Bar (search input + category dropdown + sort)
│     ├── [if no products] EmptyStoreState
│     │     └── props: { storeName }
│     ├── [if has products] ProductGrid
│     │     └── props: { products, hasMore, loading, onAddToCart, addingId, onClearFilters }
│     │     └── InfiniteScroll > ProductCard[]
│     └── BackToTopButton
└── ShopFooter
      └── ContactDrawer
```

**Props received from controller:**
- `tenant` — the resolved Tenant model for current store
- `products` (paginated, scroll)
- `categories` — all categories for this tenant
- `searchQuery`, `filters` (category_id, sort)
- `hasProducts` — boolean

**Controller:** `app/Http/Controllers/StorefrontController.php`

---

## Shared Components

Components used by **both** `/` and `/store/{store_slug}`:

| Component | File | Purpose | Used By |
|-----------|------|---------|---------|
| `HeroSection` | `Components/Storefront/HeroSection.jsx` | Hero card with store name/logo/CTAs + right-side image carousel. Props adapt: homepage passes only `websiteInfo`, storefront passes `store+websiteInfo` | Both Index pages |
| `ShopLayout` | `Layouts/ShopLayout.jsx` | Main layout with Navbar, FlashMessages, children, Footer | Both Index pages |
| `ShopNavbar` | `Components/ShopNavbar.jsx` | Sticky top navigation with logo, search, cart icon, wishlist icon, user menu. Reads tenant/website_info from Inertia share | Both (via ShopLayout) |
| `ShopFooter` | `Components/ShopFooter.jsx` | Site footer with links and contact drawer. Reads website_info from Inertia share | Both (via ShopLayout) |
| `FlashMessages` | `Components/FlashMessages.jsx` | Global toast notifications | Both (via ShopLayout) |
| `BackToTopButton` | `Components/BackToTopButton.jsx` | Scroll-to-top FAB | Both Index pages |
| `ProductCard` | `Components/ProductCard.jsx` | Individual product card with image, name, price, add-to-cart | Both (via InfiniteScroll/ProductGrid) |
| `useCart` | `Hooks/useCart.js` | Cart state management hook | Both Index pages |

---

## Homepage Only Components

Components used **only** by `/`:

| Component | File | Purpose |
|-----------|------|---------|
| `FeaturedCategories` | `Components/Storefront/FeaturedCategories.jsx` | Grid of top categories with images and product counts |
| `FeaturedProducts` (×3) | `Components/Storefront/FeaturedProducts.jsx` | Latest Products, Featured Products, Best Sellers sections |
| `PromotionBanner` | `Components/Storefront/PromotionBanner.jsx` | Legacy carousel banner from PromotionBanner model |
| `StoreFeatures` | `Components/Storefront/StoreFeatures.jsx` | Static feature highlights (shipping, support, etc.) |

These are rendered only when no active search/filter is applied (`!hasActiveFilters`).

---

## Storefront Only Components

Components used **only** by `/store/{store_slug}`:

| Component | File | Purpose |
|-----------|------|---------|
| `ProductGrid` | `Components/ProductGrid.jsx` | Wrapper for InfiniteScroll with loading/empty/end states |
| `EmptyStoreState` | `Components/Storefront/EmptyStoreState.jsx` | Displayed when the store has no products yet |

---

## Authentication Flow

### Customer Registration

```
/store/{slug}/register (GET)
  → RegisteredUserController@create
  → Checks: allow_registration enabled, Tenant::getCurrent() exists
  → Renders: Storefront/Register

/store/{slug}/register (POST)
  → RegisteredUserController@store
  → Validates: name, email, password
  → Creates: User with tenant_id = current tenant
  → Creates: customer role (tenant-scoped) if not exists
  → Assigns: customer role to user
  → Fires: Registered event
  → Logs in: Auth::login(user)
  → Redirects: admin → store/{slug}/admin/dashboard, customer → store/{slug}
```

### Customer Login

```
/store/{slug}/login (GET)
  → StorefrontLoginController@create
  → Checks: Tenant::getCurrent() exists
  → Renders: Storefront/Login

/store/{slug}/login (POST)
  → StorefrontLoginController@store
  → Checks: user is active, tenant not suspended, tenant_id matches current tenant
  → Auto-assigns: tenant_id for legacy users
  → Authenticates: LoginRequest->authenticate()
  → Regenerates session
  → Redirects: admin → admin/dashboard, customer → storefront index
```

### Merchant / Admin Login

Same as Customer Login via `/store/{slug}/login`. The controller detects admin role via `$user->isAdmin()` and redirects to store-scoped admin dashboard.

Alternatively, merchant (store owner) can use:
- `/superadmin/login` → `AuthenticatedSessionController@create` → redirects to superadmin dashboard
- Not normally used by merchants originally, but accessible

### Super Admin Login

```
/login (GET)
  → AuthenticatedSessionController@create
  → Renders: Auth/Login

/login (POST)
  → AuthenticatedSessionController@store
  → Blocks: tenant-scoped users ("login through your store URL")
  → Authenticates
  → Redirects: admin → admin/dashboard, superadmin → superadmin dashboard
```

### Logout

```
/logout (POST)
  → AuthenticatedSessionController@destroy
  → Determines context from: POST data, referrer URL, or user role
  → Redirects based on context:
      - superadmin → /superadmin/login
      - admin from store/{slug} → store/{slug}/admin/login
      - customer from store/{slug} → store/{slug}
      - fallback → /
```

### Email Verification

```
/verify-email/{id}/{hash} (signed URL)
  → VerifyEmailController
  → Validates hash (sha1 of user email)
  → Marks email as verified
  → Fires: Verified event
  → Redirects: tenant user → store/{slug}/onboarding/complete
               other → login with success status
```

### Registration Gate

The global `/register` route (in auth.php) and `/store/{slug}/register` both use `RegisteredUserController@create`. The controller checks:
1. `WebsiteInfo::getSettings()->allow_registration` — if disabled, redirects to login
2. `Tenant::getCurrent()` — if no tenant, redirects with "register from a specific store"

This means root-level `/register` always redirects away because there is no tenant context (HandleInertiaRequests sets tenant to null when no store_slug in URL).

---

## Tenant Resolution Flow

### Flow Summary

```
Request arrives
  ↓
IdentifyTenant (global web middleware)
  ├── If authenticated user:
  │     ├── SuperAdmin → skip, proceed
  │     └── Others → resolve from user.tenant_id → Tenant::find()
  ├── If unauthenticated:
  │     ├── Subdomain → Tenant::where('slug', subdomain)
  │     ├── X-Tenant header → Tenant::where('slug' or 'domain')
  │     ├── Session → Tenant::where('slug', session value)
  │     └── Fallback → Tenant::getDefault() (cached)
  ↓
Bind to app('current.tenant') and $request->merge(['tenant'])
  ↓
Storefront middleware (on store/{slug} routes)
  ├── Read store_slug from URL
  ├── StoreResolver::resolve() → cached Tenant::where('slug')
  ├── OVERRIDES IdentifyTenant's binding
  └── Bind to app('current.tenant') and $request->merge(['tenant'])
  ↓
HandleInertiaRequests
  ├── Reads app('current.tenant')
  ├── Nulls tenant if no store_slug in route (prevents root domain leakage)
  └── Shares to frontend as 'tenant' prop
```

### Key Middleware Details

**IdentifyTenant** (`app/Http/Middleware/IdentifyTenant.php`):
- Global web middleware, runs on every request
- For authenticated non-SuperAdmin users: resolves tenant from `user.tenant_id`
- For guests: tries subdomain → header → session → default
- Binds result to `app('current.tenant')`

**Storefront** (`app/Http/Middleware/Storefront.php`):
- Route middleware applied only to `store/{store_slug}` routes
- Resolves tenant from URL slug using `StoreResolver` (cached for 1 hour)
- **Overrides** any previous tenant binding
- Aborts 404 if slug is invalid

**HandleInertiaRequests** (`app/Http/Middleware/HandleInertiaRequests.php`):
- Nulls the tenant if no `store_slug` parameter exists in the route
- Prevents `/store/default/...` URL generation on root domain pages

**Tenant model** (`app/Models/Tenant.php`):
- `getCurrent()`: `App::make('current.tenant')` — retrieves from service container
- `getDefault()`: Cached query for tenant where `slug = 'default'`

**StoreResolver** (`app/Services/StoreResolver.php`):
- `resolve(slug)`: `Cache::remember("store_resolver.{slug}", 3600, ...)` — 1 hour cache
- `clearCache(slug)`: Clears cache when tenant is updated

---

## Store Creation Flow

### Step-by-step

```
User visits /create-store (GET)
  → CreateStoreController@index
  → Renders: Public/CreateStore

User submits form (POST /create-store)
  → CreateStoreController@store
  → Validates: name, slug, description, domain, owner_name, owner_email, password
      - slug: unique in tenants, regex /^[a-z0-9\-]+$/
      - owner_email: unique in users
      - password: min 8 chars

  → DB::transaction():
      Step 1: Create Tenant
        - Fields: name, slug, domain, store_url (="/store/{slug}"),
                  status="pending", settings (description)
        - Clear default tenant cache

      Step 2: Create Free Subscription
        - Find Plan::free()
        - Subscription: plan_id, billing_interval, status="pending",
                        starts_at=null, expires_at=null

      Step 3: Create Tenant-Scoped Roles
        - For each ['admin', 'customer']:
            Skip if role exists for this tenant
            Create role with tenant_id
            Copy permissions from global role (tenant_id=null)

      Step 4: Create Owner User
        - Fields: name, email, password (hashed), status=active
        - Set: tenant_id, is_owner=true
        - Assign: admin role

  → After transaction:
      - Fire: Registered(event)
      - Redirect: /store-registration/success?store={slug}

User sees success page
  → CreateStoreController@success
  → Renders: Public/StoreRegistrationSuccess

User receives verification email
  → Clicks signed URL: /verify-email/{id}/{hash}
  → VerifyEmailController
  → Marks email as verified
  → Redirects: /store/{slug}/onboarding/complete (if tenant user)

User sees onboarding complete page
  → CreateStoreController@onboarding
  → Renders: Public/OnboardingComplete
  → Shows: admin login URL, store URL, subscription plan
```

### Issues in Flow

- Tenant status is `pending` from creation until email verification
- Admin user cannot access admin panel until email is verified (`EnsureTenantIsActive` blocks "pending" tenants)
- Subscription has `status=pending` with `starts_at=null` and `expires_at=null`
- No explicit "activate tenant" step after verification — the onboarding page is just informational
- Password validation uses `min:8` string rule instead of Laravel's `Rules\Password::defaults()`

---

## Shared Component Risk Analysis

### Duplicated UI Risks

| Risk | Severity | Description |
|------|----------|-------------|
| **HeroSection dual-purpose** | HIGH | Receives different props depending on caller (`websiteInfo` only vs `store+websiteInfo`). The component must handle both `store` being present and null, complicating its internal logic. Small visual/behavioral differences could cause regressions in one context while fixing the other |
| **ShopNavbar tenant awareness** | MEDIUM | Navbar reads tenant from shared Inertia props. On the root homepage, tenant is null so store-specific links (admin dashboard, My Account) may disappear or show incorrectly. Any rendering branch that assumes tenant exists will crash on homepage |
| **ShopFooter no tenant awareness** | LOW | Footer only reads `website_info`, not tenant data. Safe to share as-is |

### Route Coupling Risks

| Risk | Severity | Description |
|------|----------|-------------|
| **Root login rejects tenant users** | MEDIUM | `AuthenticatedSessionController@store` explicitly blocks users with `tenant_id` from root `/login`. This is intentional but fragile — if a tenant user lands here, the error message ("login through your store URL") is unhelpful without context about their store |
| **Global register redirects away** | LOW | `/register` always redirects to login because no tenant context exists. The message "register from a specific store" is vague |
| **Mixed admin URL patterns** | MEDIUM | Admin routes exist both as `/admin/*` (standalone) and `/store/{slug}/admin/*` (storefront). The same controllers serve both via different middlewares. This works but creates two entry points to the same functionality |

### Tenant Leakage Risks

| Risk | Severity | Description |
|------|----------|-------------|
| **IdentifyTenant over-broad resolution** | HIGH | For unauthenticated users, IdentifyTenant tries subdomain → header → session → default. If none match, it uses `Tenant::getDefault()` (tenant with `slug='default'`). This means any unauthenticated request without context gets bound to the "default" tenant, potentially exposing its data on the root homepage |
| **HandleInertiaRequests null guard** | HIGH | The middleware manually nulls `tenant` when no `store_slug` is in the route. This is a critical safety net. Without it, all root domain pages would inherit the default tenant context and generate `/store/default/...` URLs |
| **StoreResolver caching** | MEDIUM | 1-hour cache on tenant resolution. If a tenant is renamed or deactivated, the storefront will serve stale data for up to 1 hour |

### Authentication Risks

| Risk | Severity | Description |
|------|----------|-------------|
| **Global login blocks tenant users** | LOW (by design) | Blocks tenant users with an error message. Works as intended but creates a confusing UX for customers who find their way to the root login |
| **StorefrontLogin tenant_id auto-assignment** | MEDIUM | If a user has `tenant_id = null`, the login handler auto-assigns the current store's tenant_id. This means a user who registers without a tenant context (if somehow possible) gets silently attached to whichever store they first log into |
| **CheckUserStatus logs out suspended tenant users aggressively** | MEDIUM | Global middleware that logs out tenant users if their tenant is suspended. Non-admin users are logged out and redirected to root login — but they should be redirected to their store's login |
| **VerifyEmailController redirect ambiguity** | LOW | After verification, redirects to `storefront.onboarding.complete` for tenant users. For a customer (not store owner), this onboarding page is irrelevant |

### Homepage/Storefront Coupling Risks

| Risk | Severity | Description |
|------|----------|-------------|
| **ShopLayout shared by both** | MEDIUM | Both homepage and storefront use the same layout. Any change to the layout structure (navbar, footer) affects both contexts simultaneously |
| **HeroSection shared** | HIGH | The hero is the most visually distinct component and must serve two masters. The homepage version lacks store context, the storefront version adds it. Conditional rendering in this component is a frequent source of bugs |
| **Inconsistent section availability** | MEDIUM | Homepage has FeaturedCategories, FeaturedProducts, PromotionBanner, StoreFeatures (when no filters active). Storefront has none of these. This disparity means the two pages look very different structurally, yet share the same layout |

---

## Separation Recommendation

### SAFE TO SHARE

| Component | Rationale |
|-----------|-----------|
| **ShopNavbar** | Reads `website_info` from shared props. Tenant-aware branches are minimal (admin link, My Account). Well-encapsulated |
| **ShopFooter** | No tenant dependency. Reads only global site settings |
| **FlashMessages** | Purely functional, no tenant dependency |
| **BackToTopButton** | Stateless, no dependency on context |
| **ProductCard** | Pure presentational component. Receives product data as props |
| **useCart hook** | Works with session-based cart. No tenant coupling |
| **ShopLayout** | Reasonable to share as it's structural (layout shell). Components inside it (navbar, footer) are already shared |
| **GuestLayout** | Used by all auth pages (login, register). No tenant dependency |

### SHOULD BE SEPARATED

| Component | Rationale |
|-----------|-----------|
| **HeroSection** | Different prop shapes (`websiteInfo` vs `store+websiteInfo`), different visual needs (homepage welcome vs storefront branding). Should be split into `HomepageHero` and `StorefrontHero` |
| **Client/Products/Index** (homepage page) | Already substantially different from Storefront/Index. The homepage includes FeaturedCategories, FeaturedProducts (×3), PromotionBanner, StoreFeatures — the storefront has none of these |
| **Storefront/Index** | Clean, minimal flow (Hero→Filters→Products). Should remain independent of homepage changes |
| **RegisteredUserController** | Used for both global `/register` (which always fails due to missing tenant) and storefront `/store/{slug}/register`. The global route is effectively dead; a dedicated `StorefrontRegistrationController` would be cleaner |
| **Admin routes** | The duplicate `/admin/*` and `/store/{slug}/admin/*` patterns should be consolidated. The standalone `/admin/*` pattern relies on IdentifyTenant for tenant resolution, which is inconsistent |

### UNCERTAIN

| Component | Rationale |
|-----------|-----------|
| **FeaturedCategories** | Currently homepage-only after storefront cleanup. Could be reintroduced to storefront later. Keep as is for now |
| **FeaturedProducts** | Homepage-only after cleanup. Could be useful on storefront too. Separate if storefront needs a different layout |

---

## Version 2 Readiness

### Multi Tenant

**Status: PASS**

The architecture fully supports multi-tenant stores:
- Tenant model with proper relationships
- Tenant-scoped middleware (IdentifyTenant, Storefront, CheckTenantAccess, ValidateTenantBinding)
- All admin controllers filter by `$request->user()->tenant_id`
- Product, Order, Category, User, and other models have `tenant_id` column
- Tenant-scoped roles and permissions
- Proper tenant isolation in middleware

**Notes:**
- `IdentifyTenant` falls back to a "default" tenant for unauthenticated requests without context — this is fine for the platform homepage but could cause confusion if a store is set as default unintentionally
- Slight inconsistency between `tenant_id` on some models vs tenant resolution via `getCurrent()`

### Store URLs

**Status: PASS**

- Every store has a unique `/store/{slug}` URL
- `store_url` field on Tenant model stores the canonical URL
- `Storefront` middleware resolves tenant from slug
- `StoreResolver` caches resolution for performance
- URL-based tenant resolution works without authentication

**Notes:**
- Subdomain-based resolution (`storename.example.com`) is implemented in `IdentifyTenant` but not actively used (no wildcard DNS configuration)
- No custom domain support beyond the `domain` field on Tenant

### Customer Registration

**Status: PASS**

- Registration is scoped to store URL
- `RegisteredUserController` requires `Tenant::getCurrent()`
- New users get `tenant_id` assigned at creation
- Tenant-scoped customer role is created automatically
- Email verification is sent via `Registered` event

**Notes:**
- Global `/register` route is effectively dead (always fails with "register from a specific store")
- `allow_registration` setting can disable registration globally
- Password validation uses `Rules\Password::defaults()` in `store()` but only `min:8` string in `CreateStoreController`

### Customer Login

**Status: PASS**

- Login is scoped to store URL via `StorefrontLoginController`
- Tenant/user matching enforced (`tenant_id == current tenant id`)
- Cross-tenant login is blocked
- User status checks (active/suspended/banned) enforced
- Legacy users with null `tenant_id` get auto-assigned

**Notes:**
- Root `/login` explicitly blocks tenant users (by design)
- Tenant suspension cascades to login lockout
- Admin login uses the same form and controller as customer login (differentiated by role check post-authentication)

### Merchant Login

**Status: PASS**

- Store owner (merchant) logs in via `/store/{slug}/login` same as customers
- Post-login, admin role is detected and redirected to `storefront.admin.dashboard`
- `EnsureTenantIsActive` blocks pending (unverified email) and suspended tenants
- `CheckTenantAccess` enforces merchant belongs to current store

**Notes:**
- No separate merchant login URL — shared with customer login
- Admin panel has its own login route `/store/{slug}/admin/login` that uses the same controller
- Pending status (pre-email-verification) blocks admin access with "verify your email" message

### Email Verification

**Status: PASS**

- Signed URL verification via `/verify-email/{id}/{hash}`
- SuperAdmin and verified users bypass verification prompts
- Verification redirect respects tenant context (storefront onboarding)
- Resend capability exists

**Notes:**
- Verification redirects to `storefront.onboarding.complete` — a page designed for store owners, not customers
- No verification gating on storefront browsing (customers can browse without verification)
- Verification required for admin operations (via `EnsureTenantIsActive` checking `pending` status)

### Store Creation

**Status: PASS**

- Self-service store creation via `/create-store`
- Transactional creation of: Tenant → Subscription → Roles → Admin User
- Validation prevents duplicate slugs and emails
- Email verification required before admin access
- Free plan auto-assigned

**Notes:**
- Tenant stays in `pending` status until email verification
- No automated tenant activation after verification — onboarding page is informational
- No subscription start date is set (both `starts_at` and `expires_at` are null)
- `CreateStoreController@store` uses `min:8` string rule instead of `Rules\Password::defaults()`

### Storefront Isolation

**Status: PARTIAL**

**Strengths:**
- Each store has a unique URL path
- Tenant-scoped models and queries
- `ValidateTenantBinding` middleware prevents cross-tenant data access via route model binding
- Storefront controllers query by tenant context

**Weaknesses:**
- `IdentifyTenant` binds a default tenant for all unauthenticated root-domain requests, creating potential data cross-contamination
- `HandleInertiaRequests` manually nulls the tenant when no `store_slug` is present — but this is a presentation-layer fix, not a data isolation fix
- The root homepage (`Client/Products/Index`) displays products from the default tenant without explicit tenant scoping — it inherits whatever `IdentifyTenant` resolved, which could be wrong
- Global cart has no `tenant_id` — cart scoping is done at checkout time via `StorefrontCartController` checking current tenant
- Legacy `/admin/*` routes (without store slug) rely on `IdentifyTenant`'s session-based resolution, which is less reliable than explicit URL-based resolution

### Admin Isolation

**Status: PASS**

- Admin routes require `auth`, `role:admin`, `tenant.valid`, `tenant.access`, `tenant.binding` middleware
- Admin queries filter by `$request->user()->tenant_id`
- Cross-tenant data access is blocked by `ValidateTenantBinding` (model tenant_id check)
- Cross-tenant user access blocked by `CheckTenantAccess` (user.tenant_id check)
- `EnsureTenantIsActive` blocks suspended/expired tenant admin operations
- `TenantIsValid` ensures user has a valid tenant record
- SuperAdmin has complete access across all tenants for management purposes
- Impersonation feature allows SuperAdmin to temporarily act as another user

**Notes:**
- `/admin/*` (standalone) and `/store/{slug}/admin/*` use the same controllers — the middleware handles scoping
- Email notification tenant scoping relies on tenant relationships rather than a dedicated `tenant_id` column on notifications table (migration for this exists)
- Admin panel pages are rendered via Inertia with `AdminLayout`, separate from user-facing `ShopLayout`

---

## Risks Summary

| ID | Risk | Severity | Category |
|----|------|----------|----------|
| R1 | `IdentifyTenant` binds default tenant to all unauthenticated root requests, risking cross-tenant data exposure | HIGH | Tenant Leakage |
| R2 | `HandleInertiaRequests` manually nulls tenant as a presentation-layer fix — underlying tenant binding is still wrong | HIGH | Tenant Leakage |
| R3 | Root homepage displays products from whatever tenant `IdentifyTenant` resolves, without explicit scoping | HIGH | Tenant Leakage |
| R4 | `HeroSection` handles two different prop shapes, causing frequent context-dependent bugs | HIGH | Coupling |
| R5 | Global cart lacks `tenant_id`, requiring runtime scoping via controller logic | MEDIUM | Isolation |
| R6 | `StoreResolver` 1-hour cache serves stale tenant data after updates/deactivations | MEDIUM | Caching |
| R7 | `CheckUserStatus` logs out suspended non-admin users to root login (wrong redirect) | MEDIUM | UX/Auth |
| R8 | Duplicate `/admin/*` and `/store/{slug}/admin/*` entry points, same controllers | MEDIUM | Coupling |
| R9 | VerifyEmail redirects everyone to `onboarding.complete` (irrelevant for customers) | LOW | UX |
| R10 | `RegisteredUserController` doubles for both global (dead) and storefront registration | LOW | Dead Code |
| R11 | `CreateStoreController` uses `min:8` string rule instead of `Rules\Password::defaults()` | LOW | Inconsistency |
| R12 | No subscription start/expiry dates set on store creation (both null) | LOW | Data |
| R13 | No automated tenant activation step after email verification | LOW | Workflow |

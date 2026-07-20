# Master AI Rule Book — Multi-Tenant SaaS E-Commerce Platform

**Version:** 1.0  
**Date:** 2026-07-20  
**Phase:** 1–8 complete, Sprint 9.1 complete, Sprint 9.2 complete  
**Audience:** AI coding agents (OpenCode, Claude Code, Codex, Cursor, Cline, Roo Code, Windsurf, etc.)

This document is the single source of truth. Every future AI coding session MUST follow these rules. Do not redesign the architecture. Extend existing patterns only.

---

## Project Objective

A multi-tenant SaaS e-commerce platform where merchants create and manage online stores. Each store (tenant) operates independently with its own products, orders, staff, branding, and subscription plan. The platform monetizes via tiered subscription plans with feature gating and usage limits.

---

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2+, Laravel 12 |
| Frontend | React 19, Inertia.js 3.1, Tailwind CSS 3 |
| Database | MySQL (main), SQLite (testing) |
| Build | Vite 7, Laravel Vite Plugin |
| Real-time | Pusher, Laravel Echo |
| Auth | Laravel Breeze (scaffold), Spatie Laravel Permission |
| Payments | Manual (bank transfer / evidence upload) |
| Storage | Cloudinary (images), Local (files) |
| Testing | PHPUnit 11 (Feature + Unit tests) |

---

## Project Status

### Phase 1–8: Core Platform
E-commerce fundamentals: products, orders, cart, checkout, categories, brands, promotions, coupons, users, roles, permissions, reports, activity logs, settings, team management, Telegram integration, wishlist, order overrides.

### Sprint 9.1: Production Readiness
- Logout session cleanup (invalidate + regenerate token)
- Tenant context verification middleware
- Permission cache clearing via observers
- Session invalidation on password change (CRITICAL fix)
- Order creation wrapped in DB transaction (CRITICAL fix)
- Stock validation before order placement (CRITICAL fix)

### Sprint 9.2: Subscription & Billing (Complete)
| Area | Status |
|------|--------|
| Invoices (model, migration, service, controller, auto-generation listener, pages) | ✅ Complete |
| Plan change with proration (upgrade immediate, downgrade scheduled) | ✅ Complete |
| Subscription status banner (6 states in AdminLayout) | ✅ Complete |
| Renewal with trial limit checks | ✅ Complete |
| Expiry lifecycle (active→past_due→expired→suspended, trial→expired) | ✅ Complete |
| Grace period (7-day, UI countdown) | ✅ Complete |
| Payment verification flow (evidence upload, review, approve/reject) | ✅ Complete |
| Console commands (`subscriptions:send-reminders`, `subscriptions:apply-scheduled-changes`) | ✅ Complete |

**Audit report:** `docs/sprint-9.2-subscription-billing-audit.md`

---

## Core Architecture

### Route Structure

```
/admin/*                          → Legacy admin (no store slug)
/store/{store_slug}/admin/*       → Storefront admin (tenant-aware)
/superadmin/*                     → SuperAdmin platform management
/store/{store_slug}/*             → Storefront (public)
/client/*                         → Client area
/payment/*                        → Payment gateways
```

**Middleware layering** (defined in `bootstrap/app.php`):

```
storefront         → Resolves tenant from URL store_slug
auth:web,accounts  → Requires authentication (User or Account guard)
role:admin         → Requires admin role (superadmin bypasses)
tenant.valid       → Structural tenant check (user has valid tenant)
tenant.access      → Cross-tenant guard (user matches current tenant)
tenant.binding     → Validates route model binding tenant_id match
tenant.active      → Subscription health check (applied to operations routes)
tenant.locked      → Blocks mutations when tenant is locked (expired)
```

Account routes (billing, profile) sit OUTSIDE `tenant.active` so they remain accessible during expiry. Operations routes (products, orders, etc.) sit INSIDE `tenant.active`.

### Key Architectural Decisions

- **No API layer** — server-rendered Inertia pages with controller→Inertia::render
- **No queue workers required** — synchronous unless explicitly queued
- **No PDF library** — invoices download as HTML (browser "Save as PDF")
- **Manual payment only** — no Stripe/PayPal gateway integration
- **Spatie Permission for RBAC** — roles assigned to users, permissions checked via `can()`
- **Feature gates** via `FeatureGate` service, NOT Spatie permissions
- **Limits enforced** via `SubscriptionLimitService` (usage checks before creation)

---

## Identity Architecture

### Dual Identity System

The platform supports two identity types defined by `config('identity.use_accounts')`:

1. **User model** (`App\Models\User`) — Legacy identity, directly belongs to one tenant via `tenant_id`
2. **Account model** (`App\Models\Account`) — Multi-tenant identity, joins tenants via `TenantMembership` pivot

**Key rule:** If `use_accounts` is true, use Account + TenantMembership. Otherwise use User with direct `tenant_id` FK.

### IdentityProjection

`app/Auth/IdentityProjection.php` builds the user payload shared to the frontend via Inertia. It includes: `display_name`, `email`, `role`, `roles`, `permissions`, `is_owner`, `is_admin`, `is_superadmin`, `tenant_id`, `tenant_name`, `tenant_slug`, `memberships` (for Account users).

### SuperAdmin Bypass

SuperAdmin always bypasses:
- Tenant resolution (`IdentifyTenant`)
- Tenant access checks (`CheckTenantAccess`)
- Subscription health checks (`EnsureTenantIsActive`)
- Lock checks (`CheckStoreLocked`)
- Feature gates (`FeatureGate`)

---

## Tenant Rules

### TenantAware Trait (`app/Models/Traits/TenantAware.php`)

Every business model uses `TenantAware` trait which:
- Adds a global `TenantScope` filtering by `tenant_id`
- Auto-sets `tenant_id` on `creating` from `Tenant::getCurrent()`
- Provides `scopeForTenant($tenantId)` and `scopeForCurrentTenant()`
- Provides `withoutTenantScope()` macro to bypass for admin operations

**Exempt from tenant scope:**
- `ActivityLog` — hard-coded exempt in `TenantScope::$exemptModels`
- Models that `allowsNullTenantFallback()` return true (shared reference data)
- Console commands during migrations/seeds

**When querying from controllers:**
```php
// Auto-scoped:
$products = Product::where('status', 'active')->get();

// Cross-tenant (admin/superadmin):
$count = Product::withoutTenantScope()->where('tenant_id', $tenantId)->count();
```

### Tenant Resolution Order

`IdentifyTenant` middleware resolves current tenant via:
1. Authenticated User's `tenant_id` or Account's membership
2. Subdomain (`{slug}.example.com`)
3. `X-Tenant` header
4. Session `current_tenant_slug`

### Tenant States

`Tenant.status`: `active`, `trialing`, `pending`, `suspended`
`Tenant.locked_at`: set when subscription expires past grace period

### TenantHelpers

Global helper functions in `bootstrap/helpers.php`:
- `tenant()` → `Tenant::getCurrent()`
- `tenantId()` → current tenant ID (null-safe)
- `admin_redirect('route.name')` → redirects to correct route prefix (storefront-aware)

---

## Authorization Rules

### Permission Model

- **Spatie Laravel Permission** for RBAC with roles and permissions
- Permissions stored in DB, cached for 24 hours
- Cache cleared immediately on role/permission CRUD via observers
- Permission checks via `$user->can('permission.name')` in controllers
- Front-end checks via `auth.user.permissions` array (shared via Inertia)
- Server-side `abort(403)` backup on every sensitive controller action

### Permission Naming Convention

Use dot notation: `resource.action` (e.g., `billing.view`, `billing.manage`, `products.create`)

### Key Billing Permissions

- `billing.view` — view billing pages, invoices, payment history
- `billing.manage` — manage payments, mark invoices, execute plan changes
- `billing.renew` — renew subscriptions

### Team Membership Changes

Role assignment, suspend, remove do NOT require permission cache flush — Spatie reads user-role pivot from DB directly.

---

## Subscription & Billing Rules

### Subscription States

```
pending → trialing → active → past_due → expired → suspended
              ↓                              ↑
          expired (trial ended)              |
                                        canceled
```

### Lifecycle Flow (`SubscriptionExpiryService`)

| Transition | Condition | Effect |
|-----------|-----------|--------|
| active → past_due | `expires_at` in past | 7-day grace period starts, notification sent |
| past_due → expired | 7 days past expiry | Tenant locked |
| expired → suspended | 1 day after expiry+grace | Tenant status='suspended', notification sent |
| trial → expired | `trial_ends_at` in past | Tenant locked, notification sent |

### Grace Period

Hard-coded at 7 days (`Subscription::GRACE_DAYS`, `SubscriptionExpiryService::GRACE_DAYS`).
UI shows countdown via `grace_days_remaining` shared in Inertia props.
During grace period, tenant is redirected to billing page (not blocked).

### Plan Change Rules

- **Upgrade (target price >= current):** Immediate via `changePlan()`, prorated charge
- **Downgrade (target price < current):** Scheduled via `scheduleDowngrade()` if future expiry; immediate if no future expiry
- **Pending plan** stored as `pending_plan_id` + `pending_plan_effective_at` on subscription
- Scheduled changes applied by `subscriptions:apply-scheduled-changes` command
- Proration: daily rate based on 30-day month / 365-day year

### Renewal Rules

- `renewFromInterval()` extends expiry by one billing interval
- Free plans skip renewal (no expiry)
- Suspended tenants reactivated on renewal
- Trial renewal limited by `PlatformSetting` (`max_trial_renewals`, `allow_trial_renewal`)
- Notification sent on renewal (`SubscriptionRenewed`)

### Invoice Rules

- Auto-generated from `PaymentIntentCompleted` event (listener: `GenerateInvoiceFromCompletedIntent`)
- Duplicate detection via `payment_intent_id`
- Number format: `INV-YYYY-00001`
- 5% tax included in subtotal/tax/total split
- Line items stored as JSON array cast
- Statuses: draft, unpaid, paid, cancelled, refunded

### Console Commands

```bash
# Daily: Process subscription lifecycle
php artisan subscriptions:process-expired
# Options: --dry-run (preview without applying)

# Daily: Send renewal reminders
php artisan subscriptions:send-reminders

# Hourly: Apply scheduled plan changes
php artisan subscriptions:apply-scheduled-changes
```

Pre-existing (do NOT duplicate):
- `subscriptions:process-expired` — full lifecycle (use this, NOT `subscriptions:process-expiry`)
- `subscriptions:send-expiry-warnings` — 7/3/1 day warnings

---

## UI/UX Principles

### Stack
- React 19 + Inertia.js 3 (no Livewire, no Blade for admin pages)
- Tailwind CSS 3 for styling
- `lucide-react` for icons
- Only Blade file: `resources/views/pdf/invoice.blade.php` (invoice download)
- Inertia Pages at `resources/js/Pages/`

### Patterns
- Use `AdminLayout` wrapper for admin pages
- Use `Head` from `@inertiajs/react` for page titles
- Use `adminUrl()` utility for all route URLs (storefront-aware)
- Use `formatCurrency()` + `getPlatformCurrencyConfig()` for money display
- Use `router` from `@inertiajs/react` for navigation (`router.get`, `router.post`)
- Use `<a>` tags for file downloads (never `router.get` for binary/HTML downloads)
- `usePage()` from `@inertiajs/react` for accessing shared props
- Forms: controlled components with `useState`, submit via `router.post`
- Pagination: Inertia pagination links with `preserveState: true`
- All sensitive buttons: server-side `abort(403)` backup

### Component Organization
- `resources/js/Components/Billing/` — billing-specific components
- `resources/js/Components/ProductForm/` — product form components
- `resources/js/Components/ProductType/` — product type components
- `resources/js/Components/ProductView/` — product display components
- `resources/js/Components/PublicLanding/` — landing page components
- `resources/js/Components/Storefront/` — storefront components

---

## Coding Standards

### General
- **No comments in code** unless absolutely necessary to explain WHY (not what)
- Strict TypeScript/JS: use proper null checks (optional chaining, nullish coalescing)
- Follow existing patterns in neighboring files
- Minimize output tokens — be concise, no unnecessary explanations

### PHP
- PSR-4 autoloading (`App\` namespace)
- Type hints on all method parameters and return types
- Readonly properties in constructor promotion where applicable
- Services (business logic) separated from Controllers (HTTP concerns)
- `DB::transaction()` wrap for multi-table writes
- `abort(403)` for unauthorized access
- Use `withoutTenantScope()` only when explicitly needed (admin/superadmin)
- Use `tenant()` helper for current tenant access

### JavaScript / React
- Functional components with hooks (no class components)
- Named exports for pages, default exports for components
- Destructure props at component definition
- `useState` for local state, `usePage()` for shared Inertia props
- `router.get` / `router.post` for Inertia navigation
- `adminUrl()` for all admin route generation
- `formatCurrency()` + currency config for money display

---

## File Modification Policy

- **ALWAYS prefer editing existing files** over creating new ones
- Read a file before editing it (use `Read` tool)
- After editing PHP files, run `php -l` to verify syntax
- After editing JSX files, run `npm run build` to verify compilation
- After editing migrations, run `php artisan migrate:fresh --seed` (if possible)
- Never create new component directories without checking existing patterns
- Never create documentation files (*.md) unless explicitly requested

---

## Database Rules

- Naming: `snake_case` for tables and columns
- Migrations: descriptive names (`YYYY_MM_DD_HHMMSS_description.php`)
- All business tables have `tenant_id` + use `TenantAware` trait
- `Plan` model uses `null` for unlimited (not 0 or -1) — integer casts would convert NULL→0, so disable casting on limit columns
- Soft deletes NOT used (hard deletes with tenant isolation)
- Timestamps: Laravel defaults (`created_at`, `updated_at`)

### Key Models & Their Relationships

```
Tenant (id, name, slug, status, locked_at, subscription_plan_id)
  ├── User (id, tenant_id)
  ├── Subscription (id, tenant_id, plan_id, status, expires_at, pending_plan_id)
  ├── Invoice (id, tenant_id, subscription_id, plan_id, status, totals)
  ├── Product (id, tenant_id)
  ├── Order (id, tenant_id)
  ├── Category (id, tenant_id)
  └── ...

Plan (id, name, slug, monthly_price, yearly_price, product_limit, ...)
  ├── PlanFeature (id, plan_id, feature_key, is_enabled)
  └── Subscription (id, plan_id)

PaymentIntent (id, tenant_id, plan_id, subscription_id, status, amount, gateway)
  ├── PaymentEvidence (id, intent_id, file_path, ...)
  ├── PaymentReview (id, intent_id, action, reason)
  └── PaymentTimelineEvent (id, intent_id, type, description)
```

---

## Service Layer Rules

- Services contain business logic; Controllers handle HTTP concerns
- Services are resolved via Laravel container (dependency injection)
- Naming: `{Domain}Service` (e.g., `InvoiceService`, `SubscriptionPlanChangeService`)
- Key services: `FeatureGate`, `SubscriptionLimitService`, `InvoiceService`, `SubscriptionPlanChangeService`, `SubscriptionExpiryService`, `ManualPaymentService`
- `FeatureGate` for feature access checks (cached 5 min)
- `SubscriptionLimitService` for usage limits (DB counts, not cached)

---

## React Component Rules

- One component per file
- Default export for reusable components
- Named export for pages (used by Inertia routing)
- Props destructured at function signature
- Keys on mapped elements (use unique ID, never index)
- No inline styles — use Tailwind classes
- Use `lucide-react` for icons
- Responsive design via Tailwind breakpoints (`sm:`, `md:`, `lg:`)
- Forms use controlled inputs + `router.post` submission
- State management: local `useState`, Inertia shared props, no Redux/Zustand

---

## Testing Policy

- PHPUnit 11 with Feature + Unit tests
- SQLite in-memory database for tests (`phpunit.xml`)
- Test command: `composer test` (which runs `php artisan test`)
- Run migration before tests: `php artisan migrate:fresh --seed`
- Manual testing checklist documented in each sprint audit report
- After changes: run `composer test` if possible; at minimum run `php -l` on modified files

---

## Manual Testing Workflow

Before marking any feature complete, verify:
1. `php -l` on all modified/created PHP files
2. `npm run build` compiles without errors
3. `php artisan route:list --name=<area>` shows all expected routes
4. Test both `/admin/*` and `/store/{slug}/admin/*` prefixes
5. Test with SuperAdmin (bypasses all checks)
6. Test with active subscription (normal flow)
7. Test with expired/suspended subscription (blocked flow)
8. Test empty states (no data)
9. Test error states (invalid input, unauthorized, not found)

---

## Git Workflow

- Never commit unless explicitly asked
- Before committing: `git status`, `git diff`, `git log --oneline -10`
- Stage only intended files — never commit secrets
- Commit message: concise, matches repo style
- No `--force` push, no `--amend`, no empty commits
- If commit hooks fail: fix issue, create new commit (do not amend failed)

---

## Sprint Workflow

1. Read AGENTS.md for project context and rules
2. Read affected files to understand current implementation
3. Implement changes following all rules in this document
4. Run verification: `php -l`, `npm run build`, `php artisan route:list`
5. Generate sprint audit report with checklist
6. Update AGENTS.md Sprint Status section
7. Only commit when explicitly instructed

---

## Audit Rules

- Every sprint generates a detailed audit report at `docs/sprint-<N>.<M>-<name>-audit.md`
- Audit includes: modified files, created files, implementation summary, manual testing checklist, regression checklist, remaining issues, sprint completion summary
- Review ALL areas affected by the sprint, not just new code
- Check both `/admin/*` and `/store/{slug}/admin/*` route registration
- Verify build compiles before marking complete
- Verify all PHP files pass `php -l`

---

## Performance Rules

- Reports use single-pass aggregation queries with `DB::raw` CASE expressions
- Tenant data loaded eager via relationships
- Feature checks cached for 5 minutes (`FeatureGate::CACHE_TTL`)
- Plan features cached for 5 minutes (per plan)
- Categories cached for 1 hour (non-SuperAdmin)
- Use `chunk(100)` for batch processing (console commands)
- No N+1 queries — eager load relationships
- Avoid lazy loading in loops

---

## Security Rules

- All sensitive actions have server-side `abort(403)` backup
- Password change invalidates all sessions (`Auth::logout()`, `session()->invalidate()`, `regenerateToken()`)
- Logout clears session + regenerates token
- CSRF protection enabled (except webhooks and store login)
- Tenant scope enforced globally via `TenantScope`
- Cross-tenant access prevented by `CheckTenantAccess` middleware
- Permission cache cleared on role/permission changes
- No 2FA (deferred — feature request)
- No password confirmation on admin actions (permission gates sufficient for current threat model)
- Locked tenants blocked from mutations via `CheckStoreLocked`

---

## AI Coding Rules

- **Do NOT redesign the architecture** — extend existing patterns only
- **Do NOT add comments** to code unless explaining WHY (not what)
- **Do NOT create new files** if an existing file can be edited
- **Do NOT add new dependencies** without checking `composer.json` / `package.json` first
- **Do NOT use emojis** in code or files (only in commit messages if user does)
- **DO read AGENTS.md** at the start of every session
- **DO run `php -l`** on every PHP file after editing
- **DO run `npm run build`** after editing JSX/CSS
- **DO check both route prefixes** when adding new routes
- **DO preserve existing code conventions** (naming, imports, patterns)
- **DO mimic code style** of neighboring files
- **DO check `composer.json`** before using any new PHP library
- **DO check `package.json`** before using any new JS library
- **DO fail gracefully** — never log or expose secrets/keys

### Response Style

- Concise and direct — answer in 1-4 lines when possible
- No unnecessary preamble or postamble
- No explanations of what was done unless asked
- Reference code locations as `file_path:line_number`

---

## Things AI Must Never Do

- Never redesign the architecture
- Never add new columns to existing tables (extend with new migrations)
- Never remove or rename existing migrations
- Never modify vendor files
- Never commit secrets, API keys, passwords, or `.env` files
- Never force-push, amend commits, or create empty commits
- Never add comments explaining obvious code (what, not why)
- Never write documentation files unless explicitly requested
- Never assume a library is available — always check `composer.json` / `package.json`
- Never remove or rename existing routes without full grep audit
- Never change database connection config in tests (SQLite in-memory)
- Never add duplicate console commands (check existing ones first)
- Never change the identity system (User vs Account) — honor `config('identity.use_accounts')`

---

## Critical Fixes (Preserved from Production Readiness)

### CRITICAL: Password change now invalidates sessions
**File:** `app/Http/Controllers/Auth/PasswordController.php:28-33`
Calls `Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()` after password hash update.

### CRITICAL: Order creation wrapped in DB transaction
**File:** `app/Http/Controllers/OrderController.php:184-229`
`DB::beginTransaction()` / `commit()` / `rollBack()` around order + coupon + promotion + items creation. Notifications and cart clearing outside transaction.

### CRITICAL: Stock validation before order placement
**File:** `app/Http/Controllers/OrderController.php:135-138, 293-325`
`validateStock()` bulk-loads products/variants, checks stock per cart item, handles variable vs simple products.

---

## Deferred Items

| Issue | Reasoning |
|-------|-----------|
| No 2FA | Feature enhancement, not a bug. Requires auth redesign |
| No password confirmation on admin actions | Permission gates + authorization middleware provide sufficient protection for current scope |
| Missing idempotency key on order creation | Race condition window is small; transaction + stock validation reduce risk significantly |
| Race condition in stock check (non-locking read) | `validateStock()` runs before transaction; concurrent orders could race. Future: add `lockForUpdate()` inside transaction |
| AuthenticateSession middleware not registered | Adding it would break password confirmation flow without additional work |
| Invoice download as HTML (not PDF) | No PDF library (dompdf) available; browser "Save as PDF" is the workaround |
| No Stripe/PayPal gateway integration | Only manual payment gateway implemented; real-time gateways deferred |
| No idempotency key on plan change execution | Transaction wrapping prevents partial writes; race window is small |
| Grace period hard-coded at 7 days | Future: make configurable per-plan or via platform settings |

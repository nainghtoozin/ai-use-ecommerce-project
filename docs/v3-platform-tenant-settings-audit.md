# V3-B2: Platform vs Tenant Settings Audit Report

**Date:** 2026-06-21
**Scope:** Read-only audit of all settings tables, models, controllers, routes, and UI pages.

---

## Executive Summary

The application has **3 distinct settings storage mechanisms** that are inconsistently separated between platform-owned and tenant-owned concerns. The `WebsiteInfo` model is the primary settings store, but it uses `TenantAware` scope (per-tenant) while the platform has no equivalent settings model — it relies on config files, environment variables, and the default tenant's `WebsiteInfo` row for global settings.

**Ownership classification:**
- **Platform-owned settings:** 0 settings (routedir none dedicated to platform config)
- **Tenant-owned settings:** ~40 fields in `WebsiteInfo`, key-value pairs in `settings` table, `telegram_integrations`
- **Mixed/Ambiguous:** SMTP, maintenance mode, registration toggles, SEO meta — stored per-tenant but conceptually platform-level
- **No dedicated platform settings model exists**

---

## Part 1: Settings Inventory

### 1A. Storage Mechanisms

| Storage | Model | Scope | Key Characteristic |
|---|---|---|---|
| `website_infos` table | `WebsiteInfo` | Per-tenant (`TenantAware`) | 73 fillable columns, single row per tenant via unique `tenant_id` |
| `settings` table | `Setting` | Per-tenant (`TenantAware`) | Key-value pairs, unique per `(tenant_id, key)` |
| `tenants.settings` JSON column | `Tenant` model | Per-tenant | Embedded JSON on Tenant model; currently stores only `plan_id` |
| `.env` / `config/*.php` | Config facade | Global (platform) | Laravel config files — APP_NAME, MAIL, etc. |

### 1B. `website_infos` — Full Field Inventory (73 fields)

**Category: Brand Identity (6 fields)**
- `site_name`, `site_tagline`, `site_description`, `site_keywords`
- `logo`, `favicon`

**Category: Theme & Localization (5 fields)**
- `theme_color`, `default_language`, `timezone`, `currency_code`, `currency_symbol`, `date_format`

**Category: Contact (7 fields)**
- `contact_email`, `support_email`, `phone`, `whatsapp_number`, `address`, `country`, `google_maps_embed_url`

**Category: About Page (6 fields)**
- `about_title`, `about_description`, `mission_title`, `mission_description`, `vision_title`, `vision_description`

**Category: Social Links (5 fields)**
- `facebook_url`, `instagram_url`, `twitter_url`, `linkedin_url`, `youtube_url`

**Category: SEO (6 fields)**
- `meta_title`, `meta_description`, `meta_keywords`, `canonical_url`, `robots_meta`, `og_image`

**Category: Hero Section (6 fields)**
- `hero_title`, `hero_subtitle`, `hero_button_text`, `hero_button_link`, `hero_image`, `hero_images`

**Category: Footer (3 fields)**
- `footer_description`, `footer_copyright`, `footer_logo`

**Category: Feature Toggles (7 fields)**
- `maintenance_mode`, `allow_registration`, `enable_reviews`, `enable_wishlist`, `enable_compare`, `guest_checkout_enabled`, `cod_enabled`

**Category: Shipping (2 fields)**
- `free_shipping_threshold`, `default_shipping_fee`

**Category: Structured JSON (3 fields)**
- `contact_info`, `address_info`, `footer_settings`

**Category: Status (1 field)**
- `is_active`

### 1C. `settings` Table — Key-Value Pairs

| Key | Value | Used By |
|---|---|---|
| `notifications_enabled` | `"true"`/`"false"` | `AdminNotificationSettingsController`, `ProcessOrderNotifications` job, `ProcessOrderStatusChange` job |
| `telegram_link` | URL/username | Legacy blade views (`chat.blade.php`) |
| `viber_link` | URL/phone | Legacy blade views (`chat.blade.php`) |
| `facebook_link` | URL/page | Legacy blade views (`chat.blade.php`) |
| `whatsapp_link` | URL/phone | Legacy blade views (`chat.blade.php`) |

### 1D. `telegram_integrations` Table

| Column | Type | Notes |
|---|---|---|
| `user_id` | FK to users | Owner of the bot connection |
| `bot_name` | string | Display name |
| `bot_username` | string | Bot handle |
| `bot_token` | text (encrypted) | Sensitive — `Encrypted` cast |
| `chat_id` | string | Telegram chat identifier |
| `parse_mode` | string | Default: `HTML` |
| `is_enabled` | boolean | Toggle on/off |
| `verification_status` | string | `pending_verification`, `verified`, `failed` |
| `chat_type`, `group_title`, `chat_username` | string | Chat metadata |

Per-user (one per user), tenant-scoped via `TenantAware`. Primary purpose: order notification delivery.

### 1E. `tenants.settings` JSON (on `Tenant` model)

Currently stores only:
```json
{
  "plan_id": 1
}
```

Used by `SuperAdmin\TenantController` to associate a plan with a tenant.

### 1F. Platform Settings (Where Are They?)

There is **no dedicated platform settings storage**. The application relies on:

| Concern | Current Location | Issue |
|---|---|---|
| Platform Name | `config/app.php` (`'name'`) + default tenant's `website_infos.site_name` | Duplicated |
| Platform Logo | Default tenant's `website_infos.logo` | Not platform-accessible |
| SMTP | `.env` (`MAIL_*`) | Correct |
| Maintenance Mode | Default tenant's `website_infos.maintenance_mode` | Per-tenant field used globally |
| Telegram bot config | `config/services.php` (`telegram.*`) | Correct |
| Cloudinary | `.env` / `config/cloudinary.php` | Correct |

---

## Part 2: Ownership Analysis

### Platform-Owned Settings

**None explicitly defined.** The following are implicitly platform-level but stored per-tenant:

| Setting | Stored In | Why It's Platform |
|---|---|---|
| `maintenance_mode` | `website_infos` (per-tenant) | Global maintenance should block ALL tenants |
| SMTP config | `.env` | Shared mail infrastructure |
| Cloudinary config | `.env` | Shared image service |
| Platform name/slogan | `config/app.php` | Platform identity |
| Telegram bot API key | `config/services.php` | Shared bot integration |

### Tenant-Owned Settings

| Table | Rationale |
|---|---|
| `website_infos` (all 73 fields) | Each tenant has its own store identity, branding, contact, theme, SEO |
| `settings` (key-value) | Per-tenant feature toggles and notification preferences |
| `telegram_integrations` | Per-user Telegram bot connection for order notifications |
| `tenants.settings` JSON | Tenant-level subscription config |

### Mixed/Ambiguous Settings

| Setting | Current Store | Conflict |
|---|---|---|
| `allow_registration` | `website_infos` (per-tenant) | Platform might want global registration control |
| `enable_reviews`, `enable_wishlist`, `enable_compare` | `website_infos` (per-tenant) | Platform-wide feature toggles vs. per-store choice |
| `guest_checkout_enabled` | `website_infos` (per-tenant) | Same — platform vs. store control |
| `free_shipping_threshold`, `default_shipping_fee` | `website_infos` (per-tenant) | These are operational settings, not branding — could be platform-defined |
| `theme_color` | `website_infos` (per-tenant) | Platform brand consistency vs. tenant customization |

### Needs Review

| Setting | Issue |
|---|---|
| `og_image`, `canonical_url`, `robots_meta` | SEO is per-tenant, but platform might want global defaults |
| `footer_description`, `footer_copyright` | Combined platform + tenant content |
| `contact_info` (support_email, primary_phone) | Tenant-specific, but platform support is separate |

---

## Part 3: Data Isolation

### Tenant Isolation (`TenantAware`)

| Model | `TenantAware`? | Isolation | Risk |
|---|---|---|---|
| `WebsiteInfo` | **Yes** | Per-tenant, but **single row per tenant** via unique `tenant_id` | **LOW** — global scope + unique constraint ensure one row per tenant |
| `Setting` | **Yes** | Per-tenant via `(tenant_id, key)` unique index | **LOW** — tenant_id + key uniqueness |
| `TelegramIntegration` | **Yes** | Per-tenant via scope; per-user via `user_id` FK | **LOW** — scoped to tenant + user |

### Platform Isolation

The platform has **no dedicated settings table or model**. It relies on:
1. `config/*.php` files (SMTP, app name, services)
2. Default tenant's `WebsiteInfo` row (maintenance mode, contact, SEO)
3. `.env` file

**Issue:** When `Tenant::getCurrent()` is set, `WebsiteInfo::first()` returns the current tenant's row — but for guests on the root domain (no tenant context), it returns the default tenant's settings. This means platform-level settings (maintenance mode, registration) depend on which tenant context is active.

### Isolation Verification

| Scenario | What's Returned | Correct? |
|---|---|---|
| Tenant admin views settings | Own tenant's `WebsiteInfo`, own `settings`, own `TelegramIntegration` | **Yes** (via `TenantAware`) |
| SuperAdmin views settings | Current tenant's row (if tenant context set) or default tenant's row | **Ambiguous** — no SuperAdmin settings page |
| Guest on root domain | Default tenant's settings (via `WebsiteInfo::getSettings()`) | **Partially** — works for global maintenance, but coupled to default tenant |
| Guest on tenant subdomain | Tenant's own settings | **Yes** |

---

## Part 4: UI Analysis

### SuperAdmin Settings Pages

| Page | Route | Exists? |
|---|---|---|
| Platform settings dashboard | `superadmin/settings` | **NO** |
| SMTP configuration | — | **NO** |
| Maintenance mode toggle | — | **NO** |
| Platform branding | — | **NO** |
| Tenant management UI | `superadmin/tenants/*` | **YES** (separate from settings) |

### Tenant Settings Pages

| Page | Route | Controller | Permission |
|---|---|---|---|
| Website Settings | `/admin/website-info/edit` | `SettingsController` | `settings.website` |
| Notification Settings | `/admin/settings/notifications` | `AdminNotificationSettingsController` | `settings.notifications` |
| Telegram Integration | `/admin/settings/telegram-integration` | `TelegramIntegrationController` | (none — per-user) |
| Legacy Customer Support Settings | `admin.settings.edit` (Blade) | (no matching route) | **BROKEN — route does not exist** |

### Ownership Consistency Issues

1. **No SuperAdmin settings page** — SuperAdmin cannot change platform name, logo, maintenance mode, or SMTP without editing code or `.env`.
2. **`maintenance_mode` is per-tenant but used globally** — `CheckMaintenanceMode` middleware calls `WebsiteInfo::getSettings()`, which returns the **current tenant's** maintenance flag. Different tenants could have different maintenance states, which is likely unintended.
3. **`allow_registration` is per-tenant but used globally** — `RegisteredUserController` checks `WebsiteInfo::getSettings()`, which could return different values depending on tenant context.
4. **Legacy Blade route is dead** — `admin.settings.edit` and `admin.settings.update` routes no longer exist in `web.php`, but blade views reference them. The sidebar link is broken.
5. **`setting()` helper has no tenant scope** — The `setting()` helper in `bootstrap/helpers.php` queries `Setting::where('key', $key)` without explicit tenant scoping. Since `Setting` uses `TenantAware`, it's scoped by the global scope — but if called outside a tenant context, it could return any row.

### Duplicate settings (WebsiteInfo vs settings table)

| Concern | In WebsiteInfo? | In settings table? |
|---|---|---|
| Telegram username | `contact_info.telegram_username` | `telegram_link` key |
| Support email | `support_email` | — |
| Facebook URL | `facebook_url` | `facebook_link` key |
| WhatsApp number | `whatsapp_number` config field | `whatsapp_link` key |

The legacy blade uses the `settings` table (via `setting()` helper), while the Inertia UI uses `WebsiteInfo`. These could desynchronize.

---

## Part 5: Future SaaS Design Recommendations

### Platform Settings Architecture

Create a new **platform_settings** table (no `tenant_id`):

```sql
CREATE TABLE platform_settings (
    id BIGINT PRIMARY KEY,
    site_name VARCHAR(255),
    site_logo VARCHAR(255),
    support_email VARCHAR(255),
    platform_currency VARCHAR(3) DEFAULT 'USD',
    smtp_host VARCHAR(255),
    smtp_port INT,
    smtp_encryption VARCHAR(10),
    smtp_username VARCHAR(255),
    smtp_password TEXT (encrypted),
    telegram_bot_token TEXT (encrypted),
    google_analytics_id VARCHAR(255),
    meta_global_title VARCHAR(255),
    meta_global_description TEXT,
    favicon VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

Single-row table, no tenant_id. Accessible via `PlatformSetting::first()`.

### Tenant Settings Architecture

Current tenant settings are adequate but should be **decoupled from the global scope for platform-level overrides**:

| Storage | Purpose |
|---|---|
| `website_infos` (per-tenant) | Tenant branding, contact, theme, SEO, feature toggles |
| `settings` (per-tenant key-value) | Tenant operational flags (notifications, legacy chat links) |
| `telegram_integrations` | Per-user, per-tenant bot connections |

### Recommended Split

| Setting Group | Platform | Tenant | Both |
|---|---|---|---|
| Site name | Platform name (default) | Store name (override) | — |
| Logo | Platform logo (default) | Store logo (override) | — |
| SMTP | Platform SMTP | Tenant SMTP (optional override) | — |
| Maintenance Mode | Platform maintenance | Store-specific maintenance | ✔ |
| Feature toggles (reviews, wishlist, compare) | Platform default | Tenant override | ✔ |
| Currency | Platform default currency | Store currency | ✔ |
| SEO meta | Platform global defaults | Store-specific SEO | ✔ |
| Telegram bot | Platform bot token | Store-level chat ID | — |
| Shipping thresholds | Platform default | Store override | ✔ |

---

## Part 6: Risk Analysis

### Shared Settings (No Clear Owner)

| Setting | Storage | Risk |
|---|---|---|
| `maintenance_mode` | Per-tenant `WebsiteInfo` | **Medium** — Global maintenance needs platform-level toggle. Currently depends on tenant context. |
| `allow_registration` | Per-tenant `WebsiteInfo` | **Low** — Per-store registration control is valid, but platform may want a kill switch. |
| SMTP | `.env` only | **Low** — Correctly platform-level via config. |
| Telegram bot token | `config/services.php` | **Low** — Correctly platform-level via config. |

### Cross-Tenant Leak Risks

| Scenario | Risk | Explanation |
|---|---|---|
| `setting()` helper in blade views | **Low** | Scoped by `TenantAware` on Setting model, but `setting()` query lacks explicit tenant_id filter. If `Tenant::getCurrent()` is null, results are unpredictable. |
| `WebsiteInfo::getSettings()` outside tenant context | **Low** | Returns first row without tenant scope if `Tenant::getCurrent()` is null. Currently this is the default tenant's settings. |
| Legacy `chat.blade.php` references `setting('telegram_link')` | **Low** | Only renders when tenant context is active (chat widget). |

### Incorrect Ownership

| Finding | Type | Severity |
|---|---|---|
| `maintenance_mode` in `WebsiteInfo` (per-tenant) should be platform-level | Ownership | **Medium** |
| No SuperAdmin settings page | Missing feature | **Medium** |
| `CheckMaintenanceMode` reads per-tenant settings as global | Logic | **Low** (works with single tenant in practice) |
| Legacy `admin.settings.edit` Blade route is broken (orphaned) | Dead code | **Low** |
| Duplicate contact links in `WebsiteInfo` vs `settings` table | Data duplication | **Low** |

---

## Findings Summary

| # | Finding | Severity | Category |
|---|---|---|---|
| 1 | No dedicated platform settings table/model — platform config lives in `.env`, config files, and default tenant's `website_infos` | **Medium** | Missing feature |
| 2 | `maintenance_mode` is per-tenant but used as platform-level toggle in `CheckMaintenanceMode` middleware | **Medium** | Ownership |
| 3 | No SuperAdmin UI for platform-level settings (branding, SMTP, maintenance, registration kill switch) | **Medium** | Missing feature |
| 4 | `WebsiteInfo::getSettings()` returns different data based on tenant context, but used globally for registration, checkout, wishlist | **Low** | Isolation |
| 5 | Legacy blade sidebarlink references non-existent `admin.settings.edit` route | **Low** | Dead code |
| 6 | Duplicate data: Telegram contact stored in both `WebsiteInfo.contact_info` (Inertia UI) and `settings` table (legacy blade `setting()` helper) | **Low** | Data duplication |
| 7 | `setting()` helper function lacks explicit tenant_id filter — depends entirely on `TenantAware` global scope | **Low** | Code quality |
| 8 | `tenant_id` on `website_infos` has a UNIQUE constraint — ensures one row per tenant, but limits flexibility | **Low** | Design limitation |

---

## Recommended Next Step

Create a `platform_settings` table (single row, no `tenant_id`) with a corresponding `PlatformSetting` model. Migrate platform-level configurations out of `website_infos` (specifically `maintenance_mode`) into the new table. Build a SuperAdmin settings page for managing platform-level settings.

This can be done incrementally:
1. Create `platform_settings` migration + model
2. Migrate `maintenance_mode` from `website_infos` to `platform_settings`
3. Update `CheckMaintenanceMode` to read from `PlatformSetting`
4. Build SuperAdmin settings UI page
5. Optionally add platform-level SMTP override, registration kill switch, default SEO meta

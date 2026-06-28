# V3 Landing Page Branding Hotfix

**Date:** 2026-06-26

## Problem

The landing page (`/`) displayed incorrect branding because `Client/Products/Index.jsx`, `ShopNavbar`, and `ShopFooter` were hardcoded to read from `website_info` (tenant data). On the platform landing page, there is no tenant context — the correct source is `platform_setting`.

## Root Cause

All three components used `website_info?.site_name` and `website_info?.logo` exclusively. The previous hotfix (v3-platform-branding-sync-hotfix.md) reverted these back to `website_info` only, which broke the platform landing page again.

## Changes Made

### `resources/js/Components/ShopNavbar.jsx`
- Added `platform_setting` to destructured props
- Introduced context-aware logic: on platform pages (`!storeSlug`), reads from `platform_setting`; on tenant pages (`storeSlug` set), reads from `website_info`
- Logo → `platform_setting?.site_logo` (platform) or `website_info?.logo` (tenant)
- Name → `platform_setting?.site_name` (platform) or `website_info?.site_name` (tenant)

### `resources/js/Components/ShopFooter.jsx`
- Added `platform_setting` and `tenant` to destructured props
- Same context-aware logic as `ShopNavbar`
- Footer logo → `platform_setting?.site_logo` (platform) or `website_info?.footer_logo_url || website_info?.logo` (tenant)

### `resources/js/Pages/Client/Products/Index.jsx`
- Added `platform_setting` to destructured props
- `siteName` picks `platform_setting?.site_name` first, falls back to `website_info?.site_name`, then `'My Store'`
- `HomepageHero` now receives computed `{ site_name, logo }` with `platform_setting?.site_logo` as primary logo source

### `resources/js/Components/Storefront/HomepageHero.jsx`
- No changes needed — receives correct data from parent

## Design

Context detection: `!tenant?.slug` → platform page (null on `/`, set on `/store/{slug}/*`).

| Source | Landing Page (`/`) | Tenant Page (`/store/{slug}/*`) |
|--------|-------------------|--------------------------------|
| Logo | `platform_setting.site_logo` | `website_info.logo` |
| Name | `platform_setting.site_name` | `website_info.site_name` |

# PHASE 1 AUDIT COMPLETE

## 1. Executive Summary

This is a Laravel 12, React 19, Inertia 3.1, Tailwind, single-database SaaS application. Tenants are resolved from `/store/{store_slug}` by `Storefront`, placed in `current.tenant`, and protected by tenant-aware global scopes, route binding, and access middleware.

The existing customer storefront is already a tenant-aware route family, but it is not yet a tenant-specific storefront architecture. Its presentation is mostly fixed in `ShopLayout`, `ShopNavbar`, `ShopFooter`, `StorefrontHero`, `Storefront/Index`, `Storefront/Products`, `Storefront/Show`, `Storefront/Cart`, and `Storefront/Checkout`. Customer-facing settings are shared through `HandleInertiaRequests` as `website_info` and are also injected by `Storefront`.

`website_infos` is the central coupling point. One row combines identity, currency, branding, contact data, CMS content, SEO, homepage hero media, footer content, policies, checkout flags, shipping values, account feature flags, and maintenance state. JSON columns were added for contact, address, footer, and hero collections, but the responsibility boundary remains mixed.

The safest Version 2 foundation is additive: introduce a storefront configuration resolver and separate tenant-owned entities for storefront identity, theme assignment/tokens, homepage sections, media, navigation, content/labels, checkout presentation, and SEO/social. Do not move order, cart, inventory, payment, customer, tenant, subscription, or authentication logic into the theme layer.

## 2. Current Website Settings Architecture

### Confirmed data flow

```text
website_infos
  -> App\\Models\\WebsiteInfo (TenantAware, cached by tenant)
  -> Admin\\SettingsController / WebsiteInfo::getSettings()
  -> Inertia settings prop, shared website_info prop, or controller page props
  -> ShopNavbar / ShopFooter / StorefrontHero / CMS pages / checkout gates
  -> customer
```

The admin editor is `resources/js/Pages/Admin/Settings/Edit.jsx`. It renders eleven tabs: General, Branding, Contact, About Us, Social Media, SEO, Policies, Homepage, Footer, FAQ, and System. Both legacy `/admin/website-info/edit` and tenant-aware `/store/{slug}/admin/website-info/edit` point to `SettingsController`.

`WebsiteInfo::getSettings()` uses `website_settings_{tenant_id}` forever-cache keys and creates a default row if none exists. `SettingsController::update()` uploads/deletes images through `ImageService`, folds contact/address/footer form fields into JSON columns, saves the row, clears the settings cache, and logs the update.

The old generic `settings` table and `Setting` model also exist. They are tenant-aware after the multi-tenant migrations, but no confirmed customer storefront consumer was found in this audit. `Tenant.settings` is a separate JSON object used for tenant defaults, language/theme/timezone/currency, notifications, and admin menu visibility.

## 3. Current Settings Inventory

The following table includes all relevant settings confirmed in the current model, migration, request, admin form, and storefront consumers. `UNKNOWN — REQUIRES VERIFICATION` means the field is presented or referenced but no persisted column/consumer was confirmed.

| Current Setting | Current Location | Current Consumer | Tenant-specific | New Category | New Location | Action | Migration Risk |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `site_name` | `website_infos.site_name`; `WebsiteInfo` | navbar, footer, hero, app name, CMS title, auth layouts | Yes | Store Identity | `storefronts.identity` | SPLIT | MEDIUM |
| `site_tagline` | `website_infos.site_tagline` | `StorefrontHero` fallback description, layouts | Yes | Store Identity | `storefronts.identity` | MIGRATE | LOW |
| `site_description` | `website_infos.site_description` | `StorefrontHero` | Yes | Store Identity/Content | identity description | MIGRATE | MEDIUM |
| `site_keywords` | `website_infos.site_keywords`; request/editor | no confirmed storefront consumer | Yes | SEO | `storefront_seo` | MIGRATE/DEPRECATE | LOW |
| `theme_color` | `website_infos.theme_color`; `AdminSidebar`, `ShopNavbar`, `ShopFooter`, admin CSS vars | customer/admin primary color | Yes | Design | theme tokens | SPLIT | HIGH |
| `default_language` | `website_infos.default_language` | settings/defaults; exact customer runtime use UNKNOWN — REQUIRES VERIFICATION | Yes | Behavior/Locale | storefront behavior | MIGRATE | MEDIUM |
| `timezone` | `website_infos.timezone` | settings/currency/date context | Yes | Behavior | storefront locale config | MIGRATE | MEDIUM |
| `currency_code` | `website_infos.currency_code` | `useCurrency`, currency formatting | Yes | Shop/Behavior | commerce display config | KEEP/MIGRATE | HIGH |
| `currency_symbol` | `website_infos.currency_symbol` | currency config fallback | Yes | Shop | commerce display config | MIGRATE | MEDIUM |
| `currency_position` | `website_infos.currency_position` | model/request support; consumer only partly confirmed | Yes | Shop | commerce display config | MIGRATE | MEDIUM |
| `decimal_places` | `website_infos.decimal_places` | currency config | Yes | Shop | commerce display config | MIGRATE | MEDIUM |
| `date_format` | `website_infos.date_format` | model/request support | Yes | Behavior | locale config | MIGRATE | LOW |
| `logo` | `website_infos.logo`; `ImageService` URL accessor | navbar, hero, layouts, footer, auth, emails | Yes | Store Identity/Media | storefront media role `logo` | SPLIT | HIGH |
| `favicon` | `website_infos.favicon` | admin/settings accessor; head consumer not confirmed | Yes | Media | media role `favicon` | MIGRATE | MEDIUM |
| `footer_logo` | `website_infos.footer_logo` | `ShopFooter` | Yes | Footer/Media | footer branding media | MIGRATE | MEDIUM |
| `og_image` | `website_infos.og_image` | accessor; global customer head consumer not confirmed | Yes | SEO/Media | SEO social image | MIGRATE | MEDIUM |
| `contact_email` | scalar plus `contact_info.contact_email` | CMS contact, footer, maintenance, email | Yes | Store Identity/Content | contact profile | SPLIT | HIGH |
| `support_email` | scalar plus `contact_info.support_email` | CMS contact, footer, contact form destination, email | Yes | Content | contact profile | SPLIT | HIGH |
| `phone` | scalar plus `contact_info.primary_phone` | CMS contact, footer, maintenance | Yes | Content | contact profile | SPLIT | MEDIUM |
| `secondary_phone` | request/JSON only | CMS contact | Yes | Content | contact profile | MIGRATE | LOW |
| `sales_email` | request/JSON only | CMS contact | Yes | Content | contact profile | MIGRATE | LOW |
| `whatsapp_number` | scalar plus JSON | CMS/footer/social | Yes | Social/Content | social/contact channels | SPLIT | MEDIUM |
| `telegram_username` | request/JSON | CMS/footer | Yes | Social | social channels | MIGRATE | LOW |
| `address` | scalar plus `address_info.address_line_1` | CMS contact, maintenance | Yes | Store Identity | contact profile | SPLIT | MEDIUM |
| `address_line_1` | request/JSON | CMS contact | Yes | Content | address value object | MIGRATE | LOW |
| `address_line_2` | request/JSON | CMS contact | Yes | Content | address value object | MIGRATE | LOW |
| `city` | request/JSON | CMS contact | Yes | Content | address value object | MIGRATE | LOW |
| `state`/`state_region` | request/JSON | CMS contact | Yes | Content | address value object | MERGE | MEDIUM |
| `postal_code` | request/JSON | CMS contact | Yes | Content | address value object | MIGRATE | LOW |
| `country` | scalar plus JSON | CMS contact | Yes | Store Identity | address value object | SPLIT | MEDIUM |
| `google_maps_embed_url`/`google_maps_link` | scalar/request/JSON | CMS contact | Yes | Content | contact profile | MERGE | MEDIUM |
| `about_title` | `website_infos.about_title` | CMS About | Yes | Content/Pages | `storefront_pages` | MIGRATE | MEDIUM |
| `about_description` | `website_infos.about_description` | CMS About/footer fallback | Yes | Content/Pages | `storefront_pages` | MIGRATE | HIGH |
| `about_image` | admin form/request/model boot list, no migration column confirmed | no confirmed renderer | Yes | Media/Pages | page media | DEPRECATE after verification | HIGH |
| `mission_title` | `website_infos.mission_title` | CMS About | Yes | Content/Pages | About structured content | MIGRATE | LOW |
| `mission_description` | `website_infos.mission_description` | CMS About | Yes | Content/Pages | About structured content | MIGRATE | LOW |
| `vision_title` | `website_infos.vision_title` | CMS About | Yes | Content/Pages | About structured content | MIGRATE | LOW |
| `vision_description` | `website_infos.vision_description` | CMS About | Yes | Content/Pages | About structured content | MIGRATE | LOW |
| Facebook/Instagram/Twitter/LinkedIn/YouTube URLs | scalar columns | `ShopFooter`, maintenance social props | Yes | Social | `storefront_social_links` | SPLIT | MEDIUM |
| TikTok/Pinterest URLs | admin form only; no model fillable/migration column confirmed | no confirmed consumer | UNKNOWN — REQUIRES VERIFICATION | Social | social links | DEPRECATE/VERIFY | MEDIUM |
| `meta_title` | `website_infos.meta_title` | settings only; page-wide head consumer not confirmed | Yes | SEO | `storefront_seo` | MIGRATE | MEDIUM |
| `meta_description` | `website_infos.meta_description` | settings only; page-wide head consumer not confirmed | Yes | SEO | `storefront_seo` | MIGRATE | MEDIUM |
| `meta_keywords` | `website_infos.meta_keywords` | settings only; page-wide head consumer not confirmed | Yes | SEO | `storefront_seo` | MIGRATE | LOW |
| `canonical_url` | request/model/editor | no confirmed storefront head consumer | Yes | SEO | `storefront_seo` | MIGRATE | LOW |
| `robots_meta` | scalar column/editor | no confirmed storefront head consumer | Yes | SEO | `storefront_seo` | MIGRATE | LOW |
| Google Analytics/GTM/Facebook Pixel IDs | admin form only; no persisted columns confirmed | no confirmed runtime consumer | UNKNOWN — REQUIRES VERIFICATION | SEO/Behavior | integrations, separate from theme | VERIFY then DEPRECATE/MIGRATE | HIGH |
| `hero_title` | scalar/editor | no confirmed current `StorefrontHero` consumer | Yes | Homepage/Content | homepage section config | MIGRATE | MEDIUM |
| `hero_subtitle` | scalar/editor | no confirmed current `StorefrontHero` consumer | Yes | Homepage/Content | homepage section config | MIGRATE | MEDIUM |
| `hero_button_text` | scalar/editor | no confirmed current `StorefrontHero` consumer | Yes | Homepage/Content | homepage section config | MIGRATE | MEDIUM |
| `hero_button_link` | scalar/editor | no confirmed current `StorefrontHero` consumer | Yes | Homepage/Content | homepage section config | MIGRATE | MEDIUM |
| `hero_image` | scalar/editor/model accessor | no confirmed current `StorefrontHero` consumer | Yes | Homepage/Media | section media relation | SPLIT | HIGH |
| `hero_images` | JSON/editor/controller upload flow | `StorefrontHero` carousel | Yes | Homepage/Media | ordered section media items | SPLIT | HIGH |
| `footer_description` | scalar plus `footer_settings.description` | `ShopFooter` | Yes | Footer/Content | footer config | MERGE | MEDIUM |
| `footer_extra_text` | `footer_settings.extra_text`, request/editor | `ShopFooter` | Yes | Footer/Content | footer config | MIGRATE | LOW |
| `footer_copyright` | scalar/editor | `ShopFooter` | Yes | Footer/Content | footer config | MIGRATE | LOW |
| `footer_settings.show_contact_button` | JSON generated server-side | no conditional consumer confirmed | Yes | Footer | footer section config | MIGRATE/VERIFY | LOW |
| `footer_settings.show_social_icons` | JSON generated server-side | no conditional consumer confirmed | Yes | Footer | footer section config | MIGRATE/VERIFY | LOW |
| `footer_settings.compact_mode` | JSON generated server-side | no confirmed consumer | Yes | Footer | footer section config | DEPRECATE after verification | LOW |
| `privacy_policy` | scalar/editor | CMS policy route | Yes | Pages/Content | `storefront_pages` | MIGRATE | HIGH |
| `terms_conditions` | scalar/editor | CMS policy route | Yes | Pages/Content | `storefront_pages` | MIGRATE | HIGH |
| `shipping_policy` | scalar/editor | CMS policy route | Yes | Pages/Content | `storefront_pages` | MIGRATE | HIGH |
| `return_policy` | scalar/editor | CMS policy route | Yes | Pages/Content | `storefront_pages` | MIGRATE | HIGH |
| `refund_policy` | scalar/editor | CMS policy route | Yes | Pages/Content | `storefront_pages` | MIGRATE | HIGH |
| `maintenance_mode` | scalar/editor | `CheckMaintenanceMode`, maintenance page | Yes | Behavior | operational/availability config | KEEP separate | CRITICAL |
| `maintenance_message` | scalar/editor | maintenance page | Yes | Content/Behavior | operational config + message | SPLIT | HIGH |
| `allow_registration` | scalar/editor | `ShopNavbar` registration visibility, registration controller | Yes | Behavior | storefront customer behavior | MIGRATE | HIGH |
| `enable_reviews` | scalar/editor | no confirmed storefront review component | Yes | Behavior | feature behavior | VERIFY/MIGRATE | MEDIUM |
| `enable_wishlist` | scalar/editor | shared props, navbar, wishlist counts | Yes | Behavior | feature behavior | KEEP/MIGRATE | HIGH |
| `enable_compare` | scalar/editor | no confirmed customer consumer | Yes | Behavior | feature behavior | VERIFY/MIGRATE | MEDIUM |
| `guest_checkout_enabled` | scalar/editor; `CheckoutController` reads it, `StorefrontCheckoutController` currently hard-codes true | Yes | Checkout | checkout presentation policy | SPLIT | CRITICAL |
| `cod_enabled` | scalar/editor | no confirmed `StorefrontCheckoutController` read; payment methods/user allowance used instead | Yes | Checkout | payment presentation policy | VERIFY/MIGRATE | HIGH |
| `free_shipping_threshold` | scalar/editor | no confirmed storefront checkout use | Yes | Checkout/Commerce | shared shipping policy | VERIFY/MIGRATE | HIGH |
| `default_shipping_fee` | scalar/editor | no confirmed storefront checkout use; city fee is used | Yes | Checkout/Commerce | shared shipping policy | VERIFY/MIGRATE | HIGH |
| `is_active` | scalar/model | no confirmed storefront gate | Yes | Behavior | storefront status | VERIFY | MEDIUM |
| `company_name`, `company_registration_number` | admin form only; no model fillable/migration column confirmed | no confirmed consumer | UNKNOWN — REQUIRES VERIFICATION | Store Identity | identity profile if needed | VERIFY/DEPRECATE | MEDIUM |
| `shipping_info`, `secure_payment_info`, `easy_returns_info` | admin form only; no confirmed model columns | no confirmed consumer | UNKNOWN — REQUIRES VERIFICATION | Content | homepage highlights | VERIFY/DEPRECATE | MEDIUM |

## 4. Database Audit

| Current Table | Current Purpose | Tenant-specific? | Current Dependencies | Target Table/Entity | Migration Action |
| --- | --- | --- | --- | --- | --- |
| `tenants` | tenant identity, slug/domain/status/logo, generic settings | Tenant row | `StoreResolver`, tenant middleware, tenant model, all business relationships | `tenants` remains; `storefronts` references it | KEEP; do not overload further |
| `website_infos` | mixed website/storefront settings | Yes after tenant migration; `TenantAware` | `WebsiteInfo`, settings controller/request, middleware, layouts, CMS, checkout controller, maintenance, onboarding, mail | `storefronts`, `storefront_seo`, `storefront_pages`, theme/config entities, media | KEEP during compatibility; split incrementally |
| `settings` | generic key/value settings | Yes after migration | `Setting` model and possible legacy consumers; current storefront use UNKNOWN | retain for unrelated legacy settings or deprecate later | AUDIT consumers before touching |
| `promotion_banners` | banner-like promotional content (`title`, `description`, `image`, `link`, `is_active`) | `TenantAware` model; migration history needs tenant column audit | `PromotionBanner`, admin banner controller, `PromotionBanner` component; current page data wiring not confirmed | `storefront_promotions` or campaign/ad entity | KEEP engine; later split presentation campaign from discount promotion |
| `promotions` before rename / promotion relations | discount engine with dates, values, usage, automatic rules | tenant addition exists in later migration history; verify deployed schema | `Promotion`, product/category pivots, cart/checkout/order services, storefront product pricing | shared commerce promotion engine remains separate from visual campaigns | KEEP; never merge with theme sections |
| `platform_settings` | global SaaS/platform configuration | No, platform-level | SuperAdmin pages/services | remains platform-level | KEEP separate |
| `products` | tenant products, images, product SEO, variants | Yes | storefront listing/detail/cart/order/inventory | remains shared ecommerce engine | KEEP; expose read model to sections |
| `categories` | tenant product categories | Yes | homepage/listing filters, shared props, product relations | remains engine entity; section references IDs | KEEP |
| `website_faqs` | tenant CMS FAQ entries | Yes | FAQ service, admin FAQ routes, storefront FAQ | `storefront_pages` or dedicated `storefront_faqs` | KEEP initially; migrate content boundary later |
| `payment_methods` | tenant checkout payment methods | Yes | checkout controller and admin payment routes | remains checkout engine; storefront config only controls presentation | KEEP |
| product image columns / `gallery_images` | product media | Yes through product | product card/detail/cart/checkout | remain product media; optional future media abstraction | KEEP; do not duplicate in storefront media |

`website_infos` was initially created without `tenant_id` and later converted by `2026_05_27_150002_add_tenant_id_to_business_tables.php`. The current model uses `TenantAware`, so tenant-scoped reads are structurally appropriate. The migration history contains an early `dropIfExists('website_infos')` in the create migration; this is historical migration behavior, not a Phase 1 change recommendation.

Media is currently path-based rather than record-based. `ImageService` uploads to named folders such as `website-settings` and `payment-proofs`; `WebsiteInfo` normalizes `/storage/...` and hosted storage paths and creates URL accessors. No general tenant-owned media table, alt text, mobile variant, crop metadata, or usage reference was confirmed.

## 5. Backend Dependency Map

```text
Admin Settings route
  -> SettingsController@edit/update
  -> UpdateWebsiteSettingsRequest
  -> WebsiteInfo::getSettings / WebsiteInfo::firstWhere
  -> ImageService upload/delete and JSON packing
  -> WebsiteInfo::save + cache invalidation

Storefront middleware
  -> StoreResolver resolves slug -> Tenant
  -> access check for User or Account membership
  -> current.tenant binding
  -> WebsiteInfo::first -> Inertia shared website_info

HandleInertiaRequests
  -> Tenant::getCurrent
  -> WebsiteInfo::first -> website_info, websiteSettings, app.name
  -> enable_wishlist -> wishlist props
  -> platform_setting + currency shared props

StorefrontController
  -> Tenant::getCurrent
  -> Product / Category / Promotion queries
  -> ProductService detail projection
  -> Storefront/Index, Products, Show, Faq

StorefrontCmsController
  -> WebsiteInfo::getSettings
  -> WebsiteFaqService
  -> Storefront/Cms pages and Policy

StorefrontCheckoutController
  -> Tenant, PaymentMethod, City, Township, CustomerAddress
  -> cart session, CouponService, PromotionService, StockCalculationService
  -> Storefront/Checkout
```

Important confirmed coupling: `StorefrontCheckoutController::index()` sets `$guestCheckout = true` rather than reading `WebsiteInfo::guest_checkout_enabled`, while the non-storefront `CheckoutController` reads the setting. This is a HIGH/CRITICAL compatibility issue for any migration of checkout settings, but it is documented only and not fixed in Phase 1.

## 6. Frontend Dependency Map

| Current Page/Component | Data Source | Current Settings | Hard-coded Content | Target Architecture | Risk |
| --- | --- | --- | --- | --- | --- |
| `Storefront/Index` | `StorefrontController@index`: products, categories, tenant; shared `website_info` | hero media, site name/description, theme via CSS variable | search labels, category/sort labels, empty/filter text | homepage section renderer plus shared shop discovery | HIGH |
| `Storefront/Products` | controller products/categories/filters | mostly none directly; layout/navbar settings | filter labels and product grid presentation | configurable shop layout tokens/variants | MEDIUM |
| `Storefront/Show` | product/detail/promotion | currency shared props; product SEO | option styles, buttons, stock labels, description defaults | product presentation components using tokens/content labels | HIGH |
| `Storefront/Cart` | `StorefrontCartController`, cart session, shared currency | currency only | cart, discount, shipping, button labels and classes | cart presentation config, shared cart logic unchanged | HIGH |
| `Storefront/Checkout` | checkout controller: cart/payment/cities/addresses/promotions | currency; intended guest checkout but not applied | all field labels, steps, payment instructions, validation UX | checkout presentation schema and label overrides; shared validation/order logic | CRITICAL |
| `StorefrontHero` | shared `website_info`, tenant | site name/description/logo/hero images | gradient, dimensions, Shop Now, Browse Categories | section variant resolver with no-image fallback | HIGH |
| `ShopNavbar` | shared `website_info`, tenant, auth/cart/wishlist | logo, site name, wishlist, registration, theme color | fixed Home/Products/My Orders/Login/Register labels and routes | navigation entity plus content labels and feature gates | HIGH |
| `ShopFooter` | shared `website_info`, tenant | footer logo/settings/contact/social/policies | fixed link groups and labels, dark layout | footer sections/navigation/content config | HIGH |
| `ProductGrid`/`ProductCard` | product paginator and product accessors | no storefront settings confirmed | fixed grid breakpoints, card spacing, labels | theme product-card and shop layout variants | HIGH |
| `CmsPage` and CMS pages | controller page props from `WebsiteInfo`/FAQ | policy/about/contact content | breadcrumbs, titles, date labels | page entities and shared CMS template variants | MEDIUM |
| `PromotionBanner` | `PromotionBanner` data when wired | banner image/link/status | gradient, Shop Now, desktop image hidden on mobile | campaign section with text-only fallback | HIGH |

The customer flow is:

```text
/store/{slug}
  -> StorefrontController@index
  -> Storefront/Index + StorefrontHero + ShopLayout
  -> product card/cart action
/store/{slug}/products
  -> StorefrontController@products
  -> Storefront/Products + ProductGrid
/store/{slug}/products/{product}
  -> StorefrontController@show + ProductService
  -> Storefront/Show + useCart
/store/{slug}/cart
  -> StorefrontCartController@index + cart actions
  -> Storefront/Cart
/store/{slug}/checkout
  -> StorefrontCheckoutController@index/store
  -> Storefront/Checkout
  -> shared Order/Promotion/Coupon/Stock services
```

Order success is not a standalone success page in the confirmed storefront route map; successful submission redirects to the authenticated customer order detail route `storefront.customer.orders.show`.

## 7. Customer Storefront Audit

Home currently combines a fixed hero with the product listing and filters. There is no confirmed database-driven section registry, section ordering, visibility state, category showcase, featured product selection, testimonials, gallery, newsletter, or campaign scheduling. `HomepageHero` and `FeaturedProducts`, `FeaturedCategories`, and `PromotionBanner` components exist, but their complete production page wiring was not confirmed; they must not be assumed to be active.

Shop is the more complete page: server-side search by product name, category filter, sort (`latest`, price ascending/descending, name), optional in-stock filter, pagination via Inertia scroll, sidebar, and responsive product grid. Best sellers is linked in the footer but no corresponding sort branch was confirmed.

Product detail has reusable business data from `ProductService`, active variants, combo data, promotions, image/gallery accessors, and product-level SEO fields. Presentation is strongly fixed: six hard-coded option color styles, Indigo buttons, fixed image ratio, fixed stock labels, and hard-coded text.

Cart and checkout use the shared cart session and existing order/discount/payment flows. Checkout has a three-step responsive UI, address selection, city/township lookup, payment evidence upload, payment method selection, and order submission. Its layout is mobile-aware, but field visibility/requiredness is hard-coded in both React and Laravel validation.

## 8. Current Problems

### Architecture and UX

- `website_infos` is a large mixed-responsibility row with repeated scalar and JSON representations.
- `Tenant.settings`, generic `settings`, `website_infos`, and `platform_settings` overlap conceptually; ownership rules are not obvious to merchants.
- A single admin form presents technical/operational controls beside content and branding.
- No draft, preview, publish, revision, or rollback model exists for storefront settings.
- `/admin/*` and `/store/{slug}/admin/*` duplicate route declarations. The storefront admin route file explicitly notes that controllers still redirect to legacy `admin.*` routes.
- `WebsiteInfo::getSettings()` creates records as a read side effect, which complicates migration and preview semantics.

### Frontend and responsive

- Customer labels and visual values are spread through JSX and Tailwind classes.
- Primary color is used through a CSS variable fallback, but no complete token contract exists.
- Home, navbar, footer, product detail, cart, and checkout use fixed structures rather than tenant-selected variants.
- Marketing/image sections are not modeled with independent visibility and fallback rules.
- `StorefrontHero` hides its image column when there are no images, which is a good local fallback, but the overall hero remains a fixed gradient block.
- `PromotionBanner` returns null for no banners, but image rendering is hidden on small screens rather than using a mobile image or deliberate text variant.
- Mobile support exists in many pages, but it is responsive CSS around a desktop-oriented information architecture, not a mobile-priority storefront composition.

### Media

- Images are raw paths in columns/JSON arrays, without reusable media records, alt text, mobile variants, ownership metadata, usage tracking, or crop/focal-point data.
- `about_image` is accepted by the request/controller and included in model image normalization, but no migration column or confirmed storefront renderer was found.
- Hero upload replacement/deletion is embedded in `SettingsController`, making media lifecycle a settings concern.
- Existing image URLs can be local storage or hosted paths; migration must preserve normalization and URL compatibility.

### Hard-coded content

Confirmed examples include `Shop Now`, `Browse Categories`, `Home`, `Products`, `My Orders`, `Login`, `Register`, `Shopping Cart`, `Proceed to Checkout`, `Place Order`-style checkout labels, `Product Description`, `Out of Stock`, `In Stock`, `No Image`, footer group names, and product detail labels. The exact label set is larger than the current settings schema and should be grouped into a small namespaced content/label configuration rather than hundreds of independent columns.

## 9. Target Storefront Architecture

```text
Shared Ecommerce Engine
  Products, categories, promotions, cart, checkout, orders, inventory, payment methods
          |
Tenant Storefront Aggregate
  identity, status, locale/display config, selected theme, published revision
          |
Storefront Configuration Resolver
  active draft/preview or published snapshot + safe defaults
          |
Theme Runtime
  theme identity/version -> tokens -> component/section variants
          |
Reusable Storefront Components
  shell, navigation, product cards, product detail, cart, checkout presentation
          |
Homepage Section Registry
  visibility, order, variant, typed section config, media references
```

Recommended merchant-facing groups are Overview, Identity, Theme & Appearance, Homepage, Header & Navigation, Shop, Product Display, Checkout, Footer, Pages, Media, Content & Labels, and SEO & Social. Keep billing, integrations, notifications, menu visibility, and maintenance outside the storefront editor even if they currently appear near it.

## 10. Target Database Architecture

Use dedicated tables for stable responsibilities and small typed JSON configs for section-specific variant data.

Recommended Version 2 entities:

- `storefronts`: `id`, `tenant_id` unique, `status`, `locale`, `currency/display references`, `active_theme_id`, `published_revision_id`, timestamps.
- `themes`: platform-owned theme identity, slug, immutable version, supported capabilities, default token/variant manifest. A theme is not tenant data.
- `storefront_theme_configs`: tenant-owned theme selection/configuration, theme version, token overrides, variant selections. Keep validated JSON here only for theme settings, not arbitrary page HTML.
- `storefront_revisions`: tenant-owned draft/preview/published snapshots, revision number, status, published timestamp, created by. This can later support atomic publish.
- `storefront_homepage_sections`: revision/storefront reference, stable section type, variant, enabled, desktop/mobile visibility, position, typed config JSON, media references. Add unique position/order constraints per revision.
- `storefront_media`: tenant-owned media records with storage key, type, alt text, metadata, mobile/crop/focal metadata, visibility, and timestamps. Do not create one column for every future image behavior.
- `storefront_navigation` and `storefront_navigation_items`: tenant/revision-owned menus, order, label, link target, visibility/device flags.
- `storefront_pages`: tenant/revision-owned slug, title, content, status, SEO reference. Keep FAQ as a dedicated entity or migrate it behind the same page boundary, not arbitrary JSON.
- `storefront_content`: small namespaced key/value or JSON groups for labels and customer-facing copy, with a validated key registry and locale support.
- `storefront_checkout_configs`: tenant/revision-owned presentation flags and field visibility/requiredness. Server-side business validation remains shared and must apply a safe intersection of merchant configuration and platform rules.
- `storefront_seo`: tenant/revision-owned metadata, canonical/robots/social fields, and optional analytics integration references.

Avoid storing arbitrary HTML/component names/React props. JSON is appropriate for a section's typed configuration and theme token overrides; it is not a replacement for stable entities or tenant ownership.

## 11. Target React Architecture

Keep Inertia server-rendered pages and route structure. Add a backend `StorefrontViewModel`/resolver that returns a normalized storefront contract, rather than exposing raw Eloquent settings to every component.

```text
Pages/Storefront
  StorefrontHome
  StorefrontShop
  StorefrontProduct
  StorefrontCart
  StorefrontCheckout

Components/Storefront
  StorefrontShell
  StorefrontNavigation
  StorefrontSectionRenderer
  sections/{type}/{variant}
  ProductCard variants
  ProductGallery
  CheckoutField/CheckoutStep presentation components

Runtime contract
  storefront.identity
  storefront.theme.tokens
  storefront.theme.variants
  storefront.navigation
  storefront.homepage.sections
  storefront.content.labels
  storefront.behavior
  storefront.media
```

The renderer must filter disabled sections before layout composition. A section with no valid content/media must return null or a declared fallback variant. It must never render an empty fixed-height wrapper.

## 12. Theme + Design Token Architecture

Separate three concepts:

- **Theme identity:** platform-owned stable slug/name, such as `modern`, `minimal`, or `fashion`.
- **Theme version:** immutable implementation contract, such as `modern@2.0.0`; updates must be explicit and reversible.
- **Tenant configuration:** the tenant's selected theme/version, token overrides, section variants, and content. It must never contain executable code.

Token layers should be:

```text
Theme defaults
  -> tenant validated overrides
  -> CSS custom properties / React token contract
  -> primitives: Button, Card, Input, Badge, Container, Stack
  -> sections and pages
```

Token groups: colors, typography, spacing, radius, borders, shadows, buttons, cards, inputs, container widths, and breakpoints/visibility policies. Store semantic tokens (`color.primary`, `surface.card`, `action.primary`) rather than raw component-specific values. Existing hard-coded Indigo/blue/purple/slate values should be catalogued and migrated behind primitives in Phase 2, not changed in this audit.

Theme controls presentation, layout, tokens, and variants only. It cannot control order creation, inventory deduction, payment business logic, cart mutation, customer persistence, authorization, tenant resolution, or subscription checks.

## 13. Homepage + Section Architecture

Confirmed active homepage behavior is a hero plus product discovery/listing. `HomepageHero`, `FeaturedProducts`, `FeaturedCategories`, and `PromotionBanner` exist, but their complete active wiring is UNKNOWN and requires route/page verification before migration.

Target section registry should support, when implemented: announcement, hero, promotion, categories, featured products, product showcase, brand story, store highlights, testimonials, gallery, CTA, and newsletter. Version 2 should implement only sections supported by confirmed product requirements and existing data.

Each section record needs `type`, `variant`, `enabled`, `position`, `desktop_visibility`, `mobile_visibility`, `config`, and media references. The renderer should resolve:

```text
enabled=false -> omit section entirely
enabled=true + valid config -> selected variant
selected image variant + no image -> declared text/minimal/product fallback
invalid/empty section -> omit or safe fallback, never empty height
```

Hero variants should include image, split, text-only, minimal, and product hero. Promotion variants should include image/text, text-only, and compact announcement. Mobile can select a separate variant or mobile media without duplicating business data.

## 14. Media / Image Architecture

Retain `ImageService` and existing storage providers. Add a tenant-owned media record layer around it rather than replacing storage immediately.

Recommended media record fields: `tenant_id`, storage key, provider/disk, media type, original/fallback URL metadata, alt text, title, width/height, size, mime, focal point/crop metadata, status, and timestamps. Section-specific roles should reference media IDs and store optional `desktop_media_id`, `mobile_media_id`, `link`, CTA, fit/crop, and fallback configuration in validated section config. Do not make every future capability a top-level column.

Migration must preserve existing paths, normalize legacy `/storage/` values, and avoid deleting old files until all references are verified. A media cleanup job is not safe until usage references and rollback are available.

## 15. Content / Label Architecture

Use a small namespaced content contract, for example `content.labels.add_to_cart`, `content.labels.buy_now`, `content.labels.shop_now`, `content.labels.view_product`, `content.labels.view_cart`, `content.labels.checkout`, `content.labels.place_order`, `content.labels.continue_shopping`, plus section/page copy groups.

Each label should support a preset (`default`, `buy_now`, `buy`) or custom text, with server-side length validation and locale fallback. Store only meaningful customer-facing overrides. Do not create a column for every phrase and do not let labels alter authorization or business semantics.

Existing translation files remain platform UI translation infrastructure. Tenant content labels are a separate storefront content concern.

## 16. Checkout Architecture

Checkout business logic remains in the existing controller/services and shared order flow. A future `storefront_checkout_configs` entity may control presentation and allowed fields: guest checkout presentation, phone/email/postal code/order notes visibility and requiredness, step labels, and payment instruction copy.

The server must define non-negotiable fields and validate tenant configuration against business/payment rules. React should render from a normalized field schema, but Laravel validation must remain authoritative. Payment method availability remains driven by `PaymentMethod`, account rules, platform rules, and shared checkout services.

Before migration, resolve the confirmed mismatch where the storefront checkout currently hard-codes guest checkout enabled while another checkout controller reads `guest_checkout_enabled`. This is a Phase 2 compatibility task, not a Phase 1 code change.

## 17. Responsive Architecture

Use content-aware responsive policies rather than only shrinking desktop markup. Storefront configuration should allow desktop/mobile visibility and, where useful, separate mobile media and section variants. The runtime must prioritize search, categories, products, cart, and checkout on mobile; marketing sections may be hidden or simplified.

Preserve current responsive strengths: product grid breakpoints, mobile navbar menu, product detail sticky purchase bar, cart mobile card layout, and checkout single-column layout. Replace fixed dimensions and classes gradually with tokens and variants. Test desktop, tablet, and mobile independently, including no-image and no-promotion states.

## 18. Preview / Publish Architecture

Prepare now for draft/preview/publish by making the storefront resolver accept an explicit context:

```text
published -> current published revision only
preview + authorized merchant -> selected draft revision
customer -> published revision only
```

Use immutable revisions or snapshots for atomic publish. Preview URLs require authenticated tenant membership, a signed/expiring token if shareable, and the same tenant binding checks as admin routes. Do not expose draft content through normal public routes. Desktop/tablet/mobile preview is a renderer viewport choice, not three separate content copies.

## 19. Legacy Migration Strategy

1. Inventory and freeze the confirmed legacy contract. Keep `website_infos`, all current routes, images, and fallback behavior.
2. Add a read-only storefront resolver that can return legacy data normalized to the target contract.
3. Introduce additive target tables and seed one storefront configuration per tenant without deleting or mutating legacy values.
4. Build a deterministic mapper for each setting: KEEP, MIGRATE, SPLIT, MERGE, DEPRECATE, or REMOVE LATER. Log unmapped fields and unknown admin fields.
5. Migrate identity, SEO, pages, social, footer, and media references with parity checks. Preserve legacy paths until every new media reference is verified.
6. Migrate homepage sections behind a feature flag. Start with the current hero as one section and preserve text-only fallback.
7. Switch reads through the resolver, retaining legacy fallback for missing target values. Do not dual-write blindly; use an explicit migration service and audit trail.
8. Add preview/publish only after published rendering is parity-tested.
9. Remove legacy consumers field-by-field, not table-first. Deprecate columns only after production data and rollback windows are complete.

Classification summary: identity/SEO/content/media should MIGRATE or SPLIT; currency and customer feature flags require KEEP/MIGRATE with behavior parity; maintenance and platform settings remain separate; generic `settings` requires a consumer audit; product/promotion/order/cart/payment tables remain shared engine entities.

## 20. Tenant Isolation Review

Positive controls confirmed:

- `Storefront` resolves a route slug and rejects unknown stores.
- Authenticated non-superadmins are checked against User tenant ID or active Account membership.
- `current.tenant` is set before downstream queries.
- `WebsiteInfo`, `Product`, `Category`, `PromotionBanner`, and other business models use `TenantAware` where inspected.
- Storefront routes use `storefront` and `tenant.binding`; customer routes add `tenant.access`.
- Product and cart handling includes tenant filtering in storefront checkout.

Risks requiring Phase 2 tests:

- `Storefront` uses `WebsiteInfo::first()` after setting the tenant; this relies on global scope context and should be covered by cross-tenant request tests.
- `HandleInertiaRequests` shares `WebsiteInfo::first()` based on current tenant and route shape; preview and non-slug routes need explicit isolation tests.
- Future media, revisions, navigation, and preview records must all have tenant ownership and scoped route binding.
- Any `withoutTenantScope()` migration/admin operation must require an explicit tenant ID and never accept user-controlled cross-tenant IDs.
- Promotion banner page wiring and any generic settings consumers need a tenant query audit.

No confirmed tenant leakage was automatically fixed in Phase 1. Cross-tenant leakage is CRITICAL if introduced by the new resolver, media URLs, preview route, or unscoped migration.

## 21. Version 2 -> Version 6 Compatibility

| Version | Supported by proposed foundation |
| --- | --- |
| V2 | storefront aggregate, theme identity/version, validated tokens, homepage sections, media records, responsive visibility, shop/product presentation, checkout presentation config, labels, SEO, preview-ready resolver |
| V3 | add section types/variants, ordering, mobile controls, product layouts without changing engine contracts |
| V4 | add campaign entities with scheduling and targeting; keep discount promotions separate from visual campaigns |
| V5 | immutable theme versions, theme manifests/capabilities, reusable section packages, upgrade/rollback tooling |
| V6 | visual builder writes validated section records/config, custom landing pages use the same page/section runtime; no arbitrary executable HTML required |

The key compatibility decisions are stable section type IDs, explicit variants, immutable theme versions, normalized storefront contracts, revision-based publishing, and no business logic in themes.

## 22. Risk Analysis

| Risk | Level | Assessment / mitigation |
| --- | --- | --- |
| Tenant data leakage | CRITICAL | enforce `tenant_id`, scoped relations, route binding, preview authorization, and cross-tenant tests |
| Checkout breakage | CRITICAL | keep shared controller/services; migrate presentation schema only after validation parity; address guest-checkout mismatch |
| Existing storefront breakage | HIGH | resolver fallback, feature flag, parity snapshots, retain legacy routes and settings |
| Data migration errors | HIGH | additive tables, idempotent mapper, per-tenant reconciliation, rollback/retry logs |
| Image loss | HIGH | media records reference existing storage keys; no deletion until usage verification |
| Route breakage | HIGH | retain both route families during admin migration; audit redirects and Ziggy paths |
| Hard-coded frontend dependencies | HIGH | component contract migration and labels/tokens inventory before switching reads |
| Existing merchant data | HIGH | preserve unknown columns, report unmapped values, never silently discard admin form fields |
| Legacy settings cache | HIGH | version/cache-key resolver and explicit invalidation after writes |
| Promotion/advertisement confusion | MEDIUM | separate visual campaigns from discount promotions and preserve current promotion engine |
| Preview exposing drafts | CRITICAL | signed/authenticated preview, explicit revision context, no public draft fallback |
| Over-engineering | MEDIUM | implement only typed sections and tokens needed for V2; no arbitrary page builder |

## 23. Recommended Phase 2 Implementation Order

1. Add characterization tests for current settings, tenant resolution, storefront pages, image URL behavior, both admin route prefixes, and checkout behavior.
2. Create a normalized read-only `StorefrontConfigurationResolver` backed by `WebsiteInfo` fallback. No schema replacement yet.
3. Add `storefronts` and theme identity/config entities with one tenant-safe record per tenant.
4. Introduce token contract and migrate the shared storefront shell/primitives first: container, button, card, input, navbar, footer.
5. Add media records and migrate logo/favicon/OG/hero references without deleting legacy files.
6. Add homepage section records and render only the current hero plus confirmed product discovery sections behind a feature flag.
7. Add content/label resolution for high-value CTA/cart/checkout labels.
8. Add storefront pages/navigation/SEO projection and migrate CMS consumers.
9. Add checkout presentation configuration while preserving shared Laravel validation and order logic.
10. Add draft/preview/publish revisions after published rendering has parity.
11. Migrate reads field-by-field, retain legacy fallback, run tenant isolation and regression tests, then plan legacy cleanup separately.

## 24. Files That Would Need Changes in Phase 2

Likely, based on confirmed current consumers:

- `app/Models/WebsiteInfo.php`, `Tenant.php`, `Setting.php`, and new storefront models/services.
- `app/Http/Middleware/Storefront.php` and `HandleInertiaRequests.php` for normalized storefront props.
- `app/Http/Controllers/Admin/SettingsController.php`, `UpdateWebsiteSettingsRequest.php`, and future storefront settings controllers.
- `app/Http/Controllers/StorefrontController.php`, `StorefrontCmsController.php`, `StorefrontCheckoutController.php`, and `StorefrontCartController.php` for resolver consumption only.
- `routes/web.php`, `routes/storefront-admin.php`, and generated route metadata only if new routes are introduced.
- `resources/js/Layouts/ShopLayout.jsx`, `ShopNavbar.jsx`, `ShopFooter.jsx`, `Components/Storefront/*`, `ProductGrid.jsx`, `ProductCard.jsx`, and customer storefront pages.
- `resources/js/Pages/Admin/Settings/Edit.jsx` and future merchant storefront configuration pages.
- Additive migrations, factories/seeders, feature tests, and tenant isolation tests.

Do not change order, cart, inventory, payment business logic, customer persistence, authentication, tenant middleware semantics, or subscription logic except for the minimum read-side integration required to consume a validated storefront presentation contract.

## 25. What Must NOT Be Changed

- Do not drop `website_infos`, `settings`, or any existing table during the migration.
- Do not delete legacy settings, image files, React pages, or routes until parity and rollback criteria are met.
- Do not move order creation, stock deduction/validation, cart mutation, payment processing, customer persistence, authentication, tenant isolation, or subscription enforcement into themes or sections.
- Do not expose raw JSON, CSS variables, React component names, or database keys in merchant UI.
- Do not install a page-builder package, microservice, database-per-tenant design, or arbitrary HTML execution system.
- Do not assume admin-only fields such as analytics IDs, TikTok/Pinterest, `about_image`, or info-card values are persisted; verify them before migration.
- Do not make a storefront section reserve fixed space when disabled or when its content/media is unavailable.
- Do not assume `HomepageHero`, `FeaturedProducts`, `FeaturedCategories`, or `PromotionBanner` are all active on the current homepage until their route/page wiring is verified.
- Do not fix unrelated bugs discovered during this audit automatically. The storefront checkout guest-setting mismatch and best-sellers link mismatch are documented Phase 2 investigation items.

### Audit evidence reviewed

Core evidence includes `app/Models/WebsiteInfo.php`, `app/Http/Controllers/Admin/SettingsController.php`, `app/Http/Requests/UpdateWebsiteSettingsRequest.php`, `database/migrations/*website_infos*`, `app/Http/Middleware/Storefront.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`, `routes/storefront-admin.php`, `StorefrontController`, `StorefrontCmsController`, `StorefrontCheckoutController`, `resources/js/Pages/Storefront/*`, `ShopLayout`, `ShopNavbar`, `ShopFooter`, storefront components, `PromotionBanner`, `PromotionBanner` model, `Setting`, `Tenant`, and `TenantAware`.

Items explicitly marked `UNKNOWN — REQUIRES VERIFICATION` must be confirmed against the deployed schema and complete route/page wiring before Phase 2 migration scripts are written.

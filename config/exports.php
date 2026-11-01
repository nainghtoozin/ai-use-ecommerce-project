<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Merchant Data Export
    |--------------------------------------------------------------------------
    |
    | Configuration for the manual, tenant-scoped "Export My Data" feature.
    | Generated archives are written to a private disk (never under the web
    | root) and are streamed to the browser with deleteFileAfterSend(true).
    | Any orphaned archive left behind by an interrupted request is purged
    | once it is older than temp_ttl_hours.
    |
    */

    'disk' => 'local',

    'path' => 'exports',

    'temp_ttl_hours' => 24,

    'chunk_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | Export Categories
    |--------------------------------------------------------------------------
    |
    | Each category maps to a curated, tenant-scoped dataset. `formats`
    | lists the single-dataset download formats available; the full archive
    | (ZIP) is always offered separately and bundles every category as CSV.
    |
    */

    'categories' => [
        'products' => [
            'label' => 'Products',
            'group' => 'Products & Inventory',
            'description' => 'Product catalogue with pricing, stock, SEO and media paths.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'product_variants' => [
            'label' => 'Product Variants',
            'group' => 'Products & Inventory',
            'description' => 'Variant combinations, pricing, stock and image paths.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'product_combos' => [
            'label' => 'Product Combos',
            'group' => 'Products & Inventory',
            'description' => 'Bundle contents, linked variants and quantities.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'inventory_levels' => [
            'label' => 'Inventory Levels',
            'group' => 'Products & Inventory',
            'description' => 'Computed stock levels by product, variant and warehouse.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'inventory_movements' => [
            'label' => 'Inventory Movements',
            'group' => 'Products & Inventory',
            'description' => 'Stock movement history (sales, purchases, adjustments).',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'orders' => [
            'label' => 'Orders',
            'group' => 'Orders & Sales',
            'description' => 'Order headers with customer, delivery, payment and totals.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'order_items' => [
            'label' => 'Order Items',
            'group' => 'Orders & Sales',
            'description' => 'Line items for every order.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'order_coupons' => [
            'label' => 'Order Coupons',
            'group' => 'Orders & Sales',
            'description' => 'Coupon discounts applied to orders.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'order_override_logs' => [
            'label' => 'Order Override History',
            'group' => 'Orders & Sales',
            'description' => 'Merchant-side order field overrides and reasons.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'customers' => [
            'label' => 'Customers',
            'group' => 'Customers',
            'description' => 'Customer roster including customers without orders.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'customer_addresses' => [
            'label' => 'Customer Addresses',
            'group' => 'Customers',
            'description' => 'Saved customer shipping addresses and notes.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'wishlists' => [
            'label' => 'Wishlists',
            'group' => 'Customers',
            'description' => 'Customer wishlist entries.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'categories' => [
            'label' => 'Categories',
            'group' => 'Catalogue',
            'description' => 'Product categories with parent, image and ordering.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'brands' => [
            'label' => 'Brands',
            'group' => 'Catalogue',
            'description' => 'Product brands with logo and banner paths.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'units' => [
            'label' => 'Units',
            'group' => 'Catalogue',
            'description' => 'Units of measure.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'coupons' => [
            'label' => 'Coupons',
            'group' => 'Promotions',
            'description' => 'Coupon codes, limits, schedules and usage counts.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'coupon_products' => [
            'label' => 'Coupon Products',
            'group' => 'Promotions',
            'description' => 'Products restricted to each coupon.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'coupon_categories' => [
            'label' => 'Coupon Categories',
            'group' => 'Promotions',
            'description' => 'Categories restricted to each coupon.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'promotions' => [
            'label' => 'Promotions',
            'group' => 'Promotions',
            'description' => 'Promotions, discounts and usage limits.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'promotion_products' => [
            'label' => 'Promotion Products',
            'group' => 'Promotions',
            'description' => 'Products included in each promotion.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'promotion_categories' => [
            'label' => 'Promotion Categories',
            'group' => 'Promotions',
            'description' => 'Categories included in each promotion.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'promotion_usages' => [
            'label' => 'Promotion Usages',
            'group' => 'Promotions',
            'description' => 'Promotion usage history and discounts.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'promotion_banners' => [
            'label' => 'Promotion Banners',
            'group' => 'Promotions',
            'description' => 'Promotional banners, schedules and image paths.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'flash_sales' => [
            'label' => 'Flash Sales',
            'group' => 'Promotions',
            'description' => 'Flash sale campaigns and usage counts.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'flash_sale_products' => [
            'label' => 'Flash Sale Products',
            'group' => 'Promotions',
            'description' => 'Products, variants and limits in each flash sale.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'delivery_services' => [
            'label' => 'Delivery Services',
            'group' => 'Delivery & Payments',
            'description' => 'Delivery services and fees.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'delivery_pricing' => [
            'label' => 'Delivery Pricing',
            'group' => 'Delivery & Payments',
            'description' => 'Township delivery pricing and lead times.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'cod_rules' => [
            'label' => 'COD Rules',
            'group' => 'Delivery & Payments',
            'description' => 'Cash-on-delivery amount and city rules.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'packaging_options' => [
            'label' => 'Packaging Options',
            'group' => 'Delivery & Payments',
            'description' => 'Packaging options and fees.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'warehouses' => [
            'label' => 'Warehouses',
            'group' => 'Delivery & Payments',
            'description' => 'Warehouse locations.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'payment_methods' => [
            'label' => 'Payment Methods',
            'group' => 'Delivery & Payments',
            'description' => 'Store payment methods, bank details and QR paths.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'cities' => [
            'label' => 'Cities',
            'group' => 'Delivery & Payments',
            'description' => 'Store delivery cities.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'townships' => [
            'label' => 'Townships',
            'group' => 'Delivery & Payments',
            'description' => 'Store townships, postal codes and delivery fees.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefronts' => [
            'label' => 'Storefronts',
            'group' => 'Storefront & Content',
            'description' => 'Storefront status, theme and revision pointers.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_contents' => [
            'label' => 'Storefront Content',
            'group' => 'Storefront & Content',
            'description' => 'Storefront UI labels and content.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_design_tokens' => [
            'label' => 'Storefront Design Tokens',
            'group' => 'Storefront & Content',
            'description' => 'Storefront colours, typography and spacing tokens.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_homepage_sections' => [
            'label' => 'Storefront Homepage Sections',
            'group' => 'Storefront & Content',
            'description' => 'Homepage sections, visibility and configuration.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_media' => [
            'label' => 'Storefront Media',
            'group' => 'Storefront & Content',
            'description' => 'Uploaded storefront media metadata and paths.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_navigations' => [
            'label' => 'Storefront Navigation',
            'group' => 'Storefront & Content',
            'description' => 'Storefront navigation settings.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_navigation_items' => [
            'label' => 'Storefront Navigation Items',
            'group' => 'Storefront & Content',
            'description' => 'Storefront navigation links and ordering.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_revisions' => [
            'label' => 'Storefront Revisions',
            'group' => 'Storefront & Content',
            'description' => 'Storefront revision history and configuration snapshots.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_theme_configs' => [
            'label' => 'Storefront Theme Config',
            'group' => 'Storefront & Content',
            'description' => 'Storefront theme configuration.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_checkout_configs' => [
            'label' => 'Storefront Checkout Config',
            'group' => 'Storefront & Content',
            'description' => 'Checkout layouts, labels and messages.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'storefront_product_display_configs' => [
            'label' => 'Storefront Product Display Config',
            'group' => 'Storefront & Content',
            'description' => 'Product page display and stock visibility settings.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'website_faqs' => [
            'label' => 'Website FAQs',
            'group' => 'Storefront & Content',
            'description' => 'Store FAQ entries in all languages.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'store_settings' => [
            'label' => 'Store Profile & Settings',
            'group' => 'Storefront & Content',
            'description' => 'Store profile, SEO, hero, footer, policy and feature settings.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'settings' => [
            'label' => 'Additional Settings',
            'group' => 'Storefront & Content',
            'description' => 'Other tenant settings, excluding sensitive keys.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
        'import_histories' => [
            'label' => 'Import History',
            'group' => 'Operations',
            'description' => 'Product import runs, errors, warnings and reports.',
            'formats' => ['csv', 'xlsx', 'json'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Merchant Backup
    |--------------------------------------------------------------------------
    |
    | The signed merchant backup reuses the export engine and adds a version 2
    | manifest with store identity, archive identity, creator, schema
    | fingerprint, record counts and an explicit exclusion list. Authenticity
    | is proven with HMAC-SHA256 over the canonical manifest using a dedicated
    | environment key. The key is never written into the archive and the
    | download endpoint fails closed when it is missing or too short.
    |
    | setting_key_allowlist is an explicit allowlist: only settings whose key
    | equals an entry, or starts with an entry ending in "." or "_", are
    | included. Everything else is excluded from the backup and only its key
    | name is recorded in the manifest. The legacy "settings" export keeps its
    | previous regex-only filter for backward compatibility.
    |
    */

    'backup' => [
        'schema_version' => 2,

        'signing_key' => env('MERCHANT_BACKUP_SIGNING_KEY'),

        'key_id' => env('MERCHANT_BACKUP_SIGNING_KEY_ID', 'merchant-backup-v1'),

        'minimum_key_length' => 32,

        'per_hour' => (int) env('MERCHANT_BACKUP_PER_HOUR', 10),

        'verify_per_hour' => (int) env('MERCHANT_BACKUP_VERIFY_PER_HOUR', 5),

        'recover_per_hour' => (int) env('MERCHANT_BACKUP_RECOVER_PER_HOUR', 3),

        'recovery_lock_seconds' => (int) env('MERCHANT_BACKUP_RECOVERY_LOCK_SECONDS', 300),

        'recovery_claim_lease_minutes' => (int) env('MERCHANT_BACKUP_RECOVERY_CLAIM_LEASE_MINUTES', 120),

        'temp_ttl_hours' => (int) env('MERCHANT_BACKUP_TEMP_TTL_HOURS', 2),

        'upload_ttl_hours' => (int) env('MERCHANT_BACKUP_UPLOAD_TTL_HOURS', 1),

        'max_upload_kb' => (int) env('MERCHANT_BACKUP_MAX_UPLOAD_KB', 51200),

        'max_entries' => 200,

        'max_entry_bytes' => 67108864,

        'max_total_bytes' => 268435456,

        'max_manifest_bytes' => 8388608,

        'max_assets_bytes' => (int) env('MERCHANT_BACKUP_MAX_ASSETS_BYTES', 4194304),

        'max_compression_ratio' => 200,

        'setting_key_allowlist' => [
            'notifications_enabled',
            'notification_',
            'item_',
            'other_location.',
            'cod_availability_mode',
        ],
    ],

];

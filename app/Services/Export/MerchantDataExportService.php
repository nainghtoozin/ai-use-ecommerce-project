<?php

namespace App\Services\Export;

use App\Auth\IdentityResolver;
use App\Exports\MerchantDatasetExport;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\FlashSale;
use App\Models\ImportHistory;
use App\Models\Order;
use App\Models\OrderCoupon;
use App\Models\OrderItem;
use App\Models\OrderOverrideLog;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCombo;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionBanner;
use App\Models\PromotionUsage;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Storefront;
use App\Models\StorefrontCheckoutConfig;
use App\Models\StorefrontContent;
use App\Models\StorefrontDesignToken;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontMedia;
use App\Models\StorefrontNavigation;
use App\Models\StorefrontNavigationItem;
use App\Models\StorefrontProductDisplayConfig;
use App\Models\StorefrontRevision;
use App\Models\StorefrontThemeConfig;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebsiteFaq;
use App\Models\WebsiteInfo;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class MerchantDataExportService
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_JSON = 'json';
    public const FORMAT_ZIP = 'zip';

    public function __construct(
        private readonly IdentityResolver $identityResolver
    ) {}

    /**
     * Curated store profile / configuration keys included in the export.
     */
    private const STORE_SETTING_KEYS = [
        'site_name', 'site_tagline', 'site_description', 'site_keywords',
        'theme_color', 'default_language', 'timezone', 'currency_code',
        'currency_symbol', 'currency_position', 'decimal_places', 'date_format',
        'logo', 'favicon', 'og_image', 'footer_logo', 'contact_email',
        'support_email', 'phone', 'whatsapp_number', 'address', 'country',
        'google_maps_embed_url', 'contact_info', 'support_info', 'address_info',
        'about_title', 'about_description', 'about_image', 'mission_title',
        'mission_description', 'vision_title', 'vision_description',
        'facebook_url', 'instagram_url', 'twitter_url', 'linkedin_url',
        'youtube_url', 'meta_title', 'meta_description', 'meta_keywords',
        'canonical_url', 'robots_meta', 'hero_title', 'hero_subtitle',
        'hero_button_text', 'hero_button_link', 'hero_image', 'hero_images',
        'footer_description', 'footer_copyright', 'footer_settings',
        'privacy_policy', 'terms_conditions', 'shipping_policy',
        'return_policy', 'refund_policy', 'maintenance_mode',
        'maintenance_message', 'allow_registration', 'enable_reviews',
        'enable_wishlist', 'enable_compare', 'guest_checkout_enabled',
        'cod_enabled', 'free_shipping_threshold', 'default_shipping_fee',
        'is_active',
    ];

    private const SENSITIVE_SETTING_PATTERN = '/(password|secret|token|api[_-]?key|credential|private[_-]?key)/i';

    /**
     * Areas that are intentionally absent from a merchant backup. These are
     * recorded in the manifest so a future recovery can explain the gaps.
     */
    private const BACKUP_EXCLUSIONS = [
        [
            'area' => 'Platform billing',
            'examples' => 'invoices, payment intents, subscriptions, plans, platform settings',
            'reason' => 'Owned by the platform, not by the store.',
        ],
        [
            'area' => 'Staff identity and security',
            'examples' => 'staff users, password hashes, remember tokens, roles, memberships, sessions',
            'reason' => 'Credentials and access-control records are never exported.',
        ],
        [
            'area' => 'Integration secrets',
            'examples' => 'telegram integrations, SMTP credentials, API keys, signing and encryption keys',
            'reason' => 'Secrets must never leave the platform.',
        ],
        [
            'area' => 'Audit and system data',
            'examples' => 'activity logs, audit logs, jobs, failed jobs, operator system backups',
            'reason' => 'Operational and operator data is outside the merchant scope.',
        ],
        [
            'area' => 'Media binaries',
            'examples' => 'uploaded images and files, including Cloudinary assets',
            'reason' => 'Only metadata and checksums are exported; binaries are not recoverable from this archive.',
        ],
        [
            'area' => 'Lossy order fields',
            'examples' => 'order idempotency key, transaction id, payment screenshots, order-to-customer link',
            'reason' => 'Not part of the curated dataset; a future recovery will not restore them.',
        ],
    ];


    public function categories(): array
    {
        return config('exports.categories', []);
    }

    public function hasCategory(string $category): bool
    {
        return array_key_exists($category, $this->categories());
    }

    /**
     * Grouped category metadata for the UI.
     */
    public function groupedCategories(): array
    {
        $groups = [];

        foreach ($this->categories() as $key => $meta) {
            $group = $meta['group'] ?? 'Other';
            $groups[$group][] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'description' => $meta['description'] ?? '',
                'formats' => $meta['formats'] ?? [self::FORMAT_CSV],
            ];
        }

        return array_map(
            fn ($name, $items) => ['name' => $name, 'categories' => $items],
            array_keys($groups),
            array_values($groups)
        );
    }

    public function headingsFor(string $category): array
    {
        return match ($category) {
            'products' => ['id', 'name', 'slug', 'sku', 'barcode', 'type', 'status', 'category', 'brand', 'unit', 'price', 'sale_price', 'base_price', 'cost_price', 'stock', 'low_stock_alert', 'featured', 'sort_order', 'short_description', 'description', 'seo_title', 'seo_description', 'seo_keywords', 'photo1', 'photo2', 'gallery_images', 'seo_image', 'created_at', 'updated_at'],
            'product_variants' => ['id', 'product_id', 'product_name', 'product_sku', 'sku', 'barcode', 'attributes', 'price', 'compare_price', 'cost_price', 'stock', 'low_stock_threshold', 'image', 'status', 'created_at', 'updated_at'],
            'product_combos' => ['id', 'product_id', 'product_name', 'combo_product_id', 'combo_product_name', 'linked_variant_id', 'linked_variant_label', 'quantity', 'created_at'],
            'inventory_levels' => ['product_id', 'product_name', 'product_sku', 'variant_id', 'variant_sku', 'warehouse_id', 'warehouse_name', 'quantity'],
            'inventory_movements' => ['id', 'product_id', 'product_name', 'variant_id', 'warehouse', 'type', 'adjustment_number', 'quantity', 'unit_price', 'reference_type', 'reference_id', 'description', 'created_at'],
            'orders' => ['id', 'invoice_number', 'customer_name', 'first_name', 'last_name', 'phone', 'email', 'address', 'postal_code', 'city', 'township', 'order_status', 'payment_status', 'subtotal', 'discount_amount', 'delivery_fee', 'total_amount', 'paid_amount', 'payment_method', 'delivery_service', 'packaging', 'packaging_fee', 'cod_fee', 'delivery_days_min', 'delivery_days_max', 'promotion_code', 'notes', 'payment_verified_at', 'rejection_reason', 'stock_reduced', 'created_at', 'updated_at'],
            'order_items' => ['order_id', 'invoice_number', 'product_id', 'product_name', 'product_sku', 'variant_id', 'variant_label', 'flash_sale_id', 'quantity', 'price', 'original_price', 'subtotal', 'created_at'],
            'order_coupons' => ['order_id', 'invoice_number', 'coupon_id', 'coupon_name', 'code', 'type', 'discount_amount', 'created_at'],
            'order_override_logs' => ['id', 'order_id', 'invoice_number', 'user_id', 'user_type', 'field', 'old_value', 'new_value', 'reason', 'created_at'],
            'customers' => ['id', 'customer_type', 'name', 'email', 'phone', 'status', 'joined_at', 'orders_count', 'total_spent', 'last_order_at'],
            'customer_addresses' => ['id', 'user_id', 'user_type', 'label', 'first_name', 'last_name', 'phone', 'address_line', 'city', 'township', 'postal_code', 'is_default', 'notes', 'created_at', 'updated_at'],
            'wishlists' => ['id', 'user_id', 'user_type', 'product_id', 'product_name', 'created_at'],
            'categories' => ['id', 'name', 'slug', 'parent_id', 'parent', 'description', 'image', 'is_active', 'featured', 'sort_order', 'created_at', 'updated_at'],
            'brands' => ['id', 'name', 'slug', 'description', 'logo', 'banner', 'is_active', 'featured', 'sort_order', 'created_at', 'updated_at'],
            'units' => ['id', 'name', 'short_name', 'description', 'is_active', 'created_at', 'updated_at'],
            'coupons' => ['id', 'name', 'description', 'code', 'type', 'discount_value', 'min_order_amount', 'discount_cap', 'usage_limit', 'per_customer_limit', 'used_count', 'is_active', 'starts_at', 'expires_at', 'priority', 'is_stackable', 'created_at', 'updated_at'],
            'coupon_products' => ['coupon_id', 'coupon_name', 'product_id', 'product_name'],
            'coupon_categories' => ['coupon_id', 'coupon_name', 'category_id', 'category_name'],
            'promotions' => ['id', 'name', 'code', 'description', 'type', 'value', 'max_discount_amount', 'minimum_order_amount', 'per_customer_limit', 'applies_to', 'is_active', 'is_automatic', 'stackable', 'priority', 'starts_at', 'ends_at', 'usage_limit', 'usage_count', 'created_at', 'updated_at'],
            'promotion_products' => ['promotion_id', 'promotion_name', 'product_id', 'product_name'],
            'promotion_categories' => ['promotion_id', 'promotion_name', 'category_id', 'category_name'],
            'promotion_usages' => ['id', 'promotion_id', 'promotion_name', 'user_id', 'user_type', 'order_id', 'invoice_number', 'discount_amount', 'used_at'],
            'promotion_banners' => ['id', 'promotion_id', 'promotion_name', 'title', 'description', 'image', 'storefront_media_id', 'link', 'cta_label', 'is_active', 'starts_at', 'ends_at', 'position', 'desktop_visible', 'mobile_visible', 'created_at', 'updated_at'],
            'flash_sales' => ['id', 'name', 'description', 'discount_type', 'discount_value', 'max_discount_amount', 'starts_at', 'ends_at', 'is_active', 'priority', 'usage_limit', 'usage_count', 'created_by', 'created_by_type', 'created_at', 'updated_at'],
            'flash_sale_products' => ['id', 'flash_sale_id', 'flash_sale_name', 'product_id', 'product_name', 'variant_id', 'variant_sku', 'flash_price', 'quantity_limit', 'quantity_sold', 'created_at', 'updated_at'],
            'delivery_services' => ['id', 'name', 'code', 'description', 'base_fee', 'fee_per_kg', 'min_days', 'max_days', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            'delivery_pricing' => ['id', 'delivery_service_id', 'delivery_service_name', 'township_id', 'township_name', 'min_days', 'max_days', 'is_active', 'created_at', 'updated_at'],
            'cod_rules' => ['id', 'name', 'is_active', 'min_order_amount', 'max_order_amount', 'allowed_city_ids', 'excluded_city_ids', 'created_at', 'updated_at'],
            'packaging_options' => ['id', 'name', 'code', 'description', 'fee', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            'warehouses' => ['id', 'name', 'code', 'description', 'address', 'is_default', 'is_active', 'created_at', 'updated_at'],
            'payment_methods' => ['id', 'name', 'display_name', 'slug', 'type', 'account_name', 'account_number', 'bank_name', 'branch', 'instructions', 'currency', 'qr_image', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            'cities' => ['id', 'name', 'is_active', 'created_at', 'updated_at'],
            'townships' => ['id', 'city_id', 'city_name', 'name', 'postal_code', 'delivery_fee', 'is_active', 'created_at', 'updated_at'],
            'storefronts' => ['id', 'theme_id', 'theme_name', 'theme_slug', 'status', 'draft_revision_id', 'published_revision_id', 'created_at', 'updated_at'],
            'storefront_contents' => ['id', 'storefront_id', 'labels', 'created_at', 'updated_at'],
            'storefront_design_tokens' => ['id', 'storefront_id', 'tokens', 'created_at', 'updated_at'],
            'storefront_homepage_sections' => ['id', 'storefront_id', 'type', 'variant', 'enabled', 'desktop_visible', 'mobile_visible', 'position', 'configuration', 'created_at', 'updated_at'],
            'storefront_media' => ['id', 'storefront_id', 'key', 'path', 'original_name', 'mime_type', 'size', 'alt_text', 'metadata', 'is_visible', 'created_at', 'updated_at'],
            'storefront_navigations' => ['id', 'storefront_id', 'settings', 'created_at', 'updated_at'],
            'storefront_navigation_items' => ['id', 'navigation_id', 'key', 'group', 'label', 'path', 'icon', 'enabled', 'position', 'created_at', 'updated_at'],
            'storefront_revisions' => ['id', 'storefront_id', 'revision_number', 'status', 'configuration', 'created_by_type', 'created_by_id', 'published_at', 'published_by_type', 'published_by_id', 'created_at', 'updated_at'],
            'storefront_theme_configs' => ['id', 'storefront_id', 'theme_id', 'configuration', 'created_at', 'updated_at'],
            'storefront_checkout_configs' => ['id', 'storefront_id', 'configuration', 'created_at', 'updated_at'],
            'storefront_product_display_configs' => ['id', 'storefront_id', 'configuration', 'created_at', 'updated_at'],
            'website_faqs' => ['id', 'category', 'question_en', 'question_my', 'answer_en', 'answer_my', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'store_settings' => ['key', 'value'],
            'settings' => ['key', 'value', 'updated_at'],
            'import_histories' => ['id', 'user_id', 'file_name', 'file_type', 'import_type', 'status', 'import_mode', 'total_rows', 'total_products', 'total_variants', 'products_created', 'products_skipped', 'variants_created', 'warning_count', 'error_count', 'errors', 'warnings', 'error_report_path', 'duration_ms', 'created_at'],
            default => [],
        };
    }

    /**
     * Yield each dataset row as a native-value array in heading order.
     *
     * @return \Generator<int, array<int, mixed>>
     */
    public function rowsFor(string $category, int $tenantId): \Generator
    {
        $rows = match ($category) {
            'products' => $this->productRows($tenantId),
            'product_variants' => $this->productVariantRows($tenantId),
            'product_combos' => $this->productComboRows($tenantId),
            'inventory_levels' => $this->inventoryLevelRows($tenantId),
            'inventory_movements' => $this->inventoryMovementRows($tenantId),
            'orders' => $this->orderRows($tenantId),
            'order_items' => $this->orderItemRows($tenantId),
            'order_coupons' => $this->orderCouponRows($tenantId),
            'order_override_logs' => $this->orderOverrideRows($tenantId),
            'customers' => $this->customerRows($tenantId),
            'customer_addresses' => $this->customerAddressRows($tenantId),
            'wishlists' => $this->wishlistRows($tenantId),
            'categories' => $this->categoryRows($tenantId),
            'brands' => $this->brandRows($tenantId),
            'units' => $this->unitRows($tenantId),
            'coupons' => $this->couponRows($tenantId),
            'coupon_products' => $this->couponProductRows($tenantId),
            'coupon_categories' => $this->couponCategoryRows($tenantId),
            'promotions' => $this->promotionRows($tenantId),
            'promotion_products' => $this->promotionProductRows($tenantId),
            'promotion_categories' => $this->promotionCategoryRows($tenantId),
            'promotion_usages' => $this->promotionUsageRows($tenantId),
            'promotion_banners' => $this->promotionBannerRows($tenantId),
            'flash_sales' => $this->flashSaleRows($tenantId),
            'flash_sale_products' => $this->flashSaleProductRows($tenantId),
            'delivery_services' => $this->deliveryServiceRows($tenantId),
            'delivery_pricing' => $this->deliveryPricingRows($tenantId),
            'cod_rules' => $this->codRuleRows($tenantId),
            'packaging_options' => $this->packagingOptionRows($tenantId),
            'warehouses' => $this->warehouseRows($tenantId),
            'payment_methods' => $this->paymentMethodRows($tenantId),
            'cities' => $this->cityRows($tenantId),
            'townships' => $this->townshipRows($tenantId),
            'storefronts' => $this->storefrontRows($tenantId),
            'storefront_contents' => $this->storefrontContentRows($tenantId),
            'storefront_design_tokens' => $this->storefrontDesignTokenRows($tenantId),
            'storefront_homepage_sections' => $this->storefrontHomepageSectionRows($tenantId),
            'storefront_media' => $this->storefrontMediaRows($tenantId),
            'storefront_navigations' => $this->storefrontNavigationRows($tenantId),
            'storefront_navigation_items' => $this->storefrontNavigationItemRows($tenantId),
            'storefront_revisions' => $this->storefrontRevisionRows($tenantId),
            'storefront_theme_configs' => $this->storefrontThemeConfigRows($tenantId),
            'storefront_checkout_configs' => $this->storefrontCheckoutConfigRows($tenantId),
            'storefront_product_display_configs' => $this->storefrontProductDisplayConfigRows($tenantId),
            'website_faqs' => $this->websiteFaqRows($tenantId),
            'store_settings' => $this->storeSettingRows($tenantId),
            'settings' => $this->settingRows($tenantId),
            'import_histories' => $this->importHistoryRows($tenantId),
            default => [],
        };

        yield from $rows;
    }

    /* ── Single-dataset downloads ── */

    public function downloadCategory(string $category, Tenant $tenant, string $format): StreamedResponse|BinaryFileResponse
    {
        $headings = $this->headingsFor($category);
        $filename = $this->baseFilename($tenant, $category);
        $rows = $this->rowsFor($category, $tenant->id);

        return match ($format) {
            self::FORMAT_JSON => $this->streamJson($headings, $rows, $filename),
            self::FORMAT_XLSX => Excel::download(
                new MerchantDatasetExport($headings, $this->sanitizeRows($rows)),
                $filename . '.xlsx'
            ),
            default => $this->streamCsv($headings, $rows, $filename),
        };
    }

    /* ── Full archive ── */

    public function downloadArchive(Tenant $tenant): BinaryFileResponse
    {
        $this->cleanupExpired($tenant->id);

        $dir = $this->absoluteTempDir($tenant->id);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create the export directory.');
        }

        $runId = Str::lower(Str::random(12));
        $zipPath = $dir . DIRECTORY_SEPARATOR . $this->baseFilename($tenant, 'store-data') . '-' . $runId . '.zip';
        $zip = new ZipArchive();

        $assetManifest = $this->assetManifest($tenant->id);

        $manifest = [
            'schema_version' => 1,
            'archive_version' => 1,
            'store' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'generated_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'categories' => [],
            'files' => [],
            'asset_summary' => $assetManifest['summary'],
            'notes' => [
                'Image and media binaries are not embedded; assets.json verifies local files with size and SHA-256 and flags missing, external, unverified or invalid paths.',
                'CSV cells that begin with =, +, - or @ are prefixed with a single quote to prevent formula injection.',
                'Every dataset is generated from tenant-scoped queries; platform billing, identity, security and audit records are intentionally excluded.',
            ],
        ];

        $tempFiles = [];
        $zipOpened = false;

        try {
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create the export archive.');
            }

            $zipOpened = true;

            $tempFiles = $this->addCategoryEntries($zip, $tenant, $dir, $runId, $manifest);
            $this->addAssetEntry($zip, $manifest, $assetManifest);
            $zip->addFromString('manifest.json', $this->encodeJson($manifest));

            if (!$zip->close()) {
                throw new \RuntimeException('Unable to finalize the export archive.');
            }

            $zipOpened = false;
        } catch (\Throwable $e) {
            if ($zipOpened) {
                @$zip->close();
            }

            foreach ($tempFiles as $file) {
                @unlink($file);
            }

            @unlink($zipPath);

            throw $e;
        }

        foreach ($tempFiles as $file) {
            @unlink($file);
        }

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

    /**
     * Build the hardened, signed merchant backup. Reuses every dataset from
     * the existing export engine but emits a version 2 manifest with store
     * identity, creator, schema fingerprint, record counts, explicit
     * exclusions and an HMAC-SHA256 signature. Fails closed when signing is
     * not configured securely.
     */
    public function downloadBackupArchive(Tenant $tenant, ?int $createdBy = null, ?MerchantBackupSigner $signer = null): BinaryFileResponse
    {
        $signer ??= MerchantBackupSigner::fromConfig();

        if (! $signer->isConfigured()) {
            throw new \RuntimeException('Merchant backup signing is not configured securely.');
        }

        $this->cleanupExpired($tenant->id, (int) config('exports.backup.temp_ttl_hours', 2), 'backup-');

        $dir = $this->absoluteTempDir($tenant->id);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create the backup directory.');
        }

        @chmod($dir, 0700);

        $archiveId = (string) Str::uuid();
        $zipPath = $dir . DIRECTORY_SEPARATOR . 'backup-' . $archiveId . '.zip';
        $zip = new ZipArchive();

        $assetManifest = $this->assetManifest($tenant->id);

        $manifest = [
            'schema_version' => (int) config('exports.backup.schema_version', 2),
            'archive_version' => 2,
            'purpose' => 'merchant-backup',
            'archive_id' => $archiveId,
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
            ],
            'store' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'created_by' => $this->backupCreator($createdBy),
            'generated_at' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'version' => app()->version(),
                'environment' => app()->environment(),
            ],
            'schema' => $this->schemaFingerprint(),
            'categories' => [],
            'record_counts' => [],
            'total_records' => 0,
            'files' => [],
            'asset_summary' => $assetManifest['summary'],
            'media' => [
                'bundled' => false,
                'recoverable' => false,
                'note' => 'Image and media binaries are not bundled. assets.json lists metadata, size and SHA-256 for local files only, and flags missing, external or invalid paths.',
            ],
            'encryption' => [
                'encrypted' => false,
                'note' => 'This archive is signed for authenticity but is not encrypted. Store it privately.',
            ],
            'excluded' => $this->backupExclusions(),
            'excluded_settings' => $this->excludedSettingsSummary($tenant->id),
            'notes' => [
                'Signed with HMAC-SHA256 over the canonical manifest. The signing key is never included in the archive.',
                'CSV cells that begin with =, +, - or @ are prefixed with a single quote to prevent formula injection.',
                'Every dataset is generated from tenant-scoped queries; platform billing, identity, security and audit records are intentionally excluded.',
                'Recovery is not available in this release; a future recovery will only target a new inactive store.',
            ],
        ];

        $tempFiles = [];
        $zipOpened = false;

        try {
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create the backup archive.');
            }

            $zipOpened = true;

            $tempFiles = $this->addCategoryEntries(
                $zip,
                $tenant,
                $dir,
                'backup-' . $archiveId,
                $manifest,
                fn (string $category) => $this->backupRowsFor($category, $tenant->id)
            );

            $this->addAssetEntry($zip, $manifest, $assetManifest);

            $manifest['record_counts'] = $manifest['categories'];
            $manifest['total_records'] = array_sum($manifest['categories']);
            $manifest['signature'] = $signer->sign($manifest);

            if (!$signer->verify($manifest)) {
                throw new \RuntimeException('Unable to verify the generated backup signature.');
            }

            $zip->addFromString('manifest.json', $this->encodeJson($manifest));

            if (!$zip->close()) {
                throw new \RuntimeException('Unable to finalize the backup archive.');
            }

            $zipOpened = false;
        } catch (\Throwable $e) {
            if ($zipOpened) {
                @$zip->close();
            }

            foreach ($tempFiles as $file) {
                @unlink($file);
            }

            @unlink($zipPath);

            throw $e;
        }

        foreach ($tempFiles as $file) {
            @unlink($file);
        }

        @chmod($zipPath, 0600);

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

    /**
     * Areas intentionally excluded from a merchant backup, for UI display.
     */
    public function backupExclusions(): array
    {
        return self::BACKUP_EXCLUSIONS;
    }

    /**
     * Remove archives left behind by interrupted downloads once they are stale.
     */
    public function cleanupExpired(int $tenantId, ?int $ttlHours = null, ?string $prefix = null): void
    {
        $dir = $this->absoluteTempDir($tenantId);
        if (!is_dir($dir)) {
            return;
        }

        $ttl = $ttlHours ?? (int) config('exports.temp_ttl_hours', 24);
        $threshold = time() - ($ttl * 3600);

        foreach (glob($dir . DIRECTORY_SEPARATOR . ($prefix ?? '') . '*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) !== false && filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    /* ── Archive helpers ── */

    private function addCategoryEntries(ZipArchive $zip, Tenant $tenant, string $dir, string $runId, array &$manifest, ?callable $rowsResolver = null): array
    {
        $tempFiles = [];

        foreach (array_keys($this->categories()) as $category) {
            $headings = $this->headingsFor($category);
            $rows = $rowsResolver ? $rowsResolver($category) : $this->rowsFor($category, $tenant->id);
            $csvPath = $dir . DIRECTORY_SEPARATOR . $category . '-' . $runId . '.csv';
            $count = $this->writeCsvFile($csvPath, $headings, $rows);

            $zip->addFile($csvPath, $category . '.csv');
            $manifest['categories'][$category] = $count;
            $manifest['files'][$category . '.csv'] = [
                'rows' => $count,
                'bytes' => filesize($csvPath) ?: 0,
                'sha256' => hash_file('sha256', $csvPath) ?: null,
            ];
            $tempFiles[] = $csvPath;
        }

        return $tempFiles;
    }

    private function addAssetEntry(ZipArchive $zip, array &$manifest, array $assetManifest): void
    {
        $assetJson = $this->encodeJson($assetManifest);

        $zip->addFromString('assets.json', $assetJson);
        $manifest['files']['assets.json'] = [
            'rows' => count($assetManifest['assets']),
            'bytes' => strlen($assetJson),
            'sha256' => hash('sha256', $assetJson),
        ];
    }

    private function encodeJson(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Unable to encode archive metadata.');
        }

        return $json;
    }

    private function backupRowsFor(string $category, int $tenantId): iterable
    {
        if ($category === 'settings') {
            return $this->backupSettingRows($tenantId);
        }

        return $this->rowsFor($category, $tenantId);
    }

    private function backupSettingRows(int $tenantId): \Generator
    {
        $query = Setting::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('key');

        foreach ($query->cursor() as $setting) {
            if (!$this->isBackupSafeSetting((string) $setting->key)) {
                continue;
            }

            yield [
                $setting->key,
                $setting->value,
                $this->formatDate($setting->updated_at),
            ];
        }
    }

    private function isBackupSafeSetting(string $key): bool
    {
        if (preg_match(self::SENSITIVE_SETTING_PATTERN, $key) === 1) {
            return false;
        }

        $allowlist = config('exports.backup.setting_key_allowlist', []);

        if (!is_array($allowlist)) {
            return false;
        }

        foreach ($allowlist as $allowed) {
            $allowed = (string) $allowed;

            if ($allowed === '') {
                continue;
            }

            if (str_ends_with($allowed, '.') || str_ends_with($allowed, '_')) {
                if (str_starts_with($key, $allowed)) {
                    return true;
                }

                continue;
            }

            if ($key === $allowed) {
                return true;
            }
        }

        return false;
    }

    private function excludedSettingsSummary(int $tenantId): array
    {
        $keys = Setting::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('key')
            ->pluck('key')
            ->map(fn ($key) => (string) $key)
            ->reject(fn (string $key) => $this->isBackupSafeSetting($key))
            ->values()
            ->all();

        return [
            'reason' => 'Only explicitly allowlisted, non-sensitive setting keys are exported.',
            'keys' => array_slice($keys, 0, 200),
            'total' => count($keys),
            'truncated' => count($keys) > 200,
        ];
    }

    private function backupCreator(?int $userId): array
    {
        if (!$userId) {
            return [
                'id' => null,
                'type' => 'system',
                'name' => null,
            ];
        }

        $user = User::find($userId);

        return [
            'id' => $userId,
            'type' => 'user',
            'name' => $user?->name,
        ];
    }

    private function schemaFingerprint(): array
    {
        try {
            $count = DB::table('migrations')->count();
            $latest = DB::table('migrations')->orderByDesc('id')->value('migration');
        } catch (\Throwable) {
            $count = null;
            $latest = null;
        }

        return [
            'migrations_applied' => $count,
            'latest_migration' => is_string($latest) ? $latest : null,
        ];
    }

    /* ── Row generators ── */

    private function productRows(int $tenantId): \Generator
    {
        $query = Product::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['category:id,name', 'brand:id,name', 'unit:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $product) {
            yield [
                $product->id,
                $product->name,
                $product->slug,
                $product->sku,
                $product->barcode,
                $product->type,
                $product->status,
                $product->category?->name,
                $product->brand?->name,
                $product->unit?->name,
                $product->price,
                $product->sale_price,
                $product->base_price,
                $product->cost_price,
                $product->stock,
                $product->low_stock_alert,
                (bool) $product->featured,
                $product->sort_order,
                $product->short_description,
                $product->description,
                $product->seo_title,
                $product->seo_description,
                $product->seo_keywords,
                $product->photo1,
                $product->photo2,
                $product->gallery_images,
                $product->seo_image,
                $this->formatDate($product->created_at),
                $this->formatDate($product->updated_at),
            ];
        }
    }

    private function productVariantRows(int $tenantId): \Generator
    {
        $query = ProductVariant::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name,sku'])
            ->orderBy('id');

        foreach ($query->cursor() as $variant) {
            yield [
                $variant->id,
                $variant->product_id,
                $variant->product?->name,
                $variant->product?->sku,
                $variant->sku,
                $variant->barcode,
                $variant->attributes,
                $variant->price,
                $variant->compare_price,
                $variant->cost_price,
                $variant->stock,
                $variant->low_stock_threshold,
                $variant->image,
                $variant->status,
                $this->formatDate($variant->created_at),
                $this->formatDate($variant->updated_at),
            ];
        }
    }

    private function productComboRows(int $tenantId): \Generator
    {
        $query = ProductCombo::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name', 'comboProduct:id,name', 'linkedVariant:id,sku,attributes'])
            ->orderBy('id');

        foreach ($query->cursor() as $combo) {
            yield [
                $combo->id,
                $combo->product_id,
                $combo->product?->name,
                $combo->combo_product_id,
                $combo->comboProduct?->name,
                $combo->linked_variant_id,
                $combo->linkedVariant?->label,
                $combo->quantity,
                $this->formatDate($combo->created_at),
            ];
        }
    }

    private function inventoryLevelRows(int $tenantId): \Generator
    {
        $query = StockMovement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->selectRaw('product_id, product_variant_id, warehouse_id, SUM(quantity) as quantity')
            ->groupBy('product_id', 'product_variant_id', 'warehouse_id')
            ->with(['product:id,name,sku', 'variant:id,sku', 'warehouse:id,name'])
            ->orderBy('product_id')
            ->orderBy('product_variant_id')
            ->orderBy('warehouse_id');

        foreach ($query->cursor() as $level) {
            yield [
                $level->product_id,
                $level->product?->name,
                $level->product?->sku,
                $level->product_variant_id,
                $level->variant?->sku,
                $level->warehouse_id,
                $level->warehouse?->name,
                $level->quantity,
            ];
        }
    }

    private function inventoryMovementRows(int $tenantId): \Generator
    {
        $query = StockMovement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name', 'warehouse:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $movement) {
            yield [
                $movement->id,
                $movement->product_id,
                $movement->product?->name,
                $movement->product_variant_id,
                $movement->warehouse?->name,
                $movement->type,
                $movement->adjustment_number,
                $movement->quantity,
                $movement->unit_price,
                $movement->reference_type,
                $movement->reference_id,
                $movement->description,
                $this->formatDate($movement->created_at),
            ];
        }
    }

    private function orderRows(int $tenantId): \Generator
    {
        $query = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with([
                'city:id,name',
                'township:id,name',
                'paymentMethod:id,name',
                'deliveryService:id,name',
                'packaging:id,name',
            ])
            ->orderBy('id');

        foreach ($query->cursor() as $order) {
            yield [
                $order->id,
                $order->invoice_number,
                $order->customer_name,
                $order->first_name,
                $order->last_name,
                $order->phone,
                $order->email,
                $order->address,
                $order->postal_code,
                $order->city?->name,
                $order->township?->name,
                $order->order_status,
                $order->payment_status,
                $order->subtotal,
                $order->discount_amount,
                $order->delivery_fee,
                $order->total_amount,
                $order->paid_amount,
                $order->paymentMethod?->name,
                $order->deliveryService?->name,
                $order->packaging?->name,
                $order->packaging_fee,
                $order->cod_fee,
                $order->delivery_days_min,
                $order->delivery_days_max,
                $order->promotion_code,
                $order->notes,
                $this->formatDate($order->payment_verified_at),
                $order->rejection_reason,
                (bool) $order->stock_reduced,
                $this->formatDate($order->created_at),
                $this->formatDate($order->updated_at),
            ];
        }
    }

    private function orderItemRows(int $tenantId): \Generator
    {
        $query = OrderItem::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name,sku', 'variant:id,attributes', 'order:id,invoice_number'])
            ->orderBy('id');

        foreach ($query->cursor() as $item) {
            yield [
                $item->order_id,
                $item->order?->invoice_number,
                $item->product_id,
                $item->product?->name,
                $item->product?->sku,
                $item->variant_id,
                $item->variant?->label,
                $item->flash_sale_id,
                $item->quantity,
                $item->price,
                $item->original_price,
                (float) $item->price * (int) $item->quantity,
                $this->formatDate($item->created_at),
            ];
        }
    }

    private function orderCouponRows(int $tenantId): \Generator
    {
        $query = OrderCoupon::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['order:id,invoice_number', 'coupon:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $coupon) {
            yield [
                $coupon->order_id,
                $coupon->order?->invoice_number,
                $coupon->coupon_id,
                $coupon->coupon?->name,
                $coupon->code,
                $coupon->type,
                $coupon->discount_amount,
                $this->formatDate($coupon->created_at),
            ];
        }
    }

    private function orderOverrideRows(int $tenantId): \Generator
    {
        $query = OrderOverrideLog::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['order:id,invoice_number'])
            ->orderBy('id');

        foreach ($query->cursor() as $log) {
            yield [
                $log->id,
                $log->order_id,
                $log->order?->invoice_number,
                $log->user_id,
                $log->user_type,
                $log->field,
                $log->old_value,
                $log->new_value,
                $log->reason,
                $this->formatDate($log->created_at),
            ];
        }
    }

    private function customerRows(int $tenantId): \Generator
    {
        $useAccounts = $this->identityResolver->supportsAccount();
        $stats = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('user_id')
            ->selectRaw('user_type, user_id, COUNT(*) as orders_count, SUM(total_amount) as total_spent, MAX(created_at) as last_order_at')
            ->groupBy('user_type', 'user_id')
            ->get()
            ->keyBy(fn ($row) => $row->user_type . ':' . $row->user_id);

        $customers = $this->identityResolver->queryUsersForTenant($tenantId);

        if ($useAccounts) {
            $customers->whereHas('memberships', fn ($membership) => $membership
                ->where('tenant_id', $tenantId)
                ->whereHas('role', fn ($role) => $role->where('name', 'customer')))
                ->with(['memberships' => fn ($membership) => $membership
                    ->where('tenant_id', $tenantId)
                    ->with(['role', 'customerProfile'])]);
        } else {
            $customers->whereHas('roles', fn ($role) => $role->where('name', 'customer'));
        }

        $seenEmails = [];

        foreach ($customers->orderBy('id')->cursor() as $customer) {
            $membership = $useAccounts ? $customer->memberships->first() : null;
            $email = $customer->email;
            $name = $useAccounts
                ? ($membership?->customerProfile?->name ?: ($customer->name ?: $email))
                : ($customer->name ?: $email);
            $phone = $useAccounts ? $membership?->customerProfile?->phone : null;
            $stat = $stats->get($customer->getMorphClass() . ':' . $customer->id);

            if ($email) {
                $seenEmails[strtolower($email)] = true;
            }

            yield [
                $customer->id,
                $useAccounts ? 'account' : 'user',
                $name,
                $email,
                $phone,
                $membership?->status ?? $customer->status,
                $this->formatDate($membership?->joined_at ?? $customer->created_at),
                (int) ($stat?->orders_count ?? 0),
                $stat?->total_spent ?? 0,
                $this->formatDate($stat?->last_order_at),
            ];
        }

        $guestRows = Order::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->whereNull('user_id')
            ->selectRaw('customer_name, phone, email, COUNT(*) as orders_count, SUM(total_amount) as total_spent, MAX(created_at) as last_order_at')
            ->groupBy('customer_name', 'phone', 'email')
            ->orderBy('customer_name')
            ->get();

        foreach ($guestRows as $row) {
            if ($row->email && isset($seenEmails[strtolower($row->email)])) {
                continue;
            }

            yield [
                null,
                'guest',
                $row->customer_name,
                $row->email,
                $row->phone,
                null,
                null,
                (int) $row->orders_count,
                $row->total_spent,
                $this->formatDate($row->last_order_at),
            ];
        }
    }

    private function customerAddressRows(int $tenantId): \Generator
    {
        $query = CustomerAddress::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['city:id,name', 'township:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $address) {
            yield [
                $address->id,
                $address->user_id,
                $address->user_type,
                $address->label,
                $address->first_name,
                $address->last_name,
                $address->phone,
                $address->address_line,
                $address->city?->name,
                $address->township?->name,
                $address->postal_code,
                (bool) $address->is_default,
                $address->notes,
                $this->formatDate($address->created_at),
                $this->formatDate($address->updated_at),
            ];
        }
    }

    private function wishlistRows(int $tenantId): \Generator
    {
        $query = Wishlist::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['product:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $wishlist) {
            yield [
                $wishlist->id,
                $wishlist->user_id,
                $wishlist->user_type,
                $wishlist->product_id,
                $wishlist->product?->name,
                $this->formatDate($wishlist->created_at),
            ];
        }
    }

    private function categoryRows(int $tenantId): \Generator
    {
        $query = Category::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['parent:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $category) {
            yield [
                $category->id,
                $category->name,
                $category->slug,
                $category->parent_id,
                $category->parent?->name,
                $category->description,
                $category->image,
                (bool) $category->is_active,
                (bool) $category->featured,
                $category->sort_order,
                $this->formatDate($category->created_at),
                $this->formatDate($category->updated_at),
            ];
        }
    }

    private function brandRows(int $tenantId): \Generator
    {
        $query = Brand::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $brand) {
            yield [
                $brand->id,
                $brand->name,
                $brand->slug,
                $brand->description,
                $brand->logo,
                $brand->banner,
                (bool) $brand->is_active,
                (bool) $brand->featured,
                $brand->sort_order,
                $this->formatDate($brand->created_at),
                $this->formatDate($brand->updated_at),
            ];
        }
    }

    private function unitRows(int $tenantId): \Generator
    {
        $query = Unit::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $unit) {
            yield [
                $unit->id,
                $unit->name,
                $unit->short_name,
                $unit->description,
                (bool) $unit->is_active,
                $this->formatDate($unit->created_at),
                $this->formatDate($unit->updated_at),
            ];
        }
    }

    private function couponRows(int $tenantId): \Generator
    {
        $query = Coupon::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('id');

        foreach ($query->cursor() as $coupon) {
            yield [
                $coupon->id,
                $coupon->name,
                $coupon->description,
                $coupon->code,
                $coupon->type,
                $coupon->discount_value,
                $coupon->min_order_amount,
                $coupon->discount_cap,
                $coupon->usage_limit,
                $coupon->per_customer_limit,
                $coupon->used_count,
                (bool) $coupon->is_active,
                $this->formatDate($coupon->starts_at),
                $this->formatDate($coupon->expires_at),
                $coupon->priority,
                (bool) $coupon->is_stackable,
                $this->formatDate($coupon->created_at),
                $this->formatDate($coupon->updated_at),
            ];
        }
    }

    private function couponProductRows(int $tenantId): \Generator
    {
        $rows = DB::table('coupon_product as cp')
            ->join('coupons as c', 'c.id', '=', 'cp.coupon_id')
            ->join('products as p', 'p.id', '=', 'cp.product_id')
            ->where('cp.tenant_id', $tenantId)
            ->where('c.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->select('cp.coupon_id', 'c.name as coupon_name', 'cp.product_id', 'p.name as product_name')
            ->orderBy('cp.coupon_id')
            ->orderBy('cp.product_id')
            ->cursor();

        foreach ($rows as $row) {
            yield [$row->coupon_id, $row->coupon_name, $row->product_id, $row->product_name];
        }
    }

    private function couponCategoryRows(int $tenantId): \Generator
    {
        $rows = DB::table('coupon_category as cc')
            ->join('coupons as c', 'c.id', '=', 'cc.coupon_id')
            ->join('categories as cat', 'cat.id', '=', 'cc.category_id')
            ->where('cc.tenant_id', $tenantId)
            ->where('c.tenant_id', $tenantId)
            ->where('cat.tenant_id', $tenantId)
            ->select('cc.coupon_id', 'c.name as coupon_name', 'cc.category_id', 'cat.name as category_name')
            ->orderBy('cc.coupon_id')
            ->orderBy('cc.category_id')
            ->cursor();

        foreach ($rows as $row) {
            yield [$row->coupon_id, $row->coupon_name, $row->category_id, $row->category_name];
        }
    }

    private function promotionRows(int $tenantId): \Generator
    {
        $query = Promotion::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $promotion) {
            yield [
                $promotion->id,
                $promotion->name,
                $promotion->code,
                $promotion->description,
                $promotion->type,
                $promotion->value,
                $promotion->max_discount_amount,
                $promotion->minimum_order_amount,
                $promotion->per_customer_limit,
                $promotion->applies_to,
                (bool) $promotion->is_active,
                (bool) $promotion->is_automatic,
                (bool) $promotion->stackable,
                $promotion->priority,
                $this->formatDate($promotion->starts_at),
                $this->formatDate($promotion->ends_at),
                $promotion->usage_limit,
                $promotion->usage_count,
                $this->formatDate($promotion->created_at),
                $this->formatDate($promotion->updated_at),
            ];
        }
    }

    private function promotionProductRows(int $tenantId): \Generator
    {
        $rows = DB::table('promotion_product as pp')
            ->join('promotions as promo', 'promo.id', '=', 'pp.promotion_id')
            ->join('products as p', 'p.id', '=', 'pp.product_id')
            ->where('pp.tenant_id', $tenantId)
            ->where('promo.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->select('pp.promotion_id', 'promo.name as promotion_name', 'pp.product_id', 'p.name as product_name')
            ->orderBy('pp.promotion_id')
            ->orderBy('pp.product_id')
            ->cursor();

        foreach ($rows as $row) {
            yield [$row->promotion_id, $row->promotion_name, $row->product_id, $row->product_name];
        }
    }

    private function promotionCategoryRows(int $tenantId): \Generator
    {
        $rows = DB::table('promotion_category as pc')
            ->join('promotions as promo', 'promo.id', '=', 'pc.promotion_id')
            ->join('categories as cat', 'cat.id', '=', 'pc.category_id')
            ->where('pc.tenant_id', $tenantId)
            ->where('promo.tenant_id', $tenantId)
            ->where('cat.tenant_id', $tenantId)
            ->select('pc.promotion_id', 'promo.name as promotion_name', 'pc.category_id', 'cat.name as category_name')
            ->orderBy('pc.promotion_id')
            ->orderBy('pc.category_id')
            ->cursor();

        foreach ($rows as $row) {
            yield [$row->promotion_id, $row->promotion_name, $row->category_id, $row->category_name];
        }
    }

    private function promotionUsageRows(int $tenantId): \Generator
    {
        $query = PromotionUsage::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['promotion:id,name', 'order:id,invoice_number'])
            ->orderBy('id');

        foreach ($query->cursor() as $usage) {
            yield [
                $usage->id,
                $usage->promotion_id,
                $usage->promotion?->name,
                $usage->user_id,
                $usage->user_type,
                $usage->order_id,
                $usage->order?->invoice_number,
                $usage->discount_amount,
                $this->formatDate($usage->used_at),
            ];
        }
    }

    private function promotionBannerRows(int $tenantId): \Generator
    {
        $query = PromotionBanner::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['promotion:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $banner) {
            yield [
                $banner->id,
                $banner->promotion_id,
                $banner->promotion?->name,
                $banner->title,
                $banner->description,
                $banner->image,
                $banner->storefront_media_id,
                $banner->link,
                $banner->cta_label,
                (bool) $banner->is_active,
                $this->formatDate($banner->starts_at),
                $this->formatDate($banner->ends_at),
                $banner->position,
                (bool) $banner->desktop_visible,
                (bool) $banner->mobile_visible,
                $this->formatDate($banner->created_at),
                $this->formatDate($banner->updated_at),
            ];
        }
    }

    private function flashSaleRows(int $tenantId): \Generator
    {
        $query = FlashSale::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('id');

        foreach ($query->cursor() as $sale) {
            yield [
                $sale->id,
                $sale->name,
                $sale->description,
                $sale->discount_type,
                $sale->discount_value,
                $sale->max_discount_amount,
                $this->formatDate($sale->starts_at),
                $this->formatDate($sale->ends_at),
                (bool) $sale->is_active,
                $sale->priority,
                $sale->usage_limit,
                $sale->usage_count,
                $sale->created_by,
                $sale->created_by_type,
                $this->formatDate($sale->created_at),
                $this->formatDate($sale->updated_at),
            ];
        }
    }

    private function flashSaleProductRows(int $tenantId): \Generator
    {
        $rows = DB::table('flash_sale_products as fsp')
            ->join('flash_sales as fs', 'fs.id', '=', 'fsp.flash_sale_id')
            ->join('products as p', 'p.id', '=', 'fsp.product_id')
            ->leftJoin('product_variants as pv', function ($join) use ($tenantId) {
                $join->on('pv.id', '=', 'fsp.variant_id')
                    ->where('pv.tenant_id', $tenantId);
            })
            ->where('fs.tenant_id', $tenantId)
            ->where('p.tenant_id', $tenantId)
            ->select(
                'fsp.id', 'fsp.flash_sale_id', 'fs.name as flash_sale_name',
                'fsp.product_id', 'p.name as product_name', 'fsp.variant_id', 'pv.sku as variant_sku',
                'fsp.flash_price', 'fsp.quantity_limit', 'fsp.quantity_sold',
                'fsp.created_at', 'fsp.updated_at'
            )
            ->orderBy('fsp.id')
            ->cursor();

        foreach ($rows as $row) {
            yield [
                $row->id,
                $row->flash_sale_id,
                $row->flash_sale_name,
                $row->product_id,
                $row->product_name,
                $row->variant_id,
                $row->variant_sku,
                $row->flash_price,
                $row->quantity_limit,
                $row->quantity_sold,
                $this->formatDate($row->created_at),
                $this->formatDate($row->updated_at),
            ];
        }
    }

    private function deliveryServiceRows(int $tenantId): \Generator
    {
        $query = DeliveryService::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $service) {
            yield [
                $service->id,
                $service->name,
                $service->code,
                $service->description,
                $service->base_fee,
                $service->fee_per_kg,
                $service->min_days,
                $service->max_days,
                (bool) $service->is_active,
                $service->sort_order,
                $this->formatDate($service->created_at),
                $this->formatDate($service->updated_at),
            ];
        }
    }

    private function deliveryPricingRows(int $tenantId): \Generator
    {
        $rows = DB::table('delivery_pricing as dp')
            ->join('delivery_services as ds', 'ds.id', '=', 'dp.delivery_service_id')
            ->leftJoin('townships as t', function ($join) use ($tenantId) {
                $join->on('t.id', '=', 'dp.township_id')
                    ->where('t.tenant_id', $tenantId);
            })
            ->where('ds.tenant_id', $tenantId)
            ->select(
                'dp.id', 'dp.delivery_service_id', 'ds.name as delivery_service_name',
                'dp.township_id', 't.name as township_name', 'dp.min_days', 'dp.max_days',
                'dp.is_active', 'dp.created_at', 'dp.updated_at'
            )
            ->orderBy('dp.id')
            ->cursor();

        foreach ($rows as $row) {
            yield [
                $row->id,
                $row->delivery_service_id,
                $row->delivery_service_name,
                $row->township_id,
                $row->township_name,
                $row->min_days,
                $row->max_days,
                (bool) $row->is_active,
                $this->formatDate($row->created_at),
                $this->formatDate($row->updated_at),
            ];
        }
    }

    private function codRuleRows(int $tenantId): \Generator
    {
        $query = CodRule::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $rule) {
            yield [
                $rule->id,
                $rule->name,
                (bool) $rule->is_active,
                $rule->min_order_amount,
                $rule->max_order_amount,
                $rule->allowed_city_ids,
                $rule->excluded_city_ids,
                $this->formatDate($rule->created_at),
                $this->formatDate($rule->updated_at),
            ];
        }
    }

    private function packagingOptionRows(int $tenantId): \Generator
    {
        $query = PackagingOption::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $option) {
            yield [
                $option->id,
                $option->name,
                $option->code,
                $option->description,
                $option->fee,
                (bool) $option->is_active,
                $option->sort_order,
                $this->formatDate($option->created_at),
                $this->formatDate($option->updated_at),
            ];
        }
    }

    private function warehouseRows(int $tenantId): \Generator
    {
        $query = Warehouse::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $warehouse) {
            yield [
                $warehouse->id,
                $warehouse->name,
                $warehouse->code,
                $warehouse->description,
                $warehouse->address,
                (bool) $warehouse->is_default,
                (bool) $warehouse->is_active,
                $this->formatDate($warehouse->created_at),
                $this->formatDate($warehouse->updated_at),
            ];
        }
    }

    private function paymentMethodRows(int $tenantId): \Generator
    {
        $query = PaymentMethod::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('id');

        foreach ($query->cursor() as $method) {
            yield [
                $method->id,
                $method->name,
                $method->display_name,
                $method->slug,
                $method->type,
                $method->account_name,
                $method->account_number,
                $method->bank_name,
                $method->branch,
                $method->instructions,
                $method->currency,
                $method->qr_image,
                (bool) $method->is_active,
                $method->sort_order,
                $this->formatDate($method->created_at),
                $this->formatDate($method->updated_at),
            ];
        }
    }

    private function cityRows(int $tenantId): \Generator
    {
        $query = City::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $city) {
            yield [
                $city->id,
                $city->name,
                (bool) $city->is_active,
                $this->formatDate($city->created_at),
                $this->formatDate($city->updated_at),
            ];
        }
    }

    private function townshipRows(int $tenantId): \Generator
    {
        $query = Township::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['city:id,name'])
            ->orderBy('id');

        foreach ($query->cursor() as $township) {
            yield [
                $township->id,
                $township->city_id,
                $township->city?->name,
                $township->name,
                $township->postal_code,
                $township->delivery_fee,
                (bool) $township->is_active,
                $this->formatDate($township->created_at),
                $this->formatDate($township->updated_at),
            ];
        }
    }

    private function storefrontRows(int $tenantId): \Generator
    {
        $query = Storefront::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->with(['theme:id,name,slug'])
            ->orderBy('id');

        foreach ($query->cursor() as $storefront) {
            yield [
                $storefront->id,
                $storefront->theme_id,
                $storefront->theme?->name,
                $storefront->theme?->slug,
                $storefront->status,
                $storefront->draft_revision_id,
                $storefront->published_revision_id,
                $this->formatDate($storefront->created_at),
                $this->formatDate($storefront->updated_at),
            ];
        }
    }

    private function storefrontContentRows(int $tenantId): \Generator
    {
        $query = StorefrontContent::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $content) {
            yield [
                $content->id,
                $content->storefront_id,
                $content->labels,
                $this->formatDate($content->created_at),
                $this->formatDate($content->updated_at),
            ];
        }
    }

    private function storefrontDesignTokenRows(int $tenantId): \Generator
    {
        $query = StorefrontDesignToken::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $token) {
            yield [
                $token->id,
                $token->storefront_id,
                $token->tokens,
                $this->formatDate($token->created_at),
                $this->formatDate($token->updated_at),
            ];
        }
    }

    private function storefrontHomepageSectionRows(int $tenantId): \Generator
    {
        $query = StorefrontHomepageSection::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $section) {
            yield [
                $section->id,
                $section->storefront_id,
                $section->type,
                $section->variant,
                (bool) $section->enabled,
                (bool) $section->desktop_visible,
                (bool) $section->mobile_visible,
                $section->position,
                $section->configuration,
                $this->formatDate($section->created_at),
                $this->formatDate($section->updated_at),
            ];
        }
    }

    private function storefrontMediaRows(int $tenantId): \Generator
    {
        $query = StorefrontMedia::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $media) {
            yield [
                $media->id,
                $media->storefront_id,
                $media->key,
                $media->path,
                $media->original_name,
                $media->mime_type,
                $media->size,
                $media->alt_text,
                $media->metadata,
                (bool) $media->is_visible,
                $this->formatDate($media->created_at),
                $this->formatDate($media->updated_at),
            ];
        }
    }

    private function storefrontNavigationRows(int $tenantId): \Generator
    {
        $query = StorefrontNavigation::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $navigation) {
            yield [
                $navigation->id,
                $navigation->storefront_id,
                $navigation->settings,
                $this->formatDate($navigation->created_at),
                $this->formatDate($navigation->updated_at),
            ];
        }
    }

    private function storefrontNavigationItemRows(int $tenantId): \Generator
    {
        $query = StorefrontNavigationItem::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $item) {
            yield [
                $item->id,
                $item->navigation_id,
                $item->key,
                $item->group,
                $item->label,
                $item->path,
                $item->icon,
                (bool) $item->enabled,
                $item->position,
                $this->formatDate($item->created_at),
                $this->formatDate($item->updated_at),
            ];
        }
    }

    private function storefrontRevisionRows(int $tenantId): \Generator
    {
        $query = StorefrontRevision::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $revision) {
            yield [
                $revision->id,
                $revision->storefront_id,
                $revision->revision_number,
                $revision->status,
                $revision->configuration,
                $revision->created_by_type,
                $revision->created_by_id,
                $this->formatDate($revision->published_at),
                $revision->published_by_type,
                $revision->published_by_id,
                $this->formatDate($revision->created_at),
                $this->formatDate($revision->updated_at),
            ];
        }
    }

    private function storefrontThemeConfigRows(int $tenantId): \Generator
    {
        $query = StorefrontThemeConfig::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $config) {
            yield [
                $config->id,
                $config->storefront_id,
                $config->theme_id,
                $config->configuration,
                $this->formatDate($config->created_at),
                $this->formatDate($config->updated_at),
            ];
        }
    }

    private function storefrontCheckoutConfigRows(int $tenantId): \Generator
    {
        $query = StorefrontCheckoutConfig::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $config) {
            yield [
                $config->id,
                $config->storefront_id,
                $config->configuration,
                $this->formatDate($config->created_at),
                $this->formatDate($config->updated_at),
            ];
        }
    }

    private function storefrontProductDisplayConfigRows(int $tenantId): \Generator
    {
        $query = StorefrontProductDisplayConfig::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $config) {
            yield [
                $config->id,
                $config->storefront_id,
                $config->configuration,
                $this->formatDate($config->created_at),
                $this->formatDate($config->updated_at),
            ];
        }
    }

    private function websiteFaqRows(int $tenantId): \Generator
    {
        $query = WebsiteFaq::withoutTenantScope()->where('tenant_id', $tenantId)->orderBy('id');

        foreach ($query->cursor() as $faq) {
            yield [
                $faq->id,
                $faq->category,
                $faq->question_en,
                $faq->question_my,
                $faq->answer_en,
                $faq->answer_my,
                $faq->sort_order,
                (bool) $faq->is_active,
                $this->formatDate($faq->created_at),
                $this->formatDate($faq->updated_at),
            ];
        }
    }

    private function settingRows(int $tenantId): \Generator
    {
        $query = Setting::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('key');

        foreach ($query->cursor() as $setting) {
            if (preg_match(self::SENSITIVE_SETTING_PATTERN, (string) $setting->key) === 1) {
                continue;
            }

            yield [
                $setting->key,
                $setting->value,
                $this->formatDate($setting->updated_at),
            ];
        }
    }

    private function importHistoryRows(int $tenantId): \Generator
    {
        $query = ImportHistory::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->orderBy('id');

        foreach ($query->cursor() as $history) {
            yield [
                $history->id,
                $history->user_id,
                $history->file_name,
                $history->file_type,
                $history->import_type,
                $history->status,
                $history->import_mode,
                $history->total_rows,
                $history->total_products,
                $history->total_variants,
                $history->products_created,
                $history->products_skipped,
                $history->variants_created,
                $history->warning_count,
                $history->error_count,
                $history->errors,
                $history->warnings,
                $history->error_report_path,
                $history->duration_ms,
                $this->formatDate($history->created_at),
            ];
        }
    }

    private function storeSettingRows(int $tenantId): \Generator
    {
        $settings = WebsiteInfo::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->first();

        foreach (self::STORE_SETTING_KEYS as $key) {
            $value = $settings?->getAttribute($key);
            yield [
                $key,
                is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value,
            ];
        }
    }

    /* ── Asset manifest ── */

    private function assetManifest(int $tenantId): array
    {
        $assets = [];

        foreach (Product::withoutTenantScope()->where('tenant_id', $tenantId)->select('id', 'photo1', 'photo2', 'gallery_images', 'seo_image')->cursor() as $product) {
            $this->collectAsset($assets, 'product', $product->id, 'photo1', $product->photo1);
            $this->collectAsset($assets, 'product', $product->id, 'photo2', $product->photo2);
            foreach (is_array($product->gallery_images) ? $product->gallery_images : [] as $index => $path) {
                $this->collectAsset($assets, 'product', $product->id, 'gallery_images[' . $index . ']', $path);
            }
            $this->collectAsset($assets, 'product', $product->id, 'seo_image', $product->seo_image);
        }

        foreach (ProductVariant::withoutTenantScope()->where('tenant_id', $tenantId)->select('id', 'image')->cursor() as $variant) {
            $this->collectAsset($assets, 'product_variant', $variant->id, 'image', $variant->image);
        }

        foreach (Brand::withoutTenantScope()->where('tenant_id', $tenantId)->select('id', 'logo', 'banner')->cursor() as $brand) {
            $this->collectAsset($assets, 'brand', $brand->id, 'logo', $brand->logo);
            $this->collectAsset($assets, 'brand', $brand->id, 'banner', $brand->banner);
        }

        foreach (Category::withoutTenantScope()->where('tenant_id', $tenantId)->select('id', 'image')->cursor() as $category) {
            $this->collectAsset($assets, 'category', $category->id, 'image', $category->image);
        }

        foreach (PromotionBanner::withoutTenantScope()->where('tenant_id', $tenantId)->select('id', 'image')->cursor() as $banner) {
            $this->collectAsset($assets, 'promotion_banner', $banner->id, 'image', $banner->image);
        }

        foreach (PaymentMethod::withoutTenantScope()->where('tenant_id', $tenantId)->select('id', 'qr_image')->cursor() as $method) {
            $this->collectAsset($assets, 'payment_method', $method->id, 'qr_image', $method->qr_image);
        }

        foreach (WebsiteInfo::withoutTenantScope()->where('tenant_id', $tenantId)->cursor() as $info) {
            foreach (['logo', 'favicon', 'og_image', 'hero_image', 'footer_logo', 'about_image'] as $field) {
                $this->collectAsset($assets, 'website_info', $info->id, $field, $info->getAttribute($field));
            }
            foreach (is_array($info->hero_images) ? $info->hero_images : [] as $index => $path) {
                $this->collectAsset($assets, 'website_info', $info->id, 'hero_images[' . $index . ']', $path);
            }
        }

        foreach (StorefrontMedia::withoutTenantScope()->where('tenant_id', $tenantId)->select('id', 'path')->cursor() as $media) {
            $this->collectAsset($assets, 'storefront_media', $media->id, 'path', $media->path);
        }

        $summary = [
            'total' => count($assets),
            'present' => 0,
            'missing' => 0,
            'external' => 0,
            'unverified' => 0,
            'invalid' => 0,
        ];

        foreach ($assets as $asset) {
            if (isset($summary[$asset['status']])) {
                $summary[$asset['status']]++;
            }
        }

        return [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'disk' => config('filesystems.default', 'public'),
            'bundled' => false,
            'note' => 'Image and media binaries are not embedded. Local files are verified with size and SHA-256; remote or non-local assets are explicitly marked as external or unverified.',
            'summary' => $summary,
            'assets' => $assets,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     */
    private function collectAsset(array &$assets, string $type, int $id, string $field, mixed $path): void
    {
        if (!is_string($path) || trim($path) === '') {
            return;
        }

        $assets[] = $this->describeAsset($type, $id, $field, trim($path));
    }

    /**
     * @return array<string, mixed>
     */
    private function describeAsset(string $type, int $id, string $field, string $path): array
    {
        $base = [
            'type' => $type,
            'id' => $id,
            'field' => $field,
            'path' => $path,
        ];

        if (preg_match('#^https?://#i', $path) === 1) {
            return $base + ['status' => 'external', 'disk' => null, 'size' => null, 'sha256' => null];
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if (
            $normalized === ''
            || str_contains($normalized, '..')
            || str_starts_with($normalized, '\\')
            || preg_match('#^[A-Za-z]:#', $normalized) === 1
            || preg_match('#^[a-z][a-z0-9+.-]*://#i', $normalized) === 1
        ) {
            return $base + ['status' => 'invalid', 'disk' => null, 'size' => null, 'sha256' => null];
        }

        $disk = (string) config('filesystems.default', 'public');
        $driver = config("filesystems.disks.{$disk}.driver", 'local');

        if ($driver !== 'local') {
            return $base + ['status' => 'unverified', 'disk' => $disk, 'size' => null, 'sha256' => null];
        }

        $storage = Storage::disk($disk);

        if (!$storage->exists($normalized)) {
            return $base + ['status' => 'missing', 'disk' => $disk, 'size' => null, 'sha256' => null];
        }

        $absolute = $storage->path($normalized);
        $root = realpath($storage->path(''));
        $resolved = realpath($absolute);

        if ($root === false || $resolved === false || !$this->isPathWithin($resolved, $root)) {
            return $base + ['status' => 'invalid', 'disk' => $disk, 'size' => null, 'sha256' => null];
        }

        $size = @filesize($absolute);
        $sha256 = @hash_file('sha256', $absolute);

        return $base + [
            'status' => 'present',
            'disk' => $disk,
            'size' => $size === false ? null : $size,
            'sha256' => $sha256 === false ? null : $sha256,
        ];
    }

    private function isPathWithin(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /* ── Writers ── */

    private function streamCsv(array $headings, iterable $rows, string $filename): StreamedResponse
    {
        $callback = function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'sanitizeCell'], $headings));
            foreach ($this->sanitizeRows($rows) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function streamJson(array $headings, iterable $rows, string $filename): StreamedResponse
    {
        $callback = function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "[");
            $first = true;
            foreach ($rows as $row) {
                $assoc = [];
                foreach ($headings as $index => $heading) {
                    $assoc[$heading] = $row[$index] ?? null;
                }
                $encoded = json_encode($assoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                fwrite($out, ($first ? "\n  " : ",\n  ") . $encoded);
                $first = false;
            }
            fwrite($out, "\n]\n");
            fclose($out);
        };

        return response()->streamDownload($callback, $filename . '.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    private function writeCsvFile(string $path, array $headings, iterable $rows): int
    {
        $out = fopen($path, 'w');

        if ($out === false) {
            throw new \RuntimeException('Unable to create the export data file.');
        }

        try {
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'sanitizeCell'], $headings));

            $count = 0;
            foreach ($this->sanitizeRows($rows) as $row) {
                fputcsv($out, $row);
                $count++;
            }

            return $count;
        } finally {
            fclose($out);
        }
    }

    private function sanitizeRows(iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            yield array_map([self::class, 'sanitizeCell'], $row);
        }
    }

    /**
     * Neutralise spreadsheet formula injection and stringify values for
     * CSV/XLSX output. Numeric values are preserved so negative numbers
     * are not mangled.
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $value = (string) $value;

        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    /* ── Helpers ── */

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    private function baseFilename(Tenant $tenant, string $label): string
    {
        $slug = Str::slug($tenant->slug ?: $tenant->name) ?: 'store';

        return sprintf('%s-%s-%s', $slug, $label, now()->format('Ymd-His'));
    }

    private function tempDir(int $tenantId): string
    {
        return trim((string) config('exports.path', 'exports'), '/') . '/' . $tenantId;
    }

    private function absoluteTempDir(int $tenantId): string
    {
        return Storage::disk(config('exports.disk', 'local'))->path($this->tempDir($tenantId));
    }
}

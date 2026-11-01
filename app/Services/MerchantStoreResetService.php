<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\CodRule;
use App\Models\Coupon;
use App\Models\CustomerAddress;
use App\Models\DeliveryPricing;
use App\Models\DeliveryService;
use App\Models\FlashSale;
use App\Models\FlashSaleProduct;
use App\Models\ImportHistory;
use App\Models\Inventory;
use App\Models\Message;
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
use App\Models\PromotionCategory;
use App\Models\PromotionProduct;
use App\Models\PromotionUsage;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Township;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebsiteFaq;
use App\Models\Wishlist;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantStoreResetService
{
    public function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function reset(Tenant $tenant, $actor, string $confirmation, bool $finalConfirmation = false, ?string $password = null): array
    {
        return $this->execute($tenant, $actor, $confirmation, $finalConfirmation, $password);
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(Tenant $tenant, $actor, string $confirmation, bool $finalConfirmation = false, ?string $password = null): array
    {
        $operationId = (string) Str::uuid();

        $confirmation = trim($confirmation);
        if ($confirmation !== (string) $tenant->name && $confirmation !== (string) $tenant->slug) {
            throw new MerchantStoreResetException('confirmation_mismatch', 'Type the exact store name or slug to confirm the reset.');
        }

        if (!$finalConfirmation) {
            throw new MerchantStoreResetException('confirmation_required', 'Final confirmation is required.');
        }

        if ($password !== null && $password !== '') {
            $hash = $actor->password ?? null;
            if (!$hash || !\Illuminate\Support\Facades\Hash::check($password, $hash)) {
                throw new MerchantStoreResetException('password_mismatch', 'Password confirmation failed.');
            }
        }

        $lockSeconds = max(30, (int) config('exports.backup.recovery_lock_seconds', 300));
        $lock = Cache::lock('merchant-store-reset:' . $tenant->id, $lockSeconds);

        if (!$lock->get()) {
            throw new MerchantStoreResetException('reset_in_progress', 'Another store reset is already running.');
        }

        $startedAt = now();

        try {
            $summary = DB::transaction(function () use ($tenant, $actor, $operationId, $startedAt) {
                $counts = [];

                // Orders & sales
                $counts['order_override_logs'] = $this->deleteModel(OrderOverrideLog::class, $tenant->id);
                $counts['order_coupons'] = $this->deleteModel(OrderCoupon::class, $tenant->id);
                $counts['order_items'] = $this->deleteModel(OrderItem::class, $tenant->id);
                $counts['orders'] = $this->deleteModel(Order::class, $tenant->id);

                // Flash sales
                $flashSaleIds = FlashSale::withoutTenantScope()->where('tenant_id', $tenant->id)->pluck('id');
                $counts['flash_sale_products'] = FlashSaleProduct::whereIn('flash_sale_id', $flashSaleIds)->delete();
                $counts['flash_sales'] = $this->deleteModel(FlashSale::class, $tenant->id);

                // Promotions
                $counts['promotion_usages'] = $this->deleteModel(PromotionUsage::class, $tenant->id);
                $counts['promotion_banners'] = $this->deleteModel(PromotionBanner::class, $tenant->id);
                $counts['promotion_products'] = $this->deletePivot('promotion_product', $tenant->id);
                $counts['promotion_categories'] = $this->deletePivot('promotion_category', $tenant->id);
                $counts['promotions'] = $this->deleteModel(Promotion::class, $tenant->id);

                // Coupons
                $counts['coupon_products'] = $this->deletePivot('coupon_product', $tenant->id);
                $counts['coupon_categories'] = $this->deletePivot('coupon_category', $tenant->id);
                $counts['coupons'] = $this->deleteModel(Coupon::class, $tenant->id);

                // Customers business data
                $counts['wishlists'] = $this->deleteModel(Wishlist::class, $tenant->id);
                $counts['customer_addresses'] = $this->deleteModel(CustomerAddress::class, $tenant->id);
                $counts['messages'] = $this->deleteModel(Message::class, $tenant->id);

                // Products & inventory
                $counts['product_combos'] = $this->deleteModel(ProductCombo::class, $tenant->id);
                $counts['product_variants'] = $this->deleteModel(ProductVariant::class, $tenant->id);
                $counts['stock_movements'] = $this->deleteModel(StockMovement::class, $tenant->id);
                $counts['inventories'] = $this->deleteModel(Inventory::class, $tenant->id);
                $counts['products'] = $this->deleteModel(Product::class, $tenant->id);

                // Catalogue
                Category::withoutTenantScope()->where('tenant_id', $tenant->id)->update(['parent_id' => null]);
                $counts['categories'] = $this->deleteModel(Category::class, $tenant->id);
                $counts['brands'] = $this->deleteModel(Brand::class, $tenant->id);
                $counts['units'] = $this->deleteModel(Unit::class, $tenant->id);

                // Delivery & payments config (store-level)
                $deliveryServiceIds = DeliveryService::withoutTenantScope()->where('tenant_id', $tenant->id)->pluck('id');
                $counts['delivery_pricing'] = DeliveryPricing::whereIn('delivery_service_id', $deliveryServiceIds)->delete();
                $counts['delivery_services'] = $this->deleteModel(DeliveryService::class, $tenant->id);
                $counts['cod_rules'] = $this->deleteModel(CodRule::class, $tenant->id);
                $counts['packaging_options'] = $this->deleteModel(PackagingOption::class, $tenant->id);
                $counts['warehouses'] = $this->deleteModel(Warehouse::class, $tenant->id);
                $counts['payment_methods'] = $this->deleteModel(PaymentMethod::class, $tenant->id);
                $counts['townships'] = $this->deleteModel(Township::class, $tenant->id);
                $counts['cities'] = $this->deleteModel(City::class, $tenant->id);

                // Storefront content (business content) - keep config? We'll delete FAQ only
                $counts['website_faqs'] = $this->deleteModel(WebsiteFaq::class, $tenant->id);

                // Operations history
                $counts['import_histories'] = $this->deleteModel(ImportHistory::class, $tenant->id);

                // Retained media count (storefront media preserved)
                $retainedMedia = DB::table('storefront_media')->where('tenant_id', $tenant->id)->count();

                // Audit log outside resettable datasets
                ActivityLogger::log(
                    'Merchant store data reset completed.',
                    'merchant_store_reset_completed',
                    $tenant,
                    [
                        'operation_id' => $operationId,
                        'started_at' => $startedAt->toIso8601String(),
                        'completed_at' => now()->toIso8601String(),
                        'deleted' => $counts,
                        'retained_media' => $retainedMedia,
                        'result' => 'success',
                    ],
                    'store_reset',
                    ActivityLog::CATEGORY_SYSTEM
                );

                return [
                    'operation_id' => $operationId,
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'started_at' => $startedAt->toIso8601String(),
                    'completed_at' => now()->toIso8601String(),
                    'deleted' => $counts,
                    'total_deleted' => array_sum($counts),
                    'retained_media' => $retainedMedia,
                    'result' => 'success',
                ];
            });

            return $summary;
        } catch (\Throwable $e) {
            try {
                ActivityLogger::log(
                    'Merchant store data reset failed.',
                    'merchant_store_reset_failed',
                    $tenant,
                    [
                        'operation_id' => $operationId,
                        'started_at' => $startedAt->toIso8601String(),
                        'failed_at' => now()->toIso8601String(),
                        'reason' => $e->getMessage(),
                        'result' => 'failure',
                    ],
                    'store_reset',
                    ActivityLog::CATEGORY_SYSTEM
                );
            } catch (\Throwable) {
            }

            throw new MerchantStoreResetException('reset_failed', 'Store reset failed: ' . $e->getMessage(), $e);
        } finally {
            if ($lock->isOwnedByCurrentProcess()) {
                $lock->release();
            }
        }
    }

    private function deleteModel(string $modelClass, int $tenantId): int
    {
        return $modelClass::withoutTenantScope()->where('tenant_id', $tenantId)->delete();
    }

    private function deletePivot(string $table, int $tenantId): int
    {
        return DB::table($table)->where('tenant_id', $tenantId)->delete();
    }

    private function actorId($actor): ?int
    {
        return is_object($actor) && method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null;
    }
}

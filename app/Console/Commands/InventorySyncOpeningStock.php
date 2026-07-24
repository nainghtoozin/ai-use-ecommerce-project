<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Console\Command;

class InventorySyncOpeningStock extends Command
{
    protected $signature = 'inventory:sync-opening-stock
        {--dry-run : Preview changes without applying them}';

    protected $description = 'Backfill opening stock movements for existing products that have stock but no movements';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        $products = Product::withoutTenantScope()
            ->with('variants')
            ->where('stock', '>', 0)
            ->orWhereHas('variants', fn($q) => $q->where('stock', '>', 0))
            ->get();

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        foreach ($products as $product) {
            $hasMovement = StockMovement::withoutTenantScope()
                ->where('product_id', $product->id)
                ->exists();

            if ($hasMovement) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($product->stock > 0) {
                if (!$dryRun) {
                    StockMovement::withoutTenantScope()->create([
                        'tenant_id' => $product->tenant_id,
                        'product_id' => $product->id,
                        'type' => StockMovement::TYPE_OPENING_STOCK,
                        'quantity' => $product->stock,
                        'description' => 'Backfilled opening stock for existing product.',
                    ]);
                }
                $created++;
            }

            foreach ($product->variants as $variant) {
                if ($variant->stock > 0) {
                    $hasVariantMovement = StockMovement::withoutTenantScope()
                        ->where('product_variant_id', $variant->id)
                        ->exists();

                    if (!$hasVariantMovement) {
                        if (!$dryRun) {
                            StockMovement::withoutTenantScope()->create([
                                'tenant_id' => $product->tenant_id,
                                'product_id' => $product->id,
                                'product_variant_id' => $variant->id,
                                'type' => StockMovement::TYPE_OPENING_STOCK,
                                'quantity' => $variant->stock,
                                'description' => 'Backfilled opening stock for existing variant.',
                            ]);
                        }
                        $created++;
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->table(
            ['Result', 'Count'],
            [
                ['Processed', $products->count()],
                [$dryRun ? 'Would create' : 'Created', $created],
                ['Skipped (already have movements)', $skipped],
            ]
        );

        return Command::SUCCESS;
    }
}

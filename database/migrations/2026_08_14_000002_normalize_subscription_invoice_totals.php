<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')->update([
            'subtotal' => DB::raw('COALESCE(subtotal, amount)'),
            'tax' => 0,
        ]);
        DB::table('invoices')->update(['total' => DB::raw('subtotal')]);

        DB::table('invoices')->whereNotNull('line_items')->orderBy('id')->chunkById(100, function ($invoices) {
            foreach ($invoices as $invoice) {
                $items = json_decode($invoice->line_items, true);
                if (!is_array($items)) {
                    continue;
                }

                $items = array_values(array_filter($items, fn ($item) => !str_starts_with(strtolower($item['description'] ?? ''), 'tax')));
                DB::table('invoices')->where('id', $invoice->id)->update(['line_items' => json_encode($items)]);
            }
        });
    }

    public function down(): void {}
};

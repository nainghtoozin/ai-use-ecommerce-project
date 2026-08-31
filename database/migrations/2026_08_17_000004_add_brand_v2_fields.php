<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('banner')->nullable()->after('logo');
            $table->boolean('featured')->default(false)->after('banner');
            $table->integer('sort_order')->unsigned()->default(0)->after('featured');
        });

        $this->backfillSortOrder();
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['banner', 'featured', 'sort_order']);
        });
    }

    private function backfillSortOrder(): void
    {
        $brands = DB::table('brands')
            ->whereNull('sort_order')
            ->orWhere('sort_order', 0)
            ->get(['id', 'tenant_id']);

        foreach ($brands as $index => $brand) {
            DB::table('brands')
                ->where('id', $brand->id)
                ->update(['sort_order' => $index + 1]);
        }
    }
};

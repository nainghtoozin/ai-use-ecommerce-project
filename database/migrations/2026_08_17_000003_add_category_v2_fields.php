<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('is_active');
            $table->boolean('featured')->default(false)->after('image');
            $table->integer('sort_order')->default(0)->unsigned()->after('featured');
        });

        $this->backfillSortOrder();
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image', 'featured', 'sort_order']);
        });
    }

    private function backfillSortOrder(): void
    {
        $categories = DB::table('categories')
            ->whereNull('sort_order')
            ->orWhere('sort_order', 0)
            ->get(['id', 'tenant_id']);

        foreach ($categories as $index => $category) {
            DB::table('categories')
                ->where('id', $category->id)
                ->update(['sort_order' => $index + 1]);
        }
    }
};

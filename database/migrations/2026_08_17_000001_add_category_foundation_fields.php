<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug', 255)->nullable()->after('description');
            $table->boolean('is_active')->default(true)->after('description');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['tenant_id', 'slug'], 'cat_tenant_slug_idx');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
        });

        $this->backfillSlugs();
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex('cat_tenant_slug_idx');
            $table->dropColumn(['slug', 'is_active', 'parent_id']);
        });
    }

    private function backfillSlugs(): void
    {
        $categories = DB::table('categories')->whereNull('slug')->get(['id', 'tenant_id', 'name']);

        foreach ($categories as $category) {
            $baseSlug = Str::slug($category->name);
            if (empty($baseSlug)) {
                $baseSlug = 'category-' . $category->id;
            }

            $slug = $baseSlug;
            $counter = 1;

            while (DB::table('categories')
                ->where('tenant_id', $category->tenant_id)
                ->where('slug', $slug)
                ->where('id', '!=', $category->id)
                ->exists()
            ) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            DB::table('categories')->where('id', $category->id)->update(['slug' => $slug]);
        }
    }
};

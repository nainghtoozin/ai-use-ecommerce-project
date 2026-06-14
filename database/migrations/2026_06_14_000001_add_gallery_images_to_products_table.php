<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('gallery_images')->nullable()->after('photo2');
        });

        DB::table('products')->whereNotNull('photo2')->orderBy('id')->each(function ($product) {
            DB::table('products')
                ->where('id', $product->id)
                ->update(['gallery_images' => json_encode([$product->photo2])]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('gallery_images');
        });
    }
};

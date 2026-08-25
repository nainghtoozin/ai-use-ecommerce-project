<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 20)->default('draft');
            $table->json('configuration')->nullable();
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_by_type')->nullable();
            $table->unsignedBigInteger('published_by_id')->nullable();
            $table->timestamps();
            $table->unique(['storefront_id', 'revision_number']);
            $table->index(['tenant_id', 'storefront_id', 'status']);
        });

        Schema::table('storefronts', function (Blueprint $table) {
            $table->foreignId('draft_revision_id')->nullable()->after('status')->constrained('storefront_revisions')->nullOnDelete();
            $table->foreignId('published_revision_id')->nullable()->after('draft_revision_id')->constrained('storefront_revisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('storefronts', function (Blueprint $table) {
            $table->dropForeign(['draft_revision_id']);
            $table->dropForeign(['published_revision_id']);
            $table->dropColumn(['draft_revision_id', 'published_revision_id']);
        });
        Schema::dropIfExists('storefront_revisions');
    }
};

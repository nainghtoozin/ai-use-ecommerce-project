<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('website_infos');

        Schema::create('website_infos', function (Blueprint $table) {
            $table->id();

            $table->string('site_name')->default('My E-Commerce Store');
            $table->string('site_tagline')->nullable();
            $table->text('site_description')->nullable();
            $table->string('site_keywords')->nullable();
            $table->string('theme_color')->default('#3B82F6');
            $table->string('default_language')->default('en');
            $table->string('timezone')->default('Asia/Yangon');
            $table->string('currency_code')->default('MMK');
            $table->string('currency_symbol')->default('K');
            $table->string('date_format')->default('Y-m-d');

            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('og_image')->nullable();

            $table->string('contact_email')->nullable();
            $table->string('support_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('google_maps_embed_url')->nullable();

            $table->string('about_title')->nullable();
            $table->text('about_description')->nullable();
            $table->string('mission_title')->nullable();
            $table->text('mission_description')->nullable();
            $table->string('vision_title')->nullable();
            $table->text('vision_description')->nullable();

            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('youtube_url')->nullable();

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots_meta')->default('index, follow');

            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_button_text')->nullable();
            $table->string('hero_button_link')->nullable();
            $table->string('hero_image')->nullable();

            $table->text('footer_description')->nullable();
            $table->string('footer_copyright')->nullable();

            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->boolean('allow_registration')->default(true);
            $table->boolean('enable_reviews')->default(true);
            $table->boolean('enable_wishlist')->default(true);
            $table->boolean('enable_compare')->default(true);
            $table->boolean('guest_checkout_enabled')->default(true);
            $table->boolean('cod_enabled')->default(true);
            $table->decimal('free_shipping_threshold', 12, 2)->default(50000);
            $table->decimal('default_shipping_fee', 12, 2)->default(2000);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_infos');
    }
};
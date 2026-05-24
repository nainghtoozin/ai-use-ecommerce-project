<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_name' => 'nullable|string|max:255',
            'site_tagline' => 'nullable|string|max:255',
            'site_description' => 'nullable|string|max:1000',
            'site_keywords' => 'nullable|string|max:500',
            'theme_color' => 'nullable|string|max:20',
            'default_language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:50',
            'currency_code' => 'nullable|string|max:10',
            'currency_symbol' => 'nullable|string|max:10',
            'date_format' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png,jpg,gif,svg,webp|max:512',
            'contact_email' => 'nullable|email|max:255',
            'support_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:50',
            'sales_email' => 'nullable|email|max:255',
            'whatsapp_number' => 'nullable|string|max:50',
            'telegram_username' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'address_line_1' => 'nullable|string|max:500',
            'address_line_2' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'google_maps_embed_url' => 'nullable|string|max:1000',
            'google_maps_link' => 'nullable|string|max:1000',
            'about_title' => 'nullable|string|max:255',
            'about_description' => 'nullable|string|max:5000',
            'mission_title' => 'nullable|string|max:255',
            'mission_description' => 'nullable|string|max:2000',
            'vision_title' => 'nullable|string|max:255',
            'vision_description' => 'nullable|string|max:2000',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'twitter_url' => 'nullable|url|max:255',
            'linkedin_url' => 'nullable|url|max:255',
            'youtube_url' => 'nullable|url|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|url|max:255',
            'robots_meta' => 'nullable|string|max:100',
            'og_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:500',
            'hero_button_text' => 'nullable|string|max:100',
            'hero_button_link' => 'nullable|string|max:255',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
            'hero_images' => 'nullable|array|max:5',
            'hero_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
            'hero_images_existing' => 'nullable|array|max:5',
            'footer_description' => 'nullable|string|max:1000',
            'footer_extra_text' => 'nullable|string|max:5000',
            'footer_copyright' => 'nullable|string|max:255',
            'maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:1000',
            'allow_registration' => 'nullable|boolean',
            'enable_reviews' => 'nullable|boolean',
            'enable_wishlist' => 'nullable|boolean',
            'enable_compare' => 'nullable|boolean',
            'guest_checkout_enabled' => 'nullable|boolean',
            'cod_enabled' => 'nullable|boolean',
            'free_shipping_threshold' => 'nullable|numeric|min:0',
            'default_shipping_fee' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'The logo must be an image file.',
            'logo.max' => 'The logo must not be larger than 2MB.',
            'favicon.image' => 'The favicon must be an image file.',
            'favicon.max' => 'The favicon must not be larger than 512KB.',
            'og_image.image' => 'The OG image must be an image file.',
            'og_image.max' => 'The OG image must not be larger than 2MB.',
            'hero_image.image' => 'The hero image must be an image file.',
            'hero_image.max' => 'The hero image must not be larger than 2MB.',
        ];
    }
}
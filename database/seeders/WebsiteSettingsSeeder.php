<?php

namespace Database\Seeders;

use App\Models\WebsiteInfo;
use Illuminate\Database\Seeder;

class WebsiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        WebsiteInfo::updateOrCreate(['id' => 1], [
            'site_name' => 'ShopMyanmar',
            'site_tagline' => 'Your Premier Online Shopping Destination',
            'site_description' => 'Discover amazing products at unbeatable prices. ShopMyanmar brings you the best selection of electronics, fashion, home goods and more.',
            'site_keywords' => 'ecommerce, online shopping, myanmar, electronics, fashion',
            'theme_color' => '#3B82F6',
            'default_language' => 'en',
            'timezone' => 'Asia/Yangon',
            'currency_code' => 'MMK',
            'currency_symbol' => 'K',
            'date_format' => 'Y-m-d',
            'contact_email' => 'contact@shopmyanmar.com',
            'support_email' => 'support@shopmyanmar.com',
            'phone' => '+95 9 123 456789',
            'whatsapp_number' => '+959123456789',
            'address' => '123 Merchant Street, Yangon, Myanmar',
            'country' => 'Myanmar',
            'google_maps_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3818.7892!2d96.1959!3d16.8669!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTbCsDUyJzAwLjEiTiA5NsKwMTEnNDMuNCJF!5e0!3m2!1sen!2smm!4v1700000000000!5m2!1sen!2smm',
            'contact_info' => json_encode([
                'primary_phone' => '+95 9 123 456789',
                'secondary_phone' => '',
                'support_email' => 'support@shopmyanmar.com',
                'sales_email' => '',
                'contact_email' => 'contact@shopmyanmar.com',
                'whatsapp_number' => '+959123456789',
                'telegram_username' => '',
            ]),
            'address_info' => json_encode([
                'address_line_1' => '123 Merchant Street, Yangon, Myanmar',
                'address_line_2' => '',
                'city' => 'Yangon',
                'state_region' => '',
                'postal_code' => '',
                'country' => 'Myanmar',
                'google_maps_link' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3818.7892!2d96.1959!3d16.8669!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTbCsDUyJzAwLjEiTiA5NsKwMTEnNDMuNCJF!5e0!3m2!1sen!2smm!4v1700000000000!5m2!1sen!2smm',
            ]),
            'about_title' => 'About ShopMyanmar',
            'about_description' => 'ShopMyanmar is your trusted online shopping platform in Myanmar. We are dedicated to providing a seamless shopping experience with quality products, competitive prices, and reliable delivery across the country.',
            'mission_title' => 'Our Mission',
            'mission_description' => 'To provide Myanmar consumers with access to quality products at affordable prices while supporting local businesses and creating employment opportunities.',
            'vision_title' => 'Our Vision',
            'vision_description' => 'To become the leading e-commerce platform in Myanmar, transforming the way people shop and connecting sellers with buyers across all regions.',
            'facebook_url' => 'https://facebook.com/shopmyanmar',
            'instagram_url' => 'https://instagram.com/shopmyanmar',
            'twitter_url' => 'https://twitter.com/shopmyanmar',
            'linkedin_url' => 'https://linkedin.com/company/shopmyanmar',
            'youtube_url' => 'https://youtube.com/@shopmyanmar',
            'meta_title' => 'ShopMyanmar - Your Premier Online Shopping Destination',
            'meta_description' => 'Discover amazing products at unbeatable prices. ShopMyanmar brings you the best selection online.',
            'meta_keywords' => 'ecommerce, online shopping, myanmar, electronics, fashion, buy online',
            'canonical_url' => 'https://shopmyanmar.com',
            'robots_meta' => 'index, follow',
            'hero_title' => 'Welcome to ShopMyanmar',
            'hero_subtitle' => 'Discover amazing products at unbeatable prices with fast delivery across Myanmar.',
            'hero_button_text' => 'Shop Now',
            'hero_button_link' => '/products',
            'footer_description' => 'Your trusted online shopping destination in Myanmar. Quality products, great prices, fast delivery.',
            'footer_copyright' => '2026 ShopMyanmar. All rights reserved.',
            'footer_settings' => json_encode([
                'description' => 'Your trusted online shopping destination in Myanmar. Quality products, great prices, fast delivery.',
                'extra_text' => 'ShopMyanmar was founded with a mission to bring the best online shopping experience to Myanmar. We partner with trusted local and international brands to offer you quality products at competitive prices. Our dedicated team works around the clock to ensure your orders are processed and delivered with care.',
                'show_contact_button' => true,
                'show_social_icons' => true,
                'compact_mode' => true,
            ]),
            'maintenance_mode' => false,
            'maintenance_message' => 'We are currently performing scheduled maintenance. Please check back soon.',
            'allow_registration' => true,
            'enable_reviews' => true,
            'enable_wishlist' => true,
            'enable_compare' => true,
            'guest_checkout_enabled' => true,
            'cod_enabled' => true,
            'free_shipping_threshold' => 0,
            'default_shipping_fee' => 0,
            'is_active' => true,
        ]);
    }
}
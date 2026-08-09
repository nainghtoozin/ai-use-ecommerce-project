<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\WebsiteFaq;
use App\Models\WebsiteInfo;
use Illuminate\Database\Seeder;

class TenantDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping tenant default seeding.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedPaymentMethods($tenant);
            $this->seedWebsiteInfo($tenant);
            $this->seedFaqs($tenant);
        }

        $this->command->info('Tenant defaults seeded successfully for all tenants.');
    }

    private function seedPaymentMethods(Tenant $tenant): void
    {
        $methods = [
            ['name' => 'Cash', 'type' => 'cash', 'is_active' => true],
            ['name' => 'Cash On Delivery', 'type' => 'cod', 'is_active' => true],
        ];

        foreach ($methods as $method) {
            PaymentMethod::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $method['name']],
                [
                    'type' => $method['type'],
                    'is_active' => $method['is_active'],
                ]
            );
        }
    }

    private function seedWebsiteInfo(Tenant $tenant): void
    {
        $existing = WebsiteInfo::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($existing) {
            return;
        }

        WebsiteInfo::withoutTenantScope(function () use ($tenant) {
            WebsiteInfo::create([
                'tenant_id' => $tenant->id,
                'site_name' => $tenant->name,
                'site_tagline' => 'Your Premier Online Shopping Destination',
                'site_description' => 'Discover amazing products at unbeatable prices. Shop the best selection of electronics, fashion, home goods and more.',
                'site_keywords' => 'ecommerce, online shopping, store',
                'theme_color' => '#3B82F6',
                'default_language' => 'en',
                'timezone' => 'Asia/Yangon',
                'currency_code' => 'MMK',
                'currency_symbol' => 'K',
                'date_format' => 'Y-m-d',
                'contact_email' => "contact@{$tenant->slug}.com",
                'support_email' => "support@{$tenant->slug}.com",
                'phone' => '',
                'whatsapp_number' => '',
                'address' => '',
                'country' => 'Myanmar',
                'about_title' => "About {$tenant->name}",
                'about_description' => "{$tenant->name} is your trusted online shopping platform. We are dedicated to providing a seamless shopping experience with quality products, competitive prices, and reliable delivery.",
                'mission_title' => 'Our Mission',
                'mission_description' => 'To provide customers with access to quality products at affordable prices while supporting local businesses.',
                'vision_title' => 'Our Vision',
                'vision_description' => 'To become the leading e-commerce platform, transforming the way people shop.',
                'meta_title' => "{$tenant->name} - Online Store",
                'meta_description' => "Shop at {$tenant->name}. Discover amazing products at unbeatable prices.",
                'meta_keywords' => 'ecommerce, online shopping, store',
                'hero_title' => "Welcome to {$tenant->name}",
                'hero_subtitle' => 'Discover amazing products at unbeatable prices with fast delivery.',
                'hero_button_text' => 'Shop Now',
                'hero_button_link' => '/products',
                'footer_description' => "Your trusted online shopping destination. Quality products, great prices, fast delivery.",
                'footer_copyright' => now()->year . " {$tenant->name}. All rights reserved.",
                'footer_settings' => json_encode([
                    'description' => "Your trusted online shopping destination. Quality products, great prices, fast delivery.",
                    'show_contact_button' => true,
                    'show_social_icons' => true,
                    'compact_mode' => true,
                ]),
                'contact_info' => json_encode([
                    'primary_phone' => '',
                    'secondary_phone' => '',
                    'support_email' => "support@{$tenant->slug}.com",
                    'sales_email' => '',
                    'contact_email' => "contact@{$tenant->slug}.com",
                    'whatsapp_number' => '',
                    'telegram_username' => '',
                ]),
                'address_info' => json_encode([
                    'address_line_1' => '',
                    'address_line_2' => '',
                    'city' => '',
                    'state_region' => '',
                    'postal_code' => '',
                    'country' => 'Myanmar',
                    'google_maps_link' => '',
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
        });
    }

    private function seedFaqs(Tenant $tenant): void
    {
        $existingCount = WebsiteFaq::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();

        if ($existingCount > 0) {
            return;
        }

        $faqs = [
            [
                'category' => 'getting_started',
                'question_en' => 'How do I place an order?',
                'question_my' => 'ကျွန်ုပ် ဘယ်လိုမှာယူရမလဲ?',
                'answer_en' => '<p>To place an order, browse our products, add items to your cart, and proceed to checkout. You can pay using various payment methods including cash on delivery, mobile banking, and bank transfer.</p>',
                'answer_my' => '<p>မှာယူရန် ကျွန်ုပ်တို့၏ ထုတ်ကုန်များကို ရှာဖွေပြီး သင့်စျေးခြင်းထဲသို့ ထည့်ပါ။ ထို့နောက် checkout သို့ သွားပါ။ ငွေပေးချေမှုနည်းလမ်းများစွာဖြင့် ပေးချေနိုင်ပါသည်။</p>',
                'sort_order' => 1,
            ],
            [
                'category' => 'billing',
                'question_en' => 'What payment methods do you accept?',
                'question_my' => 'ဘယ်လိုငွေပေးချေမှုနည်းလမ်းတွေကို လက်ခံပါသလဲ?',
                'answer_en' => '<p>We accept various payment methods including Cash on Delivery (COD), mobile banking (KBZPay, WavePay, AYA Pay), bank transfers, and more. Available payment methods may vary by store.</p>',
                'answer_my' => '<p>ကျွန်ုပ်တို့သည် ငွေသားဖြင့်ပေးချေမှု (COD)၊ မိုဘိုင်းဘဏ် (KBZPay, WavePay, AYA Pay)၊ ဘဏ်လွှဲပြောင်းမှု အပါအဝင် ငွေပေးချေမှုနည်းလမ်းများစွာကို လက်ခံပါသည်။</p>',
                'sort_order' => 2,
            ],
            [
                'category' => 'shipping',
                'question_en' => 'How long does shipping take?',
                'question_my' => 'ပို့ဆောင်မှု ဘယ်လောက်ကြာသလဲ?',
                'answer_en' => '<p>Shipping times vary depending on your location. Typically, orders are delivered within 2-5 business days for major cities and 5-10 business days for remote areas. You will receive tracking information once your order is shipped.</p>',
                'answer_my' => '<p>ပို့ဆောင်ချိန်သည် သင့်တည်နေရာပေါ်မူတည်ပါသည်။ ပုံမှန်အားဖြင့် အဓိကမြို့ကြီးများအတွက် 2-5 လုပ်ငန်းရက်အတွင်း ပို့ဆောင်ပေးပါသည်။</p>',
                'sort_order' => 3,
            ],
            [
                'category' => 'returns',
                'question_en' => 'What is your return policy?',
                'question_my' => 'သင်တို့၏ ပြန်ပေးမူဝါဒက ဘာလဲ?',
                'answer_en' => '<p>We offer a 7-day return policy for most items. Products must be in their original condition with all tags attached. To initiate a return, contact our support team with your order number.</p>',
                'answer_my' => '<p>ကျွန်ုပ်တို့သည် ပစ္စည်းအများစုအတွက် ရက် ၃၀ ပြန်ပေးမူဝါဒကို ပေးပါသည်။ ထုတ်ကုန်များသည် မူလအခြေအနေတွင် ရှိရပါမည်။</p>',
                'sort_order' => 4,
            ],
            [
                'category' => 'support',
                'question_en' => 'How can I contact customer support?',
                'question_my' => 'ဖောက်သည်ပံ့ပိုးမှုကို ဘယ်လိုဆက်သွယ်ရမလဲ?',
                'answer_en' => '<p>You can reach our customer support team through the Contact Us page on our website. We typically respond within 24 hours during business days.</p>',
                'answer_my' => '<p>ကျွန်ုပ်တို့၏ ဖောက်သည်ပံ့ပိုးမှုအဖွဲ့ကို ကျွန်ုပ်တို့၏ ဝက်ဘ်ဆိုက်ရှိ ဆက်သွယ်ရန် စာမျက်နှာမှတစ်ဆင့် ဆက်သွယ်နိုင်ပါသည်။</p>',
                'sort_order' => 5,
            ],
        ];

        foreach ($faqs as $faqData) {
            WebsiteFaq::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'question_en' => $faqData['question_en']],
                [
                    'category' => $faqData['category'],
                    'question_my' => $faqData['question_my'],
                    'answer_en' => $faqData['answer_en'],
                    'answer_my' => $faqData['answer_my'],
                    'sort_order' => $faqData['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}

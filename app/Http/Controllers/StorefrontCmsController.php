<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\WebsiteInfo;
use App\Services\WebsiteFaqService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontCmsController extends Controller
{
    public function __construct(
        private readonly WebsiteFaqService $faqService,
    ) {}

    public function about()
    {
        $tenant = $this->getTenant();
        $settings = $this->getSettings();

        return Inertia::render('Storefront/Cms/About', [
            'tenant' => $this->tenantData($tenant),
            'page' => [
                'title' => $settings->about_title ?? 'About Us',
                'description' => $settings->about_description,
                'mission_title' => $settings->mission_title,
                'mission_description' => $settings->mission_description,
                'vision_title' => $settings->vision_title,
                'vision_description' => $settings->vision_description,
            ],
        ]);
    }

    public function contact()
    {
        $tenant = $this->getTenant();
        $settings = $this->getSettings();
        $ci = $settings->contact_info ?? [];
        $ai = $settings->address_info ?? [];

        return Inertia::render('Storefront/Cms/Contact', [
            'tenant' => $this->tenantData($tenant),
            'contact' => [
                'email' => $ci['contact_email'] ?? $settings->contact_email,
                'support_email' => $ci['support_email'] ?? $settings->support_email,
                'sales_email' => $ci['sales_email'] ?? null,
                'phone' => $ci['primary_phone'] ?? $settings->phone,
                'secondary_phone' => $ci['secondary_phone'] ?? null,
                'whatsapp' => $ci['whatsapp_number'] ?? $settings->whatsapp_number,
                'telegram' => $ci['telegram_username'] ?? null,
                'address' => $ai['address_line_1'] ?? $settings->address,
                'address_line_2' => $ai['address_line_2'] ?? null,
                'city' => $ai['city'] ?? null,
                'state' => $ai['state_region'] ?? null,
                'postal_code' => $ai['postal_code'] ?? null,
                'country' => $ai['country'] ?? $settings->country,
                'google_maps_url' => $ai['google_maps_link'] ?? $settings->google_maps_embed_url,
            ],
        ]);
    }

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $settings = $this->getSettings();
        $toEmail = $settings->support_email ?? $settings->contact_email ?? config('mail.from.address');

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Name: {$validated['name']}\nEmail: {$validated['email']}\nSubject: {$validated['subject']}\n\nMessage:\n{$validated['message']}",
                function ($message) use ($toEmail, $validated) {
                    $message->to($toEmail)
                        ->subject('Contact Form: ' . $validated['subject'])
                        ->replyTo($validated['email'], $validated['name']);
                }
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Thank you! Your message has been sent.');
    }

    public function faq()
    {
        $tenant = $this->getTenant();
        $faqs = $this->faqService->getActiveForTenant($tenant->id);
        $categories = $this->faqService->getCategories();

        $faqCategories = $faqs->pluck('category')->unique()->filter()->values()->toArray();
        $availableCategories = array_intersect_key($categories, array_flip($faqCategories));

        return Inertia::render('Storefront/Cms/Faq', [
            'tenant' => $this->tenantData($tenant),
            'faqs' => $faqs->map(fn ($faq) => [
                'id' => $faq->id,
                'category' => $faq->category,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ]),
            'categories' => $availableCategories,
        ]);
    }

    public function privacyPolicy()
    {
        return $this->renderPolicyPage('privacy_policy', 'Privacy Policy');
    }

    public function termsConditions()
    {
        return $this->renderPolicyPage('terms_conditions', 'Terms & Conditions');
    }

    public function shippingPolicy()
    {
        return $this->renderPolicyPage('shipping_policy', 'Shipping Policy');
    }

    public function returnPolicy()
    {
        return $this->renderPolicyPage('return_policy', 'Return Policy');
    }

    public function refundPolicy()
    {
        return $this->renderPolicyPage('refund_policy', 'Refund Policy');
    }

    private function renderPolicyPage(string $field, string $defaultTitle)
    {
        $tenant = $this->getTenant();
        $settings = $this->getSettings();

        $content = $settings->{$field};

        return Inertia::render('Storefront/Cms/Policy', [
            'tenant' => $this->tenantData($tenant),
            'page' => [
                'title' => $defaultTitle,
                'content' => $content,
                'updated_at' => $settings->updated_at?->toISOString(),
            ],
        ]);
    }

    private function getTenant(): Tenant
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }
        return $tenant;
    }

    private function getSettings(): WebsiteInfo
    {
        return WebsiteInfo::getSettings();
    }

    private function tenantData(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'store_url' => $tenant->store_url,
            'logo' => $tenant->logo,
            'status' => $tenant->status,
        ];
    }
}

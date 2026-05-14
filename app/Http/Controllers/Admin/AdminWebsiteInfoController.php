<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteInfo;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminWebsiteInfoController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function edit()
    {
        $info = WebsiteInfo::first();

        return Inertia::render('Admin/WebsiteInfo/Edit', [
            'info' => $info,
        ]);
    }

    public function update(Request $request)
    {
        $info = WebsiteInfo::first() ?? new WebsiteInfo();

        $request->validate([
            'name' => 'nullable|string|max:255',
            'hero_title' => 'nullable|string|max:255',
            'hero_description' => 'nullable|string',
            'about_description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'facebook' => 'nullable|url|max:255',
            'twitter' => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
            'linkedin' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'shipping_info' => 'nullable|string',
            'secure_payment_info' => 'nullable|string',
            'easy_returns_info' => 'nullable|string',
            'currency' => 'nullable|string|max:10',
            'shipping_fee' => 'required|integer|min:0',
            'free_shipping_threshhold' => 'required|integer|min:0',
            'theme_fullname' => 'nullable|string|max:255',
            'about_us_title' => 'nullable|string|max:255',
            'about_us_description' => 'nullable|string',
            'contact_title' => 'nullable|string|max:255',
            'contact_description' => 'nullable|string',
            'faq_title' => 'nullable|string|max:255',
            'faq_description' => 'nullable|string',
            'privacy_policy_title' => 'nullable|string|max:255',
            'privacy_policy_description' => 'nullable|string',
            'terms_service_title' => 'nullable|string|max:255',
            'terms_service_description' => 'nullable|string',
        ]);

        if ($request->hasFile('logo')) {
            if ($info->logo) {
                $this->imageService->delete($info->logo);
            }
            $info->logo = $this->imageService->upload($request->file('logo'), 'website');
        }

        $generalFields = [
            'name', 'hero_title', 'hero_description', 'about_description',
            'phone', 'email', 'address', 'facebook', 'twitter', 'instagram', 'linkedin',
            'currency', 'shipping_fee', 'free_shipping_threshhold',
            'theme_fullname',
            'shipping_info', 'secure_payment_info', 'easy_returns_info'
        ];

        foreach ($generalFields as $field) {
            $info->$field = $request->$field;
        }

        $pages = ['about_us', 'contact', 'faq', 'privacy_policy', 'terms_service'];
        foreach ($pages as $page) {
            $titleField = $page . '_title';
            $descField  = $page . '_description';
            $info->$titleField = $request->$titleField;
            $info->$descField  = $request->$descField;
        }

        $info->save();

        Log::info('Website information updated', [
            'updated_fields' => $request->except(['_token', 'logo']),
        ]);

        return redirect()->back()->with('success', 'Website information updated successfully.');
    }
}

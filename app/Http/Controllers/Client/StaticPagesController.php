<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WebsiteInfo;
use Inertia\Inertia;

class StaticPagesController extends Controller
{
    public function about()
    {
        $settings = WebsiteInfo::getSettings();
        return Inertia::render('Client/Pages/About', [
            'websiteInfo' => $settings,
            'about_title' => $settings->about_title,
            'about_description' => $settings->about_description,
            'mission_title' => $settings->mission_title,
            'mission_description' => $settings->mission_description,
            'vision_title' => $settings->vision_title,
            'vision_description' => $settings->vision_description,
        ]);
    }

    public function contact()
    {
        $settings = WebsiteInfo::getSettings();
        return Inertia::render('Client/Pages/Contact', [
            'websiteInfo' => $settings,
            'contact_email' => $settings->contact_email,
            'support_email' => $settings->support_email,
            'phone' => $settings->phone,
            'whatsapp_number' => $settings->whatsapp_number,
            'address' => $settings->address,
            'country' => $settings->country,
            'google_maps_embed_url' => $settings->google_maps_embed_url,
            'contact_info' => $settings->contact_info,
            'address_info' => $settings->address_info,
        ]);
    }

    public function faq()
    {
        return Inertia::render('Client/Pages/Faq');
    }

    public function privacy()
    {
        return Inertia::render('Client/Pages/Privacy');
    }

    public function terms()
    {
        return Inertia::render('Client/Pages/Terms');
    }
}

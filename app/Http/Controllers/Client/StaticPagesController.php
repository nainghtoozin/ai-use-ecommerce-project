<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WebsiteInfo;
use Illuminate\Http\Request;
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

    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $settings = WebsiteInfo::getSettings();
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
            // Silently fail — don't expose mail errors to users
            report($e);
        }

        return back()->with('success', 'Thank you! Your message has been sent.');
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

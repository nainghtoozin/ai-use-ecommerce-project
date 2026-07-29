<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    protected array $supportedLocales = ['en', 'my'];

    public function switch(Request $request)
    {
        $locale = $request->input('locale');

        if (!in_array($locale, $this->supportedLocales)) {
            return back()->with('error', 'Unsupported language.');
        }

        // Store in session for immediate application
        Session::put('locale', $locale);

        // Set application locale for current request
        app()->setLocale($locale);

        // Persist to authenticated user's preference
        $user = $request->user();
        if ($user) {
            try {
                $user->update(['locale' => $locale]);
            } catch (\Throwable $e) {
                // Silently fail — session still works
            }
        }

        return back()->with('success', 'Language updated successfully.');
    }
}

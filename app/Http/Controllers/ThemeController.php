<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ThemeController extends Controller
{
    protected array $supportedThemes = ['light', 'dark', 'system'];

    public function switch(Request $request)
    {
        $theme = $request->input('theme');

        if (!in_array($theme, $this->supportedThemes)) {
            return back()->with('error', 'Unsupported theme.');
        }

        // Store in session for immediate application
        Session::put('theme', $theme);

        // Persist to authenticated user's preference
        $user = $request->user();
        if ($user) {
            try {
                $user->update(['theme' => $theme]);
            } catch (\Throwable $e) {
                // Silently fail — session still works
            }
        }

        return back()->with('success', 'Theme updated successfully.');
    }
}

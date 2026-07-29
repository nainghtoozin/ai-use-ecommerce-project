<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supportedLocales = ['en', 'my'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        // 1. URL parameter (GET-based language switch, legacy support)
        if ($request->has('lang') && in_array($request->input('lang'), $this->supportedLocales)) {
            $locale = $request->input('lang');
            session(['locale' => $locale]);
            $this->persistUserPreference($request, $locale);
            return $locale;
        }

        // 2. Session (set by POST language switch)
        $sessionLocale = session('locale');
        if ($sessionLocale && in_array($sessionLocale, $this->supportedLocales)) {
            return $sessionLocale;
        }

        // 3. Authenticated user preference (from database)
        $user = $request->user();
        if ($user) {
            $userLocale = $user->getAttribute('locale');
            if ($userLocale && in_array($userLocale, $this->supportedLocales)) {
                // Sync to session for faster subsequent lookups
                session(['locale' => $userLocale]);
                return $userLocale;
            }
        }

        // 4. Fallback
        return config('app.fallback_locale', 'en');
    }

    protected function persistUserPreference(Request $request, string $locale): void
    {
        $user = $request->user();
        if ($user && in_array($locale, $this->supportedLocales)) {
            try {
                $user->update(['locale' => $locale]);
            } catch (\Throwable $e) {
                // Silently fail — session still works
            }
        }
    }
}

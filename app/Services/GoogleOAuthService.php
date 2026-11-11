<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleOAuthService
{
    public function clientId(): ?string
    {
        return config('services.google_oauth.client_id');
    }

    public function clientSecret(): ?string
    {
        return config('services.google_oauth.client_secret');
    }

    public function redirectUri(): string
    {
        return config('services.google_oauth.redirect_uri') ?: url('/auth/google/callback');
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId()) && !empty($this->clientSecret());
    }

    public function authUrl(string $state): string
    {
        $params = [
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Google token exchange failed.');
        }

        return $response->json();
    }

    public function fetchUser(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo');

        if (!$response->successful()) {
            throw new \RuntimeException('Google userinfo failed.');
        }

        return $response->json();
    }

    public function generateState(): string
    {
        return Str::random(40);
    }
}

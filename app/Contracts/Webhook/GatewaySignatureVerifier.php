<?php

namespace App\Contracts\Webhook;

interface GatewaySignatureVerifier
{
    public function verify(string $payload, array $headers): bool;
}

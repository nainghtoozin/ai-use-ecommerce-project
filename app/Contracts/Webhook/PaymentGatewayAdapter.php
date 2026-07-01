<?php

namespace App\Contracts\Webhook;

interface PaymentGatewayAdapter
{
    public function getGatewayName(): string;

    public function getSignatureVerifier(): GatewaySignatureVerifier;

    public function getPayloadParser(): GatewayPayloadParser;

    public function supportedEventTypes(): array;
}

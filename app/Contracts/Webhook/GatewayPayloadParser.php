<?php

namespace App\Contracts\Webhook;

use App\Data\Webhook\WebhookEvent;

interface GatewayPayloadParser
{
    public function parse(array $payload, array $headers): WebhookEvent;
}

<?php

namespace App\Http\Controllers;

use App\Services\Webhook\WebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookProcessor $processor,
    ) {}

    public function __invoke(string $gateway, Request $request): JsonResponse
    {
        $result = $this->processor->process(
            gateway: $gateway,
            payload: $request->all(),
            headers: $request->headers->all(),
        );

        return response()->json([
            'status' => $result->status,
            'message' => $result->message,
        ], $result->httpStatus());
    }
}

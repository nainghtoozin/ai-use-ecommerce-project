<?php

namespace App\Services\Payment\Platform;

use Illuminate\Support\Str;

class IdempotencyService
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }

    public function validate(string $key): bool
    {
        return Str::isUuid($key);
    }

    public function hasActionExecuted(array $metadata, string $action): bool
    {
        $executed = $metadata['executed_actions'] ?? [];
        return in_array($action, $executed, true);
    }

    public function markActionExecuted(array $metadata, string $action, ?string $result = null): array
    {
        $executed = $metadata['executed_actions'] ?? [];

        if (in_array($action, $executed, true)) {
            return $metadata;
        }

        $executed[] = $action;
        $metadata['executed_actions'] = $executed;

        if ($result !== null) {
            $metadata['action_results'][$action] = $result;
        }

        return $metadata;
    }

    public function getLastResult(array $metadata, string $action): mixed
    {
        return $metadata['action_results'][$action] ?? null;
    }
}

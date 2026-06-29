<?php

namespace App\Services;

use App\Models\TelegramIntegration;
use Illuminate\Support\Facades\Log;

class TelegramNotificationRouter
{
    const CATEGORIES = ['order', 'payment', 'inventory', 'system', 'marketing', 'security', 'manual'];

    const DESTINATIONS = ['personal', 'group', 'both', 'disabled'];

    protected array $routingRules = [
        'system' => 'personal',
        'security' => 'personal',
        'marketing' => 'group',
    ];

    public function resolve(TelegramIntegration $integration, string $category): array
    {
        $destination = $this->getDestinationForCategory($integration, $category);

        Log::info('[TelegramNotificationRouter] Resolving', [
            'integration_id' => $integration->id,
            'category' => $category,
            'destination' => $destination,
        ]);

        $targets = match ($destination) {
            'personal' => $this->resolvePersonal($integration, $category),
            'group' => $this->resolveGroup($integration, $category),
            'both' => $this->resolveBoth($integration, $category),
            'disabled' => [],
            default => [],
        };

        Log::info('[TelegramNotificationRouter] Resolved', [
            'integration_id' => $integration->id,
            'category' => $category,
            'destination' => $destination,
            'target_count' => count($targets),
            'targets' => $targets,
        ]);

        return $targets;
    }

    public function resolveChatIds(TelegramIntegration $integration, string $category): array
    {
        return array_map(fn ($t) => $t['chat_id'], $this->resolve($integration, $category));
    }

    public function getDestinationForCategory(TelegramIntegration $integration, string $category): string
    {
        if (isset($this->routingRules[$category])) {
            return $this->routingRules[$category];
        }

        $column = $category . '_destination';

        if (isset($integration->{$column}) && $integration->{$column} !== null) {
            return $integration->{$column};
        }

        return $integration->default_destination ?? 'personal';
    }

    public function getRoutingRule(string $category): ?string
    {
        return $this->routingRules[$category] ?? null;
    }

    public function validateDestination(TelegramIntegration $integration, string $destination): ?string
    {
        if (!in_array($destination, self::DESTINATIONS, true)) {
            return "Invalid destination '{$destination}'";
        }

        if ($destination === 'disabled') {
            return null;
        }

        if (in_array($destination, ['personal', 'both'], true) && !$integration->isPersonalVerified()) {
            return 'Personal chat is not connected. Connect a personal chat first.';
        }

        if (in_array($destination, ['group', 'both'], true) && !$integration->isGroupVerified()) {
            return 'Group chat is not connected. Connect a group chat first.';
        }

        return null;
    }

    private function resolvePersonal(TelegramIntegration $integration, string $category): array
    {
        if (!$integration->isPersonalVerified()) {
            Log::warning('[TelegramNotificationRouter] Personal destination unavailable - personal chat not verified', [
                'integration_id' => $integration->id,
                'category' => $category,
            ]);
            return [];
        }

        return [['chat_id' => $integration->personal_chat_id, 'channel' => 'personal']];
    }

    private function resolveGroup(TelegramIntegration $integration, string $category): array
    {
        if (!$integration->isGroupVerified()) {
            Log::warning('[TelegramNotificationRouter] Group destination unavailable - group chat not verified', [
                'integration_id' => $integration->id,
                'category' => $category,
            ]);
            return [];
        }

        return [['chat_id' => $integration->group_chat_id, 'channel' => 'group']];
    }

    private function resolveBoth(TelegramIntegration $integration, string $category): array
    {
        $targets = [];

        if ($integration->isPersonalVerified()) {
            $targets[] = ['chat_id' => $integration->personal_chat_id, 'channel' => 'personal'];
        } else {
            Log::warning('[TelegramNotificationRouter] Both destination - personal chat unavailable', [
                'integration_id' => $integration->id,
                'category' => $category,
            ]);
        }

        if ($integration->isGroupVerified()) {
            $targets[] = ['chat_id' => $integration->group_chat_id, 'channel' => 'group'];
        } else {
            Log::warning('[TelegramNotificationRouter] Both destination - group chat unavailable', [
                'integration_id' => $integration->id,
                'category' => $category,
            ]);
        }

        return $targets;
    }
}

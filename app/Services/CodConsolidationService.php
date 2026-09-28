<?php

namespace App\Services;

use App\Models\CodRule;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CodConsolidationService
{
    /**
     * Validate an explicit tenant => keep-rule mapping without mutating.
     *
     * @param array $map [tenantId => ['keep' => ruleId, 'expected' => [ruleIds]]]
     */
    public function plan(array $map): array
    {
        $plan = ['tenants' => [], 'noop' => [], 'errors' => []];

        foreach ($map as $tenantId => $spec) {
            $tenantId = (int) $tenantId;
            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                $plan['errors'][] = "Tenant {$tenantId} does not exist.";
                continue;
            }

            $keepId = (int) ($spec['keep'] ?? 0);
            $expectedIds = array_map('intval', (array) ($spec['expected'] ?? []));

            $actualIds = CodRule::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($actualIds)) {
                $plan['errors'][] = "Tenant {$tenantId} ({$tenant->name}) has no COD rules; refusing to map an empty set.";
                continue;
            }

            sort($expectedIds);
            $sortedActual = $actualIds;
            sort($sortedActual);

            if ($sortedActual !== $expectedIds) {
                $plan['errors'][] = "Tenant {$tenantId} ({$tenant->name}) rule set changed: expected ["
                    . implode(',', $expectedIds) . '] but found [' . implode(',', $sortedActual) . '].';
                continue;
            }

            $keep = CodRule::withoutTenantScope()
                ->where('id', $keepId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$keep) {
                $plan['errors'][] = "Keep rule {$keepId} does not exist for tenant {$tenantId} ({$tenant->name}).";
                continue;
            }

            $deactivateIds = array_values(array_diff($sortedActual, [$keepId]));

            $plan['tenants'][$tenantId] = [
                'name' => $tenant->name,
                'current' => $sortedActual,
                'keep' => $keepId,
                'deactivate' => $deactivateIds,
                'spot_check' => $this->spotCheck($keep, $deactivateIds),
            ];
        }

        $mappedIds = array_keys($map);
        $plan['noop'] = Tenant::orderBy('id')->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, $mappedIds, true))
            ->values()
            ->all();

        return $plan;
    }

    public function apply(array $map): array
    {
        $plan = $this->plan($map);

        if (!empty($plan['errors'])) {
            return array_merge($plan, ['applied' => 0]);
        }

        $applied = 0;

        DB::transaction(function () use ($plan, &$applied) {
            foreach ($plan['tenants'] as $tenantId => $entry) {
                foreach ($entry['deactivate'] as $ruleId) {
                    CodRule::withoutTenantScope()
                        ->where('id', $ruleId)
                        ->where('tenant_id', $tenantId)
                        ->update(['is_active' => false]);
                    $applied++;
                }
            }
        });

        return array_merge($plan, ['applied' => $applied]);
    }

    private function spotCheck(CodRule $keep, array $deactivateIds): array
    {
        $amounts = [1000, 500000, 1500000];
        $result = [];

        foreach ($amounts as $amount) {
            $before = CodRule::withoutTenantScope()
                ->whereIn('id', array_merge([$keep->id], $deactivateIds))
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->first(fn ($rule) => $rule->isEligible($amount, null));

            $result[$amount] = [
                'before_rule_id' => $before?->id,
                'before_eligible' => $before !== null,
                'after_eligible' => $keep->isEligible($amount, null),
            ];
        }

        return $result;
    }
}

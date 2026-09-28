<?php

namespace App\Console\Commands;

use App\Services\CodConsolidationService;
use Illuminate\Console\Command;

class ConsolidateCodRules extends Command
{
    protected $signature = 'cod:consolidate
        {--map=* : Tenant mapping as TENANT_ID:KEEP_RULE_ID:EXPECTED_IDS (e.g. --map="1:4:4,5")}
        {--apply : Deactivate superseded rules (default is dry-run)}';

    protected $description = 'Consolidate tenant COD rules to a single kept rule (dry-run by default)';

    public function handle(CodConsolidationService $service): int
    {
        $map = $this->parseMap($this->option('map'));

        if ($map === null) {
            return 1;
        }

        if (empty($map)) {
            $this->warn('No --map entries provided. Nothing to do.');
            return 0;
        }

        $apply = (bool) $this->option('apply');

        if ($apply) {
            $plan = $service->apply($map);
        } else {
            $this->comment('DRY RUN — no changes will be made. Pass --apply to execute.');
            $plan = $service->plan($map);
        }

        foreach ($plan['tenants'] as $tenantId => $entry) {
            $this->line("Tenant {$tenantId} ({$entry['name']}):");
            $this->line('  Current rule IDs: [' . implode(',', $entry['current']) . ']');
            $this->line("  Keep rule: {$entry['keep']}");
            $this->line('  Would deactivate: [' . implode(',', $entry['deactivate']) . ']');
            foreach ($entry['spot_check'] as $amount => $check) {
                $this->line("  Amount {$amount}: before rule "
                    . var_export($check['before_rule_id'], true)
                    . ' eligible ' . var_export($check['before_eligible'], true)
                    . ' => after eligible '
                    . var_export($check['after_eligible'], true));
            }
        }

        if (!empty($plan['noop'])) {
            $this->line('No-op tenants (not in mapping): [' . implode(',', $plan['noop']) . ']');
        }

        foreach ($plan['errors'] as $error) {
            $this->error($error);
        }

        if (!empty($plan['errors'])) {
            return 1;
        }

        if ($apply) {
            $this->info("Applied: {$plan['applied']} rule(s) deactivated.");
        } else {
            $this->info('Dry-run plan is valid. Re-run with --apply to execute.');
        }

        return 0;
    }

    private function parseMap(array $raw): ?array
    {
        $map = [];

        foreach ($raw as $entry) {
            $parts = explode(':', $entry);

            if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
                $this->error("Invalid --map entry '{$entry}'. Expected TENANT_ID:KEEP_RULE_ID:EXPECTED_IDS (e.g. --map=\"1:4:4,5\").");
                return null;
            }

            $expected = array_filter(array_map('trim', explode(',', $parts[2])), fn ($v) => $v !== '');

            if (empty($expected) || array_filter($expected, fn ($v) => !ctype_digit($v))) {
                $this->error("Invalid --map entry '{$entry}'. Expected IDs must be a non-empty comma list.");
                return null;
            }

            $map[(int) $parts[0]] = [
                'keep' => (int) $parts[1],
                'expected' => array_map('intval', $expected),
            ];
        }

        return $map;
    }
}

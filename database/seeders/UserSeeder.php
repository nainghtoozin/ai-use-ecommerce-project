<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * DEPRECATED: Customer creation is now handled by MembershipSeeder.
 *
 * Per Platform Identity Design Lock:
 *   - Account is the canonical identity model
 *   - TenantMembership is the authorization root
 *   - Customer accounts + memberships are created by MembershipSeeder
 *
 * This seeder is retained only for backward compatibility with
 * DemoDataSeeder. It no longer creates records or assigns roles.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('UserSeeder: Skipped — customer creation handled by MembershipSeeder.');
    }
}

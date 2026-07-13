<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ─────────────────────────────────────────────────────────
            // PLATFORM SEEDERS (no tenant dependency)
            // ─────────────────────────────────────────────────────────
            PermissionSeeder::class,         // Global permissions
            RoleAndPermissionSeeder::class,   // Global superadmin role + SuperAdmin Account
            PlanSeeder::class,               // Subscription plans
            PlatformSettingSeeder::class,     // Platform settings
            BillingPaymentMethodSeeder::class, // Platform billing methods

            // ─────────────────────────────────────────────────────────
            // TENANT BOOTSTRAP (creates tenants + memberships)
            // Must run BEFORE tenant-scoped seeders
            // ─────────────────────────────────────────────────────────
            TenantSeeder::class,             // Demo tenants
            MembershipSeeder::class,         // Tenant roles + owner + customer memberships

            // ─────────────────────────────────────────────────────────
            // TENANT-SCOPED SEEDERS (require tenants to exist)
            // ─────────────────────────────────────────────────────────
            LocationSeeder::class,           // Cities + townships (per tenant)
            WebsiteSettingsSeeder::class,    // Website info (per tenant)
            PaymentMethodSeeder::class,      // Payment methods (per tenant)
            CategorySeeder::class,           // Product categories (per tenant)
            UnitSeeder::class,               // Product units (per tenant)
            BrandSeeder::class,              // Product brands (per tenant)
        ]);
    }
}

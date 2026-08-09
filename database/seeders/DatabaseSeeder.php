<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ─────────────────────────────────────────────────────────
            // GLOBAL SEEDERS (no tenant dependency)
            // ─────────────────────────────────────────────────────────
            PermissionSeeder::class,         // Global permissions
            RoleAndPermissionSeeder::class,   // Global superadmin role + SuperAdmin Account
            PlanSeeder::class,               // Subscription plans
            PlatformSettingSeeder::class,     // Platform settings
            BillingPaymentMethodSeeder::class, // Platform billing methods
            CityTownshipSeeder::class,       // Global cities + townships (shared reference data)

            // ─────────────────────────────────────────────────────────
            // TENANT BOOTSTRAP (creates tenants + memberships)
            // Must run BEFORE tenant-scoped seeders
            // ─────────────────────────────────────────────────────────
            TenantSeeder::class,             // Demo tenants
            MembershipSeeder::class,         // Tenant roles + owner + customer memberships

            // ─────────────────────────────────────────────────────────
            // TENANT DEFAULTS (payment methods, website info, FAQs)
            // ─────────────────────────────────────────────────────────
            TenantDefaultSeeder::class,      // Payment methods + WebsiteInfo + FAQs per tenant

            // ─────────────────────────────────────────────────────────
            // DEMO DATA (optional - for development/demo only)
            // Categories, Brands, Units are importable via templates
            // ─────────────────────────────────────────────────────────
            CategorySeeder::class,           // Demo categories (per tenant)
            UnitSeeder::class,               // Demo units (per tenant)
            BrandSeeder::class,              // Demo brands (per tenant)
        ]);
    }
}

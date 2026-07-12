<?php

use App\Models\Account;
use App\Models\CustomerProfile;
use App\Models\MerchantProfile;
use App\Models\TenantMembership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('name', 255)->nullable()->after('email');
        });

        Account::with('memberships.merchantProfile', 'memberships.customerProfile')
            ->chunk(100, function ($accounts) {
                foreach ($accounts as $account) {
                    $name = null;

                    $membership = $account->memberships->first();
                    if ($membership) {
                        if ($membership->merchantProfile && $membership->merchantProfile->business_name) {
                            $name = $membership->merchantProfile->business_name;
                        } elseif ($membership->customerProfile && $membership->customerProfile->name) {
                            $name = $membership->customerProfile->name;
                        }
                    }

                    if (!$name) {
                        $name = explode('@', $account->email)[0];
                    }

                    Account::withoutEvents(fn () => $account->updateQuietly(['name' => $name]));
                }
            });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};

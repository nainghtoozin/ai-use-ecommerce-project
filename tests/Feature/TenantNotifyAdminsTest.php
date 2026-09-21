<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantNotifyAdminsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('identity.use_accounts', true);
        $this->createNotifySchema();
    }

    public function test_account_owner_and_admin_receive_database_notification(): void
    {
        $tenant = Tenant::create([
            'slug' => 'notify-admins-' . uniqid(),
            'name' => 'Notify Admins Shop',
            'status' => 'active',
        ]);

        $owner = $this->makeAccount('owner-notify@example.com', $tenant->id, true, true);
        $admin = $this->makeAccount('admin-notify@example.com', $tenant->id, false, true);

        $otherTenant = Tenant::create([
            'slug' => 'notify-other-' . uniqid(),
            'name' => 'Other Shop',
            'status' => 'active',
        ]);
        $outsider = $this->makeAccount('outsider-notify@example.com', $otherTenant->id, false, true);

        $tenant->notifyAdmins(new TenantNotifyAdminsProbe());

        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $owner->id)
            ->where('type', TenantNotifyAdminsProbe::class)
            ->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $admin->id)
            ->where('type', TenantNotifyAdminsProbe::class)
            ->count());
        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_type', Account::class)
            ->where('notifiable_id', $outsider->id)
            ->count());
    }

    private function makeAccount(string $email, int $tenantId, bool $isOwner, bool $isAdmin): Account
    {
        $account = Account::create([
            'name' => 'Test Account',
            'email' => $email,
            'password' => 'secret',
            'status' => 'active',
        ]);

        $roleName = $isAdmin ? 'admin' : 'staff';
        $role = Role::withoutTenantScope()->firstOrCreate([
            'name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenantId,
        ]);

        TenantMembership::create([
            'account_id' => $account->id,
            'tenant_id' => $tenantId,
            'role_id' => $role->id,
            'is_owner' => $isOwner,
            'status' => 'active',
        ]);

        return $account;
    }

    private function createNotifySchema(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('status')->default('active');
                $table->string('store_url')->nullable();
                $table->json('settings')->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tenant_memberships')) {
            Schema::create('tenant_memberships', function ($table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('role_id')->nullable();
                $table->boolean('is_owner')->default(false);
                $table->string('status')->default('active');
                $table->timestamps();
                $table->index(['tenant_id', 'account_id']);
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->timestamps();
                $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }
}

class TenantNotifyAdminsProbe extends Notification
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Probe', 'message' => 'Recipient resolution probe.'];
    }
}

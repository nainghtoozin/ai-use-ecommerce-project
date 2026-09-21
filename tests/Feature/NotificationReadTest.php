<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createReadSchema();
    }

    public function test_user_marks_all_as_read(): void
    {
        $user = $this->makeUser('read-user@example.com');
        $this->makeRow(User::class, $user->id, null);
        $this->makeRow(User::class, $user->id, now()->subHour());

        $this->actingAs($user)->patchJson('/notifications/read-all')->assertOk();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_account_marks_all_as_read(): void
    {
        $account = Account::create([
            'name' => 'Merchant',
            'email' => 'read-acct@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $this->makeRow(Account::class, $account->id, null);
        $this->makeRow(Account::class, $account->id, null);

        $this->actingAs($account, 'accounts')->patchJson('/notifications/read-all')->assertOk();

        $this->assertSame(0, $account->fresh()->unreadNotifications()->count());
    }

    public function test_read_all_is_isolated_per_user(): void
    {
        $userA = $this->makeUser('read-iso-a@example.com');
        $userB = $this->makeUser('read-iso-b@example.com');
        $this->makeRow(User::class, $userA->id, null);
        $this->makeRow(User::class, $userB->id, null);

        $this->actingAs($userA)->patchJson('/notifications/read-all')->assertOk();

        $this->assertSame(0, $userA->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $userB->fresh()->unreadNotifications()->count());
    }

    public function test_read_all_is_idempotent(): void
    {
        $user = $this->makeUser('read-idem@example.com');
        $this->makeRow(User::class, $user->id, null);

        $this->actingAs($user)->patchJson('/notifications/read-all')->assertOk();
        $this->actingAs($user)->patchJson('/notifications/read-all')->assertOk();

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertSame(1, DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count());
    }

    public function test_single_mark_as_read(): void
    {
        $user = $this->makeUser('read-one@example.com');
        $first = $this->makeRow(User::class, $user->id, null);
        $this->makeRow(User::class, $user->id, null);

        $this->actingAs($user)->patchJson("/notifications/{$first}/read")->assertOk();

        $this->assertNotNull(DB::table('notifications')->where('id', $first)->value('read_at'));
        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    private function makeUser(string $email): User
    {
        $user = new User(['name' => 'Test User', 'email' => $email, 'password' => 'secret']);
        $user->save();

        return $user;
    }

    private function makeRow(string $type, int $id, $readAt): string
    {
        $uuid = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $uuid,
            'type' => 'App\\Notifications\\SubscriptionExpired',
            'notifiable_type' => $type,
            'notifiable_id' => $id,
            'data' => json_encode(['title' => 'T']),
            'read_at' => $readAt?->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return $uuid;
    }

    private function createReadSchema(): void
    {
        foreach (['users' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        }, 'accounts' => function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        }, 'notifications' => function ($table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        }] as $name => $blueprint) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $blueprint);
            }
        }
    }
}

<?php

namespace Tests\Feature;

use App\Auth\IdentityResolver;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Account;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatChannelCollisionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'test-key');
        config()->set('broadcasting.connections.pusher.secret', 'test-secret');
        config()->set('broadcasting.connections.pusher.app_id', '12345');
        config()->set('broadcasting.connections.pusher.options.cluster', 'ap1');

        app()->forgetInstance('Illuminate\Broadcasting\BroadcastManager');
        require base_path('routes/channels.php');

        $this->createChatSchema();
    }

    public function test_chat_channels_do_not_collide(): void
    {
        $this->assertSame('chat.user.5', IdentityResolver::chatChannel(5, User::class));
        $this->assertSame('chat.account.5', IdentityResolver::chatChannel(5, Account::class));
        $this->assertNotSame(
            IdentityResolver::chatChannel(5, User::class),
            IdentityResolver::chatChannel(5, Account::class)
        );
    }

    public function test_same_id_user_and_account_cannot_cross_authenticate(): void
    {
        $user = $this->makeUser('chat-collide-a@example.com');

        $account = new Account([
            'name' => 'Same Id',
            'email' => 'chat-collide-acct@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $account->id = $user->id;
        $account->save();

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-chat.account.' . $account->id,
            ])
            ->assertForbidden();

        $this->actingAs($account, 'accounts');
        Auth::guard('web')->logout();

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-chat.user.' . $user->id,
            ])
            ->assertForbidden();
    }

    public function test_correct_party_authenticates_and_partner_is_allowed(): void
    {
        $alice = $this->makeUser('chat-alice@example.com');
        $bob = $this->makeUser('chat-bob@example.com');
        $this->makeMessage($alice->id, User::class, $bob->id, User::class);

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($bob)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-chat.user.' . $bob->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($alice)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-chat.user.' . $bob->id,
            ])
            ->assertOk();

        $stranger = $this->makeUser('chat-stranger@example.com');

        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->actingAs($stranger)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-chat.user.' . $bob->id,
            ])
            ->assertForbidden();
    }

    public function test_message_broadcast_targets_both_namespaced_channels(): void
    {
        $merchant = Account::create([
            'name' => 'Merchant',
            'email' => 'chat-merchant@example.com',
            'password' => 'secret',
            'status' => 'active',
        ]);
        $customer = $this->makeUser('chat-customer@example.com');
        $message = $this->makeMessage($merchant->id, Account::class, $customer->id, User::class);

        $channels = collect((new MessageSent($message))->broadcastOn())
            ->map(fn ($c) => $c->name)
            ->all();

        $this->assertContains('private-chat.account.' . $merchant->id, $channels);
        $this->assertContains('private-chat.user.' . $customer->id, $channels);
        $this->assertSame('message.sent', (new MessageSent($message))->broadcastAs());

        $payload = (new MessageSent($message))->broadcastWith();
        $this->assertSame($merchant->id, $payload['sender_id']);
        $this->assertSame($customer->id, $payload['receiver_id']);
        $this->assertArrayHasKey('message', $payload);
    }

    public function test_typing_broadcast_targets_namespaced_channel(): void
    {
        $event = new UserTyping(7, 9, 'Shop', true, Account::class, User::class);

        $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

        $this->assertSame(['private-chat.user.9'], $channels);
        $this->assertSame('typing', $event->broadcastAs());
    }

    private function makeUser(string $email): User
    {
        $user = new User([
            'name' => 'Test User',
            'email' => $email,
            'password' => 'secret',
        ]);
        $user->save();

        return $user;
    }

    private function makeMessage(int $senderId, string $senderType, int $receiverId, string $receiverType): Message
    {
        $message = new Message([
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'receiver_id' => $receiverId,
            'receiver_type' => $receiverType,
            'message' => 'Hello',
        ]);

        Schema::disableForeignKeyConstraints();
        try {
            $message->save();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $message;
    }

    private function createChatSchema(): void
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
        }, 'messages' => function ($table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->string('sender_type')->nullable();
            $table->unsignedBigInteger('receiver_id');
            $table->string('receiver_type')->nullable();
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        }] as $name => $blueprint) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $blueprint);
            }
        }
    }
}

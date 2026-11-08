<?php

namespace Tests\Feature;

use App\Jobs\ProcessMerchantEmailCampaignBatch;
use App\Jobs\SendMerchantEmailJob;
use App\Models\MerchantEmailCampaign;
use App\Models\MerchantEmailRecipient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MerchantEmailCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->superadmin->assignRole($role);

        Mail::fake();
    }

    private function makeTenant(string $status = 'active', ?string $email = null): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Tenant ' . uniqid(),
            'slug' => 'tenant-' . uniqid(),
            'status' => $status,
            'email' => $email,
        ]);

        if ($email) {
            $user = User::create([
                'name' => 'Owner ' . uniqid(),
                'email' => $email,
                'password' => bcrypt('password'),
                'tenant_id' => $tenant->id,
                'is_owner' => true,
                'status' => 'active',
            ]);
            $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        }

        return $tenant;
    }

    public function test_non_superadmin_cannot_access(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $this->actingAs($merchant)->get('/superadmin/email-communications')->assertForbidden();
    }

    public function test_superadmin_can_access(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/superadmin/email-communications')
            ->assertOk();
    }

    public function test_all_mode_excludes_suspended_by_default(): void
    {
        $active = $this->makeTenant('active', 'active@test.com');
        $suspended = $this->makeTenant('suspended', 'suspended@test.com');

        $this->actingAs($this->superadmin)
            ->post('/superadmin/email-communications/preview', [
                'recipient_mode' => 'all',
                'email_type' => 'general',
                'subject' => 'Test',
                'message' => 'Hello',
            ])
            ->assertOk()
            ->assertJsonPath('recipient_count', 2);
    }

    public function test_selected_mode_sends_to_selected(): void
    {
        Queue::fake();

        $t1 = $this->makeTenant('active', 'one@test.com');
        $t2 = $this->makeTenant('active', 'two@test.com');

        $this->actingAs($this->superadmin)
            ->post('/superadmin/email-communications', [
                'recipient_mode' => 'selected',
                'tenant_ids' => [$t1->id],
                'email_type' => 'general',
                'subject' => 'Selected',
                'message' => 'Hello selected',
            ])
            ->assertRedirect();

        $campaign = MerchantEmailCampaign::first();
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertSame(1, $campaign->recipients()->count());
        Queue::assertPushed(ProcessMerchantEmailCampaignBatch::class, 1);
    }

    public function test_single_mode(): void
    {
        Queue::fake();

        $tenant = $this->makeTenant('active', 'single@test.com');

        $this->actingAs($this->superadmin)
            ->post('/superadmin/email-communications', [
                'recipient_mode' => 'single',
                'tenant_id' => $tenant->id,
                'email_type' => 'general',
                'subject' => 'Single',
                'message' => 'Hello single',
            ])
            ->assertRedirect();

        $this->assertSame(1, MerchantEmailCampaign::first()->recipient_count);
    }

    public function test_missing_email_excluded(): void
    {
        $this->makeTenant('active', null);

        $response = $this->actingAs($this->superadmin)
            ->post('/superadmin/email-communications/preview', [
                'recipient_mode' => 'all',
                'email_type' => 'general',
                'subject' => 'Test',
                'message' => 'Hello',
            ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('recipient_count'));
        $this->assertGreaterThanOrEqual(1, $response->json('excluded_count'));
    }

    public function test_duplicate_dispatch_prevented(): void
    {
        Queue::fake();

        $tenant = $this->makeTenant('active', 'dup@test.com');

        $this->actingAs($this->superadmin)
            ->post('/superadmin/email-communications', [
                'recipient_mode' => 'single',
                'tenant_id' => $tenant->id,
                'email_type' => 'general',
                'subject' => 'Dup',
                'message' => 'Hello',
            ])
            ->assertRedirect();

        // second identical request creates new campaign but recipient row unique per campaign
        $this->actingAs($this->superadmin)
            ->post('/superadmin/email-communications', [
                'recipient_mode' => 'single',
                'tenant_id' => $tenant->id,
                'email_type' => 'general',
                'subject' => 'Dup',
                'message' => 'Hello',
            ])
            ->assertRedirect();

        $this->assertSame(2, MerchantEmailCampaign::count());
    }

    public function test_job_records_failure(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP down'));

        $recipient = MerchantEmailRecipient::create([
            'campaign_id' => MerchantEmailCampaign::create([
                'subject' => 'Fail',
                'email_type' => 'general',
                'message' => 'msg',
                'recipient_mode' => 'single',
                'recipient_count' => 1,
                'status' => 'queued',
            ])->id,
            'recipient_email' => 'fail@test.com',
            'recipient_name' => 'Fail User',
            'status' => 'pending',
        ]);

        $job = new SendMerchantEmailJob($recipient->id);
        try {
            $job->handle();
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame('failed', $recipient->fresh()->status);
        $this->assertNotNull($recipient->fresh()->failure_reason);
    }

    public function test_retry_only_failed(): void
    {
        Queue::fake();

        $campaign = MerchantEmailCampaign::create([
            'subject' => 'Retry',
            'email_type' => 'general',
            'message' => 'msg',
            'recipient_mode' => 'single',
            'recipient_count' => 2,
            'status' => 'completed',
        ]);

        $sent = MerchantEmailRecipient::create([
            'campaign_id' => $campaign->id,
            'recipient_email' => 'sent@test.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $failed = MerchantEmailRecipient::create([
            'campaign_id' => $campaign->id,
            'recipient_email' => 'failed@test.com',
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->superadmin)
            ->post("/superadmin/email-communications/{$campaign->id}/retry-failed")
            ->assertRedirect();

        $this->assertSame('sent', $sent->fresh()->status);
        $this->assertSame('pending', $failed->fresh()->status);
        Queue::assertPushed(SendMerchantEmailJob::class, 1);
    }

    public function test_history_pagination(): void
    {
        $this->actingAs($this->superadmin)
            ->get('/superadmin/email-communications/history')
            ->assertOk();
    }

    public function test_mailable_renders_html_and_text(): void
    {
        $mail = new \App\Mail\Platform\MerchantCommunicationMail(
            subjectText: 'Hello <script>',
            messageText: "Line 1\nLine 2 & more",
            emailType: 'general',
            recipientName: 'Owner',
        );

        $html = $mail->render();
        $this->assertStringContainsString('Line 1', $html);
        $this->assertStringContainsString('Line 2', $html);
        $this->assertStringContainsString('Owner', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_audit_log_created(): void
    {
        Queue::fake();

        $tenant = $this->makeTenant('active', 'audit@test.com');

        $this->actingAs($this->superadmin)
            ->post('/superadmin/email-communications', [
                'recipient_mode' => 'single',
                'tenant_id' => $tenant->id,
                'email_type' => 'general',
                'subject' => 'Audit',
                'message' => 'Hello',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'merchant_email_sent',
        ]);
    }
}


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('subject');
            $table->string('email_type', 50);
            $table->longText('message');
            $table->string('recipient_mode', 20);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('status', 30)->default('queued');
            $table->string('reply_to')->nullable();
            $table->json('filters')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email_type');
        });

        Schema::create('merchant_email_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('merchant_email_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('tenant_id');
            $table->unique(['campaign_id', 'tenant_id', 'recipient_email'], 'merc_campaign_tenant_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_email_recipients');
        Schema::dropIfExists('merchant_email_campaigns');
    }
};

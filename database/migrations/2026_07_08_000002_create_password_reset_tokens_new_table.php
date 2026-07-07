<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens_new', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->primary('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens_new');
    }
};

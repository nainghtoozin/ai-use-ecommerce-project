<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_evidences', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->after('type');
            $table->string('sender_account')->nullable()->after('sender_name');
            $table->string('transaction_reference')->nullable()->after('sender_account');
            $table->decimal('transferred_amount', 15, 2)->nullable()->after('transaction_reference');
            $table->date('transfer_date')->nullable()->after('transferred_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_evidences', function (Blueprint $table) {
            $table->dropColumn(['sender_name', 'sender_account', 'transaction_reference', 'transferred_amount', 'transfer_date']);
        });
    }
};

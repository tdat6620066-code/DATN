<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $t) {
            $t->string('refund_method', 30)->nullable();
            $t->text('bank_name')->nullable();
            $t->text('bank_account_number')->nullable();
            $t->text('bank_account_holder')->nullable();
            $t->string('bank_account_last4', 4)->nullable();
        });
        Schema::table('refunds', function (Blueprint $t) {
            $t->string('refund_method', 30)->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $t) {
            $t->dropConstrainedForeignId('processed_by');
            $t->dropColumn('refund_method');
        });
        Schema::table('refund_requests', fn (Blueprint $t) => $t->dropColumn(['refund_method', 'bank_name', 'bank_account_number', 'bank_account_holder', 'bank_account_last4']));
    }
};

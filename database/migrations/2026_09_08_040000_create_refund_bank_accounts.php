<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank_code', 50)->nullable();
            $table->text('bank_name');
            $table->text('account_number');
            $table->text('account_name');
            $table->string('account_last4', 4);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        // Copy ciphertext unchanged; never infer a recipient from payment data.
        DB::table('refund_requests')->whereNotNull('bank_account_number')->orderBy('id')->chunkById(100, function ($items) {
            foreach ($items as $item) {
                DB::table('refund_bank_accounts')->insert(['refund_request_id' => $item->id, 'bank_name' => $item->bank_name, 'account_number' => $item->bank_account_number, 'account_name' => $item->bank_account_holder, 'account_last4' => $item->bank_account_last4, 'created_at' => $item->created_at, 'updated_at' => $item->updated_at]);
            }
        });
        Schema::table('refund_requests', fn (Blueprint $table) => $table->dropColumn(['bank_name', 'bank_account_number', 'bank_account_holder', 'bank_account_last4']));
        Schema::table('refunds', fn (Blueprint $table) => $table->text('processing_note')->nullable());
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->text('bank_name')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->text('bank_account_holder')->nullable();
            $table->string('bank_account_last4', 4)->nullable();
        });
        DB::table('refund_bank_accounts')->orderBy('id')->chunkById(100, function ($items) {
            foreach ($items as $item) {
                DB::table('refund_requests')->where('id', $item->refund_request_id)->update(['bank_name' => $item->bank_name, 'bank_account_number' => $item->account_number, 'bank_account_holder' => $item->account_name, 'bank_account_last4' => $item->account_last4]);
            }
        });
        Schema::dropIfExists('refund_bank_accounts');
        Schema::table('refunds', fn (Blueprint $table) => $table->dropColumn('processing_note'));
    }
};

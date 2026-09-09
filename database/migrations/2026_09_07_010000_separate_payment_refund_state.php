<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('refund_status', 30)->default('NONE');
        });
        DB::table('payments')->orderBy('id')->chunkById(200, function ($payments) {
            foreach ($payments as $payment) {
                $amount = DB::table('refunds')->where('payment_id', $payment->id)->where('status', 'COMPLETED')->sum('amount');
                $legacy = in_array($payment->status, ['REFUNDED', 'PARTIALLY_REFUNDED'], true);
                $status = $amount > 0 ? ($amount >= $payment->amount ? 'REFUNDED' : 'PARTIALLY_REFUNDED') : ($legacy ? $payment->status : 'NONE');
                DB::table('payments')->where('id', $payment->id)->update(['refunded_amount' => $amount, 'refund_status' => $status, 'status' => $legacy ? 'PAID' : $payment->status]);
            }
        });
    }

    public function down(): void
    {
        DB::table('payments')->whereIn('refund_status', ['REFUNDED', 'PARTIALLY_REFUNDED'])->update(['status' => DB::raw('refund_status')]);
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['refunded_amount', 'refund_status']));
    }
};

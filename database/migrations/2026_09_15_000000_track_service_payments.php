<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable();
        });
        DB::table('booking_services')->whereIn('booking_id', function ($query) {
            $query->select('id')->from('bookings')->whereIn('payment_status', ['PAID', 'PARTIALLY_REFUNDED', 'REFUNDED']);
        })->update(['paid_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('booking_services', fn (Blueprint $table) => $table->dropColumn('paid_at'));
    }
};

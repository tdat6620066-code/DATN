<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('booking_type', 20)->default('daily')->after('booking_code');
            $table->date('start_date')->nullable()->after('booking_type');
            $table->date('end_date')->nullable()->after('start_date');
            $table->index('booking_type');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['booking_type']);
            $table->dropColumn(['booking_type', 'start_date', 'end_date']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_bookings', function (Blueprint $table) {
            // Existing groups retain their original individual payments.
            $table->string('status')->default('LEGACY');
            $table->decimal('total_price', 12, 2)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->foreignId('fixed_booking_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_booking_id');
        });
        Schema::table('fixed_bookings', function (Blueprint $table) {
            $table->dropColumn(['status', 'total_price', 'expires_at']);
        });
    }
};

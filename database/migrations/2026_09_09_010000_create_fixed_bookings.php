<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('confirmation_key')->unique();
            $table->json('definition');
            $table->json('occurrences');
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('fixed_booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('recurrence_key', 40)->nullable();
            $table->unique(['fixed_booking_id', 'recurrence_key']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['fixed_booking_id', 'recurrence_key']);
            $table->dropConstrainedForeignId('fixed_booking_id');
            $table->dropColumn('recurrence_key');
        });
        Schema::dropIfExists('fixed_bookings');
    }
};

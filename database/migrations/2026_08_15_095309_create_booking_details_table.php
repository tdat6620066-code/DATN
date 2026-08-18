<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('court_id')->constrained('courts');
            $table->date('booking_date');
            $table->foreignId('time_slot_id')->constrained('time_slots');
            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->enum('status', ['PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->timestamps();
            
            $table->index('booking_id');
            $table->index('court_id');
            $table->index('booking_date');
            $table->index('time_slot_id');
            $table->index('status');
            $table->unique(['booking_date', 'time_slot_id', 'court_id', 'booking_id'], 'unique_booking_detail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_details');
    }
};

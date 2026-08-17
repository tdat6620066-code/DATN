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
        Schema::create('court_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->onDelete('cascade');
            $table->foreignId('time_slot_id')->constrained('time_slots');
            $table->decimal('price', 12, 2);
            $table->enum('day_type', ['WEEKDAY', 'WEEKEND', 'HOLIDAY'])->default('WEEKDAY');
            $table->boolean('is_peak')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
            
            $table->index('court_id');
            $table->index('time_slot_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_prices');
    }
};

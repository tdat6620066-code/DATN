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
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('court_type_id')->constrained('court_types');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('map_url')->nullable();
            $table->string('phone')->nullable();
            $table->time('opening_time')->default('06:00');
            $table->time('closing_time')->default('22:00');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->enum('availability_status', ['AVAILABLE', 'OCCUPIED'])->default('AVAILABLE');
            $table->enum('operational_status', ['AVAILABLE', 'LOCKED', 'MAINTENANCE'])->default('AVAILABLE');
            $table->text('status_reason')->nullable();
            $table->dateTime('status_updated_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            
            $table->index('status');
            $table->index('court_type_id');
            $table->index('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};

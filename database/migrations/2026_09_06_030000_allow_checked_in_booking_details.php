<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', fn (Blueprint $table) => $table->string('status', 30)->default('PENDING')->change());
    }

    public function down(): void
    {
        // Preserve CHECKED_IN records used to calculate unused service time.
    }
};

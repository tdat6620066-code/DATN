<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('user_id')->constrained('bookings')->nullOnDelete();
            $table->string('action_url')->nullable()->after('type');
            $table->string('unique_key')->nullable()->unique()->after('action_url');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['unique_key']);
            $table->dropColumn(['booking_id', 'action_url', 'unique_key']);
        });
    }
};

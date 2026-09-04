<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_announcements', function (Blueprint $table) {
            $table->string('target_type')->default('AUDIENCE')->after('audience');
            $table->json('target_user_ids')->nullable()->after('target_type');
            $table->foreignId('court_id')->nullable()->after('target_user_ids')->constrained('courts')->nullOnDelete();
            $table->string('area')->nullable()->after('court_id');
            $table->string('action_url')->nullable()->after('area');
        });
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignId('announcement_id')->nullable()->after('booking_id')->constrained('system_announcements')->nullOnDelete();
            $table->timestamp('read_at')->nullable()->after('is_read');
            $table->timestamp('clicked_at')->nullable()->after('read_at');
            $table->index(['announcement_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex(['announcement_id', 'is_read']);
            $table->dropConstrainedForeignId('announcement_id');
            $table->dropColumn(['read_at', 'clicked_at']);
        });
        Schema::table('system_announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('court_id');
            $table->dropColumn(['target_type', 'target_user_ids', 'area', 'action_url']);
        });
    }
};

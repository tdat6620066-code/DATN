<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            if (! Schema::hasColumn('courts', 'operational_status')) {
                $table->enum('operational_status', ['AVAILABLE', 'LOCKED', 'MAINTENANCE'])->default('AVAILABLE')->after('availability_status');
            }
            if (! Schema::hasColumn('courts', 'status_reason')) {
                $table->text('status_reason')->nullable()->after('operational_status');
            }
            if (! Schema::hasColumn('courts', 'status_updated_at')) {
                $table->dateTime('status_updated_at')->nullable()->after('status_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['operational_status', 'status_reason', 'status_updated_at']);
        });
    }
};

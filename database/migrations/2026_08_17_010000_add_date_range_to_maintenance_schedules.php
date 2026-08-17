<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('maintenance_schedules', 'start_date')) {
            Schema::table('maintenance_schedules', fn (Blueprint $table) => $table->date('start_date')->nullable()->after('maintenance_date'));
        }
        if (! Schema::hasColumn('maintenance_schedules', 'end_date')) {
            Schema::table('maintenance_schedules', fn (Blueprint $table) => $table->date('end_date')->nullable()->after('start_date'));
        }
        DB::table('maintenance_schedules')->update(['start_date' => DB::raw('maintenance_date'), 'end_date' => DB::raw('maintenance_date')]);
    }

    public function down(): void
    {
        Schema::table('maintenance_schedules', fn (Blueprint $table) => $table->dropColumn(['start_date', 'end_date']));
    }
};

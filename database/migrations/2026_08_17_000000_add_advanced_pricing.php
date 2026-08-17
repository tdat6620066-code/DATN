<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('court_prices', 'day_type')) {
                $table->enum('day_type', ['WEEKDAY', 'WEEKEND', 'HOLIDAY'])->default('WEEKDAY')->after('price');
            }
            if (! Schema::hasColumn('court_prices', 'is_peak')) {
                $table->boolean('is_peak')->default(false)->after('day_type');
            }
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::table('court_prices', fn (Blueprint $table) => $table->dropColumn(['day_type', 'is_peak']));
    }
};

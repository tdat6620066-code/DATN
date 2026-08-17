<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vouchers', 'conditions')) {
            Schema::table('vouchers', fn (Blueprint $t) => $t->text('conditions')->nullable()->after('used_count'));
        }
    }

    public function down(): void
    {
        Schema::table('vouchers', fn (Blueprint $t) => $t->dropColumn('conditions'));
    }
};

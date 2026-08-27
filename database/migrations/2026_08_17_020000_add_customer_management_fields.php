<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('phone', 30)->nullable()->after('email'));
        }

        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', fn (Blueprint $t) => $t->enum('role', ['CUSTOMER', 'EMPLOYEE', 'ADMIN'])->default('CUSTOMER')->after('password'));
        }

        if (! Schema::hasColumn('users', 'status')) {
            Schema::table('users', fn (Blueprint $t) => $t->enum('status', ['ACTIVE', 'LOCKED'])->default('ACTIVE')->after('role'));
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'status']));
    }
};

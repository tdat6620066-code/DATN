<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['CUSTOMER', 'EMPLOYEE', 'ADMIN'])->default('CUSTOMER')->after('password');
            }
        });

        Schema::table('courts', function (Blueprint $table) {
            if (! Schema::hasColumn('courts', 'availability_status')) {
                $table->enum('availability_status', ['AVAILABLE', 'OCCUPIED'])->default('AVAILABLE')->after('status');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable()->after('confirmed_at');
            }
            if (! Schema::hasColumn('bookings', 'checked_out_at')) {
                $table->dateTime('checked_out_at')->nullable()->after('checked_in_at');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('PENDING_PAYMENT','CONFIRMED','CHECKED_IN','COMPLETED','CANCELLED','EXPIRED') NOT NULL DEFAULT 'PENDING_PAYMENT'");
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'checked_out_at']);
        });
        Schema::table('courts', fn (Blueprint $table) => $table->dropColumn('availability_status'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('PENDING_PAYMENT','CONFIRMED','COMPLETED','CANCELLED','EXPIRED') NOT NULL DEFAULT 'PENDING_PAYMENT'");
        }
    }
};

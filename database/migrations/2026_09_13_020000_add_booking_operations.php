<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status', 30)->default('PENDING_PAYMENT')->change();
            $table->timestamp('no_show_at')->nullable();
            $table->foreignId('no_show_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('checkout_exception_reason')->nullable();
            $table->foreignId('extended_from_id')->nullable()->constrained('bookings')->restrictOnDelete();
        });
        Schema::table('service_orders', function (Blueprint $table) {
            $table->boolean('pay_at_checkout')->default(false);
            $table->timestamp('expires_at')->nullable()->change();
        });
        Schema::table('booking_services', function (Blueprint $table) {
            $table->boolean('requires_return')->default(false);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::table('booking_services')->whereIn('service_item_id', DB::table('service_items')->where('category', 'RENTAL')->select('id'))
            ->whereIn('booking_id', DB::table('bookings')->whereIn('status', ['PENDING_PAYMENT', 'CONFIRMED', 'CHECKED_IN'])->select('id'))
            ->update(['requires_return' => true]);
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn(['requires_return', 'returned_quantity', 'returned_at']);
        });
        Schema::table('service_orders', fn (Blueprint $table) => $table->dropColumn('pay_at_checkout'));
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('no_show_by');
            $table->dropConstrainedForeignId('extended_from_id');
            $table->dropColumn(['no_show_at', 'checkout_exception_reason']);
        });
    }
};

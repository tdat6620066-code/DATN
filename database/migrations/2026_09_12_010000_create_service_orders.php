<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->string('purpose', 20)->default('BOOKING')->index());
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_key')->unique();
            $table->string('source', 20);
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::table('booking_services', function (Blueprint $table) {
            $table->string('source', 20)->default('pre_booking');
            $table->foreignId('service_order_id')->nullable()->constrained()->restrictOnDelete();
        });
        // Existing entries were staff additions billed through the old booking payment.
        DB::table('booking_services')->update(['source' => 'at_court']);
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_order_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('service_orders');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('purpose'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('extension_of_id')->nullable()->constrained('bookings')->nullOnDelete();
        });
        Schema::create('counter_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('employee_id')->constrained('users');
            $table->string('customer_name')->nullable();
            $table->decimal('total', 12, 2);
            $table->string('payment_method');
            $table->string('transaction_id')->nullable();
            $table->timestamps();
        });
        Schema::create('counter_sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_item_id')->constrained();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
        });
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('AVAILABLE');
            $table->text('condition_note')->nullable();
            $table->timestamps();
        });
        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->foreignId('booking_id')->constrained();
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('returned_by')->nullable()->constrained('users');
            $table->text('issue_note');
            $table->text('return_note')->nullable();
            $table->string('return_status')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
        });
        Schema::create('court_cleanings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained();
            $table->foreignId('employee_id')->constrained('users');
            $table->text('note');
            $table->timestamps();
        });
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('court_cleanings');
        Schema::dropIfExists('equipment_loans');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('counter_sale_lines');
        Schema::dropIfExists('counter_sales');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropConstrainedForeignId('extension_of_id'));
    }
};

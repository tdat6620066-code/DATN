<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('added_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('court_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code')->unique();
            $table->foreignId('court_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->enum('severity', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL']);
            $table->text('description');
            $table->json('images')->nullable();
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'])->default('OPEN');
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['court_id', 'status']);
        });

        Schema::create('system_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('content');
            $table->enum('audience', ['ALL', 'CUSTOMER', 'EMPLOYEE']);
            $table->enum('status', ['DRAFT', 'SCHEDULED', 'SENT', 'CANCELLED'])->default('DRAFT');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE booking_details MODIFY status ENUM('PENDING','CONFIRMED','CHECKED_IN','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING'");
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropConstrainedForeignId('checked_out_by');
        });
        Schema::dropIfExists('system_announcements');
        Schema::dropIfExists('court_incidents');
        Schema::dropIfExists('booking_services');
        Schema::dropIfExists('service_items');
    }
};

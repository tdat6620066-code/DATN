<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('refund_approval_limit', 12, 2)->default(0)->after('role');
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->text('supporting_information')->nullable();
            $table->enum('status', ['PENDING', 'NEEDS_INFO', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('decision_note')->nullable();
            $table->text('requested_information')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('refund_code')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['PROCESSING', 'COMPLETED', 'FAILED'])->default('PROCESSING');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('refund_requests');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('refund_approval_limit'));
    }
};

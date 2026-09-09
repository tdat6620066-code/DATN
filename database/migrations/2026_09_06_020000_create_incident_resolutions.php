<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->string('payment_status', 30)->default('PENDING')->change());
        Schema::table('payments', fn (Blueprint $t) => $t->string('status', 30)->default('PENDING')->change());
        Schema::create('incident_resolutions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('court_incident_id')->constrained()->restrictOnDelete();
            $t->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('booking_detail_id')->constrained()->cascadeOnDelete();
            $t->json('original_slot');
            $t->decimal('refund_amount', 12, 2);
            $t->string('status')->default('AWAITING_CHOICE');
            $t->string('choice')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->unique(['court_incident_id', 'booking_detail_id']);
        });
        Schema::table('refund_requests', function (Blueprint $t) {
            $t->foreignId('incident_resolution_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('cancel_booking')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('incident_resolution_id');
            $t->dropColumn('cancel_booking');
        });
        Schema::dropIfExists('incident_resolutions');
        // Keep widened payment statuses to preserve existing partial-refund records.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('court_incidents', function (Blueprint $t) {
            $t->string('status', 30)->default('OPEN')->change();
            $t->string('source', 20)->default('COURT');
            $t->json('booking_snapshot')->nullable();
            $t->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('booking_detail_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('active_booking_id')->nullable()->unique()->constrained('bookings')->restrictOnDelete();
            $t->string('requested_solution', 30)->nullable();
            $t->string('proposed_solution', 30)->nullable();
            $t->decimal('proposed_amount', 12, 2)->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('review_note')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->index(['source', 'status']);
        });
        Schema::create('incident_evidences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained('court_incidents')->cascadeOnDelete();
            $t->string('file_path');
            $t->string('file_type', 100);
            $t->timestamps();
        });
        Schema::create('incident_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained('court_incidents')->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('status', 30);
            $t->text('note');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_updates');
        Schema::dropIfExists('incident_evidences');
        Schema::table('court_incidents', function (Blueprint $t) {
            foreach (['booking_id', 'booking_detail_id', 'customer_id', 'active_booking_id', 'assigned_to', 'reviewed_by'] as $column) {
                $t->dropConstrainedForeignId($column);
            }
            $t->dropIndex(['source', 'status']);
            $t->dropColumn(['source', 'booking_snapshot', 'requested_solution', 'proposed_solution', 'proposed_amount', 'review_note', 'reviewed_at']);
        });
    }
};

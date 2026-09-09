<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', fn (Blueprint $t) => $t->timestamp('processing_started_at')->nullable());
        Schema::table('incident_updates', fn (Blueprint $t) => $t->string('event_type', 40)->nullable());
    }

    public function down(): void
    {
        Schema::table('refund_requests', fn (Blueprint $t) => $t->dropColumn('processing_started_at'));
        Schema::table('incident_updates', fn (Blueprint $t) => $t->dropColumn('event_type'));
    }
};

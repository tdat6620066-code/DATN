<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transaction_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('old_status')->nullable();
            $t->string('new_status')->nullable();
            $t->decimal('amount', 12, 2)->nullable();
            $t->text('note')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transaction_logs');
    }
};

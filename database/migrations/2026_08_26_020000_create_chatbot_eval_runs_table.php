<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_eval_runs', function (Blueprint $table) {
            $table->id();
            $table->string('version', 100)->index();
            $table->string('mode', 20)->default('offline');
            $table->string('status', 20)->default('RUNNING');
            $table->unsignedSmallInteger('total')->default(0);
            $table->unsignedSmallInteger('passed')->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->json('category_scores')->nullable();
            $table->json('failures')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_eval_runs');
    }
};

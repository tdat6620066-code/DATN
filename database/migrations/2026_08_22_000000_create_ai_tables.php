<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->text('input')->nullable();
            $table->json('context')->nullable();
            $table->json('output')->nullable();
            $table->string('status', 20)->default('SUCCESS');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('ai_review_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sentiment', 20);
            $table->decimal('confidence', 5, 4);
            $table->json('topics')->nullable();
            $table->text('summary')->nullable();
            $table->string('model_version')->default('rules-v1');
            $table->timestamps();
        });

        Schema::create('ai_demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->decimal('occupancy_rate', 5, 2);
            $table->unsignedInteger('predicted_bookings')->default(0);
            $table->string('demand_level', 20);
            $table->json('recommendations')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['court_id', 'time_slot_id', 'forecast_date'], 'ai_forecast_unique');
        });

        Schema::create('ai_promotion_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('segment', 30);
            $table->string('title');
            $table->text('reason');
            $table->unsignedTinyInteger('discount_percent');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 20)->default('SUGGESTED');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_promotion_recommendations');
        Schema::dropIfExists('ai_demand_forecasts');
        Schema::dropIfExists('ai_review_analyses');
        Schema::dropIfExists('ai_interactions');
    }
};

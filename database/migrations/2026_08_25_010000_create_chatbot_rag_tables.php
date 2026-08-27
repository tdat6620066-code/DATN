<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->json('embedding')->nullable();
            $table->string('content_hash', 64);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['source_type', 'source_id']);
            $table->index(['active', 'source_type']);
        });

        Schema::create('chatbot_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_log_id')->constrained('chatbot_logs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('rating', ['UP', 'DOWN']);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['chatbot_log_id', 'user_id']);
        });

        Schema::create('chatbot_unanswered', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_log_id')->nullable()->constrained('chatbot_logs')->nullOnDelete();
            $table->text('question');
            $table->string('intent', 80)->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->enum('status', ['OPEN', 'RESOLVED', 'IGNORED'])->default('OPEN');
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'occurrences']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_unanswered');
        Schema::dropIfExists('chatbot_feedback');
        Schema::dropIfExists('chatbot_documents');
    }
};

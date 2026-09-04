<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60)->index();
            $table->string('question_hash', 64)->unique();
            $table->text('question');
            $table->text('answer');
            $table->json('keywords');
            $table->unsignedSmallInteger('priority')->default(5);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_faqs');
    }
};

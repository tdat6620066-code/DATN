<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('intent', 100)->unique();
            $table->json('keywords');
            $table->text('answer');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_knowledge');
    }
};

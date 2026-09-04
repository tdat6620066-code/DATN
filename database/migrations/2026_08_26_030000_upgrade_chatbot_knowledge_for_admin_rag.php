<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_knowledge', function (Blueprint $table) {
            $table->string('title')->nullable()->after('intent');
            $table->string('category', 80)->default('FAQ')->after('title');
            $table->string('sync_status', 20)->default('PENDING')->after('active');
            $table->text('sync_error')->nullable()->after('sync_status');
            $table->timestamp('synced_at')->nullable()->after('sync_error');
            $table->foreignId('updated_by')->nullable()->after('synced_at')->constrained('users')->nullOnDelete();
            $table->index(['category', 'active']);
            $table->index('sync_status');
        });

        Schema::table('chatbot_documents', function (Blueprint $table) {
            $table->dropUnique('chatbot_documents_source_type_source_id_unique');
            $table->unsignedSmallInteger('chunk_index')->default(0)->after('source_id');
            $table->unique(['source_type', 'source_id', 'chunk_index'], 'chatbot_documents_source_chunk_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_documents', function (Blueprint $table) {
            $table->dropUnique('chatbot_documents_source_chunk_unique');
            $table->dropColumn('chunk_index');
            $table->unique(['source_type', 'source_id']);
        });
        Schema::table('chatbot_knowledge', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['category', 'active']);
            $table->dropIndex(['sync_status']);
            $table->dropColumn(['title', 'category', 'sync_status', 'sync_error', 'synced_at', 'updated_by']);
        });
    }
};

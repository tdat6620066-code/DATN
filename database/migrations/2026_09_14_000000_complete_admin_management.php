<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->text('description')->nullable();
            $table->string('image')->nullable(); $table->string('website')->nullable();
            $table->string('status')->default('ACTIVE'); $table->timestamps();
        });
        Schema::create('access_roles', function (Blueprint $table) {
            $table->id(); $table->string('name')->unique(); $table->json('permissions'); $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->change();
            $table->foreignId('access_role_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_segment')->default('REGULAR');
        });
        Schema::table('service_items', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id(); $table->foreignId('service_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->integer('quantity'); $table->unsignedInteger('balance'); $table->string('reason', 1000); $table->timestamps();
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary(); $table->text('value')->nullable();
        });
        Schema::create('contact_threads', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('subject'); $table->string('status')->default('OPEN'); $table->timestamps();
        });
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id(); $table->foreignId('contact_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); $table->text('body'); $table->timestamps();
        });
        Schema::table('bookings', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::dropIfExists('contact_messages'); Schema::dropIfExists('contact_threads');
        Schema::dropIfExists('system_settings'); Schema::dropIfExists('stock_movements');
        Schema::table('service_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('brand_id'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_role_id'); $table->dropColumn('customer_segment');
        });
        Schema::dropIfExists('access_roles'); Schema::dropIfExists('brands');
    }
};

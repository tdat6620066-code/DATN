<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('email')->unique();

            $table->string('phone', 20)->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
<<<<<<< HEAD

            $table->timestamp('phone_verified_at')->nullable();

            $table->string('password')->nullable();

            $table->enum('role', [
                'CUSTOMER',
                'ADMIN'
            ])->default('CUSTOMER');

            $table->enum('status', [
                'ACTIVE',
                'LOCKED'
            ])->default('ACTIVE');

            $table->string('avatar')->nullable();

            $table->text('address')->nullable();

            $table->string('google_id')->nullable()->unique();

            $table->timestamp('last_login_at')->nullable();

=======
            $table->string('password');
            $table->enum('role', ['CUSTOMER', 'EMPLOYEE', 'ADMIN'])->default('CUSTOMER');
            $table->string('phone', 30)->nullable();
            $table->enum('status', ['ACTIVE', 'LOCKED'])->default('ACTIVE');
            $table->json('permissions')->nullable();
>>>>>>> 9790fd584874111b4e4d91e45e981ae25b3deaae
            $table->rememberToken();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
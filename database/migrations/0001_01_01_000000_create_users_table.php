<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {

            // ID
            $table->id();

            // Họ tên
            $table->string('name');

            // Email
            // Không cho phép trùng
            $table->string('email')->unique();

            // Số điện thoại
            // Không cho phép trùng
            $table->string('phone', 20)->unique();

            // Giữ lại để có thể sử dụng sau này nếu cần.
            // Hiện tại KHÔNG sử dụng để gửi OTP.
            $table->timestamp('email_verified_at')->nullable();

            // Giữ lại để có thể xác thực số điện thoại sau này.
            // Hiện tại KHÔNG sử dụng OTP.
            $table->timestamp('phone_verified_at')->nullable();

            // Mật khẩu
            $table->string('password');

            // Vai trò
            $table->enum('role', [
                'CUSTOMER',
                'ADMIN',
            ])->default('CUSTOMER');

            // Trạng thái tài khoản
            $table->enum('status', [
                'ACTIVE',
                'LOCKED',
            ])->default('ACTIVE');

            // Ảnh đại diện
            $table->string('avatar')->nullable();

            // Địa chỉ
            $table->text('address')->nullable();

            // ID tài khoản Google
            // Dùng cho chức năng đăng nhập bằng Google
            $table->string('google_id')
                ->nullable()
                ->unique();

            // Thời gian đăng nhập gần nhất
            $table->timestamp('last_login_at')->nullable();

            // Remember me
            $table->rememberToken();

            // created_at và updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
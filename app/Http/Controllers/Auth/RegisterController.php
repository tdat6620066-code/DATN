<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    /**
     * Hiển thị form đăng ký.
     */
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    /**
     * Xử lý đăng ký tài khoản.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,

                'email' => strtolower($request->email),

                'phone' => $request->phone,

                // Hash mật khẩu trước khi lưu
                'password' => Hash::make($request->password),

                // Tài khoản mặc định là khách hàng
                'role' => 'CUSTOMER',

                // Tài khoản hoạt động
                'status' => 'ACTIVE',

                // Hiện tại chưa triển khai Email Verification
                // nên cho phép đăng nhập ngay sau khi đăng ký
                'email_verified_at' => now(),

                // Khởi tạo số lần đăng nhập sai
                'failed_login_attempts' => 0,

                // Chưa bị khóa
                'locked_until' => null,

                // Chưa từng đăng nhập
                'last_login_at' => null,
            ]);

            DB::commit();

            return redirect()
                ->route('login')
                ->with(
                    'success',
                    'Đăng ký tài khoản thành công! Vui lòng đăng nhập.'
                );

        } catch (Throwable $e) {

            DB::rollBack();

            Log::error('Lỗi đăng ký tài khoản', [
                'message' => $e->getMessage(),
                'email' => $request->email,
            ]);

            return back()
                ->withInput(
                    $request->except(
                        'password',
                        'password_confirmation'
                    )
                )
                ->with(
                    'error',
                    'Đã xảy ra lỗi trong quá trình đăng ký. Vui lòng thử lại.'
                );
        }
    }
}
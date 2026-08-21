<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * =========================================================
     * HIỂN THỊ TRANG ĐĂNG KÝ
     * =========================================================
     */
    public function showRegister()
    {
        return view('auth.register');
    }


    /**
     * =========================================================
     * XỬ LÝ ĐĂNG KÝ
     *
     * KHÔNG GỬI OTP / EMAIL
     * =========================================================
     */
    public function register(Request $request)
    {
        try {

            // Validate dữ liệu
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    'unique:users,email',
                ],

                'phone' => [
                    'required',
                    'string',
                    'regex:/^(0|\+84)[0-9]{9,10}$/',
                    'unique:users,phone',
                ],

                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8),
                ],

                'terms' => [
                    'required',
                    'accepted',
                ],
            ], [

                'name.required' =>
                    'Vui lòng nhập họ tên.',

                'name.max' =>
                    'Họ tên không được vượt quá 255 ký tự.',

                'email.required' =>
                    'Vui lòng nhập Email.',

                'email.email' =>
                    'Email không đúng định dạng.',

                'email.unique' =>
                    'Email này đã được sử dụng.',

                'phone.required' =>
                    'Vui lòng nhập số điện thoại.',

                'phone.regex' =>
                    'Số điện thoại không hợp lệ.',

                'phone.unique' =>
                    'Số điện thoại này đã được sử dụng.',

                'password.required' =>
                    'Vui lòng nhập mật khẩu.',

                'password.confirmed' =>
                    'Mật khẩu xác nhận không khớp.',

                'password.min' =>
                    'Mật khẩu phải có ít nhất 8 ký tự.',

                'terms.required' =>
                    'Bạn phải đồng ý với điều khoản sử dụng.',

                'terms.accepted' =>
                    'Bạn phải đồng ý với điều khoản sử dụng.',
            ]);


            // Chuẩn hóa Email
            $email = strtolower(trim($validated['email']));

            // Chuẩn hóa số điện thoại
            $phone = trim($validated['phone']);


            // Kiểm tra lại Email
            if (User::where('email', $email)->exists()) {

                return back()
                    ->withInput()
                    ->withErrors([
                        'email' => 'Email này đã được sử dụng.',
                    ]);
            }


            // Kiểm tra lại số điện thoại
            if (User::where('phone', $phone)->exists()) {

                return back()
                    ->withInput()
                    ->withErrors([
                        'phone' => 'Số điện thoại này đã được sử dụng.',
                    ]);
            }


            // =====================================================
            // TẠO USER
            // =====================================================

            $user = User::create([

                'name' => trim($validated['name']),

                'email' => $email,

                'phone' => $phone,

                // Laravel tự hash nếu User model có cast password => hashed
                // nhưng Hash::make ở đây sẽ chắc chắn an toàn
                'password' => Hash::make(
                    $validated['password']
                ),

                // Role mặc định
                'role' => 'CUSTOMER',

                // Tài khoản hoạt động ngay
                'status' => 'ACTIVE',

            ]);


            // =====================================================
            // KHÔNG GỬI EMAIL / OTP
            // =====================================================

            // Không có:
            // event(new Registered($user));
            //
            // Không gửi OTP.
            // Không yêu cầu xác thực Email.


            // =====================================================
            // ĐĂNG NHẬP NGAY SAU KHI ĐĂNG KÝ
            // =====================================================

            Auth::login($user);

            // Tạo lại session để tránh session fixation
            $request->session()->regenerate();


            // =====================================================
            // CHUYỂN TRANG
            // =====================================================

            return redirect()
                ->route('home')
                ->with(
                    'success',
                    'Đăng ký tài khoản thành công!'
                );

        } catch (\Throwable $e) {

            // Ghi log lỗi
            Log::error(
                'Lỗi đăng ký tài khoản SmashZone',
                [
                    'message' => $e->getMessage(),
                    'email' => $request->email,
                ]
            );


            return back()
                ->withInput()
                ->with(
                    'error',
                    'Có lỗi xảy ra trong quá trình đăng ký. Vui lòng thử lại.'
                );
        }
    }


    /**
     * =========================================================
     * HIỂN THỊ TRANG ĐĂNG NHẬP
     * =========================================================
     */
    public function showLogin()
    {
        return view('auth.login');
    }


    /**
     * =========================================================
     * XỬ LÝ ĐĂNG NHẬP
     *
     * Email hoặc số điện thoại
     * =========================================================
     */
    public function login(Request $request)
    {
        // Validate
        $credentials = $request->validate([

            'login' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
            ],

        ], [

            'login.required' =>
                'Vui lòng nhập Email hoặc số điện thoại.',

            'password.required' =>
                'Vui lòng nhập mật khẩu.',

        ]);


        // Lấy thông tin đăng nhập
        $login = trim($credentials['login']);


        // =====================================================
        // TÌM USER
        // =====================================================

        $user = User::where('email', strtolower($login))
            ->orWhere('phone', $login)
            ->first();


        // Không tồn tại
        if (!$user) {

            return back()
                ->withInput(
                    $request->only('login')
                )
                ->withErrors([
                    'login' =>
                        'Email/số điện thoại hoặc mật khẩu không chính xác.',
                ]);
        }


        // =====================================================
        // KIỂM TRA TÀI KHOẢN
        // =====================================================

        if (
            isset($user->status)
            &&
            (
                $user->status === 'BLOCKED'
                ||
                $user->status === 'BANNED'
                ||
                $user->status === 0
            )
        ) {

            return back()
                ->withInput(
                    $request->only('login')
                )
                ->withErrors([
                    'login' =>
                        'Tài khoản của bạn đã bị khóa.',
                ]);
        }


        // =====================================================
        // KIỂM TRA PASSWORD
        // =====================================================

        if (!Hash::check(
            $credentials['password'],
            $user->password
        )) {

            return back()
                ->withInput(
                    $request->only('login')
                )
                ->withErrors([
                    'login' =>
                        'Email/số điện thoại hoặc mật khẩu không chính xác.',
                ]);
        }


        // =====================================================
        // ĐĂNG NHẬP
        // =====================================================

        Auth::login(
            $user,
            $request->boolean('remember')
        );


        // Chống session fixation
        $request->session()->regenerate();


        // =====================================================
        // GHI NHẬN THỜI GIAN ĐĂNG NHẬP
        // =====================================================

        if (
            isset($user->last_login_at)
        ) {

            $user->last_login_at = now();

            $user->save();

        }


        // =====================================================
        // CHUYỂN TRANG
        // =====================================================

        return redirect()
            ->intended(
                route('home')
            )
            ->with(
                'success',
                'Đăng nhập thành công!'
            );
    }


    /**
     * =========================================================
     * ĐĂNG XUẤT
     * =========================================================
     */
    public function logout(Request $request)
    {
        Auth::logout();


        // Xóa session
        $request->session()->invalidate();


        // Tạo CSRF token mới
        $request->session()->regenerateToken();


        return redirect()
            ->route('home')
            ->with(
                'success',
                'Đăng xuất thành công!'
            );
    }
}
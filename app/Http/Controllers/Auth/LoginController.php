<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    /**
     * Hiển thị form đăng nhập
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $login = strtolower(trim($request->login));

        /*
        |--------------------------------------------------------------------------
        | 1. Kiểm tra giới hạn đăng nhập
        |--------------------------------------------------------------------------
        */

        $throttleKey = 'login:' . $login . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {

            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('login'))
                ->with(
                    'error',
                    'Bạn đăng nhập sai quá nhiều lần. Vui lòng thử lại sau '
                    . ceil($seconds / 60)
                    . ' phút.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 2. Tìm tài khoản bằng email hoặc số điện thoại
        |--------------------------------------------------------------------------
        */

        $user = User::where('email', $login)
            ->orWhere('phone', $login)
            ->first();


        /*
        |--------------------------------------------------------------------------
        | 3. Tài khoản không tồn tại
        |--------------------------------------------------------------------------
        */

        if (!$user) {

            RateLimiter::hit($throttleKey, 900);

            return back()
                ->withInput($request->only('login'))
                ->with(
                    'error',
                    'Thông tin đăng nhập không chính xác.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 4. Kiểm tra tài khoản bị khóa
        |--------------------------------------------------------------------------
        */

        if ($user->status === 'LOCKED') {

            // Nếu thời gian khóa đã hết thì mở khóa
            if (
                $user->locked_until &&
                now()->greaterThanOrEqualTo($user->locked_until)
            ) {
                $user->update([
                    'status' => 'ACTIVE',
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);
            } else {

                return back()
                    ->withInput($request->only('login'))
                    ->with(
                        'error',
                        'Tài khoản của bạn đang bị khóa. Vui lòng thử lại sau.'
                    );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | 5. Kiểm tra email đã xác thực
        |--------------------------------------------------------------------------
        */

        if (!$user->email_verified_at) {

            return back()
                ->withInput($request->only('login'))
                ->with(
                    'error',
                    'Email của bạn chưa được xác thực. Vui lòng xác thực email trước khi đăng nhập.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Kiểm tra mật khẩu
        |--------------------------------------------------------------------------
        */

        if (!Hash::check($request->password, $user->password)) {

            RateLimiter::hit($throttleKey, 900);

            $user->increment('failed_login_attempts');

            /*
             * Sai 5 lần → khóa 15 phút
             */
            if ($user->failed_login_attempts >= 5) {

                $user->update([
                    'status' => 'LOCKED',
                    'locked_until' => now()->addMinutes(15),
                ]);

                return back()
                    ->withInput($request->only('login'))
                    ->with(
                        'error',
                        'Bạn đã nhập sai mật khẩu quá 5 lần. Tài khoản tạm thời bị khóa 15 phút.'
                    );
            }

            $remaining =
                5 - $user->failed_login_attempts;

            return back()
                ->withInput($request->only('login'))
                ->with(
                    'error',
                    'Mật khẩu không chính xác. Bạn còn '
                    . $remaining
                    . ' lần thử.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | 7. Đăng nhập thành công
        |--------------------------------------------------------------------------
        */

        RateLimiter::clear($throttleKey);

        Auth::login(
            $user,
            $request->boolean('remember')
        );


        /*
        |--------------------------------------------------------------------------
        | 8. Tạo session mới chống session fixation
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();


        /*
        |--------------------------------------------------------------------------
        | 9. Reset số lần đăng nhập sai
        |--------------------------------------------------------------------------
        */

        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'status' => 'ACTIVE',
        ]);


        /*
        |--------------------------------------------------------------------------
        | 10. Chuyển hướng
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->intended(route('home'))
            ->with(
                'success',
                'Đăng nhập thành công!'
            );
    }


    /**
     * Đăng xuất
     */
    /**
 * Đăng xuất tài khoản
 */
public function logout(Request $request): RedirectResponse
{
    try {

        // 1. Kiểm tra người dùng đang đăng nhập
        if (!Auth::check()) {

            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'
                );
        }

        // 2. Đăng xuất khỏi hệ thống
        Auth::logout();

        // 3. Hủy toàn bộ session hiện tại
        $request->session()->invalidate();

        // 4. Tạo CSRF token mới
        $request->session()->regenerateToken();

        // 5. Chuyển về trang chủ
        return redirect()
            ->route('home')
            ->with(
                'success',
                'Đăng xuất thành công!'
            );

    } catch (Throwable $e) {

        // Ghi log khi có lỗi
        Log::error('Lỗi đăng xuất tài khoản', [
            'message' => $e->getMessage(),
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('home')
            ->with(
                'error',
                'Có lỗi xảy ra trong quá trình đăng xuất.'
            );
    }
}
}

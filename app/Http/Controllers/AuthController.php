<?php

namespace App\Http\Controllers;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | UC01 - REGISTER
    |--------------------------------------------------------------------------
    */

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => strtolower($request->email),
                'phone' => $request->phone,
                'password' => $request->password,
                'role' => 'CUSTOMER',
                'status' => 'ACTIVE',
            ]);

            /*
             * Laravel sẽ gửi email verification
             * thông qua Registered event.
             */
            event(new Registered($user));

            Auth::login($user);

            $request->session()->regenerate();

            return redirect()
                ->route('verification.notice')
                ->with(
                    'success',
                    'Đăng ký thành công. Vui lòng kiểm tra Email để xác thực tài khoản.'
                );

        } catch (Throwable $e) {

            report($e);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'register' => 'Có lỗi xảy ra trong quá trình đăng ký. Vui lòng thử lại.',
                ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UC02 - LOGIN
    |--------------------------------------------------------------------------
    */


    public function showLogin()
    {
        return view('auth.login');
    }


    public function login(LoginRequest $request)
    {
        $login = trim($request->login);

        $field = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'phone';

        $credentials = [
            $field => $login,
            'password' => $request->password,
        ];

        /*
         * Kiểm tra tài khoản có tồn tại và bị khóa không
         */
        $user = User::where($field, $login)->first();

        if (!$user) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors([
                    'login' => 'Thông tin đăng nhập không chính xác.',
                ]);
        }

        /*
         * UC02 - E4
         */
        if ($user->status === 'LOCKED') {
            return back()
                ->withInput($request->only('login'))
                ->withErrors([
                    'login' => 'Tài khoản của bạn đã bị khóa.',
                ]);
        }

        /*
         * Kiểm tra email verification
         */
        if (!$user->hasVerifiedEmail()) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors([
                    'login' => 'Email chưa được xác thực. Vui lòng kiểm tra Email.',
                ]);
        }

        /*
         * Đăng nhập
         */
        if (Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {

            $request->session()->regenerate();

            $user->update([
                'last_login_at' => now(),
            ]);

            return redirect()->intended(
                route('home')
            );
        }

        return back()
            ->withInput($request->only('login'))
            ->withErrors([
                'login' => 'Thông tin đăng nhập không chính xác.',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UC03 - LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('success', 'Bạn đã đăng xuất thành công.');
    }

    /*
    |--------------------------------------------------------------------------
    | UC04 - FORGOT PASSWORD
    |--------------------------------------------------------------------------
    */

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
            ],
        ], [
            'email.required' => 'Vui lòng nhập Email.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with(
                'success',
                'Đã gửi liên kết đặt lại mật khẩu vào Email của bạn.'
            );
        }

        return back()->withErrors([
            'email' => 'Không thể gửi Email đặt lại mật khẩu.',
        ]);
    }

    public function showResetPassword(
        Request $request,
        string $token
    ) {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => [
                'required',
            ],

            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'confirmed',
                'min:8',
            ],
        ], [
            'email.required' => 'Vui lòng nhập Email.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ]);

        $status = Password::reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),
            function (User $user, string $password) {

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => null,
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {

            return redirect()
                ->route('login')
                ->with(
                    'success',
                    'Mật khẩu đã được đặt lại thành công.'
                );
        }

        return back()->withErrors([
            'email' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UC10 - GOOGLE LOGIN
    |--------------------------------------------------------------------------
    */

    public function redirectGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleCallback()
    {
        try {

            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if (!$user) {

                $user = User::create([
                    'name' => $googleUser->getName()
                        ?: $googleUser->getNickname()
                        ?: 'Google User',

                    'email' => strtolower($googleUser->getEmail()),

                    'phone' => null,

                    'password' => null,

                    'role' => 'CUSTOMER',

                    'status' => 'ACTIVE',

                    'google_id' => $googleUser->getId(),

                    'avatar' => $googleUser->getAvatar(),

                    'email_verified_at' => now(),
                ]);

            } else {

                /*
                 * Nếu tài khoản tồn tại bằng Email
                 * thì liên kết Google vào tài khoản đó.
                 */
                $user->update([
                    'google_id' => $googleUser->getId(),

                    'email_verified_at' =>
                        $user->email_verified_at ?? now(),

                    'avatar' =>
                        $user->avatar ?? $googleUser->getAvatar(),
                ]);
            }

            if ($user->status === 'LOCKED') {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'login' => 'Tài khoản của bạn đã bị khóa.',
                    ]);
            }

            Auth::login($user, true);

            request()->session()->regenerate();

            $user->update([
                'last_login_at' => now(),
            ]);

            return redirect()->intended(
                route('home')
            );

        } catch (Throwable $e) {

            report($e);

            return redirect()
                ->route('login')
                ->withErrors([
                    'login' => 'Đăng nhập bằng Google thất bại.',
                ]);
        }
    }
}



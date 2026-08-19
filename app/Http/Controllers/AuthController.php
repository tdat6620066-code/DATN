<?php

namespace App\Http\Controllers;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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

            // Tạo và gửi mã xác thực 4 số về Email.
            $this->sendVerificationCode($user);

            session(['verification_email' => $user->email]);

            return redirect()
                ->route('verification.code')
                ->with(
                    'success',
                    'Đăng ký thành công. Chúng tôi đã gửi mã xác thực 4 số đến Email của bạn.'
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

    /**
     * Hiển thị form nhập mã xác thực 4 số.
     */
    public function showVerificationCode()
    {
        $email = session('verification_email');

        if (! $email) {
            return redirect()->route('login');
        }

        return view('auth.verify-code', ['email' => $email]);
    }

    /**
     * Xác thực mã 4 số và kích hoạt tài khoản.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'digits:4'],
        ], [
            'code.required' => 'Vui lòng nhập mã xác thực.',
            'code.digits' => 'Mã xác thực phải gồm 4 chữ số.',
        ]);

        $email = session('verification_email');

        if (! $email) {
            return redirect()
                ->route('login')
                ->withErrors(['code' => 'Phiên xác thực đã hết hạn. Vui lòng đăng nhập lại.']);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()
                ->route('login')
                ->withErrors(['code' => 'Không tìm thấy tài khoản.']);
        }

        if ($user->hasVerifiedEmail()) {
            Auth::login($user);
            $request->session()->regenerate();
            session()->forget('verification_email');

            return redirect()
                ->route('home')
                ->with('success', 'Tài khoản của bạn đã được kích hoạt.');
        }

        if (! $user->verification_code || $user->verification_code !== $request->code) {
            return back()
                ->withErrors(['code' => 'Mã xác thực không đúng.'])
                ->withInput();
        }

        if ($user->verification_code_expires_at < now()) {
            return back()
                ->withErrors(['code' => 'Mã xác thực đã hết hạn. Vui lòng gửi lại mã mới.'])
                ->withInput();
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();
        session()->forget('verification_email');

        return redirect()
            ->route('home')
            ->with('success', 'Tài khoản đã được kích hoạt thành công. Chào mừng bạn đến với SmashZone!');
    }

    /**
     * Gửi lại mã xác thực 4 số.
     */
    public function resendVerificationCode()
    {
        $email = session('verification_email');
        $user = $email ? User::where('email', $email)->first() : null;

        if (! $user || $user->hasVerifiedEmail()) {
            return redirect()->route('login');
        }

        $this->sendVerificationCode($user);

        return back()->with('success', 'Mã xác thực mới đã được gửi đến Email của bạn.');
    }

    /**
     * Tạo mã 4 số, lưu vào user và gửi Email.
     */
    private function sendVerificationCode(User $user): void
    {
        $code = (string) random_int(1000, 9999);

        $user->forceFill([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(10),
        ])->save();

        Mail::to($user->email)->send(new VerificationCodeMail($user, $code));
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
            session(['verification_email' => $user->email]);

            return redirect()
                ->route('verification.code')
                ->withErrors([
                    'code' => 'Tài khoản chưa được kích hoạt. Vui lòng nhập mã xác thực đã gửi đến Email.',
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



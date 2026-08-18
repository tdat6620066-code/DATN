<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('home');
})->name('home');


/*
|--------------------------------------------------------------------------
| GUEST
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    /*
     * UC01 - Register
     */
    Route::get('/register', [
        AuthController::class,
        'showRegister'
    ])->name('register');

    Route::post('/register', [
        AuthController::class,
        'register'
    ])->name('register.store');


    /*
     * UC02 - Login
     */
    Route::get('/login', [
        AuthController::class,
        'showLogin'
    ])->name('login');

    Route::post('/login', [
        AuthController::class,
        'login'
    ])->middleware('throttle:5,1')
      ->name('login.store');


    /*
     * UC04 - Forgot password
     */
    Route::get('/forgot-password', [
        AuthController::class,
        'showForgotPassword'
    ])->name('password.request');

    Route::post('/forgot-password', [
        AuthController::class,
        'sendResetLink'
    ])->middleware('throttle:3,1')
      ->name('password.email');

    Route::get('/reset-password/{token}', [
        AuthController::class,
        'showResetPassword'
    ])->name('password.reset');

    Route::post('/reset-password', [
        AuthController::class,
        'resetPassword'
    ])->name('password.update');


    /*
     * UC10 - Google
     */
    Route::get('/auth/google', [
        AuthController::class,
        'redirectGoogle'
    ])->name('google.redirect');

    Route::get('/auth/google/callback', [
        AuthController::class,
        'googleCallback'
    ])->name('google.callback');
});


/*
|--------------------------------------------------------------------------
| AUTHENTICATED
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
     * UC03 - Logout
     */
    Route::post('/logout', [
        AuthController::class,
        'logout'
    ])->name('logout');


    /*
     * UC05 - Profile
     */
    Route::get('/profile', [
        ProfileController::class,
        'index'
    ])->name('profile');

    Route::put('/profile', [
        ProfileController::class,
        'update'
    ])->name('profile.update');


    /*
     * UC06 - Change password
     */
    Route::get('/profile/change-password', [
        ProfileController::class,
        'showChangePassword'
    ])->name('password.change');

    Route::put('/profile/change-password', [
        ProfileController::class,
        'changePassword'
    ])->name('password.change.update');


    /*
     * UC08 - Favorites
     */
    Route::get('/favorites', [
        FavoriteController::class,
        'index'
    ])->name('favorites.index');

    Route::post('/favorites/{court}', [
        FavoriteController::class,
        'store'
    ])->name('favorites.store');

    Route::delete('/favorites/{court}', [
        FavoriteController::class,
        'destroy'
    ])->name('favorites.destroy');


    /*
     * UC09 - Notifications
     */
    Route::get('/notifications', [
        NotificationController::class,
        'index'
    ])->name('notifications.index');

    Route::patch('/notifications/{notification}/read', [
        NotificationController::class,
        'markAsRead'
    ])->name('notifications.read');

    Route::patch('/notifications/read-all', [
        NotificationController::class,
        'markAllAsRead'
    ])->name('notifications.read-all');
});


/*
|--------------------------------------------------------------------------
| EMAIL VERIFICATION
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::get(
        '/email/verify/{id}/{hash}',
        function (
            \Illuminate\Foundation\Auth\EmailVerificationRequest $request
        ) {
            $request->fulfill();

            return redirect()
                ->route('home')
                ->with(
                    'success',
                    'Email của bạn đã được xác thực thành công.'
                );
        }
    )->middleware('signed')
     ->name('verification.verify');

    Route::post(
        '/email/verification-notification',
        function (\Illuminate\Http\Request $request) {

            $request->user()->sendEmailVerificationNotification();

            return back()->with(
                'success',
                'Đã gửi lại Email xác thực.'
            );
        }
    )->middleware('throttle:6,1')
     ->name('verification.send');
});
<?php

use App\Http\Controllers\AdminAnnouncementController;
// Controllers
use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\AdminCourtController;
use App\Http\Controllers\AdminCourtTypeController;
use App\Http\Controllers\AdminCustomerController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminEmployeeController;
use App\Http\Controllers\AdminIncidentController;
use App\Http\Controllers\AdminMaintenanceController;
use App\Http\Controllers\AdminKnowledgeBaseController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AdminPricingController;
use App\Http\Controllers\AdminVoucherController;
// Admin Controllers
use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatbotAnalyticsController;
use App\Http\Controllers\ChatbotFeedbackController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\EmployeeBookingController;
use App\Http\Controllers\EmployeeCourtController;
use App\Http\Controllers\EmployeeDashboardController;
use App\Http\Controllers\EmployeeIncidentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
// Employee Controllers
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefundRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
*/

Route::get('/', [
    HomeController::class,
    'index',
])->middleware(['auth', 'active'])->name('home');

/*
|--------------------------------------------------------------------------
| GUEST
|--------------------------------------------------------------------------
|
| Chỉ người chưa đăng nhập mới được truy cập.
|
*/

Route::middleware('guest')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | UC01 - ĐĂNG KÝ
    |--------------------------------------------------------------------------
    */

    Route::get('/register', [
        AuthController::class,
        'showRegister',
    ])->name('register');

    Route::post('/register', [
        AuthController::class,
        'register',
    ])->name('register.store');

    /*
    |--------------------------------------------------------------------------
    | UC02 - ĐĂNG NHẬP
    |--------------------------------------------------------------------------
    */

    Route::get('/login', [
        AuthController::class,
        'showLogin',
    ])->name('login');

    Route::post('/login', [
        AuthController::class,
        'login',
    ])
        ->middleware('throttle:5,1')
        ->name('login.store');

    /*
    |--------------------------------------------------------------------------
    | UC04 - QUÊN MẬT KHẨU
    |--------------------------------------------------------------------------
    |
    | Lưu ý:
    | Phần này là reset password qua Email.
    | Không liên quan đến OTP đăng ký.
    |
    */

    Route::get('/forgot-password', [
        AuthController::class,
        'showForgotPassword',
    ])->name('password.request');

    Route::post('/forgot-password', [
        AuthController::class,
        'sendResetLink',
    ])
        ->middleware('throttle:3,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [
        AuthController::class,
        'showResetPassword',
    ])->name('password.reset');

    Route::post('/reset-password', [
        AuthController::class,
        'resetPassword',
    ])->name('password.update');

    /*
    |--------------------------------------------------------------------------
    | UC10 - ĐĂNG NHẬP GOOGLE
    |--------------------------------------------------------------------------
    */

    Route::get('/auth/google', [
        AuthController::class,
        'redirectGoogle',
    ])->name('google.redirect');

    Route::get('/auth/google/callback', [
        AuthController::class,
        'googleCallback',
    ])->name('google.callback');
});

/*
|--------------------------------------------------------------------------
| PUBLIC COURT ROUTES
|--------------------------------------------------------------------------
|
| Không cần đăng nhập để xem sân.
|
*/

Route::middleware(['auth', 'active'])->group(function () {
Route::get('/courts', [
    CourtController::class,
    'index',
])->name('courts.index');

Route::get('/courts/{court}', [
    CourtController::class,
    'show',
])->name('courts.show');

Route::get('/courts/{court}/availability', [
    CourtController::class,
    'availability',
])->name('courts.availability');
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED - KHÁCH HÀNG
|--------------------------------------------------------------------------
|
| Các chức năng yêu cầu đăng nhập.
|
*/

Route::middleware(['auth', 'active'])->group(function () {

    /* UC24-UC28 - AI APIs */
    Route::prefix('api/ai')->name('api.ai.')->group(function () {
        Route::get('/courts/recommendations', [AiController::class, 'courts'])->name('courts');
        Route::post('/chat', [ChatController::class, 'chat'])->middleware('throttle:chatbot')->name('chat');
        Route::post('/chat/stream', [ChatController::class, 'stream'])->middleware('throttle:chatbot')->name('chat.stream');
        Route::post('/chat/{chatbotLog}/feedback', [ChatbotFeedbackController::class, 'store'])->middleware('throttle:chatbot')->name('chat.feedback');
        Route::get('/promotions/me', [AiController::class, 'promotion'])->name('promotions.me');
        Route::get('/demand-forecast', [AiController::class, 'forecast'])->name('forecast');
        Route::post('/reviews/analyze', [AiController::class, 'reviews'])->name('reviews.analyze');
        Route::post('/promotions/customers/{customer}', [AiController::class, 'customerPromotion'])->name('promotions.customer');
    });

    /*
    |--------------------------------------------------------------------------
    | UC03 - ĐĂNG XUẤT
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [
        AuthController::class,
        'logout',
    ])->name('logout');

    /*
    |--------------------------------------------------------------------------
    | UC05 - QUẢN LÝ THÔNG TIN CÁ NHÂN
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [
        ProfileController::class,
        'index',
    ])->name('profile');

    Route::put('/profile', [
        ProfileController::class,
        'update',
    ])->name('profile.update');

    /*
    |--------------------------------------------------------------------------
    | UC06 - ĐỔI MẬT KHẨU
    |--------------------------------------------------------------------------
    */

    Route::get('/profile/change-password', [
        ProfileController::class,
        'showChangePassword',
    ])->name('password.change');

    Route::put('/profile/change-password', [
        ProfileController::class,
        'changePassword',
    ])->name('password.change.update');

    /*
    |--------------------------------------------------------------------------
    | UC08 - SÂN YÊU THÍCH
    |--------------------------------------------------------------------------
    */

    Route::get('/favorites', [
        FavoriteController::class,
        'index',
    ])->name('favorites.index');

    Route::post('/favorites/{court}', [
        FavoriteController::class,
        'store',
    ])->name('favorites.store');

    Route::delete('/favorites/{court}', [
        FavoriteController::class,
        'destroy',
    ])->name('favorites.destroy');

    /*
    |--------------------------------------------------------------------------
    | UC09 - THÔNG BÁO
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [
        NotificationController::class,
        'index',
    ])->name('notifications.index');

    Route::patch('/notifications/{notification}/read', [
        NotificationController::class,
        'markAsRead',
    ])->name('notifications.read');

    Route::patch('/notifications/read-all', [
        NotificationController::class,
        'markAllAsRead',
    ])->name('notifications.read-all');

});

/*
|--------------------------------------------------------------------------
| EMPLOYEE
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'active',
    'role:EMPLOYEE',
])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [
            EmployeeDashboardController::class,
            'index',
        ])
            ->middleware('permission:employee.dashboard')
            ->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | LỊCH LÀM VIỆC
        |--------------------------------------------------------------------------
        */

        Route::get('/schedule', [
            EmployeeDashboardController::class,
            'schedule',
        ])
            ->middleware('permission:employee.dashboard')
            ->name('schedule');

        /*
        |--------------------------------------------------------------------------
        | BOOKING
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:bookings.view')->group(function () {

            Route::get('/bookings', [
                EmployeeBookingController::class,
                'index',
            ])->name('bookings.index');

            Route::get('/bookings/{booking}', [
                EmployeeBookingController::class,
                'show',
            ])->name('bookings.show');

        });

        /*
        |--------------------------------------------------------------------------
        | CHECK-IN
        |--------------------------------------------------------------------------
        */

        Route::post('/bookings/{booking}/check-in', [
            EmployeeBookingController::class,
            'checkIn',
        ])
            ->middleware('permission:bookings.checkin')
            ->name('bookings.check-in');

        /*
        |--------------------------------------------------------------------------
        | COMPLETE BOOKING
        |--------------------------------------------------------------------------
        */

        Route::post('/bookings/{booking}/complete', [
            EmployeeBookingController::class,
            'complete',
        ])
            ->middleware('permission:bookings.checkout')
            ->name('bookings.complete');

        /*
        |--------------------------------------------------------------------------
        | PAYMENT
        |--------------------------------------------------------------------------
        */

        Route::post('/bookings/{booking}/payment', [
            EmployeeBookingController::class,
            'pay',
        ])
            ->middleware('permission:payments.counter')
            ->name('bookings.payment');

        /*
        |--------------------------------------------------------------------------
        | SERVICES
        |--------------------------------------------------------------------------
        */

        Route::post('/bookings/{booking}/services', [
            EmployeeBookingController::class,
            'addService',
        ])
            ->middleware('permission:services.manage')
            ->name('bookings.services.store');

        Route::delete('/bookings/{booking}/services/{service}', [
            EmployeeBookingController::class,
            'removeService',
        ])
            ->middleware('permission:services.manage')
            ->name('bookings.services.destroy');

        /*
        |--------------------------------------------------------------------------
        | INCIDENTS
        |--------------------------------------------------------------------------
        */

        Route::get('/incidents', [
            EmployeeIncidentController::class,
            'index',
        ])
            ->middleware('permission:incidents.manage')
            ->name('incidents.index');

        Route::post('/incidents', [
            EmployeeIncidentController::class,
            'store',
        ])
            ->middleware('permission:incidents.manage')
            ->name('incidents.store');

        /*
        |--------------------------------------------------------------------------
        | COURTS
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:courts.status.manage')->group(function () {

            Route::get('/courts', [
                EmployeeCourtController::class,
                'index',
            ])->name('courts.index');

            Route::get('/courts/{court}/edit', [
                EmployeeCourtController::class,
                'edit',
            ])->name('courts.edit');

            Route::put('/courts/{court}', [
                EmployeeCourtController::class,
                'update',
            ])->name('courts.update');

        });

        /*
        |--------------------------------------------------------------------------
        | REFUND REQUESTS
        |--------------------------------------------------------------------------
        */

        Route::middleware('permission:refunds.manage')->group(function () {

            Route::get('/refund-requests', [
                RefundRequestController::class,
                'index',
            ])->name('refund-requests.index');

            Route::get('/refund-requests/{refundRequest}', [
                RefundRequestController::class,
                'show',
            ])->name('refund-requests.show');

            Route::post('/refund-requests/{refundRequest}/review', [
                RefundRequestController::class,
                'review',
            ])->name('refund-requests.review');

        });

    });

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'active',
    'role:ADMIN',
])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [
            AdminDashboardController::class,
            'index',
        ])->name('dashboard');

        Route::get('/chatbot-analytics', [ChatbotAnalyticsController::class, 'index'])
            ->name('chatbot-analytics');
        Route::post('/chatbot-analytics/unanswered/{unanswered}/resolve', [ChatbotAnalyticsController::class, 'resolve'])
            ->name('chatbot-analytics.resolve');
        Route::resource('knowledge-base', AdminKnowledgeBaseController::class)->parameters(['knowledge-base' => 'knowledge'])->except(['show']);
        Route::post('/knowledge-base/{knowledge}/sync', [AdminKnowledgeBaseController::class, 'sync'])->name('knowledge-base.sync');
        Route::post('/knowledge-base/{knowledge}/toggle', [AdminKnowledgeBaseController::class, 'toggle'])->name('knowledge-base.toggle');

        /*
        |--------------------------------------------------------------------------
        | COURTS
        |--------------------------------------------------------------------------
        */

        Route::resource('courts', AdminCourtController::class)
            ->except(['show']);

        /*
        |--------------------------------------------------------------------------
        | COURT TYPES
        |--------------------------------------------------------------------------
        */

        Route::resource('court-types', AdminCourtTypeController::class)
            ->except(['show']);

        /*
        |--------------------------------------------------------------------------
        | PRICING
        |--------------------------------------------------------------------------
        */

        Route::get('/pricing', [
            AdminPricingController::class,
            'index',
        ])->name('pricing.index');

        Route::post('/pricing/slots', [
            AdminPricingController::class,
            'storeSlot',
        ])->name('pricing.slots.store');

        Route::put('/pricing/slots/{timeSlot}', [
            AdminPricingController::class,
            'updateSlot',
        ])->name('pricing.slots.update');

        Route::delete('/pricing/slots/{timeSlot}', [
            AdminPricingController::class,
            'destroySlot',
        ])->name('pricing.slots.destroy');

        Route::put('/pricing/prices', [
            AdminPricingController::class,
            'updatePrices',
        ])->name('pricing.prices.update');

        Route::post('/pricing/holidays', [
            AdminPricingController::class,
            'storeHoliday',
        ])->name('pricing.holidays.store');

        Route::delete('/pricing/holidays/{holiday}', [
            AdminPricingController::class,
            'destroyHoliday',
        ])->name('pricing.holidays.destroy');

        /*
        |--------------------------------------------------------------------------
        | MAINTENANCE
        |--------------------------------------------------------------------------
        */

        Route::get('/maintenance', [
            AdminMaintenanceController::class,
            'index',
        ])->name('maintenance.index');

        Route::post('/maintenance', [
            AdminMaintenanceController::class,
            'store',
        ])->name('maintenance.store');

        Route::put('/maintenance/{maintenance}/cancel', [
            AdminMaintenanceController::class,
            'cancel',
        ])->name('maintenance.cancel');

        /*
        |--------------------------------------------------------------------------
        | CUSTOMERS
        |--------------------------------------------------------------------------
        */

        Route::get('/customers', [
            AdminCustomerController::class,
            'index',
        ])->name('customers.index');

        Route::get('/customers/{customer}', [
            AdminCustomerController::class,
            'show',
        ])->name('customers.show');

        Route::get('/customers/{customer}/edit', [
            AdminCustomerController::class,
            'edit',
        ])->name('customers.edit');

        Route::put('/customers/{customer}', [
            AdminCustomerController::class,
            'update',
        ])->name('customers.update');

        Route::put('/customers/{customer}/toggle-status', [
            AdminCustomerController::class,
            'toggleStatus',
        ])->name('customers.toggle-status');

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        Route::get('/employees', [
            AdminEmployeeController::class,
            'index',
        ])->name('employees.index');

        Route::get('/employees/create', [
            AdminEmployeeController::class,
            'create',
        ])->name('employees.create');

        Route::post('/employees', [
            AdminEmployeeController::class,
            'store',
        ])->name('employees.store');

        Route::get('/employees/{employee}/edit', [
            AdminEmployeeController::class,
            'edit',
        ])->name('employees.edit');

        Route::put('/employees/{employee}', [
            AdminEmployeeController::class,
            'update',
        ])->name('employees.update');

        Route::put('/employees/{employee}/toggle-status', [
            AdminEmployeeController::class,
            'toggleStatus',
        ])->name('employees.toggle-status');

        /*
        |--------------------------------------------------------------------------
        | BOOKINGS
        |--------------------------------------------------------------------------
        */

        Route::get('/bookings', [
            AdminBookingController::class,
            'index',
        ])->name('bookings.index');

        Route::get('/bookings/{booking}', [
            AdminBookingController::class,
            'show',
        ])->name('bookings.show');

        Route::put('/bookings/{booking}', [
            AdminBookingController::class,
            'update',
        ])->name('bookings.update');

        Route::put('/bookings/{booking}/cancel', [
            AdminBookingController::class,
            'cancel',
        ])->name('bookings.cancel');

        /*
        |--------------------------------------------------------------------------
        | PAYMENTS
        |--------------------------------------------------------------------------
        */

        Route::get('/payments', [
            AdminPaymentController::class,
            'index',
        ])->name('payments.index');

        Route::get('/payments/{payment}', [
            AdminPaymentController::class,
            'show',
        ])->name('payments.show');

        Route::put('/payments/{payment}/reconcile', [
            AdminPaymentController::class,
            'reconcile',
        ])->name('payments.reconcile');

        Route::put('/refund-requests/{refundRequest}/approve', [
            AdminPaymentController::class,
            'approveRefund',
        ])->name('payments.refunds.approve');

        Route::put('/refunds/{refund}/process', [
            AdminPaymentController::class,
            'processRefund',
        ])->name('payments.refunds.process');

        /*
        |--------------------------------------------------------------------------
        | VOUCHERS
        |--------------------------------------------------------------------------
        */

        Route::resource('vouchers', AdminVoucherController::class)
            ->except(['show']);

        Route::put('/vouchers/{voucher}/toggle', [
            AdminVoucherController::class,
            'toggle',
        ])->name('vouchers.toggle');

        /*
        |--------------------------------------------------------------------------
        | ANNOUNCEMENTS
        |--------------------------------------------------------------------------
        */

        Route::get('/announcements', [
            AdminAnnouncementController::class,
            'index',
        ])->name('announcements.index');

        Route::post('/announcements', [
            AdminAnnouncementController::class,
            'store',
        ])->name('announcements.store');

        Route::delete('/announcements/{announcement}', [
            AdminAnnouncementController::class,
            'destroy',
        ])->name('announcements.destroy');

        /*
        |--------------------------------------------------------------------------
        | INCIDENTS
        |--------------------------------------------------------------------------
        */

        Route::get('/incidents', [
            AdminIncidentController::class,
            'index',
        ])->name('incidents.index');

        Route::put('/incidents/{incident}', [
            AdminIncidentController::class,
            'update',
        ])->name('incidents.update');

    });

/*
|--------------------------------------------------------------------------
| BOOKING
|--------------------------------------------------------------------------
*/

/*
| Danh sách booking của người dùng.
*/

Route::get('/bookings', function (Request $request) {

    return match ($request->user()->role ?? 'CUSTOMER') {

        'ADMIN' => redirect()->route('admin.bookings.index'),

        'EMPLOYEE' => redirect()->route('employee.dashboard'),

        default => app(BookingController::class)->index($request),

    };

})
    ->middleware(['auth', 'active'])
    ->name('bookings.index');

/*
|--------------------------------------------------------------------------
| VNPay
|--------------------------------------------------------------------------
|
| Callback từ VNPay.
|
*/

Route::get('/booking/vnpay/return', [
    BookingController::class,
    'vnpayReturn',
])->name('bookings.vnpay.return');

Route::get('/booking/vnpay/ipn', [
    BookingController::class,
    'vnpayIpn',
])->name('bookings.vnpay.ipn');

/*
|--------------------------------------------------------------------------
| CUSTOMER BOOKING
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'active',
    'role:CUSTOMER',
])->group(function () {

    /*
    | Tạo booking
    */

    Route::get('/booking', [
        BookingController::class,
        'create',
    ])->name('bookings.create');

    /*
    | URL tương thích với /booking/create
    */

    Route::get('/booking/create', [
        BookingController::class,
        'create',
    ]);

    /*
    | Lưu booking
    */

    Route::post('/booking', [
        BookingController::class,
        'store',
    ])->name('bookings.store');

    /*
    | Booking định kỳ
    */

    Route::get('/booking/create-recurring', [
        BookingController::class,
        'createRecurring',
    ])->name('bookings.create-recurring');

    Route::post('/booking/recurring/preview', [
        BookingController::class,
        'previewRecurring'
    ])->name('bookings.recurring.preview');

    Route::post('/booking/recurring', [
        BookingController::class,
        'storeRecurring',
    ])->name('bookings.store-recurring');

    /*
    | Chi tiết booking
    */

    Route::get('/booking/{booking}', [
        BookingController::class,
        'show',
    ])->name('bookings.show');

    /*
    | QR booking
    */

    Route::get('/booking/{booking}/qr', [
        BookingController::class,
        'showQr',
    ])->name('bookings.qr');

    /*
    | Xác nhận thanh toán
    */

    Route::post('/booking/{booking}/confirm-payment', [
        BookingController::class,
        'confirmPayment',
    ])->name('bookings.confirm-payment');

    /*
    | Cập nhật ghi chú từ màn hình checkout trước khi thanh toán
    */

    Route::post('/booking/{booking}/update-note', [
        BookingController::class,
        'updateNote',
    ])->name('bookings.update-note');

    /*
    | Thanh toán VNPay
    */

    Route::get('/booking/{booking}/vnpay', [
        BookingController::class,
        'vnpayCreate',
    ])->name('bookings.vnpay');

    /*
    | Hủy booking
    */

    Route::post('/booking/{booking}/cancel', [
        BookingController::class,
        'cancel',
    ])->name('bookings.cancel');

    /*
    | Yêu cầu hoàn tiền
    */

    Route::post('/refund-requests', [
        RefundRequestController::class,
        'store',
    ])->name('refund-requests.store');

});

/*
|--------------------------------------------------------------------------
| EMPLOYEE CHECKOUT
|--------------------------------------------------------------------------
*/

Route::post('/booking/{booking}/checkout', [
    BookingController::class,
    'checkout',
])
    ->middleware([
        'auth',
        'active',
        'role:EMPLOYEE',
        'permission:bookings.checkout',
    ])
    ->name('bookings.checkout');

<?php

use App\Http\Controllers\{AdminAnnouncementController, AdminBookingController, AdminCourtController, AdminCourtTypeController, AdminCustomerController, AdminDashboardController, AdminEmployeeController, AdminIncidentController, AdminMaintenanceController, AdminPaymentController, AdminPricingController, AdminVoucherController, AuthController, BookingController, CourtController, EmployeeBookingController, EmployeeCourtController, EmployeeDashboardController, EmployeeIncidentController, HomeController, RefundRequestController};
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home'); // UC11

// Court routes (UC12-UC17)
Route::get('/courts', [CourtController::class, 'index'])->name('courts.index');
Route::get('/courts/{court}', [CourtController::class, 'show'])->name('courts.show');
Route::get('/courts/{court}/availability', [CourtController::class, 'availability'])->name('courts.availability');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['auth', 'active', 'role:EMPLOYEE'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
    Route::middleware('permission:bookings.view')->group(function () {
        Route::get('/bookings', [EmployeeBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [EmployeeBookingController::class, 'show'])->name('bookings.show');
    });
    Route::post('/bookings/{booking}/check-in', [EmployeeBookingController::class, 'checkIn'])->middleware('permission:bookings.checkin')->name('bookings.check-in');
    Route::post('/bookings/{booking}/complete', [EmployeeBookingController::class, 'complete'])->middleware('permission:bookings.checkout')->name('bookings.complete');
    Route::post('/bookings/{booking}/payment', [EmployeeBookingController::class, 'pay'])->middleware('permission:payments.counter')->name('bookings.payment');
    Route::post('/bookings/{booking}/services', [EmployeeBookingController::class, 'addService'])->middleware('permission:services.manage')->name('bookings.services.store');
    Route::delete('/bookings/{booking}/services/{service}', [EmployeeBookingController::class, 'removeService'])->middleware('permission:services.manage')->name('bookings.services.destroy');
    Route::get('/incidents', [EmployeeIncidentController::class, 'index'])->middleware('permission:incidents.manage')->name('incidents.index');
    Route::post('/incidents', [EmployeeIncidentController::class, 'store'])->middleware('permission:incidents.manage')->name('incidents.store');
    Route::middleware('permission:courts.status.manage')->group(function () {
        Route::get('/courts', [EmployeeCourtController::class, 'index'])->name('courts.index');
        Route::get('/courts/{court}/edit', [EmployeeCourtController::class, 'edit'])->name('courts.edit');
        Route::put('/courts/{court}', [EmployeeCourtController::class, 'update'])->name('courts.update');
    });
    Route::middleware('permission:refunds.manage')->group(function () {
        Route::get('/refund-requests', [RefundRequestController::class, 'index'])->name('refund-requests.index');
        Route::get('/refund-requests/{refundRequest}', [RefundRequestController::class, 'show'])->name('refund-requests.show');
        Route::post('/refund-requests/{refundRequest}/review', [RefundRequestController::class, 'review'])->name('refund-requests.review');
    });
});

Route::middleware(['auth', 'active', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('courts', AdminCourtController::class)->except('show');
    Route::resource('court-types', AdminCourtTypeController::class)->except('show');
    Route::get('pricing', [AdminPricingController::class, 'index'])->name('pricing.index');
    Route::post('pricing/slots', [AdminPricingController::class, 'storeSlot'])->name('pricing.slots.store');
    Route::put('pricing/slots/{timeSlot}', [AdminPricingController::class, 'updateSlot'])->name('pricing.slots.update');
    Route::delete('pricing/slots/{timeSlot}', [AdminPricingController::class, 'destroySlot'])->name('pricing.slots.destroy');
    Route::put('pricing/prices', [AdminPricingController::class, 'updatePrices'])->name('pricing.prices.update');
    Route::post('pricing/holidays', [AdminPricingController::class, 'storeHoliday'])->name('pricing.holidays.store');
    Route::delete('pricing/holidays/{holiday}', [AdminPricingController::class, 'destroyHoliday'])->name('pricing.holidays.destroy');
    Route::get('maintenance', [AdminMaintenanceController::class, 'index'])->name('maintenance.index');
    Route::post('maintenance', [AdminMaintenanceController::class, 'store'])->name('maintenance.store');
    Route::put('maintenance/{maintenance}/cancel', [AdminMaintenanceController::class, 'cancel'])->name('maintenance.cancel');
    Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('customers/{customer}/edit', [AdminCustomerController::class, 'edit'])->name('customers.edit');
    Route::put('customers/{customer}', [AdminCustomerController::class, 'update'])->name('customers.update');
    Route::put('customers/{customer}/toggle-status', [AdminCustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
    Route::get('employees', [AdminEmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [AdminEmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [AdminEmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}/edit', [AdminEmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('employees/{employee}', [AdminEmployeeController::class, 'update'])->name('employees.update');
    Route::put('employees/{employee}/toggle-status', [AdminEmployeeController::class, 'toggleStatus'])->name('employees.toggle-status');
    Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
    Route::put('bookings/{booking}', [AdminBookingController::class, 'update'])->name('bookings.update');
    Route::put('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::put('payments/{payment}/reconcile', [AdminPaymentController::class, 'reconcile'])->name('payments.reconcile');
    Route::put('refund-requests/{refundRequest}/approve', [AdminPaymentController::class, 'approveRefund'])->name('payments.refunds.approve');
    Route::put('refunds/{refund}/process', [AdminPaymentController::class, 'processRefund'])->name('payments.refunds.process');
    Route::resource('vouchers', AdminVoucherController::class)->except('show');
    Route::put('vouchers/{voucher}/toggle', [AdminVoucherController::class, 'toggle'])->name('vouchers.toggle');
    Route::get('announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::get('incidents', [AdminIncidentController::class, 'index'])->name('incidents.index');
    Route::put('incidents/{incident}', [AdminIncidentController::class, 'update'])->name('incidents.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth', 'active'])->name('logout');

// Booking routes (UC18-UC21, UC23)
Route::get('/bookings', function (\Illuminate\Http\Request $request) {
    return match ($request->user()->role ?: 'CUSTOMER') {
        'ADMIN' => redirect()->route('admin.bookings.index'),
        'EMPLOYEE' => redirect()->route('employee.dashboard'),
        default => app(BookingController::class)->index($request),
    };
})->middleware(['auth', 'active'])->name('bookings.index');

Route::middleware(['auth', 'active', 'role:CUSTOMER'])->group(function () {
    Route::get('/booking', [BookingController::class, 'create'])->name('bookings.create');
    Route::get('/booking/create', [BookingController::class, 'create']);
    Route::post('/booking', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/booking/create-recurring', [BookingController::class, 'createRecurring'])->name('bookings.create-recurring');
    Route::post('/booking/recurring', [BookingController::class, 'storeRecurring'])->name('bookings.store-recurring');

    Route::get('/booking/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/booking/{booking}/confirm-payment', [BookingController::class, 'confirmPayment'])->name('bookings.confirm-payment');
    Route::post('/booking/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::post('/refund-requests', [RefundRequestController::class, 'store'])->name('refund-requests.store');
});

Route::post('/booking/{booking}/checkout', [BookingController::class, 'checkout'])
    ->middleware(['auth', 'active', 'role:EMPLOYEE', 'permission:bookings.checkout'])
    ->name('bookings.checkout');

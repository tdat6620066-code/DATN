<?php

use App\Http\Controllers\{EmployeeCounterController, EmployeeRetailController, EmployeeEquipmentController, EmployeeShiftController, EmployeeCourtController};
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'role:EMPLOYEE'])->prefix('employee')->name('employee.')->group(function () {
    Route::middleware(['permission:bookings.view', 'permission:incidents.manage'])->group(function () {
        Route::put('/bookings/{booking}/details/{detail}/reschedule', [\App\Http\Controllers\AdminBookingController::class, 'reschedule'])->name('bookings.reschedule');
        Route::put('/bookings/{booking}/cancel', [\App\Http\Controllers\AdminBookingController::class, 'cancel'])->name('bookings.cancel');
    });
    Route::middleware(['permission:bookings.view', 'permission:payments.counter'])->group(function () {
        Route::get('/counter', [EmployeeCounterController::class, 'create'])->name('counter.create');
        Route::post('/counter', [EmployeeCounterController::class, 'store'])->name('counter.store');
        Route::get('/bookings/{booking}/extension-options', [EmployeeCounterController::class, 'extensionOptions'])->name('bookings.extension-options');
        Route::post('/bookings/{booking}/extend', [EmployeeCounterController::class, 'extend'])->name('bookings.extend');
    });
    Route::post('/scan', [EmployeeCounterController::class, 'scan'])->middleware('permission:bookings.view')->name('scan');
    Route::post('/bookings/{booking}/session-checkout', [EmployeeCounterController::class, 'checkoutSession'])->middleware(['permission:bookings.view', 'permission:bookings.checkout'])->name('bookings.session-checkout');
    Route::middleware(['permission:services.manage', 'permission:payments.counter'])->group(function () {
        Route::get('/retail', [EmployeeRetailController::class, 'index'])->name('retail.index');
        Route::post('/retail', [EmployeeRetailController::class, 'store'])->name('retail.store');
        Route::get('/retail/{sale}', [EmployeeRetailController::class, 'show'])->name('retail.show');
    });
    Route::middleware('permission:services.manage')->group(function () {
        Route::get('/equipment', [EmployeeEquipmentController::class, 'index'])->name('equipment.index');
        Route::post('/equipment', [EmployeeEquipmentController::class, 'store'])->name('equipment.store');
        Route::post('/equipment/lend', [EmployeeEquipmentController::class, 'lend'])->name('equipment.lend');
        Route::post('/equipment/loans/{loan}/return', [EmployeeEquipmentController::class, 'returnLoan'])->name('equipment.return');
        Route::post('/equipment/{equipment}/restore', [EmployeeEquipmentController::class, 'restore'])->name('equipment.restore');
    });
    Route::middleware('permission:employee.dashboard')->group(function () {
        Route::get('/shifts', [EmployeeShiftController::class, 'index'])->name('shifts.index');
        Route::post('/shifts', [EmployeeShiftController::class, 'start'])->name('shifts.start');
        Route::post('/shifts/{shift}/close', [EmployeeShiftController::class, 'close'])->name('shifts.close');
        Route::get('/shifts/{shift}/export', [EmployeeShiftController::class, 'export'])->name('shifts.export');
    });
    Route::post('/courts/{court}/clean', [EmployeeCourtController::class, 'clean'])->middleware('permission:courts.status.manage')->name('courts.clean');
});

<?php

use App\Http\Controllers\{AdminAccessController, AdminBookingController, AdminContentController, AdminCourtController, AdminPricingController, AdminReportController, AdminSettingsController, ContactController, PublicContentController};
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('content/{kind}', [AdminContentController::class, 'index'])->name('content.index');
    Route::get('content/{kind}/create', [AdminContentController::class, 'create'])->name('content.create');
    Route::post('content/{kind}', [AdminContentController::class, 'store'])->name('content.store');
    Route::get('content/{kind}/{id}/edit', [AdminContentController::class, 'edit'])->whereNumber('id')->name('content.edit');
    Route::get('content/{kind}/{id}', [AdminContentController::class, 'show'])->whereNumber('id')->name('content.show');
    Route::put('content/{kind}/{id}', [AdminContentController::class, 'update'])->whereNumber('id')->name('content.update');
    Route::delete('content/{kind}/{id}', [AdminContentController::class, 'destroy'])->whereNumber('id')->name('content.destroy');
    Route::post('services/{serviceItem}/stock', [AdminContentController::class, 'stock'])->name('services.stock');
    Route::get('users', [AdminAccessController::class, 'users'])->name('users.index');
    Route::get('users/create', [AdminAccessController::class, 'form'])->name('users.create');
    Route::post('users', [AdminAccessController::class, 'saveUser'])->name('users.store');
    Route::get('users/{user}/edit', [AdminAccessController::class, 'form'])->name('users.edit');
    Route::put('users/{user}', [AdminAccessController::class, 'saveUser'])->name('users.update');
    Route::delete('users/{user}', [AdminAccessController::class, 'destroyUser'])->name('users.destroy');
    Route::get('roles', [AdminAccessController::class, 'roles'])->name('roles.index');
    Route::post('roles', [AdminAccessController::class, 'saveRole'])->name('roles.store');
    Route::put('roles/{accessRole}', [AdminAccessController::class, 'saveRole'])->name('roles.update');
    Route::delete('roles/{accessRole}', [AdminAccessController::class, 'deleteRole'])->name('roles.destroy');
    Route::get('settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::get('time-slots', [AdminPricingController::class, 'slots'])->name('slots.index');
    Route::put('time-slots/{timeSlot}/toggle', [AdminPricingController::class, 'toggleSlot'])->name('slots.toggle');
    Route::get('courts/{court}', [AdminCourtController::class, 'show'])->name('courts.show');
    Route::put('bookings/{booking}/details/{detail}/reschedule', [AdminBookingController::class, 'reschedule'])->name('bookings.reschedule');
    Route::delete('bookings/{booking}', [AdminBookingController::class, 'destroy'])->name('bookings.destroy');
    Route::get('reports/export', [AdminReportController::class, 'export'])->name('reports.export');
});

Route::get('information', [PublicContentController::class, 'index'])->name('information');
Route::get('news/{news}', [PublicContentController::class, 'news'])->name('news.show');
Route::middleware(['auth', 'active', 'role:ADMIN,CUSTOMER'])->prefix('contacts')->name('contacts.')->group(function () {
    Route::get('/', [ContactController::class, 'index'])->name('index');
    Route::post('/', [ContactController::class, 'store'])->name('store');
    Route::get('{thread}', [ContactController::class, 'show'])->name('show');
    Route::post('{thread}/reply', [ContactController::class, 'reply'])->name('reply');
    Route::put('{thread}/status', [ContactController::class, 'status'])->middleware('role:ADMIN')->name('status');
});

<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    public function test_customer_cannot_directly_confirm_a_payment(): void
    {
        $this->assertFalse(Route::has('bookings.confirm-payment'));
    }

    public function test_all_auth_route_actions_exist(): void
    {
        foreach (['showForgotPassword', 'sendResetLink', 'showResetPassword', 'resetPassword', 'redirectGoogle', 'googleCallback'] as $method) {
            $this->assertTrue(method_exists(AuthController::class, $method), "Missing AuthController::{$method}");
        }
    }
}

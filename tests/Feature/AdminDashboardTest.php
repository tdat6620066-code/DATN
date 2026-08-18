<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard_with_real_kpis(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $booking = Booking::create([
            'booking_code' => 'ADMIN-KPI', 'user_id' => $customer->id,
            'total_amount' => 250000, 'status' => 'COMPLETED', 'payment_status' => 'PAID',
        ]);
        Payment::create([
            'booking_id' => $booking->id, 'amount' => 250000,
            'status' => 'PAID', 'paid_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard', [
            'from' => today()->toDateString(), 'to' => today()->toDateString(),
        ]))->assertOk()->assertViewHas('kpis', fn ($kpis) => $kpis['bookings'] === 1
            && $kpis['completed'] === 1
            && $kpis['revenue'] === 250000.0
            && $kpis['customers'] === 1);
    }

    public function test_dashboard_returns_zero_series_when_there_is_no_data(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin)->get(route('admin.dashboard', [
            'from' => today()->toDateString(), 'to' => today()->toDateString(),
        ]))->assertOk()->assertViewHas('chart', fn ($chart) => $chart['bookings']->all() === [0]
            && $chart['revenue']->all() === [0.0]);
    }

    public function test_non_admin_cannot_view_admin_dashboard(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);

        $this->actingAs($employee)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_is_redirected_to_admin_dashboard_after_login(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com', 'password' => 'password', 'role' => 'ADMIN',
        ]);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }
}

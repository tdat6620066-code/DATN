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
            'checked_out_at' => now(),
        ]);
        Payment::create([
            'booking_id' => $booking->id, 'amount' => 250000,
            'status' => 'PAID', 'paid_at' => now(),
        ]);
        $type = \App\Models\CourtType::create(['name' => 'Standard']);
        $court = \App\Models\Court::create(['name' => 'Court', 'code' => 'KPI', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = \App\Models\TimeSlot::create(['name' => 'Morning', 'start_time' => '08:00', 'end_time' => '09:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => today(), 'price' => 250000, 'subtotal' => 250000, 'status' => 'COMPLETED']);

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

    public function test_admin_returns_to_intended_dashboard_after_login(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com', 'password' => 'password', 'role' => 'ADMIN',
        ]);

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post('/login', ['login' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }
}

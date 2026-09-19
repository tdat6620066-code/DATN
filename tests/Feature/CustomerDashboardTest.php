<?php

namespace Tests\Feature;

use App\Models\{Booking, Court, CourtIncident, CourtType, FixedBooking, Payment, Review, TimeSlot, User, Voucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Court $court;
    private TimeSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(12, 0));
        $this->customer = User::factory()->create(['role' => 'CUSTOMER']);
        $type = CourtType::create(['name' => 'Standard', 'status' => 'ACTIVE']);
        $this->court = Court::create(['code' => 'DASH', 'name' => 'Sân Dashboard', 'court_type_id' => $type->id, 'status' => 'ACTIVE', 'operational_status' => 'AVAILABLE']);
        $this->slot = TimeSlot::create(['name' => '19:00 - 20:30', 'start_time' => '19:00', 'end_time' => '20:30', 'duration' => 90, 'status' => 'ACTIVE']);
        $this->actingAs($this->customer);
    }

    private function booking(string $status, string $payment = 'PENDING', ?User $user = null): Booking
    {
        $booking = Booking::create(['booking_code' => 'DASH-'.uniqid(), 'user_id' => ($user ?? $this->customer)->id,
            'status' => $status, 'payment_status' => $payment, 'subtotal' => 170000, 'discount' => 20000,
            'total_amount' => 150000, 'hold_expires_at' => now()->addMinutes(5)]);
        $booking->bookingDetails()->create(['court_id' => $this->court->id, 'time_slot_id' => $this->slot->id,
            'booking_date' => today()->addDay(), 'status' => $status === 'CANCELLED' ? 'CANCELLED' : 'CONFIRMED', 'price' => 120000, 'subtotal' => 120000]);
        Payment::create(['booking_id' => $booking->id, 'amount' => 150000, 'status' => $payment, 'payment_method' => 'vnpay', 'purpose' => 'BOOKING']);
        return $booking;
    }

    public function test_statistics_are_scoped_and_not_based_on_history_page(): void
    {
        $upcoming = $this->booking('CONFIRMED', 'PAID');
        for ($i = 0; $i < 11; $i++) $this->booking('COMPLETED', 'PAID');
        $this->booking('CANCELLED');
        $expired = $this->booking('PENDING_PAYMENT');
        $expired->update(['hold_expires_at' => now()->subSecond()]);
        $foreign = $this->booking('CONFIRMED', 'PAID', User::factory()->create());
        Voucher::create(['code' => 'DASH10', 'name' => 'Ưu đãi thật', 'discount_type' => 'FIXED', 'discount_value' => 10000, 'min_order_amount' => 0, 'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'status' => 'ACTIVE']);
        $this->get(route('profile', ['page' => 2]))->assertOk()
            ->assertViewHas('dashboardStats', fn ($stats) => $stats['upcoming'] === 1 && $stats['completed'] === 11 && $stats['hours'] == 16.5 && $stats['offers'] === 1)
            ->assertSee('Lịch chơi sắp tới')->assertSee($upcoming->booking_code)->assertDontSee($foreign->booking_code)
            ->assertSee('150.000đ')->assertSee('DASH10')->assertSee('customer-menu', false);
    }

    public function test_action_matrix_respects_payment_hold_and_terminal_status(): void
    {
        foreach ([['PENDING_PAYMENT', 'PENDING', true, false, false], ['CONFIRMED', 'PAID', false, false, true],
            ['CHECKED_IN', 'PAID', false, false, true], ['COMPLETED', 'PAID', false, true, true],
            ['CANCELLED', 'PENDING', false, true, false], ['EXPIRED', 'FAILED', false, true, false],
            ['COMPLETED', 'REFUNDED', false, true, false]] as [$status, $payment, $pay, $rebook, $report]) {
            $booking = $this->booking($status, $payment);
            $html = Blade::render('<x-customer-booking-card :booking="$booking"/>', compact('booking'));
            $this->assertSame($pay, str_contains($html, 'data-booking-action="pay"'), $status);
            $this->assertSame($rebook, str_contains($html, 'data-booking-action="rebook"'), $status);
            $this->assertSame($report, str_contains($html, 'data-booking-action="report"'), $status);
            $this->assertSame($pay, str_contains($html, 'data-booking-action="cancel"'), $status);
            $this->assertStringContainsString('150.000đ', $html);
        }
        $booking = $this->booking('PENDING_PAYMENT');
        $booking->update(['hold_expires_at' => now()]);
        $html = Blade::render('<x-customer-booking-card :booking="$booking"/>', compact('booking'));
        $this->assertStringNotContainsString('data-booking-action="pay"', $html);
        $this->assertStringContainsString('Hết hạn giữ chỗ', $html);
    }

    public function test_open_ticket_replaces_report_action_and_other_users_are_hidden(): void
    {
        $booking = $this->booking('CONFIRMED', 'PAID');
        CourtIncident::create(['incident_code' => 'TK-DASH', 'booking_id' => $booking->id, 'active_booking_id' => $booking->id,
            'court_id' => $this->court->id, 'customer_id' => $this->customer->id, 'reported_by' => $this->customer->id,
            'source' => 'CUSTOMER', 'type' => 'COURT_FAILURE', 'severity' => 'MEDIUM', 'status' => 'PENDING', 'description' => 'Cần kiểm tra sân']);
        $html = Blade::render('<x-customer-booking-card :booking="$booking"/>', compact('booking'));
        $this->assertStringContainsString('Theo dõi hỗ trợ', $html);
        $this->assertStringNotContainsString('data-booking-action="report"', $html);
        $this->get(route('profile', ['section' => 'support']))->assertOk()->assertSee('Cần kiểm tra sân');
        $this->actingAs(User::factory()->create());
        $this->assertStringNotContainsString($booking->booking_code, Blade::render('<x-customer-booking-card :booking="$booking"/>', compact('booking')));
        $this->get(route('profile', ['section' => 'support']))->assertOk()->assertDontSee('Cần kiểm tra sân');
    }

    public function test_status_filter_runs_before_pagination_and_menu_destinations_render(): void
    {
        for ($i = 0; $i < 16; $i++) $this->booking('CONFIRMED', 'PAID');
        $this->booking('CANCELLED');
        $url = route('bookings.index', ['status' => 'CONFIRMED']);
        $this->get($url)->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->total() === 16 && $rows->count() === 15);
        $this->get($url.'&page=2')->assertOk()->assertViewHas('bookings', fn ($rows) => $rows->count() === 1);
        foreach (['fixed', 'reviews', 'account', 'password', 'history'] as $section) $this->get(route('profile', compact('section')))->assertOk();
        $this->get(route('password.change'))->assertOk()->assertSee('name="_method" value="PUT"', false);
        $this->get(route('notifications.index'))->assertOk()->assertSee('Menu tài khoản');
    }

    public function test_fixed_payment_deadline_and_review_ownership(): void
    {
        $group = FixedBooking::create(['code' => 'FIX-DASH', 'user_id' => $this->customer->id, 'confirmation_key' => 'dash-key',
            'definition' => ['start_date' => today()->toDateString(), 'end_date' => today()->addMonth()->toDateString()],
            'occurrences' => [], 'total_price' => 300000, 'status' => 'AWAITING_PAYMENT', 'expires_at' => now()->addMinutes(5)]);
        $this->get(route('profile', ['section' => 'fixed']))->assertOk()->assertSee('FIX-DASH')->assertSee('data-booking-action="pay"', false);
        $group->update(['expires_at' => now()]);
        $this->get(route('profile', ['section' => 'fixed']))->assertOk()->assertDontSee('data-booking-action="pay"', false)->assertSee('Hết hạn giữ chỗ');
        $booking = $this->booking('COMPLETED', 'PAID');
        Review::create(['booking_id' => $booking->id, 'court_id' => $this->court->id, 'user_id' => $this->customer->id, 'rating' => 4, 'content' => 'Đánh giá riêng của khách', 'status' => 'APPROVED']);
        $this->get(route('profile', ['section' => 'reviews']))->assertOk()->assertSee('Đánh giá riêng của khách');
        $this->actingAs(User::factory()->create());
        $this->get(route('profile', ['section' => 'reviews']))->assertOk()->assertDontSee('Đánh giá riêng của khách');
    }

    public function test_staff_profile_keeps_original_view_and_account_form_contract(): void
    {
        $this->get(route('profile', ['section' => 'account']))->assertOk()
            ->assertSee(route('profile.update'), false)->assertSee('name="_method" value="PUT"', false)->assertSee('multipart/form-data', false);
        $this->actingAs(User::factory()->create(['role' => 'EMPLOYEE']))->get(route('profile'))->assertOk()->assertViewIs('profile.legacy');
    }
}

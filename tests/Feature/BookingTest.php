<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CourtAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    protected function createTestData()
    {
        // Create court type
        $courtType = CourtType::create([
            'name' => 'VIP Court',
            'description' => 'Premium badminton court',
            'status' => 'ACTIVE',
        ]);

        // Create court
        Court::create([
            'code' => 'COURT-001',
            'name' => 'Sân 1',
            'court_type_id' => $courtType->id,
            'description' => 'Sân cầu lông chất lượng cao',
            'address' => '123 Đường ABC',
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'status' => 'ACTIVE',
        ]);

        // Create time slots
        TimeSlot::create([
            'name' => '18:00 - 19:00',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'duration' => 60,
            'status' => 'ACTIVE',
        ]);

        TimeSlot::create([
            'name' => '19:00 - 20:00',
            'start_time' => '19:00',
            'end_time' => '20:00',
            'duration' => 60,
            'status' => 'ACTIVE',
        ]);

        // Create price
        $court = Court::first();
        $timeSlot = TimeSlot::first();
        $court->prices()->create([
            'time_slot_id' => $timeSlot->id,
            'price' => 150000,
            'effective_from' => now(),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Test 1: Guest cannot access booking creation page
     */
    public function test_guest_redirected_to_login_on_booking_create()
    {
        $response = $this->get('/booking/create');
        $response->assertRedirect('/login');
    }

    /**
     * Test 2: Authenticated user can access booking creation
     */
    public function test_user_can_access_booking_creation()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/booking/create');
        $response->assertOk();
    }

    /**
     * Test 3: User can view list of courts
     */
    public function test_user_can_view_courts_list()
    {
        $response = $this->actingAs(User::factory()->create())->get('/courts');
        $response->assertOk();
    }

    /**
     * Test 4: User can view court details
     */
    public function test_user_can_view_court_details()
    {
        $court = Court::first();
        $response = $this->actingAs(User::factory()->create())->get("/courts/{$court->id}");
        $response->assertOk();
    }

    /**
     * Test 5: Inactive court returns proper message
     */
    public function test_inactive_court_shows_message()
    {
        $court = Court::first();
        $court->update(['status' => 'INACTIVE']);
        
        $response = $this->actingAs(User::factory()->create())->get("/courts/{$court->id}");
        // Should either be 404 or show error message
        $this->assertTrue($response->status() === 404 || $response->getStatusCode() >= 400);
    }

    /**
     * Test 6: User can view home page
     */
    public function test_home_page_loads()
    {
        $response = $this->actingAs(User::factory()->create())->get('/');
        $response->assertOk();
    }

    /**
     * Test 7: User can view own bookings
     */
    public function test_user_can_view_own_bookings()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/bookings');
        $response->assertOk();
    }

    /**
     * Test 8: Search courts by keyword
     */
    public function test_can_search_courts_by_keyword()
    {
        $response = $this->actingAs(User::factory()->create())->get('/courts?keyword=Sân');
        $response->assertOk();
    }

    /**
     * Test 9: User cannot view other user's bookings
     */
    public function test_user_cannot_view_other_user_booking()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $court = Court::first();

        // Create booking for user1
        $booking = Booking::create([
            'booking_code' => 'BK003',
            'user_id' => $user1->id,
            'subtotal' => 150000,
            'total_amount' => 150000,
            'status' => 'PENDING_PAYMENT',
            'payment_status' => 'PENDING',
        ]);

        // User2 tries to access user1's booking
        $response = $this->actingAs($user2)->get("/booking/{$booking->id}");
        
        // Should be forbidden (403)
        $response->assertStatus(403);
    }

    /**
     * Test 10: User can access login form
     */
    public function test_user_can_access_login_form()
    {
        $response = $this->get('/login');
        $response->assertOk();
    }

    /**
     * Test 11: User can access registration form
     */
    public function test_user_can_access_registration_form()
    {
        $response = $this->get('/register');
        $response->assertOk();
    }

    /**
     * Test 12: User can register
     */
    public function test_user_can_register()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'phone' => '0912345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
        ]);
        $this->assertNotNull(User::where('email', 'testuser@example.com')->value('email_verified_at'));
    }

    /**
     * Test 13: User can login
     */
    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'login' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_weekly_preview_reports_a_conflict_with_an_existing_daily_booking()
    {
        $user = User::factory()->create();
        $court = Court::firstOrFail();
        $slot = TimeSlot::firstOrFail();
        $date = now()->startOfDay()->addDays(7);

        $this->createConfirmedDetail($user, $court, $slot, $date);
        $this->assertSame(CourtAvailabilityService::STATUS_BOOKED, app(CourtAvailabilityService::class)->checkAvailability($court->id, $date, $slot->id));

        $response = $this->actingAs($user)->post(route('bookings.recurring.preview'), [
            'from_court' => true,
            'court_id' => $court->id,
            'booking_type' => 'weekly',
            'start_date' => $date->toDateString(),
            'end_date' => $date->copy()->addWeek()->toDateString(),
            'days_of_week' => [$date->dayOfWeek],
            'time_slot_ids' => [$slot->id],
        ]);

        $response->assertRedirect(route('courts.show', $court));
        $response->assertSessionHas('recurring_preview.conflicts', fn (array $conflicts) => count($conflicts) >= 1);
    }

    public function test_monthly_preview_reports_a_conflict_with_an_existing_daily_booking()
    {
        $user = User::factory()->create();
        $court = Court::firstOrFail();
        $slot = TimeSlot::firstOrFail();
        $date = now()->startOfDay()->addDays(14);

        $this->createConfirmedDetail($user, $court, $slot, $date);

        $response = $this->actingAs($user)->post(route('bookings.recurring.preview'), [
            'from_court' => true,
            'court_id' => $court->id,
            'booking_type' => 'monthly',
            'start_date' => $date->toDateString(),
            'end_date' => $date->copy()->addMonth()->toDateString(),
            'days_of_month' => [$date->day],
            'time_slot_ids' => [$slot->id],
        ]);

        $response->assertRedirect(route('courts.show', $court));
        $response->assertSessionHas('recurring_preview.conflicts', fn (array $conflicts) => count($conflicts) >= 1);
    }

    private function createConfirmedDetail(User $user, Court $court, TimeSlot $slot, \Carbon\Carbon $date): void
    {
        $booking = Booking::create([
            'booking_code' => 'BK'.str()->upper(str()->random(10)),
            'user_id' => $user->id,
            'subtotal' => 150000,
            'total_amount' => 150000,
            'status' => 'CONFIRMED',
            'payment_status' => 'PAID',
        ]);

        BookingDetail::create([
            'booking_id' => $booking->id,
            'court_id' => $court->id,
            'booking_date' => $date->toDateString(),
            'time_slot_id' => $slot->id,
            'price' => 150000,
            'subtotal' => 150000,
            'status' => 'CONFIRMED',
        ]);
    }
}

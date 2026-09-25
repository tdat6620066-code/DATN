<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtType;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\CourtAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_bulk_approval_unpaid_cancellation_and_replay_protection(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create();
        $type = CourtType::create(['name' => 'Standard']);
        $court = Court::create(['code' => 'COURT-A', 'name' => 'Court A', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => 'Evening', 'start_time' => '18:00', 'end_time' => '19:00', 'duration' => 60, 'status' => 'ACTIVE']);
        foreach (['PAID', 'PENDING'] as $status) {
            $booking = Booking::create(['booking_code' => 'B-'.$status, 'user_id' => $customer->id, 'status' => $status === 'PAID' ? 'CONFIRMED' : 'PENDING_PAYMENT', 'payment_status' => $status, 'total_amount' => 300000]);
            $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => '2026-09-07', 'price' => 300000, 'subtotal' => 300000, 'status' => 'CONFIRMED']);
            Payment::create(['booking_id' => $booking->id, 'amount' => 300000, 'status' => $status]);
        }
        $data = ['court_id' => $court->id, 'date' => '2026-09-07', 'start_time' => '17:00', 'end_time' => '22:00', 'reason_code' => 'WEATHER', 'reason' => 'Bão', 'cancel_bookings' => 1, 'create_refunds' => 1, 'notify_customers' => 1];
        $this->actingAs($customer)->post(route('admin.incidents.bulk.store'), $data)->assertForbidden();
        $this->actingAs($admin)->get(route('admin.incidents.bulk'))->assertOk();
        $this->post(route('admin.incidents.bulk.store'), $data)->assertOk()->assertSee('B-PAID')->assertSee('B-PENDING');
        $this->assertDatabaseCount('refund_requests', 0);
        $token = session('bulk_incident_preview.token');
        $this->post(route('admin.incidents.bulk.store'), $data + ['confirm' => 1, 'preview_token' => $token])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertDatabaseHas('incident_resolutions', ['status' => 'AWAITING_CHOICE', 'refund_amount' => 300000]);
        $this->assertDatabaseHas('bookings', ['booking_code' => 'B-PAID', 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('bookings', ['booking_code' => 'B-PENDING', 'status' => 'CANCELLED']);
        $this->assertDatabaseCount('maintenance_schedules', 1);
        $overlap = TimeSlot::create(['name' => 'Overlap', 'start_time' => '16:30:00', 'end_time' => '17:30:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $boundary = TimeSlot::create(['name' => 'Boundary', 'start_time' => '22:00:00', 'end_time' => '23:00:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $availability = app(CourtAvailabilityService::class);
        $this->assertSame('MAINTENANCE', $availability->checkAvailability($court->id, Carbon::parse('2026-09-07'), $overlap->id));
        $this->assertSame('AVAILABLE', $availability->checkAvailability($court->id, Carbon::parse('2026-09-07'), $boundary->id));
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame(2, Notification::count());
        $this->get(route('special-refunds.index'))->assertOk();
        $this->post(route('admin.incidents.bulk.store'), $data + ['confirm' => 1, 'preview_token' => $token])->assertSessionHasErrors();
        $this->assertDatabaseCount('court_incidents', 1);
        // Touching the closing boundary does not overlap the booking.
        $data['start_time'] = '19:00';
        $this->post(route('admin.incidents.bulk.store'), $data)->assertOk()->assertDontSee('B-PAID');
    }

    public function test_admin_direct_approval_calculates_whole_booking_amount(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create();
        $booking = Booking::create(['booking_code' => 'DIRECT', 'user_id' => $customer->id, 'status' => 'CONFIRMED', 'payment_status' => 'PAID', 'total_amount' => 300000]);
        Payment::create(['booking_id' => $booking->id, 'status' => 'PAID', 'amount' => 300000]);
        $data = ['reason_code' => 'WEATHER', 'reason' => 'Bão', 'supporting_information' => 'Đóng cửa', 'amount' => 150000, 'refund_type' => 'FULL', 'approve_now' => 1];
        $this->actingAs($admin)->post(route('special-refunds.store', $booking), $data)->assertSessionHasNoErrors();
        $this->assertSame('300000.00', RefundRequest::firstOrFail()->amount);
        $this->assertSame('APPROVED', RefundRequest::firstOrFail()->status);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_interrupted_session_refunds_unused_time_and_preserves_other_dates(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $customer = User::factory()->create();
        $type = CourtType::create(['name' => 'Test']);
        $court = Court::create(['code' => 'PART', 'name' => 'Partial', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => 'Two hours', 'start_time' => '18:00:00', 'end_time' => '20:00:00', 'duration' => 120, 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'PARTIAL', 'user_id' => $customer->id, 'status' => 'CHECKED_IN', 'payment_status' => 'PAID', 'subtotal' => 600000, 'total_amount' => 600000]);
        foreach (['2026-09-07', '2026-09-08'] as $date) {
            $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => $date, 'price' => 300000, 'subtotal' => 300000, 'status' => $date === '2026-09-07' ? 'CHECKED_IN' : 'CONFIRMED']);
        }
        Payment::create(['booking_id' => $booking->id, 'status' => 'PAID', 'amount' => 600000]);
        $data = ['court_id' => $court->id, 'date' => '2026-09-07', 'start_time' => '19:00', 'end_time' => '22:00', 'reason_code' => 'COURT_FAILURE', 'reason' => 'Mất điện', 'notify_customers' => 1];
        $this->actingAs($admin)->post(route('admin.incidents.bulk.store'), $data)->assertOk();
        $this->post(route('admin.incidents.bulk.store'), $data + ['confirm' => 1, 'preview_token' => session('bulk_incident_preview.token')])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incident_resolutions', ['booking_id' => $booking->id, 'refund_amount' => 150000]);
        $this->assertSame('CONFIRMED', $booking->bookingDetails()->whereDate('booking_date', '2026-09-08')->first()->status);
        $this->assertSame('CHECKED_IN', $booking->fresh()->status);
        $this->assertDatabaseCount('refund_requests', 0);
    }
}

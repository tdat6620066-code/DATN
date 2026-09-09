<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\Court;
use App\Models\CourtIncident;
use App\Models\CourtPrice;
use App\Models\CourtType;
use App\Models\IncidentResolution;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentResolutionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesReceiptImages;

    private function fixture(): array
    {
        $this->travelTo(now()->setDate(2026, 9, 6)->startOfDay());
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $type = CourtType::create(['name' => 'Test']);
        $court = Court::create(['code' => 'A', 'name' => 'Sân A', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $other = Court::create(['code' => 'B', 'name' => 'Sân B', 'court_type_id' => $type->id, 'status' => 'ACTIVE']);
        $slot = TimeSlot::create(['name' => 'Evening', 'start_time' => '19:00:00', 'end_time' => '20:00:00', 'duration' => 60, 'status' => 'ACTIVE']);
        $booking = Booking::create(['booking_code' => 'RESOLVE', 'user_id' => $customer->id, 'status' => 'CANCELLED', 'payment_status' => 'PAID', 'subtotal' => 150000, 'total_amount' => 150000]);
        $detail = $booking->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => '2026-09-07', 'subtotal' => 150000, 'price' => 150000, 'status' => 'CANCELLED']);
        Payment::create(['booking_id' => $booking->id, 'status' => 'PAID', 'amount' => 150000]);
        $incident = CourtIncident::create(['incident_code' => 'INC-A', 'court_id' => $court->id, 'reported_by' => $admin->id, 'type' => 'WEATHER', 'severity' => 'HIGH', 'description' => 'Bão']);
        $resolution = IncidentResolution::create(['court_incident_id' => $incident->id, 'booking_id' => $booking->id, 'booking_detail_id' => $detail->id, 'refund_amount' => 150000, 'original_slot' => ['court_id' => $court->id, 'court' => $court->name, 'date' => '2026-09-07', 'time_slot_id' => $slot->id, 'start_time' => $slot->start_time, 'end_time' => $slot->end_time, 'subtotal' => 150000, 'unused_ratio' => 1]]);
        foreach ([$court, $other] as $target) {
            CourtPrice::create(['court_id' => $target->id, 'time_slot_id' => $slot->id, 'price' => 200000, 'day_type' => 'WEEKDAY', 'effective_from' => '2026-01-01', 'status' => 'ACTIVE']);
        }

        return [$customer, $admin, $booking, $resolution, $court, $other, $slot];
    }

    public function test_customer_refund_requires_ownership_choice_and_admin_confirmation(): void
    {
        [$customer, $admin, $booking, $item] = $this->fixture();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('incident-resolutions.choose', $item), ['choice' => 'REFUND'] + ['bank_name' => 'Test Bank', 'bank_account_number' => '001234567890', 'bank_account_holder' => 'TEST CUSTOMER', 'recipient_confirmed' => 1])->assertForbidden();
        $this->actingAs($customer)->get(route('bookings.show', $booking))->assertOk()->assertSee('Đổi lịch');
        $this->post(route('incident-resolutions.choose', $item), ['choice' => 'REFUND'])->assertSessionHasErrors('bank_account_number');
        $this->assertDatabaseCount('refund_requests', 0);
        $this->post(route('incident-resolutions.choose', $item), ['choice' => 'REFUND'] + ['bank_name' => 'Test Bank', 'bank_account_number' => '001234567890', 'bank_account_holder' => 'TEST CUSTOMER', 'recipient_confirmed' => 1])->assertSessionHasNoErrors();
        $refund = RefundRequest::firstOrFail();
        $this->assertSame('PENDING', $refund->status);
        $this->assertSame('PAID', $booking->payment->status);
        $this->assertSame('001234567890', $refund->bankAccount->account_number);
        $this->assertStringNotContainsString('001234567890', BookingAuditLog::all()->toJson());
        $this->assertStringNotContainsString('001234567890', Notification::all()->toJson());
        $this->post(route('incident-resolutions.choose', $item), ['choice' => 'REFUND'] + ['bank_name' => 'Test Bank', 'bank_account_number' => '001234567890', 'bank_account_holder' => 'TEST CUSTOMER', 'recipient_confirmed' => 1])->assertSessionHasErrors();
        $this->actingAs($admin)->post(route('special-refunds.review', $refund), ['decision' => 'APPROVED', 'decision_note' => 'Xác nhận'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.complete', $refund), ['amount' => 150000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'RF-OK'])->assertSessionHasNoErrors();
        $this->assertSame('REFUNDED', $booking->fresh()->payment_status);
        $this->assertSame('PAID', $booking->fresh()->payment->status);
        $this->assertSame('REFUNDED', $booking->fresh()->payment->refund_status);
        $this->assertSame('150000.00', $booking->fresh()->payment->refunded_amount);
        $this->assertSame('RESOLVED', $item->fresh()->status);
    }

    public function test_reschedule_absorbs_higher_price_without_refund(): void
    {
        [$customer, , $booking, $item, $court, , $slot] = $this->fixture();
        $this->actingAs($customer)->post(route('incident-resolutions.choose', $item), ['choice' => 'RESCHEDULE', 'court_id' => $court->id, 'date' => '2026-09-08', 'time_slot_id' => $slot->id])->assertSessionHasNoErrors();
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertSame('2026-09-08', $item->detail->booking_date->toDateString());
        $this->assertDatabaseCount('refund_requests', 0);
        $this->assertSame('150000.00', $booking->fresh()->total_amount);
    }

    public function test_cheaper_court_refunds_difference_and_keeps_new_slot(): void
    {
        [$customer, $admin, $booking, $item, , $court, $slot] = $this->fixture();
        CourtPrice::where('court_id', $court->id)->update(['price' => 100000]);
        $this->actingAs($customer)->post(route('incident-resolutions.choose', $item), ['choice' => 'CHANGE_COURT', 'court_id' => $court->id, 'date' => '2026-09-07', 'time_slot_id' => $slot->id])->assertSessionHasNoErrors();
        $refund = RefundRequest::firstOrFail();
        $this->assertSame('50000.00', $refund->amount);
        $this->actingAs($admin)->post(route('special-refunds.review', $refund), ['decision' => 'APPROVED', 'decision_note' => 'Chênh lệch'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.processing', $refund), ['refund_method' => 'CASH'])->assertSessionHasNoErrors();
        $this->post(route('special-refunds.complete', $refund), ['amount' => 50000, 'receipt_image' => $this->receiptImage(), 'refund_code' => 'DIFF'])->assertSessionHasNoErrors();
        $this->assertSame('PARTIALLY_REFUNDED', $booking->fresh()->payment_status);
        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertSame($court->id, $item->detail->court_id);
        $this->assertSame('CONFIRMED', $item->detail->status);
    }

    public function test_occupied_court_rejects_transfer_atomically(): void
    {
        [$customer, , $booking, $item, , $court, $slot] = $this->fixture();
        $occupied = Booking::create(['booking_code' => 'TAKEN', 'user_id' => $customer->id, 'status' => 'CONFIRMED']);
        $occupied->bookingDetails()->create(['court_id' => $court->id, 'time_slot_id' => $slot->id, 'booking_date' => '2026-09-07', 'subtotal' => 150000, 'price' => 150000, 'status' => 'CONFIRMED']);
        $this->actingAs($customer)->post(route('incident-resolutions.choose', $item), ['choice' => 'CHANGE_COURT', 'court_id' => $court->id, 'date' => '2026-09-07', 'time_slot_id' => $slot->id])->assertSessionHasErrors('choice');
        $this->assertSame('AWAITING_CHOICE', $item->fresh()->status);
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertDatabaseCount('refund_requests', 0);
    }
}

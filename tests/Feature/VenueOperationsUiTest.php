<?php

namespace Tests\Feature;

use App\Models\CourtIncident;
use App\Services\{PaymentService, BookingOperationsService};

class VenueOperationsUiTest extends StaffDashboardUiTest
{
    public function test_playing_booking_has_extension_and_checkout_modals(): void
    {
        $booking = $this->booking();
        app(PaymentService::class)->markAsPaid($booking->payment);
        $this->travelTo(now()->setTime(17, 59));
        app(BookingOperationsService::class)->checkIn($booking->fresh(), $this->employee);
        $this->get(route('employee.bookings.show', $booking))->assertOk()
            ->assertSee('id="venue-extension"', false)->assertSee('id="venue-checkout"', false)
            ->assertSee('Gia hạn & thanh toán', false)->assertSee('Hoàn tất Check-out')
            ->assertSee('100.000đ')->assertSee('name="quoted_price" value="100000"', false);
    }

    public function test_incident_notes_are_saved_without_changing_workflow(): void
    {
        $this->post(route('employee.incidents.store'), [
            'court_id'=>$this->court->id, 'type'=>'Đèn sân', 'severity'=>'LOW',
            'description'=>'Một đèn không sáng.', 'handling_direction'=>'Kiểm tra bóng đèn', 'staff_note'=>'Đã báo kỹ thuật',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $incident = CourtIncident::latest('id')->firstOrFail();
        $this->assertStringContainsString('Kiểm tra bóng đèn', $incident->resolution_note);
        $this->assertSame('OPEN', $incident->status);
        $this->assertSame('AVAILABLE', $this->court->fresh()->operational_status);
        $this->get(route('employee.incidents.index'))->assertOk()->assertSee('Mới')->assertSee('Đã báo kỹ thuật');
    }

    public function test_extension_cannot_overwrite_the_next_booking(): void
    {
        $booking = $this->booking();
        app(PaymentService::class)->markAsPaid($booking->payment);
        $this->travelTo(now()->setTime(17, 59));
        app(BookingOperationsService::class)->checkIn($booking->fresh(), $this->employee);
        $nextBooking = app(\App\Services\BookingService::class)->createBooking($booking->user_id, [
            ['court_id'=>$this->court->id, 'time_slot_id'=>$this->next->id, 'booking_date'=>today()->toDateString()],
        ]);
        app(PaymentService::class)->markAsPaid($nextBooking->payment);
        $this->get(route('employee.bookings.show', $booking))->assertOk()->assertDontSee('name="quoted_price"', false);
        $this->post(route('employee.bookings.extend', $booking), [
            'detail_id'=>$booking->bookingDetails->first()->id, 'court_id'=>$this->court->id,
            'time_slot_ids'=>[$this->next->id], 'quoted_price'=>100000,
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('CONFIRMED', $nextBooking->fresh()->status);
        $this->assertDatabaseCount('bookings', 2);
    }
}

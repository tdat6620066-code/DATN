<?php

namespace App\Services;

use App\Models\{Booking, User};

/** Read-only presentation of existing operational guards. */
class StaffBookingUiService
{
    public function actions(Booking $booking, User $actor): array
    {
        $view = $actor->hasPermission('bookings.view');
        $actions = ['view' => $view, 'checkin' => false, 'checkout' => false, 'services' => false, 'extend' => null, 'incident' => false];
        $operations = app(BookingOperationsService::class);
        if ($booking->status === 'CONFIRMED' && $actor->hasPermission('bookings.checkin')) {
            try { $operations->assertCheckinReady($booking); $actions['checkin'] = true; } catch (\DomainException) {}
        }
        if ($booking->status === 'CHECKED_IN') {
            $actions['services'] = $view && $actor->hasPermission('services.manage');
            if ($actor->hasPermission('bookings.checkout')) {
                try { $operations->assertCheckoutReady($booking); $actions['checkout'] = true; } catch (\DomainException) {}
            }
            if ($view && $actor->hasPermission('payments.counter')) {
                $extensions = app(BookingExtensionService::class);
                foreach ($booking->bookingDetails()->where('status', 'CHECKED_IN')->with('timeSlot')->get()->sortByDesc('timeSlot.end_time') as $detail) {
                    try {
                        $source = $extensions->source($booking, $detail->id);
                        $extensions->nextSlots($source, 1);
                        $actions['extend'] = $detail->id; break;
                    } catch (\DomainException) {}
                }
            }
        }
        $actions['incident'] = $actor->hasPermission('incidents.manage') && in_array($booking->status, ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']);
        return $actions;
    }
}

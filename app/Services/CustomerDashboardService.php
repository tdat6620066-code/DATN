<?php

namespace App\Services;

use App\Models\{Booking, BookingDetail, CourtIncident, FixedBooking, Review, User, Voucher};

class CustomerDashboardService
{
    public function data(User $user, string $section): array
    {
        $owned = Booking::where('user_id', $user->id);
        $upcoming = (clone $owned)
            ->where(function ($query) {
                $query->whereIn('status', ['CONFIRMED', 'CHECKED_IN'])
                    ->orWhere(fn ($pending) => $pending->where('status', 'PENDING_PAYMENT')
                        ->where(fn ($hold) => $hold->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now())));
            })
            ->whereHas('bookingDetails', function ($detail) {
                $detail->where('status', '!=', 'CANCELLED')->where(function ($date) {
                    $date->whereDate('booking_date', '>', today())->orWhere(function ($today) {
                        $today->whereDate('booking_date', today())->whereHas('timeSlot', fn ($slot) => $slot->where('end_time', '>', now()->format('H:i:s')));
                    });
                });
            });
        $offers = Voucher::where('status', 'ACTIVE')->where('start_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhere('usage_limit', 0)->orWhereColumn('used_count', '<', 'usage_limit'));
        $minutes = BookingDetail::whereHas('booking', fn ($q) => $q->where('user_id', $user->id)->where('status', 'COMPLETED'))
            ->where('booking_details.status', '!=', 'CANCELLED')->join('time_slots', 'time_slots.id', '=', 'booking_details.time_slot_id')->sum('time_slots.duration');

        // Sort by the next actual slot, rather than the booking creation date.
        $nextSlot = fn ($column) => BookingDetail::select($column)
            ->join('time_slots', 'time_slots.id', '=', 'booking_details.time_slot_id')
            ->whereColumn('booking_details.booking_id', 'bookings.id')->where('booking_details.status', '!=', 'CANCELLED')
            ->where(fn ($q) => $q->whereDate('booking_date', '>', today())
                ->orWhere(fn ($today) => $today->whereDate('booking_date', today())->where('time_slots.end_time', '>', now()->format('H:i:s'))))
            ->orderBy('booking_date')->orderBy('time_slots.start_time')->limit(1);

        return [
            'section' => $section,
            'dashboardStats' => ['upcoming' => (clone $upcoming)->count(), 'completed' => (clone $owned)->where('status', 'COMPLETED')->count(), 'hours' => $minutes / 60, 'offers' => (clone $offers)->count()],
            'upcomingBookings' => $upcoming->with('bookingDetails.court', 'bookingDetails.timeSlot', 'payment', 'fixedBooking.payment')
                ->orderBy($nextSlot('booking_details.booking_date'))->orderBy($nextSlot('time_slots.start_time'))->limit(6)->get(),
            'offers' => $offers->orderBy('end_at')->limit(4)->get(),
            'fixedBookings' => $section === 'fixed' ? FixedBooking::where('user_id', $user->id)->with('payment')->withCount('bookings')->latest()->paginate(10)->withQueryString() : collect(),
            'customerReviews' => $section === 'reviews' ? Review::where('user_id', $user->id)->with('court')->latest()->paginate(10)->withQueryString() : collect(),
            'supportTickets' => $section === 'support' ? CourtIncident::where('source', 'CUSTOMER')->where('customer_id', $user->id)->with('court')->latest()->paginate(10)->withQueryString() : collect(),
        ];
    }
}

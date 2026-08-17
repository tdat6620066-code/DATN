<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\RefundRequest;
use Illuminate\Http\Request;

class EmployeeDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('employee.dashboard'), 403);

        $todayBookingsQuery = Booking::query()->whereHas(
            'bookingDetails',
            fn ($query) => $query->whereDate('booking_date', today())
        );

        $statistics = [
            'today_bookings' => (clone $todayBookingsQuery)->count(),
            'checked_in' => Booking::where('status', 'CHECKED_IN')->count(),
            'pending_refunds' => RefundRequest::whereIn('status', ['PENDING', 'NEEDS_INFO'])->count(),
            'available_courts' => Court::where('status', 'ACTIVE')->where('availability_status', 'AVAILABLE')->count(),
        ];

        $todayBookings = (clone $todayBookingsQuery)
            ->with(['user', 'bookingDetails' => fn ($query) => $query
                ->whereDate('booking_date', today())
                ->with(['court', 'timeSlot'])])
            ->latest()
            ->limit(10)
            ->get();

        $refundRequests = RefundRequest::with(['booking', 'requester'])
            ->whereIn('status', ['PENDING', 'NEEDS_INFO'])
            ->latest()
            ->limit(6)
            ->get();

        return view('employee.dashboard', compact('statistics', 'todayBookings', 'refundRequests'));
    }
}

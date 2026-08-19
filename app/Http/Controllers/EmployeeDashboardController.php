<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\RefundRequest;
use App\Models\TimeSlot;
use Carbon\Carbon;
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

    /**
     * UC33 - Xem lịch đặt sân theo ngày / tuần / tháng.
     */
    public function schedule(Request $request)
    {
        abort_unless($request->user()->hasPermission('employee.dashboard'), 403);

        $mode = $request->query('mode', 'day');
        if (! in_array($mode, ['day', 'week', 'month'], true)) {
            $mode = 'day';
        }

        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();

        $courts = Court::where('status', 'ACTIVE')->orderBy('name')->get();
        $timeSlots = TimeSlot::where('status', 'ACTIVE')->orderBy('start_time')->get();

        [$start, $end, $dates] = $this->scheduleRange($mode, $date);

        $bookingDetails = BookingDetail::query()
            ->whereBetween('booking_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('booking', fn ($query) => $query
                ->whereIn('status', ['PENDING_PAYMENT', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED']))
            ->with(['booking.user', 'court', 'timeSlot'])
            ->orderBy('booking_date')
            ->get();

        return view('employee.schedule', compact(
            'mode', 'date', 'dates', 'start', 'end', 'courts', 'timeSlots', 'bookingDetails'
        ));
    }

    /**
     * Tính khoảng ngày hiển thị theo chế độ xem.
     *
     * @return array{0: Carbon, 1: Carbon, 2: \Illuminate\Support\Collection<int, Carbon>}
     */
    private function scheduleRange(string $mode, Carbon $date): array
    {
        if ($mode === 'week') {
            $start = $date->copy()->startOfWeek();
            $end = $date->copy()->endOfWeek();
            $dates = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));

            return [$start, $end, $dates];
        }

        if ($mode === 'month') {
            $start = $date->copy()->startOfMonth()->startOfWeek();
            $end = $date->copy()->endOfMonth()->endOfWeek();
            $dates = collect();
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $dates->push($day->copy());
            }

            return [$start, $end, $dates];
        }

        // day
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        return [$start, $end, collect([$date->copy()->startOfDay()])];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'ADMIN', 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($validated['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();
        abort_if($from->diffInDays($to) > 366, 422, 'Khoảng thống kê tối đa là 366 ngày.');

        $periodBookings = Booking::whereBetween('created_at', [$from, $to]);
        $revenue = Payment::where('status', 'PAID')->whereBetween('paid_at', [$from, $to])->sum('amount');
        $bookedSlots = BookingDetail::whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('booking', fn ($query) => $query->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']))
            ->count();
        $capacity = Court::where('status', 'ACTIVE')->count()
            * TimeSlot::where('status', 'ACTIVE')->count()
            * ($from->diffInDays($to) + 1);

        $kpis = [
            'courts' => Court::count(),
            'customers' => User::where('role', 'CUSTOMER')->count(),
            'bookings' => (clone $periodBookings)->count(),
            'revenue' => (float) $revenue,
            'pending' => (clone $periodBookings)->where('status', 'PENDING_PAYMENT')->count(),
            'completed' => (clone $periodBookings)->where('status', 'COMPLETED')->count(),
            'occupancy_rate' => $capacity > 0 ? round(min(100, $bookedSlots / $capacity * 100), 1) : 0,
        ];

        $bookingDaily = (clone $periodBookings)->get(['created_at'])->countBy(fn ($booking) => $booking->created_at->toDateString());
        $revenueDaily = Payment::where('status', 'PAID')->whereBetween('paid_at', [$from, $to])
            ->get(['amount', 'paid_at'])->groupBy(fn ($payment) => $payment->paid_at->toDateString())
            ->map->sum('amount');
        $days = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()));
        $chart = [
            'labels' => $days->map(fn ($day) => $day->format('d/m'))->values(),
            'bookings' => $days->map(fn ($day) => $bookingDaily[$day->toDateString()] ?? 0)->values(),
            'revenue' => $days->map(fn ($day) => (float) ($revenueDaily[$day->toDateString()] ?? 0))->values(),
        ];

        $popularCourts = Court::with('courtType')
            ->withCount(['bookingDetails as booking_count' => fn ($query) => $query
                ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
                ->whereHas('booking', fn ($booking) => $booking->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']))])
            ->orderByDesc('booking_count')->limit(5)->get();

        return view('admin.dashboard', compact('kpis', 'chart', 'popularCourts', 'from', 'to'));
    }
}

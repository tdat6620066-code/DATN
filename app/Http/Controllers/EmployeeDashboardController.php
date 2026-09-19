<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeeDashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('panel') === 'customers') {
            abort_unless($request->user()->hasPermission('bookings.view'), 403);
            $request->validate(['search' => 'nullable|string|max:100']);
            $customers = \App\Models\User::where('role', 'CUSTOMER')
                ->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')))
                ->withCount('bookings')->orderBy('name')->paginate(15)->withQueryString();
            return view('employee.customers', compact('customers'));
        }
        $todayBookingsQuery = Booking::query()->whereHas(
            'bookingDetails',
            fn ($query) => $query->whereDate('booking_date', today())
        );

        $statistics = [
            'today_bookings' => (clone $todayBookingsQuery)->count(),
            'checked_in' => Booking::where('status', 'CHECKED_IN')->count(),
            'available_courts' => Court::where('status', 'ACTIVE')->where('availability_status', 'AVAILABLE')->count(),
        ];
        $statistics['checked_in'] = (clone $todayBookingsQuery)->whereNotNull('checked_in_at')->distinct()->count('user_id');
        $statistics['playing_courts'] = BookingDetail::whereDate('booking_date', today())->where('status', 'CHECKED_IN')
            ->whereHas('booking', fn ($q) => $q->where('status', 'CHECKED_IN'))->distinct()->count('court_id');
        $statistics['upcoming_bookings'] = Booking::where('status', 'CONFIRMED')->whereHas('bookingDetails', fn ($q) => $q
            ->where('status', '!=', 'CANCELLED')->whereDate('booking_date', today())
            ->whereHas('timeSlot', fn ($slot) => $slot->where('start_time', '>', now()->format('H:i:s'))))->count();
        $statistics['open_incidents'] = \App\Models\CourtIncident::whereNotIn('status', ['RESOLVED', 'REJECTED'])->count();

        $todayBookings = (clone $todayBookingsQuery)
            ->with(['user', 'bookingDetails' => fn ($query) => $query
                ->whereDate('booking_date', today())
                ->with(['court', 'timeSlot'])])
            ->latest()
            ->limit(10)
            ->get();

        return view('employee.dashboard', compact('statistics', 'todayBookings'));
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

        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'court_id' => ['nullable', 'integer'], 'status' => ['nullable', 'in:PENDING_PAYMENT,CONFIRMED,CHECKED_IN,COMPLETED,CANCELLED,NO_SHOW,INCIDENT,MAINTENANCE'], 'search' => ['nullable', 'string', 'max:100']]);
        $date = $request->filled('date') ? Carbon::parse($request->query('date')) : Carbon::today();
        $allCourts = Court::with('courtType')->where('status', 'ACTIVE')->orderBy('name')->get();
        $courts = $request->filled('court_id') ? $allCourts->where('id', $request->integer('court_id')) : $allCourts;
        $timeSlots = TimeSlot::where('status', 'ACTIVE')->orderBy('start_time')->get();
        [$start, $end, $dates] = $this->scheduleRange($mode, $date);
        $bookingDetails = BookingDetail::whereIn('court_id', $courts->pluck('id'))
            ->whereDate('booking_date', '>=', $start->toDateString())->whereDate('booking_date', '<=', $end->toDateString())
            ->whereHas('booking', fn ($q) => $q->whereIn('status', ['PENDING_PAYMENT', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED', 'CANCELLED', 'NO_SHOW']))
            ->with(['booking.user', 'booking.payment', 'booking.fixedBooking.payment', 'booking.services.item', 'booking.serviceOrders.payment', 'timeSlot'])->get()
            ->filter(fn ($d) => $d->booking->status !== 'PENDING_PAYMENT' || ! $d->booking->isHoldExpired());
        $incidents = \App\Models\CourtIncident::whereNotIn('status', ['RESOLVED', 'REJECTED'])->whereIn('booking_id', $bookingDetails->pluck('booking_id'))->get();
        $courtIncidents = \App\Models\CourtIncident::where('source', 'COURT')->whereNotIn('status', ['RESOLVED', 'REJECTED'])->whereIn('court_id', $courts->pluck('id'))->get();
        $maintenance = \App\Models\MaintenanceSchedule::whereIn('court_id', $courts->pluck('id'))->where('status', '!=', 'CANCELLED')
            ->whereRaw('DATE(COALESCE(start_date, maintenance_date)) <= ?', [$end->toDateString()])
            ->whereRaw('DATE(COALESCE(end_date, maintenance_date)) >= ?', [$start->toDateString()])->get();
        $cells = []; $stats = ['bookings' => $bookingDetails->pluck('booking_id')->unique()->count(), 'playing' => $bookingDetails->where('booking.status', 'CHECKED_IN')->pluck('booking_id')->unique()->count(), 'holds' => $bookingDetails->where('booking.status', 'PENDING_PAYMENT')->pluck('booking_id')->unique()->count(), 'incidents' => $incidents->count()];
        $stats['incidents'] = $incidents->merge($courtIncidents)->unique('id')->count();
        foreach ($courts as $court) foreach ($dates as $day) {
            $key = $court->id.'|'.$day->toDateString();
            $details = $bookingDetails->filter(fn ($d) => $d->court_id === $court->id && $d->booking_date->toDateString() === $day->toDateString());
            $blocks = collect(); $occupied = []; $blocked = [];
            foreach ($timeSlots as $index => $slot) {
                $repair = $maintenance->first(fn ($m) => $m->court_id === $court->id && ($m->start_date ?? $m->maintenance_date)->toDateString() <= $day->toDateString() && ($m->end_date ?? $m->maintenance_date)->toDateString() >= $day->toDateString() && $m->start_time < $slot->end_time && $m->end_time > $slot->start_time);
                if ($repair || ($court->operational_status && $court->operational_status !== 'AVAILABLE')) $blocked[$slot->id] = $repair?->reason ?? $court->status_reason ?? 'Sân tạm ngừng phục vụ';
            }
            foreach ($details->groupBy('booking_id') as $group) {
                $booking = $group->first()->booking;
                $state = $booking->status;
                $hasIncident = $incidents->contains('booking_id', $booking->id);
                $active = $group->filter(fn ($d) => $d->status !== 'CANCELLED' && $state !== 'CANCELLED');
                foreach ($active as $d) $occupied[$d->time_slot_id] = true;
                if ($request->filled('status') && ($request->status === 'INCIDENT' ? ! $hasIncident : $request->status !== $state)) continue;
                $search = mb_strtolower(trim($request->query('search', '')));
                if ($search !== '' && ! str_contains(mb_strtolower($booking->booking_code.' '.$booking->user?->name), $search)) continue;
                $segments = []; $lastEnd = null;
                foreach ($group->sortBy('timeSlot.start_time') as $detail) {
                    if ($lastEnd !== $detail->timeSlot->start_time) $segments[] = collect();
                    $segments[array_key_last($segments)]->push($detail); $lastEnd = $detail->timeSlot->end_time;
                }
                foreach ($segments as $segment) {
                    $first = $segment->first(); $last = $segment->last();
                    $blocks->push(['booking' => $booking, 'state' => $first->status === 'CANCELLED' ? 'CANCELLED' : $state, 'incident' => $hasIncident,
                        'start' => substr($first->timeSlot->start_time, 0, 5), 'end' => substr($last->timeSlot->end_time, 0, 5),
                        'column' => max(0, $timeSlots->search(fn ($s) => $s->id === $first->time_slot_id)) + 1, 'span' => $segment->count()]);
                }
            }
            $available = [];
            if ($mode !== 'month' && !$request->filled('status') && !$request->filled('search')
                && $request->user()->hasPermission('bookings.view') && $request->user()->hasPermission('payments.counter')
                && $day->lte(today()->addDays(config('booking.max_days', 30)))) {
                foreach ($timeSlots as $slot) {
                    if (isset($occupied[$slot->id]) || isset($blocked[$slot->id]) || Carbon::parse($day->toDateString().' '.$slot->start_time)->lte(now())) continue;
                    if (app(\App\Services\CourtAvailabilityService::class)->checkAvailability($court->id, $day, $slot->id) === 'AVAILABLE'
                        && app(\App\Services\BookingService::class)->getCurrentPrice($court->id, $slot->id, $day) > 0) {
                        $available[] = $slot->id;
                        if ($mode === 'week') break;
                    }
                }
            }
            $cells[$key] = ['blocks' => $blocks, 'used' => count($occupied), 'blocked' => $blocked, 'available' => $available, 'issue' => $courtIncidents->firstWhere('court_id', $court->id),
                'free' => max(0, $timeSlots->count() - count(array_unique(array_merge(array_keys($occupied), array_keys($blocked))))),
                'count' => $details->pluck('booking_id')->unique()->count()];
        }
        $bookingActions = $bookingDetails->pluck('booking')->unique('id')->mapWithKeys(fn ($booking) => [$booking->id => app(\App\Services\StaffBookingUiService::class)->actions($booking, $request->user())]);
        return view('employee.schedule', compact('mode', 'date', 'dates', 'start', 'end', 'courts', 'allCourts', 'timeSlots', 'cells', 'stats', 'bookingActions'));
    }

    /**
     * Tính khoảng ngày hiển thị theo chế độ xem.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Collection<int, Carbon>}
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

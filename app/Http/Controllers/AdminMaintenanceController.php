<?php

namespace App\Http\Controllers;

use App\Models\BookingDetail;
use App\Models\Court;
use App\Models\MaintenanceSchedule;
use Illuminate\Http\Request;

class AdminMaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $this->admin($request);
        $courts = Court::orderBy('name')->get();
        $schedules = MaintenanceSchedule::with('court')->latest('start_date')->paginate(15);

        return view('admin.maintenance.index', compact('courts', 'schedules'));
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['court_id' => ['required', 'exists:courts,id'], 'start_date' => ['required', 'date', 'after_or_equal:today'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'reason' => ['required', 'string', 'max:2000']]);
        $affected = BookingDetail::with(['booking.user', 'timeSlot'])->where('court_id', $data['court_id'])->whereBetween('booking_date', [$data['start_date'], $data['end_date']])->whereHas('booking', fn ($q) => $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN']))->get();
        if ($affected->isNotEmpty()) {
            return back()->withInput()->with('affected_bookings', $affected)->with('error', 'Đang có booking trong khoảng bảo trì. Vui lòng xử lý booking trước.');
        }$court = Court::findOrFail($data['court_id']);
        MaintenanceSchedule::create($data + ['maintenance_date' => $data['start_date'], 'start_time' => $court->opening_time, 'end_time' => $court->closing_time, 'status' => 'SCHEDULED']);

        return back()->with('success', 'Đã tạo lịch bảo trì.');
    }

    public function cancel(MaintenanceSchedule $maintenance, Request $request)
    {
        $this->admin($request);
        $maintenance->update(['status' => 'CANCELLED']);

        return back()->with('success', 'Đã hủy lịch bảo trì.');
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN', 403);
    }
}

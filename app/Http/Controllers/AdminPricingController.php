<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\Holiday;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPricingController extends Controller
{
    public function index(Request $request)
    {
        $this->admin($request);
        $courts = Court::orderBy('name')->get();
        $selectedCourt = $courts->firstWhere('id', (int) $request->court_id) ?? $courts->first();
        $timeSlots = TimeSlot::orderBy('start_time')->get();
        $prices = $selectedCourt ? CourtPrice::where('court_id', $selectedCourt->id)->where('status', 'ACTIVE')->whereNull('effective_to')->get()->keyBy(fn ($p) => $p->time_slot_id.'-'.$p->day_type) : collect();
        $holidays = Holiday::orderBy('holiday_date')->get();

        return view('admin.pricing.index', compact('courts', 'selectedCourt', 'timeSlots', 'prices', 'holidays'));
    }

    public function storeSlot(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'duration' => ['required', 'integer', 'min:1']]);
        if ($this->overlaps($data['start_time'], $data['end_time'])) {
            return back()->withInput()->with('error', 'Khung giờ bị trùng với khung giờ hiện có.');
        } TimeSlot::create($data + ['status' => 'ACTIVE']);

        return back()->with('success', 'Đã tạo khung giờ.');
    }

    public function updateSlot(TimeSlot $timeSlot, Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'duration' => ['required', 'integer', 'min:1']]);
        if ($this->overlaps($data['start_time'], $data['end_time'], $timeSlot->id)) {
            return back()->with('error', 'Khung giờ bị trùng với khung giờ hiện có.');
        } $timeSlot->update($data);

        return back()->with('success', 'Đã cập nhật khung giờ.');
    }

    public function destroySlot(TimeSlot $timeSlot, Request $request)
    {
        $this->admin($request);
        if ($timeSlot->bookingDetails()->exists() || $timeSlot->courtPrices()->exists()) {
            $timeSlot->update(['status' => 'INACTIVE']);

            return back()->with('error', 'Khung giờ đã phát sinh dữ liệu nên được chuyển sang ngừng hoạt động.');
        } $timeSlot->delete();

        return back()->with('success', 'Đã xóa khung giờ.');
    }

    public function updatePrices(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['court_id' => ['required', 'exists:courts,id'], 'prices' => ['required', 'array'], 'prices.*.*' => ['nullable', 'numeric', 'min:0'], 'peak' => ['nullable', 'array']]);
        DB::transaction(function () use ($data) {
            foreach ($data['prices'] as $slotId => $types) {
                foreach (['WEEKDAY', 'WEEKEND', 'HOLIDAY'] as $type) {
                    $value = $types[$type] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }CourtPrice::where(['court_id' => $data['court_id'], 'time_slot_id' => $slotId, 'day_type' => $type, 'status' => 'ACTIVE'])->whereNull('effective_to')->update(['effective_to' => today()->subDay(), 'status' => 'INACTIVE']);
                    CourtPrice::create(['court_id' => $data['court_id'], 'time_slot_id' => $slotId, 'day_type' => $type, 'is_peak' => in_array((string) $slotId, $data['peak'] ?? [], true), 'price' => $value, 'effective_from' => today(), 'status' => 'ACTIVE']);
                }
            }
        });

        return back()->with('success', 'Đã lưu bảng giá mới. Booking cũ không bị thay đổi.');
    }

    public function storeHoliday(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'], 'name' => ['required', 'string', 'max:255']]);
        Holiday::create($data);

        return back()->with('success', 'Đã thêm ngày lễ.');
    }

    public function destroyHoliday(Holiday $holiday, Request $request)
    {
        $this->admin($request);
        $holiday->delete();

        return back()->with('success', 'Đã xóa ngày lễ.');
    }

    private function overlaps(string $start, string $end, ?int $except = null): bool
    {
        return TimeSlot::where('id', '!=', $except)->where('status', 'ACTIVE')->where('start_time', '<', $end)->where('end_time', '>', $start)->exists();
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN',403);
    }
}

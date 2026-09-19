<?php

namespace App\Http\Controllers;

use App\Models\{Booking, BookingAuditLog, Court, TimeSlot, User};
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeCounterController extends Controller
{
    public function create()
    {
        return view('employee.counter', [
            'courts' => Court::where('status', 'ACTIVE')->orderBy('name')->get(),
            'slots' => TimeSlot::where('status', 'ACTIVE')->orderBy('start_time')->get(),
        ]);
    }

    public function store(Request $request, BookingService $service)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'court_id' => ['required', 'exists:courts,id'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time_slot_ids' => ['required', 'array', 'min:1', 'max:24'],
            'time_slot_ids.*' => ['required', 'integer', 'distinct', 'exists:time_slots,id'],
        ]);
        try {
            $booking = DB::transaction(function () use ($data, $service, $request) {
                $customer = User::where('phone', $data['phone'])->lockForUpdate()->first();
                if (!$customer) {
                    if (empty($data['email']) || User::where('email', $data['email'])->exists()) {
                        throw ValidationException::withMessages(['email' => 'Khách mới cần email chưa được sử dụng.']);
                    }
                    $customer = User::create([
                        'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'],
                        'password' => Str::random(64), 'role' => 'CUSTOMER', 'status' => 'ACTIVE',
                    ]);
                }
                if ($customer->role !== 'CUSTOMER' || $customer->status !== 'ACTIVE') {
                    throw ValidationException::withMessages(['phone' => 'Tài khoản khách không hợp lệ hoặc đã khóa.']);
                }
                $details = array_map(fn ($id) => ['court_id' => $data['court_id'], 'booking_date' => $data['booking_date'], 'time_slot_id' => $id], $data['time_slot_ids']);
                $booking = $this->reserve($service, $customer->id, $details);
                $this->audit($booking, $request, 'COUNTER_CREATED');
                return $booking;
            });
        } catch (\DomainException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return redirect()->route('employee.bookings.show', $booking)->with('success', 'Đã giữ sân. Kiểm tra tổng tiền và xác nhận thanh toán tại quầy.');
    }

    public function extensionOptions(Request $request, Booking $booking, \App\Services\BookingExtensionService $extensions)
    {
        $data = $request->validate(['detail_id' => ['required', 'integer'], 'slot_count' => ['nullable', 'integer', 'min:1', 'max:8']]);
        try {
            $detail = $extensions->source($booking, $data['detail_id']);
            $count = (int) ($data['slot_count'] ?? 1);
            $slots = $extensions->nextSlots($detail, $count);
            $options = $extensions->options($detail, $slots);
            $timeline = collect();
            for ($length = 1; $length <= 8; $length++) {
                try { $upcoming = $extensions->nextSlots($detail, $length); }
                catch (\DomainException $e) { break; }
                $slot = $upcoming->last();
                $timeline->push(['slot' => $slot, 'count' => $length,
                    'courts' => $extensions->options($detail, collect([$slot]))->keyBy(fn ($option) => $option['court']->id)]);
            }
            return view('employee.extension-options', compact('booking', 'detail', 'slots', 'options', 'count', 'timeline'));
        } catch (\DomainException $e) {
            return redirect()->route('employee.bookings.show', $booking)->with('error', $e->getMessage());
        }
    }

    public function extend(Request $request, Booking $booking, \App\Services\BookingExtensionService $extensions)
    {
        $data = $request->validate([
            'detail_id' => ['required', 'integer'], 'time_slot_ids' => ['required', 'array', 'min:1', 'max:8'],
            'time_slot_ids.*' => ['required', 'integer', 'distinct', 'exists:time_slots,id'],
            'court_id' => ['nullable', 'integer', 'exists:courts,id'], 'quoted_price' => ['nullable', 'numeric', 'gt:0'],
        ]);
        try {
            $next = $extensions->create($booking, $data['detail_id'], $data['time_slot_ids'], $data['court_id'] ?? null, $request->user(), isset($data['quoted_price']) ? (float) $data['quoted_price'] : null);
        } catch (\DomainException $e) { return back()->withInput()->with('error', $e->getMessage()); }
        return redirect()->route('employee.bookings.show', $next)->with('success', __('employee.extension_created'));
    }

    public function checkoutSession(Request $request, Booking $booking, \App\Services\BookingExtensionService $extensions)
    {
        try { $extensions->checkoutSession($booking, $request->user()); }
        catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', __('employee.session_completed'));
    }

    public function scan(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10000']]);
        $decoded = json_decode($data['code'], true);
        $code = is_array($decoded) ? ($decoded['booking_code'] ?? '') : trim($data['code']);
        if (!is_string($code)) throw ValidationException::withMessages(['code' => 'Mã booking không hợp lệ.']);
        $booking = Booking::where('booking_code', $code)->first();
        if (!$booking) return back()->with('error', 'Không tìm thấy mã booking.');
        return redirect()->route('employee.bookings.show', $booking);
    }

    private function reserve(BookingService $service, int $userId, array $details): Booking
    {
        try { return $service->createBooking($userId, $details); }
        catch (\Exception $e) {
            $errors = json_decode($e->getMessage(), true);
            if (is_array($errors)) throw new \DomainException(collect($errors)->pluck('message')->implode('; '));
            throw $e;
        }
    }

    private function audit(Booking $booking, Request $request, string $action): void
    {
        BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => $action, 'reason' => 'Thao tác tại quầy', 'new_values' => ['extension_of_id' => $booking->extension_of_id]]);
    }
}

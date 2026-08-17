<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminBookingController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        $this->admin($request);
        $bookings = Booking::with(['user', 'bookingDetails.court'])->when($request->filled('search'), fn ($q) => $q->where(fn ($i) => $i->where('booking_code', 'like', '%'.$request->search.'%')->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%'))))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->when($request->filled('date'), fn ($q) => $q->whereHas('bookingDetails', fn ($d) => $d->whereDate('booking_date', $request->date)))->latest()->paginate(15)->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking, Request $request)
    {
        $this->admin($request);
        $booking->load(['user', 'bookingDetails.court', 'bookingDetails.timeSlot', 'payment', 'auditLogs.actor']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function update(Booking $booking, Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['PENDING_PAYMENT', 'CONFIRMED', 'CHECKED_IN', 'COMPLETED'])], 'note' => ['nullable', 'string', 'max:2000'], 'reason' => ['required', 'string', 'max:1000']]);
        try {
            DB::transaction(function () use ($booking, $request, $data) {
                $locked = Booking::lockForUpdate()->findOrFail($booking->id);
                if (in_array($locked->status, ['COMPLETED', 'CANCELLED', 'EXPIRED'], true)) {
                    throw new \DomainException('Booking ở trạng thái kết thúc và không thể thay đổi.');
                }$allowed = ['PENDING_PAYMENT' => ['PENDING_PAYMENT', 'CONFIRMED'], 'CONFIRMED' => ['CONFIRMED', 'CHECKED_IN'], 'CHECKED_IN' => ['CHECKED_IN', 'COMPLETED']];
                if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                    throw new \DomainException('Chuyển trạng thái booking không hợp lệ.');
                }$old = $locked->only(['status', 'note']);
                $locked->update(['status' => $data['status'], 'note' => $data['note'] ?? null, 'confirmed_at' => $data['status'] === 'CONFIRMED' ? ($locked->confirmed_at ?? now()) : $locked->confirmed_at, 'checked_in_at' => $data['status'] === 'CHECKED_IN' ? ($locked->checked_in_at ?? now()) : $locked->checked_in_at, 'checked_out_at' => $data['status'] === 'COMPLETED' ? ($locked->checked_out_at ?? now()) : $locked->checked_out_at]);
                $this->audit($locked, $request, 'UPDATED', $old, $locked->only(['status', 'note']), $data['reason']);
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

return back()->with('success', 'Đã cập nhật booking và ghi Audit Log.');
    }

    public function cancel(Booking $booking, Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        try {
            DB::transaction(function () use ($booking, $request, $data) {
                $locked = Booking::with('payment')->lockForUpdate()->findOrFail($booking->id);
                if (! in_array($locked->status, ['PENDING_PAYMENT', 'CONFIRMED'], true)) {
                    throw new \DomainException('Booking không còn ở trạng thái có thể hủy.');
                }$old = $locked->only(['status', 'payment_status']);
                $locked->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
                $locked->bookingDetails()->update(['status' => 'CANCELLED']);
                if ($locked->payment?->status === 'PAID') {
                    $this->payments->refund($locked->payment);
                }$this->audit($locked, $request, 'CANCELLED', $old, $locked->fresh()->only(['status', 'payment_status']), $data['reason']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

return back()->with('success', 'Đã hủy booking và ghi Audit Log.');
    }

    private function audit(Booking $booking, Request $request, string $action, array $old, array $new, string $reason): void
    {
        BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => $action, 'old_values' => $old, 'new_values' => $new, 'reason' => $reason, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN',403);
    }
}

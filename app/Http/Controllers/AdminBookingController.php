<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDetail;
use App\Models\Court;
use App\Services\CourtAvailabilityService;
use App\Services\CustomerNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminBookingController extends Controller
{
    public function __construct(
        private readonly CustomerNotificationService $notifications
    ) {}

    public function index(Request $request)
    {
        $this->admin($request);
        $bookings = Booking::with(['user', 'bookingDetails.court'])->when($request->boolean('fixed'), fn ($q) => $q->whereNotNull('fixed_booking_id'))->when($request->filled('search'), fn ($q) => $q->where(fn ($i) => $i->where('booking_code', 'like', '%'.$request->search.'%')->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%'))))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->when($request->filled('date'), fn ($q) => $q->whereHas('bookingDetails', fn ($d) => $d->whereDate('booking_date', $request->date)))->latest()->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking, Request $request)
    {
        $this->admin($request);
        $booking->load(['user', 'bookingDetails.court', 'bookingDetails.timeSlot', 'payment', 'auditLogs.actor']);

        $courts = Court::where('status', 'ACTIVE')->orderBy('name')->get();

        return view('admin.bookings.show', compact('booking', 'courts'));
    }

    public function changeCourt(Booking $booking, BookingDetail $detail, Request $request, CourtAvailabilityService $availability)
    {
        $this->admin($request);
        abort_unless($detail->booking_id === $booking->id, 404);
        $data = $request->validate(['court_id' => ['required', 'exists:courts,id'], 'reason' => ['required', 'string', 'max:1000']]);

        try {
            DB::transaction(function () use ($booking, $detail, $request, $data, $availability) {
                $locked = Booking::lockForUpdate()->findOrFail($booking->id);
                if (! in_array($locked->status, ['PENDING_PAYMENT', 'CONFIRMED'], true)) {
                    throw new \DomainException('Booking không thể chuyển sân ở trạng thái hiện tại.');
                }
                if ($locked->status === 'PENDING_PAYMENT' && $locked->fixedBooking && $locked->fixedBooking->status !== 'LEGACY') {
                    throw new \DomainException('Danh sách lịch cố định đang chờ thanh toán đã chốt. Vui lòng đặt lại lịch để thay đổi.');
                }
                $lockedDetail = BookingDetail::with('court')->lockForUpdate()->findOrFail($detail->id);
                $newCourt = Court::where('status', 'ACTIVE')->findOrFail($data['court_id']);
                if ($lockedDetail->court_id === $newCourt->id) {
                    throw new \DomainException('Vui lòng chọn một sân khác.');
                }
                if ($availability->checkAvailability($newCourt->id, $lockedDetail->booking_date, $lockedDetail->time_slot_id) !== CourtAvailabilityService::STATUS_AVAILABLE) {
                    throw new \DomainException('Sân mới không trống trong khung giờ này.');
                }

                $oldCourt = $lockedDetail->court->name;
                $lockedDetail->update(['court_id' => $newCourt->id]);
                $this->notifications->courtChanged($locked, $oldCourt, $newCourt->name);
                $this->audit($locked, $request, 'COURT_CHANGED', ['court' => $oldCourt], ['court' => $newCourt->name], $data['reason']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã chuyển sân và thông báo cho khách hàng.');
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
                if ($locked->status === 'PENDING_PAYMENT' && $locked->isHoldExpired()) {
                    throw new \DomainException('Thời gian giữ chỗ đã hết. Vui lòng tạo đơn mới.');
                }
                if ($locked->fixedBooking && $locked->fixedBooking->status !== 'LEGACY' && $locked->payment?->status !== 'PAID' && $data['status'] !== 'PENDING_PAYMENT') {
                    throw new \DomainException('Toàn bộ lịch cố định phải thanh toán thành công trước khi xác nhận.');
                }
                if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                    throw new \DomainException('Chuyển trạng thái booking không hợp lệ.');
                }$old = $locked->only(['status', 'note']);
                if ($locked->status !== $data['status'] && in_array($data['status'], ['CHECKED_IN', 'COMPLETED'])) {
                    $operations = app(\App\Services\BookingOperationsService::class);
                    if ($data['status'] === 'CHECKED_IN') $operations->checkIn($locked, $request->user());
                    else $operations->checkout($locked, $request->user());
                    $locked->refresh()->update(['note' => $data['note'] ?? null]);
                    $this->audit($locked, $request, 'UPDATED', $old, $locked->only(['status', 'note']), $data['reason']);
                    return;
                }
                $locked->update(['status' => $data['status'], 'note' => $data['note'] ?? null, 'confirmed_at' => $data['status'] === 'CONFIRMED' ? ($locked->confirmed_at ?? now()) : $locked->confirmed_at, 'checked_in_at' => $data['status'] === 'CHECKED_IN' ? ($locked->checked_in_at ?? now()) : $locked->checked_in_at, 'checked_out_at' => $data['status'] === 'COMPLETED' ? ($locked->checked_out_at ?? now()) : $locked->checked_out_at]);
                if ($old['status'] !== $data['status']) {
                    $this->notifications->statusChanged($locked, $data['status']);
                }
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
                if ($locked->payment_status === 'PAID' || $locked->payment?->status === 'PAID') {
                    throw new \DomainException('Đơn đã thanh toán không thể hủy.');
                }
                if ($locked->status !== 'PENDING_PAYMENT') {
                    throw new \DomainException('Booking không còn ở trạng thái có thể hủy.');
                }$old = $locked->only(['status', 'payment_status']);
                $locked->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
                $locked->bookingDetails()->update(['status' => 'CANCELLED']);
                if ($old['status'] === 'PENDING_PAYMENT') {
                    $this->notifications->rejected($locked, $data['reason']);
                } else {
                    $this->notifications->cancelled($locked, $data['reason']);
                }
                $this->audit($locked, $request, 'CANCELLED', $old, $locked->fresh()->only(['status', 'payment_status']), $data['reason']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã hủy booking và ghi Audit Log.');
    }

    public function reschedule(Booking $booking, BookingDetail $detail, Request $request, CourtAvailabilityService $availability)
    {
        abort(410, 'Chức năng đổi lịch đã ngừng sử dụng.');
        $this->admin($request);
        abort_unless($detail->booking_id === $booking->id, 404);
        $data = $request->validate([
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time_slot_id' => ['required', 'exists:time_slots,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        try {
            DB::transaction(function () use ($booking, $detail, $request, $data, $availability) {
                // Match the court lock used by new bookings to serialize competing reservations.
                $court = Court::lockForUpdate()->findOrFail($detail->court_id);
                $locked = Booking::lockForUpdate()->findOrFail($booking->id);
                $line = BookingDetail::lockForUpdate()->findOrFail($detail->id);
                if ($line->court_id !== $court->id || ! in_array($locked->status, ['PENDING_PAYMENT', 'CONFIRMED'], true) || $locked->fixed_booking_id || $locked->isHoldExpired() && $locked->status === 'PENDING_PAYMENT' || $line->status === 'CANCELLED') {
                    throw new \DomainException('Booking không thể đổi lịch trực tiếp. Vui lòng xử lý qua yêu cầu hỗ trợ.');
                }
                $slot = \App\Models\TimeSlot::where('status', 'ACTIVE')->findOrFail($data['time_slot_id']);
                $date = \Carbon\Carbon::parse($data['booking_date']);
                $start = $date->copy()->setTimeFromTimeString($slot->start_time);
                if ($start <= now() || substr($slot->start_time, 0, 5) < substr($court->opening_time, 0, 5) || substr($slot->end_time, 0, 5) > substr($court->closing_time, 0, 5)) {
                    throw new \DomainException('Khung giờ nằm ngoài giờ mở cửa hoặc đã bắt đầu.');
                }
                if ($line->booking_date->toDateString() === $date->toDateString() && $line->time_slot_id === $slot->id) {
                    throw new \DomainException('Vui lòng chọn ngày hoặc khung giờ khác.');
                }
                if ($availability->checkAvailability($court->id, $date, $slot->id) !== CourtAvailabilityService::STATUS_AVAILABLE) {
                    throw new \DomainException('Khung giờ mới không còn trống.');
                }
                $price = app(\App\Services\BookingService::class)->getCurrentPrice($court->id, $slot->id, $date);
                if ($price === null || (int) round($price * 100) !== (int) round($line->price * 100)) {
                    throw new \DomainException('Khung giờ mới khác giá. Vui lòng xử lý chênh lệch qua yêu cầu hỗ trợ.');
                }
                $old = $line->only(['booking_date', 'time_slot_id']);
                $line->update(['booking_date' => $date, 'time_slot_id' => $slot->id]);
                $locked->update(['start_date' => $locked->bookingDetails()->min('booking_date'), 'end_date' => $locked->bookingDetails()->max('booking_date')]);
                $this->audit($locked, $request, 'RESCHEDULED', $old, $line->only(['booking_date', 'time_slot_id']), $data['reason']);
                $this->notifications->rescheduled($locked, $date->format('d/m/Y').' '.$slot->name);
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Đã đổi lịch và lưu lịch sử thay đổi.');
    }

    public function destroy(Booking $booking, Request $request)
    {
        $this->admin($request);
        DB::transaction(function () use ($booking, $request) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            abort_unless(in_array($locked->status, ['CANCELLED', 'EXPIRED'], true)
                && in_array($locked->payment_status, ['PENDING', 'FAILED'], true) && ! $locked->fixed_booking_id
                && ! $locked->payments()->where(fn ($q) => $q->whereIn('status', ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'])->orWhereNotNull('paid_at'))->exists()
                && ! $locked->refundRequests()->exists(), 422, 'Chỉ xóa khỏi danh sách đơn đã hủy/hết hạn chưa thanh toán.');
            $this->audit($locked, $request, 'ARCHIVED', ['status' => $locked->status], [], 'Xóa khỏi danh sách quản trị');
            $locked->delete();
        });
        return redirect()->route('admin.bookings.index')->with('success', 'Đã xóa khỏi danh sách và giữ lịch sử.');
    }

    private function audit(Booking $booking, Request $request, string $action, array $old, array $new, string $reason): void
    {
        BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => $action, 'old_values' => $old, 'new_values' => $new, 'reason' => $reason, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN', 403);
    }
}

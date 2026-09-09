<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\Court;
use App\Models\IncidentResolution;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Services\BookingService;
use App\Services\CourtAvailabilityService;
use App\Services\CustomerNotificationService;
use App\Services\IncidentTicketService;
use App\Services\RefundRecipientService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentResolutionController extends Controller
{
    public function choose(Request $request, IncidentResolution $resolution, CourtAvailabilityService $availability, BookingService $pricing, CustomerNotificationService $notifications)
    {
        abort_unless($resolution->booking->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'choice' => ['required', Rule::in(['REFUND', 'RESCHEDULE', 'CHANGE_COURT'])],
            'court_id' => ['required_unless:choice,REFUND', 'nullable', 'exists:courts,id'],
            'date' => ['required_unless:choice,REFUND', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time_slot_id' => ['required_unless:choice,REFUND', 'nullable', 'exists:time_slots,id'],
        ]);
        $recipient = $data['choice'] === 'REFUND' ? $request->validate(RefundRecipientService::rules()) : null;
        DB::transaction(function () use ($resolution, $request, $data, $recipient, $availability, $pricing, $notifications) {
            // Court first, matching normal booking creation and incident processing.
            $court = $data['choice'] !== 'REFUND' ? Court::lockForUpdate()->findOrFail($data['court_id']) : null;
            $booking = Booking::lockForUpdate()->findOrFail($resolution->booking_id);
            $payment = Payment::forBooking($booking)->lockForUpdate()->firstOrFail();
            $item = IncidentResolution::lockForUpdate()->findOrFail($resolution->id);
            if ($item->status !== 'AWAITING_CHOICE' || ! in_array($payment->status, ['PAID', 'PARTIALLY_REFUNDED'], true)) {
                $this->invalid('Lựa chọn đã được xử lý hoặc thanh toán cần đối soát.');
            }
            $detail = $item->detail()->lockForUpdate()->firstOrFail();
            if ($detail->status !== 'CANCELLED') {
                $this->invalid('Lượt sân không còn chờ xử lý sự cố.');
            }
            $refund = (float) $item->refund_amount;
            if ($data['choice'] !== 'REFUND') {
                $slot = TimeSlot::lockForUpdate()->findOrFail($data['time_slot_id']);
                $original = $item->original_slot;
                if ($data['choice'] === 'RESCHEDULE' && $court->id !== $original['court_id']) {
                    $this->invalid('Đổi lịch giữ nguyên sân; chọn Đổi sân nếu cần sân khác.');
                }
                if ($data['choice'] === 'CHANGE_COURT' && ($court->id === $original['court_id'] || $data['date'] !== $original['date'] || $slot->id !== $original['time_slot_id'])) {
                    $this->invalid('Đổi sân giữ nguyên ngày và khung giờ.');
                }
                if ($slot->status !== 'ACTIVE' || Carbon::parse($data['date'].' '.$slot->start_time)->isPast()) {
                    $this->invalid('Khung giờ đã qua hoặc không còn hoạt động.');
                }
                $oldDuration = Carbon::parse($original['start_time'])->diffInMinutes(Carbon::parse($original['end_time'])) * ($original['unused_ratio'] ?? 1);
                if (abs($oldDuration - Carbon::parse($slot->start_time)->diffInMinutes(Carbon::parse($slot->end_time))) > 0.01) {
                    $this->invalid('Vui lòng chọn khung giờ có cùng thời lượng chưa sử dụng.');
                }
                if (($court->opening_time && $slot->start_time < $court->opening_time) || ($court->closing_time && $slot->end_time > $court->closing_time)) {
                    $this->invalid('Khung giờ ngoài giờ mở cửa.');
                }
                if ($availability->checkAvailability($court->id, Carbon::parse($data['date']), $slot->id) !== 'AVAILABLE') {
                    $this->invalid('Sân/giờ này không còn trống. Vui lòng chọn lại.');
                }
                $newPrice = $pricing->getCurrentPrice($court->id, $slot->id, Carbon::parse($data['date']));
                if ($newPrice === null) {
                    $this->invalid('Chưa có giá cho sân/giờ đã chọn.');
                }
                $refund = max(0, round((float) $item->refund_amount - (float) $newPrice, 2));
                $detail->update(['court_id' => $court->id, 'booking_date' => $data['date'], 'time_slot_id' => $slot->id, 'status' => 'CONFIRMED']);
                $booking->update(['status' => 'CONFIRMED', 'cancelled_at' => null, 'start_date' => $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->min('booking_date'), 'end_date' => $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->max('booking_date')]);
            }
            if ($refund > 0) {
                $reserved = $booking->refundRequests()->whereIn('status', ['PENDING', 'NEEDS_INFO', 'APPROVED'])->sum('amount');
                if (round(($reserved + $refund) * 100) > round((float) $booking->total_amount * 100)) {
                    $this->invalid('Tổng tiền hoàn vượt thanh toán; vui lòng liên hệ nhân viên.');
                }
                $refundRequest = $item->refundRequests()->create(['booking_id' => $booking->id, 'requested_by' => $request->user()->id, 'amount' => $refund, 'reason_code' => $item->incident->type, 'reason' => $item->incident->description, 'supporting_information' => $data['choice'] === 'REFUND' ? 'Khách chọn hoàn phần dịch vụ không được cung cấp.' : 'Hoàn chênh lệch sau đổi lịch/sân; SmashZone chịu chênh lệch tăng.', 'status' => 'PENDING', 'cancel_booking' => false]);
            }
            if ($recipient && isset($refundRequest)) {
                app(RefundRecipientService::class)->save($refundRequest, $recipient);
            }
            $item->update(['choice' => $data['choice'], 'status' => $refund > 0 ? 'REFUND_PENDING' : 'RESOLVED', 'resolved_at' => $refund > 0 ? null : now()]);
            BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => 'CUSTOMER_RESOLUTION', 'old_values' => $item->original_slot, 'new_values' => $data + ['refund_amount' => $refund], 'reason' => $item->incident->description, 'ip_address' => $request->ip()]);
            $notifications->resolutionChosen($booking, $item->id, $data['choice'], $refund);
            app(IncidentTicketService::class)->closeIfResolved($item);
        });

        return back()->with('success', 'Đã ghi nhận lựa chọn và cập nhật lịch. Khoản hoàn (nếu có) đang chờ Admin duyệt.');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['choice' => $message]);
    }
}

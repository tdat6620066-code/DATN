<?php

namespace App\Services;

use App\Events\CustomerNotificationCreated;
use App\Mail\CustomerAlertMail;
use App\Models\Booking;
use App\Models\FixedBooking;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

class CustomerNotificationService
{
    private const STATUS_LABELS = [
        'NO_SHOW' => 'Khách không đến',
        'PENDING_PAYMENT' => 'Chờ thanh toán',
        'CONFIRMED' => 'Đã xác nhận',
        'CHECKED_IN' => 'Đã check-in',
        'COMPLETED' => 'Đã hoàn thành',
        'CANCELLED' => 'Đã hủy',
        'EXPIRED' => 'Đã hết hạn',
    ];

    public function bookingCreated(Booking $booking): Notification
    {
        return $this->send($booking, 'BOOKING_CREATED', '🔔 Đặt sân thành công',
            "Đơn {$booking->booking_code} đã được tạo. Vui lòng thanh toán trước khi thời gian giữ chỗ kết thúc.",
            "booking-created:{$booking->id}");
    }

    public function payment(Booking $booking, string $status): Notification
    {
        $paid = $status === 'PAID';
        return $this->send($booking, 'PAYMENT', $paid ? '💳 Thanh toán thành công' : '💳 Thanh toán chưa thành công',
            $paid ? "Đơn {$booking->booking_code} đã được thanh toán và xác nhận." : "Thanh toán đơn {$booking->booking_code} chưa thành công. Vui lòng thử lại.",
            "payment:{$status}:{$booking->id}");
    }

    public function cancelled(Booking $booking, ?string $reason = null): Notification
    {
        $content = "Đơn {$booking->booking_code} đã được hủy.".($reason ? " Lý do: {$reason}" : '');
        return $this->send($booking, 'BOOKING_CANCELLED', '❌ Hủy booking', $content, "booking-cancelled:{$booking->id}");
    }

    public function rejected(Booking $booking, ?string $reason = null): Notification
    {
        $content = "Yêu cầu đặt sân {$booking->booking_code} không được chấp thuận.".($reason ? " Lý do: {$reason}" : '');
        return $this->send($booking, 'BOOKING_REJECTED', '❌ Booking bị từ chối', $content, "booking-rejected:{$booking->id}");
    }

    public function rescheduled(Booking $booking, string $schedule): Notification
    {
        return $this->send($booking, 'BOOKING_RESCHEDULED', 'Cập nhật lịch đặt sân',
            "Đơn {$booking->booking_code} đã được đổi lịch: {$schedule}.", 'rescheduled:'.$booking->id.':'.\Illuminate\Support\Str::uuid());
    }

    public function courtChanged(Booking $booking, string $oldCourt, string $newCourt): Notification
    {
        return $this->send($booking, 'COURT_CHANGED', '⚠️ Cập nhật booking',
            "Đơn {$booking->booking_code} được chuyển từ {$oldCourt} sang {$newCourt}.", "court-changed:{$booking->id}:".md5($oldCourt.'|'.$newCourt));
    }

    public function refunded(Booking $booking, ?float $amount = null, ?int $requestId = null): Notification
    {
        return $this->send($booking, 'REFUND', '💰 Hoàn tiền thành công',
            "Booking {$booking->booking_code}. Số tiền hoàn: ".number_format($amount ?? (float) $booking->payment->amount).'đ. Trạng thái: '.($booking->payment_status === 'PARTIALLY_REFUNDED' ? 'Đã hoàn tiền một phần.' : 'Đã hoàn tiền.'), "refund:{$booking->id}:{$requestId}");
    }

    public function refundProgress(\App\Models\RefundRequest $request, string $stage): Notification
    {
        $label = match ($stage) {
            'APPROVED' => 'Admin phê duyệt hoàn '.number_format($request->amount).'đ',
            'REJECTED' => 'Admin từ chối yêu cầu hoàn tiền',
            'PROCESSING' => 'Đang xử lý hoàn '.number_format($request->amount).'đ',
            default => 'Yêu cầu hoàn tiền đã được tạo',
        };
        return $this->send($request->booking, 'REFUND', $label,
            'Booking '.$request->booking->booking_code.' · Yêu cầu hoàn #'.$request->id.'. '.$label.'. '.($stage === 'REJECTED' ? $request->decision_note : 'Mở booking để theo dõi tiến trình.'), 'refund-progress:'.$request->id.':'.$stage);
    }

    public function incidentAffected(Booking $booking, string $code, string $reason, bool $paid): Notification
    {
        $slots = $booking->incidentResolutions()->whereHas('incident', fn ($q) => $q->where('incident_code', $code))->get()->map(function ($item) {
            $slot = $item->original_slot;
            return "{$slot['court']} · {$slot['date']} {$slot['start_time']}–{$slot['end_time']} · Phần chưa sử dụng: ".number_format($item->refund_amount).'đ';
        })->implode('; ');
        return $this->send($booking, 'BOOKING_STATUS', 'Thông báo sự cố sân',
            "⚠️ Lượt sân bị ảnh hưởng trong booking {$booking->booking_code} đã bị hủy: {$reason}. {$slots}. ".($paid ? 'Vui lòng mở booking để chọn hoàn tiền, đổi lịch hoặc đổi sân. Chưa tạo yêu cầu hoàn tiền khi bạn chưa chọn.' : 'Các lượt không thể phục vụ đã được hủy.'), "incident:{$code}:{$booking->id}");
    }

    public function resolutionChosen(Booking $booking, int $resolutionId, string $choice, float $amount): Notification
    {
        return $this->send($booking, 'BOOKING_STATUS', $choice === 'REFUND' ? 'Yêu cầu hoàn tiền đã được tạo' : 'Đổi lịch/sân thành công',
            "Booking {$booking->booking_code}. ".($amount > 0 ? 'Yêu cầu hoàn tiền '.number_format($amount).'đ đã được tạo, đang chờ duyệt.' : 'Đã chuyển lịch, không phát sinh khoản phải trả thêm.'), "resolution:{$resolutionId}");
    }

    public function statusChanged(Booking $booking, string $status): Notification
    {
        $label = self::STATUS_LABELS[$status] ?? $status;
        return $this->send($booking, 'BOOKING_STATUS', '⚠️ Cập nhật booking',
            "Đơn {$booking->booking_code} chuyển sang trạng thái: {$label}.", "booking-status:{$status}:{$booking->id}");
    }

    public function reminder(Booking $booking, int $detailId, string $court, string $time): ?Notification
    {
        if (! $booking->user->notificationEnabled('reminder')) return null;

        return $this->send($booking, 'BOOKING_REMINDER', '⏰ Nhắc lịch',
            "Lịch tại {$court} bắt đầu lúc {$time}. Vui lòng đến sớm để check-in.", "booking-reminder:{$detailId}");
    }

    public function fixedBooking(FixedBooking $group, string $event): Notification
    {
        [$type, $title, $message] = match ($event) {
            'CREATED' => ['BOOKING_CREATED', '🔔 Đã tạo lịch cố định', 'Vui lòng thanh toán một lần trước khi hết thời gian giữ chỗ.'],
            'PAID' => ['PAYMENT', '💳 Lịch cố định đã thanh toán', 'Toàn bộ lịch đã được thanh toán và xác nhận.'],
            'FAILED' => ['PAYMENT', '💳 Thanh toán lịch cố định chưa thành công', 'Chỗ đã được giải phóng. Vui lòng kiểm tra lịch và tạo đơn mới để thanh toán.'],
            'EXPIRED' => ['BOOKING_STATUS', 'Lịch cố định hết hạn giữ chỗ', 'Các chỗ chưa thanh toán đã được giải phóng.'],
        };
        $content = 'Đơn '.$group->code.' · '.$group->bookings()->count().' buổi · Tổng '.number_format($group->total_price ?? $group->bookings()->sum('total_amount'), 0, ',', '.').'đ. '.$message;
        return $this->send($group, $type, $title, $content, 'fixed-booking:'.$event.':'.$group->id);
    }

    private function send(Booking|FixedBooking $booking, string $type, string $title, string $content, string $key): Notification
    {
        $notification = Notification::firstOrCreate(['unique_key' => $key], [
            'user_id' => $booking->user_id,
            'booking_id' => $booking instanceof Booking ? $booking->id : null,
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'action_url' => route($booking instanceof FixedBooking ? 'bookings.fixed.show' : 'bookings.show', $booking),
            'is_read' => false,
        ]);

        if ($notification->wasRecentlyCreated) CustomerNotificationCreated::dispatch($notification);

        if ($notification->wasRecentlyCreated && $booking->user->notificationEnabled('email')) {
            Mail::to($booking->user)->queue((new CustomerAlertMail($notification))->afterCommit());
        }

        return $notification;
    }
}

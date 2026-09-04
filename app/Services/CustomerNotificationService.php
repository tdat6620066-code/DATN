<?php

namespace App\Services;

use App\Events\CustomerNotificationCreated;
use App\Mail\CustomerAlertMail;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;

class CustomerNotificationService
{
    private const STATUS_LABELS = [
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

    public function courtChanged(Booking $booking, string $oldCourt, string $newCourt): Notification
    {
        return $this->send($booking, 'COURT_CHANGED', '⚠️ Cập nhật booking',
            "Đơn {$booking->booking_code} được chuyển từ {$oldCourt} sang {$newCourt}.", "court-changed:{$booking->id}:".md5($oldCourt.'|'.$newCourt));
    }

    public function refunded(Booking $booking): Notification
    {
        return $this->send($booking, 'REFUND', '💰 Hoàn tiền',
            "Khoản thanh toán của đơn {$booking->booking_code} đã được hoàn tiền.", "refund:{$booking->id}");
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

    private function send(Booking $booking, string $type, string $title, string $content, string $key): Notification
    {
        $notification = Notification::firstOrCreate(['unique_key' => $key], [
            'user_id' => $booking->user_id,
            'booking_id' => $booking->id,
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'action_url' => route('bookings.show', $booking),
            'is_read' => false,
        ]);

        if ($notification->wasRecentlyCreated) CustomerNotificationCreated::dispatch($notification);

        if ($notification->wasRecentlyCreated && $booking->user->notificationEnabled('email')) {
            Mail::to($booking->user)->queue((new CustomerAlertMail($notification))->afterCommit());
        }

        return $notification;
    }
}

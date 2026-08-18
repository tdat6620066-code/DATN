<?php

namespace App\Notifications;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RefundRequestStatusChanged extends Notification
{
    use Queueable;

    public function __construct(private readonly RefundRequest $refundRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'refund_request_id' => $this->refundRequest->id,
            'booking_id' => $this->refundRequest->booking_id,
            'status' => $this->refundRequest->status,
            'message' => match ($this->refundRequest->status) {
                'APPROVED' => 'Yêu cầu hoàn tiền đã được phê duyệt.',
                'REJECTED' => 'Yêu cầu hoàn tiền đã bị từ chối.',
                default => 'Yêu cầu hoàn tiền cần bổ sung thông tin.',
            },
        ];
    }
}

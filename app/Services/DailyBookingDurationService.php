<?php

namespace App\Services;

use App\Models\BookingDetail;
use App\Models\TimeSlot;

class DailyBookingDurationService
{
    public function totalMinutes(int $userId, string $date, array $slotIds): int
    {
        $existing = BookingDetail::query()->whereDate('booking_date', $date)
            ->whereNotIn('status', ['CANCELLED', 'EXPIRED'])
            ->whereHas('booking', fn ($query) => $query->where('user_id', $userId)
                ->where(fn ($active) => $active->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'])
                    ->orWhere(fn ($hold) => $hold->where('status', 'PENDING_PAYMENT')->where('hold_expires_at', '>', now()))))
            ->with('timeSlot')->get()->sum(fn ($detail) => (int) ($detail->timeSlot?->duration ?? 0));

        return $existing + (int) TimeSlot::whereIn('id', $slotIds)->sum('duration');
    }

    public function warning(int $minutes, string $date): string
    {
        return "Tổng thời lượng đặt sân của bạn ngày {$date} là {$minutes} phút (gồm lịch đã đặt và đang chọn). Bạn đang đặt từ 4 giờ trong một ngày. Hãy cân nhắc thời gian nghỉ và thể trạng trước khi tiếp tục.";
    }
}

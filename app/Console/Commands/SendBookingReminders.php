<?php

namespace App\Console\Commands;

use App\Models\BookingDetail;
use App\Services\CustomerNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';
    protected $description = 'Gửi thông báo cho khách trước giờ chơi khoảng 60 phút';

    public function handle(CustomerNotificationService $notifications): int
    {
        $from = now()->addMinutes(55);
        $to = now()->addMinutes(65);

        BookingDetail::with(['booking.user', 'court', 'timeSlot'])
            ->whereDate('booking_date', $from->toDateString())
            ->whereHas('booking', fn ($query) => $query->where('status', 'CONFIRMED'))
            ->chunkById(200, function ($details) use ($notifications, $from, $to) {
                foreach ($details as $detail) {
                    $startsAt = Carbon::parse($detail->booking_date->toDateString().' '.$detail->timeSlot->start_time);
                    if ($startsAt->betweenIncluded($from, $to)) {
                        $notifications->reminder($detail->booking, $detail->id, $detail->court->name, $startsAt->format('H:i d/m/Y'));
                    }
                }
            });

        return self::SUCCESS;
    }
}

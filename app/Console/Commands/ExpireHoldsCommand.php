<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\CustomerNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireHoldsCommand extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Expire hold bookings that have exceeded their hold timeout';

    /**
     * UC23 - Execute the command to expire holds
     */
    public function handle(CustomerNotificationService $notifications)
    {
        $expired = DB::transaction(function () use ($notifications) {
            // Find bookings with expired holds
            $bookings = Booking::where(function ($query) {
                $query->where('status', 'PENDING_PAYMENT')
                    ->orWhere('status', 'HOLD');
            })
            ->where('hold_expires_at', '<', now())
            ->lockForUpdate()
            ->get();

            $count = 0;

            foreach ($bookings as $booking) {
                // Update booking status to EXPIRED
                $booking->update(['status' => 'EXPIRED']);

                // Update all booking details to CANCELLED
                $booking->bookingDetails()->update(['status' => 'CANCELLED']);

                // Update payment status if exists
                if ($booking->payment) {
                    $booking->payment->update(['status' => 'FAILED']);
                }

                $notifications->statusChanged($booking, 'EXPIRED');

                $count++;
            }

            return $count;
        });

        $this->info("Expired {$expired} holds");
        return 0;
    }
}

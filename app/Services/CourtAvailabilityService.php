<?php

namespace App\Services;

use App\Models\{Booking, BookingDetail, Court, MaintenanceSchedule, TimeSlot};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourtAvailabilityService
{
    const STATUS_AVAILABLE = 'AVAILABLE';
    const STATUS_BOOKED = 'BOOKED';
    const STATUS_HOLD = 'HOLD';
    const STATUS_MAINTENANCE = 'MAINTENANCE';

    /**
     * Check availability of a court on a specific date and time slot
     */
    public function checkAvailability(int $courtId, Carbon $date, int $timeSlotId)
    {
        // Check if court exists and is active
        $court = Court::find($courtId);
        // Older courts have no operational status yet. A null value means the
        // court has not been explicitly taken offline and should be bookable.
        if (! $court || $court->status !== 'ACTIVE'
            || ($court->operational_status !== null && $court->operational_status !== 'AVAILABLE')) {
            return null;
        }

        // Check maintenance schedule
        if ($this->isMaintenance($courtId, $date, $timeSlotId)) {
            return self::STATUS_MAINTENANCE;
        }

        // Check if time slot is booked
        if ($this->isBooked($courtId, $date, $timeSlotId)) {
            return self::STATUS_BOOKED;
        }

        // Unpaid bookings do not occupy the slot. It remains available and
        // displays its normal price until payment is confirmed.
        return self::STATUS_AVAILABLE;
    }

    /**
     * Get availability for all time slots on a specific date
     */
    public function getAvailabilityByDate(int $courtId, Carbon $date)
    {
        $timeSlots = TimeSlot::where('status', 'ACTIVE')->get();
        $availability = [];

        foreach ($timeSlots as $slot) {
            $status = $this->checkAvailability($courtId, $date, $slot->id);
            $availability[] = [
                'time_slot_id' => $slot->id,
                'name' => $slot->name,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'status' => $status,
            ];
        }

        return $availability;
    }

    /**
     * Check if court is under maintenance
     */
    private function isMaintenance(int $courtId, Carbon $date, int $timeSlotId)
    {
        $timeSlot = TimeSlot::find($timeSlotId);
        
        $maintenanceQuery = MaintenanceSchedule::where('court_id', $courtId)
            ->where('status', '!=', 'CANCELLED');

        // Existing installations created before the date-range migration only
        // have maintenance_date. Keep booking available on those databases.
        if (Schema::hasColumns('maintenance_schedules', ['start_date', 'end_date'])) {
            $maintenanceQuery->whereDate('start_date', '<=', $date->toDateString())
                ->whereDate('end_date', '>=', $date->toDateString());
        } else {
            $maintenanceQuery->whereDate('maintenance_date', $date->toDateString());
        }

        $maintenance = $maintenanceQuery
            ->whereRaw("? BETWEEN start_time AND end_time", [$timeSlot->start_time])
            ->exists();

        return $maintenance;
    }

    /**
     * Check if time slot is already booked
     */
    private function isBooked(int $courtId, Carbon $date, int $timeSlotId)
    {
        return BookingDetail::where('court_id', $courtId)
            ->whereDate('booking_date', $date->toDateString())
            ->where('time_slot_id', $timeSlotId)
            ->whereHas('booking', function ($query) {
                $query->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED']);
            })
            ->exists();
    }

    /**
     * Check if time slot is on hold (and hold not expired)
     */
    private function isOnHold(int $courtId, Carbon $date, int $timeSlotId)
    {
        return BookingDetail::where('court_id', $courtId)
            ->whereDate('booking_date', $date->toDateString())
            ->where('time_slot_id', $timeSlotId)
            ->where('status', 'PENDING')
            ->whereHas('booking', function ($query) {
                $query->where('status', 'PENDING_PAYMENT')
                    ->where('hold_expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Batch check availability for multiple slots
     */
    public function batchCheckAvailability(int $courtId, $bookingDetails)
    {
        $results = [];
        
        foreach ($bookingDetails as $detail) {
            $date = Carbon::parse($detail['booking_date']);
            $timeSlotId = $detail['time_slot_id'];
            
            $status = $this->checkAvailability($courtId, $date, $timeSlotId);
            
            if ($status !== self::STATUS_AVAILABLE) {
                $results[] = [
                    'booking_date' => $detail['booking_date'],
                    'time_slot_id' => $timeSlotId,
                    'status' => $status,
                    'message' => $this->getStatusMessage($status)
                ];
            }
        }

        return $results;
    }

    /**
     * Get human-readable status message
     */
    private function getStatusMessage($status)
    {
        return match($status) {
            self::STATUS_BOOKED => 'Khung giờ này đã được đặt',
            self::STATUS_HOLD => 'Khung giờ này đang được giữ',
            self::STATUS_MAINTENANCE => 'Sân đang bảo trì trong khung giờ này',
            default => 'Khung giờ không khả dụng'
        };
    }
}

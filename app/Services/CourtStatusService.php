<?php

namespace App\Services;

use App\Models\Court;
use Illuminate\Support\Facades\DB;

class CourtStatusService
{
    public function update(Court $court, string $status, ?string $reason): Court
    {
        return DB::transaction(function () use ($court, $status, $reason) {
            $lockedCourt = Court::query()->lockForUpdate()->findOrFail($court->id);

            if (in_array($status, ['LOCKED', 'MAINTENANCE'], true) && blank($reason)) {
                throw new \DomainException('Phải nhập lý do khi khóa hoặc chuyển sân sang bảo trì.');
            }

            if (in_array($status, ['LOCKED', 'MAINTENANCE'], true) && $this->hasAffectedBooking($lockedCourt)) {
                throw new \DomainException('Sân đang có booking chưa xử lý nên không thể khóa hoặc chuyển sang bảo trì.');
            }

            $lockedCourt->update([
                'operational_status' => $status,
                'status_reason' => $status === 'AVAILABLE' ? null : $reason,
                'status_updated_at' => now(),
            ]);

            return $lockedCourt->fresh();
        });
    }

    private function hasAffectedBooking(Court $court): bool
    {
        return $court->bookingDetails()
            ->whereDate('booking_date', '>=', today())
            ->whereHas('booking', fn ($query) => $query->whereIn('status', ['CONFIRMED', 'CHECKED_IN']))
            ->exists();
    }
}

<?php

namespace App\Services;

use App\Models\{Booking, BookingAuditLog, BookingDetail, Court, TimeSlot, User};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingExtensionService
{
    public function __construct(private BookingService $bookings, private CourtAvailabilityService $availability) {}

    public function source(Booking $booking, int $detailId): BookingDetail
    {
        if ($booking->status !== 'CHECKED_IN') throw new \DomainException('Chỉ gia hạn booking đang chơi.');
        $detail = $booking->bookingDetails()->where('status', 'CHECKED_IN')->with('timeSlot', 'court')->findOrFail($detailId);
        $later = $booking->bookingDetails()->where('court_id', $detail->court_id)->whereDate('booking_date', $detail->booking_date)
            ->where('status', '!=', 'CANCELLED')->whereHas('timeSlot', fn ($q) => $q->where('end_time', '>', $detail->timeSlot->end_time))->exists();
        if ($later) throw new \DomainException('Chọn khung giờ cuối của sân trong booking để gia hạn.');
        $start = $this->start($detail);
        if (!$detail->booking_date->isToday() || now()->startOfMinute()->gt($start)) throw new \DomainException('Đã qua thời điểm bắt đầu giờ nối tiếp. Vui lòng tìm lịch đặt mới.');
        if (Booking::where('extension_of_id', $booking->id)->where(fn ($q) => $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'COMPLETED'])
            ->orWhere(fn ($pending) => $pending->where('status', 'PENDING_PAYMENT')->where('hold_expires_at', '>', now())))->exists()) {
            throw new \DomainException('Booking đã có lượt gia hạn. Mở lượt gia hạn đó để thanh toán hoặc gia hạn tiếp.');
        }
        return $detail;
    }

    public function start(BookingDetail $detail): Carbon
    {
        return Carbon::parse($detail->booking_date->toDateString().' '.$detail->timeSlot->end_time);
    }

    public function nextSlots(BookingDetail $detail, int $count): Collection
    {
        $end = $detail->timeSlot->end_time;
        $slots = collect();
        for ($i = 0; $i < $count; $i++) {
            $slot = TimeSlot::where('status', 'ACTIVE')->where('start_time', $end)->orderBy('end_time')->first();
            if (!$slot || $slot->end_time <= $slot->start_time) throw new \DomainException('Không có đủ khung giờ nối tiếp trong bảng giờ hiện tại.');
            $slots->push($slot); $end = $slot->end_time;
        }
        return $slots;
    }

    public function options(BookingDetail $detail, Collection $slots): Collection
    {
        return Court::where('status', 'ACTIVE')->orderBy('name')->get()->map(function ($court) use ($detail, $slots) {
            $price = 0.0; $reason = null;
            foreach ($slots as $slot) {
                $status = $this->availability->checkAvailability($court->id, $detail->booking_date, $slot->id);
                if ($status !== CourtAvailabilityService::STATUS_AVAILABLE) {
                    $reason = in_array($status, ['BOOKED', 'HOLD'], true)
                        ? $court->name.' đã có khách đặt hoặc đang giữ chỗ từ '.substr($slot->start_time, 0, 5).'–'.substr($slot->end_time, 0, 5).'. Không thể gia hạn trên sân này.'
                        : 'Sân tạm ngừng hoặc đang bảo trì.';
                    break;
                }
                if (($court->opening_time && $slot->start_time < $court->opening_time) || ($court->closing_time && $slot->end_time > $court->closing_time)) { $reason = 'Ngoài giờ mở cửa của sân.'; break; }
                $slotPrice = $this->bookings->getCurrentPrice($court->id, $slot->id, $detail->booking_date);
                if ($slotPrice === null || $slotPrice <= 0) { $reason = 'Chưa có giá cho khung giờ này.'; break; }
                $price += (float) $slotPrice;
            }
            return ['court' => $court, 'price' => round($price, 2), 'available' => $reason === null, 'reason' => $reason];
        })->sortBy(fn ($option) => $option['court']->id === $detail->court_id ? 0 : 1)->values();
    }

    public function create(Booking $booking, int $detailId, array $slotIds, ?int $courtId, User $actor, ?float $quotedPrice = null): Booking
    {
        $rootId = $this->session($booking)->first()->id;
        return DB::transaction(function () use ($booking, $detailId, $slotIds, $courtId, $actor, $quotedPrice, $rootId) {
            // Lock before ordinary reads establish the MySQL repeatable-read snapshot.
            Booking::lockForUpdate()->findOrFail($rootId);
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            $detail = $this->source($locked, $detailId);
            $slots = TimeSlot::whereIn('id', $slotIds)->where('status', 'ACTIVE')->orderBy('start_time')->get();
            if ($slots->count() !== count($slotIds) || $slots->isEmpty() || !TimeSlot::areConsecutive($slots)
                || substr($slots->first()->start_time, 0, 5) !== substr($detail->timeSlot->end_time, 0, 5)) {
                throw new \DomainException('Chỉ được gia hạn bằng các khung giờ có sẵn, liền nhau và nối tiếp lượt hiện tại.');
            }
            $court = Court::lockForUpdate()->findOrFail($courtId ?? $detail->court_id);
            $option = $this->options($detail, $slots)->first(fn ($option) => $option['court']->id === $court->id);
            if (!$option || !$option['available']) throw new \DomainException($option['reason'] ?? 'Sân không còn hoạt động.');
            if ($quotedPrice !== null && round($quotedPrice, 2) !== $option['price']) throw new \DomainException('Giá sân đã thay đổi. Vui lòng xem lại phương án trước khi xác nhận.');
            $details = $slots->map(fn ($slot) => ['court_id' => $court->id, 'booking_date' => $detail->booking_date->toDateString(), 'time_slot_id' => $slot->id])->all();
            try { $next = $this->bookings->createBooking($locked->user_id, $details, extensionStart: $this->start($detail)); }
            catch (\Exception $e) {
                $errors = json_decode($e->getMessage(), true);
                if (is_array($errors)) throw new \DomainException(collect($errors)->pluck('message')->implode('; '));
                throw $e;
            }
            if (round((float) $next->total_amount, 2) !== $option['price']) throw new \DomainException('Giá đã thay đổi trong khi giữ chỗ. Vui lòng xem lại phương án.');
            $next->forceFill(['extension_of_id' => $locked->id, 'extended_from_id' => $locked->id])->save();
            BookingAuditLog::create(['booking_id' => $next->id, 'actor_id' => $actor->id, 'action' => 'EXTENSION_CREATED', 'reason' => 'Gia hạn thời gian sân', 'new_values' => ['extension_of_id' => $locked->id, 'court_id' => $court->id]]);
            return $next;
        }, 3);
    }

    public function session(Booking $booking): Collection
    {
        $root = $booking; $seen = [];
        while ($root->extension_of_id && !isset($seen[$root->id])) {
            $seen[$root->id] = true;
            $parent = Booking::find($root->extension_of_id);
            if (!$parent) break;
            $root = $parent;
        }
        $ids = collect([$root->id]); $frontier = $ids;
        while ($frontier->isNotEmpty()) {
            $frontier = Booking::whereIn('extension_of_id', $frontier)->pluck('id')->diff($ids);
            $ids = $ids->merge($frontier);
        }
        return Booking::whereIn('id', $ids)->with('bookingDetails.court', 'bookingDetails.timeSlot', 'payment')->orderBy('id')->get();
    }

    public function checkoutSession(Booking $booking, User $actor): void
    {
        $rootId = $this->session($booking)->first()->id;
        DB::transaction(function () use ($booking, $actor, $rootId) {
            Booking::lockForUpdate()->findOrFail($rootId);
            $session = $this->session($booking);
            $members = Booking::whereIn('id', $session->modelKeys())->orderBy('id')->lockForUpdate()->get();
            if ($members->contains(fn ($member) => $member->status === 'CONFIRMED' || ($member->status === 'PENDING_PAYMENT' && $member->hold_expires_at?->isFuture()))) {
                throw new \DomainException('Phiên còn lượt gia hạn chưa xử lý. Thanh toán/check-in hoặc hủy lượt chưa thanh toán trước khi kết thúc phiên.');
            }
            $playing = $members->where('status', 'CHECKED_IN');
            if ($playing->isEmpty()) throw new \DomainException('Phiên không còn lượt đang chơi.');
            foreach ($playing as $member) {
                if (!in_array($member->payment_status, ['PAID', 'PARTIALLY_REFUNDED'], true) || !in_array($member->payment?->status, ['PAID', 'PARTIALLY_REFUNDED'], true)) {
                    throw new \DomainException('Phiên còn khoản thanh toán chưa xử lý.');
                }
                if (\App\Models\EquipmentLoan::where('booking_id', $member->id)->whereNull('returned_at')->exists()) {
                    throw new \DomainException('Vui lòng xử lý thiết bị mượn trước khi kết thúc phiên.');
                }
                $this->bookings->checkoutBooking($member, $actor->id);
                $this->refreshCourtOccupancy($member);
                app(CustomerNotificationService::class)->statusChanged($member, 'COMPLETED');
                BookingAuditLog::create(['booking_id' => $member->id, 'actor_id' => $actor->id, 'action' => 'COMPLETED', 'reason' => 'Kết thúc phiên chơi liên tục']);
            }
        }, 3);
    }

    public function refreshCourtOccupancy(Booking $booking): void
    {
        $courtIds = $this->session($booking)->flatMap(fn ($member) => $member->bookingDetails->pluck('court_id'))->unique();
        foreach ($courtIds as $courtId) {
            $occupied = BookingDetail::where('court_id', $courtId)->where('status', 'CHECKED_IN')
                ->whereDate('booking_date', today())
                ->whereHas('booking', fn ($q) => $q->where('status', 'CHECKED_IN'))
                ->whereHas('timeSlot', fn ($q) => $q->where('start_time', '<=', now()->format('H:i:s'))->where('end_time', '>', now()->format('H:i:s')))->exists();
            Court::whereKey($courtId)->update(['availability_status' => $occupied ? 'OCCUPIED' : 'AVAILABLE']);
        }
    }
}

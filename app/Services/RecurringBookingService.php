<?php

namespace App\Services;

use App\Models\Court;
use App\Models\FixedBooking;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecurringBookingService
{
    public function __construct(private BookingService $bookings, private VoucherService $vouchers) {}

    public function preview(array $definition): array
    {
        $preview = $this->bookings->previewRecurringBooking($definition);
        $courts = Court::where('status', 'ACTIVE')->orderBy('name')->get();
        $slots = TimeSlot::where('status', 'ACTIVE')->get();
        foreach ($preview['conflicts'] as &$conflict) {
            $conflict['alternatives'] = [];
            $original = $slots->firstWhere('id', $conflict['time_slot_id']);
            if (! $original) {
                continue;
            }
            foreach ($courts as $court) {
                if ($court->id === $conflict['court_id']) {
                    continue;
                }
                $candidate = $this->availableOccurrence($court->id, $original->id, $conflict['booking_date']);
                if ($candidate) {
                    $candidate['kind'] = 'court';
                    $conflict['alternatives'][$court->id.'-'.$original->id] = $candidate;
                }
            }
            $duration = Carbon::parse($original->start_time)->diffInMinutes(Carbon::parse($original->end_time));
            $nearest = $slots->filter(fn ($slot) => $slot->id !== $original->id
                && Carbon::parse($slot->start_time)->diffInMinutes(Carbon::parse($slot->end_time)) === $duration)
                ->sortBy(fn ($slot) => abs(Carbon::parse($slot->start_time)->diffInMinutes(Carbon::parse($original->start_time), false)));
            foreach ($nearest as $slot) {
                $candidate = $this->availableOccurrence($conflict['court_id'], $slot->id, $conflict['booking_date']);
                if ($candidate) {
                    $candidate['kind'] = 'time';
                    $conflict['alternatives'][$conflict['court_id'].'-'.$slot->id] = $candidate;
                }
            }
        }

        return $preview;
    }

    private function availableOccurrence(int $court, int $slot, string $date): ?array
    {
        $preview = $this->bookings->previewRecurringBooking([
            'court_id' => $court, 'time_slot_id' => $slot,
            'booking_type' => 'weekly', 'start_date' => $date, 'end_date' => $date,
            'days_of_week' => [Carbon::parse($date)->dayOfWeek],
        ]);

        return $preview['schedules'][0] ?? null;
    }

    public function review(array $draft, array $choices): array
    {
        $preview = $draft['preview'];
        $selected = [];
        $occurrences = [];
        foreach ($preview['schedules'] as $item) {
            $selected[$item['key']] = $item;
            $occurrences[$item['key']] = ['original' => $this->snapshot($item), 'choice' => 'original'];
        }
        foreach ($preview['conflicts'] as $item) {
            $key = $item['key'];
            $choice = $choices[$key] ?? null;
            if ($choice !== 'skip' && (! is_string($choice) || ! isset($item['alternatives'][$choice]))) {
                throw ValidationException::withMessages(['choices' => 'Vui lòng chọn phương án hoặc bỏ qua từng buổi bị trùng.']);
            }
            $occurrences[$key] = ['original' => $this->snapshot($item), 'choice' => $choice === 'skip' ? 'skip' : $item['alternatives'][$choice]['kind']];
            if ($choice !== 'skip') {
                $selected[$key] = $item['alternatives'][$choice];
            }
        }
        if (! $selected) {
            throw ValidationException::withMessages(['choices' => 'Cần chọn ít nhất một buổi có thể đặt.']);
        }
        ksort($selected);
        ksort($occurrences);
        $checked = $this->recheck($selected);
        $subtotal = array_sum(array_column($checked, 'price'));
        $discount = $this->discount($draft['definition']['voucher_code'] ?? null, $subtotal);

        return ['selected' => $checked, 'occurrences' => $occurrences, 'booking_count' => count($this->groupSessions($checked)), 'subtotal' => $subtotal,
            'discount' => $discount, 'total' => $subtotal - $discount];
    }

    private function snapshot(array $item): array
    {
        return array_intersect_key($item, array_flip(['court_id', 'court_name', 'time_slot_id', 'booking_date', 'time_slot', 'price']));
    }

    private function groupSessions(array $selected): array
    {
        $sorted = collect($selected)->sortBy(fn ($item) => $item['booking_date'].'-'.sprintf('%010d', $item['court_id']).'-'.$item['start_time']);
        $groups = [];
        $previous = null;
        foreach ($sorted as $key => $item) {
            if (! $previous || $previous['booking_date'] !== $item['booking_date']
                || $previous['court_id'] !== $item['court_id'] || $previous['end_time'] !== $item['start_time']) {
                $groups[] = [];
            }
            $groups[array_key_last($groups)][$key] = $item;
            $previous = $item;
        }

        return $groups;
    }

    private function discount(?string $code, float $subtotal): float
    {
        if (! $code) {
            return 0;
        }
        $result = $this->vouchers->validateAndApply($code, $subtotal);
        if (! $result['valid']) {
            throw ValidationException::withMessages(['voucher_code' => $result['message']]);
        }

        return min($subtotal, (float) $result['discount']);
    }

    private function recheck(array $selected, bool $checkPrices = false): array
    {
        $checked = [];
        foreach ($selected as $key => $item) {
            $current = $this->availableOccurrence($item['court_id'], $item['time_slot_id'], $item['booking_date']);
            if (! $current) {
                throw ValidationException::withMessages(['choices' => 'Buổi '.$item['booking_date'].' · '.$item['court_name'].' · '.$item['time_slot'].' không còn trống. Vui lòng kiểm tra lịch và chọn lại phương án.']);
            }
            if ($checkPrices && round((float) $current['price'] * 100) !== round((float) $item['price'] * 100)) {
                throw ValidationException::withMessages(['choices' => 'Giá sân đã thay đổi. Vui lòng kiểm tra lại số tiền trước khi xác nhận.']);
            }
            foreach ($checked as $other) {
                if ($other['court_id'] === $current['court_id'] && $other['booking_date'] === $current['booking_date']
                    && $other['start_time'] < $current['end_time'] && $other['end_time'] > $current['start_time']) {
                    throw ValidationException::withMessages(['choices' => 'Các buổi bạn chọn đang trùng nhau. Vui lòng chọn sân/giờ khác hoặc bỏ qua một buổi.']);
                }
            }
            $checked[$key] = $current;
        }

        return $checked;
    }

    public function create(int $userId, array $draft): FixedBooking
    {
        return DB::transaction(function () use ($userId, $draft) {
            // Serialize repeated confirmations, and use the same court locks as ordinary booking.
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $quote = $draft['quote'];
            // Acquire slot-owner locks before the first consistent read (MySQL repeatable read).
            Court::whereIn('id', array_column($quote['selected'], 'court_id'))->orderBy('id')->lockForUpdate()->get();
            TimeSlot::whereIn('id', array_column($quote['selected'], 'time_slot_id'))->orderBy('id')->lockForUpdate()->get();
            $existing = FixedBooking::where('confirmation_key', $draft['confirmation_key'])->where('user_id', $userId)->first();
            if ($existing) {
                return $existing;
            }
            $selected = $this->recheck($quote['selected'], true);
            $voucherCode = $draft['definition']['voucher_code'] ?? null;
            $voucher = $voucherCode ? Voucher::where('code', $voucherCode)->lockForUpdate()->first() : null;
            $discount = $this->discount($voucherCode, (float) $quote['subtotal']);
            if (round($discount * 100) !== round($quote['discount'] * 100)) {
                throw ValidationException::withMessages(['voucher_code' => 'Ưu đãi đã thay đổi. Vui lòng kiểm tra lại số tiền.']);
            }
            $group = FixedBooking::create(['code' => 'FIX-'.Str::upper(Str::random(10)), 'user_id' => $userId,
                'confirmation_key' => $draft['confirmation_key'], 'definition' => $draft['definition'], 'occurrences' => [],
                'status' => 'AWAITING_PAYMENT', 'total_price' => $quote['total'], 'expires_at' => now()->addMinutes(15)]);
            $occurrences = $quote['occurrences'];
            $remainingDiscount = (int) round($discount * 100);
            $remainingSubtotal = (int) round($quote['subtotal'] * 100);
            foreach ($this->groupSessions($selected) as $session) {
                $key = array_key_first($session);
                $item = $session[$key];
                $price = (int) round(array_sum(array_column($session, 'price')) * 100);
                $share = $remainingSubtotal > 0 ? (int) round($remainingDiscount * $price / $remainingSubtotal) : 0;
                $remainingDiscount -= $share;
                $remainingSubtotal -= $price;
                $booking = $this->bookings->createBooking($userId, array_values($session), null, [
                    'booking_type' => $draft['definition']['booking_type'] ?? 'weekly',
                    'start_date' => $item['booking_date'], 'end_date' => $item['booking_date'],
                    'fixed_booking_id' => $group->id, 'recurrence_key' => $key,
                ], $share / 100);
                $booking->update(['hold_expires_at' => $group->expires_at]);
                foreach ($session as $occurrenceKey => $occurrence) {
                    $occurrences[$occurrenceKey]['booking_id'] = $booking->id;
                    $occurrences[$occurrenceKey]['selected'] = $this->snapshot($occurrence);
                }
            }
            if ($voucher) {
                $this->vouchers->incrementUsage($voucher->id);
            }
            $group->update(['occurrences' => $occurrences]);
            $group->payment()->create(['amount' => $group->total_price, 'status' => 'PENDING', 'payment_method' => 'vnpay']);
            app(CustomerNotificationService::class)->fixedBooking($group, 'CREATED');

            return $group;
        }, 3);
    }
}

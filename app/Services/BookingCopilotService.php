<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\Holiday;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BookingCopilotService
{
    private const SESSION_KEY = 'chatbot.booking_copilot';

    public function __construct(
        private readonly CourtAvailabilityService $availability,
        private readonly VoucherService $vouchers,
        private readonly BookingService $bookings,
    ) {}

    public function shouldHandle(string $message): bool
    {
        $text = $this->normalize($message);

        return Str::contains($text, [
            'nhu tuan truoc', 'giong tuan truoc', 'nhu lan truoc', 'giong lan truoc',
            'dat lai san cu', 'dat nhu cu', 'booking copilot',
        ]);
    }

    public function prepare(string $message, User $user): array
    {
        $previous = Booking::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['CANCELLED', 'EXPIRED'])
            ->whereHas('bookingDetails')
            ->with(['bookingDetails' => fn ($query) => $query->with(['court', 'timeSlot'])->orderBy('booking_date')->orderBy('time_slot_id')])
            ->latest('created_at')
            ->first();

        $detail = $previous?->bookingDetails->first();
        if (! $detail || ! $detail->court || ! $detail->timeSlot) {
            return $this->result(
                'Mình chưa tìm thấy lịch đặt sân trước đây của tài khoản này. Bạn cho mình biết ngày và khung giờ mong muốn nhé.',
                'COPILOT_NEEDS_HISTORY',
                [['label' => 'Tìm sân trống ngày mai']],
            );
        }

        $date = $this->resolveDate($message, $detail->booking_date);
        $candidates = $this->candidates((int) $detail->court_id, (int) $detail->time_slot_id, $date);
        if ($candidates === []) {
            return $this->result(
                'Khung giờ giống lần trước và các phương án gần nhất đều đã hết vào ngày '.$date->format('d/m/Y').'. Bạn muốn thử ngày khác không?',
                'COPILOT_NO_AVAILABILITY',
                ['Tìm sân trống ngày khác'],
            );
        }

        $choices = [];
        foreach ($candidates as $candidate) {
            $token = (string) Str::uuid();
            $choices[$token] = $this->makePreview($candidate, $date, $user);
        }
        session([self::SESSION_KEY => ['user_id' => $user->id, 'expires_at' => now()->addMinutes(10)->timestamp, 'choices' => $choices]]);

        $firstToken = array_key_first($choices);
        $first = $choices[$firstToken];
        $isExact = $first['court_id'] === (int) $detail->court_id && $first['time_slot_id'] === (int) $detail->time_slot_id;
        if (! $isExact) {
            return $this->alternatives($date, $choices);
        }

        return $this->previewResult($firstToken, $first, 'Mình đã dựa trên booking '.$previous->booking_code.' của chính tài khoản bạn.');
    }

    public function showPreview(string $token, User $user): array
    {
        $preview = $this->choice($token, $user);
        if (! $preview) {
            return $this->expired();
        }

        return $this->previewResult($token, $preview);
    }

    public function showAlternatives(User $user): array
    {
        $state = session(self::SESSION_KEY);
        if (! is_array($state) || ($state['user_id'] ?? null) !== $user->id || ($state['expires_at'] ?? 0) < now()->timestamp) {
            return $this->expired();
        }
        $choices = $state['choices'] ?? [];
        if (count($choices) < 2) {
            return $this->result('Không còn phương án thay thế trong preview này. Bạn hãy cho mình ngày hoặc khung giờ khác.', 'COPILOT_NO_ALTERNATIVES', ['Tìm sân trống ngày khác']);
        }

        return $this->alternatives(Carbon::parse(reset($choices)['date']), array_slice($choices, 1, null, true));
    }

    public function confirm(string $token, User $user): array
    {
        $preview = $this->choice($token, $user);
        if (! $preview) {
            return $this->expired();
        }

        $livePrice = $this->currentPrice($preview['court_id'], $preview['time_slot_id'], Carbon::parse($preview['date']));
        $liveVoucher = $preview['voucher_code'] ? $this->vouchers->validateAndApply($preview['voucher_code'], $livePrice) : null;
        if ($livePrice !== (float) $preview['subtotal']
            || ($preview['voucher_code'] && (! ($liveVoucher['valid'] ?? false) || (float) $liveVoucher['discount'] !== (float) $preview['discount']))) {
            session()->forget(self::SESSION_KEY);

            return $this->result('Giá hoặc voucher đã thay đổi. Mình chưa tạo booking; vui lòng yêu cầu lại để xem preview mới.', 'COPILOT_PREVIEW_CHANGED', ['Đặt như tuần trước vào tối mai'], false);
        }

        try {
            $booking = $this->bookings->createBooking($user->id, [[
                'court_id' => $preview['court_id'],
                'booking_date' => $preview['date'],
                'time_slot_id' => $preview['time_slot_id'],
            ]], $preview['voucher_code']);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->result(
                'Rất tiếc, phương án này vừa không còn khả dụng hoặc giá/voucher đã thay đổi. Mình chưa tạo booking; bạn hãy yêu cầu mình tìm lại để nhận preview mới.',
                'COPILOT_CONFIRMATION_FAILED',
                ['Đặt như tuần trước vào tối mai'],
                false,
            );
        }

        session()->forget(self::SESSION_KEY);
        $url = route('bookings.show', $booking);

        return $this->result(
            'Đã tạo booking '.$booking->booking_code.' và giữ chỗ tạm thời. Tổng thanh toán '.number_format((float) $booking->total_amount, 0, ',', '.').'đ.',
            'COPILOT_BOOKING_CREATED',
            [],
        ) + [
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'booking_total' => (float) $booking->total_amount,
            'booking_url' => $url,
            'redirect_url' => $url,
        ];
    }

    private function candidates(int $courtId, int $timeSlotId, Carbon $date): array
    {
        $exact = $this->isAvailable($courtId, $timeSlotId, $date)
            ? collect([['court_id' => $courtId, 'time_slot_id' => $timeSlotId]])
            : collect();

        $preferred = TimeSlot::find($timeSlotId);
        $preferredMinutes = $preferred ? $this->minutes($preferred->start_time) : 0;
        $sameCourt = TimeSlot::query()->where('status', 'ACTIVE')->get()
            ->sortBy(fn (TimeSlot $slot) => abs($this->minutes($slot->start_time) - $preferredMinutes))
            ->filter(fn (TimeSlot $slot) => $this->isAvailable($courtId, $slot->id, $date))
            ->take(2)->map(fn (TimeSlot $slot) => ['court_id' => $courtId, 'time_slot_id' => $slot->id]);
        $otherCourts = Court::query()->whereKeyNot($courtId)->where('status', 'ACTIVE')->where('operational_status', 'AVAILABLE')->get()
            ->filter(fn (Court $court) => $this->isAvailable($court->id, $timeSlotId, $date))
            ->take(2)->map(fn (Court $court) => ['court_id' => $court->id, 'time_slot_id' => $timeSlotId]);

        return $exact->concat($sameCourt)->concat($otherCourts)->unique(fn ($item) => $item['court_id'].'-'.$item['time_slot_id'])->take(4)->values()->all();
    }

    private function makePreview(array $candidate, Carbon $date, User $user): array
    {
        $court = Court::findOrFail($candidate['court_id']);
        $slot = TimeSlot::findOrFail($candidate['time_slot_id']);
        $subtotal = $this->currentPrice($court->id, $slot->id, $date);
        $voucher = $this->vouchers->bestForAmount($subtotal);
        $discount = (float) ($voucher['discount'] ?? 0);

        return [
            'user_id' => $user->id,
            'court_id' => $court->id,
            'court_name' => $court->name,
            'time_slot_id' => $slot->id,
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time' => substr($slot->end_time, 0, 5),
            'date' => $date->toDateString(),
            'subtotal' => $subtotal,
            'voucher_code' => $voucher['code'] ?? null,
            'discount' => $discount,
            'total' => max(0, $subtotal - $discount),
        ];
    }

    private function previewResult(string $token, array $preview, string $prefix = ''): array
    {
        $voucher = $preview['voucher_code']
            ? ' Voucher '.$preview['voucher_code'].' giảm '.number_format($preview['discount'], 0, ',', '.').'đ đã được chọn tự động.'
            : ' Hiện chưa có voucher hợp lệ cho phương án này.';
        $answer = trim($prefix.' Preview: '.$preview['court_name'].', ngày '.Carbon::parse($preview['date'])->format('d/m/Y')
            .', '.$preview['start_time'].'–'.$preview['end_time'].'. Giá hiện tại '.number_format($preview['subtotal'], 0, ',', '.').'đ.'
            .$voucher.' Tổng cộng '.number_format($preview['total'], 0, ',', '.').'đ. Mình chỉ tạo booking khi bạn bấm xác nhận.');

        $publicPreview = $preview + [
            'available' => true,
            'voucher' => $preview['voucher_code'] ? [
                'code' => $preview['voucher_code'],
                'discount' => $preview['discount'],
            ] : null,
        ];

        return $this->result($answer, 'BOOKING_PREVIEW', [
            ['id' => $token, 'action' => 'confirm_copilot_booking', 'label' => 'Xác nhận đặt sân'],
            ['action' => 'copilot_other_choices', 'label' => 'Chọn phương án khác'],
        ]) + ['preview' => $publicPreview, 'booking_preview' => $publicPreview];
    }

    private function alternatives(Carbon $date, array $choices): array
    {
        $buttons = collect($choices)->map(fn (array $choice, string $token) => [
            'id' => $token,
            'action' => 'preview_copilot_booking',
            'label' => $choice['court_name'].' · '.$choice['start_time'].'–'.$choice['end_time'].' · '.number_format($choice['total'], 0, ',', '.').'đ',
        ])->values()->all();

        return $this->result(
            'Sân/giờ giống lần trước đã hết vào ngày '.$date->format('d/m/Y').'. Mình tìm được các phương án gần nhất; giá và voucher đều đã được tính theo dữ liệu hiện tại:',
            'COPILOT_ALTERNATIVES',
            $buttons,
        );
    }

    private function choice(string $token, User $user): ?array
    {
        $state = session(self::SESSION_KEY);
        if (! is_array($state) || ($state['user_id'] ?? null) !== $user->id || ($state['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        $choice = $state['choices'][$token] ?? null;

        return is_array($choice) && ($choice['user_id'] ?? null) === $user->id ? $choice : null;
    }

    private function expired(): array
    {
        return $this->result('Preview đã hết hạn. Mình chưa tạo booking nào; bạn hãy yêu cầu tìm lại để cập nhật lịch trống, giá và voucher.', 'COPILOT_PREVIEW_EXPIRED', ['Đặt như tuần trước vào tối mai'], false);
    }

    private function isAvailable(int $courtId, int $slotId, Carbon $date): bool
    {
        if ($this->currentPrice($courtId, $slotId, $date) <= 0) {
            return false;
        }

        return $this->availability->checkAvailability($courtId, $date, $slotId) === CourtAvailabilityService::STATUS_AVAILABLE;
    }

    private function currentPrice(int $courtId, int $slotId, Carbon $date): float
    {
        $dayType = Holiday::whereDate('holiday_date', $date)->exists() ? 'HOLIDAY' : ($date->isWeekend() ? 'WEEKEND' : 'WEEKDAY');
        $query = fn (string $type) => CourtPrice::query()->where('court_id', $courtId)->where('time_slot_id', $slotId)
            ->where('day_type', $type)->where('status', 'ACTIVE')->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))->latest('effective_from')->value('price');

        return (float) ($query($dayType) ?? ($dayType === 'WEEKDAY' ? 0 : $query('WEEKDAY')) ?? 0);
    }

    private function resolveDate(string $message, Carbon $previousDate): Carbon
    {
        $text = $this->normalize($message);
        if (Str::contains($text, 'ngay kia')) {
            return today()->addDays(2);
        }
        if (Str::contains($text, 'ngay mai') || Str::contains($text, 'toi mai')) {
            return today()->addDay();
        }
        if (Str::contains($text, ['hom nay', 'toi nay'])) {
            return today();
        }
        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{4}))?\b/', $text, $matches)) {
            return Carbon::createFromDate((int) ($matches[3] ?? now()->year), (int) $matches[2], (int) $matches[1])->startOfDay();
        }

        $target = today()->next($previousDate->dayOfWeek);

        return $target->isToday() ? $target->addWeek() : $target;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text))) ?? '');
    }

    private function result(string $answer, string $intent, array $buttons = [], bool $understood = true): array
    {
        return [
            'understood' => $understood,
            'answer' => $answer,
            'intent' => $intent,
            'suggestions' => $buttons,
            'buttons' => $buttons,
            'engine' => 'booking-copilot-v1',
            'pipeline_stage' => 'booking_copilot',
            'generation_skipped' => true,
        ];
    }
}

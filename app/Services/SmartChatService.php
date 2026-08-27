<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SmartChatService
{
    public function __construct(
        private readonly AvailableCourtService $availableCourts,
        private readonly RuleBasedChatService $rules,
        private readonly BookingService $bookings,
    ) {}

    public function reply(string $message): array
    {
        $text = $this->normalize($message);

        // Backward compatibility for old/OpenAI-rendered slot buttons that send
        // their label as a normal chat message instead of action + choice_id.
        if ($choiceId = $this->resolveSlotChoiceFromMessage($text)) {
            return $this->selectSlot($choiceId);
        }

        $date = $this->resolveDate($text);
        $timeFilter = $this->resolveTimeFilter($text);
        $asksAvailableCourt = Str::contains($text, [
            'san trong', 'gio trong', 'khung gio trong', 'con san', 'con trong', 'danh sach san',
        ]) || (Str::contains($text, 'dat san') && ($date || $timeFilter));
        $pendingIntent = session('chatbot.pending_intent');

        if ($asksAvailableCourt) {
            session([
                'chatbot.pending_intent' => 'find_available_courts',
                'chatbot.time_filter' => $timeFilter ?: session('chatbot.time_filter'),
            ]);
            if (! $date) {
                return ['reply' => 'Bạn muốn tìm sân trống vào ngày nào?', 'intent' => 'ask_booking_date', 'awaiting' => 'date'];
            }
        }

        if ($pendingIntent === 'find_available_courts' && $date) {
            $asksAvailableCourt = true;
        }

        if ($asksAvailableCourt && $date) {
            session()->forget('chatbot.pending_intent');
            $timeFilter = $timeFilter ?: session('chatbot.time_filter');
            session()->forget('chatbot.time_filter');

            return $this->availableCourts->findByDate(
                $date,
                $timeFilter['hour'] ?? null,
                $timeFilter['exact'] ?? false,
            );
        }

        $fallback = $this->rules->reply($message);

        return [
            'reply' => $fallback['answer'],
            'intent' => $fallback['intent'],
            'matched' => $fallback['matched'],
            'score' => $fallback['score'],
            'source' => $fallback['source'],
        ];
    }

    public function selectSlot(string $choiceId): array
    {
        $choices = session('chatbot.slot_choices', []);
        $slot = $choices[$choiceId] ?? null;
        if (! is_array($slot)) {
            return ['reply' => 'Khung giờ này đã hết hiệu lực. Bạn hãy tìm lại sân trống.', 'intent' => 'expired_slot', 'matched' => false];
        }

        session(['chatbot.selected_slot' => $slot]);

        $price = $slot['price'] ? number_format((float) $slot['price'], 0, ',', '.').'đ' : 'xem khi đặt sân';

        return [
            'reply' => 'Bạn đã chọn '.$slot['court_name'].', ngày '.Carbon::parse($slot['date'])->format('d/m/Y').', từ '.substr($slot['start_time'], 0, 5).' đến '.substr($slot['end_time'], 0, 5).'. Giá: '.$price.'. Bạn có muốn đặt sân này không?',
            'intent' => 'confirm_booking',
            'matched' => true,
            'selected_slot' => $slot,
            'choices' => [
                ['action' => 'confirm_booking', 'label' => 'Xác nhận đặt sân'],
                ['action' => 'find_other_slot', 'label' => 'Chọn giờ khác'],
            ],
        ];
    }

    public function confirmBooking(User $user): array
    {
        $slot = session('chatbot.selected_slot');
        if (! is_array($slot)) {
            return ['reply' => 'Lựa chọn đã hết hiệu lực. Bạn hãy tìm lại sân trống.', 'intent' => 'expired_slot', 'matched' => false];
        }

        $booking = $this->bookings->createBooking($user->id, [[
            'court_id' => $slot['court_id'],
            'booking_date' => $slot['date'],
            'time_slot_id' => $slot['time_slot_id'],
        ]]);
        session()->forget(['chatbot.selected_slot', 'chatbot.slot_choices']);
        $bookingUrl = route('bookings.show', $booking);

        return [
            'reply' => 'Đã tạo booking '.$booking->booking_code.' và giữ chỗ tạm thời. Bạn hãy mở chi tiết booking để kiểm tra và thanh toán.',
            'intent' => 'booking_confirmed',
            'matched' => true,
            'selected_slot' => $slot,
            'redirect_url' => $bookingUrl,
            'booking_url' => $bookingUrl,
            'booking_code' => $booking->booking_code,
            'booking_id' => $booking->id,
            'booking_total' => (float) $booking->total_amount,
        ];
    }

    public function findOtherSlot(): array
    {
        session()->forget('chatbot.selected_slot');
        $choices = session('chatbot.slot_choices', []);

        return [
            'reply' => $choices === [] ? 'Các lựa chọn đã hết hiệu lực. Bạn muốn tìm sân vào ngày nào?' : 'Bạn hãy chọn một khung giờ khác:',
            'intent' => $choices === [] ? 'ask_booking_date' : 'find_other_slot',
            'matched' => true,
            'buttons' => collect($choices)->map(fn ($slot, $id) => [
                'id' => $id,
                'action' => 'select_slot',
                'label' => $slot['court_name'].': '.substr($slot['start_time'], 0, 5).' - '.substr($slot['end_time'], 0, 5),
            ])->take(15)->values()->all(),
        ];
    }

    private function resolveDate(string $text): ?Carbon
    {
        if (Str::contains($text, 'ngay kia')) {
            return today()->addDays(2);
        }
        if (Str::contains($text, 'ngay mai')) {
            return today()->addDay();
        }
        if (Str::contains($text, ['hom nay', 'bay gio', 'toi nay'])) {
            return today();
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{4}))?\b/', $text, $matches)) {
            try {
                return Carbon::createFromDate((int) ($matches[3] ?? now()->year), (int) $matches[2], (int) $matches[1])->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolveTimeFilter(string $text): ?array
    {
        if (preg_match('/(?:luc\s*)?(\d{1,2})\s*(?:gio|h)\s*(toi|chieu)?/', $text, $matches)) {
            $hour = (int) $matches[1];
            if (($matches[2] ?? null) && $hour < 12) {
                $hour += 12;
            }
            if ($hour >= 0 && $hour <= 23) {
                return ['hour' => $hour, 'exact' => true];
            }
        }

        return Str::contains($text, 'toi nay') ? ['hour' => 18, 'exact' => false] : null;
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9\s\/\-]/', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    private function resolveSlotChoiceFromMessage(string $text): ?string
    {
        foreach (session('chatbot.slot_choices', []) as $choiceId => $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $court = $this->normalize((string) ($slot['court_name'] ?? ''));
            $start = $this->normalize(substr((string) ($slot['start_time'] ?? ''), 0, 5));
            $end = $this->normalize(substr((string) ($slot['end_time'] ?? ''), 0, 5));

            if ($court !== '' && $start !== '' && $end !== ''
                && Str::contains($text, $court)
                && Str::contains($text, $start)
                && Str::contains($text, $end)) {
                return (string) $choiceId;
            }
        }

        return null;
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Hướng dẫn khách chưa đăng nhập đi đến bước chọn sân và mở trang đặt sân.
 *
 * Khách vẫn phải đăng nhập để tạo booking và thanh toán. Quy trình này chỉ
 * lưu lựa chọn không nhạy cảm trong session, không tuyên bố booking đã tạo.
 */
class PublicBookingGuideService
{
    private const SESSION_KEY = 'chatbot.public_booking_guide';

    public function __construct(private readonly AvailableCourtService $availableCourts) {}

    public function shouldHandle(string $message): bool
    {
        $text = $this->normalize($message);
        $state = session(self::SESSION_KEY, []);

        $stage = is_array($state) ? ($state['stage'] ?? null) : null;

        return in_array($stage, ['date', 'time', 'preferences', 'availability', 'slot_selection'], true)
            || Str::contains($text, ['dat san', 'dat lich', 'book san', 'booking san', 'huong dan dat']);
    }

    public function handle(string $message): array
    {
        $state = $this->state();
        $previousStage = $state['stage'] ?? null;
        $text = $this->normalize($message);

        if (Str::contains($text, ['huy', 'lam lai'])) {
            session()->forget(self::SESSION_KEY);

            return $this->result('Mình đã bỏ lựa chọn đặt sân. Bạn có thể bắt đầu lại bất cứ lúc nào.', 'PUBLIC_BOOKING_RESET', ['Hủy', 'Bắt đầu đặt sân']);
        }
        if (in_array($state['stage'] ?? null, ['availability', 'slot_selection', 'login'], true)
            && Str::contains($text, ['dat san', 'dat lich', 'book san', 'booking san'])) {
            session()->forget(self::SESSION_KEY);
            $state = [];
        }
        if (filled($state['stage'] ?? null) && Str::contains($text, ['lam lai', 'bat dau dat san'])) {
            session()->forget(self::SESSION_KEY);
            $state = [];
        }

        $date = $this->parseDate($message);
        if ($date) $state['date'] = $date->toDateString();
        $hour = $this->parseHour($message);
        if ($hour !== null) $state['hour'] = $hour;

        if (blank($state['date'] ?? null)) {
            return $this->ask($state, 'date', 'Bạn muốn đặt sân vào ngày nào? Bạn có thể trả lời "Hôm nay", "Ngày mai" hoặc dd/mm/yyyy.', ['Hôm nay', 'Ngày mai', 'Ngày kia']);
        }
        if (($state['hour'] ?? null) === null) {
            return $this->ask($state, 'time', 'Duyệt nhất bạn muốn chơi khung giờ nào? Ví dụ: 19h, 8h sáng hoặc 20h tối.', ['8h sáng', '14h', '19h tối']);
        }
        if (! ($state['preferences_captured'] ?? false)) {
            if ($previousStage !== 'preferences') {
                return $this->ask($state, 'preferences', 'Bạn có yêu cầu gì thêm về sân không? Ví dụ: sân VIP, khu vực, ngân sách hoặc ghi chú. Nếu không có, hãy trả lời "Không".', ['Không', 'Sân tiêu chuẩn', 'Ưu tiên sân VIP']);
            }
            if (! Str::contains($text, ['khong', 'khong can', 'tuy chon', 'mac dinh'])) {
                $state['preferences'] = trim(mb_substr($message, 0, 300));
            }
            $state['preferences_captured'] = true;
        }
        $this->remember($state);
        $availability = $this->availableCourts->findByDate(Carbon::parse($state['date']), $state['hour'], true);

        if (($availability['court_count'] ?? 0) === 0) {
            $state['stage'] = 'availability';
            $this->remember($state);

            return $this->result('Chưa có sân trống đúng khung '.$state['hour'].'h ngày '.Carbon::parse($state['date'])->format('d/m/Y').'. Bạn có thể đổi ngày hoặc khung giờ; mình chưa tạo booking nào.', 'PUBLIC_BOOKING_NO_AVAILABILITY', ['Đổi sang ngày mai 20h', 'Bắt đầu đặt sân']);
        }

        $state['stage'] = 'slot_selection';
        $state['expires_at'] = now()->addMinutes(15)->timestamp;
        $state['choices'] = collect($availability['buttons'] ?? [])->mapWithKeys(fn (array $button) => [
            $button['id'] => [
                'court_id' => $button['court_id'], 'time_slot_id' => $button['time_slot_id'], 'court_name' => $button['label'],
                'date' => $state['date'], 'start_time' => $button['start_time'],
                'end_time' => $button['end_time'], 'price' => $button['price'],
            ],
        ])->all();
        $this->remember($state);

        $buttons = collect($availability['buttons'] ?? [])->map(fn (array $button) => [
            'id' => $button['id'], 'action' => 'public_select_booking_slot', 'label' => $button['label'],
            'court_id' => $button['court_id'], 'date' => $state['date'],
            'start_time' => $button['start_time'], 'end_time' => $button['end_time'], 'price' => $button['price'],
        ])->values()->all();

        return $this->result(
            'Mình đã kiểm tra lịch thật ngày '.Carbon::parse($state['date'])->format('d/m/Y').', lúc '.$state['hour'].'h. Chọn một sân và khung giờ bên dưới; bước sau mình sẽ tổng hợp để bạn đăng nhập và xác nhận.',
            'PUBLIC_BOOKING_OPTIONS', [], ['cards' => $availability['cards'] ?? [], 'buttons' => $buttons, 'awaiting' => 'slot_selection'],
        );
    }

    public function selectSlot(string $choiceId): array
    {
        $state = $this->state();
        $slot = $state['choices'][$choiceId] ?? null;
        if (! is_array($state['choices'] ?? null) || ! is_array($slot) || ($state['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget(self::SESSION_KEY);

            return $this->result('Lựa chọn này đã hết hạn. Mình chưa tạo booking; hãy kiểm tra lại lịch để có giá và khung giờ mới nhất.', 'PUBLIC_BOOKING_SELECTION_EXPIRED', ['Bắt đầu đặt sân']);
        }

        $state['stage'] = 'login';
        $this->remember($state);
        $url = route('bookings.create', [
            'booking_date' => $state['date'], 'court_id' => $slot['court_id'], 'time_slot_id' => $slot['time_slot_id'],
        ]);
        $price = $slot['price'] ? number_format((float) $slot['price'], 0, ',', '.').'đ' : 'sẽ được xác nhận trên trang đặt sân';
        $name = Str::before((string) $slot['court_name'], ':');

        return $this->result(
            "Bạn đang chọn:\n- Sân: $name\n- Ngày: ".Carbon::parse($state['date'])->format('d/m/Y')."\n- Giờ: ".substr((string) $slot['start_time'], 0, 5).'–'.substr((string) $slot['end_time'], 0, 5)."\n- Giá tham khảo: $price\n\nĐể tạo booking và thanh toán, bạn cần đăng nhập. Trang Đặt sân sẽ kiểm tra lại lịch trống và giá trước khi xác nhận; hiện tại mình chưa tạo booking nào.",
            'PUBLIC_BOOKING_READY_TO_LOGIN', ['Hủy', 'Bắt đầu đặt sân'],
            ['redirect_url' => $url, 'awaiting' => 'login'],
        );
    }

    private function ask(array $state, string $stage, string $answer, array $suggestions): array
    {
        $state['stage'] = $stage;
        $this->remember($state);

        return $this->result($answer, 'PUBLIC_BOOKING_'.$stage, $suggestions, ['awaiting' => $stage]);
    }

    private function result(string $answer, string $intent, array $suggestions = [], array $extra = []): array
    {
        return array_merge([
            'understood' => true, 'answer' => $answer, 'intent' => $intent,
            'suggestions' => $suggestions ?: ['Hỏi về giá sân', 'Hỏi về chính sách đặt sân'],
            'buttons' => [], 'cards' => [], 'engine' => 'public-booking-guide-v1',
            'pipeline_stage' => 'public_booking_guide', 'generation_skipped' => true,
        ], $extra);
    }

    private function state(): array
    {
        $state = session(self::SESSION_KEY, []);

        return is_array($state) ? $state : [];
    }

    private function remember(array $state): void
    {
        session([self::SESSION_KEY => $state]);
    }

    private function parseDate(string $message): ?Carbon
    {
        $text = $this->normalize($message);
        if (Str::contains($text, ['ngay kia', 'hai ngay'])) return today()->addDays(2);
        if (Str::contains($text, ['ngay mai', 'toi mai'])) return today()->addDay();
        if (Str::contains($text, ['hom nay', 'toi nay'])) return today();
        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})(?:[\/-](\d{4}))?\b/', $text, $parts)) {
            try {
                $date = Carbon::createFromDate((int) ($parts[3] ?? now()->year), (int) $parts[2], (int) $parts[1])->startOfDay();

                return $date->isPast() ? null : $date;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function parseHour(string $message): ?int
    {
        $text = $this->normalize($message);
        if (preg_match('/(?:luc\s*)?(\d{1,2})\s*(?:gio|h)\b/', $text, $parts)) {
            $hour = (int) $parts[1] + (preg_match('/\b(chi|toi)\b/', $text) && (int) $parts[1] < 12 ? 12 : 0);

            return $hour >= 0 && $hour <= 23 ? $hour : null;
        }
        if (Str::contains($text, ['buoi sang', 'sang'])) return 8;
        if (Str::contains($text, ['buoi chieu', 'chieu'])) return 14;
        if (Str::contains($text, ['buoi toi', 'toi'])) return 19;

        return null;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($value))) ?? '');
    }
}

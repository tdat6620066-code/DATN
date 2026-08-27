<?php

namespace App\Services;

use Illuminate\Support\Str;

class IntentClassifierService
{
    public function __construct(private readonly OpenAiService $openai) {}

    public function classify(string $message, ?int $userId = null): array
    {
        if ($this->openai->configured()) {
            try {
                return $this->openai->classifyIntent($message, $userId) + ['classifier' => config('services.openai.model')];
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $this->fallback($message) + ['classifier' => 'local-fallback'];
    }

    private function fallback(string $message): array
    {
        $text = Str::lower(Str::ascii($message));
        preg_match('/\b(bk[0-9a-z]+)\b/i', $text, $booking);
        preg_match('/(?:luc\s*)?(\d{1,2})\s*(?:gio|h)\b/', $text, $time);

        $intent = match (true) {
            Str::contains($text, ['muon huy', 'huy booking', 'huy don']) => 'CANCEL_BOOKING',
            Str::contains($text, ['thanh toan', 'tra tien']) => 'PAYMENT_STATUS',
            Str::contains($text, ['dat san']) => 'BOOK_COURT',
            Str::contains($text, ['con trong', 'con san', 'san trong', 'gio trong']) => 'CHECK_AVAILABILITY',
            Str::contains($text, ['danh gia san', 'cach danh gia']) => 'FAQ',
            Str::contains($text, ['gia san', 'gia thue', 'gia bao nhieu', 'bang gia']) => 'COURT_PRICE',
            Str::contains($text, ['kiem tra bk', 'don ', 'booking']) => 'BOOKING_STATUS',
            Str::contains($text, ['khuyen mai', 'voucher', 'giam gia', 'uu dai']) => 'PROMOTION',
            Str::contains($text, ['dich vu', 'thue vot', 'thue giay', 'mua cau', 'ban cau']) => 'SERVICE',
            Str::contains($text, ['tim san', 'san o ', 'san tai ']) => 'FIND_COURT',
            default => 'FAQ',
        };

        return [
            'intent' => $intent,
            'date' => Str::contains($text, 'ngay mai') ? today()->addDay()->toDateString() : (Str::contains($text, ['hom nay', 'toi nay']) ? today()->toDateString() : null),
            'hour' => isset($time[1]) ? (int) $time[1] : null,
            'area' => null,
            'court_name' => null,
            'booking_code' => isset($booking[1]) ? strtoupper($booking[1]) : null,
            'service_name' => null,
            'limit' => preg_match('/\b5\s+don\b/', $text) ? 5 : null,
            'confidence' => $intent === 'FAQ' ? 0.4 : 0.75,
        ];
    }
}

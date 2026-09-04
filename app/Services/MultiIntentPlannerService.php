<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MultiIntentPlannerService
{
    public function __construct(
        private readonly OpenAiService $openai,
        private readonly AvailableCourtService $availableCourts,
    ) {}

    public function shouldHandle(string $message): bool
    {
        $text = Str::lower(Str::ascii($message));
        $revision = Str::contains($text, ['thoi chuyen', 'doi sang', 'chuyen sang', 're hon', 'gio do', 'khu vuc do']);

        return Str::contains($text, ['neu con', 'dat luon', 'duoi ', 'tim san']) && preg_match('/\d/', $text)
            || ($revision && session()->has('chatbot.search_plan'));
    }

    public function handle(string $message, User $user): array
    {
        $current = session('chatbot.search_plan', []);
        $plan = $this->makePlan($message, $current, $user->id);
        foreach (['date', 'hour', 'area', 'max_price', 'wants_booking'] as $key) {
            if (($plan[$key] ?? null) !== null) {
                $current[$key] = $plan[$key];
            }
        }
        $current['steps'] = $plan['steps'];
        $current['updated_at'] = now()->toIso8601String();
        session(['chatbot.search_plan' => $current]);
        session()->forget(['chatbot.selected_slot', 'chatbot.slot_choices']);

        if (blank($current['date'] ?? null)) {
            return $this->clarification('Bạn muốn tìm sân vào ngày nào?', 'date', $current);
        }

        $result = $this->availableCourts->findByDate(
            Carbon::parse($current['date']),
            $current['hour'] ?? null,
            isset($current['hour']),
            $current['area'] ?? null,
            isset($current['max_price']) ? (float) $current['max_price'] : null,
        );
        $constraints = collect([
            filled($current['area'] ?? null) ? 'khu vực '.$current['area'] : null,
            isset($current['hour']) ? 'lúc '.$current['hour'].'h' : null,
            isset($current['max_price']) ? 'không quá '.number_format((float) $current['max_price'], 0, ',', '.').'đ' : null,
        ])->filter()->join(', ');
        $answer = ($result['court_count'] ?? 0) > 0
            ? 'Mình đã chạy kế hoạch FIND → CHECK SLOT → PRICE'.(($current['wants_booking'] ?? false) ? ' → CONFIRM' : '').($constraints ? " với {$constraints}. " : '. ').'Bạn hãy chọn một slot; Laravel sẽ kiểm tra lại rồi hỏi xác nhận trước khi tạo booking.'
            : $result['reply'];

        return [
            'understood' => true,
            'answer' => $answer,
            'intent' => 'MULTI_INTENT_BOOKING',
            'plan' => $current,
            'context_used' => $plan['is_revision'] ?? false,
            'pipeline_stage' => 'multi_intent_planner',
            'engine' => $plan['planner'] ?? 'local-planner',
            'suggestions' => ['Đổi sang 8h', 'Rẻ hơn nữa', 'Đổi khu vực'],
        ] + collect($result)->except(['reply', 'intent'])->all();
    }

    private function makePlan(string $message, array $current, int $userId): array
    {
        if ($this->openai->configured()) {
            try {
                return $this->openai->planCourtRequest($message, $current, $userId) + ['planner' => config('services.openai.model')];
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $this->fallbackPlan($message, $current) + ['planner' => 'local-planner'];
    }

    private function fallbackPlan(string $message, array $current): array
    {
        $text = Str::lower(Str::ascii($message));
        preg_match('/(?:luc\s*)?(\d{1,2})\s*(?:h|gio)\b/', $text, $hour);
        preg_match('/(?:duoi|khong qua|toi da)\s*([0-9]+)\s*(k|nghin|000)?/', $text, $price);
        preg_match('/(?:o|sang)\s+([a-z\s]+?)(?=\s+(?:duoi|luc|[0-9]{1,2}h|neu|thi|re hon)|$)/', $text, $area);
        $maxPrice = isset($price[1]) ? (float) $price[1] * (in_array($price[2] ?? '', ['k', 'nghin'], true) ? 1000 : 1) : null;
        if (Str::contains($text, 're hon') && ! $maxPrice && isset($current['max_price'])) {
            $maxPrice = max(0, (float) $current['max_price'] * .85);
        }

        return [
            'steps' => Str::contains($text, ['dat luon', 'neu con']) ? ['FIND', 'CHECK_SLOT', 'PRICE', 'CONFIRM'] : ['FIND', 'CHECK_SLOT', 'PRICE'],
            'date' => Str::contains($text, 'ngay mai') || preg_match('/\bmai\b/', $text) ? today()->addDay()->toDateString() : (Str::contains($text, 'hom nay') ? today()->toDateString() : null),
            'hour' => isset($hour[1]) ? (int) $hour[1] : null,
            'area' => isset($area[1]) ? trim(Str::title($area[1])) : null,
            'max_price' => $maxPrice,
            'wants_booking' => Str::contains($text, ['dat luon', 'neu con thi dat']) ? true : null,
            'is_revision' => Str::contains($text, ['thoi', 'doi sang', 'chuyen sang', 're hon']),
        ];
    }

    private function clarification(string $answer, string $awaiting, array $plan): array
    {
        return [
            'understood' => true,
            'answer' => $answer,
            'intent' => 'MULTI_INTENT_BOOKING',
            'awaiting' => $awaiting,
            'plan' => $plan,
            'suggestions' => ['Hôm nay', 'Ngày mai', 'Ngày kia'],
            'pipeline_stage' => 'multi_intent_clarification',
            'engine' => 'planner',
        ];
    }
}

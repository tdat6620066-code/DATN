<?php

namespace App\Services;

use App\Models\ChatbotFaq;
use App\Models\ChatbotKnowledge;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class RuleBasedChatService
{
    public function reply(string $message): array
    {
        $message = $this->normalize($message);
        $best = null;

        $knowledges = Cache::remember('chatbot.knowledge.active', 600, fn () => ChatbotKnowledge::query()->where('active', true)->get());
        foreach ($knowledges as $knowledge) {
            $score = $this->keywordScore($message, $knowledge->keywords ?? []);
            $best = $this->choose($best, $score, (int) $knowledge->priority, $knowledge->intent, $knowledge->answer, 'chatbot_knowledge');
        }

        $faqs = Cache::remember('chatbot.faqs.active', 600, fn () => ChatbotFaq::query()->where('active', true)->get());
        foreach ($faqs as $faq) {
            $score = $this->keywordScore($message, $faq->keywords ?? []);
            $question = $this->normalize($faq->question);
            if ($question !== '' && ($message === $question || Str::contains($message, $question))) {
                $score += 12;
            }
            $best = $this->choose($best, $score, (int) $faq->priority, $faq->category, $faq->answer, 'chatbot_faqs');
        }

        if ($best) {
            return ['matched' => true] + $best;
        }

        return [
            'matched' => false,
            'intent' => null,
            'answer' => 'Tôi chưa hiểu câu hỏi. Bạn có thể hỏi về đặt sân, giá sân, thanh toán, hủy lịch hoặc hoàn tiền.',
            'score' => 0,
            'source' => 'fallback',
        ];
    }

    private function keywordScore(string $message, array $keywords): int
    {
        return collect($keywords)->map(fn ($keyword) => $this->normalize((string) $keyword))
            ->filter(fn ($keyword) => $keyword !== '' && Str::contains($message, $keyword))
            ->sum(fn ($keyword) => str_contains($keyword, ' ') ? 3 : 1);
    }

    private function choose(?array $best, int $score, int $priority, string $intent, string $answer, string $source): ?array
    {
        if ($score <= 0 || ($best && ($score < $best['score'] || ($score === $best['score'] && $priority <= $best['priority'])))) {
            return $best;
        }

        return compact('intent', 'answer', 'score', 'priority', 'source');
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}

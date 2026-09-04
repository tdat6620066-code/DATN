<?php

namespace App\Services;

use App\Models\ChatbotLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ChatbotService
{
    public function __construct(
        private readonly IntentClassifierService $classifier,
        private readonly DatabaseChatService $database,
        private readonly AvailableCourtService $availableCourts,
        private readonly RagService $rag,
    ) {}

    public function answer(string $message, User $user): ?array
    {
        [$resolvedMessage, $contextUsed] = $this->withShortContext($message, $user);
        if ($pendingIntent = session()->pull('chatbot.pending_clarification')) {
            $resolvedMessage = 'Tiếp tục ý định '.$pendingIntent.': '.$resolvedMessage;
            $contextUsed = true;
        }

        $semanticMatches = $this->rag->search($resolvedMessage);
        $directMatch = collect($semanticMatches)->first(fn (array $match) => in_array($match['source_type'], ['faq', 'knowledge'], true)
            && $match['score'] >= (float) config('services.openai.rag_direct_threshold', 0.84)
            && filled($match['metadata']['answer'] ?? null));
        if ($directMatch) {
            return [
                'understood' => true,
                'answer' => $directMatch['metadata']['answer'],
                'intent' => 'FAQ',
                'suggestions' => ['Tìm sân trống', 'Xem bảng giá'],
                'source' => 'semantic_rag_direct',
                'rag_sources' => [$directMatch],
                'pipeline_stage' => 'semantic_rag_direct',
                'context_used' => $contextUsed,
                'generation_skipped' => true,
            ];
        }

        $classification = $this->classifier->classify($resolvedMessage, $user->id);
        $this->rememberEntities($classification);

        if ($classification['intent'] === 'BOOK_COURT' && blank($classification['date'] ?? null)) {
            return $this->clarification('Bạn muốn đặt sân vào ngày nào?', 'date', $classification, $contextUsed);
        }
        if ($classification['intent'] === 'BOOK_COURT' && ($classification['hour'] ?? null) === null) {
            return $this->clarification('Bạn muốn đặt sân lúc mấy giờ?', 'hour', $classification, $contextUsed);
        }
        if ($classification['intent'] === 'CHECK_AVAILABILITY' && blank($classification['date'] ?? null)) {
            return $this->clarification('Bạn muốn kiểm tra sân trống vào ngày nào?', 'date', $classification, $contextUsed);
        }

        if (in_array($classification['intent'], ['BOOK_COURT', 'CHECK_AVAILABILITY'], true)
            && filled($classification['date'] ?? null)) {
            $availability = $this->availableCourts->findByDate(
                Carbon::parse($classification['date']),
                $classification['hour'] ?? null,
                ($classification['hour'] ?? null) !== null,
            );
            $result = [
                'understood' => true,
                'answer' => $availability['reply'],
                'suggestions' => ['Xem ngày khác', 'Xem bảng giá'],
            ] + collect($availability)->except('reply')->all();
        } else {
            $result = $this->database->answerClassified($classification, $resolvedMessage, $user);
        }

        if ($result === null) {
            session(['chatbot.pending_rag_matches' => $semanticMatches]);

            return null;
        }

        $result['intent_detail'] = $result['intent'] ?? null;
        $result['intent'] = $classification['intent'];
        $result['pipeline_stage'] = 'mysql';
        $result['context_used'] = $contextUsed;
        $result['classification'] = $classification;

        return $result;
    }

    private function withShortContext(string $message, User $user): array
    {
        $normalized = Str::lower(Str::ascii($message));
        $isFollowUp = Str::contains($normalized, [
            'san do', 'san nay', 'gio do', 'ngay do', 'don do', 'don nay', 'gia bao nhieu', 'con trong khong',
            'thi sao', 'con no', 'cai do',
        ]) || preg_match('/^(?:hom nay|ngay mai|ngay kia|cuoi tuan|(?:luc\s*)?\d{1,2}(?:h|:\d{2})?)$/', trim($normalized)) === 1;

        if (! $isFollowUp) {
            return [$message, false];
        }

        $memory = array_filter(session('chatbot.context', []), fn ($value) => filled($value));
        $previousInteraction = ChatbotLog::query()
            ->where('user_id', $user->id)
            ->where('status', 'SUCCESS')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->whereNotNull('question')
            ->whereNotNull('intent')
            ->whereIn('intent', ['BOOK_COURT', 'CHECK_AVAILABILITY'])
            ->latest()
            ->first(['question', 'intent']);

        if (! $previousInteraction && $memory === []) {
            return [$message, false];
        }

        $intentContext = match ($previousInteraction?->intent) {
            'BOOK_COURT' => 'Tôi muốn đặt sân',
            'CHECK_AVAILABILITY' => 'Tôi muốn kiểm tra sân trống',
            default => '',
        };
        $previousQuestion = trim(($intentContext ? $intentContext.'. ' : '').($previousInteraction?->question ?? ''));

        return [trim(($previousQuestion ? $previousQuestion.'. ' : '').($memory ? 'Ngữ cảnh: '.json_encode($memory, JSON_UNESCAPED_UNICODE).'. ' : '').$message), true];
    }

    private function rememberEntities(array $classification): void
    {
        $memory = session('chatbot.context', []);
        foreach (['date', 'hour', 'area', 'court_name', 'booking_code', 'service_name'] as $key) {
            if (filled($classification[$key] ?? null)) {
                $memory[$key] = $classification[$key];
            }
        }
        $memory['intent'] = $classification['intent'];
        $memory['updated_at'] = now()->toIso8601String();
        session(['chatbot.context' => $memory]);
    }

    private function clarification(string $answer, string $awaiting, array $classification, bool $contextUsed): array
    {
        session(['chatbot.pending_clarification' => $classification['intent']]);

        return [
            'understood' => true,
            'answer' => $answer,
            'intent' => $classification['intent'],
            'awaiting' => $awaiting,
            'suggestions' => $awaiting === 'date' ? ['Hôm nay', 'Ngày mai', 'Cuối tuần'] : ['18h', '19h', '20h'],
            'pipeline_stage' => 'clarification',
            'context_used' => $contextUsed,
            'classification' => $classification,
        ];
    }
}

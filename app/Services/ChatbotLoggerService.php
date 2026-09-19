<?php

namespace App\Services;

use App\Models\AiInteraction;
use App\Models\ChatbotLog;
use App\Models\ChatbotUnanswered;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ghi nhật ký hội thoại của SmashBot.
 *
 * Một lượt hỏi/đáp được ghi vào hai bảng:
 * - chatbot_logs: nhật ký chuyên biệt, cũng là nguồn lịch sử hội thoại cho
 *   AiKnowledgeService::recentConversation() để hiểu câu hỏi nối tiếp.
 * - ai_interactions: audit log dùng chung cho các tính năng AI khác.
 *
 * Câu hỏi không trả lời được còn được gom vào chatbot_unanswered để admin
 * bổ sung vào kho tri thức.
 */
class ChatbotLoggerService
{
    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function log(User $user, string $question, array $result, int $latencyMs): array
    {
        $status = ($result['understood'] ?? true) ? 'SUCCESS' : 'UNANSWERED';
        $engine = isset($result['engine']) ? Str::limit((string) $result['engine'], 80, '') : null;
        $intent = isset($result['intent']) ? Str::limit((string) $result['intent'], 80, '') : null;

        try {
            $log = ChatbotLog::create([
                'user_id' => $user->id,
                'session_id_hash' => $this->sessionHash(),
                'question' => $question,
                'answer' => $result['answer'] ?? null,
                'engine' => $engine,
                'intent' => $intent,
                'status' => $status,
                'latency_ms' => $latencyMs,
                'metadata' => $this->metadata($result),
            ]);

            if ($status === 'UNANSWERED' && filled($question)) {
                $this->recordUnanswered($log->id, $question, $intent);
            }

            $confidence = data_get($result, 'classification.confidence');

            AiInteraction::create([
                'user_id' => $user->id,
                'type' => 'CHATBOT',
                'input' => $question,
                'context' => [
                    'intent' => $intent,
                    'engine' => $engine,
                    'pipeline_stage' => $result['pipeline_stage'] ?? null,
                    'confidence' => is_numeric($confidence) ? (float) $confidence : null,
                ],
                'output' => [
                    'answer' => $result['answer'] ?? null,
                    'suggestions' => array_values((array) ($result['suggestions'] ?? [])),
                    'understood' => (bool) ($result['understood'] ?? true),
                ],
                'status' => $status === 'UNANSWERED' ? 'FAILED' : 'SUCCESS',
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $exception) {
            // Nhật ký không được làm hỏng trải nghiệm chat của khách.
            Log::warning('Không ghi được log chatbot: '.$exception->getMessage());
        }

        return $result;
    }

    private function sessionHash(): ?string
    {
        $sessionId = session()->getId();

        return filled($sessionId) ? hash('sha256', $sessionId) : null;
    }

    /**
     * Chỉ lưu metadata hữu ích cho vận hành, tránh phình bảng log.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function metadata(array $result): array
    {
        $tools = collect($result['tool_trace'] ?? [])
            ->map(fn ($item) => data_get($item, 'tool'))
            ->filter()->unique()->values()->all();

        return array_filter([
            'pipeline_stage' => $result['pipeline_stage'] ?? null,
            'fallback' => $result['fallback'] ?? null,
            // Giữ khoá openai_error để trang chatbot-analytics cũ vẫn đọc được,
            // đồng thời có llm_error cho provider Groq.
            'openai_error' => $result['llm_error'] ?? null,
            'llm_error' => $result['llm_error'] ?? null,
            'security_flag' => $result['security_flag'] ?? null,
            'context_used' => $result['context_used'] ?? null,
            'generation_skipped' => $result['generation_skipped'] ?? null,
            'tools' => $tools === [] ? null : $tools,
            'card_count' => count((array) ($result['cards'] ?? [])),
            'button_count' => count((array) ($result['buttons'] ?? [])),
        ], fn ($value) => $value !== null);
    }

    private function recordUnanswered(int $logId, string $question, ?string $intent): void
    {
        $existing = ChatbotUnanswered::query()
            ->where('question', $question)
            ->where('status', 'OPEN')
            ->first();

        if ($existing) {
            $existing->increment('occurrences');

            return;
        }

        ChatbotUnanswered::create([
            'chatbot_log_id' => $logId,
            'question' => $question,
            'intent' => $intent,
            'occurrences' => 1,
            'status' => 'OPEN',
        ]);
    }
}
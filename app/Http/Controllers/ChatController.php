<?php

namespace App\Http\Controllers;

use App\Services\AiChatbotService;
use App\Services\ChatbotLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Điểm vào của SmashBot cho giao diện chat.
 *
 * Mọi câu hỏi đều được xử lý bởi AiChatbotService: pipeline gồm security guard,
 * booking copilot, multi-intent planner, agent tool calling (Groq/OpenAI đọc dữ
 * liệu thật qua tool) và cuối cùng là engine luật khi không có API key.
 */
class ChatController extends Controller
{
    /**
     * Các action do nút bấm trong khung chat gửi lên (không phải câu hỏi tự do).
     */
    private const ACTIONS = [
        'select_slot',
        'confirm_booking',
        'find_other_slot',
        'confirm_cancel',
        'abort_cancel',
        'preview_copilot_booking',
        'confirm_copilot_booking',
        'copilot_other_choices',
    ];

    public function __construct(
        private readonly AiChatbotService $chatbot,
        private readonly ChatbotLoggerService $logger,
    ) {}

    /**
     * Trả lời dạng JSON cho API và cho test.
     */
    public function chat(Request $request): JsonResponse
    {
        [$message, $action, $choiceId] = $this->payload($request);

        $started = hrtime(true);
        $result = $this->chatbot->answer($message, $request->user(), $action, $choiceId);
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);

        $this->logger->log($request->user(), $message, $result, $latency);

        return response()->json(['data' => $result]);
    }

    /**
     * Trả lời dạng NDJSON để khung chat hiển thị dần từng cụm ký tự.
     */
    public function stream(Request $request): StreamedResponse
    {
        [$message, $action, $choiceId] = $this->payload($request);

        $started = hrtime(true);
        $result = $this->chatbot->answer($message, $request->user(), $action, $choiceId);
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);

        $this->logger->log($request->user(), $message, $result, $latency);

        $done = collect($result)->only([
            'suggestions', 'cards', 'buttons', 'intent', 'awaiting',
            'redirect_url', 'booking_url', 'booking_code', 'preview', 'plan',
        ])->all();

        return response()->stream(function () use ($result, $done): void {
            foreach (mb_str_split((string) ($result['answer'] ?? ''), 12) as $text) {
                echo json_encode(['type' => 'delta', 'text' => $text], JSON_UNESCAPED_UNICODE)."\n";
            }
            echo json_encode(['type' => 'done', 'data' => $done], JSON_UNESCAPED_UNICODE)."\n";
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Chuẩn hoá payload: câu hỏi tự do HOẶC action + choice_id của nút bấm.
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:'.(int) config('chatbot.max_message_length', 500)],
            'action' => ['nullable', 'string', 'in:'.implode(',', self::ACTIONS)],
            'choice_id' => ['nullable', 'string', 'max:100'],
        ]);

        $message = trim((string) ($data['message'] ?? ''));
        $action = $data['action'] ?? null;
        $choiceId = $data['choice_id'] ?? null;

        abort_if($message === '' && blank($action), 422, 'Cần nội dung câu hỏi hoặc action.');

        return [$message, $action, $choiceId];
    }
}

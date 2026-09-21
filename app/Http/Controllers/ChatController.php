<?php

namespace App\Http\Controllers;

use App\Services\AiChatbotService;
use App\Services\ChatbotLoggerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Entry point for the SmashBot chat interface. */
class ChatController extends Controller
{
    /** Actions emitted by chat buttons, rather than free-form messages. */
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

    /** Return a complete JSON response for the chat API. */
    public function chat(Request $request): JsonResponse
    {
        [$message, $action, $choiceId] = $this->payload($request);

        $started = hrtime(true);
        $result = $this->chatbot->answer($message, $request->user(), $action, $choiceId);
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);

        $this->logger->log($request->user(), $message, $result, $latency);

        return response()->json(['data' => $result]);
    }

    /** Stream the response as NDJSON for the chat widget. */
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
     * Normalize a free-form message or a button action payload.
     *
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', 'string', \Illuminate\Validation\Rule::in(self::ACTIONS)],
            'choice_id' => ['nullable', 'string', 'max:255'],
        ]);

        $message = trim((string) ($data['message'] ?? ''));
        $action = $data['action'] ?? null;
        $choiceId = $data['choice_id'] ?? null;

        abort_if($message === '' && blank($action), 422, 'A message or an action is required.');

        return [$message, $action, $choiceId];
    }
}

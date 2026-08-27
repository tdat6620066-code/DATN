<?php

namespace App\Http\Controllers;

use App\Models\AiInteraction;
use App\Models\ChatbotLog;
use App\Models\ChatbotUnanswered;
use App\Services\AiChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function stream(Request $request, AiChatbotService $chatbot): StreamedResponse
    {
        $jsonResponse = $this->chat($request, $chatbot);
        $payload = $jsonResponse->getData(true);
        $status = $jsonResponse->getStatusCode();

        return response()->stream(function () use ($payload, $status) {
            if ($status >= 400) {
                echo json_encode(['type' => 'error', 'message' => $payload['message'] ?? 'SmashBot không thể trả lời.'], JSON_UNESCAPED_UNICODE)."\n";

                return;
            }

            $data = $payload['data'];
            $answer = (string) ($data['answer'] ?? '');
            for ($offset = 0, $length = mb_strlen($answer); $offset < $length; $offset += 12) {
                echo json_encode(['type' => 'delta', 'text' => mb_substr($answer, $offset, 12)], JSON_UNESCAPED_UNICODE)."\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            unset($data['answer']);
            echo json_encode(['type' => 'done', 'data' => $data], JSON_UNESCAPED_UNICODE)."\n";
        }, $status, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function chat(Request $request, AiChatbotService $chatbot): JsonResponse
    {
        $data = $request->validate([
            'message' => ['nullable', 'required_without:action', 'string', 'max:500'],
            'action' => ['nullable', 'in:select_slot,confirm_booking,find_other_slot,confirm_cancel,abort_cancel,preview_copilot_booking,confirm_copilot_booking,copilot_other_choices'],
            'choice_id' => ['nullable', 'required_if:action,select_slot,preview_copilot_booking,confirm_copilot_booking', 'uuid'],
        ]);

        $started = hrtime(true);

        try {
            $message = $data['message'] ?? '';
            $output = $chatbot->answer($message, $request->user(), $data['action'] ?? null, $data['choice_id'] ?? null);

            AiInteraction::create([
                'user_id' => $request->user()->id,
                'type' => 'CHATBOT',
                'input' => $message !== '' ? $message : ($data['action'] ?? null),
                'context' => [
                    'session_id_hash' => hash('sha256', $request->session()->getId()),
                ],
                'output' => $output,
                'status' => 'SUCCESS',
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);

            $chatbotLog = ChatbotLog::create([
                'user_id' => $request->user()->id,
                'session_id_hash' => hash('sha256', $request->session()->getId()),
                'question' => $message !== '' ? $message : ($data['action'] ?? null),
                'answer' => $output['answer'] ?? null,
                'engine' => $output['engine'] ?? null,
                'intent' => $output['intent'] ?? null,
                'status' => 'SUCCESS',
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'metadata' => [
                    'action' => $data['action'] ?? null,
                    'choice_id' => $data['choice_id'] ?? null,
                    'understood' => $output['understood'] ?? null,
                    'fallback' => $output['fallback'] ?? false,
                    'pipeline_stage' => $output['pipeline_stage'] ?? null,
                    'context_used' => $output['context_used'] ?? false,
                    'security_flag' => $output['security_flag'] ?? false,
                    'security_reason' => $output['security_reason'] ?? null,
                    'openai_error' => $output['openai_error'] ?? null,
                    'booking_id' => $output['booking_id'] ?? null,
                    'booking_code' => $output['booking_code'] ?? null,
                    'booking_total' => $output['booking_total'] ?? null,
                    'tool_trace' => $output['tool_trace'] ?? [],
                ],
            ]);

            if (($output['understood'] ?? false) === false || ($output['fallback'] ?? false) === true) {
                $unanswered = ChatbotUnanswered::firstOrCreate(
                    ['question' => $message, 'status' => 'OPEN'],
                    ['chatbot_log_id' => $chatbotLog->id, 'intent' => $output['intent'] ?? null],
                );
                if (! $unanswered->wasRecentlyCreated) {
                    $unanswered->increment('occurrences');
                    $unanswered->update(['chatbot_log_id' => $chatbotLog->id]);
                }
            }

            $output['feedback_id'] = $chatbotLog->id;

            return response()->json(['data' => $output]);
        } catch (\Throwable $e) {
            AiInteraction::create([
                'user_id' => $request->user()->id,
                'type' => 'CHATBOT',
                'input' => $data['message'] ?? ($data['action'] ?? null),
                'context' => [
                    'session_id_hash' => hash('sha256', $request->session()->getId()),
                ],
                'status' => 'FAILED',
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);

            ChatbotLog::create([
                'user_id' => $request->user()->id,
                'session_id_hash' => hash('sha256', $request->session()->getId()),
                'question' => $data['message'] ?? ($data['action'] ?? null),
                'status' => 'FAILED',
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'metadata' => ['action' => $data['action'] ?? null],
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            report($e);

            return response()->json([
                'message' => 'Trợ lý tạm thời không phản hồi. Vui lòng thử lại sau.',
            ], 503);
        }
    }
}

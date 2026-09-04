<?php

namespace App\Http\Controllers;

use App\Models\ChatbotFeedback;
use App\Models\ChatbotLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotFeedbackController extends Controller
{
    public function store(Request $request, ChatbotLog $chatbotLog): JsonResponse
    {
        abort_unless($chatbotLog->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'rating' => ['required', 'in:UP,DOWN'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        ChatbotFeedback::updateOrCreate(
            ['chatbot_log_id' => $chatbotLog->id, 'user_id' => $request->user()->id],
            $data,
        );

        return response()->json(['message' => 'Cảm ơn bạn đã phản hồi.']);
    }
}

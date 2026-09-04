<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ChatbotEvalRun;
use App\Models\ChatbotFaq;
use App\Models\ChatbotFeedback;
use App\Models\ChatbotLog;
use App\Models\ChatbotUnanswered;
use Illuminate\Http\Request;

class ChatbotAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $days = min(90, max(7, (int) $request->integer('days', 30)));
        $query = ChatbotLog::query()->where('created_at', '>=', now()->subDays($days));
        $total = (clone $query)->count();
        $logs = (clone $query)->get();
        $feedbackQuery = ChatbotFeedback::query()->whereHas(
            'chatbotLog',
            fn ($feedbackLogs) => $feedbackLogs->where('created_at', '>=', now()->subDays($days)),
        );
        $feedbackTotal = (clone $feedbackQuery)->count();
        $positiveFeedback = (clone $feedbackQuery)->where('rating', 'UP')->count();
        $unansweredCount = ChatbotUnanswered::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
        $bookingIds = $logs->pluck('metadata.booking_id')->filter()->unique()->values();
        $chatbotBookings = Booking::query()->whereIn('id', $bookingIds)->get();
        $dates = collect(range($days - 1, 0))->map(fn ($offset) => today()->subDays($offset)->toDateString());
        $dailyLogs = $logs->groupBy(fn (ChatbotLog $log) => $log->created_at->toDateString());
        $topQuestions = $logs->filter(fn (ChatbotLog $log) => filled($log->question) && ! in_array($log->question, [
            'select_slot', 'confirm_booking', 'find_other_slot', 'confirm_cancel', 'abort_cancel',
        ], true))->groupBy(fn (ChatbotLog $log) => mb_strtolower(trim($log->question)))
            ->map(fn ($items) => (object) ['question' => $items->first()->question, 'total' => $items->count()])
            ->sortByDesc('total')->take(10)->values();

        return view('admin.chatbot-analytics', [
            'days' => $days,
            'evalRuns' => ChatbotEvalRun::query()->latest()->limit(8)->get(),
            'latestEval' => ChatbotEvalRun::query()->whereIn('status', ['PASSED', 'FAILED'])->latest()->first(),
            'summary' => [
                'total' => $total,
                'success_rate' => $total ? round((clone $query)->where('status', 'SUCCESS')->count() * 100 / $total, 1) : 0,
                'avg_latency' => (int) ((clone $query)->avg('latency_ms') ?? 0),
                'fallbacks' => (clone $query)->where('metadata->fallback', true)->count(),
                'positive_feedback' => $positiveFeedback,
                'negative_feedback' => (clone $feedbackQuery)->where('rating', 'DOWN')->count(),
                'positive_rate' => $feedbackTotal ? round($positiveFeedback * 100 / $feedbackTotal, 1) : 0,
                'unanswered_rate' => $total ? round($unansweredCount * 100 / $total, 1) : 0,
                'direct_answer_rate' => $total ? round((clone $query)->where('metadata->pipeline_stage', 'semantic_rag_direct')->count() * 100 / $total, 1) : 0,
                'security_blocks' => (clone $query)->where('metadata->security_flag', true)->count(),
                'openai_errors' => $logs->filter(fn (ChatbotLog $log) => filled(data_get($log->metadata, 'openai_error')))->count(),
                'chatbot_bookings' => $chatbotBookings->count(),
                'booking_value' => (float) $chatbotBookings->sum('total_amount'),
                'chatbot_revenue' => (float) $chatbotBookings->where('payment_status', 'PAID')->sum('total_amount'),
            ],
            'chart' => [
                'labels' => $dates->map(fn ($date) => date('d/m', strtotime($date)))->all(),
                'conversations' => $dates->map(fn ($date) => $dailyLogs->get($date, collect())->count())->all(),
                'latency' => $dates->map(fn ($date) => (int) ($dailyLogs->get($date, collect())->avg('latency_ms') ?? 0))->all(),
                'feedback' => [$positiveFeedback, (clone $feedbackQuery)->where('rating', 'DOWN')->count()],
            ],
            'topQuestions' => $topQuestions,
            'intents' => (clone $query)->selectRaw('intent, COUNT(*) as total')->groupBy('intent')->orderByDesc('total')->get(),
            'engines' => (clone $query)->selectRaw('engine, COUNT(*) as total')->groupBy('engine')->orderByDesc('total')->get(),
            'recent' => (clone $query)->with('user:id,name')->latest()->limit(30)->get(),
            'unanswered' => ChatbotUnanswered::where('status', 'OPEN')->orderByDesc('occurrences')->latest()->limit(20)->get(),
            'negativeFeedback' => (clone $feedbackQuery)
                ->where('rating', 'DOWN')
                ->with(['chatbotLog.user:id,name'])
                ->latest()
                ->limit(20)
                ->get(),
            'openAiErrors' => $logs->filter(fn (ChatbotLog $log) => filled(data_get($log->metadata, 'openai_error')))
                ->sortByDesc('created_at')->take(20)->values(),
        ]);
    }

    public function resolve(Request $request, ChatbotUnanswered $unanswered)
    {
        $data = $request->validate([
            'answer' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);
        ChatbotFaq::updateOrCreate(
            ['question_hash' => hash('sha256', mb_strtolower(trim($unanswered->question)))],
            [
                'category' => $data['category'] ?? 'admin_added',
                'question' => $unanswered->question,
                'answer' => $data['answer'],
                'keywords' => [],
                'priority' => 10,
                'active' => true,
            ],
        );
        $unanswered->update(['status' => 'RESOLVED', 'admin_note' => $data['answer']]);

        return back()->with('success', 'Đã bổ sung FAQ. Chạy chatbot:sync-rag để cập nhật embedding.');
    }
}

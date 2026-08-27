<?php

namespace App\Http\Controllers;

use App\Models\AiInteraction;
use App\Models\User;
use App\Services\AiAnalyticsService;
use App\Services\AiRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(
        private readonly AiRecommendationService $recommendations,
        private readonly AiAnalyticsService $analytics,
    ) {}

    public function courts(Request $request): JsonResponse
    {
        $data = $request->validate(['area' => ['nullable', 'string', 'max:100'], 'max_price' => ['nullable', 'numeric', 'min:0'], 'time_slot_id' => ['nullable', 'integer', 'exists:time_slots,id'], 'limit' => ['nullable', 'integer', 'between:1,20']]);
        return $this->tracked($request, 'COURT_RECOMMENDATION', null, $data, fn () => $this->recommendations->recommend($request->user(), $data));
    }

    public function promotion(Request $request): JsonResponse
    {
        $recommendation = $this->analytics->promotion($request->user());
        return response()->json(['data' => $recommendation]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['date' => ['nullable', 'date', 'after_or_equal:today']]);
        return response()->json(['data' => $this->analytics->forecast($data['date'] ?? now()->addDay()->toDateString())]);
    }

    public function reviews(Request $request): JsonResponse
    {
        $this->admin($request);
        return response()->json(['data' => $this->analytics->analyzeReviews()]);
    }

    public function customerPromotion(User $customer, Request $request): JsonResponse
    {
        $this->admin($request);
        abort_unless($customer->role === 'CUSTOMER', 422, 'Người dùng không phải khách hàng.');
        return response()->json(['data' => $this->analytics->promotion($customer)]);
    }

    private function tracked(Request $request, string $type, ?string $input, array $context, callable $callback): JsonResponse
    {
        $started = hrtime(true);
        try {
            $output = $callback();
            AiInteraction::create(['user_id' => $request->user()->id, 'type' => $type, 'input' => $input, 'context' => $context, 'output' => $output, 'status' => 'SUCCESS', 'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);
            return response()->json(['data' => $output]);
        } catch (\Throwable $e) {
            AiInteraction::create(['user_id' => $request->user()->id, 'type' => $type, 'input' => $input, 'context' => $context, 'status' => 'FAILED', 'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);
            report($e);
            return response()->json(['message' => 'Dịch vụ AI tạm thời không phản hồi. Vui lòng thử lại sau.'], 503);
        }
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN', 403);
    }
}

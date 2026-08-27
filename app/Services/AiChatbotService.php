<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Str;

class AiChatbotService
{
    public function __construct(
        private readonly OpenAiService $openai,
        private readonly AiKnowledgeService $knowledge,
        private readonly SmartChatService $smartChat,
        private readonly ChatbotService $chatbot,
        private readonly BookingService $bookings,
        private readonly RagService $rag,
        private readonly PromptInjectionGuardService $promptGuard,
        private readonly AiRecommendationService $recommendations,
        private readonly MultiIntentPlannerService $planner,
        private readonly ToolCallingAgentService $toolAgent,
        private readonly BookingCopilotService $bookingCopilot,
    ) {}

    public function answer(string $question, User $user, ?string $action = null, ?string $choiceId = null): array
    {
        if (filled($question)) {
            $security = $this->promptGuard->inspect($question);
            if ($security['blocked']) {
                return $this->promptGuard->blockedResponse($security['reason']);
            }
        }

        $normalizedQuestion = Str::lower(Str::ascii($question));
        if ($action === 'preview_copilot_booking' && $choiceId) {
            return $this->bookingCopilot->showPreview($choiceId, $user);
        }
        if ($action === 'confirm_copilot_booking' && $choiceId) {
            return $this->bookingCopilot->confirm($choiceId, $user);
        }
        if ($action === 'copilot_other_choices') {
            return $this->bookingCopilot->showAlternatives($user);
        }
        if (filled($question) && $this->bookingCopilot->shouldHandle($question)) {
            return $this->bookingCopilot->prepare($question, $user);
        }

        if (filled($question) && $this->planner->shouldHandle($question)) {
            return $this->planner->handle($question, $user);
        }

        if (filled($question) && Str::contains($normalizedQuestion, [
            'goi y san', 'san phu hop voi toi', 'top 5 san', 'de xuat san', 'recommend san',
        ])) {
            return $this->recommendCourts($user);
        }

        if (filled($question) && $this->toolAgent->shouldHandle($question)) {
            try {
                return $this->toolAgent->answer($question, $user);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($action === 'select_slot' && $choiceId) {
            return $this->formatLocalResult($this->smartChat->selectSlot($choiceId)) + ['engine' => 'knowledge-v3', 'pipeline_stage' => 'business_action'];
        }
        if ($action === 'confirm_booking') {
            return $this->formatLocalResult($this->smartChat->confirmBooking($user)) + ['engine' => 'knowledge-v3', 'pipeline_stage' => 'business_action'];
        }
        if ($action === 'find_other_slot') {
            return $this->formatLocalResult($this->smartChat->findOtherSlot()) + ['engine' => 'knowledge-v3', 'pipeline_stage' => 'business_action'];
        }
        if ($action === 'confirm_cancel') {
            $bookingId = session()->pull('chatbot.pending_cancel_booking_id');
            $booking = $bookingId ? Booking::query()->where('user_id', $user->id)->find($bookingId) : null;
            if (! $booking) {
                return $this->actionResult('Yêu cầu hủy đã hết hiệu lực. Bạn hãy kiểm tra lại booking.', false);
            }
            $this->bookings->cancelBooking($booking);

            return $this->actionResult('Đã hủy booking '.$booking->booking_code.'.', true);
        }
        if ($action === 'abort_cancel') {
            session()->forget('chatbot.pending_cancel_booking_id');

            return $this->actionResult('Đã giữ nguyên booking, không có thay đổi nào được thực hiện.', true);
        }

        if ($databaseAnswer = $this->chatbot->answer($question, $user)) {
            return $databaseAnswer + ['engine' => 'database'];
        }

        $fallback = $this->localAnswer($question);

        // Availability and multi-turn booking results contain live slot IDs stored
        // in the session. They must reach the browser unchanged so choice buttons
        // submit actions instead of being rewritten as plain text by OpenAI.
        if (($fallback['understood'] ?? false) === true || in_array($fallback['intent'] ?? null, [
            'ASK_BOOKING_DATE',
            'FIND_AVAILABLE_COURTS',
        ], true)) {
            return $fallback + ['engine' => 'database', 'pipeline_stage' => $fallback['intent'] === 'FAQ' ? 'faq' : 'mysql'];
        }

        if (! $this->openai->configured()) {
            return $fallback + ['engine' => 'knowledge-v3', 'pipeline_stage' => 'faq'];
        }

        try {
            $retrievedKnowledge = session()->pull('chatbot.pending_rag_matches');
            if (! is_array($retrievedKnowledge)) {
                $retrievedKnowledge = $this->rag->search($question);
            }

            return $this->openai->chatbot(
                $question,
                [
                    'retrieved_knowledge' => $retrievedKnowledge,
                    'customer_context' => $this->knowledge->personalContext($user),
                ],
                $this->knowledge->recentConversation($user),
                $user->id,
            ) + ['engine' => config('services.openai.model'), 'pipeline_stage' => 'openai'];
        } catch (\Throwable $e) {
            report($e);

            return $fallback + [
                'engine' => 'knowledge-v3',
                'pipeline_stage' => 'faq',
                'fallback' => true,
                'openai_error' => str_contains(mb_strtolower($e->getMessage()), 'quota') ? 'quota' : (str_contains($e->getMessage(), '429') || str_contains(mb_strtolower($e->getMessage()), 'rate limit') ? 'rate_limit' : 'request_failed'),
            ];
        }
    }

    private function localAnswer(string $question): array
    {
        $result = $this->smartChat->reply($question);

        return $this->formatLocalResult($result);
    }

    private function recommendCourts(User $user): array
    {
        $result = $this->recommendations->recommend($user, ['limit' => 5]);
        $recommendations = collect($result['recommendations']);
        $cards = $recommendations->map(fn (array $item) => [
            'type' => 'court_recommendation',
            'title' => $item['name'],
            'subtitle' => $item['address'],
            'meta' => implode(' · ', $item['reasons']),
            'price_from' => $item['price_from'],
            'rating' => $item['rating'],
            'image_url' => $item['image_url'],
            'match_percent' => $item['match_percent'],
            'url' => $item['url'],
        ])->all();

        $intro = ($result['data_sufficient'] ?? false)
            ? 'Đây là Top '.count($cards).' sân phù hợp nhất dựa trên lịch sử đặt sân, giá, khung giờ, khu vực, rating và khuyến mãi của bạn.'
            : 'Bạn chưa có nhiều lịch sử đặt sân, nên đây là Top '.count($cards).' gợi ý ban đầu dựa trên rating, giá và khuyến mãi hiện có.';

        return [
            'understood' => true,
            'answer' => $cards === [] ? 'Hiện chưa có sân đang hoạt động để mình gợi ý.' : $intro,
            'intent' => 'RECOMMEND_COURT',
            'suggestions' => ['Tối nay còn sân không?', 'Giá thuê sân bao nhiêu?'],
            'cards' => $cards,
            'recommendation_profile' => $result['profile'],
            'engine' => 'recommendation-v1',
            'pipeline_stage' => 'recommendation_engine',
            'generation_skipped' => true,
        ];
    }

    private function actionResult(string $answer, bool $understood): array
    {
        return [
            'understood' => $understood,
            'answer' => $answer,
            'suggestions' => ['Kiểm tra booking của tôi', 'Tìm sân trống'],
            'intent' => 'BOOKING_STATUS',
            'engine' => 'database',
            'pipeline_stage' => 'business_action',
        ];
    }

    private function formatLocalResult(array $result): array
    {
        $matched = ($result['matched'] ?? true) || in_array($result['intent'], ['ask_booking_date', 'find_available_courts'], true);

        return [
            'understood' => $matched,
            'answer' => $result['reply'],
            'suggestions' => $this->suggestionsFor($result['intent'] ?? null),
            'intent' => $this->canonicalLocalIntent($result['intent'] ?? null),
            'intent_detail' => $result['intent'] ?? null,
        ] + collect($result)->except(['reply', 'matched'])->all();
    }

    private function canonicalLocalIntent(?string $intent): string
    {
        return match ($intent) {
            'ask_booking_date', 'find_available_courts' => 'CHECK_AVAILABILITY',
            'confirm_booking', 'booking_confirmed', 'expired_slot' => 'BOOKING_STATUS',
            default => 'FAQ',
        };
    }

    private function suggestionsFor(?string $intent): array
    {
        return match ($intent) {
            'ask_booking_date' => ['Hôm nay', 'Ngày mai', 'Ngày kia'],
            'find_available_courts' => ['Đặt sân ngay', 'Sân nào giá rẻ nhất?', 'Xem ngày khác'],
            default => ['Tìm sân phù hợp với tôi', 'Giá thuê sân bao nhiêu?', 'Có khuyến mãi nào?'],
        };
    }
}

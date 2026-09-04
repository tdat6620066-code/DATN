<?php

namespace App\Services;

use App\Models\ChatbotFaq;
use App\Models\ChatbotKnowledge;
use App\Models\ChatbotLog;
use App\Models\Court;
use App\Models\Promotion;
use App\Models\User;

class AiKnowledgeService
{
    public function forChat(User $user): array
    {
        $courts = Court::query()
            ->where('status', 'ACTIVE')
            ->with(['courtType:id,name', 'prices:id,court_id,time_slot_id,price,status', 'reviews' => fn ($q) => $q->where('status', 'APPROVED')->select('id', 'court_id', 'rating')])
            ->orderByDesc('is_featured')
            ->limit(15)
            ->get()
            ->map(fn (Court $court) => [
                'id' => $court->id,
                'name' => $court->name,
                'type' => $court->courtType?->name,
                'address' => $court->address,
                'opening_time' => $court->opening_time,
                'closing_time' => $court->closing_time,
                'availability_status' => $court->availability_status,
                'price_from' => ($price = $court->prices->where('status', 'ACTIVE')->min('price')) === null ? null : (float) $price,
                'rating' => round((float) $court->reviews->avg('rating'), 1),
            ])->all();

        $promotions = Promotion::query()
            ->where('status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->limit(10)->get(['title', 'description', 'end_at'])
            ->map(fn (Promotion $promotion) => [
                'title' => $promotion->title,
                'description' => $promotion->description,
                'end_at' => $promotion->end_at?->toIso8601String(),
            ])->all();

        $bookings = $user->bookings()->with(['bookingDetails.court:id,name', 'bookingDetails.timeSlot:id,name,start_time,end_time'])
            ->latest()->limit(5)->get()->map(fn ($booking) => [
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'total_amount' => (float) $booking->total_amount,
                'details' => $booking->bookingDetails->map(fn ($detail) => [
                    'court' => $detail->court?->name,
                    'date' => (string) $detail->booking_date,
                    'time_slot' => $detail->timeSlot?->name,
                ])->all(),
            ])->all();

        $faqs = ChatbotKnowledge::query()->where('active', true)->orderByDesc('priority')
            ->get(['intent', 'keywords', 'answer', 'priority'])->map(fn (ChatbotKnowledge $item) => [
                'intent' => $item->intent,
                'keywords' => $item->keywords,
                'answer' => $item->answer,
                'priority' => $item->priority,
            ])->all();

        $detailedFaqs = ChatbotFaq::query()->where('active', true)->orderByDesc('priority')
            ->get(['category', 'question', 'answer', 'keywords'])->map(fn (ChatbotFaq $item) => [
                'category' => $item->category,
                'question' => $item->question,
                'answer' => $item->answer,
                'keywords' => $item->keywords,
            ])->all();

        return [
            'current_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'customer' => ['id' => $user->id, 'name' => $user->name],
            'courts' => $courts,
            'active_promotions' => $promotions,
            'recent_bookings' => $bookings,
            'verified_faqs' => $faqs,
            'detailed_faqs' => $detailedFaqs,
            'booking_policy' => [
                'steps' => ['Chọn sân', 'Chọn ngày và khung giờ còn trống', 'Xác nhận booking', 'Thanh toán', 'Nhận mã QR'],
                'availability_note' => 'Tình trạng trống chính xác phải được kiểm tra trên trang đặt sân trước khi xác nhận.',
                'payment_method' => 'VNPay hoặc phương thức được hiển thị tại bước thanh toán.',
            ],
        ];
    }

    public function recentConversation(User $user, int $limit = 6): array
    {
        return ChatbotLog::query()->where('user_id', $user->id)->where('status', 'SUCCESS')
            ->latest()->limit($limit)->get()->reverse()->flatMap(function (ChatbotLog $interaction) {
                $answer = $interaction->answer;

                return array_values(array_filter([
                    filled($interaction->question) ? ['role' => 'user', 'content' => $interaction->question] : null,
                    filled($answer) ? ['role' => 'assistant', 'content' => $answer] : null,
                ]));
            })->values()->all();
    }

    public function personalContext(User $user): array
    {
        return [
            'customer' => ['id' => $user->id, 'name' => $user->name],
            'recent_bookings' => $user->bookings()->with(['bookingDetails.court:id,name', 'bookingDetails.timeSlot:id,name'])
                ->latest()->limit(5)->get()->map(fn ($booking) => [
                    'booking_code' => $booking->booking_code,
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'details' => $booking->bookingDetails->map(fn ($detail) => [
                        'court' => $detail->court?->name,
                        'date' => (string) $detail->booking_date,
                        'time_slot' => $detail->timeSlot?->name,
                    ])->all(),
                ])->all(),
        ];
    }
}

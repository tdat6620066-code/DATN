<?php

namespace App\Services;

use App\Models\AiDemandForecast;
use App\Models\AiPromotionRecommendation;
use App\Models\AiReviewAnalysis;
use App\Models\BookingDetail;
use App\Models\Review;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;

class AiAnalyticsService
{
    public function __construct(private readonly OpenAiService $openai) {}

    public function forecast(string $date): array
    {
        $target = Carbon::parse($date);
        $from = $target->copy()->subWeeks(12);
        return TimeSlot::where('status', 'ACTIVE')->orderBy('start_time')->get()->map(function (TimeSlot $slot) use ($target, $from) {
            $query = BookingDetail::where('time_slot_id', $slot->id)->whereIn('status', ['CONFIRMED', 'COMPLETED'])
                ->whereBetween('booking_date', [$from->toDateString(), $target->copy()->subDay()->toDateString()]);
            $count = (clone $query)->get()->filter(fn ($detail) => Carbon::parse($detail->booking_date)->dayOfWeek === $target->dayOfWeek)->count();
            $predicted = (int) round($count / 12);
            $courtCount = max(1, \App\Models\Court::where('status', 'ACTIVE')->count());
            $rate = round(min(100, $predicted / $courtCount * 100), 2);
            $level = $rate >= 75 ? 'HIGH' : ($rate >= 40 ? 'MEDIUM' : 'LOW');
            $recommendations = $level === 'HIGH' ? ['Cân nhắc tăng giá 10-15%', 'Mở thêm sân nếu có thể'] : ($level === 'LOW' ? ['Áp dụng khuyến mãi giờ thấp điểm'] : ['Giữ mức giá hiện tại']);
            $row = AiDemandForecast::updateOrCreate(
                ['court_id' => null, 'time_slot_id' => $slot->id, 'forecast_date' => $target->toDateString()],
                ['occupancy_rate' => $rate, 'predicted_bookings' => $predicted, 'demand_level' => $level, 'recommendations' => $recommendations, 'generated_at' => now()]
            );
            return ['time_slot_id' => $slot->id, 'name' => $slot->name, 'occupancy_rate' => $row->occupancy_rate, 'predicted_bookings' => $predicted, 'demand_level' => $level, 'recommendations' => $recommendations];
        })->all();
    }

    public function promotion(User $user): AiPromotionRecommendation
    {
        $bookings = $user->bookings()->whereIn('status', ['CONFIRMED', 'COMPLETED'])->latest()->get();
        [$segment, $discount, $title, $reason] = match (true) {
            $bookings->isEmpty() => ['NEW', 15, 'Ưu đãi khách hàng mới', 'Khuyến khích hoàn tất lần đặt sân đầu tiên.'],
            $bookings->count() >= 10 => ['VIP', 10, 'Tri ân khách hàng VIP', 'Bạn thuộc nhóm khách hàng thường xuyên.'],
            $bookings->first()->created_at->lt(now()->subDays(60)) => ['INACTIVE', 20, 'Ưu đãi quay trở lại', 'Bạn chưa đặt sân trong hơn 60 ngày.'],
            default => ['ACTIVE', 5, 'Ưu đãi dành riêng cho bạn', 'Cảm ơn bạn đã thường xuyên sử dụng SmashZone.'],
        };
        return AiPromotionRecommendation::updateOrCreate(
            ['user_id' => $user->id, 'status' => 'SUGGESTED'],
            compact('segment', 'title', 'reason') + ['discount_percent' => $discount, 'expires_at' => now()->addDays(14)]
        );
    }

    public function analyzeReviews(): array
    {
        $positive = ['tốt', 'sạch', 'đẹp', 'tuyệt', 'nhanh', 'thân thiện', 'hài lòng'];
        $negative = ['tệ', 'bẩn', 'chậm', 'đắt', 'hỏng', 'ồn', 'không hài lòng'];
        $topics = ['vệ sinh' => ['sạch', 'bẩn', 'vệ sinh'], 'giá' => ['giá', 'đắt', 'rẻ'], 'nhân viên' => ['nhân viên', 'phục vụ', 'thân thiện'], 'cơ sở vật chất' => ['sân', 'đèn', 'lưới', 'hỏng']];
        foreach (Review::whereNotNull('content')->get() as $review) {
            $text = mb_strtolower($review->content);
            $pos = collect($positive)->filter(fn ($word) => str_contains($text, $word))->count();
            $neg = collect($negative)->filter(fn ($word) => str_contains($text, $word))->count();
            $sentiment = $pos > $neg ? 'POSITIVE' : ($neg > $pos ? 'NEGATIVE' : 'NEUTRAL');
            $found = collect($topics)->filter(fn ($words) => collect($words)->contains(fn ($word) => str_contains($text, $word)))->keys()->values()->all();
            $result = ['sentiment' => $sentiment, 'confidence' => min(.99, .55 + abs($pos - $neg) * .1), 'topics' => $found, 'summary' => mb_substr($review->content, 0, 255)];
            $model = 'rules-v1';
            if ($this->openai->configured()) {
                try {
                    $result = $this->openai->analyzeReview($review->content, (int) $review->rating);
                    $model = (string) config('services.openai.model');
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            AiReviewAnalysis::updateOrCreate(['review_id' => $review->id], $result + ['model_version' => $model]);
        }
        $analyses = AiReviewAnalysis::all();
        return ['total' => $analyses->count(), 'sentiments' => $analyses->countBy('sentiment')->all(), 'top_issues' => $analyses->where('sentiment', 'NEGATIVE')->pluck('topics')->flatten()->countBy()->sortDesc()->take(5)->all()];
    }
}

<?php

namespace App\Services;

use App\Models\Court;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiRecommendationService
{
    public function recommend(User $user, array $filters = []): array
    {
        $bookings = $user->bookings()
            ->whereNotIn('status', ['CANCELLED', 'EXPIRED'])
            ->with(['bookingDetails.court'])
            ->latest()
            ->limit(100)
            ->get();
        $history = $bookings->pluck('bookingDetails')->flatten();
        $courtFrequency = $history->countBy('court_id');
        $slotFrequency = $history->countBy('time_slot_id')->sortDesc();
        $favoriteIds = $user->favorites()->pluck('court_id')->flip();
        $preferredPrice = $this->median($history->pluck('price')->filter()->map(fn ($price) => (float) $price));
        $preferredAreas = $this->preferredAreas($user, $history, $filters['area'] ?? null);
        $activePromotions = Promotion::query()->where('status', 'ACTIVE')
            ->where('start_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->get(['id', 'title']);
        $hasSignals = $history->isNotEmpty() || $favoriteIds->isNotEmpty() || $preferredAreas !== []
            || isset($filters['max_price']) || isset($filters['time_slot_id']);

        $courts = Court::query()
            ->where('status', 'ACTIVE')
            ->where('operational_status', 'AVAILABLE')
            ->with(['courtType', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'), 'prices' => fn ($query) => $query->where('status', 'ACTIVE'), 'reviews' => fn ($query) => $query->where('status', 'APPROVED')])
            ->get();

        $items = $courts->map(function (Court $court) use ($filters, $courtFrequency, $slotFrequency, $favoriteIds, $preferredPrice, $preferredAreas, $activePromotions, $history) {
            $score = 10.0;
            $reasons = [];
            $breakdown = ['history' => 0, 'time' => 0, 'price' => 0, 'area' => 0, 'rating' => 0, 'promotion' => 0];
            $booked = (int) ($courtFrequency[$court->id] ?? 0);

            if ($favoriteIds->has($court->id)) {
                $breakdown['history'] += 12;
                $reasons[] = 'Có trong danh sách yêu thích';
            }
            if ($booked > 0) {
                $breakdown['history'] += min(18, 8 + $booked * 2);
                $reasons[] = "Bạn đã đặt sân này {$booked} lần";
            }

            $targetSlotId = isset($filters['time_slot_id']) ? (int) $filters['time_slot_id'] : (int) ($slotFrequency->keys()->first() ?? 0);
            if ($targetSlotId && $court->prices->contains('time_slot_id', $targetSlotId)) {
                $breakdown['time'] = 15;
                $reasons[] = 'Có giá cho khung giờ bạn thường chơi';
            }

            $price = $court->prices->min(fn ($item) => (float) $item->price);
            $targetPrice = isset($filters['max_price']) ? (float) $filters['max_price'] : $preferredPrice;
            if ($price !== null && $targetPrice !== null && $targetPrice > 0) {
                $distance = abs((float) $price - $targetPrice) / $targetPrice;
                $breakdown['price'] = (int) round(max(0, 20 * (1 - min(1, $distance))));
                if ((float) $price <= $targetPrice) {
                    $reasons[] = isset($filters['max_price']) ? 'Trong ngân sách đã chọn' : 'Gần mức giá bạn thường đặt';
                }
            }

            if ($this->matchesArea((string) $court->address, $preferredAreas)) {
                $breakdown['area'] = 15;
                $reasons[] = 'Phù hợp khu vực bạn hay đặt';
            }

            $rating = round((float) $court->reviews->avg('rating'), 1);
            if ($rating > 0) {
                $breakdown['rating'] = (int) round(min(15, $rating * 3));
                if ($rating >= 4) {
                    $reasons[] = "Được đánh giá cao {$rating}/5";
                }
            }

            if ($activePromotions->isNotEmpty()) {
                $breakdown['promotion'] = 5;
                $reasons[] = 'Đang có chương trình khuyến mãi';
            }

            $score += array_sum($breakdown);
            if ($history->isEmpty() && $reasons === []) {
                $reasons[] = 'Sân đang hoạt động và sẵn sàng nhận lịch';
            }

            return [
                'court_id' => $court->id,
                'name' => $court->name,
                'address' => $court->address,
                'court_type' => $court->courtType?->name,
                'image_url' => $court->images->first()?->url,
                'price_from' => $price === null ? null : (float) $price,
                'rating' => $rating ?: null,
                'match_percent' => (int) min(100, round($score)),
                'reasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
                'score_breakdown' => $breakdown,
                'promotion_titles' => $activePromotions->pluck('title')->take(2)->values()->all(),
                'url' => route('courts.show', $court),
            ];
        })->sortByDesc('match_percent')->values()->take(min(5, (int) ($filters['limit'] ?? 5)));

        return [
            'data_sufficient' => $hasSignals,
            'profile' => [
                'booking_samples' => $history->count(),
                'preferred_price' => $preferredPrice,
                'preferred_slot_id' => $slotFrequency->keys()->first(),
                'preferred_areas' => $preferredAreas,
            ],
            'recommendations' => $items->all(),
        ];
    }

    private function median(Collection $values): ?float
    {
        $values = $values->sort()->values();
        if ($values->isEmpty()) {
            return null;
        }
        $middle = intdiv($values->count(), 2);

        return $values->count() % 2 ? (float) $values[$middle] : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    private function preferredAreas(User $user, Collection $history, ?string $filterArea): array
    {
        $addresses = $history->pluck('court.address')->filter()->push($user->address)->filter();
        if (filled($filterArea)) {
            $addresses->prepend($filterArea);
        }

        return $addresses->flatMap(fn ($address) => explode(',', Str::lower(Str::ascii((string) $address))))
            ->map(fn ($part) => trim($part))->filter(fn ($part) => mb_strlen($part) >= 3)
            ->countBy()->sortDesc()->keys()->take(5)->values()->all();
    }

    private function matchesArea(string $address, array $areas): bool
    {
        $address = Str::lower(Str::ascii($address));

        return collect($areas)->contains(fn ($area) => str_contains($address, $area));
    }
}

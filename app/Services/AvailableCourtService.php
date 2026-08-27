<?php

namespace App\Services;

use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AvailableCourtService
{
    public function __construct(private readonly CourtAvailabilityService $availability) {}

    public function findByDate(Carbon $date, ?int $hour = null, bool $exactHour = false, ?string $area = null, ?float $maxPrice = null): array
    {
        if ($date->lt(today())) {
            return [
                'reply' => 'Ngày bạn chọn đã qua. Vui lòng chọn từ hôm nay trở đi.',
                'intent' => 'find_available_courts',
                'date' => $date->toDateString(),
                'court_count' => 0,
                'source' => 'live_database',
            ];
        }

        $courts = Court::query()->with(['images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order')])
            ->where('status', 'ACTIVE')->where('operational_status', 'AVAILABLE')
            ->when(filled($area), fn ($query) => $query->where('address', 'like', '%'.$area.'%'))
            ->orderByDesc('is_featured')->get()->map(function (Court $court) use ($date, $hour, $exactHour) {
                $slots = collect($this->availability->getAvailabilityByDate($court->id, $date))
                    ->where('status', CourtAvailabilityService::STATUS_AVAILABLE)
                    ->when($hour !== null, fn ($items) => $items->filter(function ($slot) use ($hour, $exactHour) {
                        $startHour = (int) substr($slot['start_time'], 0, 2);

                        return $exactHour ? $startHour === $hour : $startHour >= $hour;
                    }))
                    ->values();

                return ['court' => $court, 'slots' => $slots];
            })->map(function ($item) use ($maxPrice) {
                if ($maxPrice === null) {
                    return $item;
                }
                $item['slots'] = $item['slots']->filter(function ($slot) use ($item, $maxPrice) {
                    $price = $item['court']->prices()->where('time_slot_id', $slot['time_slot_id'])->where('status', 'ACTIVE')->value('price');

                    return $price !== null && (float) $price <= $maxPrice;
                })->values();

                return $item;
            })->filter(fn ($item) => $item['slots']->isNotEmpty())->values();

        if ($courts->isEmpty()) {
            return [
                'reply' => 'Không tìm thấy sân còn trống vào ngày '.$date->format('d/m/Y').'. Bạn thử chọn ngày khác nhé.',
                'intent' => 'find_available_courts',
                'date' => $date->toDateString(),
                'court_count' => 0,
                'source' => 'live_database',
            ];
        }

        $lines = $courts->take(5)->map(function ($item) {
            $names = $item['slots']->take(4)->pluck('name')->join(', ');
            $more = $item['slots']->count() > 4 ? ' và '.($item['slots']->count() - 4).' khung giờ khác' : '';

            return '• '.$item['court']->name.': '.$names.$more;
        });

        $slotChoices = $courts->take(5)->flatMap(function ($item) {
            return $item['slots']->map(fn ($slot) => (object) [
                'court_id' => $item['court']->id,
                'court_name' => $item['court']->name,
                'time_slot_id' => $slot['time_slot_id'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'price' => $item['court']->prices()->where('time_slot_id', $slot['time_slot_id'])->where('status', 'ACTIVE')->value('price'),
            ]);
        })->take(15);

        $buttons = $this->makeSlotChoices($slotChoices, $date->toDateString());

        return [
            'reply' => 'Các sân còn trống ngày '.$date->format('d/m/Y').":\n".$lines->join("\n")."\nBạn mở trang Đặt sân để xác nhận tình trạng theo thời gian thực.",
            'intent' => 'find_available_courts',
            'date' => $date->toDateString(),
            'court_count' => $courts->count(),
            'source' => 'live_database',
            'buttons' => $buttons,
            'cards' => $courts->take(5)->map(fn ($item) => [
                'type' => 'court',
                'title' => $item['court']->name,
                'subtitle' => $item['court']->address,
                'meta' => $item['slots']->take(4)->pluck('name')->join(', '),
                'price_from' => ($price = $item['court']->prices()->where('status', 'ACTIVE')->min('price')) === null ? null : (float) $price,
                'image_url' => $item['court']->images->first()?->url,
                'rating' => $item['court']->getAverageRating() === null ? null : round((float) $item['court']->getAverageRating(), 1),
                'slots' => collect($buttons)->where('court_id', $item['court']->id)->take(6)->values()->all(),
                'url' => route('courts.show', $item['court']),
            ])->values()->all(),
        ];
    }

    private function makeSlotChoices($slots, string $date): array
    {
        $storedChoices = [];
        $buttons = [];

        foreach ($slots as $slot) {
            $choiceId = (string) Str::uuid();
            $storedChoices[$choiceId] = [
                'court_id' => $slot->court_id,
                'court_name' => $slot->court_name,
                'time_slot_id' => $slot->time_slot_id,
                'date' => $date,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'price' => $slot->price === null ? null : (float) $slot->price,
            ];
            $buttons[] = [
                'id' => $choiceId,
                'action' => 'select_slot',
                'label' => $slot->court_name.': '.substr($slot->start_time, 0, 5).' - '.substr($slot->end_time, 0, 5),
                'court_id' => $slot->court_id,
                'date' => $date,
                'start_time' => substr($slot->start_time, 0, 5),
                'end_time' => substr($slot->end_time, 0, 5),
                'price' => $slot->price === null ? null : (float) $slot->price,
            ];
        }

        session(['chatbot.slot_choices' => $storedChoices]);

        return $buttons;
    }
}

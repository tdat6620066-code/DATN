<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SmashZoneToolRegistry
{
    public function __construct(
        private readonly AvailableCourtService $availability,
        private readonly SmartChatService $smartChat,
    ) {}

    public function definitions(): array
    {
        return [
            $this->tool('search_courts', 'Tìm tối đa 5 sân đang hoạt động theo khu vực và ngân sách.', [
                'area' => ['type' => ['string', 'null']],
                'max_price' => ['type' => ['number', 'null'], 'minimum' => 0],
            ]),
            $this->tool('check_availability', 'Kiểm tra slot trống thật theo ngày, giờ, khu vực và giá tối đa.', [
                'date' => ['type' => 'string'],
                'hour' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 23],
                'area' => ['type' => ['string', 'null']],
                'max_price' => ['type' => ['number', 'null'], 'minimum' => 0],
            ]),
            $this->tool('get_price', 'Lấy bảng giá thật của một sân theo ID hoặc tên.', [
                'court_id' => ['type' => ['integer', 'null']],
                'court_name' => ['type' => ['string', 'null']],
            ]),
            $this->tool('get_promotions', 'Lấy các khuyến mãi đang có hiệu lực.', []),
            $this->tool('get_my_booking', 'Lấy booking của chính người dùng hiện tại; không thể lấy booking người khác.', [
                'booking_code' => ['type' => ['string', 'null']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            ]),
            $this->tool('prepare_booking', 'Chuẩn bị slot đã tìm để hỏi xác nhận. Tool này không tạo booking.', [
                'choice_id' => ['type' => 'string'],
            ]),
        ];
    }

    public function securityContract(): array
    {
        return [
            'identity_source' => 'authenticated_user',
            'model_can_supply_user_id' => false,
            'write_tools' => [],
            'confirmation_only_tools' => ['prepare_booking'],
        ];
    }

    public function execute(string $name, array $arguments, User $user): array
    {
        return match ($name) {
            'search_courts' => $this->searchCourts($arguments),
            'check_availability' => $this->checkAvailability($arguments),
            'get_price' => $this->getPrice($arguments),
            'get_promotions' => $this->getPromotions(),
            'get_my_booking' => $this->getMyBooking($arguments, $user),
            'prepare_booking' => $this->prepareBooking($arguments),
            default => throw ValidationException::withMessages(['tool' => 'Tool không được phép.']),
        };
    }

    private function searchCourts(array $arguments): array
    {
        $data = $this->validate($arguments, ['area' => ['nullable', 'string', 'max:100'], 'max_price' => ['nullable', 'numeric', 'min:0']]);
        $courts = Court::query()->where('status', 'ACTIVE')->where('operational_status', 'AVAILABLE')
            ->with(['images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('sort_order'), 'prices' => fn ($query) => $query->where('status', 'ACTIVE')])
            ->when(filled($data['area'] ?? null), fn ($query) => $query->where('address', 'like', '%'.$data['area'].'%'))
            ->get()->filter(fn (Court $court) => ! isset($data['max_price']) || ($court->prices->min('price') !== null && (float) $court->prices->min('price') <= (float) $data['max_price']))
            ->take(5)->values();

        return [
            'ok' => true,
            'courts' => $courts->map(fn (Court $court) => [
                'court_id' => $court->id, 'name' => $court->name, 'address' => $court->address,
                'price_from' => $court->prices->min('price') === null ? null : (float) $court->prices->min('price'),
                'image_url' => $court->images->first()?->url, 'url' => route('courts.show', $court),
            ])->all(),
        ];
    }

    private function checkAvailability(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hour' => ['nullable', 'integer', 'between:0,23'],
            'area' => ['nullable', 'string', 'max:100'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $result = $this->availability->findByDate(
            Carbon::parse($data['date']), $data['hour'] ?? null, isset($data['hour']),
            $data['area'] ?? null, isset($data['max_price']) ? (float) $data['max_price'] : null,
        );

        return [
            'ok' => true,
            'message' => $result['reply'],
            'court_count' => $result['court_count'] ?? 0,
            'choices' => collect($result['buttons'] ?? [])->map(fn ($button) => [
                'choice_id' => $button['id'] ?? null,
                'label' => $button['label'] ?? null,
            ])->all(),
            'cards' => $result['cards'] ?? [],
            'buttons' => $result['buttons'] ?? [],
        ];
    }

    private function getPrice(array $arguments): array
    {
        $data = $this->validate($arguments, ['court_id' => ['nullable', 'integer'], 'court_name' => ['nullable', 'string', 'max:150']]);
        if (! isset($data['court_id']) && blank($data['court_name'] ?? null)) {
            throw ValidationException::withMessages(['court' => 'Cần court_id hoặc court_name.']);
        }
        $court = Court::query()->where('status', 'ACTIVE')
            ->when(isset($data['court_id']), fn ($query) => $query->whereKey($data['court_id']))
            ->when(! isset($data['court_id']), fn ($query) => $query->where('name', 'like', '%'.$data['court_name'].'%'))
            ->with(['prices.timeSlot'])->first();
        if (! $court) {
            return ['ok' => false, 'error' => 'Không tìm thấy sân.'];
        }

        return ['ok' => true, 'court' => $court->name, 'prices' => $court->prices->where('status', 'ACTIVE')->take(20)->map(fn ($price) => [
            'time_slot' => $price->timeSlot?->name, 'day_type' => $price->day_type, 'price' => (float) $price->price,
        ])->values()->all()];
    }

    private function getPromotions(): array
    {
        return ['ok' => true, 'promotions' => Promotion::query()->where('status', 'ACTIVE')->where('start_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->take(10)->get(['id', 'title', 'description', 'end_at'])->toArray()];
    }

    private function getMyBooking(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['booking_code' => ['nullable', 'string', 'max:50'], 'limit' => ['required', 'integer', 'between:1,5']]);
        $bookings = Booking::query()->where('user_id', $user->id)
            ->when(filled($data['booking_code'] ?? null), fn ($query) => $query->where('booking_code', strtoupper($data['booking_code'])))
            ->with(['bookingDetails.court', 'bookingDetails.timeSlot'])->latest()->limit($data['limit'])->get();

        return ['ok' => true, 'bookings' => $bookings->map(fn (Booking $booking) => [
            'booking_code' => $booking->booking_code, 'status' => $booking->status, 'payment_status' => $booking->payment_status,
            'total_amount' => (float) $booking->total_amount,
            'schedule' => $booking->bookingDetails->map(fn ($detail) => [
                'court' => $detail->court?->name, 'date' => $detail->booking_date?->toDateString(), 'time' => $detail->timeSlot?->name,
            ])->all(),
        ])->all()];
    }

    private function prepareBooking(array $arguments): array
    {
        $data = $this->validate($arguments, ['choice_id' => ['required', 'uuid']]);
        $result = $this->smartChat->selectSlot($data['choice_id']);

        return ['ok' => ($result['matched'] ?? false) === true, 'message' => $result['reply'], 'selected_slot' => $result['selected_slot'] ?? null, 'buttons' => $result['choices'] ?? []];
    }

    private function validate(array $arguments, array $rules): array
    {
        return Validator::make($arguments, $rules)->validate();
    }

    private function tool(string $name, string $description, array $properties): array
    {
        return [
            'type' => 'function', 'name' => $name, 'description' => $description, 'strict' => true,
            'parameters' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false],
        ];
    }
}

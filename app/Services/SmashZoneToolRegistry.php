<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Notification;
use App\Models\Promotion;
use App\Models\Review;
use App\Models\ServiceItem;
use App\Models\User;
use App\Models\Voucher;
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
            $this->tool('search_courts', 'Tìm tối đa 5 sân đang hoạt động. area và max_price là TUỲ CHỌN: gửi null khi khách không nêu, đừng hỏi lại khách chỉ để lấy 2 trường này.', [
                'area' => ['type' => ['string', 'null']],
                'max_price' => ['type' => ['number', 'null'], 'minimum' => 0],
            ]),
            $this->tool('check_availability', 'Kiểm tra slot trống thật. Chỉ date là bắt buộc; hour, area, max_price là TUỲ CHỌN — gửi null nếu khách không nêu. Đã biết ngày thì gọi ngay, không hỏi thêm.', [
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
            $this->tool('get_vouchers', 'Lấy danh sách voucher/mã giảm giá còn hiệu lực và còn lượt dùng.', [
                'min_order_amount' => ['type' => ['number', 'null'], 'minimum' => 0],
            ]),
            $this->tool('get_services', 'Lấy danh sách dịch vụ cho thuê thêm đang bán (nước uống, thuê vợt, bóng...).', []),
            $this->tool('get_court_info', 'Lấy thông tin chi tiết một sân: địa chỉ, giờ mở/đóng cửa, tiện ích, rating, giá từ.', [
                'court_id' => ['type' => ['integer', 'null']],
                'court_name' => ['type' => ['string', 'null']],
            ]),
            $this->tool('get_court_reviews', 'Lấy đánh giá đã kiểm duyệt của một sân để tư vấn chất lượng.', [
                'court_id' => ['type' => ['integer', 'null']],
                'court_name' => ['type' => ['string', 'null']],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            ]),
            $this->tool('get_my_notifications', 'Lấy thông báo của chính người dùng đang đăng nhập (mới nhất trước).', [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                'unread_only' => ['type' => 'boolean'],
            ]),
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
            'identity_source' => 'authenticated_user_or_public_guest',
            'model_can_supply_user_id' => false,
            'write_tools' => [],
            'confirmation_only_tools' => ['prepare_booking'],
        ];
    }

    public function execute(string $name, array $arguments, ?User $user): array
    {
        return match ($name) {
            'search_courts' => $this->searchCourts($arguments),
            'check_availability' => $this->checkAvailability($arguments),
            'get_price' => $this->getPrice($arguments),
            'get_promotions' => $this->getPromotions(),
            'get_vouchers' => $this->getVouchers($arguments),
            'get_services' => $this->getServices(),
            'get_court_info' => $this->getCourtInfo($arguments),
            'get_court_reviews' => $this->getCourtReviews($arguments),
            'get_my_notifications' => $this->getMyNotifications($arguments, $user),
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

    private function getVouchers(array $arguments): array
    {
        $data = $this->validate($arguments, ['min_order_amount' => ['nullable', 'numeric', 'min:0']]);

        $vouchers = Voucher::query()
            ->where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->when(isset($data['min_order_amount']), fn ($query) => $query->where('min_order_amount', '<=', $data['min_order_amount']))
            ->orderByDesc('discount_value')
            ->take(10)
            ->get(['code', 'name', 'discount_type', 'discount_value', 'min_order_amount', 'max_discount', 'end_at']);

        return ['ok' => true, 'vouchers' => $vouchers->map(fn (Voucher $voucher) => [
            'code' => $voucher->code,
            'name' => $voucher->name,
            'discount_type' => $voucher->discount_type,
            'discount_value' => (float) $voucher->discount_value,
            'min_order_amount' => (float) $voucher->min_order_amount,
            'max_discount' => $voucher->max_discount === null ? null : (float) $voucher->max_discount,
            'end_at' => $voucher->end_at?->toDateString(),
        ])->all()];
    }

    private function getServices(): array
    {
        $services = ServiceItem::query()->where('is_active', true)->orderBy('category')->take(20)
            ->get(['code', 'name', 'category', 'price', 'stock']);

        return ['ok' => true, 'services' => $services->map(fn (ServiceItem $service) => [
            'code' => $service->code,
            'name' => $service->name,
            'category' => $service->category,
            'price' => (float) $service->price,
            'in_stock' => (int) $service->stock > 0,
        ])->all()];
    }

    private function getCourtInfo(array $arguments): array
    {
        $court = $this->resolveCourt($arguments);
        if (! $court) {
            return ['ok' => false, 'error' => 'Không tìm thấy sân.'];
        }

        $court->load(['amenities:id,name', 'courtType:id,name']);

        return ['ok' => true, 'court' => [
            'court_id' => $court->id,
            'name' => $court->name,
            'type' => $court->courtType?->name,
            'address' => $court->address,
            'opening_time' => $court->opening_time,
            'closing_time' => $court->closing_time,
            'availability_status' => $court->availability_status,
            'amenities' => $court->amenities->pluck('name')->all(),
            'rating' => $court->getAverageRating(),
            'review_count' => $court->getReviewCount(),
            'price_from' => ($price = $court->prices()->where('status', 'ACTIVE')->min('price')) === null ? null : (float) $price,
            'url' => route('courts.show', $court),
        ]];
    }

    private function getCourtReviews(array $arguments): array
    {
        $court = $this->resolveCourt($arguments);
        if (! $court) {
            return ['ok' => false, 'error' => 'Không tìm thấy sân.'];
        }
        $data = $this->validate($arguments, ['limit' => ['required', 'integer', 'between:1,10']]);

        $reviews = Review::query()->where('court_id', $court->id)->where('status', 'APPROVED')
            ->latest()->take($data['limit'])->get(['rating', 'content', 'created_at']);

        return [
            'ok' => true,
            'court' => $court->name,
            'rating' => $court->getAverageRating(),
            'review_count' => $court->getReviewCount(),
            'reviews' => $reviews->map(fn (Review $review) => [
                'rating' => $review->rating,
                'content' => $review->content,
                'created_at' => $review->created_at?->toDateString(),
            ])->all(),
        ];
    }

    private function getMyNotifications(array $arguments, ?User $user): array
    {
        if (! $user) {
            return ['ok' => false, 'error' => 'Bạn cần đăng nhập để xem thông báo cá nhân.'];
        }
        $data = $this->validate($arguments, [
            'limit' => ['required', 'integer', 'between:1,10'],
            'unread_only' => ['required', 'boolean'],
        ]);

        $query = Notification::query()->where('user_id', $user->id);
        $unread = (clone $query)->where('is_read', false)->count();
        $notifications = $query
            ->when($data['unread_only'], fn ($builder) => $builder->where('is_read', false))
            ->latest()->take($data['limit'])->get(['title', 'content', 'type', 'action_url', 'is_read', 'created_at']);

        return ['ok' => true, 'unread_count' => $unread, 'notifications' => $notifications->map(fn (Notification $notification) => [
            'title' => $notification->title,
            'content' => $notification->content,
            'type' => $notification->type,
            'action_url' => $notification->action_url,
            'is_read' => (bool) $notification->is_read,
            'created_at' => $notification->created_at?->toDateString(),
        ])->all()];
    }

    private function getMyBooking(array $arguments, ?User $user): array
    {
        if (! $user) {
            return ['ok' => false, 'error' => 'Bạn cần đăng nhập để xem booking của mình.'];
        }
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
            'parameters' => [
                'type' => 'object',
                // JSON schema yêu cầu `properties` là object. Mảng rỗng phải
                // encode thành {} chứ không phải [] (Groq và OpenAI đều từ chối []).
                'properties' => $properties === [] ? new \stdClass() : $properties,
                'required' => array_keys($properties),
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * Resolve sân theo court_id hoặc court_name; trả null nếu không tìm thấy.
     */
    private function resolveCourt(array $arguments): ?Court
    {
        $data = $this->validate($arguments, [
            'court_id' => ['nullable', 'integer'],
            'court_name' => ['nullable', 'string', 'max:150'],
        ]);

        if (! isset($data['court_id']) && blank($data['court_name'] ?? null)) {
            throw ValidationException::withMessages(['court' => 'Cần court_id hoặc court_name.']);
        }

        return Court::query()->where('status', 'ACTIVE')
            ->when(isset($data['court_id']), fn ($query) => $query->whereKey($data['court_id']))
            ->when(! isset($data['court_id']), fn ($query) => $query->where('name', 'like', '%'.$data['court_name'].'%'))
            ->first();
    }
}

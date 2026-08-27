<?php

namespace App\Services;

use App\Models\Court;
use App\Models\Promotion;
use App\Models\ServiceItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DatabaseChatService
{
    public function answerClassified(array $classification, string $message, User $user): ?array
    {
        $intent = $classification['intent'] ?? 'FAQ';
        $bookingCode = $classification['booking_code'] ?? null;
        $searchText = trim($message.' '.($classification['area'] ?? '').' '.($classification['court_name'] ?? '').' '.($classification['service_name'] ?? ''));

        return match ($intent) {
            'FIND_COURT' => filled($classification['area'] ?? null)
                ? $this->searchCourts('tim san o '.$classification['area'])
                : $this->searchCourts($this->normalize($searchText)),
            'COURT_PRICE' => $this->prices($this->normalize($searchText)),
            'BOOKING_STATUS' => $bookingCode
                ? $this->bookingByCode($user, strtoupper($bookingCode), $this->normalize($message))
                : (($classification['limit'] ?? null) ? $this->bookingHistory($user) : $this->latestBookingStatus($user)),
            'PAYMENT_STATUS' => $bookingCode
                ? $this->bookingByCode($user, strtoupper($bookingCode), 'thanh toan')
                : $this->paymentStatus($user),
            'CANCEL_BOOKING' => $this->cancelPrompt($user, $bookingCode),
            'PROMOTION' => $this->promotions(),
            'SERVICE' => $this->serviceItems($this->normalize($searchText)),
            default => null,
        };
    }

    public function answer(string $message, User $user): ?array
    {
        $text = $this->normalize($message);

        if (preg_match('/\b(bk[0-9a-z]+)\b/i', $text, $matches)) {
            return $this->bookingByCode($user, strtoupper($matches[1]), $text);
        }

        if ($this->contains($text, ['5 don gan nhat', 'nam don gan nhat'])) {
            return $this->bookingHistory($user);
        }

        if ($this->contains($text, ['toi muon huy san', 'toi muon huy don', 'huy booking'])) {
            return $this->cancelPrompt($user);
        }

        if ($this->contains($text, ['quen mat khau', 'khong nho mat khau'])) {
            return $this->result('Bạn mở trang Đăng nhập, chọn “Quên mật khẩu”, nhập email tài khoản và làm theo hướng dẫn đặt lại mật khẩu.', 'ACCOUNT_SUPPORT');
        }

        if ($this->contains($text, ['dat san nhu the nao', 'cach dat san', 'huong dan dat san'])) {
            return $this->result('Bạn vào Đặt sân, chọn sân, ngày và khung giờ còn trống, kiểm tra thông tin rồi xác nhận. Booking chỉ được tạo sau bước xác nhận.', 'BOOKING_GUIDE');
        }

        if ($this->contains($text, ['toi da thanh toan chua', 'kiem tra thanh toan', 'trang thai thanh toan'])) {
            return $this->paymentStatus($user);
        }

        if ($this->contains($text, ['kiem tra don dat san', 'don cua toi da duoc xac nhan', 'don da xac nhan chua'])) {
            return $this->latestBookingStatus($user);
        }

        if ($this->contains($text, ['lich su dat san', 'don dat san cua toi', 'booking cua toi'])) {
            return $this->bookingHistory($user);
        }

        if (! $this->contains($text, ['ma giam gia khong dung', 'ma giam gia bi loi', 'ma het han'])
            && $this->contains($text, ['khuyen mai', 'giam gia', 'uu dai', 'ma giam', 'voucher'])) {
            return $this->promotions();
        }

        if ($this->contains($text, ['dich vu', 'phu kien', 'thue vot', 'thue giay', 'ban cau', 'mua cau', 'co cau khong'])) {
            return $this->serviceItems($text);
        }

        if (Str::contains($text, 'san') && Str::contains($text, 'gan toi')) {
            return $this->result('Bạn hãy cho tôi biết khu vực, quận hoặc địa chỉ mong muốn để tìm sân gần nhất. Ví dụ: “Tìm sân ở Cầu Giấy”.', 'ASK_LOCATION');
        }

        if ($this->contains($text, ['tim san cau long', 'tim san o ', 'tim san tai '])) {
            return $this->searchCourts($text);
        }

        if ($this->contains($text, ['dia chi', 'o dau', 'vi tri san'])) {
            return $this->courtDetails($text, 'address');
        }

        if ($this->contains($text, ['gio mo cua', 'may gio mo', 'gio dong cua', 'hoat dong may gio'])) {
            return $this->courtDetails($text, 'hours');
        }

        if (! Str::contains($text, 'muon danh gia')
            && $this->contains($text, ['danh gia', 'rating', 'san tot', 'san nao tot'])) {
            return $this->ratings($text);
        }

        if (! Str::contains($text, 'danh gia')
            && $this->contains($text, ['gia san', 'gia thue', 'bang gia', 'chi phi', 'gia bao nhieu', 'bao nhieu tien'])) {
            return $this->prices($text);
        }

        if (! $this->contains($text, ['san trong', 'gio trong', 'khung gio trong'])
            && $this->contains($text, ['danh sach san', 'co nhung san nao', 'co bao nhieu san', 'tat ca san'])) {
            return $this->courts();
        }

        return null;
    }

    private function courts(): array
    {
        $courts = $this->activeCourts()->get();
        if ($courts->isEmpty()) {
            return $this->result('Hiện chưa có sân nào đang hoạt động.', 'COURT_LIST');
        }

        $lines = $courts->map(fn (Court $court) => '• '.$court->name
            .($court->courtType?->name ? ' ('.$court->courtType->name.')' : '')
            .($court->address ? ' — '.$court->address : ''));

        return $this->result("Hệ thống hiện có {$courts->count()} sân đang hoạt động:\n".$lines->join("\n"), 'COURT_LIST');
    }

    private function prices(string $text): array
    {
        $date = $this->contains($text, ['cuoi tuan', 'thu bay', 'chu nhat'])
            ? (today()->isWeekend() ? today() : today()->next(Carbon::SATURDAY))
            : today();
        $dayType = $date->isWeekend() ? 'WEEKEND' : 'WEEKDAY';
        $courts = $this->matchingCourts($text)->with(['prices' => fn ($query) => $query
            ->where('status', 'ACTIVE')
            ->whereIn('day_type', [$dayType, 'WEEKDAY'])
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date)),
        ])->get();

        $lines = $courts->map(function (Court $court) use ($dayType) {
            $applicable = $court->prices->where('day_type', $dayType);
            if ($applicable->isEmpty() && $dayType !== 'WEEKDAY') {
                $applicable = $court->prices->where('day_type', 'WEEKDAY');
            }
            $prices = $applicable->pluck('price')->map(fn ($price) => (float) $price);
            if ($prices->isEmpty()) {
                return '• '.$court->name.': chưa cập nhật giá';
            }
            $min = number_format($prices->min(), 0, ',', '.').'đ';
            $max = number_format($prices->max(), 0, ',', '.').'đ';

            return '• '.$court->name.': '.($min === $max ? $min : $min.' – '.$max).'/khung giờ';
        });

        return $this->result($lines->isEmpty()
            ? 'Không tìm thấy sân phù hợp trong dữ liệu hiện tại.'
            : 'Bảng giá áp dụng ngày '.$date->format('d/m/Y').":\n".$lines->join("\n"), 'PRICE');
    }

    private function promotions(): array
    {
        $promotions = Promotion::query()->where('status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->orderBy('end_at')->get();

        if ($promotions->isEmpty()) {
            return $this->result('Hiện chưa có chương trình khuyến mãi đang áp dụng.', 'PROMOTION');
        }

        $lines = $promotions->map(fn (Promotion $promotion) => '• '.$promotion->title
            .($promotion->description ? ': '.$promotion->description : '')
            .($promotion->end_at ? ' (đến '.$promotion->end_at->format('d/m/Y H:i').')' : ''));

        return $this->result("Khuyến mãi đang áp dụng:\n".$lines->join("\n"), 'PROMOTION');
    }

    private function courtDetails(string $text, string $field): array
    {
        $courts = $this->matchingCourts($text)->get();
        $lines = $courts->map(function (Court $court) use ($field) {
            if ($field === 'address') {
                return '• '.$court->name.': '.($court->address ?: 'chưa cập nhật địa chỉ');
            }
            $opening = $court->opening_time ? substr($court->opening_time, 0, 5) : null;
            $closing = $court->closing_time ? substr($court->closing_time, 0, 5) : null;

            return '• '.$court->name.': '.($opening && $closing ? $opening.' – '.$closing : 'chưa cập nhật giờ hoạt động');
        });

        return $this->result($lines->isEmpty() ? 'Không tìm thấy sân phù hợp.' : $lines->join("\n"), $field === 'address' ? 'COURT_ADDRESS' : 'OPENING_HOURS');
    }

    private function ratings(string $text): array
    {
        $courts = $this->matchingCourts($text)->withAvg(['reviews' => fn ($q) => $q->where('status', 'APPROVED')], 'rating')
            ->withCount(['reviews' => fn ($q) => $q->where('status', 'APPROVED')])
            ->orderByDesc('reviews_avg_rating')->get();

        $lines = $courts->map(fn (Court $court) => '• '.$court->name.': '
            .($court->reviews_count ? number_format((float) $court->reviews_avg_rating, 1).'/5 ('.$court->reviews_count.' đánh giá)' : 'chưa có đánh giá'));

        return $this->result($lines->isEmpty() ? 'Chưa có dữ liệu đánh giá sân.' : "Đánh giá từ khách hàng:\n".$lines->join("\n"), 'COURT_RATING');
    }

    private function bookingHistory(User $user): array
    {
        $bookings = $user->bookings()->with(['bookingDetails.court:id,name', 'bookingDetails.timeSlot:id,name'])
            ->latest()->limit(5)->get();
        if ($bookings->isEmpty()) {
            return $this->result('Bạn chưa có lịch sử đặt sân.', 'BOOKING_HISTORY');
        }

        $lines = $bookings->map(function ($booking) {
            $details = $booking->bookingDetails->map(fn ($detail) => ($detail->court?->name ?? 'Sân')
                .' ngày '.optional($detail->booking_date)->format('d/m/Y')
                .($detail->timeSlot?->name ? ' '.$detail->timeSlot->name : ''))->join(', ');

            return '• '.$booking->booking_code.' — '.$details.' — '.$booking->status;
        });

        return $this->result("5 đơn gần nhất của bạn:\n".$lines->join("\n"), 'BOOKING_HISTORY');
    }

    private function latestBookingStatus(User $user): array
    {
        $booking = $user->bookings()->latest()->first();
        if (! $booking) {
            return $this->result('Bạn chưa có đơn đặt sân nào.', 'BOOKING_STATUS');
        }

        return $this->result('Đơn gần nhất '.$booking->booking_code.' có trạng thái '.$booking->status
            .' và trạng thái thanh toán '.$booking->payment_status.'.', 'BOOKING_STATUS');
    }

    private function bookingByCode(User $user, string $code, string $text): array
    {
        $booking = $user->bookings()->with('payment')->where('booking_code', $code)->first();
        if (! $booking) {
            return $this->result('Không tìm thấy booking '.$code.' thuộc tài khoản của bạn.', 'BOOKING_STATUS');
        }

        if ($this->contains($text, ['thanh toan', 'da tra tien'])) {
            $status = $booking->payment?->status ?? $booking->payment_status;

            return $this->result('Booking '.$code.' có trạng thái thanh toán '.$status.'.', 'PAYMENT_STATUS');
        }

        return $this->result('Booking '.$code.' có trạng thái '.$booking->status.' và thanh toán '.$booking->payment_status.'.', 'BOOKING_STATUS');
    }

    private function cancelPrompt(User $user, ?string $bookingCode = null): array
    {
        $booking = $user->bookings()->whereIn('status', ['PENDING_PAYMENT', 'CONFIRMED'])
            ->when($bookingCode, fn ($query) => $query->where('booking_code', strtoupper($bookingCode)))
            ->latest()->first();
        if (! $booking) {
            return $this->result('Bạn chưa có booking nào có thể hủy.', 'BOOKING_STATUS');
        }

        session(['chatbot.pending_cancel_booking_id' => $booking->id]);
        $result = $this->result('Bạn có chắc muốn hủy booking '.$booking->booking_code.' không? Thao tác chỉ được thực hiện sau khi bạn xác nhận.', 'BOOKING_STATUS');
        $result['buttons'] = [
            ['action' => 'confirm_cancel', 'label' => 'Xác nhận hủy'],
            ['action' => 'abort_cancel', 'label' => 'Không hủy'],
        ];

        return $result;
    }

    private function paymentStatus(User $user): array
    {
        $booking = $user->bookings()->with('payment')->latest()->first();
        if (! $booking) {
            return $this->result('Bạn chưa có đơn đặt sân để kiểm tra thanh toán.', 'PAYMENT_STATUS');
        }

        $status = $booking->payment?->status ?? $booking->payment_status ?? 'chưa có giao dịch';
        $paidAt = $booking->payment?->paid_at?->format('d/m/Y H:i');

        return $this->result('Thanh toán của đơn '.$booking->booking_code.' đang ở trạng thái '.$status
            .($paidAt ? ', ghi nhận lúc '.$paidAt : '').'.', 'PAYMENT_STATUS');
    }

    private function serviceItems(string $text): array
    {
        $codes = [];
        if ($this->contains($text, ['thue vot'])) {
            $codes[] = 'RACKET';
        }
        if ($this->contains($text, ['thue giay'])) {
            $codes[] = 'SHOES';
        }
        if ($this->contains($text, ['ban cau', 'mua cau', 'co cau khong'])) {
            $codes[] = 'SHUTTLE';
        }

        $items = ServiceItem::query()->where('is_active', true)
            ->when($codes !== [], fn ($query) => $query->whereIn('code', $codes))
            ->orderBy('name')
            ->get();
        foreach (['yonex', 'lining', 'victor'] as $brand) {
            if (Str::contains($text, $brand)) {
                $items = $items->filter(fn (ServiceItem $item) => Str::contains($this->normalize($item->name), $brand))->values();
            }
        }
        if ($items->isEmpty()) {
            return $this->result('Hiện chưa có dịch vụ hoặc sản phẩm này trong hệ thống.', 'SERVICE_ITEM');
        }

        $lines = $items->map(fn (ServiceItem $item) => '• '.$item->name.': '
            .number_format((float) $item->price, 0, ',', '.').'đ'
            .($item->stock === null ? '' : ' — còn '.$item->stock));

        return $this->result($lines->join("\n"), 'SERVICE_ITEM');
    }

    private function searchCourts(string $text): array
    {
        $location = null;
        if (preg_match('/(?:tim san (?:o|tai))\s+(.+)$/', $text, $matches)) {
            $location = trim($matches[1]);
        }

        $courts = $this->activeCourts()->get();
        if ($location) {
            $courts = $courts->filter(fn (Court $court) => Str::contains(
                $this->normalize((string) $court->address),
                $location
            ))->values();
        }

        if ($courts->isEmpty()) {
            return $this->result('Không tìm thấy sân đang hoạt động'.($location ? ' tại '.$location : '').' trong dữ liệu hiện tại.', 'COURT_SEARCH');
        }

        $lines = $courts->map(fn (Court $court) => '• '.$court->name.($court->address ? ' — '.$court->address : ''));

        return $this->result(($location ? 'Các sân tìm thấy tại '.$location : 'Các sân cầu lông đang hoạt động').":\n".$lines->join("\n"), 'COURT_SEARCH');
    }

    private function matchingCourts(string $text)
    {
        $query = $this->activeCourts();
        $names = Court::query()->where('status', 'ACTIVE')->pluck('name');
        $matched = $names->first(fn ($name) => Str::contains($text, $this->normalize($name)));

        return $matched ? $query->where('name', $matched) : $query;
    }

    private function activeCourts()
    {
        return Court::query()->where('status', 'ACTIVE')->where('operational_status', 'AVAILABLE')
            ->with('courtType:id,name')->orderByDesc('is_featured')->orderBy('name');
    }

    private function result(string $answer, string $intent): array
    {
        return [
            'understood' => true,
            'answer' => $answer,
            'intent' => $intent,
            'source' => 'live_database',
            'verified_at' => now()->toIso8601String(),
            'suggestions' => ['Tìm sân trống ngày mai', 'Xem bảng giá sân', 'Có khuyến mãi nào?'],
        ];
    }

    private function contains(string $text, array $terms): bool
    {
        return Str::contains($text, $terms);
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($text))) ?? '');
    }
}

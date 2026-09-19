<?php

namespace App\Services;

use App\Models\{Booking, BookingAuditLog, BookingService as ServiceLine, Court, ServiceItem, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingOperationsService
{
    public function timing(Booking $booking): array
    {
        $details = $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->with('timeSlot')->get();
        $starts = $details->map(fn ($d) => Carbon::parse($d->booking_date->toDateString().' '.$d->timeSlot->start_time))->sort();
        $ends = $details->map(fn ($d) => Carbon::parse($d->booking_date->toDateString().' '.$d->timeSlot->end_time))->sort();
        return ['start' => $starts->first(), 'end' => $ends->last()];
    }

    public function assertCheckinReady(Booking $booking): void
    {
        if ($booking->status !== 'CONFIRMED') throw new \DomainException('Chỉ đơn đã xác nhận và chưa nhận sân mới được nhận sân.');
        if (! in_array($booking->payment_status, ['PAID', 'PARTIALLY_REFUNDED']) || ! in_array($booking->payment?->status, ['PAID', 'PARTIALLY_REFUNDED'])) throw new \DomainException('Khách chưa hoàn tất thanh toán tiền sân.');
        ['start' => $start, 'end' => $end] = $this->timing($booking);
        if (! $start || ! $start->isToday()) throw new \DomainException('Đơn không có lịch chơi hôm nay.');
        if (now()->lt($start->copy()->subMinutes(config('booking.checkin_early_minutes')))) throw new \DomainException('Chỉ được nhận sân trước giờ chơi tối đa '.config('booking.checkin_early_minutes').' phút.');
        if (now()->gte($end)) throw new \DomainException('Đã hết giờ chơi của đơn này.');
        if (($booking->extension_of_id || $booking->extended_from_id) && now()->lt($start)) throw new \DomainException('Chỉ check-in gia hạn khi đến giờ sử dụng sân.');
    }

    public function checkIn(Booking $booking, User $actor): Booking
    {
        $this->permission($actor, 'bookings.checkin');
        return DB::transaction(function () use ($booking, $actor) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $this->assertCheckinReady($booking);
            $booking->update(['status' => 'CHECKED_IN', 'checked_in_at' => now(), 'checked_in_by' => $actor->id]);
            $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->update(['status' => 'CHECKED_IN']);
            app(BookingExtensionService::class)->refreshCourtOccupancy($booking);
            $this->audit($booking, $actor, 'CHECKED_IN', 'Nhân viên xác nhận nhận sân; giờ đặt không thay đổi.');
            app(CustomerNotificationService::class)->statusChanged($booking, 'CHECKED_IN');
            return $booking;
        }, 3);
    }

    public function noShow(Booking $booking, User $actor): void
    {
        $this->permission($actor, 'bookings.checkin');
        DB::transaction(function () use ($booking, $actor) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            ['start' => $start] = $this->timing($booking);
            if ($booking->status !== 'CONFIRMED' || ! $start || now()->lt($start->copy()->addMinutes(config('booking.no_show_after_minutes')))) throw new \DomainException('Chỉ đánh dấu khách không đến sau giờ bắt đầu '.config('booking.no_show_after_minutes').' phút, khi đơn chưa nhận sân.');
            $booking->update(['status' => 'NO_SHOW', 'no_show_at' => now(), 'no_show_by' => $actor->id]);
            $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->update(['status' => 'NO_SHOW']);
            $this->audit($booking, $actor, 'NO_SHOW', 'Khách không đến; không tự động hoàn tiền.');
            app(CustomerNotificationService::class)->statusChanged($booking, 'NO_SHOW');
        }, 3);
    }

    public function activeLines(Booking $booking)
    {
        return $booking->services()->whereNull('stock_released_at')->where(function ($q) use ($booking) {
            $q->whereNull('service_order_id')->orWhereIn('service_order_id', $booking->serviceOrders()->where('status', '!=', 'CANCELLED')->select('id'));
        });
    }

    public function amountDue(Booking $booking): float
    {
        return $booking->serviceOrders()->where('status', '!=', 'CANCELLED')->whereHas('payment', fn ($q) => $q->where('status', 'PENDING'))->with('payment')->get()
            ->sum(fn ($order) => (int) round($order->payment->amount * 100)) / 100;
    }

    public function assertCheckoutReady(Booking $booking): void
    {
        if (\App\Models\EquipmentLoan::where('booking_id', $booking->id)->whereNull('returned_at')->exists()) throw new \DomainException('Còn thiết bị mượn chưa xác nhận trả.');
        if (! in_array($booking->payment_status, ['PAID', 'PARTIALLY_REFUNDED']) || ! in_array($booking->payment?->status, ['PAID', 'PARTIALLY_REFUNDED'])) throw new \DomainException('Tiền sân còn khoản chưa xử lý.');
        if ($this->amountDue($booking) > 0) throw new \DomainException('Còn '.number_format($this->amountDue($booking), 0, ',', '.').'đ dịch vụ chưa thanh toán.');
        if ($booking->serviceOrders()->whereIn('status', ['PENDING', 'PAID'])->exists()) throw new \DomainException('Vui lòng xác nhận giao dịch vụ hoặc hủy dịch vụ chưa giao.');
        if ($this->activeLines($booking)->where('requires_return', true)->whereColumn('returned_quantity', '<', 'quantity')->exists()) throw new \DomainException('Còn đồ thuê chưa xác nhận trả đủ. Hãy ghi nhận trả đồ hoặc báo sự cố.');
    }

    public function returnRental(Booking $booking, ServiceLine $line, User $actor, int $quantity): void
    {
        $this->permission($actor, 'services.manage');
        DB::transaction(function () use ($booking, $line, $actor, $quantity) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $line = $this->activeLines($booking)->whereKey($line->id)->lockForUpdate()->firstOrFail();
            if (! $line->requires_return || ! in_array($booking->status, ['CHECKED_IN', 'COMPLETED']) || ($booking->status === 'COMPLETED' && ! $booking->checkout_exception_reason)) throw new \DomainException('Không thể ghi nhận trả đồ ở trạng thái hiện tại.');
            if ($quantity < $line->returned_quantity || $quantity > $line->quantity) throw new \DomainException('Số lượng đã trả không hợp lệ.');
            $delta = $quantity - $line->returned_quantity;
            if (! $delta) return;
            $product = ServiceItem::lockForUpdate()->findOrFail($line->service_item_id);
            if ($product->stock !== null) $product->increment('stock', $delta);
            $line->update(['returned_quantity' => $quantity, 'returned_at' => now(), 'returned_by' => $actor->id]);
            $this->audit($booking, $actor, 'RENTAL_RETURNED', $product->name.': đã trả '.$quantity.'/'.$line->quantity);
        }, 3);
    }

    public function checkout(Booking $booking, User $actor, ?float $amount = null, bool $delivered = false, ?string $exception = null): Booking
    {
        $this->permission($actor, 'bookings.checkout');
        if ($exception !== null) abort_unless($actor->role === 'ADMIN' && mb_strlen(trim($exception)) >= 10, 403);
        if ($amount !== null) $this->permission($actor, 'payments.counter');
        return DB::transaction(function () use ($booking, $actor, $amount, $delivered, $exception) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            if ($booking->status !== 'CHECKED_IN') throw new \DomainException('Chỉ đơn đang chơi mới được trả sân.');
            if ($amount !== null) {
                if ((int) round($amount * 100) !== (int) round($this->amountDue($booking) * 100)) throw new \DomainException('Số tiền đã thay đổi. Vui lòng tải lại và kiểm tra trước khi thu.');
                $transaction = 'CHECKOUT-'.str()->uuid();
                foreach ($booking->serviceOrders()->where('status', '!=', 'CANCELLED')->whereHas('payment', fn ($q) => $q->where('status', 'PENDING'))->get() as $order) {
                    if (! app(ServiceOrderService::class)->settle($order->payment, true, $transaction.'-'.$order->payment_id, 'CASH')) throw new \DomainException('Khoản dịch vụ không còn hợp lệ. Vui lòng kiểm tra lại.');
                }
            }
            if ($delivered) {
                $this->permission($actor, 'services.manage');
                foreach ($booking->serviceOrders()->whereIn('status', ['PENDING', 'PAID'])->get() as $order) app(ServiceOrderService::class)->deliver($order, $actor);
            }
            if ($this->amountDue($booking) > 0) throw new \DomainException('Vui lòng thanh toán toàn bộ dịch vụ phát sinh trước khi hoàn tất check-out.');
            if ($exception === null) $this->assertCheckoutReady($booking);
            else $booking->checkout_exception_reason = trim($exception);
            $booking->fill(['status' => 'COMPLETED', 'checked_out_at' => now(), 'checked_out_by' => $actor->id])->save();
            $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->update(['status' => 'COMPLETED']);
            app(BookingExtensionService::class)->refreshCourtOccupancy($booking);
            $this->audit($booking, $actor, $exception ? 'CHECKOUT_EXCEPTION' : 'COMPLETED', $exception ?? 'Đã đối soát dịch vụ, thanh toán và trả đồ thuê.');
            app(CustomerNotificationService::class)->statusChanged($booking, 'COMPLETED');
            foreach ($booking->bookingDetails()->pluck('court_id')->unique() as $courtId) {
                if (! \App\Models\BookingDetail::where('court_id', $courtId)->whereHas('booking', fn ($q) => $q->where('status', 'CHECKED_IN'))->exists()) Court::whereKey($courtId)->update(['availability_status' => 'AVAILABLE']);
            }
            return $booking;
        }, 3);
    }

    public function extend(Booking $booking, User $actor, int $slotId, float $amount): Booking
    {
        $this->permission($actor, 'bookings.checkout');
        $this->permission($actor, 'payments.counter');
        return DB::transaction(function () use ($booking, $actor, $slotId, $amount) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $last = $booking->bookingDetails()->where('status', 'CHECKED_IN')->with('timeSlot')->get()->sortBy('timeSlot.end_time')->last();
            if (!$last) throw new \DomainException('Chỉ gia hạn đơn đang chơi.');
            $extension = app(BookingExtensionService::class)->create($booking, $last->id, [$slotId], $last->court_id, $actor, $amount);
            if ((int) round($amount * 100) !== (int) round($extension->total_amount * 100)) throw new \DomainException('Giá gia hạn đã thay đổi. Vui lòng kiểm tra lại số tiền.');
            app(PaymentService::class)->markAsPaid($extension->payment, 'EXTEND-'.str()->uuid(), 'CASH');
            $this->audit($booking, $actor, 'EXTENDED', 'Gia hạn bằng đơn '.$extension->booking_code.'; giữ nguyên giao dịch gốc.');
            return $extension->fresh();
        }, 3);
    }

    private function permission(User $actor, string $permission): void
    {
        abort_unless(in_array($actor->role, ['ADMIN', 'EMPLOYEE']) && $actor->hasPermission($permission), 403);
    }

    private function audit(Booking $booking, User $actor, string $action, string $reason): void
    {
        BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $actor->id, 'action' => $action, 'reason' => $reason,
            'new_values' => ['status' => $booking->status, 'outstanding_amount' => $this->amountDue($booking)]]);
    }
}

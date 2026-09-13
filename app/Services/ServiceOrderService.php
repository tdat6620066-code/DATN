<?php
namespace App\Services;

use App\Models\{Booking, BookingAuditLog, Payment, PaymentTransactionLog, ServiceItem, ServiceOrder, User};
use Illuminate\Support\Facades\DB;

class ServiceOrderService
{
    /** Called inside the transaction that creates the booking. */
    public function includeInBooking(Booking $booking, array $items): void
    {
        foreach ($items as $item) {
            if (filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT) === false || $item['quantity'] < 0 || $item['quantity'] > 1000) throw new \DomainException('Số lượng dịch vụ không hợp lệ.');
        }
        if (collect($items)->pluck('service_item_id')->duplicates()->isNotEmpty()) throw new \DomainException('Mỗi dịch vụ chỉ được chọn một lần.');
        $items = collect($items)->filter(fn ($item) => $item['quantity'] > 0)->sortBy('service_item_id');
        $catalogue = ServiceItem::whereIn('id', $items->pluck('service_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $total = 0;
        foreach ($items as $item) {
            $product = $catalogue->get($item['service_item_id']);
            if (! $product || ! $product->is_active || $product->price <= 0) throw new \DomainException('Dịch vụ không còn bán hoặc chưa có giá hợp lệ.');
            if ($product->stock !== null && $product->stock < $item['quantity']) throw new \DomainException($product->name.': tồn kho không đủ số lượng yêu cầu.');
            $subtotal = (int) round($product->price * 100) * $item['quantity'];
            $booking->services()->create(['service_item_id' => $product->id, 'added_by' => $booking->user_id,
                'source' => 'pre_booking', 'quantity' => $item['quantity'], 'unit_price' => $product->price, 'subtotal' => $subtotal / 100, 'requires_return' => $product->category === 'RENTAL']);
            if ($product->stock !== null) $product->decrement('stock', $item['quantity']);
            $total += $subtotal;
        }
        $booking->update(['subtotal' => $booking->subtotal + $total / 100, 'total_amount' => $booking->total_amount + $total / 100]);
    }

    public function releaseIncludedStock(Booking $booking): void
    {
        if ($booking->checked_in_at) return;
        DB::transaction(function () use ($booking) {
            $lines = $booking->services()->whereNull('service_order_id')->where('source', 'pre_booking')
                ->whereNull('stock_released_at')->orderBy('service_item_id')->lockForUpdate()->get();
            foreach ($lines as $line) {
                $product = ServiceItem::lockForUpdate()->findOrFail($line->service_item_id);
                if ($product->stock !== null) $product->increment('stock', $line->quantity);
                $line->forceFill(['stock_released_at' => now()])->save();
            }
        });
    }

    public function canCustomerAdd(Booking $booking): bool
    {
        if (! in_array($booking->status, ['PENDING_PAYMENT', 'CONFIRMED']) || ($booking->status === 'PENDING_PAYMENT' && $booking->isHoldExpired())) return false;
        $details = $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->with('timeSlot')->get();
        return $details->isNotEmpty() && $details->every(fn ($d) => \Carbon\Carbon::parse($d->booking_date->toDateString().' '.$d->timeSlot->start_time)->isFuture());
    }

    public function create(Booking $booking, User $actor, array $items, string $key): ServiceOrder
    {
        return DB::transaction(function () use ($booking, $actor, $items, $key) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            $existing = ServiceOrder::where('request_key', $key)->first();
            if ($existing) {
                abort_unless($existing->booking_id === $booking->id && $existing->added_by === $actor->id, 403);
                return $existing;
            }
            $customer = ($actor->role ?: 'CUSTOMER') === 'CUSTOMER';
            abort_unless($customer ? $booking->user_id === $actor->id : in_array($actor->role, ['ADMIN', 'EMPLOYEE']) && $actor->hasPermission('services.manage'), 403);
            if ($customer ? ! $this->canCustomerAdd($booking) : $booking->status !== 'CHECKED_IN') {
                throw new \DomainException($customer ? 'Khách chỉ được thêm dịch vụ trước giờ chơi; sau check-in vui lòng nhờ Staff.' : 'Staff chỉ thêm dịch vụ khi booking đã check-in và chưa kết thúc.');
            }
            foreach ($items as $item) {
                if (filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT) === false || $item['quantity'] < 0 || $item['quantity'] > 1000) {
                    throw new \DomainException('Số lượng dịch vụ không hợp lệ.');
                }
            }
            if (collect($items)->pluck('service_item_id')->duplicates()->isNotEmpty()) throw new \DomainException('Mỗi dịch vụ chỉ được chọn một lần.');
            $items = collect($items)->filter(fn ($i) => $i['quantity'] > 0)->sortBy('service_item_id')->values();
            if ($items->isEmpty()) throw new \DomainException('Chọn ít nhất một dịch vụ.');
            $catalogue = ServiceItem::whereIn('id', $items->pluck('service_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = 0;
            foreach ($items as $item) {
                $product = $catalogue->get($item['service_item_id']);
                if (! $product || ! $product->is_active || (float) $product->price <= 0) throw new \DomainException('Dịch vụ không còn bán hoặc chưa có giá hợp lệ.');
                if ($product->stock !== null && $product->stock < $item['quantity']) throw new \DomainException($product->name.': tồn kho không đủ số lượng yêu cầu.');
                $total += (int) round((float) $product->price * 100) * $item['quantity'];
            }
            $payment = Payment::create(['booking_id' => $booking->id, 'purpose' => 'SERVICE', 'amount' => $total / 100, 'status' => 'PENDING']);
            $order = ServiceOrder::create(['booking_id' => $booking->id, 'payment_id' => $payment->id, 'added_by' => $actor->id,
                'request_key' => $key, 'source' => $customer ? 'pre_booking' : 'at_court', 'pay_at_checkout' => ! $customer, 'expires_at' => $customer ? now()->addMinutes(15) : null]);
            foreach ($items as $item) {
                $product = $catalogue[$item['service_item_id']];
                $order->items()->create(['booking_id' => $booking->id, 'service_item_id' => $product->id, 'added_by' => $actor->id,
                    'source' => $order->source, 'quantity' => $item['quantity'], 'unit_price' => $product->price, 'subtotal' => (float) $product->price * $item['quantity'], 'requires_return' => $product->category === 'RENTAL']);
                if ($product->stock !== null) $product->decrement('stock', $item['quantity']);
            }
            BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $actor->id, 'action' => 'SERVICE_ADDED', 'reason' => 'Đơn dịch vụ #'.$order->id,
                'new_values' => ['service_amount' => $payment->amount, 'source' => $order->source]]);
            return $order;
        }, 3);
    }

    public function cancel(ServiceOrder $order): void
    {
        DB::transaction(function () use ($order) {
            Booking::whereKey($order->booking_id)->lockForUpdate()->firstOrFail();
            $order = ServiceOrder::lockForUpdate()->findOrFail($order->id);
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            if ($order->status === 'CANCELLED') return;
            if ($order->status !== 'PENDING' || $payment->status === 'PAID') throw new \DomainException('Không được xóa hoặc hủy dịch vụ đã thanh toán.');
            foreach ($order->items()->orderBy('service_item_id')->get() as $line) {
                $product = ServiceItem::lockForUpdate()->findOrFail($line->service_item_id);
                if ($product->stock !== null) $product->increment('stock', $line->quantity);
            }
            $order->update(['status' => 'CANCELLED']);
            $payment->update(['status' => 'FAILED']);
        }, 3);
    }

    public function settle(Payment $payment, bool $success, ?string $transactionId, string $method): bool
    {
        return DB::transaction(function () use ($payment, $success, $transactionId, $method) {
            $booking = Booking::lockForUpdate()->findOrFail($payment->booking_id);
            $order = ServiceOrder::where('payment_id', $payment->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === 'PAID') return true;
            if (! in_array($order->status, ['PENDING', 'DELIVERED'])) return false;
            if ($success && $booking->payment_status !== 'PAID') return false;
            if (! $success && $order->pay_at_checkout) return false;
            if (! $success || $order->expires_at?->lte(now()) || in_array($booking->status, ['CANCELLED', 'EXPIRED', 'NO_SHOW']) || ($booking->status === 'COMPLETED' && ! $booking->checkout_exception_reason)) {
                $this->cancel($order);
                return false;
            }
            $payment->update(['status' => 'PAID', 'paid_at' => now(), 'transaction_id' => $transactionId, 'payment_method' => $method]);
            $order->update(['status' => $order->delivered_at ? 'DELIVERED' : 'PAID']);
            PaymentTransactionLog::create(['payment_id' => $payment->id, 'actor_id' => auth()->id(), 'action' => 'SERVICE_PAYMENT',
                'old_status' => 'PENDING', 'new_status' => 'PAID', 'amount' => $payment->amount, 'note' => 'Thu riêng đơn dịch vụ #'.$order->id]);
            return true;
        }, 3);
    }

    public function deliver(ServiceOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $booking = Booking::lockForUpdate()->findOrFail($order->booking_id);
            $order = ServiceOrder::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'DELIVERED') return;
            if ($booking->status !== 'CHECKED_IN' || ! in_array($order->status, ['PENDING', 'PAID']) || (! $order->pay_at_checkout && $order->payment->status !== 'PAID')) throw new \DomainException('Chỉ giao dịch vụ hợp lệ khi khách đang chơi.');
            $order->update(['status' => 'DELIVERED', 'delivered_at' => now(), 'delivered_by' => $actor->id]);
            BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $actor->id, 'action' => 'SERVICE_DELIVERED', 'reason' => 'Đã giao đơn dịch vụ #'.$order->id]);
        }, 3);
    }
}

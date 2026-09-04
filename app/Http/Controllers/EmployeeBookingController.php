<?php

namespace App\Http\Controllers;

use App\Models\{Booking, BookingAuditLog, BookingService, PaymentTransactionLog, ServiceItem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\CustomerNotificationService;

class EmployeeBookingController extends Controller
{
    public function __construct(private readonly CustomerNotificationService $notifications) {}

    public function index(Request $request)
    {
        $this->employee($request);
        $bookings = Booking::with(['user', 'bookingDetails.court', 'bookingDetails.timeSlot', 'payment'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($i) => $i
                ->where('booking_code', 'like', '%'.$request->search.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date'), fn ($q) => $q->whereHas('bookingDetails', fn ($d) => $d->whereDate('booking_date', $request->date)))
            ->latest()->paginate(20)->withQueryString();
        return view('employee.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking, Request $request)
    {
        $this->employee($request);
        $booking->load(['user', 'bookingDetails.court', 'bookingDetails.timeSlot', 'payment', 'services.item']);
        $serviceItems = ServiceItem::where('is_active', true)->orderBy('name')->get();
        return view('employee.bookings.show', compact('booking', 'serviceItems'));
    }

    public function checkIn(Booking $booking, Request $request)
    {
        abort_unless($request->user()->hasPermission('bookings.checkin'), 403);
        try {
            DB::transaction(function () use ($booking, $request) {
                $locked = Booking::lockForUpdate()->with(['payment', 'bookingDetails.timeSlot'])->findOrFail($booking->id);
                if ($locked->status === 'CHECKED_IN') throw new \DomainException('Đơn đã được check-in.');
                if ($locked->status !== 'CONFIRMED') throw new \DomainException('Chỉ đơn đã xác nhận mới được check-in.');
                if ($locked->payment_status !== 'PAID' && $locked->payment?->status !== 'PAID') throw new \DomainException('Khách hàng chưa hoàn tất thanh toán.');
                if (! $locked->bookingDetails->contains(fn ($d) => $d->booking_date->isToday())) throw new \DomainException('Đơn không có lịch chơi hôm nay.');
                $old = $locked->status;
                $locked->update(['status' => 'CHECKED_IN', 'checked_in_at' => now(), 'checked_in_by' => $request->user()->id]);
                $locked->bookingDetails()->whereDate('booking_date', today())->update(['status' => 'CHECKED_IN']);
                $this->notifications->statusChanged($locked, 'CHECKED_IN');
                $this->audit($locked, $request, 'CHECKED_IN', $old, 'CHECKED_IN');
            });
            return back()->with('success', 'Khách đã check-in thành công.');
        } catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
    }

    public function complete(Booking $booking, Request $request)
    {
        abort_unless($request->user()->hasPermission('bookings.checkout'), 403);
        try {
            DB::transaction(function () use ($booking, $request) {
                $locked = Booking::lockForUpdate()->with('payment')->findOrFail($booking->id);
                if ($locked->status !== 'CHECKED_IN') throw new \DomainException('Khách chưa check-in hoặc đơn đã hoàn thành.');
                if ($locked->payment_status !== 'PAID' || $locked->payment?->status !== 'PAID') throw new \DomainException('Đơn còn khoản thanh toán chưa xử lý.');
                $locked->update(['status' => 'COMPLETED', 'checked_out_at' => now(), 'checked_out_by' => $request->user()->id]);
                $locked->bookingDetails()->update(['status' => 'COMPLETED']);
                $locked->bookingDetails()->with('court')->get()->each(fn ($d) => $d->court->update(['availability_status' => 'AVAILABLE']));
                $this->notifications->statusChanged($locked, 'COMPLETED');
                $this->audit($locked, $request, 'COMPLETED', 'CHECKED_IN', 'COMPLETED');
            });
            return back()->with('success', 'Đơn đã hoàn thành và sân đã được giải phóng.');
        } catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
    }

    public function pay(Booking $booking, Request $request)
    {
        abort_unless($request->user()->hasPermission('payments.counter'), 403);
        $data = $request->validate(['payment_method' => ['required', Rule::in(['CASH', 'BANK_TRANSFER', 'QR'])], 'amount' => ['required', 'numeric', 'gt:0'], 'transaction_id' => ['nullable', 'string', 'max:100']]);
        try {
            DB::transaction(function () use ($booking, $request, $data) {
                $locked = Booking::lockForUpdate()->with('payment')->findOrFail($booking->id);
                if (! $locked->payment || $locked->payment->status === 'PAID') throw new \DomainException('Đơn không còn khoản phải thu.');
                if (round((float) $data['amount'], 2) !== round((float) $locked->total_amount, 2)) throw new \DomainException('Số tiền thanh toán phải bằng tổng tiền của đơn.');
                $transactionId = $data['transaction_id'] ?: 'POS-'.now()->format('YmdHis').'-'.$locked->id;
                $locked->payment->update(['amount' => $data['amount'], 'payment_method' => $data['payment_method'], 'transaction_id' => $transactionId, 'status' => 'PAID', 'paid_at' => now()]);
                $locked->update(['payment_status' => 'PAID', 'status' => $locked->status === 'PENDING_PAYMENT' ? 'CONFIRMED' : $locked->status, 'confirmed_at' => $locked->confirmed_at ?? now()]);
                $this->notifications->payment($locked, 'PAID');
                PaymentTransactionLog::create(['payment_id' => $locked->payment->id, 'actor_id' => $request->user()->id, 'action' => 'COUNTER_PAYMENT', 'old_status' => 'PENDING', 'new_status' => 'PAID', 'amount' => $data['amount'], 'note' => 'Thanh toán tại quầy', 'metadata' => ['method' => $data['payment_method']]]);
            });
            return back()->with('success', 'Thanh toán thành công.');
        } catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
    }

    public function addService(Booking $booking, Request $request)
    {
        abort_unless($request->user()->hasPermission('services.manage'), 403);
        $data = $request->validate(['service_item_id' => ['required', 'exists:service_items,id'], 'quantity' => ['required', 'integer', 'min:1']]);
        try {
            DB::transaction(function () use ($booking, $request, $data) {
                $locked = Booking::lockForUpdate()->findOrFail($booking->id);
                if ($locked->status !== 'CHECKED_IN') throw new \DomainException('Chỉ thêm dịch vụ khi khách đang sử dụng sân.');
                $item = ServiceItem::lockForUpdate()->where('is_active', true)->findOrFail($data['service_item_id']);
                if ($item->stock !== null && $item->stock < $data['quantity']) throw new \DomainException('Dịch vụ hiện không đủ số lượng.');
                $subtotal = (float) $item->price * $data['quantity'];
                BookingService::create(['booking_id' => $locked->id, 'service_item_id' => $item->id, 'added_by' => $request->user()->id, 'quantity' => $data['quantity'], 'unit_price' => $item->price, 'subtotal' => $subtotal]);
                if ($item->stock !== null) $item->decrement('stock', $data['quantity']);
                $locked->increment('subtotal', $subtotal); $locked->increment('total_amount', $subtotal);
                $locked->payment?->increment('amount', $subtotal);
                if ($locked->payment_status === 'PAID') { $locked->update(['payment_status' => 'PENDING']); $locked->payment?->update(['status' => 'PENDING']); }
                $this->audit($locked, $request, 'SERVICE_ADDED', null, $item->name.' x'.$data['quantity']);
            });
            return back()->with('success', 'Thêm dịch vụ thành công.');
        } catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
    }

    public function removeService(Booking $booking, BookingService $service, Request $request)
    {
        abort_unless($request->user()->hasPermission('services.manage'), 403);
        abort_unless($service->booking_id === $booking->id, 404);
        DB::transaction(function () use ($booking, $service, $request) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            if ($locked->status !== 'CHECKED_IN') throw new \DomainException('Không thể sửa dịch vụ của đơn đã hoàn tất.');
            $amount = (float) $service->subtotal; $item = $service->item;
            if ($item->stock !== null) $item->increment('stock', $service->quantity);
            $service->delete(); $locked->decrement('subtotal', $amount); $locked->decrement('total_amount', $amount); $locked->payment?->decrement('amount', $amount);
            $this->audit($locked, $request, 'SERVICE_REMOVED', $item->name, null);
        });
        return back()->with('success', 'Đã xóa dịch vụ khỏi đơn.');
    }

    private function employee(Request $request): void { abort_unless(in_array($request->user()->role, ['EMPLOYEE', 'ADMIN'], true), 403); }
    private function audit(Booking $booking, Request $request, string $action, mixed $old, mixed $new): void { BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => $action, 'old_values' => ['value' => $old], 'new_values' => ['value' => $new], 'reason' => 'Thao tác vận hành', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]); }
}

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
        $sessionBookings = app(\App\Services\BookingExtensionService::class)->session($booking);
        return view('employee.bookings.show', compact('booking', 'serviceItems', 'sessionBookings'));
    }

    public function checkIn(Booking $booking, Request $request)
    {
        return app(BookingOperationsController::class)->checkIn($request, $booking, app(\App\Services\BookingOperationsService::class));
    }

    public function complete(Booking $booking, Request $request)
    {
        return app(BookingOperationsController::class)->checkout($request, $booking, app(\App\Services\BookingOperationsService::class));
    }

    public function pay(Booking $booking, Request $request)
    {
        abort_unless($request->user()->hasPermission('payments.counter'), 403);
        $data = $request->validate(['payment_method' => ['required', Rule::in(['CASH', 'BANK_TRANSFER', 'QR'])], 'amount' => ['required', 'numeric', 'gt:0'], 'transaction_id' => ['nullable', 'string', 'max:100']]);
        try {
            DB::transaction(function () use ($booking, $request, $data) {
                $locked = Booking::lockForUpdate()->with('payment')->findOrFail($booking->id);
                if ($locked->fixedBooking && $locked->fixedBooking->status !== 'LEGACY') throw new \DomainException('Lịch cố định phải thanh toán toàn bộ tại trang lịch cố định.');
                if (! $locked->payment || $locked->payment->status === 'PAID') throw new \DomainException('Đơn không còn khoản phải thu.');
                if ($locked->status === 'PENDING_PAYMENT' && $locked->isHoldExpired()) throw new \DomainException('Thời gian giữ chỗ đã hết. Vui lòng tạo đơn mới.');
                if (round((float) $data['amount'], 2) !== round((float) $locked->total_amount, 2)) throw new \DomainException('Số tiền thanh toán phải bằng tổng tiền của đơn.');
                if (in_array($locked->status, ['CANCELLED', 'EXPIRED', 'COMPLETED'], true) || in_array($locked->payment->status, ['REFUNDED', 'PARTIALLY_REFUNDED'], true)) throw new \DomainException('Không thể thu tiền cho đơn đã kết thúc hoặc hoàn tiền.');
                if ($locked->status === 'PENDING_PAYMENT' && (!$locked->hold_expires_at || $locked->hold_expires_at->lte(now()))) throw new \DomainException('Thời gian giữ sân đã hết. Vui lòng đặt lại.');
                $transactionId = ($data['transaction_id'] ?? null) ?: 'POS-'.now()->format('YmdHis').'-'.$locked->id;
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
        return app(ServiceOrderController::class)->store($request, $booking, app(\App\Services\ServiceOrderService::class));
    }

    public function removeService(Booking $booking, BookingService $service, Request $request)
    {
        abort_unless($service->booking_id === $booking->id, 404);
        if (! $service->service_order_id) return back()->with('error', 'Dịch vụ cũ đã gộp vào tiền sân, không được sửa giao dịch đã chốt.');
        return app(ServiceOrderController::class)->cancel($request, \App\Models\ServiceOrder::findOrFail($service->service_order_id), app(\App\Services\ServiceOrderService::class));
    }

    private function employee(Request $request): void { abort_unless(in_array($request->user()->role, ['EMPLOYEE', 'ADMIN'], true), 403); }
    private function audit(Booking $booking, Request $request, string $action, mixed $old, mixed $new): void { BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => $action, 'old_values' => ['value' => $old], 'new_values' => ['value' => $new], 'reason' => 'Thao tác vận hành', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]); }
}

<?php
namespace App\Http\Controllers;

use App\Models\{Booking, ServiceOrder, PaymentTransactionLog};
use App\Services\{ServiceOrderService, VnPayService};
use Illuminate\Http\Request;

class ServiceOrderController extends Controller
{
    private function access(Request $request, Booking $booking, ?string $permission = null): void
    {
        $user = $request->user();
        abort_unless($permission
            ? in_array($user->role, ['ADMIN', 'EMPLOYEE']) && $user->hasPermission($permission)
            : (($user->role ?: 'CUSTOMER') === 'CUSTOMER' && $booking->user_id === $user->id)
                || (in_array($user->role, ['ADMIN', 'EMPLOYEE']) && $user->hasPermission('bookings.view')), 403);
    }

    public function store(Request $request, Booking $booking, ServiceOrderService $service)
    {
        $this->access($request, $booking, 'services.manage');
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'items' => ['required', 'array', 'max:100'],
            'items.*.service_item_id' => ['required', 'integer', 'distinct', 'exists:service_items,id'], 'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000']]);
        try {
            $service->create($booking, $request->user(), $data['items'], $data['request_key']);
            return back()->with('success', 'Đã thêm dịch vụ vào booking của khách. Cần thanh toán khoản phát sinh trước khi hoàn tất check-out.');
        } catch (\DomainException $e) { return back()->withInput()->with('error', $e->getMessage()); }
    }

    public function cash(Request $request, ServiceOrder $serviceOrder, ServiceOrderService $service)
    {
        abort_unless(\App\Models\SystemSetting::valueFor('cash_enabled', '1') === '1', 422, 'Thanh toán tiền mặt đang tạm ngừng.');
        $this->access($request, $serviceOrder->booking, 'payments.counter');
        $data = $request->validate(['amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0']]);
        if (round((float) $data['amount'] * 100) !== round((float) $serviceOrder->payment->amount * 100)) return back()->withErrors(['amount' => 'Số tiền phải khớp khoản dịch vụ cần thu.']);
        $ok = $service->settle($serviceOrder->payment, true, 'SERVICE-CASH-'.$serviceOrder->id, 'CASH');
        return back()->with($ok ? 'success' : 'error', $ok ? 'Đã thu tiền dịch vụ. Staff có thể xác nhận giao.' : 'Khoản dịch vụ đã hủy hoặc hết hạn.');
    }

    public function cancel(Request $request, ServiceOrder $serviceOrder, ServiceOrderService $service)
    {
        $this->access($request, $serviceOrder->booking, 'services.manage');
        try { $service->cancel($serviceOrder); return back()->with('success', 'Đã hủy khoản dịch vụ chưa thanh toán và trả lại tồn kho.'); }
        catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
    }

    public function deliver(Request $request, ServiceOrder $serviceOrder, ServiceOrderService $service)
    {
        $this->access($request, $serviceOrder->booking, 'services.manage');
        try { $service->deliver($serviceOrder, $request->user()); return back()->with('success', 'Đã ghi nhận giao dịch vụ.'); }
        catch (\DomainException $e) { return back()->with('error', $e->getMessage()); }
    }

    public function pay(Request $request, ServiceOrder $serviceOrder, VnPayService $gateway)
    {
        $this->access($request, $serviceOrder->booking);
        if (in_array($request->user()->role, ['ADMIN', 'EMPLOYEE'])) $this->access($request, $serviceOrder->booking, 'payments.counter');
        if ($serviceOrder->booking->payment_status !== 'PAID') return back()->with('error', 'Vui lòng thanh toán tiền sân trước khi thanh toán dịch vụ.');
        if (! in_array($serviceOrder->status, ['PENDING', 'DELIVERED']) || $serviceOrder->payment->status !== 'PENDING' || $serviceOrder->expires_at?->lte(now())) return back()->with('error', 'Khoản dịch vụ không còn chờ thanh toán.');
        try { return redirect()->away($gateway->createPaymentUrl($serviceOrder, route('bookings.vnpay.return'))); }
        catch (\RuntimeException $e) { return back()->with('error', $e->getMessage()); }
    }

    public function callback(Request $request, bool $ipn = false)
    {
        $data = $request->all(); $code = '00'; $ok = false; $order = null;
        if (! app(VnPayService::class)->verifyResponse($data)) $code = '97';
        elseif (($data['vnp_TmnCode'] ?? null) !== config('vnpay.tmn_code')) $code = '02';
        elseif (! preg_match('/^SVC([1-9][0-9]*)$/', $data['vnp_TxnRef'] ?? '', $match) || ! ($order = ServiceOrder::with('payment')->find($match[1]))) $code = '01';
        elseif (! ctype_digit((string) ($data['vnp_Amount'] ?? '')) || (string) $data['vnp_Amount'] !== (string) (int) round((float) $order->payment->amount * 100)) $code = '04';
        else {
            $success = ($data['vnp_ResponseCode'] ?? null) === '00' && ($data['vnp_TransactionStatus'] ?? null) === '00';
            $ok = app(ServiceOrderService::class)->settle($order->payment, $success, $data['vnp_TransactionNo'] ?? null, 'vnpay');
            if ($success && ! $ok) {
                $code = '02';
                PaymentTransactionLog::firstOrCreate(['payment_id' => $order->payment_id, 'action' => 'VNPAY_REQUIRES_REVIEW'],
                    ['amount' => $order->payment->amount, 'note' => 'Tiền dịch vụ về sau khi đơn đã hủy/hết hạn; cần đối chiếu.', 'metadata' => ['transaction_id' => $data['vnp_TransactionNo'] ?? null]]);
            }
        }
        if ($ipn) return response()->json(['RspCode' => $code, 'Message' => $code === '00' ? 'Confirm Success' : 'Payment not accepted']);
        return ($order ? redirect()->route('bookings.show', $order->booking_id) : redirect()->route('bookings.index'))
            ->with($ok ? 'success' : 'error', $ok ? 'Đã thanh toán dịch vụ. Vui lòng nhận dịch vụ từ Staff.' : 'Thanh toán dịch vụ chưa được xác nhận. Nếu đã bị trừ tiền, vui lòng liên hệ Staff để đối chiếu.');
    }
}

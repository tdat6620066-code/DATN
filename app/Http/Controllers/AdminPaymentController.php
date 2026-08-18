<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentTransactionLog;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Services\RefundRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminPaymentController extends Controller
{
    public function __construct(private readonly RefundRequestService $refundRequests) {}

    public function index(Request $request)
    {
        $this->admin($request);
        $payments = Payment::with(['booking.user', 'refunds'])->when($request->filled('search'), fn ($q) => $q->where('transaction_id', 'like', '%'.$request->search.'%')->orWhereHas('booking', fn ($b) => $b->where('booking_code', 'like', '%'.$request->search.'%')))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(15)->withQueryString();

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment, Request $request)
    {
        $this->admin($request);
        $payment->load(['booking.user', 'booking.bookingDetails.court', 'refunds.refundRequest', 'transactionLogs.actor']);
        $refundRequests = RefundRequest::where('booking_id', $payment->booking_id)->with('requester')->latest()->get();

        return view('admin.payments.show', compact('payment', 'refundRequests'));
    }

    public function reconcile(Payment $payment, Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['action' => ['required', Rule::in(['MARK_PAID', 'MARK_FAILED'])], 'amount' => ['required', 'numeric', 'min:0'], 'transaction_id' => ['nullable', 'string', 'max:255'], 'note' => ['required', 'string', 'max:1000']]);
        try {
            DB::transaction(function () use ($payment, $request, $data) {
                $locked = Payment::with('booking')->lockForUpdate()->findOrFail($payment->id);
                if ($locked->status === 'REFUNDED') {
                    throw new \DomainException('Giao dịch đã hoàn tiền và không thể đối chiếu lại.');
                }if ((float) $data['amount'] !== (float) $locked->booking->total_amount) {
                    throw new \DomainException('Số tiền giao dịch không khớp tổng tiền booking.');
                }$old = $locked->status;
                $new = $data['action'] === 'MARK_PAID' ? 'PAID' : 'FAILED';
                $locked->update(['status' => $new, 'amount' => $data['amount'], 'transaction_id' => $data['transaction_id'] ?? $locked->transaction_id, 'paid_at' => $new === 'PAID' ? now() : $locked->paid_at]);
                $locked->booking->update(['payment_status' => $new, 'status' => $new === 'PAID' && $locked->booking->status === 'PENDING_PAYMENT' ? 'CONFIRMED' : $locked->booking->status]);
                $this->log($locked, $request, 'RECONCILED', $old, $new, $data['amount'], $data['note']);
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

return back()->with('success', 'Đã đối chiếu và cập nhật giao dịch.');
    }

    public function approveRefund(RefundRequest $refundRequest, Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        try {
            $approved = $this->refundRequests->review($refundRequest, $request->user(), ['decision' => 'APPROVED', 'decision_note' => $data['note']]);
            $this->log($approved->booking->payment, $request, 'REFUND_APPROVED', $approved->booking->payment->status, $approved->booking->payment->status, $approved->amount, $data['note']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

return back()->with('success', 'Đã phê duyệt yêu cầu hoàn tiền.');
    }

    public function processRefund(Refund $refund, Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['result' => ['required', Rule::in(['COMPLETED', 'FAILED'])], 'note' => ['required', 'string', 'max:1000']]);
        try {
            DB::transaction(function () use ($refund, $request, $data) {
                $locked = Refund::with('payment.booking')->lockForUpdate()->findOrFail($refund->id);
                if ($locked->status !== 'PROCESSING') {
                    throw new \DomainException($locked->status === 'COMPLETED' ? 'Giao dịch đã được hoàn tiền.' : 'Yêu cầu hoàn tiền không còn ở trạng thái xử lý.');
                }$completed = (float) Refund::where('payment_id', $locked->payment_id)->where('status', 'COMPLETED')->sum('amount');
                if ($data['result'] === 'COMPLETED' && $completed + (float) $locked->amount > (float) $locked->payment->amount) {
                    throw new \DomainException('Tổng số tiền hoàn vượt quá số tiền khách đã thanh toán.');
                }$locked->update(['status' => $data['result'], 'processed_at' => now()]);
                $old = $locked->payment->status;
                if ($data['result'] === 'COMPLETED' && $completed + (float) $locked->amount >= (float) $locked->payment->amount) {
                    $locked->payment->update(['status' => 'REFUNDED']);
                    $locked->payment->booking->update(['payment_status' => 'REFUNDED']);
                }$this->log($locked->payment, $request, 'REFUND_'.$data['result'], $old, $locked->payment->fresh()->status, $locked->amount, $data['note']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

return back()->with('success', 'Đã cập nhật kết quả hoàn tiền.');
    }

    private function log(Payment $payment, Request $request, string $action, ?string $old, ?string $new, $amount, string $note): void
    {
        PaymentTransactionLog::create(['payment_id' => $payment->id, 'actor_id' => $request->user()->id, 'action' => $action, 'old_status' => $old, 'new_status' => $new, 'amount' => $amount, 'note' => $note, 'metadata' => ['ip' => $request->ip()]]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN',403);
    }
}

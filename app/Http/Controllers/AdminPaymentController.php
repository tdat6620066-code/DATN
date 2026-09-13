<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentTransactionLog;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments
    ) {}

    public function index(Request $request)
    {
        $this->admin($request);
        $payments = Payment::with(['booking.user', 'fixedBooking.user'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search
                ->where('transaction_id', 'like', '%'.$request->search.'%')
                ->orWhereHas('booking', fn ($b) => $b->where('booking_code', 'like', '%'.$request->search.'%'))
                ->orWhereHas('fixedBooking', fn ($f) => $f->where('code', 'like', '%'.$request->search.'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('refund_status'), fn ($q) => $q->where('refund_status', $request->refund_status))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment, Request $request)
    {
        $this->admin($request);
        $payment->load(['booking.user', 'fixedBooking.user', 'booking.bookingDetails.court', 'transactionLogs.actor']);

        return view('admin.payments.show', compact('payment'));
    }

    public function reconcile(Payment $payment, Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['action' => ['required', Rule::in(['MARK_PAID', 'MARK_FAILED'])], 'amount' => ['required', 'numeric', 'min:0'], 'transaction_id' => ['nullable', 'string', 'max:255'], 'note' => ['required', 'string', 'max:1000']]);
        try {
            DB::transaction(function () use ($payment, $request, $data) {
                $locked = Payment::with('booking')->lockForUpdate()->findOrFail($payment->id);
                if ($locked->hasRefundActivity()) {
                    throw new \DomainException('Giao dịch đã hoàn tiền và không thể đối chiếu lại.');
                }if ((float) $data['amount'] !== (float) ($locked->purpose === 'SERVICE' ? $locked->amount : ($locked->fixed_booking_id ? $locked->fixedBooking->total_price : $locked->booking->total_amount))) {
                    throw new \DomainException('Số tiền giao dịch không khớp tổng tiền booking.');
                }$old = $locked->status;
                $new = $data['action'] === 'MARK_PAID' ? 'PAID' : 'FAILED';
                $locked->update(['amount' => $data['amount']]);
                if ($new === 'PAID') {
                    $this->payments->markAsPaid($locked, $data['transaction_id'], 'ADMIN_RECONCILIATION');
                } else {
                    $this->payments->markAsFailed($locked, $data['transaction_id']);
                }
                $this->log($locked, $request, 'RECONCILED', $old, $new, $data['amount'], $data['note']);
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã đối chiếu và cập nhật giao dịch.');
    }

    private function log(Payment $payment, Request $request, string $action, ?string $old, ?string $new, $amount, string $note): void
    {
        PaymentTransactionLog::create(['payment_id' => $payment->id, 'actor_id' => $request->user()->id, 'action' => $action, 'old_status' => $old, 'new_status' => $new, 'amount' => $amount, 'note' => $note, 'metadata' => ['ip' => $request->ip()]]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'ADMIN', 403);
    }
}

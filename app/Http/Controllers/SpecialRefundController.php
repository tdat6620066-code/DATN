<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\IncidentResolution;
use App\Models\Payment;
use App\Models\PaymentTransactionLog;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Services\CustomerNotificationService;
use App\Services\IncidentTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SpecialRefundController extends Controller
{
    public function index()
    {
        $items = RefundRequest::with(['booking.user', 'booking.payment', 'requester'])->whereIn('status', ['PENDING', 'APPROVED'])->whereDoesntHave('refund')->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")->latest()->paginate(20);

        return view('admin.incidents.refunds', compact('items'));
    }

    public function store(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'reason_code' => ['required', Rule::in(array_keys(RefundRequest::REASONS))],
            'reason' => ['required', 'string', 'max:2000'],
            'supporting_information' => ['required', 'string', 'max:4000'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'refund_type' => ['sometimes', Rule::in(['FULL', 'PARTIAL'])],
            'approve_now' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($booking, $request, $data) {
            $locked = Booking::lockForUpdate()->findOrFail($booking->id);
            $payment = Payment::forBooking($locked)->lockForUpdate()->first();
            if ($locked->incidentResolutions()->exists()) {
                throw ValidationException::withMessages(['amount' => 'Booking có sự cố: xử lý theo lựa chọn của khách bên dưới.']);
            }
            $this->checkAmount($locked, $payment, $data['amount']);
            $full = round((float) $data['amount'] * 100) === round((float) $locked->total_amount * 100);
            if (isset($data['refund_type']) && (($data['refund_type'] === 'FULL') !== $full)) {
                throw ValidationException::withMessages(['amount' => 'Hoàn toàn bộ phải bằng số tiền đã thanh toán; hoàn một phần phải nhỏ hơn.']);
            }
            $approve = ! empty($data['approve_now']);
            abort_if($approve && $request->user()->role !== 'ADMIN', 403);
            unset($data['refund_type'], $data['approve_now']);
            if ($locked->refundRequests()->whereIn('status', ['PENDING', 'NEEDS_INFO', 'APPROVED'])->exists()) {
                throw ValidationException::withMessages(['amount' => 'Đơn đã có yêu cầu hoàn tiền đang xử lý hoặc đã hoàn tất.']);
            }
            $item = $locked->refundRequests()->create($data + ['requested_by' => $request->user()->id, 'status' => $approve ? 'APPROVED' : 'PENDING', 'reviewed_by' => $approve ? $request->user()->id : null, 'reviewed_at' => $approve ? now() : null, 'decision_note' => $approve ? $data['reason'] : null]);
            app(CustomerNotificationService::class)->refundProgress($item, $approve ? 'APPROVED' : 'PENDING');
        });

        return back()->with('success', $request->boolean('approve_now') ? 'Đã phê duyệt hoàn tiền, đang chờ chuyển tiền cho khách.' : 'Đã tạo yêu cầu hoàn tiền đặc biệt, chờ Admin xác nhận sự cố.');
    }

    public function review(Request $request, RefundRequest $refundRequest)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'decision_note' => ['required', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0'],
        ]);
        DB::transaction(function () use ($refundRequest, $request, $data) {
            $booking = Booking::lockForUpdate()->findOrFail($refundRequest->booking_id);
            $payment = Payment::forBooking($booking)->lockForUpdate()->first();
            $item = RefundRequest::lockForUpdate()->findOrFail($refundRequest->id);
            if ($item->status !== 'PENDING' || ! isset(RefundRequest::REASONS[$item->reason_code])) {
                throw ValidationException::withMessages(['decision' => 'Yêu cầu không còn chờ duyệt hoặc không phải hoàn tiền đặc biệt.']);
            }
            if ($data['decision'] === 'APPROVED') {
                if (isset($data['amount'])) {
                    if ((float) $data['amount'] > (float) $item->amount) {
                        throw ValidationException::withMessages(['amount' => 'Không vượt số tiền đã được xác minh/đề nghị.']);
                    }
                    $item->amount = $data['amount'];
                }
                $this->checkAmount($booking, $payment, $item->amount, (bool) $item->incident_resolution_id);
            }
            $item->update(['status' => $data['decision'], 'decision_note' => $data['decision_note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            app(CustomerNotificationService::class)->refundProgress($item, $data['decision']);
            if ($data['decision'] === 'REJECTED' && $item->incident_resolution_id) {
                $resolution = IncidentResolution::findOrFail($item->incident_resolution_id);
                $resolution->update(['status' => $resolution->choice === 'REFUND' ? 'AWAITING_CHOICE' : 'RESOLVED', 'resolved_at' => $resolution->choice === 'REFUND' ? null : now()]);
            }
        });

        return back()->with('success', 'Đã lưu quyết định của Admin.');
    }

    public function complete(Request $request, RefundRequest $refundRequest, CustomerNotificationService $notifications)
    {
        $data = $request->validate(['refund_code' => ['required', 'string', 'max:100'], 'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'], 'processing_note' => ['nullable', 'string', 'max:2000'], 'receipt_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']], [
            'receipt_image.required' => 'Vui lòng tải ảnh giao dịch thành công hoặc biên nhận chi trả.',
            'receipt_image.image' => 'Minh chứng chi trả phải là ảnh.',
            'receipt_image.mimes' => 'Chọn ảnh JPG, PNG hoặc WebP.',
            'receipt_image.max' => 'Ảnh chi trả không được vượt quá 10MB.',
        ]);
        $receiptPath = null;
        try {
            DB::transaction(function () use ($refundRequest, $request, $data, $notifications, &$receiptPath) {
                $booking = Booking::lockForUpdate()->findOrFail($refundRequest->booking_id);
                $payment = Payment::forBooking($booking)->lockForUpdate()->first();
                $item = RefundRequest::lockForUpdate()->findOrFail($refundRequest->id);
                if ($item->status !== 'APPROVED' || ! isset(RefundRequest::REASONS[$item->reason_code]) || $item->refund()->exists()) {
                    throw ValidationException::withMessages(['refund_code' => 'Yêu cầu chưa được duyệt hoặc đã ghi nhận hoàn tiền.']);
                }
                $this->checkAmount($booking, $payment, $item->amount, (bool) $item->incident_resolution_id);
                if (round((float) $data['amount'] * 100) !== round((float) $item->amount * 100)) {
                    throw ValidationException::withMessages(['amount' => 'Số tiền thực hoàn phải khớp số tiền Admin đã duyệt.']);
                }
                if (Refund::where('refund_code', $data['refund_code'])->exists()) {
                    throw ValidationException::withMessages(['refund_code' => 'Mã giao dịch hoàn tiền đã được sử dụng.']);
                }
                if (! $item->processing_started_at || ! in_array($item->refund_method, ['BANK_TRANSFER', 'CASH'], true)) {
                    throw ValidationException::withMessages(['refund_code' => 'Chọn phương thức và xác nhận xử lý trước khi ghi nhận đã hoàn tiền.']);
                }
                $receiptPath = $request->file('receipt_image')->store('refund-receipts', 'local');
                if (! $receiptPath) throw new \RuntimeException('Không thể lưu ảnh chi trả. Vui lòng thử lại.');
                Refund::create(['receipt_path' => $receiptPath, 'refund_request_id' => $item->id, 'payment_id' => $payment->id, 'refund_code' => $data['refund_code'], 'amount' => $item->amount, 'status' => 'COMPLETED', 'processed_at' => now(), 'refund_method' => $item->refund_method, 'processed_by' => $request->user()->id, 'processing_note' => $data['processing_note'] ?? null]);
                $old = $booking->status;
                $newPaymentStatus = round((float) $payment->refunds()->where('status', 'COMPLETED')->sum('amount') * 100) >= round((float) $payment->amount * 100) ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
                $oldPaymentStatus = $payment->status;
                $payment->update(['refunded_amount' => $payment->refunds()->where('status', 'COMPLETED')->sum('amount'), 'refund_status' => $newPaymentStatus]);
                $childRefunded = $booking->refunds()->where('refunds.status', 'COMPLETED')->sum('refunds.amount');
                $booking->update(['payment_status' => round((float) $childRefunded * 100) >= round((float) $booking->total_amount * 100) ? 'REFUNDED' : 'PARTIALLY_REFUNDED']);
                if ($item->cancel_booking) {
                    $booking->update(['status' => 'CANCELLED', 'cancelled_at' => now(), 'hold_expires_at' => null]);
                    $booking->bookingDetails()->where('status', '!=', 'COMPLETED')->update(['status' => 'CANCELLED']);
                }
                if ($item->incident_resolution_id) {
                    IncidentResolution::whereKey($item->incident_resolution_id)->update(['status' => 'RESOLVED', 'resolved_at' => now()]);
                    app(IncidentTicketService::class)->closeIfResolved(IncidentResolution::findOrFail($item->incident_resolution_id));
                }
                app(IncidentTicketService::class)->closeAfterDirectRefund($item);
                PaymentTransactionLog::create(['payment_id' => $payment->id, 'actor_id' => $request->user()->id, 'action' => 'SPECIAL_REFUND', 'old_status' => $oldPaymentStatus, 'new_status' => $payment->status, 'amount' => $item->amount, 'note' => $item->reason, 'metadata' => ['refund_request_id' => $item->id, 'refund_code' => $data['refund_code'], 'refund_status' => $newPaymentStatus]]);
                BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => 'SPECIAL_REFUND', 'old_values' => ['status' => $old], 'new_values' => ['status' => $booking->status, 'refund_amount' => $item->amount], 'reason' => $item->reason, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
                $notifications->refunded($booking, (float) $item->amount, $item->id);
            });
        } catch (\Throwable $e) {
            if ($receiptPath) \Illuminate\Support\Facades\Storage::disk('local')->delete($receiptPath);
            throw $e;
        }

        return back()->with('success', 'Đã ghi nhận hoàn tiền và thông báo cho khách.');
    }

    public function processing(Request $request, RefundRequest $refundRequest, CustomerNotificationService $notifications)
    {
        $data = $request->validate(['refund_method' => ['required', Rule::in(['BANK_TRANSFER', 'CASH'])]]);
        DB::transaction(function () use ($refundRequest, $notifications, $data) {
            $booking = Booking::lockForUpdate()->findOrFail($refundRequest->booking_id);
            $payment = Payment::forBooking($booking)->lockForUpdate()->first();
            $item = RefundRequest::lockForUpdate()->findOrFail($refundRequest->id);
            if ($item->status !== 'APPROVED' || $item->refund()->exists()) {
                throw ValidationException::withMessages(['refund_code' => 'Yêu cầu chưa được duyệt hoặc đã xử lý hoàn tiền.']);
            }
            $this->checkAmount($booking, $payment, $item->amount, (bool) $item->incident_resolution_id);
            if ($item->refund_method && $item->refund_method !== $data['refund_method']) {
                throw ValidationException::withMessages(['refund_method' => 'Phương thức đã khóa khi bắt đầu xử lý.']);
            }
            if ($data['refund_method'] === 'BANK_TRANSFER' && (! $item->bank_name || ! $item->bank_account_number || ! $item->bank_account_holder)) {
                throw ValidationException::withMessages(['refund_method' => 'Khách cần cung cấp đầy đủ thông tin nhận chuyển khoản.']);
            }
            if ($data['refund_method'] === 'BANK_TRANSFER' && ! $item->processing_started_at && $item->needsBankConfirmation()) {
                throw ValidationException::withMessages(['refund_method' => 'Khách cần xác nhận lại tài khoản nhận tiền trước khi chuyển khoản.']);
            }
            if (! $item->processing_started_at || ! $item->refund_method) {
                $item->forceFill(['processing_started_at' => $item->processing_started_at ?? now(), 'refund_method' => $data['refund_method']])->save();
                $notifications->refundProgress($item, 'PROCESSING');
            }
        });

        return back()->with('success', 'Đã thông báo khách rằng khoản hoàn đang được xử lý. Chỉ xác nhận hoàn tất sau khi chuyển tiền thành công.');
    }

    private function checkAmount(Booking $booking, ?Payment $payment, mixed $amount, bool $incident = false): void
    {
        $remaining = $payment ? round((float) $payment->amount * 100) - round((float) $payment->refunds()->whereIn('status', ['PROCESSING', 'COMPLETED'])->sum('amount') * 100) : 0;
        $remaining = min($remaining, round((float) $booking->total_amount * 100) - round((float) $booking->refunds()->whereIn('refunds.status', ['PROCESSING', 'COMPLETED'])->sum('refunds.amount') * 100));
        if (! $payment || ! in_array($payment->status, ['PAID', 'PARTIALLY_REFUNDED'], true) || ! in_array($booking->payment_status, ['PAID', 'PARTIALLY_REFUNDED'], true) || (! $incident && in_array($booking->status, ['CANCELLED', 'EXPIRED'], true)) || round((float) $amount * 100) <= 0 || round((float) $amount * 100) > $remaining) {
            throw ValidationException::withMessages(['amount' => 'Đơn phải đã thanh toán; số tiền hoàn phải lớn hơn 0 và không vượt số tiền còn được hoàn.']);
        }
    }
}

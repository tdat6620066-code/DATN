<?php

namespace App\Services;

use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\RefundRequestStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundRequestService
{
    public function review(RefundRequest $refundRequest, User $employee, array $data): RefundRequest
    {
        return DB::transaction(function () use ($refundRequest, $employee, $data) {
            $request = RefundRequest::query()
                ->with(['booking.payment', 'requester'])
                ->lockForUpdate()
                ->findOrFail($refundRequest->id);

            if (! in_array($request->status, ['PENDING', 'NEEDS_INFO'], true)) {
                throw new \DomainException('Yêu cầu này đã được xử lý.');
            }

            $decision = $data['decision'];
            if ($decision === 'NEEDS_INFO') {
                $this->requireValue($data, 'requested_information', 'Phải ghi rõ thông tin khách hàng cần bổ sung.');
            } else {
                $this->requireValue($data, 'decision_note', 'Phải nhập ghi chú quyết định.');
            }

            if ($decision === 'APPROVED') {
                $payment = $request->booking->payment;
                if (! $payment || $payment->status !== 'PAID') {
                    throw new \DomainException('Booking không có khoản thanh toán hợp lệ để hoàn tiền.');
                }
                if ((float) $request->amount > (float) $payment->amount) {
                    throw new \DomainException('Số tiền hoàn vượt quá số tiền đã thanh toán.');
                }
                if ($employee->role !== 'ADMIN' && (float) $request->amount > (float) $employee->refund_approval_limit) {
                    abort(403, 'Yêu cầu vượt hạn mức phê duyệt của nhân viên.');
                }
            }

            $request->update([
                'status' => $decision,
                'reviewed_by' => $employee->id,
                'decision_note' => $data['decision_note'] ?? null,
                'requested_information' => $data['requested_information'] ?? null,
                'reviewed_at' => now(),
            ]);

            if ($decision === 'APPROVED') {
                Refund::create([
                    'refund_request_id' => $request->id,
                    'payment_id' => $request->booking->payment->id,
                    'refund_code' => 'RF-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'amount' => $request->amount,
                    'status' => 'PROCESSING',
                ]);
            }

            $request->requester->notify(new RefundRequestStatusChanged($request));

            return $request->fresh(['booking.payment', 'requester', 'reviewer', 'refund']);
        });
    }

    private function requireValue(array $data, string $key, string $message): void
    {
        if (blank($data[$key] ?? null)) {
            throw new \DomainException($message);
        }
    }
}

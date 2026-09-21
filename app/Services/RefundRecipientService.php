<?php

namespace App\Services;

use App\Models\RefundRequest;

class RefundRecipientService
{
    public function restoreFromBookingTicket(RefundRequest $request): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $item = RefundRequest::lockForUpdate()->findOrFail($request->id);
            if ($item->bankAccount()->exists() || $item->processing_started_at || $item->refund()->exists()
                || !in_array($item->status, ['PENDING', 'APPROVED', 'NEEDS_INFO'], true)) return;
            $tickets = \App\Models\CourtIncident::where('active_booking_id', $item->booking_id)
                ->where('source', 'CUSTOMER')->where('customer_id', $item->booking->user_id)
                ->where('requested_solution', 'REFUND')->whereNotNull('refund_recipient')->get();
            // Never guess between several submitted recipient instructions.
            if ($tickets->count() !== 1) return;
            $data = $tickets->first()->refund_recipient;
            if (validator($data, self::rules() + ['confirmed_at' => ['required', 'date']])->fails()) return;
            $this->save($item, $data, $data['confirmed_at']);
        });
        $request->unsetRelation('bankAccount');
    }

    public static function rules(): array
    {
        return [
            'bank_code' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'regex:/^[0-9]{6,34}$/'],
            'bank_account_holder' => ['required', 'string', 'max:150'],
            'recipient_confirmed' => ['accepted'],
        ];
    }

    public function save(RefundRequest $item, array $data, ?string $confirmedAt = null): void
    {
        $item->bankAccount()->updateOrCreate([], [
            'bank_code' => $data['bank_code'] ?? null,
            'bank_name' => $data['bank_name'],
            'account_number' => $data['bank_account_number'],
            'account_name' => $data['bank_account_holder'],
            'account_last4' => substr($data['bank_account_number'], -4),
            'confirmed_at' => $confirmedAt ?? now(),
        ]);
        $item->unsetRelation('bankAccount');
    }
}

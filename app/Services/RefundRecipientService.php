<?php

namespace App\Services;

use App\Models\RefundRequest;

class RefundRecipientService
{
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

    public function save(RefundRequest $item, array $data): void
    {
        $item->bankAccount()->updateOrCreate([], [
            'bank_code' => $data['bank_code'] ?? null,
            'bank_name' => $data['bank_name'],
            'account_number' => $data['bank_account_number'],
            'account_name' => $data['bank_account_holder'],
            'account_last4' => substr($data['bank_account_number'], -4),
            'confirmed_at' => now(),
        ]);
        $item->unsetRelation('bankAccount');
    }
}

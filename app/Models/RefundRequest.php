<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    public const REASONS = [
        'WEATHER' => 'Mưa bão, thiên tai',
        'COURT_FAILURE' => 'Sân hỏng, mất điện, thiết bị không đảm bảo',
        'POWER_FAILURE' => 'Mất điện / thiết bị hỏng',
        'COURT_CLOSED' => 'Sân đóng đột xuất',
        'STAFF_CANCELLED' => 'Sân chủ động hủy lịch',
        'SERVICE_INTERRUPTED' => 'Dịch vụ bị gián đoạn do lỗi sân',
        'OTHER_FORCE_MAJEURE' => 'Bất khả kháng khác',
    ];

    protected $fillable = [
        'booking_id', 'requested_by', 'reviewed_by', 'amount', 'reason',
        'supporting_information', 'status', 'decision_note',
        'requested_information', 'reviewed_at', 'reason_code', 'incident_resolution_id', 'cancel_booking', 'processing_started_at',
    ];

    protected $hidden = ['bank_name', 'bank_account_number', 'bank_account_holder', 'bankAccount'];

    protected $casts = ['amount' => 'decimal:2', 'reviewed_at' => 'datetime', 'processing_started_at' => 'datetime'];

    public function bankAccount()
    {
        return $this->hasOne(RefundBankAccount::class);
    }

    public function getBankNameAttribute()
    {
        return $this->bankAccount?->bank_name;
    }

    public function getBankAccountNumberAttribute()
    {
        return $this->bankAccount?->account_number;
    }

    public function getBankAccountHolderAttribute()
    {
        return $this->bankAccount?->account_name;
    }

    public function getBankAccountLast4Attribute()
    {
        return $this->bankAccount?->account_last4;
    }

    public function getPayoutStatusAttribute(): string
    {
        if ($this->status === 'APPROVED' && ! $this->processing_started_at && ! $this->refund && $this->needsBankConfirmation()) return 'WAITING_BANK_CONFIRMATION';
        return $this->refund?->status === 'COMPLETED' ? 'REFUNDED' : ($this->processing_started_at ? 'PROCESSING' : $this->status);
    }

    public function needsBankConfirmation(): bool
    {
        return ! $this->bankAccount?->confirmed_at || $this->bankAccount->confirmed_at->lte(now()->subHours(config('refunds.bank_confirmation_hours', 24)));
    }

    public function getPayoutLabelAttribute(): string
    {
        if ($this->payout_status === 'WAITING_BANK_CONFIRMATION') return 'Chờ xác nhận tài khoản nhận tiền';
        return ['PENDING' => 'Chờ Admin duyệt', 'NEEDS_INFO' => 'Chờ bổ sung thông tin', 'APPROVED' => 'Đã phê duyệt', 'PROCESSING' => 'Đang xử lý hoàn tiền', 'REFUNDED' => 'Đã hoàn tiền', 'REJECTED' => 'Đã từ chối'][$this->payout_status] ?? $this->payout_status;
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function incidentResolution()
    {
        return $this->belongsTo(IncidentResolution::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function refund()
    {
        return $this->hasOne(Refund::class);
    }
}

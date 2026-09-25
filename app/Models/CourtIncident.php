<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtIncident extends Model
{
    public const TYPES = ['WEATHER' => 'Thời tiết / thiên tai', 'COURT_FAILURE' => 'Sân không thể sử dụng', 'POWER_FAILURE' => 'Mất điện / thiết bị hỏng', 'COURT_CLOSED' => 'Sân đóng cửa', 'SERVICE_INTERRUPTED' => 'Dịch vụ bị gián đoạn', 'OTHER_FORCE_MAJEURE' => 'Khác'];

    public const SOLUTIONS = ['REFUND' => 'Hoàn tiền', 'RESCHEDULE' => 'Đổi lịch', 'CHANGE_COURT' => 'Đổi sân', 'CONTACT_ME' => 'Nhân viên liên hệ'];
    public const AVAILABLE_SOLUTIONS = ['REFUND' => 'Hoàn tiền'];

    public const TICKET_STATUSES = ['PENDING' => 'Chờ xử lý', 'REVIEWING' => 'Đang xem xét', 'NEED_MORE_INFO' => 'Cần bổ sung thông tin', 'APPROVED' => 'Đã xác minh / đang xử lý', 'REJECTED' => 'Đã từ chối', 'RESOLVED' => 'Đã xử lý'];

    protected $fillable = ['incident_code', 'court_id', 'reported_by', 'type', 'severity', 'description', 'images', 'status', 'resolution_note', 'resolved_at', 'source', 'booking_id', 'booking_detail_id', 'customer_id', 'active_booking_id', 'requested_solution', 'proposed_solution', 'proposed_amount', 'assigned_to', 'reviewed_by', 'review_note', 'reviewed_at'];

    protected $hidden = ['refund_recipient'];

    protected $casts = ['refund_recipient' => 'encrypted:array', 'images' => 'array', 'resolved_at' => 'datetime', 'booking_snapshot' => 'array', 'reviewed_at' => 'datetime', 'proposed_amount' => 'decimal:2'];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function detail()
    {
        return $this->belongsTo(BookingDetail::class, 'booking_detail_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function evidences()
    {
        return $this->hasMany(IncidentEvidence::class, 'incident_id');
    }

    public function updates()
    {
        return $this->hasMany(IncidentUpdate::class, 'incident_id');
    }

    public function resolutions()
    {
        return $this->hasMany(IncidentResolution::class, 'court_incident_id');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\Court;
use App\Models\CourtIncident;
use App\Models\IncidentResolution;
use App\Models\MaintenanceSchedule;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Services\CustomerNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BulkIncidentController extends Controller
{
    public function create()
    {
        return view('admin.incidents.bulk', ['courts' => Court::orderBy('name')->get()]);
    }

    public function store(Request $request, CustomerNotificationService $notifications)
    {
        $data = $request->validate([
            'court_id' => ['required', 'exists:courts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason_code' => ['required', Rule::in(array_keys(RefundRequest::REASONS))],
            'reason' => ['required', 'string', 'max:2000'],
            'notify_customers' => ['sometimes', 'boolean'],
            'confirm' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($request, $data, $notifications) {
            $court = Court::lockForUpdate()->findOrFail($data['court_id']);
            $bookings = Booking::with(['user', 'payment', 'bookingDetails.timeSlot'])->whereIn('status', ['PENDING_PAYMENT', 'CONFIRMED', 'CHECKED_IN'])
                ->whereHas('bookingDetails', fn ($q) => $q->where('court_id', $court->id)->whereDate('booking_date', $data['date'])
                    ->whereNotIn('status', ['CANCELLED', 'COMPLETED'])
                    ->whereHas('timeSlot', fn ($s) => $s->where('start_time', '<', $data['end_time'].':00')->where('end_time', '>', $data['start_time'].':00')))
                ->orderBy('id')->lockForUpdate()->get();
            if (empty($data['confirm'])) {
                $token = (string) Str::uuid();
                $request->session()->put('bulk_incident_preview', ['token' => $token, 'data' => $data, 'ids' => $bookings->modelKeys()]);

                return view('admin.incidents.bulk', ['courts' => Court::orderBy('name')->get(), 'bookings' => $bookings, 'data' => $data, 'token' => $token]);
            }
            $preview = $request->session()->get('bulk_incident_preview');
            $original = $data;
            unset($original['confirm']);
            if (! $preview || $preview['token'] !== $request->input('preview_token') || $preview['data'] != $original || $preview['ids'] !== $bookings->modelKeys()) {
                throw ValidationException::withMessages(['confirm' => 'Danh sách đã thay đổi hoặc phiên xác nhận hết hạn. Vui lòng xem trước lại.']);
            }
            $incident = CourtIncident::create(['incident_code' => 'INC-'.Str::uuid(), 'court_id' => $court->id, 'reported_by' => $request->user()->id, 'type' => $data['reason_code'], 'severity' => 'CRITICAL', 'status' => 'IN_PROGRESS', 'description' => $data['date'].' '.$data['start_time'].'–'.$data['end_time'].': '.$data['reason']]);
            MaintenanceSchedule::create(['court_id' => $court->id, 'maintenance_date' => $data['date'], 'start_date' => $data['date'], 'end_date' => $data['date'], 'start_time' => $data['start_time'], 'end_time' => $data['end_time'], 'reason' => $incident->incident_code.': '.$data['reason'], 'status' => 'SCHEDULED']);
            foreach ($bookings as $booking) {
                $payment = Payment::forBooking($booking)->lockForUpdate()->first();
                if ($booking->refundRequests()->whereIn('status', ['PENDING', 'NEEDS_INFO', 'APPROVED'])->where('cancel_booking', true)->exists()) {
                    throw ValidationException::withMessages(['confirm' => "Đơn {$booking->booking_code} đã có yêu cầu hoàn tiền. Hãy xử lý yêu cầu đó trước."]);
                }
                $paid = in_array($payment?->status, ['PAID', 'PARTIALLY_REFUNDED'], true);
                if (in_array($booking->payment_status, ['PAID', 'PARTIALLY_REFUNDED'], true) !== $paid || ($payment && ! in_array($payment->status, ['PAID', 'PARTIALLY_REFUNDED', 'PENDING', 'FAILED'], true)) || ($paid && (float) $booking->total_amount <= 0)) {
                    throw ValidationException::withMessages(['confirm' => "Thanh toán đơn {$booking->booking_code} cần đối soát trước."]);
                }
                foreach ($booking->bookingDetails as $detail) {
                    if ($detail->court_id !== $court->id || $detail->booking_date->toDateString() !== $data['date'] || in_array($detail->status, ['CANCELLED', 'COMPLETED'], true)) {
                        continue;
                    }
                    $start = Carbon::parse($data['date'].' '.$detail->timeSlot->start_time);
                    $end = Carbon::parse($data['date'].' '.$detail->timeSlot->end_time);
                    $incidentStart = Carbon::parse($data['date'].' '.$data['start_time']);
                    if ($start >= Carbon::parse($data['date'].' '.$data['end_time']) || $end <= $incidentStart) {
                        continue;
                    }
                    if ($paid) {
                        $base = max((float) $booking->subtotal, (float) $booking->bookingDetails->sum('subtotal'), 1);
                        $unused = $detail->status === 'CHECKED_IN' ? min(1, max(0, $incidentStart->diffInSeconds($end, false) / max(1, $start->diffInSeconds($end)))) : 1;
                        $previousRefunds = RefundRequest::whereIn('incident_resolution_id', IncidentResolution::where('booking_detail_id', $detail->id)->select('id'))->whereIn('status', ['PENDING', 'NEEDS_INFO', 'APPROVED'])->sum('amount');
                        $amount = floor(max(0, min((float) $detail->subtotal, (float) $booking->total_amount * (float) $detail->subtotal / $base) - $previousRefunds) * $unused * 100) / 100;
                        IncidentResolution::create(['court_incident_id' => $incident->id, 'booking_id' => $booking->id, 'booking_detail_id' => $detail->id, 'refund_amount' => $amount, 'original_slot' => ['court_id' => $court->id, 'court' => $court->name, 'date' => $data['date'], 'time_slot_id' => $detail->time_slot_id, 'start_time' => $detail->timeSlot->start_time, 'end_time' => $detail->timeSlot->end_time, 'subtotal' => $detail->subtotal, 'unused_ratio' => $unused]]);
                    }
                    $detail->update(['status' => 'CANCELLED']);
                }
                if (! $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->exists()) {
                    $booking->update(['status' => 'CANCELLED', 'cancelled_at' => now(), 'hold_expires_at' => null]);
                }
                BookingAuditLog::create(['booking_id' => $booking->id, 'actor_id' => $request->user()->id, 'action' => 'BULK_INCIDENT', 'reason' => $incident->description, 'new_values' => ['incident_id' => $incident->id, 'refund_pending' => $paid], 'ip_address' => $request->ip()]);
                if (! empty($data['notify_customers'])) {
                    $notifications->incidentAffected($booking, $incident->incident_code, $incident->description, $paid);
                }
            }
            $request->session()->forget('bulk_incident_preview');

            return redirect()->route($request->user()->role === 'ADMIN' ? 'admin.incidents.index' : 'employee.incidents.index')->with('success', 'Đã khóa khung giờ, hủy các lượt bị ảnh hưởng và mời khách chọn hoàn tiền, đổi lịch hoặc đổi sân.');
        });
    }
}

<?php

namespace App\Services;

use App\Events\CustomerNotificationCreated;
use App\Models\CourtIncident;
use App\Models\IncidentResolution;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class IncidentTicketService
{
    public function closeAfterDirectRefund(RefundRequest $request): void
    {
        $refund = $request->refund;
        if ($request->incident_resolution_id || ! $request->cancel_booking || $refund?->status !== 'COMPLETED' || $request->booking->status !== 'CANCELLED') {
            return;
        }

        $tickets = CourtIncident::where('source', 'CUSTOMER')
            ->where('booking_id', $request->booking_id)
            ->where('requested_solution', 'REFUND')
            ->whereIn('status', ['PENDING', 'REVIEWING', 'NEED_MORE_INFO', 'APPROVED'])
            ->whereDoesntHave('resolutions')
            ->lockForUpdate()->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['status' => 'RESOLVED', 'resolved_at' => $refund->processed_at ?? now(), 'active_booking_id' => null]);
            $ticket->updates()->create([
                'actor_id' => $refund->processed_by ?? $request->reviewed_by ?? $request->requested_by,
                'status' => 'RESOLVED',
                'event_type' => 'RESOLVED',
                'note' => 'Đã hoàn tiền đặc biệt theo yêu cầu #'.$request->id.' và hủy booking. Mã hoàn tiền: '.$refund->refund_code,
            ]);
        }
    }

    public function notify(CourtIncident $ticket, string $message, bool $staff): void
    {
        $recipients = $staff ? User::whereIn('role', ['ADMIN', 'EMPLOYEE'])->get()->filter(fn ($u) => $u->role === 'ADMIN' || ($u->hasPermission('incidents.manage') && (! $ticket->assigned_to || $u->id === $ticket->assigned_to))) : collect([$ticket->reporter]);
        foreach ($recipients as $user) {
            $notification = Notification::create(['user_id' => $user->id, 'booking_id' => $ticket->booking_id, 'type' => 'BOOKING_STATUS', 'title' => 'Sự cố / hỗ trợ booking', 'content' => $ticket->incident_code.' · '.$ticket->booking->booking_code.' · '.$ticket->reporter->name.' · '.$ticket->court->name.' · '.($ticket->booking_snapshot['date'] ?? $ticket->detail->booking_date->toDateString()).' '.($ticket->booking_snapshot['start_time'] ?? $ticket->detail->timeSlot->start_time).'–'.($ticket->booking_snapshot['end_time'] ?? $ticket->detail->timeSlot->end_time).' · '.(CourtIncident::TYPES[$ticket->type] ?? $ticket->type).' · '.CourtIncident::SOLUTIONS[$ticket->requested_solution].'. '.$message, 'action_url' => route('incident-tickets.show', $ticket), 'is_read' => false]);
            CustomerNotificationCreated::dispatch($notification);
        }
    }

    public function approve(CourtIncident $ticket, User $admin, float $amount): void
    {
        if ($ticket->requested_solution === 'CONTACT_ME') {
            return;
        }
        $booking = $ticket->booking;
        $payment = Payment::forBooking($booking)->lockForUpdate()->first();
        $detail = $ticket->detail()->lockForUpdate()->firstOrFail();
        $snapshot = $ticket->booking_snapshot;
        if ($snapshot && ($snapshot['court_id'] !== $detail->court_id || $snapshot['date'] !== $detail->booking_date->toDateString() || $snapshot['time_slot_id'] !== $detail->time_slot_id)) {
            throw ValidationException::withMessages(['amount' => 'Lượt sân đã thay đổi sau khi gửi ticket; hãy xác minh lại và liên hệ khách.']);
        }
        if (! $payment || $payment->status !== 'PAID' || $detail->status === 'CANCELLED' || $ticket->resolutions()->exists() || $booking->refundRequests()->where('cancel_booking', true)->whereIn('status', ['PENDING', 'NEEDS_INFO', 'APPROVED'])->exists()) {
            throw ValidationException::withMessages(['amount' => 'Booking chưa thanh toán hoặc lượt này đã có phương án xử lý.']);
        }
        $reserved = $booking->refundRequests()->whereIn('status', ['PENDING', 'NEEDS_INFO', 'APPROVED'])->sum('amount');
        $base = max((float) $booking->subtotal, (float) $booking->bookingDetails()->sum('subtotal'), 1);
        $maximum = min((float) $booking->total_amount - $reserved, (float) $detail->subtotal, (float) $booking->total_amount * (float) $detail->subtotal / $base);
        if ($amount <= 0 || round($amount * 100) > round($maximum * 100)) {
            throw ValidationException::withMessages(['amount' => 'Số tiền phải lớn hơn 0, không vượt giá trị lượt sân và số tiền còn được hoàn.']);
        }
        $slot = $detail->timeSlot;
        $resolution = IncidentResolution::create(['court_incident_id' => $ticket->id, 'booking_id' => $booking->id, 'booking_detail_id' => $detail->id, 'refund_amount' => $amount, 'original_slot' => ['court_id' => $detail->court_id, 'court' => $detail->court->name, 'date' => $detail->booking_date->toDateString(), 'time_slot_id' => $detail->time_slot_id, 'start_time' => $slot->start_time, 'end_time' => $slot->end_time, 'subtotal' => $detail->subtotal, 'unused_ratio' => min(1, $amount / max(0.01, min((float) $detail->subtotal, (float) $booking->total_amount * (float) $detail->subtotal / $base)))]]);
        $detail->update(['status' => 'CANCELLED']);
        if (! $booking->bookingDetails()->where('status', '!=', 'CANCELLED')->exists()) {
            $booking->update(['status' => 'CANCELLED', 'cancelled_at' => now(), 'hold_expires_at' => null]);
        }

        if ($ticket->requested_solution === 'REFUND' && ($ticket->proposed_solution ?? 'REFUND') === 'REFUND' && $ticket->refund_recipient) {
            $request = $resolution->refundRequests()->create([
                'booking_id' => $booking->id, 'requested_by' => $ticket->reported_by,
                'amount' => $amount, 'reason_code' => $ticket->type, 'reason' => $ticket->description,
                'status' => 'APPROVED', 'cancel_booking' => false,
                'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'decision_note' => $ticket->review_note,
            ]);
            app(RefundRecipientService::class)->save($request, $ticket->refund_recipient, $ticket->refund_recipient['confirmed_at']);
            $resolution->update(['choice' => 'REFUND', 'status' => 'REFUND_PENDING']);
            app(CustomerNotificationService::class)->refundProgress($request, 'APPROVED');
        }

    }

    public function closeIfResolved(IncidentResolution $resolution): void
    {
        $ticket = $resolution->incident;
        if ($ticket->source === 'CUSTOMER' && $ticket->status === 'APPROVED' && ! $ticket->resolutions()->where('status', '!=', 'RESOLVED')->exists()) {
            $ticket->update(['status' => 'RESOLVED', 'resolved_at' => now(), 'active_booking_id' => null]);
            $ticket->updates()->create(['actor_id' => auth()->id() ?? $ticket->reviewed_by ?? $ticket->reported_by, 'status' => 'RESOLVED', 'event_type' => 'RESOLVED', 'note' => 'Đã hoàn tất phương án xử lý và đóng yêu cầu.']);
            $this->notify($ticket, 'Yêu cầu đã được xử lý hoàn tất.', false);
        }
    }
}

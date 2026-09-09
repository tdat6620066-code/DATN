<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\CourtIncident;
use App\Models\IncidentEvidence;
use App\Models\User;
use App\Services\IncidentTicketService;
use App\Services\IncidentTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentTicketController extends Controller
{
    public function index(Request $request)
    {
        $this->staff($request);
        $tickets = CourtIncident::where('source', 'CUSTOMER')->with(['booking', 'court', 'reporter', 'assignee'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(20)->withQueryString();

        return view('incident-tickets.index', compact('tickets'));
    }

    public function create(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $booking->load('bookingDetails.court', 'bookingDetails.timeSlot');
        $tickets = CourtIncident::where('booking_id', $booking->id)->where('source', 'CUSTOMER')->latest()->get();

        return view('incident-tickets.create', compact('booking', 'tickets'));
    }

    public function store(Request $request, Booking $booking, IncidentTicketService $service)
    {
        abort_unless($booking->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'booking_detail_id' => ['required', 'integer'], 'type' => ['required', Rule::in(array_keys(CourtIncident::TYPES))],
            'description' => ['required', 'string', 'min:10', 'max:4000'],
            'requested_solution' => ['required', Rule::in(array_keys(CourtIncident::SOLUTIONS))],
        ] + $this->fileRules());
        $paths = [];
        try {
            $ticket = DB::transaction(function () use ($request, $booking, $data, $service, &$paths) {
                $locked = Booking::lockForUpdate()->findOrFail($booking->id);
                if (! \App\Models\Payment::forBooking($locked)->where('status', 'PAID')->exists()) {
                    throw ValidationException::withMessages(['description' => 'Chỉ có thể báo cáo sự cố từ booking đã thanh toán.']);
                }
                if (CourtIncident::where('active_booking_id', $locked->id)->exists()) {
                    throw ValidationException::withMessages(['description' => 'Booking đã có một yêu cầu đang mở. Vui lòng bổ sung trong yêu cầu đó.']);
                }
                $detail = $locked->bookingDetails()->findOrFail($data['booking_detail_id']);
                $ticket = CourtIncident::create(['incident_code' => 'TK-'.Str::uuid(), 'source' => 'CUSTOMER', 'booking_id' => $locked->id, 'booking_detail_id' => $detail->id, 'active_booking_id' => $locked->id, 'court_id' => $detail->court_id, 'customer_id' => $request->user()->id, 'reported_by' => $request->user()->id, 'type' => $data['type'], 'description' => $data['description'], 'requested_solution' => $data['requested_solution'], 'severity' => 'MEDIUM', 'status' => 'PENDING']);
                $this->evidence($request, $ticket, $paths);
                $ticket->forceFill(['booking_snapshot' => ['court_id' => $detail->court_id, 'date' => $detail->booking_date->toDateString(), 'time_slot_id' => $detail->time_slot_id, 'start_time' => $detail->timeSlot->start_time, 'end_time' => $detail->timeSlot->end_time]])->save();
                $ticket->updates()->create(['actor_id' => $request->user()->id, 'status' => 'PENDING', 'event_type' => 'SUBMITTED', 'note' => $data['description']]);
                $service->notify($ticket, 'Đã ghi nhận yêu cầu hỗ trợ, chờ nhân viên tiếp nhận.', false);
                $service->notify($ticket, 'Khách gửi yêu cầu mới, cần xác minh.', true);

                return $ticket;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        return redirect()->route('incident-tickets.show', $ticket)->with('success', 'Đã gửi yêu cầu. Nhân viên sẽ xác minh; chưa phát sinh hoàn tiền.');
    }

    public function show(Request $request, CourtIncident $ticket)
    {
        $this->access($request, $ticket);
        $ticket->load(['booking.payment', 'detail.timeSlot', 'court', 'reporter', 'assignee', 'updates.actor', 'evidences', 'resolutions.refundRequests.refund']);
        $staff = $request->user()->role === 'ADMIN' ? User::whereIn('role', ['ADMIN', 'EMPLOYEE'])->get()->filter(fn ($u) => $u->hasPermission('incidents.manage')) : collect();

        $timeline = app(IncidentTimelineService::class)->ticket($ticket);

        return view('incident-tickets.show', compact('ticket', 'staff', 'timeline'));
    }

    public function supplement(Request $request, CourtIncident $ticket, IncidentTicketService $service)
    {
        $this->access($request, $ticket);
        abort_unless($request->user()->role === 'CUSTOMER', 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:4000']] + $this->fileRules());
        $paths = [];
        try {
            DB::transaction(function () use ($request, $ticket, $data, $service, &$paths) {
                Booking::lockForUpdate()->findOrFail($ticket->booking_id);
                $locked = CourtIncident::lockForUpdate()->findOrFail($ticket->id);
                if (! in_array($locked->status, ['PENDING', 'REVIEWING', 'NEED_MORE_INFO'], true)) {
                    throw ValidationException::withMessages(['note' => 'Yêu cầu đã có quyết định, không thể bổ sung.']);
                }
                $this->evidence($request, $locked, $paths);
                $locked->update(['status' => $locked->status === 'NEED_MORE_INFO' ? 'REVIEWING' : $locked->status]);
                $locked->updates()->create(['actor_id' => $request->user()->id, 'status' => $locked->status, 'event_type' => 'SUPPLEMENTED', 'note' => $data['note']]);
                $service->notify($locked, 'Đã ghi nhận thông tin bổ sung; nhân viên sẽ tiếp tục xem xét.', false);
                $service->notify($locked, 'Khách đã bổ sung thông tin.', true);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        return back()->with('success', 'Đã bổ sung thông tin và bằng chứng.');
    }

    public function review(Request $request, CourtIncident $ticket, IncidentTicketService $service)
    {
        $this->staff($request);
        abort_unless($ticket->source === 'CUSTOMER', 404);
        $data = $request->validate(['action' => ['required', Rule::in(['REVIEWING', 'NEED_MORE_INFO', 'PROPOSE', 'APPROVED', 'REJECTED', 'RESOLVED'])], 'note' => ['required', 'string', 'max:4000'], 'assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'proposed_solution' => ['nullable', Rule::in(array_keys(CourtIncident::SOLUTIONS))], 'amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0']]);
        if (in_array($data['action'], ['APPROVED', 'REJECTED', 'RESOLVED'], true)) {
            abort_unless($request->user()->role === 'ADMIN', 403);
        }
        DB::transaction(function () use ($request, $ticket, $data, $service) {
            Booking::lockForUpdate()->findOrFail($ticket->booking_id);
            $locked = CourtIncident::lockForUpdate()->findOrFail($ticket->id);
            if (in_array($locked->status, ['REJECTED', 'RESOLVED'], true) || ($locked->status === 'APPROVED' && $data['action'] !== 'RESOLVED')) {
                throw ValidationException::withMessages(['action' => 'Yêu cầu đã được quyết định.']);
            }
            if ($data['action'] === 'RESOLVED' && ($locked->status !== 'APPROVED' || $locked->resolutions()->where('status', '!=', 'RESOLVED')->exists())) {
                throw ValidationException::withMessages(['action' => 'Chỉ đóng sau khi đã xác minh và hoàn tất phương án xử lý.']);
            }
            $assignee = $locked->assigned_to ?? $request->user()->id;
            if (! empty($data['assigned_to'])) {
                abort_unless($request->user()->role === 'ADMIN', 403);
                $user = User::findOrFail($data['assigned_to']);
                if (! in_array($user->role, ['ADMIN', 'EMPLOYEE'], true) || ! $user->hasPermission('incidents.manage')) {
                    throw ValidationException::withMessages(['assigned_to' => 'Người phụ trách phải có quyền xử lý sự cố.']);
                }
                $assignee = $user->id;
            }
            if ($data['action'] === 'PROPOSE' && empty($data['proposed_solution'])) {
                throw ValidationException::withMessages(['proposed_solution' => 'Chọn phương án đề xuất.']);
            }
            $status = $data['action'] === 'PROPOSE' ? 'REVIEWING' : $data['action'];
            $locked->update(['status' => $status, 'assigned_to' => $assignee, 'review_note' => $data['note'], 'proposed_solution' => $data['proposed_solution'] ?? $locked->proposed_solution, 'proposed_amount' => $data['amount'] ?? $locked->proposed_amount]);
            if ($status === 'APPROVED') {
                $locked->update(['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
                $service->approve($locked, $request->user(), (float) ($data['amount'] ?? $locked->proposed_amount ?? 0));
            }
            if (in_array($status, ['REJECTED', 'RESOLVED'], true)) {
                $locked->update(['active_booking_id' => null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'resolved_at' => now()]);
            }
            $locked->updates()->create(['actor_id' => $request->user()->id, 'status' => $status, 'event_type' => $data['action'], 'note' => $data['note'].(! empty($data['amount']) ? ' · Số tiền đề xuất/xác minh: '.$data['amount'].'đ' : '')]);
            $service->notify($locked, CourtIncident::TICKET_STATUSES[$status].': '.$data['note'], false);
            if ($data['action'] === 'PROPOSE' || ! empty($data['assigned_to'])) {
                $service->notify($locked, 'Có đề xuất hoặc phân công cần xem xét.', true);
            }
        });

        return back()->with('success', 'Đã lưu kết quả xem xét.');
    }

    public function download(Request $request, IncidentEvidence $evidence)
    {
        $this->access($request, $evidence->incident);

        abort_unless(Storage::disk('local')->exists($evidence->file_path), 404);
        return Storage::disk('local')->response($evidence->file_path, 'evidence-'.$evidence->id.'.'.pathinfo($evidence->file_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], 'inline');
    }

    private function fileRules(): array
    {
        return ['evidences' => ['nullable', 'array', 'max:5'], 'evidences.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,webm', 'max:20480']];
    }

    private function evidence(Request $request, CourtIncident $ticket, array &$paths): void
    {
        if ($ticket->evidences()->count() + count($request->file('evidences', [])) > 10) {
            throw ValidationException::withMessages(['evidences' => 'Mỗi yêu cầu tối đa 10 tệp bằng chứng.']);
        }
        foreach ($request->file('evidences', []) as $file) {
            $path = $file->store('incident-evidences', 'local');
            if (! $path) {
                throw new \RuntimeException('Không thể lưu bằng chứng.');
            }
            $paths[] = $path;
            $ticket->evidences()->create(['file_path' => $path, 'file_type' => $file->getMimeType()]);
        }
    }

    private function staff(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['ADMIN', 'EMPLOYEE'], true) && $request->user()->hasPermission('incidents.manage'), 403);
    }

    private function access(Request $request, CourtIncident $ticket): void
    {
        abort_unless($ticket->source === 'CUSTOMER', 404);
        if ($request->user()->role === 'CUSTOMER') {
            abort_unless($ticket->customer_id === $request->user()->id, 403);
        } else {
            $this->staff($request);
        }
    }
}

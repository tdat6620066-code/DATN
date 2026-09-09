<?php

namespace App\Services;

use App\Models\CourtIncident;
use App\Models\IncidentResolution;
use Illuminate\Support\Collection;

class IncidentTimelineService
{
    public function ticket(CourtIncident $ticket): Collection
    {
        $ticket->loadMissing(['updates.actor', 'resolutions.refundRequests.refund']);
        $events = $ticket->updates->map(function ($update) {
            $type = $update->event_type ?? $update->status;
            $label = match ($type) {
                'SUBMITTED', 'PENDING' => 'Đã gửi yêu cầu',
                'REVIEWING' => 'Nhân viên đã tiếp nhận',
                'PROPOSE' => 'Nhân viên đã xác minh và gửi đề xuất',
                'SUPPLEMENTED' => 'Khách đã bổ sung thông tin',
                'NEED_MORE_INFO' => 'Cần khách bổ sung thông tin',
                'APPROVED' => 'Đã xác minh sự cố, chấp thuận hỗ trợ',
                'REJECTED' => 'Yêu cầu hỗ trợ bị từ chối',
                'RESOLVED' => 'Yêu cầu hỗ trợ đã hoàn tất',
                default => $type,
            };
            $rank = match ($type) {
                'SUBMITTED','PENDING' => 10, 'REVIEWING' => 20, 'PROPOSE' => 30, 'APPROVED' => 40, 'RESOLVED' => 90, default => 25
            };

            return ['at' => $update->created_at, 'rank' => $rank, 'label' => $label, 'note' => ($update->actor?->name ? $update->actor->name.' · ' : '').$update->note, 'complete' => $type === 'RESOLVED'];
        });
        foreach ($ticket->resolutions as $resolution) {
            $events = $events->concat($this->resolution($resolution, false));
        }

        return $this->ordered($events);
    }

    public function resolution(IncidentResolution $resolution, bool $includeIncident = true): Collection
    {
        $resolution->loadMissing(['incident', 'refundRequests.refund']);
        $events = collect();
        if ($includeIncident) {
            $events->push(['at' => $resolution->created_at, 'label' => 'Sân đã xác nhận sự cố; chờ bạn chọn phương án', 'note' => $resolution->incident->description, 'complete' => false]);
        }
        foreach ($resolution->refundRequests as $request) {
            $events->push(['at' => $request->created_at, 'rank' => 50, 'label' => 'Đã tạo yêu cầu hoàn tiền #'.$request->id, 'note' => 'Chờ Admin kiểm tra số tiền và phê duyệt.', 'complete' => false]);
            if ($request->reviewed_at) {
                $events->push(['at' => $request->reviewed_at, 'rank' => 60, 'label' => $request->status === 'REJECTED' ? 'Admin từ chối yêu cầu hoàn tiền' : 'Admin phê duyệt hoàn '.number_format($request->amount).'đ', 'note' => $request->decision_note, 'complete' => false]);
            }
            if ($request->processing_started_at) {
                $events->push(['at' => $request->processing_started_at, 'rank' => 70, 'label' => 'Đang xử lý hoàn tiền', 'note' => 'Khoản hoàn '.number_format($request->amount).'đ đang được thực hiện; chưa xác nhận chuyển thành công.', 'complete' => false]);
            }
            if ($request->refund?->status === 'COMPLETED' && $request->refund->processed_at) {
                $events->push(['at' => $request->refund->processed_at, 'rank' => 80, 'label' => 'Hoàn tiền thành công: '.number_format($request->refund->amount).'đ', 'note' => 'Mã giao dịch: '.$request->refund->refund_code, 'complete' => true]);
            }
            if ($request->refund?->status === 'FAILED' && $request->refund->processed_at) {
                $events->push(['at' => $request->refund->processed_at, 'label' => 'Hoàn tiền chưa thành công', 'note' => 'Vui lòng chờ nhân viên xử lý lại.', 'complete' => false]);
            }
        }
        if ($resolution->resolved_at && in_array($resolution->choice, ['RESCHEDULE', 'CHANGE_COURT'], true)) {
            $events->push(['at' => $resolution->resolved_at, 'label' => $resolution->choice === 'RESCHEDULE' ? 'Đổi lịch đã hoàn tất' : 'Đổi sân đã hoàn tất', 'note' => 'Lịch mới được hiển thị trong booking.', 'complete' => true]);
        }

        return $this->ordered($events);
    }

    private function ordered(Collection $events): Collection
    {
        return $events->sortBy(fn ($e) => $e['at']->format('Y-m-d H:i:s.u').sprintf('%03d', $e['rank'] ?? 45))->values();
    }
}

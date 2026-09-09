from pathlib import Path
import re

paths = [
 'incident-tickets/create.blade.php', 'incident-tickets/show.blade.php', 'incident-tickets/index.blade.php',
 'employee/incidents/index.blade.php', 'admin/incidents/index.blade.php', 'admin/incidents/refunds.blade.php',
 'admin/incidents/bulk.blade.php', 'refund-payouts/index.blade.php', 'refund-payouts/show.blade.php',
 'partials/special-refunds.blade.php',
]
for path in paths:
 p=Path('resources/views')/path
 s=p.read_text(encoding='utf-8')
 if "@section('content')" in s:
  s=s.replace("@section('content')", "@section('content')\n@include('partials.incident-ui')",1)
 else: s="@include('partials.incident-ui')\n"+s
 def classes(match):
  tokens=match.group(1).split()
  if 'btn' not in tokens: return match.group(0)
  variant = 'sz-action--danger' if any('danger' in t for t in tokens) else 'sz-action--primary' if any(t in ['btn-primary','btn-success'] for t in tokens) else 'sz-action--report' if any('warning' in t for t in tokens) else ''
  small = 'sz-action--small' if 'btn-sm' in tokens else ''
  rest=[t for t in tokens if t!='btn' and not t.startswith('btn-')]
  return 'class="'+' '.join(t for t in ['sz-action',variant,small]+rest if t)+'"'
 s=re.sub(r'class="([^"]*)"',classes,s)
 s=s.replace('class="card p-4"', 'class="sz-work-panel"').replace('class="card p-4 mb-3"','class="sz-work-panel mb-3"').replace('class="card p-3 mb-3"','class="sz-work-panel mb-3"')
 p.write_text(s,encoding='utf-8')

def edit(path,old,new):
 p=Path('resources/views')/path
 s=p.read_text(encoding='utf-8')
 assert old in s, (path,old)
 p.write_text(s.replace(old,new),encoding='utf-8')

edit('bookings/show.blade.php', '<div class="container pt-3"><a class="btn btn-outline-warning" href="{{ route(\'incident-tickets.create\',$booking) }}">⚠ Báo cáo sự cố / Theo dõi hỗ trợ</a></div>', '''@include('partials.incident-ui')
<div class="container pt-3"><div class="sz-support-strip">
<div class="sz-support-copy"><i class="bi bi-headset" aria-hidden="true"></i><div><strong>Cần hỗ trợ với lịch chơi?</strong><p>Gửi minh chứng và theo dõi tiến trình xử lý tại đây.</p></div></div>
<a class="sz-action sz-action--report" href="{{ route('incident-tickets.create',$booking) }}"><i class="bi bi-flag" aria-hidden="true"></i>Báo cáo sự cố</a>
</div></div>''')
edit('bookings/card.blade.php', '<a class="btn btn-sm btn-outline-warning my-2" href="{{ route(\'incident-tickets.create\',$booking) }}">⚠ Báo cáo sự cố</a>', '''@include('partials.incident-ui')
                        <a class="sz-action sz-action--report sz-action--small my-2" href="{{ route('incident-tickets.create',$booking) }}"><i class="bi bi-flag" aria-hidden="true"></i>Báo cáo sự cố</a>''')
edit('incident-tickets/create.blade.php','<div class="container py-4"><h1 class="h3">Báo cáo sự cố / hỗ trợ booking</h1><p><a href="{{ route(\'bookings.show\',$booking) }}">{{ $booking->booking_code }}</a> · {{ $booking->user->name }}</p>', '''<div class="container py-4 sz-support-page"><header class="sz-support-header">
<a class="small text-decoration-none" href="{{ route('bookings.show',$booking) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Trở về đơn {{ $booking->booking_code }}</a>
<h1>Báo cáo sự cố</h1><p>Cho SmashZone biết vấn đề bạn gặp phải để được hỗ trợ.</p></header>''')
edit('incident-tickets/create.blade.php','<p>Yêu cầu sẽ được xác minh. Gửi báo cáo không tự hủy booking hoặc tự hoàn tiền.</p><button class="sz-action sz-action--report">Gửi yêu cầu</button>', '''<p class="sz-help">Nhân viên sẽ xác minh và cập nhật kết quả trong yêu cầu của bạn.</p><div class="sz-action-bar"><button class="sz-action sz-action--primary"><i class="bi bi-send" aria-hidden="true"></i>Gửi báo cáo</button><a class="sz-action" href="{{ route('bookings.show', $booking) }}">Quay lại</a></div>''')
edit('incident-tickets/show.blade.php','<div class="d-flex flex-wrap gap-2">\n@if($ticket->status', '<div class="sz-action-bar">\n@if($ticket->status')
edit('incident-tickets/show.blade.php', '<details class="align-self-center"><summary class="small text-muted">Thao tác khác</summary><div class="d-flex flex-wrap gap-2 mt-2">', '<details class="sz-more"><summary class="sz-action"><i class="bi bi-three-dots" aria-hidden="true"></i>Thao tác khác</summary><div class="sz-more-content">')
edit('incident-tickets/show.blade.php', '>Gửi Admin duyệt</button>', '><i class="bi bi-send-check" aria-hidden="true"></i>Gửi Admin duyệt</button>')
edit('incident-tickets/show.blade.php', '>Xác minh và chấp thuận hỗ trợ</button>', '><i class="bi bi-check2-circle" aria-hidden="true"></i>Chấp thuận hỗ trợ</button>')
edit('incident-tickets/show.blade.php', '>Đã hỗ trợ xong</button>', '><i class="bi bi-check2-all" aria-hidden="true"></i>Hoàn tất hỗ trợ</button>')
edit('incident-tickets/show.blade.php', '>Mở booking để duyệt / xác nhận hoàn tiền</a>', '><i class="bi bi-wallet2" aria-hidden="true"></i>Xem đơn & xử lý hoàn tiền</a>')
edit('incident-tickets/show.blade.php', '>Chọn hoàn tiền / đổi lịch / đổi sân</a>', '><i class="bi bi-arrow-left-right" aria-hidden="true"></i>Xem phương án hỗ trợ</a>')
edit('incident-tickets/index.blade.php','class="d-flex gap-3 mb-3"','class="sz-toolbar"')
edit('incident-tickets/index.blade.php','>Sân phát hiện sự cố — tìm booking bị ảnh hưởng</a>', '><i class="bi bi-calendar-x" aria-hidden="true"></i>Xử lý sự cố sân</a>')
edit('incident-tickets/index.blade.php','<th>Phụ trách</th>','<th>Phụ trách</th><th class="text-end">Thao tác</th>')
edit('incident-tickets/index.blade.php', "{{ $ticket->assignee?->name ?? 'Chưa phân công' }}</td></tr>", "{{ $ticket->assignee?->name ?? 'Chưa phân công' }}</td><td class=\"text-end\"><a class=\"sz-action sz-action--small\" href=\"{{ route('incident-tickets.show', $ticket) }}\"><i class=\"bi bi-arrow-up-right\" aria-hidden=\"true\"></i>Xem yêu cầu</a></td></tr>")
edit('incident-tickets/index.blade.php','colspan="5"','colspan="6"')
edit('admin/incidents/index.blade.php', '>Sự cố sân — xử lý booking hàng loạt</a>', '><i class="bi bi-calendar-x" aria-hidden="true"></i>Xử lý sự cố sân</a>')
edit('admin/incidents/index.blade.php', '>Hoàn tiền chờ xử lý</a>', '><i class="bi bi-wallet2" aria-hidden="true"></i>Hoàn tiền chờ xử lý</a>')
edit('admin/incidents/index.blade.php', '<td><form method="POST"', '<td class="sz-table-actions"><details class="sz-more"><summary class="sz-action sz-action--small"><i class="bi bi-sliders2" aria-hidden="true"></i>Cập nhật xử lý</summary><div class="sz-more-content"><form method="POST"')
edit('admin/incidents/index.blade.php', '</button></form></td>', '</button></form></div></details></td>')
edit('admin/incidents/index.blade.php', '<select name="status"', '<select aria-label="Trạng thái xử lý" name="status"')
edit('admin/incidents/index.blade.php', '<input name="resolution_note"', '<input aria-label="Ghi chú xử lý" name="resolution_note"')
edit('employee/incidents/index.blade.php', '<summary class="fw-semibold">+ Tạo báo cáo sự cố</summary>', '<summary class="sz-action sz-action--primary"><i class="bi bi-plus-lg" aria-hidden="true"></i>Tạo báo cáo sự cố</summary>')
edit('employee/incidents/index.blade.php', '<details class="staff-card p-3 mb-3"', '<details class="sz-more sz-work-panel mb-3"')
edit('employee/incidents/index.blade.php', '>Gửi báo cáo</button>', '><i class="bi bi-send" aria-hidden="true"></i>Gửi báo cáo</button>')
edit('partials/special-refunds.blade.php', '>Xử lý hoàn tiền</a>', '><i class="bi bi-wallet2" aria-hidden="true"></i>Xử lý hoàn tiền</a>')
edit('refund-payouts/show.blade.php', '>Xác nhận đã hoàn tiền</button>', '><i class="bi bi-check2-circle" aria-hidden="true"></i>Xác nhận đã chi trả</button>')
edit('refund-payouts/show.blade.php', '>Xác nhận xử lý hoàn tiền</button>', '><i class="bi bi-wallet2" aria-hidden="true"></i>Bắt đầu chi trả</button>')
edit('refund-payouts/index.blade.php', '<a href="{{ route(\'refund-payouts.show\',$item) }}">Xử lý khoản hoàn #{{ $item->id }}</a>', '<a class="sz-action sz-action--small" href="{{ route(\'refund-payouts.show\',$item) }}"><i class="bi bi-arrow-up-right" aria-hidden="true"></i>Chi trả #{{ $item->id }}</a>')

@extends('layouts.admin')
@section('page_heading', 'Duyệt hoàn tiền')
@section('content')<x-admin.workspace>
@include('partials.incident-ui')
<div class="d-flex justify-content-between align-items-center mb-3">
<p class="mb-0">Kiểm tra sự cố và số tiền trước khi phê duyệt. Sau khi duyệt, khoản hoàn sẽ chuyển sang bước chi trả.</p>
<a class="sz-action ms-3" href="{{ route('refund-payouts.index') }}">Chi trả hoàn tiền đã duyệt</a>
</div>
@forelse($items as $item)
<article class="sz-work-panel mb-3">
<div class="d-flex justify-content-between flex-wrap gap-2">
<h2 class="h5">Yêu cầu #{{ $item->id }} · {{ $item->booking->booking_code }}</h2>
<span class="badge {{ $item->status === 'PENDING' ? 'bg-warning text-dark' : 'bg-success' }}">{{ $item->payout_label }}</span>
</div>
<p>Khách hàng: <strong>{{ $item->booking->user->name }}</strong> · Người đề nghị: {{ $item->requester?->name }}</p>
<p>Đã thanh toán: {{ number_format($item->booking->total_amount) }}đ · Đề nghị hoàn: <strong>{{ number_format($item->amount) }}đ</strong></p>
<p>Lý do: {{ \App\Models\RefundRequest::REASONS[$item->reason_code] ?? $item->reason_code }} — {{ $item->reason }}</p>
@if($item->bank_account_last4)<p>Ngân hàng: {{ $item->bank_name }} · Tài khoản: ********{{ $item->bank_account_last4 }} · Chủ tài khoản: {{ $item->bank_account_holder }}</p>@endif
@if($item->supporting_information)<p>Bằng chứng / cách tính tiền: {{ $item->supporting_information }}</p>@endif
<a class="mb-3" href="{{ route('admin.bookings.show', $item->booking) }}">Xem chi tiết booking và sự cố</a>
@if($item->status === 'PENDING' && isset(\App\Models\RefundRequest::REASONS[$item->reason_code]))
<form method="POST" action="{{ route('special-refunds.review', $item) }}">
@csrf
<label for="amount-{{ $item->id }}" class="form-label">Số tiền phê duyệt (đ)</label>
<input id="amount-{{ $item->id }}" name="amount" type="number" min="0.01" max="{{ $item->amount }}" step="0.01" value="{{ $item->amount }}" class="form-control mb-3" required>
<label for="note-{{ $item->id }}" class="form-label">Kết quả xác minh / ghi chú quyết định</label>
<textarea id="note-{{ $item->id }}" name="decision_note" maxlength="2000" class="form-control mb-3" required></textarea>
<button name="decision" value="APPROVED" class="sz-action sz-action--primary">Phê duyệt hoàn tiền</button>
<button name="decision" value="REJECTED" class="sz-action sz-action--danger">Từ chối yêu cầu</button>
</form>
@elseif($item->status === 'APPROVED')
<p>{{ $item->decision_note }}</p>
<a class="sz-action sz-action--primary align-self-start" href="{{ route('refund-payouts.show', $item) }}">Tiếp tục chi trả hoàn tiền</a>
@endif
</article>
@empty
<div class="sz-work-panel">Chưa có yêu cầu hoàn tiền chờ duyệt hoặc chờ chi trả. Mở chi tiết booking để tạo yêu cầu hoàn tiền đặc biệt, hoặc tiếp nhận yêu cầu tại Sự cố & Khiếu nại.</div>
@endforelse
<x-admin.pagination :rows="$items" />
</x-admin.workspace>@endsection

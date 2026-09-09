@include('partials.incident-ui')
@include('partials.detail-styles')
<section class="cardx staff-card p-4 my-3 sz-refunds">
<h2 class="h5">Hoàn tiền đặc biệt</h2>
<p>Booking: <strong>{{ $booking->booking_code }}</strong> · Khách: {{ $booking->user->name }} · Đã thanh toán: {{ $booking->payment?->paid_at ? number_format($booking->total_amount).'đ' : 'Chưa xác nhận' }}</p>
<p>Chỉ áp dụng khi bất khả kháng hoặc lỗi phía sân. Khách không được tự hủy lịch đã thanh toán.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(!$booking->incidentResolutions()->exists() && auth()->user()->hasPermission('incidents.manage') && $booking->payment_status === 'PAID' && $booking->payment?->status === 'PAID' && !$booking->refundRequests()->whereIn('status', ['PENDING','NEEDS_INFO','APPROVED'])->exists())
<details class="sz-disclosure" @if($errors->any()) open @endif><summary>Tạo yêu cầu hoàn tiền đặc biệt</summary><form method="POST" action="{{ route('special-refunds.store', $booking) }}" class="formx mt-3">
@csrf
<label>Loại hoàn</label><select name="refund_type" class="form-select mb-2" onchange="const a=this.form.elements.amount; if(this.value==='FULL') a.value=a.max; a.readOnly=this.value==='FULL';"><option value="FULL">Toàn bộ</option><option value="PARTIAL" @selected(old('refund_type') === 'PARTIAL')>Một phần</option></select>
<label>Lý do đặc biệt</label><select name="reason_code" class="form-select mb-2" required>@foreach(\App\Models\RefundRequest::REASONS as $code => $label)<option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>@endforeach</select>
<label>Mô tả sự cố</label><textarea name="reason" class="form-control mb-2" maxlength="2000" required>{{ old('reason') }}</textarea>
<label>Bằng chứng / thời gian đã sử dụng / cách tính tiền hoàn</label><textarea name="supporting_information" class="form-control mb-2" maxlength="4000" required>{{ old('supporting_information') }}</textarea>
<label>Số tiền đề nghị hoàn (đ)</label><input type="number" name="amount" class="form-control mb-2" min="0.01" step="0.01" max="{{ $booking->total_amount }}" value="{{ old('amount', $booking->total_amount) }}" required>
<p>Gợi ý: lỗi sân hoàn 100%; gián đoạn hoàn theo thời gian chưa sử dụng. Ví dụ 300.000đ / 120 phút × 60 phút chưa sử dụng = 150.000đ.</p>
@if(auth()->user()->role === 'ADMIN')<button name="approve_now" value="1" class="sz-action sz-action--primary sz-action--small">Phê duyệt hoàn tiền</button>@else<button class="sz-action sz-action--primary sz-action--small">Gửi Admin duyệt</button>@endif
</form></details>
@endif
@foreach($booking->refundRequests()->with(['requester', 'reviewer', 'refund'])->latest()->get() as $item)
<article class="border-top mt-3 pt-3">
<strong>#{{ $item->id }} · {{ \App\Models\RefundRequest::REASONS[$item->reason_code] ?? 'Yêu cầu cũ' }} · {{ number_format($item->amount) }}đ</strong>
<p>{{ $item->payout_label }} · Người tạo: {{ $item->requester?->name }}</p>
@if($item->bank_account_last4)<p>Ngân hàng: {{ $item->bank_name }} · Tài khoản: ********{{ $item->bank_account_last4 }}</p>@endif
<details class="sz-event-note"><summary>Lý do và thông tin xác minh</summary><p>{{ $item->reason }}</p><p>{{ $item->supporting_information }}</p></details>
@if($item->reviewed_at)<p>Admin: {{ $item->reviewer?->name }} · {{ $item->decision_note }}</p>@endif
@if($item->refund)<p>Mã hoàn tiền: {{ $item->refund->refund_code }} · {{ $item->refund->processed_at }}</p>@include('partials.refund-receipt', ['receipt' => $item->refund])@endif
@if(auth()->user()->role === 'ADMIN' && isset(\App\Models\RefundRequest::REASONS[$item->reason_code]))
@if($item->status === 'PENDING')
<form method="POST" action="{{ route('special-refunds.review', $item) }}">@csrf
<label>Số tiền Admin duyệt (đ, có thể giảm để hoàn một phần)</label><input type="number" name="amount" min="0.01" step="0.01" max="{{ $item->amount }}" value="{{ $item->amount }}" class="form-control mb-2">
<label>Xác nhận sự cố và kiểm tra số tiền hoàn</label><textarea name="decision_note" class="form-control mb-2" maxlength="2000" required></textarea>
<button name="decision" value="APPROVED" class="sz-action sz-action--primary">Xác nhận sự cố và duyệt</button>
<button name="decision" value="REJECTED" class="sz-action sz-action--danger">Từ chối</button></form>
@endif
@endif
@if(auth()->user()->hasPermission('refunds.process') && $item->status === 'APPROVED' && !$item->refund)
<a class="sz-action sz-action--primary" href="{{ route('refund-payouts.show',$item) }}"><i class="bi bi-wallet2" aria-hidden="true"></i>Xử lý hoàn tiền</a>
@endif
</article>
@endforeach
</section>

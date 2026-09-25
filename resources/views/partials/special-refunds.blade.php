@include('partials.incident-ui')
@include('partials.detail-styles')
<section class="cardx staff-card p-4 my-3 sz-refunds">
<h2 class="h5">Hoàn tiền đặc biệt</h2>
<p>Booking: <strong>{{ $booking->booking_code }}</strong> · Khách: {{ $booking->user->name }} · Đã thanh toán: {{ $booking->payment?->paid_at ? number_format($booking->total_amount).'đ' : 'Chưa xác nhận' }}</p>
<p>Chỉ áp dụng khi bất khả kháng hoặc lỗi phía sân. Khách không được tự hủy lịch đã thanh toán.</p>
@if(!app(\App\Services\BookingRefundPolicy::class)->eligible($booking))
<p class="alert alert-info">Booking đã check-in không được hoàn tiền.</p>
@else
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(!$booking->incidentResolutions()->exists() && auth()->user()->hasPermission('incidents.manage') && $booking->payment_status === 'PAID' && $booking->payment?->status === 'PAID' && !$booking->refundRequests()->whereIn('status', ['PENDING','NEEDS_INFO','APPROVED'])->exists())
<details class="sz-disclosure" @if($errors->any()) open @endif><summary>Tạo yêu cầu hoàn tiền đặc biệt</summary><form method="POST" action="{{ route('special-refunds.store', $booking) }}" class="formx mt-3">
@csrf
<input type="hidden" name="refund_type" value="FULL"><p>Hoàn toàn bộ booking, không chia theo khung giờ.</p>
<label>Lý do đặc biệt</label><select name="reason_code" class="form-select mb-2" required>@foreach(\App\Models\RefundRequest::REASONS as $code => $label)<option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>@endforeach</select>
<label>Mô tả sự cố</label><textarea name="reason" class="form-control mb-2" maxlength="2000" required>{{ old('reason') }}</textarea>
<label>Bằng chứng / thời gian đã sử dụng / cách tính tiền hoàn</label><textarea name="supporting_information" class="form-control mb-2" maxlength="4000" required>{{ old('supporting_information') }}</textarea>
<label>Số tiền hoàn toàn booking (đ)</label><input type="number" name="amount" class="form-control mb-2" value="{{ app(\App\Services\BookingRefundPolicy::class)->remaining($booking) }}" readonly>
<p>Hệ thống tự tính số tiền đã thu còn được hoàn của booking.</p>
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
<label>Số tiền hoàn toàn booking (đ)</label><input type="number" name="amount" value="{{ app(\App\Services\BookingRefundPolicy::class)->approvalAmount($item) }}" class="form-control mb-2" readonly>
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
@endif
</section>

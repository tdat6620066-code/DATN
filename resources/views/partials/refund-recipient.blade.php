@if(auth()->user()->role === 'CUSTOMER' && $refundRequest->booking->user_id === auth()->id())
<div class="card p-3 my-3">
@include('partials.refund-receipt', ['receipt' => $refundRequest->refund])
<p>Booking {{ $refundRequest->booking->booking_code }} · Trạng thái: {{ $refundRequest->payout_label }} · Số tiền dự kiến hoàn: {{ number_format($refundRequest->amount) }}đ</p><h3 class="h6">Thông tin nhận hoàn tiền — yêu cầu #{{ $refundRequest->id }}</h3>
@if($refundRequest->bank_account_last4)<p>Đã cung cấp tài khoản: ********{{ $refundRequest->bank_account_last4 }}</p>@else<p>Nếu nhận chuyển khoản, vui lòng cung cấp tài khoản. Nhận tiền mặt không cần thông tin ngân hàng.</p>@endif
@if(in_array($refundRequest->status,['PENDING','APPROVED','NEEDS_INFO']) && !$refundRequest->processing_started_at && !$refundRequest->refund)
<form method="POST" action="{{ route('refund-recipient.update',$refundRequest) }}">@csrf
@include('partials.refund-bank-fields')
<button class="btn btn-outline-primary">Lưu thông tin nhận tiền</button></form>
@else<p>Thông tin nhận tiền đã khóa khi bắt đầu xử lý.</p>@endif
</div>
@endif

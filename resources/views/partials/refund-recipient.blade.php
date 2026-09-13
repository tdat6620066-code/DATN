@if(auth()->user()->role === 'CUSTOMER' && $refundRequest->booking->user_id === auth()->id())
<div class="card p-3 my-3">
@include('partials.refund-receipt', ['receipt' => $refundRequest->refund])
<p>Booking {{ $refundRequest->booking->booking_code }} · Trạng thái: {{ $refundRequest->payout_label }} · Số tiền dự kiến hoàn: {{ number_format($refundRequest->amount) }}đ</p><h3 class="h6">Thông tin nhận hoàn tiền — yêu cầu #{{ $refundRequest->id }}</h3>
@if($refundRequest->bank_account_last4)<p>{{ $refundRequest->bank_name }} · ********{{ $refundRequest->bank_account_last4 }} · {{ $refundRequest->bank_account_holder }}</p>
<p>Xác nhận lần cuối: {{ $refundRequest->bankAccount->confirmed_at?->format('d/m/Y H:i') ?? 'Chưa xác nhận' }}</p>
@else<p>Nếu nhận chuyển khoản, vui lòng cung cấp tài khoản. Nhận tiền mặt không cần thông tin ngân hàng.</p>@endif
@if(in_array($refundRequest->status,['PENDING','APPROVED','NEEDS_INFO']) && !$refundRequest->processing_started_at && !$refundRequest->refund)
@if($refundRequest->bankAccount)
@if($refundRequest->needsBankConfirmation())<p class="text-warning">Vui lòng kiểm tra và xác nhận lại thông tin để Admin chuyển khoản.</p>@endif
<form method="POST" action="{{ route('refund-recipient.confirm', $refundRequest) }}" class="mb-3">@csrf
<input type="hidden" name="account_version" value="{{ hash('sha256', $refundRequest->bankAccount->getRawOriginal('account_number')) }}">
<label><input type="checkbox" name="recipient_confirmed" value="1" required> Tôi xác nhận tài khoản trên là chính xác.</label>
<button class="btn btn-outline-success">Xác nhận thông tin</button></form>
@endif
<details><summary>{{ $refundRequest->bankAccount ? 'Chỉnh sửa tài khoản' : 'Nhập tài khoản nhận tiền' }}</summary>
<form method="POST" action="{{ route('refund-recipient.update',$refundRequest) }}">@csrf
@include('partials.refund-bank-fields')
<button class="btn btn-outline-primary">Lưu thông tin nhận tiền</button></form>
</details>
@else<p>Thông tin nhận tiền đã khóa khi bắt đầu xử lý.</p>@endif
</div>
@endif

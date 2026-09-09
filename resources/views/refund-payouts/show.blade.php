@extends(auth()->user()->role === 'ADMIN' ? 'layouts.admin' : 'layouts.employee')
@section('page_heading','Xử lý hoàn tiền thủ công')
@section('content')
@include('partials.incident-ui')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="sz-work-panel"><h1 class="h4">Yêu cầu hoàn tiền #{{ $item->id }}</h1>
<p>Booking: {{ $item->booking->booking_code }} · Khách: {{ $item->booking->user->name }}</p>
<p>Đã thanh toán: {{ number_format($item->booking->total_amount) }}đ · Số tiền được duyệt: <strong>{{ number_format($item->amount) }}đ</strong></p><p>Lý do: {{ $item->reason }}</p>
@if($item->bank_account_last4)
<p>Tài khoản: ********{{ $item->bank_account_last4 }}</p>
<details class="mb-3"><summary>Xem thông tin để chuyển khoản</summary><p>Ngân hàng: {{ $item->bank_name }}<br>Số tài khoản: {{ $item->bank_account_number }}<br>Chủ tài khoản: {{ $item->bank_account_holder }}</p></details>
@else<p>Khách chưa cung cấp thông tin ngân hàng.</p>@endif
@if($item->refund)<p>{{ $item->refund->processing_note }}</p><p>Đã hoàn {{ number_format($item->refund->amount) }}đ · {{ $item->refund->refund_method === 'CASH' ? 'Tiền mặt' : ($item->refund->refund_method === 'BANK_TRANSFER' ? 'Chuyển khoản' : 'Phương thức cũ chưa ghi nhận') }} · Biên nhận {{ $item->refund->refund_code }}</p>
@include('partials.refund-receipt', ['receipt' => $item->refund])
@elseif($item->status !== 'APPROVED')<p>Yêu cầu chưa được Admin phê duyệt.</p>
@elseif(!$item->processing_started_at || !$item->refund_method)
<form method="POST" action="{{ route('special-refunds.processing',$item) }}">@csrf
<label>Phương thức hoàn tiền</label><select name="refund_method" class="form-select mb-3" required><option value="BANK_TRANSFER">Chuyển khoản ngân hàng</option><option value="CASH">Tiền mặt</option></select>
<p>Kiểm tra thông tin nhận tiền với khách trước khi bắt đầu. Thao tác này chưa ghi nhận tiền đã hoàn.</p><button class="sz-action sz-action--primary"><i class="bi bi-wallet2" aria-hidden="true"></i>Bắt đầu chi trả</button></form>
@else
<p>Đang xử lý: {{ $item->refund_method === 'CASH' ? 'Tiền mặt' : 'Chuyển khoản ngân hàng' }} · Thông tin nhận tiền đã khóa.</p>
<form method="POST" action="{{ route('special-refunds.complete',$item) }}" enctype="multipart/form-data">@csrf
<label>Số tiền thực hoàn</label><input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount', $item->amount) }}" class="form-control mb-2" required>
<label>Mã giao dịch / biên nhận giao tiền mặt</label><input name="refund_code" value="{{ old('refund_code') }}" maxlength="100" class="form-control mb-3" required>
@include('partials.media-upload', ['name' => 'receipt_image', 'label' => $item->refund_method === 'CASH' ? 'Ảnh biên nhận giao tiền mặt' : 'Ảnh giao dịch chuyển khoản thành công', 'accept' => 'image/jpeg,image/png,image/webp', 'multiple' => false, 'required' => true, 'hint' => 'Bắt buộc đính kèm ảnh JPG, PNG hoặc WebP, tối đa 10MB. Khách sẽ xem được ảnh này.'])
<label>Ghi chú chi trả</label><textarea name="processing_note" maxlength="2000" class="form-control mb-3">{{ old('processing_note') }}</textarea>
<p>Chỉ xác nhận sau khi đã chuyển khoản thành công hoặc thực sự giao tiền mặt cho khách.</p><button class="sz-action sz-action--primary"><i class="bi bi-check2-circle" aria-hidden="true"></i>Xác nhận đã chi trả</button></form>
@endif
</div>
@endsection

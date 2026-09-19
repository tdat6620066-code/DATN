@extends('layouts.employee')
@section('page_heading', 'Bán sản phẩm / dịch vụ')
@section('content')
@if(auth()->user()->hasPermission('services.manage'))
<section class="staff-card p-4 mb-4" aria-labelledby="playing-booking-title">
    <h1 class="h3" id="playing-booking-title">Thêm dịch vụ cho khách đang chơi</h1>
    <p class="text-muted">Chọn booking đã check-in. Dịch vụ được ghi vào booking của khách và thanh toán trước khi hoàn tất check-out.</p>
    <form method="GET" action="{{ route('employee.retail.index') }}" class="row g-3 align-items-end">
        <div class="col-md-9"><label for="playing-booking" class="form-label">Booking / Khách hàng / Sân đang chơi</label>
        <select id="playing-booking" name="booking_id" class="form-select" required>
            <option value="">Chọn booking đang chơi</option>
            @foreach($activeBookings as $playingBooking)
                <option value="{{ $playingBooking->id }}" @selected($selectedBooking?->id === $playingBooking->id)>{{ $playingBooking->booking_code }} · {{ $playingBooking->user->name }} · {{ $playingBooking->bookingDetails->where('status', '!=', 'CANCELLED')->pluck('court.name')->unique()->join(', ') }}</option>
            @endforeach
        </select></div>
        <div class="col-md-3"><button class="btn btn-primary w-100" @disabled($activeBookings->isEmpty())>Chọn dịch vụ cho booking</button></div>
    </form>
    @if($activeBookings->isEmpty())<p class="text-muted mt-3 mb-0">Chưa có booking đang chơi. Hãy check-in khách trước khi thêm dịch vụ.</p>@endif
    @if(request()->filled('booking_id') && !$selectedBooking)<p class="text-warning mt-3 mb-0">Booking không còn trong danh sách đang chơi. Vui lòng chọn lại.</p>@endif
</section>
@if($selectedBooking)
    @include('partials.service-orders', ['booking' => $selectedBooking])
@endif
@endif
<div class="staff-page-title"><h1>Bán lẻ tại quầy</h1><p>Chỉ xác nhận sau khi đã nhận tiền mặt hoặc kiểm tra giao dịch chuyển khoản / QR.</p></div>
<form method="POST" action="{{ route('employee.retail.store') }}" class="staff-card p-4 mb-4">@csrf
<input type="hidden" name="request_key" value="{{ old('request_key', (string) Str::uuid()) }}">
<label for="customer" class="form-label">Tên khách (không bắt buộc)</label><input id="customer" name="customer_name" class="form-control mb-3" value="{{ old('customer_name') }}">
<div class="table-responsive"><table class="table"><thead><tr><th>Sản phẩm / dịch vụ</th><th>Đơn giá</th><th>Tồn kho</th><th>Số lượng</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->name }}</td><td>{{ number_format($item->price) }}đ</td><td>{{ $item->stock ?? 'Không giới hạn' }}</td><td><input aria-label="Số lượng {{ $item->name }}" type="number" min="0" max="{{ $item->stock ?? 10000 }}" name="quantities[{{ $item->id }}]" value="{{ old('quantities.'.$item->id, 0) }}" data-price="{{ $item->price }}" class="form-control sale-quantity"></td></tr>@empty<tr><td colspan="4">Chưa có sản phẩm / dịch vụ đang bán. Admin cần thêm trong quản lý nội dung.</td></tr>@endforelse
</tbody></table></div>
<p class="fs-4">Tổng cộng: <strong id="sale-total">0đ</strong></p>
<div class="row g-3"><div class="col-md-6"><label for="method" class="form-label">Phương thức thanh toán</label><select id="method" name="payment_method" class="form-select">@foreach(['CASH'=>'Tiền mặt','BANK_TRANSFER'=>'Chuyển khoản','QR'=>'QR'] as $key=>$label)<option value="{{ $key }}" @selected(old('payment_method')===$key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-6"><label for="transaction" class="form-label">Mã giao dịch chuyển khoản / QR</label><input id="transaction" name="transaction_id" class="form-control" value="{{ old('transaction_id') }}"></div></div>
<button class="staff-button staff-button-primary mt-3" @disabled($items->isEmpty())>Đã nhận tiền — tạo hóa đơn</button>
</form>
<h2 class="h5">Hóa đơn của tôi</h2><div class="staff-card table-responsive"><table class="table staff-table"><thead><tr><th>Mã</th><th>Thời gian</th><th>Khách</th><th>Tổng tiền</th></tr></thead><tbody>@forelse($sales as $sale)<tr><td><a href="{{ route('employee.retail.show', $sale) }}">BL-{{ $sale->id }}</a></td><td>{{ $sale->created_at->format('d/m/Y H:i') }}</td><td>{{ $sale->customer_name ?: 'Khách lẻ' }}</td><td>{{ number_format($sale->total) }}đ</td></tr>@empty<tr><td colspan="4">Chưa có hóa đơn.</td></tr>@endforelse</tbody></table></div>{{ $sales->links() }}
@endsection
@push('scripts')<script>
const quantities = document.querySelectorAll('.sale-quantity');
function updateTotal() { let total = 0; quantities.forEach(input => total += Math.max(0, Number(input.value)||0) * Number(input.dataset.price)); document.getElementById('sale-total').textContent = new Intl.NumberFormat('vi-VN').format(total) + 'đ'; }
quantities.forEach(input => input.addEventListener('input', updateTotal)); updateTotal();
</script>@endpush

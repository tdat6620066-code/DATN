@extends('layouts.admin')
@section('page_heading', 'Báo cáo doanh thu sân')
@section('content')<x-admin.workspace>
<a class="btn btn-outline-success mb-3" href="{{ route('admin.reports.export', request()->only(['from', 'to'])) }}">Xuất báo cáo CSV</a>
<div class="d-flex flex-wrap gap-3 justify-content-between mb-4">
<a href="{{ route('admin.reports.cash-flow', ['from'=>$from->toDateString(), 'to'=>$to->toDateString()]) }}" class="btn btn-outline-success">Báo cáo thanh toán / dòng tiền</a>
<x-admin.filters class="d-flex gap-2"><label>Từ ngày<input type="date" class="form-control" name="from" value="{{ $from->toDateString() }}"></label><label>Đến ngày<input type="date" class="form-control" name="to" value="{{ $to->toDateString() }}"></label><button class="btn btn-dark">Áp dụng</button></x-admin.filters>
</div>
<section class="card p-4 mb-4"><x-admin.page-heading>Doanh thu sân theo ngày thanh toán</x-admin.page-heading>
<strong class="fs-2">{{ number_format($revenue['revenue']) }}đ</strong><p>{{ $revenue['slots'] }} lượt thuộc các giao dịch đã thanh toán trong kỳ.</p>
<p>Ghi nhận số tiền thực thanh toán theo ngày thanh toán thành công, kể cả lịch chơi trong tương lai. Thanh toán lịch cố định được tính một lần. Hủy lịch không xóa khoản đã thu; tiền hoàn ghi riêng theo ngày chi trả trong báo cáo dòng tiền.</p>
<table class="table"><thead><tr><th>Ngày thanh toán</th><th>Doanh thu sân</th></tr></thead><tbody>@forelse($revenue['daily'] as $date=>$amount)<tr><td>{{ $date }}</td><td>{{ number_format($amount) }}đ</td></tr>@empty<tr><td colspan="2"><x-admin.empty message="Chưa có thanh toán thành công trong kỳ." /></td></tr>@endforelse</tbody></table>
<h2 class="h5">Theo sân</h2><table class="table"><thead><tr><th>Sân</th><th>Lượt thuộc giao dịch đã thanh toán</th><th>Doanh thu</th></tr></thead><tbody>@foreach($revenue['courts'] as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['slots'] }}</td><td>{{ number_format($row['amount']) }}đ</td></tr>@endforeach</tbody></table>
</section>
<section class="card p-4 mb-4"><h2 class="h4">Booking theo ngày sử dụng sân</h2>
<p>Đếm booking có lượt sân trong kỳ, không dựa vào ngày thanh toán. Booking có nhiều lượt được đếm một lần trong mỗi chỉ số; các nhóm trạng thái có thể giao nhau. Sự cố tính theo ngày của lượt sân gốc, kể cả khi khách đã đổi lịch.</p>
<div class="row g-3">
@foreach(['total'=>'Tổng booking', 'completed'=>'Có lượt hoàn thành', 'cancelled'=>'Có lượt đã hủy', 'incident'=>'Bị ảnh hưởng do sự cố'] as $key=>$label)<div class="col-md-3"><span>{{ $label }}</span><strong class="d-block fs-3">{{ $bookings[$key] }}</strong></div>@endforeach
</div>
<p class="mt-3">Giá trị lịch sân còn hiệu lực sau giảm giá: <strong>{{ number_format($bookings['scheduled_value']) }}đ</strong>. Đây là giá trị lịch đặt, không phải tiền đã thu hoặc doanh thu kế toán.</p>
<table class="table"><thead><tr><th>Ngày thanh toán</th><th>Booking</th><th>Lượt sân</th><th>Lượt đã hủy</th></tr></thead><tbody>@forelse($bookings['daily']->sortKeys() as $date=>$row)<tr><td>{{ $date }}</td><td>{{ $row['bookings'] }}</td><td>{{ $row['slots'] }}</td><td>{{ $row['cancelled'] }}</td></tr>@empty<tr><td colspan="4"><x-admin.empty message="Chưa có dữ liệu." /></td></tr>@endforelse</tbody></table>
</section>
<section class="card p-4 mb-4"><h2 class="h4">Báo cáo hoàn tiền</h2>
<p>Trạng thái hiện tại của các yêu cầu được tạo trong kỳ:</p>
<div class="row g-3">@foreach(['requests'=>'Tổng yêu cầu', 'completed'=>'Đã hoàn', 'processing'=>'Đang xử lý / chờ duyệt', 'rejected'=>'Từ chối'] as $key=>$label)<div class="col-md-3"><span>{{ $label }}</span><strong class="d-block fs-3">{{ $refunds[$key] }}</strong></div>@endforeach</div>
<hr><p>Tiền thực hoàn theo ngày hoàn thành giao dịch trong kỳ (kể cả yêu cầu tạo từ kỳ trước): <strong>{{ number_format($refunds['amount']) }}đ</strong> · {{ $refunds['transactions'] }} giao dịch.</p>
<p>Trong các payment có hoàn tiền trong kỳ: <strong>{{ $refunds['full'] }}</strong> đã hoàn toàn bộ, <strong>{{ $refunds['partial'] }}</strong> hoàn một phần, tính theo số tiền hoàn lũy kế đến hết kỳ.</p>
<div class="row"><div class="col-md-6"><h3 class="h5">Nguyên nhân</h3><table class="table"><thead><tr><th>Lý do</th><th>Giao dịch</th><th>Tiền đã hoàn</th></tr></thead><tbody>@forelse($refunds['reasons'] as $reason=>$row)<tr><td>{{ \App\Models\RefundRequest::REASONS[$reason] ?? 'Khác / chưa phân loại' }}</td><td>{{ $row['count'] }}</td><td>{{ number_format($row['amount']) }}đ</td></tr>@empty<tr><td colspan="3"><x-admin.empty message="Chưa có khoản hoàn thành công." /></td></tr>@endforelse</tbody></table></div>
<div class="col-md-6"><h3 class="h5">Sân liên quan</h3><table class="table"><thead><tr><th>Sân</th><th>Giao dịch</th><th>Tiền đã hoàn</th></tr></thead><tbody>@forelse($refunds['courts'] as $court=>$row)<tr><td>{{ $court }}</td><td>{{ $row['count'] }}</td><td>{{ number_format($row['amount']) }}đ</td></tr>@empty<tr><td colspan="3"><x-admin.empty message="Chưa có khoản hoàn thành công." /></td></tr>@endforelse</tbody></table></div></div>
<p class="small text-muted">Khoản hoàn gắn sự cố được tính cho sân gốc. Khoản hoàn chung cho booking nhiều sân được xếp riêng, không cộng lặp cho từng sân.</p>
<a href="{{ route('special-refunds.index') }}">Mở danh sách yêu cầu cần xử lý</a>
</section>
</x-admin.workspace>@endsection

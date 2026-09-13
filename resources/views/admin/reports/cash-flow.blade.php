@extends('layouts.admin')
@section('page_heading', 'Báo cáo thanh toán / dòng tiền')
@section('content')
<div class="d-flex flex-wrap gap-3 justify-content-between mb-4">
<a class="btn btn-outline-success" href="{{ route('admin.reports.index', ['from'=>$from->toDateString(), 'to'=>$to->toDateString()]) }}">Báo cáo doanh thu sân</a>
<form class="d-flex gap-2" method="GET"><label>Từ ngày<input type="date" class="form-control" name="from" value="{{ $from->toDateString() }}"></label><label>Đến ngày<input type="date" class="form-control" name="to" value="{{ $to->toDateString() }}"></label><button class="btn btn-dark">Áp dụng</button></form>
</div>
<section class="card p-4"><h1 class="h4">Tiền thu và hoàn tiền trong kỳ</h1>
<div class="row g-3 my-3">@foreach(['gross_revenue'=>'Tiền đã thu', 'refund_amount'=>'Đã hoàn tiền', 'net_revenue'=>'Tiền thu ròng'] as $key=>$label)<div class="col-md-4"><span>{{ $label }}</span><strong class="d-block fs-3">{{ number_format($cash[$key]) }}đ</strong></div>@endforeach</div>
<p>Tiền thu theo ngày thanh toán thành công; tiền hoàn theo ngày chi trả hoàn tất. Thanh toán lịch cố định được tính một lần cho cả lịch. Yêu cầu hoàn đang chờ duyệt hoặc đang xử lý chưa làm giảm dòng tiền. Tiền thu ròng có thể âm khi hoàn khoản thu từ kỳ trước.</p>
<table class="table"><thead><tr><th>Ngày giao dịch</th><th>Tiền đã thu</th><th>Đã hoàn tiền</th><th>Tiền thu ròng</th></tr></thead><tbody>
@forelse($cash['gross_daily']->keys()->merge($cash['refund_daily']->keys())->unique()->sort() as $date)
@php($received = $cash['gross_daily'][$date] ?? 0)
@php($returned = $cash['refund_daily'][$date] ?? 0)
<tr><td>{{ $date }}</td><td>{{ number_format($received) }}đ</td><td>{{ number_format($returned) }}đ</td><td>{{ number_format($received - $returned) }}đ</td></tr>
@empty<tr><td colspan="4">Chưa có giao dịch trong kỳ.</td></tr>@endforelse
</tbody></table></section>
@endsection

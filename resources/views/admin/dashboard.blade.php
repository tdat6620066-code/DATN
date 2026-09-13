@extends('layouts.admin')
@section('page_heading', 'Tổng quan kinh doanh')
@section('content')
@include('partials.ticket-stats')
<a class="btn btn-outline-success mb-3" href="{{ route('admin.reports.index', ['from'=>$from->toDateString(), 'to'=>$to->toDateString()]) }}">Báo cáo theo ngày sử dụng sân & hoàn tiền</a>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
<div><h1 class="h3">Tổng quan kinh doanh</h1><p class="text-muted mb-0">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</p></div>
<form class="d-flex gap-2 align-items-end" method="GET">
<div><label for="from">Từ ngày</label><input id="from" class="form-control" name="from" type="date" value="{{ $from->toDateString() }}"></div>
<div><label for="to">Đến ngày</label><input id="to" class="form-control" name="to" type="date" value="{{ $to->toDateString() }}"></div>
<button class="btn btn-dark">Áp dụng</button></form></div>
<div class="row g-3 mb-4">
@foreach([
['Doanh thu sân', number_format($kpis['revenue']).'đ', 'Lượt đã hoàn thành, theo ngày sử dụng'],
['Tiền đã thu', number_format($kpis['gross_revenue']).'đ', 'Theo ngày thanh toán thành công'],
['Đã hoàn tiền', number_format($kpis['refund_amount']).'đ', 'Chỉ khoản đã chi trả hoàn tất trong kỳ'],
['Tiền thu ròng', number_format($kpis['net_revenue']).'đ', 'Tiền đã thu − tiền đã hoàn'],
] as [$label, $value, $note])
<div class="col-12 col-md-6 col-xl-3"><article class="card h-100 border-0 shadow-sm p-4 {{ $loop->first ? 'bg-success text-white' : '' }}"><span>{{ $label }}</span><strong class="fs-3 my-2">{{ $value }}</strong><small>{{ $note }}</small></article></div>
@endforeach
</div>
<p class="text-muted small">Tiền thu tính theo ngày thanh toán; tiền hoàn tính theo ngày hoàn thành. Khoản hoàn cho giao dịch kỳ trước vẫn trừ trong kỳ hoàn tiền, nên thực nhận có thể âm. Yêu cầu chờ duyệt và hoàn tiền đang xử lý chưa được trừ.</p>
<div class="row g-3 mb-4">
@foreach(['courts' => 'Tổng sân', 'customers' => 'Khách hàng', 'bookings' => 'Booking tạo trong kỳ', 'pending' => 'Booking chờ thanh toán'] as $key => $label)
<div class="col-6 col-xl-3"><div class="card border-0 shadow-sm p-3"><span>{{ $label }}</span><strong class="fs-4">{{ number_format($kpis[$key]) }}</strong></div></div>
@endforeach
</div>
<div class="row g-3">
<div class="col-lg-8"><section class="card border-0 shadow-sm p-3 mb-3"><h2 class="h5">Doanh thu sân theo ngày sử dụng</h2><div style="height:240px"><canvas id="courtRevenueChart"></canvas></div></section><section class="card border-0 shadow-sm p-3"><h2 class="h5">Dòng tiền theo ngày giao dịch</h2><a href="{{ route('admin.reports.cash-flow', ['from'=>$from->toDateString(), 'to'=>$to->toDateString()]) }}">Xem báo cáo dòng tiền</a><div style="height:300px"><canvas id="performanceChart"></canvas></div></section></div>
<div class="col-lg-4"><section class="card border-0 shadow-sm p-3"><h2 class="h5">Sân được đặt nhiều</h2><p>Tỷ lệ lấp đầy: <strong>{{ $kpis['occupancy_rate'] }}%</strong></p>
@forelse($popularCourts as $court)<div class="d-flex justify-content-between border-top py-3"><div><strong>{{ $court->name }}</strong><br><small>{{ $court->courtType?->name }} · {{ $court->code }}</small></div><span>{{ $court->booking_count }} lượt</span></div>@empty<p>Chưa có dữ liệu.</p>@endforelse
</section></div></div>
<section class="report-summary"><div><strong>Booking theo ngày sử dụng</strong><p>Tổng {{ $bookingReport['total'] }} · Có lượt hoàn thành {{ $bookingReport['completed'] }} · Có lượt hủy {{ $bookingReport['cancelled'] }} · Sự cố {{ $bookingReport['incident'] }}</p></div><div><strong>Hoàn tiền trong kỳ</strong><p>{{ number_format($refundReport['amount']) }}đ · Hoàn toàn bộ {{ $refundReport['full'] }} payment · Hoàn một phần {{ $refundReport['partial'] }} payment</p></div></section>
@endsection
@push('styles')<style>.report-summary{display:flex;flex-wrap:wrap;gap:24px;margin:24px 0;padding:20px;background:#fff;border-radius:12px}</style>@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const chartData = @json($chart);
new Chart(document.getElementById('courtRevenueChart'), {
    type: 'bar', data: {labels: chartData.labels, datasets: [{label: 'Doanh thu sân', data: chartData.revenue, backgroundColor: '#23875f'}]},
    options: {responsive: true, maintainAspectRatio: false, scales: {y: {beginAtZero: true, ticks: {callback: v => new Intl.NumberFormat('vi-VN', {notation: 'compact'}).format(v) + 'đ'}}}}
});
new Chart(document.getElementById('performanceChart'), {
type: 'line',
data: {labels: chartData.labels, datasets: [
{label: 'Tổng thanh toán', data: chartData.gross_revenue, type: 'line', borderColor: '#23875f', pointRadius: 2, yAxisID: 'revenue'},
{label: 'Đã hoàn tiền', data: chartData.refund_amount, type: 'line', borderColor: '#d07936', pointRadius: 2, yAxisID: 'revenue'},
{label: 'Tiền thu ròng', data: chartData.net_cash, type: 'line', borderColor: '#0a5266', pointRadius: 2, yAxisID: 'revenue'}
]},
options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}, scales: {
x: {grid: {display: false}},
revenue: {position: 'right', beginAtZero: true, grid: {display: false}, ticks: {callback: v => new Intl.NumberFormat('vi-VN', {notation: 'compact'}).format(v) + 'đ'}}
}}
});
</script>
@endpush

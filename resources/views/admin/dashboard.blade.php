@extends('layouts.admin')
@section('page_heading','Dashboard')
@section('content')
<x-admin.workspace>
@include('partials.ticket-stats')
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4"><div><x-admin.page-heading>Tổng quan kinh doanh</x-admin.page-heading><p class="text-muted mb-0">Theo dõi doanh thu và hoạt động tại SmashZone.</p></div><x-admin.filters class="d-flex flex-wrap align-items-end gap-2"><div><label for="from">Từ ngày</label><input class="form-control" type="date" name="from" id="from" value="{{ $from->toDateString() }}"></div><div><label for="to">Đến ngày</label><input class="form-control" type="date" name="to" id="to" value="{{ $to->toDateString() }}"></div><button class="btn btn-primary">Áp dụng</button></x-admin.filters></div>
<div class="row g-3 mb-3">
@foreach([
['Doanh thu sân',number_format($kpis['revenue'],0,',','.').'đ','Ghi nhận theo ngày thanh toán thành công'],
['Tiền đã thu',number_format($kpis['gross_revenue'],0,',','.').'đ','Theo ngày thanh toán thành công'],
['Đã hoàn tiền',number_format($kpis['refund_amount'],0,',','.').'đ','Khoản hoàn đã chi trả trong kỳ'],
['Tiền thu ròng',number_format($kpis['net_revenue'],0,',','.').'đ','Tiền đã thu trừ tiền đã hoàn'],
] as [$label,$value,$note])<div class="col-sm-6 col-xl-3"><article @class(['admin-kpi','admin-kpi-primary'=>$loop->first])><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $note }}</small></article></div>@endforeach
</div>
<p class="admin-data-summary mb-4">Kỳ tài chính {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}. Tiền thu ròng có thể âm khi hoàn giao dịch của kỳ trước.</p>
<div class="row g-3 mb-4">
@foreach([['Booking hôm nay',$todayStats['bookings'],'Có lịch sử dụng sân hôm nay'],['Sân đang hoạt động',$todayStats['courts'],'Sân hoạt động, không bảo trì'],['Khách đang chơi',$todayStats['playing'],'Khách có booking đã check-in'],['Sự cố',$todayStats['incidents'],'Sự cố chưa kết thúc xử lý']] as [$label,$value,$note])<div class="col-6 col-xl-3"><article class="admin-kpi"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong><small>{{ $note }}</small></article></div>@endforeach
</div>
<div class="row g-3 mb-4">
<div class="col-xl-6"><section class="card admin-chart-card h-100"><h2>Doanh thu sân</h2><p class="admin-data-summary">Theo ngày thanh toán thành công trong kỳ đã chọn.</p><x-admin.trend :values="$chart['revenue']" :labels="$chart['labels']" label="Doanh thu" :money="true" /></section></div>
<div class="col-xl-6"><section class="card admin-chart-card h-100"><h2>Booking</h2><p class="admin-data-summary">Số booking được tạo mỗi ngày trong kỳ.</p><x-admin.trend :values="$chart['bookings']" :labels="$chart['labels']" label="Booking" /></section></div>
<div class="col-xl-6"><section class="card admin-chart-card h-100"><h2>Tỷ lệ sử dụng sân</h2><strong class="display-5 fw-semibold text-success my-3">{{ $kpis['occupancy_rate'] }}%</strong><div class="admin-metric-track" role="meter" aria-label="Tỷ lệ sử dụng sân trong kỳ" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $kpis['occupancy_rate'] }}"><span style="width:{{ $kpis['occupancy_rate'] }}%"></span></div><p class="admin-data-summary mt-3">Theo số slot đã đặt trên tổng sân và khung giờ hoạt động trong kỳ.</p>
@forelse($popularCourts as $court)<div class="d-flex justify-content-between gap-2 border-top py-2"><span>{{ $court->name }}</span><strong>{{ $court->booking_count }} lượt</strong></div>@empty<x-admin.empty />@endforelse</section></div>
<div class="col-xl-6"><section class="card admin-chart-card h-100"><h2>Thanh toán</h2><p class="admin-data-summary">Dòng tiền theo ngày giao dịch.</p><x-admin.trend :values="$chart['net_cash']" :labels="$chart['labels']" label="Tiền thu ròng" :money="true" /><a class="mt-3" href="{{ route('admin.reports.cash-flow',['from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}">Xem tiền thu và hoàn tiền chi tiết <i class="bi bi-arrow-right"></i></a></section></div>
</div>
<section class="card p-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Booking gần đây</h2><a href="{{ route('admin.bookings.index') }}">Xem tất cả</a></div><div class="table-responsive"><table class="table"><thead><tr><th>Booking</th><th>Khách hàng</th><th>Sân / ngày</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@forelse($recentBookings as $booking)<tr><td>{{ $booking->booking_code }}</td><td>{{ $booking->user?->name }}</td><td>{{ $booking->bookingDetails->first()?->court?->name ?? '—' }}<small class="d-block text-muted">{{ $booking->bookingDetails->first()?->booking_date?->format('d/m/Y') }}</small></td><td>{{ number_format($booking->total_amount,0,',','.') }}đ</td><td><span class="badge bg-light text-dark">{{ $booking->status }}</span></td><td><x-admin.row-actions><a href="{{ route('admin.bookings.show',$booking) }}"><i class="bi bi-eye"></i>Xem</a></x-admin.row-actions></td></tr>@empty<tr><td colspan="6"><x-admin.empty message="Chưa có booking." /></td></tr>@endforelse
</tbody></table></div></section>
</x-admin.workspace>
@endsection

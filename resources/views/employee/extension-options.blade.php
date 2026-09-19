@extends('layouts.employee')
@section('page_heading', 'Gia hạn thời gian sân')
@section('content')
<a href="{{ route('employee.bookings.show',$booking) }}">← Booking {{ $booking->booking_code }}</a><h1 class="h3 mt-3">Chọn phương án gia hạn</h1>
<p>Lượt hiện tại: {{ $detail->court->name }} · {{ $detail->timeSlot->name }}. Gia hạn ngày {{ $detail->booking_date->format('d/m/Y') }}: <strong>{{ substr($slots->first()->start_time,0,5) }}–{{ substr($slots->last()->end_time,0,5) }}</strong> ({{ $slots->sum('duration') }} phút).</p>
<form method="GET" class="d-flex gap-2 mb-3"><input type="hidden" name="detail_id" value="{{ $detail->id }}"><label>Số khung giờ<input type="number" name="slot_count" min="1" max="8" value="{{ $count }}" class="form-control" required></label><button class="staff-button align-self-end">Tìm lại</button></form>
<section class="staff-card p-3 mb-4" aria-labelledby="extension-map-title">
<h2 id="extension-map-title" class="h5">Sân nào còn trống để chơi tiếp?</h2>
<p class="text-muted small">Hàng là sân, cột là giờ nối tiếp. Bấm giờ ở đầu cột để xem phương án gia hạn từ lúc kết thúc lượt hiện tại đến hết giờ đó.</p>
<div class="table-responsive" tabindex="0" aria-label="Lịch sân nối tiếp, cuộn ngang để xem thêm giờ">
<table class="table table-bordered align-middle mb-0">
<thead><tr><th scope="col" style="min-width:160px">Sân / Khung giờ</th>@foreach($timeline as $column)<th scope="col" style="min-width:150px"><a class="btn {{ $column['count'] === $count ? 'btn-primary' : 'btn-outline-primary' }} w-100" href="{{ route('employee.bookings.extension-options', ['booking'=>$booking, 'detail_id'=>$detail->id, 'slot_count'=>$column['count']]) }}" @if($column['count'] === $count) aria-current="true" @endif>{{ substr($column['slot']->start_time,0,5) }}–{{ substr($column['slot']->end_time,0,5) }}<span class="d-block small">Gia hạn {{ $column['count'] }} khung</span></a></th>@endforeach</tr></thead>
<tbody>@foreach($options as $option)<tr><th scope="row">{{ $option['court']->name }}@if($option['court']->id === $detail->court_id)<span class="d-block small text-primary">Sân hiện tại</span>@endif</th>
@foreach($timeline as $column)
@php($cell = $column['courts']->get($option['court']->id))
<td class="{{ $cell && $cell['available'] ? 'table-success' : 'table-light' }}">
@if($cell && $cell['available'])<strong class="text-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Còn trống</strong><span class="d-block small">{{ number_format($cell['price'],0,',','.') }}đ</span>
@else<strong>Không khả dụng</strong><span class="d-block small text-muted">{{ $cell['reason'] ?? 'Sân không còn hoạt động.' }}</span>@endif
</td>@endforeach</tr>@endforeach</tbody></table></div>
<p class="small text-muted mt-3 mb-0">Chỉ gia hạn các khung giờ liền nhau. Một ô còn trống chưa có nghĩa toàn bộ khoảng gia hạn đều trống; xem phương án hợp lệ bên dưới.</p>
</section>
<h2 class="h5">Phương án cho {{ $count }} khung giờ đã chọn</h2>
@include('employee.partials.extension-choices')
@endsection

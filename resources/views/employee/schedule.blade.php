@extends('layouts.employee')
@section('title', 'Lịch đặt sân - SmashZone')
@section('page_heading', 'Lịch đặt sân')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/employee-schedule.css') }}">
@endpush
@section('content')
@php
    $paymentLabels = ['PAID'=>'Đã thanh toán','PENDING'=>'Chờ thanh toán','FAILED'=>'Thanh toán thất bại','REFUNDED'=>'Đã hoàn tiền','PARTIALLY_REFUNDED'=>'Đã hoàn tiền một phần'];
    $labels = ['CONFIRMED'=>'● Đã đặt','PENDING_PAYMENT'=>'⏳ Giữ chỗ','CHECKED_IN'=>'▶ Đang chơi','COMPLETED'=>'✓ Hoàn thành','CANCELLED'=>'✕ Đã hủy','NO_SHOW'=>'Khách không đến','INCIDENT'=>'⚠ Sự cố','MAINTENANCE'=>'⚠ Bảo trì'];
    $url = fn ($changes) => route('employee.schedule', array_merge(request()->only('court_id','status','search'), ['mode'=>$mode,'date'=>$date->toDateString()], $changes));
    $previous = $date->copy(); $next = $date->copy();
    if ($mode === 'month') { $previous->subMonthNoOverflow(); $next->addMonthNoOverflow(); } elseif ($mode === 'week') { $previous->subWeek(); $next->addWeek(); } else { $previous->subDay(); $next->addDay(); }
@endphp
<div class="sz-calendar">
<p class="text-muted small">Theo dõi tình trạng sân và booking · {{ $start->format('d/m') }} – {{ $end->format('d/m/Y') }}</p>
<div class="sc-stats">@foreach(['bookings'=>'Đơn trong kỳ','playing'=>'Đang chơi','holds'=>'Giữ chỗ','incidents'=>'Sự cố chưa xử lý'] as $key=>$label)<div><strong>{{ $stats[$key] }}</strong><span>{{ $label }}</span></div>@endforeach</div>
<form class="sc-toolbar" method="GET">
<div class="sc-tabs">@foreach(['day'=>'Ngày','week'=>'Tuần','month'=>'Tháng'] as $value=>$label)<a class="{{ $mode === $value ? 'selected' : '' }}" href="{{ $url(['mode'=>$value]) }}">{{ $label }}</a>@endforeach</div>
<input type="hidden" name="mode" value="{{ $mode }}">
<label><span class="visually-hidden">Ngày xem lịch</span><input type="date" name="date" value="{{ $date->toDateString() }}"></label>
<a href="{{ $url(['date'=>$previous->toDateString()]) }}" aria-label="Kỳ trước">❮</a><a href="{{ $url(['date'=>today()->toDateString()]) }}">Hôm nay</a><a href="{{ $url(['date'=>$next->toDateString()]) }}" aria-label="Kỳ sau">❯</a>
<select name="court_id" aria-label="Lọc sân"><option value="">Tất cả sân</option>@foreach($allCourts as $court)<option value="{{ $court->id }}" @selected(request('court_id') == $court->id)>{{ $court->name }}</option>@endforeach</select>
<select name="status" aria-label="Lọc trạng thái"><option value="">Tất cả trạng thái</option>@foreach($labels as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
<input type="search" name="search" value="{{ request('search') }}" placeholder="Mã đơn / khách hàng" aria-label="Tìm mã đơn hoặc khách hàng"><button class="btn btn-primary btn-sm">Áp dụng</button>
</form>
<div class="sc-legend">@foreach($labels as $key=>$label)<span class="sc-state state-{{ $key }}">{{ $label }}</span>@endforeach<span>✓ Trống: nền nhạt</span></div>
<p class="small text-muted">{{ $mode === 'day' ? 'Bấm booking để xem nhanh và thao tác. Cuộn ngang để xem toàn bộ khung giờ.' : 'Bấm ngày để mở lịch vận hành chi tiết.' }} Số khung sử dụng được tính trên toàn bộ booking, kể cả khi đang lọc.</p>
@if($courts->isEmpty() || $timeSlots->isEmpty())<div class="sc-empty">Chưa có sân hoặc khung giờ phù hợp.</div>
@elseif($mode === 'month')
<div class="sc-month-wrap"><div class="sc-month">@foreach(['T2','T3','T4','T5','T6','T7','CN'] as $label)<strong class="sc-weekday">{{ $label }}</strong>@endforeach
@foreach($dates as $day)
@php
$daily = collect($courts)->map(fn ($c) => $cells[$c->id.'|'.$day->toDateString()]);
$capacity = $courts->count() * $timeSlots->count(); $used = $daily->sum('used');
@endphp
<article class="sc-month-day {{ $day->month !== $date->month ? 'outside' : '' }} {{ $day->isToday() ? 'today' : '' }}"><a href="{{ $url(['mode'=>'day','date'=>$day->toDateString()]) }}"><strong>{{ $day->format('d/m') }}</strong> · {{ $daily->sum('count') }} lượt</a><progress max="{{ max(1,$capacity) }}" value="{{ $used }}"></progress>
@php $shown = 0; @endphp
@foreach($courts as $monthCourt) @foreach($cells[$monthCourt->id.'|'.$day->toDateString()]['blocks'] as $monthBlock)
@if($shown < 2)
<button type="button" class="sc-block state-{{ $monthBlock['state'] }}" data-dialog="booking-{{ $monthCourt->id }}-{{ $day->format('Ymd') }}-{{ $monthBlock['booking']->id }}-{{ $loop->index }}"><strong>{{ $monthBlock['start'] }}–{{ $monthBlock['end'] }}</strong><span>{{ $monthCourt->name }} · {{ $monthBlock['booking']->booking_code }}</span><small>{{ $monthBlock['booking']->user?->name }}</small></button>
@php $shown++; @endphp
@endif
@endforeach @endforeach
<a class="small" href="{{ $url(['mode'=>'day','date'=>$day->toDateString()]) }}">Xem lịch ngày</a></article>
@endforeach</div></div>
@else
<div class="sc-scroll"><table class="sc-table"><thead><tr><th class="sc-court">Sân</th>
@if($mode === 'day')<th><div class="sc-hours" style="--slots:{{ $timeSlots->count() }}">@foreach($timeSlots as $slot)<span>{{ substr($slot->start_time,0,5) }}<small>{{ substr($slot->end_time,0,5) }}</small></span>@endforeach</div></th>
@else @foreach($dates as $day)<th class="{{ $day->isToday() ? 'today' : '' }}"><a href="{{ $url(['mode'=>'day','date'=>$day->toDateString()]) }}">{{ ['CN','T2','T3','T4','T5','T6','T7'][$day->dayOfWeek] }} · {{ $day->format('d/m') }}</a></th>@endforeach @endif
</tr></thead><tbody>
@foreach($courts as $court)<tr><th class="sc-court">{{ $court->name }}<small>{{ $court->courtType?->name }}</small></th>
@foreach($dates as $day)
@php $cell = $cells[$court->id.'|'.$day->toDateString()]; @endphp
<td>
@if($cell['issue'] && !request('search') && (!request('status') || request('status') === 'INCIDENT'))<div class="sc-block state-INCIDENT mb-2"><strong>Sự cố sân chưa xử lý</strong><small>{{ Str::limit($cell['issue']->description, 120) }}</small></div>@endif
@if($mode === 'week')<a class="sc-utilization" href="{{ $url(['mode'=>'day','date'=>$day->toDateString(),'court_id'=>$court->id]) }}">{{ $cell['used'] }}/{{ $timeSlots->count() }} khung đã đặt <small>· {{ $cell['free'] }} trống</small></a>@endif
<div class="{{ $mode === 'day' ? 'sc-track' : 'sc-week-blocks' }}" style="--slots:{{ $timeSlots->count() }}">
@foreach($cell['available'] as $freeSlot)
<a class="sc-free" @if($mode === 'day') style="grid-column:{{ $timeSlots->search(fn ($s) => $s->id === $freeSlot) + 1 }}" @endif href="{{ route('employee.counter.create', ['court_id'=>$court->id, 'booking_date'=>$day->toDateString(), 'time_slot_id'=>$freeSlot]) }}" title="Tạo booking" aria-label="Tạo booking {{ $court->name }} {{ $day->format('d/m/Y') }} {{ substr($timeSlots->firstWhere('id', $freeSlot)->start_time, 0, 5) }}"><span aria-hidden="true">+</span></a>
@endforeach
@foreach($cell['blocked'] as $slotId=>$reason)
@if(!request('search') && (!request('status') || request('status') === 'MAINTENANCE'))
<div class="sc-block state-MAINTENANCE" @if($mode === 'day') style="grid-column:{{ $timeSlots->search(fn ($s)=>$s->id === $slotId)+1 }}" @endif title="{{ $reason }}">⚠ Bảo trì<small>{{ substr($timeSlots->firstWhere('id',$slotId)->start_time,0,5) }} · {{ $reason }}</small></div>
@endif @endforeach
@foreach($cell['blocks'] as $block)
@php $booking=$block['booking']; $dialogId='booking-'.$court->id.'-'.$day->format('Ymd').'-'.$booking->id.'-'.$loop->index; @endphp
<button type="button" class="sc-block state-{{ $block['state'] }}" @if($mode === 'day') style="grid-column:{{ $block['column'] }} / span {{ $block['span'] }}" @endif data-dialog="{{ $dialogId }}"><strong>{{ $block['start'] }} – {{ $block['end'] }}</strong><span>{{ $booking->booking_code }}</span><span>{{ $booking->user?->name }}</span><small>{{ $labels[$block['state']] }}{{ $block['incident'] ? ' · ⚠ Sự cố' : '' }}</small><small>{{ $paymentLabels[$booking->payment?->status ?? $booking->payment_status] ?? $booking->payment_status }}</small>@if($block['state'] === 'PENDING_PAYMENT' && $booking->hold_expires_at)<small data-hold-until="{{ $booking->hold_expires_at->toIso8601String() }}">Đang giữ chỗ</small>@endif</button>
@endforeach
</div></td>
@endforeach</tr>@endforeach
</tbody></table></div>
@endif
@foreach($courts as $court) @foreach($dates as $day) @foreach($cells[$court->id.'|'.$day->toDateString()]['blocks'] as $block)
@php
$booking=$block['booking']; $dialogId='booking-'.$court->id.'-'.$day->format('Ymd').'-'.$booking->id.'-'.$loop->index;
$services=$booking->services->filter(fn ($line)=>!$line->service_order_id || $booking->serviceOrders->contains(fn ($o)=>$o->id === $line->service_order_id && $o->status !== 'CANCELLED'));
$extra=$booking->serviceOrders->where('status','!=','CANCELLED')->sum(fn ($o)=>(float)($o->payment?->amount ?? 0));
$phone=$booking->user?->phone; $masked=$phone ? substr($phone,0,2).str_repeat('*',max(0,strlen($phone)-4)).substr($phone,-2) : 'Chưa có';
@endphp
<dialog id="{{ $dialogId }}" class="sc-dialog" aria-labelledby="{{ $dialogId }}-title"><div class="sc-dialog-heading"><div><small>CHI TIẾT BOOKING</small><h2 id="{{ $dialogId }}-title">{{ $booking->booking_code }}</h2></div><button type="button" data-close aria-label="Đóng">×</button></div>
<dl><dt>Khách</dt><dd>{{ $booking->user?->name }} · {{ $masked }}</dd><dt>Sân / ngày</dt><dd>{{ $court->name }} · {{ $day->format('d/m/Y') }}</dd><dt>Giờ</dt><dd>{{ $block['start'] }} – {{ $block['end'] }}</dd><dt>Booking</dt><dd>{{ $labels[$block['state']] }}{{ $block['incident'] ? ' · ⚠ Sự cố chưa xử lý' : '' }}</dd><dt>Thanh toán sân</dt><dd>{{ ['PAID'=>'Đã thanh toán','PENDING'=>'Chờ thanh toán','REFUNDED'=>'Đã hoàn tiền','PARTIALLY_REFUNDED'=>'Đã hoàn một phần'][$booking->payment_status] ?? $booking->payment_status }}</dd><dt>Tiền booking</dt><dd>{{ number_format($booking->total_amount,0,',','.') }}đ</dd><dt>Dịch vụ thu riêng</dt><dd>{{ number_format($extra,0,',','.') }}đ</dd><dt>Tổng booking + dịch vụ</dt><dd><strong>{{ number_format($booking->total_amount+$extra,0,',','.') }}đ</strong></dd></dl>
<p class="small text-muted">Số tiền tính cho toàn booking; dịch vụ cũ đã gộp trong booking không cộng lại.</p>
<h3 class="h6">Dịch vụ</h3>@if($services->isNotEmpty())<ul>@foreach($services as $line)<li>{{ $line->quantity }} × {{ $line->item?->name }}</li>@endforeach</ul>@else<p class="small text-muted">Chưa có dịch vụ đi kèm.</p>@endif
@include('employee.partials.schedule-actions')
</dialog>
@endforeach @endforeach @endforeach
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/employee-schedule.js') }}" defer></script>
@endpush

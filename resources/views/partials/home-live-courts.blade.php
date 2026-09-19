<section class="sz-live-section"><div class="sz-home-container sz-live-layout">
<div class="sz-live-board"><div class="sz-live-board-title"><span>LỊCH SÂN HÔM NAY</span><time>{{ today()->format('d.m.Y') }}</time></div>
@forelse($liveCourts as $row)<div class="sz-live-row"><h3>{{ $row['court']->name }}</h3><div class="sz-live-slots">
@forelse($row['slots'] as $entry)
@php($label = match($entry['status']) {'AVAILABLE'=>'Còn trống','BOOKED'=>'Đã đặt','HOLD'=>'Đang giữ',default=>'Không khả dụng'})
@if($entry['status'] === 'AVAILABLE')<a href="{{ route('courts.show', ['court'=>$row['court'], 'booking_date'=>today()->toDateString(), 'time_slot_id'=>$entry['slot']->id]) }}#court-schedule" class="sz-live-slot is-available"><strong>{{ substr($entry['slot']->start_time,0,5) }}</strong><span>{{ $label }} ↗</span></a>
@else<div class="sz-live-slot"><strong>{{ substr($entry['slot']->start_time,0,5) }}</strong><span>{{ $label }}</span></div>@endif
@empty<p>Đã hết khung giờ hôm nay. Chọn ngày khác để xem lịch.</p>@endforelse
</div></div>@empty<p>Lịch sân đang được cập nhật.</p>@endforelse
<p class="sz-live-note">Lịch tại thời điểm tải trang. Hệ thống kiểm tra lại khi bạn đặt sân.</p></div>
<div><span class="sz-eyebrow">YOUR COURT. YOUR GAME.</span><h2>Trận đấu của bạn<br>bắt đầu từ đây.</h2><p>Một khung giờ phù hợp. Một cuộc hẹn với đồng đội. Chọn lịch và sẵn sàng ra sân.</p><a class="btn btn-light" href="{{ route('bookings.create') }}">Xem toàn bộ lịch sân →</a></div>
</div></section>

@if($sessionBookings->count() > 1)
<section class="staff-card p-4 mt-3"><h2 class="h5">Phiên chơi liên tục</h2><p>Mỗi lượt có booking và thanh toán riêng. Khi đến giờ, mở lượt đã trả tiền và bấm check-in để tiếp tục chơi. Các lượt đã hết giờ có thể chờ kết thúc chung khi khách dừng chơi; nếu chuyển sân, sân cũ được giải phóng khi không còn khách đang sử dụng.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Booking</th><th>Sân / giờ</th><th>Trạng thái</th><th>Tiền lượt chơi</th><th>Thanh toán</th></tr></thead><tbody>
@foreach($sessionBookings as $member)<tr><td><a href="{{ route('employee.bookings.show',$member) }}">{{ $member->booking_code }}</a>@if($member->extension_of_id)<small class="d-block">Lượt gia hạn</small>@endif</td><td>@foreach($member->bookingDetails->where('status','!=','CANCELLED') as $line)<div>{{ $line->court->name }} · {{ $line->booking_date->format('d/m') }} · {{ $line->timeSlot->name }}</div>@endforeach</td><td>{{ ['PENDING_PAYMENT'=>'Chờ thanh toán','CONFIRMED'=>'Đã xác nhận','CHECKED_IN'=>'Đang chơi','COMPLETED'=>'Hoàn thành','CANCELLED'=>'Đã hủy','EXPIRED'=>'Hết hạn'][$member->status] ?? $member->status }}</td><td>{{ number_format($member->total_amount) }}đ</td><td>{{ $member->payment_status }}</td></tr>@endforeach
</tbody></table></div>
@if(auth()->user()->hasPermission('bookings.checkout') && $sessionBookings->contains('status','CHECKED_IN'))
<form method="POST" action="{{ route('employee.bookings.session-checkout',$booking) }}">@csrf<button class="staff-button">Kết thúc phiên chơi</button></form><small>Chỉ hoàn tất khi các lượt đã thanh toán đủ và đã xử lý thiết bị mượn.</small>
@endif</section>
@endif

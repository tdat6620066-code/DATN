@php($actions = $bookingActions[$booking->id])
<div class="sc-actions">
    @if($actions['checkin'])<form method="POST" action="{{ route('employee.bookings.check-in', $booking) }}" data-operation-form>@csrf<button class="btn btn-primary btn-sm" data-staff-action="checkin">Check-in</button></form>@endif
    @if($actions['services'])<a class="btn btn-outline-primary btn-sm" data-staff-action="services" href="{{ route('employee.bookings.show', $booking) }}#booking-services">Thêm dịch vụ</a>@endif
    @if($actions['extend'])<a class="btn btn-outline-primary btn-sm" data-staff-action="extend" href="{{ route('employee.bookings.show', $booking) }}?operation=extension">Gia hạn sân</a>@endif
    @if($actions['incident'])<a class="btn btn-outline-danger btn-sm" data-staff-action="incident" href="{{ route('employee.incidents.index', ['court_id'=>$court->id,'booking_id'=>$booking->id]) }}">Báo sự cố</a>@endif
    @if($actions['checkout'])<a class="btn btn-primary btn-sm" data-staff-action="checkout" href="{{ route('employee.bookings.show', $booking) }}?operation=checkout">Check-out</a>@endif
    @if($actions['view'])<a class="btn btn-outline-secondary btn-sm" href="{{ route('employee.bookings.show', $booking) }}">Xem chi tiết</a>@endif
</div>
@if($booking->status === 'CHECKED_IN' && !$actions['checkout'] && auth()->user()->hasPermission('bookings.checkout') && $actions['view'])<p class="small text-muted mt-3 mb-0">Mở chi tiết để kiểm tra thanh toán, dịch vụ và đồ thuê trước khi check-out.</p>@endif

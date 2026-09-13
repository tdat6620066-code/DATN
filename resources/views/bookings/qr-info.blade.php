<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Thông tin đơn {{ $booking->booking_code }} - SmashZone</title>
    <style>body{margin:0;padding:24px;background:#f3f6f5;color:#153442;font:16px/1.6 system-ui,sans-serif}main{max-width:640px;margin:auto;background:white;padding:24px;border-radius:18px}h1{font-size:24px}h2{font-size:18px}article{padding:12px 0;border-bottom:1px solid #dde5e1}strong{overflow-wrap:anywhere}.status{background:#e8f9f1;padding:12px;border-radius:8px}a{color:#087347}.note{color:#64748b}</style>
</head>
<body><main>
    <h1>Thông tin đơn đặt sân</h1>
    <p>SmashZone · <strong>{{ $booking->booking_code }}</strong></p>
    <p class="status">{{ ['PENDING_PAYMENT'=>'Chờ thanh toán', 'CONFIRMED'=>'Đã xác nhận', 'CHECKED_IN'=>'Đã nhận sân', 'COMPLETED'=>'Đã hoàn thành', 'CANCELLED'=>'Đã hủy', 'EXPIRED'=>'Đã hết hạn'][$booking->status] ?? 'Chưa xác định' }}</p>
    <p>Thanh toán: <strong>{{ ['PENDING'=>'Chưa thanh toán', 'PAID'=>'Đã thanh toán', 'REFUNDED'=>'Đã hoàn tiền', 'PARTIALLY_REFUNDED'=>'Đã hoàn một phần'][$booking->payment_status] ?? 'Chưa xác nhận' }}</strong></p>
    <h2>Lịch đặt sân</h2>
    @foreach($booking->bookingDetails as $detail)
        <article><strong>{{ $detail->court->name }}</strong><br>{{ $detail->booking_date->format('d/m/Y') }} · {{ $detail->timeSlot->name }}
            @if($detail->status === 'CANCELLED')<span> · Đã hủy</span>@endif
        </article>
    @endforeach
    <h2>Dịch vụ đặt cùng sân</h2>
    @forelse($booking->services->whereNull('service_order_id') as $line)
        <article>{{ $line->item?->name }} × {{ $line->quantity }} · {{ number_format($line->subtotal, 0, ',', '.') }}đ</article>
    @empty<p>Không có dịch vụ đặt kèm.</p>@endforelse
    <p>Tổng tiền đặt sân và dịch vụ: <strong>{{ number_format($booking->total_amount, 0, ',', '.') }}đ</strong></p>
    <p class="note">Quét mã chỉ mở thông tin đơn. Nhân viên xác nhận nhận sân sau khi kiểm tra đơn và thanh toán.</p>
    @if(auth()->check() && in_array(auth()->user()->role, ['ADMIN', 'EMPLOYEE']) && auth()->user()->hasPermission('bookings.view'))
        <a href="{{ route(auth()->user()->role === 'ADMIN' ? 'admin.bookings.show' : 'employee.bookings.show', $booking) }}">Mở đơn để kiểm tra và nhận sân</a>
    @elseif(auth()->id() === $booking->user_id)
        <a href="{{ route('bookings.show', $booking) }}">Xem chi tiết đơn của tôi</a>
    @else
        <p class="note">Để quản lý đơn, vui lòng mở website SmashZone hoặc liên hệ nhân viên tại sân.</p>
    @endif
</main></body></html>

@extends('layouts.app')
@section('title', 'Tài khoản — SmashZone')
@section('content')
<div data-profile-dashboard>
<x-customer-shell :active="$section">
    @php($titles = ['overview' => 'Tổng quan', 'history' => 'Booking của tôi', 'fixed' => 'Đặt sân cố định', 'reviews' => 'Đánh giá của tôi', 'support' => 'Hỗ trợ', 'account' => 'Hồ sơ', 'password' => 'Đổi mật khẩu'])
    <header class="sz-dashboard-heading"><div><h1>{{ $titles[$section] }}</h1><p>{{ $section === 'overview' ? 'Chào '.$user->name.', sẵn sàng cho trận đấu tiếp theo?' : 'Quản lý lịch chơi và tài khoản SmashZone của bạn.' }}</p></div></header>
    @if($section === 'overview')
        <section class="sz-dashboard-section"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h2 class="mb-0">Lịch chơi sắp tới</h2><a href="{{ route('bookings.index') }}">Xem tất cả <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
            @forelse($upcomingBookings as $booking)<x-customer-booking-card :booking="$booking"/>@empty<x-empty-state icon="bi-calendar2-plus" title="Bạn chưa có lịch chơi sắp tới" description="Chọn sân và khung giờ phù hợp để lên lịch buổi chơi tiếp theo."/><a class="btn btn-primary mt-3" href="{{ route('bookings.create') }}">Đặt sân ngay</a>@endforelse
        </section>
        <div class="sz-dashboard-stats">
            @foreach([['calendar2-check', 'Booking sắp tới', $dashboardStats['upcoming'], 'Đơn còn hiệu lực'], ['check2-circle', 'Đã hoàn thành', $dashboardStats['completed'], 'Buổi chơi hoàn thành'], ['clock', 'Tổng giờ chơi', number_format($dashboardStats['hours'], 1, ',', '.'), 'Theo lịch đã hoàn thành'], ['ticket-perforated', 'Ưu đãi', $dashboardStats['offers'], 'Mã còn hiệu lực']] as [$icon, $label, $value, $note])
                <div class="sz-dashboard-stat"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $note }}</small></div>
            @endforeach
        </div>
        <section class="sz-dashboard-section" id="offers"><h2 class="mb-3">Ưu đãi dành cho buổi chơi</h2><div class="sz-customer-offers">@forelse($offers as $offer)<article class="sz-customer-panel"><h3 class="h6">{{ $offer->name }}</h3><code>{{ $offer->code }}</code><p class="small mt-2 mb-1">Đơn tối thiểu {{ number_format($offer->min_order_amount, 0, ',', '.') }}đ</p><p class="small text-muted mb-0">{{ $offer->end_at ? 'Đến '.$offer->end_at->format('d/m/Y H:i') : 'Không giới hạn ngày kết thúc' }}. Mã được kiểm tra khi đặt sân.</p></article>@empty<p class="text-muted">Hiện chưa có ưu đãi còn hiệu lực.</p>@endforelse</div></section>
    @elseif($section === 'history')
        <form method="GET" action="{{ route('profile') }}" class="sz-customer-filter"><input type="hidden" name="section" value="history"><div><label for="status" class="form-label">Trạng thái</label><select id="status" name="status" class="form-select"><option value="">Tất cả</option>@foreach(['PENDING_PAYMENT'=>'Chờ xác nhận','CONFIRMED'=>'Đã xác nhận','CHECKED_IN'=>'Đang chơi','COMPLETED'=>'Hoàn thành','CANCELLED'=>'Đã hủy','EXPIRED'=>'Đã hết hạn','NO_SHOW'=>'Không đến sân'] as $value=>$label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></div><div><label for="date_from" class="form-label">Ngày tạo đơn từ</label><input id="date_from" type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}"></div><div><label for="date_to" class="form-label">Đến ngày</label><input id="date_to" type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}"></div><button class="btn btn-primary" type="submit">Lọc</button></form>
        @forelse($bookings as $booking)<x-customer-booking-card :booking="$booking"/>@empty<x-empty-state title="Không có booking phù hợp" description="Thử thay đổi bộ lọc hoặc đặt sân mới."/>@endforelse
        {{ $bookings->appends(['section' => 'history'])->links() }}
    @elseif($section === 'fixed')
        <a href="{{ route('bookings.create-recurring') }}" class="btn btn-primary mb-4">Đặt lịch cố định mới</a>
        @forelse($fixedBookings as $group)
            <x-customer-fixed-card :group="$group"/>
        @empty<x-empty-state icon="bi-calendar-week" title="Chưa có lịch cố định" description="Đặt lịch lặp lại để duy trì buổi chơi mỗi tuần."/>@endforelse
        {{ $fixedBookings->links() }}
    @elseif($section === 'reviews')
        <p class="text-muted">Để viết đánh giá, mở booking đã hoàn thành trong <a href="{{ route('bookings.index') }}">Booking của tôi</a> và chọn phần đánh giá trải nghiệm.</p>
        @forelse($customerReviews as $review)<article class="sz-customer-panel"><div class="d-flex justify-content-between flex-wrap gap-2"><h2 class="h6">{{ $review->court?->name ?? 'Sân cầu lông' }}</h2><span class="small text-muted">{{ $review->created_at->format('d/m/Y') }}</span></div><p class="text-warning" aria-label="{{ $review->rating }} trên 5 sao">@for($i=1;$i<=5;$i++)<i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>@endfor</p><p>{{ $review->content }}</p><x-status-badge :label="match($review->status) {'APPROVED'=>'Đã duyệt','PENDING'=>'Chờ duyệt','REJECTED'=>'Không được duyệt',default=>$review->status}"/></article>@empty<x-empty-state icon="bi-star" title="Bạn chưa có đánh giá" description="Các đánh giá của bạn sẽ được hiển thị tại đây."/>@endforelse
        {{ $customerReviews->links() }}
    @elseif($section === 'support')
        <div class="sz-customer-panel"><h2 class="h5">Bạn cần hỗ trợ?</h2><p>Với đơn đã thanh toán, chọn “Báo cáo sự cố” tại booking tương ứng. Các yêu cầu đang mở được theo dõi bên dưới.</p><a class="btn btn-outline-primary" href="{{ route('contacts.index') }}">Liên hệ SmashZone</a></div>
        @forelse($supportTickets as $ticket)<article class="sz-customer-panel"><div class="d-flex justify-content-between gap-2 flex-wrap"><strong>{{ $ticket->court?->name ?? 'Yêu cầu hỗ trợ' }}</strong><x-status-badge :label="\App\Models\CourtIncident::TICKET_STATUSES[$ticket->status] ?? $ticket->status"/></div><p class="small text-muted mt-2">{{ $ticket->created_at->format('d/m/Y H:i') }}</p><p>{{ Str::limit($ticket->description, 180) }}</p><a href="{{ route('incident-tickets.show', $ticket) }}" class="btn btn-outline-primary btn-sm">Theo dõi yêu cầu</a></article>@empty<x-empty-state icon="bi-headset" title="Chưa có yêu cầu hỗ trợ" description="Các yêu cầu hỗ trợ của bạn sẽ xuất hiện tại đây."/>@endforelse
        {{ $supportTickets->links() }}
    @elseif($section === 'account')
        <div class="customer-settings-layout"><aside><span class="customer-kicker">01 / HỒ SƠ</span><h2>Thông tin của bạn.</h2><p>Cập nhật thông tin liên hệ để SmashZone hỗ trợ đúng người, đúng lịch chơi.</p></aside><section class="sz-customer-panel">@include('partials.customer-account-form')</section></div>
    @elseif($section === 'password')
        <div class="customer-settings-layout"><aside><span class="customer-kicker">02 / BẢO MẬT</span><h2>Bảo vệ tài khoản.</h2><p>Sử dụng mật khẩu riêng cho tài khoản SmashZone của bạn.</p></aside><section class="sz-customer-panel">@include('partials.customer-password-form')</section></div>
    @endif
</x-customer-shell>
</div>
@endsection

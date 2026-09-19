@extends('layouts.app')
@section('title', 'SmashZone — Đặt sân nhanh, chơi hết mình')
@section('body_class', 'sz-home')
@push('styles')
    @vite(['resources/css/home.css', 'resources/js/home.js'])
@endpush
@section('content')
@php
    $currentRole = Auth::user()?->role;
    $canBook = ! Auth::check() || in_array($currentRole, ['CUSTOMER', null], true);
    $primaryRoute = $currentRole === 'ADMIN' ? route('admin.dashboard') : ($currentRole === 'EMPLOYEE' ? route('employee.dashboard') : route('bookings.create'));
    $primaryLabel = $canBook ? 'Đặt sân ngay' : ($currentRole === 'ADMIN' ? 'Vào quản trị' : 'Vào vận hành');
    $searchCourts = $featured_courts->concat($most_booked_courts)->unique('id');
    $homeAmenities = $searchCourts->flatMap(fn ($court) => $court->amenities)->unique('id')->take(6);
@endphp
<section class="sz-hero" aria-labelledby="hero-title">
    <img class="sz-cinematic-photo" src="{{ asset('images/club-hero.png') }}" alt="Không gian sân cầu lông SmashZone" width="1536" height="1024" fetchpriority="high">
    <div class="sz-court-lines" aria-hidden="true"></div><span class="sz-hero-word" aria-hidden="true">SMASH</span>
    <div class="sz-home-container sz-cinematic-content"><span class="sz-eyebrow">SMASHZONE / BOOK · PLAY · CONNECT</span>
        <h1 id="hero-title">KHÔNG CHỈ<br>LÀ MỘT<br><span>TRẬN ĐẤU.</span></h1>
        <p>Khám phá không gian cầu lông hiện đại, chọn sân và bắt đầu trận đấu chỉ trong vài phút.</p>
        <div class="sz-hero-actions"><a class="btn btn-primary" href="{{ $primaryRoute }}">{{ $primaryLabel }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a class="sz-hero-explore" href="#featured">Khám phá sân ↓</a></div>
        <div class="sz-hero-trust"><strong>{{ number_format($statistics['courts']) }}</strong><span>Sân đang hoạt động</span><span class="sz-trust-divider"></span><strong>{{ $statistics['rating'] ?: '—' }}</strong><span>{{ $statistics['rating'] ? 'Điểm đánh giá / 5' : 'Chờ đánh giá từ bạn' }}</span></div>
    </div>
    <a class="sz-float-note" href="{{ route('bookings.create') }}"><span>FIND YOUR NEXT GAME</span><strong>Sân của bạn.<br>Giờ của bạn.</strong><small>Kiểm tra lịch sân →</small></a>
    <a class="sz-scroll-cue" href="#featured">SCROLL TO EXPLORE <span>↓</span></a>
</section>
<div class="sz-home-container sz-floating-search"><form class="sz-booking-search" action="{{ route('courts.index') }}" method="GET" aria-label="Tìm sân cầu lông">
            <h2 class="sz-search-title"><span>QUICK BOOKING</span>Tìm sân. Vào trận.</h2><div><label for="home-date"><i class="bi bi-calendar3" aria-hidden="true"></i> Ngày</label><input class="form-control" id="home-date" type="date" name="booking_date" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required></div>
            <div><label for="home-court"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Sân</label><select class="form-select" id="home-court" name="keyword"><option value="">Tất cả sân</option>@foreach($searchCourts as $court)<option value="{{ $court->name }}">{{ $court->name }}</option>@endforeach</select></div>
            <div><label for="home-time"><i class="bi bi-clock" aria-hidden="true"></i> Khung giờ</label><select class="form-select" id="home-time" name="time_slot_id"><option value="">Tất cả khung giờ</option>@foreach($timeSlots as $slot)<option value="{{ $slot->id }}">{{ \Carbon\Carbon::parse($slot->start_time)->format('H:i') }}</option>@endforeach</select></div>
            <div><label for="home-type">Loại sân</label><select class="form-select" id="home-type" name="court_type_id"><option value="">Tất cả loại sân</option>@foreach($searchCourts->pluck('courtType')->filter()->unique('id') as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Tìm sân →</button>
        </form>
        </div>
<section class="sz-home-section" id="featured" aria-labelledby="featured-title"><div class="sz-home-container">
    <div class="sz-section-heading"><div><span class="sz-eyebrow">EXPLORE THE ZONE</span><h2 id="featured-title">Chọn trải nghiệm<br>phù hợp với trận đấu.</h2><p>Mỗi không gian, một nhịp chơi. Khám phá sân dành cho bạn.</p></div><a class="sz-text-link" href="{{ route('courts.index') }}">Tất cả sân <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
    @if($featured_courts->isNotEmpty())<div class="sz-universe-grid">@foreach($featured_courts->take(5) as $court)@include('partials.home-court-card', ['court' => $court])@endforeach</div>
    @else<x-empty-state title="Danh sách sân đang được cập nhật" description="Vui lòng quay lại sau để xem các sân đang hoạt động."/>@endif
</div></section>

@include('partials.home-live-courts')
<section class="sz-home-section sz-section-white" id="why" aria-labelledby="why-title"><div class="sz-home-container">
    <div class="sz-section-heading"><div><span class="sz-eyebrow">TẬP TRUNG VÀO TRẬN ĐẤU</span><h2 id="why-title">Vì sao chọn SmashZone?</h2><p>Mọi thứ bạn cần cho một buổi chơi trọn vẹn.</p></div></div>
    <div class="sz-benefits-grid">
        <article class="sz-benefit"><i class="bi bi-calendar2-check" aria-hidden="true"></i><h3>Chủ động lịch chơi</h3><p>Xem lịch sân theo ngày và chọn khung giờ phù hợp với bạn.</p></article>
        <article class="sz-benefit"><i class="bi bi-wallet2" aria-hidden="true"></i><h3>Chi phí rõ ràng</h3><p>Xem giá sân và tổng tiền trước khi xác nhận đặt lịch.</p></article>
        <article class="sz-benefit"><i class="bi bi-arrow-repeat" aria-hidden="true"></i><h3>Đặt sân linh hoạt</h3><p>Đặt theo ngày hoặc tạo lịch định kỳ cho nhóm chơi của bạn.</p></article>
        <article class="sz-benefit"><i class="bi bi-headset" aria-hidden="true"></i><h3>Hỗ trợ ngay tại đây</h3><p>Gửi yêu cầu hỗ trợ và theo dõi phản hồi từ tài khoản của bạn.</p></article>
    </div>
</div></section>

<section class="sz-home-section" id="how-it-works" aria-labelledby="steps-title"><div class="sz-home-container">
    <div class="sz-section-heading sz-heading-center"><div><span class="sz-eyebrow">ĐƠN GIẢN TỪ BƯỚC ĐẦU</span><h2 id="steps-title">Chỉ bốn bước.<br>Sẵn sàng ra sân.</h2></div></div>
    <ol class="sz-steps"><li><span>01</span><h3>Tìm sân yêu thích</h3><p>Khám phá hình ảnh, tiện ích và giá sân phù hợp.</p></li><li><span>02</span><h3>Chọn ngày & khung giờ</h3><p>Chọn giờ còn trống và kiểm tra thông tin đặt sân.</p></li><li><span>03</span><h3>Thanh toán</h3><p>Kiểm tra tổng tiền và hoàn tất thanh toán cho booking.</p></li><li><span>04</span><h3>Ra sân</h3><p>Theo dõi lịch đặt, mang mã QR đến sân và bắt đầu trận đấu.</p></li></ol>
</div></section>

<section class="sz-home-section sz-app-experience"><div class="sz-home-container sz-app-layout"><div class="sz-phone-preview" aria-label="Minh họa các mục trong tài khoản"><span>SMASHZONE / MY GAME</span><h3>Sẵn sàng cho<br>trận tiếp theo?</h3><a href="{{ route('bookings.index') }}">Lịch đặt của tôi <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a href="{{ route('notifications.index') }}">Thông báo <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a href="{{ route('profile') }}">Hồ sơ của tôi <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><small>Truy cập tài khoản để xem thông tin của bạn.</small></div><div><span class="sz-eyebrow">TỪ ĐẶT LỊCH ĐẾN CHECK-IN</span><h2>Mọi thứ.<br>Trong một nơi.</h2><p>Lịch chơi, thanh toán và thông báo nằm ngay trong tài khoản. Mở trên điện thoại, mang theo đến sân.</p><a class="btn btn-primary" href="{{ $primaryRoute }}">{{ $primaryLabel }} →</a></div></div></section>
<section class="sz-home-section sz-section-white" id="offers" aria-labelledby="offers-title"><div class="sz-home-container">
    <div class="sz-section-heading"><div><span class="sz-eyebrow">THÊM NIỀM VUI CHO MỖI TRẬN ĐẤU</span><h2 id="offers-title">Khuyến mãi dành cho bạn</h2><p>Các chương trình đang diễn ra tại SmashZone.</p></div></div>
    @if($promotions->isNotEmpty())<div class="sz-content-grid">@foreach($promotions->take(3) as $promotion)
        @php($promotionImage = $promotion->image ? (Str::startsWith($promotion->image, ['http://', 'https://', '/']) ? $promotion->image : asset('storage/'.$promotion->image)) : null)
        <article class="sz-promotion"><div class="sz-promotion-image">@if($promotionImage)<img src="{{ $promotionImage }}" alt="{{ $promotion->title }}" loading="lazy">@else<i class="bi bi-ticket-perforated" aria-hidden="true"></i>@endif<span class="badge text-bg-success">Ưu đãi</span></div><div class="sz-content-body"><small>{{ $promotion->end_at ? 'Đến '.$promotion->end_at->format('d/m/Y') : 'Đang diễn ra' }}</small><h3>{{ $promotion->title }}</h3><p>{{ Str::limit(strip_tags($promotion->description ?? ''), 140) }}</p><a class="sz-text-link" href="{{ route('courts.index') }}">Khám phá sân <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></article>
    @endforeach</div>@else<x-empty-state icon="bi-ticket-perforated" title="Chưa có chương trình khuyến mãi" description="Các ưu đãi mới sẽ được cập nhật tại đây."/>@endif
</div></section>

<section class="sz-home-section" id="services" aria-labelledby="services-title"><div class="sz-home-container">
    <div class="sz-section-heading"><div><span class="sz-eyebrow">CHO BUỔI CHƠI TRỌN VẸN</span><h2 id="services-title">Dịch vụ & tiện ích tại sân</h2><p>Khám phá tiện ích được cung cấp tại các sân. Chi tiết tùy theo sân bạn chọn.</p></div></div>
    @if($homeServices->isNotEmpty())<div class="sz-services-grid" tabindex="0" role="region" aria-label="Dịch vụ tại sân, cuộn ngang để xem thêm">@foreach($homeServices as $service)<article class="sz-service"><span class="sz-service-number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><i class="bi bi-{{ $service->category === 'RENTAL' ? 'arrow-repeat' : 'bag-check' }}" aria-hidden="true"></i><h3>{{ $service->name }}</h3><p>{{ number_format($service->price,0,',','.') }}đ</p><a href="{{ $primaryRoute }}">Chọn khi đặt sân ↗</a></article>@endforeach</div>
    @else<x-empty-state icon="bi-grid" title="Dịch vụ đang được cập nhật" description="Liên hệ nhân viên để biết dịch vụ tại sân."/>@endif
</div></section>

<section class="sz-home-section sz-section-white" id="reviews" aria-labelledby="reviews-title"><div class="sz-home-container">
    <div class="sz-community-heading"><span class="sz-eyebrow">BOOK · PLAY · CONNECT</span><h2>Không chỉ đến để chơi.<br>Đến để kết nối.</h2><p>Rủ đồng đội, giữ nhịp chơi và chia sẻ trải nghiệm của bạn sau mỗi trận đấu.</p></div>
    <div class="sz-section-heading"><div><span class="sz-eyebrow">TỪ CỘNG ĐỒNG NGƯỜI CHƠI</span><h2 id="reviews-title">Những trải nghiệm được chia sẻ</h2><p>Đánh giá từ khách hàng đã chơi tại SmashZone.</p></div></div>
    @if($reviews->isNotEmpty())<div class="sz-content-grid">@foreach($reviews->take(3) as $review)<article class="sz-review"><div class="sz-review-stars" aria-label="{{ $review->rating }} trên 5 sao">@for($star = 1; $star <= 5; $star++)<i class="bi {{ $star <= $review->rating ? 'bi-star-fill' : 'bi-star' }}" aria-hidden="true"></i>@endfor</div><blockquote>{{ $review->content }}</blockquote><div class="sz-review-person"><span class="sz-review-avatar" aria-hidden="true">{{ Str::upper(Str::substr($review->user?->name ?? 'K', 0, 1)) }}</span><div><strong>{{ $review->user?->name ?? 'Khách hàng' }}</strong><small>{{ $review->court?->name }}</small></div><time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('d/m/Y') }}</time></div></article>@endforeach</div>
    @else<x-empty-state icon="bi-chat-square-heart" title="Chưa có đánh giá được công bố" description="Chia sẻ trải nghiệm của bạn sau mỗi buổi chơi."/>@endif
</div></section>

<section class="sz-home-section" id="news" aria-labelledby="news-title"><div class="sz-home-container">
    <div class="sz-section-heading"><div><span class="sz-eyebrow">NHỊP CẦU SMASHZONE</span><h2 id="news-title">Tin tức & cảm hứng chơi cầu</h2></div><a class="sz-text-link" href="{{ route('information') }}">Xem thêm <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
    @if($news->isNotEmpty())<div class="sz-content-grid">@foreach($news->take(3) as $article)
        @php($newsImage = $article->thumbnail ? (Str::startsWith($article->thumbnail, ['http://', 'https://', '/']) ? $article->thumbnail : asset('storage/'.$article->thumbnail)) : null)
        <article class="sz-news"><a class="sz-news-image" href="{{ route('news.show', $article) }}">@if($newsImage)<img src="{{ $newsImage }}" alt="{{ $article->title }}" loading="lazy">@else<i class="bi bi-newspaper" aria-hidden="true"></i><span class="visually-hidden">{{ $article->title }}</span>@endif</a><div class="sz-content-body"><small>{{ $article->published_at?->format('d/m/Y') }}</small><h3><a href="{{ route('news.show', $article) }}">{{ $article->title }}</a></h3><p>{{ Str::limit(strip_tags($article->content), 120) }}</p><a class="sz-text-link" href="{{ route('news.show', $article) }}">Đọc bài viết <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div></article>
    @endforeach</div>@else<x-empty-state icon="bi-newspaper" title="Tin tức đang được cập nhật" description="Hẹn gặp bạn trong những câu chuyện sắp tới từ SmashZone."/>@endif
</div></section>

<section class="sz-final-section"><div class="sz-home-container"><div class="sz-final-cta"><div><span class="sz-eyebrow">READY TO SMASH?</span><h2>SẴN SÀNG<br>RA SÂN?</h2><p>Chọn sân phù hợp, rủ đồng đội và sẵn sàng chơi hết mình.</p></div><a class="btn btn-light" href="{{ $primaryRoute }}">{{ $primaryLabel }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div></div></section>

@endsection

@php
    $image = $court->images->first()?->url;
    $minimumPrice = $court->prices->where('status', 'ACTIVE')->filter(fn ($price) => (! $price->effective_from || $price->effective_from->lte(today())) && (! $price->effective_to || $price->effective_to->gte(today())))->min('price');
    $rating = $court->approved_rating ? number_format($court->approved_rating, 1) : null;
@endphp
<article class="sz-home-court">
    <div class="sz-court-cover">
        <a href="{{ route('courts.show', $court) }}" aria-label="Xem {{ $court->name }}">
            @if($image)<img src="{{ $image }}" alt="{{ $court->name }}" loading="lazy" width="640" height="400">@else<i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>@endif
        </a>
        <x-status-badge :label="$court->status === 'ACTIVE' ? 'Đang hoạt động' : 'Tạm ngưng'" :tone="$court->status === 'ACTIVE' ? 'success' : 'neutral'"/>
        @if(Auth::check() && $canBook)
            <form class="sz-court-favorite" method="POST" action="{{ route('favorites.store', $court) }}">@csrf<button type="submit" aria-label="Thêm {{ $court->name }} vào yêu thích"><i class="bi bi-heart" aria-hidden="true"></i></button></form>
        @elseif(!Auth::check())
            <div class="sz-court-favorite"><a href="{{ route('login') }}" aria-label="Đăng nhập để yêu thích sân"><i class="bi bi-heart" aria-hidden="true"></i></a></div>
        @endif
    </div>
    <div class="sz-court-content">
        <div class="sz-court-type"><span>{{ $court->courtType?->name ?: 'Sân cầu lông' }}</span><span class="sz-court-rating"><i class="bi bi-star-fill" aria-hidden="true"></i> {{ $rating ?: 'Chưa có đánh giá' }} @if($rating)<small>({{ $court->approved_reviews_count }})</small>@endif</span></div>
        <h3><a href="{{ route('courts.show', $court) }}">{{ $court->name }}</a></h3>
        <p class="sz-court-address"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $court->address ?: ($venueAddress ?: 'Địa chỉ đang cập nhật') }}</p>
        <div class="sz-court-amenities">@forelse($court->amenities->take(3) as $amenity)<span>{{ $amenity->name }}</span>@empty<span>Tiện ích đang cập nhật</span>@endforelse</div>
        <div class="sz-court-price">@if($minimumPrice !== null)<small>Từ</small> <strong>{{ number_format($minimumPrice, 0, ',', '.') }}đ</strong><small>/giờ</small>@else<small>Giá sân đang cập nhật</small>@endif</div>
        <div class="sz-court-actions"><a class="btn btn-outline-primary" href="{{ route('courts.show', $court) }}">Xem chi tiết</a><a class="btn btn-primary" href="{{ $canBook ? route('bookings.create', ['court_id' => $court->id]) : $primaryRoute }}">{{ $canBook ? 'Đặt ngay' : $primaryLabel }}</a></div>
    </div>
</article>

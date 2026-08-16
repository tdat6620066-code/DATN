@php
    $minimumPrice = $court->prices->min('price') ?? 0;
    $image = $court->images->first()?->image;
    $rating = $court->approved_rating ? number_format($court->approved_rating, 1) : null;
@endphp
<article class="pro-court-card">
    <div class="pro-court-image">
        @if($image)<img src="{{ $image }}" alt="{{ $court->name }}" loading="lazy">@else<div class="court-image-fallback"><i class="bi bi-trophy"></i></div>@endif
        @if(isset($rank))<span class="court-rank">#{{ $rank }}</span>@endif
        <span class="court-tag">{{ $court->booking_count > 0 ? 'TOP' : 'MỚI' }}</span>
    </div>
    <div class="pro-court-body">
        <div class="court-rating">@if($rating)<i class="bi bi-star-fill"></i> {{ $rating }} <small>({{ $court->approved_reviews_count }})</small>@else <i class="bi bi-stars"></i> Sân chất lượng @endif</div>
        <h3>{{ $court->name }}</h3>
        <p class="court-location"><i class="bi bi-geo-alt"></i>{{ $court->address ?: $court->courtType?->name }}</p>
        @if($court->amenities->isNotEmpty())<div class="court-amenities-mini">@foreach($court->amenities->take(3) as $amenity)<span>{{ $amenity->name }}</span>@endforeach</div>@endif
        <div class="court-card-bottom"><div><small>Giá từ</small><strong>{{ number_format($minimumPrice, 0, ',', '.') }}đ<span>/giờ</span></strong></div><button type="button" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">Xem sân <i class="bi bi-arrow-up-right"></i></button></div>
    </div>
</article>

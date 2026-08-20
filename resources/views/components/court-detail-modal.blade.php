@php
    $minimumPrice = $court->prices->min('price') ?? 0;
    $galleryImages = $court->images->take(5);
    $coverImage = $galleryImages->first()?->image;
    $rating = $court->approved_rating ? number_format($court->approved_rating, 1) : null;
    $courtType = $court->courtType?->name;
@endphp
<div class="modal fade court-detail-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header border-0 justify-content-center py-2"><span class="modal-handle"></span><button type="button" class="btn-close position-absolute end-0 me-4" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
        <div class="modal-body pt-2 pb-5"><div class="court-gallery">@forelse($galleryImages as $image)<img src="{{ $image->url }}" alt="{{ $court->name }}">@empty<img src="https://via.placeholder.com/800x500?text=No+Image" alt="{{ $court->name }}">@endforelse</div>
            <section class="court-detail-content"><h2 id="{{ $modalId }}Label">{{ $court->name }}</h2><p class="court-contact"><i class="bi bi-geo-alt"></i>{{ $court->address ?? 'Địa chỉ đang cập nhật' }}</p><p class="court-contact"><i class="bi bi-telephone"></i>{{ $court->phone ?? 'Đang cập nhật' }}</p>
            @if($court->opening_time && $court->closing_time)<p class="court-contact"><i class="bi bi-clock"></i>Thứ 2 - CN: {{ \Carbon\Carbon::parse($court->opening_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($court->closing_time)->format('H:i') }}</p>@endif
            <h3>Dịch vụ tiện ích</h3>@if($court->amenities->isNotEmpty())<div class="court-amenities">@foreach($court->amenities as $amenity)<span><i class="bi bi-check-circle-fill"></i>{{ $amenity->name }}</span>@endforeach</div>@endif
            <p class="court-description">{{ $court->description ?: 'Sân được đầu tư khang trang, sạch đẹp với trang thiết bị hiện đại, phù hợp cho luyện tập và thi đấu.' }}</p></section>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            <div class="modal-body">
                <div class="cdm-grid">
                    <div class="cdm-gallery">
                        <div class="cdm-main">
                            @if($coverImage)
                                <img src="{{ $coverImage }}" alt="{{ $court->name }}" loading="lazy">
                            @else
                                <div class="cdm-fallback"><i class="bi bi-trophy-fill"></i></div>
                            @endif
                            @if($courtType)
                                <span class="cdm-type">{{ $courtType }}</span>
                            @endif
                        </div>
                        @if($galleryImages->count() > 1)
                            <div class="cdm-thumbs">
                                @foreach($galleryImages as $image)
                                    <button type="button" class="cdm-thumb @if($loop->first) active @endif" onclick="switchCourtImage('{{ $modalId }}', '{{ $image->image }}', this)" aria-label="Xem ảnh {{ $loop->iteration }}">
                                        <img src="{{ $image->image }}" alt="{{ $court->name }} ảnh {{ $loop->iteration }}" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="cdm-info">
                        @if($courtType)
                            <span class="cdm-kicker">{{ $courtType }}</span>
                        @endif
                        <h2 id="{{ $modalId }}Label">{{ $court->name }}</h2>
                        <div class="cdm-rating">
                            @if($rating)
                                <span class="stars">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="bi bi-star{{ $i <= round($rating) ? '-fill' : '' }}"></i>
                                    @endfor
                                </span>
                                <span>{{ $rating }}</span>
                                <small>({{ $court->approved_reviews_count }} đánh giá)</small>
                            @else
                                <span class="stars"><i class="bi bi-stars"></i></span>
                                <small>Sân chất lượng</small>
                            @endif
                        </div>
                        <div class="cdm-contact">
                            <p><i class="bi bi-geo-alt-fill"></i><span><strong>Địa chỉ:</strong> {{ $court->address ?? 'Đang cập nhật' }}</span></p>
                            @if($court->phone)
                                <p><i class="bi bi-telephone-fill"></i><span><strong>Hotline:</strong> {{ $court->phone }}</span></p>
                            @endif
                            @if($court->opening_time && $court->closing_time)
                                <p><i class="bi bi-clock-fill"></i><span><strong>Giờ mở cửa:</strong> Thứ 2 - CN: {{ \Carbon\Carbon::parse($court->opening_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($court->closing_time)->format('H:i') }}</span></p>
                            @endif
                        </div>
                        @if($court->amenities->isNotEmpty())
                            <div class="cdm-amenities">
                                <h3>Tiện ích</h3>
                                <div>
                                    @foreach($court->amenities as $amenity)
                                        <span><i class="bi bi-check-circle-fill"></i>{{ $amenity->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="cdm-desc">
                            <h3>Giới thiệu</h3>
                            <p>{{ $court->description ?: 'Sân được đầu tư khang trang, sạch đẹp với trang thiết bị hiện đại, phù hợp cho luyện tập và thi đấu.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer cdm-footer">
                <div>
                    <small>GIÁ THUÊ TỪ</small>
                    <strong>{{ number_format($minimumPrice, 0, ',', '.') }}đ <em>/giờ</em></strong>
                </div>
                <a href="{{ route('courts.show', $court) }}" class="btn">Đặt sân ngay <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            window.switchCourtImage = function (modalId, src, el) {
                const modal = document.getElementById(modalId);
                if (!modal) return;
                const img = modal.querySelector('.cdm-main img');
                if (img) img.src = src;
                modal.querySelectorAll('.cdm-thumb').forEach((t) => t.classList.remove('active'));
                if (el) el.classList.add('active');
            };
        </script>
    @endpush
@endonce
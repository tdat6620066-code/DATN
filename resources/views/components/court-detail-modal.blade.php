@php
    $minimumPrice = $court->prices->min('price') ?? 0;
    $galleryImages = $court->images->take(5);
@endphp
<div class="modal fade court-detail-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header border-0 justify-content-center py-2"><span class="modal-handle"></span><button type="button" class="btn-close position-absolute end-0 me-4" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
        <div class="modal-body pt-2 pb-5"><div class="court-gallery">@forelse($galleryImages as $image)<img src="{{ $image->image }}" alt="{{ $court->name }}">@empty<img src="https://via.placeholder.com/800x500?text=No+Image" alt="{{ $court->name }}">@endforelse</div>
            <section class="court-detail-content"><h2 id="{{ $modalId }}Label">{{ $court->name }}</h2><p class="court-contact"><i class="bi bi-geo-alt"></i>{{ $court->address ?? 'Địa chỉ đang cập nhật' }}</p><p class="court-contact"><i class="bi bi-telephone"></i>{{ $court->phone ?? 'Đang cập nhật' }}</p>
            @if($court->opening_time && $court->closing_time)<p class="court-contact"><i class="bi bi-clock"></i>Thứ 2 - CN: {{ \Carbon\Carbon::parse($court->opening_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($court->closing_time)->format('H:i') }}</p>@endif
            <h3>Dịch vụ tiện ích</h3>@if($court->amenities->isNotEmpty())<div class="court-amenities">@foreach($court->amenities as $amenity)<span><i class="bi bi-check-circle-fill"></i>{{ $amenity->name }}</span>@endforeach</div>@endif
            <p class="court-description">{{ $court->description ?: 'Sân được đầu tư khang trang, sạch đẹp với trang thiết bị hiện đại, phù hợp cho luyện tập và thi đấu.' }}</p></section>
        </div>
        <div class="modal-footer court-booking-footer"><div><small>GIÁ TỪ</small><strong>{{ number_format($minimumPrice, 0, ',', '.') }}đ <em>/giờ</em></strong></div><a href="{{ route('courts.show', $court) }}" class="btn">Đặt ngay</a></div>
    </div></div>
</div>

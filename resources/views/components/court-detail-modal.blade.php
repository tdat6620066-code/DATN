@php
    $minimumPrice = $court->prices->min('price') ?? 0;
    $galleryImages = $court->images->take(5);
    $coverImage = $galleryImages->first()?->image;
    $rating = $court->approved_rating ? number_format($court->approved_rating, 1) : null;
    $courtType = $court->courtType?->name;
@endphp
<div class="modal fade court-detail-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
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
                <a href="{{ route('courts.show', $court) }}" class="btn" data-court-booking-picker data-court-id="{{ $court->id }}">Đặt sân ngay <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="cdm-booking-picker" hidden>
                <button class="cdm-booking-back" type="button" aria-label="Quay lại"><i class="bi bi-chevron-left"></i></button>
                <div class="cdm-booking-card">
                    <h2>Chọn hình thức đặt sân</h2>
                    <label class="cdm-booking-option"><input type="radio" name="booking_type_{{ $modalId }}" value="daily" checked> Đặt theo ngày</label>
                    <label class="cdm-booking-option"><input type="radio" name="booking_type_{{ $modalId }}" value="weekly"> Đặt theo tuần</label>
                    <label class="cdm-booking-option"><input type="radio" name="booking_type_{{ $modalId }}" value="monthly"> Đặt theo tháng</label>
                    <button class="cdm-booking-continue" type="button">TIẾP TỤC</button>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    <style>
        .cdm-booking-picker { min-height: 500px; padding: 42px; background: linear-gradient(135deg, #006b3c, #05a761); color: #fff; }
        .cdm-booking-back { border: 0; background: transparent; color: #fff; font-size: 28px; cursor: pointer; }
        .cdm-booking-card { width: min(510px, 100%); margin: 105px auto 0; padding: 28px; border-radius: 14px; background: #fff; color: #12382a; }
        .cdm-booking-card h2 { margin: 0 0 20px; text-align: center; font-size: 23px; }
        .cdm-booking-option { display: flex; align-items: center; gap: 13px; margin: 14px 0; padding: 13px; border: 1px solid #dce8e2; border-radius: 10px; cursor: pointer; font-weight: 700; }
        .cdm-booking-option:has(input:checked) { border-color: #05d381; background: #edfff5; }
        .cdm-booking-option input { width: 20px; height: 20px; accent-color: #009e5b; }
        .cdm-booking-continue { width: 100%; margin-top: 18px; padding: 13px; border: 0; border-radius: 12px; background: #007b43; color: #fff; font: inherit; font-weight: 800; cursor: pointer; }
        @media (max-width: 767px) { .cdm-booking-picker { min-height: calc(100vh - 24px); padding: 22px; } .cdm-booking-card { margin-top: 80px; padding: 22px; } }
    </style>
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

            document.querySelectorAll('[data-court-booking-picker]').forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    const modal = trigger.closest('.court-detail-modal');
                    if (!modal) return;
                    modal.querySelector('.modal-body').hidden = true;
                    modal.querySelector('.cdm-footer').hidden = true;
                    modal.querySelector('.cdm-booking-picker').hidden = false;
                });
            });

            document.querySelectorAll('.court-detail-modal').forEach((modal) => {
                const resetBookingPicker = () => {
                    modal.querySelector('.modal-body').hidden = false;
                    modal.querySelector('.cdm-footer').hidden = false;
                    modal.querySelector('.cdm-booking-picker').hidden = true;
                };

                modal.querySelector('.cdm-booking-back').addEventListener('click', resetBookingPicker);
                modal.addEventListener('hidden.bs.modal', resetBookingPicker);
                modal.querySelector('.cdm-booking-continue').addEventListener('click', () => {
                    const type = modal.querySelector('.cdm-booking-option input:checked').value;
                    const courtId = modal.querySelector('[data-court-booking-picker]').dataset.courtId;
                    const bookingUrl = modal.querySelector('[data-court-booking-picker]').href;

                    if (type === 'daily') return window.location.assign(bookingUrl);

                    const url = new URL('{{ route('bookings.create-recurring') }}', window.location.origin);
                    url.searchParams.set('court_id', courtId);
                    url.searchParams.set('booking_type', type);
                    window.location.assign(url.toString());
                });
            });
        </script>
    @endpush
@endonce

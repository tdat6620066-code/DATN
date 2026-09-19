@extends('layouts.app')
@section('title', 'Tìm sân phù hợp với bạn — SmashZone')
@section('body_class', 'sz-courts-page')
@push('styles') @vite(['resources/css/courts.css', 'resources/js/courts.js']) @endpush
@section('content')
<x-booking-progress :step="1"/>
<header class="court-page-heading"><nav aria-label="Đường dẫn"><a href="{{ route('home') }}">Trang chủ</a><span aria-hidden="true">/</span><span>Sân cầu lông</span></nav><span class="court-eyebrow">SMASHZONE · KHÁM PHÁ SÂN</span><h1>Tìm sân phù hợp với bạn</h1><p>Chọn sân, xem khung giờ và lên lịch cho trận đấu tiếp theo.</p></header>
<div class="court-list-layout">
    <aside class="court-filter card"><div class="card-body"><h2 class="d-none d-lg-block"><i class="bi bi-sliders" aria-hidden="true"></i> Bộ lọc tìm sân</h2>
        <button class="btn btn-outline-primary d-lg-none w-100" type="button" data-bs-toggle="collapse" data-bs-target="#customerCourtFilters" aria-expanded="false" aria-controls="customerCourtFilters"><i class="bi bi-sliders me-2" aria-hidden="true"></i>Tùy chỉnh bộ lọc</button>
        <div id="customerCourtFilters" class="collapse d-lg-block pt-3">
        <form action="{{ route('courts.index') }}" method="GET" data-court-filter>
            <div class="mb-3"><label class="form-label" for="keyword">Tìm kiếm</label><input class="form-control" id="keyword" name="keyword" value="{{ request('keyword') }}" placeholder="Tên sân, mã hoặc loại sân" maxlength="255"></div>
            <div class="mb-3"><label class="form-label" for="booking_date">Ngày chơi</label><input class="form-control" id="booking_date" name="booking_date" type="date" value="{{ request('booking_date') }}" min="{{ today()->toDateString() }}" max="{{ today()->addDays(config('booking.max_days', 30))->toDateString() }}"></div>
            <div class="mb-3"><label class="form-label" for="time_slot_id">Khung giờ</label><select class="form-select" id="time_slot_id" name="time_slot_id"><option value="">Tất cả khung giờ</option>@foreach($timeSlots as $slot)<option value="{{ $slot->id }}" @selected(request('time_slot_id') == $slot->id)>{{ substr($slot->start_time, 0, 5) }} – {{ substr($slot->end_time, 0, 5) }}</option>@endforeach</select></div>
            <fieldset class="mb-3"><legend class="form-label">Khoảng giá / giờ</legend><div class="court-price-inputs"><input class="form-control" aria-label="Giá thấp nhất" name="price_min" type="number" min="0" value="{{ request('price_min') }}" placeholder="Từ"><input class="form-control" aria-label="Giá cao nhất" name="price_max" type="number" min="0" value="{{ request('price_max') }}" placeholder="Đến"></div></fieldset>
            <div class="mb-3"><label class="form-label" for="court_type_id">Loại sân</label><select class="form-select" id="court_type_id" name="court_type_id"><option value="">Tất cả loại sân</option>@foreach($courtTypes as $type)<option value="{{ $type->id }}" @selected(request('court_type_id') == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
            <div class="mb-3"><label class="form-label" for="availability_status">Trạng thái</label><select class="form-select" id="availability_status" name="availability_status" aria-describedby="status-filter-help"><option value="">Tất cả trạng thái</option>@foreach(['AVAILABLE' => 'Còn sân', 'BOOKED' => 'Đã đặt', 'HOLD' => 'Đang giữ', 'MAINTENANCE' => 'Không khả dụng'] as $value => $label)<option value="{{ $value }}" @selected(request('availability_status') === $value)>{{ $label }}</option>@endforeach</select><p id="status-filter-help" class="form-text">Chọn ngày và khung giờ để lọc trạng thái chính xác.</p></div>
            <div class="mb-3"><label class="form-label" for="sort_by">Sắp xếp</label><select class="form-select" id="sort_by" name="sort_by">@foreach(['name_asc' => 'Tên A–Z', 'name_desc' => 'Tên Z–A', 'price_asc' => 'Giá tăng dần', 'price_desc' => 'Giá giảm dần', 'most_booked' => 'Được đặt nhiều'] as $value => $label)<option value="{{ $value }}" @selected(request('sort_by', 'name_asc') === $value)>{{ $label }}</option>@endforeach</select></div>
            @foreach((array) request('amenity_ids', []) as $amenityId)<input type="hidden" name="amenity_ids[]" value="{{ $amenityId }}">@endforeach
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Tìm sân</button><a class="btn btn-link w-100 mt-2" href="{{ route('courts.index') }}">Xóa bộ lọc</a>
        </form></div>
    </div></aside>
    <section id="court-list" aria-labelledby="results-title"><div class="court-results-heading"><h2 id="results-title">{{ $courts->total() }} sân phù hợp</h2><span>{{ $courts->firstItem() ?? 0 }}–{{ $courts->lastItem() ?? 0 }} / {{ $courts->total() }}</span></div>
        <div data-list-loading hidden><x-loading-state label="Đang tìm sân phù hợp…"/></div>
        <div class="court-results-grid">
        @forelse($courts as $court)
            @php
                $pricingDate = \Carbon\Carbon::parse(request('booking_date') ?: today())->startOfDay();
                $price = $court->prices->where('status', 'ACTIVE')->filter(fn ($price) => (! $price->effective_from || $price->effective_from->lte($pricingDate)) && (! $price->effective_to || $price->effective_to->gte($pricingDate)) && (! request('time_slot_id') || $price->time_slot_id == request('time_slot_id')))->min('price');
                $status = $courtAvailability[$court->id] ?? null;
                [$label, $tone] = match ($status) { 'AVAILABLE' => ['Còn sân', 'success'], 'BOOKED' => ['Đã đặt', 'danger'], 'HOLD' => ['Đang giữ', 'warning'], 'MAINTENANCE' => ['Không khả dụng', 'neutral'], default => ['Chọn giờ để xem lịch', 'neutral'] };
                $detailUrl = route('courts.show', ['court' => $court, 'booking_date' => request('booking_date'), 'time_slot_id' => request('time_slot_id')]);
            @endphp
            <article class="court-result card"><a class="court-result-image" href="{{ $detailUrl }}">@if($court->images->first())<img src="{{ $court->images->first()->url }}" alt="{{ $court->name }}" width="640" height="400" loading="lazy">@else<i class="bi bi-image" aria-hidden="true"></i><span class="visually-hidden">{{ $court->name }}</span>@endif</a><div class="card-body"><span class="court-type">{{ $court->courtType?->name }}</span><h3><a href="{{ $detailUrl }}">{{ $court->name }}</a></h3><div class="court-rating"><i class="bi bi-star-fill" aria-hidden="true"></i> {{ $court->approved_rating ? number_format($court->approved_rating, 1).' / 5' : 'Chưa có đánh giá' }} <span>({{ $court->approved_reviews_count }})</span></div><p class="court-address"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $court->address ?: 'Địa chỉ đang cập nhật' }}</p><div class="court-amenities">@forelse($court->amenities->take(3) as $amenity)<span>{{ $amenity->name }}</span>@empty<span>Tiện ích đang cập nhật</span>@endforelse</div><div class="court-result-price"><div><small>Giá từ</small>@if($price !== null)<strong>{{ number_format($price, 0, ',', '.') }}đ <small>/ giờ</small></strong>@else<strong>Đang cập nhật</strong>@endif</div><x-status-badge :label="$label" :tone="$tone"/></div><div class="court-result-actions"><a class="btn btn-outline-primary" href="{{ $detailUrl }}">Xem chi tiết</a><a class="btn btn-primary" href="{{ $detailUrl }}#booking-card">Đặt ngay</a></div></div></article>
        @empty
            <div class="court-list-empty"><x-empty-state icon="bi-search" title="Chưa tìm thấy sân phù hợp" description="Thử đổi ngày, khung giờ hoặc mở rộng bộ lọc."><a class="btn btn-outline-primary" href="{{ route('courts.index') }}">Xem tất cả sân</a></x-empty-state></div>
        @endforelse
        </div><div class="mt-4">{{ $courts->links() }}</div>
    </section>
</div>
@endsection

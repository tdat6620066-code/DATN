@props(['label' => 'Đang tải nội dung…'])
<div {{ $attributes->class(['sz-skeleton', 'card', 'placeholder-glow']) }} role="status" aria-busy="true">
    <span class="visually-hidden">{{ $label }}</span>
    <div class="sz-skeleton-image" aria-hidden="true"></div>
    <div class="card-body" aria-hidden="true"><span class="placeholder col-8 mb-3"></span><span class="placeholder col-12 mb-2"></span><span class="placeholder col-5"></span></div>
</div>

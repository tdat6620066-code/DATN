@props(['label' => 'Đang tải dữ liệu…'])
<div {{ $attributes->class(['sz-loading']) }} role="status" aria-live="polite">
    <span class="spinner-border" aria-hidden="true"></span><span>{{ $label }}</span>
</div>

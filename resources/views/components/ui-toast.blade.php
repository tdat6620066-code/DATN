@props(['title' => 'Thông báo'])
<div {{ $attributes->class(['toast']) }} role="status" aria-live="polite" aria-atomic="true">
    <div class="toast-header"><strong class="me-auto">{{ $title }}</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Đóng thông báo"></button></div>
    <div class="toast-body">{{ $slot }}</div>
</div>

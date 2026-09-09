@include('partials.detail-styles')
<section class="sz-timeline" aria-label="Tiến trình xử lý yêu cầu">
    <div class="sz-section-heading"><h2>Tiến trình xử lý</h2><span>{{ count($timeline) }} mốc</span></div>
    <ol class="sz-events">
    @forelse($timeline as $event)
        <li class="sz-event {{ $event['complete'] ? 'is-complete' : '' }}">
            <span class="sz-event-dot" aria-hidden="true">{{ $event['complete'] ? '✓' : '' }}</span>
            <time datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->format('d/m/Y · H:i') }}</time>
            <strong>{{ $event['label'] }}</strong>
            @if($event['note'])
            <details class="sz-event-note"><summary>Xem ghi chú</summary><p>{{ $event['note'] }}</p></details>
            @endif
        </li>
    @empty
        <li class="text-muted small">Chưa có hoạt động.</li>
    @endforelse
    </ol>
</section>

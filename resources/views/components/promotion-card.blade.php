<article class="promotion-card">
    <div class="promotion-visual" @if($promotion->image) style="background-image:url('{{ $promotion->image }}')" @endif><span>ƯU ĐÃI</span></div>
    <div><small>Đến {{ optional($promotion->end_at)->format('d/m/Y') ?? 'khi có thông báo' }}</small><h3>{{ $promotion->title }}</h3><p>{{ Str::limit($promotion->description, 105) }}</p><a href="{{ route('courts.index') }}">Xem ưu đãi <i class="bi bi-arrow-right"></i></a></div>
</article>

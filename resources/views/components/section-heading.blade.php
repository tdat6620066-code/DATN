<div class="home-section-heading {{ $align ?? '' }}">
    @isset($eyebrow)<span>{{ $eyebrow }}</span>@endisset
    <h2>{{ $title }}</h2>
    @isset($description)<p>{{ $description }}</p>@endisset
</div>

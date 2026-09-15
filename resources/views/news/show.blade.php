@extends('layouts.app')

@section('title', $news->title.' - SmashZone')

@push('styles')
<style>
    .article{max-width:840px;margin:34px auto 0}.article__back{color:#087c42;font-size:13px;font-weight:800;text-decoration:none}.article__date{display:block;margin-top:28px;color:#71838b;font-size:13px;font-weight:700}.article h1{margin:10px 0 24px;color:#102a34;font-size:clamp(28px,4vw,42px);line-height:1.25;font-weight:800}.article__image{width:100%;max-height:440px;object-fit:cover;border-radius:20px}.article__fallback{display:grid;min-height:250px;place-items:center;border-radius:20px;background:#e7f7eb;color:#087c42;font-size:56px}.article__body{margin-top:28px;color:#314b54;font-size:16px;line-height:1.85}.article__related{max-width:1180px;margin:55px auto 0}.article__related h2{font-size:24px;font-weight:800}.article__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.article__grid .news-card{padding:20px;border:1px solid #dbe8df;border-radius:16px;background:#fff}.article__grid .news-card h3{font-size:16px;font-weight:800}.article__grid .news-card a{color:#087c42;text-decoration:none;font-size:13px;font-weight:800}@media(max-width:700px){.article{margin-top:18px}.article__grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<article class="article">
    <a class="article__back" href="{{ route('news.index') }}"><i class="bi bi-arrow-left"></i> Tất cả tin tức</a>
    <time class="article__date" datetime="{{ $news->published_at->toDateString() }}">{{ $news->published_at->format('d/m/Y') }}</time>
    <h1>{{ $news->title }}</h1>
    @if($news->thumbnail)<img class="article__image" src="{{ $news->thumbnail }}" alt="{{ $news->title }}">@else<div class="article__fallback"><i class="bi bi-newspaper"></i></div>@endif
    <div class="article__body">{!! nl2br(e($news->content)) !!}</div>
</article>
@if($relatedNews->isNotEmpty())
<section class="article__related"><h2>Bài viết liên quan</h2><div class="article__grid">@foreach($relatedNews as $item)<x-news-card :news="$item" />@endforeach</div></section>
@endif
@endsection

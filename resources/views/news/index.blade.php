@extends('layouts.app')

@section('title', 'Tin tức - SmashZone')

@push('styles')
<style>
    .news-page { max-width:1180px; margin:34px auto 0; }.news-page__hero { padding:42px; border-radius:24px; color:#fff; background:linear-gradient(120deg,#082536,#166c51); }.news-page__hero span{font-size:12px;font-weight:800;letter-spacing:.08em}.news-page__hero h1{margin:12px 0 8px;font-size:clamp(28px,4vw,43px);font-weight:800}.news-page__hero p{margin:0;color:rgba(255,255,255,.85)}.news-page__heading{margin:36px 0 18px}.news-page__heading h2{margin:0;font-size:25px;font-weight:800}.news-page__heading p{margin:6px 0 0;color:#58708b}.news-list{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.news-card{display:flex;flex-direction:column;overflow:hidden;border:1px solid #dbe8df;border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(8,35,37,.06)}.news-image{height:190px;display:grid;place-items:center;background:#e7f7eb;color:#087c42;font-size:38px}.news-image img{width:100%;height:100%;object-fit:cover}.news-card>div:last-child{display:flex;flex:1;flex-direction:column;padding:19px}.news-card small{color:#71838b;font-size:12px;font-weight:700}.news-card h3{margin:9px 0;font-size:17px;line-height:1.45;font-weight:800}.news-card p{margin:0;color:#60727a;font-size:13px;line-height:1.6}.news-card a{margin-top:auto;padding-top:16px;color:#087c42;font-size:13px;font-weight:800;text-decoration:none}.news-empty{padding:45px;border:1px dashed #b7c9bd;border-radius:18px;background:#fff;text-align:center;color:#58708b}.news-empty i{font-size:32px;color:#08a65a}@media(max-width:900px){.news-list{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.news-page{margin-top:16px}.news-page__hero{padding:28px 22px}.news-list{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<section class="news-page">
    <div class="news-page__hero"><span>SMASHZONE BLOG</span><h1>Tin tức & cẩm nang cầu lông</h1><p>Cập nhật hoạt động, ưu đãi và những kiến thức hữu ích dành cho người chơi.</p></div>
    <div class="news-page__heading"><h2>Bài viết mới nhất</h2><p>{{ $news->total() }} bài viết đã được đăng.</p></div>
    @forelse($news as $item)
        @if($loop->first)<div class="news-list">@endif
        <x-news-card :news="$item" />
        @if($loop->last)</div>@endif
    @empty
        <div class="news-empty"><i class="bi bi-newspaper"></i><h2 class="mt-2 fs-5 fw-bold text-dark">Chưa có bài viết</h2><p class="mb-0">Các tin tức mới sẽ được cập nhật sớm.</p></div>
    @endforelse
    <div class="mt-4">{{ $news->links() }}</div>
</section>
@endsection

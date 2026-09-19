@extends('layouts.app')

@section('title', 'Khuyến mãi - SmashZone')

@push('styles')
<style>
    .promotion-page { max-width: 1180px; margin: 34px auto 0; }.promotion-hero { padding:42px; border-radius:24px; color:#fff; background:linear-gradient(120deg,#063d43,#06834b); box-shadow:0 18px 38px rgba(6,61,67,.18); }.promotion-hero span { display:inline-block; padding:6px 10px; border-radius:999px; background:rgba(255,255,255,.16); font-size:12px; font-weight:800; letter-spacing:.08em; }.promotion-hero h1 { margin:14px 0 8px; font-size:clamp(28px,4vw,43px); font-weight:800; }.promotion-hero p { max-width:600px; margin:0; color:rgba(255,255,255,.85); }.promotion-list-head { display:flex; justify-content:space-between; align-items:end; gap:20px; margin:36px 0 18px; }.promotion-list-head h2 { margin:0; font-size:25px; font-weight:800; }.promotion-list-head p { margin:6px 0 0; color:#58708b; }
    .voucher-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }.voucher-card { display:flex; flex-direction:column; min-height:325px; padding:22px; border:1px solid #dbe8df; border-radius:18px; background:#fff; box-shadow:0 8px 24px rgba(8,35,37,.06); }.voucher-card__top { display:flex; justify-content:space-between; align-items:center; gap:10px; color:#087c42; }.voucher-card__top strong { font-size:20px; color:#063d43; }.voucher-card__badge { font-size:10px; font-weight:800; color:#087c42; }.voucher-card h2 { margin:18px 0 14px; font-size:18px; font-weight:800; color:#102a34; }.voucher-card__code { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:9px 10px; border:1px dashed #a5cdb3; border-radius:10px; background:#f1fbf4; }.voucher-card code { font-size:15px; font-weight:800; letter-spacing:.06em; color:#087c42; }.copy-voucher { border:0; background:transparent; color:#087c42; font-size:12px; font-weight:700; white-space:nowrap; }.voucher-card dl { margin:16px 0 8px; }.voucher-card dl div { display:flex; justify-content:space-between; gap:12px; margin:7px 0; font-size:12px; }.voucher-card dt { color:#72838b; font-weight:500; }.voucher-card dd { margin:0; color:#203b43; font-weight:700; text-align:right; }.voucher-card__conditions { margin:7px 0; color:#60727a; font-size:12px; line-height:1.5; }.voucher-card__action { margin-top:auto; padding-top:14px; color:#087c42; font-size:13px; font-weight:800; text-decoration:none; }.promotion-empty { padding:45px; border:1px dashed #b7c9bd; border-radius:18px; background:#fff; text-align:center; color:#58708b; }.promotion-empty i { font-size:32px; color:#08a65a; }.promotion-empty h2 { margin:10px 0 6px; color:#102a34; font-size:20px; font-weight:800; }
    @media(max-width:900px){.voucher-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.promotion-page{margin-top:16px}.promotion-hero{padding:28px 22px}.voucher-grid{grid-template-columns:1fr}.promotion-list-head{align-items:start;flex-direction:column}}
</style>
@endpush

@section('content')
<section class="promotion-page">
    <div class="promotion-hero"><span>ƯU ĐÃI SMASHZONE</span><h1>Khuyến mãi đang áp dụng</h1><p>Chọn mã phù hợp, sao chép và nhập tại bước đặt sân để nhận ưu đãi.</p></div>
    <div class="promotion-list-head"><div><h2>Mã giảm giá dành cho bạn</h2><p>{{ $vouchers->total() }} mã hiện còn hiệu lực.</p></div></div>
    @forelse($vouchers as $voucher)
        @if($loop->first)<div class="voucher-grid">@endif
        <x-voucher-card :voucher="$voucher" />
        @if($loop->last)</div>@endif
    @empty
        <div class="promotion-empty"><i class="bi bi-gift"></i><h2>Chưa có ưu đãi phù hợp</h2><p>Hãy quay lại sau để không bỏ lỡ các mã giảm giá mới.</p></div>
    @endforelse
    <div class="mt-4">{{ $vouchers->links() }}</div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.copy-voucher').forEach(button => button.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(button.dataset.code); button.innerHTML = '<i class="bi bi-check2"></i> Đã chép'; setTimeout(() => button.innerHTML = '<i class="bi bi-copy"></i> Sao chép', 1800); } catch (_) { window.prompt('Sao chép mã giảm giá:', button.dataset.code); }
}));
</script>
@endpush

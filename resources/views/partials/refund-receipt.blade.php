@if($receipt?->receipt_path)
<figure class="my-3">
    <figcaption class="small fw-semibold mb-2">Ảnh xác nhận chi trả thành công</figcaption>
    <a href="{{ route('refund-payouts.receipt', $receipt) }}" target="_blank" rel="noopener" title="Xem ảnh đầy đủ">
        <img src="{{ route('refund-payouts.receipt', $receipt) }}" alt="Biên nhận hoàn tiền #{{ $receipt->id }}" loading="lazy" class="img-fluid rounded border" style="max-height:280px;object-fit:contain">
    </a>
</figure>
@endif

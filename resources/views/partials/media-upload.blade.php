<div class="mb-3" data-media-upload>
    <label class="d-block"><span class="form-label d-block">{{ $label ?? 'Ảnh/video minh chứng' }}</span>
        <input type="file" name="{{ $name ?? 'evidences[]' }}" class="form-control" accept="{{ $accept ?? 'image/jpeg,image/png,image/webp,video/mp4,video/webm' }}" @if($multiple ?? true) multiple @endif @if($required ?? false) required @endif>
    </label>
    <small class="text-muted">{{ $hint ?? 'Tối đa 5 tệp, 20MB/tệp. Ảnh và video xem trực tiếp sau khi gửi.' }}</small>
    <div class="d-flex flex-wrap gap-2 mt-2" data-media-preview aria-live="polite"></div>
</div>
@once
<script>
document.addEventListener('change', function (event) {
    const input = event.target;
    const container = input.closest('[data-media-upload]');
    if (!container || input.type !== 'file') return;
    const preview = container.querySelector('[data-media-preview]');
    preview.querySelectorAll('[data-object-url]').forEach(node => URL.revokeObjectURL(node.dataset.objectUrl));
    preview.replaceChildren();
    Array.from(input.files).forEach(file => {
        const card = document.createElement('figure');
        card.className = 'm-0';
        const caption = document.createElement('figcaption');
        caption.className = 'small text-muted text-break';
        caption.textContent = file.name;
        if (file.type.startsWith('image/') || file.type.startsWith('video/')) {
            const media = document.createElement(file.type.startsWith('image/') ? 'img' : 'video');
            media.src = URL.createObjectURL(file);
            media.dataset.objectUrl = media.src;
            media.style.cssText = 'width:140px;height:110px;object-fit:contain;border-radius:8px;background:#f1f5f9';
            if (media.tagName === 'VIDEO') { media.controls = true; media.preload = 'metadata'; }
            else media.alt = file.name;
            card.append(media);
        }
        card.style.width = '140px';
        card.append(caption);
        preview.append(card);
    });
});
</script>
@endonce

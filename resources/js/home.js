// Keep CMS banners when available; use the existing local asset if one fails.
document.querySelectorAll('img[data-fallback-src]').forEach(image => {
    const useFallback = () => {
        const fallback = image.dataset.fallbackSrc;
        if (!fallback) return;
        delete image.dataset.fallbackSrc;
        image.src = fallback;
    };
    image.addEventListener('error', useFallback, { once: true });
    if (image.complete && !image.naturalWidth) useFallback();
});

// Close mobile navigation after choosing a section on the current page.
document.querySelectorAll('#customerNavigation a').forEach(link => {
    link.addEventListener('click', () => {
        const navigation = document.getElementById('customerNavigation');
        if (window.innerWidth < 1200 && navigation?.classList.contains('show')) {
            window.bootstrap?.Collapse.getOrCreateInstance(navigation, { toggle: false }).hide();
        }
    });
});

// Native scroll keeps reviews readable when JavaScript or motion is disabled.
const reviews = document.querySelector('#reviews .sz-content-grid');
if (reviews && reviews.children.length > 1) {
    const controls = document.createElement('div');
    controls.className = 'd-flex gap-2 mt-3';
    [[-1, 'Đánh giá trước'], [1, 'Đánh giá tiếp theo']].forEach(([direction, label]) => {
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'btn btn-outline-primary';
        button.textContent = direction < 0 ? '←' : '→'; button.setAttribute('aria-label', label);
        button.addEventListener('click', () => reviews.scrollBy({left: direction * reviews.clientWidth * .75, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'}));
        controls.append(button);
    });
    reviews.after(controls);
}

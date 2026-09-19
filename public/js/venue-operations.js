document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#venue-extension, #venue-checkout').forEach(modal => document.body.appendChild(modal));
    document.querySelectorAll('[data-venue-submit]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button:not([type="button"])').forEach(button => {
                button.disabled = true;
                button.textContent = 'Đang xử lý…';
            });
        });
    });
    const operation = new URLSearchParams(location.search).get('operation');
    if (['checkout', 'extension'].includes(operation)) {
        const modal = document.getElementById(`venue-${operation}`);
        if (modal && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });

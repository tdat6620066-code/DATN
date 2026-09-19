document.querySelectorAll('[data-dialog]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.dialog)?.showModal()));
document.querySelectorAll('.sc-dialog').forEach(dialog => {
    dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); } });
});
const updateScheduleHolds = () => document.querySelectorAll('[data-hold-until]').forEach(node => {
    const remaining = Math.max(0, Math.ceil((Date.parse(node.dataset.holdUntil) - Date.now()) / 1000));
    node.textContent = remaining ? 'Còn ' + String(Math.floor(remaining / 60)).padStart(2, '0') + ':' + String(remaining % 60).padStart(2, '0') : 'Đã hết hạn giữ chỗ · tải lại lịch';
});
updateScheduleHolds(); setInterval(updateScheduleHolds, 1000);
document.querySelectorAll('[data-operation-form]').forEach(form => form.addEventListener('submit', event => {
    if (form.dataset.busy === 'true') { event.preventDefault(); return; }
    if (form.dataset.confirmOperation && !window.confirm(form.dataset.confirmOperation)) { event.preventDefault(); return; }
    form.dataset.busy = 'true'; form.setAttribute('aria-busy', 'true');
    const button = form.querySelector('button'); button.disabled = true; button.textContent = 'Đang xử lý…';
}));
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });

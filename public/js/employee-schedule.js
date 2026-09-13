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

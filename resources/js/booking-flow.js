// Presentation only: all payable amounts and discounts come from Laravel.
const checkout = document.querySelector('[data-checkout-form]');
if (checkout) {
    const button = document.querySelector('[data-payment-submit]');
    const confirmation = checkout.querySelector('[data-confirm-booking]');
    const countdown = document.querySelector('[data-hold-deadline]');
    const status = document.getElementById('checkout-feedback');
    const started = performance.now();
    const serverNow = Number(countdown?.dataset.serverNow);
    let expired = false;
    let busy = false;
    const sync = () => { button.disabled = expired || busy || !confirmation.checked; };
    confirmation.addEventListener('change', sync);
    const tick = () => {
        if (!countdown) return;
        const remaining = Math.max(0, Math.ceil((Number(countdown.dataset.holdDeadline) - serverNow - (performance.now() - started)) / 1000));
        countdown.textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
        expired = remaining === 0;
        if (expired) {
            status.hidden = false;
            status.textContent = 'Thời gian giữ chỗ đã hết. Vui lòng tải lại trang để kiểm tra trạng thái và chọn lịch mới.';
        }
        sync();
    };
    checkout.addEventListener('submit', event => {
        tick();
        if (expired || busy || !checkout.checkValidity()) {
            event.preventDefault(); checkout.classList.add('was-validated'); checkout.reportValidity(); return;
        }
        busy = true; sync();
        checkout.setAttribute('aria-busy', 'true');
        button.querySelector('[data-submit-label]').textContent = 'Đang chuyển đến thanh toán…';
        button.querySelector('.spinner-border').hidden = false;
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    tick(); sync();
    if (countdown) setInterval(tick, 1000);
}

document.querySelectorAll('[data-booking-services]').forEach(picker => picker.addEventListener('toggle', () => {
    const progress = document.querySelector('.sz-booking-progress');
    if (!progress) return;
    const step = picker.open ? 3 : 2;
    progress.querySelectorAll('li').forEach((item, index) => {
        item.classList.toggle('is-current', index + 1 === step);
        item.classList.toggle('is-complete', index + 1 < step);
        if (index + 1 === step) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
    });
}));

document.querySelectorAll('.time-slot-cell[role="button"], .calendar-day[role="button"]').forEach(cell => cell.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); cell.click(); }
}));
document.getElementById('changeDateBtn')?.addEventListener('click', () => {
    document.querySelector('.calendar-day.selected')?.focus();
    document.getElementById('datePickerContainer')?.scrollIntoView({behavior: 'smooth', block: 'center'});
});

const bookingForm = document.getElementById('bookingForm');
if (bookingForm) {
    let submitting = false;
    bookingForm.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        // Let existing slot/date validation run before showing loading feedback.
        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            submitting = true;
            bookingForm.setAttribute('aria-busy', 'true');
            document.querySelectorAll('button[type="submit"]').forEach(button => {
                if (button.form !== bookingForm) return;
                button.disabled = true;
                button.textContent = 'Đang kiểm tra và giữ chỗ…';
            });
        });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
}

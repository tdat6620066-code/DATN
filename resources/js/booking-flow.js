// Presentation only: all payable amounts and discounts come from Laravel.
const checkout = document.querySelector('[data-checkout-form]');
if (checkout) {
    const button = document.querySelector('[data-payment-submit]');
    const countdown = document.querySelector('[data-hold-deadline]');
    const status = document.getElementById('checkout-feedback');
    const started = performance.now();
    const serverNow = Number(countdown?.dataset.serverNow);
    let expired = false;
    let busy = false;
    let refreshing = false;
    let finished = false;
    const refreshStatus = async () => {
        if (refreshing || finished || busy || document.hidden) return;
        refreshing = true;
        try {
            const response = await fetch(window.location.href, { cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const main = page.getElementById('sz-main');
            if (main && !main.querySelector('[data-checkout-form]') && main.querySelector('.booking-hero')) {
                finished = true;
                document.getElementById('sz-main').replaceChildren(...main.childNodes);
            }
        } catch { /* Retry on the next status check if the connection is interrupted. */ }
        finally { refreshing = false; }
    };
    const sync = () => { button.disabled = expired || busy; };
    const tick = () => {
        if (!countdown || finished) return;
        const remaining = Math.max(0, Math.ceil((Number(countdown.dataset.holdDeadline) - serverNow - (performance.now() - started)) / 1000));
        countdown.textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
        expired = remaining === 0;
        if (expired) {
            status.hidden = false;
            status.textContent = 'Đã hết thời gian giữ chỗ. Đang cập nhật trạng thái đơn đặt sân…';
            refreshStatus();
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
    setInterval(refreshStatus, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { tick(); refreshStatus(); } });
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

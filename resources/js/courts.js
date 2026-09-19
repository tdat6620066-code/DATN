import '../../public/js/slot-sequence.js';

const money = value => `${Number(value).toLocaleString('vi-VN')}đ`;
const filterForm = document.querySelector('[data-court-filter]');
if (filterForm) {
    const syncRequirements = () => {
        const status = filterForm.elements.availability_status.value;
        filterForm.elements.booking_date.required = !!status;
        filterForm.elements.time_slot_id.required = !!status;
    };
    filterForm.elements.availability_status.addEventListener('change', syncRequirements);
    syncRequirements();
}
for (const [formSelector, loadingSelector] of [['[data-court-filter]', '[data-list-loading]'], ['[data-date-form]', '[data-date-loading]']]) {
    const form = document.querySelector(formSelector);
    const loading = document.querySelector(loadingSelector);
    form?.addEventListener('submit', () => {
        if (loading) loading.hidden = false;
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        if (loading) loading.hidden = true;
        form?.removeAttribute('aria-busy');
    });
}

document.querySelectorAll('[data-gallery-image]').forEach(button => {
    button.addEventListener('click', () => {
        const main = document.getElementById('court-main-image');
        main.src = button.dataset.galleryImage;
        main.closest('a').href = button.dataset.galleryImage;
        document.querySelectorAll('[data-gallery-image]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    });
});

const bookingForm = document.querySelector('[data-court-booking]');
if (bookingForm) {
    const selected = new Map();
    const cells = [...document.querySelectorAll('.court-slot.available')];
    const slotsField = document.getElementById('selectedSlots');
    const message = document.getElementById('slot-message');
    const submit = document.getElementById('court-submit');
    const dateInput = document.getElementById('dateInput');
    const tell = text => { message.textContent = text; message.hidden = !text; };
    const update = () => {
        const ordered = [...selected.values()].sort((a, b) => a.dataset.start.localeCompare(b.dataset.start));
        const courtTotal = ordered.reduce((sum, cell) => sum + Number(cell.dataset.price), 0);
        const serviceTotal = window.bookingServiceTotal || 0;
        slotsField.replaceChildren(...ordered.map(cell => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'time_slot_ids[]'; input.value = cell.dataset.slot;
            return input;
        }));
        document.getElementById('courtId').value = ordered.length ? bookingForm.dataset.courtId : '';
        document.getElementById('selectionCount').textContent = ordered.length;
        document.getElementById('selected-time-label').textContent = ordered.length ? ordered.map(cell => `${cell.dataset.start} – ${cell.dataset.end}`).join(', ') : 'Chưa chọn khung giờ.';
        document.getElementById('court-cost').textContent = money(courtTotal);
        document.getElementById('services-cost').textContent = money(serviceTotal);
        document.getElementById('subtotal-cost').textContent = money(courtTotal + serviceTotal);
        document.getElementById('selectionSummary').textContent = money(courtTotal + serviceTotal);
        document.getElementById('discount-label').textContent = bookingForm.elements.voucher_code.value.trim() ? 'Chờ xác nhận mã' : 'Chưa áp dụng';
        if (submit) submit.disabled = !ordered.length || dateInput.value !== bookingForm.elements.booking_date.value;
        const applyVoucher = document.getElementById('court-voucher-apply');
        if (applyVoucher) applyVoucher.disabled = !ordered.length || dateInput.value !== bookingForm.elements.booking_date.value;
    };
    cells.forEach(cell => cell.addEventListener('click', () => {
        const proposed = [...selected.values()].filter(item => item !== cell);
        if (!selected.has(cell.dataset.slot)) proposed.push(cell);
        if (!window.smashZoneConsecutiveSlots(proposed.map(item => ({ start: item.dataset.start, end: item.dataset.end })))) {
            tell('Chỉ chọn các khung giờ liền nhau. Khi bỏ chọn, hãy bỏ từ đầu hoặc cuối dãy giờ.');
            return;
        }
        const active = !selected.has(cell.dataset.slot);
        active ? selected.set(cell.dataset.slot, cell) : selected.delete(cell.dataset.slot);
        cell.classList.toggle('selected', active);
        cell.setAttribute('aria-pressed', String(active));
        cell.querySelector('[data-slot-label]').textContent = active ? 'Đã chọn' : 'Còn trống';
        tell(''); update();
    }));
    document.addEventListener('booking-services-changed', update);
    bookingForm.elements.voucher_code.addEventListener('input', update);
    dateInput.addEventListener('input', () => {
        tell(dateInput.value !== bookingForm.elements.booking_date.value ? 'Bạn đã đổi ngày. Nhấn “Xem lịch” trước khi chọn và đặt khung giờ mới.' : '');
        update();
    });
    bookingForm.addEventListener('submit', event => {
        if (!selected.size || dateInput.value !== bookingForm.elements.booking_date.value) {
            event.preventDefault(); tell('Vui lòng xem lịch đúng ngày và chọn khung giờ trước khi đặt.');
            document.getElementById('court-schedule').scrollIntoView({ behavior: 'smooth' });
        }
    });
    const initialIds = JSON.parse(bookingForm.dataset.initialSlots || '[]').map(String);
    const initialCells = cells.filter(cell => initialIds.includes(cell.dataset.slot)).sort((a, b) => a.dataset.start.localeCompare(b.dataset.start));
    if (initialCells.length && window.smashZoneConsecutiveSlots(initialCells.map(cell => ({start: cell.dataset.start, end: cell.dataset.end})))) {
        initialCells.forEach(cell => cell.click());
    }
    update();
}

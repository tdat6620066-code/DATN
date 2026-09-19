// Preserve links to the old account tabs, including links from booking details.
if (document.querySelector('[data-profile-dashboard]')) {
    const legacy = {account: 'account', history: 'history', password: 'password'};
    const handleHash = () => {
        const section = legacy[location.hash.slice(1)];
        if (!section) return;
        const url = new URL(location.href);
        if (url.searchParams.get('section') === section) return;
        url.searchParams.set('section', section); url.hash = '';
        location.replace(url);
    };
    window.addEventListener('hashchange', handleHash); handleHash();
}
// Hide expired payment actions using the server clock; backend remains authoritative.
const loadedAt = performance.now();
function refreshPaymentActions() {
    document.querySelectorAll('[data-payment-deadline]').forEach(link => {
        if (Number(link.dataset.serverNow) + performance.now() - loadedAt >= Number(link.dataset.paymentDeadline)) {
            link.hidden = true;
            link.setAttribute('aria-disabled', 'true');
        }
    });
}
refreshPaymentActions();setInterval(refreshPaymentActions, 1000);
document.querySelectorAll('[data-cancel-booking]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm('Bạn chắc chắn muốn hủy booking đang chờ thanh toán này?')) event.preventDefault();
}));

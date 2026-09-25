document.addEventListener('DOMContentLoaded', () => {
    const element = document.getElementById('daily-duration-modal');
    const form = document.getElementById('bookingForm');
    if (!element || !form || !window.bootstrap) return;
    const modal = bootstrap.Modal.getOrCreateInstance(element);
    const confirmation = document.getElementById('daily-duration-confirmed');
    document.getElementById('daily-duration-continue').addEventListener('click', () => {
        confirmation.disabled = false;
        modal.hide();
        form.requestSubmit();
    });
    modal.show();
});

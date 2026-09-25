document.addEventListener('DOMContentLoaded', () => {
    let busy = false;
    const refresh = async () => {
        if (busy) return;
        busy = true;
        try {
            const response = await fetch(location.href, {cache:'no-store', signal:AbortSignal.timeout(10000)});
            if (!response.ok || response.redirected) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.getElementById('booking-payment-summary');
            const current = document.getElementById('booking-payment-summary');
            if (next && current) current.replaceWith(next);
        } catch (_) { /* Retry without changing the visible figures. */ }
        finally { busy = false; }
    };
    setInterval(refresh, 3000);
    window.addEventListener('focus', refresh);
    window.addEventListener('online', refresh);
});

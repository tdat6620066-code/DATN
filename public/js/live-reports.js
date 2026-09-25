document.addEventListener('DOMContentLoaded', () => {
    const main = document.querySelector('.admin-main');
    if (!main) return;
    const status = document.createElement('p');
    status.className = 'small text-muted mb-3';
    status.setAttribute('role', 'status');
    main.before(status);
    status.style.padding = '8px 30px 0';
    status.textContent = 'Tự cập nhật khi báo cáo có số liệu mới';
    // Patch only changed nodes; keep the page, filter forms and scroll containers.
    const patch = (current, next) => {
        if (current.nodeType !== next.nodeType || current.nodeName !== next.nodeName) {
            current.replaceWith(next.cloneNode(true));
            return;
        }
        if (current.nodeType === Node.TEXT_NODE) {
            if (current.nodeValue !== next.nodeValue) current.nodeValue = next.nodeValue;
            return;
        }
        if (current.nodeType !== Node.ELEMENT_NODE || current.matches('form,script,style,.alert,.toast')) return;
        for (const attr of next.attributes) {
            if (current.getAttribute(attr.name) !== attr.value) current.setAttribute(attr.name, attr.value);
        }
        for (const attr of [...current.attributes]) {
            if (!next.hasAttribute(attr.name) && !['tabindex', 'aria-label', 'role'].includes(attr.name)) current.removeAttribute(attr.name);
        }
        const oldNodes = [...current.childNodes];
        const newNodes = [...next.childNodes];
        newNodes.forEach((node, index) => {
            if (oldNodes[index]) patch(oldNodes[index], node);
            else current.append(node.cloneNode(true));
        });
        oldNodes.slice(newNodes.length).forEach(node => node.remove());
    };
    const signature = element => {
        const copy = element.cloneNode(true);
        copy.querySelectorAll('form,script,style,.alert,.toast').forEach(node => node.remove());
        return copy.textContent.replace(/\s+/g, ' ').trim();
    };
    let previous = signature(main);
    let busy = false;
    let stopped = false;
    const update = async () => {
        if (busy || stopped || document.hidden || main.contains(document.activeElement) && document.activeElement.matches('input,select,textarea') || document.querySelector('.modal.show') || window.getSelection()?.toString()) return;
        busy = true;
        try {
            const response = await fetch(location.href, {cache: 'no-store', credentials: 'same-origin', signal: AbortSignal.timeout(10000), headers: {'X-Requested-With': 'XMLHttpRequest'}});
            if (response.redirected || response.status === 401 || response.status === 403) {
                stopped = true;
                status.textContent = 'Phiên truy cập đã thay đổi. Vui lòng tải lại trang.';
                return;
            }
            if (!response.ok) throw new Error('Report unavailable');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const incoming = page.querySelector('.admin-main');
            if (!incoming) throw new Error('Missing report');
            const fingerprint = signature(incoming);
            if (fingerprint === previous) return;
            const position = {left: window.scrollX, top: window.scrollY};
            const scroll = [...main.querySelectorAll('.table-responsive')].map(el => [el, el.scrollLeft, el.scrollTop]);
            patch(main, incoming);
            scroll.forEach(([el, left, top]) => { el.scrollLeft = left; el.scrollTop = top; });
            window.scrollTo({...position, behavior: 'instant'});
            previous = fingerprint;
            status.textContent = 'Đã nhận số liệu mới lúc ' + new Date().toLocaleTimeString('vi-VN');
        } catch (error) {
            status.textContent = 'Chưa thể cập nhật số liệu. Hệ thống sẽ tự thử lại.';
        } finally { busy = false; }
    };
    // Quiet background checks do not redraw unchanged reports.
    setInterval(update, 10000);
    window.addEventListener('focus', update);
    window.addEventListener('online', update);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
    update();
});

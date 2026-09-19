(() => {
    const header = document.querySelector('.sc-nav');
    if (!header) return;
    const sync = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
    sync();
    window.addEventListener('scroll', sync, { passive: true });
    header.querySelectorAll('.sc-nav-links a.sc-active').forEach(link => link.setAttribute('aria-current', 'page'));
    const menu = header.querySelector('#customerNavigation');
    menu?.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !menu.classList.contains('show')) return;
        window.bootstrap?.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
        header.querySelector('[data-bs-target="#customerNavigation"]')?.focus();
    });
})();

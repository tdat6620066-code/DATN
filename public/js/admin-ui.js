document.addEventListener('DOMContentLoaded', () => {
    const main = document.querySelector('.admin-main');
    if (!main) return;
    const validation = document.getElementById('admin-validation');
    if (validation) {
        const data = JSON.parse(validation.textContent);
        const editor = data.modal && document.getElementById(data.modal);
        if (editor?.classList.contains('modal') && window.bootstrap) bootstrap.Modal.getOrCreateInstance(editor).show();
        (editor || main).querySelectorAll('input,select,textarea').forEach(field => {
            if (!data.errors[field.name]) return;
            field.classList.add('is-invalid'); field.setAttribute('aria-invalid','true');
            const message = document.createElement('div'); message.className = 'invalid-feedback';
            message.textContent = data.errors[field.name].join(' '); field.after(message);
        });
    }
    main.querySelectorAll('table').forEach(table => {
        if (!table.closest('.table-responsive')) {
            const wrapper = document.createElement('div'); wrapper.className = 'table-responsive';
            table.before(wrapper); wrapper.append(table);
        }
        const wrapper = table.closest('.table-responsive');
        wrapper.tabIndex = 0; wrapper.setAttribute('role', 'region'); wrapper.setAttribute('aria-label', 'Bảng dữ liệu, cuộn ngang để xem thêm');
        if (table.tBodies.length && !table.tBodies[0].rows.length) {
            const cell = table.tBodies[0].insertRow().insertCell(); cell.colSpan = table.tHead?.rows[0]?.cells.length || 1;
            cell.className = 'text-center text-muted py-5'; cell.textContent = 'Chưa có dữ liệu phù hợp.';
        }
    });
    const modalElement = document.getElementById('admin-confirm');
    let pending = null;
    const modal = window.bootstrap && bootstrap.Modal.getOrCreateInstance(modalElement);
    document.querySelectorAll('.admin-main form, .admin-action-menu form').forEach(form => {
        const inline = form.getAttribute('onsubmit') || '';
        const oldConfirmation = inline.match(/^return confirm\(['"](.*?)['"]\);?$/);
        if (oldConfirmation) { form.dataset.confirm = oldConfirmation[1]; form.removeAttribute('onsubmit'); }
        if (form.querySelector('input[name="_method"][value="DELETE"]')) form.dataset.confirm ||= 'Xóa mục này? Thao tác sẽ được kiểm tra theo quy tắc của hệ thống.';
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            if (form.dataset.confirm && form.dataset.confirmed !== 'true') {
                event.preventDefault();
                if (!modal) { if (window.confirm(form.dataset.confirm)) { form.dataset.confirmed = 'true'; form.requestSubmit(event.submitter); } return; }
                pending = {form, submitter:event.submitter};
                modalElement.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
                modal.show(); return;
            }
            if (event.defaultPrevented) return;
            delete form.dataset.confirmed;
            form.dataset.submitting = 'true'; form.setAttribute('aria-busy','true');
            // Keep named submit controls successful so existing backend actions are preserved.
            form.querySelectorAll('button:not([type=button])').forEach(button => {
                button.setAttribute('aria-disabled','true');
                if (!button.name) button.disabled = true;
            });
            const status = document.createElement('span'); status.className = 'small ms-2'; status.setAttribute('role','status'); status.textContent = 'Đang xử lý…'; form.append(status);
        });
    });
    modalElement?.querySelector('[data-confirm-submit]').addEventListener('click', () => {
        if (!pending) return;
        const {form,submitter} = pending; pending = null; modal.hide();
        form.dataset.confirmed = 'true'; form.requestSubmit(submitter || undefined);
    });
    modalElement?.addEventListener('hidden.bs.modal', () => { pending = null; });
    const menus = [];
    document.querySelectorAll('.admin-row-actions').forEach(details => {
        const menu = details.querySelector('.admin-action-menu'); const summary = details.querySelector('summary');
        menu.querySelectorAll('a,button').forEach(control => {
            if (control.textContent.trim()) return;
            const icon = control.querySelector('i');
            const labels = {'bi-pencil':'Sửa','bi-trash':'Xóa','bi-eye':'Xem','bi-power':'Đổi trạng thái','bi-arrow-repeat':'Đồng bộ'};
            const label = control.title || Object.entries(labels).find(([key]) => icon?.classList.contains(key))?.[1] || 'Thao tác';
            control.append(document.createTextNode(label));
        });
        const close = () => { details.open = false; menu.style.cssText = ''; details.append(menu); };
        menus.push({details,menu,close});
        details.addEventListener('toggle', () => {
            if (!details.open) { menu.style.cssText = ''; details.append(menu); return; }
            menus.filter(item => item.details !== details).forEach(item => item.close());
            const rect = summary.getBoundingClientRect(); document.body.append(menu);
            menu.style.position = 'fixed'; menu.style.display = 'block';
            menu.style.left = `${Math.max(12, Math.min(rect.right - menu.offsetWidth, innerWidth-menu.offsetWidth-12))}px`;
            menu.style.top = `${Math.max(12, Math.min(rect.bottom+6, innerHeight-menu.offsetHeight-12))}px`;
        });
    });
    document.addEventListener('click', event => menus.forEach(item => { if (event.target.closest('[data-bs-toggle="modal"]') || (!item.details.contains(event.target) && !item.menu.contains(event.target))) item.close(); }));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') menus.forEach(item => { if (item.details.open) { item.close(); item.details.querySelector('summary').focus(); } }); });
    window.addEventListener('resize', () => menus.forEach(item => item.close()));
    document.addEventListener('scroll', event => menus.forEach(item => { if (!item.menu.contains(event.target)) item.close(); }), true);
    main.querySelectorAll('input,select,textarea').forEach(field => {
        if (!field.getAttribute('aria-label') && !field.id && !field.closest('label')) field.setAttribute('aria-label',field.placeholder || field.name || 'Thông tin');
        field.addEventListener('invalid', () => { field.classList.add('is-invalid'); field.setAttribute('aria-invalid','true'); });
        field.addEventListener('input', () => { if(field.validity.valid) {field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid');} });
    });
});
window.addEventListener('pageshow', event => { if(event.persisted) location.reload(); });

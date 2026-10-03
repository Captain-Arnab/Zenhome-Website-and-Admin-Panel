/* ==========================================================================
   Zen Home Experts - Admin Panel UI scripts (vanilla JS)
   Backend calls go through AdminApi (assets/js/api.js). Markup hooks:
     data-api="module.action"        on forms, delete/confirm buttons, toggles
     data-api-click="module.action"  plain action buttons/links
     data-api-params='{"k":"v"}'     extra payload; data-id adds {id}
     data-reload / data-redirect     what to do after success
   ========================================================================== */
(function () {
    'use strict';

    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const body = document.body;
    const isDesktop = () => window.matchMedia('(min-width: 992px)').matches;

    /* ------------------------------------------------------------------
       Toast helper - AdminUI.toast('Saved', 'success')
       ------------------------------------------------------------------ */
    const toastIcons = {
        success: 'bi-check-circle-fill text-success',
        danger: 'bi-x-circle-fill text-danger',
        warning: 'bi-exclamation-triangle-fill text-warning',
        info: 'bi-info-circle-fill text-info'
    };

    function toast(message, type = 'success') {
        const container = $('#toastContainer');
        if (!container || !window.bootstrap) return;
        const el = document.createElement('div');
        el.className = 'toast align-items-center bg-white';
        el.setAttribute('role', 'status');
        el.innerHTML =
            '<div class="d-flex align-items-center p-3 gap-2">' +
            '<i class="bi ' + (toastIcons[type] || toastIcons.info) + ' fs-5"></i>' +
            '<div class="flex-grow-1 fs-13 fw-semibold"></div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div>';
        el.querySelector('.flex-grow-1').textContent = message;
        container.appendChild(el);
        const t = new bootstrap.Toast(el, { delay: 3200 });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        t.show();
    }

    window.AdminUI = { toast };

    const Api = window.AdminApi;
    const reloadSoon = (ms = 700) => setTimeout(() => window.location.reload(), ms);
    const goSoon = (url, ms = 700) => setTimeout(() => { window.location.href = url; }, ms);

    /** Payload from data-api-params (+ data-id). */
    function payloadFor(el) {
        let payload = {};
        try { payload = JSON.parse(el.dataset.apiParams || '{}'); } catch (err) { payload = {}; }
        if (el.dataset.id) payload.id = el.dataset.id;
        return payload;
    }

    /** First "id" found in a response (data.id or data.<entity>.id). */
    function responseId(data) {
        if (!data || typeof data !== 'object') return '';
        if (data.id) return data.id;
        const nested = Object.values(data).find(v => v && typeof v === 'object' && v.id);
        return nested ? nested.id : '';
    }

    function afterSuccess(el, res) {
        if (el.dataset.redirect) {
            goSoon(el.dataset.redirect.replace('{id}', encodeURIComponent(responseId(res.data))));
        } else if (el.hasAttribute('data-reload')) {
            reloadSoon();
        }
    }

    /* ------------------------------------------------------------------
       Sidebar: collapse on desktop (remembered), off-canvas on mobile
       ------------------------------------------------------------------ */
    if (localStorage.getItem('zcAdminSidebar') === 'collapsed' && isDesktop()) {
        body.classList.add('sidebar-collapsed');
    }

    $$('[data-sidebar-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (isDesktop()) {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('zcAdminSidebar',
                    body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
            } else {
                body.classList.toggle('sidebar-open');
            }
        });
    });

    $$('[data-sidebar-close]').forEach(el =>
        el.addEventListener('click', () => body.classList.remove('sidebar-open')));

    // Clicking a submenu parent while collapsed expands the sidebar first
    $$('.sidebar [data-bs-toggle="collapse"]').forEach(link => {
        link.addEventListener('click', () => {
            if (isDesktop() && body.classList.contains('sidebar-collapsed')) {
                body.classList.remove('sidebar-collapsed');
                localStorage.setItem('zcAdminSidebar', 'expanded');
            }
        });
    });

    window.addEventListener('resize', () => {
        if (isDesktop()) body.classList.remove('sidebar-open');
    });

    /* ------------------------------------------------------------------
       Bootstrap tooltips
       ------------------------------------------------------------------ */
    if (window.bootstrap) {
        $$('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    }

    /* ------------------------------------------------------------------
       Confirm modal (delete / cancel / destructive actions)
       Usage: <button data-confirm="Delete this service?"
                      data-confirm-title="Delete Service"
                      data-confirm-btn="Delete"
                      data-remove-row>...</button>
       ------------------------------------------------------------------ */
    const confirmModalEl = $('#confirmModal');
    let confirmTrigger = null;

    document.addEventListener('click', e => {
        const trigger = e.target.closest('[data-confirm]');
        if (!trigger || !confirmModalEl) return;
        e.preventDefault();
        confirmTrigger = trigger;
        $('#confirmModalTitle').textContent = trigger.dataset.confirmTitle || 'Are you sure?';
        $('#confirmModalText').textContent = trigger.dataset.confirm || 'This action cannot be undone.';
        $('#confirmModalBtn').textContent = trigger.dataset.confirmBtn || 'Yes, Delete';
        bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
    });

    if (confirmModalEl) {
        const confirmBtn = $('#confirmModalBtn');
        confirmBtn.addEventListener('click', async () => {
            const trigger = confirmTrigger;
            if (!trigger) return;
            let message = trigger.dataset.confirmDone || 'Done.';
            let res = null;
            if (trigger.dataset.api) {
                try {
                    res = await Api.busy(confirmBtn, Api.post(trigger.dataset.api, payloadFor(trigger)));
                    message = res.message || message;
                } catch (err) {
                    toast(err.message, 'danger');
                    return;
                }
            }
            if (trigger.hasAttribute('data-remove-row')) {
                const row = trigger.closest('tr, .removable');
                if (row) {
                    const table = row.closest('table');
                    if (table && window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(table)) {
                        jQuery(table).DataTable().row(row).remove().draw(false);
                    } else {
                        row.remove();
                    }
                }
            }
            bootstrap.Modal.getInstance(confirmModalEl).hide();
            toast(message, 'success');
            confirmTrigger = null;
            if (res) afterSuccess(trigger, res);
        });
    }

    /* ------------------------------------------------------------------
       Plain API action buttons/links (no confirmation)
       Usage: <button data-api-click="dashboard.alerts_read" data-reload>
       ------------------------------------------------------------------ */
    document.addEventListener('click', e => {
        const el = e.target.closest('[data-api-click]');
        if (!el || el.hasAttribute('data-confirm')) return;
        e.preventDefault();
        if (el.getAttribute('aria-busy') === 'true') return;
        Api.busy(el, Api.post(el.dataset.apiClick, payloadFor(el)))
            .then(res => {
                toast(res.message || 'Done.', 'success');
                if (el.hasAttribute('data-mark-read')) {
                    $$('.notification-item.unread').forEach(n => n.classList.remove('unread'));
                    $$('.topbar .dot-count').forEach(n => n.remove());
                    el.remove();
                }
                el.dispatchEvent(new CustomEvent('api:success', { bubbles: true, detail: res }));
                afterSuccess(el, res);
            })
            .catch(err => toast(err.message, 'danger'));
    });

    /* ------------------------------------------------------------------
       Active / inactive toggle switches
       Usage: <input class="form-check-input js-status-toggle" type="checkbox"
                     data-on="Active" data-off="Inactive">
       ------------------------------------------------------------------ */
    document.addEventListener('change', e => {
        const input = e.target.closest('.js-status-toggle');
        if (!input) return;
        const on = input.dataset.on || 'Active';
        const off = input.dataset.off || 'Inactive';
        const label = input.closest('.form-check')?.querySelector('.form-check-label');
        const sync = () => { if (label) label.textContent = input.checked ? on : off; };
        sync();
        if (!input.dataset.api) return;

        const payload = payloadFor(input);
        payload.status = input.checked ? 1 : 0;
        input.disabled = true;
        Api.post(input.dataset.api, payload)
            .then(res => {
                toast(res.message || ((input.dataset.name ? input.dataset.name + ': ' : '') + (input.checked ? on : off)), input.checked ? 'success' : 'warning');
                input.dispatchEvent(new CustomEvent('api:success', { bubbles: true, detail: res }));
            })
            .catch(err => {
                input.checked = !input.checked;
                sync();
                toast(err.message, 'danger');
            })
            .finally(() => { input.disabled = false; });
    });

    /* ------------------------------------------------------------------
       Form validation (Bootstrap pattern) + API submit
       <form class="needs-validation" data-api="categories.save"
             data-redirect="page.php?id={id}" | data-reload>
       Forms inside a modal reload the page after success by default.
       Server-side field errors (422) are shown under the matching inputs.
       Pages can listen for "api:before" (sync editors) / "api:success".
       ------------------------------------------------------------------ */
    function clearFieldErrors(form) {
        $$('.is-invalid', form).forEach(el => el.classList.remove('is-invalid'));
        $$('.api-feedback', form).forEach(el => el.remove());
        $$('.invalid-feedback[data-orig]', form).forEach(fb => { fb.textContent = fb.dataset.orig; });
    }

    function showFieldErrors(form, errors) {
        let first = null;
        Object.keys(errors || {}).forEach(name => {
            let field = form.elements[name] || form.elements[name + '[]'];
            if (field instanceof RadioNodeList) field = field[0];
            if (!field || !field.classList) return;
            field.classList.add('is-invalid');
            const scope = field.closest('.input-group') || field.parentElement;
            let fb = scope && scope.querySelector('.invalid-feedback');
            if (fb && (scope === field.parentElement || field.closest('.input-group'))) {
                if (!fb.dataset.orig) fb.dataset.orig = fb.textContent;
                fb.textContent = errors[name];
            } else {
                fb = document.createElement('div');
                fb.className = 'invalid-feedback api-feedback d-block';
                fb.textContent = errors[name];
                (field.closest('.input-group') || field).insertAdjacentElement('afterend', fb);
            }
            first = first || field;
        });
        if (first && first.focus) first.focus();
    }

    /** FormData with unchecked switches sent as 0 and empty file inputs dropped. */
    function formPayload(form) {
        const fd = new FormData(form);
        $$('input[type="checkbox"][name]', form).forEach(cb => {
            if (cb.name.endsWith('[]') || cb.disabled) return;
            fd.set(cb.name, cb.checked ? '1' : '0');
        });
        $$('input[type="file"][name]', form).forEach(input => {
            if (!input.files || !input.files.length) fd.delete(input.name);
        });
        return fd;
    }

    function submitApiForm(form, submitter) {
        const before = new CustomEvent('api:before', { cancelable: true });
        if (!form.dispatchEvent(before)) return;
        clearFieldErrors(form);
        const button = submitter || form.querySelector('[type="submit"]') || $('[type="submit"][form="' + form.id + '"]');
        const endpoint = (submitter && submitter.dataset.api) || form.dataset.api;
        const fd = formPayload(form);
        if (submitter && submitter.name) fd.set(submitter.name, submitter.value);
        Api.busy(button, Api.post(endpoint, fd))
            .then(res => {
                toast(res.message || 'Saved successfully.', 'success');
                form.dispatchEvent(new CustomEvent('api:success', { detail: res }));
                const modal = form.closest('.modal');
                if (modal) bootstrap.Modal.getInstance(modal)?.hide();
                if (form.hasAttribute('data-reset')) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
                if (form.dataset.redirect) {
                    goSoon(form.dataset.redirect.replace('{id}', encodeURIComponent(responseId(res.data))));
                } else if (form.hasAttribute('data-reload') || (modal && !form.hasAttribute('data-no-reload'))) {
                    reloadSoon();
                }
            })
            .catch(err => {
                form.classList.remove('was-validated');
                showFieldErrors(form, err.errors);
                toast(err.message, 'danger');
                form.dispatchEvent(new CustomEvent('api:error', { detail: err }));
            });
    }

    $$('form.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');
                const firstInvalid = form.querySelector(':invalid');
                if (firstInvalid) firstInvalid.focus();
                return;
            }
            form.classList.add('was-validated');
            if (form.dataset.api && !form.hasAttribute('data-no-auto-submit')) {
                e.preventDefault();
                submitApiForm(form, e.submitter);
            }
        });
        form.addEventListener('input', e => {
            if (e.target.classList && e.target.classList.contains('is-invalid')) e.target.classList.remove('is-invalid');
        });
    });

    window.AdminUI.submitApiForm = submitApiForm;
    window.AdminUI.showFieldErrors = showFieldErrors;

    // Reset validation state whenever a modal containing a form is closed
    $$('.modal').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', () => {
            $$('form', modal).forEach(f => { f.classList.remove('was-validated'); clearFieldErrors(f); });
        });
    });

    /* ------------------------------------------------------------------
       CRUD modals: prefill fields from the trigger's data-fill JSON
       Usage: <button data-bs-toggle="modal" data-bs-target="#categoryModal"
                      data-title="Edit Category"
                      data-fill='{"name":"AC Service","status":true}'>
       Modal needs class "js-crud-modal" and an element with [data-modal-title].
       ------------------------------------------------------------------ */
    $$('.js-crud-modal').forEach(modal => {
        const titleEl = $('[data-modal-title]', modal);
        const defaultTitle = titleEl ? titleEl.textContent : '';
        modal.addEventListener('show.bs.modal', e => {
            const trigger = e.relatedTarget;
            const form = $('form', modal);
            if (!form || !trigger) return;
            form.reset();
            // Hidden inputs keep JS-set values across reset(); clear them (e.g. "id")
            $$('input[type="hidden"]:not([data-keep])', form).forEach(h => { h.value = ''; });
            $$('.upload-box', form).forEach(box => {
                box.classList.remove('has-image');
                const img = $('img.preview', box);
                if (img) img.removeAttribute('src');
            });
            if (titleEl) titleEl.textContent = trigger.dataset.title || defaultTitle;
            let data = {};
            try { data = JSON.parse(trigger.dataset.fill || '{}'); } catch (err) { data = {}; }
            Object.keys(data).forEach(key => {
                const field = form.elements[key];
                if (!field) return;
                if (field.type === 'file') {
                    // File inputs can't be prefilled: the value is the current image URL for the preview
                    const box = field.closest('.upload-box');
                    const img = box && $('img.preview', box);
                    if (img && data[key]) { img.src = data[key]; box.classList.add('has-image'); }
                    return;
                }
                if (field.type === 'checkbox') {
                    field.checked = !!data[key];
                    field.dispatchEvent(new Event('sync'));
                } else if (field instanceof RadioNodeList) {
                    $$('input', form).filter(i => i.name === key).forEach(i => { i.checked = i.value === String(data[key]); });
                } else if (field.multiple) {
                    const values = [].concat(data[key]).map(String);
                    Array.from(field.options).forEach(o => { o.selected = values.includes(o.value); });
                } else {
                    field.value = data[key];
                    field.dispatchEvent(new Event('change'));
                }
            });
            // Keep switch labels in sync with prefilled state
            $$('.js-status-toggle', form).forEach(input => {
                const label = input.closest('.form-check')?.querySelector('.form-check-label');
                if (label) label.textContent = input.checked ? (input.dataset.on || 'Active') : (input.dataset.off || 'Inactive');
            });
        });
    });

    /* ------------------------------------------------------------------
       Image upload preview
       Markup: <label class="upload-box"><input type="file" accept="image/*">
               <img class="preview"><div class="upload-placeholder">...</div></label>
       ------------------------------------------------------------------ */
    document.addEventListener('change', e => {
        const input = e.target;
        if (input.type !== 'file' || !input.closest('.upload-box')) return;
        const box = input.closest('.upload-box');
        const img = $('img.preview', box);
        const file = input.files && input.files[0];
        if (!file || !img) return;
        if (!file.type.startsWith('image/')) {
            toast('Please choose an image file.', 'danger');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = ev => { img.src = ev.target.result; box.classList.add('has-image'); };
        reader.readAsDataURL(file);
    });

    /* ------------------------------------------------------------------
       Dependent selects (e.g. Category -> Subcategory)
       Parent: <select data-child="#subcategory">
       Child options: <option data-parent="AC Service">Split AC</option>
       ------------------------------------------------------------------ */
    $$('select[data-child]').forEach(parent => {
        const child = $(parent.dataset.child);
        if (!child) return;
        const sync = () => {
            const val = parent.value;
            let firstMatch = null;
            Array.from(child.options).forEach(opt => {
                if (!opt.dataset.parent) return;
                const show = !val || opt.dataset.parent === val;
                opt.hidden = !show;
                opt.disabled = !show;
                if (show && !firstMatch) firstMatch = opt;
            });
            const selected = child.selectedOptions[0];
            if (selected && selected.disabled) child.value = '';
        };
        parent.addEventListener('change', sync);
        sync();
    });

    /* ------------------------------------------------------------------
       Password show/hide
       ------------------------------------------------------------------ */
    document.addEventListener('click', e => {
        const btn = e.target.closest('.toggle-pass');
        if (!btn) return;
        const input = btn.closest('.input-group')?.querySelector('input');
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    /* ------------------------------------------------------------------
       Select-all checkboxes
       Usage: <input type="checkbox" data-check-all=".svc-check">
       ------------------------------------------------------------------ */
    document.addEventListener('change', e => {
        const master = e.target.closest('[data-check-all]');
        if (!master) return;
        const scope = master.closest('[data-check-scope]') || document;
        $$(master.dataset.checkAll, scope).forEach(cb => { cb.checked = master.checked; });
    });

    /* ------------------------------------------------------------------
       Template variable chips + live character counter (SMS, notifications)
       Chip: <span class="var-chip" data-insert="{customer_name}" data-target="#tplBody">
       Counter: <textarea data-char-count="#counterEl" data-preview="#previewEl">
       ------------------------------------------------------------------ */
    document.addEventListener('click', e => {
        const chip = e.target.closest('.var-chip');
        if (!chip) return;
        const target = $(chip.dataset.target);
        if (!target) return;
        const text = chip.dataset.insert || chip.textContent.trim();
        const start = target.selectionStart ?? target.value.length;
        const end = target.selectionEnd ?? target.value.length;
        target.value = target.value.slice(0, start) + text + target.value.slice(end);
        target.focus();
        target.selectionStart = target.selectionEnd = start + text.length;
        target.dispatchEvent(new Event('input'));
    });

    function syncCounter(el) {
        const counter = $(el.dataset.charCount);
        if (counter) {
            const len = el.value.length;
            const parts = len <= 160 ? 1 : Math.ceil(len / 153);
            counter.textContent = len + ' characters' + (el.hasAttribute('data-sms') ? ' - ' + parts + ' SMS' : '');
        }
        const preview = el.dataset.preview ? $(el.dataset.preview) : null;
        if (preview) preview.textContent = el.value || preview.dataset.empty || '';
    }
    $$('[data-char-count]').forEach(el => {
        el.addEventListener('input', () => syncCounter(el));
        syncCounter(el);
    });

    /* ------------------------------------------------------------------
       Assign / Reassign professional modal (shared include)
       Trigger: <button data-bs-toggle="modal" data-bs-target="#assignModal"
                        data-booking="ZC1004" data-service="AC Service"
                        data-category="AC Service" data-current="Ravi Kumar">
       ------------------------------------------------------------------ */
    const assignModal = $('#assignModal');
    if (assignModal) {
        assignModal.addEventListener('show.bs.modal', e => {
            const t = e.relatedTarget;
            if (!t) return;
            const form = $('form', assignModal);
            form.reset();
            form.classList.remove('was-validated');
            const current = t.dataset.current || '';
            $('[data-assign-booking]', assignModal).textContent = t.dataset.code || t.dataset.booking || '-';
            $('[data-assign-service]', assignModal).textContent = t.dataset.service || '-';
            $('[data-assign-slot]', assignModal).textContent = t.dataset.slot || '-';
            $('[data-assign-title]', assignModal).textContent = current ? 'Reassign Professional' : 'Assign Professional';
            const currentWrap = $('[data-assign-current-wrap]', assignModal);
            currentWrap.classList.toggle('d-none', !current);
            $('[data-assign-current]', assignModal).textContent = current;
            $('[data-assign-reason-wrap]', assignModal).classList.toggle('d-none', !current);
            form.elements.reassign_reason.required = !!current;
            form.elements.booking_id.value = t.dataset.booking || '';

            // Star professionals matching the booking's category; hide the current one
            const category = (t.dataset.category || '').toLowerCase();
            const currentId = t.dataset.currentId || '';
            Array.from(form.elements.professional_id.options).forEach(opt => {
                if (!opt.value) return;
                const base = (opt.dataset.label || opt.textContent).trim();
                opt.dataset.label = base;
                const cats = (opt.dataset.categories || '').split('|').filter(Boolean);
                const match = category && cats.some(c => category.includes(c) || c.includes(category));
                opt.textContent = (match ? '\u2605 ' : '') + base;
                opt.hidden = opt.disabled = opt.value === currentId;
            });
        });

        $('form', assignModal).addEventListener('submit', e => {
            e.preventDefault();
            const form = e.target;
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            clearFieldErrors(form);
            const fd = formPayload(form);
            Api.busy(form.querySelector('[type="submit"]'), Api.post('bookings.assign', fd))
                .then(res => {
                    bootstrap.Modal.getInstance(assignModal).hide();
                    toast(res.message || 'Professional assigned.', 'success');
                    reloadSoon(900);
                })
                .catch(err => {
                    form.classList.remove('was-validated');
                    showFieldErrors(form, err.errors);
                    toast(err.message, 'danger');
                });
        });
    }

    /* ------------------------------------------------------------------
       CSV export of a table (all rows matching the current filters)
       Usage: <button data-export="#bookingsTable" data-filename="bookings.csv">
       Columns with class "no-export" on the <th> (e.g. Actions) are skipped.
       ------------------------------------------------------------------ */
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-export]');
        if (!btn) return;
        e.preventDefault();
        const table = $(btn.dataset.export);
        if (!table) return;
        const heads = $$('thead th', table);
        const skip = heads.map(th => th.classList.contains('no-export') || th.textContent.trim() === '' || /^actions?$/i.test(th.textContent.trim()) || !!th.querySelector('input[type="checkbox"]'));
        let rows;
        if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(table)) {
            rows = jQuery(table).DataTable().rows({ search: 'applied' }).nodes().toArray();
        } else {
            rows = $$('tbody tr', table).filter(tr => !tr.querySelector('.empty-state') && !tr.hidden);
        }
        if (!rows.length) { toast('Nothing to export.', 'warning'); return; }
        const clean = el => (el.dataset.export || el.innerText || '').replace(/\s+/g, ' ').trim();
        const csvCell = v => /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
        const lines = [heads.filter((th, i) => !skip[i]).map(th => csvCell(clean(th))).join(',')];
        rows.forEach(tr => {
            lines.push(Array.from(tr.children).filter((td, i) => !skip[i]).map(td => csvCell(clean(td))).join(','));
        });
        const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = btn.dataset.filename || 'export.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        toast(rows.length + ' row' + (rows.length > 1 ? 's' : '') + ' exported.', 'success');
    });

    /* ------------------------------------------------------------------
       DataTables (optional - only when the page loads the library)
       Table:   <table class="table js-datatable" id="bookingsTable">
       Search:  <input data-dt-search="#bookingsTable">
       Filter:  <select data-dt-filter="#bookingsTable" data-column="6" data-exact>
       Tabs:    <button data-dt-tab="#bookingsTable" data-column="6" data-value="Pending">
       ------------------------------------------------------------------ */
    if (window.jQuery && jQuery.fn.DataTable) {
        const escapeRegex = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        $$('table.js-datatable').forEach(table => {
            jQuery(table).DataTable({
                order: [],
                pageLength: parseInt(table.dataset.pageLength || '10', 10),
                lengthMenu: [10, 25, 50, 100],
                autoWidth: false,
                dom: "<'table-responsive't><'dt-bottom'<'dataTables_info_wrap'i><'dt-controls'lp>>",
                language: {
                    lengthMenu: 'Show _MENU_',
                    info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                    infoEmpty: 'No entries',
                    emptyTable: '<div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">' + (table.dataset.empty || 'No records yet') + '</p></div>',
                    zeroRecords: '<div class="empty-state"><i class="bi bi-inbox"></i><p class="mt-2 mb-0">No matching records found</p></div>',
                    paginate: { previous: '<i class="bi bi-chevron-left"></i>', next: '<i class="bi bi-chevron-right"></i>' }
                }
            });
        });

        const applyColumnFilter = (tableSel, column, value, exact) => {
            const dt = jQuery(tableSel).DataTable();
            if (!value) {
                dt.column(column).search('').draw();
            } else if (exact) {
                dt.column(column).search('^\\s*' + escapeRegex(value) + '\\s*$', true, false).draw();
            } else {
                dt.column(column).search(value).draw();
            }
        };

        $$('[data-dt-search]').forEach(input => {
            input.addEventListener('input', () => jQuery(input.dataset.dtSearch).DataTable().search(input.value).draw());
        });

        $$('[data-dt-filter]').forEach(select => {
            select.addEventListener('change', () =>
                applyColumnFilter(select.dataset.dtFilter, parseInt(select.dataset.column, 10), select.value, select.hasAttribute('data-exact')));
        });

        $$('[data-dt-tab]').forEach(tab => {
            tab.addEventListener('click', () => {
                const siblings = $$('[data-dt-tab="' + tab.dataset.dtTab + '"]');
                const dt = jQuery(tab.dataset.dtTab).DataTable();
                siblings.forEach(t => {
                    t.classList.remove('active');
                    dt.column(parseInt(t.dataset.column, 10)).search('');
                });
                tab.classList.add('active');
                applyColumnFilter(tab.dataset.dtTab, parseInt(tab.dataset.column, 10), tab.dataset.value, true);
            });
        });
        // Apply a tab that was preselected server-side (e.g. bookings.php?status=unassigned)
        $$('[data-dt-tab].active').forEach(tab => { if (tab.dataset.value) tab.click(); });

        $$('[data-dt-reset]').forEach(btn => {
            btn.addEventListener('click', () => {
                const sel = btn.dataset.dtReset;
                const scope = btn.closest('.filter-bar') || document;
                $$('input, select', scope).forEach(el => { el.value = ''; });
                const dt = jQuery(sel).DataTable();
                dt.search('').columns().search('').draw();
                $$('[data-dt-tab="' + sel + '"]').forEach((t, i) => t.classList.toggle('active', i === 0));
            });
        });
    }

    /* ------------------------------------------------------------------
       Keep a tab open after reload via URL hash (#tab-id)
       ------------------------------------------------------------------ */
    if (location.hash && window.bootstrap) {
        const tabTrigger = $('[data-bs-toggle="tab"][data-bs-target="' + location.hash + '"], [data-bs-toggle="pill"][data-bs-target="' + location.hash + '"]');
        if (tabTrigger) bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
    }
    $$('[data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(t => {
        t.addEventListener('shown.bs.tab', () => {
            if (t.dataset.bsTarget) history.replaceState(null, '', t.dataset.bsTarget);
            if (window.jQuery && jQuery.fn.dataTable) jQuery.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });
    });
})();

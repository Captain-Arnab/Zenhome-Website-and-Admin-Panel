/* ==========================================================================
   Zen Home Experts admin - API client (all browser -> backend calls go through here)
   AdminApi.post('customers.set_status', { id: 5, status: 0 })
   AdminApi.get('bookings.list', { status: 'New' })
   - Calls ../api/admin/<module>.php?action=<action>
   - Sends the session cookie + X-CSRF-Token (from <meta name="csrf-token">)
   - Resolves with the JSON body; rejects with ApiError {message, status, errors}
   ========================================================================== */
(function () {
    'use strict';

    const BASE = '../api/admin/';

    class ApiError extends Error {
        constructor(message, status, errors) {
            super(message);
            this.status = status;
            this.errors = errors || {};
        }
    }

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function url(endpoint, params) {
        const [module, action] = endpoint.split('.');
        const qs = new URLSearchParams(Object.assign({ action }, params || {}));
        return BASE + module + '.php?' + qs.toString();
    }

    async function request(method, endpoint, data) {
        const options = {
            method,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' }
        };
        let target = url(endpoint, method === 'GET' ? data : null);
        if (method !== 'GET') {
            if (data instanceof FormData) {
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data || {});
            }
        }

        let response;
        try {
            response = await fetch(target, options);
        } catch (err) {
            throw new ApiError('Network error. Check your connection and try again.', 0);
        }

        let body = null;
        try { body = await response.json(); } catch (err) { body = null; }

        if (!response.ok || !body || body.status !== 'success') {
            const message = (body && body.message) || ('Request failed (' + response.status + ').');
            if (response.status === 401) {
                setTimeout(() => { window.location.href = 'login.php?next=' + encodeURIComponent(location.pathname.split('/').pop() + location.search); }, 1200);
            }
            throw new ApiError(message, response.status, body && body.errors);
        }
        return body;
    }

    /** Disables a button and shows a spinner while a promise runs. */
    function busy(button, promise) {
        if (!button) return promise;
        const html = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + (button.dataset.busyText || 'Please wait...');
        const restore = () => { button.disabled = false; button.removeAttribute('aria-busy'); button.innerHTML = html; };
        return promise.then(r => { restore(); return r; }, e => { restore(); throw e; });
    }

    window.AdminApi = {
        ApiError,
        get: (endpoint, params) => request('GET', endpoint, params),
        post: (endpoint, data) => request('POST', endpoint, data),
        busy
    };
})();

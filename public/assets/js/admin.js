/* =============================================================================
   ELHOE Admin - Shared JS (utilities, AJAX helpers, table interactions)
   ========================================================================== */
(function () {
    'use strict';
    const $  = (s, r=document) => r.querySelector(s);
    const $$ = (s, r=document) => Array.from(r.querySelectorAll(s));

    // CSRF token from meta
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    window.CSRF = csrfMeta ? csrfMeta.content : '';

    // POST JSON helper
    window.apiPost = async function (url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload || {})
        });
        let data = {};
        try { data = await res.json(); } catch (e) { /* */ }
        return { ok: res.ok, status: res.status, data };
    };

    // Toast
    window.toast = function (msg, type = 'info') {
        let host = $('#toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'toast-host';
            host.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px';
            document.body.appendChild(host);
        }
        const el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = msg;
        host.appendChild(el);
        setTimeout(() => { el.classList.add('show'); }, 10);
        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }, 3500);
    };

    // Confirm
    window.confirmAction = function (msg) {
        return window.confirm(msg);
    };

    // Bulk select-all in tables
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (t && t.matches && t.matches('.bulk-check-all')) {
            const scope = t.closest('table') || document;
            scope.querySelectorAll('input.bulk-check').forEach((c) => { c.checked = t.checked; });
        }
    });

    // Sticky table header shadow on scroll
    const tableWrap = $('.table-wrap');
    if (tableWrap) {
        tableWrap.addEventListener('scroll', () => {
            tableWrap.classList.toggle('is-scrolled', tableWrap.scrollLeft > 0);
        });
    }

    // Auto-dismiss flash
    setTimeout(() => {
        $$('.flash').forEach((f) => { f.style.transition = 'opacity .4s'; f.style.opacity = '0'; setTimeout(() => f.remove(), 400); });
    }, 4500);
})();

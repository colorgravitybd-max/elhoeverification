/* =============================================================================
   ELHOE Verification - Customer-Facing JS
   Handles input sanitization, verify/register API calls, and UI rendering.
   No external libraries. Vanilla JS, ES2017+.
   ========================================================================== */

(function () {
    'use strict';

    const cfg = window.ELHOE_CONFIG || {};
    // API base must be SAME-ORIGIN to avoid CORS. We accept either an
    // absolute URL or a path; if it's a path, we leave it as-is so the
    // browser resolves it against the page's origin.
    const API = cfg.apiBase || '/checker/api';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const escapeHtml = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // ------------------------------------------------------------------------
    // Form & input
    // ------------------------------------------------------------------------
    const form = $('#verify-form');
    const codeInput = $('#code-input');
    const verifyBtn = $('#verify-btn');
    const pasteBtn  = $('#paste-btn');
    const mount = $('#result-mount');

    if (!form) return;

    // Force digits-only on input
    if (codeInput) {
        codeInput.addEventListener('input', () => {
            const stripped = codeInput.value.replace(/\D/g, '').slice(0, 20);
            if (codeInput.value !== stripped) codeInput.value = stripped;
        });
        codeInput.addEventListener('paste', (e) => {
            e.preventDefault();
            const txt = (e.clipboardData || window.clipboardData).getData('text');
            const digits = (txt || '').replace(/\D/g, '').slice(0, 20);
            codeInput.value = digits;
            codeInput.dispatchEvent(new Event('input'));
        });
        // Auto-focus on desktop only (not mobile, to avoid surprise keyboard)
        if (window.matchMedia('(min-width: 768px)').matches) {
            setTimeout(() => codeInput.focus(), 100);
        }
    }

    // Paste button
    if (pasteBtn && navigator.clipboard) {
        pasteBtn.addEventListener('click', async () => {
            try {
                const text = await navigator.clipboard.readText();
                const digits = (text || '').replace(/\D/g, '').slice(0, 20);
                if (digits) {
                    codeInput.value = digits;
                    codeInput.focus();
                }
            } catch (err) {
                // Permission denied or unsupported
            }
        });
    } else if (pasteBtn) {
        pasteBtn.style.display = 'none';
    }

    // Form submit
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = (codeInput.value || '').replace(/\D/g, '');
        if (!code) {
            renderError('Please enter your product code.');
            return;
        }
        await verifyCode(code);
    });

    // Auto-verify if prefilled from QR scan
    if (cfg.autoVerify && codeInput && codeInput.value) {
        // Defer slightly so analytics ping isn't blocked
        setTimeout(() => form.dispatchEvent(new Event('submit')), 200);
    }

    // ------------------------------------------------------------------------
    // Verify
    // ------------------------------------------------------------------------
    async function verifyCode(code) {
        setLoading(true);
        try {
            const res = await fetch(API + '/verify.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': cfg.csrfToken || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ code })
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok && res.status !== 200) {
                renderError(data.message || 'Something went wrong. Please try again.');
                return;
            }
            renderResult(data);
            pushAnalytics(data);
        } catch (err) {
            renderError('Network error. Please check your connection and try again.');
        } finally {
            setLoading(false);
        }
    }

    function setLoading(on) {
        verifyBtn.disabled = !!on;
        verifyBtn.classList.toggle('is-loading', !!on);
    }

    // ------------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------------
    function renderResult(data) {
        const r = data.result;
        let html = '';

        switch (r) {
            case 'valid_universal':
            case 'already_registered':
                html = renderSuccess(data);
                break;
            case 'valid_unique_first':
            case 'valid_unique_returning':
                html = renderSuccess(data) + renderRegisterForm(data);
                break;
            case 'quarantined':
                html = renderQuarantined(data);
                break;
            case 'invalid':
                html = renderInvalid(data);
                break;
            case 'malformed':
                html = renderMalformed(data);
                break;
            case 'rate_limited':
                html = renderRateLimited(data);
                break;
            default:
                html = renderError('Unknown response. Please try again.');
                break;
        }

        mount.innerHTML = html;
        attachResultHandlers();
        mount.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderSuccess(data) {
        const p = data.product || {};
        const c = data.code || {};
        const owner = data.owner || null;

        const expiry = c.expiry_date ? formatDate(c.expiry_date) : null;
        const headline = data.result === 'already_registered'
            ? `Welcome back${owner && owner.first_name ? ', ' + escapeHtml(owner.first_name) : ''}!`
            : 'Authentic Product';

        return `
        <div class="result result-success">
            <div class="result-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <h2 class="result-title">${escapeHtml(headline)}</h2>
            <p class="result-message">${escapeHtml(data.message || '')}</p>
            ${p.id ? renderProductCard(p, c, expiry) : ''}
            ${data.recommendations && data.recommendations.length ? renderRecommendations(data.recommendations) : ''}
        </div>`;
    }

    function renderProductCard(p, c, expiry) {
        const img = p.image_url ? `<img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" loading="lazy">` : `<div style="width:80px;height:80px;background:var(--color-beige);border-radius:6px;flex:0 0 80px"></div>`;
        const linkOpen  = p.product_url ? `<a href="${escapeHtml(p.product_url)}" target="_blank" rel="noopener">` : '';
        const linkClose = p.product_url ? `</a>` : '';
        const meta = [];
        if (c.batch_number) meta.push(`<dt>Batch</dt><dd>${escapeHtml(c.batch_number)}</dd>`);
        if (expiry)         meta.push(`<dt>Expires</dt><dd>${escapeHtml(expiry)}</dd>`);
        if (c.code)         meta.push(`<dt>Code</dt><dd>${escapeHtml(c.code)}</dd>`);

        return `
        <div class="result-product">
            ${linkOpen}${img}${linkClose}
            <div class="result-product-meta">
                <h3 class="result-product-name">${linkOpen}${escapeHtml(p.name || '')}${linkClose}</h3>
                ${meta.length ? `<dl>${meta.join('')}</dl>` : ''}
                ${p.product_url ? `<a href="${escapeHtml(p.product_url)}" target="_blank" rel="noopener" class="buy-again">Buy Again →</a>` : ''}
            </div>
        </div>`;
    }

    function renderRecommendations(items) {
        const cards = items.filter(Boolean).map((p) => `
            <a class="rec-card" href="${escapeHtml(p.product_url || '#')}" target="_blank" rel="noopener">
                ${p.image_url ? `<img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" loading="lazy">` : `<div style="width:100%;aspect-ratio:1;background:var(--color-beige);border-radius:6px;margin-bottom:8px"></div>`}
                <p class="rec-card-name">${escapeHtml(p.name || '')}</p>
            </a>`).join('');
        return `
        <div class="recommendations">
            <h3>Complete Your Routine</h3>
            <div class="rec-grid">${cards}</div>
        </div>`;
    }

    function renderRegisterForm(data) {
        const codeId = (data.code && data.code.id) ? data.code.id : '';
        return `
        <div class="register-form" id="register-form-wrap">
            <h3>Register your product</h3>
            <p style="font-size:14px;color:var(--color-muted);margin:0 0 12px">Activate your warranty and stay updated with care tips.</p>
            <form id="register-form" data-code-id="${escapeHtml(codeId)}">
                <div class="field-row">
                    <div class="field"><label for="reg-fn">First Name</label><input id="reg-fn" name="first_name" required maxlength="60"></div>
                    <div class="field"><label for="reg-ln">Last Name</label><input id="reg-ln" name="last_name" maxlength="60"></div>
                </div>
                <div class="field"><label for="reg-em">Email</label><input id="reg-em" name="email" type="email" required maxlength="120"></div>
                <div class="field-row">
                    <div class="field"><label for="reg-ph">Phone (optional)</label><input id="reg-ph" name="phone" type="tel" inputmode="tel" maxlength="32"></div>
                    <div class="field"><label for="reg-ct">City</label><input id="reg-ct" name="city" maxlength="60"></div>
                </div>
                <label class="checkbox-field"><input type="checkbox" name="consent_marketing" value="1"> I agree to receive product care tips and exclusive offers from ELHOE.</label>
                <button type="submit" class="btn btn-primary btn-block">Register & Activate Warranty</button>
                <div id="reg-msg" style="margin-top:10px;font-size:13px"></div>
            </form>
        </div>`;
    }

    function renderQuarantined(data) {
        const supportEmail = (data.support_email || cfg.supportEmail || '').toString();
        return `
        <div class="result result-warning">
            <div class="result-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
            </div>
            <h2 class="result-title">Suspicious Code</h2>
            <p class="result-message">${escapeHtml(data.message || '')}</p>
            ${supportEmail ? `<p style="margin-top:12px"><a href="mailto:${escapeHtml(supportEmail)}" class="btn btn-secondary">Contact Support</a></p>` : ''}
        </div>`;
    }

    function renderInvalid(data) {
        let suggestionsHtml = '';
        if (Array.isArray(data.suggestions) && data.suggestions.length) {
            const buttons = data.suggestions.map((s) => `<button type="button" data-code="${escapeHtml(s)}">${escapeHtml(s)}</button>`).join('');
            suggestionsHtml = `<div class="suggestions"><h4>Did you mean?</h4><div class="suggestions-list">${buttons}</div></div>`;
        }
        return `
        <div class="result result-danger">
            <div class="result-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
            </div>
            <h2 class="result-title">Code Not Recognised</h2>
            <p class="result-message">${escapeHtml(data.message || '')}</p>
            ${suggestionsHtml}
            <p style="font-size:13px;color:var(--color-muted);margin-top:12px">If you believe this is an error, please contact support.</p>
        </div>`;
    }

    function renderMalformed(data) {
        return `
        <div class="result result-warning">
            <div class="result-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            </div>
            <h2 class="result-title">Check the Code</h2>
            <p class="result-message">${escapeHtml(data.message || '')}</p>
        </div>`;
    }

    function renderRateLimited(data) {
        const wait = data.retry_after_seconds || 60;
        return `
        <div class="result result-warning">
            <div class="result-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 7v5l3 3"/></svg>
            </div>
            <h2 class="result-title">Slow Down</h2>
            <p class="result-message">${escapeHtml(data.message || '')} Please wait ${wait} seconds.</p>
        </div>`;
    }

    function renderError(msg) {
        const html = `
        <div class="result result-danger">
            <div class="result-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            </div>
            <h2 class="result-title">Oops</h2>
            <p class="result-message">${escapeHtml(msg)}</p>
        </div>`;
        if (mount) mount.innerHTML = html;
        return html;
    }

    function attachResultHandlers() {
        // Click a "did you mean" suggestion → fill input and re-verify
        $$('.suggestions-list button').forEach((btn) => {
            btn.addEventListener('click', () => {
                const code = btn.getAttribute('data-code') || '';
                if (!code) return;
                codeInput.value = code;
                codeInput.dispatchEvent(new Event('input'));
                form.dispatchEvent(new Event('submit'));
            });
        });

        // Register form
        const regForm = $('#register-form');
        if (regForm) {
            regForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const codeId = parseInt(regForm.getAttribute('data-code-id') || '0', 10);
                if (!codeId) return;
                const fd = new FormData(regForm);
                const payload = {
                    code_id: codeId,
                    first_name: fd.get('first_name') || '',
                    last_name:  fd.get('last_name')  || '',
                    email:      fd.get('email')      || '',
                    phone:      fd.get('phone')      || '',
                    city:       fd.get('city')       || '',
                    consent_marketing: fd.get('consent_marketing') ? 1 : 0
                };
                const submitBtn = regForm.querySelector('button[type=submit]');
                const msgEl = $('#reg-msg', regForm);
                submitBtn.disabled = true;
                msgEl.textContent = 'Registering…';
                msgEl.style.color = 'var(--color-muted)';

                try {
                    const res = await fetch(API + '/register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': cfg.csrfToken || '',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(() => ({}));
                    if (data.ok) {
                        msgEl.textContent = '✓ Registered! Your warranty is active.';
                        msgEl.style.color = 'var(--color-success)';
                        regForm.querySelectorAll('input').forEach(i => i.disabled = true);
                        // Push GA/GTM event for registration
                        try {
                            window.dataLayer = window.dataLayer || [];
                            window.dataLayer.push({
                                event: 'spv_registration',
                                product_name: '',
                                serial_code: ''
                            });
                            if (window.fbq) window.fbq('track', 'Lead', {
                                content_name: 'Product Registration',
                                content_category: 'Registration'
                            });
                        } catch (e) { /* ignore */ }
                    } else {
                        msgEl.textContent = data.message || 'Registration failed.';
                        msgEl.style.color = 'var(--color-danger)';
                        submitBtn.disabled = false;
                    }
                } catch (err) {
                    msgEl.textContent = 'Network error. Please try again.';
                    msgEl.style.color = 'var(--color-danger)';
                    submitBtn.disabled = false;
                }
            });
        }
    }

    function pushAnalytics(data) {
        const result = data.result;
        const product = data.product || {};
        const code = data.code || {};

        try {
            window.dataLayer = window.dataLayer || [];
            if (['valid_universal', 'valid_unique_first', 'valid_unique_returning', 'already_registered'].indexOf(result) >= 0) {
                window.dataLayer.push({
                    event: 'spv_verify_success',
                    product_name: product.name || '',
                    serial_code: code.code || '',
                    scenario: result
                });
                if (window.fbq) window.fbq('track', 'ViewContent', {
                    content_name: product.name || '',
                    content_ids: [product.sku || product.id || ''],
                    content_type: 'product'
                });
            } else if (result === 'invalid' || result === 'quarantined') {
                window.dataLayer.push({
                    event: 'spv_verify_fail',
                    scenario: result === 'quarantined' ? 'quarantined' : 'invalid'
                });
            }
        } catch (e) { /* ignore */ }
    }

    function formatDate(d) {
        if (!d) return '';
        const parts = String(d).split('-');
        if (parts.length !== 3) return d;
        const [y, m, dd] = parts;
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const mi = parseInt(m, 10) - 1;
        return `${parseInt(dd,10)} ${months[mi] || m} ${y}`;
    }

    // Register service worker (PWA) - use a path-relative URL so it works
    // on both elhoe.com and www.elhoe.com without CORS issues.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swPath = (cfg.apiBase || '/checker/api').replace(/\/api$/, '') + '/service-worker.js';
            navigator.serviceWorker.register(swPath).catch(() => {});
        });
    }
})();

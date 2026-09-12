/* Same-origin signup only. No contact data is written to browser storage. */
(() => {
    'use strict';
    const forms = () => Array.from(document.querySelectorAll('.ba-newsletter-form'));
    window.bactiveNewsletterReady = () => {
        forms().forEach((form) => {
            const target = form.querySelector('.ba-newsletter-captcha');
            if (!target || target.dataset.widgetId !== undefined) return;
            const compact = target.clientWidth < 300;
            target.style.minHeight = compact ? '140px' : '65px';
            target.dataset.widgetId = window.turnstile.render(target, {
                size: compact ? 'compact' : 'flexible',
                sitekey: target.dataset.sitekey,
                action: 'newsletter',
                'error-callback': () => {
                    form.querySelector('.ba-newsletter-status').textContent = 'The security check could not load. Refresh this page to try again.';
                }
            });
        });
    };
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('.ba-newsletter-form')) return;
        event.preventDefault();
        if (form.dataset.pending === 'true') return;
        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector('.ba-newsletter-status');
        const data = new FormData(form);
        if (!data.get('cf-turnstile-response')) {
            status.textContent = 'Complete the security check before joining.';
            return;
        }
        form.dataset.pending = 'true';
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        button.textContent = 'Joining…';
        status.textContent = '';
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 25000);
        try {
            // Cached storefront HTML can outlive a WordPress nonce.
            const nonceResponse = await fetch(form.dataset.nonceUrl, {
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal
            });
            const nonce = await nonceResponse.json();
            if (!nonceResponse.ok || typeof nonce.nonce !== 'string') throw new Error('Nonce unavailable');
            data.set('ba_signup_nonce', nonce.nonce);
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', body: data,
                headers: {Accept: 'application/json'}, signal: controller.signal
            });
            const result = await response.json();
            if (typeof result.message !== 'string') throw new Error('Invalid response');
            status.textContent = result.message;
            if (response.ok && result.ok === true) form.reset();
        } catch (_) {
            status.textContent = 'We could not confirm your signup request. Check your inbox first; if no confirmation arrives, try again later.';
        } finally {
            window.clearTimeout(timeout);
            const target = form.querySelector('.ba-newsletter-captcha');
            if (window.turnstile && target?.dataset.widgetId !== undefined) window.turnstile.reset(target.dataset.widgetId);
            delete form.dataset.pending;
            form.removeAttribute('aria-busy');
            button.disabled = false;
            button.textContent = 'Join the club';
        }
    });
})();

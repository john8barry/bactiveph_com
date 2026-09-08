/* Native details preserve navigation when scripting is unavailable. */
(() => {
    'use strict';
    const header = document.querySelector('#header.bactive-header--sage');
    if (!header) return;
    const disclosures = Array.from(header.querySelectorAll('.bactive-header__disclosure'));
    const mobileMenu = header.querySelector('.bactive-header__mobile-menu');
    const close = (details, returnFocus = false) => {
        if (!details || !details.open) return;
        details.open = false;
        if (returnFocus) details.querySelector('summary').focus();
    };
    disclosures.forEach(details => {
        details.addEventListener('toggle', () => {
            if (!details.open) return;
            disclosures.forEach(other => { if (other !== details) close(other); });
        });
        details.addEventListener('focusout', event => {
            if (event.relatedTarget && !details.contains(event.relatedTarget)) close(details);
        });
    });
    document.addEventListener('click', event => {
        disclosures.forEach(details => { if (!details.contains(event.target)) close(details); });
    });
    header.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const active = mobileMenu && mobileMenu.open && mobileMenu.contains(event.target)
            ? mobileMenu : event.target.closest('details[open]');
        if (active && header.contains(active)) {
            close(active, true);
            event.preventDefault();
            event.stopPropagation();
        }
    });
    const desktop = window.matchMedia('(min-width: 1000px)');
    desktop.addEventListener('change', () => {
        const focused = header.contains(document.activeElement);
        disclosures.forEach(details => close(details));
        close(mobileMenu);
        if (focused) {
            const target = header.querySelector(desktop.matches ? '.bactive-header__desktop .site-logo-container' : '.bactive-header__menu-toggle');
            if (target) target.focus();
        }
    });
})();

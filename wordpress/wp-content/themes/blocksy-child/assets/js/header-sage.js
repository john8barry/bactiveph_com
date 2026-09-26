/* Native details preserve navigation when scripting is unavailable. */
(() => {
    'use strict';
    const header = document.querySelector('#header.bactive-header--sage');
    if (!header || header.dataset.stickyInitialized) return;
    header.dataset.stickyInitialized = 'true';
    // Keep Blocksy's home link and accessible name; enhance only loaded marks.
    const scriptURL = document.currentScript && document.currentScript.src;
    if (scriptURL) {
        const markURL = new URL('../images/header-sage-mark.png', scriptURL).href;
        header.querySelectorAll('.site-logo-container').forEach(brand => {
            if (!brand.querySelector('img')) return;
            const mark = document.createElement('img');
            mark.className = 'bactive-header__compact-mark';
            mark.alt = '';
            mark.setAttribute('aria-hidden', 'true');
            mark.width = 1024;
            mark.height = 1024;
            mark.decoding = 'async';
            mark.addEventListener('load', () => {
                brand.classList.toggle('bactive-header__mark-ready', mark.naturalWidth > 0);
            });
            mark.addEventListener('error', () => brand.classList.remove('bactive-header__mark-ready'));
            brand.append(mark);
            mark.src = markURL;
        });
    }
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
        const active = event.target.closest('details[open]');
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

    // The existing body overflow rules make a viewport-fixed bar more reliable
    // than sticky positioning. The spacer never follows the compact height.
    const spacer = document.createElement('div');
    spacer.className = 'bactive-header-spacer';
    spacer.setAttribute('aria-hidden', 'true');
    header.before(spacer);
    const root = document.documentElement;
    const adminBar = document.getElementById('wpadminbar');
    let compact = false;
    let keyboardInteraction = true;
    let frame = 0;
    const setPixel = (element, property, value) => {
        const next = `${Math.round(value)}px`;
        if (element.style.getPropertyValue(property) !== next) element.style.setProperty(property, next);
    };
    const locked = () => {
        const visible = header.querySelector(desktop.matches ? '.bactive-header__desktop' : '.bactive-header__mobile');
        return Boolean(keyboardInteraction && visible && visible.contains(document.activeElement)) ||
            (desktop.matches ? disclosures.some(details => details.open) : Boolean(mobileMenu && mobileMenu.open));
    };
    const update = () => {
        frame = 0;
        // Use layout height: a software keyboard must not switch to normal flow.
        const enabled = window.innerHeight >= 240;
        header.classList.toggle('bactive-header--fixed', enabled);
        root.classList.toggle('bactive-header-sticky', enabled);
        spacer.hidden = !enabled;
        if (!enabled) {
            compact = false;
            header.classList.remove('bactive-header--compact');
            root.style.removeProperty('--bactive-header-clearance');
            return;
        }
        const offset = adminBar ? Math.max(0, adminBar.getBoundingClientRect().bottom) : 0;
        setPixel(header, '--header-admin-offset', offset);
        if (!locked()) {
            const y = Math.max(0, window.scrollY);
            if (y > 72) compact = true;
            else if (y <= 16) compact = false;
        }
        header.classList.toggle('bactive-header--compact', compact);
        const bottom = header.getBoundingClientRect().bottom;
        const viewport = window.visualViewport;
        const viewportBottom = viewport ? viewport.offsetTop + viewport.height : window.innerHeight;
        // Include dropdown offsets and a breathing gap above the viewport edge.
        setPixel(header, '--header-panel-space', Math.max(0, viewportBottom - bottom - 24));
        setPixel(root, '--bactive-header-clearance', bottom + 12);
    };
    const schedule = () => {
        if (!frame) frame = window.requestAnimationFrame(update);
    };
    // Touch/pointer focus may remain on a closed menu. Only keyboard focus
    // should hold its size; never blur the control or change native behavior.
    document.addEventListener('pointerdown', () => {
        keyboardInteraction = false;
        schedule();
    }, {capture: true, passive: true});
    document.addEventListener('keydown', event => {
        if (event.metaKey || event.ctrlKey || event.altKey) return;
        keyboardInteraction = true;
        schedule();
    }, true);
    window.addEventListener('scroll', schedule, {passive: true});
    window.addEventListener('resize', schedule);
    window.addEventListener('pageshow', schedule);
    desktop.addEventListener('change', schedule);
    header.addEventListener('toggle', schedule, true);
    header.addEventListener('focusin', schedule);
    header.addEventListener('focusout', schedule);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', schedule);
        window.visualViewport.addEventListener('scroll', schedule);
    }
    if (window.ResizeObserver) {
        const observer = new ResizeObserver(schedule);
        observer.observe(header);
        if (adminBar) observer.observe(adminBar);
    }
    // Scroll-padding covers anchors and ordinary focus scrolling. Also protect
    // backwards tabbing to controls already positioned underneath the bar.
    document.addEventListener('focusin', event => {
        if (header.contains(event.target)) return;
        window.requestAnimationFrame(() => {
            if (!header.classList.contains('bactive-header--fixed') ||
                event.target !== document.activeElement || !event.target.getBoundingClientRect) return;
            const rect = event.target.getBoundingClientRect();
            const clearance = header.getBoundingClientRect().bottom + 12;
            if (rect.height && rect.top < clearance && rect.bottom > 0) {
                window.scrollBy({top: rect.top - clearance, behavior: 'instant'});
            }
        });
    });
    update();
})();

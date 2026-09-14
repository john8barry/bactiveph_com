/* Native WooCommerce selects and events own matching, AJAX, stock, price and cart. */
(function ($) {
    'use strict';
    if (!$) return;
    const config = window.bactiveCatalogVisuals;
    if (!config || config.schemaVersion !== 1 || !Number.isInteger(config.productId) ||
        config.productId < 1 || typeof config.version !== 'string' ||
        !/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/.test(config.version)) return;

    // Blocksy's default gallery otherwise clicks a captured thumbnail after a
    // fixed 500ms, even when its lazy slider has not finished mounting. Delay
    // only that native image operation; Woo still resolves price/stock/cart now.
    function prepareGallery(form, product, cleanup, failed) {
        const variations = $(form).data('product_variations');
        if (!Array.isArray(variations) || !variations.length ||
            !variations.every(item => item.blocksy_gallery_source === 'default')) return;
        let active = true;
        let sequence = 0;
        let wrapper;
        let original;
        const pendingMounts = new WeakMap();
        const mountWaiters = new Set();
        const ready = slider => slider.flexy && !String(slider.dataset.flexy || '').includes('no');
        function awaitFirstRender(slider) {
            if (ready(slider)) return Promise.resolve();
            if (!slider.flexy) return Promise.reject(new Error('Gallery did not mount'));
            // Flexy assigns its instance before its first animation-frame draw.
            // Wait for the native readiness attribute, rather than treating that
            // ordinary intermediate state as an enhancement failure.
            return new Promise((resolve, reject) => {
                let observer;
                let timer;
                const finish = error => {
                    observer.disconnect();
                    window.clearTimeout(timer);
                    mountWaiters.delete(finish);
                    if (error) reject(error); else resolve();
                };
                observer = new MutationObserver(() => { if (ready(slider)) finish(); });
                mountWaiters.add(finish);
                observer.observe(slider, {attributes: true, attributeFilter: ['data-flexy']});
                // A failure deadline only: successful native rendering proceeds
                // immediately, with no polling or delayed selection replay.
                timer = window.setTimeout(() => finish(new Error('Gallery did not render')), 5000);
                if (ready(slider)) finish();
            });
        }
        const attributes = () => JSON.stringify([...form.querySelectorAll('.variations select')]
            .map(select => [select.name, select.value]));
        const cancel = () => { sequence++; };
        function manual(event) {
            if (event.isTrusted && event.target.closest('.woocommerce-product-gallery')) cancel();
        }
        function install() {
            if (!active || typeof $.fn.wc_variations_image_update !== 'function' ||
                $.fn.wc_variations_image_update === wrapper) return;
            original = $.fn.wc_variations_image_update;
            const nativeUpdate = original;
            wrapper = function (variation) {
                if (!active || this[0] !== form) return nativeUpdate.apply(this, arguments);
                const generation = ++sequence;
                const slider = product.querySelector('.woocommerce-product-gallery .flexy-container');
                const context = this;
                const args = arguments;
                const chosen = attributes();
                const expectedId = variation && variation.variation_id ? String(variation.variation_id) : '';
                const current = () => active && generation === sequence && form.isConnected &&
                    slider && slider.isConnected &&
                    product.querySelector('.woocommerce-product-gallery .flexy-container') === slider &&
                    attributes() === chosen;
                function applyNative() {
                    const result = nativeUpdate.apply(context, args);
                    if (!expectedId || !slider || !window.requestAnimationFrame) return result;
                    // Native same-image short-circuiting can leave a manually
                    // chosen slide visible. Reconcile once after native commit,
                    // using fresh nodes; a subsequent manual action always wins.
                    window.requestAnimationFrame(() => {
                        if (!current() || !slider.flexy) return;
                        const nativeId = form.querySelector('input[name="variation_id"], input.variation_id');
                        if (!nativeId || nativeId.value !== expectedId) return;
                        const image = variation.image && variation.image.src ? variation.image : variation.blocksy_original_image;
                        if (!image) return;
                        const normalized = value => {
                            try { return value ? new URL(value, document.baseURI).href : ''; } catch (_) { return ''; }
                        };
                        const urls = [image.src, image.full_src].map(normalized).filter(Boolean);
                        const items = slider.querySelector('.flexy-items');
                        const pills = slider.querySelector('.flexy-pills > ol');
                        if (!urls.length || !items || !pills) return;
                        const matches = [...items.children].map((item, index) => {
                            const img = item.querySelector('img:not(.zoomImg)');
                            return img && [img.getAttribute('src'), img.currentSrc,
                                img.parentElement.getAttribute('data-src')].map(normalized)
                                .some(url => urls.includes(url)) ? index : -1;
                        }).filter(index => index >= 0);
                        if (matches.length !== 1) return;
                        const pill = pills.children[matches[0]];
                        if (pill && !pill.classList.contains('active')) pill.click();
                    });
                    return result;
                }
                if (!slider || !String(slider.dataset.flexy || '').includes('no') ||
                    (!slider.flexy && typeof slider.forcedMount !== 'function')) return applyNative();
                let mounted = pendingMounts.get(slider);
                if (!mounted) {
                    mounted = Promise.resolve().then(() => {
                        if (!slider.flexy) return slider.forcedMount();
                    }).then(() => awaitFirstRender(slider));
                    pendingMounts.set(slider, mounted);
                }
                mounted.then(() => {
                    if (!current()) return;
                    const nativeId = form.querySelector('input[name="variation_id"], input.variation_id');
                    if (expectedId && (!nativeId || nativeId.value !== expectedId)) return;
                    if (!ready(slider)) return failed();
                    applyNative();
                }).catch(() => { if (active && generation === sequence) failed(); });
                return this;
            };
            $.fn.wc_variations_image_update = wrapper;
        }
        // This Woo event runs before image resolution, including initial
        // defaults. Reinstall if Blocksy loaded its own handler lazily since then.
        $(form).on('woocommerce_update_variation_values.bactiveGallery', install);
        // Woo's reset_data handler calls this wrapper with false via reset_image.
        // That new request cancels the previous variation and must remain live
        // until Flexy's first render can restore the original product image.
        ['pointerdown', 'touchstart', 'keydown', 'click'].forEach(name =>
            product.addEventListener(name, manual, true));
        install();
        cleanup.push(() => {
            active = false; cancel();
            [...mountWaiters].forEach(finish => finish());
            $(form).off('.bactiveGallery');
            ['pointerdown', 'touchstart', 'keydown', 'click'].forEach(name =>
                product.removeEventListener(name, manual, true));
            if ($.fn.wc_variations_image_update === wrapper) $.fn.wc_variations_image_update = original;
        });
    }

    function enhance(form) {
        if (form.dataset.bactiveSelectors || Number(form.dataset.product_id) !== config.productId) return;
        const selects = [...form.querySelectorAll('.variations select')].filter(select =>
            ['attribute_pa_size', 'attribute_pa_colour', 'attribute_pa_color'].includes(select.name));
        if (!selects.length) return;
        const groups = [];
        const $form = $(form);
        const notice = document.createElement('p');
        notice.className = 'bactive-selector-status';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');
        const fallback = document.createElement('button');
        fallback.type = 'button';
        fallback.className = 'bactive-selector-fallback';
        fallback.textContent = 'Use dropdowns';
        let observer;
        let active = true;
        let invalidated = false;
        const layoutCleanup = [];
        function restore() {
            active = false;
            if (observer) observer.disconnect();
            $form.off('.bactiveSelectors');
            groups.forEach(({select, group, hidden}) => { select.hidden = hidden; group.remove(); });
            layoutCleanup.reverse().forEach(cleanup => cleanup());
            notice.remove(); fallback.remove();
            form.classList.remove('bactive-catalog-selectors');
            form.dataset.bactiveSelectors = 'fallback';
        }
        function sync() {
            if (!active) return;
            try {
                groups.forEach(entry => {
                    const {select, buttons} = entry;
                    const previous = [...select.options].find(item => item.value === entry.previous);
                    if (entry.previous && !select.value && (!previous || previous.disabled)) invalidated = true;
                    entry.previous = select.value;
                    buttons.forEach((button, value) => {
                        const option = [...select.options].find(item => item.value === value);
                        button.disabled = select.disabled || !option || option.disabled;
                        button.setAttribute('aria-pressed', String(select.value === value));
                    });
                });
            } catch (_) { restore(); }
        }
        function status(message) { notice.textContent = message; sync(); }
        try {
            selects.forEach(select => {
                const group = document.createElement('div');
                group.className = 'bactive-selector-options';
                group.dataset.bactiveKind = select.name === 'attribute_pa_size' ? 'size' : 'colour';
                group.setAttribute('role', 'group');
                const label = select.labels && select.labels[0];
                group.setAttribute('aria-label', label ? label.textContent.trim() :
                    (select.name === 'attribute_pa_size' ? 'Size' : 'Colour'));
                const buttons = new Map();
                // Take the original option universe where Woo has already narrowed the select.
                const source = $(select).data('attribute_options');
                const options = Array.isArray(source) && source.length ? source : [...select.options];
                options.filter(option => option.value).forEach(option => {
                    if (buttons.has(option.value)) return;
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'bactive-selector-option';
                    const hex = config.palette && config.palette[select.name] && config.palette[select.name][option.value];
                    if (typeof hex === 'string' && /^#[a-fA-F0-9]{6}$/.test(hex)) {
                        button.classList.add('bactive-selector-option--colour');
                        const circle = document.createElement('span');
                        circle.className = 'bactive-selector-colour';
                        circle.setAttribute('aria-hidden', 'true');
                        circle.style.backgroundColor = hex;
                        button.append(circle);
                    }
                    const name = document.createElement('span');
                    name.textContent = option.textContent;
                    button.append(name);
                    button.addEventListener('click', () => {
                        try {
                            const current = [...select.options].find(item => item.value === option.value);
                            if (!current || current.disabled || select.disabled) return sync();
                            notice.textContent = '';
                            invalidated = false;
                            select.value = select.value === option.value ? '' : option.value;
                            // The native Woo handler clears the old variation_id before resolving.
                            $(select).trigger('change');
                            sync();
                        } catch (_) { restore(); }
                    });
                    buttons.set(option.value, button); group.append(button);
                });
                if (!buttons.size) return;
                groups.push({select, group, buttons, hidden: select.hidden, previous: select.value});
                select.after(group);
            });
            if (!groups.length) return;
            // Reorder the real rows, preserving DOM focus order and native selects.
            const size = selects.find(select => select.name === 'attribute_pa_size');
            const colour = selects.find(select => select.name !== 'attribute_pa_size');
            const sizeRow = size && size.closest('tr');
            const colourRow = colour && colour.closest('tr');
            if (sizeRow && colourRow && sizeRow.parentNode === colourRow.parentNode) {
                const marker = document.createComment('original-colour-row');
                colourRow.before(marker);
                sizeRow.before(colourRow);
                layoutCleanup.push(() => { marker.before(colourRow); marker.remove(); });
            }
            // Retain every word of the original description in an accessible disclosure.
            const summary = form.closest('.summary');
            const description = summary && summary.querySelector(':scope > .woocommerce-product-details__short-description');
            if (description && !summary.querySelector('.bactive-product-details')) {
                const marker = document.createComment('original-product-description');
                const details = document.createElement('details');
                details.className = 'bactive-product-details';
                const heading = document.createElement('summary');
                heading.textContent = 'Details & fit';
                description.before(marker);
                details.append(heading, description);
                (form.closest('.ct-product-add-to-cart') || form).after(details);
                layoutCleanup.push(() => { marker.before(description); marker.remove(); details.remove(); });
            }
            // Blocksy owns gallery slides and variation image replacement. Give its
            // existing thumbnail click targets equivalent keyboard operation.
            const product = form.closest('.product');
            if (product) {
                prepareGallery(form, product, layoutCleanup, restore);
                const original = new Map();
                const attributes = ['role', 'tabindex', 'aria-pressed'];
                function thumbnails() {
                    product.querySelectorAll('.woocommerce-product-gallery .flexy-pills li > span').forEach(target => {
                        if (!original.has(target)) original.set(target, attributes.map(name => target.getAttribute(name)));
                        target.setAttribute('role', 'button');
                        target.setAttribute('tabindex', '0');
                        target.setAttribute('aria-pressed', String(target.parentElement.classList.contains('active')));
                    });
                }
                function thumbnailKey(event) {
                    if (!['Enter', ' '].includes(event.key) || event.defaultPrevented) return;
                    const target = event.target.closest('.woocommerce-product-gallery .flexy-pills li > span');
                    if (!target || !product.contains(target)) return;
                    event.preventDefault();
                    target.click();
                }
                const galleryObserver = new MutationObserver(() => { try { thumbnails(); } catch (_) { restore(); } });
                layoutCleanup.push(() => {
                    galleryObserver.disconnect();
                    product.removeEventListener('keydown', thumbnailKey);
                    original.forEach((values, target) => attributes.forEach((name, index) => {
                        if (values[index] === null) target.removeAttribute(name);
                        else target.setAttribute(name, values[index]);
                    }));
                });
                thumbnails();
                product.addEventListener('keydown', thumbnailKey);
                galleryObserver.observe(product, {childList: true, subtree: true, attributes: true, attributeFilter: ['class']});
            }
            form.querySelector('.variations').after(notice, fallback);
            fallback.addEventListener('click', () => { restore(); selects[0].focus(); });
            $form.on('woocommerce_update_variation_values.bactiveSelectors woocommerce_variation_has_changed.bactiveSelectors', sync);
            $form.on('found_variation.bactiveSelectors', function () { invalidated = false; status(''); });
            $form.on('reset_data.bactiveSelectors', function () {
                sync();
                const complete = [...form.querySelectorAll('.variations select')].every(select => select.value);
                status(complete ? 'This combination is unavailable. Choose another size or colour.' :
                    (invalidated ? 'A choice is no longer available. Choose another size or colour.' : ''));
                invalidated = false;
            });
            $form.on('change.bactiveSelectors', '.variations select', sync);
            observer = new MutationObserver(sync);
            selects.forEach(select => observer.observe(select, {childList: true, subtree: true, attributes: true,
                attributeFilter: ['disabled', 'selected']}));
            sync();
            groups.forEach(({select}) => { select.hidden = true; });
            form.classList.add('bactive-catalog-selectors');
            form.dataset.bactiveSelectors = 'ready';
        } catch (_) { restore(); }
    }
    $(document).on('wc_variation_form.bactiveSelectors', '.variations_form', function () { enhance(this); });
    $(function () { $('.variations_form').each(function () { enhance(this); }); });
})(window.jQuery);

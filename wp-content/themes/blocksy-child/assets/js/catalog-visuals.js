/* Native WooCommerce selects and events own matching, AJAX, stock, price and cart. */
(function ($) {
    'use strict';
    if (!$) return;
    const config = window.bactiveCatalogVisuals;
    if (!config || config.schemaVersion !== 1 || !Number.isInteger(config.productId) ||
        config.productId < 1 || typeof config.version !== 'string' ||
        !/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/.test(config.version)) return;

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
        function restore() {
            active = false;
            if (observer) observer.disconnect();
            $form.off('.bactiveSelectors');
            groups.forEach(({select, group, hidden}) => { select.hidden = hidden; group.remove(); });
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

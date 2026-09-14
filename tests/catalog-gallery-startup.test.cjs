/*
 * Actual Woo variation engine and Blocksy variable-products.js; only the lazy
 * Flexy mount/slide driver is simulated. This proves event/timer ordering, not
 * browser geometry, trusted-input delivery, or production behavior.
 *
 * NODE_PATH=<catalogue-runtime/node_modules> node --test tests/catalog-gallery-startup.test.cjs
 * CATALOG_GALLERY_CHILD_PATH may point to the predecessor to prove regressions.
 * WC_VARIATION_ENGINE_PATH and BLOCKSY_VARIATION_ENGINE_PATH support the exact
 * private restore sources. The incumbent Blocksy source matched fresh restore
 * SHA256 9a4edaff389fcfe0d5e4d0d006e0d4248bd282a99c7b3e180b61ec0c861866d4.
 */
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {JSDOM} = require('jsdom');

const source = path => readFileSync(path, 'utf8');
const wordpress = require('node:path').resolve(__dirname, '../wordpress');
const jquery = source(`${wordpress}/wp-includes/js/jquery/jquery.js`);
const underscore = source(`${wordpress}/wp-includes/js/underscore.js`);
const wpUtil = source(`${wordpress}/wp-includes/js/wp-util.js`);
const woo = source(process.env.WC_VARIATION_ENGINE_PATH || `${wordpress}/wp-content/plugins/woocommerce/assets/js/frontend/add-to-cart-variation.js`);
const parent = source(process.env.BLOCKSY_VARIATION_ENGINE_PATH || `${wordpress}/wp-content/themes/blocksy/static/js/frontend/woocommerce/variable-products.js`);
const child = source(process.env.CATALOG_GALLERY_CHILD_PATH || `${wordpress}/wp-content/themes/blocksy-child/assets/js/catalog-visuals.js`);
const colourPhoto = source(`${wordpress}/wp-content/themes/blocksy-child/assets/js/catalogue-colour-photo.js`);
const layout = source(`${wordpress}/wp-content/themes/blocksy-child/assets/js/catalogue-layout.js`);
const parentScript = parent.replace(/^import .*\n/gm, '').replace('export const mount =', 'window.mountBlocksyVariableProducts =');
assert.match(parentScript, /window\.mountBlocksyVariableProducts/);
assert.doesNotMatch(parentScript, /^import /m);
const flush = async () => { await Promise.resolve(); await new Promise(setImmediate); };

function clock(window) {
    let time = 0, id = 0;
    const tasks = new Map();
    window.setTimeout = (callback, delay = 0, ...args) => {
        const key = ++id;
        tasks.set(key, {at: time + Math.max(0, Number(delay) || 0), callback, args});
        return key;
    };
    window.clearTimeout = key => tasks.delete(key);
    window.requestAnimationFrame = callback => window.setTimeout(() => callback(time), 16);
    window.cancelAnimationFrame = window.clearTimeout;
    return {
        async tick(duration = 0) {
            const until = time + duration;
            await flush();
            let count = 0;
            while (true) {
                const entry = [...tasks].filter(([, task]) => task.at <= until)
                    .sort((a, b) => a[1].at - b[1].at || a[0] - b[0])[0];
                if (!entry) break;
                assert.ok(++count < 2000, 'timer loop must remain bounded');
                tasks.delete(entry[0]); time = entry[1].at;
                entry[1].callback(...entry[1].args);
                await flush();
            }
            time = until;
            await flush();
        }
    };
}

async function fixture({patched = true, ajax = false, custom = false, defaults = false, offGallery = false, integrated = false, productId = 117} = {}) {
    const dom = new JSDOM(`<div id="product-${productId}" class="product type-product product-entry-wrapper">
      <div class="woocommerce-product-gallery"><div class="ct-product-gallery-container"><div class="flexy-container" data-flexy="no">
        <div class="flexy"><div class="flexy-view"><div class="flexy-items"></div></div>
        <button class="flexy-arrow-prev">Previous</button><button class="flexy-arrow-next">Next</button></div>
        <div class="flexy-pills"><ol></ol></div></div></div></div>
      <div class="summary"><form class="variations_form" data-product_id="${productId}">
        <table class="variations"><tr><th><label for="colour">Colour</label></th><td><select id="colour" name="attribute_pa_colour" data-attribute_name="attribute_pa_colour"><option value="">Choose</option><option value="white">White</option><option value="jujube-red">Jujube Red</option><option value="navy">Navy</option></select></td></tr>
        <tr><th><label for="size">Size</label></th><td><select id="size" name="attribute_pa_size" data-attribute_name="attribute_pa_size"><option value="">Choose</option><option value="l">L</option></select></td></tr></table>
        <a class="reset_variations" href="#">Clear</a><div class="reset_variations_alert"></div>
        <div class="single_variation_wrap"><div class="single_variation"></div><input name="variation_id" value="">
        <div class="woocommerce-variation-add-to-cart"><input name="quantity" type="number" value="1"><button class="single_add_to_cart_button">Add to cart</button></div></div>
      </form></div></div>
      <script type="text/template" id="tmpl-variation-template"><div>{{{ data.variation.price_html }}}</div></script>
      <script type="text/template" id="tmpl-unavailable-variation-template"><div>Unavailable</div></script>`,
    {runScripts: 'outside-only', url: 'https://example.test/product/test/', pretendToBeVisual: true});
    const w = dom.window, timer = clock(w);
    w.eval(jquery); const $ = w.jQuery;
    $.fx.off = true;
    w.eval(underscore); w.eval(wpUtil);
    w.bactiveCatalogVisuals = {schemaVersion: 1, version: 'startup-test', productId, palette: {}};
    const pendingPhotos = [];
    if (integrated) {
        w.document.body.classList.add('bactive-product-page');
        w.bactiveCatalogVisuals.previews = {attribute_pa_colour: {
            'jujube-red': {src: 'https://example.test/red-representative.jpg', alt: 'Red representative'},
            white: {src: 'https://example.test/white-representative.jpg', alt: 'White representative'}
        }};
        w.Image = function () {
            const image = w.document.createElement('img');
            Object.defineProperty(image, 'naturalWidth', {value: 1200});
            Object.defineProperty(image, 'naturalHeight', {value: 1600});
            pendingPhotos.push(image); return image;
        };
    }
    w.wc_add_to_cart_variation_params = {wc_ajax_url: 'https://example.test/?wc-ajax=%%endpoint%%', i18n_no_matching_variations_text: 'No match', i18n_make_a_selection_text: 'Select', i18n_unavailable_text: 'Unavailable'};
    w.ct_localizations = {ajax_url: 'https://example.test/wp-admin/admin-ajax.php'};
    $.fn.block = $.fn.unblock = function () { return this; };
    const form = w.document.querySelector('form'), $form = $(form), product = form.closest('.product');
    const gallery = product.querySelector('.woocommerce-product-gallery'), slider = gallery.querySelector('.flexy-container');
    const items = slider.querySelector('.flexy-items'), pills = slider.querySelector('.flexy-pills ol');
    const image = (name, id) => ({id, src: `https://example.test/${name}.jpg`, full_src: `https://example.test/${name}.jpg`, gallery_thumbnail_src: `https://example.test/${name}-thumb.jpg`, width: 1200, height: 1600, full_src_w: 1200, full_src_h: 1600, srcset: ''});
    const images = [image('white', 543), image('red', 390), image('navy', 391)];
    images.forEach((photo, index) => {
        items.insertAdjacentHTML('beforeend', `<div><figure class="ct-media-container" data-src="${photo.src}" data-width="1200" data-height="1600"><img src="${photo.src}" width="1200" height="1600"></figure></div>`);
        pills.insertAdjacentHTML('beforeend', `<li${index ? '' : ' class="active"'}><span aria-label="Slide ${index + 1}"><img src="${photo.gallery_thumbnail_src}"></span></li>`);
    });
    const variations = ['white', 'jujube-red', 'navy'].map((colour, index) => ({
        variation_id: 125 + index, attributes: {attribute_pa_colour: colour, attribute_pa_size: 'l'},
        image_id: images[index].id, image: {...images[index]}, blocksy_original_image: {...images[0]},
        blocksy_gallery_source: custom ? 'custom' : 'default',
        is_in_stock: true, is_purchasable: true, variation_is_visible: true, variation_is_active: true,
        min_qty: 1, max_qty: 3, price_html: `<span class="price">Native ${125 + index}</span>`
    }));
    if (offGallery) {
        variations[1].image_id = 989;
        variations[1].image = image('red-other-model', 989);
    }
    form.dataset.product_variations = JSON.stringify(ajax ? false : variations);
    $form.data('product_variations', ajax ? false : variations);
    const payloadBefore = JSON.stringify(variations), requests = [], fetches = [], nativeCalls = [], clicked = [];
    $.ajax = options => { requests.push(options); return {abort() {}}; };
    w.ctEvents = {trigger() {}};
    w.cachedFetch = url => { fetches.push(url); return Promise.resolve({json: () => Promise.resolve({success: false})}); };
    // Record only handlers registered by the child. jsdom cannot create trusted
    // browser events, so the manual-input test invokes those exact callbacks
    // with a trusted event-shaped object. Real trusted delivery needs a browser.
    const gestureHandlers = [];
    const originalAdd = product.addEventListener.bind(product);
    const originalRemove = product.removeEventListener.bind(product);
    product.addEventListener = (type, handler, options) => {
        gestureHandlers.push({type, handler, options}); originalAdd(type, handler, options);
    };
    product.removeEventListener = (type, handler, options) => {
        const index = gestureHandlers.findIndex(row => row.type === type && row.handler === handler);
        if (index >= 0) gestureHandlers.splice(index, 1);
        originalRemove(type, handler, options);
    };
    let resolveMount, rejectMount, mountCalls = 0, installed = false;
    const mountPromise = new w.Promise((resolve, reject) => { resolveMount = resolve; rejectMount = reject; });
    slider.forcedMount = () => { mountCalls++; return mountPromise; };
    function commitMount() {
        if (!installed) {
            installed = true; slider.flexy = {}; slider.dataset.flexy = '';
            [...pills.children].forEach((pill, index) => pill.addEventListener('click', event => {
                // Flexy's real pill handler also commits on a subsequent task.
                w.setTimeout(() => {
                    if (event.defaultPrevented) return;
                    pills.querySelector('.active')?.classList.remove('active');
                    pill.classList.add('active'); clicked.push(index); event.preventDefault();
                });
            }));
        }
        resolveMount(slider.flexy);
    }
    if (defaults) {
        $form.find('[name=attribute_pa_colour]').val('jujube-red');
        $form.find('[name=attribute_pa_size]').val('l');
    }
    w.eval(woo);
    if (integrated) w.eval(layout);
    if (patched) w.eval(child);
    if (integrated) w.eval(colourPhoto);
    await timer.tick(0); // Ready callbacks create Woo and child form handlers.
    // The parent installs lazily AFTER the child; the wrapper must reinstall on
    // Woo's synchronous update event before found_variation/default matching.
    w.eval(`(function ($, ctEvents, cachedFetch) { ${parentScript}\n})(window.jQuery, window.ctEvents, window.cachedFetch);`);
    w.mountBlocksyVariableProducts(form);
    const native = $.fn.wc_variations_image_update;
    $.fn.wc_variations_image_update = function () {
        nativeCalls.push({context: this, args: [...arguments]});
        return native.apply(this, arguments);
    };
    await timer.tick(100);
    nativeCalls.length = 0;
    return {
        w, $, form, $form, product, gallery, slider, variations, nativeCalls, clicked, requests, fetches, pendingPhotos,
        tick: timer.tick, mount: async () => { commitMount(); await timer.tick(0); },
        reject: async () => { rejectMount(new Error('synthetic mount failure')); await timer.tick(0); },
        mountCalls: () => mountCalls,
        active: () => [...pills.children].findIndex(pill => pill.classList.contains('active')),
        choose(colour, size = 'l') {
            $form.find('[name=attribute_pa_colour]').val(colour).trigger('change');
            $form.find('[name=attribute_pa_size]').val(size).trigger('change');
        },
        reset() { $form.find('.reset_variations').trigger('click'); },
        manual(type = 'pointerdown', options = {}) {
            const event = {isTrusted: true, target: options.target || pills.children[2], type, key: options.key || 'ArrowRight', defaultPrevented: false,
                preventDefault() { this.defaultPrevented = true; }, stopPropagation() {}, stopImmediatePropagation() { this.stopped = true; }};
            const capture = row => row.options === true || row.options?.capture;
            gestureHandlers.filter(row => row.type === type).sort((a, b) => Number(!!capture(b)) - Number(!!capture(a)))
                .forEach(row => { if (!event.stopped) row.handler.call(product, event); });
            return event;
        },
        click(index) { pills.children[index].click(); },
        id: () => form.querySelector('[name=variation_id]').value,
        intact() { assert.equal(JSON.stringify(variations), payloadBefore, 'native variation objects must not change'); },
        close() { dom.window.close(); }
    };
}

test('unpatched Blocksy reproduces first-selection mismatch with slow lazy mount', async () => {
    const f = await fixture({patched: false});
    try {
        f.choose('jujube-red'); await f.tick(600);
        assert.equal(f.id(), '126'); assert.equal(f.active(), 0);
        await f.mount(); await f.tick(600);
        assert.equal(f.active(), 0, 'the old 500ms click was lost before listeners existed'); f.intact();
    } finally { f.close(); }
});

test('slow mount keeps native commerce immediate and displays the exact latest variation after mount', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(600);
        assert.equal(f.id(), '126'); assert.match(f.form.querySelector('.single_variation').textContent, /Native 126/);
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0, 'parent must not create its 500ms timer');
        await f.mount(); await f.tick(600);
        assert.equal(f.active(), 1); assert.equal(f.id(), '126');
        assert.deepEqual(f.clicked, [1], 'normal native commit must not receive a duplicate corrective click');
        const last = f.nativeCalls.at(-1); assert.equal(last.args[0], f.variations[1]); assert.equal(last.context[0], f.form);
        f.intact();
    } finally { f.close(); }
});

test('fast A mount then B before 500ms cannot be overridden by an old A timer', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(25); await f.mount();
        assert.equal(f.active(), 1);
        f.choose('navy'); await f.tick(600);
        assert.equal(f.id(), '127'); assert.equal(f.active(), 2); f.intact();
    } finally { f.close(); }
});

test('A to B while mounting executes only the latest complete native payload', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(20); f.choose('navy'); await f.tick(20);
        await f.mount(); await f.tick(600);
        assert.equal(f.active(), 2); assert.equal(f.id(), '127');
        assert.deepEqual(f.nativeCalls.filter(call => call.args[0]?.variation_id).map(call => call.args[0].variation_id), [127]); f.intact();
    } finally { f.close(); }
});

test('initial defaults also reinstall after the parent lazily replaces the global image handler', async () => {
    const f = await fixture({defaults: true});
    try {
        await f.tick(600); assert.equal(f.id(), '126'); await f.mount(); await f.tick(600);
        assert.equal(f.active(), 1); f.intact();
    } finally { f.close(); }
});

test('reset cancels a pending positive image update and mounted reset still restores the original', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(20); f.reset(); await f.tick(20);
        await f.mount(); await f.tick(600);
        assert.equal(f.id(), ''); assert.equal(f.active(), 0);
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0);
        f.choose('navy'); await f.tick(20); assert.equal(f.active(), 2);
        f.reset(); await f.tick(600); assert.equal(f.id(), ''); assert.equal(f.active(), 0); f.intact();
    } finally { f.close(); }
});

test('native first-image replacement and Reset remain intact for a photo outside the original gallery', async () => {
    const f = await fixture({offGallery: true});
    try {
        f.choose('jujube-red'); await f.tick(20); await f.mount(); await f.tick(600);
        const first = () => f.slider.querySelector('.flexy-items img');
        assert.equal(f.id(), '126'); assert.equal(f.active(), 0);
        assert.equal(first().src, 'https://example.test/red-other-model.jpg');
        assert.equal(first().parentElement.dataset.src, first().src);
        f.reset(); await f.tick(600);
        assert.equal(f.id(), ''); assert.equal(first().src, 'https://example.test/white.jpg');
        assert.equal(first().parentElement.dataset.src, first().src); f.intact();
    } finally { f.close(); }
});

test('trusted manual gallery intent cancels pending synchronization without synthetic clicks cancelling new work', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(20); f.manual();
        await f.mount(); f.click(2); await f.tick(600);
        assert.equal(f.active(), 2); assert.equal(f.id(), '126');
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0);
        f.choose('white'); await f.tick(20); assert.equal(f.active(), 0); f.intact();
    } finally { f.close(); }
});

test('manual intent after native same-image return cancels the queued frame correction', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(20); f.manual();
        await f.mount(); f.click(2); await f.tick(20);
        f.choose('white'); f.manual(); await f.tick(100);
        assert.equal(f.id(), '125'); assert.equal(f.active(), 2);
        assert.deepEqual(f.clicked, [2], 'manual browsing must win over the pending correction'); f.intact();
    } finally { f.close(); }
});

test('ambiguous matching photos do not make the correction choose an arbitrary slide', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(20); f.manual();
        await f.mount(); f.click(2); await f.tick(20);
        const items = f.slider.querySelector('.flexy-items');
        const pills = f.slider.querySelector('.flexy-pills ol');
        items.append(items.firstElementChild.cloneNode(true));
        pills.append(pills.firstElementChild.cloneNode(true));
        f.choose('white'); await f.tick(100);
        assert.equal(f.id(), '125'); assert.equal(f.active(), 2);
        assert.deepEqual(f.clicked, [2]); f.intact();
    } finally { f.close(); }
});

test('detached form and replaced slider discard late mount completions', async () => {
    for (const replace of [false, true]) {
        const f = await fixture();
        try {
            f.choose('jujube-red'); await f.tick(20);
            if (replace) f.slider.replaceWith(f.slider.cloneNode(true)); else f.product.remove();
            await f.mount(); await f.tick(600);
            assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0); f.intact();
        } finally { f.close(); }
    }
});

test('mount rejection restores native controls without executing an obsolete image update', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(20); await f.reject(); await f.tick(600);
        assert.equal(f.id(), '126'); assert.equal(f.form.dataset.bactiveSelectors, 'fallback');
        assert.ok([...f.form.querySelectorAll('.variations select')].every(select => !select.hidden));
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0); f.intact();
    } finally { f.close(); }
});

test('explicit dropdown fallback cancels the pending bridge and keeps later native variation selection usable', async () => {
    const f = await fixture();
    try {
        f.choose('jujube-red'); await f.tick(20); f.form.querySelector('.bactive-selector-fallback').click();
        await f.mount(); await f.tick(600);
        assert.equal(f.form.dataset.bactiveSelectors, 'fallback');
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0);
        f.choose('navy'); await f.tick(600);
        assert.equal(f.id(), '127'); assert.equal(f.active(), 2); f.intact();
    } finally { f.close(); }
});

test('native AJAX and custom-gallery products pass through without starting a child mount', async () => {
    for (const options of [{ajax: true}, {custom: true}]) {
        const f = await fixture(options);
        try {
            f.choose('jujube-red');
            if (options.ajax) {
                assert.ok(f.requests.length); f.requests.at(-1).success(f.variations[1]);
            }
            await f.tick(600);
            assert.equal(f.id(), '126'); assert.equal(f.mountCalls(), 0);
            assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 1);
            assert.equal(f.fetches.length, 1); f.intact();
        } finally { f.close(); }
    }
});

test('new-product selector, representative overlay, gallery, keyboard, and fallback share one lifecycle', async () => {
    const f = await fixture({integrated: true, productId: 802});
    const overlay = () => f.gallery.querySelector('.bactive-colour-photo');
    const media = () => [...f.gallery.querySelectorAll('.ct-media-container')];
    try {
        assert.equal(f.form.dataset.bactiveSelectors, 'ready', 'new IDs must not need a JS allowlist');
        f.choose('jujube-red', ''); await f.tick(0);
        f.pendingPhotos.at(-1).onload(); await f.tick(0);
        assert.equal(f.id(), ''); assert.equal(overlay().querySelector('img').src, 'https://example.test/red-representative.jpg');
        assert.ok(media().every(item => item.inert));
        assert.equal(f.slider.querySelector('.flexy-items img').src, 'https://example.test/white.jpg');

        f.choose('jujube-red'); await f.tick(600);
        assert.equal(f.id(), '126'); assert.equal(overlay(), null);
        assert.ok(media().every(item => !item.inert));
        await f.mount(); await f.tick(600);
        assert.equal(f.active(), 1); assert.equal(overlay(), null);
        assert.deepEqual(f.clicked, [1]);

        f.choose('jujube-red', ''); await f.tick(0);
        f.pendingPhotos.at(-1).onload(); await f.tick(0);
        assert.ok(overlay()); assert.equal(f.id(), '');
        const before = f.clicked.length;
        const thumb = f.slider.querySelectorAll('.flexy-pills li > span')[2];
        const key = f.manual('keydown', {key: 'Enter', target: thumb}); await f.tick(30);
        assert.equal(key.defaultPrevented, true);
        assert.equal(f.clicked.length, before + 1, 'shared layout and selector handlers must activate once');
        assert.equal(f.active(), 2); assert.equal(overlay(), null);
        assert.ok(media().every(item => !item.inert));

        f.choose('white', ''); await f.tick(0);
        f.pendingPhotos.at(-1).onload(); await f.tick(0);
        assert.ok(overlay());
        f.form.querySelector('.bactive-selector-fallback').click(); await f.tick(0);
        assert.equal(f.form.dataset.bactiveSelectors, 'fallback'); assert.equal(overlay(), null);
        assert.ok([...f.form.querySelectorAll('.variations select')].every(select => !select.hidden));
        assert.ok(media().every(item => !item.inert));
        f.choose('jujube-red'); await f.tick(600);
        assert.equal(f.id(), '126'); assert.equal(f.active(), 1); assert.equal(overlay(), null); f.intact();
    } finally { f.close(); }
});

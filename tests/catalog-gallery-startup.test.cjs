/*
 * Actual Woo variation engine and Blocksy variable-products.js. Readiness cases
 * also execute the actual bundled Flexy class and parent mount function, with
 * controlled geometry and chunk-load completion. This proves event/timer ordering, not
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
const flexyEngine = source(process.env.BLOCKSY_FLEXY_ENGINE_PATH || `${wordpress}/wp-content/themes/blocksy/static/bundle/71.c54d2d99d2996e1be440.js`);
const flexyMount = source(process.env.BLOCKSY_FLEXY_MOUNT_PATH || `${wordpress}/wp-content/themes/blocksy/static/js/frontend/flexy.js`);
assert.equal(require('node:crypto').createHash('sha256').update(flexyEngine).digest('hex'), 'b0355ac2f1727c55d30c028115c35785797fe521059dc353c9b97fd6f871fcc8', 'review a changed native Flexy implementation');
const parentScript = parent.replace(/^import .*\n/gm, '').replace('export const mount =', 'window.mountBlocksyVariableProducts =');
assert.match(parentScript, /window\.mountBlocksyVariableProducts/);
assert.doesNotMatch(parentScript, /^import /m);
const flush = async () => { await Promise.resolve(); await new Promise(setImmediate); };

function clock(window) {
    let time = 0, id = 0;
    const tasks = new Map();
    let priorityFrames = 0, timerAfterFrame = false, alignFrames = false;
    const schedule = (kind, callback, delay, args) => {
        const key = ++id;
        tasks.set(key, {kind, at: time + Math.max(0, Number(delay) || 0), callback, args});
        return key;
    };
    window.setTimeout = (callback, delay = 0, ...args) => schedule('timer', callback, delay, args);
    window.clearTimeout = key => tasks.delete(key);
    window.requestAnimationFrame = callback => {
        const next = alignFrames && [...tasks.values()].filter(task => task.kind === 'frame' && task.at > time)
            .sort((a, b) => a.at - b.at)[0];
        return schedule('frame', () => callback(time), next ? next.at - time : 16, []);
    };
    window.cancelAnimationFrame = window.clearTimeout;
    return {
        renderBeforeTimers(count) {
            assert.ok(Number.isInteger(count) && count >= 1 && count <= 4);
            priorityFrames = count; alignFrames = true;
        },
        async tick(duration = 0) {
            const until = time + duration;
            await flush();
            let count = 0;
            while (true) {
                const due = [...tasks].filter(([, task]) => task.at <= until)
                    .sort((a, b) => a[1].at - b[1].at || a[0] - b[0]);
                if (priorityFrames && !timerAfterFrame) {
                    const frame = due.find(([, task]) => task.kind === 'frame');
                    if (frame) {
                        // A browser may render before an eligible zero-delay
                        // timer. Deliver a whole rendering opportunity, then one
                        // FIFO timer, without pretending RAF is a timer task.
                        const callbacks = due.filter(([, task]) => task.kind === 'frame' && task.at === frame[1].at);
                        time = Math.max(time, frame[1].at);
                        for (const [key, task] of callbacks) {
                            if (!tasks.has(key)) continue;
                            assert.ok(++count < 2000, 'timer loop must remain bounded');
                            tasks.delete(key); task.callback(...task.args); await flush();
                        }
                        priorityFrames--; timerAfterFrame = true;
                        continue;
                    }
                }
                const entry = (timerAfterFrame && due.find(([, task]) => task.kind === 'timer')) || due[0];
                if (!entry) break;
                timerAfterFrame = false;
                assert.ok(++count < 2000, 'timer loop must remain bounded');
                tasks.delete(entry[0]); time = Math.max(time, entry[1].at);
                entry[1].callback(...entry[1].args);
                await flush();
            }
            time = until;
            await flush();
        }
    };
}

async function fixture({patched = true, ajax = false, custom = false, defaults = false, offGallery = false, nativeFlexy = false, holdNativeFrame = false, initialItem = null, rally = false} = {}) {
    const dom = new JSDOM(`<div id="product-117" class="product type-product">
      <div class="woocommerce-product-gallery"><div class="ct-product-gallery-container"><div class="flexy-container" data-flexy="no">
        <div class="flexy"><div class="flexy-view"><div class="flexy-items"></div></div>
        <button class="flexy-arrow-prev">Previous</button><button class="flexy-arrow-next">Next</button></div>
        <div class="flexy-pills"><ol></ol></div></div></div></div>
      <div class="summary"><form class="variations_form" data-product_id="117">
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
    w.bactiveCatalogVisuals = {schemaVersion: 1, version: 'startup-test', productId: 117, palette: {}};
    w.wc_add_to_cart_variation_params = {wc_ajax_url: 'https://example.test/?wc-ajax=%%endpoint%%', i18n_no_matching_variations_text: 'No match', i18n_make_a_selection_text: 'Select', i18n_unavailable_text: 'Unavailable'};
    w.ct_localizations = {ajax_url: 'https://example.test/wp-admin/admin-ajax.php'};
    $.fn.block = $.fn.unblock = function () { return this; };
    const form = w.document.querySelector('form'), $form = $(form), product = form.closest('.product');
    if (rally) {
        product.id = 'product-50'; form.dataset.product_id = '50'; w.bactiveCatalogVisuals.productId = 50;
        form.querySelector('#colour').innerHTML = '<option value="">Choose</option><option value="beige">Beige</option><option value="mocha">Mocha</option><option value="navy-blue">Navy Blue</option>';
        form.querySelector('#size').innerHTML = '<option value="">Choose</option><option value="m">M</option><option value="l">L</option><option value="xl">XL</option>';
    }
    const gallery = product.querySelector('.woocommerce-product-gallery'), slider = gallery.querySelector('.flexy-container');
    const items = slider.querySelector('.flexy-items'), pills = slider.querySelector('.flexy-pills ol');
    const image = (name, id) => ({id, src: `https://example.test/${name}.jpg`, full_src: `https://example.test/${name}.jpg`, gallery_thumbnail_src: `https://example.test/${name}-thumb.jpg`, width: 1200, height: 1600, full_src_w: 1200, full_src_h: 1600, srcset: ''});
    const images = rally ? [image('rally-navy-original', 607), image('rally-gray', 395), image('rally-mocha', 396), image('rally-beige', 397)] :
        [image('white', 543), image('red', 390), image('navy', 391)];
    if (rally) {
        const hashes = ['5fa0a93a33c945656a8af9dfe84bd03ba64b034a43ac005fa253e82b6d133a61',
            '85398b6dd3012a945dfdb031612b3a441c42d62f6de4844791e5b2c15a298d1d',
            'a52857ccd72121f997cd36f1bcdc7e309e6fe020ecf817ce2f8a4eda32ec110e'];
        images.slice(1).forEach((photo, index) => {
            photo.src = photo.full_src = `https://example.test/lossless/${hashes[index]}.webp`;
        });
    }
    images.forEach((photo, index) => {
        items.insertAdjacentHTML('beforeend', `<div><figure class="ct-media-container" data-src="${photo.src}" data-width="1200" data-height="1600"><img src="${photo.src}" width="1200" height="1600"></figure></div>`);
        pills.insertAdjacentHTML('beforeend', `<li${index ? '' : ' class="active"'}><span aria-label="Slide ${index + 1}"><img src="${photo.gallery_thumbnail_src}"></span></li>`);
    });
    // Rally's reviewed attachment 474 and fourth gallery attachment 397 have
    // identical source bytes, hence the same lossless URL despite distinct IDs.
    const rows = rally ? [
        {id: 52, colour: 'mocha', size: 'l', photo: {...images[2], id: 475}},
        {id: 53, colour: 'beige', size: 'm', photo: {...images[3], id: 474}},
        {id: 54, colour: 'beige', size: 'l', photo: {...images[3], id: 474}},
        {id: 55, colour: 'navy-blue', size: 'xl', photo: image('rally-navy-variation', 394)}
    ] : ['white', 'jujube-red', 'navy'].map((colour, index) => ({id: 125 + index, colour, size: 'l', photo: images[index]}));
    const variations = rows.map(row => ({
        variation_id: row.id, attributes: {attribute_pa_colour: row.colour, attribute_pa_size: row.size},
        image_id: row.photo.id, image: {...row.photo}, blocksy_original_image: {...images[0]},
        blocksy_gallery_source: custom ? 'custom' : 'default',
        is_in_stock: true, is_purchasable: true, variation_is_visible: true, variation_is_active: true,
        min_qty: 1, max_qty: 3, price_html: `<span class="price">Native ${row.id}</span>`
    }));
    if (offGallery) {
        variations[1].image_id = 989;
        variations[1].image = image('red-other-model', 989);
    }
    form.dataset.product_variations = JSON.stringify(ajax ? false : variations);
    $form.data('product_variations', ajax ? false : variations);
    const payloadBefore = JSON.stringify(variations), requests = [], fetches = [], nativeCalls = [], clicked = [], clickStates = [];
    $.ajax = options => { requests.push(options); return {abort() {}}; };
    w.ctEvents = {trigger() {}};
    w.cachedFetch = url => { fetches.push(url); return Promise.resolve({json: () => Promise.resolve({success: false})}); };
    const heldNativeFrames = [];
    let galleryWidth = 300;
    if (nativeFlexy) {
        // The real library needs layout measurements. Geometry alone is fixed;
        // its constructor, first RAF, native attributes and pill handlers are real.
        w.Element.prototype.getBoundingClientRect = function () {
            let left = 0;
            if (rally) {
                // Model native CSS flex ordering and the transform actually
                // written by Flexy; do not infer visibility from its active pill.
                const item = this.closest('.flexy-items > div');
                if (item && item.parentElement === items) {
                    const ordered = [...items.children].sort((a, b) => (Number(a.style.order) || 0) - (Number(b.style.order) || 0));
                    const translation = Number((item.style.transform.match(/translate3d\((-?[\d.]+)px/) || [])[1] || 0);
                    left = ordered.indexOf(item) * galleryWidth + translation;
                }
            }
            return {left, top: 0, width: galleryWidth, height: 400, right: left + galleryWidth, bottom: 400};
        };
        const nativeComputed = w.getComputedStyle.bind(w), computedCache = new WeakMap();
        const computed = element => {
            if (!rally) return nativeComputed(element);
            if (!computedCache.has(element)) computedCache.set(element, nativeComputed(element));
            return computedCache.get(element);
        };
        w.getComputedStyle = (element) => new Proxy(computed(element), {get(target, key) {
            if (rally && key === 'transform') return element.style.transform || 'none';
            if (key === 'content') return '""';
            if (typeof key === 'string' && /^(padding|margin|border)/.test(key)) return '0px';
            const value = target[key];
            return typeof value === 'function' ? value.bind(target) : value;
        }});
        if (initialItem !== null) slider.style.setProperty('--current-item', String(initialItem));
        w.eval(flexyEngine);
        const exports = {};
        const module = w.blocksyJsonP.find(chunk => chunk[0].includes(71))[1][3071];
        module({}, exports, {d: (target, definitions) => Object.entries(definitions)
            .forEach(([key, get]) => Object.defineProperty(target, key, {get}))});
        w.NativeFlexy = exports.r;
        const mountScript = flexyMount.replace(/^import .*\n/gm, '')
            .replace(/^export \{ Flexy \}.*$/gm, '')
            .replace('export const mount =', 'window.mountBlocksyFlexy =');
        assert.doesNotMatch(mountScript, /^(import|export) /m);
        w.eval(`(function ($, Flexy, ctEvents, getCurrentScreen, isTouchDevice, pauseVideo, maybePlayAutoplayedVideo, getScalarOrCallback) { ${mountScript}\n})(window.jQuery, window.NativeFlexy, window.ctEvents, () => 'desktop', () => false, () => {}, () => {}, value => typeof value === 'function' ? value() : value);`);
    }
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
            installed = true;
            if (nativeFlexy) {
                const raf = w.requestAnimationFrame;
                if (holdNativeFrame) w.requestAnimationFrame = callback => { heldNativeFrames.push(callback); return 0; };
                try { w.mountBlocksyFlexy(slider); } finally { w.requestAnimationFrame = raf; }
                [...pills.children].forEach((pill, index) => pill.addEventListener('click', () => {
                    clicked.push(index); clickStates.push({index, moving: slider.hasAttribute('data-flexy-moving')});
                }));
            } else {
                slider.flexy = {}; slider.dataset.flexy = '';
                [...pills.children].forEach((pill, index) => pill.addEventListener('click', event => {
                // Flexy's real pill handler also commits on a subsequent task.
                w.setTimeout(() => {
                    if (event.defaultPrevented) return;
                    pills.querySelector('.active')?.classList.remove('active');
                    pill.classList.add('active'); clicked.push(index); event.preventDefault();
                });
                }));
            }
        }
        resolveMount(slider.flexy);
    }
    if (defaults) {
        $form.find('[name=attribute_pa_colour]').val('jujube-red');
        $form.find('[name=attribute_pa_size]').val('l');
        // The native PHP gallery already identifies a server-selected default.
        if (nativeFlexy) gallery.dataset.currentVariation = '126';
    }
    w.eval(woo);
    if (patched) w.eval(child);
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
        w, $, form, $form, product, gallery, slider, variations, nativeCalls, clicked, clickStates, requests, fetches,
        resize(width) { galleryWidth = width; },
        tick: timer.tick, renderBeforeTimers: timer.renderBeforeTimers,
        mount: async () => { commitMount(); await timer.tick(0); },
        reject: async () => { rejectMount(new Error('synthetic mount failure')); await timer.tick(0); },
        mountCalls: () => mountCalls,
        releaseNativeFrame() { heldNativeFrames.splice(0).forEach(callback => w.requestAnimationFrame(callback)); },
        active: () => [...pills.children].findIndex(pill => pill.classList.contains('active')),
        choose(colour, size = 'l') {
            $form.find('[name=attribute_pa_colour]').val(colour).trigger('change');
            $form.find('[name=attribute_pa_size]').val(size).trigger('change');
        },
        reset() { $form.find('.reset_variations').trigger('click'); },
        manual(type = 'pointerdown') {
            const event = {isTrusted: true, target: pills.children[2], type, key: 'ArrowRight', preventDefault() {}, stopPropagation() {}, stopImmediatePropagation() {}};
            gestureHandlers.filter(row => row.type === type).forEach(row => row.handler.call(product, event));
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

test('real Flexy first RAF completes default selection without collapsing selectors', async () => {
    const f = await fixture({nativeFlexy: true, defaults: true, initialItem: 1});
    try {
        assert.equal(f.id(), '126');
        await f.mount();
        assert.ok(f.slider.flexy, 'the parent assigns the actual instance before its first draw');
        assert.equal(f.slider.dataset.flexy, 'no', 'the native first frame has not committed yet');
        assert.equal(f.form.dataset.bactiveSelectors, 'ready', 'normal intermediate state is not a failure');
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0);
        await f.tick(100);
        assert.equal(f.slider.dataset.flexy, '');
        assert.equal(f.form.dataset.bactiveSelectors, 'ready');
        assert.equal(f.nativeCalls.at(-1).args[0], f.variations[1]);
        assert.equal(f.id(), '126'); assert.equal(f.active(), 1); f.intact();
        await f.tick(5200);
        assert.equal(f.form.dataset.bactiveSelectors, 'ready', 'successful readiness clears its failure watchdog');
    } finally { f.close(); }
});

test('real Flexy instance before its first RAF does not let newer selection bypass readiness', async () => {
    const f = await fixture({nativeFlexy: true});
    try {
        f.choose('jujube-red'); await f.tick(0); await f.mount();
        assert.ok(f.slider.flexy); assert.equal(f.slider.dataset.flexy, 'no');
        f.choose('navy'); await f.tick(0);
        assert.equal(f.id(), '127');
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0);
        await f.tick(600);
        assert.equal(f.form.dataset.bactiveSelectors, 'ready');
        assert.deepEqual(f.nativeCalls.filter(call => call.args[0]?.variation_id).map(call => call.args[0].variation_id), [127]);
        assert.equal(f.active(), 2); f.intact();
    } finally { f.close(); }
});

test('reset and trusted manual navigation cancel during the real native readiness interval', async () => {
    for (const reset of [true, false]) {
        const f = await fixture({nativeFlexy: true});
        try {
            f.choose('jujube-red'); await f.tick(0); await f.mount();
            assert.equal(f.slider.dataset.flexy, 'no');
            if (reset) f.reset(); else f.manual();
            await f.tick(100);
            assert.equal(f.form.dataset.bactiveSelectors, 'ready');
            assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0);
            if (reset) assert.equal(f.id(), ''); else { f.click(2); await f.tick(30); assert.equal(f.active(), 2); }
            f.intact();
        } finally { f.close(); }
    }
});

test('fallback cleanup cancels readiness resources before the real native first frame', async () => {
    const f = await fixture({nativeFlexy: true, holdNativeFrame: true});
    try {
        f.choose('jujube-red'); await f.tick(0); await f.mount();
        f.form.querySelector('.bactive-selector-fallback').click();
        assert.equal(f.form.dataset.bactiveSelectors, 'fallback');
        f.releaseNativeFrame(); await f.tick(5500);
        assert.equal(f.slider.dataset.flexy, '');
        assert.equal(f.form.dataset.bactiveSelectors, 'fallback');
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0); f.intact();
    } finally { f.close(); }
});

test('clearing a server default before the real native first frame restores the original photo', async () => {
    const f = await fixture({nativeFlexy: true, defaults: true, initialItem: 1});
    try {
        await f.mount();
        assert.equal(f.slider.dataset.flexy, 'no');
        assert.equal(f.gallery.dataset.currentVariation, '126');
        f.reset(); await f.tick(600);
        assert.equal(f.id(), '');
        assert.equal(f.form.dataset.bactiveSelectors, 'ready');
        assert.equal(f.active(), 0, 'native Reset must restore the original White photo');
        f.intact();
    } finally { f.close(); }
});

test('native readiness that never commits has a bounded deadline and no late image update', async () => {
    const f = await fixture({nativeFlexy: true, holdNativeFrame: true});
    try {
        f.choose('jujube-red'); await f.tick(0); await f.mount();
        assert.ok(f.slider.flexy); assert.equal(f.slider.dataset.flexy, 'no');
        await f.tick(4900);
        assert.equal(f.form.dataset.bactiveSelectors, 'ready', 'watchdog is not a fixed image delay');
        await f.tick(200);
        assert.equal(f.form.dataset.bactiveSelectors, 'fallback');
        assert.ok([...f.form.querySelectorAll('.variations select')].every(select => !select.hidden));
        assert.equal(f.nativeCalls.filter(call => call.args[0]?.variation_id).length, 0);
        f.releaseNativeFrame(); await f.tick(100);
        assert.equal(f.form.dataset.bactiveSelectors, 'fallback'); f.intact();
    } finally { f.close(); }
});

test('native Flexy alone handles the explicit initial-index zero edge in a four-slide gallery', async () => {
    const f = await fixture({patched: false, nativeFlexy: true, initialItem: 0, rally: true});
    try {
        f.choose('beige', 'm'); await f.mount(); await f.tick(2500);
        const items = [...f.slider.querySelector('.flexy-items').children];
        assert.equal(f.id(), '53'); assert.deepEqual(f.clicked, [3]);
        assert.equal(f.slider.hasAttribute('data-flexy-moving'), false);
        assert.equal(items[3].getBoundingClientRect().left, 0);
        assert.equal(items[3].querySelector('img').src, f.variations[1].image.src);
        f.intact();
    } finally { f.close(); }
});

test('explicit initial-index zero edge waits for native movement without a duplicate thumbnail click', async () => {
    // This deliberately supplied zero is a separate native initialization edge.
    // The current public Rally response does not supply that initial inline value.
    for (const prewarmed of [false, true]) {
        const f = await fixture({nativeFlexy: true, initialItem: 0, rally: true});
        try {
            if (prewarmed) { await f.mount(); await f.tick(50); }
            f.choose('beige', 'm');
            if (!prewarmed) await f.mount();
            await f.tick(2500);
            const items = [...f.slider.querySelector('.flexy-items').children];
            const visible = items.filter(item => Math.abs(item.getBoundingClientRect().left) < 0.5);
            assert.equal(f.id(), '53');
            assert.equal(f.variations[1].image_id, 474);
            assert.equal(f.variations[1].blocksy_original_image.id, 607);
            assert.match(f.form.querySelector('.single_variation').textContent, /Native 53/);
            assert.equal(f.form.dataset.bactiveSelectors, 'ready');
            assert.equal(f.slider.hasAttribute('data-flexy-moving'), false, 'assert settled output rather than a transient pill class');
            assert.equal(visible.length, 1);
            assert.equal(visible[0], items[3], 'the fourth Beige image must occupy the gallery viewport');
            assert.equal(visible[0].querySelector('img').src, f.variations[1].image.src);
            assert.deepEqual(f.clicked, [3], 'the child must not interrupt native wraparound with a second pill click');
            f.intact();
        } finally { f.close(); }
    }
});

test('no-inline Rally gallery corrects a mid-animation resize after native movement settles', async () => {
    for (const resizeAfter of [16, 100]) {
        const f = await fixture({nativeFlexy: true, rally: true});
        try {
            await f.mount(); await f.tick(50);
            assert.equal(f.slider.style.getPropertyValue('--current-item'), '', 'match the current native first-slide markup');
            f.choose('beige', 'm'); await f.tick(resizeAfter);
            assert.equal(f.slider.hasAttribute('data-flexy-moving'), true);
            // Geometry change, not a rewritten Flexy state: its own draw loop
            // detects the new width and applies its native position rounding.
            f.resize(343.1875); await f.tick(2500);
            const items = [...f.slider.querySelector('.flexy-items').children];
            assert.equal(f.id(), '53'); assert.equal(f.form.dataset.bactiveSelectors, 'ready');
            assert.equal(f.slider.hasAttribute('data-flexy-moving'), false);
            assert.equal(items[3].getBoundingClientRect().left, 0);
            assert.equal(items[3].querySelector('img').src, f.variations[1].image.src);
            assert.deepEqual(f.clicked, [3, 3], 'one native selection and one correction after its interrupted movement settles');
            assert.deepEqual(f.clickStates.map(click => click.moving), [false, false]);
            f.intact();
        } finally { f.close(); }
    }
});

test('no-inline Rally gallery corrects a width change before the first selection frame even when its pill is active', async () => {
    for (const resizeBeforeSelect of [true, false]) {
        const f = await fixture({nativeFlexy: true, rally: true});
        try {
            await f.mount(); await f.tick(50);
            assert.equal(f.slider.style.getPropertyValue('--current-item'), '');
            if (resizeBeforeSelect) f.resize(343.1875);
            f.choose('beige', 'm');
            if (!resizeBeforeSelect) { await f.tick(0); f.resize(343.1875); }
            await f.tick(2500);
            const items = [...f.slider.querySelector('.flexy-items').children];
            assert.equal(f.id(), '53'); assert.equal(f.form.dataset.bactiveSelectors, 'ready');
            assert.equal(f.slider.hasAttribute('data-flexy-moving'), false);
            assert.equal(items[3].getBoundingClientRect().left, 0, 'an active Beige pill alone does not prove its photo is visible');
            assert.equal(items[3].querySelector('img').src, f.variations[1].image.src);
            assert.deepEqual(f.clicked, [3, 3]);
            assert.deepEqual(f.clickStates.map(click => click.moving), [false, false]);
            f.intact();
        } finally { f.close(); }
    }
});

test('a render opportunity before the native pill timer cannot trigger an early second click', async () => {
    const f = await fixture({nativeFlexy: true, rally: true});
    try {
        await f.mount(); await f.tick(50);
        // The native click schedules a zero-delay timer. Real browsers may
        // render before that timer, and render again before the next FIFO task.
        // A RAF alone must not be mistaken for the native target commit.
        f.renderBeforeTimers(2); f.choose('beige', 'm'); await f.tick(2500);
        const items = [...f.slider.querySelector('.flexy-items').children];
        assert.equal(f.id(), '53'); assert.equal(f.form.dataset.bactiveSelectors, 'ready');
        assert.equal(f.slider.hasAttribute('data-flexy-moving'), false);
        assert.equal(items[3].getBoundingClientRect().left, 0);
        assert.equal(items[3].querySelector('img').src, f.variations[1].image.src);
        assert.deepEqual(f.clicked, [3], 'only the native selection click is needed when its timer and movement finish');
        f.intact();
    } finally { f.close(); }
});

test('new selection, manual input, Reset, fallback and removal cancel the queued native-task boundary', async () => {
    for (const cancellation of ['selection', 'manual', 'reset', 'fallback', 'remove']) {
        const f = await fixture();
        try {
            f.choose('jujube-red'); await f.tick(20); f.manual();
            await f.mount(); f.click(2); await f.tick(20);
            f.choose('white'); // Native same-image return; child task is queued.
            if (cancellation === 'selection') f.choose('navy');
            if (cancellation === 'manual') f.manual();
            if (cancellation === 'reset') f.reset();
            if (cancellation === 'fallback') f.form.querySelector('.bactive-selector-fallback').click();
            if (cancellation === 'remove') f.product.remove();
            await f.tick(100);
            assert.equal(f.clicked.includes(0), false, `${cancellation} must prevent the queued White correction`);
            assert.equal(f.form.dataset.bactiveSelectors, cancellation === 'fallback' ? 'fallback' : 'ready');
            f.intact();
        } finally { f.close(); }
    }
});

test('geometry correction ignores unmeasurable active slides and one-pixel drift', async () => {
    const cases = [
        {viewWidth: 0, imageWidth: 300, offset: 50, clicks: 0},
        {viewWidth: 300, imageWidth: 0, offset: 50, clicks: 0},
        {viewWidth: 300, imageWidth: 300, offset: 0.5, clicks: 0},
        {viewWidth: 300, imageWidth: 300, offset: 1, clicks: 0},
        {viewWidth: 300, imageWidth: 300, offset: 1.5, clicks: 1}
    ];
    for (const sample of cases) {
        const f = await fixture();
        try {
            f.choose('white'); await f.mount(); await f.tick(50);
            assert.equal(f.active(), 0); assert.deepEqual(f.clicked, []);
            const view = f.slider.querySelector('.flexy-view');
            const item = f.slider.querySelector('.flexy-items').firstElementChild;
            view.getBoundingClientRect = () => ({left: 100, width: sample.viewWidth});
            item.getBoundingClientRect = () => ({left: 100 + sample.offset, width: sample.imageWidth});
            f.choose('white'); await f.tick(50);
            assert.deepEqual(f.clicked, Array(sample.clicks).fill(0));
            assert.equal(f.id(), '125'); assert.equal(f.form.dataset.bactiveSelectors, 'ready');
            f.intact();
        } finally { f.close(); }
    }
});

async function pendingMotionCorrection() {
    const f = await fixture();
    f.choose('jujube-red'); await f.tick(20); f.manual();
    await f.mount(); f.click(2); await f.tick(20);
    f.slider.setAttribute('data-flexy-moving', '');
    f.choose('white'); await f.tick(30);
    assert.equal(f.id(), '125');
    assert.deepEqual(f.clicked, [2], 'same-image correction must wait while native movement is active');
    return f;
}

test('same-image correction waits for native idle, then resolves once using current gallery nodes', async () => {
    const f = await pendingMotionCorrection();
    try {
        f.slider.removeAttribute('data-flexy-moving'); await f.tick(30);
        assert.deepEqual(f.clicked, [2, 0]); assert.equal(f.active(), 0);
        await f.tick(5100);
        assert.equal(f.form.dataset.bactiveSelectors, 'ready', 'successful settlement clears its failure deadline');
        f.intact();
    } finally { f.close(); }
});

test('manual input, Reset, fallback and removal cancel the pending motion correction', async () => {
    for (const cancellation of ['manual', 'reset', 'fallback', 'remove']) {
        const f = await pendingMotionCorrection();
        try {
            if (cancellation === 'manual') f.manual();
            if (cancellation === 'reset') f.reset();
            if (cancellation === 'fallback') f.form.querySelector('.bactive-selector-fallback').click();
            if (cancellation === 'remove') f.product.remove();
            await f.tick(30);
            const clicksAfterNativeCancellation = [...f.clicked];
            f.slider.removeAttribute('data-flexy-moving'); await f.tick(5200);
            assert.deepEqual(f.clicked, clicksAfterNativeCancellation, `${cancellation} must prevent a stale corrective click`);
            assert.equal(f.form.dataset.bactiveSelectors, cancellation === 'fallback' ? 'fallback' : 'ready');
            f.intact();
        } finally { f.close(); }
    }
});

test('native motion that never settles has a bounded deadline and no late corrective click', async () => {
    const f = await pendingMotionCorrection();
    try {
        await f.tick(4900); assert.equal(f.form.dataset.bactiveSelectors, 'ready');
        await f.tick(200); assert.equal(f.form.dataset.bactiveSelectors, 'fallback');
        assert.ok([...f.form.querySelectorAll('.variations select')].every(select => !select.hidden));
        f.slider.removeAttribute('data-flexy-moving'); await f.tick(100);
        assert.deepEqual(f.clicked, [2]); f.intact();
    } finally { f.close(); }
});


// Model the observed deadline boundary: the native movement attribute remains
// set, while measured image position may already be correct. This does not
// simulate the cause of the stuck native flag or replace browser verification.
function deadlineGeometry(f, {viewWidth = 300, imageWidth = 300, offset = 0, index = 0} = {}) {
    f.slider.querySelector('.flexy-view').getBoundingClientRect = () => ({left: 100, width: viewWidth});
    f.slider.querySelector('.flexy-items').children[index].getBoundingClientRect = () =>
        ({left: 100 + offset, width: imageWidth});
}

test('native movement deadline accepts a uniquely matched visible photo without another click', async () => {
    for (const offset of [0, 0.5, 1]) {
        const f = await pendingMotionCorrection();
        try {
            deadlineGeometry(f, {offset});
            await f.tick(4900);
            assert.equal(f.form.dataset.bactiveSelectors, 'ready');
            await f.tick(200);
            assert.equal(f.slider.hasAttribute('data-flexy-moving'), true, 'the child must not rewrite the native movement flag');
            assert.equal(f.form.dataset.bactiveSelectors, 'ready', `visible photo at ${offset}px must retain enhanced controls`);
            assert.equal(f.id(), '125');
            assert.equal(f.form.querySelector('[name=attribute_pa_colour]').value, 'white');
            assert.equal(f.form.querySelector('[name=attribute_pa_size]').value, 'l');
            assert.match(f.form.querySelector('.single_variation').textContent, /Native 125/);
            assert.deepEqual(f.clicked, [2], 'acceptance at the failure deadline cannot send a corrective click');
            f.slider.removeAttribute('data-flexy-moving'); await f.tick(100);
            assert.deepEqual(f.clicked, [2], 'the completed deadline waiter cannot replay on a later native attribute change');
            f.intact();
        } finally { f.close(); }
    }
});

test('native movement deadline still fails closed for wrong, hidden, ambiguous or mismatched photos', async () => {
    const cases = [
        {name: 'misplaced photo', geometry: {offset: 1.5}},
        {name: 'hidden viewport', geometry: {viewWidth: 0}},
        {name: 'unmeasurable image', geometry: {imageWidth: 0}},
        {name: 'ambiguous image', mutate(f) {
            f.slider.querySelector('.flexy-items').children[1].querySelector('img').src = f.variations[0].image.src;
        }},
        {name: 'missing matching image', mutate(f) {
            const figure = f.slider.querySelector('.flexy-items').firstElementChild.querySelector('figure');
            figure.querySelector('img').src = 'https://example.test/other.jpg';
            figure.dataset.src = 'https://example.test/other.jpg';
        }},
        {name: 'different native variation ID', mutate(f) {
            f.form.querySelector('[name=variation_id]').value = '127';
        }}
    ];
    for (const sample of cases) {
        const f = await pendingMotionCorrection();
        try {
            deadlineGeometry(f, sample.geometry);
            sample.mutate?.(f);
            await f.tick(5100);
            assert.equal(f.form.dataset.bactiveSelectors, 'fallback', sample.name);
            assert.ok([...f.form.querySelectorAll('.variations select')].every(select => !select.hidden), sample.name);
            assert.deepEqual(f.clicked, [2], `${sample.name}: timeout cannot send a corrective click`);
            f.slider.removeAttribute('data-flexy-moving'); await f.tick(100);
            assert.deepEqual(f.clicked, [2], `${sample.name}: no late correction after fallback`);
            f.intact();
        } finally { f.close(); }
    }
});

test('a newer selected photo survives an older movement deadline without stale fallback or replay', async () => {
    const f = await pendingMotionCorrection();
    try {
        // Keep the old White target out of position so accepting it would be a
        // false pass. Only the current Navy photo has valid aligned geometry.
        deadlineGeometry(f, {offset: 50});
        await f.tick(100);
        f.choose('navy'); await f.tick(30);
        deadlineGeometry(f, {index: 2});
        assert.equal(f.id(), '127');
        const clicksAfterNativeSelection = [...f.clicked];
        await f.tick(5100);
        assert.equal(f.form.dataset.bactiveSelectors, 'ready');
        assert.equal(f.id(), '127');
        assert.equal(f.form.querySelector('[name=attribute_pa_colour]').value, 'navy');
        assert.deepEqual(f.clicked, clicksAfterNativeSelection, 'old White timeout cannot click or collapse the current selection');
        f.slider.removeAttribute('data-flexy-moving'); await f.tick(100);
        assert.deepEqual(f.clicked, clicksAfterNativeSelection); f.intact();
    } finally { f.close(); }
});

test('manual intent, Reset, fallback, removal and a changed tuple make an old movement deadline inert', async () => {
    for (const cancellation of ['manual', 'reset', 'fallback', 'remove', 'tuple']) {
        const f = await pendingMotionCorrection();
        try {
            deadlineGeometry(f, {offset: 50});
            if (cancellation === 'manual') f.manual();
            if (cancellation === 'reset') f.reset();
            if (cancellation === 'fallback') f.form.querySelector('.bactive-selector-fallback').click();
            if (cancellation === 'remove') f.product.remove();
            if (cancellation === 'tuple') f.form.querySelector('[name=attribute_pa_size]').value = '';
            await f.tick(30);
            const clicksAfterCancellation = [...f.clicked];
            await f.tick(5100);
            assert.equal(f.form.dataset.bactiveSelectors, cancellation === 'fallback' ? 'fallback' : 'ready', cancellation);
            assert.deepEqual(f.clicked, clicksAfterCancellation, `${cancellation}: timeout cannot replay the old photo`);
            f.slider.removeAttribute('data-flexy-moving'); await f.tick(100);
            assert.deepEqual(f.clicked, clicksAfterCancellation); f.intact();
        } finally { f.close(); }
    }
});

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const { execFileSync } = require('node:child_process');

const projectRoot = path.resolve(__dirname, '..');
const scriptPath = path.join(
    projectRoot,
    'wordpress/wp-content/themes/blocksy-child/assets/js/size-guide.js'
);
const functionsPath = path.join(
    projectRoot,
    'wordpress/wp-content/themes/blocksy-child/functions.php'
);
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function loadSizeGuide(options = {}) {
    const documentListeners = {};
    const modalListeners = {};
    const triggerListeners = {};
    const closeListeners = {};

    const trigger = {
        focusCount: 0,
        addEventListener(type, handler) {
            triggerListeners[type] = handler;
        },
        focus() {
            this.focusCount += 1;
        },
    };

    const closeButton = {
        addEventListener(type, handler) {
            closeListeners[type] = handler;
        },
    };

    const modal = {
        open: false,
        showCount: 0,
        closeCount: 0,
        querySelector(selector) {
            return selector === '.bactive-modal-close' ? closeButton : null;
        },
        addEventListener(type, handler) {
            modalListeners[type] = handler;
        },
        showModal() {
            this.open = true;
            this.showCount += 1;
        },
        close() {
            this.open = false;
            this.closeCount += 1;
            modalListeners.close?.();
        },
    };

    if (options.unsupportedDialog) {
        modal.showModal = undefined;
    }

    const document = {
        addEventListener(type, handler) {
            documentListeners[type] = handler;
        },
        getElementById(id) {
            if (options.missingModal) {
                return null;
            }

            return id === 'bactive-size-modal' ? modal : null;
        },
        querySelectorAll(selector) {
            return selector === '.bactive-size-guide-link' ? [trigger] : [];
        },
    };

    vm.runInNewContext(scriptSource, { document });
    documentListeners.DOMContentLoaded();

    return { closeButton, closeListeners, modal, modalListeners, trigger, triggerListeners };
}

test('pages without a product dialog initialize without side effects', () => {
    const fixture = loadSizeGuide({ missingModal: true });

    assert.deepEqual(fixture.triggerListeners, {});
    assert.deepEqual(fixture.modalListeners, {});
});

test('product trigger opens the native dialog without navigating', () => {
    const fixture = loadSizeGuide();
    let prevented = false;

    fixture.triggerListeners.click({
        preventDefault() {
            prevented = true;
        },
    });

    assert.equal(prevented, true);
    assert.equal(fixture.modal.open, true);
    assert.equal(fixture.modal.showCount, 1);
});

test('close button closes the dialog and returns focus to its trigger', () => {
    const fixture = loadSizeGuide();

    fixture.triggerListeners.click({ preventDefault() {} });
    fixture.closeListeners.click();

    assert.equal(fixture.modal.open, false);
    assert.equal(fixture.modal.closeCount, 1);
    assert.equal(fixture.trigger.focusCount, 1);
});

test('backdrop click closes, while clicks inside the dialog do not', () => {
    const fixture = loadSizeGuide();

    fixture.triggerListeners.click({ preventDefault() {} });
    fixture.modalListeners.click({ target: {} });
    assert.equal(fixture.modal.closeCount, 0);

    fixture.modalListeners.click({ target: fixture.modal });
    assert.equal(fixture.modal.closeCount, 1);
});

test('native close events return focus to the trigger', () => {
    const fixture = loadSizeGuide();

    fixture.triggerListeners.click({ preventDefault() {} });
    fixture.modal.close();

    assert.equal(fixture.trigger.focusCount, 1);
});

test('the same trigger can reopen the dialog after it closes', () => {
    const fixture = loadSizeGuide();

    fixture.triggerListeners.click({ preventDefault() {} });
    fixture.modal.close();
    fixture.triggerListeners.click({ preventDefault() {} });

    assert.equal(fixture.modal.open, true);
    assert.equal(fixture.modal.showCount, 2);
});

test('unsupported dialogs retain the Size Guide page fallback', () => {
    const fixture = loadSizeGuide({ unsupportedDialog: true });
    let prevented = false;

    fixture.triggerListeners.click({
        preventDefault() {
            prevented = true;
        },
    });

    assert.equal(prevented, false);
});

test('PHP markup exposes an accessible dialog and usable fallback link', () => {
    const markup = fs.readFileSync(functionsPath, 'utf8');

    assert.match(markup, /home_url\( '\/size-guide\/' \)/);
    assert.match(markup, /'bactive-size-guide'/);
    assert.match(markup, /\/assets\/js\/size-guide\.js/);
    assert.match(markup, /\/assets\/css\/size-guide\.css/);
    assert.match(markup, /aria-haspopup="dialog"/);
    assert.match(markup, /aria-controls="bactive-size-modal"/);
    assert.match(markup, /aria-labelledby="bactive-size-modal-title"/);
    assert.match(markup, /aria-describedby="court-skort-measurements-description"/);
    assert.match(markup, /id="court-skort-measurements-description" hidden/);
    assert.match(markup, /add_filter\( 'the_content', 'bactive_size_guide_page_content' \)/);
    assert.match(markup, /is_page\( 'size-guide' \)/);
    assert.doesNotMatch(markup, /you\\'re/);
});

// Execute the actual isolated size-guide section with a minimal WordPress
// contract. This tests rendered category routing, not just source patterns.
function renderGuide({ slug = '', product = true, page = false, admin = false,
    loop = true, main = true, action = 'modal', chart = '', query = {} } = {}) {
    const source = fs.readFileSync(functionsPath, 'utf8');
    const start = source.indexOf('add_action( \'woocommerce_single_product_summary\', \'bactive_size_guide_link\'');
    const end = source.indexOf('/**\n * Phase 4: Sticky Add-to-Cart HTML', start);
    assert.ok(start > 0 && end > start, 'size-guide section exists');
    const config = Buffer.from(JSON.stringify({ slug, product, page, admin, loop, main, action, chart, query })).toString('base64');
    const section = Buffer.from(source.slice(start, end)).toString('base64');
    const php = `
        $config = json_decode(base64_decode('${config}'), true);
        $_GET = $config['query'];
        function add_action(...$args) {}
        function add_filter(...$args) {}
        function is_product() { return $GLOBALS['config']['product']; }
        function get_queried_object_id() { return 123; }
        function get_post_field($field, $id) {
            if ($field !== 'post_name' || $id !== 123) {
                throw new Exception('Unexpected product identity lookup');
            }
            return $GLOBALS['config']['slug'];
        }
        function is_page($slug) { return $slug === 'size-guide' && $GLOBALS['config']['page']; }
        function is_admin() { return $GLOBALS['config']['admin']; }
        function in_the_loop() { return $GLOBALS['config']['loop']; }
        function is_main_query() { return $GLOBALS['config']['main']; }
        function home_url($path) { return 'https://bactiveph.com' . $path; }
        function get_stylesheet_directory_uri() { return 'https://bactiveph.com/wp-content/themes/blocksy-child'; }
        function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES); }
        function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES); }
        eval(base64_decode('${section}'));
        switch ($config['action']) {
            case 'modal': bactive_size_guide_modal(); break;
            case 'link': bactive_size_guide_link(); break;
            case 'page': echo bactive_size_guide_page_content('original content'); break;
            case 'content': echo bactive_get_size_guide_content('test-title', $config['chart']); break;
            default: throw new Exception('Unexpected test action');
        }
    `;
    return execFileSync('php', ['-r', php], { encoding: 'utf8' });
}

test('Court Skort displays only the visual chart with a nonvisual text alternative', () => {
    const html = renderGuide({ slug: 'the-court-skort' });
    assert.doesNotMatch(html, /<table|<dl|<h3/);
    assert.match(html, /id="court-skort-measurements-description" hidden/);
    for (const values of [
        'Sizes, in order: 4, 6, 8, 10, 12, 14.',
        'Length: 35, 36, 37, 38, 39, 40.',
        'Waist: 64, 68, 72, 76, 80, 84.',
        'Inner Hip: 72, 76, 80, 84, 88, 92.',
        'Inner Leg Opening: 40, 42, 44, 46, 48, 50.',
        'Inner Length: 8.5, 8.8, 9.1, 9.4, 9.7, 10.0.',
    ]) assert.ok(html.includes(values));
    assert.match(html, /Measure across the leg opening of the built-in shorts/);
    assert.match(html, /Please allow 1–2 cm difference/);
    assert.doesNotMatch(html, /Bubble Dress|Slack Bottom|follow below/);
});

test('other skorts, dresses and unknown products never inherit an approved chart', () => {
    for (const slug of ['the-everyday-skort', 'the-flow-skort', 'the-breeze-skort',
        'the-ace-dress', 'the-court-dress', 'the-ribbed-tank', 'the-strappy-bra',
        'the-sculpt-legging', 'the-sculpt-romper', 'the-court-skort-lookalike', '']) {
        const html = renderGuide({ slug });
        assert.match(html, /Size guidance/);
        assert.match(html, /https:\/\/bactiveph.com\/contact\//);
        assert.doesNotMatch(html, /<table|<img|Skort size chart|80 to 84|Asian fit/);
        assert.match(renderGuide({ slug, action: 'link' }), /\/size-guide\/#sizing-help/);
    }
    assert.match(renderGuide({ slug: 'the-court-skort', action: 'link' }), /#court-skort-size-chart/);
    assert.match(renderGuide({ slug: 'the-bubble-dress', action: 'link' }), /#bubble-dress-size-chart/);
    assert.doesNotMatch(renderGuide({ action: 'content', chart: 'unapproved' }), /<table/);
    assert.doesNotMatch(renderGuide({ action: 'content', chart: 'skort' }), /<table/);
    assert.equal(renderGuide({ slug: 'the-court-skort', product: false }), '');
});

test('Bubble Dress displays only the visual chart with a nonvisual text alternative', () => {
    const html = renderGuide({ slug: 'the-bubble-dress' });
    assert.doesNotMatch(html, /<table|<dl|<h3/);
    assert.match(html, /id="bubble-dress-measurements-description" hidden/);
    for (const values of [
        'S: 74, 68, 56, 80, 41.', 'M: 76, 72, 60, 84, 43.',
        'L: 78, 76, 64, 88, 45.', 'XL: 80, 80, 68, 92, 47.',
        'XXL: 84, 84, 72, 98, 49.',
    ]) assert.ok(html.includes(values));
    assert.match(html, /flat half-width of the leg opening/);
    assert.match(html, /Double to get full thigh opening circumference/);
    assert.doesNotMatch(html, /Court Skort|numeric labels|Inner Hip|follow below/);
});

test('standalone guide offers a chooser instead of stacking charts', () => {
    const html = renderGuide({ action: 'page', page: true, product: false });
    assert.match(html, /Choose your product/);
    assert.doesNotMatch(html, /<img|<table/);
    for (const chart of ['court-skort', 'bubble-dress']) {
        assert.ok(html.includes('/size-guide/?chart=' + chart + '#' + chart + '-size-chart'));
        assert.ok(html.includes('id="' + chart + '-size-chart"'));
    }
    assert.match(html, /id="sizing-help"/);
    for (const override of [{ page: false }, { admin: true }, { loop: false }, { main: false }]) {
        assert.equal(renderGuide({ action: 'page', page: true, ...override }), 'original content');
    }
});

test('product fallback pages show only their exact selected visual chart', () => {
    for (const chart of ['court-skort', 'bubble-dress']) {
        const html = renderGuide({ action: 'page', page: true, product: false, query: { chart } });
        assert.equal([...html.matchAll(/<img /g)].length, 1);
        assert.doesNotMatch(html, /<table|Choose your product/);
        assert.ok(html.includes(chart + '-illustrated-20260911.jpg'));
        assert.ok(html.includes('id="' + chart + '-size-chart"'));
        const link = renderGuide({ action: 'link', slug: 'the-' + chart });
        assert.ok(link.includes('/size-guide/?chart=' + chart + '#' + chart + '-size-chart'));
        const other = chart === 'court-skort' ? 'bubble-dress' : 'court-skort';
        assert.ok(!html.includes(other));
    }
});

test('invalid chart queries fail safely to the chooser without reflecting input', () => {
    for (const chart of ['', 'skort', 'court-skort-lookalike', 'Court-skort',
        '../court-skort', '<script>alert(1)</script>', ['court-skort'],
        { key: 'bubble-dress' }, null, 1]) {
        const html = renderGuide({ action: 'page', page: true, query: { chart } });
        assert.match(html, /Choose your product/);
        assert.doesNotMatch(html, /<img|<table|<script>|\.\.\//);
    }
});

test('original illustrated guides are intact and restricted to their exact products', () => {
    const { createHash } = require('node:crypto');
    const guides = [
        ['court-skort', '9658c8213afa114480f5563f839fb890a93cb786bc019e374d788b6c0b6cdfaf'],
        ['bubble-dress', '42a81babe16dea20dbeb5bf6a86cd6c8d05880a4466ebd727c78136badbac2e9'],
    ];
    for (const [chart, sha256] of guides) {
        const filename = `${chart}-illustrated-20260911.jpg`;
        const asset = fs.readFileSync(path.join(projectRoot,
            'wordpress/wp-content/themes/blocksy-child/assets/images/size-guides', filename));
        assert.equal(createHash('sha256').update(asset).digest('hex'), sha256);
        assert.equal(asset.subarray(0, 3).toString('hex'), 'ffd8ff');
        const html = renderGuide({ slug: `the-${chart}` });
        assert.equal([...html.matchAll(/<img /g)].length, 1);
        assert.doesNotMatch(html, /<table/);
        assert.ok(html.includes(`/assets/images/size-guides/${filename}`));
        assert.match(html, /width="853" height="1280" loading="lazy" alt="[^"]+"/);
        assert.match(html, /target="_blank" rel="noopener" aria-label="Open [^"]+new tab"/);
        for (const [other] of guides.filter(([key]) => key !== chart)) {
            assert.ok(!html.includes(`${other}-illustrated-20260911.jpg`));
        }
    }
    assert.equal([...renderGuide({ action: 'page', page: true }).matchAll(/<img /g)].length, 0);
    assert.doesNotMatch(renderGuide({ action: 'content', chart: 'unapproved' }), /<img/);
});

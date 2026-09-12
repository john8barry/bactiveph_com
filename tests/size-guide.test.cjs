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
    assert.match(markup, /class="bactive-size-table-wrap"/);
    assert.match(markup, /<caption>Skort measurements in centimeters \(cm\)<\/caption>/);
    assert.match(markup, /<th scope="col">Size<\/th>/);
    assert.match(markup, /<th scope="col">14<\/th>/);
    assert.match(markup, /add_filter\( 'the_content', 'bactive_size_guide_page_content' \)/);
    assert.match(markup, /is_page\( 'size-guide' \)/);
    assert.doesNotMatch(markup, /you\\'re/);
});

// Execute the actual isolated size-guide section with a minimal WordPress
// contract. This tests rendered category routing, not just source patterns.
function renderGuide({ category = '', product = true, page = false, admin = false,
    loop = true, main = true, action = 'modal', chart = '' } = {}) {
    const source = fs.readFileSync(functionsPath, 'utf8');
    const start = source.indexOf('add_action( \'woocommerce_single_product_summary\', \'bactive_size_guide_link\'');
    const end = source.indexOf('/**\n * Phase 4: Sticky Add-to-Cart HTML', start);
    assert.ok(start > 0 && end > start, 'size-guide section exists');
    const config = Buffer.from(JSON.stringify({ category, product, page, admin, loop, main, action, chart })).toString('base64');
    const section = Buffer.from(source.slice(start, end)).toString('base64');
    const php = `
        $config = json_decode(base64_decode('${config}'), true);
        function add_action(...$args) {}
        function add_filter(...$args) {}
        function is_product() { return $GLOBALS['config']['product']; }
        function get_queried_object_id() { return 123; }
        function has_term($term, $taxonomy, $id) {
            if ($term !== 'skorts' || $taxonomy !== 'product_cat' || $id !== 123) {
                throw new Exception('Unexpected taxonomy lookup');
            }
            return $GLOBALS['config']['category'] === $term;
        }
        function is_page($slug) { return $slug === 'size-guide' && $GLOBALS['config']['page']; }
        function is_admin() { return $GLOBALS['config']['admin']; }
        function in_the_loop() { return $GLOBALS['config']['loop']; }
        function is_main_query() { return $GLOBALS['config']['main']; }
        function home_url($path) { return 'https://bactiveph.com' . $path; }
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

test('skort chart preserves every supplied size and measurement exactly', () => {
    const html = renderGuide({ category: 'skorts' });
    const rows = [...html.matchAll(/<tr>(.*?)<\/tr>/g)].map(([, row]) =>
        [...row.matchAll(/<(?:th|td)[^>]*>(.*?)<\/(?:th|td)>/g)].map(([, value]) => value)
    );
    assert.deepEqual(rows, [
        ['Size', '4', '6', '8', '10', '12', '14'],
        ['Length (cm)', '35', '36', '37', '38', '39', '40'],
        ['Waist (cm)', '64', '68', '72', '76', '80', '84'],
        ['Inner Hip (cm)', '72', '76', '80', '84', '88', '92'],
        ['Inner Leg Opening (cm)', '40', '42', '44', '46', '48', '50'],
        ['Inner Length (cm)', '8.5', '8.8', '9.1', '9.4', '9.7', '10.0'],
    ]);
    assert.match(html, /Measure across the leg opening of the built-in shorts/);
    assert.match(html, /Please allow 1–2 cm difference/);
    assert.match(html, /Contact us to confirm your matching skort size/);
    assert.doesNotMatch(html, /80 to 84|Asian fit|runs true to size/);
});

test('other and unknown categories never inherit the skort or placeholder chart', () => {
    for (const category of ['tops', 'sports-bras', 'dresses', 'leggings', 'bottoms', 'rompers', '']) {
        const html = renderGuide({ category });
        assert.match(html, /Size guidance/);
        assert.match(html, /https:\/\/bactiveph.com\/contact\//);
        assert.doesNotMatch(html, /<table|Skort size chart|80 to 84|Asian fit/);
        assert.match(renderGuide({ category, action: 'link' }), /\/size-guide\/#sizing-help/);
    }
    assert.doesNotMatch(renderGuide({ category: 'skorts', action: 'link' }), /#sizing-help/);
    assert.doesNotMatch(renderGuide({ action: 'content', chart: 'unapproved' }), /<table/);
    assert.equal(renderGuide({ category: 'skorts', product: false }), '');
});

test('standalone guide labels skorts separately and preserves unrelated content', () => {
    const html = renderGuide({ action: 'page', page: true, product: false });
    assert.match(html, /Skort size chart/);
    assert.match(html, /id="sizing-help"/);
    assert.match(html, /does not apply to these garments/);
    for (const override of [{ page: false }, { admin: true }, { loop: false }, { main: false }]) {
        assert.equal(renderGuide({ action: 'page', page: true, ...override }), 'original content');
    }
});

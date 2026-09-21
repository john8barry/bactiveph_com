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

// Independent expectations from the owner's selected original images.
// Product slugs are explicit: display names and chart IDs are not URL identities.
const guides = [
    {
        chart: 'court-skort', slugs: ['the-court-skort'], name: 'Court Skort',
        sha256: '341ebb2b36beefa5ac339db20f64cbf87925b06b419ddaa63a35c4ccf7f4f4dc',
        values: [
            'Sizes, in order: 4, 6, 8, 10, 12, 14.',
            'Length: 35, 36, 37, 38, 39, 40.', 'Waist: 64, 68, 72, 76, 80, 84.',
            'Inner Hip: 72, 76, 80, 84, 88, 92.',
            'Inner Leg Opening: 40, 42, 44, 46, 48, 50.',
            'Inner Length: 8.5, 8.8, 9.1, 9.4, 9.7, 10.0.',
        ],
        instructions: [
            'Measure from the top of the waistband to the hem.',
            'Measure around the narrowest part of your waist.',
            'Measure around the fullest part of your hips (below the waistband).',
            'Measure across the leg opening of the built-in shorts.',
            'Measure the length of the inner shorts (from crotch to hem).',
            'Please allow 1–2 cm difference due to manual measurement.',
        ],
    },
    {
        chart: 'strappy-bra', slugs: ['the-strappy-bra'], name: 'Strappy Bra',
        sha256: 'db7ad53c2283b43d720c2275b29faab279f8e6d6dbf7be99d34633bcf95cf324',
        values: [
            'Each size lists Upper Bust, Under Bust and Waist, in that order.',
            'S: 84, 69, 63.', 'M: 91, 76, 79.', 'L: 93, 81, 84.', 'XL: 103, 89, 95.',
        ],
        instructions: [
            'Upper Bust: Measure around the fullest part of your bust, keeping the tape level.',
            'Under Bust: Measure around the ribcage directly under your bust, keeping the tape level.',
            'Waist: Measure around the narrowest part of your waist.',
            'Scoop neckline, criss cross straps and supportive wide band.',
            'Please allow 1–2 cm difference due to fabric stretch and manufacturing.',
        ],
    },
    {
        chart: 'bubble-dress', slugs: ['bubble-dress'], name: 'Bubble Dress',
        sha256: '3ff8944b83406662e4f94fef60529f37172acaf26c277cea68f4ac717b585785',
        values: [
            'Each size lists Coat Length, Bust, Waist, Hip and Slack Bottom, in that order.',
            'S: 74, 68, 56, 80, 41.', 'M: 76, 72, 60, 84, 43.',
            'L: 78, 76, 64, 88, 45.', 'XL: 80, 80, 68, 92, 47.',
            'XXL: 84, 84, 72, 98, 49.',
        ],
        instructions: [
            'Coat Length: Total length from top of shoulder to bottom hem of outer skirt.',
            'Bust: Measure around the fullest part of your bust.',
            'Waist: Measure around the narrowest part of your waist.',
            'Hip: Measure around the fullest part of your hips.',
            'Slack Bottom: This is the flat half-width of the leg opening of the built-in inner shorts.',
            'Double to get full thigh opening circumference.',
            'This dimension tells how loose/tight the inner shorts fit around your thighs.',
            'Please allow 1–2 cm difference due to manual measurement.',
        ],
    },
    {
        chart: 'match-dress', slugs: ['the-match-dress'], name: 'Match Dress',
        sha256: 'ba42bf1d802427f80e10bc4bd24c231511f1fcfebffe6e3834292a78cca2af62',
        values: [
            'Each size lists Coat Length, Bust, Waist and Hip, in that order.',
            'S: 77, 74, 60, 84.', 'M: 79, 78, 64, 88.',
            'L: 81, 82, 68, 92.', 'XL: 83, 86, 72, 96.',
        ],
        instructions: [
            'Bust: Measure around the fullest part of your bust, keeping the tape level.',
            'Waist: Measure around the narrowest part of your waist.',
            'Hip: Measure around the fullest part of your hips.',
            'Coat Length: Total length from top of shoulder to bottom hem of outer skirt.',
            'Clean front design, back cutout detail and signature waist band.',
            'Please allow 1–2 cm difference due to fabric stretch and manufacturing.',
        ],
    },
    {
        chart: 'serve-dress', slugs: ['the-serve-dress'], name: 'Serve Dress',
        sha256: '780f5994fe93e494080d10020c8225d14d0ee027288a04ff19c7c634dbd7c7f9',
        values: [
            'Sizes, in order: 4, 6, 8, 10, 12.',
            'Length: 71, 73, 75, 77, 79.', 'Bust: 72, 76, 80, 84, 88.',
            'Hem Circumference: 70, 74, 78, 82, 86.',
        ],
        instructions: [
            'Bust: Measure around the fullest part of your bust, keeping the tape level.',
            'Length: Measure from the top of the shoulder to the hem.',
            'Hem Circumference: Measure around the bottom hem opening of the dress.',
            'Please allow 1–2 cm difference due to manual measurement.',
        ],
    },
    {
        chart: 'elite-dress', slugs: ['the-eyelet-dress'], name: 'Elite Dress',
        sha256: '2fd6f034828fb829936a3efd8f4dac1d64b304f7905032a2cd0b90e1db0cb897',
        values: [
            'Each size lists Bust, Waist, Hip, Coat Length and Slack Bottom, in that order.',
            'S: 78–84, 62–68, 86–92, 78, 24.', 'M: 84–90, 68–74, 92–98, 79, 25.',
            'L: 90–96, 74–80, 98–104, 80, 26.', 'XL: 96–102, 80–86, 104–110, 81, 27.',
        ],
        instructions: [
            'Bust: Measure around the fullest part of your bust, keeping the tape level.',
            'Waist: Measure around the narrowest part of your waist.',
            'Hip: Measure around the fullest part of your hips.',
            'Coat Length: Total length from top of shoulder to bottom hem of outer skirt.',
            'Slack Bottom: This is the flat half-width of the leg opening of the built-in inner shorts.',
            'Double to get full thigh opening circumference.',
            'This dimension tells how loose/tight the inner shorts fit around your thighs.',
            'Measurements may vary slightly (±1–2 cm) due to fabric stretch and manufacturing.',
        ],
    },
    {
        chart: 'courtline-dress', slugs: ['the-ace-dress'], name: 'Courtline Dress',
        sha256: '9d24a369872e87363460888bc4e367933c0555a222740d75bad2cd95cafcc73d',
        values: [
            'Each size lists Waist, Hip, Pants Length and Thigh, in that order.',
            'S: 63, 90, 86, 70.5.', 'M: 67, 94, 88, 72.5.',
            'L: 71, 98, 90, 74.5.', 'XL: 75, 102, 92, 76.5.',
        ],
        instructions: [
            'Bust: Measure around the fullest part of your bust, keeping the tape level.',
            'Waist: Measure around the narrowest part of your waist.',
            'Hip: Measure around the fullest part of your hips.',
            'Pants Length: Total length from top of shoulder to bottom hem of outer skirt.',
            'Thigh: Measure around the fullest part of your thigh.',
            'Clean front design, back cutout detail and signature waist band.',
            'Please allow 1–2 cm difference due to fabric stretch and manufacturing.',
        ],
    },
    {
        chart: 'mens-polo-tee', slugs: ['everyday-active-tee', 'every-active-polo'],
        name: 'Men’s Polo / Tee', filename: 'mens-polo-tee-illustrated-20260921.jpg',
        sha256: '6abaa6fe70448b00c60110129f6f20a966aa465ffe28572c1fc96e86f84c2c92',
        values: [
            'Each size lists Bust, Shoulder Width, Sleeve Length and Cuff, in that order.',
            'S: 98, 43, 20.5, 34.', 'M: 102, 44.5, 23, 35.3.',
            'L: 106, 46, 23.5, 36.6.', 'XL: 110, 47.5, 25, 37.9.',
            'XXL: 114, 49, 26.5, 39.2.',
        ],
        instructions: [
            'Shoulder Width: Measure from one shoulder seam to the other.',
            'Bust: Measure around the fullest part of your chest.',
            'Sleeve Length: Measure from the shoulder seam to the end of the sleeve.',
            'Cuff: Measure around the sleeve opening.',
            'Please allow 1–2 cm difference due to manual measurement.',
            'Lightweight and breathable, moisture wicking, 4-way stretch and comfort for every move.',
        ],
    },
].map(guide => ({ ...guide, filename: guide.filename ?? `${guide.chart}-illustrated-20260919.jpg` }));
const productGuides = guides.flatMap(guide => guide.slugs.map(slug => ({ ...guide, slug })));

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
    assert.match(markup, /aria-describedby=/);
    assert.match(markup, /add_filter\( 'the_content', 'bactive_size_guide_page_content' \)/);
    assert.match(markup, /is_page\( 'size-guide' \)/);
    assert.doesNotMatch(markup, /you\\'re/);
});

// Execute the actual isolated size-guide section with a minimal WordPress
// contract. This tests rendered exact-product routing, not just source patterns.
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
        function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES); }
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

for (const { slug, ...guide } of productGuides) {
    test(`${slug} displays its original chart and complete nonvisual measurements`, () => {
        const html = renderGuide({ slug });
        assert.equal([...html.matchAll(/<img /g)].length, 1);
        assert.doesNotMatch(html, /<table|<dl|<h3/);
        assert.ok(html.includes(guide.name + ' size chart'));
        assert.ok(html.includes('/assets/images/size-guides/' + guide.filename));
        const descriptionId = guide.chart + '-measurements-description';
        assert.ok(html.includes(`aria-describedby="${descriptionId}"`));
        const description = html.match(new RegExp(`<div id="${descriptionId}" hidden>([^<]+)</div>`))?.[1];
        assert.ok(description, 'link references a hidden complete text alternative');
        assert.ok(description.includes('measurements in centimeters (cm).'));
        for (const value of [...guide.values, ...guide.instructions]) {
            assert.ok(description.includes(value), `${guide.chart}: ${value}`);
        }
        assert.ok(description.includes('If you are between sizes, we recommend sizing up for a more comfortable fit.'));
        for (const other of guides.filter(item => item.chart !== guide.chart)) {
            assert.ok(!html.includes(other.filename), 'another product chart must not appear');
        }
    });
}

test('other skorts, dresses and unknown products never inherit an approved chart', () => {
    for (const slug of ['the-everyday-skort', 'the-flow-skort', 'the-breeze-skort',
        'the-court-dress', 'the-ribbed-tank', 'the-sculpt-legging', 'the-sculpt-romper',
        'the-bubble-dress', 'the-elite-dress', 'the-courtline-dress',
        'the-match-polo', 'the-everyday-tee', 'unrelated-mens-polo', 'mens-training-shorts',
        'everyday-active-polo', 'everyday-active-tee-lookalike', 'every-active-polo-lookalike',
        'the-court-skort-lookalike', 'the-eyelet-dress-lookalike', 'Bubble-Dress', '']) {
        const html = renderGuide({ slug });
        assert.match(html, /Size guidance/);
        assert.match(html, /https:\/\/bactiveph.com\/contact\//);
        assert.doesNotMatch(html, /<table|<img|Skort size chart|80 to 84|Asian fit/);
        assert.match(renderGuide({ slug, action: 'link' }), /\/size-guide\/#sizing-help/);
    }
    for (const chart of ['unapproved', 'skort', ['court-skort'], null, 1]) {
        assert.doesNotMatch(renderGuide({ action: 'content', chart }), /<img|<table/);
    }
    assert.equal(renderGuide({ slug: 'the-court-skort', product: false }), '');
});

test('product charts ignore variation and chart query values', () => {
    for (const { slug } of productGuides) {
        const expected = renderGuide({ slug });
        for (const size of ['S', 'XL', '4', '12']) {
            const html = renderGuide({ slug, query: {
                attribute_pa_size: size, attribute_pa_color: 'black', chart: 'unapproved',
            } });
            assert.equal(html, expected);
        }
    }
    const serve = renderGuide({ slug: 'the-serve-dress' });
    assert.doesNotMatch(serve, /4\s*=\s*S|S:\s*71|XL:\s*79/);
});

test('standalone guide offers a chooser instead of stacking charts', () => {
    const html = renderGuide({ action: 'page', page: true, product: false });
    assert.match(html, /Choose your product/);
    assert.doesNotMatch(html, /<img|<table/);
    assert.equal([...html.matchAll(/<li /g)].length, guides.length);
    for (const { chart, name } of guides) {
        assert.ok(html.includes('/size-guide/?chart=' + chart + '#' + chart + '-size-chart'));
        assert.ok(html.includes('id="' + chart + '-size-chart" class="bactive-size-chart-anchor"'));
        assert.ok(html.includes(name + ' visual size chart'));
    }
    assert.match(html, /id="sizing-help"/);
    assert.equal([...html.matchAll(/id="mens-polo-tee-size-chart"/g)].length, 1,
        'both exact men’s products share one chooser entry');
    for (const override of [{ page: false }, { admin: true }, { loop: false }, { main: false }]) {
        assert.equal(renderGuide({ action: 'page', page: true, ...override }), 'original content');
    }
});

test('product fallback pages show only their exact selected visual chart', () => {
    for (const { chart, slugs, filename } of guides) {
        const html = renderGuide({ action: 'page', page: true, product: false, query: { chart } });
        assert.equal([...html.matchAll(/<img /g)].length, 1);
        assert.doesNotMatch(html, /<table|Choose your product/);
        assert.ok(html.includes(filename));
        assert.ok(html.includes('id="' + chart + '-size-chart" class="bactive-size-chart-anchor"'));
        for (const slug of slugs) {
            const link = renderGuide({ action: 'link', slug });
            assert.ok(link.includes('/size-guide/?chart=' + chart + '#' + chart + '-size-chart'));
        }
        for (const other of guides.filter(item => item.chart !== chart)) {
            assert.ok(!html.includes(other.filename));
        }
    }
});

test('invalid chart queries fail safely to the chooser without reflecting input', () => {
    for (const chart of ['', 'skort', 'court-skort-lookalike', 'Court-skort',
        '../court-skort', '<script>alert(1)</script>', ['court-skort'],
        { key: 'bubble-dress' }, 'the-eyelet-dress', 'elite-dress ', 'mens-polo-tee ',
        'everyday-active-tee', 'every-active-polo', ['mens-polo-tee'], '__proto__', null, 1]) {
        const html = renderGuide({ action: 'page', page: true, query: { chart } });
        assert.match(html, /Choose your product/);
        assert.doesNotMatch(html, /<img|<table|<script>|\.\.\//);
    }
});

test('original illustrated guides are intact and restricted to their exact products', () => {
    const { createHash } = require('node:crypto');
    for (const { chart, slugs, sha256, filename } of guides) {
        const asset = fs.readFileSync(path.join(projectRoot,
            'wordpress/wp-content/themes/blocksy-child/assets/images/size-guides', filename));
        assert.equal(createHash('sha256').update(asset).digest('hex'), sha256);
        assert.equal(asset.subarray(0, 3).toString('hex'), 'ffd8ff');
        for (const slug of slugs) {
            const html = renderGuide({ slug });
            assert.equal([...html.matchAll(/<img /g)].length, 1);
            assert.doesNotMatch(html, /<table/);
            assert.ok(html.includes(`/assets/images/size-guides/${filename}`));
            assert.match(html, /width="853" height="1280" loading="lazy" alt="[^"]+"/);
            assert.match(html, /target="_blank" rel="noopener" aria-label="Open [^"]+new tab"/);
            for (const other of guides.filter(item => item.chart !== chart)) {
                assert.ok(!html.includes(other.filename));
            }
        }
    }
    // Superseded originals remain intact for scoped rollback; they are no longer rendered.
    for (const [chart, sha256] of [
        ['court-skort', '9658c8213afa114480f5563f839fb890a93cb786bc019e374d788b6c0b6cdfaf'],
        ['bubble-dress', '42a81babe16dea20dbeb5bf6a86cd6c8d05880a4466ebd727c78136badbac2e9'],
    ]) {
        const asset = fs.readFileSync(path.join(projectRoot,
            'wordpress/wp-content/themes/blocksy-child/assets/images/size-guides',
            `${chart}-illustrated-20260911.jpg`));
        assert.equal(createHash('sha256').update(asset).digest('hex'), sha256);
    }
    assert.equal([...renderGuide({ action: 'page', page: true }).matchAll(/<img /g)].length, 0);
    assert.doesNotMatch(renderGuide({ action: 'content', chart: 'unapproved' }), /<img/);
});

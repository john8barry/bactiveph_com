const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

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

test('native close events, including Escape, return focus to the trigger', () => {
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
    assert.match(markup, /<caption>Size chart in centimetres<\/caption>/);
    assert.match(markup, /<th scope="col">Size<\/th>/);
    assert.match(markup, /<th scope="row">XL<\/th>/);
    assert.match(markup, /add_filter\( 'the_content', 'bactive_size_guide_page_content' \)/);
    assert.match(markup, /is_page\( 'size-guide' \)/);
    assert.doesNotMatch(markup, /you\\'re/);
});

/* Browser boundary contract tests with isolated DOM/fetch doubles. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/newsletter.js'), 'utf8');

async function scenario(responses, token = 'fixture-captcha', doubleSubmit = false) {
    let submit;
    const calls = [];
    class Form {
        constructor() {
            this.dataset = {nonceUrl: 'https://bactiveph.com/wp-admin/admin-ajax.php?action=bactive_brevo_nonce'};
            this.action = 'https://bactiveph.com/wp-admin/admin-post.php';
            this.nodes = {button: {disabled: false, textContent: 'Join the club'}, status: {textContent: ''}, captcha: {dataset: {widgetId: 'fixture'}}};
            this.fields = {'cf-turnstile-response': token, ba_signup_nonce: 'stale-cached-nonce'};
            this.resetCount = 0;
        }
        matches() { return true; }
        querySelector(selector) { return selector.startsWith('button') ? this.nodes.button : selector.includes('status') ? this.nodes.status : this.nodes.captcha; }
        setAttribute() {}
        removeAttribute() {}
        reset() { this.resetCount++; }
    }
    class Data {
        constructor(form) { this.values = {...form.fields}; }
        get(key) { return this.values[key]; }
        set(key, value) { this.values[key] = value; }
    }
    const form = new Form();
    const context = {
        document: {querySelectorAll: () => [], addEventListener: (event, handler) => { submit = handler; }},
        window: {setTimeout, clearTimeout, turnstile: {reset: () => {}}},
        HTMLFormElement: Form, FormData: Data, AbortController,
        fetch: async (url, options) => {
            calls.push({url, options});
            const next = responses.shift();
            if (!next) throw new Error('Unexpected request');
            if (next instanceof Error) throw next;
            return next;
        }
    };
    vm.runInNewContext(source, context);
    const first = submit({target: form, preventDefault() {}});
    if (doubleSubmit) await submit({target: form, preventDefault() {}});
    await first;
    assert.equal(form.nodes.button.disabled, false, 'Button must recover after every outcome.');
    assert.equal(form.dataset.pending, undefined, 'In-flight guard must clear.');
    return {form, calls};
}
const response = (data, ok = true) => ({ok, json: async () => data});
(async () => {
    let result = await scenario([response({nonce: 'fresh-nonce'}), response({ok: true, message: 'Check your inbox.'})], 'token', true);
    assert.equal(result.calls.length, 2, 'Repeated click must produce just one bootstrap and one signup.');
    assert.equal(result.calls[0].options.cache, 'no-store');
    assert.equal(result.calls[1].options.body.get('ba_signup_nonce'), 'fresh-nonce', 'Cached nonce must be replaced.');
    assert.equal(result.form.resetCount, 1);
    result = await scenario([response({nonce: 'bad'}, false)]);
    assert.equal(result.calls.length, 1, 'Failed nonce bootstrap must not attempt signup.');
    assert.equal(result.form.resetCount, 0);
    result = await scenario([response({nonce: 'fresh'}), new Error('Connection failed')]);
    assert.equal(result.calls.length, 2, 'Ambiguous submission must not be retried.');
    assert.match(result.form.nodes.status.textContent, /Check your inbox first/);
    result = await scenario([], '');
    assert.equal(result.calls.length, 0, 'Missing security token must not send a request.');
    assert.match(result.form.nodes.status.textContent, /security check/);
    console.log('Newsletter client boundary tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });

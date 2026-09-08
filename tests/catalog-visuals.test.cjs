/* NODE_PATH=/tmp/bactive-selector-tests/node_modules node --test tests/catalog-visuals.test.cjs
 * Harness dependencies: jsdom, installed outside the repository.
 * Exercises the actual bundled Woo variation engine for native and AJAX modes.
 */
const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {JSDOM} = require('jsdom');
const jquery = readFileSync(require.resolve('../wordpress/wp-includes/js/jquery/jquery.js'), 'utf8');
const theme = '../wordpress/wp-content/themes/blocksy-child/';
const source = readFileSync(require.resolve(theme + 'assets/js/catalog-visuals.js'), 'utf8');
// CI exercises the incumbent source; release qualification also points at the
// independently verified active-version engine from the private backup.
const woo = readFileSync(process.env.WC_VARIATION_ENGINE_PATH || require.resolve('../wordpress/wp-content/plugins/woocommerce/assets/js/frontend/add-to-cart-variation.js'), 'utf8');
const config = {schemaVersion: 1, version: 'test-1', productId: 36, palette: {}};
async function page(overrides = {}, ajax = false) {
    const dom = new JSDOM(`<div class="product"><div class="summary"><form class="variations_form" data-product_id="36">
    <table class="variations"><tr><th><label for="size">Size</label></th><td><select id="size" name="attribute_pa_size" data-attribute_name="attribute_pa_size"><option value="">Choose</option><option value="s">S</option><option value="m">M</option></select></td></tr>
    <tr><th><label for="colour">Colour</label></th><td><select id="colour" name="attribute_pa_colour" data-attribute_name="attribute_pa_colour"><option value="">Choose</option><option value="black">Black</option><option value="white">&lt;img src=x onerror=alert(1)&gt;</option></select></td></tr></table>
    <a class="reset_variations" href="#">Clear</a><div class="reset_variations_alert"></div><div class="single_variation_wrap"><div class="single_variation"></div><input name="variation_id" value=""><div class="woocommerce-variation-add-to-cart"><input name="quantity" type="number" value="1"><button class="single_add_to_cart_button">Add to cart</button></div></div></form></div></div>
    <script type="text/template" id="tmpl-variation-template"><div>Native variation</div></script><script type="text/template" id="tmpl-unavailable-variation-template"><div>Unavailable</div></script>`, {runScripts:'outside-only',url:'https://example.test/product/test/'});
    const w = dom.window; w.eval(jquery); const $ = w.jQuery;
    w.bactiveCatalogVisuals = {...config, ...overrides};
    w.wc_add_to_cart_variation_params = {wc_ajax_url:'https://example.test/?wc-ajax=%%endpoint%%', i18n_no_matching_variations_text:'No matching variations',i18n_make_a_selection_text:'Select',i18n_unavailable_text:'Unavailable'};
    $.fn.block = $.fn.unblock = function () {return this;};
    $.fn.wc_set_content = $.fn.wc_reset_content = $.fn.wc_variations_image_update = function () {return this;};
    w.wp = {template: () => () => '<div>Native variation</div>'};
    const form = $('form');
    const variations = [
        {variation_id:101,attributes:{attribute_pa_size:'s',attribute_pa_colour:'black'},is_in_stock:true,is_purchasable:true,variation_is_visible:true,variation_is_active:true,min_qty:1,max_qty:3,image:{},price_html:'native-price'},
        {variation_id:102,attributes:{attribute_pa_size:'m',attribute_pa_colour:'white'},is_in_stock:true,is_purchasable:true,variation_is_visible:true,variation_is_active:true,min_qty:1,max_qty:3,image:{}}
    ];
    form.data('product_variations', ajax ? false : variations);
    const requests = [];
    $.ajax = options => {requests.push(options); return {abort(){}};};
    w.eval(woo); w.eval(source);
    await new Promise(resolve => w.setTimeout(resolve, 300));
    return {w,$,form,requests,variations,close:()=>w.close(), buttons:()=>[...w.document.querySelectorAll('.bactive-selector-option')],
        button: text => [...w.document.querySelectorAll('.bactive-selector-option')].find(b=>b.textContent===text)};
}
test('off, wrong ID, and malformed version preserve native dropdowns', async () => {
    for (const overrides of [{schemaVersion:2},{productId:99},{version:'</script>'}]) {
        const p = await page(overrides);
        assert.equal(p.buttons().length,0); assert.equal(p.w.document.querySelector('select').hidden,false); p.close();
    }
});
test('native Woo engine resolves existing ID, syncs constrained choices, and resets', async () => {
    const p = await page();
    assert.equal(p.buttons().length,4);
    p.button('S').click(); p.button('Black').click();
    assert.equal(p.form.find('[name=variation_id]').val(),'101');
    assert.equal(p.button('S').getAttribute('aria-pressed'),'true');
    assert.equal(p.button('M').disabled,true);
    p.form.find('.reset_variations').trigger('click');
    assert.equal(p.form.find('[name=variation_id]').val(),'');
    assert.equal(p.button('S').getAttribute('aria-pressed'),'false');
    assert.equal(p.button('M').disabled,false);
    p.close();
});
test('AJAX mode uses Woo request and rejects invalid combination without fabricating ID', async () => {
    const p = await page({},true);
    p.button('S').click(); p.button('Black').click();
    assert.equal(p.requests.length,1);
    assert.equal(p.requests[0].data.attribute_pa_size,'s');
    p.requests[0].success(false);
    assert.match(p.w.document.querySelector('[role=status]').textContent,/unavailable/);
    assert.equal(p.form.find('[name=variation_id]').val(),'');
    p.button('Black').click();
    assert.equal(p.w.document.querySelector('[role=status]').textContent,'');
    p.close();
});
test('labels are text and unapproved/malicious palette values never create colour circles', async () => {
    const p = await page({palette:{attribute_pa_colour:{black:'url(javascript:alert(1))',white:'#ffffff'}}});
    assert.equal(p.w.document.querySelectorAll('.bactive-selector-options img').length,0);
    assert.equal(p.w.document.querySelectorAll('.bactive-selector-colour').length,1);
    assert.match(p.buttons()[3].textContent,/<img/);
    p.close();
});
test('explicit fallback restores native selection and removes enhancement handlers', async () => {
    const p = await page(); p.button('S').click();
    p.w.document.querySelector('.bactive-selector-fallback').click();
    assert.equal(p.w.document.querySelector('select').hidden,false);
    assert.equal(p.form.find('[name=attribute_pa_size]').val(),'s');
    assert.equal(p.buttons().length,0);
    p.form.find('[name=attribute_pa_colour]').val('black').trigger('change');
    assert.equal(p.form.find('[name=variation_id]').val(),'101');
    p.close();
});
test('external native selection changes synchronize buttons and Woo ID', async () => {
    const p = await page();
    p.form.find('[name=attribute_pa_colour]').val('white').trigger('change');
    p.form.find('[name=attribute_pa_size]').val('m').trigger('change');
    assert.equal(p.form.find('[name=variation_id]').val(),'102');
    assert.equal(p.button('M').getAttribute('aria-pressed'),'true');
    assert.equal(p.button('Black').disabled,true);
    p.close();
});
test('partial initialization failure restores all native controls', async () => {
    const p = await page({productId:99});
    p.w.bactiveCatalogVisuals = config;
    const observe = p.w.MutationObserver.prototype.observe;
    p.w.MutationObserver.prototype.observe = () => {throw new Error('simulated failure');};
    p.w.eval(source); p.form.trigger('wc_variation_form');
    assert.equal(p.buttons().length,0);
    assert.ok([...p.w.document.querySelectorAll('select')].every(select=>!select.hidden));
    p.w.MutationObserver.prototype.observe = observe;
    p.close();
});

const {test}=require('node:test');
const assert=require('node:assert/strict');
const {JSDOM}=require('jsdom');
const fs=require('node:fs');
const script=fs.readFileSync('wp-content/themes/blocksy-child/assets/js/catalogue-colour-photo.js','utf8');
function fixture(){
 const dom=new JSDOM(`<div class="product"><div class="woocommerce-product-gallery"><div class="flexy-view"><a class="ct-media-container" href="/yellow.jpg"><img src="/yellow.jpg"></a></div><div class="flexy-pills"><span tabindex="0">Next photo</span></div></div><form class="variations_form" data-product_id="1" data-bactive-selectors="ready"><div class="variations"><select name="attribute_pa_colour"><option value="">Colour</option><option value="black">Black</option><option value="white">White</option></select><select name="attribute_pa_size"><option value="">Size</option><option value="s">S</option></select></div></form></div>`,{url:'https://bactiveph.com/product/test/',runScripts:'outside-only'});
 const w=dom.window;w.eval(fs.readFileSync('wordpress/wp-includes/js/jquery/jquery.js','utf8'));const $=w.jQuery;
 w.bactiveCatalogVisuals={productId:1,previews:{attribute_pa_colour:{black:{src:'https://bactiveph.com/black.jpg',alt:'Black skort'},white:{src:'https://bactiveph.com/white.jpg',alt:'White skort'}}}};
 const pending=[];w.Image=function(){const image=w.document.createElement('img');Object.defineProperty(image,'naturalWidth',{value:1200});Object.defineProperty(image,'naturalHeight',{value:1600});pending.push(image);return image;};
 // jsdom cannot produce trusted input. Invoke the actual registered capture
 // handlers for that boundary; normal synthetic clicks still use DOM dispatch.
 const handlers=[],product=w.document.querySelector('.product'),add=product.addEventListener.bind(product);
 product.addEventListener=(type,handler,options)=>{handlers.push({type,handler});add(type,handler,options);};
 w.eval(script);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
 const form=w.document.querySelector('form'),selects=[...form.querySelectorAll('select')];
 return {dom,w,$,pending,form,selects,choose(i,v){selects[i].value=v;$(selects[i]).trigger('change');},overlay(){return w.document.querySelector('.bactive-colour-photo');},manual(type,target,key){const event={isTrusted:true,type,target,key,preventDefault(){},stopImmediatePropagation(){}};handlers.filter(row=>row.type===type).forEach(row=>row.handler.call(product,event));}};
}
const settle=()=>new Promise(resolve=>setImmediate(resolve));
test('latest colour wins; full variation and reset remove the representative without editing native image',async()=>{
 const f=fixture();f.choose(0,'black');f.choose(0,'white');f.pending[1].onload();f.pending[0].onload();
 assert.equal(f.overlay().querySelector('img').src,'https://bactiveph.com/white.jpg');
 assert.equal(f.w.document.querySelector('.ct-media-container img').src,'https://bactiveph.com/yellow.jpg');
 f.choose(1,'s');assert.equal(f.overlay(),null);await settle();assert.equal(f.overlay(),null);
 f.choose(1,'');f.pending.at(-1).onload();assert.ok(f.overlay());f.choose(0,'');assert.equal(f.overlay(),null);f.dom.window.close();
});
test('native dropdown fallback clears preview, including late loads and observer changes',async()=>{
 const f=fixture();f.choose(0,'black');f.pending.at(-1).onload();assert.ok(f.overlay());
 f.form.dataset.bactiveSelectors='fallback';await settle();assert.equal(f.overlay(),null);
 f.choose(0,'white');await settle();assert.equal(f.overlay(),null);f.dom.window.close();
 const g=fixture();g.choose(0,'black');g.form.dataset.bactiveSelectors='fallback';g.pending.at(-1).onload();await settle();assert.equal(g.overlay(),null);g.dom.window.close();
});
test('manual thumbnails suspend overlay until a selection changes; wrong native zoom is inaccessible',async()=>{
 const f=fixture();f.choose(0,'black');f.pending.at(-1).onload();const media=f.w.document.querySelector('.ct-media-container');assert.equal(media.inert,true);
 const click=new f.w.MouseEvent('click',{bubbles:true,cancelable:true});f.overlay().dispatchEvent(click);assert.equal(click.defaultPrevented,true);
 const key=new f.w.KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true});media.dispatchEvent(key);assert.equal(key.defaultPrevented,true);
 const thumb=f.w.document.querySelector('.flexy-pills span');f.manual('click',thumb);thumb.click();await settle();assert.equal(f.overlay(),null);assert.ok(!media.inert);
 f.w.document.querySelector('.flexy-view').append(f.w.document.createElement('span'));await settle();assert.equal(f.overlay(),null);
 f.choose(0,'white');f.pending.at(-1).onload();assert.ok(f.overlay());f.dom.window.close();
});
test('native synthetic pill clicks preserve a partial preview while trusted keyboard navigation clears it',async()=>{
 const f=fixture();f.choose(0,'black');f.pending.at(-1).onload();const preview=f.overlay();
 const thumb=f.w.document.querySelector('.flexy-pills span');thumb.click();await settle();assert.equal(f.overlay(),preview);
 f.manual('keydown',thumb,'Enter');thumb.click();await settle();assert.equal(f.overlay(),null);
 f.dom.window.close();
});
test('failed and external photos leave the original usable; styling does not depend on layout switch',()=>{
 const f=fixture();f.choose(0,'black');f.pending.at(-1).onerror();assert.equal(f.overlay(),null);assert.ok(!f.w.document.querySelector('.ct-media-container').inert);
 f.w.bactiveCatalogVisuals.previews.attribute_pa_colour.white.src='https://other.test/white.jpg';f.choose(0,'white');assert.equal(f.pending.length,1);
 const css=fs.readFileSync('wp-content/themes/blocksy-child/assets/css/catalog-visuals.css','utf8');assert.match(css,/^\.woocommerce-product-gallery \.bactive-colour-photo-target \{ position: relative; \}/m);f.dom.window.close();
});

test('portrait overlay resizes independently of old gallery ratio and restores exact native height',()=>{
 const f=fixture(),view=f.w.document.querySelector('.flexy-view');let width=400;
 view.getBoundingClientRect=()=>({width});view.style.setProperty('height','250px','important');
 f.choose(0,'black');f.pending.at(-1).onload();
 assert.equal(view.style.height,`${400*1600/1200}px`);assert.equal(view.style.getPropertyPriority('height'),'important');
 width=240;f.w.dispatchEvent(new f.w.Event('resize'));assert.equal(view.style.height,'320px');
 f.choose(1,'s');assert.equal(view.style.height,'250px');assert.equal(view.style.getPropertyPriority('height'),'important');
 width=300;f.w.dispatchEvent(new f.w.Event('resize'));assert.equal(view.style.height,'250px');f.dom.window.close();
});
test('rapid previews and native reset leave no stale height override',()=>{
 const f=fixture(),view=f.w.document.querySelector('.flexy-view');view.getBoundingClientRect=()=>({width:300});
 f.choose(0,'black');f.choose(0,'white');f.pending[1].onload();f.pending[0].onload();
 assert.equal(view.style.height,'400px');f.choose(0,'');f.$(f.form).trigger('reset_data');assert.equal(view.style.height,'');f.dom.window.close();
});
test('gallery sizing removes both old outer and inline image ratio constraints',()=>{
 const css=fs.readFileSync('wp-content/themes/blocksy-child/assets/css/catalog-visuals.css','utf8');
 assert.doesNotMatch(css,/aspect-ratio:\s*3\s*\/\s*4/);
 assert.match(css,/\.flexy-view \.ct-media-container\s*\{[^}]*aspect-ratio: auto !important/s);
 assert.match(css,/\.flexy-view img\s*\{[^}]*height: auto;[^}]*aspect-ratio: auto !important/s);
});

const {test}=require('node:test');
const assert=require('node:assert/strict');
const {JSDOM}=require('jsdom');
const fs=require('node:fs');
const script=fs.readFileSync('wordpress/wp-content/themes/blocksy-child/assets/js/collection-visuals.js','utf8');
function fixture(section='related',swap=false){
 const dom=new JSDOM(`<section class="${section}" data-hover="swap"><li class="bactive-collection-product"><figure><a class="ct-media-container" href="/product/test/">${swap ? '<img class="ct-swap" src="/detail.jpg" width="1200" height="800">' : ''}<img src="/old.jpg" width="800" height="1200" srcset="/old-small.jpg 200w"></a></figure><h2 class="woocommerce-loop-product__title">Test dress</h2><a class="button" href="/product/test/">Select options</a><a class="bactive-colour-preview" href="/product/test/?colour=white" data-preview-src="/white.jpg">White</a><a class="bactive-colour-preview" href="/product/test/?colour=black" data-preview-src="/black.jpg">Black</a><a class="bactive-colour-preview" href="/product/test/?colour=bad" data-preview-src="https://other.test/bad.jpg">Bad</a></li></section>`,{url:'https://bactiveph.com/product/current/',runScripts:'outside-only'});
 const pending=[];dom.window.Image=class{constructor(){this.naturalWidth=800;this.naturalHeight=1200;pending.push(this);}set src(v){this.url=v;}};
 dom.window.eval(script);dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));
 return {dom,pending,links:dom.window.document.querySelectorAll('.bactive-colour-preview'),img:dom.window.document.querySelector('img')};
}
function click(f,i){return f.links[i].dispatchEvent(new f.dom.window.MouseEvent('click',{bubbles:true,cancelable:true,button:0}));}
test('related previews stay on page; newest successful image wins; cart links stay intact',()=>{
 const f=fixture();assert.equal(click(f,0),false);assert.equal(click(f,1),false);f.pending[1].onload();f.pending[0].onload();
 assert.equal(f.img.src,'https://bactiveph.com/black.jpg');assert.equal(f.img.getAttribute('srcset'),null);assert.equal(f.links[1].getAttribute('aria-pressed'),'true');assert.equal(f.links[0].getAttribute('aria-pressed'),'false');assert.equal(f.dom.window.location.pathname,'/product/current/');assert.equal(f.dom.window.document.querySelector('.button').getAttribute('href'),'/product/test/');assert.equal(f.dom.window.document.querySelector('figure a').getAttribute('href'),'/product/test/');f.dom.window.close();
});
test('space operates preview; failed load preserves old image and restores link semantics',()=>{
 const f=fixture();assert.equal(f.links[0].dispatchEvent(new f.dom.window.KeyboardEvent('keydown',{key:' ',bubbles:true,cancelable:true})),false);f.pending[0].onerror();assert.equal(f.img.src,'https://bactiveph.com/old.jpg');assert.equal(f.links[0].getAttribute('role'),null);assert.equal(f.links[0].getAttribute('href'),'/product/test/?colour=white');click(f,1);f.pending[1].onload();assert.equal(f.links[0].getAttribute('aria-pressed'),null);assert.equal(f.links[2].getAttribute('aria-pressed'),null);f.dom.window.close();
});
test('external images are not enhanced and modified click retains navigation',()=>{
 const f=fixture();assert.equal(f.links[2].getAttribute('role'),null);const e=new f.dom.window.MouseEvent('click',{ctrlKey:true,cancelable:true});f.links[0].dispatchEvent(e);assert.equal(e.defaultPrevented,false);assert.equal(f.pending.length,0);f.dom.window.close();
});
test('ordinary collection card previews without changing product links',()=>{
 const f=fixture('products');
 assert.equal(click(f,0),false);f.pending[0].onload();
 assert.equal(f.img.src,'https://bactiveph.com/white.jpg');
 assert.equal(f.links[0].getAttribute('aria-pressed'),'true');
 assert.equal(f.dom.window.document.querySelector('.button').getAttribute('href'),'/product/test/');
 f.dom.window.close();
});
test('newly inserted collection cards receive previews once',async()=>{
 const f=fixture('products');
 const card=f.dom.window.document.querySelector('.bactive-collection-product').cloneNode(true);
 f.dom.window.document.querySelector('.products').append(card);
 await new Promise(resolve=>f.dom.window.queueMicrotask(resolve));
 const link=card.querySelector('.bactive-colour-preview');
 assert.equal(link.dispatchEvent(new f.dom.window.MouseEvent('click',{bubbles:true,cancelable:true,button:0})),false);
 assert.equal(f.pending.length,1);f.pending[0].onload();
 assert.equal(card.querySelector('img').src,'https://bactiveph.com/white.jpg');
 f.dom.window.close();
});

test('hover exception uses its native ratio, then selected portrait wins over hover',()=>{
 const f=fixture('products',true),frame=f.dom.window.document.querySelector('.ct-media-container');
 assert.equal(frame.style.aspectRatio,'800 / 1200');
 frame.dispatchEvent(new f.dom.window.MouseEvent('mouseenter'));assert.equal(frame.style.aspectRatio,'1200 / 800');
 frame.dispatchEvent(new f.dom.window.MouseEvent('mouseleave'));assert.equal(frame.style.aspectRatio,'800 / 1200');
 frame.dispatchEvent(new f.dom.window.MouseEvent('mouseenter'));click(f,0);f.pending[0].onload();assert.equal(frame.style.aspectRatio,'800 / 1200');
 frame.dispatchEvent(new f.dom.window.MouseEvent('mouseenter'));assert.equal(frame.style.aspectRatio,'800 / 1200');f.dom.window.close();
});
test('collection CSS cannot impose old 3:4 ratio on a delivered portrait',()=>{
 const css=fs.readFileSync('wp-content/themes/blocksy-child/assets/css/collection-visuals.css','utf8');
 assert.doesNotMatch(css,/aspect-ratio:\s*3\s*\/\s*4/);assert.match(css,/aspect-ratio: auto 2 \/ 3 !important/);
});

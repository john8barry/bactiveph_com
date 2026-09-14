const {test}=require('node:test');
const assert=require('node:assert/strict');
const {JSDOM}=require('jsdom');
const fs=require('node:fs');
const script=fs.readFileSync('wordpress/wp-content/themes/blocksy-child/assets/js/collection-visuals.js','utf8');
function fixture(){
 const dom=new JSDOM(`<section class="related"><li class="bactive-collection-product"><figure><a class="ct-media-container" href="/product/test/"><img src="/old.jpg" srcset="/old-small.jpg 200w"></a></figure><h2 class="woocommerce-loop-product__title">Test dress</h2><a class="button" href="/product/test/">Select options</a><a class="bactive-colour-preview" href="/product/test/?colour=white" data-preview-src="/white.jpg">White</a><a class="bactive-colour-preview" href="/product/test/?colour=black" data-preview-src="/black.jpg">Black</a><a class="bactive-colour-preview" href="/product/test/?colour=bad" data-preview-src="https://other.test/bad.jpg">Bad</a></li></section>`,{url:'https://bactiveph.com/product/current/',runScripts:'outside-only'});
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

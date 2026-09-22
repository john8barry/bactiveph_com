const {test} = require('node:test');
const assert = require('node:assert/strict');
const {JSDOM} = require('jsdom');
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
const script = fs.readFileSync('wordpress/wp-content/themes/blocksy-child/assets/js/header-sage.js', 'utf8');
// Use actual rendered navigation, including the default-open mobile Shop group.
const markup = execFileSync('php', ['-r', 'ob_start(); require "tests/sage-header-contract.php"; ob_end_clean(); echo $markup;'], {encoding:'utf8'});
function fixture({mobile=false, y=0, height=900, adminBottom=0}={}) {
    const dom = new JSDOM(`<div id="wpadminbar"></div><header id="header" class="bactive-header--sage">${markup}</header><button id="outside">Outside</button>`, {url:'https://bactiveph.com', runScripts:'outside-only'});
    const w=dom.window, d=w.document, h=d.querySelector('header');
    const media = new w.EventTarget(); media.matches=!mobile;
    w.matchMedia=()=>media; w.scrollY=y; w.innerHeight=height;
    w.visualViewport=new w.EventTarget(); w.visualViewport.height=height; w.visualViewport.offsetTop=0;
    let frames=[];
    w.requestAnimationFrame=callback=>{frames.push(callback);return frames.length;};
    w.scrollBy=options=>{w.lastScroll=options;};
    d.querySelector('#wpadminbar').getBoundingClientRect=()=>({bottom:adminBottom});
    h.getBoundingClientRect=()=>({bottom:adminBottom+(media.matches?(h.classList.contains('bactive-header--compact')?78:110):(h.classList.contains('bactive-header--compact')?68:96))});
    const flush=()=>{const pending=frames;frames=[];pending.forEach(f=>f());};
    const scroll=value=>{w.scrollY=value;w.dispatchEvent(new w.Event('scroll'));flush();};
    const toggle=(el,open)=>{el.open=open;el.dispatchEvent(new w.Event('toggle'));flush();};
    w.eval(script);
    return {w,d,h,media,flush,scroll,toggle,compact:()=>h.classList.contains('bactive-header--compact'),frames:()=>frames.length,close:()=>dom.window.close()};
}
test('threshold hysteresis, stable spacer and one initialization',()=>{
    const f=fixture();assert.equal(f.compact(),false);
    f.scroll(72);assert.equal(f.compact(),false);f.scroll(73);assert.equal(f.compact(),true);
    f.scroll(40);assert.equal(f.compact(),true);f.scroll(16);assert.equal(f.compact(),false);
    f.w.eval(script);assert.equal(f.d.querySelectorAll('.bactive-header-spacer').length,1);
    assert.equal(f.d.querySelector('.bactive-header-spacer').style.height,'');
    f.close();
});
test('restored scroll and pageshow synchronize without a user scroll',()=>{
    const f=fixture({y:450});assert.equal(f.compact(),true);
    f.w.scrollY=0;f.w.dispatchEvent(new f.w.Event('pageshow'));f.flush();assert.equal(f.compact(),false);f.close();
});
test('desktop disclosures lock both states; hidden mobile open Shop does not',()=>{
    const f=fixture(); const shop=f.d.querySelector('.bactive-header__shop');
    f.scroll(100);assert.equal(f.compact(),true);
    f.toggle(shop,true);f.scroll(0);assert.equal(f.compact(),true);
    f.toggle(shop,false);assert.equal(f.compact(),false);
    f.toggle(shop,true);f.scroll(150);assert.equal(f.compact(),false);
    f.toggle(shop,false);assert.equal(f.compact(),true);f.close();
});
test('mobile menu and visible keyboard focus hold size until interaction ends',()=>{
    const f=fixture({mobile:true}); const menu=f.d.querySelector('.bactive-header__mobile-menu');
    f.toggle(menu,true);f.scroll(200);assert.equal(f.compact(),false);
    f.toggle(menu,false);assert.equal(f.compact(),true);
    const summary=menu.querySelector('summary');summary.focus();f.scroll(0);assert.equal(f.compact(),true);
    f.d.querySelector('#outside').focus();f.flush();assert.equal(f.compact(),false);f.close();
});
test('nested Escape restores focus and breakpoint change closes mobile menu',()=>{
    const f=fixture({mobile:true});const menu=f.d.querySelector('.bactive-header__mobile-menu');
    f.toggle(menu,true);const men=menu.querySelector('.bactive-header__men');f.toggle(men,true);
    men.querySelector('a').focus();men.querySelector('a').dispatchEvent(new f.w.KeyboardEvent('keydown',{key:'Escape',bubbles:true,cancelable:true}));
    assert.equal(men.open,false);assert.equal(menu.open,true);assert.equal(f.d.activeElement,men.querySelector('summary'));
    f.media.matches=true;f.media.dispatchEvent(new f.w.Event('change'));f.flush();
    assert.equal(menu.open,false);assert.equal(f.d.activeElement,f.d.querySelector('.bactive-header__desktop .site-logo-container'));f.close();
});
test('short layout viewport disables fixed mode; keyboard visual viewport only constrains panels',()=>{
    const f=fixture({mobile:true,height:200});assert.equal(f.h.classList.contains('bactive-header--fixed'),false);
    assert.equal(f.d.querySelector('.bactive-header-spacer').hidden,true);
    f.w.innerHeight=800;f.w.dispatchEvent(new f.w.Event('resize'));f.flush();assert.equal(f.h.classList.contains('bactive-header--fixed'),true);
    f.w.visualViewport.height=180;f.w.visualViewport.dispatchEvent(new f.w.Event('resize'));f.flush();
    assert.equal(f.h.classList.contains('bactive-header--fixed'),true);assert.equal(f.h.style.getPropertyValue('--header-panel-space'),'60px');f.close();
});
test('admin offset and focus clearance account for occupied header space',()=>{
    const f=fixture({adminBottom:32});assert.equal(f.h.style.getPropertyValue('--header-admin-offset'),'32px');
    assert.equal(f.d.documentElement.style.getPropertyValue('--bactive-header-clearance'),'154px');
    const outside=f.d.querySelector('#outside');outside.getBoundingClientRect=()=>({top:80,bottom:124,height:44});outside.focus();f.flush();
    assert.equal(f.w.lastScroll.top,-74);f.close();
});
test('scroll bursts coalesce and missing header is safe',()=>{
    const f=fixture();for(let i=0;i<20;i++)f.w.dispatchEvent(new f.w.Event('scroll'));
    assert.equal(f.frames(),1);f.flush();f.close();
    const dom=new JSDOM('',{runScripts:'outside-only'});assert.doesNotThrow(()=>dom.window.eval(script));dom.window.close();
});
test('open mobile menu survives resize; closing reevaluates scroll and admin offset',()=>{
    const f=fixture({mobile:true,adminBottom:46});const menu=f.d.querySelector('.bactive-header__mobile-menu');
    f.toggle(menu,true);f.w.innerHeight=390;f.w.visualViewport.height=390;
    f.w.dispatchEvent(new f.w.Event('resize'));f.scroll(300);
    assert.equal(menu.open,true);assert.equal(f.compact(),false);
    f.d.querySelector('#wpadminbar').getBoundingClientRect=()=>({bottom:-254});
    f.toggle(menu,false);assert.equal(f.compact(),true);
    assert.equal(f.h.style.getPropertyValue('--header-admin-offset'),'0px');f.close();
});

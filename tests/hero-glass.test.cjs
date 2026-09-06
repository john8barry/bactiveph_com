"use strict";

const assert = require("node:assert/strict");
const { setMaxListeners } = require("node:events");
const { readFileSync } = require("node:fs");
const path = require("node:path");
const test = require("node:test");
const vm = require("node:vm");

const source = readFileSync(path.join(__dirname,
    "../wordpress/wp-content/themes/blocksy-child/assets/js/hero-glass.js"), "utf8");
const finePointer = "(hover: hover) and (pointer: fine)";
const reducedMotion = "(prefers-reduced-motion: reduce)";

function createPage() {
    const styles = new Map();
    const attributes = new Map();
    const frames = new Map();
    const media = new Map();
    const observers = [];
    let nextFrame = 0;
    let time = 0;
    let writes = 0;
    const bounds = { left: 100, top: 50, width: 800, height: 480 };
    const card = Object.assign(new EventTarget(), {
        isConnected: true,
        getBoundingClientRect: () => ({ ...bounds }),
        style: {
            setProperty(name, value) { styles.set(name, value); writes += 1; },
            removeProperty(name) { styles.delete(name); },
        },
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: name => attributes.delete(name),
    });
    const document = Object.assign(new EventTarget(), {
        hidden: false,
        readyState: "complete",
        querySelectorAll: selector => selector === ".home .hero-glass-card" ? [card] : [],
    });
    class SimulatedObserver {
        constructor(callback) { this.callback = callback; observers.push(this); }
        observe(target) { this.target = target; }
        disconnect() { this.target = null; }
        notify(states) {
            this.callback(states.map(isIntersecting => ({ target: this.target, isIntersecting })));
        }
    }
    class BrowserAbortController extends AbortController {
        constructor() {
            super();
            // Browser AbortSignals do not impose Node's listener warning threshold.
            setMaxListeners(0, this.signal);
        }
    }
    const window = Object.assign(new EventTarget(), {
        CSS: { supports: () => true },
        innerWidth: 1440,
        innerHeight: 900,
        IntersectionObserver: SimulatedObserver,
    });
    const matchMedia = query => {
        if (!media.has(query)) media.set(query, Object.assign(new EventTarget(), {
            matches: query === finePointer,
        }));
        return media.get(query);
    };
    vm.runInNewContext(source, {
        window, document, CSS: window.CSS, Event, matchMedia,
        AbortController: BrowserAbortController,
        IntersectionObserver: SimulatedObserver,
        requestAnimationFrame: callback => { frames.set(++nextFrame, callback); return nextFrame; },
        cancelAnimationFrame: id => frames.delete(id),
    }, { filename: "hero-glass.js" });

    function frame() {
        time += 16;
        const callbacks = [...frames.values()];
        frames.clear();
        callbacks.forEach(callback => callback(time));
    }
    return {
        card, document, window, bounds,
        observer: observers[0],
        pending: () => frames.size,
        writes: () => writes,
        light: () => Object.fromEntries(styles),
        frame,
        settle() {
            let count = 0;
            while (frames.size && count < 120) { frame(); count += 1; }
            assert.equal(frames.size, 0, "animation must settle instead of scheduling forever");
            return count;
        },
        pointer(type = "pointermove", pointerType = "mouse", clientX = 700, clientY = 290) {
            card.dispatchEvent(Object.assign(new Event(type), { pointerType, clientX, clientY }));
        },
        preference(query, matches) {
            const target = matchMedia(query);
            target.matches = matches;
            target.dispatchEvent(new Event("change"));
        },
    };
}

function assertReset(page) {
    assert.equal(page.pending(), 0, "reset must cancel pending animation");
    assert.deepEqual(page.light(), {}, "reset must return the light to its CSS defaults");
}

test("visible pointer input works after a stale offscreen observer notification", () => {
    const page = createPage();
    assert.ok(page.bounds.top + page.bounds.height < page.window.innerHeight);
    assert.equal(page.card.isConnected, true);
    page.observer.notify([false]);
    page.pointer("pointerenter");
    page.pointer();
    assert.equal(page.pending(), 1, "real pointer input must reactivate the visible hero");
    page.settle();
    assert.deepEqual(page.light(), { "--hero-glass-x": "75.00%", "--hero-glass-y": "50.00%" });
});

test("pointer events coalesce and the animation stops after reaching the pointer", () => {
    const page = createPage();
    for (let i = 0; i < 20; i += 1) page.pointer();
    assert.equal(page.pending(), 1);
    assert.ok(page.settle() > 1, "the existing gradual follow must be preserved");
    assert.deepEqual(page.light(), { "--hero-glass-x": "75.00%", "--hero-glass-y": "50.00%" });
    const writes = page.writes();
    page.frame();
    assert.equal(page.writes(), writes, "settled light must not keep repainting");
});

test("a batch ending in visible does not reset the active light", () => {
    const page = createPage();
    page.pointer();
    page.frame();
    const light = page.light();
    page.observer.notify([false, true]);
    assert.deepEqual(page.light(), light);
    assert.equal(page.pending(), 1);
    page.settle();
});

test("a batch ending offscreen clears the light and cancels pending work", () => {
    const page = createPage();
    page.pointer();
    page.frame();
    page.bounds.top = page.window.innerHeight + 100;
    page.observer.notify([true, false]);
    assertReset(page);
    page.frame();
    assertReset(page);
});

test("an empty observer batch leaves the active light alone", () => {
    const page = createPage();
    page.pointer();
    page.frame();
    const light = page.light();
    assert.doesNotThrow(() => page.observer.notify([]));
    assert.deepEqual(page.light(), light);
    assert.equal(page.pending(), 1);
    page.settle();
});

test("reduced motion cancels active work and blocks further pointer animation", () => {
    const page = createPage();
    page.pointer();
    page.frame();
    page.preference(reducedMotion, true);
    assertReset(page);
    page.pointer();
    assertReset(page);
});

test("touch and coarse-pointer input retain static glass", () => {
    const page = createPage();
    page.pointer("pointerenter", "touch");
    page.pointer("pointermove", "touch");
    assertReset(page);
    page.preference(finePointer, false);
    page.pointer();
    assertReset(page);
});

test("leaving, scrolling, hiding, and resizing reset the decoration", () => {
    for (const [surface, event] of [
        ["card", "pointerleave"], ["card", "pointercancel"],
        ["window", "blur"], ["window", "pagehide"],
        ["window", "scroll"], ["window", "resize"],
        ["document", "visibilitychange"],
    ]) {
        const page = createPage();
        page.pointer();
        page.frame();
        page[surface].dispatchEvent(new Event(event));
        assertReset(page);
    }
});

test("a hidden document blocks pointer input even without an observer notification", () => {
    const page = createPage();
    page.document.hidden = true;
    page.pointer();
    assertReset(page);
});

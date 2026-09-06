/* Optional homepage enhancement. Load with defer after hero-glass.css. */
(() => {
    "use strict";

    function mount() {
        const cards = document.querySelectorAll(".home .hero-glass-card");
        if (cards.length !== 1) return;
        const card = cards[0];
        const disposeEvent = "bactive:hero-glass:dispose";

        /* Reapplying a preview disposes the previous instance first. */
        card.dispatchEvent(new Event(disposeEvent));
        if (!window.CSS || !(CSS.supports("backdrop-filter", "blur(1px)") ||
            CSS.supports("-webkit-backdrop-filter", "blur(1px)"))) return;

        const controller = new AbortController();
        const options = { passive: true, signal: controller.signal };
        const queries = [
            matchMedia("(hover: hover) and (pointer: fine)"),
            matchMedia("(prefers-reduced-motion: reduce)"),
            matchMedia("(prefers-reduced-transparency: reduce)"),
            matchMedia("(prefers-contrast: more)"),
            matchMedia("(forced-colors: active)")
        ];
        let frame = 0;
        let previousTime = 0;
        let point = null;
        let x = 0.18;
        let y = 0.08;
        let disposed = false;

        const enabled = () => !disposed && !document.hidden &&
            queries[0].matches && !queries.slice(1).some(query => query.matches);

        function reset() {
            cancelAnimationFrame(frame);
            frame = 0;
            point = null;
            previousTime = 0;
            x = 0.18;
            y = 0.08;
            card.style.removeProperty("--hero-glass-x");
            card.style.removeProperty("--hero-glass-y");
        }

        function paint(time) {
            frame = 0;
            if (!point || !enabled() || !card.isConnected) {
                reset();
                return;
            }
            /* One geometry read and two style writes per frame, scoped to the hero. */
            const bounds = card.getBoundingClientRect();
            if (!bounds.width || !bounds.height) {
                reset();
                return;
            }
            const targetX = Math.max(0, Math.min(1, (point.x - bounds.left) / bounds.width));
            const targetY = Math.max(0, Math.min(1, (point.y - bounds.top) / bounds.height));
            const elapsed = previousTime ? Math.min(time - previousTime, 64) : 16;
            const follow = 1 - Math.exp(-elapsed / 70);
            previousTime = time;
            x += (targetX - x) * follow;
            y += (targetY - y) * follow;
            const settled = Math.abs(targetX - x) + Math.abs(targetY - y) < 0.002;
            if (settled) {
                x = targetX;
                y = targetY;
            }
            card.style.setProperty("--hero-glass-x", `${(x * 100).toFixed(2)}%`);
            card.style.setProperty("--hero-glass-y", `${(y * 100).toFixed(2)}%`);
            if (!settled) frame = requestAnimationFrame(paint);
        }

        function followPointer(event) {
            if (event.pointerType === "touch" || !enabled()) return;
            point = { x: event.clientX, y: event.clientY };
            if (!frame) {
                previousTime = 0;
                frame = requestAnimationFrame(paint);
            }
        }

        const observer = "IntersectionObserver" in window ? new IntersectionObserver(entries => {
            // Observer notifications can lag pointer input; use them only to stop work.
            const latest = entries[entries.length - 1];
            if (latest && !latest.isIntersecting) reset();
        }) : null;

        function dispose() {
            disposed = true;
            reset();
            observer?.disconnect();
            controller.abort();
            card.removeAttribute("data-hero-glass-interactive");
        }

        card.addEventListener(disposeEvent, dispose, { once: true, signal: controller.signal });
        card.addEventListener("pointerenter", followPointer, options);
        card.addEventListener("pointermove", followPointer, options);
        card.addEventListener("pointerleave", reset, options);
        card.addEventListener("pointercancel", reset, options);
        window.addEventListener("blur", reset, options);
        window.addEventListener("pagehide", reset, options);
        window.addEventListener("scroll", reset, options);
        window.addEventListener("resize", reset, options);
        document.addEventListener("visibilitychange", reset, options);
        queries.forEach(query => query.addEventListener("change", reset, options));
        observer?.observe(card);
        card.setAttribute("data-hero-glass-interactive", "true");
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", mount, { once: true });
    } else {
        mount();
    }
})();

/* Colour previews never select a purchasable variation or change cart actions. */
(() => {
  'use strict';
  const initializedLinks = new WeakSet();
  const initializedFrames = new WeakSet();
  function init(root = document) {
    const cards = [root, ...root.querySelectorAll('.bactive-collection-product')]
      .filter(node => node.matches?.('.bactive-collection-product'));
    cards.forEach(card => {
      const images = card.querySelectorAll(':scope > figure > a.ct-media-container > img:not(.ct-swap)');
      if (images.length !== 1) return;
      const image = images[0];
      const frame = image.parentElement;
      if (!initializedFrames.has(frame)) {
        initializedFrames.add(frame);
        let hovering = false;
        const swap = frame.querySelector('img.ct-swap');
        function sizeFrame() {
          const swapping = hovering && swap && frame.closest('[data-hover="swap"]') &&
            (!window.matchMedia || window.matchMedia('(hover: hover)').matches);
          const active = swapping && !card.classList.contains('bactive-card-preview-selected') ? swap : image;
          const width = active.naturalWidth || Number(active.getAttribute('width'));
          const height = active.naturalHeight || Number(active.getAttribute('height'));
          if (!(width > 0 && height > 0)) return;
          frame.style.aspectRatio = `${width} / ${height}`;
          frame.classList.add('bactive-photo-frame-ready');
        }
        frame.addEventListener('mouseenter', () => { hovering = true; sizeFrame(); });
        frame.addEventListener('mouseleave', () => { hovering = false; sizeFrame(); });
        image.addEventListener('load', sizeFrame);
        swap?.addEventListener('load', sizeFrame);
        new MutationObserver(sizeFrame).observe(image, {attributes: true, attributeFilter: ['src', 'width', 'height']});
        sizeFrame();
      }
      const links = [...card.querySelectorAll('a.bactive-colour-preview[data-preview-src]')];
      let sequence = 0;
      links.forEach(link => {
        if (initializedLinks.has(link)) return;
        let source;
        try {
          source = new URL(link.dataset.previewSrc, location.href);
          if (source.origin !== location.origin || !/^https?:$/.test(source.protocol)) return;
        } catch (_) { return; }
        initializedLinks.add(link);
        link.setAttribute('role', 'button');
        link.setAttribute('aria-pressed', 'false');
        function preview(event) {
          if (event.type === 'click' && (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) return;
          event.preventDefault();
          const request = ++sequence;
          const pending = new Image();
          link.setAttribute('aria-busy', 'true');
          pending.onload = () => {
            link.removeAttribute('aria-busy');
            if (request !== sequence) return;
            image.removeAttribute('srcset');
            image.removeAttribute('sizes');
            image.src = source.href;
            image.width = pending.naturalWidth;
            image.height = pending.naturalHeight;
            image.removeAttribute('title');
            card.classList.add('bactive-card-preview-selected');
            frame.style.aspectRatio = `${pending.naturalWidth} / ${pending.naturalHeight}`;
            frame.classList.add('bactive-photo-frame-ready');
            image.alt = `${card.querySelector('.woocommerce-loop-product__title')?.textContent.trim() || ''} — ${link.textContent.trim()}`;
            links.filter(item => item.getAttribute('role') === 'button').forEach(item => item.setAttribute('aria-pressed', String(item === link)));
          };
          pending.onerror = () => {
            link.removeAttribute('aria-busy');
            if (request !== sequence) return;
            // Restore ordinary navigation if the requested preview cannot load.
            link.removeAttribute('role');
            link.removeAttribute('aria-pressed');
            link.removeEventListener('click', preview);
            link.removeEventListener('keydown', keyboard);
          };
          pending.src = source.href;
        }
        function keyboard(event) {
          if (event.key === ' ') preview(event);
        }
        link.addEventListener('click', preview);
        link.addEventListener('keydown', keyboard);
      });
    });
  }
  function start() {
    init();
    new MutationObserver(records => {
      records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeType === 1) init(node);
      }));
    }).observe(document.body, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();

/* Colour previews never select a purchasable variation or change cart actions. */
(() => {
  'use strict';
  function init(root = document) {
    root.querySelectorAll('.related .bactive-collection-product').forEach(card => {
      const images = card.querySelectorAll(':scope > figure > a.ct-media-container > img:not(.ct-swap)');
      if (images.length !== 1) return;
      const image = images[0];
      const links = [...card.querySelectorAll('a.bactive-colour-preview[data-preview-src]')];
      let sequence = 0;
      links.forEach(link => {
        if (link.dataset.previewReady) return;
        let source;
        try {
          source = new URL(link.dataset.previewSrc, location.href);
          if (source.origin !== location.origin || !/^https?:$/.test(source.protocol)) return;
        } catch (_) { return; }
        link.dataset.previewReady = 'true';
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
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
  else init();
})();

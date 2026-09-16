/* A colour-only photograph is a preview; native Woo resolves complete combinations. */
(() => {
  'use strict';
  function init() {
    const config = window.bactiveCatalogVisuals;
    if (!config?.previews || !window.jQuery) return;
    const form = document.querySelector(`.variations_form[data-product_id="${Number(config.productId)}"]`);
    if (!form || form.dataset.bactivePhotoPreview) return;
    form.dataset.bactivePhotoPreview = 'ready';
    const product = form.closest('.product');
    let sequence = 0;
    let current = '';
    let overlay;
    let previewTarget;
    let browsingGallery = false;
    let hiddenMedia = [];
    let restoreFrame = () => {};
    function clear() {
      sequence++; current = '';
      restoreFrame(); restoreFrame = () => {};
      overlay?.remove(); overlay = null;
      previewTarget?.classList.remove('bactive-colour-photo-target'); previewTarget = null;
      hiddenMedia.forEach(([media, inert]) => { media.inert = inert; });
      hiddenMedia = [];
    }
    function sync() {
      if (browsingGallery || form.dataset.bactiveSelectors === 'fallback') { clear(); return; }
      const selects = [...form.querySelectorAll('.variations select')];
      const colours = selects.filter(select => ['attribute_pa_colour', 'attribute_pa_color'].includes(select.name) && select.value);
      if (selects.every(select => select.value) || colours.length !== 1) { clear(); return; }
      const colour = colours[0];
      const photo = config.previews[colour.name]?.[colour.value];
      const target = product?.querySelector('.woocommerce-product-gallery .flexy-view');
      if (!photo || !target) { clear(); return; }
      let source;
      try { source = new URL(photo.src, location.href); } catch (_) { clear(); return; }
      if (source.origin !== location.origin || !['http:', 'https:'].includes(source.protocol)) { clear(); return; }
      if (current === source.href && overlay?.isConnected) return;
      clear();
      current = source.href;
      const request = sequence;
      const pending = new Image();
      pending.onload = () => {
        if (request !== sequence || !target.isConnected || browsingGallery || form.dataset.bactiveSelectors === 'fallback') return;
        // Recheck values after loading; never cover a selected native variation.
        if (selects.every(select => select.value) || config.previews[colour.name]?.[colour.value]?.src !== source.href) { clear(); return; }
        if (!(pending.naturalWidth > 0 && pending.naturalHeight > 0)) { clear(); return; }
        // Flexy retains its own live slide height underneath this temporary
        // override. Remove only our override when native variation/gallery wins.
        const previousHeight = target.style.getPropertyValue('height');
        const previousPriority = target.style.getPropertyPriority('height');
        const resize = () => {
          const width = target.getBoundingClientRect().width;
          if (width > 0) target.style.setProperty('height', `${width * pending.naturalHeight / pending.naturalWidth}px`, 'important');
        };
        const observer = window.ResizeObserver ? new ResizeObserver(resize) : null;
        observer?.observe(target);
        window.addEventListener('resize', resize);
        restoreFrame = () => {
          observer?.disconnect();
          window.removeEventListener('resize', resize);
          if (previousHeight) target.style.setProperty('height', previousHeight, previousPriority);
          else target.style.removeProperty('height');
        };
        resize();
        overlay = document.createElement('div');
        overlay.className = 'bactive-colour-photo';
        previewTarget = target; target.classList.add('bactive-colour-photo-target');
        // A preview has no native zoom source. Keep the covered gallery inert
        // until a size is selected or the customer deliberately browses it.
        hiddenMedia = [...target.querySelectorAll('.ct-media-container')].map(media => [media, media.inert]);
        hiddenMedia.forEach(([media]) => { media.inert = true; });
        pending.alt = String(photo.alt || '');
        pending.width = pending.naturalWidth; pending.height = pending.naturalHeight;
        overlay.append(pending); target.append(overlay);
      };
      pending.onerror = () => { if (request === sequence) clear(); };
      pending.src = source.href;
    }
    const $form = window.jQuery(form);
    $form.on('change.bactiveColourPhoto', '.variations select', () => { browsingGallery = false; sync(); });
    $form.on('found_variation.bactiveColourPhoto reset_data.bactiveColourPhoto', sync);
    product?.addEventListener('click', event => {
      const gallery = event.target.closest('.woocommerce-product-gallery');
      if (!gallery) return;
      // Blocksy also clicks pills when committing/resetting a variation. Only
      // customer navigation suspends the colour-only representative preview.
      if (event.isTrusted && event.target.closest('.flexy-pills, .flexy-arrow-prev, .flexy-arrow-next')) {
        browsingGallery = true; clear(); return;
      }
      if (overlay && event.target.closest('.flexy-view, .woocommerce-product-gallery__trigger')) {
        event.preventDefault(); event.stopImmediatePropagation();
      }
    }, true);
    product?.addEventListener('keydown', event => {
      if (!event.target.closest('.woocommerce-product-gallery')) return;
      // Capture keyboard intent before the accessible thumbnail handler emits
      // its synthetic click, which must remain distinct from a native update.
      if (event.isTrusted && ['Enter', ' '].includes(event.key) && event.target.closest('.flexy-pills, .flexy-arrow-prev, .flexy-arrow-next')) {
        browsingGallery = true; clear(); return;
      }
      if (overlay && ['Enter', ' '].includes(event.key) && event.target.closest('.flexy-view, .woocommerce-product-gallery__trigger')) {
        event.preventDefault(); event.stopImmediatePropagation();
      }
    }, true);
    // Native AJAX may replace the gallery; retain only the still-current colour preview.
    if (product) new MutationObserver(sync).observe(product, {childList: true, subtree: true});
    new MutationObserver(sync).observe(form, {attributes: true, attributeFilter: ['data-bactive-selectors']});
    sync();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

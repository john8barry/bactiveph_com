/* Shared presentation works for simple products and native selector fallbacks. */
(() => {
  'use strict';
  function init() {
    if (!document.body.classList.contains('bactive-product-page')) return;
    const product = document.querySelector('.product-entry-wrapper');
    if (!product || product.dataset.bactiveLayout) return;
    product.dataset.bactiveLayout = 'ready';
    const summary = product.querySelector('.summary');
    const description = summary?.querySelector(':scope > .woocommerce-product-details__short-description');
    const purchase = summary?.querySelector('.ct-product-add-to-cart, form.cart');
    if (description && purchase && !summary.querySelector('.bactive-product-details')) {
      const details = document.createElement('details');
      const heading = document.createElement('summary');
      details.className = 'bactive-product-details';
      heading.textContent = 'Details & fit';
      details.append(heading, description);
      purchase.after(details);
    }
    function thumbnails() {
      product.querySelectorAll('.woocommerce-product-gallery .flexy-pills li > span').forEach(target => {
        target.setAttribute('role', 'button');
        target.setAttribute('tabindex', '0');
        target.setAttribute('aria-pressed', String(target.parentElement.classList.contains('active')));
      });
    }
    thumbnails();
    new MutationObserver(thumbnails).observe(product, {childList: true, subtree: true, attributes: true, attributeFilter: ['class']});
    product.addEventListener('keydown', event => {
      if (!['Enter', ' '].includes(event.key) || event.defaultPrevented) return;
      const target = event.target.closest('.woocommerce-product-gallery .flexy-pills li > span');
      if (!target || !product.contains(target)) return;
      event.preventDefault(); target.click();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

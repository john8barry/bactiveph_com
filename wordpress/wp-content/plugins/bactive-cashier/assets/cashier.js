/* B Active cashier. Server responses are the authority for every sale and payment. */
(() => {
  'use strict';
  const config = window.BActiveCashier;
  const root = document.getElementById('bactive-cashier');
  if (!config || !root) return;
  const storageKey = `bactive-cashier-sale:${config.staff.id}`;
  const state = { products: [], basket: new Map(), sale: null, key: '', busy: false, error: '', email: '', received: '', invoice: '', search: '', loading: true, uncertain: false };
  let timer, searchTimer, searchSequence = 0, createPayload = null;
  const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
  const el = (tag, text, className) => { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (className) node.className = className; return node; };
  const button = (label, action, variant = '') => { const node = el('button', label, `bc-button ${variant}`); node.type = 'button'; node.disabled = state.busy; node.addEventListener('click', action); return node; };
  function field(label, type, value, onInput, attrs = {}) {
    const wrap = el('label', undefined, 'bc-field'); wrap.append(el('span', label));
    const input = el('input'); input.type = type; input.value = value;
    Object.entries(attrs).forEach(([key, val]) => input.setAttribute(key, val));
    input.addEventListener('input', () => onInput(input.value, input)); wrap.append(input); return wrap;
  }
  function remember(key) { state.key = key; try { key ? localStorage.setItem(storageKey, key) : localStorage.removeItem(storageKey); } catch (_) { /* The same in-memory sale still prevents duplicate taps. */ } }
  async function api(path, payload) {
    const response = await fetch(config.api + path, { method: payload === undefined ? 'GET' : 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json' }, ...(payload === undefined ? {} : { body: JSON.stringify(payload) }) });
    let data; try { data = await response.json(); } catch (_) { throw new Error('The server response could not be read. Check this sale before collecting payment again.'); }
    if (!response.ok) { if (data.data && /^[0-9a-f-]{36}$/i.test(data.data.key || '')) remember(data.data.key); throw new Error(data.message || 'The request failed. Refresh this sale or ask a manager.'); }
    return data;
  }
  function safeUrl(value) { if (typeof value !== 'string' || !value.trim()) return ''; try { const url = new URL(value, location.href); return url.protocol === 'https:' || (config.training && url.origin === location.origin) ? url.href : ''; } catch (_) { return ''; } }
  function accept(sale) { state.sale = sale; state.uncertain = false; remember(sale.key); state.invoice = sale.invoice || state.invoice; schedule(); }
  function schedule() { clearTimeout(timer); if (state.sale && state.sale.status === 'pending') timer = setTimeout(refresh, 3000); }
  async function refresh() {
    if (!state.key || state.busy) { schedule(); return; }
    try { accept(await api(`sales/${encodeURIComponent(state.key)}`)); state.error = ''; render(); }
    catch (error) { state.error = error.message + ' Payment status is not confirmed.'; render(); schedule(); }
  }
  async function action(path, payload) {
    if (state.busy) return;
    state.busy = true; state.error = ''; render();
    try { accept(await api(path, payload)); }
    catch (error) { state.error = error.message; state.uncertain = true; }
    finally { state.busy = false; render(); schedule(); }
  }
  async function loadProducts() {
    const sequence = ++searchSequence; state.loading = true; renderCatalog();
    try { const data = await api(`products?search=${encodeURIComponent(state.search)}`); if (sequence !== searchSequence) return; state.products = data.products; state.more = data.has_more; }
    catch (error) { if (sequence === searchSequence) state.error = error.message; }
    finally { if (sequence === searchSequence) { state.loading = false; renderCatalog(); renderError(); } }
  }
  function renderError() { const box = root.querySelector('.bc-error'); if (box) { box.textContent = state.error; box.hidden = !state.error; } }
  function renderCatalog() {
    const list = root.querySelector('.bc-products'); if (!list) return;
    list.replaceChildren();
    if (state.loading) { list.append(el('p', 'Loading available items…', 'bc-empty')); return; }
    if (!state.products.length) list.append(el('p', 'No available items found. Try the product name or ask a manager to check its stock.', 'bc-empty'));
    state.products.forEach(product => {
      const item = el('article', undefined, 'bc-product');
      const image = safeUrl(product.image);
      if (image) { const img = el('img'); img.src = image; img.alt = ''; img.loading = 'lazy'; img.addEventListener('error', () => img.remove()); img.width = 112; img.height = 140; item.append(img); }
      const details = el('div', undefined, 'bc-product-detail'); details.append(el('h3', product.name));
      if (product.attributes) details.append(el('p', product.attributes, 'bc-variant'));
      details.append(el('p', `${money(product.price)} · ${product.stock} available`, 'bc-price'));
      const add = button('Add item', () => {
        const existing = state.basket.get(product.id); const quantity = existing ? existing.quantity + 1 : 1;
        if (quantity > product.stock) { state.error = 'That quantity is not available. Ask a manager to check the item.'; renderError(); return; }
        state.basket.set(product.id, { ...product, quantity }); state.error = ''; renderBasket(); renderError();
      });
      add.disabled = state.busy || !!state.sale || state.uncertain || !config.enabled || product.stock < 1;
      details.append(add); item.append(details); list.append(item);
    });
    if (state.more) list.append(el('p', 'More items are available. Search by name to narrow the list.', 'bc-empty'));
  }
  function renderBasket() {
    const basket = root.querySelector('.bc-basket-body'); if (!basket) return; basket.replaceChildren();
    const sale = state.sale;
    if (sale) { renderSale(basket, sale); return; }
    if (state.uncertain && state.key) {
      basket.append(el('h3', 'Check the previous request'), el('p', 'We could not confirm whether your sale was created. Resume it before starting another sale.'), button('Resume this sale', refresh, 'bc-primary'));
      if (createPayload && createPayload.key === state.key && config.enabled) {
        basket.append(button('Retry this same sale', () => action('sales', createPayload)), el('p', 'This retries the same items and sale reference. It does not collect payment.', 'bc-hint'));
      } else {
        basket.append(el('p', 'If the sale cannot be found, ask a manager to check your active sale. Do not start a replacement sale or collect payment.', 'bc-hint'));
      }
      return;
    }
    if (!state.basket.size) { basket.append(el('p', 'Add the customer’s items. Check the size and color before taking payment.', 'bc-empty')); return; }
    let estimated = 0;
    state.basket.forEach(item => {
      estimated += Number(item.price) * item.quantity;
      const row = el('div', undefined, 'bc-basket-row'); row.append(el('strong', item.name));
      if (item.attributes) row.append(el('span', item.attributes, 'bc-variant'));
      const controls = el('div', undefined, 'bc-quantity');
      const decrement = button('−', () => { item.quantity--; if (!item.quantity) state.basket.delete(item.id); renderBasket(); }); decrement.setAttribute('aria-label', `Remove one ${item.name}`);
      const increment = button('+', () => { if (item.quantity < item.stock) item.quantity++; renderBasket(); }); increment.setAttribute('aria-label', `Add one ${item.name}`); increment.disabled = state.busy || item.quantity >= item.stock;
      controls.append(decrement, el('span', String(item.quantity)), increment, el('span', money(Number(item.price) * item.quantity), 'bc-line-total')); row.append(controls); basket.append(row);
    });
    basket.append(el('p', `Estimated total ${money(estimated)}`, 'bc-total'), el('p', 'Review the final server-calculated total on the next screen.', 'bc-hint'));
    const email = field('Email confirmation (optional)', 'email', state.email, value => { state.email = value; }, { autocomplete: 'off', maxlength: '254' });
    const review = button(state.busy ? 'Checking stock…' : 'Review sale & payment', () => {
      const input = email.querySelector('input'); if (!input.reportValidity()) return;
      if (!state.key) remember(crypto.randomUUID());
      createPayload = { key: state.key, items: [...state.basket.values()].map(item => ({ id: item.id, quantity: item.quantity })), email: state.email.trim() };
      action('sales', createPayload);
    }, 'bc-primary');
    review.disabled = state.busy || !config.enabled;
    basket.append(email, review);
  }
  function renderSale(basket, sale) {
    const labels = { unpaid: 'Ready for payment', pending: 'Waiting for payment', paid: 'Payment received', review: 'Manager help needed', completed: 'Sale complete', cancelled: 'Sale cancelled' };
    basket.append(el('p', `Order #${sale.number}`, 'bc-order'), el('h3', labels[sale.status] || 'Check sale status', `bc-status bc-status-${sale.status}`));
    if (sale.message) basket.append(el('p', sale.message, 'bc-sale-message'));
    sale.items.forEach(item => { const row = el('div', undefined, 'bc-sale-row'); row.append(el('span', `${item.quantity} × ${item.name}`), el('strong', money(item.total))); basket.append(row); });
    basket.append(el('p', money(sale.total), 'bc-total'));
    if (state.uncertain) basket.append(el('p', 'The last request could not be confirmed. Refresh the sale before doing anything else.', 'bc-warning'), button('Refresh sale status', refresh, 'bc-primary'));
    else if (sale.status === 'unpaid') {
      basket.append(el('p', 'Confirm the items and total with the customer.', 'bc-hint'));
      if (!sale.method || sale.method === 'cash') {
        const cash = el('div', undefined, 'bc-tender'); cash.append(el('h4', 'Cash'));
        const received = field('Amount received (PHP)', 'text', state.received, value => {
          state.received = value; const valid = /^\d+(\.\d{1,2})?$/.test(value) && Number(value) >= Number(sale.total);
          confirm.disabled = state.busy || !valid; change.textContent = valid ? `Change: ${money(Number(value) - Number(sale.total))}` : 'Enter the cash you have counted.';
        }, { inputmode: 'decimal', autocomplete: 'off' });
        const valid = /^\d+(\.\d{1,2})?$/.test(state.received) && Number(state.received) >= Number(sale.total);
        const change = el('p', valid ? `Change: ${money(Number(state.received) - Number(sale.total))}` : 'Enter the cash you have counted.', 'bc-hint');
        const confirm = button('Confirm cash received', () => action(`sales/${sale.key}/cash`, { received: state.received }), 'bc-primary'); confirm.disabled = state.busy || !valid;
        cash.append(received, change, confirm); basket.append(cash);
      }
      if (!sale.method || sale.method === 'bactive_paymongo') basket.append(button('Pay digitally', () => action(`sales/${sale.key}/digital`, {}), 'bc-secondary'), el('p', 'QRPh · Maya · ShopeePay · GrabPay', 'bc-hint'));
    }
    if (sale.status === 'pending') {
      basket.append(el('p', 'Keep the goods with you. Wait for “Payment received” on this screen.', 'bc-warning'));
      const url = safeUrl(sale.payment_url);
      if (url) {
        if (typeof window.qrcode === 'function') {
          try {
            const qr = window.qrcode(0, 'M'); qr.addData(url); qr.make();
            const canvas = el('canvas', undefined, 'bc-qr'); const count = qr.getModuleCount(); const scale = Math.max(3, Math.floor(280 / (count + 8))); canvas.width = canvas.height = (count + 8) * scale;
            canvas.setAttribute('role', 'img'); canvas.setAttribute('aria-label', 'Scan with your phone camera to open this order’s secure payment page');
            const ctx = canvas.getContext('2d'); ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height); ctx.fillStyle = '#000000';
            for (let row = 0; row < count; row++) for (let col = 0; col < count; col++) if (qr.isDark(row, col)) ctx.fillRect((col + 4) * scale, (row + 4) * scale, scale, scale);
            basket.append(canvas, el('p', 'Customer: scan with your phone camera, then choose how to pay. This opens the payment page; it is not a bank-app QR code.', 'bc-hint'));
          } catch (_) { basket.append(el('p', 'QR code unavailable. Open the secure payment page below.', 'bc-hint')); }
        }
        const link = el('a', 'Open secure payment page', 'bc-button bc-secondary'); link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer'; basket.append(link);
      }
      basket.append(button('Refresh payment status', refresh));
    }
    if (sale.status === 'review') basket.append(el('p', 'Do not collect another payment or hand over goods. Ask a manager to check this order and its payment.', 'bc-warning'), button('Refresh sale status', refresh));
    if (sale.status === 'paid' && !state.uncertain) {
      if (sale.method === 'cash') basket.append(el('p', `Cash received ${money(sale.received)} · Change ${money(sale.change)}`, 'bc-change'));
      basket.append(el('p', 'Write the invoice, return any change, and confirm the items are ready to hand over.', 'bc-hint'));
      const invoice = field('Handwritten invoice number', 'text', state.invoice, value => { state.invoice = value; handover.disabled = state.busy || !value.trim(); }, { maxlength: '64', autocomplete: 'off' });
      const handover = button('Confirm invoice & hand over goods', () => action(`sales/${sale.key}/handover`, { invoice: state.invoice.trim() }), 'bc-primary'); handover.disabled = state.busy || !state.invoice.trim(); basket.append(invoice, handover);
    }
    if ((sale.status === 'paid' || sale.status === 'completed') && sale.email) {
      basket.append(el('p', `Email: ${sale.email}`, 'bc-hint'), el('p', `Confirmation: ${{ accepted: 'Submitted to email service', failed: 'Sending failed — try resending', sending: 'Being submitted to email service', not_requested: 'Not requested' }[sale.email_state] || 'Not submitted yet'}`, 'bc-hint'), button('Resend email confirmation', () => action(`sales/${sale.key}/email`, {})));
    }
    if (sale.status === 'completed') basket.append(el('p', `Invoice ${sale.invoice}`, 'bc-hint'));
    if (sale.status === 'completed' || sale.status === 'cancelled') basket.append(button('Start next sale', () => { clearTimeout(timer); remember(''); state.sale = null; createPayload = null; state.basket.clear(); state.email = ''; state.received = ''; state.invoice = ''; state.error = ''; state.uncertain = false; render(); loadProducts(); }, 'bc-primary'));
    else if (sale.can_cancel && !state.uncertain) basket.append(button('Cancel this unpaid sale', () => { if (window.confirm('Cancel this unpaid sale and release its reserved items?')) action(`sales/${sale.key}/cancel`, {}); }, 'bc-quiet'));
    if (state.busy) basket.append(el('p', 'Saving… Please wait.', 'bc-hint'));
  }
  function render() {
    root.replaceChildren(); root.className = 'bc-app';
    const header = el('header', undefined, 'bc-header'); const brand = el('div'); brand.append(el('h1', 'B Active · In-store checkout'), el('p', `Davao store · ${config.staff.name}`)); header.append(brand);
    const logoutUrl = safeUrl(config.logoutUrl);
    if (logoutUrl) {
      const logout = el('a', 'Sign out', 'bc-button'); logout.href = logoutUrl;
      logout.addEventListener('click', event => { if (state.key && (!state.sale || !['completed', 'cancelled'].includes(state.sale.status)) && !window.confirm('Your active sale stays associated with your account. Sign back in with this account to resume it. Sign out now?')) event.preventDefault(); });
      header.append(logout);
    }
    root.append(header);
    if (config.training) root.append(el('p', 'Training environment — no real customer payments', 'bc-training'));
    if (!config.enabled) root.append(el('p', 'New sales are paused. You can still check an existing sale. Ask a manager for help.', 'bc-warning'));
    const error = el('div', state.error, 'bc-error'); error.setAttribute('role', 'alert'); error.hidden = !state.error; root.append(error);
    const layout = el('main', undefined, 'bc-layout'); const catalog = el('section', undefined, 'bc-catalog'); catalog.setAttribute('aria-label', 'Available products');
    catalog.append(el('h2', 'Choose items'));
    catalog.append(field('Search product or SKU', 'search', state.search, value => { state.search = value; clearTimeout(searchTimer); searchTimer = setTimeout(loadProducts, 300); }, { autocomplete: 'off' }));
    const products = el('div', undefined, 'bc-products'); products.setAttribute('aria-live', 'polite'); catalog.append(products);
    const basket = el('aside', undefined, 'bc-basket'); basket.setAttribute('aria-label', 'Current sale'); basket.append(el('h2', 'Customer’s sale'), el('div', undefined, 'bc-basket-body'));
    layout.append(catalog, basket); root.append(layout); renderCatalog(); renderBasket();
    root.append(el('footer', 'Only hand over goods after this screen confirms payment. Need help? Ask your manager and quote the order number.', 'bc-footer'));
  }
  try { const saved = config.activeKey || localStorage.getItem(storageKey); if (saved && /^[0-9a-f-]{36}$/i.test(saved)) { state.key = saved; state.uncertain = true; } } catch (_) { if (config.activeKey) { state.key = config.activeKey; state.uncertain = true; } }
  render(); loadProducts(); if (state.key) refresh();
  window.addEventListener('online', () => { if (state.key) refresh(); });
  window.addEventListener('offline', () => { state.error = 'Connection lost. Keep this sale open. Do not collect another payment; reconnect and refresh its status.'; renderError(); });
})();

/* Admin dashboard */
(function () {
  if (!GS.initShell()) return;
  const { $, $$, esc, peso, LOW } = GS;

  let filter = 'all';

  const statusBadge = s => ({
    pending: '<span class="badge badge--warn">Pending</span>',
    approved: '<span class="badge badge--info">Approved</span>',
    received: '<span class="badge badge--ok">Received</span>',
    cancelled: '<span class="badge badge--muted">Cancelled</span>'
  }[s]);

  const fmtDate = iso => new Date(iso).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });

  /* ----- stats ----- */
  function renderStats() {
    const prods = GS.products(), reqs = GS.requests();
    const units = prods.reduce((n, p) => n + p.stock, 0);
    const value = prods.reduce((n, p) => n + p.stock * p.price, 0);
    const low = prods.filter(p => p.stock <= LOW).length;
    const pending = reqs.filter(r => r.status === 'pending').length;
    const cards = [
      ['📦', 'Total products', prods.length, ''],
      ['💰', 'Inventory value', peso(value), 'stat--ok'],
      ['⚠️', 'Low / out of stock', low, 'stat--warn'],
      ['🚚', 'Pending requests', pending, 'stat--info']
    ];
    $('#stats').innerHTML = cards.map(c =>
      `<div class="card stat ${c[3]}"><div class="stat__icon">${c[0]}</div><div><span>${c[1]}</span><strong>${c[2]}</strong></div></div>`).join('') ;
    $('#stats').dataset.units = units;
  }

  /* ----- category bars ----- */
  function renderBars() {
    const map = {};
    GS.products().forEach(p => { map[p.category] = (map[p.category] || 0) + p.stock; });
    const rows = Object.entries(map).sort((a, b) => b[1] - a[1]);
    const max = Math.max(1, ...rows.map(r => r[1]));
    $('#bars').innerHTML = rows.map(([c, n]) =>
      `<div class="bar__row"><span>${esc(c)}</span><div class="bar__track"><div class="bar__fill" style="width:0" data-w="${(n / max) * 100}"></div></div><strong>${n}</strong></div>`).join('');
    requestAnimationFrame(() => requestAnimationFrame(() => {
      $$('.bar__fill').forEach(b => { b.style.width = b.dataset.w + '%'; });
    }));
  }

  /* ----- low stock ----- */
  function renderLow() {
    const low = GS.products().filter(p => p.stock <= LOW).sort((a, b) => a.stock - b.stock);
    $('#lowList').innerHTML = low.length ? low.map(p => {
      const s = GS.stockInfo(p);
      return `<div class="list__item">
        <img src="${esc(p.img)}" alt="">
        <div><strong>${esc(p.name)}</strong><span class="badge ${s.cls}">${esc(s.label)}</span></div>
        <button class="btn btn--ghost btn--sm" data-restock="${esc(p.id)}">Restock</button>
      </div>`;
    }).join('') : '<div class="empty" style="padding:24px 8px"><div class="empty__icon">✅</div><p>All products are well stocked.</p></div>';
  }

  /* ----- requests table ----- */
  function renderRequests() {
    const prods = GS.products();
    let reqs = GS.requests().slice().sort((a, b) => new Date(b.date) - new Date(a.date));
    if (filter !== 'all') reqs = reqs.filter(r => r.status === filter);
    $('#reqEmpty').hidden = reqs.length > 0;
    $('#reqBody').innerHTML = reqs.map(r => {
      const p = prods.find(x => x.id === r.productId);
      const name = p ? p.name : 'Deleted product';
      let actions = '<span class="muted">—</span>';
      if (r.status === 'pending')
        actions = `<button class="btn btn--primary btn--sm" data-act="approve" data-id="${r.id}">Approve</button><button class="btn btn--soft-danger btn--sm" data-act="cancel" data-id="${r.id}">Cancel</button>`;
      else if (r.status === 'approved')
        actions = `<button class="btn btn--primary btn--sm" data-act="receive" data-id="${r.id}">Mark received</button><button class="btn btn--soft-danger btn--sm" data-act="cancel" data-id="${r.id}">Cancel</button>`;
      return `<tr>
        <td class="cell-main" data-label="Product"><div class="cell-product">${p ? `<img src="${esc(p.img)}" alt="">` : ''}<div><strong>${esc(name)}</strong><small>${esc(r.note || 'No note')}</small></div></div></td>
        <td data-label="Qty"><strong>${r.qty}</strong></td>
        <td data-label="Supplier">${esc(r.supplier)}</td>
        <td data-label="Date">${fmtDate(r.date)}</td>
        <td data-label="Status">${statusBadge(r.status)}</td>
        <td class="actions">${actions}</td>
      </tr>`;
    }).join('');
  }

  const renderAll = () => { renderStats(); renderBars(); renderLow(); renderRequests(); };

  $('#reqFilter').addEventListener('click', e => {
    const b = e.target.closest('[data-f]');
    if (!b) return;
    filter = b.dataset.f;
    $$('#reqFilter button').forEach(x => x.classList.toggle('active', x === b));
    renderRequests();
  });

  /* ----- request actions ----- */
  $('#reqBody').addEventListener('click', async e => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const reqs = GS.requests();
    const r = reqs.find(x => x.id === b.dataset.id);
    if (!r) return;

    if (b.dataset.act === 'approve') {
      r.status = 'approved';
      GS.toast('Request approved', 'success');
    } else if (b.dataset.act === 'receive') {
      const prods = GS.products();
      const p = prods.find(x => x.id === r.productId);
      if (p) { p.stock += r.qty; GS.setProducts(prods); }
      r.status = 'received';
      GS.toast(p ? `+${r.qty} units added to ${p.name}` : 'Marked as received', 'success');
    } else if (b.dataset.act === 'cancel') {
      if (!await GS.confirmBox('Cancel this request?', 'The request will be marked as cancelled.', 'Cancel request')) return;
      r.status = 'cancelled';
      GS.toast('Request cancelled');
    }
    GS.setRequests(reqs);
    renderAll();
  });

  /* ----- new request modal ----- */
  const modal = $('#reqModal');
  function openRequest(productId) {
    $('#rqProduct').innerHTML = GS.products().map(p =>
      `<option value="${esc(p.id)}">${esc(p.name)} (stock: ${p.stock})</option>`).join('');
    if (productId) $('#rqProduct').value = productId;
    $('#rqQty').value = 10;
    $('#rqSupplier').value = '';
    $('#rqNote').value = '';
    $('#rqError').hidden = true;
    GS.openModal(modal);
  }
  $('#newReqBtn').addEventListener('click', () => openRequest());
  $('#lowList').addEventListener('click', e => {
    const b = e.target.closest('[data-restock]');
    if (b) openRequest(b.dataset.restock);
  });

  $('#reqForm').addEventListener('submit', e => {
    e.preventDefault();
    const qty = parseInt($('#rqQty').value, 10), supplier = $('#rqSupplier').value.trim();
    const err = $('#rqError');
    if (!qty || qty < 1) { err.textContent = 'Enter a quantity of at least 1.'; err.hidden = false; return; }
    if (!supplier) { err.textContent = 'Please enter a supplier.'; err.hidden = false; return; }
    const reqs = GS.requests();
    reqs.unshift({
      id: GS.uid('r'), productId: $('#rqProduct').value, qty, supplier,
      note: $('#rqNote').value.trim(), status: 'pending', date: new Date().toISOString()
    });
    GS.setRequests(reqs);
    GS.closeModal(modal);
    GS.toast('Stock request submitted', 'success');
    renderAll();
  });

  /* ----- reset demo ----- */
  $('#resetBtn').addEventListener('click', async () => {
    if (!await GS.confirmBox('Reset demo data?', 'Products and requests return to their original sample data.', 'Reset')) return;
    GS.resetDemo();
    GS.toast('Demo data reset', 'success');
    renderAll();
  });

  renderAll();
})();

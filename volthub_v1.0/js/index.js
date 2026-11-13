/* Storefront page */
(function () {
  const { $, $$, esc, peso, stockInfo } = GS;

  const all = GS.products();
  let category = 'All', query = '', sort = 'featured';
  let cart = GS.load(GS.KEYS.cart, []);
  let current = null;

  const gridEl = $('#grid'), emptyEl = $('#empty'), countEl = $('#count');

  /* ----- category chips ----- */
  const cats = ['All', ...Array.from(new Set(all.map(p => p.category))).sort()];
  $('#chips').innerHTML = cats.map(c =>
    `<button class="chip${c === category ? ' active' : ''}" data-cat="${esc(c)}" role="tab">${esc(c)}</button>`).join('');

  $('#chips').addEventListener('click', e => {
    const b = e.target.closest('[data-cat]');
    if (!b) return;
    category = b.dataset.cat;
    $$('.chip').forEach(c => c.classList.toggle('active', c === b));
    render();
  });

  $('#search').addEventListener('input', e => { query = e.target.value.trim().toLowerCase(); render(); });
  $('#sort').addEventListener('change', e => { sort = e.target.value; render(); });

  /* ----- render grid ----- */
  function render() {
    let list = all.filter(p =>
      (category === 'All' || p.category === category) &&
      (!query || (p.name + ' ' + p.desc + ' ' + p.category).toLowerCase().includes(query)));

    if (sort === 'price-asc') list.sort((a, b) => a.price - b.price);
    else if (sort === 'price-desc') list.sort((a, b) => b.price - a.price);
    else if (sort === 'name') list.sort((a, b) => a.name.localeCompare(b.name));

    countEl.textContent = `${list.length} product${list.length === 1 ? '' : 's'}`;
    emptyEl.hidden = list.length > 0;

    gridEl.innerHTML = list.map((p, i) => {
      const s = stockInfo(p);
      return `<article class="product" role="button" tabindex="0" data-id="${esc(p.id)}" style="animation-delay:${Math.min(i, 12) * 40}ms">
        <div class="product__img">
          <img src="${esc(p.img)}" alt="${esc(p.name)}" loading="lazy">
          ${p.stock <= 0 ? '<span class="product__ribbon badge badge--danger">Sold out</span>' : ''}
        </div>
        <div class="product__body">
          <span class="product__cat">${esc(p.category)}</span>
          <h3 class="product__name">${esc(p.name)}</h3>
          <div class="product__foot">
            <span class="price">${peso(p.price)}</span>
            <span class="badge ${s.cls}">${esc(s.label)}</span>
          </div>
        </div>
      </article>`;
    }).join('');
  }

  /* ----- product modal ----- */
  function openProduct(id) {
    const p = all.find(x => x.id === id);
    if (!p) return;
    current = p;
    const s = stockInfo(p);
    $('#pmImg').src = p.img;
    $('#pmImg').alt = p.name;
    $('#pmCat').textContent = p.category;
    $('#pmName').textContent = p.name;
    $('#pmPrice').textContent = peso(p.price);
    $('#pmStock').innerHTML = `<span class="badge ${s.cls}">${esc(s.label)}</span>`;
    $('#pmDesc').textContent = p.desc;
    $('#pmUnits').textContent = p.stock > 0 ? `${p.stock} units` : 'Unavailable';
    $('#pmId').textContent = p.id.toUpperCase();
    $('#pmAdd').disabled = p.stock <= 0;
    $('#pmAdd').textContent = p.stock <= 0 ? 'Sold out' : 'Add to cart';
    GS.openModal($('#productModal'));
  }

  const open = e => {
    const card = e.target.closest('.product');
    if (card) openProduct(card.dataset.id);
  };
  gridEl.addEventListener('click', open);
  gridEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(e); }
  });

  /* ----- cart (count only, demo) ----- */
  function updateCart() { $('#cartCount').textContent = cart.length; }
  $('#pmAdd').addEventListener('click', () => {
    if (!current) return;
    cart.push(current.id);
    GS.save(GS.KEYS.cart, cart);
    updateCart();
    GS.toast(`${current.name} added to cart`, 'success');
    GS.closeModal($('#productModal'));
  });
  $('#cartBtn').addEventListener('click', () => {
    GS.toast(cart.length ? `${cart.length} item(s) in your cart. Checkout comes later.` : 'Your cart is empty.');
  });

  updateCart();
  render();
})();

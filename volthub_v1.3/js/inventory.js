/* Inventory management page */
(function () {
  if (!GS.initShell()) return;
  const { $, esc, peso, LOW } = GS;

  const modal = $('#editModal');
  let editingId = null;       // null = adding a new product
  let currentImg = '';        // image currently shown in the editor

  const defaultArt = () => GS.art('🛍️', '#6366f1', '#ec4899');

  /* ----- filters ----- */
  function fillCategories() {
    const cats = Array.from(new Set(GS.products().map(p => p.category))).sort();
    const sel = $('#invCat'), keep = sel.value || 'all';
    sel.innerHTML = '<option value="all">All categories</option>' + cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
    sel.value = cats.includes(keep) ? keep : 'all';
    $('#catList').innerHTML = cats.map(c => `<option value="${esc(c)}">`).join('');
  }

  /* ----- table ----- */
  function render() {
    const q = $('#invSearch').value.trim().toLowerCase();
    const cat = $('#invCat').value, lvl = $('#invStock').value;

    const list = GS.products().filter(p => {
      if (q && !(p.name + ' ' + p.category + ' ' + p.desc).toLowerCase().includes(q)) return false;
      if (cat !== 'all' && p.category !== cat) return false;
      if (lvl === 'in' && p.stock <= LOW) return false;
      if (lvl === 'low' && !(p.stock > 0 && p.stock <= LOW)) return false;
      if (lvl === 'out' && p.stock > 0) return false;
      return true;
    });

    $('#invEmpty').hidden = list.length > 0;
    $('#invBody').innerHTML = list.map(p => {
      const s = GS.stockInfo(p);
      return `<tr class="clickable" data-id="${esc(p.id)}" tabindex="0">
        <td class="cell-main"><div class="cell-product"><img src="${esc(p.img)}" alt=""><div><strong>${esc(p.name)}</strong><small>${esc(p.id.toUpperCase())}</small></div></div></td>
        <td data-label="Category">${esc(p.category)}</td>
        <td class="num" data-label="Price"><strong>${peso(p.price)}</strong></td>
        <td class="num" data-label="Stock">${p.stock}</td>
        <td data-label="Status"><span class="badge ${s.cls}">${esc(s.label)}</span></td>
        <td class="actions"><button class="btn btn--ghost btn--sm" data-edit="${esc(p.id)}">✏️ Edit</button></td>
      </tr>`;
    }).join('');
  }

  ['input', 'change'].forEach(ev => {
    $('#invSearch').addEventListener(ev, render);
    $('#invCat').addEventListener(ev, render);
    $('#invStock').addEventListener(ev, render);
  });

  /* ----- open editor ----- */
  function setPreview(src) {
    currentImg = src;
    $('#edPreview').src = src;
  }

  function openEditor(id) {
    editingId = id;
    const p = id ? GS.products().find(x => x.id === id) : null;
    $('#edTitle').textContent = p ? 'Edit product' : 'Add product';
    $('#edDelete').hidden = !p;
    $('#edName').value = p ? p.name : '';
    $('#edCategory').value = p ? p.category : '';
    $('#edPrice').value = p ? p.price : '';
    $('#edStock').value = p ? p.stock : 0;
    $('#edDesc').value = p ? p.desc : '';
    $('#edUrl').value = p && /^https?:/i.test(p.img) ? p.img : '';
    $('#edFile').value = '';
    $('#edError').hidden = true;
    setPreview(p ? p.img : defaultArt());
    GS.openModal(modal);
  }

  $('#invBody').addEventListener('click', e => {
    const row = e.target.closest('tr[data-id]');
    if (row) openEditor(row.dataset.id);
  });
  $('#invBody').addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    const row = e.target.closest('tr[data-id]');
    if (row) openEditor(row.dataset.id);
  });
  $('#addBtn').addEventListener('click', () => openEditor(null));

  /* ----- photo input ----- */
  $('#edFile').addEventListener('change', async e => {
    const f = e.target.files[0];
    if (!f) return;
    try {
      setPreview(await GS.fileToDataURL(f));
      $('#edUrl').value = '';
    } catch {
      GS.toast('Could not read that image.', 'error');
    }
  });
  $('#edUrl').addEventListener('input', e => {
    const v = e.target.value.trim();
    if (/^https?:\/\//i.test(v)) setPreview(v);
  });
  $('#edPreview').addEventListener('error', () => {
    if (currentImg !== defaultArt()) { GS.toast('Image could not be loaded.', 'error'); setPreview(defaultArt()); }
  });

  /* ----- save ----- */
  $('#editForm').addEventListener('submit', e => {
    e.preventDefault();
    const err = $('#edError');
    const name = $('#edName').value.trim(), category = $('#edCategory').value.trim();
    const price = parseFloat($('#edPrice').value), stock = parseInt($('#edStock').value, 10);
    const desc = $('#edDesc').value.trim();
    const fail = m => { err.textContent = m; err.hidden = false; };

    if (!name) return fail('Please enter a product name.');
    if (!category) return fail('Please enter a category.');
    if (isNaN(price) || price < 0) return fail('Enter a valid price.');
    if (isNaN(stock) || stock < 0) return fail('Enter a valid stock quantity.');
    if (!desc) return fail('Please add a description.');

    const prods = GS.products();
    if (editingId) {
      const p = prods.find(x => x.id === editingId);
      Object.assign(p, { name, category, price, stock, desc, img: currentImg || defaultArt() });
    } else {
      prods.unshift({ id: GS.uid('p'), name, category, price, stock, desc, img: currentImg || defaultArt() });
    }
    if (!GS.setProducts(prods)) return;
    GS.closeModal(modal);
    GS.toast(editingId ? 'Product updated' : 'Product added', 'success');
    fillCategories();
    render();
  });

  /* ----- delete ----- */
  $('#edDelete').addEventListener('click', async () => {
    if (!editingId) return;
    const p = GS.products().find(x => x.id === editingId);
    if (!await GS.confirmBox('Delete this product?', `"${p ? p.name : 'This product'}" will be removed from the store.`, 'Delete')) return;
    GS.setProducts(GS.products().filter(x => x.id !== editingId));
    GS.closeModal(modal);
    GS.toast('Product deleted');
    fillCategories();
    render();
  });

  fillCategories();
  render();
})();

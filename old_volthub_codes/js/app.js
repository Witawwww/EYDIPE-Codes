/* =========================================================
   GS — shared helpers: demo data (localStorage), auth, UI utils
   Front-end only. Swap the load/save helpers for API calls later.
   ========================================================= */
const GS = (() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const peso = n => '₱' + Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 2 });
  const uid = p => p + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
  const LOW = 5; // stock at or below this is "low"

  const KEYS = { products: 'gs_products', requests: 'gs_requests', admins: 'gs_admins', session: 'gs_session', cart: 'gs_cart' };

  /* ---------- storage ---------- */
  const load = (k, fb) => {
    try { const v = JSON.parse(localStorage.getItem(k)); return v == null ? fb : v; } catch { return fb; }
  };
  const save = (k, v) => {
    try { localStorage.setItem(k, JSON.stringify(v)); return true; }
    catch { toast('Storage is full. Try a smaller photo.', 'error'); return false; }
  };

  /* ---------- placeholder art (inline SVG, no external images) ---------- */
  const art = (emoji, c1, c2) => {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${c1}"/><stop offset="1" stop-color="${c2}"/></linearGradient></defs><rect width="400" height="300" fill="url(#g)"/><circle cx="330" cy="40" r="90" fill="#fff" opacity=".1"/><text x="200" y="190" font-size="120" text-anchor="middle">${emoji}</text></svg>`;
    return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  };

  /* ---------- demo data ---------- */
  const seedProducts = () => [
    { id: 'p1', name: 'Nova X Pro 5G', category: 'Phones', price: 42990, stock: 14, img: art('📱', '#6366f1', '#a78bfa'),
      desc: '6.7" AMOLED 120Hz display, 256GB storage and a 50MP triple camera. All-day battery with 65W fast charging.' },
    { id: 'p2', name: 'AeroBook 14 Slim', category: 'Laptops', price: 56990, stock: 6, img: art('💻', '#0ea5e9', '#6366f1'),
      desc: 'Ultra-light 14" laptop with 16GB RAM, 512GB SSD and up to 14 hours of battery. Perfect for work and study.' },
    { id: 'p3', name: 'PulseBuds ANC', category: 'Audio', price: 3490, stock: 32, img: art('🎧', '#ec4899', '#f97316'),
      desc: 'True wireless earbuds with active noise cancelling, 30-hour battery case and IPX5 water resistance.' },
    { id: 'p4', name: 'Chrono Fit Watch', category: 'Wearables', price: 7990, stock: 4, img: art('⌚', '#14b8a6', '#3b82f6'),
      desc: 'Fitness smartwatch with heart-rate and sleep tracking, GPS, 100+ sport modes and a 10-day battery.' },
    { id: 'p5', name: 'Slate Tab 11', category: 'Phones', price: 18990, stock: 9, img: art('📲', '#f59e0b', '#ef4444'),
      desc: '11" tablet with a crisp 2K display, quad speakers and a stylus-ready screen. Great for notes and movies.' },
    { id: 'p6', name: 'KeyForge TKL Mechanical', category: 'Accessories', price: 3990, stock: 0, img: art('⌨️', '#8b5cf6', '#ec4899'),
      desc: 'Hot-swappable mechanical keyboard with tactile switches, PBT keycaps and per-key RGB lighting.' },
    { id: 'p7', name: 'Viper Lite Gaming Mouse', category: 'Accessories', price: 1890, stock: 21, img: art('🖱️', '#22c55e', '#0ea5e9'),
      desc: 'Featherweight 58g mouse with a 26K DPI sensor, 70-hour wireless battery and programmable buttons.' },
    { id: 'p8', name: 'VoltMax 20,000mAh', category: 'Accessories', price: 1590, stock: 3, img: art('🔋', '#f97316', '#eab308'),
      desc: '20,000mAh power bank with 45W USB-C fast charging. Charges a phone four times on a single charge.' },
    { id: 'p9', name: 'BoomBox Mini Speaker', category: 'Audio', price: 2790, stock: 17, img: art('🔊', '#3b82f6', '#8b5cf6'),
      desc: 'Portable Bluetooth speaker with deep bass, 360° sound and 18 hours of playtime. Splash proof.' },
    { id: 'p10', name: 'ClearCam 2K Webcam', category: 'Accessories', price: 2290, stock: 11, img: art('📷', '#64748b', '#0f172a'),
      desc: '2K webcam with auto-focus, dual noise-reducing mics and a privacy shutter for calls and streaming.' }
  ];

  const seedRequests = () => {
    const d = n => new Date(Date.now() - n * 864e5).toISOString();
    return [
      { id: 'r1', productId: 'p6', qty: 20, supplier: 'KeyForge Distribution', note: 'Out of stock, high demand.', status: 'pending', date: d(1) },
      { id: 'r2', productId: 'p8', qty: 30, supplier: 'VoltMax PH', note: '', status: 'approved', date: d(3) },
      { id: 'r3', productId: 'p4', qty: 12, supplier: 'Chrono Wearables', note: 'Restock before the holidays.', status: 'pending', date: d(2) },
      { id: 'r4', productId: 'p3', qty: 40, supplier: 'PulseAudio Trading', note: '', status: 'received', date: d(9) }
    ];
  };

  const products = () => {
    let p = load(KEYS.products, null);
    if (!Array.isArray(p)) { p = seedProducts(); save(KEYS.products, p); }
    return p;
  };
  const setProducts = p => save(KEYS.products, p);

  const requests = () => {
    let r = load(KEYS.requests, null);
    if (!Array.isArray(r)) { r = seedRequests(); save(KEYS.requests, r); }
    return r;
  };
  const setRequests = r => save(KEYS.requests, r);

  const resetDemo = () => { localStorage.removeItem(KEYS.products); localStorage.removeItem(KEYS.requests); };

  const stockInfo = p => {
    if (p.stock <= 0) return { label: 'Out of stock', cls: 'badge--danger' };
    if (p.stock <= LOW) return { label: `Only ${p.stock} left`, cls: 'badge--warn' };
    return { label: 'In stock', cls: 'badge--ok' };
  };

  /* ---------- auth (demo only — NOT secure; a real backend replaces this) ---------- */
  const admins = () => {
    let a = load(KEYS.admins, null);
    if (!Array.isArray(a)) { a = [{ name: 'Store Admin', username: 'admin', password: 'admin123' }]; save(KEYS.admins, a); }
    return a;
  };
  const addAdmin = a => { const list = admins(); list.push(a); save(KEYS.admins, list); };
  const session = () => load(KEYS.session, null);
  const setSession = s => save(KEYS.session, s);
  const logout = () => { localStorage.removeItem(KEYS.session); location.href = 'login_reg.html'; };
  const requireAuth = () => {
    if (!session()) { location.replace('login_reg.html'); return false; }
    return true;
  };

  /* ---------- UI: toast ---------- */
  const toast = (msg, type = 'info') => {
    let box = $('.toasts');
    if (!box) {
      box = document.createElement('div');
      box.className = 'toasts';
      box.setAttribute('aria-live', 'polite');
      document.body.appendChild(box);
    }
    const t = document.createElement('div');
    t.className = 'toast toast--' + type;
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 300); }, 2800);
  };

  /* ---------- UI: modal ---------- */
  const openModal = el => {
    el.hidden = false;
    document.body.classList.add('no-scroll');
    const dlg = $('.modal__dialog', el);
    if (dlg) { dlg.setAttribute('tabindex', '-1'); dlg.focus({ preventScroll: true }); }
  };
  const closeModal = el => {
    el.hidden = true;
    if (!$('.modal:not([hidden])')) document.body.classList.remove('no-scroll');
  };

  document.addEventListener('click', e => {
    const c = e.target.closest('[data-close]');
    if (c) { const m = c.closest('.modal'); if (m) closeModal(m); }
  });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const open = $$('.modal:not([hidden])');
    const top = open[open.length - 1];
    if (!top) return;
    const cancel = $('[data-cancel]', top);
    if (cancel) cancel.click(); else closeModal(top);
  });

  const confirmBox = (title, message, okText = 'Confirm') => new Promise(res => {
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = `<div class="modal__backdrop" data-cancel></div>
      <div class="modal__dialog modal__dialog--sm confirm-wrap" role="alertdialog" aria-modal="true">
        <div class="confirm"><h3>${esc(title)}</h3><p class="muted">${esc(message)}</p>
        <div class="confirm__actions"><button class="btn btn--ghost" data-cancel>Cancel</button><button class="btn btn--danger" data-ok>${esc(okText)}</button></div></div>
      </div>`;
    const done = v => { m.remove(); if (!$('.modal:not([hidden])')) document.body.classList.remove('no-scroll'); res(v); };
    m.addEventListener('click', e => {
      if (e.target.closest('[data-ok]')) done(true);
      else if (e.target.closest('[data-cancel]')) done(false);
    });
    document.body.appendChild(m);
    document.body.classList.add('no-scroll');
    $('[data-ok]', m).focus();
  });

  /* ---------- UI: admin shell (sidebar + user + logout) ---------- */
  const initShell = () => {
    if (!requireAuth()) return false;
    const s = session();
    $$('[data-user-name]').forEach(e => { e.textContent = s.name; });
    $$('[data-user-initial]').forEach(e => { e.textContent = (s.name || 'A').charAt(0).toUpperCase(); });
    const sb = $('.sidebar'), ov = $('.sidebar-overlay');
    const set = o => { sb.classList.toggle('open', o); ov.classList.toggle('show', o); };
    const btn = $('#menuBtn');
    if (btn) btn.addEventListener('click', () => set(!sb.classList.contains('open')));
    ov.addEventListener('click', () => set(false));
    $$('[data-logout]').forEach(b => b.addEventListener('click', logout));
    return true;
  };

  /* ---------- image helper: file -> resized data URL ---------- */
  const fileToDataURL = (file, max = 800) => new Promise((res, rej) => {
    const fr = new FileReader();
    fr.onerror = rej;
    fr.onload = () => {
      const img = new Image();
      img.onerror = rej;
      img.onload = () => {
        const s = Math.min(1, max / Math.max(img.width, img.height));
        const c = document.createElement('canvas');
        c.width = Math.round(img.width * s);
        c.height = Math.round(img.height * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        res(c.toDataURL('image/jpeg', 0.82));
      };
      img.src = fr.result;
    };
    fr.readAsDataURL(file);
  });

  return {
    $, $$, esc, peso, uid, LOW, KEYS, load, save, art,
    products, setProducts, requests, setRequests, resetDemo, stockInfo,
    admins, addAdmin, session, setSession, logout, requireAuth,
    toast, openModal, closeModal, confirmBox, initShell, fileToDataURL
  };
})();

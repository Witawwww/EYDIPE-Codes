/* =========================================================
   VoltHub — Shared JavaScript (Merged)
   ========================================================= */

// --- START OF ORIGINAL app.js (For PHP Pages) ---
// 1. Fade out and remove the flash toasts after a few seconds.
document.querySelectorAll(".toast").forEach(function (t) {
  setTimeout(function () {
    t.classList.add("out");
    setTimeout(function () {
      t.remove();
    }, 300);
  }, 2800);
});

// 2. Mobile admin menu (open / close the sidebar).
(function () {
  var sb = document.querySelector(".sidebar");
  var ov = document.querySelector(".sidebar-overlay");
  var b = document.getElementById("menuBtn");
  if (b && sb && ov) {
    var set = function (open) {
      sb.classList.toggle("open", open);
      ov.classList.toggle("show", open);
    };
    b.addEventListener("click", function () {
      set(!sb.classList.contains("open"));
    });
    ov.addEventListener("click", function () {
      set(false);
    });
  }
})();

// 3. Show / hide password buttons.
document.querySelectorAll(".toggle-pass").forEach(function (btn) {
  btn.addEventListener("click", function () {
    var input = btn.previousElementSibling;
    var show = input.type === "password";
    input.type = show ? "text" : "password";
    btn.textContent = show ? "⦵" : "👁";
    btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
  });
});

// 4. Confirm dialogs for forms/buttons marked with data-confirm="message".
document.addEventListener("submit", function (e) {
  var m = e.target.getAttribute && e.target.getAttribute("data-confirm");
  if (m && !confirm(m)) e.preventDefault();
});
document.addEventListener("click", function (e) {
  var b = e.target.closest && e.target.closest("button[data-confirm]");
  if (b && !confirm(b.getAttribute("data-confirm"))) e.preventDefault();
});

// 5. Whole table row is clickable when it has data-href (inventory page).
document.querySelectorAll("tr[data-href]").forEach(function (row) {
  row.addEventListener("click", function (e) {
    if (!e.target.closest("a,button"))
      location.href = row.getAttribute("data-href");
  });
});
// --- END OF ORIGINAL app.js ---

// --- START OF index.js ---
/* Storefront page */
(function () {
  if (typeof GS === "undefined") return; // Skip if GS is not defined
  const { $, $$, esc, peso, stockInfo } = GS;

  const all = GS.products();
  let category = "All",
    query = "",
    sort = "featured";
  let cart = GS.load(GS.KEYS.cart, []);
  let current = null;

  const gridEl = $("#grid"),
    emptyEl = $("#empty"),
    countEl = $("#count");

  /* ----- category chips ----- */
  const cats = [
    "All",
    ...Array.from(new Set(all.map((p) => p.category))).sort(),
  ];
  $("#chips").innerHTML = cats
    .map(
      (c) =>
        `<button class="chip${c === category ? " active" : ""}" data-cat="${esc(c)}" role="tab">${esc(c)}</button>`,
    )
    .join("");

  $("#chips").addEventListener("click", (e) => {
    const b = e.target.closest("[data-cat]");
    if (!b) return;
    category = b.dataset.cat;
    $$(".chip").forEach((c) => c.classList.toggle("active", c === b));
    render();
  });

  $("#search").addEventListener("input", (e) => {
    query = e.target.value.trim().toLowerCase();
    render();
  });
  $("#sort").addEventListener("change", (e) => {
    sort = e.target.value;
    render();
  });

  /* ----- render grid ----- */
  function render() {
    let list = all.filter(
      (p) =>
        (category === "All" || p.category === category) &&
        (!query ||
          (p.name + " " + p.desc + " " + p.category)
            .toLowerCase()
            .includes(query)),
    );

    if (sort === "price-asc") list.sort((a, b) => a.price - b.price);
    else if (sort === "price-desc") list.sort((a, b) => b.price - a.price);
    else if (sort === "name") list.sort((a, b) => a.name.localeCompare(b.name));

    countEl.textContent = `${list.length} product${list.length === 1 ? "" : "s"}`;
    emptyEl.hidden = list.length > 0;

    gridEl.innerHTML = list
      .map((p, i) => {
        const s = stockInfo(p);
        return `<article class="product" role="button" tabindex="0" data-id="${esc(p.id)}" style="animation-delay:${Math.min(i, 12) * 40}ms">
        <div class="product__img">
          <img src="${esc(p.img)}" alt="${esc(p.name)}" loading="lazy">
          ${p.stock <= 0 ? '<span class="product__ribbon badge badge--danger">Sold out</span>' : ""}
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
      })
      .join("");
  }

  /* ----- product modal ----- */
  function openProduct(id) {
    const p = all.find((x) => x.id === id);
    if (!p) return;
    current = p;
    const s = stockInfo(p);
    $("#pmImg").src = p.img;
    $("#pmImg").alt = p.name;
    $("#pmCat").textContent = p.category;
    $("#pmName").textContent = p.name;
    $("#pmPrice").textContent = peso(p.price);
    $("#pmStock").innerHTML =
      `<span class="badge ${s.cls}">${esc(s.label)}</span>`;
    $("#pmDesc").textContent = p.desc;
    $("#pmUnits").textContent =
      p.stock > 0 ? `${p.stock} units` : "Unavailable";
    $("#pmId").textContent = p.id.toUpperCase();
    $("#pmAdd").disabled = p.stock <= 0;
    $("#pmAdd").textContent = p.stock <= 0 ? "Sold out" : "Add to cart";
    GS.openModal($("#productModal"));
  }

  const open = (e) => {
    const card = e.target.closest(".product");
    if (card) openProduct(card.dataset.id);
  };
  gridEl.addEventListener("click", open);
  gridEl.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      open(e);
    }
  });

  /* ----- cart (count only, demo) ----- */
  function updateCart() {
    $("#cartCount").textContent = cart.length;
  }
  $("#pmAdd").addEventListener("click", () => {
    if (!current) return;
    cart.push(current.id);
    GS.save(GS.KEYS.cart, cart);
    updateCart();
    GS.toast(`${current.name} added to cart`, "success");
    GS.closeModal($("#productModal"));
  });
  $("#cartBtn").addEventListener("click", () => {
    GS.toast(
      cart.length
        ? `${cart.length} item(s) in your cart. Checkout comes later.`
        : "Your cart is empty.",
    );
  });

  updateCart();
  render();
})();
// --- END OF index.js ---

// --- START OF inventory.js ---
/* Inventory management page */
(function () {
  if (typeof GS === "undefined") return;
  if (!GS.initShell()) return;
  const { $, esc, peso, LOW } = GS;

  const modal = $("#editModal");
  let editingId = null;
  let currentImg = "";

  const defaultArt = () => GS.art("🛍️", "#6366f1", "#ec4899");

  function fillCategories() {
    const cats = Array.from(
      new Set(GS.products().map((p) => p.category)),
    ).sort();
    const sel = $("#invCat"),
      keep = sel.value || "all";
    sel.innerHTML =
      '<option value="all">All categories</option>' +
      cats.map((c) => `<option value="${esc(c)}">${esc(c)}</option>`).join("");
    sel.value = cats.includes(keep) ? keep : "all";
    $("#catList").innerHTML = cats
      .map((c) => `<option value="${esc(c)}">`)
      .join("");
  }

  function render() {
    const q = $("#invSearch").value.trim().toLowerCase();
    const cat = $("#invCat").value,
      lvl = $("#invStock").value;

    const list = GS.products().filter((p) => {
      if (
        q &&
        !(p.name + " " + p.category + " " + p.desc).toLowerCase().includes(q)
      )
        return false;
      if (cat !== "all" && p.category !== cat) return false;
      if (lvl === "in" && p.stock <= LOW) return false;
      if (lvl === "low" && !(p.stock > 0 && p.stock <= LOW)) return false;
      if (lvl === "out" && p.stock > 0) return false;
      return true;
    });

    $("#invEmpty").hidden = list.length > 0;
    $("#invBody").innerHTML = list
      .map((p) => {
        const s = GS.stockInfo(p);
        return `<tr class="clickable" data-id="${esc(p.id)}" tabindex="0">
        <td class="cell-main"><div class="cell-product"><img src="${esc(p.img)}" alt=""><div><strong>${esc(p.name)}</strong><small>${esc(p.id.toUpperCase())}</small></div></div></td>
        <td data-label="Category">${esc(p.category)}</td>
        <td class="num" data-label="Price"><strong>${peso(p.price)}</strong></td>
        <td class="num" data-label="Stock">${p.stock}</td>
        <td data-label="Status"><span class="badge ${s.cls}">${esc(s.label)}</span></td>
        <td class="actions"><button class="btn btn--ghost btn--sm" data-edit="${esc(p.id)}">✏️ Edit</button></td>
      </tr>`;
      })
      .join("");
  }

  ["input", "change"].forEach((ev) => {
    $("#invSearch").addEventListener(ev, render);
    $("#invCat").addEventListener(ev, render);
    $("#invStock").addEventListener(ev, render);
  });

  function setPreview(src) {
    currentImg = src;
    $("#edPreview").src = src;
  }

  function openEditor(id) {
    editingId = id;
    const p = id ? GS.products().find((x) => x.id === id) : null;
    $("#edTitle").textContent = p ? "Edit product" : "Add product";
    $("#edDelete").hidden = !p;
    $("#edName").value = p ? p.name : "";
    $("#edCategory").value = p ? p.category : "";
    $("#edPrice").value = p ? p.price : "";
    $("#edStock").value = p ? p.stock : 0;
    $("#edDesc").value = p ? p.desc : "";
    $("#edUrl").value = p && /^https?:/i.test(p.img) ? p.img : "";
    $("#edFile").value = "";
    $("#edError").hidden = true;
    setPreview(p ? p.img : defaultArt());
    GS.openModal(modal);
  }

  $("#invBody").addEventListener("click", (e) => {
    const row = e.target.closest("tr[data-id]");
    if (row) openEditor(row.dataset.id);
  });
  $("#invBody").addEventListener("keydown", (e) => {
    if (e.key !== "Enter") return;
    const row = e.target.closest("tr[data-id]");
    if (row) openEditor(row.dataset.id);
  });
  $("#addBtn").addEventListener("click", () => openEditor(null));

  $("#edFile").addEventListener("change", async (e) => {
    const f = e.target.files[0];
    if (!f) return;
    try {
      setPreview(await GS.fileToDataURL(f));
      $("#edUrl").value = "";
    } catch {
      GS.toast("Could not read that image.", "error");
    }
  });
  $("#edUrl").addEventListener("input", (e) => {
    const v = e.target.value.trim();
    if (/^https?:\/\//i.test(v)) setPreview(v);
  });
  $("#edPreview").addEventListener("error", () => {
    if (currentImg !== defaultArt()) {
      GS.toast("Image could not be loaded.", "error");
      setPreview(defaultArt());
    }
  });

  $("#editForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const err = $("#edError");
    const name = $("#edName").value.trim(),
      category = $("#edCategory").value.trim();
    const price = parseFloat($("#edPrice").value),
      stock = parseInt($("#edStock").value, 10);
    const desc = $("#edDesc").value.trim();
    const fail = (m) => {
      err.textContent = m;
      err.hidden = false;
    };

    if (!name) return fail("Please enter a product name.");
    if (!category) return fail("Please enter a category.");
    if (isNaN(price) || price < 0) return fail("Enter a valid price.");
    if (isNaN(stock) || stock < 0) return fail("Enter a valid stock quantity.");
    if (!desc) return fail("Please add a description.");

    const prods = GS.products();
    if (editingId) {
      const p = prods.find((x) => x.id === editingId);
      Object.assign(p, {
        name,
        category,
        price,
        stock,
        desc,
        img: currentImg || defaultArt(),
      });
    } else {
      prods.unshift({
        id: GS.uid("p"),
        name,
        category,
        price,
        stock,
        desc,
        img: currentImg || defaultArt(),
      });
    }
    if (!GS.setProducts(prods)) return;
    GS.closeModal(modal);
    GS.toast(editingId ? "Product updated" : "Product added", "success");
    fillCategories();
    render();
  });

  $("#edDelete").addEventListener("click", async () => {
    if (!editingId) return;
    const p = GS.products().find((x) => x.id === editingId);
    if (
      !(await GS.confirmBox(
        "Delete this product?",
        `"${p ? p.name : "This product"}" will be removed from the store.`,
        "Delete",
      ))
    )
      return;
    GS.setProducts(GS.products().filter((x) => x.id !== editingId));
    GS.closeModal(modal);
    GS.toast("Product deleted");
    fillCategories();
    render();
  });

  fillCategories();
  render();
})();
// --- END OF inventory.js ---

// --- START OF login.js ---
/* Login / register page (demo auth stored in localStorage) */
(function () {
  if (typeof GS === "undefined") return;
  const { $, $$ } = GS;

  if (GS.session()) {
    location.replace("admin.html");
    return;
  }

  const loginForm = $("#loginForm"),
    regForm = $("#regForm");
  const copy = {
    login: ["Welcome back", "Sign in to manage your store."],
    register: [
      "Create your account",
      "Set up an admin account for the dashboard.",
    ],
  };

  function showTab(name) {
    $$(".tab").forEach((t) =>
      t.classList.toggle("active", t.dataset.tab === name),
    );
    loginForm.hidden = name !== "login";
    regForm.hidden = name !== "register";
    $("#title").textContent = copy[name][0];
    $("#subtitle").textContent = copy[name][1];
    $("#loginError").hidden = true;
    $("#regError").hidden = true;
  }
  $$(".tab").forEach((t) =>
    t.addEventListener("click", () => showTab(t.dataset.tab)),
  );

  $$(".toggle-pass").forEach((b) =>
    b.addEventListener("click", () => {
      const input = b.previousElementSibling;
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      b.textContent = show ? "🙈" : "👁️";
      b.setAttribute("aria-label", show ? "Hide password" : "Show password");
    }),
  );

  const fail = (id, msg) => {
    const e = $(id);
    e.textContent = msg;
    e.hidden = false;
  };

  function enter(user) {
    GS.setSession({ name: user.name, username: user.username });
    location.href = "admin.html";
  }

  loginForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const u = $("#lUser").value.trim().toLowerCase(),
      p = $("#lPass").value;
    if (!u || !p)
      return fail("#loginError", "Please enter your username and password.");
    const user = GS.admins().find(
      (a) => a.username.toLowerCase() === u && a.password === p,
    );
    if (!user) return fail("#loginError", "Incorrect username or password.");
    enter(user);
  });

  regForm.addEventListener("submit", (e) => {
    e.preventDefault();
    const name = $("#rName").value.trim(),
      u = $("#rUser").value.trim().toLowerCase();
    const p = $("#rPass").value,
      p2 = $("#rPass2").value;
    if (!name || !u || !p)
      return fail("#regError", "Please fill in all fields.");
    if (!/^[a-z0-9_.-]{3,}$/.test(u))
      return fail(
        "#regError",
        "Username needs 3+ letters, numbers, dot, dash or underscore.",
      );
    if (p.length < 6)
      return fail("#regError", "Password must be at least 6 characters.");
    if (p !== p2) return fail("#regError", "Passwords do not match.");
    if (GS.admins().some((a) => a.username.toLowerCase() === u))
      return fail("#regError", "That username is already taken.");
    const user = { name, username: u, password: p };
    GS.addAdmin(user);
    enter(user);
  });
})();
// --- END OF login.js ---

// --- START OF admin.js ---
/* Admin dashboard */
(function () {
  if (typeof GS === "undefined") return;
  if (!GS.initShell()) return;
  const { $, $$, esc, peso, LOW } = GS;

  let filter = "all";

  const statusBadge = (s) =>
    ({
      pending: '<span class="badge badge--warn">Pending</span>',
      approved: '<span class="badge badge--info">Approved</span>',
      received: '<span class="badge badge--ok">Received</span>',
      cancelled: '<span class="badge badge--muted">Cancelled</span>',
    })[s];

  const fmtDate = (iso) =>
    new Date(iso).toLocaleDateString("en-PH", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });

  function renderStats() {
    const prods = GS.products(),
      reqs = GS.requests();
    const units = prods.reduce((n, p) => n + p.stock, 0);
    const value = prods.reduce((n, p) => n + p.stock * p.price, 0);
    const low = prods.filter((p) => p.stock <= LOW).length;
    const pending = reqs.filter((r) => r.status === "pending").length;
    const cards = [
      ["📦", "Total products", prods.length, ""],
      ["💰", "Inventory value", peso(value), "stat--ok"],
      ["⚠️", "Low / out of stock", low, "stat--warn"],
      ["🚚", "Pending requests", pending, "stat--info"],
    ];
    $("#stats").innerHTML = cards
      .map(
        (c) =>
          `<div class="card stat ${c[3]}"><div class="stat__icon">${c[0]}</div><div><span>${c[1]}</span><strong>${c[2]}</strong></div></div>`,
      )
      .join("");
    $("#stats").dataset.units = units;
  }

  function renderBars() {
    const map = {};
    GS.products().forEach((p) => {
      map[p.category] = (map[p.category] || 0) + p.stock;
    });
    const rows = Object.entries(map).sort((a, b) => b[1] - a[1]);
    const max = Math.max(1, ...rows.map((r) => r[1]));
    $("#bars").innerHTML = rows
      .map(
        ([c, n]) =>
          `<div class="bar__row"><span>${esc(c)}</span><div class="bar__track"><div class="bar__fill" style="width:0" data-w="${(n / max) * 100}"></div></div><strong>${n}</strong></div>`,
      )
      .join("");
    requestAnimationFrame(() =>
      requestAnimationFrame(() => {
        $$(".bar__fill").forEach((b) => {
          b.style.width = b.dataset.w + "%";
        });
      }),
    );
  }

  function renderLow() {
    const low = GS.products()
      .filter((p) => p.stock <= LOW)
      .sort((a, b) => a.stock - b.stock);
    $("#lowList").innerHTML = low.length
      ? low
          .map((p) => {
            const s = GS.stockInfo(p);
            return `<div class="list__item">
        <img src="${esc(p.img)}" alt="">
        <div><strong>${esc(p.name)}</strong><span class="badge ${s.cls}">${esc(s.label)}</span></div>
        <button class="btn btn--ghost btn--sm" data-restock="${esc(p.id)}">Restock</button>
      </div>`;
          })
          .join("")
      : '<div class="empty" style="padding:24px 8px"><div class="empty__icon">✅</div><p>All products are well stocked.</p></div>';
  }

  function renderRequests() {
    const prods = GS.products();
    let reqs = GS.requests()
      .slice()
      .sort((a, b) => new Date(b.date) - new Date(a.date));
    if (filter !== "all") reqs = reqs.filter((r) => r.status === filter);
    $("#reqEmpty").hidden = reqs.length > 0;
    $("#reqBody").innerHTML = reqs
      .map((r) => {
        const p = prods.find((x) => x.id === r.productId);
        const name = p ? p.name : "Deleted product";
        let actions = '<span class="muted">—</span>';
        if (r.status === "pending")
          actions = `<button class="btn btn--primary btn--sm" data-act="approve" data-id="${r.id}">Approve</button><button class="btn btn--soft-danger btn--sm" data-act="cancel" data-id="${r.id}">Cancel</button>`;
        else if (r.status === "approved")
          actions = `<button class="btn btn--primary btn--sm" data-act="receive" data-id="${r.id}">Mark received</button><button class="btn btn--soft-danger btn--sm" data-act="cancel" data-id="${r.id}">Cancel</button>`;
        return `<tr>
        <td class="cell-main" data-label="Product"><div class="cell-product">${p ? `<img src="${esc(p.img)}" alt="">` : ""}<div><strong>${esc(name)}</strong><small>${esc(r.note || "No note")}</small></div></div></td>
        <td data-label="Qty"><strong>${r.qty}</strong></td>
        <td data-label="Supplier">${esc(r.supplier)}</td>
        <td data-label="Date">${fmtDate(r.date)}</td>
        <td data-label="Status">${statusBadge(r.status)}</td>
        <td class="actions">${actions}</td>
      </tr>`;
      })
      .join("");
  }

  const renderAll = () => {
    renderStats();
    renderBars();
    renderLow();
    renderRequests();
  };

  $("#reqFilter").addEventListener("click", (e) => {
    const b = e.target.closest("[data-f]");
    if (!b) return;
    filter = b.dataset.f;
    $$("#reqFilter button").forEach((x) =>
      x.classList.toggle("active", x === b),
    );
    renderRequests();
  });

  $("#reqBody").addEventListener("click", async (e) => {
    const b = e.target.closest("[data-act]");
    if (!b) return;
    const reqs = GS.requests();
    const r = reqs.find((x) => x.id === b.dataset.id);
    if (!r) return;

    if (b.dataset.act === "approve") {
      r.status = "approved";
      GS.toast("Request approved", "success");
    } else if (b.dataset.act === "receive") {
      const prods = GS.products();
      const p = prods.find((x) => x.id === r.productId);
      if (p) {
        p.stock += r.qty;
        GS.setProducts(prods);
      }
      r.status = "received";
      GS.toast(
        p ? `+${r.qty} units added to ${p.name}` : "Marked as received",
        "success",
      );
    } else if (b.dataset.act === "cancel") {
      if (
        !(await GS.confirmBox(
          "Cancel this request?",
          "The request will be marked as cancelled.",
          "Cancel request",
        ))
      )
        return;
      r.status = "cancelled";
      GS.toast("Request cancelled");
    }
    GS.setRequests(reqs);
    renderAll();
  });

  const modal = $("#reqModal");
  function openRequest(productId) {
    $("#rqProduct").innerHTML = GS.products()
      .map(
        (p) =>
          `<option value="${esc(p.id)}">${esc(p.name)} (stock: ${p.stock})</option>`,
      )
      .join("");
    if (productId) $("#rqProduct").value = productId;
    $("#rqQty").value = 10;
    $("#rqSupplier").value = "";
    $("#rqNote").value = "";
    $("#rqError").hidden = true;
    GS.openModal(modal);
  }
  $("#newReqBtn").addEventListener("click", () => openRequest());
  $("#lowList").addEventListener("click", (e) => {
    const b = e.target.closest("[data-restock]");
    if (b) openRequest(b.dataset.restock);
  });

  $("#reqForm").addEventListener("submit", (e) => {
    e.preventDefault();
    const qty = parseInt($("#rqQty").value, 10),
      supplier = $("#rqSupplier").value.trim();
    const err = $("#rqError");
    if (!qty || qty < 1) {
      err.textContent = "Enter a quantity of at least 1.";
      err.hidden = false;
      return;
    }
    if (!supplier) {
      err.textContent = "Please enter a supplier.";
      err.hidden = false;
      return;
    }
    const reqs = GS.requests();
    reqs.unshift({
      id: GS.uid("r"),
      productId: $("#rqProduct").value,
      qty,
      supplier,
      note: $("#rqNote").value.trim(),
      status: "pending",
      date: new Date().toISOString(),
    });
    GS.setRequests(reqs);
    GS.closeModal(modal);
    GS.toast("Stock request submitted", "success");
    renderAll();
  });

  $("#resetBtn").addEventListener("click", async () => {
    if (
      !(await GS.confirmBox(
        "Reset demo data?",
        "Products and requests return to their original sample data.",
        "Reset",
      ))
    )
      return;
    GS.resetDemo();
    GS.toast("Demo data reset", "success");
    renderAll();
  });

  renderAll();
})();
// --- END OF admin.js ---

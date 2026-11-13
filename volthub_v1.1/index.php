<?php
/**
 * VoltHub — index.php (Storefront)
 * Features: product grid, search, category filter, sorting, pagination,
 * product details modal, add to cart.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

/* ---------- POST: cart actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $p = find_product((string)($_POST['product_id'] ?? ''));
        if ($p && (int)$p['stock'] > 0) {
            $have = (int)($_SESSION['cart'][$p['id']] ?? 0);
            if ($have >= (int)$p['stock']) {
                flash('Only ' . (int)$p['stock'] . ' of ' . $p['name'] . ' available', 'error');
            } else {
                $_SESSION['cart'][$p['id']] = $have + 1;
                flash($p['name'] . ' added to cart', 'success');
            }
        }
    }
    redirect(safe_back((string)($_POST['back'] ?? '')));
}

/* ---------- filter / sort state (from the URL) ---------- */
$all      = all_products();
$category = (string)($_GET['cat'] ?? 'All');
$query    = trim((string)($_GET['q'] ?? ''));
$sort     = (string)($_GET['sort'] ?? 'featured');
$view     = (string)($_GET['view'] ?? '');

$cats = array_values(array_unique(array_column($all, 'category')));
sort($cats);
array_unshift($cats, 'All');
if (!in_array($category, $cats, true)) $category = 'All';
if (!in_array($sort, ['featured', 'price-asc', 'price-desc', 'name'], true)) $sort = 'featured';

// Page number from the URL ($_GET['page'])
$reqPage = max(1, (int)($_GET['page'] ?? 1));

$list = array_values(array_filter($all, function ($p) use ($category, $query) {
    if ($category !== 'All' && $p['category'] !== $category) return false;
    return $query === '' || mb_stripos($p['name'] . ' ' . $p['description'] . ' ' . $p['category'], $query) !== false;
}));

if ($sort === 'price-asc')       usort($list, fn($a, $b) => $a['price'] <=> $b['price']);
elseif ($sort === 'price-desc')  usort($list, fn($a, $b) => $b['price'] <=> $a['price']);
elseif ($sort === 'name')        usort($list, fn($a, $b) => strcasecmp($a['name'], $b['name']));

// Split the filtered list into pages of PER_PAGE products
[$pageItems, $totalPages, $page] = paginate($list, $reqPage);

/** Build a URL that keeps the current filters and page. */
$url = function (array $over = []) use ($category, $query, $sort, $page): string {
    $q = array_merge(['cat' => $category, 'q' => $query, 'sort' => $sort, 'page' => $page], $over);
    if ((int)$q['page'] <= 1) unset($q['page']);
    if ($q['cat'] === 'All') unset($q['cat']);
    if ($q['q'] === '' || $q['q'] === null) unset($q['q']);
    if ($q['sort'] === 'featured') unset($q['sort']);
    foreach ($q as $k => $v) if ($v === null) unset($q[$k]);
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
};

$current   = $view !== '' ? find_product($view) : null;
$cartCount = cart_count();

page_head('VoltHub — Gadget Store', $current ? 'no-scroll' : '', 'Shop the latest phones, laptops, audio gear and accessories.');
?>
  <header class="site-header">
    <div class="container header__inner">
      <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>

      <form method="get" action="index.php" class="search header__search">
        <?php if ($category !== 'All'): ?><input type="hidden" name="cat" value="<?= e($category) ?>"><?php endif; ?>
        <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
        <span class="search__icon"></span>
        <input id="search" class="input" type="search" name="q" value="<?= e($query) ?>" placeholder="Search gadgets…" aria-label="Search gadgets" autocomplete="off">
      </form>

      <div class="header__actions">
        <a class="btn btn--ghost" href="cart.php" aria-label="Cart">🛒 <span id="cartCount" class="badge-dot"><?= $cartCount ?></span></a>
        <?php if (current_admin()): ?>
          <a href="admin.php" class="btn btn--primary">Dashboard</a>
        <?php elseif ($cu = current_user()): ?>
          <span class="muted" style="font-size:.88rem">Hi, <?= e($cu['name']) ?></span>
          <form method="post" action="logout.php" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit">Log out</button>
          </form>
        <?php else: ?>
          <a href="login_reg.php" class="btn btn--primary">Log In</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="container">
    <section class="hero">
      <span class="hero__tag">New arrivals · Free shipping over ₱5,000</span>
      <h1>Gadgets that keep up with you.</h1>
      <p>Phones, laptops, audio and accessories, hand-picked and ready to ship.</p>
      <a href="#shop" class="btn">Shop now ↓</a>
    </section>

    <section id="shop">
      <form method="get" action="index.php#shop" class="toolbar">
        <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= e($query) ?>"><?php endif; ?>
        <div id="chips" class="chips" role="tablist" aria-label="Categories">
          <?php foreach ($cats as $c): ?>
            <button class="chip<?= $c === $category ? ' active' : '' ?>" name="cat" value="<?= e($c) ?>" role="tab" type="submit"><?= e($c) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="toolbar__right">
          <label class="sr-only" for="sort">Sort by</label>
          <select id="sort" class="input" name="sort" onchange="this.form.submit()">
            <option value="featured"<?= $sort === 'featured' ? ' selected' : '' ?>>Featured</option>
            <option value="price-asc"<?= $sort === 'price-asc' ? ' selected' : '' ?>>Price: low to high</option>
            <option value="price-desc"<?= $sort === 'price-desc' ? ' selected' : '' ?>>Price: high to low</option>
            <option value="name"<?= $sort === 'name' ? ' selected' : '' ?>>Name A–Z</option>
          </select>
          <noscript><button class="btn btn--ghost btn--sm" type="submit">Apply</button></noscript>
        </div>
      </form>
      <p id="count" class="result-count"><?= count($list) ?> product<?= count($list) === 1 ? '' : 's' ?></p>

      <div id="grid" class="grid">
        <?php foreach ($pageItems as $i => $p): $s = stock_info($p); ?>
          <a href="<?= e($url(['view' => $p['id']])) ?>" style="display:contents;color:inherit;text-decoration:none">
            <article class="product" style="animation-delay:<?= min($i, 12) * 40 ?>ms">
              <div class="product__img">
                <img src="<?= e($p['img']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                <?php if ((int)$p['stock'] <= 0): ?><span class="product__ribbon badge badge--danger">Sold out</span><?php endif; ?>
              </div>
              <div class="product__body">
                <span class="product__cat"><?= e($p['category']) ?></span>
                <h3 class="product__name"><?= e($p['name']) ?></h3>
                <div class="product__foot">
                  <span class="price"><?= e(peso($p['price'])) ?></span>
                  <span class="badge <?= e($s['cls']) ?>"><?= e($s['label']) ?></span>
                </div>
              </div>
            </article>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="chips" style="justify-content:center;margin-top:24px" aria-label="Pages">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="chip<?= $i === $page ? ' active' : '' ?>" href="<?= e($url(['page' => $i]) . '#shop') ?>"><?= $i ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>

      <?php if (!$list): ?>
        <div id="empty" class="empty">
          <div class="empty__icon">🔎</div>
          <h3>No gadgets found</h3>
          <p>Try a different search or category.</p>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container">
      <span>© <?= date('Y') ?> VoltHub. All rights reserved.</span>
      <a href="login_reg.php">Admin login</a>
    </div>
  </footer>

  <?php if ($current): $s = stock_info($current); $inStock = (int)$current['stock'] > 0; ?>
  <!-- Product information modal (rendered server-side when ?view=ID) -->
  <div id="productModal" class="modal">
    <a class="modal__backdrop" href="<?= e($url()) ?>" aria-label="Close" style="display:block"></a>
    <div class="modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pmName">
      <a class="modal__close" href="<?= e($url()) ?>" aria-label="Close">×</a>
      <div class="detail">
        <div class="detail__img"><img src="<?= e($current['img']) ?>" alt="<?= e($current['name']) ?>"></div>
        <div class="detail__info">
          <span class="product__cat"><?= e($current['category']) ?></span>
          <h2 id="pmName"><?= e($current['name']) ?></h2>
          <div class="detail__price"><?= e(peso($current['price'])) ?></div>
          <div><span class="badge <?= e($s['cls']) ?>"><?= e($s['label']) ?></span></div>
          <p class="detail__desc"><?= e($current['description']) ?></p>
          <div class="detail__meta">
            <div><span>Availability</span><strong><?= $inStock ? (int)$current['stock'] . ' units' : 'Unavailable' ?></strong></div>
            <div><span>Product ID</span><strong><?= e(strtoupper($current['id'])) ?></strong></div>
          </div>
          <div class="detail__actions">
            <form method="post" action="<?= e($url()) ?>" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="act" value="add">
              <input type="hidden" name="product_id" value="<?= e($current['id']) ?>">
              <input type="hidden" name="back" value="<?= e($url()) ?>">
              <button class="btn btn--primary" type="submit"<?= $inStock ? '' : ' disabled' ?>><?= $inStock ? 'Add to cart' : 'Sold out' ?></button>
            </form>
            <a class="btn btn--ghost" href="<?= e($url()) ?>">Close</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
<?php page_end(); ?>
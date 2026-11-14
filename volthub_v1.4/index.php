<?php

/**
 * VoltHub — index.php (Storefront)
 * Shows the products to users and lets them add items to the cart.
 */
require 'db.php';
session_start();

// Check if the add to cart form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
  $product_id = $_POST['product_id'];
  $product = getProductById($product_id);

  if ($product && $product['stock'] > 0) {
    if (!isset($_SESSION['cart'])) {
      $_SESSION['cart'] = [];
    }

    $currentQty = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
    $newQty = $currentQty + 1;

    if ($newQty <= $product['stock']) {
      $_SESSION['cart'][$product_id] = $newQty;
      flash($product['name'] . ' added to cart!', 'success');
    } else {
      flash('Not enough stock for ' . $product['name'], 'error');
    }
  }

  redirect('index.php');
}

$all_products = getAllProducts();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['cat']) ? $_GET['cat'] : 'All';
$view_id = isset($_GET['view']) ? $_GET['view'] : ''; // For the product modal
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // For the page picker
$categories = getCategories(); // Category tabs come from the ENUM in the database

if ($page < 1) {
  $page = 1;
}

// Manual filtering (no array_filter)
$filtered_products = [];
foreach ($all_products as $p) {
  $match_cat = ($category === 'All' || $p['category'] === $category);
  $match_query = ($query === '' || strpos(strtolower($p['name']), strtolower($query)) !== false);

  if ($match_cat && $match_query) {
    $filtered_products[] = $p;
  }
}

// ==========================================
// PAGINATION LOGIC (Max 8 items per page)
// ==========================================
$per_page = 8;
$total_products = count($filtered_products);
$total_pages = ceil($total_products / $per_page); // ceil() rounds up

if ($total_pages < 1) {
  $total_pages = 1;
}
if ($page > $total_pages) {
  $page = $total_pages;
}

$offset = ($page - 1) * $per_page;

// Take only the 8 products for the current page using array_slice()
$displayed_products = array_slice($filtered_products, $offset, $per_page);

// Get the selected product for the modal
$current_product = null;
if ($view_id !== '') {
  $current_product = getProductById($view_id);
}

$cart_count = 0;
if (isset($_SESSION['cart'])) {
  foreach ($_SESSION['cart'] as $qty) {
    $cart_count += $qty;
  }
}

// Build the URL parameters for the pagination links (keeps the search and category)
$url_params = [];
if ($query !== '') {
  $url_params[] = "q=" . urlencode($query);
}
if ($category !== 'All') {
  $url_params[] = "cat=" . urlencode($category);
}
$base_url = "index.php?";
if (count($url_params) > 0) {
  $base_url .= implode("&", $url_params) . "&";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e('VoltHub — Gadget Store') ?></title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="site-header">
  <div class="container header__inner">
    <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>

    <form method="get" action="index.php" class="search header__search">
      <input class="input" type="search" name="q" value="<?= e($query) ?>" placeholder="Search gadgets...">
      <?php if ($category !== 'All'): ?>
        <input type="hidden" name="cat" value="<?= e($category) ?>">
      <?php endif; ?>
    </form>

    <div class="header__actions">
      <a class="btn btn--ghost" href="cart.php">🛒 <span id="cartCount" class="badge-dot"><?= $cart_count ?></span></a>
      <?php if (isset($_SESSION['logged_in']) && $_SESSION['role'] === 'admin'): ?>
        <a href="admin.php" class="btn btn--primary">Dashboard</a>
      <?php elseif (isset($_SESSION['logged_in'])): ?>
        <span class="muted">Hi, <?= e($_SESSION['username']) ?></span>
        <a href="logout.php" class="btn btn--ghost">Log out</a>
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
  </section>

  <section id="shop">
    <div class="toolbar">
      <div class="chips">
        <a href="index.php" class="chip <?= $category === 'All' ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $c): ?>
          <a href="index.php?cat=<?= urlencode($c) ?>" class="chip <?= $category === $c ? 'active' : '' ?>">
            <?php if (categoryImage($c) !== ''): ?>
              <img class="chip__img" src="<?= e(categoryImage($c)) ?>" alt="">
            <?php endif; ?>
            <?= e($c) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <p class="result-count"><?= $total_products ?> product<?= $total_products === 1 ? '' : 's' ?> found</p>

    <div class="grid">
      <!-- Loop through $displayed_products (max 8) -->
      <?php foreach ($displayed_products as $p): ?>
        <article class="product">
          <a href="index.php?view=<?= $p['id'] ?>" style="text-decoration:none; color:inherit;">
            <div class="product__img">
              <img src="<?= e($p['img']) ?>" alt="<?= e($p['name']) ?>">
            </div>
            <div class="product__body">
              <span class="product__cat"><?= e($p['category']) ?></span>
              <h3 class="product__name"><?= e($p['name']) ?></h3>
              <div class="product__foot">
                <span class="price"><?= formatPeso($p['price']) ?></span>
                <span class="badge"><?= checkStockStatus($p['stock']) ?></span>
              </div>
            </div>
          </a>
          <div style="padding: 0 16px 16px;">
            <form method="post" action="index.php">
              <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
              <button class="btn btn--primary btn--block" type="submit" name="add_to_cart" <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
                Add to Cart
              </button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($total_products === 0): ?>
      <div class="empty">
        <h3>No gadgets found</h3>
        <p>Try a different search or category.</p>
      </div>
    <?php endif; ?>

    <!-- PAGE PICKER -->
    <?php if ($total_pages > 1): ?>
      <nav class="chips" style="justify-content:center; margin-top:28px;" aria-label="Pages">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
          <a class="chip <?= $i === $page ? 'active' : '' ?>" href="<?= $base_url ?>page=<?= $i ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  </section>
</main>

<footer class="site-footer">
  <div class="container">
    <span>© <?= date('Y') ?> VoltHub. All rights reserved.</span>
  </div>
</footer>

<!-- Product Modal (only shows when the URL has ?view=ID) -->
<?php if ($current_product): ?>
  <div id="productModal" class="modal" style="display:grid;">
    <a class="modal__backdrop" href="index.php" aria-label="Close"></a>
    <div class="modal__dialog" role="dialog" aria-modal="true">
      <a class="modal__close" href="index.php" aria-label="Close" style="display: flex; justify-content: center; align-items: center; text-decoration: none; padding-bottom: 2px;">×</a>
      <div class="detail">
        <div class="detail__img">
          <img src="<?= e($current_product['img']) ?>" alt="<?= e($current_product['name']) ?>">
        </div>
        <div class="detail__info">
          <span class="product__cat"><?= e($current_product['category']) ?></span>
          <h2><?= e($current_product['name']) ?></h2>
          <div class="detail__price"><?= formatPeso($current_product['price']) ?></div>

          <div>
            <span class="badge <?= $current_product['stock'] <= 0 ? 'badge--danger' : 'badge--ok' ?>">
              <?= checkStockStatus($current_product['stock']) ?>
            </span>
          </div>

          <p class="detail__desc"><?= e($current_product['description']) ?></p>

          <div class="detail__meta">
            <div>
              <span>Availability</span>
              <strong><?= $current_product['stock'] > 0 ? $current_product['stock'] . ' units' : 'Unavailable' ?></strong>
            </div>
            <div>
              <span>Product ID</span>
              <strong><?= $current_product['id'] ?></strong>
            </div>
          </div>

          <div class="detail__actions">
            <form method="post" action="index.php" style="width:100%;">
              <input type="hidden" name="product_id" value="<?= $current_product['id'] ?>">
              <button class="btn btn--primary btn--block" type="submit" name="add_to_cart" <?= $current_product['stock'] <= 0 ? 'disabled' : '' ?>>
                <?= $current_product['stock'] > 0 ? 'Add to cart' : 'Sold out' ?>
              </button>
            </form>
            <a class="btn btn--ghost" href="index.php">Close</a>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php show_flash(); ?>
<script src="js/app.js"></script>
</body>

</html>

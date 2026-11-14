<?php

/**
 * VoltHub — inventory.php
 * The admin does the CRUD (Create, Read, Update, Delete) on products here.
 * Product images are uploaded with $_FILES, saved in uploads/, and the path is stored in product_images.
 */
require 'db.php';
session_start();

// Only a logged-in admin can open this page
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
  redirect('login_reg.php');
}

$error = '';
$show_modal = false;
$edit_product = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (isset($_POST['delete_product'])) {
    $id = $_POST['id'];
    deleteProductImageFile($id); // remove the file; the product_images row is removed by the foreign key
    $stmt = $GLOBALS['connection']->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    flash('Product deleted successfully', 'success');
    redirect('inventory.php');
  }

  if (isset($_POST['save_product'])) {
    $show_modal = true;
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $price = trim($_POST['price']);
    $stock = trim($_POST['stock']);
    $description = trim($_POST['description']);

    if ($name === '' || $category === '' || $description === '') {
      $error = 'Please fill in all text fields.';
    } elseif (filter_var($price, FILTER_VALIDATE_FLOAT) === false || $price < 0) {
      $error = 'Please enter a valid price.';
    } elseif (filter_var($stock, FILTER_VALIDATE_INT) === false || $stock < 0) {
      $error = 'Please enter a valid stock quantity.';
    } elseif (!in_array($category, getCategories())) {
      $error = 'Please choose a valid category.';
    } else {
      if ($id === '') {
        $stmt = $GLOBALS['connection']->prepare(
          "INSERT INTO products (name, category, price, stock, description) VALUES (:name, :category, :price, :stock, :description)"
        );
        $stmt->execute([
          ':name' => $name,
          ':category' => $category,
          ':price' => $price,
          ':stock' => $stock,
          ':description' => $description
        ]);
        $id = $GLOBALS['connection']->lastInsertId();
        $message = 'Product added successfully';
      } else {
        $stmt = $GLOBALS['connection']->prepare(
          "UPDATE products SET name = :name, category = :category, price = :price, stock = :stock, description = :description WHERE id = :id"
        );
        $stmt->execute([
          ':name' => $name,
          ':category' => $category,
          ':price' => $price,
          ':stock' => $stock,
          ':description' => $description,
          ':id' => $id
        ]);
        $message = 'Product updated successfully';
      }

      // Save the image if one was uploaded (otherwise the current image stays)
      $image_error = saveProductImage($id, $_FILES['product_image'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
      if ($image_error !== '') {
        flash($message . ', but: ' . $image_error, 'error');
      } else {
        flash($message, 'success');
      }
      redirect('inventory.php');
    }
  }
}

// If the form came back with an error while editing, keep editing the same product
if ($show_modal && $id !== '' && $edit_product === null) {
  $edit_product = getProductById($id);
}

if (isset($_GET['edit'])) {
  $show_modal = true;
  if ($_GET['edit'] !== 'new') {
    $edit_product = getProductById($_GET['edit']);
  }
}

$products = getAllProducts();

$categories = getCategories();
$active = 'inventory';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory — VoltHub Admin</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <div class="app">
    <?php include 'admin_nav.php'; ?>

    <div class="main">
      <header class="topbar">
        <button id="menuBtn" class="btn btn--ghost btn--icon menu-btn" type="button">☰</button>
        <div>
          <h1>Inventory</h1>
          <div class="topbar__sub">Manage your products</div>
        </div>
        <div class="topbar__actions"><a href="inventory.php?edit=new" class="btn btn--primary">＋ Add product</a></div>
      </header>
      <main class="content">

<section class="card panel">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Category</th>
          <th class="num">Price</th>
          <th class="num">Stock</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td class="cell-main">
              <div class="cell-product">
                <img src="<?= e($p['img']) ?>" alt="">
                <div><strong><?= e($p['name']) ?></strong></div>
              </div>
            </td>
            <td><?= e($p['category']) ?></td>
            <td class="num"><strong><?= formatPeso($p['price']) ?></strong></td>
            <td class="num"><?= $p['stock'] ?></td>
            <td><span class="badge"><?= checkStockStatus($p['stock']) ?></span></td>
            <td class="actions">
              <a class="btn btn--ghost btn--sm" href="inventory.php?edit=<?= $p['id'] ?>">✏️ Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($show_modal): ?>
  <div class="modal" style="display:grid;">
    <a class="modal__backdrop" href="inventory.php"></a>
    <div class="modal__dialog modal__dialog--md">
      <!-- Close button -->
      <a class="modal__close" href="inventory.php" style="display: flex; justify-content: center; align-items: center; text-decoration: none; padding-bottom: 2px;">×</a>
      <form method="post" action="inventory.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $edit_product ? $edit_product['id'] : '' ?>">
        <div class="modal__head">
          <h3><?= $edit_product ? 'Edit product' : 'Add product' ?></h3>
        </div>
        <div class="modal__body">
          <?php if ($error !== ''): ?>
            <div class="form-error"><?= e($error) ?></div>
          <?php endif; ?>

          <div class="form-grid">
            <div class="field span-2">
              <label>Product name</label>
              <input class="input" type="text" name="name" value="<?= $edit_product ? e($edit_product['name']) : '' ?>" required>
            </div>
            <div class="field">
              <label>Category</label>
              <select class="input" name="category" required>
                <option value="">Choose a category</option>
                <?php
                // Keep the typed value if the form came back with an error
                $selected = $edit_product ? $edit_product['category'] : (isset($_POST['category']) ? $_POST['category'] : '');
                foreach ($categories as $c): ?>
                  <option value="<?= e($c) ?>" <?= $selected === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Price (₱)</label>
              <input class="input" type="number" name="price" step="0.01" value="<?= $edit_product ? e($edit_product['price']) : '' ?>" required>
            </div>
            <div class="field">
              <label>Stock quantity</label>
              <input class="input" type="number" name="stock" value="<?= $edit_product ? e($edit_product['stock']) : '' ?>" required>
            </div>
            <div class="field span-2">
              <label>Product Image (Upload)</label>
              <?php if ($edit_product): ?>
                <img src="<?= e($edit_product['img']) ?>" alt="Current image" style="width:96px; height:96px; object-fit:cover; border-radius:10px; border:1px solid #ddd;">
              <?php endif; ?>
              <input class="input" type="file" name="product_image" accept="image/jpeg,image/png,image/webp,image/gif">
              <?php if ($edit_product): ?>
                <small class="muted">Choose a file only if you want to replace the current image.</small>
              <?php endif; ?>
            </div>
            <div class="field span-2">
              <label>Description</label>
              <textarea class="input" name="description" required><?= $edit_product ? e($edit_product['description']) : '' ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal__foot">
          <a class="btn btn--ghost" href="inventory.php">Cancel</a>
          <button class="btn btn--primary" type="submit" name="save_product">Save changes</button>
        </div>
      </form>

      <?php if ($edit_product): ?>
        <form method="post" action="inventory.php" style="padding: 0 24px 22px;">
          <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">
          <button class="btn btn--soft-danger" type="submit" name="delete_product" onclick="return confirm('Delete this product?');">🗑 Delete Product</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

      </main>
    </div>
  </div>
  <?php show_flash(); ?>
  <script src="js/app.js"></script>
</body>

</html>

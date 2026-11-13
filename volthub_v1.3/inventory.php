<?php

/**
 * VoltHub — inventory.php
 * Dito ginagawa ng admin ang CRUD (Create, Read, Update, Delete) sa products.
 * Kasama na ang file upload para sa images gamit ang $_FILES.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

require_auth();

$error = '';
$show_modal = false;
$edit_product = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (isset($_POST['delete_product'])) {
    $id = $_POST['id'];
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
    } else {
      $img_path = 'uploads/default.jpg';

      if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['product_image']['name'];
        $tmp_name = $_FILES['product_image']['tmp_name'];
        $img_path = 'uploads/' . $file_name;
        move_uploaded_file($tmp_name, $img_path);
      } elseif ($id !== '') {
        $old_product = getProductById($id);
        $img_path = $old_product['img'];
      }

      if ($id === '') {
        $stmt = $GLOBALS['connection']->prepare(
          "INSERT INTO products (name, category, price, stock, description, img) VALUES (:name, :category, :price, :stock, :description, :img)"
        );
        $stmt->execute([
          ':name' => $name,
          ':category' => $category,
          ':price' => $price,
          ':stock' => $stock,
          ':description' => $description,
          ':img' => $img_path
        ]);
        flash('Product added successfully', 'success');
      } else {
        $stmt = $GLOBALS['connection']->prepare(
          "UPDATE products SET name = :name, category = :category, price = :price, stock = :stock, description = :description, img = :img WHERE id = :id"
        );
        $stmt->execute([
          ':name' => $name,
          ':category' => $category,
          ':price' => $price,
          ':stock' => $stock,
          ':description' => $description,
          ':img' => $img_path,
          ':id' => $id
        ]);
        flash('Product updated successfully', 'success');
      }
      redirect('inventory.php');
    }
  }
}

if (isset($_GET['edit'])) {
  $show_modal = true;
  if ($_GET['edit'] !== 'new') {
    $edit_product = getProductById($_GET['edit']);
  }
}

$products = getAllProducts();

admin_top(
  'inventory',
  'Inventory',
  'Manage your products',
  '<a href="inventory.php?edit=new" class="btn btn--primary">＋ Add product</a>'
);
?>

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
      <!-- Inayos din natin ang close button dito para pantay -->
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
              <input class="input" type="text" name="category" value="<?= $edit_product ? e($edit_product['category']) : '' ?>" required>
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
              <input class="input" type="file" name="product_image" accept="image/*">
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

<?php admin_bottom(); ?>
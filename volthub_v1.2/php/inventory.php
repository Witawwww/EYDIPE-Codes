<?php
/**
 * VoltHub — php/inventory.php (Admin product management)
 * Features: search + category + stock-level filters, add / edit / delete product,
 * product photo by image URL (file upload: see the TODO in the editor). Products are saved in the MySQL `products` table.
 */
declare(strict_types=1);
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/layout.php';

require_auth();

/* ---------- filters (GET) ---------- */
$q   = trim((string)($_GET['q'] ?? ''));
$cat = (string)($_GET['cat'] ?? 'all');
$lvl = (string)($_GET['stock'] ?? 'all');
if (!in_array($lvl, ['all', 'in', 'low', 'out'], true)) $lvl = 'all';

$keep = array_filter(['q' => $q, 'cat' => $cat !== 'all' ? $cat : '', 'stock' => $lvl !== 'all' ? $lvl : ''], fn($v) => $v !== '');
$url  = fn(array $extra = []) => 'inventory.php' . (($p = array_merge($keep, $extra)) ? '?' . http_build_query($p) : '');

/* ---------- image helper ---------- */
function valid_img(string $s): bool {
    return (bool)preg_match('#^https?://\S+$#i', $s);
}

/* ---------- editor state ---------- */
$editParam = $_GET['edit'] ?? null;               // product id, or "new"
$editing   = null;                                 // existing product row being edited
$edError   = '';
$showEditor = $editParam !== null;
$f = ['id' => '', 'name' => '', 'category' => '', 'price' => '', 'stock' => '0', 'description' => '', 'img_url' => '', 'img' => default_art()];

if ($editParam !== null && $editParam !== 'new') {
    $editing = find_product((string)$editParam);
    if (!$editing) { $showEditor = false; }
}
if ($editing) {
    $f = ['id' => $editing['id'], 'name' => $editing['name'], 'category' => $editing['category'], 'price' => $editing['price'],
          'stock' => $editing['stock'], 'description' => $editing['description'],
          'img_url' => valid_img($editing['img']) ? $editing['img'] : '', 'img' => $editing['img']];
}

/* ---------- POST: save / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $id  = (string)($_POST['id'] ?? '');
    $existing = $id !== '' ? find_product($id) : null;

    if ($act === 'delete' && $existing) {
        delete_product($id);
        flash('Product deleted');
        redirect($url());
    }

    if ($act === 'save') {
        $editing    = $existing;
        $showEditor = true;
        $name  = trim((string)($_POST['name'] ?? ''));
        $categ = trim((string)($_POST['category'] ?? ''));
        $priceRaw = trim((string)($_POST['price'] ?? ''));
        $stockRaw = trim((string)($_POST['stock'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $imgUrl = trim((string)($_POST['img_url'] ?? ''));
        $f = ['id' => $id, 'name' => $name, 'category' => $categ, 'price' => $priceRaw, 'stock' => $stockRaw,
              'description' => $desc, 'img_url' => $imgUrl, 'img' => $existing['img'] ?? default_art()];

        if ($name === '')                                              $edError = 'Please enter a product name.';
        elseif ($categ === '')                                         $edError = 'Please enter a category.';
        elseif (!is_numeric($priceRaw) || (float)$priceRaw < 0)        $edError = 'Enter a valid price.';
        elseif (!ctype_digit($stockRaw))                               $edError = 'Enter a valid stock quantity.';
        elseif ($desc === '')                                          $edError = 'Please add a description.';
        elseif ($imgUrl !== '' && !valid_img($imgUrl))                 $edError = 'Photo URL must start with http:// or https://.';

        if (!$edError) {
            // photo priority: pasted URL > existing photo > default art
            $img = $imgUrl !== '' ? $imgUrl : ($existing['img'] ?? default_art());

            $row = ['name' => $name, 'category' => $categ, 'price' => (float)$priceRaw,
                    'stock' => (int)$stockRaw, 'description' => $desc, 'img' => $img];
            if ($existing) {
                save_product(['id' => $id] + $row);
                flash('Product updated', 'success');
            } else {
                save_product(['id' => uid('p')] + $row);   // new products are placed at the top of the list
                flash('Product added', 'success');
            }
            redirect($url());
        }
    }
}

/* ---------- list ---------- */
$products = all_products();
$cats = array_values(array_unique(array_column($products, 'category')));
sort($cats);

$list = array_filter($products, function ($p) use ($q, $cat, $lvl) {
    if ($q !== '' && mb_stripos($p['name'] . ' ' . $p['category'] . ' ' . $p['description'], $q) === false) return false;
    if ($cat !== 'all' && $p['category'] !== $cat) return false;
    if ($lvl === 'in'  && $p['stock'] <= LOW) return false;
    if ($lvl === 'low' && !($p['stock'] > 0 && $p['stock'] <= LOW)) return false;
    if ($lvl === 'out' && $p['stock'] > 0) return false;
    return true;
});

admin_top('inventory', 'Inventory', 'Click a product to edit its photo, description and price',
    '<a href="' . e($url(['edit' => 'new'])) . '" class="btn btn--primary">＋ Add product</a>');
?>
        <section class="card panel">
          <form method="get" action="inventory.php" class="filters">
            <div class="search">
              <span class="search__icon">🔍</span>
              <input id="invSearch" class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search products…" aria-label="Search products" autocomplete="off">
            </div>
            <select id="invCat" class="input" name="cat" aria-label="Category" onchange="this.form.submit()">
              <option value="all">All categories</option>
              <?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"<?= $c === $cat ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
            </select>
            <select id="invStock" class="input" name="stock" aria-label="Stock level" onchange="this.form.submit()">
              <?php foreach (['all' => 'All stock levels', 'in' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock'] as $k => $label): ?>
                <option value="<?= $k ?>"<?= $k === $lvl ? ' selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </form>

          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th></th></tr></thead>
              <tbody id="invBody">
                <?php foreach ($list as $p): $s = stock_info($p); $editUrl = $url(['edit' => $p['id']]); ?>
                  <tr class="clickable" tabindex="0" data-href="<?= e($editUrl) ?>">
                    <td class="cell-main"><div class="cell-product"><img src="<?= e($p['img']) ?>" alt=""><div><strong><?= e($p['name']) ?></strong><small><?= e(strtoupper($p['id'])) ?></small></div></div></td>
                    <td data-label="Category"><?= e($p['category']) ?></td>
                    <td class="num" data-label="Price"><strong><?= e(peso($p['price'])) ?></strong></td>
                    <td class="num" data-label="Stock"><?= (int)$p['stock'] ?></td>
                    <td data-label="Status"><span class="badge <?= e($s['cls']) ?>"><?= e($s['label']) ?></span></td>
                    <td class="actions"><a class="btn btn--ghost btn--sm" href="<?= e($editUrl) ?>">✏️ Edit</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if (!$list): ?>
            <div id="invEmpty" class="empty">
              <div class="empty__icon">🔎</div>
              <h3>No products match</h3>
              <p>Adjust your filters or add a new product.</p>
            </div>
          <?php endif; ?>
        </section>
<?php
ob_start();
if ($showEditor): ?>
  <div id="editModal" class="modal">
    <a class="modal__backdrop" href="<?= e($url()) ?>" aria-label="Close" style="display:block"></a>
    <div class="modal__dialog modal__dialog--md" role="dialog" aria-modal="true" aria-labelledby="edTitle">
      <a class="modal__close" href="<?= e($url()) ?>" aria-label="Close">×</a>
      <form method="post" action="<?= e($url($editing ? ['edit' => $editing['id']] : ['edit' => 'new'])) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>">
        <button type="submit" name="act" value="save" hidden aria-hidden="true" tabindex="-1"></button><!-- default action for Enter key -->
        <div class="modal__head"><h3 id="edTitle"><?= $editing ? 'Edit product' : 'Add product' ?></h3></div>
        <div class="modal__body">
          <div class="editor">
            <div class="editor__photo">
              <div class="photo-preview"><img src="<?= e($f['img']) ?>" alt="Product photo preview"></div>
              <!-- TODO (admin photo upload): add your <input type="file" name="..."> here, save the file to /uploads
                   and record it in the `product_photos` table (php/db.php + sql/volthub.sql). -->
              <div class="field">
                <label for="edUrl" class="sr-only">Photo URL</label>
                <input id="edUrl" class="input" type="url" name="img_url" value="<?= e($f['img_url']) ?>" placeholder="…or paste image URL">
              </div>
            </div>

            <div class="form-grid">
              <div class="field span-2">
                <label for="edName">Product name</label>
                <input id="edName" class="input" type="text" name="name" value="<?= e($f['name']) ?>" required>
              </div>
              <div class="field">
                <label for="edCategory">Category</label>
                <input id="edCategory" class="input" type="text" name="category" value="<?= e($f['category']) ?>" list="catList" required>
                <datalist id="catList"><?php foreach ($cats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
              </div>
              <div class="field">
                <label for="edPrice">Price (₱)</label>
                <input id="edPrice" class="input" type="number" name="price" value="<?= e($f['price']) ?>" min="0" step="0.01" required>
              </div>
              <div class="field">
                <label for="edStock">Stock quantity</label>
                <input id="edStock" class="input" type="number" name="stock" value="<?= e($f['stock']) ?>" min="0" step="1" required>
              </div>
              <div class="field span-2">
                <label for="edDesc">Description</label>
                <textarea id="edDesc" class="input" name="description" required><?= e($f['description']) ?></textarea>
              </div>
              <?php if ($edError): ?><div id="edError" class="form-error span-2"><?= e($edError) ?></div><?php endif; ?>
            </div>
          </div>
        </div>
        <div class="modal__foot">
          <?php if ($editing): ?>
            <button type="submit" name="act" value="delete" formnovalidate class="btn btn--soft-danger spacer"
                    data-confirm="Delete &quot;<?= e($editing['name']) ?>&quot;? It will be removed from the store.">🗑 Delete</button>
          <?php endif; ?>
          <a class="btn btn--ghost" href="<?= e($url()) ?>">Cancel</a>
          <button type="submit" name="act" value="save" class="btn btn--primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
<?php endif;
admin_bottom((string)ob_get_clean());

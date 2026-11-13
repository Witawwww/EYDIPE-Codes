<?php
/**
 * VoltHub — cart.php (Shopping cart + checkout)
 * Features: update quantity (capped at stock), remove item, clear cart, checkout that deducts stock.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

/* ---------- POST: update / remove / clear ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $id  = (string)($_POST['id'] ?? '');

    if ($act === 'update' && isset($_SESSION['cart'][$id])) {
        $p   = find_product($id);
        $qty = (int)($_POST['qty'] ?? 1);
        if (!$p || $qty < 1) {
            unset($_SESSION['cart'][$id]);
            flash('Item removed');
        } else {
            if ($qty > (int)$p['stock']) { $qty = (int)$p['stock']; flash('Only ' . $qty . ' available', 'error'); }
            $_SESSION['cart'][$id] = $qty;
        }
    } elseif ($act === 'remove') {
        unset($_SESSION['cart'][$id]);
        flash('Item removed');
    } elseif ($act === 'clear') {
        $_SESSION['cart'] = [];
        flash('Cart cleared');
    } elseif ($act === 'checkout') {
        [$ok, $msg] = checkout_cart();           // subtracts stock in a DB transaction
        flash($msg, $ok ? 'success' : 'error');
    }
    redirect('cart.php');
}

$lines = cart_lines();
$total = array_sum(array_column($lines, 'sub'));
$items = cart_count();

page_head('Your Cart — VoltHub');
?>
  <header class="site-header">
    <div class="container header__inner">
      <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>
      <div class="header__actions" style="margin-left:auto">
        <a class="btn btn--ghost" href="index.php">← Continue shopping</a>
        <?php if (current_admin()): ?>
          <a href="admin.php" class="btn btn--primary">Dashboard</a>
        <?php elseif (!current_user()): ?>
          <a href="login_reg.php" class="btn btn--primary">Log In</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="container" style="margin-top:28px">
    <section class="card panel">
      <div class="panel__head">
        <h2>Your cart (<?= $items ?> item<?= $items === 1 ? '' : 's' ?>)</h2>
        <?php if ($lines): ?>
          <form method="post" action="cart.php" data-confirm="Remove everything from your cart?">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="clear">
            <button class="btn btn--soft-danger btn--sm" type="submit">Clear cart</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($lines): ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Product</th><th class="num">Price</th><th>Quantity</th><th class="num">Subtotal</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($lines as $l): $p = $l['p']; ?>
                <tr>
                  <td class="cell-main"><div class="cell-product"><img src="<?= e($p['img']) ?>" alt=""><div><strong><?= e($p['name']) ?></strong><small><?= e($p['category']) ?> · <?= (int)$p['stock'] ?> in stock</small></div></div></td>
                  <td class="num" data-label="Price"><?= e(peso($p['price'])) ?></td>
                  <td data-label="Quantity">
                    <form method="post" action="cart.php" style="display:inline-flex;gap:6px;align-items:center">
                      <?= csrf_field() ?>
                      <input type="hidden" name="act" value="update">
                      <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                      <input class="input" type="number" name="qty" value="<?= (int)$l['qty'] ?>" min="1" max="<?= (int)$p['stock'] ?>" step="1" style="width:76px;padding:7px 10px" aria-label="Quantity">
                      <button class="btn btn--ghost btn--sm" type="submit">Update</button>
                    </form>
                  </td>
                  <td class="num" data-label="Subtotal"><strong><?= e(peso($l['sub'])) ?></strong></td>
                  <td class="actions">
                    <form method="post" action="cart.php" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="act" value="remove">
                      <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                      <button class="btn btn--soft-danger btn--sm" type="submit">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
          <div><span class="muted">Total</span> <strong style="font-size:1.6rem;margin-left:8px"><?= e(peso($total)) ?></strong></div>
          <form method="post" action="cart.php">
            <?= csrf_field() ?>
            <input type="hidden" name="act" value="checkout">
            <button class="btn btn--primary" type="submit">Checkout</button>
          </form>
        </div>
      <?php else: ?>
        <div class="empty">
          <div class="empty__icon">🛒</div>
          <h3>Your cart is empty</h3>
          <p>Browse the store and add something you like.</p>
          <a class="btn btn--primary" style="margin-top:16px" href="index.php">Shop now</a>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container"><span>© <?= date('Y') ?> VoltHub. All rights reserved.</span></div>
  </footer>
<?php page_end(); ?>
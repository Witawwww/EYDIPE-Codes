<?php

/**
 * VoltHub — admin.php (Dashboard)
 */
require 'db.php';
session_start();

// Only a logged-in admin can open this page
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
  redirect('login_reg.php');
}

// Handle POST actions for the stock requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['new_request'])) {
    saveStockRequest($_POST['product_id'], $_POST['qty'], $_POST['supplier']);
    flash('Stock request submitted', 'success');
    redirect('admin.php');
  }

  if (isset($_POST['approve_request'])) {
    updateRequestStatus($_POST['request_id'], 'approved');
    flash('Request approved', 'success');
    redirect('admin.php');
  }

  if (isset($_POST['receive_request'])) {
    $req_id = $_POST['request_id'];
    $req_qty = $_POST['request_qty'];
    $req_prod_id = $_POST['request_product_id'];

    // Add the received quantity to the product stock
    $stmt = $GLOBALS['connection']->prepare("UPDATE products SET stock = stock + :qty WHERE id = :id");
    $stmt->execute([':qty' => $req_qty, ':id' => $req_prod_id]);

    updateRequestStatus($req_id, 'received');
    flash('Stock received and added to inventory', 'success');
    redirect('admin.php');
  }

  if (isset($_POST['cancel_request'])) {
    updateRequestStatus($_POST['request_id'], 'cancelled');
    flash('Request cancelled', 'info');
    redirect('admin.php');
  }
}

// Get all products for the stats
$products = getAllProducts();
$total_units = 0;
$total_value = 0;
$low_stock_count = 0;
$low_stock_list = [];

foreach ($products as $p) {
  $total_units += $p['stock'];
  $total_value += ($p['stock'] * $p['price']);

  if ($p['stock'] <= $GLOBALS['LOW_STOCK']) {
    $low_stock_count++;
    $low_stock_list[] = $p;
  }
}

$requests = getAllRequests();
$pending_count = 0;
foreach ($requests as $r) {
  if ($r['status'] === 'pending') $pending_count++;
}

$active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — VoltHub Admin</title>
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <div class="app">
    <?php include 'admin_nav.php'; ?>

    <div class="main">
      <header class="topbar">
        <button id="menuBtn" class="btn btn--ghost btn--icon menu-btn" type="button">☰</button>
        <div>
          <h1>Dashboard</h1>
          <div class="topbar__sub">Inventory overview and stock requests</div>
        </div>
        <div class="topbar__actions"><a href="#new-request" class="btn btn--primary">＋ New stock request</a></div>
      </header>
      <main class="content">

<section class="stats">
  <div class="card stat">
    <div class="stat__icon">📦</div>
    <div><span>Total products</span><strong><?= count($products) ?></strong></div>
  </div>
  <div class="card stat stat--ok">
    <div class="stat__icon">💰</div>
    <div><span>Inventory value</span><strong><?= formatPeso($total_value) ?></strong></div>
  </div>
  <div class="card stat stat--warn">
    <div class="stat__icon">⚠️</div>
    <div><span>Low / out of stock</span><strong><?= $low_stock_count ?></strong></div>
  </div>
  <div class="card stat stat--info">
    <div class="stat__icon">🚚</div>
    <div><span>Pending requests</span><strong><?= $pending_count ?></strong></div>
  </div>
</section>

<section class="split">
  <div class="card panel">
    <div class="panel__head">
      <h2>Low stock alerts</h2>
      <a href="inventory.php" class="btn btn--ghost btn--sm">Open inventory</a>
    </div>
    <div class="list">
      <?php foreach ($low_stock_list as $p): ?>
        <div class="list__item">
          <img src="<?= e($p['img']) ?>" alt="">
          <div>
            <strong><?= e($p['name']) ?></strong>
            <span class="badge badge--warn"><?= checkStockStatus($p['stock']) ?> (<?= $p['stock'] ?> left)</span>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (count($low_stock_list) === 0): ?>
        <div class="empty" style="padding:24px 8px">
          <p>All products are well stocked.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card panel" id="new-request">
    <div class="panel__head">
      <h2>New Stock Request</h2>
    </div>
    <form method="post" action="admin.php">
      <div class="field">
        <label>Product</label>
        <select class="input" name="product_id" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (Stock: <?= $p['stock'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-top:10px;">
        <label>Quantity</label>
        <input class="input" type="number" name="qty" min="1" value="10" required>
      </div>
      <div class="field" style="margin-top:10px;">
        <label>Supplier</label>
        <input class="input" type="text" name="supplier" placeholder="Supplier Name" required>
      </div>
      <button class="btn btn--primary" type="submit" name="new_request" style="margin-top:15px; width:100%;">Submit Request</button>
    </form>
  </div>
</section>

<section class="card panel">
  <div class="panel__head">
    <h2>Stock Requests / Orders</h2>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Qty</th>
          <th>Supplier</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
          <?php $prod = getProductById($r['product_id']); ?>
          <tr>
            <td class="cell-main">
              <strong><?= $prod ? e($prod['name']) : 'Deleted Product' ?></strong>
            </td>
            <td><strong><?= $r['qty'] ?></strong></td>
            <td><?= e($r['supplier']) ?></td>
            <td><?= e($r['date']) ?></td>
            <td>
              <span class="badge <?= $r['status'] === 'pending' ? 'badge--warn' : ($r['status'] === 'approved' ? 'badge--info' : 'badge--ok') ?>">
                <?= ucfirst($r['status']) ?>
              </span>
            </td>
            <td class="actions">
              <?php if ($r['status'] === 'pending'): ?>
                <form method="post" action="admin.php" style="display:inline;">
                  <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                  <button class="btn btn--primary btn--sm" type="submit" name="approve_request">Approve</button>
                </form>
                <form method="post" action="admin.php" style="display:inline;">
                  <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                  <button class="btn btn--soft-danger btn--sm" type="submit" name="cancel_request">Cancel</button>
                </form>
              <?php elseif ($r['status'] === 'approved'): ?>
                <form method="post" action="admin.php" style="display:inline;">
                  <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                  <input type="hidden" name="request_qty" value="<?= $r['qty'] ?>">
                  <input type="hidden" name="request_product_id" value="<?= $r['product_id'] ?>">
                  <button class="btn btn--primary btn--sm" type="submit" name="receive_request">Receive</button>
                </form>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

      </main>
    </div>
  </div>
  <?php show_flash(); ?>
  <script src="js/app.js"></script>
</body>

</html>

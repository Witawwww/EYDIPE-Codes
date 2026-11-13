<?php
/**
 * VoltHub — admin.php (Dashboard)
 * Features: inventory statistics, low-stock alerts, stock-request workflow
 * (create / approve / receive / cancel), filter by status, demo-data reset.
 */
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

require_auth();

$filter = $_GET['f'] ?? 'all';
if (!in_array($filter, ['all', 'pending', 'approved', 'received'], true)) $filter = 'all';
$self = 'admin.php' . ($filter !== 'all' ? '?f=' . $filter : '');

$rqError   = '';
$showModal = isset($_GET['new']);
$form      = ['product' => (string)($_GET['product'] ?? ''), 'qty' => '10', 'supplier' => '', 'note' => ''];

/* ---------- POST actions ($_POST + $_SERVER['REQUEST_METHOD']) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';

    if (in_array($act, ['approve', 'receive', 'cancel'], true)) {
        $r = find_request((string)($_POST['id'] ?? ''));

        if ($r) {
            if ($act === 'approve' && $r['status'] === 'pending') {
                set_request_status($r['id'], 'approved');
                flash('Request approved', 'success');
            } elseif ($act === 'receive' && $r['status'] === 'approved') {
                $p = find_product($r['product_id']);
                if ($p) adjust_stock($p['id'], (int)$r['qty']);
                set_request_status($r['id'], 'received');
                flash($p ? "+{$r['qty']} units added to {$p['name']}" : 'Marked as received', 'success');
            } elseif ($act === 'cancel' && in_array($r['status'], ['pending', 'approved'], true)) {
                set_request_status($r['id'], 'cancelled');
                flash('Request cancelled');
            }
        }
        redirect($self);
    }

    if ($act === 'new_request') {
        $showModal = true;
        $form = [
            'product'  => (string)($_POST['product'] ?? ''),
            'qty'      => (string)($_POST['qty'] ?? ''),
            'supplier' => trim((string)($_POST['supplier'] ?? '')),
            'note'     => trim((string)($_POST['note'] ?? '')),
        ];
        $qty = (int)$form['qty'];
        if ($qty < 1)                         $rqError = 'Enter a quantity of at least 1.';
        elseif ($form['supplier'] === '')     $rqError = 'Please enter a supplier.';
        elseif (!find_product($form['product'])) $rqError = 'Please choose a valid product.';
        else {
            save_request([
                'id' => uid('r'), 'product_id' => $form['product'], 'qty' => $qty,
                'supplier' => $form['supplier'], 'note' => $form['note'],
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'),
            ]);
            flash('Stock request submitted', 'success');
            redirect($self);
        }
    }

    if ($act === 'reset') {
        reset_demo();
        flash('Demo data reset', 'success');
        redirect('admin.php');
    }
}

/* ---------- data for the page: totals use arithmetic (+=, *) and comparison operators ---------- */
$prods = all_products();
$units = $value = $low = 0;
$byCat = [];
foreach ($prods as $p) {
    $units += $p['stock'];
    $value += $p['stock'] * $p['price'];
    if ($p['stock'] <= LOW) $low++;
    $byCat[$p['category']] = ($byCat[$p['category']] ?? 0) + $p['stock'];
}
arsort($byCat);
$maxCat = max(1, ...array_values($byCat ?: [0]));

$pending = count(array_filter(all_requests(), fn($r) => $r['status'] === 'pending'));

$lowList = array_filter($prods, fn($p) => $p['stock'] <= LOW);
usort($lowList, fn($a, $b) => $a['stock'] <=> $b['stock']);

$reqs = requests_joined($filter);

$stats = [
    ['📦', 'Total products', count($prods), ''],
    ['💰', 'Inventory value', peso($value), 'stat--ok'],
    ['⚠️', 'Low / out of stock', $low, 'stat--warn'],
    ['🚚', 'Pending requests', $pending, 'stat--info'],
];

/** Small helper: one POST button for a request action. */
$actBtn = function (string $act, string $id, string $label, string $cls, string $confirm = '') use ($self): string {
    return '<form method="post" action="' . e($self) . '" style="display:inline"'
         . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
         . csrf_field()
         . '<input type="hidden" name="act" value="' . e($act) . '"><input type="hidden" name="id" value="' . e($id) . '">'
         . '<button class="btn ' . $cls . ' btn--sm" type="submit">' . e($label) . '</button></form>';
};

admin_top('dashboard', 'Dashboard', 'Inventory overview and stock requests',
    '<a href="admin.php?new=1" class="btn btn--primary">＋ New stock request</a>');
?>
        <section class="stats" id="stats">
          <?php foreach ($stats as $c): ?>
            <div class="card stat <?= e($c[3]) ?>"><div class="stat__icon"><?= $c[0] ?></div><div><span><?= e($c[1]) ?></span><strong><?= e($c[2]) ?></strong></div></div>
          <?php endforeach; ?>
        </section>

        <section class="split">
          <div class="card panel">
            <div class="panel__head"><h2>Units in stock by category</h2></div>
            <div id="bars" class="bars">
              <?php foreach ($byCat as $c => $n): ?>
                <div class="bar__row"><span><?= e($c) ?></span><div class="bar__track"><div class="bar__fill" style="width:<?= round($n / $maxCat * 100, 2) ?>%"></div></div><strong><?= (int)$n ?></strong></div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card panel">
            <div class="panel__head">
              <h2>Low stock alerts</h2>
              <a href="inventory.php" class="btn btn--ghost btn--sm">Open inventory</a>
            </div>
            <div id="lowList" class="list">
              <?php foreach ($lowList as $p): $s = stock_info($p); ?>
                <div class="list__item">
                  <img src="<?= e($p['img']) ?>" alt="">
                  <div><strong><?= e($p['name']) ?></strong><span class="badge <?= e($s['cls']) ?>"><?= e($s['label']) ?></span></div>
                  <a class="btn btn--ghost btn--sm" href="admin.php?new=1&amp;product=<?= urlencode($p['id']) ?>">Restock</a>
                </div>
              <?php endforeach; ?>
              <?php if (!$lowList): ?>
                <div class="empty" style="padding:24px 8px"><div class="empty__icon">✅</div><p>All products are well stocked.</p></div>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <section class="card panel">
          <div class="panel__head">
            <h2>Stock requests / orders</h2>
            <form method="get" action="admin.php" id="reqFilter" class="seg" role="tablist">
              <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'received' => 'Received'] as $k => $label): ?>
                <button type="submit" name="f" value="<?= $k ?>" class="<?= $filter === $k ? 'active' : '' ?>"><?= $label ?></button>
              <?php endforeach; ?>
            </form>
          </div>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Product</th><th>Qty</th><th>Supplier</th><th>Date</th><th>Status</th><th></th></tr></thead>
              <tbody id="reqBody">
                <?php foreach ($reqs as $r): ?>
                  <tr>
                    <td class="cell-main" data-label="Product"><div class="cell-product">
                      <?= $r['product_img'] ? '<img src="' . e($r['product_img']) . '" alt="">' : '' ?>
                      <div><strong><?= e($r['product_name'] ?? 'Deleted product') ?></strong><small><?= e($r['note'] ?: 'No note') ?></small></div>
                    </div></td>
                    <td data-label="Qty"><strong><?= (int)$r['qty'] ?></strong></td>
                    <td data-label="Supplier"><?= e($r['supplier']) ?></td>
                    <td data-label="Date"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
                    <td data-label="Status"><?= status_badge($r['status']) ?></td>
                    <td class="actions">
                      <?php if ($r['status'] === 'pending'): ?>
                        <?= $actBtn('approve', $r['id'], 'Approve', 'btn--primary') ?>
                        <?= $actBtn('cancel', $r['id'], 'Cancel', 'btn--soft-danger', 'Cancel this request?') ?>
                      <?php elseif ($r['status'] === 'approved'): ?>
                        <?= $actBtn('receive', $r['id'], 'Mark received', 'btn--primary') ?>
                        <?= $actBtn('cancel', $r['id'], 'Cancel', 'btn--soft-danger', 'Cancel this request?') ?>
                      <?php else: ?>
                        <span class="muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (!$reqs): ?>
            <div id="reqEmpty" class="empty">
              <div class="empty__icon">📭</div>
              <h3>No requests here</h3>
              <p>Create a stock request to restock a product.</p>
            </div>
          <?php endif; ?>
        </section>

        <form method="post" action="admin.php" class="muted" style="font-size:.85rem" data-confirm="Reset demo data? Products and requests return to their original sample data.">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="reset">
          Demo data is saved in the data/ folder (JSON files).
          <button class="btn btn--ghost btn--sm" style="margin-left:8px" type="submit">Reset demo data</button>
        </form>
<?php
ob_start();
if ($showModal): ?>
  <div id="reqModal" class="modal">
    <a class="modal__backdrop" href="<?= e($self) ?>" aria-label="Close" style="display:block"></a>
    <div class="modal__dialog modal__dialog--md" role="dialog" aria-modal="true" aria-labelledby="reqTitle">
      <a class="modal__close" href="<?= e($self) ?>" aria-label="Close">×</a>
      <form method="post" action="<?= e($self) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="act" value="new_request">
        <div class="modal__head"><h3 id="reqTitle">New stock request</h3></div>
        <div class="modal__body">
          <div class="form-grid">
            <div class="field span-2">
              <label for="rqProduct">Product</label>
              <select id="rqProduct" class="input" name="product" required>
                <?php foreach ($prods as $p): ?>
                  <option value="<?= e($p['id']) ?>"<?= $p['id'] === $form['product'] ? ' selected' : '' ?>><?= e($p['name']) ?> (stock: <?= (int)$p['stock'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="rqQty">Quantity</label>
              <input id="rqQty" class="input" type="number" name="qty" min="1" step="1" value="<?= e($form['qty']) ?>" required>
            </div>
            <div class="field">
              <label for="rqSupplier">Supplier</label>
              <input id="rqSupplier" class="input" type="text" name="supplier" value="<?= e($form['supplier']) ?>" placeholder="Supplier name" required>
            </div>
            <div class="field span-2">
              <label for="rqNote">Note (optional)</label>
              <textarea id="rqNote" class="input" name="note" style="min-height:80px" placeholder="Anything the supplier should know…"><?= e($form['note']) ?></textarea>
            </div>
            <?php if ($rqError): ?><div id="rqError" class="form-error span-2"><?= e($rqError) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="modal__foot">
          <a class="btn btn--ghost" href="<?= e($self) ?>">Cancel</a>
          <button type="submit" class="btn btn--primary">Submit request</button>
        </div>
      </form>
    </div>
  </div>
<?php endif;
admin_bottom((string)ob_get_clean());

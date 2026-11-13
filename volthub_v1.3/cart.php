<?php

/**
 * VoltHub — cart.php
 * Dito nakikita ng user ang mga na-add niya sa cart at makakapag-checkout.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'])) {
        $id = $_POST['id'];
        $qty = (int)$_POST['qty'];
        $product = getProductById($id);

        if ($qty > 0 && $qty <= $product['stock']) {
            $_SESSION['cart'][$id] = $qty;
        }
    }

    if (isset($_POST['remove_item'])) {
        $id = $_POST['id'];
        unset($_SESSION['cart'][$id]);
    }

    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_POST['checkout'])) {
        // Tinatawag ang checkout function na gumagawa ng stock subtraction
        if (processCheckout()) {
            flash('Order placed successfully!', 'success');
        }
        redirect('cart.php');
    }

    redirect('cart.php');
}

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$total = calculateCartTotal();
$items_count = 0;
foreach ($cart as $qty) {
    $items_count += $qty;
}

page_head('Your Cart — VoltHub');
?>

<header class="site-header">
    <div class="container header__inner">
        <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>
        <div class="header__actions" style="margin-left:auto">
            <a class="btn btn--ghost" href="index.php">← Continue shopping</a>
        </div>
    </div>
</header>

<main class="container" style="margin-top:28px">
    <section class="card panel">
        <div class="panel__head">
            <h2>Your cart (<?= $items_count ?> items)</h2>
            <?php if ($items_count > 0): ?>
                <form method="post" action="cart.php">
                    <button class="btn btn--soft-danger btn--sm" type="submit" name="clear_cart">Clear cart</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($items_count > 0): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th class="num">Price</th>
                            <th>Quantity</th>
                            <th class="num">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart as $id => $qty): $p = getProductById($id); ?>
                            <tr>
                                <td class="cell-main">
                                    <div class="cell-product">
                                        <img src="<?= e($p['img']) ?>" alt="">
                                        <div><strong><?= e($p['name']) ?></strong></div>
                                    </div>
                                </td>
                                <td class="num"><?= formatPeso($p['price']) ?></td>
                                <td>
                                    <form method="post" action="cart.php" style="display:flex; gap:6px;">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <input class="input" type="number" name="qty" value="<?= $qty ?>" min="1" max="<?= $p['stock'] ?>" style="width:70px;">
                                        <button class="btn btn--ghost btn--sm" type="submit" name="update_qty">Update</button>
                                    </form>
                                </td>
                                <td class="num"><strong><?= formatPeso($p['price'] * $qty) ?></strong></td>
                                <td class="actions">
                                    <form method="post" action="cart.php">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button class="btn btn--soft-danger btn--sm" type="submit" name="remove_item">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:16px; border-top:1px solid #ccc;">
                <div><span>Total:</span> <strong style="font-size:1.5rem;"><?= formatPeso($total) ?></strong></div>
                <form method="post" action="cart.php">
                    <button class="btn btn--primary" type="submit" name="checkout">Checkout</button>
                </form>
            </div>
        <?php else: ?>
            <div class="empty">
                <h3>Your cart is empty</h3>
                <p>Browse the store and add something you like.</p>
                <a class="btn btn--primary" style="margin-top:16px" href="index.php">Shop now</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php page_end(); ?>
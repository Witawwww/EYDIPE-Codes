<?php
/**
 * VoltHub — admin_nav.php
 * Admin sidebar, shared by admin.php and inventory.php.
 * Set $active ('dashboard' or 'inventory') before including this file.
 */
$name = $_SESSION['username'];
?>
<aside class="sidebar">
    <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>
    <nav class="sidebar__nav">
        <div class="sidebar__label">Manage</div>
        <a href="admin.php" class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>">
            <span class="ico">📊</span>Dashboard
        </a>
        <a href="inventory.php" class="nav-link <?= $active === 'inventory' ? 'active' : '' ?>">
            <span class="ico">📦</span>Inventory
        </a>
        <div class="sidebar__label" style="margin-top:14px">Store</div>
        <a href="index.php" class="nav-link"><span class="ico">🛍️</span>View storefront</a>
    </nav>
    <div class="sidebar__foot">
        <div class="user-chip">
            <div class="avatar"><?= e(substr($name, 0, 1)) ?></div>
            <div><strong><?= e($name) ?></strong><small>Administrator</small></div>
        </div>
        <form method="post" action="logout.php">
            <button class="nav-link" type="submit"><span class="ico">🚪</span>Log out</button>
        </form>
    </div>
</aside>

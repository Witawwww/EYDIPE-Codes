<?php

/**
 * VoltHub — includes/layout.php
 * Dito naka-save ang mga reusable HTML templates para sa head, footer, at admin sidebar.
 */

// Function para i-print ang head at opening body tag
function page_head($title, $bodyClass = '')
{
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?></title>
        <link rel="stylesheet" href="css/style.css">
    </head>

    <body class="<?= e($bodyClass) ?>">
    <?php
}

// Function para i-print ang closing tags at flash messages
function page_end()
{
    // Ipakita ang flash messages kung meron man
    if (isset($_SESSION['flash'])) {
        echo '<div class="toasts">';
        foreach ($_SESSION['flash'] as $f) {
            echo '<div class="toast toast--' . e($f['type']) . '">' . e($f['msg']) . '</div>';
        }
        echo '</div>';
        // Burahin na yung flash para hindi na maulit sa next page
        unset($_SESSION['flash']);
    }
    ?>
        <script src="js/app.js"></script>
    </body>

    </html>
<?php
}

// Function para sa admin sidebar at topbar
function admin_top($active, $title, $sub, $actions = '')
{
    $admin = current_admin();
    $name = $admin ? $admin['username'] : 'Admin';
    page_head($title . ' — VoltHub Admin');
?>
    <div class="app">
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

        <div class="main">
            <header class="topbar">
                <button id="menuBtn" class="btn btn--ghost btn--icon menu-btn" type="button">☰</button>
                <div>
                    <h1><?= e($title) ?></h1>
                    <div class="topbar__sub"><?= e($sub) ?></div>
                </div>
                <div class="topbar__actions"><?= $actions ?></div>
            </header>
            <main class="content">
            <?php
        }

        // Function para isara ang admin layout
        function admin_bottom($modalHtml = '')
        {
            ?>
            </main>
        </div>
    </div>
    <?= $modalHtml ?>
<?php
            page_end();
        }
?>
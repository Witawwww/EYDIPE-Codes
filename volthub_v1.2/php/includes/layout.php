<?php
/**
 * VoltHub — php/includes/layout.php
 * Shared page templates (HTML head, toasts, admin sidebar shell). Loaded with require by every page.
 */
declare(strict_types=1);

/** Print the <head> and opening <body> tag of a page. */
function page_head(string $title, string $bodyClass = '', string $desc = ''): void { ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <?php if ($desc): ?><meta name="description" content="<?= e($desc) ?>"><?php endif; ?>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body<?= $bodyClass ? ' class="' . e($bodyClass) . '"' : '' ?>>
<?php }

/** Print queued flash toasts, load js/app.js and close the page. */
function page_end(): void {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if ($flash): ?>
  <div class="toasts" aria-live="polite">
    <?php foreach ($flash as [$msg, $type]): ?>
      <div class="toast toast--<?= e($type) ?>"><?= e($msg) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
  <script src="../js/app.js"></script>
</body>
</html>
<?php }

/** Open the admin shell: sidebar navigation + top bar. */
function admin_top(string $active, string $title, string $sub, string $actions = ''): void {
    $admin = current_admin();
    $name = $admin['name'] ?? 'Admin';
    page_head($title . ' — VoltHub Admin');
    ?>
  <div class="app">
    <aside class="sidebar" aria-label="Admin navigation">
      <a href="index.php" class="brand"><span class="brand__mark">⚡</span>VoltHub</a>
      <nav class="sidebar__nav">
        <div class="sidebar__label">Manage</div>
        <a href="admin.php" class="nav-link<?= $active === 'dashboard' ? ' active' : '' ?>"<?= $active === 'dashboard' ? ' aria-current="page"' : '' ?>><span class="ico">📊</span>Dashboard</a>
        <a href="inventory.php" class="nav-link<?= $active === 'inventory' ? ' active' : '' ?>"<?= $active === 'inventory' ? ' aria-current="page"' : '' ?>><span class="ico">📦</span>Inventory</a>
        <div class="sidebar__label" style="margin-top:14px">Store</div>
        <a href="index.php" class="nav-link"><span class="ico">🛍️</span>View storefront</a>
      </nav>
      <div class="sidebar__foot">
        <div class="user-chip">
          <div class="avatar"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></div>
          <div><strong><?= e($name) ?></strong><small>Administrator</small></div>
        </div>
        <form method="post" action="logout.php">
          <?= csrf_field() ?>
          <button class="nav-link" type="submit"><span class="ico">🚪</span>Log out</button>
        </form>
      </div>
    </aside>
    <div class="sidebar-overlay"></div>

    <div class="main">
      <header class="topbar">
        <button id="menuBtn" class="btn btn--ghost btn--icon menu-btn" aria-label="Open menu" type="button">☰</button>
        <div>
          <h1><?= e($title) ?></h1>
          <div class="topbar__sub"><?= e($sub) ?></div>
        </div>
        <div class="topbar__actions"><?= $actions ?></div>
      </header>
      <main class="content">
<?php }

/** Close the admin shell and print an optional modal after it. */
function admin_bottom(string $modalHtml = ''): void { ?>
      </main>
    </div>
  </div>
<?= $modalHtml ?>
<?php page_end();
}

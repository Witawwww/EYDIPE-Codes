<?php
/**
 * VoltHub — php/includes/functions.php
 * Shared core: session, helpers, product database (MySQL via php/db.php), login and cart.
 *
 * PRODUCTS are stored in the MySQL table `products` (shared by every visitor, survive restarts).
 * Stock requests, accounts and the cart still live in $_SESSION (they reset when the browser session ends).
 */
declare(strict_types=1);

session_start();
require __DIR__ . '/../db.php';

const LOW      = 5;   // stock at or below this number is "low"
const PER_PAGE = 8;   // products per page on the storefront

/* =========================================================
   1. HELPERS
   ========================================================= */

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function peso($n): string { return '₱' . rtrim(rtrim(number_format((float)$n, 2), '0'), '.'); }

function uid(string $prefix): string { return $prefix . bin2hex(random_bytes(4)); }

function redirect(string $url): never { header('Location: ' . $url); exit; }

/** Placeholder product picture (an inline SVG, so no image files are needed). */
function art(string $emoji, string $c1, string $c2): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
         . '<stop offset="0" stop-color="' . $c1 . '"/><stop offset="1" stop-color="' . $c2 . '"/></linearGradient></defs>'
         . '<rect width="400" height="300" fill="url(#g)"/><circle cx="330" cy="40" r="90" fill="#fff" opacity=".1"/>'
         . '<text x="200" y="190" font-size="120" text-anchor="middle">' . $emoji . '</text></svg>';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svg);
}

function default_art(): string { return art('🛍️', '#6366f1', '#ec4899'); }

function stock_info(array $p): array {
    $s = (int)$p['stock'];
    if ($s <= 0)    return ['label' => 'Out of stock', 'cls' => 'badge--danger'];
    if ($s <= LOW)  return ['label' => "Only $s left", 'cls' => 'badge--warn'];
    return ['label' => 'In stock', 'cls' => 'badge--ok'];
}

function status_badge(string $status): string {
    $map = ['pending' => ['warn', 'Pending'], 'approved' => ['info', 'Approved'],
            'received' => ['ok', 'Received'], 'cancelled' => ['muted', 'Cancelled']];
    return isset($map[$status]) ? '<span class="badge badge--' . $map[$status][0] . '">' . $map[$status][1] . '</span>' : '';
}

/** Returns [items for this page, total pages, current page]. */
function paginate(array $items, int $page, int $perPage = PER_PAGE): array {
    $pages = max(1, (int)ceil(count($items) / $perPage));
    $page  = min(max(1, $page), $pages);
    return [array_slice($items, ($page - 1) * $perPage, $perPage), $pages, $page];
}

/** Only allow redirects back to one of our own pages. */
function safe_back(string $url, string $fallback = 'index.php'): string {
    return preg_match('#^[a-z_]+\.php(\?[^\r\n]*)?$#i', $url) ? $url : $fallback;
}

/* =========================================================
   2. FLASH MESSAGES + CSRF
   ========================================================= */

function flash(string $msg, string $type = 'info'): void { $_SESSION['flash'][] = [$msg, $type]; }

function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }

function csrf_check(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid or expired form token. Go back, reload the page and try again.');
    }
}

/* =========================================================
   3. DATA
      MySQL `products`      : id       => product row   (see php/db.php for the table)
      $_SESSION['requests'] : id       => stock request row
      $_SESSION['users']    : username => account row
   ========================================================= */

/** Replace the whole `products` table with the sample products. */
function seed_products(): void {
    $rows = [
        ['p1', 'Nova X Pro 5G', 'Phones', 42990, 14, '📱', '#6366f1', '#a78bfa', '6.7" AMOLED 120Hz display, 256GB storage and a 50MP triple camera. All-day battery with 65W fast charging.'],
        ['p2', 'AeroBook 14 Slim', 'Laptops', 56990, 6, '💻', '#0ea5e9', '#6366f1', 'Ultra-light 14" laptop with 16GB RAM, 512GB SSD and up to 14 hours of battery. Perfect for work and study.'],
        ['p3', 'PulseBuds ANC', 'Audio', 3490, 32, '🎧', '#ec4899', '#f97316', 'True wireless earbuds with active noise cancelling, 30-hour battery case and IPX5 water resistance.'],
        ['p4', 'Chrono Fit Watch', 'Wearables', 7990, 4, '⌚', '#14b8a6', '#3b82f6', 'Fitness smartwatch with heart-rate and sleep tracking, GPS, 100+ sport modes and a 10-day battery.'],
        ['p5', 'Slate Tab 11', 'Phones', 18990, 9, '📲', '#f59e0b', '#ef4444', '11" tablet with a crisp 2K display, quad speakers and a stylus-ready screen. Great for notes and movies.'],
        ['p6', 'KeyForge TKL Mechanical', 'Accessories', 3990, 0, '⌨️', '#8b5cf6', '#ec4899', 'Hot-swappable mechanical keyboard with tactile switches, PBT keycaps and per-key RGB lighting.'],
        ['p7', 'Viper Lite Gaming Mouse', 'Accessories', 1890, 21, '🖱️', '#22c55e', '#0ea5e9', 'Featherweight 58g mouse with a 26K DPI sensor, 70-hour wireless battery and programmable buttons.'],
        ['p8', 'VoltMax 20,000mAh', 'Accessories', 1590, 3, '🔋', '#f97316', '#eab308', '20,000mAh power bank with 45W USB-C fast charging. Charges a phone four times on a single charge.'],
        ['p9', 'BoomBox Mini Speaker', 'Audio', 2790, 17, '🔊', '#3b82f6', '#8b5cf6', 'Portable Bluetooth speaker with deep bass, 360° sound and 18 hours of playtime. Splash proof.'],
        ['p10', 'ClearCam 2K Webcam', 'Accessories', 2290, 11, '📷', '#64748b', '#0f172a', '2K webcam with auto-focus, dual noise-reducing mics and a privacy shutter for calls and streaming.'],
    ];
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM products');
    $ins = $pdo->prepare('INSERT INTO products (id, pos, name, category, price, stock, description, img) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($rows as $i => $r) {
        $ins->execute([$r[0], $i + 1, $r[1], $r[2], $r[3], $r[4], $r[8], art($r[5], $r[6], $r[7])]);
    }
    $pdo->commit();
}

function seed_requests(): void {
    $ago  = fn(int $days) => date('Y-m-d H:i:s', time() - $days * 86400);
    $rows = [
        ['r1', 'p6', 20, 'KeyForge Distribution', 'Out of stock, high demand.', 'pending', $ago(1)],
        ['r2', 'p8', 30, 'VoltMax PH', '', 'approved', $ago(3)],
        ['r3', 'p4', 12, 'Chrono Wearables', 'Restock before the holidays.', 'pending', $ago(2)],
        ['r4', 'p3', 40, 'PulseAudio Trading', '', 'received', $ago(9)],
    ];
    $_SESSION['requests'] = [];
    foreach ($rows as $r) {
        $_SESSION['requests'][$r[0]] = ['id' => $r[0], 'product_id' => $r[1], 'qty' => $r[2], 'supplier' => $r[3],
                                        'note' => $r[4], 'status' => $r[5], 'created_at' => $r[6]];
    }
}

/** First time the database is created: load the sample products. */
db();
if (db_is_fresh()) seed_products();

/** First visit of a session: load the demo stock requests and the admin account. */
if (!isset($_SESSION['requests'])) seed_requests();
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = ['admin' => ['name' => 'Store Admin', 'username' => 'admin',
                                      'password_hash' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin']];
}

/** Admin button: put products (database) and requests (session) back to the demo data (accounts are kept). */
function reset_demo(): void { seed_products(); seed_requests(); }

/* ---------- users ---------- */

function find_user(string $username): ?array { return $_SESSION['users'][$username] ?? null; }

/** Add an account. Returns false if the username already exists. */
function add_user(string $name, string $username, string $hash): bool {
    if (find_user($username)) return false;
    $_SESSION['users'][$username] = ['name' => $name, 'username' => $username, 'password_hash' => $hash, 'role' => 'user'];
    return true;
}

/* ---------- products ---------- */

/** Make sure numbers come back from MySQL as numbers (DECIMAL arrives as a string). */
function norm_product(array $r): array {
    $r['pos']   = (int)$r['pos'];
    $r['price'] = (float)$r['price'];
    $r['stock'] = (int)$r['stock'];
    return $r;
}

const PRODUCT_COLS = 'id, pos, name, category, price, stock, description, img';

/** All products in display order. */
function all_products(): array {
    $rows = db()->query('SELECT ' . PRODUCT_COLS . ' FROM products ORDER BY pos, id')->fetchAll();
    return array_map('norm_product', $rows);
}

function find_product(string $id): ?array {
    if ($id === '') return null;
    $st = db()->prepare('SELECT ' . PRODUCT_COLS . ' FROM products WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ? norm_product($row) : null;
}

/** Insert or update a product (matched by 'id'). New products go to the top of the list. */
function save_product(array $row): void {
    $pdo = db();
    if (find_product((string)$row['id'])) {
        $st = $pdo->prepare('UPDATE products SET name = ?, category = ?, price = ?, stock = ?, description = ?, img = ? WHERE id = ?');
        $st->execute([$row['name'], $row['category'], $row['price'], $row['stock'], $row['description'], $row['img'], $row['id']]);
    } else {
        $pos = (int)$pdo->query('SELECT COALESCE(MIN(pos), 1) - 1 FROM products')->fetchColumn();
        $st = $pdo->prepare('INSERT INTO products (id, pos, name, category, price, stock, description, img) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$row['id'], $pos, $row['name'], $row['category'], $row['price'], $row['stock'], $row['description'], $row['img']]);
    }
}

function delete_product(string $id): void {
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
}

/** Add (or subtract, with a negative number) units to a product's stock. Never goes below 0. */
function adjust_stock(string $id, int $delta): void {
    db()->prepare('UPDATE products SET stock = GREATEST(0, stock + ?) WHERE id = ?')->execute([$delta, $id]);
}

/* ---------- stock requests ---------- */

function all_requests(): array { return array_values($_SESSION['requests']); }

function find_request(string $id): ?array { return $_SESSION['requests'][$id] ?? null; }

function save_request(array $row): void { $_SESSION['requests'][$row['id']] = $row; }

function set_request_status(string $id, string $status): void {
    if (isset($_SESSION['requests'][$id])) $_SESSION['requests'][$id]['status'] = $status;
}

/** Requests newest-first, each with product_name / product_img attached. Optional status filter. */
function requests_joined(string $status = 'all'): array {
    $rows = array_filter(all_requests(), fn($r) => $status === 'all' || $r['status'] === $status);
    foreach ($rows as &$r) {
        $p = find_product($r['product_id']);
        $r['product_name'] = $p['name'] ?? null;
        $r['product_img']  = $p['img'] ?? null;
    }
    unset($r);
    usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    return $rows;
}

/* =========================================================
   4. AUTHENTICATION  ($_SESSION['user'] = username of whoever is logged in)
   ========================================================= */

function current_user(): ?array {
    $row = find_user((string)($_SESSION['user'] ?? ''));
    if (!$row) { unset($_SESSION['user']); return null; }
    return ['name' => $row['name'], 'username' => $row['username'], 'role' => $row['role']];
}

function current_admin(): ?array {
    $u = current_user();
    return ($u && $u['role'] === 'admin') ? $u : null;
}

/** Admin-only pages: guests go to the login page, normal users go back to the store. */
function require_auth(): array {
    $u = current_user();
    if (!$u) redirect('login_reg.php');
    if ($u['role'] !== 'admin') redirect('index.php');
    return $u;
}

/* =========================================================
   5. SHOPPING CART ($_SESSION['cart'] = product id => quantity)
   ========================================================= */

function cart_count(): int { return array_sum(array_map('intval', $_SESSION['cart'] ?? [])); }

function cart_lines(): array {
    $lines = [];
    foreach ($_SESSION['cart'] ?? [] as $id => $qty) {
        $p = find_product((string)$id);
        if (!$p || (int)$p['stock'] <= 0) { unset($_SESSION['cart'][$id]); continue; }
        $qty = max(1, min((int)$qty, (int)$p['stock']));
        $_SESSION['cart'][$id] = $qty;
        $lines[] = ['p' => $p, 'qty' => $qty, 'sub' => $qty * (float)$p['price']];
    }
    return $lines;
}

/** Checkout: all-or-nothing. Every line must still have enough stock, then stock is subtracted. */
function checkout_cart(): array {
    $lines = cart_lines();
    if (!$lines) return [false, 'Your cart is empty.'];

    $total = 0.0;
    foreach ($lines as $l) {
        if ((int)$l['p']['stock'] < $l['qty']) return [false, $l['p']['name'] . ' is no longer available in that quantity.'];
        $total += $l['sub'];
    }
    foreach ($lines as $l) adjust_stock($l['p']['id'], -$l['qty']);
    $_SESSION['cart'] = [];
    return [true, 'Order placed! Total ' . peso($total) . ' — thank you for shopping.'];
}

<?php
/**
 * VoltHub — includes/bootstrap.php  (NO DATABASE VERSION)
 * Shared core: session, constants, helpers, auth, JSON-file storage and cart logic.
 * Data lives in /data/*.json (products, requests, users) — created automatically on first run.
 */
declare(strict_types=1);

session_start();                // $_SESSION: login state, cart, flash messages, CSRF

/* ---------- constants ---------- */
const LOW  = 5;                 // stock at or below this number is "low"
const PER_PAGE = 8;             // products per page on the storefront
define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');

/* =========================================================
   1. SMALL HELPERS
   ========================================================= */

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function peso($n): string { return '₱' . rtrim(rtrim(number_format((float)$n, 2), '0'), '.'); }

function uid(string $p): string { return $p . base_convert((string)(int)(microtime(true) * 1000), 10, 36) . bin2hex(random_bytes(2)); }

function redirect(string $url): never { header('Location: ' . $url); exit; }

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
    $level = $s <= 0 ? 'out' : ($s <= LOW ? 'low' : 'ok');
    switch ($level) {
        case 'out': return ['label' => 'Out of stock', 'cls' => 'badge--danger'];
        case 'low': return ['label' => "Only $s left", 'cls' => 'badge--warn'];
        default:    return ['label' => 'In stock',     'cls' => 'badge--ok'];
    }
}

function status_badge(string $status): string {
    switch ($status) {
        case 'pending':   return '<span class="badge badge--warn">Pending</span>';
        case 'approved':  return '<span class="badge badge--info">Approved</span>';
        case 'received':  return '<span class="badge badge--ok">Received</span>';
        case 'cancelled': return '<span class="badge badge--muted">Cancelled</span>';
        default:          return '';
    }
}

function paginate(array $items, int $page, int $perPage = PER_PAGE): array {
    $pages = max(1, (int)ceil(count($items) / $perPage));
    $page  = min(max(1, $page), $pages);
    return [array_slice($items, ($page - 1) * $perPage, $perPage), $pages, $page];
}

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
   3. JSON FILE STORAGE (replaces MySQL / PDO)
   ========================================================= */

/** Run $fn while holding an exclusive file lock (re-entrant). Prevents two requests writing at once. */
function with_lock(callable $fn) {
    static $depth = 0;
    static $h = null;
    if ($depth > 0) return $fn();
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
    $h = fopen(DATA_DIR . '/.lock', 'c');
    flock($h, LOCK_EX);
    $depth++;
    try {
        return $fn();
    } finally {
        $depth--;
        flock($h, LOCK_UN);
        fclose($h);
    }
}

/** Read a table (products / requests / users) as an array of rows. */
function store_read(string $name): array {
    boot_store();
    $f = DATA_DIR . "/$name.json";
    if (!is_file($f)) return [];
    $data = json_decode((string)file_get_contents($f), true);
    return is_array($data) ? $data : [];
}

/** Save a table. Writes to a temp file first, then renames, so a crash never leaves a half-written file. */
function store_write(string $name, array $rows): void {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
    $f   = DATA_DIR . "/$name.json";
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    rename($tmp, $f);
}

/** First run: create the data folder (blocked from the web) and fill it with demo data. */
function boot_store(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    with_lock(function () {
        $ht = DATA_DIR . '/.htaccess';
        if (!is_file($ht)) file_put_contents($ht, "Require all denied\nDeny from all\n");   // users.json holds password hashes

        if (!is_file(DATA_DIR . '/users.json')) {
            store_write('users', [[
                'id' => 1, 'name' => 'Store Admin', 'username' => 'admin',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin',
            ]]);
        }
        if (!is_file(DATA_DIR . '/products.json') && !is_file(DATA_DIR . '/requests.json')) {
            seed_products();
            seed_requests();
        }
    });
}

/* ---------- demo data ---------- */

function seed_products(): void {
    $rows = [
        ['p1', 'Nova X Pro 5G', 'Phones', 42990, 14, art('📱', '#6366f1', '#a78bfa'), '6.7" AMOLED 120Hz display, 256GB storage and a 50MP triple camera. All-day battery with 65W fast charging.'],
        ['p2', 'AeroBook 14 Slim', 'Laptops', 56990, 6, art('💻', '#0ea5e9', '#6366f1'), 'Ultra-light 14" laptop with 16GB RAM, 512GB SSD and up to 14 hours of battery. Perfect for work and study.'],
        ['p3', 'PulseBuds ANC', 'Audio', 3490, 32, art('🎧', '#ec4899', '#f97316'), 'True wireless earbuds with active noise cancelling, 30-hour battery case and IPX5 water resistance.'],
        ['p4', 'Chrono Fit Watch', 'Wearables', 7990, 4, art('⌚', '#14b8a6', '#3b82f6'), 'Fitness smartwatch with heart-rate and sleep tracking, GPS, 100+ sport modes and a 10-day battery.'],
        ['p5', 'Slate Tab 11', 'Phones', 18990, 9, art('📲', '#f59e0b', '#ef4444'), '11" tablet with a crisp 2K display, quad speakers and a stylus-ready screen. Great for notes and movies.'],
        ['p6', 'KeyForge TKL Mechanical', 'Accessories', 3990, 0, art('⌨️', '#8b5cf6', '#ec4899'), 'Hot-swappable mechanical keyboard with tactile switches, PBT keycaps and per-key RGB lighting.'],
        ['p7', 'Viper Lite Gaming Mouse', 'Accessories', 1890, 21, art('🖱️', '#22c55e', '#0ea5e9'), 'Featherweight 58g mouse with a 26K DPI sensor, 70-hour wireless battery and programmable buttons.'],
        ['p8', 'VoltMax 20,000mAh', 'Accessories', 1590, 3, art('🔋', '#f97316', '#eab308'), '20,000mAh power bank with 45W USB-C fast charging. Charges a phone four times on a single charge.'],
        ['p9', 'BoomBox Mini Speaker', 'Audio', 2790, 17, art('🔊', '#3b82f6', '#8b5cf6'), 'Portable Bluetooth speaker with deep bass, 360° sound and 18 hours of playtime. Splash proof.'],
        ['p10', 'ClearCam 2K Webcam', 'Accessories', 2290, 11, art('📷', '#64748b', '#0f172a'), '2K webcam with auto-focus, dual noise-reducing mics and a privacy shutter for calls and streaming.'],
    ];
    $out = [];
    foreach ($rows as $i => $r) {
        $out[] = ['id' => $r[0], 'pos' => $i + 1, 'name' => $r[1], 'category' => $r[2], 'price' => $r[3],
                  'stock' => $r[4], 'img' => $r[5], 'description' => $r[6]];
    }
    store_write('products', $out);
}

function seed_requests(): void {
    $d = fn(int $n) => date('Y-m-d H:i:s', time() - $n * 86400);
    $rows = [
        ['r1', 'p6', 20, 'KeyForge Distribution', 'Out of stock, high demand.', 'pending', $d(1)],
        ['r2', 'p8', 30, 'VoltMax PH', '', 'approved', $d(3)],
        ['r3', 'p4', 12, 'Chrono Wearables', 'Restock before the holidays.', 'pending', $d(2)],
        ['r4', 'p3', 40, 'PulseAudio Trading', '', 'received', $d(9)],
    ];
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['id' => $r[0], 'product_id' => $r[1], 'qty' => $r[2], 'supplier' => $r[3],
                  'note' => $r[4], 'status' => $r[5], 'created_at' => $r[6]];
    }
    store_write('requests', $out);
}

/** Wipe products and requests and load the demo data again (admin button). Users are kept. */
function reset_demo(): void {
    with_lock(function () {
        boot_store();
        seed_products();
        seed_requests();
    });
}

/* ---------- users ---------- */

function find_user(string $username): ?array {
    foreach (store_read('users') as $u) {
        if ($u['username'] === $username) return $u;
    }
    return null;
}

/** Add an account. Returns false if the username already exists. */
function add_user(string $name, string $username, string $hash, string $role = 'user'): bool {
    return with_lock(function () use ($name, $username, $hash, $role) {
        $rows = store_read('users');
        foreach ($rows as $u) if ($u['username'] === $username) return false;
        $rows[] = ['id' => (int)max(0, ...array_column($rows, 'id') ?: [0]) + 1, 'name' => $name,
                   'username' => $username, 'password_hash' => $hash, 'role' => $role];
        store_write('users', $rows);
        return true;
    });
}

/* ---------- products ---------- */

/** All products in display order. */
function all_products(): array {
    $rows = store_read('products');
    usort($rows, fn($a, $b) => $a['pos'] <=> $b['pos']);
    return $rows;
}

function find_product(string $id): ?array {
    foreach (store_read('products') as $p) {
        if ($p['id'] === $id) return $p;
    }
    return null;
}

/** Insert or update a product (matched by 'id'). New products without 'pos' go to the top of the list. */
function save_product(array $row): void {
    with_lock(function () use ($row) {
        $rows = store_read('products');
        $found = false;
        foreach ($rows as &$p) {
            if ($p['id'] === $row['id']) { $p = array_merge($p, $row); $found = true; break; }
        }
        unset($p);
        if (!$found) {
            $row['pos'] ??= (int)min($rows ? array_column($rows, 'pos') : [1]) - 1;
            $rows[] = $row;
        }
        store_write('products', $rows);
    });
}

function delete_product(string $id): void {
    with_lock(function () use ($id) {
        store_write('products', array_filter(store_read('products'), fn($p) => $p['id'] !== $id));
    });
}

/** Add (or subtract, with a negative number) units to a product's stock. */
function adjust_stock(string $id, int $delta): void {
    with_lock(function () use ($id, $delta) {
        $rows = store_read('products');
        foreach ($rows as &$p) {
            if ($p['id'] === $id) { $p['stock'] = max(0, (int)$p['stock'] + $delta); break; }
        }
        unset($p);
        store_write('products', $rows);
    });
}

/* ---------- stock requests ---------- */

function all_requests(): array { return store_read('requests'); }

function find_request(string $id): ?array {
    foreach (store_read('requests') as $r) {
        if ($r['id'] === $id) return $r;
    }
    return null;
}

function save_request(array $row): void {
    with_lock(function () use ($row) {
        $rows = store_read('requests');
        $rows[] = $row;
        store_write('requests', $rows);
    });
}

function set_request_status(string $id, string $status): void {
    with_lock(function () use ($id, $status) {
        $rows = store_read('requests');
        foreach ($rows as &$r) {
            if ($r['id'] === $id) { $r['status'] = $status; break; }
        }
        unset($r);
        store_write('requests', $rows);
    });
}

/** Requests newest-first, each with product_name / product_img attached. Optional status filter. */
function requests_joined(string $status = 'all'): array {
    $byId = [];
    foreach (store_read('products') as $p) $byId[$p['id']] = $p;
    $rows = array_filter(store_read('requests'), fn($r) => $status === 'all' || $r['status'] === $status);
    foreach ($rows as &$r) {
        $r['product_name'] = $byId[$r['product_id']]['name'] ?? null;
        $r['product_img']  = $byId[$r['product_id']]['img'] ?? null;
    }
    unset($r);
    usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    return $rows;
}

/* =========================================================
   4. AUTHENTICATION
   ========================================================= */

function current_user(): ?array {
    static $cached = false;
    if ($cached !== false) return $cached;
    $s = $_SESSION['admin'] ?? null;
    if (!$s) return $cached = null;
    $row = find_user((string)($s['username'] ?? ''));
    if (!$row) { unset($_SESSION['admin']); return $cached = null; }
    return $cached = ['name' => $row['name'], 'username' => $row['username'], 'role' => $row['role']];
}

function current_admin(): ?array {
    $u = current_user();
    return ($u && ($u['role'] ?? 'user') === 'admin') ? $u : null;
}

function require_auth(): array {
    $u = current_user();
    if (!$u) redirect('login_reg.php');
    if (($u['role'] ?? 'user') !== 'admin') redirect('index.php');
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

/**
 * Checkout: all-or-nothing. Under a file lock, check every line still has enough stock,
 * subtract, and save once. If any line fails, nothing is written.
 */
function checkout_cart(): array {
    $lines = cart_lines();
    if (!$lines) return [false, 'Your cart is empty.'];

    return with_lock(function () use ($lines) {
        $rows  = store_read('products');
        $index = [];
        foreach ($rows as $i => $p) $index[$p['id']] = $i;

        $total = 0.0;
        foreach ($lines as $l) {
            $i = $index[$l['p']['id']] ?? null;
            if ($i === null || (int)$rows[$i]['stock'] < $l['qty']) {
                return [false, $l['p']['name'] . ' is no longer available in that quantity.'];
            }
            $rows[$i]['stock'] = (int)$rows[$i]['stock'] - $l['qty'];
            $total += $l['sub'];
        }
        store_write('products', $rows);
        $_SESSION['cart'] = [];
        return [true, 'Order placed! Total ' . peso($total) . ' — thank you for shopping.'];
    });
}

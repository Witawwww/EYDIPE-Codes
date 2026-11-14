<?php

/**
 * VoltHub — db.php
 * Database connection + shared helper functions.
 * Every page loads this file, then calls session_start() (same style as fin_act2):
 *   require 'db.php';
 *   session_start();
 */

$host = 'localhost';
$dbName = 'volthub';
$username = 'root';
$password = '';

// Data Source Name
$dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

// Make PDO throw an exception when something goes wrong
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $GLOBALS['connection'] = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Low stock threshold
$GLOBALS['LOW_STOCK'] = 5;

// Shown when a product has no uploaded image
define('DEFAULT_IMG', 'assets/default.svg');

/* =========================================================
   5 USER-DEFINED FUNCTIONS (Midterm Requirement)
   ========================================================= */

// 1. Format a price with the peso sign
function formatPeso($price)
{
    return '₱' . number_format($price, 2);
}

// 2. Tell if a product is in stock, low stock or out of stock
function checkStockStatus($stock)
{
    if ($stock <= 0) {
        return "Out of Stock";
    } elseif ($stock <= $GLOBALS['LOW_STOCK']) {
        return "Low Stock";
    } else {
        return "In Stock";
    }
}

// 3. Get all products (each row has an "img" path from the product_images table)
function getAllProducts()
{
    $stmt = $GLOBALS['connection']->prepare(
        "SELECT p.*, COALESCE(i.file_path, '" . DEFAULT_IMG . "') AS img
         FROM products p
         LEFT JOIN product_images i ON i.product_id = p.id
         ORDER BY p.id DESC"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// 4. Get one product by ID
function getProductById($id)
{
    $stmt = $GLOBALS['connection']->prepare(
        "SELECT p.*, COALESCE(i.file_path, '" . DEFAULT_IMG . "') AS img
         FROM products p
         LEFT JOIN product_images i ON i.product_id = p.id
         WHERE p.id = :id"
    );
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

// 5. Calculate the total of everything in the cart
function calculateCartTotal()
{
    $total = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $id => $qty) {
            $product = getProductById($id);
            if ($product) {
                $total += ($product['price'] * $qty);
            }
        }
    }
    return $total;
}

/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */

// Safely print text (prevents XSS)
function e($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Redirect to another page
function redirect($url)
{
    header("Location: " . $url);
    exit;
}

// Save a flash message in the session (shown once on the next page)
function flash($message, $type = 'info')
{
    $_SESSION['flash'][] = ['msg' => $message, 'type' => $type];
}

// Print the flash messages, then clear them
function show_flash()
{
    if (isset($_SESSION['flash'])) {
        echo '<div class="toasts">';
        foreach ($_SESSION['flash'] as $f) {
            echo '<div class="toast toast--' . e($f['type']) . '">' . e($f['msg']) . '</div>';
        }
        echo '</div>';
        unset($_SESSION['flash']);
    }
}

/* =========================================================
   CATEGORIES (read straight from the ENUM in the products table)
   ========================================================= */

// Returns the category names, e.g. ['Phones & Tablets', 'Computers', ...]
function getCategories()
{
    $row = $GLOBALS['connection']->query("SHOW COLUMNS FROM products LIKE 'category'")->fetch();
    preg_match_all("/'([^']+)'/", $row['Type'], $matches);
    return $matches[1];
}

// Path of a category picture you add manually, or '' if there is none yet.
// File name = lowercase category name with dashes, e.g. assets/categories/phones-tablets.jpg
function categoryImage($category)
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($category)), '-');
    foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $ext) {
        if (file_exists(__DIR__ . "/assets/categories/$slug.$ext")) {
            return "assets/categories/$slug.$ext";
        }
    }
    return '';
}

/* =========================================================
   PRODUCT IMAGES (file goes in uploads/, its path goes in product_images)
   ========================================================= */

// Save an uploaded image for a product. Returns '' on success or an error message.
function saveProductImage($product_id, $file)
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ''; // nothing uploaded, keep the current image
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The image could not be uploaded.';
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return 'The image must be 5MB or smaller.';
    }

    // Check that it is really an image and pick the extension from its real type
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($types[$info[2]])) {
        return 'The image must be a JPG, PNG, WEBP or GIF file.';
    }

    $folder = __DIR__ . '/uploads';
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    // Unique file name so two products never overwrite each other's image
    $file_path = 'uploads/' . uniqid('p' . $product_id . '_') . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/' . $file_path)) {
        return 'The image could not be saved.';
    }

    // Remove the old file, then insert or replace the image row
    deleteProductImageFile($product_id);
    $stmt = $GLOBALS['connection']->prepare(
        "INSERT INTO product_images (product_id, file_path) VALUES (:id, :path)
         ON DUPLICATE KEY UPDATE file_path = :path2, uploaded_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([':id' => $product_id, ':path' => $file_path, ':path2' => $file_path]);
    return '';
}

// Delete the image file of a product from the uploads folder (the DB row is handled separately)
function deleteProductImageFile($product_id)
{
    $stmt = $GLOBALS['connection']->prepare("SELECT file_path FROM product_images WHERE product_id = :id");
    $stmt->execute([':id' => $product_id]);
    $old = $stmt->fetchColumn();
    if ($old && strpos($old, 'uploads/') === 0 && file_exists(__DIR__ . '/' . $old)) {
        unlink(__DIR__ . '/' . $old);
    }
}

/* =========================================================
   STOCK REQUESTS LOGIC (Admin Dashboard, session-based)
   ========================================================= */

// Create the starting demo requests once per session
function setupDemoRequests()
{
    if (!isset($_SESSION['requests'])) {
        $_SESSION['requests'] = [
            ['id' => 1, 'product_id' => 1, 'qty' => 20, 'supplier' => 'Supplier A', 'status' => 'pending', 'date' => date('Y-m-d')],
            ['id' => 2, 'product_id' => 2, 'qty' => 15, 'supplier' => 'Supplier B', 'status' => 'approved', 'date' => date('Y-m-d')]
        ];
    }
}

// Get all requests
function getAllRequests()
{
    setupDemoRequests();
    return $_SESSION['requests'];
}

// Save a new request
function saveStockRequest($product_id, $qty, $supplier)
{
    setupDemoRequests();
    $new_id = count($_SESSION['requests']) + 1;
    $_SESSION['requests'][] = [
        'id' => $new_id,
        'product_id' => $product_id,
        'qty' => $qty,
        'supplier' => $supplier,
        'status' => 'pending',
        'date' => date('Y-m-d')
    ];
}

// Change the status of a request (approve, receive, cancel)
function updateRequestStatus($id, $new_status)
{
    setupDemoRequests();
    foreach ($_SESSION['requests'] as $key => $req) {
        if ($req['id'] == $id) {
            $_SESSION['requests'][$key]['status'] = $new_status;
            break;
        }
    }
}

/* =========================================================
   CHECKOUT LOGIC (Cart)
   ========================================================= */

function processCheckout()
{
    if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
        return false;
    }

    // Make sure there is still enough stock in the database before checking out
    foreach ($_SESSION['cart'] as $id => $qty) {
        $product = getProductById($id);
        if (!$product || $product['stock'] < $qty) {
            flash('Not enough stock for ' . ($product ? $product['name'] : 'an item in your cart'), 'error');
            return false;
        }
    }

    // Subtract the purchased quantities from the database stock
    foreach ($_SESSION['cart'] as $id => $qty) {
        $stmt = $GLOBALS['connection']->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
        $stmt->execute([':qty' => $qty, ':id' => $id]);
    }

    // Empty the cart
    $_SESSION['cart'] = [];
    return true;
}

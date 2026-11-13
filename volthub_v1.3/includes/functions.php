<?php

/**
 * VoltHub — includes/functions.php
 * Dito naka-save ang mga custom functions at shared logic ng system.
 * session, database queries, cart, at stock requests.
 */

// Simulan ang session para magamit ang $_SESSION
session_start();

// Isama ang database connection (galing sa labas ng includes folder)
require_once __DIR__ . '/../db.php';

// Constant para sa low stock threshold
$GLOBALS['LOW_STOCK'] = 5;

/* =========================================================
   5 USER-DEFINED FUNCTIONS (Midterm Requirement)
   ========================================================= */

// 1. Function para i-format ang presyo na may peso sign
function formatPeso($price)
{
    return '₱' . number_format($price, 2);
}

// 2. Function para malaman kung low stock, out of stock, o in stock
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

// 3. Function para kunin lahat ng products sa database
function getAllProducts()
{
    $stmt = $GLOBALS['connection']->prepare("SELECT * FROM products ORDER BY id DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// 4. Function para kumuha ng isang product base sa ID
function getProductById($id)
{
    $stmt = $GLOBALS['connection']->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

// 5. Function para kalkulahin ang total ng nasa cart
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

// Function para safe i-display ang text (iwas XSS)
function e($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Function para mag-redirect
function redirect($url)
{
    header("Location: " . $url);
    exit;
}

// Function para mag-set ng flash message
function flash($message, $type = 'info')
{
    $_SESSION['flash'][] = ['msg' => $message, 'type' => $type];
}

// Checker kung may naka-login na user
function current_user()
{
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

// Checker kung admin ang naka-login
function current_admin()
{
    $user = current_user();
    if ($user && $user['role'] === 'admin') {
        return $user;
    }
    return null;
}

// I-limit ang access sa mga admin pages
function require_auth()
{
    if (!current_admin()) {
        redirect('login_reg.php');
    }
}

/* =========================================================
   STOCK REQUESTS LOGIC (Admin Dashboard)
   ========================================================= */

// Function para i-setup ang initial demo requests
function setupDemoRequests()
{
    if (!isset($_SESSION['requests'])) {
        $_SESSION['requests'] = [
            ['id' => 1, 'product_id' => 1, 'qty' => 20, 'supplier' => 'Supplier A', 'status' => 'pending', 'date' => date('Y-m-d')],
            ['id' => 2, 'product_id' => 2, 'qty' => 15, 'supplier' => 'Supplier B', 'status' => 'approved', 'date' => date('Y-m-d')]
        ];
    }
}

// Kunin lahat ng requests
function getAllRequests()
{
    setupDemoRequests();
    return $_SESSION['requests'];
}

// Mag-save ng bagong request
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

// I-update ang status ng request (approve, receive, cancel)
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

    // Checker kung sapat pa ang stock sa database bago mag-checkout
    foreach ($_SESSION['cart'] as $id => $qty) {
        $product = getProductById($id);
        if (!$product || $product['stock'] < $qty) {
            flash('Not enough stock for ' . $product['name'], 'error');
            return false;
        }
    }

    // Ibawas ang binili sa stock ng database
    foreach ($_SESSION['cart'] as $id => $qty) {
        $stmt = $GLOBALS['connection']->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
        $stmt->execute([':qty' => $qty, ':id' => $id]);
    }

    // Burahin ang cart pagkatapos
    $_SESSION['cart'] = [];
    return true;
}

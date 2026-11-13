<?php
/**
 * VoltHub — php/logout.php: clears the login session ($_SESSION) and returns to the login page.
 */
declare(strict_types=1);
require __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    unset($_SESSION['user']);
    session_regenerate_id(true);
}
redirect('login_reg.php');

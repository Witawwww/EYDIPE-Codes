<?php
/**
 * VoltHub — db.php (PDO Connection)
 */

$host = 'localhost';
$dbName = 'volthub';
$username = 'root';
$password = '';

// Data Source Name
$dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

// Options para sure na error exception ang ibabato kung may mali
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    //connection at nilagay sa $GLOBALS para ma-access ng ibang files
    $GLOBALS['connection'] = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Kapag hindi nakakonekta, patayin ang system at ipakita ang error
    die("Database connection failed: " . $e->getMessage());
}

/*
=====================================================================
QUERIES NA KAILANGAN I-PASTE SA PHPMYADMIN (SQL TAB)
=====================================================================

-- 1. database
CREATE DATABASE IF NOT EXISTS volthub;
USE volthub;

-- 2.  users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user'
);

-- 3. products table (Para sa CRUD)
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(60) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL,
    description TEXT NOT NULL,
    img VARCHAR(255) NOT NULL
);

-- 4. insert ng admin account (Password is 'admin123')
INSERT INTO users (name, username, email, password, role) 
VALUES ('Store Admin', 'admin', 'admin@volthub.com', '$2y$10$wW56b.5Xm6B8Vw.3Q3Q3QeW56b.5Xm6B8Vw.3Q3Q3QeW56b.5Xm6B8Vw', 'admin');
=====================================================================
*/
?>
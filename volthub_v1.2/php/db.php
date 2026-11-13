<?php
/**
 * VoltHub — php/db.php  (PDO connection + automatic setup)
 *
 * Products live in the MySQL/MariaDB database `volthub` (table `products`).
 * Call db() anywhere to get the shared PDO connection. The first time it runs it will:
 *   1. create the database `volthub` if it does not exist,
 *   2. create the tables `products` and `product_photos` if they do not exist,
 *   3. remember (db_is_fresh()) that the tables were just created, so functions.php can load the sample products.
 *
 * Change the four settings below if your MySQL is not the XAMPP/Laragon default.
 */

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'volthub';

const DB_OPTIONS = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

/** Shared PDO connection (created on first use). */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    try {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, DB_OPTIONS);
        } catch (PDOException $e) {
            if ((int)$e->getCode() !== 1049) throw $e;          // 1049 = unknown database -> create it
            $boot = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, DB_OPTIONS);
            $boot->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` DEFAULT CHARACTER SET utf8mb4');
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, DB_OPTIONS);
        }
        db_setup($pdo);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('<h3>VoltHub could not connect to the database.</h3>'
           . '<p>Start MySQL (XAMPP / Laragon), then check the settings at the top of <code>php/db.php</code>.</p>'
           . '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>');
    }
    return $pdo;
}

/** True only during the request in which the `products` table was created. */
function db_is_fresh(bool $set = false): bool {
    static $fresh = false;
    if ($set) $fresh = true;
    return $fresh;
}

/** Create the tables if they are missing. */
function db_setup(PDO $pdo): void {
    $exists = $pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn() !== false;

    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id`          VARCHAR(20)   NOT NULL,
        `pos`         INT           NOT NULL DEFAULT 0,
        `name`        VARCHAR(150)  NOT NULL,
        `category`    VARCHAR(60)   NOT NULL,
        `price`       DECIMAL(10,2) NOT NULL DEFAULT 0,
        `stock`       INT           NOT NULL DEFAULT 0,
        `description` TEXT          NOT NULL,
        `img`         MEDIUMTEXT    NOT NULL,
        `created_at`  TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_category` (`category`),
        KEY `idx_pos` (`pos`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_photos` (
        `id`          INT          NOT NULL AUTO_INCREMENT,
        `product_id`  VARCHAR(20)  NOT NULL,
        `file_name`   VARCHAR(255) NOT NULL,
        `file_path`   VARCHAR(255) NOT NULL,
        `uploaded_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `product_id` (`product_id`),
        CONSTRAINT `fk_photo_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!$exists) db_is_fresh(true);
}

CREATE DATABASE IF NOT EXISTS volthub;

USE volthub;

CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `category` enum('Phones & Tablets','Computers','Audio','Wearables','Cameras','Gaming','Smart Home','Accessories & Power') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One image per product. file_path is relative to the project folder (e.g. uploads/abc123.jpg).
-- Deleting a product also deletes its image row.
CREATE TABLE `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`),
  CONSTRAINT `fk_image_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`id`, `name`, `category`, `price`, `stock`, `description`) VALUES
(1, 'Apple iPhone 16', 'Phones & Tablets', 37200.00, 25, 'A smartphone series developed by Apple that runs iOS. It is recognized for its camera quality, consistent performance and extended software support. Typical price: PHP 37,200 to 99,200.'),
(2, 'Samsung Galaxy S series', 'Phones & Tablets', 43400.00, 12, 'The flagship Android smartphone series from Samsung. It features high-quality displays, advanced camera systems and a comprehensive set of software features. Typical price: PHP 43,400 to 80,600.'),
(3, 'Apple iPad', 'Phones & Tablets', 21700.00, 40, 'A tablet series from Apple for media consumption, note-taking, creative work and light productivity. Models range from entry-level to professional grade. Typical price: PHP 21,700 to 74,400.'),
(4, 'Apple MacBook Air', 'Computers', 62000.00, 0, 'A thin and lightweight laptop from Apple with a fanless design and long battery life. It is suited to everyday work, study and general use. Typical price: PHP 62,000 to 99,200.'),
(5, 'Dell XPS 13', 'Computers', 62000.00, 15, 'A compact premium Windows ultrabook from Dell with a narrow-bezel display and a refined, portable build. Typical price: PHP 62,000 to 99,200.'),
(6, 'ASUS ROG Zephyrus (gaming)', 'Computers', 93000.00, 20, 'A slim high-performance gaming laptop from ASUS with dedicated graphics. It is intended for demanding games and graphics-intensive applications. Typical price: PHP 93,000 to 186,000.'),
(7, 'Apple AirPods Pro', 'Audio', 15500.00, 18, 'Wireless in-ear earphones from Apple with active noise cancellation. They integrate closely with other Apple devices. Typical price: PHP 15,500.'),
(8, 'Sony WH-1000XM series headphones', 'Audio', 18600.00, 50, 'Over-ear wireless headphones from Sony. The series is widely regarded for its noise cancellation, sound quality and long battery life. Typical price: PHP 18,600 to 27,900.'),
(9, 'JBL Flip portable speaker', 'Audio', 6200.00, 12, 'A portable Bluetooth speaker from JBL with a water-resistant construction. It is designed for outdoor use and travel. Typical price: PHP 6,200 to 9,300.'),
(10, 'Apple Watch', 'Wearables', 15500.00, 40, 'A smartwatch from Apple designed for use with the iPhone. It provides health monitoring, fitness tracking and notification management. Typical price: PHP 15,500 to 49,600.'),
(11, 'Samsung Galaxy Watch', 'Wearables', 12400.00, 3, 'A smartwatch from Samsung running Wear OS. It offers health and fitness monitoring and is designed to operate with Android phones. Typical price: PHP 12,400 to 27,900.'),
(12, 'Fitbit Charge', 'Wearables', 8100.00, 15, 'A slim fitness tracker from Fitbit. It records activity, heart rate and sleep data for everyday health monitoring. Typical price: PHP 8,100 to 10,500.'),
(13, 'GoPro Hero', 'Cameras', 12400.00, 6, 'A compact action camera from GoPro. It is waterproof, durable and designed for recording sports and outdoor activities. Typical price: PHP 12,400 to 24,800.'),
(14, 'Canon EOS R series', 'Cameras', 43400.00, 18, 'A range of mirrorless interchangeable-lens cameras from Canon. It supports both photography and video across a wide selection of lenses. Typical price: PHP 43,400 to 186,000+.'),
(15, 'Fujifilm Instax Mini (instant camera)', 'Cameras', 4340.00, 25, 'An instant film camera from Fujifilm that produces credit-card-sized prints. It is commonly used for casual photography and keepsakes. Typical price: PHP 4,340 to 6,200.'),
(16, 'Sony PlayStation 5', 'Gaming', 27900.00, 40, 'A home video game console from Sony. It features fast solid-state storage and supports 4K gaming. Typical price: PHP 27,900 to 34,100.'),
(17, 'Nintendo Switch', 'Gaming', 15500.00, 3, 'A hybrid gaming console from Nintendo. It can be used connected to a television or as a handheld device. Typical price: PHP 15,500 to 27,900.'),
(18, 'Valve Steam Deck', 'Gaming', 24800.00, 15, 'A handheld gaming computer from Valve. It allows users to play games from their Steam library on a portable device. Typical price: PHP 24,800 to 40,300.'),
(19, 'Amazon Echo Dot', 'Smart Home', 3100.00, 6, 'A compact smart speaker from Amazon with the Alexa voice assistant. It supports voice commands, music playback and smart home control. Typical price: PHP 3,100 to 3,720.'),
(20, 'Ring Video Doorbell', 'Smart Home', 3720.00, 18, 'A video doorbell that sends motion alerts and live video to a mobile device. It is intended for home monitoring and visitor identification. Typical price: PHP 3,720 to 15,500.'),
(21, 'Roomba robot vacuum', 'Smart Home', 12400.00, 25, 'A series of robotic vacuum cleaners from iRobot. They clean floors automatically, and selected models also include mopping. Typical price: PHP 12,400 to 62,000.'),
(22, 'Anker PowerCore power banks', 'Accessories & Power', 1550.00, 12, 'Portable battery packs from Anker used to charge phones, tablets and other devices while away from a power outlet. Typical price: PHP 1,550 to 7,400.'),
(23, 'Apple AirTag', 'Accessories & Power', 1860.00, 8, 'A small tracking device from Apple. It helps users locate personal items through the Find My network. Typical price: PHP 1,860.'),
(24, 'Logitech MX Master mouse', 'Accessories & Power', 6200.00, 40, 'An ergonomic wireless mouse from Logitech. It is designed for productivity and includes programmable buttons. Typical price: PHP 6,200.');

INSERT INTO `product_images` (`id`, `product_id`, `file_path`, `uploaded_at`) VALUES
(1, 24, 'uploads/p24_6af7da76989ed.jpg', '2026-11-14 03:50:14'),
(2, 23, 'uploads/p23_6af7d8e039062.jpg', '2026-11-14 03:43:28'),
(3, 22, 'uploads/p22_6af7d8ed1bcec.jpg', '2026-11-14 03:43:41'),
(4, 21, 'uploads/p21_6af7da9561bfd.jpg', '2026-11-14 03:50:45'),
(5, 20, 'uploads/p20_6af7d8fc38023.webp', '2026-11-14 03:43:56'),
(6, 19, 'uploads/p19_6af7d9040942d.webp', '2026-11-14 03:44:04'),
(7, 18, 'uploads/p18_6af7d9130edc3.jpg', '2026-11-14 03:44:19'),
(8, 17, 'uploads/p17_6af7d91c02449.jpg', '2026-11-14 03:44:28'),
(9, 16, 'uploads/p16_6af7d92863e59.webp', '2026-11-14 03:44:40'),
(10, 15, 'uploads/p15_6af7d93288b56.jpg', '2026-11-14 03:44:50'),
(11, 14, 'uploads/p14_6af7d93c26914.jpg', '2026-11-14 03:45:00'),
(12, 13, 'uploads/p13_6af7d948010b0.jpg', '2026-11-14 03:45:12'),
(13, 12, 'uploads/p12_6af7d9520e4d4.jpg', '2026-11-14 03:45:22'),
(14, 11, 'uploads/p11_6af7d95fb85d0.jpg', '2026-11-14 03:45:35'),
(15, 10, 'uploads/p10_6af7d96a7f90c.jpg', '2026-11-14 03:45:46'),
(16, 9, 'uploads/p9_6af7d976e4b94.webp', '2026-11-14 03:45:58'),
(17, 8, 'uploads/p8_6af7d98a3a5d6.jpg', '2026-11-14 03:46:18'),
(18, 7, 'uploads/p7_6af7d99510ad7.jpg', '2026-11-14 03:46:29'),
(19, 6, 'uploads/p6_6af7d99df04a4.png', '2026-11-14 03:46:37'),
(20, 5, 'uploads/p5_6af7d9a618724.jpg', '2026-11-14 03:46:46'),
(21, 4, 'uploads/p4_6af7d9d317cc4.jpg', '2026-11-14 03:47:31'),
(22, 3, 'uploads/p3_6af7d9fa4c151.jpg', '2026-11-14 03:48:10'),
(23, 2, 'uploads/p2_6af7da385b45d.jpg', '2026-11-14 03:49:12'),
(24, 1, 'uploads/p1_6af7da62c8a75.jpg', '2026-11-14 03:49:54');

INSERT INTO `users` (`id`, `name`, `username`, `email`, `password`, `role`) VALUES
(1, 'admin', 'admin', 'adminadmin@gmail.com', '$2y$10$3eM3rA6PBOM9QhoneVRcD.DHRNt3IicTRzqi9pOPZg0i66l1EhQ6O', 'admin');

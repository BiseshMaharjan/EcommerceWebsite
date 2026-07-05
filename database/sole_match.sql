-- ============================================
-- SOLEMATCH - Complete Database
-- Database: sole_match
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS `sole_match`;
USE `sole_match`;

-- ============================================
-- 1. USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100),
    `phone` VARCHAR(20),
    `address` TEXT,
    `city` VARCHAR(50),
    `postal_code` VARCHAR(20),
    `role` ENUM('admin','manager','customer') DEFAULT 'customer',
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- 2. BRANDS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `brands` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) UNIQUE NOT NULL,
    `description` TEXT,
    `logo_url` VARCHAR(255),
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 3. CATEGORIES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(50) UNIQUE NOT NULL,
    `description` TEXT,
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- 4. PRODUCTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `brand_id` INT,
    `category_id` INT,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL,
    `stock` INT DEFAULT 0,
    `image_url` VARCHAR(255),
    `status` ENUM('active','inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`brand_id`) REFERENCES `brands`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
);

-- ============================================
-- 5. PRODUCT VARIANTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `product_id` INT NOT NULL,
    `variant_name` VARCHAR(50) NOT NULL,
    `size` VARCHAR(10),
    `color` VARCHAR(30),
    `price` DECIMAL(10,2),
    `stock` INT DEFAULT 0,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
);

-- ============================================
-- 6. CART TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `cart` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `variant_id` INT,
    `quantity` INT DEFAULT 1,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `unique_cart_item` (`user_id`, `product_id`, `variant_id`)
);

-- ============================================
-- 7. ORDERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `order_number` VARCHAR(50) UNIQUE NOT NULL,
    `user_id` INT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `shipping_address` TEXT,
    `shipping_city` VARCHAR(50),
    `shipping_postal` VARCHAR(20),
    `payment_method` ENUM('cod','khalti','esewa','card') NOT NULL,
    `payment_status` ENUM('pending','paid','failed') DEFAULT 'pending',
    `order_status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    `transaction_id` VARCHAR(100),
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- ============================================
-- 8. ORDER ITEMS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `variant_id` INT,
    `quantity` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL
);

-- ============================================
-- 9. ADMIN LOGS TABLE (Optional)
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `admin_id` INT,
    `action` VARCHAR(100),
    `details` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

-- ============================================
-- ============================================
-- INSERT SAMPLE DATA
-- ============================================
-- ============================================

-- ============================================
-- 1. INSERT USERS
-- ============================================
-- Password: admin123 (hashed)
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `phone`, `role`, `status`) VALUES
('admin', 'admin@solematch.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', '9800000000', 'admin', 'active'),
('demo_user', 'demo@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo User', '9800000001', 'customer', 'active'),
('john_doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '9800000002', 'customer', 'active'),
('jane_smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', '9800000003', 'customer', 'active'),
('guest_user', 'guest@solematch.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guest User', '9800000004', 'customer', 'active');

-- ============================================
-- 2. INSERT CATEGORIES
-- ============================================
INSERT INTO `categories` (`name`, `description`, `status`) VALUES
('Running', 'Performance running shoes for all terrains', 'active'),
('Casual', 'Everyday casual footwear for comfort', 'active'),
('Boots', 'Durable boots for all weather conditions', 'active'),
('Heritage', 'Classic vintage styles with modern comfort', 'active'),
('Trail', 'Off-road and trail running shoes', 'active'),
('Performance', 'High-performance athletic shoes', 'active'),
('Lifestyle', 'Stylish footwear for daily life', 'active');

-- ============================================
-- 3. INSERT BRANDS
-- ============================================
INSERT INTO `brands` (`name`, `description`, `status`) VALUES
('Nike', 'American sportswear brand', 'active'),
('Adidas', 'German multinational sportswear', 'active'),
('New Balance', 'American sports footwear', 'active'),
('Vans', 'American skateboarding shoes', 'active'),
('Converse', 'American shoe company', 'active'),
('ASICS', 'Japanese athletic equipment', 'active'),
('Salomon', 'French sports equipment', 'active'),
('Puma', 'German multinational sportswear', 'active'),
('Reebok', 'American fitness footwear', 'active'),
('Timberland', 'American outdoor footwear', 'active'),
('Merrell', 'American outdoor footwear', 'active'),
('Under Armour', 'American sports equipment', 'active');

-- ============================================
-- 4. INSERT PRODUCTS
-- ============================================
INSERT INTO `products` (`name`, `brand_id`, `category_id`, `description`, `price`, `stock`, `image_url`, `status`) VALUES
(
    'Air Zoom Pegasus 40',
    (SELECT id FROM brands WHERE name = 'Nike'),
    (SELECT id FROM categories WHERE name = 'Running'),
    'The Nike Air Zoom Pegasus 40 delivers a responsive ride with a breathable upper and ample cushioning for daily runs.',
    12990, 24,
    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&h=300&fit=crop',
    'active'
),
(
    'Ultraboost Light',
    (SELECT id FROM brands WHERE name = 'Adidas'),
    (SELECT id FROM categories WHERE name = 'Running'),
    'The Adidas Ultraboost Light features the lightest BOOST foam ever, delivering energy return with every stride.',
    15990, 18,
    'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=400&h=300&fit=crop',
    'active'
),
(
    '550 Vintage',
    (SELECT id FROM brands WHERE name = 'New Balance'),
    (SELECT id FROM categories WHERE name = 'Casual'),
    'The New Balance 550 Vintage is a retro basketball shoe turned lifestyle icon with premium leather and classic styling.',
    11250, 32,
    'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=400&h=300&fit=crop',
    'active'
),
(
    'Old Skool',
    (SELECT id FROM brands WHERE name = 'Vans'),
    (SELECT id FROM categories WHERE name = 'Casual'),
    'The Vans Old Skool is a classic skate shoe featuring the iconic side stripe and durable canvas construction.',
    6490, 45,
    'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=400&h=300&fit=crop',
    'active'
),
(
    'Chuck 70 Hi',
    (SELECT id FROM brands WHERE name = 'Converse'),
    (SELECT id FROM categories WHERE name = 'Casual'),
    'The Converse Chuck 70 Hi is a premium version of the classic canvas high-top with enhanced cushioning and durability.',
    7590, 28,
    'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=400&h=300&fit=crop',
    'active'
),
(
    'Gel-Kayano 30',
    (SELECT id FROM brands WHERE name = 'ASICS'),
    (SELECT id FROM categories WHERE name = 'Running'),
    'The ASICS Gel-Kayano 30 offers premium stability with gel cushioning and a supportive fit for overpronators.',
    18490, 15,
    'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=400&h=300&fit=crop',
    'active'
),
(
    'Trailhead GTX',
    (SELECT id FROM brands WHERE name = 'Salomon'),
    (SELECT id FROM categories WHERE name = 'Trail'),
    'The Salomon Trailhead GTX is a waterproof trail shoe with Gore-Tex protection and aggressive grip for off-road terrain.',
    21990, 12,
    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=400&h=300&fit=crop',
    'active'
),
(
    'Suede Classic',
    (SELECT id FROM brands WHERE name = 'Puma'),
    (SELECT id FROM categories WHERE name = 'Casual'),
    'The Puma Suede Classic is an iconic lifestyle sneaker with a premium suede upper and timeless design.',
    7990, 36,
    'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=400&h=300&fit=crop',
    'active'
),
(
    'Vortex Elite Runner',
    (SELECT id FROM brands WHERE name = 'Nike'),
    (SELECT id FROM categories WHERE name = 'Performance'),
    'The Vortex Elite Runner is engineered for speed with a carbon-fiber plate and responsive foam for race day performance.',
    19990, 8,
    'https://images.unsplash.com/photo-1595950653106-6c9ebd614d3a?w=400&h=300&fit=crop',
    'active'
),
(
    'Orbit Max',
    (SELECT id FROM brands WHERE name = 'New Balance'),
    (SELECT id FROM categories WHERE name = 'Lifestyle'),
    'The Orbit Max is a chunky retro sneaker with maximum comfort and a bold, futuristic design.',
    11990, 21,
    'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=400&h=300&fit=crop',
    'active'
),
(
    'Premium Boot',
    (SELECT id FROM brands WHERE name = 'Timberland'),
    (SELECT id FROM categories WHERE name = 'Boots'),
    'The Premium Boot is a waterproof leather boot with a padded collar and rugged outsole for all-season wear.',
    23990, 14,
    'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?w=400&h=300&fit=crop',
    'active'
);

-- ============================================
-- 5. INSERT PRODUCT VARIANTS
-- ============================================
INSERT INTO `product_variants` (`product_id`, `variant_name`, `size`, `color`, `price`, `stock`) VALUES
(1, 'Black/White', 'US 8', 'Black', 12990, 5),
(1, 'Black/White', 'US 9', 'Black', 12990, 7),
(1, 'Black/White', 'US 10', 'Black', 12990, 4),
(1, 'White/Blue', 'US 8', 'White/Blue', 12990, 3),
(1, 'White/Blue', 'US 9', 'White/Blue', 12990, 5),
(2, 'Core Black', 'US 8', 'Black', 15990, 4),
(2, 'Core Black', 'US 9', 'Black', 15990, 6),
(2, 'Core Black', 'US 10', 'Black', 15990, 3),
(3, 'White/Navy', 'US 8', 'White/Navy', 11250, 8),
(3, 'White/Navy', 'US 9', 'White/Navy', 11250, 10);

-- ============================================
-- ============================================
-- SAMPLE ORDERS (for testing)
-- ============================================
-- ============================================

INSERT INTO `orders` (`order_number`, `user_id`, `total_amount`, `shipping_address`, `shipping_city`, `payment_method`, `payment_status`, `order_status`, `created_at`) VALUES
('STRD-20260630-ABC123', 2, 12990, 'Demo User, 123 Main St, Kathmandu, 44600', 'Kathmandu', 'cod', 'pending', 'pending', '2026-06-30 10:30:00'),
('STRD-20260630-DEF456', 3, 15990, 'John Doe, 456 Oak Ave, Kathmandu, 44600', 'Kathmandu', 'cod', 'pending', 'confirmed', '2026-06-30 11:45:00'),
('STRD-20260630-GHI789', 4, 11250, 'Jane Smith, 789 Pine Rd, Kathmandu, 44600', 'Kathmandu', 'khalti', 'paid', 'delivered', '2026-06-30 14:20:00');

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 1, 12990),
(2, 2, 1, 15990),
(3, 3, 1, 11250);

-- ============================================
-- ============================================
-- SAMPLE CART ITEMS
-- ============================================
-- ============================================

INSERT INTO `cart` (`user_id`, `product_id`, `quantity`) VALUES
(2, 4, 2),
(3, 5, 1),
(4, 6, 3);

-- ============================================
-- ============================================
-- FINAL VERIFICATION
-- ============================================
-- ============================================

SELECT '✅ Database Setup Complete!' AS Status;
SELECT '📊 Tables Created:' AS Info;
SHOW TABLES;

SELECT '📈 Data Counts:' AS Info;
SELECT 'Users: ' || COUNT(*) FROM users UNION
SELECT 'Brands: ' || COUNT(*) FROM brands UNION
SELECT 'Categories: ' || COUNT(*) FROM categories UNION
SELECT 'Products: ' || COUNT(*) FROM products UNION
SELECT 'Orders: ' || COUNT(*) FROM orders;

-- ============================================
-- DEFAULT ADMIN CREDENTIALS
-- ============================================
SELECT '🔑 Default Admin Credentials:' AS Info;
SELECT 'Email: admin@solematch.com' AS Credential;
SELECT 'Password: admin123' AS Credential;
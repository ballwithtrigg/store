-- ============================================================
-- YOURSTORE Database Schema
-- For MySQL / MariaDB (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS yourstore
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE yourstore;

-- ============================================================
-- 1. USERS
--    Holds customer and admin accounts.
--    Maps to: login.html & signup.html (email + password)
-- ============================================================
CREATE TABLE users (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,              -- store bcrypt / argon2 hash
    full_name   VARCHAR(255)    DEFAULT NULL,
    phone       VARCHAR(30)     DEFAULT NULL,
    role        ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. CATEGORIES
--    Normalised lookup for the four categories visible in the
--    navigation bar: Men, Women, Shoes, Accessories.
-- ============================================================
CREATE TABLE categories (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL UNIQUE,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed the default categories
INSERT INTO categories (name) VALUES
    ('Men'),
    ('Women'),
    ('Shoes'),
    ('Accessories');

-- ============================================================
-- 3. PRODUCTS
--    Core product catalogue. Maps 1-to-1 with the JS objects
--    currently stored in localStorage.
-- ============================================================
CREATE TABLE products (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255)    NOT NULL,
    price           DECIMAL(10,2)   NOT NULL,
    image_url       VARCHAR(512)    NOT NULL,
    category_id     INT UNSIGNED    NOT NULL,
    stock_quantity  INT UNSIGNED    DEFAULT 0,
    description     TEXT            DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_product_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_products_category (category_id),
    FULLTEXT INDEX idx_products_search (name, description)   -- powers the search bar
) ENGINE=InnoDB;

-- ============================================================
-- 4. PRODUCT_SIZES
--    Each product can have many sizes (S, M, L, XL, 7, 8 …).
--    Stored as a separate table because sizes are variable-length.
-- ============================================================
CREATE TABLE product_sizes (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED    NOT NULL,
    size_label  VARCHAR(20)     NOT NULL,

    CONSTRAINT fk_size_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uq_product_size (product_id, size_label)
) ENGINE=InnoDB;

-- ============================================================
-- 5. ORDERS
--    Created when a customer submits the checkout form.
--    Captures the customer info (name, phone, address) from
--    checkout.html.
-- ============================================================
CREATE TABLE orders (
    id                  INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED    DEFAULT NULL,           -- NULL for guest checkout
    customer_name       VARCHAR(255)    NOT NULL,
    customer_phone      VARCHAR(30)     NOT NULL,
    shipping_address    TEXT            NOT NULL,
    total               DECIMAL(10,2)   NOT NULL,
    status              ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled')
                            NOT NULL DEFAULT 'pending',
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_order_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    INDEX idx_orders_user   (user_id),
    INDEX idx_orders_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 6. ORDER_ITEMS
--    Line items for each order. Mirrors the cart items that
--    currently live in localStorage (id, name, price, size, qty).
-- ============================================================
CREATE TABLE order_items (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    order_id        INT UNSIGNED    NOT NULL,
    product_id      INT UNSIGNED    NOT NULL,
    product_name    VARCHAR(255)    NOT NULL,   -- snapshot at time of purchase
    product_image   VARCHAR(512)    DEFAULT NULL,
    size            VARCHAR(20)     NOT NULL,
    quantity        INT UNSIGNED    NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2)   NOT NULL,   -- snapshot at time of purchase
    line_total      DECIMAL(10,2)   GENERATED ALWAYS AS (quantity * unit_price) STORED,

    CONSTRAINT fk_item_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_item_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    INDEX idx_items_order   (order_id),
    INDEX idx_items_product (product_id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. CART  (optional – server-side cart persistence)
--    Allows carts to survive across devices / sessions.
--    Can replace the current localStorage cart entirely.
-- ============================================================
CREATE TABLE cart_items (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NOT NULL,
    product_id  INT UNSIGNED    NOT NULL,
    size        VARCHAR(20)     NOT NULL,
    quantity    INT UNSIGNED    NOT NULL DEFAULT 1,
    added_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_cart_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_cart_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uq_cart_item (user_id, product_id, size)
) ENGINE=InnoDB;

-- ============================================================
-- 8. REVIEWS
--    Customer feedback on products.
-- ============================================================
CREATE TABLE reviews (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment     TEXT            DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_review_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_review_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE KEY uq_user_product_review (user_id, product_id)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA  –  matches the 6 products in index.html
-- ============================================================
INSERT INTO products (id, name, price, image_url, category_id, description) VALUES
    (1, 'Classic Denim Jacket',   79.99,  'https://via.placeholder.com/300x200/4B5563/FFFFFF?text=Denim+Jacket',   1, 'A timeless classic, our denim jacket is perfect for any casual occasion. Made from 100% premium cotton for comfort and durability.'),
    (2, 'Elegant Summer Dress',   49.99,  'https://via.placeholder.com/300x200/EC4899/FFFFFF?text=Summer+Dress',   2, 'Light and airy, this summer dress is designed for ultimate comfort and style. Features a floral pattern and a flattering silhouette.'),
    (3, 'Sporty Running Shoes',   99.00,  'https://via.placeholder.com/300x200/10B981/FFFFFF?text=Running+Shoes',  3, 'Achieve your best performance with our lightweight and supportive running shoes. Engineered for speed and comfort on any terrain.'),
    (4, 'Leather Crossbody Bag', 120.00,  'https://via.placeholder.com/300x200/F59E0B/FFFFFF?text=Crossbody+Bag',  4, 'Stylish and practical, this genuine leather crossbody bag is perfect for carrying your essentials. Features multiple compartments and an adjustable strap.'),
    (5, 'Men''s Casual Shirt',    35.50,  'https://via.placeholder.com/300x200/6B7280/FFFFFF?text=Casual+Shirt',   1, 'Comfortable and versatile, this casual shirt is a wardrobe staple. Made from soft cotton, ideal for everyday wear.'),
    (6, 'High-Waisted Jeans',     55.00,  'https://via.placeholder.com/300x200/9CA3AF/FFFFFF?text=High-Waisted+Jeans', 2, 'Flattering and fashionable, these high-waisted jeans offer a perfect fit. Made with stretch denim for comfort.');

INSERT INTO product_sizes (product_id, size_label) VALUES
    (1, 'S'), (1, 'M'), (1, 'L'), (1, 'XL'),
    (2, 'XS'), (2, 'S'), (2, 'M'), (2, 'L'),
    (3, '7'), (3, '8'), (3, '9'), (3, '10'), (3, '11'),
    (4, 'One Size'),
    (5, 'S'), (5, 'M'), (5, 'L'), (5, 'XL'),
    (6, '26'), (6, '28'), (6, '30'), (6, '32');

-- Default admin account (password should be hashed in production!)
INSERT INTO users (email, password, full_name, role) VALUES
    ('admin@yourstore.com', '$2y$10$PLACEHOLDER_HASH_CHANGE_ME', 'Store Admin', 'admin');

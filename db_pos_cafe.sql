CREATE DATABASE db_pos_system;
USE db_pos_system;

CREATE TABLE users (
    user_ID INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('cashier', 'manager', 'kitchen') NOT NULL
);

CREATE TABLE products (
    product_ID INT AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255),
    name VARCHAR(100) NOT NULL,
    category ENUM('Cold Drinks', 'Hot Drinks', 'Snacks') NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL
);

CREATE TABLE sales (
    sales_ID INT AUTO_INCREMENT PRIMARY KEY,
    user_ID INT NOT NULL,
    product_ID INT NOT NULL,
    quantity INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    type ENUM('Dine In', 'Take Out') NOT NULL,
    discount ENUM('Normal', 'Senior', 'PWD') NOT NULL,
    note TEXT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_ID) REFERENCES users(user_ID),
    FOREIGN KEY (product_ID) REFERENCES products(product_ID)
);

ALTER TABLE sales
ADD customer_payment DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
ADD payment_method ENUM('Cash') NOT NULL DEFAULT 'Cash',
ADD change_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00;

-- Add transactions table
CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_type` enum('none','senior','pwd') DEFAULT 'none',
  `discount_id` varchar(50) DEFAULT NULL,
  `vat_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `transaction_date` datetime NOT NULL,
  `cashier_id` int(11) NOT NULL,
  PRIMARY KEY (`transaction_id`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add transaction_details table
CREATE TABLE IF NOT EXISTS `transaction_details` (
  `detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `transaction_details_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `transaction_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_ID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 


CREATE TABLE audit_logs_products (
    audit_ID INT AUTO_INCREMENT PRIMARY KEY,
    user_ID INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    product_ID INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    field_changed VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_ID) REFERENCES users(user_ID),
    FOREIGN KEY (product_ID) REFERENCES products(product_ID)
);

CREATE TABLE login_audit_logs (
    login_audit_ID INT AUTO_INCREMENT PRIMARY KEY,
    user_ID INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    action ENUM('LOGIN', 'LOGOUT') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_ID) REFERENCES users(user_ID)
);

CREATE TABLE audit_logs_sales (
    audit_ID INT AUTO_INCREMENT PRIMARY KEY,
    user_ID INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    transaction_ID INT, -- links to transactions table
    action ENUM('CREATE', 'UPDATE', 'DELETE') NOT NULL,
    field_changed VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_ID) REFERENCES users(user_ID),
    FOREIGN KEY (transaction_ID) REFERENCES transactions(transaction_id)
);


-- Create suppliers table
CREATE TABLE suppliers (
    supplier_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create supply_orders table
CREATE TABLE supply_orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_id INT,
    product_id INT,
    quantity INT NOT NULL,
    status ENUM('pending', 'preparing', 'out_for_delivery', 'delivered') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_date TIMESTAMP NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (product_id) REFERENCES products(product_ID)
);

-- Create supply_order_status_logs table
CREATE TABLE supply_order_status_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT,
    status ENUM('pending', 'preparing', 'out_for_delivery', 'delivered'),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES supply_orders(order_id)
);

-- Insert sample supplier
INSERT INTO suppliers (name, contact_number, email, address) VALUES
('Main Supplier', '+639563902628', 'ricpogi@gmail.com', 'Mataasnakahoy Batangas'); 

-- Create orders table
CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `status` ENUM('new', 'preparing', 'ready') NOT NULL DEFAULT 'new',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE orders ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
ALTER TABLE orders MODIFY COLUMN status ENUM('new', 'preparing', 'ready', 'completed') NOT NULL DEFAULT 'new';

-- Create order items table
CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `notes` TEXT,
  PRIMARY KEY (`order_item_id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_ID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create order audit logs table
CREATE TABLE IF NOT EXISTS `audit_logs_orders` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `action` ENUM('CREATE', 'UPDATE', 'DELETE') NOT NULL,
  `status_change` varchar(50),
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_orders_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `audit_logs_orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_ID`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 
ALTER TABLE audit_logs_orders ADD COLUMN details TEXT AFTER status_change, ADD COLUMN old_status VARCHAR(50) AFTER details, ADD COLUMN new_status VARCHAR(50) AFTER old_status, ADD COLUMN transaction_id INT AFTER new_status, ADD FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id);


CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_ID INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expiry DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_ID) REFERENCES users(user_ID)
); 



INSERT INTO users (name, username, password, email, role) VALUES
('ric', 'cashier1', 'cafe-cashier-1', 'villanuevaric12@gmail.com', 'cashier'),
('vincent', 'cashier2', 'cafe-cashier-2', 'villanuevaric12@gmail.com', 'cashier'),
('tiquis', 'kitchen1', 'cafe-kitchen-1', 'tiquis.villanueva12@gmail.com', 'kitchen'),
('villanueva', 'kitchen2', 'cafe- kitchen -2', 'tiquis.villanueva12@gmail.com', 'kitchen'),
('mine', 'manager1', 'cafe-manager-1', '22-35042@g.batstate-u.edu.ph', 'manager');

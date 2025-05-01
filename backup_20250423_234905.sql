

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `industry` enum('pharmacy','retail','restaurant','other') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores user accounts';

INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('1', 'pharmacy_admin', '$2y$10$tHRnqBaGSwYIJrdTlcbZWumq81dVLHZMzSVF7PojvhjzyfGxbjmFq', 'pharmacy', '2025-04-23 13:25:32', '2025-04-23 13:26:02');
INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('2', 'retail_admin', '$2y$10$X0z1z2Y3z4W5z6X7z8Y9z0$abcdef1234567890abcdef', 'retail', '2025-04-23 13:25:32', '2025-04-23 13:25:32');
INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('3', 'restaurant_admin', '$2y$10$X0z1z2Y3z4W5z6X7z8Y9z0$abcdef1234567890abcdef', 'restaurant', '2025-04-23 13:25:32', '2025-04-23 13:25:32');
INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('4', 'pharmacy_user1', '$2y$10$X0z1z2Y3z4W5z6X7z8Y9z0$abcdef1234567890abcdef', 'pharmacy', '2025-04-23 13:25:32', '2025-04-23 13:25:32');
INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('5', 'pharmacy_user2', '$2y$10$X0z1z2Y3z4W5z6X7z8Y9z0$abcdef1234567890abcdef', 'pharmacy', '2025-04-23 13:25:32', '2025-04-23 13:25:32');
INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('6', 'hamis', '$2y$10$3l15REqs2Usaqtdo4rcYr.mkS62OyK7V49Z4rc8sMUTAtrMhBvpau', 'pharmacy', '2025-04-23 13:52:56', '2025-04-23 13:52:56');
INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('7', 'admin', '$2y$10$lmrg3rT401n9NRi/GBeGQurZ8VNHAXQFBaTQZLhXtGP40/Ver6CZe', 'pharmacy', '2025-04-23 14:12:53', '2025-04-23 14:12:53');
INSERT INTO users (`id`, `username`, `password`, `industry`, `created_at`, `updated_at`) VALUES ('8', 'baraka', '$2y$10$SMGdn1up32fNAt1oo1u0DuhHup06vxBk.ePPsmMRS.dMDpi7SD/XO', 'pharmacy', '2025-04-23 14:40:11', '2025-04-23 14:40:11');


CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_sku` (`sku`),
  KEY `idx_barcode` (`barcode`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores product inventory details';

INSERT INTO products (`id`, `name`, `sku`, `barcode`, `unit_price`, `stock`, `low_stock_threshold`, `created_at`, `updated_at`) VALUES ('1', 'Paracetamol', 'PARA001', '123456789012', '5.00', '1', '10', '2025-04-23 13:25:33', '2025-04-23 14:45:43');
INSERT INTO products (`id`, `name`, `sku`, `barcode`, `unit_price`, `stock`, `low_stock_threshold`, `created_at`, `updated_at`) VALUES ('2', 'Ibuprofen', 'IBU001', '987654321098', '7.00', '3', '5', '2025-04-23 13:25:33', '2025-04-23 14:46:23');
INSERT INTO products (`id`, `name`, `sku`, `barcode`, `unit_price`, `stock`, `low_stock_threshold`, `created_at`, `updated_at`) VALUES ('3', 'Antasida', 'ANT001', '456789123456', '3.00', '89', '20', '2025-04-23 13:25:33', '2025-04-23 14:47:13');
INSERT INTO products (`id`, `name`, `sku`, `barcode`, `unit_price`, `stock`, `low_stock_threshold`, `created_at`, `updated_at`) VALUES ('4', 'Amoxicillin', 'AMOX001', '789123456789', '10.00', '40', '10', '2025-04-23 13:25:33', '2025-04-23 13:25:33');
INSERT INTO products (`id`, `name`, `sku`, `barcode`, `unit_price`, `stock`, `low_stock_threshold`, `created_at`, `updated_at`) VALUES ('5', 'Cetirizine', 'CET001', '321654987654', '4.00', '60', '15', '2025-04-23 13:25:33', '2025-04-23 13:25:33');


CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','online') NOT NULL,
  `channel` enum('pos','online') DEFAULT 'pos',
  `location_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `prescription_status` varchar(20) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores order details';

INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('1', '1', 'ORD001', '23.00', 'cash', 'pos', '1', '2025-04-20 10:00:00', '2025-04-23 13:25:33', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('2', '4', 'ORD002', '15.20', 'card', 'pos', '1', '2025-04-21 12:00:00', '2025-04-23 13:25:33', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('3', '5', 'ORD003', '18.00', 'online', 'pos', '1', '2025-04-22 14:00:00', '2025-04-23 13:25:33', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('4', '4', 'ORD004', '30.00', 'cash', 'pos', '1', '2025-04-23 09:00:00', '2025-04-23 13:25:33', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('5', '1', 'ORD1745444020', '180.00', '', 'pos', NULL, '2025-04-23 14:33:40', '2025-04-23 14:33:40', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('6', '1', 'ORD1745444052', '144.00', 'cash', 'pos', NULL, '2025-04-23 14:34:12', '2025-04-23 14:34:12', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('7', '1', 'ORD1745444743983', '33.00', 'cash', 'pos', NULL, '2025-04-23 14:45:43', '2025-04-23 14:45:43', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('8', '1', 'ORD1745444783505', '77.00', '', 'pos', NULL, '2025-04-23 14:46:23', '2025-04-23 14:46:23', 'pending');
INSERT INTO orders (`id`, `user_id`, `order_number`, `total_amount`, `payment_method`, `channel`, `location_id`, `created_at`, `updated_at`, `prescription_status`) VALUES ('9', '1', 'ORD1745444833302', '33.00', 'cash', 'pos', NULL, '2025-04-23 14:47:13', '2025-04-23 14:47:13', 'pending');


CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores items within each order';

INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('1', '1', '1', '2', '5.00', '2025-04-20 10:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('2', '1', '2', '1', '7.00', '2025-04-20 10:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('3', '2', '1', '1', '5.00', '2025-04-21 12:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('4', '2', '3', '2', '3.00', '2025-04-21 12:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('5', '3', '3', '2', '3.00', '2025-04-22 14:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('6', '3', '4', '1', '10.00', '2025-04-22 14:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('7', '4', '2', '2', '7.00', '2025-04-23 09:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('8', '4', '5', '2', '4.00', '2025-04-23 09:00:00');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('9', '5', '1', '36', '5.00', '2025-04-23 14:33:40');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('10', '6', '1', '12', '5.00', '2025-04-23 14:34:12');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('11', '6', '2', '12', '7.00', '2025-04-23 14:34:12');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('12', '7', '1', '1', '5.00', '2025-04-23 14:45:43');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('13', '7', '2', '4', '7.00', '2025-04-23 14:45:43');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('14', '8', '2', '11', '7.00', '2025-04-23 14:46:23');
INSERT INTO order_items (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES ('15', '9', '3', '11', '3.00', '2025-04-23 14:47:13');


CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `prescriber_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `medication_name` varchar(255) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `prescriber_id` (`prescriber_id`),
  KEY `patient_id` (`patient_id`),
  KEY `idx_prescription_status` (`status`),
  KEY `idx_prescription_order_id` (`order_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`prescriber_id`) REFERENCES `users` (`id`),
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


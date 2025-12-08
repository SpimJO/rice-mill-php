-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2025 at 04:10 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rice_mill_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `blockchain_log`
--

CREATE TABLE `blockchain_log` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_user` varchar(50) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `timestamp` datetime DEFAULT current_timestamp(),
  `previous_hash` varchar(255) DEFAULT NULL,
  `current_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blockchain_log`
--

INSERT INTO `blockchain_log` (`id`, `user_id`, `action`, `target_user`, `data`, `timestamp`, `previous_hash`, `current_hash`) VALUES
(27, '2', 'ADD_USER', '1', '{\"name\":\"1\",\"role\":\"Admin\"}', '2025-11-06 16:04:35', '', 'c97b0826572893a33d3381d32802ef6de3577758ca07d6e876977879af5b6a15'),
(28, '1', 'LOGIN_SUCCESS', '1', '{\"role\":\"Admin\",\"name\":\"1\"}', '2025-11-06 16:04:39', 'c97b0826572893a33d3381d32802ef6de3577758ca07d6e876977879af5b6a15', 'bfc9d2becbb2f6761cc13570054331fd5cdb897d42dd60b7bcb1d2c71dc09821'),
(29, '1', 'Add Purchase', 'sample', '{\"supplier\":\"sample\",\"quantity\":100,\"price\":31,\"total_amount\":3100,\"date\":\"2025-11-06\"}', '2025-11-06 16:04:51', 'bfc9d2becbb2f6761cc13570054331fd5cdb897d42dd60b7bcb1d2c71dc09821', '46792879f27c09d761b5e34eadab0485d958dcb9bad7436a401a9914a1e01302'),
(30, '1', 'Mark as Paid', 'Purchase ID: 61', '{\"purchase_id\":61,\"supplier\":\"sample\",\"quantity\":100,\"price\":\"31.00\",\"status\":\"Paid\"}', '2025-11-06 16:04:59', '46792879f27c09d761b5e34eadab0485d958dcb9bad7436a401a9914a1e01302', 'ef445d41c1d31fff37a67376aa9e7b5226e151ebfabaee80d652fb74f61be83a'),
(31, '1', 'Add Purchase', 'sample 2', '{\"supplier\":\"sample 2\",\"quantity\":2,\"price\":1,\"total_amount\":2,\"date\":\"2025-11-06\"}', '2025-11-06 16:06:24', 'ef445d41c1d31fff37a67376aa9e7b5226e151ebfabaee80d652fb74f61be83a', '4376ee8832ed488003c731adc4145cda4d95128f281d61e3e7a46e4018736d71'),
(32, '1', 'Mark as Paid', 'Purchase ID: 62', '{\"purchase_id\":62,\"supplier\":\"sample 2\",\"quantity\":2,\"price\":\"1.00\",\"status\":\"Paid\"}', '2025-11-06 16:06:28', '4376ee8832ed488003c731adc4145cda4d95128f281d61e3e7a46e4018736d71', '02ac1fbbf9bff5105f8d6883998e1d43490ae451952626e84d9d06c3b15d1c2b'),
(33, '1', 'ADD_USER', '2', '{\"name\":\"2\",\"role\":\"Operator\"}', '2025-11-06 16:06:47', '02ac1fbbf9bff5105f8d6883998e1d43490ae451952626e84d9d06c3b15d1c2b', '48b500b5378214f97fb7404aaf1184135af340d0fb95f3ee1bc4dc6ad9c96dc0'),
(34, '2', 'LOGIN_SUCCESS', '2', '{\"role\":\"Operator\",\"name\":\"2\"}', '2025-11-06 16:06:51', '48b500b5378214f97fb7404aaf1184135af340d0fb95f3ee1bc4dc6ad9c96dc0', '324b9e662cbd4dfcbbc6cbe498e51f4f21a8753446f1bd99187b5d590ee77c70'),
(35, '1', 'LOGIN_SUCCESS', '1', '{\"role\":\"Admin\",\"name\":\"1\"}', '2025-11-06 16:07:18', '324b9e662cbd4dfcbbc6cbe498e51f4f21a8753446f1bd99187b5d590ee77c70', 'f704dcf70d4d2213421a4bcbc2a72014e8f11a2726ff8336f0ff1d9de198880d'),
(36, '1', 'Add Rice Type', '1', '{\"rice_type\":\"Jasmine\"}', '2025-11-06 16:07:27', 'f704dcf70d4d2213421a4bcbc2a72014e8f11a2726ff8336f0ff1d9de198880d', 'f2e62f5f5500c4942b106d0663845970793b477418773a20fb674471b7d1573e'),
(37, '1', 'Add Milling', '2', '{\"rice_type\":\"Jasmine\",\"quantity\":55,\"output\":50}', '2025-11-06 16:07:50', 'f2e62f5f5500c4942b106d0663845970793b477418773a20fb674471b7d1573e', 'a9c0379466d2251fe264cb0acea22b970f679546b014d8d0092684df91c92d07'),
(38, '1', 'Edit Milling', '2', '{\"milling_id\":18,\"new_quantity\":30,\"new_output\":2}', '2025-11-06 16:08:31', 'a9c0379466d2251fe264cb0acea22b970f679546b014d8d0092684df91c92d07', '811cfb24f632a1e75a5c67b9ba72ec521c8f0efdd00b8c3b1cdbb8d6309a4cf5');

--
-- Triggers `blockchain_log`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_blockchain_log` BEFORE DELETE ON `blockchain_log` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on blockchain_log blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_blockchain_log` BEFORE INSERT ON `blockchain_log` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on blockchain_log blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_blockchain_log` BEFORE UPDATE ON `blockchain_log` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on blockchain_log blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `rice_type` varchar(255) NOT NULL,
  `total_25kg` int(11) DEFAULT 0,
  `price_25kg` decimal(10,2) DEFAULT 0.00,
  `total_50kg` int(11) DEFAULT 0,
  `price_50kg` decimal(10,2) DEFAULT 0.00,
  `total_5kg` int(11) DEFAULT 0,
  `price_5kg` decimal(10,2) DEFAULT 0.00,
  `total_kg` decimal(10,2) GENERATED ALWAYS AS (`total_25kg` * 25 + `total_50kg` * 50 + `total_5kg` * 5) STORED,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventory`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_inventory` BEFORE DELETE ON `inventory` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on inventory blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_inventory` BEFORE INSERT ON `inventory` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on inventory blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_inventory` BEFORE UPDATE ON `inventory` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on inventory blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_adjustments_log`
--

CREATE TABLE `inventory_adjustments_log` (
  `id` int(11) NOT NULL,
  `rice_type` varchar(255) NOT NULL,
  `adjusted_25kg` int(11) DEFAULT 0,
  `adjusted_50kg` int(11) DEFAULT 0,
  `adjusted_5kg` int(11) DEFAULT 0,
  `price_25kg` decimal(10,2) DEFAULT 0.00,
  `price_50kg` decimal(10,2) DEFAULT 0.00,
  `price_5kg` decimal(10,2) DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `operator_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventory_adjustments_log`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_inventory_adjustments_log` BEFORE DELETE ON `inventory_adjustments_log` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on inventory_adjustments_log blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_inventory_adjustments_log` BEFORE INSERT ON `inventory_adjustments_log` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on inventory_adjustments_log blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_inventory_adjustments_log` BEFORE UPDATE ON `inventory_adjustments_log` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on inventory_adjustments_log blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_adjustments_after_insert` AFTER INSERT ON `inventory_adjustments_log` FOR EACH ROW BEGIN
    INSERT INTO inventory_history
    (rice_type, total_25kg, total_50kg, total_5kg, total_kg, snapshot_date, reference_type, reference_id)
    VALUES
    (NEW.rice_type, NEW.adjusted_25kg, NEW.adjusted_50kg, NEW.adjusted_5kg, NEW.adjusted_25kg*25 + NEW.adjusted_50kg*50 + NEW.adjusted_5kg*5, CURDATE(), 'adjustment', NEW.id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_history`
--

CREATE TABLE `inventory_history` (
  `id` int(11) NOT NULL,
  `rice_type` varchar(100) NOT NULL,
  `total_25kg` int(11) NOT NULL DEFAULT 0,
  `total_50kg` int(11) NOT NULL DEFAULT 0,
  `total_5kg` int(11) NOT NULL DEFAULT 0,
  `total_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `snapshot_date` date NOT NULL,
  `reference_type` enum('initial','sale','adjustment','snapshot') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `inventory_history`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_inventory_history` BEFORE DELETE ON `inventory_history` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on inventory_history blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_inventory_history` BEFORE INSERT ON `inventory_history` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on inventory_history blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_inventory_history` BEFORE UPDATE ON `inventory_history` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on inventory_history blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `milling`
--

CREATE TABLE `milling` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `milled_output` decimal(10,2) NOT NULL,
  `operator_user_id` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rice_name` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milling`
--

INSERT INTO `milling` (`id`, `date`, `quantity`, `milled_output`, `operator_user_id`, `created_at`, `rice_name`, `status`, `remarks`) VALUES
(18, '2025-11-06', 30.00, 2.00, '2', '2025-11-06 15:07:50', 'Jasmine', 'Pending', NULL);

--
-- Triggers `milling`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_milling` BEFORE DELETE ON `milling` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on milling blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_milling` BEFORE INSERT ON `milling` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on milling blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_milling` BEFORE UPDATE ON `milling` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on milling blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `palay_milling_process`
--

CREATE TABLE `palay_milling_process` (
  `id` int(11) NOT NULL,
  `rice_type` varchar(100) DEFAULT NULL,
  `palay_quantity` decimal(10,2) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `added_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `palay_milling_process`
--

INSERT INTO `palay_milling_process` (`id`, `rice_type`, `palay_quantity`, `added_by`, `added_date`) VALUES
(10, NULL, 72.00, 1, '2025-11-06 23:04:59');

--
-- Triggers `palay_milling_process`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_palay_milling_process` BEFORE DELETE ON `palay_milling_process` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on palay_milling_process blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_palay_milling_process` BEFORE INSERT ON `palay_milling_process` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on palay_milling_process blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_palay_milling_process` BEFORE UPDATE ON `palay_milling_process` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on palay_milling_process blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `palay_purchases`
--

CREATE TABLE `palay_purchases` (
  `id` int(11) NOT NULL,
  `supplier` varchar(100) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `payment_status` enum('Pending','Paid','Void') NOT NULL,
  `pv_printed` tinyint(1) NOT NULL DEFAULT 0,
  `pv_number` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `palay_purchases`
--

INSERT INTO `palay_purchases` (`id`, `supplier`, `user_id`, `quantity`, `total_amount`, `purchase_date`, `price`, `payment_status`, `pv_printed`, `pv_number`) VALUES
(61, 'sample', '1', 100.00, 3100.00, '2025-11-06', 31.00, 'Paid', 1, 'PV-20251106-0061'),
(62, 'sample 2', '1', 2.00, 2.00, '2025-11-06', 1.00, 'Paid', 1, 'PV-20251106-0062');

--
-- Triggers `palay_purchases`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_palay_purchases` BEFORE DELETE ON `palay_purchases` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on palay_purchases blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_palay_purchases` BEFORE INSERT ON `palay_purchases` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on palay_purchases blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_palay_purchases` BEFORE UPDATE ON `palay_purchases` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on palay_purchases blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `rice_type` varchar(100) NOT NULL,
  `sack_25kg` int(11) DEFAULT 0,
  `sack_50kg` int(11) DEFAULT 0,
  `sack_5kg` int(11) DEFAULT 0,
  `total_kg` decimal(10,2) NOT NULL,
  `operator_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `products`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_products` BEFORE DELETE ON `products` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on products blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_products` BEFORE INSERT ON `products` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on products blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_products` BEFORE UPDATE ON `products` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on products blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_products_after_insert` AFTER INSERT ON `products` FOR EACH ROW BEGIN
    INSERT INTO inventory_history 
    (rice_type, total_25kg, total_50kg, total_5kg, total_kg, snapshot_date, reference_type, reference_id)
    VALUES 
    (NEW.rice_type, NEW.sack_25kg, NEW.sack_50kg, NEW.sack_5kg, NEW.total_kg, CURDATE(), 'initial', NEW.id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `rice_types`
--

CREATE TABLE `rice_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `total_quantity_kg` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rice_types`
--

INSERT INTO `rice_types` (`id`, `type_name`, `total_quantity_kg`) VALUES
(20, 'Jasmine', 0.00);

--
-- Triggers `rice_types`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_rice_types` BEFORE DELETE ON `rice_types` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on rice_types blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_rice_types` BEFORE INSERT ON `rice_types` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on rice_types blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_rice_types` BEFORE UPDATE ON `rice_types` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on rice_types blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `rice_type` varchar(255) NOT NULL,
  `sack_25kg` int(11) DEFAULT 0,
  `sack_50kg` int(11) DEFAULT 0,
  `sack_5kg` int(11) DEFAULT 0,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `operator_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `sales`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_sales` BEFORE DELETE ON `sales` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on sales blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_sales` BEFORE INSERT ON `sales` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on sales blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_sales` BEFORE UPDATE ON `sales` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on sales blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sales_after_insert` AFTER INSERT ON `sales` FOR EACH ROW BEGIN
    INSERT INTO inventory_history 
        (rice_type, total_25kg, total_50kg, total_5kg, total_kg, snapshot_date, reference_type, reference_id)
    VALUES
        (NEW.rice_type,
         -NEW.sack_25kg,
         -NEW.sack_50kg,
         -NEW.sack_5kg,
         -(NEW.sack_25kg*25 + NEW.sack_50kg*50 + NEW.sack_5kg*5),
         NOW(),
         'sale',
         NEW.id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date_added` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `name`, `role`, `password`, `date_added`) VALUES
(0, '1', '1', 'Admin', '$2y$10$yub5zHiowh08Kd92x3x7Z.PGe4L3D0nNxSAjftf0O0.aX3zLu8DbW', '2025-11-06'),
(0, '2', '2', 'Operator', '$2y$10$/ZheY7AJ76i8ojn75bZmYeOMaTuXrghRu8sIQWm9ptURuua4e/GZG', '2025-11-06');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_users` BEFORE DELETE ON `users` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on users blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_users` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on users blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_users` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on users blocked';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `void_logs`
--

CREATE TABLE `void_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `action_type` enum('ITEM','CART') NOT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `size` varchar(20) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `void_logs`
--
DELIMITER $$
CREATE TRIGGER `secure_delete_void_logs` BEFORE DELETE ON `void_logs` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized DELETE on void_logs blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_insert_void_logs` BEFORE INSERT ON `void_logs` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized INSERT on void_logs blocked';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `secure_update_void_logs` BEFORE UPDATE ON `void_logs` FOR EACH ROW BEGIN
  IF (@website_authorized IS NULL OR @website_authorized != 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unauthorized UPDATE on void_logs blocked';
  END IF;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blockchain_log`
--
ALTER TABLE `blockchain_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`rice_type`);

--
-- Indexes for table `inventory_adjustments_log`
--
ALTER TABLE `inventory_adjustments_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_history`
--
ALTER TABLE `inventory_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `milling`
--
ALTER TABLE `milling`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_operator_user_id` (`operator_user_id`);

--
-- Indexes for table `palay_milling_process`
--
ALTER TABLE `palay_milling_process`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `palay_purchases`
--
ALTER TABLE `palay_purchases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rice_types`
--
ALTER TABLE `rice_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `void_logs`
--
ALTER TABLE `void_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blockchain_log`
--
ALTER TABLE `blockchain_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `inventory_adjustments_log`
--
ALTER TABLE `inventory_adjustments_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `inventory_history`
--
ALTER TABLE `inventory_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `milling`
--
ALTER TABLE `milling`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `palay_milling_process`
--
ALTER TABLE `palay_milling_process`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `palay_purchases`
--
ALTER TABLE `palay_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `rice_types`
--
ALTER TABLE `rice_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `void_logs`
--
ALTER TABLE `void_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

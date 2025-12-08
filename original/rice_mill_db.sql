-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 20, 2025 at 08:25 AM
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
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_id` varchar(20) NOT NULL,
  `rice_type` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `stock_type` varchar(10) NOT NULL DEFAULT 'In'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `rice_type`, `date`, `quantity`, `stock_type`) VALUES
(1, '', 'Jasponica', '2025-09-25', 3, 'In'),
(2, '', 'Jasponica', '2025-09-12', 7, 'In'),
(3, '', 'Jasponica', '0000-00-00', 1, 'In'),
(4, '', 'Sinandomeng', '0000-00-00', 22, 'In'),
(5, '', 'Sinandomeng', '0000-00-00', 1, 'In'),
(6, '', 'Jasponica', '0000-00-00', 22222, 'In'),
(7, '123', 'Jasponica', '2025-09-20', 22, 'In');

-- --------------------------------------------------------

--
-- Table structure for table `milling`
--

CREATE TABLE `milling` (
  `id` int(11) NOT NULL,
  `palay_type` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `milled_output` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `palay_purchases`
--

CREATE TABLE `palay_purchases` (
  `id` int(11) NOT NULL,
  `supplier` varchar(100) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `purchase_date` date NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `payment_status` enum('Pending','Paid') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `palay_purchases`
--

INSERT INTO `palay_purchases` (`id`, `supplier`, `quantity`, `total_amount`, `purchase_date`, `price`, `payment_status`) VALUES
(7, 'caster', 7.00, 0.98, '2025-09-20', 0.14, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` varchar(20) NOT NULL,
  `description` varchar(100) NOT NULL,
  `price_25kg` int(11) NOT NULL,
  `price_retail` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `description`, `price_25kg`, `price_retail`) VALUES
('123', '123123', 123123, 123123),
('123123123123', '2213', 3123, 22),
('2222', '1', 1, 1),
('asd', 'asd', 222, 222),
('sample', 'sample', 1500, 25),
('wewe', 'wewe', 111, 111);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `date_added` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `name`, `role`, `date_added`) VALUES
(7, 'sample', 'caster', 'Admin', '2000-03-19'),
(8, 'USID-0003', 'Renz Luis Liwanag', 'Cashier', '2025-09-16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `milling`
--
ALTER TABLE `milling`
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
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `milling`
--
ALTER TABLE `milling`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `palay_purchases`
--
ALTER TABLE `palay_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

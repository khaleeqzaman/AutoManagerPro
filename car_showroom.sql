-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 02:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `car_showroom`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_type` enum('cash','bank','mobile_wallet') DEFAULT 'cash',
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'PKR',
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cars`
--

CREATE TABLE `cars` (
  `id` int(11) NOT NULL,
  `chassis_no` varchar(100) NOT NULL,
  `engine_no` varchar(100) DEFAULT NULL,
  `registration_no` varchar(50) DEFAULT NULL,
  `make` varchar(50) NOT NULL,
  `model` varchar(50) NOT NULL,
  `variant` varchar(50) DEFAULT NULL,
  `year` year(4) NOT NULL,
  `registration_year` year(4) DEFAULT NULL,
  `city_registered` varchar(50) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `assembly` enum('local','imported') DEFAULT 'local',
  `body_type` enum('sedan','suv','hatchback','pickup','van','crossover','other') DEFAULT NULL,
  `mileage` int(11) DEFAULT 0,
  `fuel_type` enum('petrol','diesel','hybrid','electric','cng') DEFAULT 'petrol',
  `transmission` enum('manual','automatic') DEFAULT 'manual',
  `engine_capacity` int(11) DEFAULT NULL,
  `ownership` enum('1st','2nd','3rd','4th+') DEFAULT '1st',
  `condition_rating` enum('excellent','good','average','below_average') DEFAULT 'good',
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `min_price` decimal(12,2) DEFAULT 0.00,
  `min_sale_price` decimal(12,2) DEFAULT NULL,
  `is_negotiable` tinyint(1) DEFAULT 1,
  `supplier_name` varchar(100) DEFAULT NULL,
  `buying_source` enum('individual','auction','trade_in','dealer') DEFAULT 'individual',
  `status` enum('available','reserved','sold') DEFAULT 'available',
  `has_original_book` tinyint(1) DEFAULT 0,
  `has_original_file` tinyint(1) DEFAULT 0,
  `has_smart_card` tinyint(1) DEFAULT 0,
  `token_paid` tinyint(1) DEFAULT 0,
  `tracker_installed` tinyint(1) DEFAULT 0,
  `abs` tinyint(1) DEFAULT 0,
  `airbags` tinyint(1) DEFAULT 0,
  `sunroof` tinyint(1) DEFAULT 0,
  `alloy_rims` tinyint(1) DEFAULT 0,
  `navigation` tinyint(1) DEFAULT 0,
  `climate_control` tinyint(1) DEFAULT 0,
  `keyless_entry` tinyint(1) DEFAULT 0,
  `push_start` tinyint(1) DEFAULT 0,
  `cruise_control` tinyint(1) DEFAULT 0,
  `parking_sensors` tinyint(1) DEFAULT 0,
  `reverse_camera` tinyint(1) DEFAULT 0,
  `slug` varchar(200) DEFAULT NULL,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `car_costs`
--

CREATE TABLE `car_costs` (
  `id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `cost_type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `cost_date` date DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `car_custom_values`
--

CREATE TABLE `car_custom_values` (
  `id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `car_images`
--

CREATE TABLE `car_images` (
  `id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `car_price_history`
--

CREATE TABLE `car_price_history` (
  `id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `old_price` decimal(12,2) DEFAULT NULL,
  `new_price` decimal(12,2) DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `reason` varchar(100) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `car_timeline`
--

CREATE TABLE `car_timeline` (
  `id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `event_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `done_by` int(11) DEFAULT NULL,
  `event_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `car_timeline`
--

INSERT INTO `car_timeline` (`id`, `car_id`, `event_type`, `description`, `done_by`, `event_date`) VALUES
(1, 1, 'added', 'Car added: 2011 Toyota Corolla at PKR 1,600,000', 1, '2026-03-01 05:57:23'),
(2, 1, 'updated', 'Car updated: 2011 Toyota Corolla', 1, '2026-03-01 06:01:27'),
(3, 1, 'sold', 'Car sold to Khaleeq Zaman for PKR 1,600,000 | Invoice: INV-2026-0001', 1, '2026-03-01 06:37:34'),
(4, 2, 'added', 'Car added: 2020 BMW M2 at PKR 15,000,000', 1, '2026-03-02 11:45:08'),
(5, 2, 'sold', 'Car sold to Mubeen for PKR 14,800,000 | Invoice: INV-2026-0002', 1, '2026-03-02 12:00:43');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `cnic` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `full_name`, `cnic`, `phone`, `whatsapp`, `email`, `city`, `address`, `created_at`) VALUES
(1, 'Khaleeq Zaman', '41201-6520034-9', '03332151519', NULL, 'web.engineer@hotmail.com', 'Hyderabad', NULL, '2026-03-01 06:37:34');

-- --------------------------------------------------------

--
-- Table structure for table `custom_fields`
--

CREATE TABLE `custom_fields` (
  `id` int(11) NOT NULL,
  `field_label` varchar(100) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_type` enum('text','number','dropdown','checkbox') DEFAULT 'text',
  `placeholder` varchar(255) DEFAULT NULL,
  `field_options` text DEFAULT NULL,
  `dropdown_options` text DEFAULT NULL,
  `applies_to` varchar(50) DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `show_in_list` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `paid_to` varchar(100) DEFAULT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `car_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `source` enum('website','walk_in','phone','whatsapp','referral') DEFAULT 'website',
  `status` enum('new','contacted','interested','negotiating','closed_won','closed_lost') DEFAULT 'new',
  `assigned_to` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`) VALUES
(1, 'Admin', '2026-02-28 19:17:54'),
(2, 'Manager', '2026-02-28 19:17:54'),
(3, 'Salesperson', '2026-02-28 19:17:54'),
(4, 'Accountant', '2026-02-28 19:17:54');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `granted` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission`, `granted`) VALUES
(1, 1, 'inventory.view', 1),
(2, 1, 'inventory.add', 1),
(3, 1, 'inventory.edit', 1),
(4, 1, 'inventory.delete', 1),
(5, 1, 'leads.view', 1),
(6, 1, 'leads.add', 1),
(7, 1, 'leads.edit', 1),
(8, 1, 'leads.delete', 1),
(9, 1, 'sales.view', 1),
(10, 1, 'sales.create', 1),
(11, 1, 'accounts.view', 1),
(12, 1, 'accounts.manage', 1),
(13, 1, 'expenses.view', 1),
(14, 1, 'expenses.add', 1),
(15, 1, 'expenses.delete', 1),
(16, 1, 'reports.view', 1),
(17, 1, 'users.manage', 1),
(18, 1, 'settings.manage', 1),
(19, 2, 'inventory.view', 1),
(20, 2, 'inventory.add', 1),
(21, 2, 'inventory.edit', 1),
(22, 2, 'inventory.delete', 1),
(23, 2, 'leads.view', 1),
(24, 2, 'leads.add', 1),
(25, 2, 'leads.edit', 1),
(26, 2, 'leads.delete', 1),
(27, 2, 'sales.view', 1),
(28, 2, 'sales.create', 1),
(29, 2, 'accounts.view', 1),
(30, 2, 'accounts.manage', 1),
(31, 2, 'expenses.view', 1),
(32, 2, 'expenses.add', 1),
(33, 2, 'expenses.delete', 1),
(34, 2, 'reports.view', 1),
(35, 2, 'users.manage', 0),
(36, 2, 'settings.manage', 0),
(37, 3, 'inventory.view', 1),
(38, 3, 'leads.view', 1),
(39, 3, 'leads.add', 1),
(40, 3, 'leads.edit', 1),
(41, 3, 'sales.view', 1),
(42, 3, 'sales.create', 1),
(43, 3, 'inventory.add', 0),
(44, 3, 'inventory.edit', 0),
(45, 3, 'inventory.delete', 0),
(46, 3, 'leads.delete', 0),
(47, 3, 'accounts.view', 0),
(48, 3, 'accounts.manage', 0),
(49, 3, 'expenses.view', 0),
(50, 3, 'expenses.add', 0),
(51, 3, 'expenses.delete', 0),
(52, 3, 'reports.view', 0),
(53, 3, 'users.manage', 0),
(54, 3, 'settings.manage', 0),
(55, 4, 'sales.view', 1),
(56, 4, 'accounts.view', 1),
(57, 4, 'accounts.manage', 1),
(58, 4, 'expenses.view', 1),
(59, 4, 'expenses.add', 1),
(60, 4, 'reports.view', 1),
(61, 4, 'inventory.view', 0),
(62, 4, 'inventory.add', 0),
(63, 4, 'inventory.edit', 0),
(64, 4, 'inventory.delete', 0),
(65, 4, 'leads.view', 0),
(66, 4, 'leads.add', 0),
(67, 4, 'leads.edit', 0),
(68, 4, 'leads.delete', 0),
(69, 4, 'sales.create', 0),
(70, 4, 'expenses.delete', 0),
(71, 4, 'users.manage', 0),
(72, 4, 'settings.manage', 0);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `car_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `buyer_name` varchar(100) DEFAULT NULL,
  `buyer_phone` varchar(20) DEFAULT NULL,
  `salesperson_id` int(11) DEFAULT NULL,
  `sold_by` int(11) DEFAULT NULL,
  `sale_price` decimal(12,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(12,2) NOT NULL,
  `payment_type` enum('cash','bank_transfer','cheque','installment') DEFAULT 'cash',
  `payment_method` varchar(50) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `cheque_no` varchar(50) DEFAULT NULL,
  `token_amount` decimal(10,2) DEFAULT 0.00,
  `token_date` date DEFAULT NULL,
  `remaining_amount` decimal(12,2) DEFAULT 0.00,
  `commission_type` enum('percentage','fixed') DEFAULT NULL,
  `commission_value` decimal(10,2) DEFAULT NULL,
  `commission_amount` decimal(10,2) DEFAULT 0.00,
  `commission_paid` tinyint(1) DEFAULT 0,
  `commission_paid_date` date DEFAULT NULL,
  `purchase_price` decimal(12,2) DEFAULT NULL,
  `total_extra_costs` decimal(12,2) DEFAULT 0.00,
  `net_profit` decimal(12,2) DEFAULT NULL,
  `profit` decimal(12,2) DEFAULT NULL,
  `transfer_fee` decimal(10,2) DEFAULT 0.00,
  `withholding_tax` decimal(10,2) DEFAULT 0.00,
  `sale_date` date NOT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_label` varchar(150) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_label`, `updated_at`) VALUES
(1, 'showroom_name', 'AutoManager Pro', 'Showroom Name', '2026-03-01 06:13:10'),
(2, 'whatsapp_no', '923352151519', 'WhatsApp Number (with country code, no +)', '2026-03-01 06:13:10'),
(3, 'phone_no', '03352151519', 'Display Phone Number', '2026-03-01 06:13:10'),
(4, 'showroom_address', '', 'Showroom Address', '2026-03-01 06:13:10'),
(5, 'showroom_city', 'Hyderabad', 'Showroom City', '2026-03-01 06:13:10'),
(6, 'showroom_email', 'info@infinitymotors.pk', 'Email Address', '2026-03-01 06:13:10'),
(7, 'currency', 'PKR', 'Currency', '2026-03-01 06:13:10'),
(8, 'logo_path', '', 'Logo Image Path', '2026-03-01 06:13:10');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('credit','debit') NOT NULL,
  `category` enum('sale_income','expense','commission_payment','deposit','withdrawal','transfer','other') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) DEFAULT NULL,
  `reference_type` enum('sale','expense','commission','manual') DEFAULT 'manual',
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `added_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `commission_type` enum('percentage','fixed') DEFAULT 'percentage',
  `commission_value` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `email`, `phone`, `password`, `commission_type`, `commission_value`, `status`, `last_login`, `created_at`) VALUES
(1, 1, 'Admin User', 'admin@showroom.com', '03352151519', '$2y$10$qmus9hV6LDxamlB9gNWfz.eKzQf71S41.mMxBEHpr09PEEAc5zIZK', 'percentage', 0.00, 'active', '2026-03-05 13:22:22', '2026-02-28 19:17:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cars`
--
ALTER TABLE `cars`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chassis_no` (`chassis_no`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_make` (`make`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_chassis` (`chassis_no`);

--
-- Indexes for table `car_costs`
--
ALTER TABLE `car_costs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `car_custom_values`
--
ALTER TABLE `car_custom_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `car_images`
--
ALTER TABLE `car_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `car_id` (`car_id`);

--
-- Indexes for table `car_price_history`
--
ALTER TABLE `car_price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `car_timeline`
--
ALTER TABLE `car_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `done_by` (`done_by`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_fields`
--
ALTER TABLE `custom_fields`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_perm` (`role_id`,`permission`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `car_id` (`car_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `salesperson_id` (`salesperson_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cars`
--
ALTER TABLE `cars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `car_costs`
--
ALTER TABLE `car_costs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `car_custom_values`
--
ALTER TABLE `car_custom_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `car_images`
--
ALTER TABLE `car_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `car_price_history`
--
ALTER TABLE `car_price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `car_timeline`
--
ALTER TABLE `car_timeline`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `custom_fields`
--
ALTER TABLE `custom_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cars`
--
ALTER TABLE `cars`
  ADD CONSTRAINT `cars_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `car_costs`
--
ALTER TABLE `car_costs`
  ADD CONSTRAINT `car_costs_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `car_costs_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `car_custom_values`
--
ALTER TABLE `car_custom_values`
  ADD CONSTRAINT `car_custom_values_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `car_custom_values_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `car_images`
--
ALTER TABLE `car_images`
  ADD CONSTRAINT `car_images_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `car_price_history`
--
ALTER TABLE `car_price_history`
  ADD CONSTRAINT `car_price_history_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `car_price_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `car_timeline`
--
ALTER TABLE `car_timeline`
  ADD CONSTRAINT `car_timeline_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `car_timeline_ibfk_2` FOREIGN KEY (`done_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leads_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leads_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`salesperson_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

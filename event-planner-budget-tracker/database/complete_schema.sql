-- Event Planner Budget Tracker Database Schema
-- Generated: April 16, 2026

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ========================================
-- Database: `event-planner-budget-tracker`
-- ========================================

-- ========================================
-- Table: `users`
-- ========================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `events`
-- ========================================
CREATE TABLE IF NOT EXISTS `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `event_date` date NOT NULL,
  `end_date` date,
  `location` varchar(255) NOT NULL,
  `description` text,
  `status` enum('planned', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `budgets`
-- ========================================
CREATE TABLE IF NOT EXISTS `budgets` (
  `budget_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `total_budget` decimal(15,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`budget_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `budget_categories`
-- ========================================
CREATE TABLE IF NOT EXISTS `budget_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL,
  `spent_amount` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  FOREIGN KEY (`budget_id`) REFERENCES `budgets`(`budget_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `expenses`
-- ========================================
CREATE TABLE IF NOT EXISTS `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `category_id` int(11),
  `vendor_id` int(11),
  `title` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash', 'credit_card', 'debit_card', 'bank_transfer', 'check') NOT NULL,
  `expense_date` date NOT NULL,
  `description` text,
  `status` enum('pending', 'paid', 'partially_paid') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`expense_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `budget_categories`(`category_id`) ON DELETE SET NULL,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`vendor_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `vendors`
-- ========================================
CREATE TABLE IF NOT EXISTS `vendors` (
  `vendor_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `vendor_name` varchar(100) NOT NULL,
  `vendor_type` varchar(50),
  `contact_person` varchar(100),
  `email` varchar(100),
  `phone` varchar(20),
  `address` varchar(255),
  `city` varchar(50),
  `state` varchar(50),
  `postal_code` varchar(10),
  `website` varchar(255),
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`vendor_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `tasks`
-- ========================================
CREATE TABLE IF NOT EXISTS `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `task_name` varchar(150) NOT NULL,
  `description` text,
  `due_date` date NOT NULL,
  `priority` enum('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  `status` enum('not_started', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'not_started',
  `assignee` varchar(100),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `calendar_events`
-- ========================================
CREATE TABLE IF NOT EXISTS `calendar_events` (
  `calendar_event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `event_datetime` datetime NOT NULL,
  `end_datetime` datetime,
  `event_type` varchar(50),
  `location` varchar(255),
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`calendar_event_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table: `cost_tracking`
-- ========================================
CREATE TABLE IF NOT EXISTS `cost_tracking` (
  `tracking_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `total_budget` decimal(15,2) NOT NULL,
  `total_spent` decimal(15,2) DEFAULT 0.00,
  `remaining_budget` decimal(15,2) DEFAULT 0.00,
  `percentage_spent` decimal(5,2) DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tracking_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================
-- Table Indexes
-- ========================================

-- Indexes for `users`
ALTER TABLE `users` ADD INDEX `idx_email` (`email`);

-- Indexes for `events`
ALTER TABLE `events` ADD INDEX `idx_user_id` (`user_id`);
ALTER TABLE `events` ADD INDEX `idx_event_date` (`event_date`);
ALTER TABLE `events` ADD INDEX `idx_status` (`status`);

-- Indexes for `budgets`
ALTER TABLE `budgets` ADD INDEX `idx_event_id` (`event_id`);

-- Indexes for `budget_categories`
ALTER TABLE `budget_categories` ADD INDEX `idx_budget_id` (`budget_id`);

-- Indexes for `expenses`
ALTER TABLE `expenses` ADD INDEX `idx_event_id` (`event_id`);
ALTER TABLE `expenses` ADD INDEX `idx_category_id` (`category_id`);
ALTER TABLE `expenses` ADD INDEX `idx_vendor_id` (`vendor_id`);
ALTER TABLE `expenses` ADD INDEX `idx_expense_date` (`expense_date`);
ALTER TABLE `expenses` ADD INDEX `idx_status` (`status`);

-- Indexes for `vendors`
ALTER TABLE `vendors` ADD INDEX `idx_event_id` (`event_id`);
ALTER TABLE `vendors` ADD INDEX `idx_vendor_type` (`vendor_type`);

-- Indexes for `tasks`
ALTER TABLE `tasks` ADD INDEX `idx_event_id` (`event_id`);
ALTER TABLE `tasks` ADD INDEX `idx_due_date` (`due_date`);
ALTER TABLE `tasks` ADD INDEX `idx_priority` (`priority`);
ALTER TABLE `tasks` ADD INDEX `idx_status` (`status`);

-- Indexes for `calendar_events`
ALTER TABLE `calendar_events` ADD INDEX `idx_event_id` (`event_id`);
ALTER TABLE `calendar_events` ADD INDEX `idx_event_datetime` (`event_datetime`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

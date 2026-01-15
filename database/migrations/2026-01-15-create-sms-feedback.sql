-- Migration: Create SMS Feedback Table
-- Date: 2026-01-15
-- Description: Stores SMS feedback messages received from users via SMS forwarder

CREATE TABLE IF NOT EXISTS `sms_feedback` (
  `feedbackID` int(11) NOT NULL AUTO_INCREMENT,
  `from_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `sim_slot` varchar(50) DEFAULT NULL,
  `status` enum('unread','read','archived') DEFAULT 'unread',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `readAt` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`feedbackID`),
  KEY `idx_from_number` (`from_number`),
  KEY `idx_status` (`status`),
  KEY `idx_createdAt` (`createdAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

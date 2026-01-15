-- Migration: Add SMS Sent Tracking to sms_feedback table
-- Date: 2026-01-16
-- Description: Adds fields to track sent SMS messages from admin

ALTER TABLE `sms_feedback` 
ADD COLUMN `direction` enum('inbound','outbound') DEFAULT 'inbound' AFTER `status`,
ADD COLUMN `sent_by_admin_id` int(11) DEFAULT NULL AFTER `direction`,
ADD COLUMN `error_message` text DEFAULT NULL AFTER `sent_by_admin_id`;

-- Update existing records to be inbound
UPDATE `sms_feedback` SET `direction` = 'inbound' WHERE `direction` IS NULL;

-- Modify status enum to include 'sent' and 'failed' for outbound messages
ALTER TABLE `sms_feedback` 
MODIFY COLUMN `status` enum('unread','read','archived','sent','failed') DEFAULT 'unread';

-- Add index for better query performance
ALTER TABLE `sms_feedback` 
ADD INDEX `idx_direction` (`direction`),
ADD INDEX `idx_sent_by_admin` (`sent_by_admin_id`);

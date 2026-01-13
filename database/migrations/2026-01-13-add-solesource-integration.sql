-- Migration: Add SoleSource API integration columns to vouchers table
-- Date: 2026-01-13
-- Purpose: Track voucher redemptions and integrate with SoleSource e-commerce platform

ALTER TABLE `vouchers`
    ADD COLUMN `discount_type` ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent' AFTER `discountPercentage`,
    ADD COLUMN `redeemed_order` VARCHAR(64) NULL COMMENT 'SoleSource order number' AFTER `isUsed`,
    ADD COLUMN `redeemed_at` DATETIME NULL COMMENT 'When voucher was redeemed at SoleSource' AFTER `redeemed_order`,
    ADD COLUMN `source` ENUM('course', 'sms', 'manual') NOT NULL DEFAULT 'course' COMMENT 'How voucher was generated' AFTER `redeemed_at`,
    ADD COLUMN `student_identifier` VARCHAR(128) NULL COMMENT 'Identifier sent to SoleSource API' AFTER `userID`,
    ADD INDEX `idx_voucher_code` (`voucherCode`),
    ADD INDEX `idx_user_id` (`userID`),
    ADD INDEX `idx_is_used` (`isUsed`),
    ADD INDEX `idx_expiry_date` (`expiryDate`);

-- Notes:
-- 1. `voucherCode` will store the SoleSource-generated code (e.g., REWARD-XXXX)
-- 2. `student_identifier` tracks what we send to SoleSource (can be userID, email, or custom ID)
-- 3. `discount_type` allows future fixed-amount discounts (SoleSource supports both)
-- 4. `redeemed_order` and `redeemed_at` are populated when webhook arrives from SoleSource
-- 5. `source` distinguishes course-completion vouchers from SMS or manual admin-issued vouchers

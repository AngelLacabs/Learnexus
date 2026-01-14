-- Migration: Create certificate_downloads table
-- Run this with your database client (phpMyAdmin, mysql CLI, or a migration runner)

CREATE TABLE IF NOT EXISTS `certificate_downloads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `certificateID` INT UNSIGNED NOT NULL,
  `userID` INT UNSIGNED DEFAULT NULL,
  `ipAddress` VARCHAR(45) DEFAULT NULL,
  `userAgent` TEXT,
  `downloadedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`certificateID`),
  INDEX (`userID`),
  CONSTRAINT `fk_cert_downloads_certificate` FOREIGN KEY (`certificateID`) REFERENCES `certificates` (`certificateID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cert_downloads_user` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 11, 2026 at 09:54 AM
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
-- Database: `lmslearnexus`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `reset_all_tables` ()   BEGIN
    DECLARE _foreign_key_checks INT DEFAULT @@FOREIGN_KEY_CHECKS;
    
    SET FOREIGN_KEY_CHECKS = 0;
    
    TRUNCATE TABLE `vouchers`;
    TRUNCATE TABLE `certificates`;
    TRUNCATE TABLE `quiz_results`;
    TRUNCATE TABLE `choices`;
    TRUNCATE TABLE `questions`;
    TRUNCATE TABLE `quizzes`;
    TRUNCATE TABLE `contents`;
    TRUNCATE TABLE `modules`;
    TRUNCATE TABLE `payments`;
    TRUNCATE TABLE `enrollments`;
    TRUNCATE TABLE `courses`;
    TRUNCATE TABLE `sms_feedback`;
    TRUNCATE TABLE `sms_otp`;
    TRUNCATE TABLE `email_otp`;
    TRUNCATE TABLE `users`;
    
    SET FOREIGN_KEY_CHECKS = _foreign_key_checks;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `certificateID` int(11) NOT NULL,
  `enrollmentID` int(11) NOT NULL,
  `courseID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `certificateUUID` varchar(36) NOT NULL,
  `issuedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `instructorName` varchar(255) DEFAULT NULL,
  `studentName` varchar(255) DEFAULT NULL,
  `courseTitle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `choices`
--

CREATE TABLE `choices` (
  `choiceID` int(11) NOT NULL,
  `questionID` int(11) NOT NULL,
  `choiceLetter` char(1) NOT NULL,
  `choiceText` text NOT NULL,
  `isCorrect` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contents`
--

CREATE TABLE `contents` (
  `contentID` int(11) NOT NULL,
  `moduleID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `orderNumber` int(11) NOT NULL,
  `filePath` varchar(500) DEFAULT NULL,
  `uploadedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `courseID` int(11) NOT NULL,
  `teacherID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `passingScore` int(11) DEFAULT 70,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_otp`
--

CREATE TABLE `email_otp` (
  `emailOtpID` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otpCode` varchar(6) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `expiresAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_otp`
--

INSERT INTO `email_otp` (`emailOtpID`, `email`, `otpCode`, `userID`, `expiresAt`, `verified`, `createdAt`) VALUES
(1, 'angelicalacaba0660@gmail.com', '035342', NULL, '2026-01-11 00:05:18', 0, '2026-01-11 06:55:18'),
(5, 'paduakim085@gmail.com', '758525', NULL, '2026-01-11 08:13:09', 1, '2026-01-11 08:13:01'),
(6, 'paduakim085@gmail.com', '753245', NULL, '2026-01-11 01:31:42', 0, '2026-01-11 08:21:42'),
(7, 'paduakim085@gmail.com', '844270', NULL, '2026-01-11 01:52:01', 0, '2026-01-11 08:42:01'),
(8, 'paduakim085@gmail.com', '608636', NULL, '2026-01-11 01:52:51', 0, '2026-01-11 08:42:51'),
(9, 'paduakim085@gmail.com', '890798', NULL, '2026-01-11 01:53:20', 0, '2026-01-11 08:43:20'),
(10, 'paduakim085@gmail.com', '211649', NULL, '2026-01-11 01:54:13', 0, '2026-01-11 08:44:13'),
(11, 'paduakim085@gmail.com', '947969', NULL, '2026-01-11 01:57:41', 0, '2026-01-11 08:47:41'),
(12, 'paduakim085@gmail.com', '237220', NULL, '2026-01-11 01:59:49', 0, '2026-01-11 08:49:49'),
(13, 'paduakim085@gmail.com', '676513', NULL, '2026-01-11 01:59:54', 0, '2026-01-11 08:49:54'),
(14, 'paduakim085@gmail.com', '361423', NULL, '2026-01-11 02:02:53', 0, '2026-01-11 08:52:53');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollmentID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `courseID` int(11) NOT NULL,
  `paymentID` int(11) DEFAULT NULL,
  `progressPercentage` decimal(5,2) DEFAULT 0.00,
  `enrolledAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `completedAt` timestamp NULL DEFAULT NULL,
  `status` enum('active','completed','dropped','pending') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `moduleID` int(11) NOT NULL,
  `courseID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `orderNumber` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `paymentID` int(11) NOT NULL,
  `enrollmentID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `courseID` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transactionReference` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `paymentDate` timestamp NULL DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `questionID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `questionText` text NOT NULL,
  `points` int(11) DEFAULT 1,
  `orderNumber` int(11) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quizID` int(11) NOT NULL,
  `courseID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `allowRetake` tinyint(1) DEFAULT 1,
  `passingScore` int(11) DEFAULT 70,
  `timeLimitMinutes` int(11) DEFAULT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_results`
--

CREATE TABLE `quiz_results` (
  `resultID` int(11) NOT NULL,
  `enrollmentID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `totalPoints` int(11) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `status` enum('passed','failed','pending') DEFAULT 'pending',
  `submittedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_feedback`
--

CREATE TABLE `sms_feedback` (
  `feedbackID` int(11) NOT NULL,
  `userPhone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `receivedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read','archived') DEFAULT 'unread'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_otp`
--

CREATE TABLE `sms_otp` (
  `otpID` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `otpCode` varchar(6) NOT NULL,
  `expiresAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_otp`
--

INSERT INTO `sms_otp` (`otpID`, `phone`, `otpCode`, `expiresAt`, `verified`, `createdAt`) VALUES
(1, '09940695628', '479126', '2026-01-11 08:49:04', 1, '2026-01-11 08:47:50'),
(2, '09940695628', '624548', '2026-01-11 01:59:04', 0, '2026-01-11 08:49:04'),
(3, '09940695628', '998073', '2026-01-11 02:00:02', 0, '2026-01-11 08:50:02'),
(4, '09940695628', '633557', '2026-01-11 02:03:02', 0, '2026-01-11 08:53:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `middleInitial` varchar(5) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `phoneVerified` tinyint(1) DEFAULT 0,
  `emailVerified` tinyint(1) DEFAULT 0,
  `role` enum('student','instructor','admin') DEFAULT 'student',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `studentNumber` varchar(50) DEFAULT NULL,
  `teacherNumber` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `voucherID` int(11) NOT NULL,
  `voucherCode` varchar(50) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `certificateID` int(11) DEFAULT NULL,
  `discountPercentage` decimal(5,2) NOT NULL,
  `isUsed` tinyint(1) DEFAULT 0,
  `expiryDate` date NOT NULL,
  `generatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`certificateID`),
  ADD UNIQUE KEY `certificateUUID` (`certificateUUID`),
  ADD KEY `enrollmentID` (`enrollmentID`),
  ADD KEY `courseID` (`courseID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `idx_certificates_uuid` (`certificateUUID`);

--
-- Indexes for table `choices`
--
ALTER TABLE `choices`
  ADD PRIMARY KEY (`choiceID`),
  ADD KEY `questionID` (`questionID`);

--
-- Indexes for table `contents`
--
ALTER TABLE `contents`
  ADD PRIMARY KEY (`contentID`),
  ADD KEY `moduleID` (`moduleID`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`courseID`),
  ADD KEY `idx_courses_teacher` (`teacherID`);

--
-- Indexes for table `email_otp`
--
ALTER TABLE `email_otp`
  ADD PRIMARY KEY (`emailOtpID`),
  ADD KEY `email` (`email`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollmentID`),
  ADD KEY `paymentID` (`paymentID`),
  ADD KEY `idx_enrollments_user` (`userID`),
  ADD KEY `idx_enrollments_course` (`courseID`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`moduleID`),
  ADD KEY `courseID` (`courseID`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`paymentID`),
  ADD UNIQUE KEY `transactionReference` (`transactionReference`),
  ADD KEY `enrollmentID` (`enrollmentID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `courseID` (`courseID`),
  ADD KEY `idx_payments_reference` (`transactionReference`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `quizID` (`quizID`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quizID`),
  ADD KEY `courseID` (`courseID`);

--
-- Indexes for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD PRIMARY KEY (`resultID`),
  ADD KEY `enrollmentID` (`enrollmentID`),
  ADD KEY `quizID` (`quizID`),
  ADD KEY `idx_quiz_results_user_quiz` (`userID`,`quizID`);

--
-- Indexes for table `sms_feedback`
--
ALTER TABLE `sms_feedback`
  ADD PRIMARY KEY (`feedbackID`);

--
-- Indexes for table `sms_otp`
--
ALTER TABLE `sms_otp`
  ADD PRIMARY KEY (`otpID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `studentNumber` (`studentNumber`),
  ADD UNIQUE KEY `teacherNumber` (`teacherNumber`),
  ADD KEY `idx_users_email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`voucherID`),
  ADD UNIQUE KEY `voucherCode` (`voucherCode`),
  ADD KEY `userID` (`userID`),
  ADD KEY `certificateID` (`certificateID`),
  ADD KEY `idx_vouchers_code` (`voucherCode`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `certificateID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `choices`
--
ALTER TABLE `choices`
  MODIFY `choiceID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contents`
--
ALTER TABLE `contents`
  MODIFY `contentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `courseID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_otp`
--
ALTER TABLE `email_otp`
  MODIFY `emailOtpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollmentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `moduleID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quizID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `resultID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_feedback`
--
ALTER TABLE `sms_feedback`
  MODIFY `feedbackID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_otp`
--
ALTER TABLE `sms_otp`
  MODIFY `otpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `voucherID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`enrollmentID`) REFERENCES `enrollments` (`enrollmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_3` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `choices`
--
ALTER TABLE `choices`
  ADD CONSTRAINT `choices_ibfk_1` FOREIGN KEY (`questionID`) REFERENCES `questions` (`questionID`) ON DELETE CASCADE;

--
-- Constraints for table `contents`
--
ALTER TABLE `contents`
  ADD CONSTRAINT `contents_ibfk_1` FOREIGN KEY (`moduleID`) REFERENCES `modules` (`moduleID`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacherID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `email_otp`
--
ALTER TABLE `email_otp`
  ADD CONSTRAINT `email_otp_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`paymentID`) REFERENCES `payments` (`paymentID`) ON DELETE SET NULL;

--
-- Constraints for table `modules`
--
ALTER TABLE `modules`
  ADD CONSTRAINT `modules_ibfk_1` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`enrollmentID`) REFERENCES `enrollments` (`enrollmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD CONSTRAINT `quiz_results_ibfk_1` FOREIGN KEY (`enrollmentID`) REFERENCES `enrollments` (`enrollmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_results_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_results_ibfk_3` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE;

--
-- Constraints for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD CONSTRAINT `vouchers_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE SET NULL,
  ADD CONSTRAINT `vouchers_ibfk_2` FOREIGN KEY (`certificateID`) REFERENCES `certificates` (`certificateID`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

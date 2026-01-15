-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2026 at 12:06 PM
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
    
    -- Use DELETE instead of TRUNCATE to avoid FK constraint issues
    -- Delete child tables first (tables with FK constraints)
    DELETE FROM `vouchers`;
    DELETE FROM `certificates`;
    DELETE FROM `quizanswers`;
    DELETE FROM `quizresults`;
    DELETE FROM `lessoncompletion`;
    DELETE FROM `payments`;
    DELETE FROM `enrollments`;
    
    -- Then delete parent tables
    DELETE FROM `quizquestions`;
    DELETE FROM `quizzes`;
    DELETE FROM `lessons`;
    DELETE FROM `courses`;
    DELETE FROM `smsotp`;
    DELETE FROM `emailotp`;
    DELETE FROM `users`;
    
    -- Reset auto-increment counters
    ALTER TABLE `vouchers` AUTO_INCREMENT = 1;
    ALTER TABLE `certificates` AUTO_INCREMENT = 1;
    ALTER TABLE `quizanswers` AUTO_INCREMENT = 1;
    ALTER TABLE `quizresults` AUTO_INCREMENT = 1;
    ALTER TABLE `quizquestions` AUTO_INCREMENT = 1;
    ALTER TABLE `quizzes` AUTO_INCREMENT = 1;
    ALTER TABLE `lessoncompletion` AUTO_INCREMENT = 1;
    ALTER TABLE `lessons` AUTO_INCREMENT = 1;
    ALTER TABLE `payments` AUTO_INCREMENT = 1;
    ALTER TABLE `enrollments` AUTO_INCREMENT = 1;
    ALTER TABLE `courses` AUTO_INCREMENT = 1;
    ALTER TABLE `smsotp` AUTO_INCREMENT = 1;
    ALTER TABLE `emailotp` AUTO_INCREMENT = 1;
    ALTER TABLE `users` AUTO_INCREMENT = 1;
    
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

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`courseID`, `teacherID`, `title`, `description`, `price`, `category`, `status`, `passingScore`, `createdAt`) VALUES
(1, 1, 'Subject 1', 'test 1', 10.00, 'Programming', 'draft', 70, '2026-01-15 10:19:53'),
(2, 1, 'Subject 2', 'Test 2', 10.00, 'Design', 'published', 70, '2026-01-15 10:39:56');

-- --------------------------------------------------------

--
-- Table structure for table `emailotp`
--

CREATE TABLE `emailotp` (
  `emailOtpID` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otpCode` varchar(6) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `expiresAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emailotp`
--

INSERT INTO `emailotp` (`emailOtpID`, `email`, `otpCode`, `userID`, `expiresAt`, `verified`, `createdAt`) VALUES
(1, 'angelicalacaba0660@gmail.com', '961123', NULL, '2026-01-15 09:06:28', 1, '2026-01-15 09:02:43'),
(2, 'angelicalacaba0660@gmail.com', '557338', NULL, '2026-01-15 02:16:28', 0, '2026-01-15 09:06:28'),
(3, 'angelicalacaba0660@gmail.com', '951183', NULL, '2026-01-15 02:19:45', 0, '2026-01-15 09:09:45'),
(4, 'angelicalacaba0660@gmail.com', '502130', NULL, '2026-01-15 02:22:30', 0, '2026-01-15 09:12:30'),
(5, 'justineweslley.0@gmail.com', '767798', NULL, '2026-01-15 02:45:59', 0, '2026-01-15 09:35:59'),
(6, 'justineweslley.0@gmail.com', '957553', NULL, '2026-01-15 02:46:25', 0, '2026-01-15 09:36:25');

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

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enrollmentID`, `userID`, `courseID`, `paymentID`, `progressPercentage`, `enrolledAt`, `completedAt`, `status`) VALUES
(1, 3, 2, 1, 100.00, '2026-01-15 10:44:24', '2026-01-15 10:44:42', 'completed');

-- --------------------------------------------------------

--
-- Table structure for table `lessoncompletion`
--

CREATE TABLE `lessoncompletion` (
  `id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `lessonID` int(11) NOT NULL,
  `completedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessoncompletion`
--

INSERT INTO `lessoncompletion` (`id`, `userID`, `lessonID`, `completedAt`) VALUES
(1, 3, 1, '2026-01-15 18:44:42');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `lessonID` int(11) NOT NULL,
  `courseID` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploadedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`lessonID`, `courseID`, `title`, `filename`, `uploadedAt`) VALUES
(1, 2, 'Lesson 1', 'uploads/lessons/6968c45f7a638_FINALERD.drawio1.pdf', '2026-01-15 10:41:35');

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`paymentID`, `enrollmentID`, `userID`, `courseID`, `amount`, `transactionReference`, `status`, `paymentDate`, `createdAt`) VALUES
(1, 1, 3, 2, 10.00, '53X96568WH858392P', 'completed', '2026-01-15 10:44:24', '2026-01-15 10:44:24');

-- --------------------------------------------------------

--
-- Table structure for table `quizanswers`
--

CREATE TABLE `quizanswers` (
  `answerID` int(11) NOT NULL,
  `quizResultID` int(11) NOT NULL,
  `questionID` int(11) NOT NULL,
  `selectedOption` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quizquestions`
--

CREATE TABLE `quizquestions` (
  `questionID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `question` text NOT NULL,
  `option1` varchar(255) NOT NULL,
  `option2` varchar(255) NOT NULL,
  `option3` varchar(255) NOT NULL,
  `option4` varchar(255) NOT NULL,
  `correct_option` tinyint(4) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizquestions`
--

INSERT INTO `quizquestions` (`questionID`, `quizID`, `question`, `option1`, `option2`, `option3`, `option4`, `correct_option`, `createdAt`) VALUES
(1, 1, '1+1 =', '2', 'frf', 'r', 'ade', 0, '2026-01-15 10:38:19');

-- --------------------------------------------------------

--
-- Table structure for table `quizresults`
--

CREATE TABLE `quizresults` (
  `resultID` int(11) NOT NULL,
  `enrollmentID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `totalPoints` int(11) DEFAULT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `status` enum('passed','failed','pending') DEFAULT 'pending',
  `submittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `passed` tinyint(1) NOT NULL DEFAULT 0,
  `takenAt` datetime NOT NULL DEFAULT current_timestamp()
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

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quizID`, `courseID`, `title`, `description`, `allowRetake`, `passingScore`, `timeLimitMinutes`, `createdAt`) VALUES
(1, 1, 'Subject 1 Exam', 'Exam 1', 1, 70, 5, '2026-01-15 10:37:58'),
(2, 2, 'Quiz2', '', 1, 70, NULL, '2026-01-15 10:40:34');

-- --------------------------------------------------------

--
-- Table structure for table `smsotp`
--

CREATE TABLE `smsotp` (
  `otpID` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `otpCode` varchar(6) NOT NULL,
  `expiresAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified` tinyint(1) DEFAULT 0,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `smsotp`
--

INSERT INTO `smsotp` (`otpID`, `phone`, `otpCode`, `expiresAt`, `verified`, `createdAt`) VALUES
(1, '09954778940', '638038', '2026-01-15 09:08:52', 1, '2026-01-15 09:07:37'),
(2, '09954778940', '591002', '2026-01-15 09:08:59', 1, '2026-01-15 09:08:52'),
(3, '09954778940', '664137', '2026-01-15 09:09:16', 1, '2026-01-15 09:08:59'),
(4, '09954778940', '116109', '2026-01-15 09:14:58', 1, '2026-01-15 09:09:16'),
(5, '09954778940', '961309', '2026-01-15 09:15:22', 1, '2026-01-15 09:14:58'),
(6, '09913487886', '215372', '2026-01-15 02:46:15', 0, '2026-01-15 09:36:15');

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
  `avatar` varchar(255) DEFAULT NULL,
  `phoneVerified` tinyint(1) DEFAULT 0,
  `emailVerified` tinyint(1) DEFAULT 0,
  `role` enum('student','instructor','admin') DEFAULT 'student',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `studentNumber` varchar(50) DEFAULT NULL,
  `teacherNumber` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `email`, `passwordHash`, `firstName`, `lastName`, `middleInitial`, `phone`, `avatar`, `phoneVerified`, `emailVerified`, `role`, `status`, `createdAt`, `studentNumber`, `teacherNumber`) VALUES
(1, 'angelicalacaba0660@gmail.com', '$2y$10$/RJWTkGCAQyzqLPdvSeXjuInbvO7Fgc1H2I.VEXZc0dQ7p436ggmi', 'Angelica', 'Lacaba', '', '09954778940', '../uploads/avatars/avatar_1_1768468564.png', 1, 1, 'instructor', 'active', '2026-01-15 09:15:22', NULL, 'Teacher-1-2026'),
(2, 'learnexuspupstc@gmail.com', '$2y$10$FchQ6ncrleF/tyOJ8IWu1eD0/MDksgNPwjiA2eLkLWpoQtOD4NbEu', 'Admin', 'Learnexus', 'L', '09123456789', NULL, 1, 1, 'admin', 'active', '2026-01-15 09:16:50', NULL, NULL),
(3, 'justineweslley.0@gmail.com', '$2y$10$NHgYOoVWLfneBc6PjZcMN..j82Eu4KHzQj.PEnHA3I0wRNdQyIora', 'Justine', 'Pontilla', '', '09913487886', '../uploads/avatars/avatar_3_1768470067.jpg', 1, 1, 'student', 'active', '2026-01-15 09:37:08', 'Student-1-2026', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `voucherID` int(11) NOT NULL,
  `voucherCode` varchar(50) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `student_identifier` varchar(128) DEFAULT NULL COMMENT 'Identifier sent to SoleSource API',
  `certificateID` int(11) DEFAULT NULL,
  `discountPercentage` decimal(5,2) NOT NULL,
  `discount_type` enum('percent','fixed') NOT NULL DEFAULT 'percent',
  `isUsed` tinyint(1) DEFAULT 0,
  `redeemed_order` varchar(64) DEFAULT NULL COMMENT 'SoleSource order number',
  `redeemed_at` datetime DEFAULT NULL COMMENT 'When voucher was redeemed at SoleSource',
  `source` enum('course','sms','manual') NOT NULL DEFAULT 'course' COMMENT 'How voucher was generated',
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
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`courseID`),
  ADD KEY `idx_courses_teacher` (`teacherID`);

--
-- Indexes for table `emailotp`
--
ALTER TABLE `emailotp`
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
-- Indexes for table `lessoncompletion`
--
ALTER TABLE `lessoncompletion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_completion` (`userID`,`lessonID`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`lessonID`),
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
-- Indexes for table `quizanswers`
--
ALTER TABLE `quizanswers`
  ADD PRIMARY KEY (`answerID`),
  ADD KEY `quizResultID` (`quizResultID`),
  ADD KEY `questionID` (`questionID`);

--
-- Indexes for table `quizquestions`
--
ALTER TABLE `quizquestions`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `quizID` (`quizID`);

--
-- Indexes for table `quizresults`
--
ALTER TABLE `quizresults`
  ADD PRIMARY KEY (`resultID`),
  ADD KEY `enrollmentID` (`enrollmentID`),
  ADD KEY `quizID` (`quizID`),
  ADD KEY `idx_quiz_results_user_quiz` (`userID`,`quizID`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quizID`),
  ADD KEY `courseID` (`courseID`);

--
-- Indexes for table `smsotp`
--
ALTER TABLE `smsotp`
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
  ADD KEY `idx_vouchers_code` (`voucherCode`),
  ADD KEY `idx_voucher_code` (`voucherCode`),
  ADD KEY `idx_user_id` (`userID`),
  ADD KEY `idx_is_used` (`isUsed`),
  ADD KEY `idx_expiry_date` (`expiryDate`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `certificateID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `courseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `emailotp`
--
ALTER TABLE `emailotp`
  MODIFY `emailOtpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lessoncompletion`
--
ALTER TABLE `lessoncompletion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `lessonID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quizanswers`
--
ALTER TABLE `quizanswers`
  MODIFY `answerID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizquestions`
--
ALTER TABLE `quizquestions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quizresults`
--
ALTER TABLE `quizresults`
  MODIFY `resultID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quizID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `smsotp`
--
ALTER TABLE `smsotp`
  MODIFY `otpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacherID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `emailotp`
--
ALTER TABLE `emailotp`
  ADD CONSTRAINT `emailotp_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`paymentID`) REFERENCES `payments` (`paymentID`) ON DELETE SET NULL;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`enrollmentID`) REFERENCES `enrollments` (`enrollmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE;

--
-- Constraints for table `quizanswers`
--
ALTER TABLE `quizanswers`
  ADD CONSTRAINT `quizAnswers_ibfk_1` FOREIGN KEY (`quizResultID`) REFERENCES `quizresults` (`resultID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quizAnswers_ibfk_2` FOREIGN KEY (`questionID`) REFERENCES `quizquestions` (`questionID`) ON DELETE CASCADE;

--
-- Constraints for table `quizquestions`
--
ALTER TABLE `quizquestions`
  ADD CONSTRAINT `quizQuestions_ibfk_1` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE;

--
-- Constraints for table `quizresults`
--
ALTER TABLE `quizresults`
  ADD CONSTRAINT `quizResults_ibfk_1` FOREIGN KEY (`enrollmentID`) REFERENCES `enrollments` (`enrollmentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quizResults_ibfk_2` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quizResults_ibfk_3` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE;

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

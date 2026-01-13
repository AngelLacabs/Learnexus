-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 13, 2026 at 12:56 PM
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

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`certificateID`, `enrollmentID`, `courseID`, `userID`, `certificateUUID`, `issuedAt`, `instructorName`, `studentName`, `courseTitle`) VALUES
(1, 1, 2, 2, 'a259ccd4-8e2c-4dda-bc9d-7cbc80a314a1', '2026-01-12 06:04:17', 'Andrew Coral', 'Andrew B. Coral', 'Data Administration'),
(2, 5, 4, 2, 'c4519743-f1b2-4e80-9f1c-ab72f272adc2', '2026-01-13 04:39:54', 'Andrew Coral', 'Andrew B. Coral', 'RIPH'),
(3, 7, 3, 2, 'cert_6965e4736b4cf7.16107855', '2026-01-13 06:21:39', NULL, NULL, NULL);

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

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`courseID`, `teacherID`, `title`, `description`, `price`, `category`, `status`, `passingScore`, `createdAt`) VALUES
(2, 3, 'Data Administration', '', 100.00, 'Programming', 'published', 70, '2026-01-11 10:21:25'),
(3, 3, 'SAD', '', 50.00, 'Design', 'published', 70, '2026-01-12 06:18:05'),
(4, 3, 'RIPH', '', 10.00, 'Marketing', 'published', 70, '2026-01-12 06:21:37'),
(5, 3, 'TEST1', '', 50.00, 'Programming', 'published', 70, '2026-01-12 07:10:22'),
(7, 3, 'Art Appreciation', '', 10.00, 'Programming', 'published', 70, '2026-01-13 06:23:37');

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
(1, 'angelicalacaba0660@gmail.com', '427507', NULL, '2026-01-09 10:40:05', 0, '2026-01-09 17:30:05'),
(8, 'coralandrew7@gmail.com', '620305', NULL, '2026-01-10 18:21:38', 0, '2026-01-11 01:11:38'),
(10, 'gelicakes.rodrigo66@gmail.com', '858689', NULL, '2026-01-11 00:49:05', 0, '2026-01-11 07:39:05'),
(11, 'paduakim085@gmail.com', '628521', NULL, '2026-01-11 01:22:24', 0, '2026-01-11 08:12:24'),
(12, 'marasigan.juliamae@gmail.com', '426840', NULL, '2026-01-11 23:40:54', 0, '2026-01-12 06:30:54'),
(13, 'andrewacadz@gmail.com', '118286', NULL, '2026-01-12 19:09:24', 0, '2026-01-13 01:59:24'),
(14, 'andrewacadz@gmail.com', '597724', NULL, '2026-01-12 19:20:28', 0, '2026-01-13 02:10:28'),
(15, 'andrewacadz@gmail.com', '639394', NULL, '2026-01-12 19:30:00', 0, '2026-01-13 02:20:00'),
(16, 'andrewacadz@gmail.com', '249351', NULL, '2026-01-12 19:35:17', 0, '2026-01-13 02:25:17'),
(17, 'andrewacadz@gmail.com', '597231', NULL, '2026-01-12 19:41:27', 0, '2026-01-13 02:31:27'),
(18, 'andrewacadz@gmail.com', '300600', NULL, '2026-01-12 19:46:59', 0, '2026-01-13 02:36:59');

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
(1, 2, 2, 3, 100.00, '2026-01-11 15:32:17', '2026-01-12 22:47:46', 'completed'),
(5, 2, 4, 12, 100.00, '2026-01-13 04:30:06', '2026-01-12 22:47:36', 'completed'),
(7, 2, 3, 14, 100.00, '2026-01-13 06:01:41', '2026-01-13 00:20:05', 'completed'),
(19, 2, 7, 32, 100.00, '2026-01-13 11:34:15', '2026-01-13 11:54:52', 'completed');

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
(2, 2, 'Part 1', 'uploads/lessons/6963b390b09cf_Self Intro.pdf', '2026-01-11 14:28:32'),
(3, 3, 'SAD - Intro Lesson', 'uploads/lessons/6964921d42278_FINALIZING-LMS.drawio.pdf', '2026-01-12 06:18:05'),
(4, 4, 'RIPH - Intro Lesson', 'uploads/lessons/696492f1ec86a_myDB.pdf', '2026-01-12 06:21:37'),
(5, 5, 'TEST1 - Intro Lesson', 'uploads/lessons/69649e5ecd0ec_Mermaid Notation.pdf', '2026-01-12 07:10:22'),
(7, 4, 'Lesson 1', '../uploads/lessons/6965cb3f88d0c_FINALIZING-LMS.drawio.pdf', '2026-01-13 04:34:07'),
(10, 7, 'Art Appreciation - Intro Lesson', '../uploads/lessons/6965e4e96d9ec_LESSON WEEK 10-12.pdf', '2026-01-13 06:23:37');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_completions`
--

CREATE TABLE `lesson_completions` (
  `id` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `lessonID` int(11) NOT NULL,
  `completedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lesson_completions`
--

INSERT INTO `lesson_completions` (`id`, `userID`, `lessonID`, `completedAt`) VALUES
(22, 2, 2, '2026-01-12 14:00:29'),
(27, 2, 4, '2026-01-13 12:39:44'),
(28, 2, 7, '2026-01-13 12:39:45'),
(29, 2, 3, '2026-01-13 14:21:34'),
(45, 2, 10, '2026-01-13 19:54:52');

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`paymentID`, `enrollmentID`, `userID`, `courseID`, `amount`, `transactionReference`, `status`, `paymentDate`, `createdAt`) VALUES
(3, 1, 2, 2, 100.00, '3PD75966M49920535', 'completed', '2026-01-11 15:32:17', '2026-01-11 15:32:17'),
(4, 1, 2, 2, 100.00, '95P06104YJ299842S', 'completed', '2026-01-12 05:46:48', '2026-01-12 05:46:48'),
(5, 1, 2, 2, 100.00, '65648183083769645', 'completed', '2026-01-12 05:52:49', '2026-01-12 05:52:49'),
(6, 1, 2, 2, 100.00, '17P05731LP463472L', 'completed', '2026-01-12 05:55:35', '2026-01-12 05:55:35'),
(7, 1, 2, 2, 100.00, '2MW68986YN407984D', 'completed', '2026-01-12 05:57:50', '2026-01-12 05:57:50'),
(8, 1, 2, 2, 100.00, '5VS99243D90050401', 'completed', '2026-01-12 06:00:23', '2026-01-12 06:00:23'),
(12, 5, 2, 4, 10.00, '967593466N745394Y', 'completed', '2026-01-13 04:30:06', '2026-01-13 04:30:06'),
(14, 7, 2, 3, 50.00, '88B76882SS637902L', 'completed', '2026-01-13 06:01:41', '2026-01-13 06:01:41'),
(32, 19, 2, 7, 10.00, '7D096846L1341280F', 'completed', '2026-01-13 11:34:15', '2026-01-13 11:34:15'),
(33, 19, 2, 7, 100.00, '2P659505NP894524E', 'completed', '2026-01-13 11:44:30', '2026-01-13 11:44:30'),
(34, 19, 2, 7, 100.00, '5G315536UA928160H', 'completed', '2026-01-13 11:46:28', '2026-01-13 11:46:28'),
(35, 19, 2, 7, 100.00, '33N818398H3803617', 'completed', '2026-01-13 11:50:16', '2026-01-13 11:50:16');

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

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quizID`, `courseID`, `title`, `description`, `allowRetake`, `passingScore`, `timeLimitMinutes`, `createdAt`) VALUES
(1, 2, 'QUIZ 1', '', 1, 70, NULL, '2026-01-11 17:52:13'),
(2, 4, 'FINAL EXAM', '', 0, 75, NULL, '2026-01-13 04:38:28'),
(4, 7, 'Final Exam', '', 1, 70, NULL, '2026-01-13 11:52:54');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_answers`
--

CREATE TABLE `quiz_answers` (
  `answerID` int(11) NOT NULL,
  `quizResultID` int(11) NOT NULL,
  `questionID` int(11) NOT NULL,
  `selectedOption` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
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
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`questionID`, `quizID`, `question`, `option1`, `option2`, `option3`, `option4`, `correct_option`, `createdAt`) VALUES
(3, 1, 'tulog kana?', 'yes', 'no', 'maybe', 'pake mo', 0, '2026-01-11 19:38:48'),
(4, 1, 'Ye_?', 's', 'b', 'd', 'p', 0, '2026-01-11 20:14:49'),
(5, 2, 'hep hep?', 'hooray', 'notize', 'help', 'idk', 0, '2026-01-13 04:39:06'),
(8, 4, 'Who painted Mona Lisa', 'Ewan', 'Me', 'Vincent Van Gogh', 'Siya', 2, '2026-01-13 11:54:26');

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
  `submittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `passed` tinyint(1) NOT NULL DEFAULT 0,
  `takenAt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_results`
--

INSERT INTO `quiz_results` (`resultID`, `enrollmentID`, `userID`, `quizID`, `score`, `totalPoints`, `percentage`, `status`, `submittedAt`, `passed`, `takenAt`) VALUES
(12, 1, 2, 1, 2.00, NULL, 100.00, 'passed', '2026-01-12 06:00:38', 1, '2026-01-12 14:00:38'),
(13, 5, 2, 2, 1.00, NULL, 100.00, 'passed', '2026-01-13 04:39:50', 1, '2026-01-13 12:39:50');

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
(1, '09661308611', '742565', '2026-01-11 01:22:34', 0, '2026-01-11 08:12:34'),
(2, '09387450528', '942507', '2026-01-11 23:41:04', 0, '2026-01-12 06:31:04');

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
(2, 'coralandrew7@gmail.com', '$2y$10$El2k8n20aI0h69tNIVHfaOVIZESP58WFkO4lbrh2QypLPLwzSZ6ia', 'Andrew', 'Coral', 'B', '09661308611', '../uploads/avatars/avatar_2_1768096756.jpg', 1, 1, 'student', 'active', '2026-01-11 01:14:31', '2023-00361-ST-0', NULL),
(3, 'gelicakes.rodrigo66@gmail.com', '$2y$10$3AcItDmydOTghw1zXATUXuDvmVMYh1/9bcUdgLP7CMuV9m6AvtPm6', 'Andrew', 'Coral', 'B', '09306906952', '../uploads/avatars/avatar_3_1768120349.png', 1, 1, 'instructor', 'active', '2026-01-11 07:39:32', NULL, '123456789'),
(4, 'marasigan.juliamae@gmail.com', '$2y$10$IhPbMcwZggqLiRfI8/0TZOPLcGhNtIa1XeQaHdGHlviGz4L6tQW8K', 'Julia', 'Marasigan', '', '09387450528', NULL, 1, 1, 'student', 'active', '2026-01-12 06:31:41', 'STU-007', NULL);

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
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`lessonID`),
  ADD KEY `courseID` (`courseID`);

--
-- Indexes for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_completion` (`userID`,`lessonID`);

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
-- Indexes for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD PRIMARY KEY (`answerID`),
  ADD KEY `quizResultID` (`quizResultID`),
  ADD KEY `questionID` (`questionID`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `quizID` (`quizID`);

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
  MODIFY `certificateID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `courseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `email_otp`
--
ALTER TABLE `email_otp`
  MODIFY `emailOtpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `lessonID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `moduleID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quizID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `answerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `resultID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `sms_feedback`
--
ALTER TABLE `sms_feedback`
  MODIFY `feedbackID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_otp`
--
ALTER TABLE `sms_otp`
  MODIFY `otpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`courseID`) REFERENCES `courses` (`courseID`) ON DELETE CASCADE;

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
-- Constraints for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  ADD CONSTRAINT `quiz_answers_ibfk_1` FOREIGN KEY (`quizResultID`) REFERENCES `quiz_results` (`resultID`) ON DELETE CASCADE,
  ADD CONSTRAINT `quiz_answers_ibfk_2` FOREIGN KEY (`questionID`) REFERENCES `quiz_questions` (`questionID`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE;

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

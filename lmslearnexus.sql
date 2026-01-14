-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 13, 2026 at 06:10 PM
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
(8, 9, 2, 5, '21b9db90-f3d2-456b-832c-6ad4fa15601e', '2026-01-13 12:59:17', 'Andrew Coral', 'SoleSource KICKS. Caranay', 'Data Administration'),
(11, 14, 2, 9, '7aa240a9-205d-4b16-b11a-7e989f1c3755', '2026-01-13 13:57:37', 'Andrew Coral', 'Adrian L. Caranay', 'Data Administration'),
(12, 17, 2, 10, '2d5d4f29-8dce-4380-8f88-eff5d97ec66b', '2026-01-13 16:18:26', 'Andrew Coral', 'Angelica R.. Lacaba', 'Data Administration');

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
(3, 3, 'SAD', '', 50.00, 'Design', 'draft', 70, '2026-01-12 06:18:05'),
(4, 3, 'RIPH', '', 10.00, 'Programming', 'published', 70, '2026-01-12 06:21:37'),
(5, 3, 'Web Development', '', 10.00, 'Programming', 'published', 70, '2026-01-13 13:57:15');

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
(13, 'solesource@gmail.com', '584839', NULL, '2026-01-13 04:20:51', 0, '2026-01-13 11:10:51'),
(14, 'noreply.solesource@gmail.com', '471578', NULL, '2026-01-13 04:22:22', 0, '2026-01-13 11:12:22'),
(15, 'jamescarlorivera52@gmail.com', '696817', NULL, '2026-01-13 05:42:48', 0, '2026-01-13 12:32:48'),
(16, 'laurenceadrian1@gmail.com', '645488', NULL, '2026-01-13 06:43:36', 0, '2026-01-13 13:33:36'),
(17, 'laurenceadrian1@gmail.com', '843666', NULL, '2026-01-13 07:06:13', 0, '2026-01-13 13:56:13'),
(18, 'carlorivera206@gmail.com', '580669', NULL, '2026-01-13 09:22:30', 0, '2026-01-13 16:12:30'),
(19, 'abulencialeanne@gmail.com', '014670', NULL, '2026-01-13 10:15:43', 0, '2026-01-13 17:05:43'),
(20, 'yanayand18@gmail.com', '420844', NULL, '2026-01-13 10:17:43', 0, '2026-01-13 17:07:43'),
(21, 'justineweslley.0@gmail.com', '263637', NULL, '2026-01-13 10:19:02', 0, '2026-01-13 17:09:02');

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
(1, 2, 2, 3, 100.00, '2026-01-11 15:32:17', '2026-01-13 06:51:58', 'completed'),
(9, 5, 2, 16, 100.00, '2026-01-13 12:59:05', '2026-01-13 12:59:15', 'completed'),
(13, 2, 4, 18, 100.00, '2026-01-13 13:54:09', '2026-01-13 06:54:51', 'completed'),
(14, 9, 2, 19, 100.00, '2026-01-13 13:57:17', '2026-01-13 13:57:34', 'completed'),
(15, 2, 5, 20, 100.00, '2026-01-13 14:04:17', '2026-01-13 14:07:09', 'completed'),
(16, 10, 5, 21, 33.00, '2026-01-13 16:13:24', NULL, 'completed'),
(17, 10, 2, 22, 100.00, '2026-01-13 16:14:21', '2026-01-13 16:14:28', 'completed');

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
(5, 5, 'Web Development - Intro Lesson', '../uploads/lessons/69664f3b32892_Act3.pdf', '2026-01-13 13:57:15');

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
(30, 5, 2, '2026-01-13 20:59:11'),
(31, 8, 2, '2026-01-13 21:37:49'),
(32, 2, 4, '2026-01-13 21:54:20'),
(33, 9, 2, '2026-01-13 21:57:27'),
(34, 2, 5, '2026-01-13 22:07:09'),
(35, 10, 5, '2026-01-14 00:13:31'),
(36, 10, 2, '2026-01-14 00:14:28');

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
(16, 9, 5, 2, 100.00, '5T132583JE281001E', 'completed', '2026-01-13 12:59:05', '2026-01-13 12:59:05'),
(18, 13, 2, 4, 10.00, '09T24446MM440903E', 'completed', '2026-01-13 13:54:09', '2026-01-13 13:54:09'),
(19, 14, 9, 2, 100.00, '5BP58424KJ7773841', 'completed', '2026-01-13 13:57:17', '2026-01-13 13:57:17'),
(20, 15, 2, 5, 10.00, '85H4119985755372E', 'completed', '2026-01-13 14:04:17', '2026-01-13 14:04:17'),
(21, 16, 10, 5, 10.00, '04M58607AF543612H', 'completed', '2026-01-13 16:13:24', '2026-01-13 16:13:24'),
(22, 17, 10, 2, 100.00, '3YL776214Y783190U', 'completed', '2026-01-13 16:14:21', '2026-01-13 16:14:21');

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
(2, 5, 'Finals', '', 0, 70, NULL, '2026-01-13 13:57:54'),
(3, 5, 'Finals', '', 0, 70, NULL, '2026-01-13 13:58:06');

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
(5, 3, 'Meaning of API', 'inaapi', 'mangaapi', 'Application Programming Interface', 'APIr', 0, '2026-01-13 13:58:44');

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
(19, 9, 5, 1, 2.00, NULL, 100.00, 'passed', '2026-01-13 12:59:15', 1, '2026-01-13 20:59:15'),
(21, 14, 9, 1, 2.00, NULL, 100.00, 'passed', '2026-01-13 13:57:34', 1, '2026-01-13 21:57:34'),
(22, 17, 10, 1, 2.00, NULL, 100.00, 'passed', '2026-01-13 16:14:39', 1, '2026-01-14 00:14:39');

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
(4, 'marasigan.juliamae@gmail.com', '$2y$10$IhPbMcwZggqLiRfI8/0TZOPLcGhNtIa1XeQaHdGHlviGz4L6tQW8K', 'Julia', 'Marasigan', '', '09387450528', NULL, 1, 1, 'student', 'active', '2026-01-12 06:31:41', 'STU-007', NULL),
(5, 'noreply.solesource@gmail.com', '$2y$10$moZuIycWLa7NGQ5mfDVaFeXUPXXm9Xf2AE2YeWUZWddef1CJeh8ei', 'SoleSource', 'Caranay', 'KICKS', '09457996892', NULL, 1, 1, 'student', 'active', '2026-01-13 11:12:42', 'SOLESOURCE01', NULL),
(6, 'jamescarlorivera52@gmail.com', '$2y$10$H9hMKvs3e8v.AM1npfZeg.ch1FtVnZdV.06E1.yPLmqs5IN5QFIvm', 'test', 'test', 'test', '09457996892', NULL, 1, 1, 'student', 'active', '2026-01-13 12:33:20', 'SOLESOURCE02', NULL),
(7, 'learnexuspupstc@gmail.com', '$2y$10$SlP1S6.Jen0XGSSIIoq.ouuNYSvsHajSFcMrhJvQm61XhmNV54uEq', 'Admin', 'Learnexus', 'L', '09123456789', NULL, 1, 1, 'admin', 'active', '2026-01-13 12:49:38', NULL, NULL),
(9, 'laurenceadrian1@gmail.com', '$2y$10$gnJ4OerQnvt9qXSqUC6cAuFWLk4.AiHToodLbK1Pjt7AtUgXFIQMq', 'Adrian', 'Caranay', 'L', '09918043621', NULL, 1, 1, 'student', 'active', '2026-01-13 13:56:32', 'solesourcetest', NULL),
(10, 'carlorivera206@gmail.com', '$2y$10$hNd4OSzWzbTgU.XbTQ7Pv.5SUQ7PNsDkJ/cNB0FBiOdVrXPykQ.bW', 'Angelica', 'Lacaba', 'R.', '09954778940', NULL, 1, 1, 'student', 'active', '2026-01-13 16:12:46', 'rivera123', NULL),
(11, 'abulencialeanne@gmail.com', '$2y$10$.bG.XkKpKzHjOtKQhvNML.pqdo1fvNuW/Gn/nPMZj0yximpvsH0tu', 'Angelica', 'Lacaba', '', '09913487886', NULL, 1, 1, 'student', 'active', '2026-01-13 17:06:04', '123456', NULL),
(12, 'justineweslley.0@gmail.com', '$2y$10$EyyohtWuApUqqYvLf5WvCeSO7o.2uD.JF7l8Qn4ovfKWdY/Jv.m8C', 'moana', 'heyhey', '', '12345555554', NULL, 1, 1, 'instructor', 'active', '2026-01-13 17:09:21', NULL, 'moanaandheyhey');

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
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`voucherID`, `voucherCode`, `userID`, `student_identifier`, `certificateID`, `discountPercentage`, `discount_type`, `isUsed`, `redeemed_order`, `redeemed_at`, `source`, `expiryDate`, `generatedAt`) VALUES
(1, 'GETSOLE-5XNU', 5, 'learnexus-5', 8, 12.00, 'percent', 1, 'ORDER-TEST-001', '2026-01-13 20:00:00', 'course', '2026-01-20', '2026-01-13 12:59:17'),
(2, 'REWARD-SAFJ', NULL, 'learnexus-8', NULL, 12.00, 'percent', 1, 'SO-20260113143906-4183', '2026-01-13 21:39:06', 'course', '2026-01-20', '2026-01-13 13:38:02'),
(3, 'GETSOLE-UZZS', 9, 'learnexus-9', 11, 12.00, 'percent', 1, 'SO-20260113150008-7025', '2026-01-13 15:00:08', 'course', '2026-01-20', '2026-01-13 13:57:37'),
(4, 'GETSOLE-37WK', 10, 'learnexus-10', 12, 12.00, 'percent', 1, 'SO-20260113172258-7705', '2026-01-13 17:22:58', 'course', '2026-01-20', '2026-01-13 16:18:26');

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
  MODIFY `certificateID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
  MODIFY `courseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_otp`
--
ALTER TABLE `email_otp`
  MODIFY `emailOtpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `lessonID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `lesson_completions`
--
ALTER TABLE `lesson_completions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `moduleID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quizID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `quiz_answers`
--
ALTER TABLE `quiz_answers`
  MODIFY `answerID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `resultID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `voucherID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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

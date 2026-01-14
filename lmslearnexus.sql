-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 14, 2026 at 05:01 PM
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
(12, 17, 2, 10, '2d5d4f29-8dce-4380-8f88-eff5d97ec66b', '2026-01-13 16:18:26', 'Andrew Coral', 'Angelica R.. Lacaba', 'Data Administration'),
(13, 19, 2, 11, 'ad55bada-313d-4751-a2c0-ba4af63b5685', '2026-01-14 02:41:23', 'Andrew Coral', 'Angelica Lacaba', 'Data Administration'),
(14, 21, 7, 11, '9d9af3e3-d7f3-40f2-a393-599a14844ff6', '2026-01-14 11:27:01', 'moana heyhey', 'Angelica Lacaba', 'Hotel Management'),
(15, 22, 9, 11, 'a0bc0dc4-5ed0-43e5-9dab-8da7362fe78d', '2026-01-14 12:48:34', 'moana heyhey', 'Angelica Lacaba', 'WebDEveloper'),
(16, 23, 10, 11, 'bba12b41-adba-466b-8a17-1dd3327b5568', '2026-01-14 13:02:36', 'moana heyhey', 'Angelica Lacaba', 'Fundamentals of Research');

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
(5, 3, 'Web Development', '', 10.00, 'Programming', 'published', 70, '2026-01-13 13:57:15'),
(6, 12, 'Moana study 1', 'test 1', 10.00, 'Marketing', 'published', 70, '2026-01-14 06:10:44'),
(7, 12, 'Hotel Management', 'Hotel Management test course 1', 50.00, 'Marketing', 'published', 70, '2026-01-14 11:11:45'),
(8, 12, 'programming chap 1', 'chapter 1', 10.00, 'Programming', 'published', 70, '2026-01-14 11:21:52'),
(9, 12, 'WebDEveloper', 'WebDEv', 200.00, 'Programming', 'published', 70, '2026-01-14 12:27:08'),
(10, 12, 'Fundamentals of Research', 'Research', 10.00, 'Design', 'published', 70, '2026-01-14 12:49:14');

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
(21, 'justineweslley.0@gmail.com', '263637', NULL, '2026-01-13 10:19:02', 0, '2026-01-13 17:09:02'),
(22, 'thelifeangelica@gmail.com', '840125', NULL, '2026-01-13 10:31:39', 0, '2026-01-13 17:21:39'),
(23, 'justineweslley.1@gmail.com', '641921', NULL, '2026-01-13 10:40:11', 0, '2026-01-13 17:30:11');

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
(17, 10, 2, 22, 100.00, '2026-01-13 16:14:21', '2026-01-13 16:14:28', 'completed'),
(18, 11, 5, 23, 33.00, '2026-01-14 02:39:54', NULL, 'completed'),
(19, 11, 2, 24, 100.00, '2026-01-14 02:40:45', '2026-01-14 04:36:30', 'completed'),
(20, 11, 6, 25, 0.00, '2026-01-14 06:17:01', NULL, 'active'),
(21, 11, 7, 26, 75.00, '2026-01-14 11:23:45', NULL, 'completed'),
(22, 11, 9, 27, 75.00, '2026-01-14 12:36:03', NULL, 'active'),
(23, 11, 10, 28, 100.00, '2026-01-14 13:02:05', '2026-01-14 06:02:34', 'completed');

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
(22, 2, 2, '2026-01-12 14:00:29'),
(30, 5, 2, '2026-01-13 20:59:11'),
(31, 8, 2, '2026-01-13 21:37:49'),
(32, 2, 4, '2026-01-13 21:54:20'),
(33, 9, 2, '2026-01-13 21:57:27'),
(34, 2, 5, '2026-01-13 22:07:09'),
(35, 10, 5, '2026-01-14 00:13:31'),
(36, 10, 2, '2026-01-14 00:14:28'),
(37, 11, 5, '2026-01-14 10:40:03'),
(38, 11, 2, '2026-01-14 10:41:05'),
(39, 11, 8, '2026-01-14 19:26:41'),
(40, 11, 9, '2026-01-14 19:26:43'),
(41, 11, 11, '2026-01-14 20:36:58'),
(42, 11, 12, '2026-01-14 20:37:00'),
(44, 11, 13, '2026-01-14 21:02:28'),
(45, 11, 14, '2026-01-14 21:02:29');

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
(5, 5, 'Web Development - Intro Lesson', 'uploads/lessons/69664f3b32892_Act3.pdf', '2026-01-13 13:57:15'),
(6, 6, 'Introduction', 'uploads/lessons/lesson_69673364184d6.pdf', '2026-01-14 06:10:44'),
(7, 6, '11', 'uploads/lessons/6967351c547f2_namecheap-order-191981448.pdf', '2026-01-14 06:18:04'),
(8, 7, 'Introduction', 'uploads/lessons/lesson_696779f19ff52.pdf', '2026-01-14 11:11:45'),
(9, 7, '1', 'uploads/lessons/69677a98de0d6_Certificate_Database_Julia_Marasigan.pdf', '2026-01-14 11:14:32'),
(10, 8, '1', 'uploads/lessons/6967800d5593c_allaboutSadSiaWeb.pdf', '2026-01-14 11:37:49'),
(11, 9, 'lesson 1', 'uploads/lessons/69678bb667f9c_Certificate_Hotel_Management_Angelica___Lacaba.pdf', '2026-01-14 12:27:34'),
(12, 9, 'lesson 2', 'uploads/lessons/69678bc4d9966_Lesson1.pdf', '2026-01-14 12:27:48'),
(13, 10, '1', 'uploads/lessons/696792ce7d8d4_yeess-IntroLesson.pdf', '2026-01-14 12:57:50'),
(14, 10, 'Lesson 2', 'uploads/lessons/696792db47337_Certificate_Hotel_Management_Angelica___Lacaba.pdf', '2026-01-14 12:58:03');

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
(22, 17, 10, 2, 100.00, '3YL776214Y783190U', 'completed', '2026-01-13 16:14:21', '2026-01-13 16:14:21'),
(23, 18, 11, 5, 10.00, '0X7905570Y914112L', 'completed', '2026-01-14 02:39:54', '2026-01-14 02:39:54'),
(24, 19, 11, 2, 100.00, '81261375DK2663400', 'completed', '2026-01-14 02:40:45', '2026-01-14 02:40:45'),
(25, 20, 11, 6, 10.00, '00H798717A8391241', 'completed', '2026-01-14 06:17:01', '2026-01-14 06:17:01'),
(26, 21, 11, 7, 50.00, '44732611EC205614E', 'completed', '2026-01-14 11:23:45', '2026-01-14 11:23:45'),
(27, 22, 11, 9, 200.00, '2DJ71596W1646641G', 'completed', '2026-01-14 12:36:03', '2026-01-14 12:36:03'),
(28, 23, 11, 10, 10.00, '4HT273265M323453X', 'completed', '2026-01-14 13:02:05', '2026-01-14 13:02:05');

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

--
-- Dumping data for table `quizanswers`
--

INSERT INTO `quizanswers` (`answerID`, `quizResultID`, `questionID`, `selectedOption`) VALUES
(1, 23, 3, 0),
(2, 23, 4, 0),
(3, 24, 8, 0),
(4, 25, 10, 3),
(5, 25, 11, 0),
(6, 26, 14, 0);

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
(3, 1, 'tulog kana?', 'yes', 'no', 'maybe', 'pake mo', 0, '2026-01-11 19:38:48'),
(4, 1, 'Ye_?', 's', 'b', 'd', 'p', 0, '2026-01-11 20:14:49'),
(5, 3, 'Meaning of API', 'inaapi', 'mangaapi', 'Application Programming Interface', 'APIr', 0, '2026-01-13 13:58:44'),
(6, 4, '1+1', '3', '2', '4', '1', 1, '2026-01-14 06:12:20'),
(7, 4, '2+2', '3', '2', '4', '6', 2, '2026-01-14 06:12:35'),
(8, 5, '1+1 = 2', 't', 'f', 'maybe', 'stupid', 0, '2026-01-14 11:15:22'),
(9, 6, '1+ 1 =', 'f', '2', 'r', 'f', 1, '2026-01-14 11:16:07'),
(10, 8, '1+1', '4', '4', '56', '2', 3, '2026-01-14 12:28:17'),
(11, 8, '2+2', '4', 'df', 'g', 'sf', 0, '2026-01-14 12:28:30'),
(12, 9, '1+1', 'edad', 'asd', '2', 'asd', 2, '2026-01-14 12:29:15'),
(13, 9, 'abcd', 'e', 'dasd', 'asdad', 'adas', 0, '2026-01-14 12:29:27'),
(14, 10, 'dadad', 'a', 'sdfsdf', 'fsdfdsf', 'sdfsdf', 0, '2026-01-14 12:59:39');

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

--
-- Dumping data for table `quizresults`
--

INSERT INTO `quizresults` (`resultID`, `enrollmentID`, `userID`, `quizID`, `score`, `totalPoints`, `percentage`, `status`, `submittedAt`, `passed`, `takenAt`) VALUES
(12, 1, 2, 1, 2.00, NULL, 100.00, 'passed', '2026-01-12 06:00:38', 1, '2026-01-12 14:00:38'),
(19, 9, 5, 1, 2.00, NULL, 100.00, 'passed', '2026-01-13 12:59:15', 1, '2026-01-13 20:59:15'),
(21, 14, 9, 1, 2.00, NULL, 100.00, 'passed', '2026-01-13 13:57:34', 1, '2026-01-13 21:57:34'),
(22, 17, 10, 1, 2.00, NULL, 100.00, 'passed', '2026-01-13 16:14:39', 1, '2026-01-14 00:14:39'),
(23, 19, 11, 1, 2.00, NULL, 100.00, 'passed', '2026-01-14 02:41:19', 1, '2026-01-14 10:41:19'),
(24, 21, 11, 5, 1.00, NULL, 100.00, 'passed', '2026-01-14 11:26:49', 1, '2026-01-14 19:26:49'),
(25, 22, 11, 8, 2.00, NULL, 100.00, 'passed', '2026-01-14 12:37:22', 1, '2026-01-14 20:37:22'),
(26, 23, 11, 10, 1.00, NULL, 100.00, 'passed', '2026-01-14 13:02:34', 1, '2026-01-14 21:02:34');

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
(3, 5, 'Finals', '', 0, 70, NULL, '2026-01-13 13:58:06'),
(4, 6, 'final test 1', 'test 1 final', 1, 70, NULL, '2026-01-14 06:12:02'),
(5, 7, 'Final Exam Test 1', 'math', 1, 70, NULL, '2026-01-14 11:14:57'),
(6, 7, 'quiz 2 with time', 'timing quizzzo', 1, 70, 5, '2026-01-14 11:15:51'),
(7, 8, 'qui 1', '', 1, 70, 5, '2026-01-14 11:39:13'),
(8, 9, 'qui 1', '', 1, 70, 5, '2026-01-14 12:28:05'),
(9, 9, 'Quiz 2', '2 webdev', 1, 70, 5, '2026-01-14 12:29:05'),
(10, 10, 'FInal exam1', 'ddhfnsa;ufhonweiofbhweikjufewdf', 1, 70, 5, '2026-01-14 12:59:24');

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
(11, 'abulencialeanne@gmail.com', '$2y$10$.bG.XkKpKzHjOtKQhvNML.pqdo1fvNuW/Gn/nPMZj0yximpvsH0tu', 'Angelica', 'Lacaba', '', '09913487886', '../uploads/avatars/avatar_11_1768390084.png', 1, 1, 'student', 'active', '2026-01-13 17:06:04', '123456', NULL),
(12, 'justineweslley.0@gmail.com', '$2y$10$N1n/QanWDGtMAioq.rsueeRpL44thFW.G6s3hNKOlwYeRtLaQGduC', 'moana', 'heyhey', '', '12345555554', '../uploads/avatars/avatar_12_1768389390.png', 1, 1, 'instructor', 'active', '2026-01-13 17:09:21', NULL, 'moanaandheyhey'),
(13, 'thelifeangelica@gmail.com', '$2y$10$sbz7WEWNHMrEDnHiYInvz.WqprVpHlBkRPy5qDcPBvISdpHzDqBG.', 'samantha', 'leanne', '', '23242342353', NULL, 1, 1, 'student', 'active', '2026-01-13 17:21:52', 'sam123', NULL),
(14, 'justineweslley.1@gmail.com', '$2y$10$.XzHj35RjN7m2jys4ohaA.boZ9qlbNVcgw763/1fNXrhr/15Qal4m', 'Justine Weslley', 'Pontilla', 'b.', '09940695628', NULL, 1, 1, 'student', 'active', '2026-01-13 17:30:29', '12345678', NULL);

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
(4, 'GETSOLE-37WK', 10, 'learnexus-10', 12, 12.00, 'percent', 1, 'SO-20260113172258-7705', '2026-01-13 17:22:58', 'course', '2026-01-20', '2026-01-13 16:18:26'),
(5, 'GETSOLE-FHEU', 11, 'learnexus-11', 13, 12.00, 'percent', 0, NULL, NULL, 'course', '2026-01-21', '2026-01-14 02:41:23'),
(6, 'GETSOLE-P336', 11, 'learnexus-11', 14, 12.00, 'percent', 0, NULL, NULL, 'course', '2026-01-21', '2026-01-14 11:27:01'),
(7, 'GETSOLE-2LZ6', 11, 'learnexus-11', 15, 12.00, 'percent', 0, NULL, NULL, 'course', '2026-01-21', '2026-01-14 12:48:35'),
(8, 'GETSOLE-AK9P', 11, 'learnexus-11', 16, 12.00, 'percent', 0, NULL, NULL, 'course', '2026-01-21', '2026-01-14 13:02:36');

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
  MODIFY `certificateID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `courseID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `emailotp`
--
ALTER TABLE `emailotp`
  MODIFY `emailOtpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `lessoncompletion`
--
ALTER TABLE `lessoncompletion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `lessonID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `quizanswers`
--
ALTER TABLE `quizanswers`
  MODIFY `answerID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `quizquestions`
--
ALTER TABLE `quizquestions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `quizresults`
--
ALTER TABLE `quizresults`
  MODIFY `resultID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quizID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `smsotp`
--
ALTER TABLE `smsotp`
  MODIFY `otpID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `voucherID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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

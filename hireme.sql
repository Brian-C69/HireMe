-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2025 at 04:18 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hireme`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('SuperAdmin','Support','Verifier','Finance') NOT NULL DEFAULT 'Support',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `profile_photo` text DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `status` enum('Active','Suspended','Deleted') NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `full_name`, `email`, `password_hash`, `role`, `permissions`, `profile_photo`, `last_login_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Site Admin', 'admin@hireme.local', '$2y$10$zUO7gd3sE8TxLA4yTHSMtOFHpf8Y2FE/FNlMH5461Rl7QjaniVyea', 'Support', NULL, NULL, NULL, 'Active', '2025-09-13 00:04:49', '2025-09-13 00:04:49'),
(2, 'Super Admin', 'admin@example.com', '$2y$10$RKN8s.cGbQeqhRGX7enCEegVhd3oIJkCshm/ZWMxwYhT3PZ3CcZ4.', 'SuperAdmin', NULL, NULL, NULL, 'Active', '2025-09-13 09:40:19', '2025-09-13 09:40:19');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `applicant_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `job_posting_id` int(11) NOT NULL,
  `application_date` datetime NOT NULL DEFAULT current_timestamp(),
  `application_status` varchar(50) NOT NULL DEFAULT 'Applied',
  `resume_url` varchar(500) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`applicant_id`, `candidate_id`, `job_posting_id`, `application_date`, `application_status`, `resume_url`, `cover_letter`, `notes`, `updated_at`) VALUES
(1, 1, 2, '2025-09-08 17:50:28', 'Interview', NULL, NULL, NULL, '2025-09-11 00:35:31'),
(2, 1, 1, '2025-09-08 18:04:46', 'Withdrawn', NULL, NULL, NULL, '2025-09-09 13:08:33'),
(3, 1, 6, '2025-09-09 13:43:13', 'Applied', NULL, NULL, NULL, '2025-09-09 13:43:13'),
(4, 1, 4, '2025-09-09 13:43:26', 'Applied', NULL, NULL, NULL, '2025-09-09 13:43:26'),
(5, 1, 3, '2025-09-09 13:47:33', 'Applied', NULL, NULL, NULL, '2025-09-09 13:47:33'),
(6, 4, 8, '2025-09-09 14:26:21', 'Interview', NULL, NULL, NULL, '2025-09-13 22:04:22'),
(7, 3, 8, '2025-09-09 14:28:21', 'Rejected', NULL, NULL, NULL, '2025-09-13 22:04:42'),
(8, 5, 14, '2025-09-10 17:37:16', 'Applied', NULL, NULL, NULL, '2025-09-10 17:37:16'),
(9, 5, 12, '2025-09-10 17:37:30', 'Applied', NULL, NULL, NULL, '2025-09-10 17:37:30'),
(10, 1, 17, '2025-09-11 11:53:01', 'Rejected', NULL, NULL, NULL, '2025-09-11 11:54:59'),
(11, 4, 14, '2025-09-13 22:12:45', 'Applied', NULL, NULL, NULL, '2025-09-13 22:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `application_answers`
--

CREATE TABLE `application_answers` (
  `answer_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_text` text NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_answers`
--

INSERT INTO `application_answers` (`answer_id`, `application_id`, `question_id`, `answer_text`, `created_at`) VALUES
(1, 1, 2, 'nope', '2025-09-08 17:50:28'),
(2, 1, 3, 'Heck yes', '2025-09-08 17:50:28'),
(3, 1, 8, 'nope, i suck', '2025-09-08 17:50:28'),
(7, 3, 4, 'asd', '2025-09-09 13:43:13'),
(8, 3, 6, 'asd', '2025-09-09 13:43:13'),
(9, 3, 7, 'asd', '2025-09-09 13:43:13'),
(10, 4, 1, 'asd', '2025-09-09 13:43:26'),
(11, 4, 4, 'asd', '2025-09-09 13:43:26'),
(12, 4, 6, 'asd', '2025-09-09 13:43:26'),
(16, 5, 1, 'asd', '2025-09-09 13:47:33'),
(17, 5, 2, 'asd', '2025-09-09 13:47:33'),
(18, 5, 8, 'asd', '2025-09-09 13:47:33'),
(19, 6, 1, 'ad', '2025-09-09 14:26:21'),
(20, 6, 3, 'as', '2025-09-09 14:26:21'),
(21, 6, 5, 'asdf', '2025-09-09 14:26:21'),
(22, 7, 1, 'asdasd', '2025-09-09 14:28:21'),
(23, 7, 3, 'asdasdas', '2025-09-09 14:28:21'),
(24, 7, 5, 'sda', '2025-09-09 14:28:21'),
(25, 8, 2, 'asd', '2025-09-10 17:37:16'),
(26, 8, 3, 'asd', '2025-09-10 17:37:16'),
(27, 8, 6, 'asd', '2025-09-10 17:37:16'),
(28, 9, 2, 'asd', '2025-09-10 17:37:30'),
(29, 9, 3, 'asd', '2025-09-10 17:37:30'),
(30, 9, 8, 'asd', '2025-09-10 17:37:30'),
(31, 10, 1, 'asdf', '2025-09-11 11:53:01'),
(32, 10, 3, 'asdf', '2025-09-11 11:53:01'),
(33, 10, 5, 'asdf', '2025-09-11 11:53:01'),
(34, 11, 2, 'He\'ll be happier than ever', '2025-09-13 22:12:45'),
(35, 11, 3, 'Money can\'t buy happiness, but without money, you can\'t even feel happy', '2025-09-13 22:12:45'),
(36, 11, 6, 'You think I\'m applying this job cause I feel happy with my employer?', '2025-09-13 22:12:45');

-- --------------------------------------------------------

--
-- Table structure for table `application_status_history`
--

CREATE TABLE `application_status_history` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by_type` enum('Candidate','Employer','Recruiter','Admin','System') NOT NULL,
  `changed_by_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_status_history`
--

INSERT INTO `application_status_history` (`id`, `application_id`, `old_status`, `new_status`, `changed_by_type`, `changed_by_id`, `note`, `created_at`) VALUES
(1, 3, NULL, 'Applied', 'Candidate', 1, NULL, '2025-09-09 13:32:10'),
(2, 3, 'Applied', 'Withdrawn', 'Candidate', 1, NULL, '2025-09-09 13:32:17'),
(3, 3, 'Withdrawn', 'Applied', 'Candidate', 1, 'Re-applied after Withdrawn', '2025-09-09 13:43:13'),
(4, 4, NULL, 'Applied', 'Candidate', 1, NULL, '2025-09-09 13:43:26'),
(5, 5, NULL, 'Applied', 'Candidate', 1, NULL, '2025-09-09 13:43:39'),
(6, 5, 'Applied', 'Withdrawn', 'Candidate', 1, NULL, '2025-09-09 13:43:41'),
(7, 5, 'Withdrawn', 'Applied', 'Candidate', 1, 'Re-applied after Withdrawn', '2025-09-09 13:47:33'),
(8, 6, NULL, 'Applied', 'Candidate', 4, NULL, '2025-09-09 14:26:21'),
(9, 7, NULL, 'Applied', 'Candidate', 3, NULL, '2025-09-09 14:28:21'),
(10, 8, NULL, 'Applied', 'Candidate', 5, NULL, '2025-09-10 17:37:16'),
(11, 9, NULL, 'Applied', 'Candidate', 5, NULL, '2025-09-10 17:37:30'),
(12, 1, 'Applied', 'Interview', 'Employer', 1, NULL, '2025-09-11 00:35:31'),
(13, 10, NULL, 'Applied', 'Candidate', 1, NULL, '2025-09-11 11:53:01'),
(14, 10, 'Applied', 'Rejected', 'Employer', 3, NULL, '2025-09-11 11:54:59'),
(15, 6, 'Applied', 'Interview', 'Employer', 6, NULL, '2025-09-13 22:04:26'),
(16, 7, 'Applied', 'Rejected', 'Employer', 6, NULL, '2025-09-13 22:04:45'),
(17, 11, NULL, 'Applied', 'Candidate', 4, NULL, '2025-09-13 22:12:48');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `billing_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `transaction_type` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `reference_number` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`billing_id`, `user_id`, `user_type`, `transaction_type`, `amount`, `payment_method`, `transaction_date`, `status`, `reference_number`, `created_at`, `updated_at`) VALUES
(1, 1, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-09 14:41:26', 'Completed', 'PM-B91781483E03', '2025-09-09 14:41:26', '2025-09-09 14:41:26'),
(2, 1, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-09 15:28:00', 'Completed', 'PM-904B56BF7B6D', '2025-09-09 15:28:00', '2025-09-09 15:28:00'),
(3, 1, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-09 15:35:03', 'Completed', 'PM-53BA0A1FA76D', '2025-09-09 15:35:03', '2025-09-09 15:35:03'),
(4, 1, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-09 15:35:09', 'Completed', 'PM-547CEB7CE0BB', '2025-09-09 15:35:09', '2025-09-09 15:35:09'),
(5, 1, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-09 20:59:00', 'Completed', 'PM-CC759D297BE0', '2025-09-09 20:59:00', '2025-09-09 20:59:00'),
(6, 1, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-10 14:38:27', 'Completed', 'PM-57577E6E220B', '2025-09-10 14:38:27', '2025-09-10 14:38:27'),
(7, 5, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-10 17:12:57', 'Completed', 'PM-32A0927F43D8', '2025-09-10 17:12:57', '2025-09-10 17:12:57'),
(8, 5, 'Candidate', 'Premium Badge Purchase', '50.00', 'Test', '2025-09-10 17:39:48', 'Completed', 'PM-3A6DCED82E99', '2025-09-10 17:39:48', '2025-09-10 17:39:48'),
(9, 1, 'Employer', 'Credits Purchase', '5.00', 'Test', '2025-09-11 07:30:10', 'Completed', 'CR-6BF47A138703', '2025-09-11 07:30:10', '2025-09-11 07:30:10'),
(10, 1, 'Recruiter', 'Credits Purchase', '5.00', 'Test', '2025-09-11 08:10:12', 'Completed', 'CR-91BB68AED89A', '2025-09-11 08:10:12', '2025-09-11 08:10:12'),
(11, 1, 'Recruiter', 'Credits Purchase', '5.00', 'Test', '2025-09-11 08:10:15', 'Completed', 'CR-66C4F123E66F', '2025-09-11 08:10:15', '2025-09-11 08:10:15'),
(12, 3, 'Employer', 'Credits Purchase', '5.00', 'Test', '2025-09-11 11:50:58', 'Completed', 'CR-9A0FD25006C2', '2025-09-11 11:50:58', '2025-09-11 11:50:58'),
(13, 1, 'Employer', 'Credits Purchase', '5.00', 'Test', '2025-09-12 23:24:57', 'Completed', 'CR-4927EF442943', '2025-09-12 23:24:57', '2025-09-12 23:24:57');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `candidate_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `expected_salary` decimal(10,2) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notice_period` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `profile_picture_url` varchar(500) DEFAULT NULL,
  `resume_url` varchar(500) DEFAULT NULL,
  `verified_status` tinyint(1) NOT NULL DEFAULT 0,
  `verification_date` datetime DEFAULT NULL,
  `verification_doc_type` varchar(50) DEFAULT NULL,
  `verification_doc_url` varchar(500) DEFAULT NULL,
  `premium_badge` tinyint(1) NOT NULL DEFAULT 0,
  `premium_badge_date` datetime DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verification_state` enum('Pending','Approved','Rejected') DEFAULT NULL,
  `verification_review_notes` text DEFAULT NULL,
  `verification_reviewed_at` datetime DEFAULT NULL,
  `verification_reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`candidate_id`, `full_name`, `email`, `password_hash`, `phone_number`, `expected_salary`, `linkedin_url`, `location`, `notice_period`, `date_of_birth`, `address`, `city`, `state`, `postal_code`, `country`, `profile_picture_url`, `resume_url`, `verified_status`, `verification_date`, `verification_doc_type`, `verification_doc_url`, `premium_badge`, `premium_badge_date`, `skills`, `experience_years`, `education_level`, `created_at`, `updated_at`, `verification_state`, `verification_review_notes`, `verification_reviewed_at`, `verification_reviewed_by`) VALUES
(1, 'Bernard Choong', 'bchoong1@gmail.com', '$2y$10$ljER33uDzK8102WfmEgib.rnOo17e79CI7iCYAWW5IBOAN1itK5Za', '+60123095550', '4500.00', NULL, 'Kuala Lumpur, Malaysia.', '1 MONTH', '2002-04-19', '27, Jalan Margosa SD10/2', 'Bandar Sri Damansara', 'Kuala Lumpur', '52200', 'Malaysia', '/assets/uploads/profiles/cand_1_1757288228.png', '/assets/uploads/resumes/resume_1_1757921487.pdf', 1, '2025-09-09 15:07:45', 'IC', '/assets/uploads/kyc/kyc_1_1757401665.jpg', 1, '2025-09-15 15:47:23', 'PHP', 3, NULL, '2025-09-08 06:32:25', '2025-09-15 15:47:23', 'Approved', NULL, '2025-09-13 10:36:58', 2),
(2, 'Ong Zhi Chen', 'zchenong@outlook.com', '$2y$10$BwL9xMkaVNFJvM1tQPvw4OaKtv96XLBEWJbyLRsRTJDqb3QyBuS7q', '0146211740', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Malaysia', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-09-08 18:11:21', '2025-09-08 18:11:21', NULL, NULL, NULL, NULL),
(3, 'Bryan KFW', 'bryankfw-wm19@student.tarc.edu.my', '$2y$10$mqb.JeeON13cJNCq5U425.hFoSS4YwhsSx5DhC67UrbrCnwF8bKSG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Malaysia', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-09-09 14:04:22', '2025-09-09 14:13:35', NULL, NULL, NULL, NULL),
(4, 'Bryan Kok Fong Wen', 'bryankokfongwen@gmail.com', '$2y$10$GfbbV0ITktwEhK/S4Y805eE5vonNyx8fEXGNP76V3Le6KgcYMCIC2', '01163306232', '4000.00', NULL, 'Kuala Lumpur', NULL, '2001-03-03', 'BLK-H 317 JALAN PJU 10/1 APARTMENT PERMAI DAMANSARA DAMAI 47830', 'Petaling Jaya', 'Selangor', '47830', 'Malaysia', '/assets/uploads/profiles/cand_4_1757772009.jpg', '/assets/uploads/resumes/resume_4_1757772136.pdf', 1, '2025-09-13 21:51:30', 'IC', '/assets/uploads/kyc/kyc_4_1757771490.png', 1, NULL, 'PHP,MySQL,C,C#,HTML,CSS,Java,Cloud Computing', NULL, 'Diploma', '2025-09-09 14:10:37', '2025-09-13 22:03:04', 'Approved', NULL, '2025-09-13 21:56:50', 2),
(5, 'John Doeve', 'bchoong1+1@gmail.com', '$2y$10$OGEAAqYbn.QVu31jqQ5J0uCEB5FQP/PrbAOUdKmif2JEUD.Cpiiry', '+60123456789', '4500.00', NULL, 'Kuala Lumpur, Malaysia.', '2 WEEK', '2002-01-01', NULL, NULL, NULL, NULL, 'Malaysia', '/assets/uploads/profiles/cand_5_1757495006.jpg', NULL, 1, '2025-09-13 03:22:44', 'IC', '/assets/uploads/kyc/kyc_5_1757704964.jpg', 1, '2025-09-10 17:39:48', NULL, NULL, NULL, '2025-09-10 16:53:40', '2025-09-13 03:42:50', 'Approved', NULL, '2025-09-13 03:42:50', NULL),
(6, 'Christian Lau Zi Xian', 'christianlz-sm22@student.tarc.edu.my', '$2y$10$Yc/yJDQjVNT6DG1IhMtrTucK1ZDAdC.1pzLPIWIm1ZmjJOOM1Vdsq', '1234567890', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Malaysia', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-09-15 22:11:35', '2025-09-15 22:11:35', NULL, NULL, NULL, NULL),
(7, 'Christian Lau Zi Xian', 'lauzixian1226@gmail.com', '$2y$10$HsIvyzQYgcR677dSuvI/3e/hTj0TClJMM4aicYC4iDI1QWRtIh5LG', '1234567890', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Malaysia', NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-09-15 22:15:59', '2025-09-15 22:15:59', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `candidate_education`
--

CREATE TABLE `candidate_education` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `qualification` enum('SPM','Diploma','Degree','Master','Prof Quali') NOT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `field` varchar(255) DEFAULT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_education`
--

INSERT INTO `candidate_education` (`id`, `candidate_id`, `qualification`, `institution`, `field`, `graduation_year`, `details`, `created_at`, `updated_at`) VALUES
(4, 4, 'Degree', 'TAR UMT', 'IT', 2026, NULL, '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(7, 1, 'Diploma', 'Tunku Abdul Rahman University of Management and Technology', 'Information Technology', 2022, 'Focused on the fundamentals of computer science and information technology, including programming, database management, networking, and system analysis. Gained practical skills in software development, IT infrastructure, and troubleshooting, along with a strong foundation in problem-solving and critical thinking for technology-driven environments.', '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(11, 5, 'Degree', 'Universiti Kebangsaan Malaysia', 'Software Engineering', 2022, 'This course introduces the principles, methods, and tools for designing and developing reliable, efficient, and maintainable software systems. Students will explore the software development lifecycle, including requirements analysis, system design, implementation, testing, deployment, and maintenance. Emphasis is placed on project management, teamwork, software quality assurance, and the use of modern development methodologies such as Agile and DevOps.', '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(12, 5, 'Diploma', 'University Malaya', 'Information Technology', 2019, 'This course provides students with a broad foundation in the principles and practices of information technology. It covers the use, design, and management of computer systems, networks, databases, and software applications that support business, research, and communication. Students will explore both technical skills and organizational considerations, preparing them to solve real-world IT challenges.', '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(13, 5, 'SPM', 'SMK Kuala Lumpur', 'Science', 2017, 'The Science Stream is one of the main academic pathways offered to upper secondary school students in Malaysia (Form 4–5) as they prepare for the SPM examination. It is designed for students with strong interest and ability in science and mathematics. This stream provides the foundation for pursuing tertiary studies in fields such as medicine, engineering, information technology, pure sciences, and other technical disciplines.', '2025-09-10 17:04:44', '2025-09-10 17:04:44');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_experiences`
--

CREATE TABLE `candidate_experiences` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_experiences`
--

INSERT INTO `candidate_experiences` (`id`, `candidate_id`, `company`, `job_title`, `start_date`, `end_date`, `description`, `created_at`, `updated_at`) VALUES
(9, 1, 'GA2 Medical Sdn Bhd', 'IT Manager', '2025-02-01', '2025-02-28', 'As the IT Manager at GA2 Medical, I am responsible for overseeing and managing all aspects of the company\'s information technology systems to ensure they align with business goals and objectives.', '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(10, 1, 'Cheematrade Sdn Bhd', 'Head of Information Technology', '2025-01-01', '2025-01-31', 'As the Head of IT at Cheematrade Sdn Bhd, I am responsible for shaping and executing the company’s technology vision, ensuring that IT systems and strategies align seamlessly with business objectives. I lead the design, implementation, and management of secure and efficient IT infrastructures, encompassing network systems, enterprise software, and data management solutions.', '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(14, 5, 'Telekom Malaysia', 'Software Engineer', '2024-01-01', NULL, 'Designs, develops, tests, and maintains software applications. Collaborates with cross-functional teams to deliver high-quality, scalable solutions. Writes clean, efficient code and ensures applications meet performance and security standards.', '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(15, 5, 'Maxis Sdn Bhd', 'Human Resources Manager', '2023-01-01', '2023-12-31', 'Oversees recruitment, onboarding, training, and employee relations. Develops HR policies, manages performance reviews, and ensures compliance with labor laws. Acts as a bridge between management and staff to support organizational growth.', '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(16, 5, 'Digi Sdn Bhd', 'Project Manager', '2022-01-01', '2022-12-31', 'Plans, executes, and closes projects according to deadlines and budgets. Coordinates with stakeholders, allocates resources, manages risks, and ensures deliverables align with business objectives.', '2025-09-10 17:04:44', '2025-09-10 17:04:44');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_languages`
--

CREATE TABLE `candidate_languages` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `language` varchar(50) NOT NULL,
  `spoken_level` enum('Basic','Intermediate','Fluent','Native') NOT NULL DEFAULT 'Basic',
  `written_level` enum('Basic','Intermediate','Fluent','Native') NOT NULL DEFAULT 'Basic',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_languages`
--

INSERT INTO `candidate_languages` (`id`, `candidate_id`, `language`, `spoken_level`, `written_level`, `created_at`, `updated_at`) VALUES
(5, 4, 'English', 'Basic', 'Basic', '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(6, 4, 'Malay', 'Basic', 'Basic', '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(7, 4, 'Mandarin', 'Fluent', 'Fluent', '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(12, 1, 'English', 'Fluent', 'Intermediate', '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(13, 1, 'Malay', 'Fluent', 'Basic', '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(16, 5, 'English', 'Intermediate', 'Intermediate', '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(17, 5, 'Malay', 'Fluent', 'Fluent', '2025-09-10 17:04:44', '2025-09-10 17:04:44');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_skills`
--

CREATE TABLE `candidate_skills` (
  `id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_skills`
--

INSERT INTO `candidate_skills` (`id`, `candidate_id`, `name`, `level`, `created_at`, `updated_at`) VALUES
(7, 4, 'PHP', 75, '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(8, 4, 'HTML', 100, '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(9, 4, 'CSS', 50, '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(10, 4, 'Leadership', 80, '2025-09-09 14:25:19', '2025-09-09 14:25:19'),
(17, 1, 'HTML', 100, '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(18, 1, 'Laravel', 50, '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(19, 1, 'PHP', 25, '2025-09-10 16:51:33', '2025-09-10 16:51:33'),
(24, 5, 'HTML', 100, '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(25, 5, 'AWS', 80, '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(26, 5, 'JAVASCRIPT', 60, '2025-09-10 17:04:44', '2025-09-10 17:04:44'),
(27, 5, 'LARAVEL', 50, '2025-09-10 17:04:44', '2025-09-10 17:04:44');

-- --------------------------------------------------------

--
-- Table structure for table `contact_form`
--

CREATE TABLE `contact_form` (
  `contact_id` int(11) NOT NULL,
  `contact_firstname` varchar(255) NOT NULL,
  `contact_lastname` varchar(255) NOT NULL,
  `contact_email` varchar(255) NOT NULL,
  `contact_message` text NOT NULL,
  `contact_ip_address` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credits_ledger`
--

CREATE TABLE `credits_ledger` (
  `id` int(11) NOT NULL,
  `user_role` enum('Employer','Recruiter') NOT NULL,
  `user_id` int(11) NOT NULL,
  `delta` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `credits_ledger`
--

INSERT INTO `credits_ledger` (`id`, `user_role`, `user_id`, `delta`, `reason`, `admin_id`, `created_at`) VALUES
(1, 'Employer', 6, 100, NULL, 2, '2025-09-13 21:57:22');

-- --------------------------------------------------------

--
-- Table structure for table `employers`
--

CREATE TABLE `employers` (
  `employer_id` int(11) NOT NULL,
  `is_client_company` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_recruiter_id` int(11) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `contact_person_name` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `company_logo` text DEFAULT NULL,
  `company_description` text DEFAULT NULL,
  `credits_balance` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employers`
--

INSERT INTO `employers` (`employer_id`, `is_client_company`, `created_by_recruiter_id`, `company_name`, `email`, `password_hash`, `industry`, `location`, `contact_person_name`, `contact_number`, `company_logo`, `company_description`, `credits_balance`, `created_at`, `updated_at`) VALUES
(1, 0, NULL, 'GA2 Medical Sdn Bhd', 'bernard.cheematrade@gmail.com', '$2y$10$22G57oE/eAmeuT398hYTfub0.LthIfvuOCfBJ65A55fGhjH3feLJG', 'Medical', 'Semenyih, Selangor', 'Manpreet Kaur', '+60123095550', '/assets/uploads/companies/company_1_1757314378.png', 'Yeet', 17, '2025-09-08 14:01:05', '2025-09-15 19:31:17'),
(2, 0, NULL, 'Zealotech solution (M) sdn bhd', 'jason.ong@zealotechsolution.com', '$2y$10$ejD0UpecH46gY8Wh1KKCvOabwuFkA3xsieaBookm/j7JAyBdcQc4u', NULL, NULL, NULL, '0146211740', NULL, NULL, 0, '2025-09-08 18:13:36', '2025-09-08 18:13:36'),
(3, 0, NULL, 'NTC Sdn Bhd', 'ntc@gmail.com', '$2y$10$6wTyq9HtJ9f/kuyr4DUxaupZHi6KhTupJsFVIvop.T1y74cIKhFq6', NULL, 'Kuala Lumpur', NULL, '+1234567890', '/assets/uploads/companies/company_3_1757562866.jpg', NULL, 8, '2025-09-08 20:13:48', '2025-09-15 22:06:19'),
(4, 0, NULL, 'GA2 Wellness Sdn Bhd', 'info@ga2wellness.com', '$2y$10$G254MgDaGZrIN5YddWBd7euRti0satFvF928z/Nd/bHn3zBSzWIoO', NULL, 'Kuala Lumpur, Malaysia.', NULL, '+60123095550', '/assets/uploads/companies/company_4_1757388159.png', NULL, 0, '2025-09-08 21:06:23', '2025-09-09 11:22:39'),
(5, 0, NULL, 'ABC', '1@gmail.com', '$2y$10$1GYB7dxKnyzpsERsg55oLOcNApHrRs/eGoMWpm.D/CZ/D/9krJeh6', NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-09-09 00:48:58', '2025-09-09 00:48:58'),
(6, 0, NULL, 'TM', 'test@gmail.com', '$2y$10$n/lzZeiR.O1QeewuOtO32uOrhioIvJpNIQUSkzw6kK.55gkwiGUPO', 'Network', 'Kuala Lumpur', NULL, NULL, '/assets/uploads/companies/company_6_1757397795.png', NULL, 99, '2025-09-09 00:51:44', '2025-09-13 22:04:07'),
(7, 1, 1, 'Maybank Berhad', '', '', 'Banking', 'Kuala Lumpur, Malaysia.', 'Mr Ali', '+60123456789', '/assets/uploads/companies/clientco_1_1757550627.png', NULL, 0, '2025-09-11 08:30:27', '2025-09-11 08:30:27'),
(9, 1, 1, 'Public Bank Berhad', NULL, '', 'Banking', 'Kuala Lumpur, Malaysia.', 'Mr Wong', '+60123456789', '/assets/uploads/companies/clientco_1_1757551092.png', NULL, 0, '2025-09-11 08:38:12', '2025-09-11 08:38:12'),
(10, 1, 1, 'CIMB Bank Berhad', NULL, '', 'Banking', 'Kuala Lumpur, Malaysia.', 'Mr Rahman', '0107887682', '/assets/uploads/companies/clientco_1_1757551130.png', NULL, 0, '2025-09-11 08:38:50', '2025-09-11 08:38:50'),
(11, 1, 1, 'Tenaga Nasional Berhad', NULL, '', 'Power', 'Klang, Selangor, Malaysia', 'Mr Fizal', '0107887682', '/assets/uploads/companies/clientco_1_1757551160.png', NULL, 0, '2025-09-11 08:39:20', '2025-09-11 08:39:20');

-- --------------------------------------------------------

--
-- Table structure for table `job_micro_questions`
--

CREATE TABLE `job_micro_questions` (
  `job_posting_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_micro_questions`
--

INSERT INTO `job_micro_questions` (`job_posting_id`, `question_id`) VALUES
(2, 2),
(2, 3),
(2, 8),
(3, 1),
(3, 2),
(3, 8),
(4, 1),
(4, 4),
(4, 6),
(5, 1),
(5, 4),
(5, 5),
(6, 4),
(6, 6),
(6, 7),
(7, 1),
(7, 2),
(7, 4),
(8, 1),
(8, 3),
(8, 5),
(9, 1),
(9, 2),
(9, 4),
(10, 1),
(10, 5),
(10, 6),
(11, 1),
(11, 4),
(11, 7),
(12, 2),
(12, 3),
(12, 8),
(13, 1),
(13, 2),
(13, 3),
(14, 2),
(14, 3),
(14, 6),
(15, 4),
(15, 6),
(15, 7),
(16, 1),
(16, 4),
(16, 7),
(17, 1),
(17, 3),
(17, 5);

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `job_posting_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `recruiter_id` int(11) DEFAULT NULL,
  `job_title` varchar(255) NOT NULL,
  `job_description` text NOT NULL,
  `job_requirements` text DEFAULT NULL,
  `job_location` varchar(255) DEFAULT NULL,
  `job_languages` varchar(255) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT NULL,
  `salary_range_min` decimal(10,2) DEFAULT NULL,
  `salary_range_max` decimal(10,2) DEFAULT NULL,
  `application_deadline` date DEFAULT NULL,
  `date_posted` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Open',
  `number_of_positions` int(11) NOT NULL DEFAULT 1,
  `required_experience` varchar(100) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_postings`
--

INSERT INTO `job_postings` (`job_posting_id`, `company_id`, `recruiter_id`, `job_title`, `job_description`, `job_requirements`, `job_location`, `job_languages`, `employment_type`, `salary_range_min`, `salary_range_max`, `application_deadline`, `date_posted`, `status`, `number_of_positions`, `required_experience`, `education_level`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Marketing Intern', 'yes\r\nyes\r\nyes', NULL, 'Klang', 'English, Malay', 'Internship', '1500.00', NULL, NULL, '2025-09-08 14:21:08', 'Open', 1, NULL, NULL, '2025-09-08 14:21:08', '2025-09-08 14:21:08'),
(2, 1, NULL, 'Accountant', 'Stonks', NULL, 'Klang', 'English', 'Full-time', '6500.00', NULL, NULL, '2025-09-08 16:34:41', 'Open', 1, NULL, NULL, '2025-09-08 16:34:41', '2025-09-08 17:50:00'),
(3, 4, NULL, 'Accountant', 'test', NULL, 'Setapak', 'Malay', 'Full-time', '2000.00', NULL, NULL, '2025-09-09 00:29:21', 'Open', 1, NULL, NULL, '2025-09-09 00:29:21', '2025-09-09 00:29:21'),
(4, 2, NULL, 'Network engineers', 'Handle customer network and company network.\r\nConfigure network', NULL, 'Puchong', 'English, Malay, Chinese', 'Full-time', '5000.00', NULL, NULL, '2025-09-09 00:43:08', 'Open', 1, NULL, NULL, '2025-09-09 00:43:08', '2025-09-09 00:43:08'),
(5, 6, NULL, 'Test', 'Test', NULL, NULL, NULL, 'Part-time', NULL, NULL, NULL, '2025-09-09 00:52:58', 'Deleted', 1, NULL, NULL, '2025-09-09 00:52:58', '2025-09-09 14:54:20'),
(6, 5, NULL, 'promoter', 'Good social skill', NULL, 'Sabah', 'Mandarin', 'Part-time', '1200.00', NULL, NULL, '2025-09-09 01:24:04', 'Open', 1, NULL, NULL, '2025-09-09 01:24:04', '2025-09-09 01:24:04'),
(7, 6, NULL, 'Backend Developer', 'Develop app', NULL, 'Petaling Jaya', 'English,Malay,Mandarin', 'Full-time', '3500.00', NULL, NULL, '2025-09-09 14:00:28', 'Open', 1, NULL, NULL, '2025-09-09 14:00:28', '2025-09-09 14:52:03'),
(8, 6, NULL, 'Project Manager', 'Manage project, consult with client', NULL, 'Kuala Lumpur', 'English,Malay,Mandarin', 'Contract', '5000.00', NULL, NULL, '2025-09-09 14:19:41', 'Open', 1, NULL, NULL, '2025-09-09 14:19:41', '2025-09-09 14:19:41'),
(9, 6, NULL, 'Intern', 'Internship offer to university student', NULL, 'Kuala Lumpur', 'English,Malay,Mandarin', 'Internship', '1500.00', NULL, NULL, '2025-09-09 14:50:41', 'Open', 1, NULL, NULL, '2025-09-09 14:50:41', '2025-09-09 14:52:24'),
(10, 1, NULL, 'Software Engineer', 'Designs, develops, tests, and maintains software applications or systems. Collaborates with cross-functional teams to solve technical problems and improve software performance.', NULL, 'Kuala Lumpur', 'English, Malay', 'Full-time', '8000.00', NULL, NULL, '2025-09-09 14:54:20', 'Open', 1, NULL, NULL, '2025-09-09 14:54:20', '2025-09-09 14:54:20'),
(11, 1, NULL, 'Marketing Manager', 'Develops and implements marketing strategies to promote products or services. Oversees campaigns, analyzes market trends, manages budgets, and coordinates with sales teams.', NULL, 'Kuala Lumpur', 'English, Malay', 'Full-time', '5000.00', NULL, NULL, '2025-09-09 14:54:45', 'Open', 1, NULL, NULL, '2025-09-09 14:54:45', '2025-09-09 14:54:45'),
(12, 1, NULL, 'Human Resources Specialist', 'Manages recruitment, onboarding, employee relations, and benefits administration. Ensures compliance with labor laws and supports employee development programs.', NULL, 'Kuala Lumpur', 'English, Malay', 'Full-time', '4000.00', NULL, NULL, '2025-09-09 14:55:10', 'Open', 1, NULL, NULL, '2025-09-09 14:55:10', '2025-09-09 14:55:10'),
(13, 1, NULL, 'Financial Analyst', 'Analyzes financial data, creates reports, forecasts trends, and provides insights to guide business decisions. Assists in budgeting and investment planning.', NULL, 'Kuala Lumpur', 'English, Malay', 'Full-time', '6000.00', NULL, NULL, '2025-09-09 14:55:28', 'Open', 1, NULL, NULL, '2025-09-09 14:55:28', '2025-09-09 14:55:28'),
(14, 1, NULL, 'Customer Support Rep', 'Provides assistance and solutions to customer inquiries via phone, email, or chat. Handles complaints, processes orders, and maintains customer satisfaction.', NULL, 'Kuala Lumpur', 'English, Malay', 'Full-time', '8000.00', NULL, NULL, '2025-09-09 14:56:16', 'Open', 1, NULL, NULL, '2025-09-09 14:56:16', '2025-09-09 15:04:56'),
(15, 6, NULL, 'Network Engineer', 'Manage network', NULL, 'Kota Damansara', 'English,Malay,Mandarin', 'Full-time', '5000.00', NULL, NULL, '2025-09-09 15:14:28', 'Open', 1, NULL, NULL, '2025-09-09 15:14:28', '2025-09-09 15:14:28'),
(16, 10, 1, 'Front Desk', 'talk to customers', NULL, 'Kuala Lumpur', 'Malay, English', 'Full-time', '3500.00', NULL, NULL, '2025-09-11 08:43:18', 'Open', 1, NULL, NULL, '2025-09-11 08:43:18', '2025-09-11 08:43:18'),
(17, 3, NULL, '3 Stack Dev', 'I expected RM 30000 worth of work with RM2000 salary.', NULL, 'Kuala Lumpur', 'ABC', 'Full-time', '9000.00', NULL, NULL, '2025-09-11 11:52:12', 'Open', 1, NULL, NULL, '2025-09-11 11:52:12', '2025-09-11 11:52:12');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_attempt_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip_address`, `attempts`, `last_attempt_at`) VALUES
(4, 'ntc@gmai.com', '103.130.13.166', 1, '2025-09-11 11:49:46'),
(5, 'abc@gmail.com', '118.101.203.94', 1, '2025-09-15 18:29:11');

-- --------------------------------------------------------

--
-- Table structure for table `micro_questions`
--

CREATE TABLE `micro_questions` (
  `id` int(11) NOT NULL,
  `prompt` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `micro_questions`
--

INSERT INTO `micro_questions` (`id`, `prompt`, `active`) VALUES
(1, 'What can you add to this company?', 1),
(2, 'Will your boss be disappointed when you leave?', 1),
(3, 'Is money the driving force in your career?', 1),
(4, 'Do you work to live or live to work?', 1),
(5, 'Is promotion a driving force in your career?', 1),
(6, 'Are you happy with your existing employer?', 1),
(7, 'Will your current boss offer you more money?', 1),
(8, 'Do you think you are a good \"Man-manager\"?', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_type` enum('Candidate','Employer','Recruiter') DEFAULT NULL,
  `token_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `user_type`, `token_hash`, `created_at`, `expires_at`, `used_at`) VALUES
(9, 'christianlz-sm22@student.tarc.edu.my', 'Candidate', '535e40d1802d80b23773a6b9f68226fca4ae9e20caab187d77c2c7c608e687f6', '2025-09-15 22:14:28', '2025-09-15 23:14:28', NULL),
(10, 'lauzixian1226@gmail.com', 'Candidate', 'ca4ce01fccf6b4949757e72b02af17f47e8f525d39d092bf6f97b65efcb182d0', '2025-09-15 22:17:11', '2025-09-15 23:17:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `user_type` enum('Candidate','Employer','Recruiter') NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `purpose` enum('Premium Badge','Resume Credits','Subscription') NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `transaction_status` enum('Success','Failed','Pending') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `user_type`, `user_id`, `amount`, `purpose`, `payment_method`, `transaction_status`, `transaction_id`, `created_at`) VALUES
(1, 'Candidate', 1, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-B91781483E03', '2025-09-09 14:41:26'),
(2, 'Candidate', 1, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-904B56BF7B6D', '2025-09-09 15:28:00'),
(3, 'Candidate', 1, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-53BA0A1FA76D', '2025-09-09 15:35:03'),
(4, 'Candidate', 1, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-547CEB7CE0BB', '2025-09-09 15:35:09'),
(5, 'Candidate', 1, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-CC759D297BE0', '2025-09-09 20:59:00'),
(6, 'Candidate', 1, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-57577E6E220B', '2025-09-10 14:38:27'),
(7, 'Candidate', 5, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-32A0927F43D8', '2025-09-10 17:12:57'),
(8, 'Candidate', 5, '50.00', 'Premium Badge', 'Test', 'Success', 'PM-3A6DCED82E99', '2025-09-10 17:39:48'),
(9, 'Employer', 1, '5.00', '', 'Test', 'Success', 'CR-6BF47A138703', '2025-09-11 07:30:10'),
(10, 'Recruiter', 1, '5.00', '', 'Test', 'Success', 'CR-91BB68AED89A', '2025-09-11 08:10:12'),
(11, 'Recruiter', 1, '5.00', '', 'Test', 'Success', 'CR-66C4F123E66F', '2025-09-11 08:10:15'),
(12, 'Employer', 3, '5.00', '', 'Test', 'Success', 'CR-9A0FD25006C2', '2025-09-11 11:50:58'),
(13, 'Employer', 1, '5.00', '', 'Test', 'Success', 'CR-4927EF442943', '2025-09-12 23:24:57');

-- --------------------------------------------------------

--
-- Table structure for table `recruiters`
--

CREATE TABLE `recruiters` (
  `recruiter_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `agency_name` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `credits_balance` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recruiters`
--

INSERT INTO `recruiters` (`recruiter_id`, `full_name`, `email`, `password_hash`, `agency_name`, `contact_number`, `location`, `credits_balance`, `created_at`, `updated_at`) VALUES
(1, 'Alimudin bin Abu', 'info@ga2medical.com', '$2y$10$sjPpXVKGprFhNMnYMKxLgu/KG6uz19Re8tt7Ak7NQdQAsyeQIA/Wq', 'SuperIdol Sdn Bhd', '+60107887682', 'Kuala Lumpur, Malaysia.', 12, '2025-09-08 18:01:25', '2025-09-13 04:54:49');

-- --------------------------------------------------------

--
-- Table structure for table `resumes`
--

CREATE TABLE `resumes` (
  `resume_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `resume_url` text NOT NULL,
  `generated_by_system` tinyint(1) NOT NULL DEFAULT 0,
  `summary` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resumes`
--

INSERT INTO `resumes` (`resume_id`, `candidate_id`, `resume_url`, `generated_by_system`, `summary`, `created_at`, `updated_at`) VALUES
(1, 4, '/assets/uploads/resumes/resume_4_1757772136.pdf', 0, NULL, '2025-09-13 22:02:16', '2025-09-13 22:02:16'),
(2, 1, '/assets/uploads/resumes/resume_1_1757921487.pdf', 0, NULL, '2025-09-15 15:31:27', '2025-09-15 15:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `resume_unlocks`
--

CREATE TABLE `resume_unlocks` (
  `unlock_id` int(11) NOT NULL,
  `unlocked_by_type` enum('Employer','Recruiter') NOT NULL,
  `unlocked_by_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resume_unlocks`
--

INSERT INTO `resume_unlocks` (`unlock_id`, `unlocked_by_type`, `unlocked_by_id`, `candidate_id`, `created_at`) VALUES
(1, 'Employer', 1, 1, '2025-09-11 07:30:20'),
(2, 'Recruiter', 1, 4, '2025-09-11 08:10:22'),
(3, 'Recruiter', 1, 5, '2025-09-11 08:10:32'),
(4, 'Employer', 3, 4, '2025-09-11 11:51:10'),
(5, 'Employer', 1, 5, '2025-09-13 04:54:08'),
(6, 'Recruiter', 1, 3, '2025-09-13 04:54:37'),
(7, 'Employer', 6, 4, '2025-09-13 22:04:07'),
(8, 'Employer', 1, 4, '2025-09-15 19:31:17'),
(9, 'Employer', 3, 5, '2025-09-15 22:06:19');

-- --------------------------------------------------------

--
-- Table structure for table `stripe_payments`
--

CREATE TABLE `stripe_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('Employer','Recruiter','Candidate','Admin') NOT NULL,
  `purpose` enum('credits','premium') NOT NULL,
  `credits` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL,
  `currency` char(3) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `payment_intent` varchar(255) DEFAULT NULL,
  `status` varchar(64) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stripe_payments`
--

INSERT INTO `stripe_payments` (`id`, `user_id`, `user_role`, `purpose`, `credits`, `amount`, `currency`, `session_id`, `payment_intent`, `status`, `payload`, `created_at`, `updated_at`) VALUES
(1, 1, 'Candidate', 'premium', NULL, 5000, 'myr', 'cs_test_a1Z0t5BiGYR7fSlMbcN8D8OVLspSapkVlHK1SaCr6ttuWMSIcEIi9l2lyq', NULL, 'created', NULL, '2025-09-13 04:24:17', '2025-09-13 04:24:17'),
(2, 1, 'Candidate', 'premium', NULL, 5000, 'myr', 'cs_test_a1FBgwYTViRB0BTgvG0yUuT28RDFLCLRbdKVqQTs4Gv7ZCM8TZ7tAlA0cM', NULL, 'created', NULL, '2025-09-13 04:25:48', '2025-09-13 04:25:48'),
(3, 1, 'Candidate', 'premium', NULL, 5000, 'myr', 'cs_test_a1sJzV1km3u59f13ORhOwHLBNtk7TgB2jQ8EGzddS7nz7yU2iDOHaLgXbJ', 'pi_3S6ddEIs8dFmRPkx3MTXlEit', 'paid', '{\"id\":\"cs_test_a1sJzV1km3u59f13ORhOwHLBNtk7TgB2jQ8EGzddS7nz7yU2iDOHaLgXbJ\",\"object\":\"checkout.session\",\"adaptive_pricing\":{\"enabled\":true},\"after_expiration\":null,\"allow_promotion_codes\":null,\"amount_subtotal\":5000,\"amount_total\":5000,\"automatic_tax\":{\"enabled\":false,\"liability\":null,\"provider\":null,\"status\":null},\"billing_address_collection\":null,\"cancel_url\":\"http:\\/\\/localhost\\/HireMe\\/public\\/premium\",\"client_reference_id\":null,\"client_secret\":null,\"collected_information\":null,\"consent\":null,\"consent_collection\":null,\"created\":1757709098,\"currency\":\"myr\",\"currency_conversion\":null,\"custom_fields\":[],\"custom_text\":{\"after_submit\":null,\"shipping_address\":null,\"submit\":null,\"terms_of_service_acceptance\":null},\"customer\":null,\"customer_creation\":\"if_required\",\"customer_details\":{\"address\":{\"city\":null,\"country\":\"MY\",\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"bchoong1@gmail.com\",\"name\":\"Test Name\",\"phone\":null,\"tax_exempt\":\"none\",\"tax_ids\":[]},\"customer_email\":null,\"discounts\":[],\"expires_at\":1757795498,\"invoice\":null,\"invoice_creation\":{\"enabled\":false,\"invoice_data\":{\"account_tax_ids\":null,\"custom_fields\":null,\"description\":null,\"footer\":null,\"issuer\":null,\"metadata\":[],\"rendering_options\":null}},\"livemode\":false,\"locale\":null,\"metadata\":{\"purpose\":\"premium\",\"user_id\":\"1\",\"user_role\":\"Candidate\"},\"mode\":\"payment\",\"origin_context\":null,\"payment_intent\":\"pi_3S6ddEIs8dFmRPkx3MTXlEit\",\"payment_link\":null,\"payment_method_collection\":\"if_required\",\"payment_method_configuration_details\":{\"id\":\"pmc_1S6cpcIs8dFmRPkxobJ0BttC\",\"parent\":null},\"payment_method_options\":{\"card\":{\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\",\"link\"],\"payment_status\":\"paid\",\"permissions\":null,\"phone_number_collection\":{\"enabled\":false},\"recovered_from\":null,\"saved_payment_method_options\":null,\"setup_intent\":null,\"shipping_address_collection\":null,\"shipping_cost\":null,\"shipping_options\":[],\"status\":\"complete\",\"submit_type\":null,\"subscription\":null,\"success_url\":\"http:\\/\\/localhost\\/HireMe\\/public\\/premium?sid={CHECKOUT_SESSION_ID}\",\"total_details\":{\"amount_discount\":0,\"amount_shipping\":0,\"amount_tax\":0},\"ui_mode\":\"hosted\",\"url\":null,\"wallet_options\":null}', '2025-09-13 04:31:39', '2025-09-13 04:31:48'),
(4, 1, 'Employer', 'credits', 10, 1000, 'myr', 'cs_test_a1gq1v3tqDvKKCScywEBEFrod8Lw3ydJVzm1Jg6K8exXamTqeGVLmX0osM', NULL, 'created', NULL, '2025-09-13 04:35:19', '2025-09-13 04:35:19'),
(5, 1, 'Employer', 'credits', 5, 500, 'myr', 'cs_test_a1Ku0CCtMc4eczwnx1wSDoRSm33pFiZjxlI37eMHjXQKTQMco8WD5egaNG', NULL, 'created', NULL, '2025-09-13 04:35:50', '2025-09-13 04:35:50'),
(6, 1, 'Employer', 'credits', 5, 500, 'myr', 'cs_test_a1a8JsxpE6mTc16zw4UKi3t9nR640Vq47mSOPWVRl0gONRK6bRMEdwoWST', NULL, 'created', NULL, '2025-09-13 04:40:44', '2025-09-13 04:40:44'),
(7, 1, 'Employer', 'credits', 5, 500, 'myr', 'cs_test_a1Rva0873Wx983OKauax7I2m3NgbT8BlyjkjxkAlqLJOeoiV593s7HOkrb', NULL, 'created', NULL, '2025-09-13 04:42:31', '2025-09-13 04:42:31'),
(8, 1, 'Employer', 'credits', 100, 10000, 'myr', 'cs_test_a1AkQtu2XajF7hjpiAydNWCoASKH8Kb3wT62Erg70xycK1ZVf4zfEulgBc', NULL, 'created', NULL, '2025-09-13 04:44:34', '2025-09-13 04:44:34'),
(9, 1, 'Employer', 'credits', 10, 1000, 'myr', 'cs_test_a11tXk6qQJDWiRPRr8BzZnfqQQK99LqJ45Nyf5tvJowfo84otqdsFoveQJ', NULL, 'created', NULL, '2025-09-13 04:47:09', '2025-09-13 04:47:09'),
(10, 1, 'Employer', 'credits', 10, 1000, 'myr', 'cs_test_a1qNXRSWJxWk2fDXhn1UHCzHS4itiL2sljdtYxWKETTGpm7ivbqnh7y0kr', NULL, 'created', NULL, '2025-09-13 04:49:22', '2025-09-13 04:49:22'),
(11, 1, 'Employer', 'credits', 10, 1000, 'myr', 'cs_test_a1tAKq2CBkI3uEbw5NrZnmXE5Ne3k5lXrElBdNlXvXLOIZpcQ71EEgjkM2', 'pi_3S6dybIs8dFmRPkx1eFcYnDH', 'paid', '{\"id\":\"cs_test_a1tAKq2CBkI3uEbw5NrZnmXE5Ne3k5lXrElBdNlXvXLOIZpcQ71EEgjkM2\",\"object\":\"checkout.session\",\"adaptive_pricing\":{\"enabled\":true},\"after_expiration\":null,\"allow_promotion_codes\":null,\"amount_subtotal\":1000,\"amount_total\":1000,\"automatic_tax\":{\"enabled\":false,\"liability\":null,\"provider\":null,\"status\":null},\"billing_address_collection\":null,\"cancel_url\":\"http://localhost/HireMe/public/credits/cancel\",\"client_reference_id\":null,\"client_secret\":null,\"collected_information\":null,\"consent\":null,\"consent_collection\":null,\"created\":1757710423,\"currency\":\"myr\",\"currency_conversion\":null,\"custom_fields\":[],\"custom_text\":{\"after_submit\":null,\"shipping_address\":null,\"submit\":null,\"terms_of_service_acceptance\":null},\"customer\":null,\"customer_creation\":\"if_required\",\"customer_details\":{\"address\":{\"city\":null,\"country\":\"MY\",\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"bchoong1@gmail.com\",\"name\":\"Test Name\",\"phone\":null,\"tax_exempt\":\"none\",\"tax_ids\":[]},\"customer_email\":null,\"discounts\":[],\"expires_at\":1757796823,\"invoice\":null,\"invoice_creation\":{\"enabled\":false,\"invoice_data\":{\"account_tax_ids\":null,\"custom_fields\":null,\"description\":null,\"footer\":null,\"issuer\":null,\"metadata\":[],\"rendering_options\":null}},\"livemode\":false,\"locale\":null,\"metadata\":{\"credits\":\"10\",\"purpose\":\"credits\",\"user_id\":\"1\",\"user_role\":\"Employer\"},\"mode\":\"payment\",\"origin_context\":null,\"payment_intent\":{\"id\":\"pi_3S6dybIs8dFmRPkx1eFcYnDH\",\"object\":\"payment_intent\",\"amount\":1000,\"amount_capturable\":0,\"amount_details\":{\"tip\":[]},\"amount_received\":1000,\"application\":null,\"application_fee_amount\":null,\"automatic_payment_methods\":null,\"canceled_at\":null,\"cancellation_reason\":null,\"capture_method\":\"automatic_async\",\"client_secret\":\"pi_3S6dybIs8dFmRPkx1eFcYnDH_secret_pJpSiKC3s6TzYvMA1woDzrZgP\",\"confirmation_method\":\"automatic\",\"created\":1757710429,\"currency\":\"myr\",\"customer\":null,\"description\":null,\"excluded_payment_method_types\":null,\"last_payment_error\":null,\"latest_charge\":\"ch_3S6dybIs8dFmRPkx13kqWFw1\",\"livemode\":false,\"metadata\":[],\"next_action\":null,\"on_behalf_of\":null,\"payment_method\":\"pm_1S6dyaIs8dFmRPkxHbJ5RYzL\",\"payment_method_configuration_details\":null,\"payment_method_options\":{\"card\":{\"installments\":null,\"mandate_options\":null,\"network\":null,\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\"],\"processing\":null,\"receipt_email\":null,\"review\":null,\"setup_future_usage\":null,\"shipping\":null,\"source\":null,\"statement_descriptor\":null,\"statement_descriptor_suffix\":null,\"status\":\"succeeded\",\"transfer_data\":null,\"transfer_group\":null},\"payment_link\":null,\"payment_method_collection\":\"if_required\",\"payment_method_configuration_details\":{\"id\":\"pmc_1S6cpcIs8dFmRPkxobJ0BttC\",\"parent\":null},\"payment_method_options\":{\"card\":{\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\",\"link\"],\"payment_status\":\"paid\",\"permissions\":null,\"phone_number_collection\":{\"enabled\":false},\"recovered_from\":null,\"saved_payment_method_options\":null,\"setup_intent\":null,\"shipping_address_collection\":null,\"shipping_cost\":null,\"shipping_options\":[],\"status\":\"complete\",\"submit_type\":null,\"subscription\":null,\"success_url\":\"http://localhost/HireMe/public/credits/success?sid={CHECKOUT_SESSION_ID}\",\"total_details\":{\"amount_discount\":0,\"amount_shipping\":0,\"amount_tax\":0},\"ui_mode\":\"hosted\",\"url\":null,\"wallet_options\":null}', '2025-09-13 04:53:45', '2025-09-13 04:53:53'),
(12, 1, 'Recruiter', 'credits', 5, 500, 'myr', 'cs_test_a13MZCGAtOttLYhESHwVezE9ed6f2xSu1Jy8hjys2RZeBfaRsXfVpRDn1c', 'pi_3S6dzVIs8dFmRPkx3GrLiN8A', 'paid', '{\"id\":\"cs_test_a13MZCGAtOttLYhESHwVezE9ed6f2xSu1Jy8hjys2RZeBfaRsXfVpRDn1c\",\"object\":\"checkout.session\",\"adaptive_pricing\":{\"enabled\":true},\"after_expiration\":null,\"allow_promotion_codes\":null,\"amount_subtotal\":500,\"amount_total\":500,\"automatic_tax\":{\"enabled\":false,\"liability\":null,\"provider\":null,\"status\":null},\"billing_address_collection\":null,\"cancel_url\":\"http://localhost/HireMe/public/credits/cancel\",\"client_reference_id\":null,\"client_secret\":null,\"collected_information\":null,\"consent\":null,\"consent_collection\":null,\"created\":1757710479,\"currency\":\"myr\",\"currency_conversion\":null,\"custom_fields\":[],\"custom_text\":{\"after_submit\":null,\"shipping_address\":null,\"submit\":null,\"terms_of_service_acceptance\":null},\"customer\":null,\"customer_creation\":\"if_required\",\"customer_details\":{\"address\":{\"city\":null,\"country\":\"MY\",\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"bchoong1@gmail.com\",\"name\":\"Test Name\",\"phone\":null,\"tax_exempt\":\"none\",\"tax_ids\":[]},\"customer_email\":null,\"discounts\":[],\"expires_at\":1757796879,\"invoice\":null,\"invoice_creation\":{\"enabled\":false,\"invoice_data\":{\"account_tax_ids\":null,\"custom_fields\":null,\"description\":null,\"footer\":null,\"issuer\":null,\"metadata\":[],\"rendering_options\":null}},\"livemode\":false,\"locale\":null,\"metadata\":{\"credits\":\"5\",\"purpose\":\"credits\",\"user_id\":\"1\",\"user_role\":\"Recruiter\"},\"mode\":\"payment\",\"origin_context\":null,\"payment_intent\":{\"id\":\"pi_3S6dzVIs8dFmRPkx3GrLiN8A\",\"object\":\"payment_intent\",\"amount\":500,\"amount_capturable\":0,\"amount_details\":{\"tip\":[]},\"amount_received\":500,\"application\":null,\"application_fee_amount\":null,\"automatic_payment_methods\":null,\"canceled_at\":null,\"cancellation_reason\":null,\"capture_method\":\"automatic_async\",\"client_secret\":\"pi_3S6dzVIs8dFmRPkx3GrLiN8A_secret_eq7UEzj7jQ0Zq5IDBmwWgSryI\",\"confirmation_method\":\"automatic\",\"created\":1757710485,\"currency\":\"myr\",\"customer\":null,\"description\":null,\"excluded_payment_method_types\":null,\"last_payment_error\":null,\"latest_charge\":\"ch_3S6dzVIs8dFmRPkx3ajL356T\",\"livemode\":false,\"metadata\":[],\"next_action\":null,\"on_behalf_of\":null,\"payment_method\":\"pm_1S6dzUIs8dFmRPkxC7XiT6rX\",\"payment_method_configuration_details\":null,\"payment_method_options\":{\"card\":{\"installments\":null,\"mandate_options\":null,\"network\":null,\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\"],\"processing\":null,\"receipt_email\":null,\"review\":null,\"setup_future_usage\":null,\"shipping\":null,\"source\":null,\"statement_descriptor\":null,\"statement_descriptor_suffix\":null,\"status\":\"succeeded\",\"transfer_data\":null,\"transfer_group\":null},\"payment_link\":null,\"payment_method_collection\":\"if_required\",\"payment_method_configuration_details\":{\"id\":\"pmc_1S6cpcIs8dFmRPkxobJ0BttC\",\"parent\":null},\"payment_method_options\":{\"card\":{\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\",\"link\"],\"payment_status\":\"paid\",\"permissions\":null,\"phone_number_collection\":{\"enabled\":false},\"recovered_from\":null,\"saved_payment_method_options\":null,\"setup_intent\":null,\"shipping_address_collection\":null,\"shipping_cost\":null,\"shipping_options\":[],\"status\":\"complete\",\"submit_type\":null,\"subscription\":null,\"success_url\":\"http://localhost/HireMe/public/credits/success?sid={CHECKOUT_SESSION_ID}\",\"total_details\":{\"amount_discount\":0,\"amount_shipping\":0,\"amount_tax\":0},\"ui_mode\":\"hosted\",\"url\":null,\"wallet_options\":null}', '2025-09-13 04:54:41', '2025-09-13 04:54:49'),
(13, 1, 'Candidate', 'premium', NULL, 5000, 'myr', 'cs_test_a1251c10MLPOqjuhv2ShIouQA2TOQFrBQpCWbLLknSTQBe2EBnJruKyr6s', 'pi_3S6ikrIs8dFmRPkx1n9YXam0', 'paid', '{\"id\":\"cs_test_a1251c10MLPOqjuhv2ShIouQA2TOQFrBQpCWbLLknSTQBe2EBnJruKyr6s\",\"object\":\"checkout.session\",\"adaptive_pricing\":{\"enabled\":true},\"after_expiration\":null,\"allow_promotion_codes\":null,\"amount_subtotal\":5000,\"amount_total\":5000,\"automatic_tax\":{\"enabled\":false,\"liability\":null,\"provider\":null,\"status\":null},\"billing_address_collection\":null,\"cancel_url\":\"http:\\/\\/bernard.onthewifi.com\\/HireMe\\/public\\/premium\",\"client_reference_id\":null,\"client_secret\":null,\"collected_information\":null,\"consent\":null,\"consent_collection\":null,\"created\":1757728777,\"currency\":\"myr\",\"currency_conversion\":null,\"custom_fields\":[],\"custom_text\":{\"after_submit\":null,\"shipping_address\":null,\"submit\":null,\"terms_of_service_acceptance\":null},\"customer\":null,\"customer_creation\":\"if_required\",\"customer_details\":{\"address\":{\"city\":null,\"country\":\"MY\",\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"bchoong1@gmail.com\",\"name\":\"Bernard Choong\",\"phone\":null,\"tax_exempt\":\"none\",\"tax_ids\":[]},\"customer_email\":null,\"discounts\":[],\"expires_at\":1757815177,\"invoice\":null,\"invoice_creation\":{\"enabled\":false,\"invoice_data\":{\"account_tax_ids\":null,\"custom_fields\":null,\"description\":null,\"footer\":null,\"issuer\":null,\"metadata\":[],\"rendering_options\":null}},\"livemode\":false,\"locale\":null,\"metadata\":{\"purpose\":\"premium\",\"user_id\":\"1\",\"user_role\":\"Candidate\"},\"mode\":\"payment\",\"origin_context\":null,\"payment_intent\":\"pi_3S6ikrIs8dFmRPkx1n9YXam0\",\"payment_link\":null,\"payment_method_collection\":\"if_required\",\"payment_method_configuration_details\":{\"id\":\"pmc_1S6cpcIs8dFmRPkxobJ0BttC\",\"parent\":null},\"payment_method_options\":{\"card\":{\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\",\"link\"],\"payment_status\":\"paid\",\"permissions\":null,\"phone_number_collection\":{\"enabled\":false},\"recovered_from\":null,\"saved_payment_method_options\":null,\"setup_intent\":null,\"shipping_address_collection\":null,\"shipping_cost\":null,\"shipping_options\":[],\"status\":\"complete\",\"submit_type\":null,\"subscription\":null,\"success_url\":\"http:\\/\\/bernard.onthewifi.com\\/HireMe\\/public\\/premium?sid={CHECKOUT_SESSION_ID}\",\"total_details\":{\"amount_discount\":0,\"amount_shipping\":0,\"amount_tax\":0},\"ui_mode\":\"hosted\",\"url\":null,\"wallet_options\":null}', '2025-09-13 09:59:39', '2025-09-13 10:00:02'),
(14, 6, 'Employer', 'credits', 10, 1000, 'myr', 'cs_test_a1rCkUJ44yPSMCAxc9W91znbkJIS6UuWOZlKs8xakX7DumRTtAwiPqtFq1', NULL, 'created', NULL, '2025-09-13 21:52:23', '2025-09-13 21:52:23'),
(15, 1, 'Candidate', 'premium', NULL, 5000, 'myr', 'cs_test_a1jhLyblDjRE7rcGB2a5XrJBoAHulVpdxpM6irToeS1WROz3K42jo1rUlO', 'pi_3S7X89Is8dFmRPkx1WTQfwBP', 'paid', '{\"id\":\"cs_test_a1jhLyblDjRE7rcGB2a5XrJBoAHulVpdxpM6irToeS1WROz3K42jo1rUlO\",\"object\":\"checkout.session\",\"adaptive_pricing\":{\"enabled\":true},\"after_expiration\":null,\"allow_promotion_codes\":null,\"amount_subtotal\":5000,\"amount_total\":5000,\"automatic_tax\":{\"enabled\":false,\"liability\":null,\"provider\":null,\"status\":null},\"billing_address_collection\":null,\"cancel_url\":\"http:\\/\\/bernard.onthewifi.com\\/HireMe\\/public\\/premium\",\"client_reference_id\":null,\"client_secret\":null,\"collected_information\":null,\"consent\":null,\"consent_collection\":null,\"created\":1757922426,\"currency\":\"myr\",\"currency_conversion\":null,\"custom_fields\":[],\"custom_text\":{\"after_submit\":null,\"shipping_address\":null,\"submit\":null,\"terms_of_service_acceptance\":null},\"customer\":null,\"customer_creation\":\"if_required\",\"customer_details\":{\"address\":{\"city\":null,\"country\":\"MY\",\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"bchoong1@gmail.com\",\"name\":\"Test Name\",\"phone\":null,\"tax_exempt\":\"none\",\"tax_ids\":[]},\"customer_email\":null,\"discounts\":[],\"expires_at\":1758008826,\"invoice\":null,\"invoice_creation\":{\"enabled\":false,\"invoice_data\":{\"account_tax_ids\":null,\"custom_fields\":null,\"description\":null,\"footer\":null,\"issuer\":null,\"metadata\":[],\"rendering_options\":null}},\"livemode\":false,\"locale\":null,\"metadata\":{\"purpose\":\"premium\",\"user_id\":\"1\",\"user_role\":\"Candidate\"},\"mode\":\"payment\",\"origin_context\":null,\"payment_intent\":\"pi_3S7X89Is8dFmRPkx1WTQfwBP\",\"payment_link\":null,\"payment_method_collection\":\"if_required\",\"payment_method_configuration_details\":{\"id\":\"pmc_1S6cpcIs8dFmRPkxobJ0BttC\",\"parent\":null},\"payment_method_options\":{\"card\":{\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\",\"link\"],\"payment_status\":\"paid\",\"permissions\":null,\"phone_number_collection\":{\"enabled\":false},\"recovered_from\":null,\"saved_payment_method_options\":null,\"setup_intent\":null,\"shipping_address_collection\":null,\"shipping_cost\":null,\"shipping_options\":[],\"status\":\"complete\",\"submit_type\":null,\"subscription\":null,\"success_url\":\"http:\\/\\/bernard.onthewifi.com\\/HireMe\\/public\\/premium?sid={CHECKOUT_SESSION_ID}\",\"total_details\":{\"amount_discount\":0,\"amount_shipping\":0,\"amount_tax\":0},\"ui_mode\":\"hosted\",\"url\":null,\"wallet_options\":null}', '2025-09-15 15:47:11', '2025-09-15 15:47:23'),
(16, 3, 'Employer', 'credits', 5, 500, 'myr', 'cs_test_a1EgnaMB6nFSf86VLobx1Hj1QSrILZD1zAxzjRu7MrN9j5YE3mtYe7ODFv', NULL, 'created', NULL, '2025-09-15 18:29:32', '2025-09-15 18:29:32'),
(17, 3, 'Employer', 'credits', 5, 500, 'myr', 'cs_test_a12cwoYYnNXUxHlhS6flkufjjmr1LTXFRLV5xrLocMKMBwYSdZ3Mu0bmH3', NULL, 'created', NULL, '2025-09-15 18:35:43', '2025-09-15 18:35:43'),
(18, 3, 'Employer', 'credits', 5, 500, 'myr', 'cs_test_a1jT2fsavcQXmvJh02peqyvW8fXVct0pVzKqXlg35gIaIsIo046fP6KlHT', NULL, 'created', NULL, '2025-09-15 18:37:26', '2025-09-15 18:37:26'),
(19, 3, 'Employer', 'credits', 500, 50000, 'myr', 'cs_test_a1vDodq7b6vkH9umcVUSE3jpfvtdbVQr3xUJNFO8vqNJD4TF8IjH6qbVPc', NULL, 'created', NULL, '2025-09-15 18:37:41', '2025-09-15 18:37:41'),
(20, 3, 'Employer', 'credits', 5, 500, 'myr', 'cs_test_a1XKV6EQMZhBZrAkjNywkjfCfAqKXV5Tqhw2YjVOvUJGdoUFEljfQ9gVZI', 'pi_3S7ZpXIs8dFmRPkx2RVXTVnF', 'paid', '{\"id\":\"cs_test_a1XKV6EQMZhBZrAkjNywkjfCfAqKXV5Tqhw2YjVOvUJGdoUFEljfQ9gVZI\",\"object\":\"checkout.session\",\"adaptive_pricing\":{\"enabled\":true},\"after_expiration\":null,\"allow_promotion_codes\":null,\"amount_subtotal\":500,\"amount_total\":500,\"automatic_tax\":{\"enabled\":false,\"liability\":null,\"provider\":null,\"status\":null},\"billing_address_collection\":null,\"cancel_url\":\"https://bernard.onthewifi.com/HireMe/public/credits/cancel\",\"client_reference_id\":null,\"client_secret\":null,\"collected_information\":null,\"consent\":null,\"consent_collection\":null,\"created\":1757932732,\"currency\":\"myr\",\"currency_conversion\":null,\"custom_fields\":[],\"custom_text\":{\"after_submit\":null,\"shipping_address\":null,\"submit\":null,\"terms_of_service_acceptance\":null},\"customer\":null,\"customer_creation\":\"if_required\",\"customer_details\":{\"address\":{\"city\":null,\"country\":\"MY\",\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"NTC@gmail.com\",\"name\":\"NTC\",\"phone\":null,\"tax_exempt\":\"none\",\"tax_ids\":[]},\"customer_email\":null,\"discounts\":[],\"expires_at\":1758019131,\"invoice\":null,\"invoice_creation\":{\"enabled\":false,\"invoice_data\":{\"account_tax_ids\":null,\"custom_fields\":null,\"description\":null,\"footer\":null,\"issuer\":null,\"metadata\":[],\"rendering_options\":null}},\"livemode\":false,\"locale\":null,\"metadata\":{\"credits\":\"5\",\"purpose\":\"credits\",\"user_id\":\"3\",\"user_role\":\"Employer\"},\"mode\":\"payment\",\"origin_context\":null,\"payment_intent\":{\"id\":\"pi_3S7ZpXIs8dFmRPkx2RVXTVnF\",\"object\":\"payment_intent\",\"amount\":500,\"amount_capturable\":0,\"amount_details\":{\"tip\":[]},\"amount_received\":500,\"application\":null,\"application_fee_amount\":null,\"automatic_payment_methods\":null,\"canceled_at\":null,\"cancellation_reason\":null,\"capture_method\":\"automatic_async\",\"client_secret\":\"pi_3S7ZpXIs8dFmRPkx2RVXTVnF_secret_bUUY9CfoXH4knpNTmxq3mRDy9\",\"confirmation_method\":\"automatic\",\"created\":1757932819,\"currency\":\"myr\",\"customer\":null,\"description\":null,\"excluded_payment_method_types\":null,\"last_payment_error\":null,\"latest_charge\":\"ch_3S7ZpXIs8dFmRPkx26COknQH\",\"livemode\":false,\"metadata\":[],\"next_action\":null,\"on_behalf_of\":null,\"payment_method\":\"pm_1S7ZpXIs8dFmRPkxLTPakG5R\",\"payment_method_configuration_details\":null,\"payment_method_options\":{\"card\":{\"installments\":null,\"mandate_options\":null,\"network\":null,\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\"],\"processing\":null,\"receipt_email\":null,\"review\":null,\"setup_future_usage\":null,\"shipping\":null,\"source\":null,\"statement_descriptor\":null,\"statement_descriptor_suffix\":null,\"status\":\"succeeded\",\"transfer_data\":null,\"transfer_group\":null},\"payment_link\":null,\"payment_method_collection\":\"if_required\",\"payment_method_configuration_details\":{\"id\":\"pmc_1S6cpcIs8dFmRPkxobJ0BttC\",\"parent\":null},\"payment_method_options\":{\"card\":{\"request_three_d_secure\":\"automatic\"}},\"payment_method_types\":[\"card\",\"link\"],\"payment_status\":\"paid\",\"permissions\":null,\"phone_number_collection\":{\"enabled\":false},\"recovered_from\":null,\"saved_payment_method_options\":null,\"setup_intent\":null,\"shipping_address_collection\":null,\"shipping_cost\":null,\"shipping_options\":[],\"status\":\"complete\",\"submit_type\":null,\"subscription\":null,\"success_url\":\"https://bernard.onthewifi.com/HireMe/public/credits/success?sid={CHECKOUT_SESSION_ID}\",\"total_details\":{\"amount_discount\":0,\"amount_shipping\":0,\"amount_tax\":0},\"ui_mode\":\"hosted\",\"url\":null,\"wallet_options\":null}', '2025-09-15 18:38:52', '2025-09-15 18:40:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_admins_email` (`email`),
  ADD KEY `idx_admins_role` (`role`),
  ADD KEY `idx_admins_status` (`status`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`applicant_id`),
  ADD UNIQUE KEY `uniq_candidate_job` (`candidate_id`,`job_posting_id`),
  ADD KEY `idx_applications_candidate` (`candidate_id`),
  ADD KEY `idx_applications_job` (`job_posting_id`),
  ADD KEY `idx_applications_status` (`application_status`),
  ADD KEY `idx_applications_date` (`application_date`);

--
-- Indexes for table `application_answers`
--
ALTER TABLE `application_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `fk_ans_app` (`application_id`),
  ADD KEY `fk_ans_q` (`question_id`);

--
-- Indexes for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ash_app` (`application_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `idx_billing_user` (`user_type`,`user_id`),
  ADD KEY `idx_billing_date` (`transaction_date`),
  ADD KEY `idx_billing_status` (`status`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`candidate_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_candidates_email` (`email`),
  ADD KEY `idx_candidates_created_at` (`created_at`);

--
-- Indexes for table `candidate_education`
--
ALTER TABLE `candidate_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ced_candidate` (`candidate_id`);

--
-- Indexes for table `candidate_experiences`
--
ALTER TABLE `candidate_experiences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ce_candidate` (`candidate_id`);

--
-- Indexes for table `candidate_languages`
--
ALTER TABLE `candidate_languages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cl_candidate` (`candidate_id`);

--
-- Indexes for table `candidate_skills`
--
ALTER TABLE `candidate_skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cs_candidate` (`candidate_id`);

--
-- Indexes for table `contact_form`
--
ALTER TABLE `contact_form`
  ADD PRIMARY KEY (`contact_id`),
  ADD KEY `idx_contact_email` (`contact_email`),
  ADD KEY `idx_contact_created_at` (`created_at`);

--
-- Indexes for table `credits_ledger`
--
ALTER TABLE `credits_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_role` (`user_role`,`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `employers`
--
ALTER TABLE `employers`
  ADD PRIMARY KEY (`employer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_employers_email` (`email`),
  ADD KEY `idx_employers_created_at` (`created_at`);

--
-- Indexes for table `job_micro_questions`
--
ALTER TABLE `job_micro_questions`
  ADD PRIMARY KEY (`job_posting_id`,`question_id`),
  ADD KEY `fk_jmq_q` (`question_id`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`job_posting_id`),
  ADD KEY `idx_job_postings_company` (`company_id`),
  ADD KEY `idx_job_postings_recruiter` (`recruiter_id`),
  ADD KEY `idx_job_postings_status` (`status`);
ALTER TABLE `job_postings` ADD FULLTEXT KEY `ft_job_postings_search` (`job_title`,`job_description`,`job_requirements`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attempt` (`email`,`ip_address`),
  ADD KEY `idx_last_attempt` (`last_attempt_at`);

--
-- Indexes for table `micro_questions`
--
ALTER TABLE `micro_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_pr_email` (`email`),
  ADD KEY `idx_pr_expires` (`expires_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_payments_user` (`user_type`,`user_id`),
  ADD KEY `idx_payments_created_at` (`created_at`),
  ADD KEY `idx_payments_status` (`transaction_status`);

--
-- Indexes for table `recruiters`
--
ALTER TABLE `recruiters`
  ADD PRIMARY KEY (`recruiter_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_recruiters_email` (`email`),
  ADD KEY `idx_recruiters_created_at` (`created_at`);

--
-- Indexes for table `resumes`
--
ALTER TABLE `resumes`
  ADD PRIMARY KEY (`resume_id`),
  ADD KEY `idx_resumes_candidate` (`candidate_id`),
  ADD KEY `idx_resumes_created_at` (`created_at`);

--
-- Indexes for table `resume_unlocks`
--
ALTER TABLE `resume_unlocks`
  ADD PRIMARY KEY (`unlock_id`),
  ADD UNIQUE KEY `uniq_unlock` (`unlocked_by_type`,`unlocked_by_id`,`candidate_id`),
  ADD KEY `fk_unlock_candidate` (`candidate_id`);

--
-- Indexes for table `stripe_payments`
--
ALTER TABLE `stripe_payments`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `applicant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `application_answers`
--
ALTER TABLE `application_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `application_status_history`
--
ALTER TABLE `application_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `candidate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `candidate_education`
--
ALTER TABLE `candidate_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `candidate_experiences`
--
ALTER TABLE `candidate_experiences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `candidate_languages`
--
ALTER TABLE `candidate_languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `candidate_skills`
--
ALTER TABLE `candidate_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `contact_form`
--
ALTER TABLE `contact_form`
  MODIFY `contact_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credits_ledger`
--
ALTER TABLE `credits_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employers`
--
ALTER TABLE `employers`
  MODIFY `employer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `job_posting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `micro_questions`
--
ALTER TABLE `micro_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `recruiters`
--
ALTER TABLE `recruiters`
  MODIFY `recruiter_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `resumes`
--
ALTER TABLE `resumes`
  MODIFY `resume_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `resume_unlocks`
--
ALTER TABLE `resume_unlocks`
  MODIFY `unlock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `stripe_payments`
--
ALTER TABLE `stripe_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_application_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_application_job` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`job_posting_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `application_answers`
--
ALTER TABLE `application_answers`
  ADD CONSTRAINT `fk_ans_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ans_q` FOREIGN KEY (`question_id`) REFERENCES `micro_questions` (`id`);

--
-- Constraints for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD CONSTRAINT `fk_ash_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`applicant_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_education`
--
ALTER TABLE `candidate_education`
  ADD CONSTRAINT `fk_ced_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_experiences`
--
ALTER TABLE `candidate_experiences`
  ADD CONSTRAINT `fk_ce_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_languages`
--
ALTER TABLE `candidate_languages`
  ADD CONSTRAINT `fk_cl_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_skills`
--
ALTER TABLE `candidate_skills`
  ADD CONSTRAINT `fk_cs_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_micro_questions`
--
ALTER TABLE `job_micro_questions`
  ADD CONSTRAINT `fk_jmq_job` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`job_posting_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jmq_q` FOREIGN KEY (`question_id`) REFERENCES `micro_questions` (`id`);

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `fk_job_company` FOREIGN KEY (`company_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_job_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `recruiters` (`recruiter_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `resumes`
--
ALTER TABLE `resumes`
  ADD CONSTRAINT `fk_resume_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `resume_unlocks`
--
ALTER TABLE `resume_unlocks`
  ADD CONSTRAINT `fk_unlock_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

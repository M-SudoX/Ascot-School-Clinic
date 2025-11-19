-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2025 at 03:47 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ascot_clinic_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `user_type` varchar(10) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `action` varchar(255) NOT NULL,
  `log_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` varchar(50) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `action` varchar(255) NOT NULL,
  `log_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `sent_by` varchar(100) NOT NULL,
  `send_email` tinyint(1) DEFAULT 0,
  `post_on_front` tinyint(4) DEFAULT 1,
  `attachment` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `email_sent_count` int(11) NOT NULL DEFAULT 0,
  `email_failed_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` datetime DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archive_date` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `sent_by`, `send_email`, `post_on_front`, `attachment`, `status`, `is_active`, `email_sent_count`, `email_failed_count`, `created_at`, `expiry_date`, `is_archived`, `archive_date`, `updated_at`) VALUES
(104, 'FROM:ASCOT CLINIC', 'Patient reports sore throat, mild cough, nasal congestion, and fatigue for 2 days. No shortness of breath.\r\n', 'Admin User', 0, 1, NULL, 'active', 1, 0, 0, '2025-11-18 02:55:50', '2025-11-19 08:00:00', 0, NULL, '2025-11-18 02:55:50');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_emails`
--

CREATE TABLE `announcement_emails` (
  `id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime NOT NULL,
  `error_message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_recipients`
--

CREATE TABLE `announcement_recipients` (
  `id` int(11) NOT NULL,
  `announcement_id` int(255) NOT NULL,
  `student_id` int(10) NOT NULL,
  `recipient_type` enum('email','front_page') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `consultation_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `certificate_type` varchar(100) NOT NULL,
  `diagnosis` text NOT NULL,
  `recommendation` text NOT NULL,
  `date_issued` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `college_physician`
--

CREATE TABLE `college_physician` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `college_physician`
--

INSERT INTO `college_physician` (`id`, `name`, `title`, `created_at`, `updated_at`) VALUES
(1, 'MARILYN R. GANTE, MD', 'College Physician', '2025-11-03 07:10:03', '2025-11-03 07:10:03');

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `symptoms` text NOT NULL,
  `temperature` varchar(20) NOT NULL,
  `diagnosis` text NOT NULL,
  `blood_pressure` varchar(20) NOT NULL,
  `treatment` text NOT NULL,
  `heart_rate` varchar(20) NOT NULL,
  `attending_staff` varchar(100) NOT NULL,
  `consultation_date` date NOT NULL,
  `consultation_time` time NOT NULL DEFAULT '00:00:00',
  `physician_notes` text NOT NULL,
  `is_archived` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `student_number`, `symptoms`, `temperature`, `diagnosis`, `blood_pressure`, `treatment`, `heart_rate`, `attending_staff`, `consultation_date`, `consultation_time`, `physician_notes`, `is_archived`, `created_at`, `archived_at`) VALUES
(52, '22-01-0822', 'Patient reports sore throat, mild cough, nasal congestion, and fatigue for 2 days. No shortness of breath.', '37.8 °C', 'Acute viral upper respiratory infection (common cold).', '118/76 mmHg', 'Acetaminophen 500 mg every 6 hours as needed for fever or discomfort', '84 bpm', 'Nurse Jamie R. Collins', '2025-11-18', '08:00:00', 'Patient appears mildly ill but non-toxic. Lungs clear to auscultation. No signs of bacterial involvement at this time. Supportive care recommended.', 0, '2025-11-17 04:41:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `consultation_requests`
--

CREATE TABLE `consultation_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `requested` varchar(255) NOT NULL,
  `notes` text NOT NULL,
  `status` enum('Pending','Approved','Rejected','Rescheduled','Completed') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_history`
--

CREATE TABLE `medical_history` (
  `id` int(11) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `medical_attention` varchar(10) NOT NULL,
  `medical_conditions` varchar(255) NOT NULL,
  `other_conditions` varchar(255) NOT NULL,
  `previous_hospitalization` varchar(10) NOT NULL,
  `hosp_year` varchar(10) NOT NULL,
  `surgery` varchar(10) NOT NULL,
  `surgery_year` varchar(10) NOT NULL,
  `surgery_details` varchar(255) NOT NULL,
  `food_allergies` varchar(255) NOT NULL,
  `medicine_allergies` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_history`
--

INSERT INTO `medical_history` (`id`, `student_number`, `medical_attention`, `medical_conditions`, `other_conditions`, `previous_hospitalization`, `hosp_year`, `surgery`, `surgery_year`, `surgery_details`, `food_allergies`, `medicine_allergies`, `created_at`, `updated_at`) VALUES
(51, '22-01-0822', 'No', '', 'None', 'No', '', 'No', '', '', 'None', 'None', '2025-11-05 13:34:39', '2025-11-05 13:34:39');

-- --------------------------------------------------------

--
-- Table structure for table `medical_officers`
--

CREATE TABLE `medical_officers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(100) NOT NULL,
  `license_number` varchar(100) NOT NULL,
  `signature_type` enum('physician','dentist','other') NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_information`
--

CREATE TABLE `student_information` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `age` int(11) NOT NULL,
  `sex` varchar(10) NOT NULL,
  `civil_status` varchar(50) NOT NULL,
  `blood_type` varchar(5) NOT NULL,
  `father_name` varchar(100) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `course_year` varchar(50) NOT NULL,
  `cellphone_number` varchar(20) NOT NULL,
  `archived` tinyint(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_information`
--

INSERT INTO `student_information` (`id`, `fullname`, `address`, `age`, `sex`, `civil_status`, `blood_type`, `father_name`, `student_number`, `date`, `school_year`, `course_year`, `cellphone_number`, `archived`, `created_at`, `archived_at`, `updated_at`) VALUES
(185, 'Maynard Bihasa', 'Baler', 21, 'Male', 'Single', 'A+', 'Annaliza Bihasa', '22-01-0822', '2025-11-04', '2025-2026', 'BSED-4A', '0961 550 0281', 0, '2025-11-04 09:04:28', NULL, '2025-11-04 09:04:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `activation_code` varchar(255) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `code_expiration` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `student_number`, `email`, `password`, `activation_code`, `is_verified`, `reset_token`, `reset_expires`, `contact_number`, `created_at`, `code_expiration`) VALUES
(193, 'Maynard Bihasa', '22-01-0822', 'bihasamaynard060@gmail.com', '$2y$10$p80sPYICCGq5ue5n.2TBFeukfKOp05ZRDmEmRfxlnEKhv278f.s6a', '', 1, NULL, NULL, NULL, '2025-11-04 09:04:28', '2025-11-04 10:05:28');

-- --------------------------------------------------------

--
-- Table structure for table `verification_codes`
--

CREATE TABLE `verification_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcement_emails`
--
ALTER TABLE `announcement_emails`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcement_recipients`
--
ALTER TABLE `announcement_recipients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `college_physician`
--
ALTER TABLE `college_physician`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_history`
--
ALTER TABLE `medical_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medical_officers`
--
ALTER TABLE `medical_officers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_information`
--
ALTER TABLE `student_information`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`student_number`),
  ADD UNIQUE KEY `id` (`id`,`fullname`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `verification_codes`
--
ALTER TABLE `verification_codes`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=523;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=429;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `announcement_emails`
--
ALTER TABLE `announcement_emails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `announcement_recipients`
--
ALTER TABLE `announcement_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT for table `college_physician`
--
ALTER TABLE `college_physician`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `consultation_requests`
--
ALTER TABLE `consultation_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `medical_history`
--
ALTER TABLE `medical_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `medical_officers`
--
ALTER TABLE `medical_officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_information`
--
ALTER TABLE `student_information`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `verification_codes`
--
ALTER TABLE `verification_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

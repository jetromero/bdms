-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 04, 2025 at 02:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bdms`
--

-- --------------------------------------------------------

--
-- Table structure for table `blood_inventory`
--

CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL,
  `blood_type` varchar(10) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('Available','Used','Expired') DEFAULT 'Available',
  `donated_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `birthdate` date NOT NULL,
  `address` text NOT NULL,
  `blood_type` varchar(5) NOT NULL,
  `civil_status` varchar(20) NOT NULL,
  `donation_history` varchar(50) NOT NULL,
  `classification` varchar(50) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `blood_quantity` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `email` varchar(255) NOT NULL,
  `donation_type` varchar(50) NOT NULL,
  `donation_location` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `quantity_ml` int(11) DEFAULT 450,
  `blood_quantity_ml` int(11) DEFAULT 450,
  `donation_status` varchar(50) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `contact` varchar(50) DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `donation_dates` text DEFAULT NULL,
  `number_of_donations` int(11) DEFAULT 0,
  `status` enum('Active','Inactive','Deferred') DEFAULT NULL,
  `medical_eligibility` text DEFAULT NULL,
  `donation_frequency` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blood_thresholds`
-- (US-04: predefined low-inventory thresholds)
--

CREATE TABLE `blood_thresholds` (
  `blood_type` varchar(10) NOT NULL,
  `threshold_ml` int(11) NOT NULL DEFAULT 500,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`blood_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`id`, `name`, `birthdate`, `address`, `blood_type`, `civil_status`, `donation_history`, `classification`, `contact_number`, `gender`, `blood_quantity`, `collection_date`, `email`, `donation_type`, `donation_location`, `age`, `expiry_date`, `donation_date`, `quantity_ml`, `blood_quantity_ml`, `donation_status`, `last_updated`, `contact`, `sex`, `donation_dates`, `number_of_donations`, `status`, `medical_eligibility`, `donation_frequency`) VALUES
(1, 'Joyce Navilgas Odan', '2004-04-25', 'Brgy. Tugbong, Kananga, Leyte', 'A+', 'Married', 'First Time', 'Student', '09103744482', 'Female', 200, '2025-04-25', 'odanjoyce569@gmail.com', 'Walk-In/Voluntary', 'Red Cross Area', 21, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09103744482 | odanjoyce569@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(2, 'Isiah James Garcia', '1999-07-01', 'Sa Puso ni Joyce', 'B+', 'Single', 'First Time', 'Staff', '09738573659', 'Male', 200, '2025-04-26', 'isiahjames.garcia@gmail.com', 'In House', 'Red Cross Area', 25, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09738573659 | isiahjames.garcia@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(7, 'Jelian Mae Morga', '2003-12-04', 'Tagaytay, Kananga', 'B+', 'Single', 'First Time', 'Student', '09457226651', 'Female', 100, '2025-04-03', 'jelianmaemorga@evsu.edu.ph', 'In House', 'Red Cross Area', 21, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09457226651 | jelianmaemorga@evsu.edu.ph', NULL, NULL, 0, NULL, NULL, NULL),
(10, 'Joyce Navilgas Odan', '2004-04-25', 'Brgy. Tugbong, Kananga, Leyte', 'AB-', 'Single', 'Regular Donor', 'Staff', '09103744482', 'Female', 150, '2025-04-25', 'odanjoyce569@gmail.com', 'In House', 'Red Cross Area', 21, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09103744482 | odanjoyce569@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(11, 'Lemuel Roble', '2000-03-08', 'Samar', 'B+', 'Single', 'First Time', 'Student', '09657365748', 'Male', 300, '2025-04-25', 'lemuelroble25@gmail.com', 'In House', 'Red Cross Area', 25, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09657365748 | lemuelroble25@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(12, 'Russell Laudiza', '2003-02-07', 'Simangan', 'A-', 'Single', 'First Time', 'Student', '09876546543', 'Male', 500, '2025-05-02', 'russelllaudiza10@gmail.com', 'In House', 'Red Cross Area', 22, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09876546543 | russelllaudiza10@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(13, 'Carlos Garcia', '1998-02-01', 'Bohol, Philippines', 'A+', 'Single', 'First Time', 'Student', '09786543654', 'Female', 800, '2025-04-25', 'carlosgarcia1@gmail.com', 'In House', 'Red Cross Area', 27, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09786543654 | carlosgarcia1@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(14, 'CPJ Garcia', '2001-06-05', 'Bohol', 'A+', 'Single', 'First Time', 'Student', '09746354256', 'Male', 143, '2025-04-25', 'cpjgarcia01@gmail.com', 'In House', 'Red Cross Area', 23, NULL, NULL, 450, 450, 'Active', '2025-05-03 00:39:38', '09746354256 | cpjgarcia01@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(15, 'Jose Garcia', '2000-02-01', 'Bohol', 'AB+', 'Single', 'First Time', 'Student', '09746354256', 'Female', 143, '2020-02-01', 'cpjgarcia01@gmail.com', 'Walk-In/Voluntary', 'Red Cross Area', 25, NULL, NULL, 450, 450, 'Expired', '2025-05-03 00:39:38', '09746354256 | cpjgarcia01@gmail.com', NULL, NULL, 0, NULL, NULL, NULL),
(16, 'Maria Garcia', '1976-02-09', 'Bohol', 'A-', 'Single', 'Regular Donor', 'Student', '09786543654', 'Female', 799, '2020-07-09', 'mariagarcia90@gmail.com', 'Walk-In/Voluntary', 'Red Cross Area', 49, NULL, NULL, 450, 450, 'Expired', '2025-05-03 00:39:38', '09786543654 | mariagarcia90@gmail.com', NULL, NULL, 0, NULL, NULL, NULL);

--
-- Dumping data for table `blood_thresholds`
--

INSERT INTO `blood_thresholds` (`blood_type`, `threshold_ml`) VALUES
('A+', 500),
('A-', 500),
('B+', 500),
('B-', 500),
('AB+', 500),
('AB-', 500),
('O+', 500),
('O-', 500);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donated_by` (`donated_by`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD CONSTRAINT `blood_inventory_ibfk_1` FOREIGN KEY (`donated_by`) REFERENCES `donors` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
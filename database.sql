-- VoltCraft 3D - Service Booking & Engineer Management Platform Database Schema
-- Database: voltcraft_db

CREATE DATABASE IF NOT EXISTS `voltcraft_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `voltcraft_db`;

-- Table 1: users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `role` ENUM('customer', 'engineer', 'admin') NOT NULL DEFAULT 'customer',
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: engineers
CREATE TABLE IF NOT EXISTS `engineers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `title` VARCHAR(150) NOT NULL,
  `bio` TEXT DEFAULT NULL,
  `experience_years` INT NOT NULL DEFAULT 1,
  `qualification` VARCHAR(200) DEFAULT NULL,
  `location` VARCHAR(150) NOT NULL,
  `hourly_rate` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `rating` DECIMAL(3,2) NOT NULL DEFAULT 5.00,
  `total_reviews` INT NOT NULL DEFAULT 0,
  `availability_status` ENUM('available', 'busy', 'offline') NOT NULL DEFAULT 'available',
  `upi_id` VARCHAR(100) DEFAULT NULL,
  `upi_qr_image` VARCHAR(255) DEFAULT NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_engineers_rating` (`rating`),
  INDEX `idx_engineers_avail` (`availability_status`),
  INDEX `idx_engineers_location` (`location`),
  CONSTRAINT `fk_engineers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: services
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `category` VARCHAR(100) NOT NULL,
  `icon` VARCHAR(100) DEFAULT 'cpu',
  `description` TEXT NOT NULL,
  `base_price` DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
  `estimated_time` VARCHAR(50) DEFAULT '2-4 Hours',
  `image` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5: engineer_services
CREATE TABLE IF NOT EXISTS `engineer_services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `engineer_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `custom_rate` DECIMAL(10,2) DEFAULT NULL,
  UNIQUE KEY `uk_eng_srv` (`engineer_id`, `service_id`),
  CONSTRAINT `fk_es_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `engineers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 6: engineer_skills
CREATE TABLE IF NOT EXISTS `engineer_skills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `engineer_id` INT NOT NULL,
  `skill_name` VARCHAR(100) NOT NULL,
  `proficiency` INT NOT NULL DEFAULT 90,
  INDEX `idx_skill_eng` (`engineer_id`),
  CONSTRAINT `fk_skills_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `engineers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 7: service_requests
CREATE TABLE IF NOT EXISTS `service_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_code` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `engineer_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `address` TEXT NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `preferred_date` DATE NOT NULL,
  `preferred_time` VARCHAR(50) NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `estimated_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('PENDING', 'ACCEPTED', 'REJECTED', 'PAYMENT_PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_req_cust` (`customer_id`),
  INDEX `idx_req_eng` (`engineer_id`),
  INDEX `idx_req_status` (`status`),
  CONSTRAINT `fk_sr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `engineers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 8: bookings
CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_code` VARCHAR(30) NOT NULL UNIQUE,
  `request_id` INT NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `engineer_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `advance_amount` DECIMAL(10,2) NOT NULL,
  `remaining_amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('PENDING', 'ADVANCE_PAID', 'FULLY_PAID', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
  `booking_status` ENUM('CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'CONFIRMED',
  `scheduled_at` DATETIME NOT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_bk_cust` (`customer_id`),
  INDEX `idx_bk_eng` (`engineer_id`),
  INDEX `idx_bk_status` (`booking_status`),
  INDEX `idx_bk_pay_status` (`payment_status`),
  CONSTRAINT `fk_bk_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bk_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `engineers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bk_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 9: payments
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_ref` VARCHAR(100) NOT NULL UNIQUE,
  `booking_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `engineer_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_type` ENUM('ADVANCE_20', 'REMAINING_80', 'FULL') NOT NULL DEFAULT 'ADVANCE_20',
  `payment_method` ENUM('UPI', 'CARD', 'NETBANKING', 'GATEWAY_DEMO') NOT NULL DEFAULT 'UPI',
  `payment_status` ENUM('PENDING', 'SUCCESS', 'FAILED') NOT NULL DEFAULT 'SUCCESS',
  `gateway_payment_id` VARCHAR(100) DEFAULT NULL,
  `gateway_order_id` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pay_bk` (`booking_id`),
  INDEX `idx_pay_cust` (`customer_id`),
  INDEX `idx_pay_eng` (`engineer_id`),
  CONSTRAINT `fk_pay_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `engineers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 10: reviews
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `engineer_id` INT NOT NULL,
  `rating` INT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_rev_eng` (`engineer_id`),
  CONSTRAINT `fk_rev_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_engineer` FOREIGN KEY (`engineer_id`) REFERENCES `engineers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 11: notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 12: complaints
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('OPEN', 'IN_REVIEW', 'RESOLVED') NOT NULL DEFAULT 'OPEN',
  `resolution` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_comp_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 13: contact_messages
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('NEW', 'READ', 'REPLIED') NOT NULL DEFAULT 'NEW',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================================
-- SEED DATA
-- Default Passwords:
-- Admin: admin@voltcraft.com / admin123
-- Engineers: [email]@voltcraft.com / engineer123
-- Customers: [email]@voltcraft.com / customer123
-- =========================================================================

-- Clean existing data if re-importing
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `contact_messages`;
TRUNCATE TABLE `complaints`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `reviews`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `bookings`;
TRUNCATE TABLE `service_requests`;
TRUNCATE TABLE `engineer_skills`;
TRUNCATE TABLE `engineer_services`;
TRUNCATE TABLE `services`;
TRUNCATE TABLE `engineers`;
TRUNCATE TABLE `customers`;
TRUNCATE TABLE `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- Users (1 Admin, 6 Engineers, 4 Customers)
INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`, `status`, `avatar`) VALUES
(1, 'Admin Controller', 'admin@voltcraft.com', '$2y$10$j.hbc.4i2rUNFTm3kW5L.eATJHz6ehBXzBtKYDfH6ZKVfYQNAZgJi', '+91 98000 00001', 'admin', 'active', 'assets/images/avatars/admin.jpg'),
(2, 'Rajesh Sharma', 'rajesh@voltcraft.com', '$2y$10$lvYX/ulP4qHTghLUuadute8BIi5QYM7sxG4q8QSDC0dOwuX6iq9Om', '+91 98450 12345', 'engineer', 'active', 'assets/images/avatars/engineer1.jpg'),
(3, 'Priya Nair', 'priya@voltcraft.com', '$2y$10$lvYX/ulP4qHTghLUuadute8BIi5QYM7sxG4q8QSDC0dOwuX6iq9Om', '+91 97120 54321', 'engineer', 'active', 'assets/images/avatars/engineer2.jpg'),
(4, 'Amit Verma', 'amit@voltcraft.com', '$2y$10$lvYX/ulP4qHTghLUuadute8BIi5QYM7sxG4q8QSDC0dOwuX6iq9Om', '+91 98110 99887', 'engineer', 'active', 'assets/images/avatars/engineer3.jpg'),
(5, 'Sneha Patel', 'sneha@voltcraft.com', '$2y$10$lvYX/ulP4qHTghLUuadute8BIi5QYM7sxG4q8QSDC0dOwuX6iq9Om', '+91 99040 33445', 'engineer', 'active', 'assets/images/avatars/engineer4.jpg'),
(6, 'Vikram Rathore', 'vikram@voltcraft.com', '$2y$10$lvYX/ulP4qHTghLUuadute8BIi5QYM7sxG4q8QSDC0dOwuX6iq9Om', '+91 98220 77665', 'engineer', 'active', 'assets/images/avatars/engineer5.jpg'),
(7, 'Ananya Roy', 'ananya@voltcraft.com', '$2y$10$lvYX/ulP4qHTghLUuadute8BIi5QYM7sxG4q8QSDC0dOwuX6iq9Om', '+91 94440 88221', 'engineer', 'active', 'assets/images/avatars/engineer6.jpg'),
(8, 'Rohan Mehta', 'customer@voltcraft.com', '$2y$10$5qllakEWqaJggtEsjGm5HuadmRsM07ybFD6dlYu12eFjuB4LSfjIS', '+91 98860 11223', 'customer', 'active', 'assets/images/avatars/customer1.jpg'),
(9, 'Neha Kapoor', 'neha@voltcraft.com', '$2y$10$5qllakEWqaJggtEsjGm5HuadmRsM07ybFD6dlYu12eFjuB4LSfjIS', '+91 98200 44556', 'customer', 'active', 'assets/images/avatars/customer2.jpg'),
(10, 'Arun Sundaram', 'arun@voltcraft.com', '$2y$10$5qllakEWqaJggtEsjGm5HuadmRsM07ybFD6dlYu12eFjuB4LSfjIS', '+91 98400 77889', 'customer', 'active', 'assets/images/avatars/customer3.jpg'),
(11, 'Pooja Gupta', 'pooja@voltcraft.com', '$2y$10$5qllakEWqaJggtEsjGm5HuadmRsM07ybFD6dlYu12eFjuB4LSfjIS', '+91 98100 22334', 'customer', 'active', 'assets/images/avatars/customer4.jpg');

-- Customers Table
INSERT INTO `customers` (`id`, `user_id`, `address`, `city`, `state`, `postal_code`) VALUES
(1, 8, '402, Skyline Residency, 100 Feet Rd, Indiranagar', 'Bangalore', 'Karnataka', '560038'),
(2, 9, 'Flat 12B, Sea Crest Towers, Bandra West', 'Mumbai', 'Maharashtra', '400050'),
(3, 10, '25/3, Karpagam Avenue, R.A. Puram', 'Chennai', 'Tamil Nadu', '600028'),
(4, 11, 'D-84, Hauz Khas Enclave', 'Delhi NCR', 'Delhi', '110016');

-- Engineers Table
INSERT INTO `engineers` (`id`, `user_id`, `title`, `bio`, `experience_years`, `qualification`, `location`, `hourly_rate`, `rating`, `total_reviews`, `availability_status`, `upi_id`, `upi_qr_image`, `is_verified`) VALUES
(1, 2, 'Senior Micro-Soldering & Embedded Specialist', 'Certified IPC master technician with 8+ years specializing in SMD rework, motherboard micro-soldering, and multi-layer PCB fault diagnostics.', 8, 'B.Tech Electronics & Comm, IPC-A-610 Master Certified', 'Bangalore, Indiranagar', 750.00, 4.95, 42, 'available', 'rajesh.volt@okhdfcbank', 'assets/images/qr/qr1.svg', 1),
(2, 3, 'Industrial Automation & PLC/VFD Systems Engineer', 'Specialist in Siemens, Allen-Bradley PLCs, variable frequency drives, servo actuators, and heavy factory telemetry circuits.', 6, 'M.Tech Industrial Electronics, Siemens TIA Certified', 'Mumbai, Andheri East', 900.00, 4.88, 35, 'available', 'priyanair@okaxis', 'assets/images/qr/qr2.svg', 1),
(3, 4, 'Solar Inverters & High-Capacity Battery Engineer', 'Expert in hybrid solar inverter diagnostics, grid-tie synchronization, lithium battery BMS balancing, and high-voltage DC protection.', 7, 'B.E. Electrical & Electronics, MNRE Grid Specialist', 'Delhi NCR, Gurgaon Sector 49', 650.00, 4.90, 58, 'available', 'amit.solar@paytm', 'assets/images/qr/qr3.svg', 1),
(4, 5, 'Drone Avionics & Robotics Hardware Engineer', 'Designing and troubleshooting brushless ESCs, flight controllers, LIDAR gimbal power distribution, and precision robotic joints.', 5, 'B.Tech Robotics & Automation, DGCA Avionics Associate', 'Hyderabad, Madhapur', 850.00, 4.75, 29, 'busy', 'sneha.robotics@icici', 'assets/images/qr/qr4.svg', 1),
(5, 6, 'Audiophile Hi-Fi & Tube Amplifier Restorer', 'Vintage and modern high-end audio hardware restoration, toroidal transformer winding, DAC signal isolation, and acoustic calibration.', 10, 'Audio Engineering Society (AES) Fellow, Dip. Sound Tech', 'Pune, Koregaon Park', 1000.00, 5.00, 64, 'available', 'vikram.audio@oksbi', 'assets/images/qr/qr5.svg', 1),
(6, 7, 'Smart Home IoT & Sensor Architecture Engineer', 'Deploying Zigbee/Thread automation nodes, microcontroller custom firmware (ESP32/STM32), and smart metering telemetry.', 4, 'B.E. Instrumentation Engineering, AWS IoT Certified', 'Chennai, OMR IT Corridor', 600.00, 4.82, 23, 'available', 'ananya.iot@ybl', 'assets/images/qr/qr6.svg', 1);

-- Services Table
INSERT INTO `services` (`id`, `name`, `slug`, `category`, `icon`, `description`, `base_price`, `estimated_time`, `image`, `is_active`) VALUES
(1, 'PCB Micro-Soldering & SMD Rework', 'pcb-micro-soldering', 'Hardware Engineering', 'cpu', 'Precision micro-soldering, short-circuit detection, BGA reballing, traces jumpering, and SMD component replacement on complex electronics boards.', 2500.00, '2-4 Hours', 'assets/images/services/pcb.jpg', 1),
(2, 'Smart Home IoT & Sensor Diagnostics', 'smart-home-iot', 'Automation', 'wifi', 'Comprehensive troubleshooting of smart automation controllers, Zigbee/Matter gateways, smart switches, capacitive sensors, and relay banks.', 1800.00, '1-3 Hours', 'assets/images/services/iot.jpg', 1),
(3, 'Industrial Automation & PLC / VFD Repair', 'industrial-automation-plc', 'Industrial', 'settings', 'On-site diagnostics for industrial motor drives, PLC ladder logic errors, AC/DC inverter drives, sensors, and SCADA interface cards.', 4500.00, '3-6 Hours', 'assets/images/services/industrial.jpg', 1),
(4, 'Solar Inverter & Battery BMS Servicing', 'solar-inverter-bms', 'Power Systems', 'sun', 'Health checks and repairs for solar charge controllers, hybrid inverters, MPPT tracking units, and lithium-ion Battery Management Systems.', 3200.00, '2-5 Hours', 'assets/images/services/solar.jpg', 1),
(5, 'High-End Audio & Amplifier Restoration', 'audio-amplifier-restoration', 'Audio Tech', 'volume-2', 'Restoration, bias calibration, noise hum removal, capacitor bank upgrades, and audio power amplifier tuning for audiophile sound systems.', 2800.00, '3-5 Hours', 'assets/images/services/audio.jpg', 1),
(6, 'Drone Avionics & ESC Telemetry Repair', 'drone-avionics-esc', 'Robotics', 'navigation', 'Electronic speed controllers (ESC) replacement, flight controller board diagnostics, sensor calibrations, telemetry antennas, and gimbal PCB repair.', 3500.00, '2-4 Hours', 'assets/images/services/drone.jpg', 1),
(7, 'EV Charger & Power Supply Diagnostics', 'ev-charger-diagnostics', 'Electric Vehicles', 'zap', 'Safety checks, board-level component repair, thermal runaway protection tests, and contactor maintenance for EV home and commercial chargers.', 3000.00, '2-4 Hours', 'assets/images/services/ev.jpg', 1),
(8, 'Smart Appliance Logic Board Repair', 'smart-appliance-logic-board', 'Appliances', 'hard-drive', 'Diagnostic and circuit-level repair for inverter refrigerators, microwave magnetrons, modern washing machine motherboards, and smart displays.', 2200.00, '1-3 Hours', 'assets/images/services/appliance.jpg', 1);

-- Engineer Services (Which engineer offers which services)
INSERT INTO `engineer_services` (`engineer_id`, `service_id`, `custom_rate`) VALUES
(1, 1, 2600.00), -- Rajesh: PCB
(1, 8, 2200.00), -- Rajesh: Appliance
(2, 3, 4800.00), -- Priya: Industrial
(2, 7, 3200.00), -- Priya: EV
(3, 4, 3000.00), -- Amit: Solar
(3, 7, 2900.00), -- Amit: EV
(4, 6, 3600.00), -- Sneha: Drone
(4, 1, 2700.00), -- Sneha: PCB
(5, 5, 2900.00), -- Vikram: Audio
(5, 1, 2500.00), -- Vikram: PCB
(6, 2, 1800.00), -- Ananya: IoT
(6, 8, 2100.00); -- Ananya: Appliance

-- Engineer Skills
INSERT INTO `engineer_skills` (`engineer_id`, `skill_name`, `proficiency`) VALUES
(1, 'BGA Reballing & Micro-soldering', 98),
(1, 'Oscilloscope & Logic Analyzer Debugging', 95),
(1, 'Multi-layer Trace Restoration', 92),
(1, 'Thermal Imaging Diagnostics', 94),
(2, 'Siemens & Allen-Bradley PLC Ladder Logic', 96),
(2, 'VFD Inverter Drive Calibration', 94),
(2, 'Modbus & Profibus Industrial Telemetry', 90),
(2, 'Three-Phase High Voltage Safety', 95),
(3, 'Hybrid Solar Inverter Topology', 96),
(3, 'Lithium LiFePO4 BMS Cell Balancing', 93),
(3, 'MPPT Tracking Algorithm Optimization', 89),
(3, 'High Voltage Surge Protection Circuits', 92),
(4, 'BLDC Motor ESC Signal Timing', 95),
(4, 'STM32 / PX4 Flight Controller Debug', 92),
(4, 'LIDAR & Optical Flow Sensors', 88),
(4, 'Carbon Fiber & High-G Shock Mounting', 86),
(5, 'Analog Tube & MOSFET Pre-amps', 99),
(5, 'Total Harmonic Distortion (THD) Tuning', 97),
(5, 'Toroidal Transformer Repair', 91),
(5, 'Audio DAC Signal Isolation', 94),
(6, 'ESP32 / Zigbee 3.0 / Matter Protocol', 96),
(6, 'Microcontroller Firmware Flashing', 92),
(6, 'Smart Relay PCB Layout & Testing', 90),
(6, 'Home Assistant & MQTT Integration', 95);

-- Service Requests
INSERT INTO `service_requests` (`id`, `request_code`, `customer_id`, `engineer_id`, `service_id`, `title`, `description`, `address`, `city`, `preferred_date`, `preferred_time`, `estimated_amount`, `status`) VALUES
(1, 'REQ-2026-101', 1, 1, 1, 'MacBook M2 Pro Liquid Spill Logic Board Corrosion', 'Spilled electrolyte drink on MacBook keyboard. Machine is completely unresponsive, no chime, 20V rail drawing 0.02A. Need ultrasonic cleaning and trace jumpering.', '402, Skyline Residency, 100 Feet Rd, Indiranagar', 'Bangalore', '2026-10-15', '11:00 AM', 3500.00, 'COMPLETED'),
(2, 'REQ-2026-102', 2, 2, 3, 'CNC Machine VFD Inverter Fault Code F0002 Overvoltage', 'Siemens Micromaster 440 VFD tripping during spindle deceleration. Suspected dynamic braking resistor or DC link capacitor bank failure.', 'Flat 12B, Sea Crest Towers, Bandra West', 'Mumbai', '2026-10-18', '02:00 PM', 5000.00, 'CONFIRMED'),
(3, 'REQ-2026-103', 3, 3, 4, '5kW Solar Hybrid Inverter Error 04 (Bus Voltage Low)', 'Luminous Solarverter 5kVA system shutting off when shifting to battery mode. Need BMS communication check and MPPT board replacement.', '25/3, Karpagam Avenue, R.A. Puram', 'Chennai', '2026-10-20', '10:00 AM', 4200.00, 'ACCEPTED'),
(4, 'REQ-2026-104', 4, 5, 5, 'Marantz Vintage Receiver Right Channel Severe Distortion', 'Restoration required for 1978 Marantz 2270 receiver. Hum on phono input and right speaker output produces distorted waveform under 100Hz.', 'D-84, Hauz Khas Enclave', 'Delhi NCR', '2026-10-22', '03:30 PM', 4000.00, 'PENDING'),
(5, 'REQ-2026-105', 1, 6, 2, 'Smart Living Room Automation Relays Ghost Triggering', 'Zigbee 4-channel solid state relays randomly toggling when heavy AC turns on. Suspected inductive kickback and lack of snubber diode circuit.', '402, Skyline Residency, 100 Feet Rd, Indiranagar', 'Bangalore', '2026-10-24', '11:30 AM', 2500.00, 'IN_PROGRESS'),
(6, 'REQ-2026-106', 2, 4, 6, 'DJI Matrice Quadcopter ESC Motor sync lost during takeoff', 'Motor 3 stutters upon arming and sends ESC error to ground station. Need oscilloscope testing on MOSFET gate driver outputs.', 'Flat 12B, Sea Crest Towers, Bandra West', 'Mumbai', '2026-10-26', '04:00 PM', 3800.00, 'REJECTED');

-- Bookings (Matching corresponding requests)
-- Note: Request 1 is COMPLETED, Request 2 is CONFIRMED, Request 3 is ACCEPTED (Awaiting payment), Request 5 is IN_PROGRESS
INSERT INTO `bookings` (`id`, `booking_code`, `request_id`, `customer_id`, `engineer_id`, `service_id`, `total_amount`, `advance_amount`, `remaining_amount`, `payment_status`, `booking_status`, `scheduled_at`, `completed_at`, `notes`) VALUES
(1, 'BK-2026-701', 1, 1, 1, 1, 3500.00, 700.00, 2800.00, 'FULLY_PAID', 'COMPLETED', '2026-10-15 11:00:00', '2026-10-15 15:30:00', 'Replaced two corroded capacitors on PPBUS_G3H rail. Ultrasonic bath completed. System boots normally.'),
(2, 'BK-2026-702', 2, 2, 2, 3, 5000.00, 1000.00, 4000.00, 'ADVANCE_PAID', 'CONFIRMED', '2026-10-18 14:00:00', NULL, 'Customer deposited 20% advance (₹1,000). Engineer confirmed on-site diagnostic kit ready.'),
(3, 'BK-2026-703', 5, 1, 6, 2, 2500.00, 500.00, 2000.00, 'ADVANCE_PAID', 'IN_PROGRESS', '2026-10-24 11:30:00', NULL, 'Advance received. Engineer is currently installing RC snubber circuits across relay outputs.');

-- Payments
INSERT INTO `payments` (`id`, `transaction_ref`, `booking_id`, `customer_id`, `engineer_id`, `amount`, `payment_type`, `payment_method`, `payment_status`, `gateway_payment_id`, `gateway_order_id`, `notes`) VALUES
(1, 'TXN-VOLT-981241', 1, 1, 1, 700.00, 'ADVANCE_20', 'UPI', 'SUCCESS', 'pay_volt_981241_adv', 'order_volt_001', '20% Advance Payment for Request REQ-2026-101 via UPI (rajesh.volt@okhdfcbank)'),
(2, 'TXN-VOLT-981242', 1, 1, 1, 2800.00, 'REMAINING_80', 'CARD', 'SUCCESS', 'pay_volt_981242_bal', 'order_volt_001', 'Remaining 80% Final Payment upon successful logic board repair completion.'),
(3, 'TXN-VOLT-982055', 2, 2, 2, 1000.00, 'ADVANCE_20', 'UPI', 'SUCCESS', 'pay_volt_982055_adv', 'order_volt_002', '20% Advance Booking Fee for VFD Diagnosis via UPI (priyanair@okaxis)'),
(4, 'TXN-VOLT-983190', 3, 1, 6, 500.00, 'ADVANCE_20', 'UPI', 'SUCCESS', 'pay_volt_983190_adv', 'order_volt_003', '20% Advance Booking Fee for IoT Relay diagnostics via UPI (ananya.iot@ybl)');

-- Reviews
INSERT INTO `reviews` (`id`, `booking_id`, `customer_id`, `engineer_id`, `rating`, `comment`) VALUES
(1, 1, 1, 1, 5, 'Rajesh is an absolute wizard! Apple store told me my MacBook was dead and asked for ₹65,000 for a motherboard swap. Rajesh restored the board and ultrasonic cleaned it in 3 hours for ₹3,500! Amazing 3D booking experience too.'),
(2, 2, 2, 2, 5, 'Priya was prompt, professional, and diagnosed the factory line fault right on the dot. Highly recommended for heavy PLC and automation equipment.'),
(3, 3, 1, 6, 5, 'Ananya arrived on time and isolated the electrical noise immediately. The automated dashboard notifications kept me informed at each step.');

-- Notifications
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`) VALUES
(1, 8, 'booking_completed', 'Service Completed Successfully', 'Your logic board repair service (REQ-2026-101) with Rajesh Sharma has been marked as Completed.', 'customer/booking-details.php?id=1', 1),
(2, 9, 'booking_confirmed', '20% Advance Paid - Booking Confirmed', 'Your 20% advance payment of ₹1,000 for VFD repair has been verified. Engineer scheduled for Oct 18.', 'customer/booking-details.php?id=2', 0),
(3, 10, 'request_accepted', 'Engineer Accepted Your Request!', 'Amit Verma has accepted your Solar Inverter service request. Please pay the 20% advance (₹840) to confirm.', 'customer/payment.php?request_id=3', 0),
(4, 2, 'payment_received', 'New Booking Advance Received', 'Rohan Mehta has paid ₹700 (20% advance) for booking BK-2026-701.', 'engineer/bookings.php', 1),
(5, 3, 'payment_received', 'Booking Advance Confirmed', 'Neha Kapoor has paid ₹1,000 advance. Your service is confirmed for Oct 18.', 'engineer/bookings.php', 0),
(6, 4, 'new_request', 'New Service Request Received', 'Arun Sundaram submitted a request for 5kW Solar Hybrid Inverter Diagnostics.', 'engineer/requests.php', 1);

-- Complaints
INSERT INTO `complaints` (`id`, `booking_id`, `user_id`, `subject`, `description`, `status`, `resolution`) VALUES
(1, 2, 9, 'Request for earlier appointment slot', 'Client requested if engineer could arrive 30 minutes earlier due to factory shift transition.', 'RESOLVED', 'Engineer notified and confirmed 1:30 PM arrival slot.');

-- Contact Messages
INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`) VALUES
(1, 'Kavita Chawla', 'kavita@techsol.in', '+91 98980 12345', 'Enterprise AMC for Laboratory Electronics', 'We run a hardware prototyping lab with 25 test benches. Looking for recurring quarterly maintenance.', 'NEW');

-- =========================================================================
-- CUSTOMER-TO-CUSTOMER PROPERTY MODULE TABLES & SEED DATA
-- =========================================================================

-- Table 14: customer_properties
CREATE TABLE IF NOT EXISTS `customer_properties` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `property_code` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `property_type` ENUM('flat', 'apartment', 'independent_house', 'studio', 'villa', 'commercial') NOT NULL DEFAULT 'flat',
  `listing_type` ENUM('rent', 'sale') NOT NULL DEFAULT 'rent',
  `price` DECIMAL(12,2) NOT NULL,
  `security_deposit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `advance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `address` TEXT NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `bedrooms` INT DEFAULT 1,
  `bathrooms` INT DEFAULT 1,
  `area_sqft` INT DEFAULT NULL,
  `facilities` TEXT DEFAULT NULL,
  `images` TEXT DEFAULT NULL,
  `primary_image` VARCHAR(255) DEFAULT NULL,
  `description` TEXT NOT NULL,
  `whatsapp_number` VARCHAR(30) NOT NULL,
  `allow_direct_calls` TINYINT(1) NOT NULL DEFAULT 1,
  `phone_number` VARCHAR(30) DEFAULT NULL,
  `cancellation_policy` TEXT DEFAULT NULL,
  `verification_status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  `verification_notes` TEXT DEFAULT NULL,
  `availability_status` ENUM('AVAILABLE', 'VISIT_SCHEDULED', 'RESERVED', 'RENTED_OR_SOLD', 'INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
  `views_count` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_prop_cust` (`customer_id`),
  INDEX `idx_prop_status` (`availability_status`),
  INDEX `idx_prop_verify` (`verification_status`),
  INDEX `idx_prop_city` (`city`),
  INDEX `idx_prop_type` (`property_type`),
  CONSTRAINT `fk_prop_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 15: property_bookings (C2C Bookings & Visits)
CREATE TABLE IF NOT EXISTS `property_bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_code` VARCHAR(30) NOT NULL UNIQUE,
  `property_id` INT NOT NULL,
  `requester_customer_id` INT NOT NULL,
  `owner_customer_id` INT NOT NULL,
  `request_type` ENUM('visit', 'rent_booking', 'purchase_offer') NOT NULL DEFAULT 'visit',
  `scheduled_visit_date` DATE DEFAULT NULL,
  `scheduled_visit_time` VARCHAR(50) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `offered_price` DECIMAL(12,2) DEFAULT NULL,
  `advance_amount` DECIMAL(12,2) DEFAULT 0.00,
  `security_deposit` DECIMAL(12,2) DEFAULT 0.00,
  `status` ENUM('Request Submitted', 'Pending Owner Approval', 'Accepted', 'Rejected', 'Visit Scheduled', 'Reserved', 'Rented or Sold', 'Cancelled') NOT NULL DEFAULT 'Request Submitted',
  `owner_response` TEXT DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_pbk_prop` (`property_id`),
  INDEX `idx_pbk_req` (`requester_customer_id`),
  INDEX `idx_pbk_owner` (`owner_customer_id`),
  INDEX `idx_pbk_status` (`status`),
  CONSTRAINT `fk_pbk_property` FOREIGN KEY (`property_id`) REFERENCES `customer_properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbk_requester` FOREIGN KEY (`requester_customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pbk_owner` FOREIGN KEY (`owner_customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 16: property_saved (Bookmarks)
CREATE TABLE IF NOT EXISTS `property_saved` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `property_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_saved_prop` (`customer_id`, `property_id`),
  CONSTRAINT `fk_ps_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_property` FOREIGN KEY (`property_id`) REFERENCES `customer_properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 17: property_reports (Safety, Misleading Price & Violations)
CREATE TABLE IF NOT EXISTS `property_reports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `reason` VARCHAR(150) NOT NULL,
  `details` TEXT NOT NULL,
  `status` ENUM('PENDING', 'REVIEWED', 'DISMISSED', 'ACTIONED') NOT NULL DEFAULT 'PENDING',
  `admin_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_prep_prop` (`property_id`),
  INDEX `idx_prep_status` (`status`),
  CONSTRAINT `fk_prep_property` FOREIGN KEY (`property_id`) REFERENCES `customer_properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 18: property_inquiries (Contact History Log)
CREATE TABLE IF NOT EXISTS `property_inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `property_id` INT NOT NULL,
  `sender_user_id` INT DEFAULT NULL,
  `channel` ENUM('WHATSAPP', 'CALL', 'WEB_INQUIRY') NOT NULL DEFAULT 'WHATSAPP',
  `message_summary` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pinq_prop` (`property_id`),
  CONSTRAINT `fk_pinq_property` FOREIGN KEY (`property_id`) REFERENCES `customer_properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

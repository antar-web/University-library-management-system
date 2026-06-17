-- ============================================================
-- Library Management System - Database Schema
-- PHP & MySQL (XAMPP Compatible)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `library_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `library_db`;

-- ============================================================
-- Table: admins
-- Stores admin login credentials with hashed passwords
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `username` (`username`),
    UNIQUE INDEX `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: admin / admin123 (CHANGE IN PRODUCTION)
INSERT INTO `admins` (`username`, `email`, `password`) VALUES
('admin', 'admin@library.com', '$2y$10$jZPtL/IZhQUkqIqHfSDtLOBlVvMv9Ez5Uvcz4dRyapfBLcW6qgMn6');

-- ============================================================
-- Table: categories
-- Book categories (e.g., Fiction, Science, History)
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample categories
INSERT INTO `categories` (`name`) VALUES
('Fiction'),
('Non-Fiction'),
('Science'),
('Technology'),
('History'),
('Mathematics'),
('Literature'),
('Philosophy'),
('Arts'),
('Biography');

-- ============================================================
-- Table: books
-- Stores all book inventory details
-- ============================================================
CREATE TABLE IF NOT EXISTS `books` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `author` VARCHAR(255) NOT NULL,
    `isbn` VARCHAR(20) NOT NULL,
    `category_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'Total copies owned',
    `available` INT(11) NOT NULL DEFAULT 1 COMMENT 'Copies currently available',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `isbn` (`isbn`),
    INDEX `category_id` (`category_id`),
    INDEX `title` (`title`),
    CONSTRAINT `fk_books_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: members
-- Registered library members
-- ============================================================
CREATE TABLE IF NOT EXISTS `members` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `member_id` VARCHAR(20) NOT NULL COMMENT 'Unique member identifier (e.g., LIB-1001)',
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `reg_date` DATE NOT NULL COMMENT 'Registration date',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `member_id` (`member_id`),
    UNIQUE INDEX `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: issued_books
-- Tracks book issuance, returns, and fines
-- ============================================================
CREATE TABLE IF NOT EXISTS `issued_books` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `book_id` INT(11) NOT NULL,
    `member_id` INT(11) NOT NULL,
    `issue_date` DATE NOT NULL COMMENT 'Date the book was issued',
    `expected_return_date` DATE NOT NULL COMMENT 'Expected return date (issue_date + 14 days)',
    `actual_return_date` DATE DEFAULT NULL COMMENT 'Actual return date (NULL if not returned)',
    `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Calculated fine in BDT',
    `status` ENUM('Pending','issued','Return Pending','returned','Rejected') NOT NULL DEFAULT 'Pending' COMMENT 'Pending=awaiting approval, issued=borrowed, Return Pending=return requested, returned=completed, Rejected=denied',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    INDEX `book_id` (`book_id`),
    INDEX `member_id` (`member_id`),
    INDEX `status` (`status`),
    CONSTRAINT `fk_issued_book` FOREIGN KEY (`book_id`)
        REFERENCES `books` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_issued_member` FOREIGN KEY (`member_id`)
        REFERENCES `members` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: students
-- Self-registered students for the library portal
-- ============================================================
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` VARCHAR(20) NOT NULL COMMENT 'Unique student ID (e.g., 2023001)',
    `name` VARCHAR(255) NOT NULL,
    `department` VARCHAR(100) NOT NULL COMMENT 'Department name',
    `phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Phone number',
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MIGRATIONS — Run these if you already have the database created
-- ============================================================

-- 1) Fix students table: add phone column, rename dept_name -> department
ALTER TABLE `students`
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Phone number' AFTER `name`;
ALTER TABLE `students`
    CHANGE COLUMN `dept_name` `department` VARCHAR(100) NOT NULL COMMENT 'Department name';

-- 2) Update issued_books status ENUM to include new workflow states
ALTER TABLE `issued_books`
    MODIFY COLUMN `status` ENUM('Pending','issued','Return Pending','returned','Rejected')
    NOT NULL DEFAULT 'Pending';

-- 3) Create departments table for dynamic dropdown management
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `dept_name` VARCHAR(100) NOT NULL COMMENT 'Department name',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `dept_name` (`dept_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Fix collation mismatch between students.department and departments.dept_name
ALTER TABLE `departments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `students` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Seed departments
INSERT IGNORE INTO `departments` (`dept_name`) VALUES
('CSE'),
('EEE'),
('IPE'),
('BBA'),
('LAW'),
('ENGLISH'),
('BANGLA'),
('PHARMACY'),
('BIOCHEMISTRY'),
('MICROBIOLOGY'),
('PSYCHOLOGY'),
('SOCIOLOGY'),
('ECONOMICS'),
('POLITICAL SCIENCE'),
('PUBLIC ADMINISTRATION'),
('FISHERIES'),
('AGRIBUSINESS'),
('NURSING'),
('PHYSIOTHERAPY');

-- ============================================================
-- Sample Data (Optional - for testing)
-- ============================================================

-- ============================================================

-- Sample books
INSERT INTO `books` (`title`, `author`, `isbn`, `category_id`, `quantity`, `available`) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '9780743273565', 1, 5, 5),
('A Brief History of Time', 'Stephen Hawking', '9780553380163', 3, 3, 3),
('The Art of Programming', 'Donald Knuth', '9780201896831', 4, 2, 2),
('To Kill a Mockingbird', 'Harper Lee', '9780061120084', 1, 4, 4),
('The Origin of Species', 'Charles Darwin', '9780451529060', 3, 2, 2),
('Clean Code', 'Robert C. Martin', '9780132350884', 4, 3, 3),
('1984', 'George Orwell', '9780451524935', 1, 6, 6),
('Sapiens', 'Yuval Noah Harari', '9780062316097', 2, 3, 3),
('The Republic', 'Plato', '9780140455113', 8, 2, 2),
('Introduction to Algorithms', 'Thomas H. Cormen', '9780262033848', 5, 2, 2);

-- Sample members
INSERT INTO `members` (`member_id`, `name`, `email`, `phone`, `reg_date`) VALUES
('LIB-1001', 'Rahman Karim', 'rahman@email.com', '01711111111', '2026-01-15'),
('LIB-1002', 'Fatima Begum', 'fatima@email.com', '01722222222', '2026-02-10'),
('LIB-1003', 'Hasan Mahmud', 'hasan@email.com', '01733333333', '2026-03-05'),
('LIB-1004', 'Nusrat Jahan', 'nusrat@email.com', '01744444444', '2026-03-20'),
('LIB-1005', 'Kabir Hossain', 'kabir@email.com', '01755555555', '2026-04-01');

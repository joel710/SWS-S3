-- SQL Schema for the Object Storage Service
-- This script is designed for MySQL.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

--
-- Table structure for table `projects`
--
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `api_key` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `buckets`
--
DROP TABLE IF EXISTS `buckets`;
CREATE TABLE `buckets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `is_public` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `buckets_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `objects`
--
DROP TABLE IF EXISTS `objects`;
CREATE TABLE `objects` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bucket_id` INT NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `mime_type` VARCHAR(255) NOT NULL,
  `size` INT NOT NULL,
  `file_hash` VARCHAR(64) DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `optimized_at` DATETIME DEFAULT NULL,
  `thumbnails_available` BOOLEAN DEFAULT FALSE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bucket_id` (`bucket_id`),
  KEY `file_hash` (`file_hash`),
  KEY `mime_type` (`mime_type`),
  KEY `optimized_at` (`optimized_at`),
  CONSTRAINT `objects_ibfk_1` FOREIGN KEY (`bucket_id`) REFERENCES `buckets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

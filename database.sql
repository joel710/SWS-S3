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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Table structure for table `optimization_jobs`
--
DROP TABLE IF EXISTS `optimization_jobs`;

CREATE TABLE `optimization_jobs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `object_id` INT NOT NULL,
    `job_type` ENUM(
        'thumbnail_generation',
        'image_optimization',
        'webp_conversion'
    ) NOT NULL,
    `status` ENUM(
        'pending',
        'processing',
        'completed',
        'failed'
    ) DEFAULT 'pending',
    `priority` INT DEFAULT 0,
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `retry_count` INT DEFAULT 0,
    `max_retries` INT DEFAULT 3,
    `progress` INT DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `object_id` (`object_id`),
    KEY `status` (`status`),
    KEY `priority` (`priority`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `optimization_jobs_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `objects` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Table structure for table `rate_limit_requests`
--
DROP TABLE IF EXISTS `rate_limit_requests`;

CREATE TABLE `rate_limit_requests` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `identifier` VARCHAR(255) NOT NULL,
    `request_time` DATETIME NOT NULL,
    `endpoint` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `identifier` (`identifier`),
    KEY `request_time` (`request_time`),
    KEY `ip_address` (`ip_address`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Table structure for table `rate_limit_violations`
--
DROP TABLE IF EXISTS `rate_limit_violations`;

CREATE TABLE `rate_limit_violations` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `violation_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `violation_type` ENUM(
        'rate_limit',
        'concurrent_requests',
        'request_size'
    ) DEFAULT 'rate_limit',
    `endpoint` VARCHAR(255) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ip_address` (`ip_address`),
    KEY `violation_time` (`violation_time`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Table structure for table `blocked_ips`
--
DROP TABLE IF EXISTS `blocked_ips`;

CREATE TABLE `blocked_ips` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `block_reason` TEXT DEFAULT NULL,
    `blocked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `blocked_until` DATETIME DEFAULT NULL,
    `is_permanent` BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_ip_address` (`ip_address`),
    KEY `blocked_until` (`blocked_until`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Table structure for table `active_requests`
--
DROP TABLE IF EXISTS `active_requests`;

CREATE TABLE `active_requests` (
    `request_id` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `endpoint` VARCHAR(255) DEFAULT NULL,
    `start_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`request_id`),
    KEY `ip_address` (`ip_address`),
    KEY `start_time` (`start_time`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

--
-- Add indexes for better performance on existing tables
--
ALTER TABLE `projects` ADD INDEX `created_at` (`created_at`);

ALTER TABLE `buckets` ADD INDEX `created_at` (`created_at`);

ALTER TABLE `buckets` ADD INDEX `is_public` (`is_public`);

ALTER TABLE `objects` ADD INDEX `created_at` (`created_at`);

ALTER TABLE `objects` ADD INDEX `size` (`size`);

SET foreign_key_checks = 1;
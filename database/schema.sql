-- =============================================================================
-- Listening Community – Suicide Prevention Campaign (LC-SPC)
-- Reference Schema Export (Authoritative structure is managed via migrations/)
-- Target Database: u806388046_LC
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- -----------------------------------------------------------------------------
-- Table structure for migrations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `batch` INT UNSIGNED NOT NULL,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



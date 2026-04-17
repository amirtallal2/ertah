-- =====================================================
-- نظام الإعلامات والإشعارات المتقدم
-- Advanced Notification System
-- Created: 2026-03-12
-- =====================================================

-- =====================================================
-- جدول سجل الإعلامات المرسلة (Notification Logs)
-- =====================================================
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `channel` ENUM('email', 'whatsapp', 'both') NOT NULL DEFAULT 'email',
    `event_type` VARCHAR(80) NOT NULL COMMENT 'new_order, new_complaint, incomplete_order, new_furniture_request, new_container_request, order_status_change',
    `recipient_email` VARCHAR(255) DEFAULT NULL,
    `recipient_phone` VARCHAR(30) DEFAULT NULL,
    `recipient_name` VARCHAR(150) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `body` TEXT DEFAULT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL COMMENT 'order, complaint, furniture_request, container_request',
    `reference_id` INT DEFAULT NULL,
    `status` ENUM('sent', 'failed', 'queued') NOT NULL DEFAULT 'queued',
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notif_logs_event` (`event_type`),
    INDEX `idx_notif_logs_status` (`status`),
    INDEX `idx_notif_logs_reference` (`reference_type`, `reference_id`),
    INDEX `idx_notif_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- جدول مستلمي الإعلامات (Notification Recipients)
-- =====================================================
CREATE TABLE IF NOT EXISTS `notification_recipients` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(30) DEFAULT NULL COMMENT 'WhatsApp number with country code',
    `receive_new_orders` TINYINT(1) NOT NULL DEFAULT 1,
    `receive_complaints` TINYINT(1) NOT NULL DEFAULT 1,
    `receive_furniture` TINYINT(1) NOT NULL DEFAULT 1,
    `receive_containers` TINYINT(1) NOT NULL DEFAULT 1,
    `receive_incomplete` TINYINT(1) NOT NULL DEFAULT 0,
    `channels` VARCHAR(50) NOT NULL DEFAULT 'email' COMMENT 'email, whatsapp, both',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_notif_recipients_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

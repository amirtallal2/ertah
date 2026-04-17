-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- مضيف: localhost
-- وقت الجيل: 05 أبريل 2026 الساعة 22:39
-- إصدار الخادم: 10.11.10-MariaDB-log
-- نسخة PHP: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- قاعدة بيانات: `ertah_admin`
--

-- --------------------------------------------------------

--
-- بنية الجدول `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `model`, `model_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 06:27:00'),
(2, 1, 'add_banner', 'banners', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 08:00:21'),
(3, 1, 'update_banner', 'banners', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 08:00:30'),
(4, 1, 'toggle_user_status', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 08:22:32'),
(5, 1, 'update_wallet', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 08:24:17'),
(6, 1, 'update_category', 'service_categories', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-27 08:41:17'),
(7, 1, 'update_banner', 'banners', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 11:50:54'),
(8, 1, 'update_profile', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 11:51:24'),
(9, 1, 'update_profile', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 11:51:39'),
(10, 1, 'update_points', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 12:06:44'),
(11, 1, 'toggle_user_status', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 12:09:22'),
(12, 1, 'update_points', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 12:19:02'),
(13, 1, 'update_wallet', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 12:19:20'),
(14, 1, 'update_wallet', 'users', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 12:26:05'),
(15, 1, 'add_banner', 'banners', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 12:12:55'),
(16, 1, 'add_banner', 'banners', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 12:14:31'),
(17, 1, 'add_banner', 'banners', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 12:14:43'),
(18, 1, 'add_offer', 'offers', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 13:22:01'),
(19, 1, 'update_offer', 'offers', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 13:32:00'),
(20, 1, 'update_offer', 'offers', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 13:36:23'),
(21, 1, 'update_offer', 'offers', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 13:53:36'),
(22, 1, 'update_offer', 'offers', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-07 14:04:40'),
(23, 1, 'update_offer', 'offers', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:39:26'),
(24, 1, 'update_offer', 'offers', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:39:43'),
(25, 1, 'update_offer', 'offers', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:39:50'),
(26, 1, 'update_offer', 'offers', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:39:57'),
(27, 1, 'update_category', 'service_categories', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:40:47'),
(28, 1, 'update_category', 'service_categories', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:41:49'),
(29, 1, 'update_category', 'service_categories', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:42:19'),
(30, 1, 'update_category', 'service_categories', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:43:48'),
(31, 1, 'update_category', 'service_categories', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:44:40'),
(32, 1, 'update_category', 'service_categories', 7, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:45:42'),
(33, 1, 'update_category', 'service_categories', 8, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 07:47:33'),
(34, 1, 'update_service', 'services', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:07:58'),
(35, 1, 'update_service', 'services', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:08:10'),
(36, 1, 'update_service', 'services', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:08:21'),
(37, 1, 'update_service', 'services', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:08:30'),
(38, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 03:18:57'),
(39, 1, 'add_service', 'services', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 03:20:07'),
(40, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'curl/8.7.1', '2026-02-18 03:20:17'),
(41, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'curl/8.7.1', '2026-02-18 03:20:25'),
(42, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'curl/8.7.1', '2026-02-18 03:20:44'),
(43, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'curl/8.7.1', '2026-02-18 03:22:56'),
(44, 1, 'delete_user', 'users', 1, '{\"full_name\":null}', '{\"deleted\":true}', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 03:53:21'),
(45, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 04:29:55'),
(46, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 04:57:26'),
(47, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 04:58:35'),
(48, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:02:34'),
(49, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:02:46'),
(50, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:02:58'),
(51, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:03:29'),
(52, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:11:37'),
(53, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:12:05'),
(54, 1, 'update_complaint', 'complaints', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:21:01'),
(55, 1, 'update_complaint', 'complaints', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 05:48:02'),
(56, 1, 'update_complaint', 'complaints', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 08:28:25'),
(57, 1, 'update_complaint', 'complaints', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 08:29:28'),
(58, 1, 'add_store', 'stores', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 08:34:52'),
(59, 1, 'update_spare_part', 'spare_parts', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 08:35:16'),
(60, 1, 'add_category', 'service_categories', 9, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 08:52:27'),
(61, 1, 'update_category', 'service_categories', 9, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:02:50'),
(62, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:16:27'),
(63, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:17:02'),
(64, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:20:03'),
(65, 1, 'update_order_confirmation', 'orders', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:52:39'),
(66, 1, 'delete_banner', 'banners', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 10:12:30'),
(67, 1, 'delete_banner', 'banners', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 10:12:34'),
(68, 1, 'delete_banner', 'banners', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 10:12:39'),
(69, 1, 'update_banner', 'banners', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 10:13:19'),
(70, 1, 'update_banner', 'banners', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 10:32:50'),
(71, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 11:27:18'),
(72, 1, 'set_estimate', 'orders', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 20:47:54'),
(73, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-21 21:03:23'),
(74, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:44:07'),
(75, 1, 'add_furniture_service', 'furniture_services', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 12:45:38'),
(76, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 20:41:44'),
(77, 1, 'send_notification', 'notifications', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 20:43:55'),
(78, 1, 'send_notification', 'notifications', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 20:50:24'),
(79, 1, 'send_notification', 'notifications', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 20:50:33'),
(80, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0}\"', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 21:12:05'),
(81, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"http:\\\\\\/\\\\\\/localhost\\\\\\/ertah\\\\\\/admin-panel\\\\\\/uploads\\\\\\/notifications\\\\\\/69a4b00a1a307_1772400650.jpeg\\\"}\"', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 21:30:50'),
(82, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 22:11:24'),
(83, 1, 'update_settings', 'settings', 0, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 13:35:15'),
(84, 1, 'login', 'admins', 1, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/145.0.0.0 Safari/537.36', '2026-03-04 14:07:54'),
(85, 1, 'login', 'admins', 1, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/145.0.0.0 Safari/537.36', '2026-03-04 14:10:21'),
(86, 1, 'login', 'admins', 1, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/145.0.0.0 Safari/537.36', '2026-03-04 14:11:35'),
(87, 1, 'login', 'admins', 1, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/145.0.0.0 Safari/537.36', '2026-03-04 14:12:46'),
(88, 1, 'login', 'admins', 1, NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/145.0.0.0 Safari/537.36', '2026-03-04 14:14:24'),
(89, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 02:19:21'),
(90, 1, 'sync_myfatoorah_status', 'orders', 11, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 02:20:05'),
(91, 1, 'update_user_info', 'users', 17, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 02:22:43'),
(92, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'curl/8.7.1', '2026-03-05 02:28:28'),
(93, 1, 'login', 'admins', 1, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:31:11'),
(94, 1, 'sync_myfatoorah_status', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:32:09'),
(95, 1, 'set_estimate', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:33:40'),
(96, 1, 'update_order_confirmation', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:33:41'),
(97, 1, 'update_order_status', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:33:42'),
(98, 1, 'update_order_status', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:33:43'),
(99, 1, 'sync_myfatoorah_status', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:33:43'),
(100, 1, 'sync_myfatoorah_status', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:36:19'),
(101, 1, 'update_order_status', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:38:49'),
(102, 1, 'update_order_status', 'orders', 11, NULL, NULL, '127.0.0.1', 'curl/8.7.1', '2026-03-05 02:38:50'),
(103, 1, 'create_service_area', 'service_areas', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 03:32:30'),
(104, 1, 'login', 'admins', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 18:35:48'),
(105, 1, 'login', 'admins', 1, NULL, NULL, '102.44.221.18', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 17:10:28'),
(106, 1, 'login', 'admins', 1, NULL, NULL, '197.167.49.175', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 17:16:37'),
(107, 1, 'update_commission', 'providers', 16, NULL, NULL, '197.167.49.175', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 17:17:12'),
(108, 1, 'update_membership', 'users', 33, NULL, '{\"membership_level\":\"vip\"}', '197.167.49.175', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 17:17:36'),
(109, 1, 'update_settings', 'settings', 0, NULL, NULL, '102.44.221.18', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 17:41:57'),
(110, 1, 'login', 'admins', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:14:32'),
(111, 1, 'update_profile', 'admins', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:26:01'),
(112, 1, 'update_membership', 'users', 34, NULL, '{\"membership_level\":\"vip\"}', '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:27:22'),
(113, 1, 'update_points', 'users', 34, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:28:49'),
(114, 1, 'update_points', 'users', 34, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:29:00'),
(115, 1, 'update_points', 'users', 34, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:29:13'),
(116, 1, 'update_wallet', 'users', 34, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:29:29'),
(117, 1, 'approve_provider', 'providers', 17, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:30:20'),
(118, 1, 'update_commission', 'providers', 17, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:31:18'),
(119, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"\\\"}\"', '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:37:46'),
(120, 1, 'delete_furniture_area', 'furniture_areas', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:38:59'),
(121, 1, 'create_service_area', 'service_areas', 4, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:44:16'),
(122, 1, 'delete_category', 'service_categories', 10, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:49:43'),
(123, 1, 'update_order_status', 'orders', 14, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:50:00'),
(124, 1, 'update_furniture_service', 'furniture_services', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:50:46'),
(125, 1, 'add_furniture_service', 'furniture_services', 2, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:51:50'),
(126, 1, 'delete_furniture_area', 'furniture_areas', 3, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:52:03'),
(127, 1, 'add_furniture_service', 'furniture_services', 3, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:54:08'),
(128, 1, 'update_category', 'service_categories', 9, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:56:49'),
(129, 1, 'update_order_status', 'orders', 15, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 22:57:04'),
(130, 1, 'update_category', 'service_categories', 9, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:00:02'),
(131, 1, 'add_container_store', 'container_stores', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:01:20'),
(132, 1, 'add_container_service', 'container_services', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-08 23:02:32'),
(133, 1, 'update_category', 'service_categories', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 01:56:01'),
(134, 1, 'update_category', 'service_categories', 2, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 01:56:37'),
(135, 1, 'update_category', 'service_categories', 3, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 01:57:00'),
(136, 1, 'update_category', 'service_categories', 4, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 01:57:22'),
(137, 1, 'update_category', 'service_categories', 4, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 01:57:24'),
(138, 1, 'update_category', 'service_categories', 5, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-09 01:57:40'),
(139, 1, 'update_settings', 'settings', 0, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:18:55'),
(140, 1, 'update_settings', 'settings', 0, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:23:35'),
(141, 1, 'update_settings', 'settings', 0, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:24:51'),
(142, 1, 'update_settings', 'settings', 0, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:25:18'),
(143, 1, 'set_estimate', 'orders', 21, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:27:35'),
(144, 1, 'update_order_confirmation', 'orders', 21, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:27:52'),
(145, 1, 'update_order_confirmation', 'orders', 21, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:28:09'),
(146, 1, 'assign_provider', 'orders', 21, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:28:37'),
(147, 1, 'assign_provider', 'orders', 21, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:30:41'),
(148, 1, 'assign_provider', 'orders', 22, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:38:53'),
(149, 1, 'update_spare_part', 'spare_parts', 4, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:42:41'),
(150, 1, 'add_spare_part', 'spare_parts', 5, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:45:09'),
(151, 1, 'delete_spare_part', 'spare_parts', 5, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:45:32'),
(152, 1, 'update_points', 'users', 34, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:47:46'),
(153, 1, 'update_points', 'users', 34, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:48:40'),
(154, 1, 'update_points', 'users', 34, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:52:25'),
(155, 1, 'add_promo_code', 'promo_codes', 1, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:56:54'),
(156, 1, 'update_banner', 'banners', 4, NULL, NULL, '41.130.138.198', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 04:58:40'),
(157, 1, 'update_banner', 'banners', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:15:05'),
(158, 1, 'update_banner', 'banners', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:21:20'),
(159, 1, 'update_banner', 'banners', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:22:05'),
(160, 1, 'add_banner', 'banners', 6, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:32:44'),
(161, 1, 'update_banner', 'banners', 6, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:33:42'),
(162, 1, 'update_banner', 'banners', 6, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:34:16'),
(163, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:36:32'),
(164, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:52:31'),
(165, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:53:14'),
(166, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 18:59:19'),
(167, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:06:50'),
(168, 1, 'update_furniture_service', 'furniture_services', 2, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:13:00'),
(169, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:27:59'),
(170, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:29:12'),
(171, 1, 'update_promo_code', 'promo_codes', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:33:36'),
(172, 1, 'update_promo_code', 'promo_codes', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:34:53'),
(173, 1, 'delete_banner', 'banners', 4, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:35:32'),
(174, 1, 'update_promo_code', 'promo_codes', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:36:18'),
(175, 1, 'delete_service', 'services', 5, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:41:04'),
(176, 1, 'delete_service_area', 'service_areas', 3, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:41:33'),
(177, 1, 'update_category', 'service_categories', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:46:03'),
(178, 1, 'update_category', 'service_categories', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:46:12'),
(179, 1, 'add_category', 'service_categories', 12, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:46:54'),
(180, 1, 'update_category', 'service_categories', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:47:18'),
(181, 1, 'add_category', 'service_categories', 13, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:48:21'),
(182, 1, 'add_category', 'service_categories', 14, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:49:02'),
(183, 1, 'update_category', 'service_categories', 14, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:49:14'),
(184, 1, 'add_category', 'service_categories', 15, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:49:49'),
(185, 1, 'add_category', 'service_categories', 16, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:50:41'),
(186, 1, 'login', 'admins', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-03-09 19:52:36'),
(187, 1, 'add_category', 'service_categories', 17, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:54:37'),
(188, 1, 'update_service', 'services', 2, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 19:58:57'),
(189, 1, 'add_service', 'services', 6, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 20:06:12'),
(190, 1, 'add_service', 'services', 7, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 20:07:14'),
(191, 1, 'add_problem_detail_option', 'problem_detail_options', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 20:14:57'),
(192, 1, 'add_problem_detail_option', 'problem_detail_options', 2, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 20:15:30'),
(193, 1, 'add_problem_detail_option', 'problem_detail_options', 3, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-09 20:15:52'),
(194, 1, 'delete_user', 'users', 34, '{\"full_name\":\"\\u062d\\u0645\\u062f\\u064a\"}', '{\"deleted\":true}', '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 00:39:01'),
(195, 1, 'delete_provider', 'providers', 17, '{\"full_name\":\"\\u0639\\u0628\\u0627\\u0633 \\u062a\\u0643\\u064a\\u064a\\u0641\"}', NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 00:39:11'),
(196, 1, 'delete_user', 'users', 35, '{\"full_name\":\"\\u062d\\u0645\\u062f\\u064a\"}', '{\"deleted\":true}', '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 00:52:41'),
(197, 1, 'approve_provider', 'providers', 18, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:01:34'),
(198, 1, 'update_order_confirmation', 'orders', 25, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:11:55'),
(199, 1, 'update_order_confirmation', 'orders', 25, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:11:56'),
(200, 1, 'set_estimate', 'orders', 25, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:13:15'),
(201, 1, 'assign_provider', 'orders', 25, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:13:42'),
(202, 1, 'update_wallet', 'users', 36, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:22:32'),
(203, 1, 'update_promo_code', 'promo_codes', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:30:35'),
(204, 1, 'update_promo_code', 'promo_codes', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:32:04'),
(205, 1, 'update_promo_code', 'promo_codes', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:34:14'),
(206, 1, 'set_estimate', 'orders', 26, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:38:43'),
(207, 1, 'update_order_confirmation', 'orders', 26, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:39:24'),
(208, 1, 'update_order_confirmation', 'orders', 26, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:39:49'),
(209, 1, 'assign_provider', 'orders', 26, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 01:40:12'),
(210, 1, 'logout', 'admins', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 02:18:36'),
(211, 1, 'login', 'admins', 1, NULL, NULL, '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 02:19:41'),
(212, 1, 'update_membership', 'users', 36, NULL, '{\"membership_level\":\"premium\"}', '45.247.55.253', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-10 02:27:31'),
(213, 1, 'login', 'admins', 1, NULL, NULL, '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 17:18:25'),
(214, 1, 'update_promo_code', 'promo_codes', 1, NULL, NULL, '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 19:04:34'),
(215, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 20:57:35'),
(216, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"https:\\\\\\/\\\\\\/ertah.org\\\\\\/admin-panel\\\\\\/uploads\\\\\\/notifications\\\\\\/69b3341bf3f13_1773351963.png\\\"}\"', '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 21:46:04'),
(217, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 21:46:45'),
(218, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 21:47:20'),
(219, 1, 'update_wallet', 'users', 36, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 21:58:34'),
(220, 1, 'logout', 'admins', 1, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 22:15:46'),
(221, 1, 'login', 'admins', 1, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 22:15:49'),
(222, 1, 'assign_container_store', 'container_requests', 3, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 22:25:17'),
(223, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 22:41:27'),
(224, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 22:41:43'),
(225, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 22:43:04'),
(226, 1, 'update_points', 'users', 37, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-12 22:46:08'),
(227, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"\\\"}\"', '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 02:55:10'),
(228, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"\\\"}\"', '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 02:56:13'),
(229, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"\\\"}\"', '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 02:56:34'),
(230, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"\\\"}\"', '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 02:57:01');
INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `model`, `model_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(231, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"\\\"}\"', '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 02:59:00'),
(232, 1, 'send_notification', 'notifications', 0, NULL, '\"{\\\"target_group\\\":\\\"all_users\\\",\\\"target_count\\\":0,\\\"target_user_id\\\":null,\\\"target_provider_id\\\":null,\\\"destination_type\\\":null,\\\"destination_id\\\":null,\\\"db_inserted\\\":0,\\\"push_successful\\\":1,\\\"push_failed\\\":0,\\\"image_url\\\":\\\"\\\"}\"', '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 03:00:27'),
(233, 1, 'update_settings', 'settings', 0, NULL, NULL, '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-13 04:00:07'),
(234, 1, 'login', 'admins', 1, NULL, NULL, '154.179.139.240', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-16 02:07:48'),
(235, 1, 'delete_user', 'users', 36, '{\"full_name\":\"\\u062d\\u0645\\u062f\\u064a\"}', '{\"deleted\":true}', '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 02:37:10'),
(236, 1, 'delete_provider', 'providers', 18, '{\"full_name\":\"\\u0634\\u0648\\u0642\\u064a\"}', NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 02:37:24'),
(237, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 02:40:56'),
(238, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 02:41:24'),
(239, 1, 'update_settings', 'settings', 0, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 02:43:10'),
(240, 1, 'create_service_area', 'service_areas', 5, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:08:26'),
(241, 1, 'delete_container_request', 'container_requests', 1, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:09:24'),
(242, 1, 'delete_container_request', 'container_requests', 2, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:09:29'),
(243, 1, 'delete_container_request', 'container_requests', 3, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:09:35'),
(244, 1, 'update_container_store', 'container_stores', 1, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:10:01'),
(245, 1, 'update_container_store', 'container_stores', 1, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:10:33'),
(246, 1, 'update_points', 'users', 40, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:17:49'),
(247, 1, 'assign_container_store', 'container_requests', 4, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:21:12'),
(248, 1, 'update_order_status', 'orders', 39, NULL, NULL, '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 03:38:21'),
(249, 1, 'delete_user', 'users', 40, '{\"full_name\":\"\\u062d\\u0645\\u062f\\u064a \\u0627\\u062d\\u0645\\u062f\"}', '{\"deleted\":true}', '45.241.252.182', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-17 20:42:31'),
(250, 1, 'login', 'admins', 1, NULL, NULL, '197.163.154.104', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-27 04:12:10'),
(251, 1, 'login', 'admins', 1, NULL, NULL, '45.243.211.89', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 17:28:08'),
(252, 1, 'update_container_request', 'container_requests', 4, NULL, NULL, '45.243.211.89', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 17:32:41');

-- --------------------------------------------------------

--
-- بنية الجدول `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `address` text NOT NULL,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `country_code` varchar(8) DEFAULT NULL,
  `city_name` varchar(120) DEFAULT NULL,
  `village_name` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `title`, `address`, `lat`, `lng`, `notes`, `is_default`, `country_code`, `city_name`, `village_name`) VALUES
(2, 2, 'المنزل', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر, , صنافير', 30.221532358486353, 31.169377975165844, '', 0, NULL, NULL, NULL),
(3, 2, 'موقع محدد', 'صنافير, محافظة القليوبية, EG', 30.221581898423363, 31.169417537748814, '', 0, NULL, NULL, NULL),
(4, 2, 'موقع محدد', 'صنافير, محافظة القليوبية, EG', 30.221586533737852, 31.169414855539802, '', 0, NULL, NULL, NULL),
(5, 2, 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.221536124681275, 31.169364228844643, '', 0, NULL, NULL, NULL),
(6, 2, 'موقع محدد', 'جاري تحديد الموقع...', 30.2215572, 31.1693858, '', 0, NULL, NULL, NULL),
(7, 2, 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.221561908627216, 31.169393062591553, '', 0, NULL, NULL, NULL),
(8, 2, 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.221578132230196, 31.169405803084373, '', 0, NULL, NULL, NULL),
(9, 2, 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.221577263108692, 31.169412843883034, '', 0, NULL, NULL, NULL),
(10, 2, 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.221552927703, 31.16930924355984, '', 0, NULL, NULL, NULL),
(11, 2, 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.221529171705928, 31.169335395097733, '', 0, NULL, NULL, NULL),
(12, 2, 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.221529171705928, 31.169335395097733, '', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default.png',
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `avatar`, `role`, `permissions`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@ertah.com', '$2y$10$ll3AaOD.y.V/BFDVwAMRMOu15VIw5RVOQ1rqbXb7CB9AM2wS7b7V.', 'admin', '', 'admins/69adf779ecc85_1773008761.png', 'super_admin', NULL, 1, '2026-04-02 20:28:08', '2026-01-27 06:25:59', '2026-04-02 17:28:08');

-- --------------------------------------------------------

--
-- بنية الجدول `app_content_pages`
--

CREATE TABLE `app_content_pages` (
  `id` int(11) NOT NULL,
  `page_key` varchar(50) NOT NULL,
  `language_code` varchar(5) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `app_content_pages`
--

INSERT INTO `app_content_pages` (`id`, `page_key`, `language_code`, `title`, `content`, `created_at`, `updated_at`) VALUES
(1, 'about', 'ar', 'عن Darfix', 'Darfix هو تطبيق سعودي رائد في مجال الخدمات المنزلية، نربط بين العملاء ومقدمي الخدمات المحترفين بسرعة وسهولة في مختلف مدن المملكة.\r\n\r\nنوفر لك تجربة موثوقة تشمل الحجز السريع، متابعة الطلب لحظياً، طرق دفع آمنة، ودعم متواصل لضمان أفضل تجربة خدمة.', '2026-02-18 08:48:41', '2026-03-12 20:23:29'),
(2, 'about', 'en', 'About Darfix', 'Darfix is a leading Saudi home-services app that connects customers with trusted service providers quickly and reliably across the Kingdom.\r\n\r\nWe provide a smooth experience with fast booking, real-time order tracking, secure payments, and responsive support.', '2026-02-18 08:48:41', '2026-03-12 20:23:29'),
(3, 'about', 'ur', 'Darfix کے بارے میں', 'Darfix سعودی عرب میں گھریلو خدمات کے لیے ایک نمایاں ایپ ہے جو صارفین کو قابلِ اعتماد سروس فراہم کنندگان سے تیزی اور آسانی کے ساتھ جوڑتی ہے۔\r\n\r\nہم تیز بکنگ، آرڈر کی لائیو ٹریکنگ، محفوظ ادائیگی اور مسلسل سپورٹ فراہم کرتے ہیں تاکہ بہترین تجربہ مل سکے۔', '2026-02-18 08:48:41', '2026-03-12 20:23:29'),
(4, 'privacy', 'ar', 'سياسة الخصوصية', 'نحن نحترم خصوصيتك ونلتزم بحماية بياناتك الشخصية وفق أعلى المعايير.\r\n\r\nالبيانات التي نجمعها قد تشمل: الاسم، رقم الهاتف، الموقع الجغرافي، وسجل الطلبات.\r\n\r\nنستخدم هذه البيانات لتحسين الخدمة، تنفيذ الطلبات، التواصل معك، ورفع جودة تجربة الاستخدام.\r\n\r\nلا نشارك بياناتك الشخصية مع أي طرف غير مصرح له.', '2026-02-18 08:48:41', '2026-02-18 09:16:27'),
(5, 'privacy', 'en', 'Privacy Policy', 'We respect your privacy and are committed to protecting your personal data.\r\n\r\nData we may collect includes your name, phone number, location, and order history.\r\n\r\nWe use this data to deliver services, process orders, communicate with you, and improve the app experience.\r\n\r\nWe do not share your personal data with unauthorized parties.', '2026-02-18 08:48:41', '2026-02-18 09:16:27'),
(6, 'privacy', 'ur', 'رازداری کی پالیسی', 'ہم آپ کی رازداری کا احترام کرتے ہیں اور آپ کے ذاتی ڈیٹا کے تحفظ کے لیے پُرعزم ہیں۔\r\n\r\nہم جو معلومات جمع کرتے ہیں ان میں نام، فون نمبر، لوکیشن اور آرڈر ہسٹری شامل ہو سکتی ہیں۔\r\n\r\nیہ معلومات خدمات فراہم کرنے، آرڈرز مکمل کرنے، آپ سے رابطہ کرنے اور ایپ کے تجربے کو بہتر بنانے کے لیے استعمال کی جاتی ہیں۔\r\n\r\nہم آپ کا ذاتی ڈیٹا غیر مجاز فریقوں کے ساتھ شیئر نہیں کرتے۔', '2026-02-18 08:48:41', '2026-02-18 09:16:27'),
(7, 'terms', 'ar', 'شروط الاستخدام', 'باستخدامك لتطبيق Darfix، فإنك توافق على الالتزام بشروط الاستخدام.\r\n\r\n1) يجب استخدام التطبيق بطريقة قانونية وعدم إساءة الاستخدام.\r\n2) الأسعار والمواعيد تخضع لتأكيد مقدم الخدمة.\r\n3) يمكن إلغاء الطلب وفق سياسة الإلغاء المعتمدة.\r\n4) يحق للتطبيق تحديث هذه الشروط عند الحاجة.', '2026-02-18 08:48:41', '2026-03-12 20:23:29'),
(8, 'terms', 'en', 'Terms of Use', 'By using Darfix, you agree to comply with these terms.\r\n\r\n1) The app must be used lawfully and without abuse.\r\n2) Pricing and scheduling are subject to provider confirmation.\r\n3) Orders may be canceled according to the cancellation policy.\r\n4) We reserve the right to update these terms when needed.', '2026-02-18 08:48:41', '2026-03-12 20:23:29'),
(9, 'terms', 'ur', 'استعمال کی شرائط', 'Darfix ایپ استعمال کرنے سے آپ ان شرائط کی پابندی سے اتفاق کرتے ہیں۔\r\n\r\n1) ایپ کو قانونی طور پر اور بغیر غلط استعمال کے استعمال کیا جائے۔\r\n2) قیمت اور وقت سروس فراہم کنندہ کی تصدیق کے تابع ہیں۔\r\n3) آرڈر منسوخی پالیسی کے مطابق منسوخ کیا جا سکتا ہے۔\r\n4) ضرورت کے مطابق ان شرائط میں تبدیلی کا حق محفوظ ہے۔', '2026-02-18 08:48:41', '2026-03-12 20:23:29'),
(10, 'refund', 'ar', 'سياسة الاسترداد', 'بعد تأكيد الطلب وبدء إجراءات التنفيذ، تصبح عملية الدفع غير قابلة للاسترداد.\r\n\r\nيمكن قبول طلبات الاسترداد فقط في الحالات التي يتعذر فيها تقديم الخدمة من طرفنا أو عند وجود خطأ في عملية الخصم.\r\n\r\nيتم تقديم طلب الاسترداد خلال 7 أيام عمل من تاريخ الدفع عبر فريق الدعم.', '2026-03-16 01:16:15', '2026-03-16 02:40:56'),
(11, 'refund', 'en', 'Refund Policy', 'Payments become non-refundable once the order is confirmed and processing starts.\r\n\r\nRefund requests are accepted only if the service cannot be delivered by us or if there is a billing error.\r\n\r\nPlease submit refund requests within 7 business days of payment via support.', '2026-03-16 01:16:15', '2026-03-16 02:40:56'),
(12, 'refund', 'ur', 'واپسی کی پالیسی', 'آرڈر کی تصدیق اور پراسیسنگ شروع ہونے کے بعد ادائیگی ناقابل واپسی ہو جاتی ہے۔\r\n\r\nواپسی کی درخواستیں صرف اس صورت میں قبول کی جائیں گی جب سروس فراہم نہ ہو سکے یا ادائیگی میں غلطی ہو۔\r\n\r\nبراہ کرم ادائیگی کے 7 کاروباری دنوں کے اندر سپورٹ سے رابطہ کریں۔', '2026-03-16 01:16:15', '2026-03-16 02:40:56');

-- --------------------------------------------------------

--
-- بنية الجدول `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `description`) VALUES
('about_feature_1_description_ar', 'نضمن لك أفضل جودة في الخدمات المنزلية', ''),
('about_feature_1_description_en', 'We guarantee top quality home services.', ''),
('about_feature_1_description_ur', 'ہم گھریلو خدمات میں بہترین معیار کی ضمانت دیتے ہیں۔', ''),
('about_feature_1_icon', '⭐', ''),
('about_feature_1_title_ar', 'جودة عالية', ''),
('about_feature_1_title_en', 'High Quality', ''),
('about_feature_1_title_ur', 'اعلی معیار', ''),
('about_feature_2_description_ar', 'أفضل الأسعار في السوق السعودي', ''),
('about_feature_2_description_en', 'Best value in the local market.', ''),
('about_feature_2_description_ur', 'مارکیٹ میں بہترین قیمتیں۔', ''),
('about_feature_2_icon', '💰', ''),
('about_feature_2_title_ar', 'أسعار منافسة', ''),
('about_feature_2_title_en', 'Competitive Prices', ''),
('about_feature_2_title_ur', 'مسابقتی قیمتیں', ''),
('about_feature_3_description_ar', 'استجابة فورية وتنفيذ سريع', ''),
('about_feature_3_description_en', 'Fast response and quick execution.', ''),
('about_feature_3_description_ur', 'فوری رسپانس اور تیز عمل درآمد۔', ''),
('about_feature_3_icon', '⚡', ''),
('about_feature_3_title_ar', 'خدمة سريعة', ''),
('about_feature_3_title_en', 'Fast Service', ''),
('about_feature_3_title_ur', 'تیز سروس', ''),
('about_feature_4_description_ar', 'فريق دعم متاح على مدار الساعة', ''),
('about_feature_4_description_en', 'Support team available around the clock.', ''),
('about_feature_4_description_ur', 'سپورٹ ٹیم ہر وقت دستیاب ہے۔', ''),
('about_feature_4_icon', '🎧', ''),
('about_feature_4_title_ar', 'دعم 24/7', ''),
('about_feature_4_title_en', '24/7 Support', ''),
('about_feature_4_title_ur', '24/7 سپورٹ', ''),
('about_feature_5_description_ar', 'ضمان جودة الخدمة مع المتابعة', ''),
('about_feature_5_description_en', 'Service quality guarantee with follow-up.', ''),
('about_feature_5_description_ur', 'سروس کے معیار کی ضمانت اور فالو اپ۔', ''),
('about_feature_5_icon', '🛡️', ''),
('about_feature_5_title_ar', 'ضمان شامل', ''),
('about_feature_5_title_en', 'Comprehensive Warranty', ''),
('about_feature_5_title_ur', 'جامع وارنٹی', ''),
('about_stat_completed_orders', '100,00+', ''),
('about_stat_happy_clients', '5,000+', ''),
('about_stat_service_providers', '2,50+', ''),
('allow_registration', '1', 'السماح بالتسجيل الجديد (1=نعم، 0=لا)'),
('app_font', 'zain', ''),
('app_logo', 'branding/69b76e3e3f82b_1773628990.png', 'App branding logo'),
('app_name_ar', 'دارفيكس', ''),
('app_name_en', 'Darfix', ''),
('app_name_ur', 'Darfix', ''),
('confirmation_lead_hours', '2', NULL),
('fixed_otp', '1234', 'كود التحقق الثابت عند تعطيل الرسائل (اتركه فارغاً للتوليد العشوائي)'),
('help_banner_text_ar', '', ''),
('help_banner_text_en', '', ''),
('help_banner_text_ur', '', ''),
('help_faq_1_answer_ar', 'يمكنك طلب الخدمة من الصفحة الرئيسية ثم اختيار الموعد المناسب.', ''),
('help_faq_1_answer_en', 'Choose your service from home, then select the suitable time.', ''),
('help_faq_1_answer_ur', 'ہوم اسکرین سے سروس منتخب کریں اور مناسب وقت چنیں۔', ''),
('help_faq_1_question_ar', 'كيف يمكنني طلب خدمة؟', ''),
('help_faq_1_question_en', 'How can I book a service?', ''),
('help_faq_1_question_ur', 'میں سروس کیسے بُک کر سکتا ہوں؟', ''),
('help_faq_2_answer_ar', 'يمكنك الدفع عبر البطاقة أو المحفظة أو Apple Pay حسب المتاح.', ''),
('help_faq_2_answer_en', 'Pay by card, wallet, or Apple Pay based on availability.', ''),
('help_faq_2_answer_ur', 'کارڈ، والٹ یا ایپل پے کے ذریعے ادائیگی کریں۔', ''),
('help_faq_2_question_ar', 'كيف يمكنني الدفع؟', ''),
('help_faq_2_question_en', 'How can I pay?', ''),
('help_faq_2_question_ur', 'میں ادائیگی کیسے کر سکتا ہوں؟', ''),
('help_faq_3_answer_ar', 'نعم، يمكنك الإلغاء حسب سياسة الإلغاء داخل التطبيق.', ''),
('help_faq_3_answer_en', 'Yes, according to the cancellation policy in the app.', ''),
('help_faq_3_answer_ur', 'جی ہاں، ایپ کی منسوخی پالیسی کے مطابق۔', ''),
('help_faq_3_question_ar', 'هل يمكنني إلغاء الطلب؟', ''),
('help_faq_3_question_en', 'Can I cancel my order?', ''),
('help_faq_3_question_ur', 'کیا میں آرڈر منسوخ کر سکتا ہوں؟', ''),
('help_faq_4_answer_ar', 'من صفحة تتبع الطلب ستجد أزرار الاتصال والدردشة.', ''),
('help_faq_4_answer_en', 'Use call/chat actions from the order tracking screen.', ''),
('help_faq_4_answer_ur', 'آرڈر ٹریکنگ اسکرین سے کال/چیٹ کے بٹن استعمال کریں۔', ''),
('help_faq_4_question_ar', 'كيف أتواصل مع الفني؟', ''),
('help_faq_4_question_en', 'How do I contact the technician?', ''),
('help_faq_4_question_ur', 'میں ٹیکنیشن سے کیسے رابطہ کروں؟', ''),
('help_faq_count', '4', ''),
('home_how_it_works_step_1_image', 'home_how_it_works/69af1f3fc5ba9_1773084479.png', 'How it works step image'),
('home_how_it_works_step_1_subtitle_ar', 'التي تحتاجها', ''),
('home_how_it_works_step_1_subtitle_en', 'That you need', ''),
('home_how_it_works_step_1_subtitle_ur', 'جس کی آپ کو ضرورت ہے', ''),
('home_how_it_works_step_1_title_ar', 'احجز الخدمة', ''),
('home_how_it_works_step_1_title_en', 'Book Service', ''),
('home_how_it_works_step_1_title_ur', 'سروس بک کریں', ''),
('home_how_it_works_step_2_image', 'home_how_it_works/69af1f3fc69e7_1773084479.png', 'How it works step image'),
('home_how_it_works_step_2_subtitle_ar', 'من مركز العمليات', ''),
('home_how_it_works_step_2_subtitle_en', 'From the operations center', ''),
('home_how_it_works_step_2_subtitle_ur', 'سروس فراہم کنندگان سے', ''),
('home_how_it_works_step_2_title_ar', 'تعيين الفني', ''),
('home_how_it_works_step_2_title_en', 'Appointing a technician', ''),
('home_how_it_works_step_2_title_ur', 'پیشکشیں وصول کریں', ''),
('home_how_it_works_step_3_image', 'home_how_it_works/69af1f3fc6d69_1773084479.png', 'How it works step image'),
('home_how_it_works_step_3_subtitle_ar', 'فاتورة وتنفيذ', ''),
('home_how_it_works_step_3_subtitle_en', 'Invoice and execution', ''),
('home_how_it_works_step_3_subtitle_ur', 'قیمت اور درجہ بندی', ''),
('home_how_it_works_step_3_title_ar', 'تنفيذ الخدمة', ''),
('home_how_it_works_step_3_title_en', 'Service implementation', ''),
('home_how_it_works_step_3_title_ur', 'بہترین کا انتخاب کریں', ''),
('home_how_it_works_step_4_image', 'home_how_it_works/69af1f3fc6f64_1773084479.png', 'How it works step image'),
('home_how_it_works_step_4_subtitle_ar', 'تقييم الخدمة', ''),
('home_how_it_works_step_4_subtitle_en', 'Service evaluation', ''),
('home_how_it_works_step_4_subtitle_ur', 'اعلی معیار', ''),
('home_how_it_works_step_4_title_ar', 'انتهاء وتقييم', ''),
('home_how_it_works_step_4_title_en', 'End and evaluation', ''),
('home_how_it_works_step_4_title_ur', 'سروس انجام دیں', ''),
('home_limit_banners', '5', ''),
('home_limit_categories', '6', ''),
('home_limit_cities', '200', ''),
('home_limit_how_it_works_steps', '4', ''),
('home_limit_most_requested_services', '2', ''),
('home_limit_offers', '2', ''),
('home_limit_spare_parts', '3', ''),
('home_limit_stores', '5', ''),
('home_section_icon_most_requested', 'home_sections/6995404370948_1771388995.png', 'Home section icon'),
('home_section_icon_services', 'home_sections/69af171a9bf57_1773082394.png', 'Home section icon'),
('home_section_order_ad_banner', '4', ''),
('home_section_order_how_it_works', '1', ''),
('home_section_order_most_requested_services', '3', ''),
('home_section_order_offers', '7', ''),
('home_section_order_services', '2', ''),
('home_section_order_spare_parts', '5', ''),
('home_section_order_stores', '6', ''),
('home_section_visible_ad_banner', '1', ''),
('home_section_visible_how_it_works', '1', ''),
('home_section_visible_most_requested_services', '1', ''),
('home_section_visible_offers', '1', ''),
('home_section_visible_services', '1', ''),
('home_section_visible_spare_parts', '1', ''),
('home_section_visible_stores', '0', ''),
('incomplete_order_hours', '24', NULL),
('maintenance_mode', '0', 'وضع الصيانة (1=مفعل، 0=معطل)'),
('myfatoorah_base_url', 'https://api-sa.myfatoorah.com', 'MyFatoorah API base URL'),
('myfatoorah_enabled', '1', 'MyFatoorah gateway enabled'),
('myfatoorah_token', 'SK_SAU_bnqE5cGtCbNmYtEJn3x1cONyJxOjM3xih4A8RpjdjmqDmybWNrYr12F7Ga4PSbbU', 'MyFatoorah API token'),
('no_show_blacklist_threshold', '3', NULL),
('notification_enabled', '1', NULL),
('notify_complaints', '1', NULL),
('notify_containers', '1', NULL),
('notify_furniture', '1', NULL),
('notify_incomplete', '1', NULL),
('notify_new_orders', '1', NULL),
('points_per_currency_unit', '10', ''),
('provider_app_logo', 'branding/69b76db87f6d0_1773628856.png', 'App branding logo'),
('referral_reward_amount', '5', ''),
('share_benefit_1_subtitle_ar', 'عند تسجيله لأول مرة', ''),
('share_benefit_1_subtitle_en', 'When they register for the first time', ''),
('share_benefit_1_subtitle_ur', 'جب وہ پہلی بار رجسٹر کرے', ''),
('share_benefit_1_title_ar', 'احصل على 5 ريال لكل صديق', ''),
('share_benefit_1_title_en', 'Get 5 SAR for each friend', ''),
('share_benefit_1_title_ur', 'ہر دوست پر 5 ریال حاصل کریں', ''),
('share_benefit_2_subtitle_ar', 'واتساب، إيميل أو أي تطبيق', ''),
('share_benefit_2_subtitle_en', 'WhatsApp, email, or any app', ''),
('share_benefit_2_subtitle_ur', 'واٹس ایپ، ای میل یا کسی بھی ایپ سے', ''),
('share_benefit_2_title_ar', 'شارك الرابط بسهولة', ''),
('share_benefit_2_title_en', 'Share your link easily', ''),
('share_benefit_2_title_ur', 'اپنا لنک آسانی سے شیئر کریں', ''),
('share_benefit_3_subtitle_ar', 'يضاف تلقائياً بعد تحقق الشروط', ''),
('share_benefit_3_subtitle_en', 'Added automatically when conditions are met', ''),
('share_benefit_3_subtitle_ur', 'شرائط پوری ہونے پر خودکار طور پر شامل ہوگا', ''),
('share_benefit_3_title_ar', 'رصيد فوري في المحفظة', ''),
('share_benefit_3_title_en', 'Instant wallet credit', ''),
('share_benefit_3_title_ur', 'والٹ میں فوری کریڈٹ', ''),
('share_invite_message_ar', '', ''),
('share_invite_message_en', '', ''),
('share_invite_message_ur', '', ''),
('share_invite_subtitle_ar', '', ''),
('share_invite_subtitle_en', '', ''),
('share_invite_subtitle_ur', '', ''),
('share_link_base', '', ''),
('share_program_title_ar', '', ''),
('share_program_title_en', '', ''),
('share_program_title_ur', '', ''),
('share_reward_reason_ar', '', ''),
('share_reward_reason_en', '', ''),
('share_reward_reason_ur', '', ''),
('sms_api_key', 'h0AVtNJP00Nw3EqCYI4qrgtE9137TuNRD12vmyUc', ''),
('sms_api_secret', 'RQ9gmIs5zEm6sycJQb0zA2vexefCkUc5i90mOy56BvE6IqvVhQQnew4KitI9PrcEKOSkwJXI7RJqUZGa7yi3xRYWKBpCx89rC25T', ''),
('sms_api_url', 'https://api-sms.4jawaly.com/api/v1/account/area/sms/v2/send', ''),
('sms_enabled', '1', 'حالة إرسال الرسائل (1=مفعل، 0=معطل/مجاني)'),
('sms_sender_id', 'ErtahApp', ''),
('smtp_enabled', '1', NULL),
('smtp_encryption', 'ssl', NULL),
('smtp_from_email', 'support@bluedeskgroup.net', NULL),
('smtp_from_name', 'دارفيكس - Darfix', NULL),
('smtp_host', 'smtp.hostinger.com', NULL),
('smtp_password', 'amAM123123@@@@', NULL),
('smtp_port', '465', NULL),
('smtp_username', 'support@bluedeskgroup.net', NULL),
('spare_parts_min_order_with_installation', '99.97', ''),
('support_address', 'الرياض، المملكة العربية السعودية <', ''),
('support_email', 'support@ertah.ap', ''),
('support_phone', '+966501234511', ''),
('supported_countries', 'EG,SA', ''),
('whatsapp', '+966501234561', ''),
('whatsapp_api_key', 'h0AVtNJP00Nw3EqCYI4qrgtE9137TuNRD12vmyUc', NULL),
('whatsapp_api_secret', 'RQ9gmIs5zEm6sycJQb0zA2vexefCkUc5i90mOy56BvE6IqvVhQQnew4KitI9PrcEKOSkwJXI7RJqUZGa7yi3xRYWKBpCx89rC25T', NULL),
('whatsapp_enabled', '1', NULL),
('whatsapp_gateway', '4jawaly', NULL),
('whatsapp_sender', 'ErtahApp', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `title_ur` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `subtitle_en` varchar(255) DEFAULT NULL,
  `subtitle_ur` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `link_type` enum('category','offer','product','external','none') DEFAULT 'none',
  `link_id` int(11) DEFAULT NULL,
  `position` enum('home_slider','home_popup','home_middle','category','offer') DEFAULT 'home_slider',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `background_color` varchar(20) DEFAULT NULL,
  `background_color_end` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `banners`
--

INSERT INTO `banners` (`id`, `title`, `title_en`, `title_ur`, `subtitle`, `subtitle_en`, `subtitle_ur`, `image`, `link`, `link_type`, `link_id`, `position`, `is_active`, `sort_order`, `start_date`, `end_date`, `created_at`, `background_color`, `background_color_end`) VALUES
(1, 'خصومات تصل ل ٣٠ ٪', NULL, NULL, 'خصم شامل علي خدمات السباكة بمناسبة عيدالفطر', NULL, NULL, 'banners/69af0fa06a0d3_1773080480.png', NULL, 'category', 1, 'home_slider', 1, 0, NULL, NULL, '2026-01-27 08:00:21', '#6BABFF', NULL),
(6, 'عروض وخصومات قوية', NULL, NULL, 'خصومات وعروض علي قطع الغيار والصيانة للتكيف', NULL, NULL, 'banners/69af124ce0747_1773081164.png', NULL, 'none', NULL, 'home_slider', 1, 0, NULL, NULL, '2026-03-09 18:32:44', '#FFD257', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `categories`
--

INSERT INTO `categories` (`id`, `name_ar`, `name_en`, `image`, `icon`, `is_active`, `display_order`) VALUES
(1, 'كهرباء', 'Electricity', 'assets/images/electricity.png', '⚡', 1, 0),
(2, 'سباكة', 'Plumbing', 'assets/images/plumbing.png', '🚰', 1, 0),
(3, 'تكييف', 'AC', 'assets/images/ac.png', '❄️', 1, 0),
(4, 'تنظيف', 'Cleaning', 'assets/images/cleaning.png', '🧹', 1, 0),
(5, 'مكافحة حشرات', 'Pest Control', 'assets/images/pest.png', '🦗', 1, 0),
(6, 'نجارة', 'Carpentry', 'assets/images/carpentry.png', '🪚', 1, 0);

-- --------------------------------------------------------

--
-- بنية الجدول `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `country_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `cities`
--

INSERT INTO `cities` (`id`, `name_ar`, `name_en`, `is_active`, `created_at`, `country_id`) VALUES
(1, 'الرياض', 'Riyadh', 1, '2026-01-27 06:25:59', 1),
(2, 'جدة', 'Jeddah', 1, '2026-01-27 06:25:59', 1),
(3, 'الدمام', 'Dammam', 1, '2026-01-27 06:25:59', 1);

-- --------------------------------------------------------

--
-- بنية الجدول `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `assigned_to` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `complaints`
--

INSERT INTO `complaints` (`id`, `ticket_number`, `user_id`, `provider_id`, `order_id`, `subject`, `description`, `attachments`, `priority`, `status`, `assigned_to`, `admin_notes`, `resolution`, `resolved_at`, `created_at`, `updated_at`) VALUES
(1, 'TKT-20260218-5267', 2, NULL, NULL, 'عوعوعو٦و', 'اغتغوغوف', NULL, 'medium', 'open', 1, '', '', '2026-02-18 11:29:28', '2026-02-18 05:16:32', '2026-03-02 20:44:37');

-- --------------------------------------------------------

--
-- بنية الجدول `complaint_replies`
--

CREATE TABLE `complaint_replies` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `attachments` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sender_type` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `complaint_replies`
--

INSERT INTO `complaint_replies` (`id`, `complaint_id`, `user_id`, `admin_id`, `message`, `attachments`, `created_at`, `sender_type`) VALUES
(1, 1, NULL, 1, 'اتانلبلبتن', NULL, '2026-02-18 05:21:01', NULL),
(2, 1, 2, NULL, 'غوفو', NULL, '2026-02-18 05:47:14', NULL),
(3, 1, NULL, 1, 'لااتنامتللانكمتلا', NULL, '2026-02-18 05:48:02', NULL),
(7, 1, 2, NULL, 'هل ل٨ا', NULL, '2026-02-18 08:18:58', NULL),
(8, 1, 2, NULL, 'غو٦ت٦ة', NULL, '2026-02-18 08:28:10', NULL),
(9, 1, NULL, 1, 'بعكمغتنفال', NULL, '2026-02-18 08:28:25', NULL),
(10, 1, 2, NULL, 'رسالة اختبار فورية من التطبيق', NULL, '2026-03-02 20:44:37', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `container_requests`
--

CREATE TABLE `container_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(30) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `container_service_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `site_city` varchar(100) DEFAULT NULL,
  `site_address` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `duration_days` int(11) NOT NULL DEFAULT 1,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `needs_loading_help` tinyint(1) NOT NULL DEFAULT 0,
  `needs_operator` tinyint(1) NOT NULL DEFAULT 0,
  `purpose` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new',
  `estimated_price` decimal(10,2) DEFAULT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estimated_weight_kg` decimal(10,2) DEFAULT NULL,
  `estimated_distance_meters` decimal(10,2) DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `media_json` longtext DEFAULT NULL,
  `source_order_id` int(11) DEFAULT NULL,
  `container_store_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `container_requests`
--

INSERT INTO `container_requests` (`id`, `request_number`, `user_id`, `container_service_id`, `customer_name`, `phone`, `site_city`, `site_address`, `start_date`, `end_date`, `duration_days`, `quantity`, `needs_loading_help`, `needs_operator`, `purpose`, `notes`, `status`, `estimated_price`, `final_price`, `admin_notes`, `created_at`, `updated_at`, `estimated_weight_kg`, `estimated_distance_meters`, `details_json`, `media_json`, `source_order_id`, `container_store_id`) VALUES
(4, 'CT26031655469', 40, 1, 'حمدي احمد', '+966535036778', 'الدمام', 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', '2026-03-17', '2026-03-20', 4, 1, 0, 0, '', 'استخدام للقمامة', 'new', 108.00, NULL, '', '2026-03-16 03:12:19', '2026-04-02 17:32:41', 20.00, NULL, '{\"source\":\"mobile_order\",\"source_order_id\":38,\"module\":\"container_rental\",\"problem_details\":{\"type\":\"container_rental\",\"user_desc\":\"استخدام للقمامة\",\"module\":\"container_rental\",\"container_request\":{\"container_service_name\":\"حاوية ٢٠\",\"container_size\":\"٢٠\",\"site_city\":\"الدمام\",\"site_address\":\"H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate\",\"start_date\":\"2026-03-17\",\"end_date\":\"2026-03-20\",\"notes\":\"استخدام للقمامة\",\"container_service_id\":1,\"duration_days\":4,\"quantity\":1,\"capacity_ton\":0.02,\"daily_price\":2,\"weekly_price\":14,\"monthly_price\":0,\"delivery_fee\":100,\"price_per_kg\":0,\"price_per_meter\":0,\"minimum_charge\":0,\"estimated_weight_kg\":20,\"needs_loading_help\":false,\"needs_operator\":false},\"is_custom_service\":true,\"custom_service\":{\"title\":\"طلب خدمة الحاويات - حاوية ٢٠\",\"description\":\"استخدام للقمامة\"}},\"pricing\":{\"base_price\":108,\"price_per_kg\":0,\"price_per_meter\":0,\"minimum_charge\":0,\"calculated_estimated_price\":108}}', '[\"orders\\/69b775132924c_1773630739.jpg\"]', 38, 1),
(5, 'CT26040228950', 41, 1, 'حمدي', '+966535036778', 'القاهرة', 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', NULL, NULL, 1, 1, 0, 0, '', 'مخلفات', 'new', 102.00, NULL, NULL, '2026-04-02 17:37:08', '2026-04-02 17:37:08', NULL, NULL, '{\"source\":\"mobile_order\",\"source_order_id\":41,\"module\":\"container_rental\",\"problem_details\":{\"type\":\"container_rental\",\"user_desc\":\"مخلفات\",\"module\":\"container_rental\",\"container_request\":{\"container_service_name\":\"حاوية ٢٠\",\"container_size\":\"٢٠\",\"site_city\":\"القاهرة\",\"site_address\":\"H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate\",\"notes\":\"مخلفات\",\"container_service_id\":1,\"duration_days\":1,\"quantity\":1,\"capacity_ton\":0.02,\"daily_price\":2,\"weekly_price\":14,\"monthly_price\":0,\"delivery_fee\":100,\"price_per_kg\":0,\"price_per_meter\":0,\"minimum_charge\":0,\"needs_loading_help\":false,\"needs_operator\":false},\"is_custom_service\":true,\"custom_service\":{\"title\":\"طلب خدمة الحاويات - حاوية ٢٠\",\"description\":\"مخلفات\"}},\"pricing\":{\"base_price\":102,\"price_per_kg\":0,\"price_per_meter\":0,\"minimum_charge\":0,\"calculated_estimated_price\":102}}', '[\"orders\\/69cea944aba3f_1775151428.jpg\"]', 41, 1);

-- --------------------------------------------------------

--
-- بنية الجدول `container_services`
--

CREATE TABLE `container_services` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `container_size` varchar(100) NOT NULL,
  `capacity_ton` decimal(6,2) NOT NULL DEFAULT 0.00,
  `daily_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `weekly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_note` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price_per_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_per_meter` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimum_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `name_ur` varchar(150) DEFAULT NULL,
  `description_ur` text DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `container_services`
--

INSERT INTO `container_services` (`id`, `name_ar`, `name_en`, `description_ar`, `description_en`, `container_size`, `capacity_ton`, `daily_price`, `weekly_price`, `monthly_price`, `delivery_fee`, `price_note`, `image`, `is_active`, `sort_order`, `created_at`, `updated_at`, `price_per_kg`, `price_per_meter`, `minimum_charge`, `name_ur`, `description_ur`, `store_id`) VALUES
(1, 'حاوية ٢٠', '', '', '', '٢٠', 0.02, 2.00, 14.00, 0.00, 100.00, '', 'services/69ae00088997d_1773010952.png', 1, 0, '2026-03-08 23:02:32', '2026-03-08 23:02:32', 0.00, 0.00, 0.00, 'حاوية ٢٠', '', 1);

-- --------------------------------------------------------

--
-- بنية الجدول `container_stores`
--

CREATE TABLE `container_stores` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) DEFAULT NULL,
  `name_ur` varchar(150) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `container_stores`
--

INSERT INTO `container_stores` (`id`, `name_ar`, `name_en`, `name_ur`, `contact_person`, `phone`, `email`, `address`, `logo`, `notes`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'حاويتنا', '', 'حاويتنا', 'محمود', '', '', 'الاسماعيلية', NULL, '', 1, 0, '2026-03-08 23:01:20', '2026-03-16 03:10:33');

-- --------------------------------------------------------

--
-- بنية الجدول `container_store_account_entries`
--

CREATE TABLE `container_store_account_entries` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `entry_type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `source` enum('manual','request','payment','settlement','adjustment') NOT NULL DEFAULT 'manual',
  `reference_type` varchar(60) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `code` varchar(5) DEFAULT NULL,
  `phone_code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `countries`
--

INSERT INTO `countries` (`id`, `name_ar`, `name_en`, `code`, `phone_code`, `is_active`, `created_at`) VALUES
(1, 'السعودية', 'Saudi Arabia', 'SA', '966', 1, '2026-02-18 03:22:45');

-- --------------------------------------------------------

--
-- بنية الجدول `furniture_areas`
--

CREATE TABLE `furniture_areas` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(120) NOT NULL,
  `name_en` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name_ur` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `furniture_areas`
--

INSERT INTO `furniture_areas` (`id`, `name_ar`, `name_en`, `is_active`, `sort_order`, `created_at`, `updated_at`, `name_ur`) VALUES
(4, 'الدمام', 'Dammam', 1, 3, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `furniture_requests`
--

CREATE TABLE `furniture_requests` (
  `id` int(11) NOT NULL,
  `request_number` varchar(30) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `area_name` varchar(150) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `pickup_city` varchar(100) DEFAULT NULL,
  `pickup_address` text DEFAULT NULL,
  `dropoff_city` varchar(100) DEFAULT NULL,
  `dropoff_address` text DEFAULT NULL,
  `move_date` date DEFAULT NULL,
  `preferred_time` varchar(50) DEFAULT NULL,
  `rooms_count` int(11) NOT NULL DEFAULT 1,
  `floors_from` int(11) NOT NULL DEFAULT 0,
  `floors_to` int(11) NOT NULL DEFAULT 0,
  `elevator_from` tinyint(1) NOT NULL DEFAULT 0,
  `elevator_to` tinyint(1) NOT NULL DEFAULT 0,
  `needs_packing` tinyint(1) NOT NULL DEFAULT 0,
  `estimated_items` int(11) NOT NULL DEFAULT 0,
  `details_json` longtext DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new',
  `estimated_price` decimal(10,2) DEFAULT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estimated_weight_kg` decimal(10,2) DEFAULT NULL,
  `estimated_distance_meters` decimal(10,2) DEFAULT NULL,
  `source_order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `furniture_requests`
--

INSERT INTO `furniture_requests` (`id`, `request_number`, `user_id`, `service_id`, `area_id`, `area_name`, `customer_name`, `phone`, `pickup_city`, `pickup_address`, `dropoff_city`, `dropoff_address`, `move_date`, `preferred_time`, `rooms_count`, `floors_from`, `floors_to`, `elevator_from`, `elevator_to`, `needs_packing`, `estimated_items`, `details_json`, `notes`, `status`, `estimated_price`, `final_price`, `admin_notes`, `created_at`, `updated_at`, `estimated_weight_kg`, `estimated_distance_meters`, `source_order_id`) VALUES
(2, 'FM26030960034', 34, 1, 4, 'الدمام', 'حمدي', '+966535036778', 'الدمام', 'الدمام', 'الخبر', 'الخبر', '2026-03-13', '09:46', 1, 1, 1, 1, 1, 1, 3, '{\"fields\":{\"rooms_count\":\"1\",\"floors_from\":\"1\",\"floors_to\":\"1\",\"elevator_from\":true,\"elevator_to\":true,\"needs_packing\":true,\"estimated_items\":\"3\",\"estimated_weight_kg\":100},\"meta\":{\"selected_service_ids\":[1],\"selected_services\":[{\"id\":1,\"name_ar\":\"اختبار\",\"name_en\":\"test\",\"base_price\":100,\"price_per_kg\":0,\"price_per_meter\":0,\"minimum_charge\":0}],\"auto_estimated_price\":100}}', '', 'new', 100.00, NULL, NULL, '2026-03-08 22:46:20', '2026-03-08 22:46:20', 100.00, NULL, 14),
(3, 'FM26030992917', 34, 2, 4, 'الدمام', 'حمدي', '+966535036778', 'الدمام', 'الدمام', '', 'الخبر', '2026-03-20', '08:55', 2, 1, 1, 1, 1, 1, 0, '{\"fields\":{\"rooms_count\":\"2\",\"floors_from\":\"1\",\"floors_to\":\"1\",\"elevator_from\":true,\"elevator_to\":true,\"needs_packing\":true,\"estimated_items\":\"\",\"estimated_weight_kg\":100},\"meta\":{\"selected_service_ids\":[2],\"selected_services\":[{\"id\":2,\"name_ar\":\"فك وتركيب ونقل غرفة نوم\",\"name_en\":\"bedroom dismantling, assembly, and moving\",\"base_price\":50,\"price_per_kg\":1,\"price_per_meter\":0.5,\"minimum_charge\":0}],\"auto_estimated_price\":150}}', '', 'new', 150.00, NULL, NULL, '2026-03-08 22:55:18', '2026-03-08 22:55:18', 100.00, NULL, 15),
(4, 'FM26030915010', 34, 2, 4, 'الدمام', 'حمدي', '+966535036778', 'الدمام', 'الدمام', '', 'الخبر', NULL, NULL, 2, 1, 1, 0, 0, 0, 0, '{\"fields\":{\"rooms_count\":\"2\",\"floors_from\":\"1\",\"floors_to\":\"1\",\"elevator_from\":false,\"elevator_to\":false,\"needs_packing\":false,\"estimated_items\":\"\"},\"meta\":{\"selected_service_ids\":[2],\"selected_services\":[{\"id\":2,\"name_ar\":\"فك وتركيب ونقل غرفة نوم\",\"name_en\":\"bedroom dismantling, assembly, and moving\",\"base_price\":50,\"price_per_kg\":1,\"price_per_meter\":0.5,\"minimum_charge\":0}],\"auto_estimated_price\":50}}', '', 'new', 50.00, NULL, NULL, '2026-03-08 22:57:41', '2026-03-08 22:57:41', NULL, NULL, 16),
(5, 'FM26030965270', 34, 2, 4, 'الدمام', 'حمدي', '+966535036778', 'الدمام', 'ال', '', 'فلص', NULL, NULL, 1, 1, 1, 0, 0, 0, 0, '{\"fields\":{\"rooms_count\":\"1\",\"floors_from\":\"1\",\"floors_to\":\"1\",\"elevator_from\":false,\"elevator_to\":false,\"needs_packing\":false,\"estimated_items\":\"\"},\"meta\":{\"selected_service_ids\":[2],\"selected_services\":[{\"id\":2,\"name_ar\":\"فك وتركيب ونقل غرفة نوم\",\"name_en\":\"bedroom dismantling, assembly, and moving\",\"base_price\":50,\"price_per_kg\":1,\"price_per_meter\":0.5,\"minimum_charge\":0}],\"auto_estimated_price\":50}}', '', 'new', 50.00, NULL, NULL, '2026-03-08 22:59:42', '2026-03-08 22:59:42', NULL, NULL, 17),
(6, 'FM26031019107', 36, 2, 4, 'الدمام', 'حمدي', '+966535036778', 'الدمام', 'الشارع الأول', 'الخبر', 'الجسر', '2026-03-11', '06:53', 1, 2, 1, 1, 1, 1, 5, '{\"fields\":{\"rooms_count\":\"1\",\"floors_from\":\"2\",\"floors_to\":\"1\",\"elevator_from\":true,\"elevator_to\":true,\"needs_packing\":true,\"estimated_items\":\"5\",\"estimated_weight_kg\":100,\"estimated_distance_meters\":50},\"meta\":{\"selected_service_ids\":[2],\"selected_services\":[{\"id\":2,\"name_ar\":\"فك وتركيب ونقل غرفة نوم\",\"name_en\":\"bedroom dismantling, assembly, and moving\",\"base_price\":50,\"price_per_kg\":1,\"price_per_meter\":0.5,\"minimum_charge\":0}],\"auto_estimated_price\":175}}', 'تلىر', 'new', 175.00, NULL, NULL, '2026-03-10 01:53:52', '2026-03-10 01:53:52', 100.00, 50.00, 27),
(7, 'FM26031370079', 36, 2, 4, 'الدمام', 'حمدي', '+966535036778', 'الدمام', 'الدمام', '', 'الرياض', '2026-03-20', '20:52', 1, 2, 1, 0, 0, 0, 0, '{\"fields\":{\"rooms_count\":\"1\",\"floors_from\":\"2\",\"floors_to\":\"1\",\"elevator_from\":false,\"elevator_to\":false,\"needs_packing\":false,\"estimated_items\":\"\",\"estimated_weight_kg\":10},\"meta\":{\"selected_service_ids\":[2],\"selected_services\":[{\"id\":2,\"name_ar\":\"فك وتركيب ونقل غرفة نوم\",\"name_en\":\"bedroom dismantling, assembly, and moving\",\"base_price\":50,\"price_per_kg\":1,\"price_per_meter\":0.5,\"minimum_charge\":0}],\"auto_estimated_price\":60}}', '', 'new', 60.00, NULL, NULL, '2026-03-12 21:52:33', '2026-03-12 21:52:33', 10.00, NULL, 32),
(8, 'FM26031681522', 40, 2, 4, 'الدمام', 'حمدي احمد', '+966535036778', 'الدمام', 'الدمام', '', 'الخبر', NULL, NULL, 1, 2, 8, 0, 0, 0, 0, '{\"fields\":{\"rooms_count\":\"1\",\"floors_from\":\"2\",\"floors_to\":\"8\",\"elevator_from\":false,\"elevator_to\":false,\"needs_packing\":false,\"estimated_items\":\"\"},\"meta\":{\"selected_service_ids\":[2],\"selected_services\":[{\"id\":2,\"name_ar\":\"فك وتركيب ونقل غرفة نوم\",\"name_en\":\"bedroom dismantling, assembly, and moving\",\"base_price\":50,\"price_per_kg\":1,\"price_per_meter\":0.5,\"minimum_charge\":0}],\"auto_estimated_price\":50}}', '', 'new', 50.00, NULL, NULL, '2026-03-16 03:40:10', '2026-03-16 03:40:10', NULL, NULL, 40);

-- --------------------------------------------------------

--
-- بنية الجدول `furniture_request_fields`
--

CREATE TABLE `furniture_request_fields` (
  `id` int(11) NOT NULL,
  `field_key` varchar(80) NOT NULL,
  `label_ar` varchar(150) NOT NULL,
  `label_en` varchar(150) DEFAULT NULL,
  `field_type` varchar(30) NOT NULL DEFAULT 'text',
  `placeholder_ar` varchar(255) DEFAULT NULL,
  `placeholder_en` varchar(255) DEFAULT NULL,
  `options_json` longtext DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `label_ur` varchar(150) DEFAULT NULL,
  `placeholder_ur` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `furniture_request_fields`
--

INSERT INTO `furniture_request_fields` (`id`, `field_key`, `label_ar`, `label_en`, `field_type`, `placeholder_ar`, `placeholder_en`, `options_json`, `is_required`, `is_active`, `sort_order`, `created_at`, `updated_at`, `label_ur`, `placeholder_ur`) VALUES
(1, 'rooms_count', 'عدد الغرف', 'Rooms Count', 'number', 'مثال: 3', 'Example: 3', NULL, 1, 1, 1, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL, NULL),
(2, 'floors_from', 'الدور في موقع التحميل', 'Pickup Floor', 'number', 'مثال: 2', 'Example: 2', NULL, 1, 1, 2, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL, NULL),
(3, 'floors_to', 'الدور في موقع التنزيل', 'Dropoff Floor', 'number', 'مثال: 4', 'Example: 4', NULL, 1, 1, 3, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL, NULL),
(4, 'elevator_from', 'هل يوجد مصعد في موقع التحميل؟', 'Elevator at Pickup?', 'checkbox', NULL, NULL, NULL, 0, 1, 4, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL, NULL),
(5, 'elevator_to', 'هل يوجد مصعد في موقع التنزيل؟', 'Elevator at Dropoff?', 'checkbox', NULL, NULL, NULL, 0, 1, 5, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL, NULL),
(6, 'needs_packing', 'هل تحتاج خدمة تغليف؟', 'Needs Packing?', 'checkbox', NULL, NULL, NULL, 0, 1, 6, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL, NULL),
(7, 'estimated_items', 'عدد القطع التقريبي', 'Estimated Items', 'number', 'مثال: 25', 'Example: 25', NULL, 0, 1, 7, '2026-02-25 11:47:08', '2026-02-25 11:47:08', NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `furniture_services`
--

CREATE TABLE `furniture_services` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_note` varchar(255) DEFAULT NULL,
  `estimated_duration_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price_per_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_per_meter` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimum_charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  `name_ur` varchar(150) DEFAULT NULL,
  `description_ur` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `furniture_services`
--

INSERT INTO `furniture_services` (`id`, `name_ar`, `name_en`, `description_ar`, `description_en`, `base_price`, `price_note`, `estimated_duration_hours`, `image`, `is_active`, `sort_order`, `created_at`, `updated_at`, `price_per_kg`, `price_per_meter`, `minimum_charge`, `name_ur`, `description_ur`) VALUES
(1, 'اختبار', 'test', 'اختبار', 'test', 100.00, 'يبدأ من ١٠٠ ريال حسب المنطقه', 2.00, 'services/699eeef2d2ee9_1772023538.jpg', 0, 0, '2026-02-25 12:45:38', '2026-03-08 22:50:46', 0.00, 0.00, 0.00, 'test', 'test'),
(2, 'فك وتركيب ونقل غرفة نوم', 'bedroom dismantling, assembly, and moving', '', '', 50.00, '', 0.00, 'services/69af1bbc82063_1773083580.png', 1, 0, '2026-03-08 22:51:50', '2026-03-09 19:13:00', 1.00, 0.50, 0.00, 'bedroom dismantling, assembly, and moving', ''),
(3, 'فك وتركيب ونقل غرفة اطفال', 'Disassembling, assembling, and moving a children&amp;#039;s room', '', '', 40.00, '', 0.00, 'services/69adfe106d5ad_1773010448.png', 1, 0, '2026-03-08 22:54:08', '2026-03-08 22:54:08', 1.00, 0.50, 0.00, 'Disassembling, assembling, and moving a children&amp;#039;s room', '');

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `type` enum('order','promotion','system','wallet','review') DEFAULT 'system',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `provider_id`, `title`, `body`, `type`, `data`, `is_read`, `created_at`) VALUES
(1, 2, NULL, 'تحديث رصيد النقاط', 'مبروك! تم إضافة 3 نقطة إلى رصيدك. رصيدك الحالي: 7 نقطة', 'promotion', NULL, 0, '2026-02-04 13:19:02'),
(2, 2, NULL, 'تحديث رصيد المحفظة', 'تم إضافة رصيد بقيمة 0.02 ر.س إلى محفظتك. السبب: إيداع رصيد من لوحة التحكم', '', NULL, 0, '2026-02-04 13:19:20'),
(3, 2, NULL, 'تحديث رصيد المحفظة', 'تم إضافة رصيد بقيمة 4,000.00 ر.س إلى محفظتك. السبب: إيداع رصيد من لوحة التحكم', '', NULL, 0, '2026-02-04 13:26:05'),
(4, 12, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(5, 10, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(6, 2, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(7, 14, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(8, 13, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(9, 11, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(10, 9, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(11, 8, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(12, 7, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(13, 15, NULL, 'بسم الله اختبار', 'بسم الله هذا اشعار من امير طلال', 'system', NULL, 0, '2026-03-01 20:43:55'),
(14, 12, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(15, 10, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(16, 2, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(17, 14, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(18, 13, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(19, 11, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(20, 9, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(21, 8, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(22, 7, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(23, 15, NULL, 'غقفيالبغف', 'ثقفثقفثقف', 'system', NULL, 0, '2026-03-01 20:50:24'),
(24, NULL, 4, 'ثقصفغصثقفغ', 'صثقغصثق', 'system', NULL, 0, '2026-03-01 20:50:33'),
(25, 17, NULL, 'تم استلام طلبك', 'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.', 'order', '{\"event\":\"order_created\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"pending\"}', 0, '2026-03-02 19:23:10'),
(26, 17, NULL, 'تم تأكيد مقدم الخدمة', 'تم قبول الطلب من مقدم الخدمة، وسيتم تحديثك عند التحرك إلى موقعك.', 'order', '{\"event\":\"provider_assignment_accepted\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"accepted\"}', 0, '2026-03-02 19:23:41'),
(27, 17, NULL, 'الفني في الطريق', 'مقدم الخدمة في الطريق إليك الآن.', 'order', '{\"event\":\"order_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"on_the_way\"}', 0, '2026-03-02 19:23:42'),
(28, 17, NULL, 'وصل الفني إلى موقعك', 'مقدم الخدمة وصل إلى العنوان المحدد.', 'order', '{\"event\":\"order_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"arrived\"}', 0, '2026-03-02 19:23:42'),
(29, 17, NULL, 'بدأ تنفيذ الخدمة', 'تم بدء تنفيذ الخدمة في موقعك.', 'order', '{\"event\":\"order_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"in_progress\"}', 0, '2026-03-02 19:23:43'),
(30, 17, NULL, 'تم تحديث التقدير المبدئي', 'تم تحديث تقدير التكلفة المبدئي لطلبك من العمليات.', 'order', '{\"event\":\"estimate_updated\",\"order_id\":11,\"deep_link\":\"order:11\"}', 0, '2026-03-02 19:23:43'),
(31, 17, NULL, 'تم إصدار الفاتورة', 'الفاتورة جاهزة للمراجعة. يمكنك الموافقة أو طلب تعديل قبل الدفع.', 'order', '{\"event\":\"invoice_submitted\",\"order_id\":11,\"deep_link\":\"order:11\",\"invoice_status\":\"pending\"}', 0, '2026-03-02 19:23:43'),
(32, 17, NULL, 'تم إكمال الخدمة', 'تم تنفيذ طلبك بنجاح. يمكنك الآن الدفع وتقييم الخدمة.', 'order', '{\"event\":\"job_completed\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"completed\"}', 0, '2026-03-02 19:24:16'),
(33, 28, NULL, 'تم استلام طلبك', 'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.', 'order', '{\"event\":\"order_created\",\"order_id\":12,\"deep_link\":\"order:12\",\"status\":\"pending\"}', 0, '2026-03-02 21:44:03'),
(34, 28, NULL, 'تم إلغاء الطلب', 'تم تأكيد إلغاء طلبك بنجاح.', 'order', '{\"event\":\"order_cancelled\",\"order_id\":12,\"deep_link\":\"order:12\",\"status\":\"cancelled\"}', 0, '2026-03-02 21:44:04'),
(35, 28, NULL, 'تم استلام طلبك', 'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.', 'order', '{\"event\":\"order_created\",\"order_id\":13,\"deep_link\":\"order:13\",\"status\":\"pending\"}', 0, '2026-03-02 21:44:45'),
(36, 28, NULL, 'الفني في الطريق', 'مقدم الخدمة في الطريق إليك الآن.', 'order', '{\"event\":\"order_status_updated\",\"order_id\":13,\"deep_link\":\"order:13\",\"status\":\"on_the_way\"}', 0, '2026-03-02 21:44:46'),
(37, 17, NULL, 'تحديث البيانات الشخصية', 'تم تحديث بيانات ملفك الشخصي من قبل الإدارة', '', NULL, 0, '2026-03-05 02:22:43'),
(38, 17, NULL, 'تم تحديث التقدير المبدئي', 'تم تحديث التقدير المبدئي لتكلفة طلبك من مركز العمليات.', 'order', '{\"event\":\"estimate_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"min_estimate\":33.3299999999999982946974341757595539093017578125,\"max_estimate\":44.43999999999999772626324556767940521240234375}', 0, '2026-03-05 02:33:40'),
(39, 17, NULL, 'تعذر التواصل مؤقتًا', 'تعذر الوصول إليك، يرجى متابعة هاتفك لتأكيد الموعد.', 'order', '{\"event\":\"confirmation_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"confirmation_status\":\"unreachable\"}', 0, '2026-03-05 02:33:40'),
(40, 17, NULL, 'تم إغلاق الطلب', 'تم إنهاء الطلب بنجاح. يرجى تقييم الخدمة.', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"completed\"}', 0, '2026-03-05 02:33:41'),
(41, NULL, 6, 'تحديث على الطلب #11', 'تم تحديث حالة الطلب إلى: مكتمل', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"target\":\"provider\",\"status\":\"completed\"}', 0, '2026-03-05 02:33:41'),
(42, 17, NULL, 'الفني في الطريق', 'مقدم الخدمة في الطريق إليك الآن.', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"on_the_way\"}', 0, '2026-03-05 02:33:42'),
(43, NULL, 6, 'تحديث على الطلب #11', 'تم تحديث حالة الطلب إلى: في الطريق', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"target\":\"provider\",\"status\":\"on_the_way\"}', 0, '2026-03-05 02:33:42'),
(44, 17, NULL, 'تم إلغاء الطلب', 'تم إلغاء الطلب من مركز العمليات. سنخدمك في أي وقت.', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"cancelled\"}', 0, '2026-03-05 02:38:49'),
(45, NULL, 6, 'تحديث على الطلب #11', 'تم تحديث حالة الطلب إلى: ملغي', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"target\":\"provider\",\"status\":\"cancelled\"}', 0, '2026-03-05 02:38:49'),
(46, 17, NULL, 'الفني في الطريق', 'مقدم الخدمة في الطريق إليك الآن.', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"status\":\"on_the_way\"}', 0, '2026-03-05 02:38:49'),
(47, NULL, 6, 'تحديث على الطلب #11', 'تم تحديث حالة الطلب إلى: في الطريق', 'order', '{\"event\":\"admin_status_updated\",\"order_id\":11,\"deep_link\":\"order:11\",\"target\":\"provider\",\"status\":\"on_the_way\"}', 0, '2026-03-05 02:38:50'),
(69, NULL, NULL, 'تنبيه إداري: رفض فاتورة طلب', 'العميل \"حمدي\" رفض فاتورة الطلب #RT11001 (القيمة 50.00 ر.س). رقم التواصل: +966535036778. مقدم الخدمة: عباس تكييف. يرجى متابعة الحالة هاتفيًا.', 'system', '{\"event\":\"invoice_rejected\",\"order_id\":21,\"order_number\":\"RT11001\",\"client_name\":\"حمدي\",\"client_phone\":\"+966535036778\",\"provider_name\":\"عباس تكييف\"}', 0, '2026-03-09 04:32:56'),
(120, 37, NULL, 'تم استلام طلبك', 'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.', 'order', '{\"event\":\"order_created\",\"order_id\":35,\"deep_link\":\"order:35\",\"status\":\"pending\"}', 0, '2026-03-12 22:43:40'),
(121, 37, NULL, 'تم إصدار رابط الدفع', 'تم إصدار رابط دفع لطلبك. أكمل الدفع لتأكيد الطلب.', 'order', '{\"event\":\"payment_link_created\",\"order_id\":35,\"deep_link\":\"order:35\",\"status\":\"pending\",\"invoice_id\":\"70079979\",\"payment_required\":true}', 0, '2026-03-12 22:44:00'),
(122, 37, NULL, 'تم استلام طلبك', 'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.', 'order', '{\"event\":\"order_created\",\"order_id\":36,\"deep_link\":\"order:36\",\"status\":\"pending\"}', 0, '2026-03-14 04:46:05'),
(123, 37, NULL, 'تم استلام طلبك', 'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.', 'order', '{\"event\":\"order_created\",\"order_id\":37,\"deep_link\":\"order:37\",\"status\":\"pending\"}', 0, '2026-03-14 04:48:32'),
(129, 41, NULL, 'تم استلام طلبك', 'طلبك قيد المراجعة من العمليات وسيتم تحديثك أولاً بأول.', 'order', '{\"event\":\"order_created\",\"order_id\":41,\"deep_link\":\"order:41\",\"status\":\"pending\"}', 0, '2026-04-02 17:37:08');

-- --------------------------------------------------------

--
-- بنية الجدول `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `channel` enum('email','whatsapp','both') NOT NULL DEFAULT 'email',
  `event_type` varchar(80) NOT NULL COMMENT 'new_order, new_complaint, incomplete_order, new_furniture_request, new_container_request, order_status_change',
  `recipient_email` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(30) DEFAULT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'order, complaint, furniture_request, container_request',
  `reference_id` int(11) DEFAULT NULL,
  `status` enum('sent','failed','queued') NOT NULL DEFAULT 'queued',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notification_recipients`
--

CREATE TABLE `notification_recipients` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL COMMENT 'WhatsApp number with country code',
  `receive_new_orders` tinyint(1) NOT NULL DEFAULT 1,
  `receive_complaints` tinyint(1) NOT NULL DEFAULT 1,
  `receive_furniture` tinyint(1) NOT NULL DEFAULT 1,
  `receive_containers` tinyint(1) NOT NULL DEFAULT 1,
  `receive_incomplete` tinyint(1) NOT NULL DEFAULT 0,
  `channels` varchar(50) NOT NULL DEFAULT 'email' COMMENT 'email, whatsapp, both',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notification_recipients`
--

INSERT INTO `notification_recipients` (`id`, `name`, `email`, `phone`, `receive_new_orders`, `receive_complaints`, `receive_furniture`, `receive_containers`, `receive_incomplete`, `channels`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'عمليات تجريب', 'ss4s@live.com', '+966535036778', 1, 1, 1, 1, 1, 'both', 1, '2026-03-16 02:39:10', '2026-03-16 02:39:10');

-- --------------------------------------------------------

--
-- بنية الجدول `offers`
--

CREATE TABLE `offers` (
  `id` int(11) NOT NULL,
  `title_ar` varchar(200) NOT NULL,
  `title_en` varchar(200) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `target_audience` enum('all','new','existing') NOT NULL DEFAULT 'all',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `link_type` enum('none','category','offer','product','spare_parts','external') NOT NULL DEFAULT 'none',
  `link_id` int(11) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `offers`
--

INSERT INTO `offers` (`id`, `title_ar`, `title_en`, `description_ar`, `description_en`, `image`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `category_id`, `target_audience`, `start_date`, `end_date`, `usage_limit`, `used_count`, `is_active`, `created_at`, `link_type`, `link_id`, `link`) VALUES
(1, 'بالبلنتايب', NULL, 'عاهتتنماهعل', NULL, 'offers/69874678c4a92_1770473080.jpeg', 'percentage', 10.00, 0.00, NULL, 1, 'all', '2026-02-07', '2026-02-28', NULL, 0, 1, '2026-02-07 13:22:01', 'none', NULL, NULL),
(2, 'عرض الصيف', NULL, 'خصم خاص على جميع خدمات التكييف', NULL, 'offers/698ae0bf0b884_1770709183.avif', 'percentage', 25.00, 0.00, NULL, NULL, 'all', '2026-02-09', '2026-03-11', NULL, 0, 1, '2026-02-09 12:26:56', 'none', NULL, NULL),
(3, 'باقة التوفير', NULL, 'اطلب 3 خدمات واحصل على الرابعة مجانا', NULL, 'offers/698ae0aede6c6_1770709166.jpeg', 'fixed', 100.00, 0.00, NULL, NULL, 'all', '2026-02-09', '2026-04-10', NULL, 0, 1, '2026-02-09 12:26:56', 'none', NULL, NULL),
(4, 'عرض الصيف', NULL, 'خصم خاص على جميع خدمات التكييف', NULL, 'offers/698ae0cdad8d0_1770709197.avif', 'percentage', 25.00, 0.00, NULL, NULL, 'all', '2026-02-09', '2026-03-11', NULL, 0, 1, '2026-02-09 12:27:46', 'none', NULL, NULL),
(5, 'باقة التوفير', NULL, 'اطلب 3 خدمات واحصل على الرابعة مجانا', NULL, 'offers/698ae0c61eb66_1770709190.png', 'fixed', 100.00, 0.00, NULL, NULL, 'all', '2026-02-09', '2026-04-10', NULL, 0, 1, '2026-02-09 12:27:46', 'none', NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `status` enum('pending','accepted','assigned','on_the_way','arrived','in_progress','completed','cancelled','rejected') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `problem_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`problem_details`)),
  `min_estimate` decimal(10,2) DEFAULT NULL,
  `max_estimate` decimal(10,2) DEFAULT NULL,
  `labor_cost` decimal(10,2) DEFAULT 0.00,
  `parts_cost` decimal(10,2) DEFAULT 0.00,
  `invoice_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`invoice_items`)),
  `invoice_status` enum('none','pending','approved','rejected') DEFAULT 'none',
  `inspection_notes` text DEFAULT NULL,
  `is_rated` tinyint(1) DEFAULT 0,
  `address` text DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `confirmation_status` enum('pending','confirmed','unreachable','cancelled') DEFAULT 'pending',
  `confirmation_due_at` datetime DEFAULT NULL,
  `confirmation_attempts` int(11) DEFAULT 0,
  `confirmation_notes` text DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  `problem_description` text DEFAULT NULL,
  `problem_images` longtext DEFAULT NULL,
  `inspection_fee` decimal(10,2) DEFAULT 0.00,
  `service_fee` decimal(10,2) DEFAULT 0.00,
  `parts_fee` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `payment_method` enum('cash','wallet','card','apple_pay') DEFAULT 'cash',
  `payment_status` enum('pending','paid','refunded','failed') DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` text DEFAULT NULL,
  `cancelled_by` enum('user','provider','admin') DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `myfatoorah_invoice_id` varchar(100) DEFAULT NULL,
  `myfatoorah_payment_url` text DEFAULT NULL,
  `myfatoorah_payment_method_id` int(11) DEFAULT NULL,
  `myfatoorah_payment_id` varchar(150) DEFAULT NULL,
  `myfatoorah_invoice_status` varchar(50) DEFAULT NULL,
  `myfatoorah_last_status_at` datetime DEFAULT NULL,
  `inspection_images` longtext DEFAULT NULL,
  `subtotal_amount` decimal(10,2) DEFAULT NULL,
  `promo_code` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `provider_id`, `category_id`, `status`, `total_amount`, `created_at`, `attachments`, `problem_details`, `min_estimate`, `max_estimate`, `labor_cost`, `parts_cost`, `invoice_items`, `invoice_status`, `inspection_notes`, `is_rated`, `address`, `lat`, `lng`, `notes`, `scheduled_date`, `scheduled_time`, `confirmation_status`, `confirmation_due_at`, `confirmation_attempts`, `confirmation_notes`, `confirmed_at`, `address_id`, `problem_description`, `problem_images`, `inspection_fee`, `service_fee`, `parts_fee`, `discount_amount`, `payment_method`, `payment_status`, `started_at`, `cancelled_at`, `cancel_reason`, `cancelled_by`, `admin_notes`, `updated_at`, `myfatoorah_invoice_id`, `myfatoorah_payment_url`, `myfatoorah_payment_method_id`, `myfatoorah_payment_id`, `myfatoorah_invoice_status`, `myfatoorah_last_status_at`, `inspection_images`, `subtotal_amount`, `promo_code`) VALUES
(1, 'RT79023', 2, NULL, 2, 'pending', 0.00, '2026-02-15 22:11:08', '[\"orders\\/6992447c4f9f3_1771193468.png\"]', '{\"type\":\"انسداد\",\"user_desc\":\"ةتغغععغ\"}', NULL, NULL, 0.00, 0.00, NULL, 'none', NULL, 0, '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.22152917, 31.16933540, 'ةتغغععغ', '2026-02-16', '00:11:00', 'pending', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-02-18 03:22:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'RT26288', 2, NULL, 6, 'pending', 0.00, '2026-02-15 22:14:00', '[\"orders\\/6992452817b95_1771193640.png\"]', '{\"type\":\"انسداد\",\"user_desc\":\"هةخاختهوه\"}', NULL, NULL, 0.00, 0.00, NULL, 'none', NULL, 0, '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.22152917, 31.16933540, 'هةخاختهوه', '2026-02-16', '00:13:00', 'pending', NULL, 0, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-02-18 03:22:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'RT63704', 2, NULL, 2, 'pending', 0.00, '2026-02-18 03:00:22', '[\"orders\\/69952b462c52f_1771383622.jpg\"]', '{\"type\":\"انسداد\",\"user_desc\":\"نبنبنبن\",\"sub_services\":[]}', NULL, NULL, 0.00, 0.00, NULL, 'none', NULL, 0, '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', 30.22156683, 31.16940681, 'نبنبنبن', '2026-02-18', '05:00:00', 'confirmed', '2026-02-18 03:00:00', 0, '', '2026-02-18 12:52:39', NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-02-25 12:03:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'RT59027', 10, NULL, 1, 'pending', 0.00, '2026-02-21 10:33:27', NULL, NULL, 100.00, 150.00, 0.00, 0.00, NULL, 'none', NULL, 0, 'الرياض اختبار نهائي', 24.71360000, 46.67530000, 'payment verification temp', NULL, NULL, 'pending', NULL, 0, NULL, NULL, NULL, 'payment verification temp', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-02-25 12:03:53', '68146530', 'https://sa.myfatoorah.com/Ar/SAU/PayInvoice/Checkout?invoiceKey=06081142666814653072-3792b973&paymentGatewayId=27', 6, 'INV-68146530', 'Pending', '2026-02-21 14:00:23', NULL, NULL, NULL),
(11, 'RT83454', 17, 6, 1, 'completed', 150.00, '2026-03-02 19:23:10', NULL, NULL, 0.00, 0.00, 100.00, 50.00, '[{\"name\":\"قطع غيار\",\"quantity\":1,\"unit_price\":50,\"total_price\":50,\"source\":\"manual_parts_cost\"}]', 'approved', 'فاتورة اختبار تكامل', 0, 'عنوان اختبار تكامل البروفايدر', 24.71360000, 46.67530000, 'طلب اختبار end-to-end', '2026-03-03', '10:30:00', 'pending', '2026-03-03 08:30:00', 1, 'BTN_CONFIRM_TRY', '2026-03-05 05:32:07', NULL, 'طلب اختبار end-to-end', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', '2026-03-02 22:23:43', NULL, NULL, NULL, 'ثقصفصثف', '2026-03-05 02:45:50', '69034665', 'https://sa.myfatoorah.com/Ar/SAU/PayInvoice/Checkout?invoiceKey=06081142666903466573-b165d22d&paymentGatewayId=27', 6, '0808690346656721164383', 'Pending', '2026-03-05 05:36:19', NULL, NULL, NULL),
(35, 'RT53822', 37, NULL, 11, 'pending', 750.00, '2026-03-12 22:43:40', NULL, '{\"type\":\"spare_parts_order\",\"requires_installation\":true,\"spare_parts\":[{\"spare_part_id\":3,\"name\":\"فلتر مياه\",\"quantity\":1,\"pricing_mode\":\"with_installation\",\"requires_installation\":true,\"notes\":\"\",\"unit_price\":750}],\"is_custom_service\":true,\"custom_service\":{\"title\":\"طلب قطع غيار\",\"description\":\"\"}}', NULL, NULL, 0.00, 0.00, NULL, 'none', NULL, 0, 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', 30.56559767, 32.23357223, NULL, '2026-03-26', '08:43:00', 'pending', '2026-03-26 06:43:00', 0, NULL, NULL, NULL, '', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-03-12 22:44:11', '70079979', 'https://sa.myfatoorah.com/Ar/SAU/PayInvoice/Checkout?invoiceKey=06081142667007997974-073c9faf&paymentGatewayId=27', 6, '0808700799796805227985', 'Pending', '2026-03-13 01:44:11', NULL, 750.00, NULL),
(36, 'RT22199', 37, NULL, 11, 'pending', 0.00, '2026-03-14 04:46:05', NULL, '{\"type\":\"spare_parts_order\",\"requires_installation\":true,\"spare_parts\":[{\"spare_part_id\":3,\"name\":\"فلتر مياه\",\"quantity\":1,\"pricing_mode\":\"with_installation\",\"requires_installation\":true,\"notes\":\"\",\"unit_price\":750}],\"is_custom_service\":true,\"custom_service\":{\"title\":\"طلب قطع غيار\",\"description\":\"\"}}', NULL, NULL, 0.00, 0.00, NULL, 'none', NULL, 0, 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', 30.56559767, 32.23357223, NULL, '2026-03-20', '08:45:00', 'pending', '2026-03-20 06:45:00', 0, NULL, NULL, NULL, '', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-03-14 04:46:05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'RT05862', 37, NULL, 11, 'pending', 0.00, '2026-03-14 04:48:32', NULL, '{\"type\":\"spare_parts_order\",\"requires_installation\":true,\"spare_parts\":[{\"spare_part_id\":3,\"name\":\"فلتر مياه\",\"quantity\":1,\"pricing_mode\":\"with_installation\",\"requires_installation\":true,\"notes\":\"\",\"unit_price\":750}],\"is_custom_service\":true,\"custom_service\":{\"title\":\"طلب قطع غيار\",\"description\":\"\"}}', NULL, NULL, 0.00, 0.00, NULL, 'none', NULL, 0, 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', 30.56559767, 32.23357223, NULL, '2026-03-27', '08:48:00', 'pending', '2026-03-27 06:48:00', 0, NULL, NULL, NULL, '', NULL, 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-03-14 04:48:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'RT88032', 41, NULL, 18, 'pending', 0.00, '2026-04-02 17:37:08', '[\"orders\\/69cea944aba3f_1775151428.jpg\"]', '{\"type\":\"container_rental\",\"user_desc\":\"مخلفات\",\"module\":\"container_rental\",\"container_request\":{\"container_service_name\":\"حاوية ٢٠\",\"container_size\":\"٢٠\",\"site_city\":\"القاهرة\",\"site_address\":\"H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate\",\"notes\":\"مخلفات\",\"container_service_id\":1,\"duration_days\":1,\"quantity\":1,\"capacity_ton\":0.02,\"daily_price\":2,\"weekly_price\":14,\"monthly_price\":0,\"delivery_fee\":100,\"price_per_kg\":0,\"price_per_meter\":0,\"minimum_charge\":0,\"needs_loading_help\":false,\"needs_operator\":false},\"is_custom_service\":true,\"custom_service\":{\"title\":\"طلب خدمة الحاويات - حاوية ٢٠\",\"description\":\"مخلفات\"}}', NULL, NULL, 0.00, 0.00, NULL, 'none', NULL, 0, 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', 30.56560200, 32.23340191, 'مخلفات', NULL, NULL, 'pending', NULL, 0, NULL, NULL, NULL, 'مخلفات', '[\"orders\\/69cea944aba3f_1775151428.jpg\"]', 0.00, 0.00, 0.00, 0.00, 'cash', 'pending', NULL, NULL, NULL, NULL, NULL, '2026-04-02 17:37:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `order_live_locations`
--

CREATE TABLE `order_live_locations` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `lat` decimal(10,8) NOT NULL,
  `lng` decimal(11,8) NOT NULL,
  `accuracy` decimal(10,2) DEFAULT NULL,
  `speed` decimal(10,2) DEFAULT NULL,
  `heading` decimal(10,2) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `order_live_locations`
--

INSERT INTO `order_live_locations` (`id`, `order_id`, `provider_id`, `lat`, `lng`, `accuracy`, `speed`, `heading`, `created_at`) VALUES
(2, 21, 17, 30.56569270, 32.23357700, 30.19, 0.00, 0.00, '2026-03-09 07:31:41'),
(3, 21, 17, 30.56569270, 32.23357700, 30.19, 0.00, 0.00, '2026-03-09 07:31:42'),
(4, 21, 17, 30.56552330, 32.23356400, 17.85, 2.03, 188.16, '2026-03-09 07:32:03'),
(5, 25, 18, 30.56570560, 32.23357090, 52.17, 0.00, 0.00, '2026-03-10 04:14:51'),
(6, 25, 18, 30.56570560, 32.23357090, 52.17, 0.00, 0.00, '2026-03-10 04:14:53'),
(7, 25, 18, 30.56578650, 32.23355400, 15.72, 0.02, 0.00, '2026-03-10 04:16:01'),
(8, 26, 18, 30.56570510, 32.23359010, 52.82, 0.00, 0.00, '2026-03-10 04:41:10'),
(9, 26, 18, 30.56570510, 32.23359010, 52.82, 0.00, 0.00, '2026-03-10 04:41:12'),
(10, 26, 18, 30.56573490, 32.23357860, 11.79, 2.20, 161.54, '2026-03-10 04:42:19');

-- --------------------------------------------------------

--
-- بنية الجدول `order_providers`
--

CREATE TABLE `order_providers` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `assignment_status` varchar(32) NOT NULL DEFAULT 'assigned',
  `assigned_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `order_providers`
--

INSERT INTO `order_providers` (`id`, `order_id`, `provider_id`, `assignment_status`, `assigned_at`, `created_at`) VALUES
(8, 11, 6, 'completed', '2026-03-02 21:23:18', '2026-03-02 19:23:18');

-- --------------------------------------------------------

--
-- بنية الجدول `order_services`
--

CREATE TABLE `order_services` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `order_services`
--

INSERT INTO `order_services` (`id`, `order_id`, `service_id`, `service_name`, `is_custom`, `notes`, `created_at`) VALUES
(4, 18, NULL, 'طلب خدمة الحاويات - حاوية ٢٠', 1, 'باياياب', '2026-03-08 23:03:40'),
(5, 19, 2, 'تأسيس سباكة', 0, NULL, '2026-03-09 02:17:34'),
(6, 20, NULL, 'طلب قطع غيار', 1, '', '2026-03-09 02:19:31'),
(7, 21, 1, 'صيانة مكيف سبليت', 0, NULL, '2026-03-09 04:20:43'),
(8, 22, 1, 'صيانة مكيف سبليت', 0, NULL, '2026-03-09 04:36:50'),
(9, 23, 2, 'تأسيس مواسير المياه', 0, NULL, '2026-03-09 20:19:56'),
(10, 24, 2, 'تأسيس مواسير المياه', 0, NULL, '2026-03-09 20:25:44'),
(11, 24, 6, 'تأسيس مواسير الصرف', 0, NULL, '2026-03-09 20:25:44'),
(12, 24, 7, 'تمديد خطوط التغذية', 0, NULL, '2026-03-09 20:25:44'),
(13, 25, 2, 'تأسيس مواسير المياه', 0, NULL, '2026-03-10 01:09:51'),
(14, 26, 2, 'تأسيس مواسير المياه', 0, NULL, '2026-03-10 01:36:49'),
(15, 28, NULL, 'طلب خدمة الحاويات - حاوية ٢٠', 1, 'اىرؤؤر', '2026-03-10 01:55:43'),
(16, 29, NULL, 'طلب قطع غيار', 1, 'خغلهغرهفره', '2026-03-10 01:57:41'),
(17, 31, 2, 'تأسيس مواسير المياه', 0, NULL, '2026-03-12 21:50:02'),
(18, 31, 6, 'تأسيس مواسير الصرف', 0, NULL, '2026-03-12 21:50:02'),
(19, 31, 7, 'تمديد خطوط التغذية', 0, NULL, '2026-03-12 21:50:02'),
(20, 33, NULL, 'طلب خدمة الحاويات - حاوية ٢٠', 1, 'بتتتي', '2026-03-12 21:57:42'),
(21, 34, NULL, 'طلب قطع غيار', 1, '', '2026-03-12 22:39:18'),
(22, 35, NULL, 'طلب قطع غيار', 1, '', '2026-03-12 22:43:40'),
(23, 36, NULL, 'طلب قطع غيار', 1, '', '2026-03-14 04:46:05'),
(24, 37, NULL, 'طلب قطع غيار', 1, '', '2026-03-14 04:48:32'),
(25, 38, NULL, 'طلب خدمة الحاويات - حاوية ٢٠', 1, 'استخدام للقمامة', '2026-03-16 03:12:19'),
(26, 39, 2, 'تأسيس مواسير المياه', 0, NULL, '2026-03-16 03:35:49'),
(27, 39, 6, 'تأسيس مواسير الصرف', 0, NULL, '2026-03-16 03:35:49'),
(28, 41, NULL, 'طلب خدمة الحاويات - حاوية ٢٠', 1, 'مخلفات', '2026-04-02 17:37:08');

-- --------------------------------------------------------

--
-- بنية الجدول `order_spare_parts`
--

CREATE TABLE `order_spare_parts` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `store_id` int(11) DEFAULT NULL,
  `spare_part_id` int(11) DEFAULT NULL,
  `spare_part_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `is_committed` tinyint(1) NOT NULL DEFAULT 0,
  `committed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pricing_mode` varchar(32) DEFAULT NULL,
  `requires_installation` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `order_spare_parts`
--

INSERT INTO `order_spare_parts` (`id`, `order_id`, `provider_id`, `store_id`, `spare_part_id`, `spare_part_name`, `quantity`, `unit_price`, `total_price`, `notes`, `is_committed`, `committed_at`, `created_at`, `pricing_mode`, `requires_installation`) VALUES
(3, 19, NULL, 0, 3, 'فلتر مياه', 1, 750.00, 750.00, '', 0, NULL, '2026-03-09 02:17:34', 'with_installation', 1);

-- --------------------------------------------------------

--
-- بنية الجدول `problem_detail_options`
--

CREATE TABLE `problem_detail_options` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `title_ar` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `title_ur` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `problem_detail_options`
--

INSERT INTO `problem_detail_options` (`id`, `category_id`, `service_id`, `title_ar`, `title_en`, `title_ur`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 12, 2, 'عدم وجود تمديدات مياه جاهزة للموقع', 'No water piping network installed in the property', NULL, 0, 1, '2026-03-09 20:14:57', '2026-03-09 20:14:57'),
(2, 12, 2, 'تأسيس شبكة مياه كاملة لوحدة جديدة', 'install a complete water piping network for a new unit', NULL, 0, 1, '2026-03-09 20:15:30', '2026-03-09 20:15:30'),
(3, 12, 2, 'التمديدات الحالية قديمة أو غير مناسبة للتصميم الجديد', 'Existing water pipes are old or unsuitable for the new layout', NULL, 0, 1, '2026-03-09 20:15:52', '2026-03-09 20:15:52');

-- --------------------------------------------------------

--
-- بنية الجدول `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(200) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `name_en` varchar(200) DEFAULT NULL,
  `original_price` decimal(10,2) DEFAULT NULL,
  `discount_percent` int(11) DEFAULT 0,
  `stock_quantity` int(11) DEFAULT 0,
  `description_ar` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `promo_codes`
--

CREATE TABLE `promo_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `title_ar` varchar(255) DEFAULT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `title_ur` varchar(255) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_ur` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_per_user` int(11) DEFAULT 1,
  `used_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `usage_limit_per_user` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `promo_codes`
--

INSERT INTO `promo_codes` (`id`, `code`, `title_ar`, `title_en`, `title_ur`, `description_ar`, `description_en`, `description_ur`, `description`, `image`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount_amount`, `start_date`, `end_date`, `usage_limit`, `usage_per_user`, `used_count`, `is_active`, `created_at`, `usage_limit_per_user`) VALUES
(1, 'EID30', 'خصم حصري بمناسبة عيد الفطر', 'Exclusive discount for Eid al-Fitr', 'ordo', NULL, NULL, NULL, 'استخدم الكود eid30  واستمتع بخصم 30 ٪ علي الخدمات', 'offers/69af209045f7c_1773084816.png', 'fixed', 30.00, 0.00, NULL, '2026-03-09', '2026-04-30', 10, 1, 3, 1, '2026-03-09 04:56:54', 1);

-- --------------------------------------------------------

--
-- بنية الجدول `promo_code_usages`
--

CREATE TABLE `promo_code_usages` (
  `id` int(11) NOT NULL,
  `promo_code_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `used_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `promo_code_usages`
--

INSERT INTO `promo_code_usages` (`id`, `promo_code_id`, `user_id`, `order_id`, `used_at`) VALUES
(1, 1, 40, 38, '2026-03-16 03:20:26');

-- --------------------------------------------------------

--
-- بنية الجدول `providers`
--

CREATE TABLE `providers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default-provider.png',
  `status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `is_available` tinyint(1) DEFAULT 1,
  `rating` decimal(3,2) DEFAULT 0.00,
  `wallet_balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `total_reviews` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `completed_orders` int(11) DEFAULT 0,
  `commission_rate` decimal(5,2) DEFAULT 15.00,
  `experience_years` int(11) DEFAULT 0,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `profile_completed` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_number` varchar(32) DEFAULT NULL,
  `current_lat` decimal(10,8) DEFAULT NULL,
  `current_lng` decimal(11,8) DEFAULT NULL,
  `location_updated_at` datetime DEFAULT NULL,
  `residency_document_path` varchar(255) DEFAULT NULL,
  `categories_locked` tinyint(1) NOT NULL DEFAULT 0,
  `device_token` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `ajeer_certificate_path` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deletion_requested_at` datetime DEFAULT NULL,
  `deletion_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `providers`
--

INSERT INTO `providers` (`id`, `full_name`, `phone`, `email`, `password`, `avatar`, `status`, `is_available`, `rating`, `wallet_balance`, `created_at`, `city`, `district`, `total_reviews`, `total_orders`, `completed_orders`, `commission_rate`, `experience_years`, `approved_at`, `approved_by`, `bio`, `otp_code`, `is_verified`, `last_login`, `profile_completed`, `whatsapp_number`, `current_lat`, `current_lng`, `location_updated_at`, `residency_document_path`, `categories_locked`, `device_token`, `country`, `ajeer_certificate_path`, `deleted_at`, `deletion_requested_at`, `deletion_reason`) VALUES
(4, 'مقدم خدمة جديد', '966555123457', NULL, NULL, 'default-provider.png', 'approved', 0, 0.00, 0.00, '2026-02-27 13:45:51', NULL, NULL, 0, 0, 0, 15.00, 0, NULL, NULL, NULL, NULL, 1, '2026-02-27 15:51:05', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'مقدم خدمة جديد', '+966500000001', NULL, NULL, 'default-provider.png', 'approved', 0, 0.00, 0.00, '2026-03-02 19:09:15', NULL, NULL, 0, 0, 0, 15.00, 0, NULL, NULL, NULL, NULL, 1, '2026-03-02 22:10:28', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'اختبار بروفايدر مكتمل', '+966500000003', 'provider.test+3@example.com', NULL, 'uploads/avatars/provider_69a5e2a848a2e8.25143163.png', 'approved', 0, 0.00, 0.00, '2026-03-02 19:11:25', 'الرياض', 'العليا', 0, 0, 0, 15.00, 5, NULL, NULL, 'فني اختبار لتكامل النظام', NULL, 1, '2026-03-02 22:25:15', 1, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'مقدم خدمة جديد', '+966562480876', NULL, NULL, 'default-provider.png', 'approved', 0, 0.00, 0.00, '2026-03-02 19:47:57', NULL, NULL, 0, 0, 0, 15.00, 0, NULL, NULL, NULL, '2418', 1, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'مقدم خدمة جديد', '+966572480876', NULL, NULL, 'default-provider.png', 'approved', 0, 0.00, 0.00, '2026-03-02 19:47:58', NULL, NULL, 0, 0, 0, 15.00, 0, NULL, NULL, NULL, '3888', 1, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'مقدم خدمة جديد', '0502000202', NULL, NULL, 'default-provider.png', 'approved', 0, 0.00, 0.00, '2026-03-02 21:42:02', NULL, NULL, 0, 0, 0, 15.00, 0, NULL, NULL, NULL, NULL, 1, '2026-03-03 00:44:45', 0, NULL, 24.71500000, 46.67700000, '2026-03-03 00:44:46', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'مزود اختبار', '+966555002222', NULL, NULL, 'default-provider.png', 'approved', 0, 0.00, 0.00, '2026-03-04 14:00:18', 'الجيزة', NULL, 0, 0, 0, 15.00, 0, NULL, NULL, 'سيرة تجريبية', NULL, 1, '2026-03-04 17:00:18', 0, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'مقدم خدمة جديد', '+966540465689', NULL, NULL, 'default-provider.png', 'pending', 0, 0.00, 0.00, '2026-03-16 03:23:07', NULL, NULL, 0, 0, 0, 15.00, 0, NULL, NULL, NULL, NULL, 1, '2026-03-16 06:23:16', 0, NULL, NULL, NULL, NULL, NULL, 0, '491f4b88-8ffb-4a89-8567-2d8b2306481a', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `provider_services`
--

CREATE TABLE `provider_services` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `provider_services`
--

INSERT INTO `provider_services` (`id`, `provider_id`, `category_id`, `created_at`) VALUES
(3, 6, 1, '2026-03-02 19:19:04'),
(4, 6, 2, '2026-03-02 19:19:04');

-- --------------------------------------------------------

--
-- بنية الجدول `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL COMMENT '1 to 5',
  `comment` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quality_rating` tinyint(4) DEFAULT NULL,
  `speed_rating` tinyint(4) DEFAULT NULL,
  `price_rating` tinyint(4) DEFAULT NULL,
  `behavior_rating` tinyint(4) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `rewards`
--

CREATE TABLE `rewards` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `title_ur` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_ur` text DEFAULT NULL,
  `points_required` int(11) NOT NULL,
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `discount_type` enum('percentage','fixed') DEFAULT 'fixed',
  `icon` varchar(50) DEFAULT 'gift',
  `color_class` varchar(50) DEFAULT 'warning',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `name_ur` varchar(100) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_ur` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `requests_count` int(11) DEFAULT 0,
  `rating` decimal(3,2) DEFAULT 5.00,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `services`
--

INSERT INTO `services` (`id`, `name_ar`, `name_en`, `name_ur`, `description_ar`, `description_en`, `description_ur`, `price`, `image`, `category_id`, `requests_count`, `rating`, `is_active`, `is_featured`, `created_at`, `sort_order`) VALUES
(1, 'صيانة مكيف سبليت', 'Split AC Maintenance', NULL, 'غسيل وتنظيف شامل للمكيف', '', NULL, 150.00, 'services/698ae77e0eba1_1770710910.png', 3, 1000, 4.80, 1, 1, '2026-02-09 12:27:46', 0),
(2, 'تأسيس مواسير المياه', 'Water pipe installation', 'پانی کے پائپ کی تنصیب', '', '', '', 500.00, 'services/698ae7750ecd9_1770710901.png', 12, 198, 4.90, 1, 1, '2026-02-09 12:27:46', 0),
(3, 'تنظيف شقق', 'Apartment Cleaning', NULL, 'تنظيف شامل للأرضيات والجدران', '', NULL, 300.00, 'services/698ae76abbec0_1770710890.jpg', 4, 212, 4.70, 1, 1, '2026-02-09 12:27:46', 0),
(4, 'فحص كهرباء', 'Electrical Inspection', NULL, 'فحص شامل للوحة الكهرباء والأسلاك', '', NULL, 100.00, 'services/698ae75e11b9c_1770710878.webp', 2, 460, 4.60, 1, 1, '2026-02-09 12:27:46', 0),
(6, 'تأسيس مواسير الصرف', 'Drainage Pipe Installation', 'نکاسی آب کے پائپ کی تنصیب', '', '', '', 300.00, NULL, 12, 0, 5.00, 1, 1, '2026-03-09 20:06:12', 0),
(7, 'تمديد خطوط التغذية', 'Water Supply Line Extension', 'پانی کی سپلائی لائن کی توسیع', '', '', '', 400.00, NULL, 12, 0, 5.00, 1, 1, '2026-03-09 20:07:14', 0);

-- --------------------------------------------------------

--
-- بنية الجدول `service_areas`
--

CREATE TABLE `service_areas` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `country_code` varchar(8) NOT NULL DEFAULT 'SA',
  `city_name` varchar(120) DEFAULT NULL,
  `village_name` varchar(120) DEFAULT NULL,
  `geometry_type` enum('circle','polygon') NOT NULL DEFAULT 'circle',
  `center_lat` decimal(10,8) DEFAULT NULL,
  `center_lng` decimal(11,8) DEFAULT NULL,
  `radius_km` decimal(8,3) DEFAULT NULL,
  `polygon_json` longtext DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `name_en` varchar(150) DEFAULT NULL,
  `name_ur` varchar(150) DEFAULT NULL,
  `city_name_en` varchar(120) DEFAULT NULL,
  `city_name_ur` varchar(120) DEFAULT NULL,
  `village_name_en` varchar(120) DEFAULT NULL,
  `village_name_ur` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `service_areas`
--

INSERT INTO `service_areas` (`id`, `name`, `country_code`, `city_name`, `village_name`, `geometry_type`, `center_lat`, `center_lng`, `radius_km`, `polygon_json`, `notes`, `is_active`, `priority`, `created_by`, `updated_by`, `created_at`, `updated_at`, `name_en`, `name_ur`, `city_name_en`, `city_name_ur`, `village_name_en`, `village_name_ur`) VALUES
(4, 'مصر', 'EG', 'مصر', NULL, 'polygon', 30.76422554, 31.62963867, 5.000, '[{\"lat\":29.39443989,\"lng\":31.13525391},{\"lat\":29.78613118,\"lng\":31.69555664},{\"lat\":29.59525222,\"lng\":32.50854492},{\"lat\":29.90048426,\"lng\":32.73925781},{\"lat\":30.2142784,\"lng\":32.6184082},{\"lat\":31.03675593,\"lng\":32.56347656},{\"lat\":31.40317267,\"lng\":32.33276367},{\"lat\":31.53435979,\"lng\":31.97021484},{\"lat\":31.54372326,\"lng\":31.67358398},{\"lat\":31.65601161,\"lng\":31.08032227},{\"lat\":31.49689652,\"lng\":30.8605957},{\"lat\":31.50626374,\"lng\":30.49804688},{\"lat\":30.86716251,\"lng\":29.50927734}]', NULL, 1, 0, 1, 1, '2026-03-09 01:44:16', '2026-03-09 01:44:16', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'وسط الدمام', 'SA', NULL, NULL, 'circle', 26.41799319, 50.10105434, 10.000, NULL, NULL, 1, 0, 1, 1, '2026-03-16 06:08:26', '2026-03-16 06:08:26', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `service_area_services`
--

CREATE TABLE `service_area_services` (
  `id` int(11) NOT NULL,
  `service_area_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `service_area_services`
--

INSERT INTO `service_area_services` (`id`, `service_area_id`, `service_id`, `created_at`) VALUES
(5, 4, 2, '2026-03-09 22:58:57'),
(6, 4, 6, '2026-03-09 23:06:12'),
(7, 4, 7, '2026-03-09 23:07:14');

-- --------------------------------------------------------

--
-- بنية الجدول `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `name_ur` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `warranty_days` int(11) DEFAULT 14
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `service_categories`
--

INSERT INTO `service_categories` (`id`, `parent_id`, `name_ar`, `name_en`, `name_ur`, `icon`, `color`, `image`, `is_active`, `sort_order`, `created_at`, `warranty_days`) VALUES
(1, NULL, 'سباكة', 'Plumbing', 'پلمبنگ', 'categories/69ae28b1a183d_1773021361.png', 'from-blue-400 to-blue-600', 'categories/69ae28b1a11a1_1773021361.png', 1, 1, '2026-01-27 06:30:37', 11),
(2, NULL, 'كهرباء', 'Electrical', 'Electrical', 'categories/69ae28d5a2a56_1773021397.png', 'from-yellow-400 to-yellow-600', 'categories/69ae28d5a29e7_1773021397.png', 1, 2, '2026-01-27 06:30:37', 14),
(3, NULL, 'تكييف', 'AC', 'AC', 'categories/69ae28eca87db_1773021420.png', 'from-cyan-400 to-cyan-600', 'categories/69ae28eca877f_1773021420.png', 1, 3, '2026-01-27 06:30:37', 14),
(4, NULL, 'تنظيف', 'Cleaning', 'Cleaning', 'categories/69ae290477240_1773021444.png', 'from-green-400 to-green-600', 'categories/69ae2904771cf_1773021444.png', 1, 4, '2026-01-27 06:30:37', 14),
(5, NULL, 'نجارة', 'Carpentry', 'Carpentry', 'categories/69ae29141eace_1773021460.png', 'from-orange-400 to-orange-600', 'categories/69ae29141ea69_1773021460.png', 1, 5, '2026-01-27 06:30:37', 14),
(6, NULL, 'أجهزة منزلية', 'Home Appliances', NULL, 'categories/69787a2ddf4a2_1769503277.png', 'from-purple-400 to-purple-600', NULL, 1, 6, '2026-01-27 06:30:37', 14),
(7, NULL, 'دهان', 'Painting', NULL, 'categories/698ae226ad98c_1770709542.png', 'from-pink-400 to-pink-600', 'categories/698ae226ad3c2_1770709542.avif', 1, 7, '2026-01-27 06:30:37', 14),
(8, NULL, 'تبليط', 'Tiling', NULL, 'categories/698ae2954edff_1770709653.png', 'from-gray-400 to-gray-600', 'categories/698ae2954e823_1770709653.avif', 1, 8, '2026-01-27 06:30:37', 14),
(9, 3, 'تنظيف الفلاتر', 'تنظيف الفلاتر', 'تنظيف الفلاتر', 'categories/69957dcb9b7e6_1771404747.png', NULL, 'categories/69957dcb9ba92_1771404747.png', 1, 0, '2026-02-18 08:52:27', 14),
(11, NULL, 'خدمة أخرى', 'Other Service', NULL, '🔧', NULL, NULL, 1, 999, '2026-03-08 23:03:40', 0),
(12, 1, 'تأسيس السباكة', 'Plumbing Installation', 'پلمبنگ کی تنصیب', NULL, NULL, NULL, 1, 0, '2026-03-09 19:46:54', 14),
(13, 1, 'صيانة السباكة', 'Plumbing Maintenance', 'پلمبنگ کی مرمت', NULL, NULL, NULL, 1, 0, '2026-03-09 19:48:21', 14),
(14, 1, 'تسليك المجاري والصرف', 'Drain Cleaning', 'نالی اور ڈرین کی صفائی', NULL, NULL, NULL, 1, 0, '2026-03-09 19:49:02', 14),
(15, 1, 'تركيب الأدوات الصحية', 'Sanitary Ware Installation', 'سینیٹری اشیاء کی تنصیب', NULL, NULL, NULL, 1, 0, '2026-03-09 19:49:49', 14),
(16, 1, 'كشف التسربات', 'Leak Detection', 'Leak Detection', NULL, NULL, NULL, 1, 0, '2026-03-09 19:50:41', 14),
(17, 1, 'سباكة المطابخ', 'Kitchen Plumbing', 'کچن پلمبنگ', NULL, NULL, NULL, 1, 0, '2026-03-09 19:54:37', 14),
(18, NULL, 'الحاويات', 'Containers', NULL, '📦', NULL, NULL, 1, 9002, '2026-03-16 03:12:19', 0),
(19, NULL, 'نقل العفش', 'Furniture Moving', NULL, '🚚', NULL, NULL, 1, 9001, '2026-03-16 03:40:10', 0);

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `group` varchar(50) DEFAULT 'general',
  `about_us` text DEFAULT NULL,
  `terms_and_conditions` text DEFAULT NULL,
  `refund_policy` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `group`, `about_us`, `terms_and_conditions`, `refund_policy`) VALUES
(1, 'app_name', 'Darfix', 'general', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `spare_parts`
--

CREATE TABLE `spare_parts` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `old_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `store_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price_with_installation` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_without_installation` decimal(10,2) NOT NULL DEFAULT 0.00,
  `old_price_with_installation` decimal(10,2) DEFAULT NULL,
  `old_price_without_installation` decimal(10,2) DEFAULT NULL,
  `warranty_duration` varchar(150) DEFAULT NULL,
  `warranty_terms` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `spare_parts`
--

INSERT INTO `spare_parts` (`id`, `name_ar`, `name_en`, `description_ar`, `description_en`, `price`, `stock_quantity`, `old_price`, `image`, `is_active`, `sort_order`, `created_at`, `store_id`, `category_id`, `price_with_installation`, `price_without_installation`, `old_price_with_installation`, `old_price_without_installation`, `warranty_duration`, `warranty_terms`) VALUES
(1, 'كمبروسر مكيف', 'AC Compressor', 'كمبروسر أصلي ضمان سنة شامل التركيب', '', 1200.00, 0, 1400.00, 'spare_parts/698ae46e26c09_1770710126.webp', 1, 0, '2026-02-09 12:27:46', NULL, 3, 1200.00, 1200.00, 1400.00, 1400.00, NULL, NULL),
(2, 'موتور مياه', 'Water Pump', 'موتور نصف حصان صامت', '', 450.00, 0, 600.00, 'spare_parts/698ae45480bf3_1770710100.jpg', 1, 0, '2026-02-09 12:27:46', NULL, 1, 450.00, 450.00, 600.00, 600.00, NULL, NULL),
(3, 'فلتر مياه', 'Water Filter', 'فلتر 7 مراحل تايواني', '', 750.00, 0, 900.00, 'spare_parts/698ae43f31dc9_1770710079.webp', 1, 0, '2026-02-09 12:27:46', NULL, 1, 750.00, 750.00, 900.00, 900.00, NULL, NULL),
(4, 'سخان فوري', 'Instant Heater', 'سخان مياه فوري موفر للطاقة', '', 350.00, 0, 450.00, 'spare_parts/698ae41b040f8_1770710043.jpg', 1, 0, '2026-02-09 12:27:46', 1, 6, 350.00, 350.00, 450.00, 450.00, '15', 'لا يشمل عطل من الكهرباء');

-- --------------------------------------------------------

--
-- بنية الجدول `spare_part_services`
--

CREATE TABLE `spare_part_services` (
  `id` int(11) NOT NULL,
  `spare_part_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `spare_part_services`
--

INSERT INTO `spare_part_services` (`id`, `spare_part_id`, `service_id`, `created_at`) VALUES
(5, 4, 2, '2026-03-09 07:42:41');

-- --------------------------------------------------------

--
-- بنية الجدول `spare_part_service_areas`
--

CREATE TABLE `spare_part_service_areas` (
  `id` int(11) NOT NULL,
  `spare_part_id` int(11) NOT NULL,
  `service_area_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `spare_part_service_areas`
--

INSERT INTO `spare_part_service_areas` (`id`, `spare_part_id`, `service_area_id`, `created_at`) VALUES
(5, 4, 4, '2026-03-09 07:42:41');

-- --------------------------------------------------------

--
-- بنية الجدول `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `stores`
--

INSERT INTO `stores` (`id`, `name_ar`, `is_active`, `created_at`, `sort_order`, `is_featured`, `description_ar`, `description_en`, `address`, `logo`, `banner`, `name_en`, `phone`, `email`, `rating`, `lat`, `lng`) VALUES
(1, 'لاتلنالبل', 1, '2026-02-18 08:34:52', 0, 0, 'drftghijokhigufydt', NULL, 'rtfghjkhh', 'stores/699579ac4e091_1771403692.jpg', 'stores/699579ac4e1e4_1771403692.jpg', 'لناتبلايبل', '456543456', 'ghgjgjhkghj@fghjk.jhgfds', 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `store_account_entries`
--

CREATE TABLE `store_account_entries` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `entry_type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `source` enum('manual','withdrawal','return','adjustment') NOT NULL DEFAULT 'manual',
  `notes` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `store_spare_part_movements`
--

CREATE TABLE `store_spare_part_movements` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `spare_part_id` int(11) NOT NULL,
  `movement_type` enum('withdrawal','return','adjustment_in','adjustment_out') NOT NULL DEFAULT 'withdrawal',
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `provider_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `type` enum('deposit','withdrawal','payment','refund','commission','reward','referral_bonus') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `provider_id`, `order_id`, `type`, `amount`, `balance_after`, `description`, `reference_number`, `status`, `created_at`) VALUES
(1, 2, NULL, NULL, 'deposit', 100.00, 100.00, 'تعديل من لوحة التحكم', NULL, 'completed', '2026-01-27 08:24:17'),
(2, 2, NULL, NULL, 'reward', 3.00, 7.00, 'إضافة 3 نقطة مكافأة من الإدارة', NULL, 'completed', '2026-02-04 12:19:02'),
(3, 2, NULL, NULL, 'deposit', 0.02, 100.02, 'إيداع رصيد من لوحة التحكم', NULL, 'completed', '2026-02-04 12:19:20'),
(4, 2, NULL, NULL, 'deposit', 4000.00, 4100.02, 'إيداع رصيد من لوحة التحكم', NULL, 'completed', '2026-02-04 12:26:05'),
(18, 37, NULL, NULL, 'reward', -1000.00, 0.00, 'استبدال مكافأة: 50', NULL, 'completed', '2026-03-12 22:46:15');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default-user.png',
  `wallet_balance` decimal(10,2) DEFAULT 0.00,
  `points` int(11) DEFAULT 0,
  `membership_level` varchar(50) DEFAULT 'silver',
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `device_token` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `no_show_count` int(11) DEFAULT 0,
  `is_blacklisted` tinyint(1) DEFAULT 0,
  `blacklist_reason` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deletion_requested_at` datetime DEFAULT NULL,
  `deletion_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `full_name`, `phone`, `otp_code`, `email`, `avatar`, `wallet_balance`, `points`, `membership_level`, `referral_code`, `referred_by`, `is_active`, `is_verified`, `device_token`, `last_login`, `created_at`, `updated_at`, `no_show_count`, `is_blacklisted`, `blacklist_reason`, `city`, `country`, `gender`, `birth_date`, `deleted_at`, `deletion_requested_at`, `deletion_reason`) VALUES
(2, 'احمد سيد', '+201505768014', NULL, 'jjeiek@jekrk.fkjj', 'uploads/avatars/6978746218b93.jpg', 4100.02, 7, 'silver', NULL, NULL, 1, 1, NULL, '2026-02-18 07:20:26', '2026-01-27 07:51:02', '2026-03-02 22:25:30', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, NULL, '+201010576801', '4312', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-02-18 11:10:34', '2026-02-25 10:00:39', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, NULL, '+966500000004', '0553', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-02-18 11:11:35', '2026-02-18 11:19:53', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, NULL, '+966513541656', '8070', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-02-18 11:15:51', '2026-02-18 11:15:51', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, NULL, '0500000004', NULL, NULL, 'default-user.png', 0.00, 0, 'silver', 'ERTLYKT5Q27', NULL, 1, 1, NULL, '2026-02-25 12:00:06', '2026-02-18 11:18:41', '2026-02-25 10:00:06', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, NULL, '01012345678', '9244', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-02-25 14:11:30', '2026-02-25 14:11:30', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, NULL, '966555123456', '6697', NULL, 'default-user.png', 0.00, 0, 'silver', 'ERT9W0KXYEX', NULL, 1, 1, NULL, '2026-02-27 15:51:05', '2026-02-27 13:45:51', '2026-02-27 13:51:44', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, NULL, '966555123457', '7127', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-02-27 13:46:17', '2026-02-27 13:49:51', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, NULL, '0500000001', NULL, NULL, 'default-user.png', 0.00, 0, 'silver', 'ERTHQ5G1H86', NULL, 1, 1, NULL, '2026-03-02 23:07:07', '2026-02-28 18:29:23', '2026-03-02 20:07:08', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'QA User', '0500000099', NULL, NULL, 'default-user.png', 0.00, 0, 'silver', 'ERT2IEQ43F3', NULL, 1, 1, NULL, NULL, '2026-02-28 18:29:55', '2026-02-28 18:29:55', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'اختبار مقدم خدمة API', '+966500000001', NULL, 'provider.test+1@example.com', 'default-user.png', 0.00, 0, 'silver', 'ERT3BZWGCFM', NULL, 1, 1, NULL, NULL, '2026-03-02 19:10:28', '2026-03-02 19:10:28', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'ثفغعفقغثعثقفعغ', '+966500000003', NULL, '', 'default-user.png', 0.00, 0, 'silver', 'ERTERZIS4C2', NULL, 1, 1, NULL, '2026-03-02 22:24:16', '2026-03-02 19:12:01', '2026-03-05 02:22:43', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, NULL, '+966552480876', '9616', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-03-02 19:47:56', '2026-03-02 19:47:56', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, NULL, '+966572480876', '8645', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-03-02 19:47:57', '2026-03-02 19:47:57', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, NULL, '0501000101', NULL, NULL, 'default-user.png', 0.00, 0, 'silver', 'ERT5ODU0ZGE', NULL, 1, 1, NULL, '2026-03-03 00:48:55', '2026-03-02 21:41:42', '2026-03-02 21:48:55', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, NULL, '+966532585255', '7789', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-03-04 13:22:59', '2026-03-04 13:22:59', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, NULL, '+966535855555', '8863', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-03-04 13:31:15', '2026-03-04 13:31:15', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, NULL, '+966525445555', '0560', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-03-04 13:34:36', '2026-03-04 13:34:43', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'نتللعال', '+966525445655', '4894', 'hddhdjfj@jfnr.rkekdk', 'uploads/avatars/69a836866e646.png', 0.00, 0, 'silver', 'ERTAWTLMSZR', NULL, 1, 1, '340056b7-105e-485f-9327-2a4f45822fdd', '2026-03-04 16:35:21', '2026-03-04 13:34:53', '2026-03-04 15:37:50', 0, 0, NULL, 'تبتتبف', NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'اختبار ملفي', '+966555001111', NULL, 'tester.profile@example.com', 'uploads/avatars/69a83b0a60fb8.png', 0.00, 0, 'vip', 'ERTCJ5MLZSD', NULL, 1, 1, NULL, '2026-03-04 17:21:05', '2026-03-04 14:00:18', '2026-03-08 17:17:36', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'oooo', '+966522222222', '6862', NULL, 'default-user.png', 0.00, 0, 'silver', 'ERTMOOOZ7GT', NULL, 1, 1, '864d39e2-59d4-48b2-9035-e132c397d6de', '2026-03-13 01:41:33', '2026-03-12 22:41:09', '2026-03-12 22:46:15', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, NULL, '+966564346458', '2091', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-03-13 03:03:40', '2026-03-13 03:03:40', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, NULL, '+966543454544', '9783', NULL, 'default-user.png', 0.00, 0, 'silver', NULL, NULL, 1, 0, NULL, NULL, '2026-03-13 04:00:21', '2026-03-13 04:00:21', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'حمدي', '+966535036778', NULL, NULL, 'default-user.png', 0.00, 0, 'silver', 'ERTYBLY3MFM', NULL, 1, 1, '7bff811a-e499-4b8a-9f18-b91fbc1fe9c1', '2026-03-17 23:43:29', '2026-03-17 20:43:15', '2026-03-17 20:43:43', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('home','work','other') DEFAULT 'home',
  `label` varchar(50) DEFAULT NULL,
  `address` text NOT NULL,
  `details` text DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `city` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `street` varchar(150) DEFAULT NULL,
  `country_code` varchar(8) DEFAULT NULL,
  `city_name` varchar(120) DEFAULT NULL,
  `village_name` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `type`, `label`, `address`, `details`, `lat`, `lng`, `is_default`, `created_at`, `city`, `district`, `street`, `country_code`, `city_name`, `village_name`) VALUES
(1, 2, 'home', 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', '', 30.22156683, 31.16940681, 0, '2026-02-18 03:00:10', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 'home', 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', '', 30.22157234, 31.16941620, 0, '2026-02-18 04:53:42', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 2, 'home', 'موقع محدد', 'PM7G+C4M، العليا، الرياض 12251، السعودية، PM7G+C4M، العليا، الرياض، منطقة الرياض', '', 30.22151903, 31.16936725, 0, '2026-02-18 05:20:30', NULL, NULL, NULL, NULL, NULL, NULL),
(4, 2, 'home', 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', '', 30.22155467, 31.16939373, 0, '2026-02-18 10:42:45', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 32, 'home', 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', '', 30.22156770, 31.16936859, 0, '2026-03-04 13:35:26', NULL, NULL, NULL, 'EG', 'صنافير', ''),
(6, 32, 'home', 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', '', 30.22156770, 31.16936859, 0, '2026-03-04 13:41:14', NULL, NULL, NULL, 'EG', 'صنافير', ''),
(7, 32, 'home', 'موقع محدد', '65C9+HPR، صنافير، مركز قليوب، محافظة القليوبية 6317010، مصر، 65C9+HPR، صنافير، محافظة القليوبية', '', 30.22156423, 31.16936289, 0, '2026-03-04 13:49:07', NULL, NULL, NULL, 'EG', 'صنافير', ''),
(20, 37, 'home', 'موقع محدد', 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', '', 30.56559767, 32.23357223, 0, '2026-03-12 22:41:36', NULL, NULL, NULL, 'EG', '', ''),
(24, 41, 'home', 'موقع محدد', 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', '', 30.56556534, 32.23353166, 0, '2026-03-17 20:43:38', NULL, NULL, NULL, 'EG', '', ''),
(25, 41, 'home', 'موقع محدد', 'H68M+68P، الإسماعيلية السويس الصحراوي، Ismailia، H68M+68P، Ismailia Governorate', '', 30.56560200, 32.23340191, 0, '2026-04-02 17:36:35', NULL, NULL, NULL, 'EG', '', '');

-- --------------------------------------------------------

--
-- بنية الجدول `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- فهارس للجدول `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- فهارس للجدول `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- فهارس للجدول `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- فهارس للجدول `app_content_pages`
--
ALTER TABLE `app_content_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_page_lang` (`page_key`,`language_code`),
  ADD KEY `idx_page_key` (`page_key`),
  ADD KEY `idx_language_code` (`language_code`);

--
-- فهارس للجدول `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- فهارس للجدول `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- فهارس للجدول `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- فهارس للجدول `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`);

--
-- فهارس للجدول `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- فهارس للجدول `complaint_replies`
--
ALTER TABLE `complaint_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `idx_complaint_replies_complaint` (`complaint_id`),
  ADD KEY `idx_complaint_replies_user` (`user_id`),
  ADD KEY `idx_complaint_replies_admin` (`admin_id`);

--
-- فهارس للجدول `container_requests`
--
ALTER TABLE `container_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `idx_container_requests_status` (`status`),
  ADD KEY `idx_container_requests_service` (`container_service_id`),
  ADD KEY `idx_container_requests_user` (`user_id`),
  ADD KEY `idx_container_requests_start_date` (`start_date`),
  ADD KEY `idx_container_requests_created` (`created_at`),
  ADD KEY `idx_container_requests_source_order` (`source_order_id`);

--
-- فهارس للجدول `container_services`
--
ALTER TABLE `container_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_container_services_active_sort` (`is_active`,`sort_order`);

--
-- فهارس للجدول `container_stores`
--
ALTER TABLE `container_stores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_container_stores_active_sort` (`is_active`,`sort_order`);

--
-- فهارس للجدول `container_store_account_entries`
--
ALTER TABLE `container_store_account_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_container_store_account_store` (`store_id`),
  ADD KEY `idx_container_store_account_created` (`created_at`);

--
-- فهارس للجدول `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`);

--
-- فهارس للجدول `furniture_areas`
--
ALTER TABLE `furniture_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_furniture_areas_active_sort` (`is_active`,`sort_order`);

--
-- فهارس للجدول `furniture_requests`
--
ALTER TABLE `furniture_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `idx_furniture_requests_status` (`status`),
  ADD KEY `idx_furniture_requests_service` (`service_id`),
  ADD KEY `idx_furniture_requests_area` (`area_id`),
  ADD KEY `idx_furniture_requests_user` (`user_id`),
  ADD KEY `idx_furniture_requests_move_date` (`move_date`),
  ADD KEY `idx_furniture_requests_created` (`created_at`),
  ADD KEY `idx_furniture_requests_source_order` (`source_order_id`);

--
-- فهارس للجدول `furniture_request_fields`
--
ALTER TABLE `furniture_request_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_furniture_request_field_key` (`field_key`),
  ADD KEY `idx_furniture_request_fields_active_sort` (`is_active`,`sort_order`);

--
-- فهارس للجدول `furniture_services`
--
ALTER TABLE `furniture_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_furniture_services_active_sort` (`is_active`,`sort_order`);

--
-- فهارس للجدول `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `idx_notifications_provider` (`provider_id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- فهارس للجدول `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_logs_event` (`event_type`),
  ADD KEY `idx_notif_logs_status` (`status`),
  ADD KEY `idx_notif_logs_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_notif_logs_created` (`created_at`);

--
-- فهارس للجدول `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_recipients_active` (`is_active`);

--
-- فهارس للجدول `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_offers_link_type` (`link_type`);

--
-- فهارس للجدول `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `category_id` (`category_id`);

--
-- فهارس للجدول `order_live_locations`
--
ALTER TABLE `order_live_locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_oll_order` (`order_id`),
  ADD KEY `idx_oll_provider` (`provider_id`),
  ADD KEY `idx_oll_created_at` (`created_at`);

--
-- فهارس للجدول `order_providers`
--
ALTER TABLE `order_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_provider` (`order_id`,`provider_id`),
  ADD KEY `idx_order_providers_order` (`order_id`),
  ADD KEY `idx_order_providers_provider` (`provider_id`);

--
-- فهارس للجدول `order_services`
--
ALTER TABLE `order_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_services_order` (`order_id`),
  ADD KEY `idx_order_services_service` (`service_id`);

--
-- فهارس للجدول `order_spare_parts`
--
ALTER TABLE `order_spare_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_osp_order` (`order_id`),
  ADD KEY `idx_osp_spare_part` (`spare_part_id`),
  ADD KEY `idx_osp_store` (`store_id`),
  ADD KEY `idx_osp_committed` (`is_committed`);

--
-- فهارس للجدول `problem_detail_options`
--
ALTER TABLE `problem_detail_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_problem_detail_category` (`category_id`),
  ADD KEY `idx_problem_detail_service` (`service_id`),
  ADD KEY `idx_problem_detail_active` (`is_active`);

--
-- فهارس للجدول `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `category_id` (`category_id`);

--
-- فهارس للجدول `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`);

--
-- فهارس للجدول `promo_codes`
--
ALTER TABLE `promo_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- فهارس للجدول `promo_code_usages`
--
ALTER TABLE `promo_code_usages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_promo_user_order` (`promo_code_id`,`user_id`,`order_id`),
  ADD KEY `idx_promo_usage_user` (`user_id`),
  ADD KEY `idx_promo_usage_promo` (`promo_code_id`),
  ADD KEY `idx_promo_usage_order` (`order_id`);

--
-- فهارس للجدول `providers`
--
ALTER TABLE `providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- فهارس للجدول `provider_services`
--
ALTER TABLE `provider_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_provider_category` (`provider_id`,`category_id`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- فهارس للجدول `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- فهارس للجدول `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`id`);

--
-- فهارس للجدول `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- فهارس للجدول `service_areas`
--
ALTER TABLE `service_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_areas_country` (`country_code`),
  ADD KEY `idx_service_areas_active` (`is_active`),
  ADD KEY `idx_service_areas_priority` (`priority`);

--
-- فهارس للجدول `service_area_services`
--
ALTER TABLE `service_area_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_service_area_service` (`service_area_id`,`service_id`),
  ADD KEY `idx_sas_service_area` (`service_area_id`),
  ADD KEY `idx_sas_service` (`service_id`);

--
-- فهارس للجدول `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_service_categories_parent_id` (`parent_id`);

--
-- فهارس للجدول `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- فهارس للجدول `spare_parts`
--
ALTER TABLE `spare_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spare_parts_store_id` (`store_id`),
  ADD KEY `idx_spare_parts_category_id` (`category_id`);

--
-- فهارس للجدول `spare_part_services`
--
ALTER TABLE `spare_part_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_spare_part_service` (`spare_part_id`,`service_id`),
  ADD KEY `idx_sps_spare_part` (`spare_part_id`),
  ADD KEY `idx_sps_service` (`service_id`);

--
-- فهارس للجدول `spare_part_service_areas`
--
ALTER TABLE `spare_part_service_areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_spare_part_service_area` (`spare_part_id`,`service_area_id`),
  ADD KEY `idx_spsa_spare_part` (`spare_part_id`),
  ADD KEY `idx_spsa_service_area` (`service_area_id`);

--
-- فهارس للجدول `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`);

--
-- فهارس للجدول `store_account_entries`
--
ALTER TABLE `store_account_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_account_store` (`store_id`),
  ADD KEY `idx_store_account_created` (`created_at`);

--
-- فهارس للجدول `store_spare_part_movements`
--
ALTER TABLE `store_spare_part_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_movements_store` (`store_id`),
  ADD KEY `idx_store_movements_part` (`spare_part_id`),
  ADD KEY `idx_store_movements_created` (`created_at`);

--
-- فهارس للجدول `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_transactions_created` (`created_at`),
  ADD KEY `idx_transactions_user` (`user_id`),
  ADD KEY `idx_transactions_provider` (`provider_id`),
  ADD KEY `idx_transactions_type_created` (`type`,`created_at`);

--
-- فهارس للجدول `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `referred_by` (`referred_by`);

--
-- فهارس للجدول `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- فهارس للجدول `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `app_content_pages`
--
ALTER TABLE `app_content_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `complaint_replies`
--
ALTER TABLE `complaint_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `container_requests`
--
ALTER TABLE `container_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `container_services`
--
ALTER TABLE `container_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `container_stores`
--
ALTER TABLE `container_stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `container_store_account_entries`
--
ALTER TABLE `container_store_account_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `furniture_areas`
--
ALTER TABLE `furniture_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `furniture_requests`
--
ALTER TABLE `furniture_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `furniture_request_fields`
--
ALTER TABLE `furniture_request_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `furniture_services`
--
ALTER TABLE `furniture_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `order_live_locations`
--
ALTER TABLE `order_live_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_providers`
--
ALTER TABLE `order_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `order_services`
--
ALTER TABLE `order_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `order_spare_parts`
--
ALTER TABLE `order_spare_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `problem_detail_options`
--
ALTER TABLE `problem_detail_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `promo_codes`
--
ALTER TABLE `promo_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `promo_code_usages`
--
ALTER TABLE `promo_code_usages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `providers`
--
ALTER TABLE `providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `provider_services`
--
ALTER TABLE `provider_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rewards`
--
ALTER TABLE `rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `service_areas`
--
ALTER TABLE `service_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service_area_services`
--
ALTER TABLE `service_area_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `spare_parts`
--
ALTER TABLE `spare_parts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `spare_part_services`
--
ALTER TABLE `spare_part_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `spare_part_service_areas`
--
ALTER TABLE `spare_part_service_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `store_account_entries`
--
ALTER TABLE `store_account_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_spare_part_movements`
--
ALTER TABLE `store_spare_part_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- القيود المفروضة على الجداول الملقاة
--

--
-- قيود الجداول `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `complaints_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `complaint_replies`
--
ALTER TABLE `complaint_replies`
  ADD CONSTRAINT `complaint_replies_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `offers`
--
ALTER TABLE `offers`
  ADD CONSTRAINT `offers_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`);

--
-- قيود الجداول `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`);

--
-- قيود الجداول `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 08-04-2026 a las 20:57:38
-- Versión del servidor: 11.4.10-MariaDB-cll-lve
-- Versión de PHP: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `xygfyvca_jaf`
--

DELIMITER $$
--
-- Funciones
--
CREATE FUNCTION `AdjustPaymentDay` (`target_year` INT, `target_month` INT, `payment_day` INT) RETURNS DATE DETERMINISTIC BEGIN
    DECLARE last_day INT;
    DECLARE result_date DATE;
    
    SET last_day = DAY(LAST_DAY(CONCAT(target_year, '-', LPAD(target_month, 2, '0'), '-01')));
    
    IF payment_day > last_day THEN
        SET result_date = CONCAT(target_year, '-', LPAD(target_month, 2, '0'), '-', last_day);
    ELSE
        SET result_date = CONCAT(target_year, '-', LPAD(target_month, 2, '0'), '-', LPAD(payment_day, 2, '0'));
    END IF;
    
    RETURN result_date;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(53, 3, 'update', 'sale_transaction', 53, NULL, NULL, 'Actualizó transacción REN-2025-0001', '179.53.75.130', NULL, '2025-10-28 01:36:12'),
(54, 1, 'update', 'sale_transaction', 53, NULL, NULL, 'Actualizó transacción REN-2025-0001', '148.101.6.16', NULL, '2025-10-28 01:36:22'),
(55, 1, 'update', 'sale_transaction', 54, NULL, NULL, 'Actualizó transacción SAL-2025-0001', '148.101.6.16', NULL, '2025-10-28 01:59:17'),
(56, 1, 'created', 'client', 23, NULL, '{\"reference\":\"CLI-EAAC7477\",\"first_name\":\"Mercedes\",\"last_name\":\"Reyes\",\"document_id\":\"001-1234567-8\",\"document_type\":\"cedula\",\"email\":\"mercedes@localhost.com\",\"phone_mobile\":\"809-555-7788\",\"phone_home\":null,\"address\":null,\"city\":null,\"state_province\":null,\"country\":\"Rep\\u00fablica Dominicana\",\"postal_code\":null,\"date_of_birth\":\"1954-10-26\",\"client_type\":\"tenant\",\"status\":\"qualified\",\"source\":\"social_media\",\"budget_min\":2000,\"budget_max\":10000,\"property_type_interest\":\"[\\\"3\\\",\\\"2\\\",\\\"9\\\",\\\"4\\\"]\",\"locations_interest\":\"[\\\"Miami\\\",\\\"Santo Domingo\\\"]\",\"bedrooms_desired\":null,\"bathrooms_desired\":null,\"must_have_features\":null,\"estimated_decision_date\":null,\"priority\":\"high\",\"probability\":50,\"agent_id\":1,\"notes\":null,\"tags\":null,\"is_active\":1,\"created_at\":\"2025-10-27 23:55:22\",\"updated_at\":\"2025-10-27 23:55:22\",\"last_contact_date\":\"2025-10-27 23:55:22\"}', NULL, '148.101.6.16', NULL, '2025-10-28 03:55:22'),
(57, 1, 'update', 'sale_transaction', 55, NULL, NULL, 'Actualizó transacción REN-2025-0002', '148.101.6.16', NULL, '2025-10-28 04:03:13'),
(58, 1, 'update', 'property', 42, NULL, NULL, 'Property {reference} updated', '148.101.6.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:14:17'),
(59, 1, 'update', 'property', 23, NULL, NULL, 'Property {reference} updated', '148.101.6.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:14:38'),
(60, 1, 'update', 'property', 18, NULL, NULL, 'Property {reference} updated', '148.101.6.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:14:51'),
(61, 1, 'update', 'property', 13, NULL, NULL, 'Property {reference} updated', '148.101.6.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:15:05'),
(62, 1, 'update', 'property', 16, NULL, NULL, 'Property {reference} updated', '148.101.6.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:15:17'),
(63, 1, 'update', 'property', 3, NULL, NULL, 'Property {reference} updated', '148.101.6.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:15:33'),
(64, 1, 'created', 'document', 22, NULL, NULL, 'Documento subido: contrato-venta', '148.101.6.16', NULL, '2025-10-28 04:16:55'),
(65, 1, 'update', 'sale_transaction', 55, NULL, NULL, 'Actualizó transacción REN-2025-0002', '148.101.6.16', NULL, '2025-10-28 17:18:04'),
(66, 1, 'create', 'calendar_event', 11, NULL, NULL, 'Evento creado: Llamada para coordinar venta', '148.101.6.16', NULL, '2025-10-28 17:34:28'),
(67, 1, 'create', 'calendar_event', 12, NULL, NULL, 'Evento creado: Prueba', '148.101.6.16', NULL, '2025-10-28 17:36:23'),
(68, 1, 'update', 'sale_transaction', 56, NULL, NULL, 'Actualizó transacción REN-2025-0003', '179.52.184.182', NULL, '2025-10-30 04:02:59'),
(69, 3, 'update', 'property', 55, NULL, NULL, 'Propiedad {reference} actualizada', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 05:02:11'),
(70, 3, 'update', 'property', 55, NULL, NULL, 'Propiedad {reference} actualizada', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-18 14:40:06'),
(71, 3, 'update', 'property', 55, NULL, NULL, 'Propiedad {reference} actualizada', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:40:14'),
(72, 3, 'update', 'property', 55, NULL, NULL, 'Propiedad {reference} actualizada', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:40:36'),
(73, 3, 'update', 'property', 55, NULL, NULL, 'Propiedad {reference} actualizada', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:44:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` text NOT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `author_id` int(10) UNSIGNED NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `views_count` int(10) UNSIGNED DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('visit','meeting','call','signing','deadline','other') DEFAULT 'meeting',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `all_day` tinyint(1) DEFAULT 0,
  `location` varchar(500) DEFAULT NULL,
  `attendees` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attendees`)),
  `related_client_id` int(10) UNSIGNED DEFAULT NULL,
  `related_property_id` int(10) UNSIGNED DEFAULT NULL,
  `color` varchar(20) DEFAULT '#3B82F6',
  `reminder_minutes` int(10) UNSIGNED DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `status` enum('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',
  `created_by` int(10) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `calendar_events`
--

INSERT INTO `calendar_events` (`id`, `title`, `description`, `event_type`, `start_datetime`, `end_datetime`, `all_day`, `location`, `attendees`, `related_client_id`, `related_property_id`, `color`, `reminder_minutes`, `reminder_sent`, `status`, `created_by`, `notes`, `created_at`, `updated_at`) VALUES
(11, 'Llamada para coordinar venta', '', 'call', '2025-10-28 16:00:00', '2025-10-28 21:00:00', 0, 'Calle 1era #123', '[\"1\"]', 2, 52, '#3b82f6', 15, 0, 'scheduled', 1, NULL, '2025-10-28 17:34:28', '2025-10-28 17:34:28'),
(12, 'Prueba', '', 'call', '2025-10-28 16:00:00', '2025-10-28 17:35:00', 0, '', '[\"1\"]', 2, 52, '#667eea', NULL, 0, 'scheduled', 1, NULL, '2025-10-28 17:36:23', '2025-10-28 17:36:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cities`
--

CREATE TABLE `cities` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'República Dominicana',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cities`
--

INSERT INTO `cities` (`id`, `name`, `state_province`, `country`, `latitude`, `longitude`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Santo Domingo', 'Nacional', 'República Dominicana', NULL, NULL, 1, '2026-01-18 02:51:37', '2026-01-18 02:51:37'),
(2, 'Santiago', 'Santiago', 'República Dominicana', NULL, NULL, 1, '2026-01-18 02:51:37', '2026-01-18 02:51:37'),
(3, 'La Romana', 'La Romana', 'República Dominicana', NULL, NULL, 1, '2026-01-18 02:51:37', '2026-01-18 02:51:37'),
(4, 'Punta Cana', 'La Altagracia', 'República Dominicana', NULL, NULL, 1, '2026-01-18 02:51:37', '2026-01-18 02:51:37'),
(5, 'Puerto Plata', 'Puerto Plata', 'República Dominicana', NULL, NULL, 1, '2026-01-18 02:51:37', '2026-01-18 02:51:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clients`
--

CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `document_id` varchar(100) DEFAULT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL COMMENT 'Contraseña hash para portal',
  `portal_active` tinyint(1) DEFAULT 0 COMMENT '1=Portal activo, 0=Desactivado',
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `payment_day` int(2) DEFAULT NULL COMMENT 'Día del mes para pago (1-31)',
  `phone_mobile` varchar(50) DEFAULT NULL,
  `phone_home` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `client_type` enum('buyer','seller','tenant','landlord','investor','other') NOT NULL,
  `status` enum('lead','contacted','qualified','proposal_sent','negotiation','closed','lost') DEFAULT 'lead',
  `source` enum('website','referral','call','portal','social_media','walk_in','other') DEFAULT 'website',
  `budget_min` decimal(15,2) DEFAULT NULL,
  `budget_max` decimal(15,2) DEFAULT NULL,
  `property_type_interest` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`property_type_interest`)),
  `locations_interest` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`locations_interest`)),
  `bedrooms_desired` int(10) UNSIGNED DEFAULT NULL,
  `bathrooms_desired` int(10) UNSIGNED DEFAULT NULL,
  `must_have_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`must_have_features`)),
  `estimated_decision_date` date DEFAULT NULL,
  `priority` enum('high','medium','low') DEFAULT 'medium',
  `probability` int(10) UNSIGNED DEFAULT 0,
  `agent_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_contact_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clients`
--

INSERT INTO `clients` (`id`, `reference`, `first_name`, `last_name`, `document_id`, `document_type`, `email`, `password`, `portal_active`, `password_reset_token`, `password_reset_expires`, `last_login`, `payment_day`, `phone_mobile`, `phone_home`, `address`, `city`, `state_province`, `country`, `postal_code`, `date_of_birth`, `client_type`, `status`, `source`, `budget_min`, `budget_max`, `property_type_interest`, `locations_interest`, `bedrooms_desired`, `bathrooms_desired`, `must_have_features`, `estimated_decision_date`, `priority`, `probability`, `agent_id`, `notes`, `tags`, `is_active`, `created_at`, `updated_at`, `last_contact_date`) VALUES
(1, 'CLI-00001', 'Jenniffer', 'Rodríguez', '001-0234567-8', 'cedula', 'jenniffer@rodriguez.com', '$2y$10$MtBzI1F931yaEFKAZddVMui.KML5Nw2VBGgXrBj9bZ0bbl85bdoJa', 1, NULL, NULL, '2025-10-28 02:19:55', 25, '809-555-1001', '809-221-3344', 'Calle Principal #45, Los Cacicazgos', 'Santo Domingo', 'Distrito Nacional', 'República Dominicana', '10147', '1978-03-15', 'buyer', 'qualified', 'website', 8000000.00, 12000000.00, '[\"Apartamento\",\"Penthouse\"]', '[\"Piantini\",\"Naco\",\"Bella Vista\"]', 3, 3, '[\"parking\",\"elevator\",\"pool\",\"gym\",\"security\"]', '2025-12-30', 'high', 85, 1, 'Cliente VIP interesado en propiedades de lujo en zonas premium. Busca mudarse en 6 meses.', '[\"vip\",\"alta-capacidad\",\"urgente\"]', 1, '2025-09-15 18:30:00', '2025-10-28 02:19:55', '2025-10-05 22:22:00'),
(2, 'CLI-00002', 'Camil', 'Peralta', '001-0234567-9', 'cedula', 'camil@peralta.com', '$2y$10$MtBzI1F931yaEFKAZddVMui.KML5Nw2VBGgXrBj9bZ0bbl85bdoJa', 1, NULL, NULL, '2025-10-25 04:24:40', 25, '809-555-1001', '809-221-3344', 'Calle Principal #45, Los Cacicazgos', 'Santo Domingo', 'Distrito Nacional', 'República Dominicana', '10147', '1978-03-15', 'buyer', 'qualified', 'website', 8000000.00, 12000000.00, NULL, NULL, NULL, NULL, '\"[\\\"parking\\\",\\\"elevator\\\",\\\"pool\\\",\\\"gym\\\",\\\"security\\\"]\"', NULL, 'high', 50, 1, NULL, NULL, 1, '2025-09-15 18:30:00', '2025-10-26 20:27:06', '2025-10-05 22:22:00'),
(23, 'CLI-EAAC7477', 'Mercedes', 'Reyes', '001-1234567-8', 'cedula', 'mercedes@localhost.com', NULL, 0, NULL, NULL, NULL, NULL, '809-555-7788', NULL, NULL, NULL, NULL, 'República Dominicana', NULL, '1954-10-26', 'tenant', 'qualified', 'social_media', 2000.00, 10000.00, '[\"3\",\"2\",\"9\",\"4\"]', '[\"Miami\",\"Santo Domingo\"]', NULL, NULL, NULL, NULL, 'high', 50, 1, NULL, NULL, 1, '2025-10-28 03:55:22', '2025-10-28 03:55:22', '2025-10-28 03:55:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client_favorite_properties`
--

CREATE TABLE `client_favorite_properties` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client_interactions`
--

CREATE TABLE `client_interactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `interaction_type` enum('call','email','meeting','visit','offer','document_shared','whatsapp','other') NOT NULL,
  `interaction_date` datetime NOT NULL,
  `duration` int(10) UNSIGNED DEFAULT NULL COMMENT 'Duración en minutos',
  `subject` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `outcome` varchar(255) DEFAULT NULL,
  `next_action` varchar(255) DEFAULT NULL,
  `next_action_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `client_interactions`
--

INSERT INTO `client_interactions` (`id`, `client_id`, `user_id`, `interaction_type`, `interaction_date`, `duration`, `subject`, `notes`, `property_id`, `outcome`, `next_action`, `next_action_date`, `created_at`, `updated_at`) VALUES
(2, 23, 1, '', '2025-10-27 23:55:22', NULL, NULL, 'Cliente registrado en el sistema', NULL, NULL, NULL, NULL, '2025-10-28 03:55:22', '2025-10-28 03:55:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client_property_comments`
--

CREATE TABLE `client_property_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID del admin/vendedor que responde',
  `sender_type` enum('client','admin') NOT NULL COMMENT 'Quién envió el mensaje',
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Para respuestas anidadas',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `client_property_documents`
--

CREATE TABLE `client_property_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Relación con venta/alquiler',
  `document_name` varchar(255) NOT NULL,
  `document_description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Tamaño en bytes',
  `file_type` varchar(50) DEFAULT NULL COMMENT 'pdf, jpg, png, etc',
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by_client` tinyint(1) DEFAULT 1 COMMENT '1=Cliente, 0=Admin',
  `uploaded_by_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Si lo subió un admin',
  `is_visible_to_client` tinyint(1) DEFAULT 1,
  `upload_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contractors`
--

CREATE TABLE `contractors` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL COMMENT 'RNC/Cédula',
  `contractor_type` varchar(100) DEFAULT NULL COMMENT 'Maestro de Obra, Electricista, etc.',
  `specialties` text DEFAULT NULL COMMENT 'JSON o CSV',
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0 COMMENT '0.0-5.0',
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `availability` enum('Disponible','Ocupado','No Disponible') DEFAULT 'Disponible',
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contractor_documents`
--

CREATE TABLE `contractor_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `contractor_id` int(10) UNSIGNED NOT NULL,
  `document_type` varchar(100) DEFAULT NULL COMMENT 'Licencia, Seguro, Certificación',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contractor_evaluations`
--

CREATE TABLE `contractor_evaluations` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `contractor_id` int(10) UNSIGNED NOT NULL,
  `quality_rating` int(11) DEFAULT NULL COMMENT '1-5',
  `punctuality_rating` int(11) DEFAULT NULL COMMENT '1-5',
  `communication_rating` int(11) DEFAULT NULL COMMENT '1-5',
  `would_hire_again` tinyint(1) DEFAULT 1,
  `comments` text DEFAULT NULL,
  `evaluated_by` int(10) UNSIGNED DEFAULT NULL,
  `evaluated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documents`
--

CREATE TABLE `documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `folder_id` int(10) UNSIGNED DEFAULT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_number` varchar(100) DEFAULT NULL COMMENT 'Número de contrato o referencia',
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `is_starred` tinyint(1) DEFAULT 0,
  `is_shared` tinyint(1) DEFAULT 0,
  `color_tag` varchar(20) DEFAULT NULL,
  `last_accessed_at` timestamp NULL DEFAULT NULL,
  `access_count` int(11) DEFAULT 0,
  `file_size` bigint(20) DEFAULT NULL COMMENT 'Tamaño en bytes',
  `file_type` varchar(100) DEFAULT NULL,
  `mime_type` varchar(150) DEFAULT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `city_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `version` int(10) UNSIGNED DEFAULT 1,
  `parent_document_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Para control de versiones',
  `is_template` tinyint(1) DEFAULT 0,
  `is_signed` tinyint(1) DEFAULT 0,
  `signed_date` datetime DEFAULT NULL,
  `signed_by` varchar(255) DEFAULT NULL,
  `signature_file` varchar(500) DEFAULT NULL,
  `related_entity_type` enum('client','property','user','lead','general') DEFAULT 'general',
  `related_entity_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('draft','active','archived','expired','cancelled') DEFAULT 'active',
  `visibility` enum('public','private','restricted') DEFAULT 'private',
  `views_count` int(11) DEFAULT 0,
  `downloads_count` int(11) DEFAULT 0,
  `expiration_date` date DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documents`
--

INSERT INTO `documents` (`id`, `city`, `property_id`, `folder_id`, `document_name`, `document_number`, `file_name`, `file_path`, `thumbnail_url`, `is_starred`, `is_shared`, `color_tag`, `last_accessed_at`, `access_count`, `file_size`, `file_type`, `mime_type`, `thumbnail_path`, `category_id`, `city_id`, `description`, `version`, `parent_document_id`, `is_template`, `is_signed`, `signed_date`, `signed_by`, `signature_file`, `related_entity_type`, `related_entity_id`, `status`, `visibility`, `views_count`, `downloads_count`, `expiration_date`, `tags`, `metadata`, `uploaded_by`, `created_at`, `updated_at`) VALUES
(23, 'Miami', 4, 8, 'FAIRCRETE RMW CRYSTALLINE', NULL, 'FAIRCRETE RMW CRYSTALLINE.pdf', 'uploads/documents/Miami/#MIA001A/696a79dd42f5c_1768585693.pdf', NULL, 0, 0, NULL, NULL, 0, 337060, 'pdf', 'application/pdf', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 3, 1, NULL, NULL, NULL, 3, '2026-01-16 18:48:13', '2026-01-17 00:22:36'),
(24, 'Miami', 5, 2, 'FAIRCRETE RMW CRYSTALLINE', NULL, 'FAIRCRETE RMW CRYSTALLINE.pdf', 'uploads/documents/Miami/#MIA002A/696a7a6ea8150_1768585838.pdf', NULL, 0, 0, NULL, NULL, 0, 337060, 'pdf', 'application/pdf', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 2, 0, NULL, NULL, NULL, 3, '2026-01-16 18:50:38', '2026-01-19 13:45:57'),
(25, 'Santo Domingo', 1, 9, 'FAIRCRETE RMW CRYSTALLINE', NULL, 'FAIRCRETE RMW CRYSTALLINE.pdf', 'uploads/documents/Santo Domingo/#68DF53EA73AA8/696a7ac785f70_1768585927.pdf', NULL, 0, 0, NULL, NULL, 0, 337060, 'pdf', 'application/pdf', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 0, 0, NULL, NULL, NULL, 3, '2026-01-16 18:52:07', '2026-01-16 18:52:07'),
(26, 'Santo Domingo', 2, 4, 'contrato-venta', NULL, 'contrato-venta.png', 'uploads/documents/Santo Domingo/#68DF566631A8F/696b0767b2eb1_1768621927.png', NULL, 0, 0, NULL, NULL, 0, 2053982, 'png', 'image/png', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 1, 0, NULL, NULL, NULL, 3, '2026-01-17 04:52:07', '2026-01-17 03:52:22'),
(27, 'Santo Domingo', 2, 7, 'contrato-venta', NULL, 'contrato-venta.png', 'uploads/documents/Santo Domingo/#68DF566631A8F/696b079e1f766_1768621982.png', NULL, 0, 0, NULL, NULL, 0, 2053982, 'png', 'image/png', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 0, 0, NULL, NULL, NULL, 3, '2026-01-17 04:53:02', '2026-01-17 04:53:02'),
(28, 'Miami', 55, 3, 'contrato-venta', NULL, 'contrato-venta.png', 'uploads/documents/Miami/#MIA052A/696b0a0e3c93e_1768622606.png', NULL, 0, 0, NULL, NULL, 0, 2053982, 'png', 'image/png', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, '', 'private', 0, 0, NULL, NULL, NULL, 3, '2026-01-17 05:03:26', '2026-01-17 05:03:43'),
(29, 'Miami', 5, 1, 'Contrato', NULL, 'Contrato.png', 'uploads/documents/Miami/#MIA002A/696ce66b8421d_1768744555.png', NULL, 0, 0, NULL, NULL, 0, 305994, 'png', 'image/png', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 2, 0, NULL, NULL, NULL, 3, '2026-01-18 14:55:55', '2026-01-19 13:45:54'),
(30, 'Miami', 55, 7, 'diehard3-horizontal', NULL, 'diehard3-horizontal.jpg', 'uploads/documents/Miami/#MIA052A/696e34ab59e61_1768830123.jpg', NULL, 0, 0, NULL, NULL, 0, 77447, 'jpg', 'image/jpeg', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 0, 0, NULL, NULL, NULL, 3, '2026-01-19 14:42:03', '2026-01-19 14:42:03'),
(31, 'Miami', 55, 8, 'diehard3', NULL, 'diehard3.jpg', 'uploads/documents/Miami/#MIA052A/696e34ccb937f_1768830156.jpg', NULL, 0, 0, NULL, NULL, 0, 261355, 'jpg', 'image/jpeg', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 1, 1, NULL, NULL, NULL, 3, '2026-01-19 14:42:36', '2026-01-19 13:43:10'),
(32, 'Miami', 55, 9, 'Logo-Cultura-Racing-RD', NULL, 'Logo-Cultura-Racing-RD.png', 'uploads/documents/Miami/#MIA052A/696e356f1f0cf_1768830319.png', NULL, 0, 0, NULL, NULL, 0, 111744, 'png', 'image/png', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 1, 0, NULL, NULL, NULL, 3, '2026-01-19 14:45:19', '2026-01-19 13:45:30'),
(33, 'Miami', 23, 3, 'blanco', NULL, 'blanco.jpg', 'uploads/documents/Miami/#MIA020L/696e35b133bcc_1768830385.jpg', NULL, 0, 0, NULL, NULL, 0, 1014799, 'jpg', 'image/jpeg', NULL, NULL, NULL, '', 1, NULL, 0, 0, NULL, NULL, NULL, 'general', NULL, 'active', 'private', 1, 0, NULL, NULL, NULL, 3, '2026-01-19 14:46:25', '2026-01-19 13:47:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_activity`
--

CREATE TABLE `document_activity` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` enum('uploaded','viewed','downloaded','edited','deleted','shared','signed','commented','starred','accessed') NOT NULL,
  `action_details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_categories`
--

CREATE TABLE `document_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-file',
  `color` varchar(20) DEFAULT '#667eea',
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `display_order` int(10) UNSIGNED DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `document_categories`
--

INSERT INTO `document_categories` (`id`, `name`, `slug`, `description`, `icon`, `color`, `parent_id`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Contratos de Compraventa', 'contratos-compraventa', 'Contratos de compra y venta de propiedades', 'description', '#10b981', NULL, 1, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38'),
(2, 'Contratos de Arrendamiento', 'contratos-arrendamiento', 'Contratos de alquiler y arrendamiento', 'edit_document', '#3b82f6', NULL, 2, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38'),
(3, 'Documentos de Propiedades', 'documentos-propiedades', 'Escrituras, planos, certificados de propiedades', 'home', '#f59e0b', NULL, 3, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38'),
(4, 'Documentos de Clientes', 'documentos-clientes', 'Identificaciones, comprobantes de ingresos, referencias', 'badge', '#8b5cf6', NULL, 4, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38'),
(5, 'Documentos Legales', 'documentos-legales', 'Poderes, autorizaciones, documentos notariales', 'gavel', '#ef4444', NULL, 5, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38'),
(6, 'Documentos Financieros', 'documentos-financieros', 'Facturas, recibos, estados de cuenta', 'attach_money', '#06b6d4', NULL, 6, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38'),
(7, 'Plantillas', 'plantillas', 'Plantillas de documentos reutilizables', 'content_copy', '#ec4899', NULL, 7, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38'),
(8, 'Otros', 'otros', 'Documentos varios', 'folder', '#6b7280', NULL, 8, 1, '2025-10-09 16:38:14', '2026-01-18 02:51:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_comments`
--

CREATE TABLE `document_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `parent_comment_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_folders`
--

CREATE TABLE `document_folders` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-folder',
  `color` varchar(20) DEFAULT '#3b82f6',
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `document_folders`
--

INSERT INTO `document_folders` (`id`, `name`, `icon`, `color`, `description`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Escrituras y Títulos', 'fa-file-contract', '#10b981', NULL, 1, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(2, 'Planos y Diseños', 'fa-drafting-compass', '#3b82f6', NULL, 2, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(3, 'Contratos', 'fa-handshake', '#f59e0b', NULL, 3, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(4, 'Permisos y Licencias', 'fa-stamp', '#8b5cf6', NULL, 4, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(5, 'Inspecciones', 'fa-clipboard-check', '#06b6d4', NULL, 5, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(6, 'Facturas y Pagos', 'fa-receipt', '#ef4444', NULL, 6, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(7, 'Fotografías', 'fa-camera', '#ec4899', NULL, 7, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(8, 'Documentos Legales', 'fa-gavel', '#64748b', NULL, 8, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(9, 'Certificados', 'fa-certificate', '#14b8a6', NULL, 9, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30'),
(10, 'Otros', 'fa-file-alt', '#6b7280', NULL, 10, 1, '2026-01-16 12:50:30', '2026-01-16 12:50:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_permissions`
--

CREATE TABLE `document_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `permission_type` enum('view','download','edit','delete','share') NOT NULL,
  `granted_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_shares`
--

CREATE TABLE `document_shares` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `shared_with_email` varchar(255) NOT NULL,
  `shared_with_name` varchar(255) DEFAULT NULL,
  `share_token` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `can_download` tinyint(1) DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `accessed_at` datetime DEFAULT NULL,
  `access_count` int(10) UNSIGNED DEFAULT 0,
  `shared_by` int(10) UNSIGNED NOT NULL,
  `shared_with_user_id` int(10) UNSIGNED DEFAULT NULL,
  `permission_level` enum('viewer','editor','owner') DEFAULT 'viewer',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_tracking`
--

CREATE TABLE `document_tracking` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `action` enum('view','download','share','edit','delete') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `document_tracking`
--

INSERT INTO `document_tracking` (`id`, `document_id`, `user_id`, `action`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 23, 3, '', '179.52.212.177', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-16 18:48:13'),
(2, 23, 3, 'view', '179.52.212.177', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-16 18:48:38'),
(3, 23, 3, 'download', '179.52.212.177', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-16 18:48:55'),
(4, 24, 3, '', '179.52.212.177', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-16 18:50:38'),
(5, 25, 3, '', '179.52.212.177', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-16 18:52:07'),
(6, 23, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-16 19:49:22'),
(7, 23, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 01:22:36'),
(8, 26, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 04:52:07'),
(9, 26, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 04:52:22'),
(10, 27, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 04:53:02'),
(11, 28, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 05:03:26'),
(12, 28, 3, 'delete', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-01-17 05:03:43'),
(13, 24, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-18 14:41:30'),
(14, 29, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-18 14:55:55'),
(15, 29, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-18 14:56:44'),
(16, 30, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:42:03'),
(17, 31, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:42:36'),
(18, 31, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:43:06'),
(19, 31, 3, 'download', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:43:10'),
(20, 32, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:45:19'),
(21, 32, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:45:30'),
(22, 29, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:45:54'),
(23, 24, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:45:57'),
(24, 33, 3, '', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:46:25'),
(25, 33, 3, 'view', '201.229.159.246', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-19 14:47:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `document_versions`
--

CREATE TABLE `document_versions` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `version_number` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `version_notes` text DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body_html` text DEFAULT NULL,
  `body_text` text DEFAULT NULL,
  `template_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','sent','failed','bounced') DEFAULT 'pending',
  `sent_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `related_entity_type` varchar(100) DEFAULT NULL,
  `related_entity_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `template_name` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_text` text DEFAULT NULL,
  `template_type` varchar(100) DEFAULT NULL,
  `available_variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`available_variables`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_name`, `subject`, `body_html`, `body_text`, `template_type`, `available_variables`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'welcome_client', 'Bienvenido a Jaf Investments', '<h1>Bienvenido {client_name}</h1><p>Gracias por contactarnos. Nuestro equipo se pondrá en contacto contigo pronto.</p>', NULL, 'client', '[\"client_name\", \"agent_name\", \"agent_email\", \"agent_phone\"]', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(2, 'new_inquiry', 'Nueva consulta recibida', '<h2>Nueva Consulta</h2><p>Has recibido una nueva consulta de {client_name} para la propiedad {property_reference}.</p>', NULL, 'agent', '[\"client_name\", \"client_email\", \"client_phone\", \"property_reference\", \"property_title\", \"message\"]', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(3, 'property_match', 'Nueva propiedad que te puede interesar', '<h1>¡Nueva Propiedad Disponible!</h1><p>Hola {client_name}, tenemos una nueva propiedad que coincide con tus criterios de búsqueda.</p>', NULL, 'client', '[\"client_name\", \"property_title\", \"property_price\", \"property_url\"]', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(4, 'visit_confirmation', 'Confirmación de visita', '<h2>Visita Confirmada</h2><p>Hola {client_name}, tu visita a {property_title} ha sido confirmada para el {visit_date} a las {visit_time}.</p>', NULL, 'client', '[\"client_name\", \"property_title\", \"visit_date\", \"visit_time\", \"agent_name\", \"agent_phone\"]', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(5, 'task_reminder', 'Recordatorio de tarea', '<h2>Recordatorio</h2><p>Tienes una tarea pendiente: {task_title}</p><p>Fecha límite: {due_date}</p>', NULL, 'agent', '[\"task_title\", \"task_description\", \"due_date\", \"priority\"]', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expense_attachments`
--

CREATE TABLE `expense_attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `expense_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL COMMENT 'pdf, jpg, png, etc.',
  `file_size` int(11) DEFAULT NULL COMMENT 'en bytes',
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `expense_types`
--

CREATE TABLE `expense_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `type_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `expense_types`
--

INSERT INTO `expense_types` (`id`, `type_name`, `description`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'Materiales de Construcción', 'Compra de materiales para construcción', 1, 1, '2025-10-13 03:33:49'),
(2, 'Mano de Obra', 'Pago a trabajadores y contratistas', 1, 2, '2025-10-13 03:33:49'),
(3, 'Equipos y Herramientas', 'Compra o alquiler de equipos', 1, 3, '2025-10-13 03:33:49'),
(4, 'Transporte y Logística', 'Transporte de materiales y personal', 1, 4, '2025-10-13 03:33:49'),
(5, 'Alquiler de Maquinaria', 'Alquiler de maquinaria pesada', 1, 5, '2025-10-13 03:33:49'),
(6, 'Servicios Profesionales', 'Arquitectos, ingenieros, diseñadores', 1, 6, '2025-10-13 03:33:49'),
(7, 'Permisos y Licencias', 'Trámites legales y permisos', 1, 7, '2025-10-13 03:33:49'),
(8, 'Servicios Públicos', 'Agua, electricidad, gas durante obra', 1, 8, '2025-10-13 03:33:49'),
(9, 'Limpieza y Disposición de Escombros', 'Limpieza del sitio', 1, 9, '2025-10-13 03:33:49'),
(10, 'Seguros', 'Seguros de obra y responsabilidad civil', 1, 10, '2025-10-13 03:33:49'),
(11, 'Alimentación del Personal', 'Comidas para trabajadores', 1, 11, '2025-10-13 03:33:49'),
(12, 'Hospedaje', 'Alojamiento de trabajadores si es necesario', 1, 12, '2025-10-13 03:33:49'),
(13, 'Seguridad', 'Personal de seguridad y vigilancia', 1, 13, '2025-10-13 03:33:49'),
(14, 'Imprevistos', 'Gastos no planificados', 1, 14, '2025-10-13 03:33:49'),
(15, 'Otros', 'Gastos diversos', 1, 15, '2025-10-13 03:33:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `features`
--

CREATE TABLE `features` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `display_order` int(10) UNSIGNED DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `features`
--

INSERT INTO `features` (`id`, `name`, `slug`, `category`, `icon`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Aire Acondicionado', 'aire-acondicionado', 'Interior', 'fa-snowflake', 1, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(2, 'Calefacción', 'calefaccion', 'Interior', 'fa-fire', 2, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(3, 'Piscina', 'piscina', 'Exterior', 'fa-swimming-pool', 3, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(4, 'Jardín', 'jardin', 'Exterior', 'fa-leaf', 4, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(5, 'Terraza', 'terraza', 'Exterior', 'fa-umbrella-beach', 5, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(6, 'Balcón', 'balcon', 'Exterior', 'fa-door-open', 6, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(7, 'Amueblado', 'amueblado', 'Interior', 'fa-couch', 7, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(8, 'Cocina Equipada', 'cocina-equipada', 'Interior', 'fa-utensils', 8, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(9, 'Ascensor', 'ascensor', 'Edificio', 'fa-elevator', 9, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(10, 'Garaje', 'garaje', 'Edificio', 'fa-car', 10, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(11, 'Trastero', 'trastero', 'Edificio', 'fa-box', 11, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(12, 'Portero', 'portero', 'Edificio', 'fa-door-closed', 12, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(13, 'Alarma', 'alarma', 'Seguridad', 'fa-bell', 13, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(14, 'Puerta Blindada', 'puerta-blindada', 'Seguridad', 'fa-shield-alt', 14, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(15, 'Vigilancia 24h', 'vigilancia-24h', 'Seguridad', 'fa-video', 15, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(16, 'Internet/WiFi', 'internet-wifi', 'Servicios', 'fa-wifi', 16, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(17, 'Lavandería', 'lavanderia', 'Servicios', 'fa-tshirt', 17, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(18, 'Gimnasio', 'gimnasio', 'Servicios', 'fa-dumbbell', 18, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(19, 'Sauna', 'sauna', 'Servicios', 'fa-hot-tub', 19, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(20, 'Chimenea', 'chimenea', 'Interior', 'fa-fire-alt', 20, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(21, 'Armarios Empotrados', 'armarios-empotrados', 'Interior', 'fa-door-closed', 21, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(22, 'Suelo Radiante', 'suelo-radiante', 'Interior', 'fa-thermometer-half', 22, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(23, 'Doble Acristalamiento', 'doble-acristalamiento', 'Interior', 'fa-window-maximize', 23, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(24, 'Vistas al Mar', 'vistas-al-mar', 'Ubicación', 'fa-water', 24, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(25, 'Vistas a la Montaña', 'vistas-montaña', 'Ubicación', 'fa-mountain', 25, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(26, 'Cerca de Playa', 'cerca-playa', 'Ubicación', 'fa-umbrella-beach', 26, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(27, 'Cerca de Transporte', 'cerca-transporte', 'Ubicación', 'fa-bus', 27, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(28, 'Zona Escolar', 'zona-escolar', 'Ubicación', 'fa-school', 28, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(29, 'Zona Comercial', 'zona-comercial', 'Ubicación', 'fa-shopping-cart', 29, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(30, 'Mascotas Permitidas', 'mascotas-permitidas', 'Condiciones', 'fa-paw', 30, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('new','read','replied','archived','converted') DEFAULT 'new',
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Si se convierte en cliente',
  `source_page` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inquiry_responses`
--

CREATE TABLE `inquiry_responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `inquiry_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `response_text` text NOT NULL,
  `sent_email` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int(10) UNSIGNED NOT NULL,
  `material_type_id` int(10) UNSIGNED NOT NULL,
  `movement_type` enum('Entrada','Salida') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL,
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `invoice_reference` varchar(100) DEFAULT NULL,
  `responsible_user` int(10) UNSIGNED DEFAULT NULL,
  `movement_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_number` varchar(100) NOT NULL COMMENT 'Número de factura único',
  `client_id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `agent_id` int(10) UNSIGNED DEFAULT NULL,
  `invoice_type` enum('rent','sale','maintenance','other') NOT NULL DEFAULT 'rent',
  `billing_period` varchar(20) DEFAULT NULL COMMENT 'Formato: Oct/2025 - Alias de period_display',
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `period_start` date DEFAULT NULL COMMENT 'Para alquileres: inicio del período',
  `period_end` date DEFAULT NULL COMMENT 'Para alquileres: fin del período',
  `period_month` int(2) DEFAULT NULL COMMENT 'Mes de la factura (1-12)',
  `period_year` int(4) DEFAULT NULL COMMENT 'Año de la factura',
  `period_display` varchar(20) DEFAULT NULL COMMENT 'Formato: Oct-2025',
  `payment_day` tinyint(2) DEFAULT NULL COMMENT 'Día del mes para pago (alquileres)',
  `subtotal` decimal(15,2) NOT NULL,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `balance_due` decimal(15,2) NOT NULL,
  `status` enum('draft','pending','partial','paid','overdue','cancelled') DEFAULT 'pending',
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_reference` varchar(150) DEFAULT NULL,
  `paid_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `late_fee` decimal(15,2) DEFAULT 0.00,
  `is_recurring` tinyint(1) DEFAULT 0 COMMENT '1=Factura recurrente mensual',
  `next_invoice_date` date DEFAULT NULL COMMENT 'Para facturas recurrentes',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `auto_generated` tinyint(1) DEFAULT 0 COMMENT '1=Generada automáticamente, 0=Manual',
  `generation_date` timestamp NULL DEFAULT NULL COMMENT 'Fecha de generación automática',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `client_id`, `property_id`, `transaction_id`, `agent_id`, `invoice_type`, `billing_period`, `invoice_date`, `due_date`, `period_start`, `period_end`, `period_month`, `period_year`, `period_display`, `payment_day`, `subtotal`, `tax_percentage`, `tax_amount`, `discount_percentage`, `discount_amount`, `total_amount`, `amount_paid`, `balance_due`, `status`, `payment_method`, `payment_reference`, `paid_date`, `notes`, `late_fee`, `is_recurring`, `next_invoice_date`, `created_by`, `auto_generated`, `generation_date`, `created_at`, `updated_at`, `cancelled_at`, `cancelled_reason`) VALUES
(71, 'INV-2025-0001', 2, 9, 53, 2, 'rent', 'Oct/2025', '2025-10-01', '2025-10-31', '2025-10-01', '2025-10-31', 10, 2025, NULL, 1, 1850.00, 0.00, 0.00, 0.00, 0.00, 1850.00, 1850.00, 0.00, 'paid', 'credit_card', '', '2025-10-27', NULL, 0.00, 1, NULL, 3, 1, '2025-10-28 01:36:52', '2025-10-28 01:36:52', '2025-10-28 01:41:22', NULL, NULL),
(72, 'INV-2025-0002', 2, 9, 53, 2, 'rent', 'Nov/2025', '2025-11-01', '2025-11-30', '2025-11-01', '2025-11-30', 11, 2025, NULL, 1, 1850.00, 0.00, 0.00, 0.00, 0.00, 1850.00, 1850.00, 0.00, 'paid', 'cash', '', '2025-10-27', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 01:43:31', '2025-10-28 01:43:31', '2025-10-28 01:46:17', NULL, NULL),
(73, 'INV-2025-0003', 2, 9, 53, 2, 'rent', 'Dec/2025', '2025-12-01', '2025-12-31', '2025-12-01', '2025-12-31', 12, 2025, NULL, 1, 1850.00, 0.00, 0.00, 0.00, 0.00, 1850.00, 1850.00, 0.00, 'paid', 'cash', '', '2025-10-28', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 01:46:21', '2025-10-28 01:46:21', '2025-10-28 04:02:49', NULL, NULL),
(74, 'INV-2025-0004', 1, 6, 54, 2, 'sale', NULL, '2025-10-27', '2025-11-26', NULL, NULL, NULL, NULL, NULL, NULL, 4500000.00, 18.00, 810000.00, 0.00, 0.00, 5310000.00, 5310000.00, 0.00, 'paid', 'cash', '', '2025-10-27', '', 0.00, 0, NULL, 1, 0, NULL, '2025-10-28 01:58:59', '2025-10-28 02:00:40', NULL, NULL),
(75, 'INV-2025-0005', 23, 23, 55, 1, 'rent', 'Oct/2025', '2025-10-01', '2025-10-31', '2025-10-01', '2025-10-31', 10, 2025, NULL, 1, 12000.00, 0.00, 0.00, 0.00, 0.00, 12000.00, 12000.00, 0.00, 'paid', 'cash', '', '2025-10-28', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 03:56:05', '2025-10-28 03:56:05', '2025-10-28 04:04:00', NULL, NULL),
(76, 'INV-2026-0001', 2, 9, 53, 2, 'rent', 'Jan/2026', '2026-01-01', '2026-01-31', '2026-01-01', '2026-01-31', 1, 2026, NULL, 1, 1850.00, 0.00, 0.00, 0.00, 0.00, 1850.00, 1850.00, 0.00, 'paid', 'cash', '', '2025-10-28', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 04:04:27', '2025-10-28 04:04:27', '2025-10-28 04:07:14', NULL, NULL),
(77, 'INV-2025-0006', 23, 23, 55, 2, 'rent', 'Nov/2025', '2025-11-01', '2025-11-30', '2025-11-01', '2025-11-30', 11, 2025, NULL, 1, 12000.00, 0.00, 0.00, 0.00, 0.00, 12000.00, 12000.00, 0.00, 'paid', 'cash', '', '2025-10-28', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 04:04:27', '2025-10-28 04:04:27', '2025-10-28 04:08:24', NULL, NULL),
(78, 'INV-2026-0002', 2, 9, 53, 2, 'rent', 'Feb/2026', '2026-02-01', '2026-02-28', '2026-02-01', '2026-02-28', 2, 2026, NULL, 1, 1850.00, 0.00, 0.00, 0.00, 0.00, 1850.00, 1850.00, 0.00, 'paid', 'cash', '', '2025-10-28', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 04:07:19', '2025-10-28 04:07:19', '2025-10-28 04:07:40', NULL, NULL),
(79, 'INV-2026-0003', 2, 9, 53, 2, 'rent', 'Mar/2026', '2026-03-01', '2026-03-31', '2026-03-01', '2026-03-31', 3, 2026, NULL, 1, 1850.00, 0.00, 0.00, 0.00, 0.00, 1850.00, 1850.00, 0.00, 'paid', 'cash', '', '2025-10-28', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 04:07:46', '2025-10-28 04:07:46', '2025-10-28 04:11:06', NULL, NULL),
(80, 'INV-2025-0007', 23, 23, 55, 2, 'rent', 'Dec/2025', '2025-12-01', '2025-12-31', '2025-12-01', '2025-12-31', 12, 2025, NULL, 1, 12000.00, 0.00, 0.00, 0.00, 0.00, 12000.00, 12000.00, 0.00, 'paid', 'cash', '', '2025-10-28', NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 04:08:32', '2025-10-28 04:08:32', '2025-10-28 04:09:38', NULL, NULL),
(81, 'INV-2026-0004', 2, 9, 53, 2, 'rent', 'Apr/2026', '2026-04-01', '2026-04-30', '2026-04-01', '2026-04-30', 4, 2026, NULL, 1, 1850.00, 0.00, 0.00, 0.00, 0.00, 1850.00, 0.00, 1850.00, 'pending', NULL, NULL, NULL, NULL, 0.00, 1, NULL, 1, 1, '2025-10-28 04:11:53', '2025-10-28 04:11:53', '2025-10-28 04:11:53', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoice_generation_log`
--

CREATE TABLE `invoice_generation_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `generation_type` enum('rent','sale','maintenance') NOT NULL,
  `total_generated` int(10) UNSIGNED DEFAULT 0,
  `success_count` int(10) UNSIGNED DEFAULT 0,
  `failed_count` int(10) UNSIGNED DEFAULT 0,
  `total_errors` int(10) UNSIGNED DEFAULT 0,
  `generated_by` int(10) UNSIGNED NOT NULL,
  `client_ids` text DEFAULT NULL COMMENT 'JSON con IDs de clientes seleccionados',
  `clients_processed` text DEFAULT NULL COMMENT 'JSON con IDs de clientes procesados',
  `errors_log` text DEFAULT NULL COMMENT 'JSON con errores ocurridos',
  `execution_time` decimal(10,2) DEFAULT NULL COMMENT 'Tiempo de ejecución en segundos',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `invoice_generation_log`
--

INSERT INTO `invoice_generation_log` (`id`, `generation_type`, `total_generated`, `success_count`, `failed_count`, `total_errors`, `generated_by`, `client_ids`, `clients_processed`, `errors_log`, `execution_time`, `created_at`) VALUES
(57, 'rent', 1, 1, 0, 0, 3, '[\"53\"]', '[\"Camil Peralta\"]', '[]', NULL, '2025-10-28 01:36:52'),
(58, 'rent', 1, 1, 0, 0, 1, '[]', '[\"Camil Peralta\"]', '[]', NULL, '2025-10-28 01:43:31'),
(59, 'rent', 0, 0, 0, 0, 1, '[]', '[\"Camil Peralta\"]', '[]', NULL, '2025-10-28 01:46:06'),
(60, 'rent', 1, 1, 0, 0, 1, '[]', '[\"Camil Peralta\"]', '[]', NULL, '2025-10-28 01:46:21'),
(61, 'rent', 1, 1, 0, 0, 1, '[]', '[\"Camil Peralta\",\"Mercedes Reyes\"]', '[]', NULL, '2025-10-28 03:56:05'),
(62, 'rent', 2, 2, 0, 0, 1, '[]', '[\"Camil Peralta\",\"Mercedes Reyes\"]', '[]', NULL, '2025-10-28 04:04:27'),
(63, 'rent', 1, 1, 0, 0, 1, '[]', '[\"Camil Peralta\",\"Mercedes Reyes\"]', '[]', NULL, '2025-10-28 04:07:19'),
(64, 'rent', 1, 1, 0, 0, 1, '[]', '[\"Camil Peralta\",\"Mercedes Reyes\"]', '[]', NULL, '2025-10-28 04:07:46'),
(65, 'rent', 1, 1, 0, 0, 1, '[]', '[\"Camil Peralta\",\"Mercedes Reyes\"]', '[]', NULL, '2025-10-28 04:08:32'),
(66, 'rent', 0, 0, 0, 0, 1, '[]', '[\"Camil Peralta\",\"Mercedes Reyes\"]', '[]', NULL, '2025-10-28 04:10:11'),
(67, 'rent', 1, 1, 0, 0, 1, '[]', '[\"Camil Peralta\",\"Mercedes Reyes\"]', '[]', NULL, '2025-10-28 04:11:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_reference` varchar(150) DEFAULT NULL COMMENT 'Número de cheque, transferencia, etc',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `invoice_payments`
--

INSERT INTO `invoice_payments` (`id`, `invoice_id`, `payment_date`, `payment_amount`, `payment_method`, `payment_reference`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(35, 71, '2025-10-27', 1850.00, 'credit_card', '', '', 1, '2025-10-28 01:41:22', '2025-10-28 01:41:22'),
(36, 72, '2025-10-27', 1850.00, 'cash', '', '', 1, '2025-10-28 01:46:17', '2025-10-28 01:46:17'),
(37, 74, '2025-10-27', 1000000.00, 'bank_transfer', '', '', 1, '2025-10-28 02:00:02', '2025-10-28 02:00:02'),
(38, 74, '2025-10-27', 4310000.00, 'cash', '', '', 1, '2025-10-28 02:00:40', '2025-10-28 02:00:40'),
(39, 73, '2025-10-28', 1850.00, 'cash', '', '', 1, '2025-10-28 04:02:49', '2025-10-28 04:02:49'),
(40, 75, '2025-10-28', 12000.00, 'cash', '', '', 1, '2025-10-28 04:04:00', '2025-10-28 04:04:00'),
(41, 76, '2025-10-28', 1850.00, 'cash', '', '', 1, '2025-10-28 04:07:14', '2025-10-28 04:07:14'),
(42, 78, '2025-10-28', 1850.00, 'cash', '', '', 1, '2025-10-28 04:07:40', '2025-10-28 04:07:40'),
(43, 77, '2025-10-28', 12000.00, 'cash', '', '', 1, '2025-10-28 04:08:24', '2025-10-28 04:08:24'),
(44, 80, '2025-10-28', 12000.00, 'cash', '', '', 1, '2025-10-28 04:09:38', '2025-10-28 04:09:38'),
(45, 79, '2025-10-28', 1000.00, 'cash', '', '', 1, '2025-10-28 04:10:57', '2025-10-28 04:10:57'),
(46, 79, '2025-10-28', 850.00, 'cash', '', '', 1, '2025-10-28 04:11:06', '2025-10-28 04:11:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `material_inventory`
--

CREATE TABLE `material_inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `material_type_id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'puede ser general o específico',
  `quantity_in_stock` decimal(10,2) NOT NULL,
  `unit_of_measure` varchar(50) DEFAULT NULL,
  `average_cost` decimal(10,2) DEFAULT NULL,
  `total_value` decimal(15,2) DEFAULT NULL COMMENT 'quantity * average_cost',
  `storage_location` varchar(255) DEFAULT NULL,
  `minimum_stock_level` decimal(10,2) DEFAULT NULL COMMENT 'para alertas',
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `material_types`
--

CREATE TABLE `material_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit_of_measure` varchar(50) DEFAULT NULL COMMENT 'm², kg, litro, unidad, etc.',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `material_types`
--

INSERT INTO `material_types` (`id`, `material_name`, `category`, `unit_of_measure`, `description`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'Cemento Portland', 'Estructurales', 'Saco (50kg)', NULL, 1, 1, '2025-10-13 03:33:49'),
(2, 'Concreto Premezclado', 'Estructurales', 'm³', NULL, 1, 2, '2025-10-13 03:33:49'),
(3, 'Arena', 'Estructurales', 'm³', NULL, 1, 3, '2025-10-13 03:33:49'),
(4, 'Grava', 'Estructurales', 'm³', NULL, 1, 4, '2025-10-13 03:33:49'),
(5, 'Ladrillos', 'Estructurales', 'Unidad', NULL, 1, 5, '2025-10-13 03:33:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `notification_type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `related_entity_type` varchar(100) DEFAULT NULL,
  `related_entity_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `offices`
--

CREATE TABLE `offices` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(500) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'República Dominicana',
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `offices`
--

INSERT INTO `offices` (`id`, `name`, `address`, `city`, `state_province`, `country`, `postal_code`, `phone`, `email`, `latitude`, `longitude`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Oficina Principal', 'Avenida Principal 123', 'Santo Domingo', 'Nacional', 'República Dominicana', NULL, '+1 (809) 555-0100', 'info@lnuazoql_jafinvestments.com', NULL, NULL, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `phase_tasks`
--

CREATE TABLE `phase_tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `phase_id` int(10) UNSIGNED NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a contractors',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `task_status` enum('Pendiente','En Progreso','Completada','Bloqueada') DEFAULT 'Pendiente',
  `depends_on` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a otra tarea',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_contractors`
--

CREATE TABLE `project_contractors` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `contractor_id` int(10) UNSIGNED NOT NULL,
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `agreed_rate` decimal(10,2) DEFAULT NULL,
  `payment_terms` text DEFAULT NULL,
  `contract_file` varchar(500) DEFAULT NULL,
  `status` enum('Activo','Completado','Cancelado') DEFAULT 'Activo',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_documents`
--

CREATE TABLE `project_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `document_category` varchar(100) DEFAULT NULL COMMENT 'Planos, Permisos, Reportes, Fotos, etc.',
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'bytes',
  `version` int(11) DEFAULT 1,
  `is_current_version` tinyint(1) DEFAULT 1,
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `tags` text DEFAULT NULL COMMENT 'JSON array de etiquetas',
  `description` text DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_expenses`
--

CREATE TABLE `project_expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `expense_date` date NOT NULL,
  `invoice_reference` varchar(100) DEFAULT NULL,
  `expense_type_id` int(10) UNSIGNED NOT NULL,
  `material_type_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a contractors',
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit_of_measure` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT NULL,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `payment_method` enum('Efectivo','Transferencia','Cheque','Tarjeta Crédito','Tarjeta Débito') DEFAULT 'Efectivo',
  `payment_status` enum('Pagado','Pendiente','Pago Parcial','Atrasado') DEFAULT 'Pendiente',
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_phases`
--

CREATE TABLE `project_phases` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `phase_name` varchar(255) NOT NULL,
  `phase_order` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `estimated_start_date` date DEFAULT NULL,
  `estimated_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a contractors',
  `phase_status` enum('Pendiente','En Progreso','Completada','Bloqueada') DEFAULT 'Pendiente',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `budget_allocated` decimal(15,2) DEFAULT 0.00,
  `actual_spent` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_photos`
--

CREATE TABLE `project_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `photo_category` enum('Antes','Durante','Después','Problema','Comparativa') NOT NULL,
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `photo_date` date DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `taken_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `properties`
--

CREATE TABLE `properties` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `property_type_id` int(10) UNSIGNED NOT NULL,
  `operation_type` enum('sale','rent','vacation_rent','transfer') NOT NULL,
  `status` enum('available','reserved','rented','sold','retired','draft','deleted') DEFAULT 'draft',
  `price` decimal(15,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `deposit` decimal(15,2) DEFAULT NULL,
  `community_fees` decimal(10,2) DEFAULT NULL,
  `ibi_annual` decimal(10,2) DEFAULT NULL,
  `price_per_sqm` decimal(10,2) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'República Dominicana',
  `state_province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zone` varchar(150) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `bedrooms` int(10) UNSIGNED DEFAULT 0,
  `bathrooms` decimal(3,1) DEFAULT 0.0,
  `garage` tinyint(1) DEFAULT 0,
  `garage_spaces` int(10) UNSIGNED DEFAULT 0,
  `elevator` tinyint(1) DEFAULT 0,
  `floor_number` int(11) DEFAULT NULL,
  `total_floors` int(10) UNSIGNED DEFAULT NULL,
  `useful_area` decimal(10,2) DEFAULT NULL,
  `built_area` decimal(10,2) DEFAULT NULL,
  `plot_area` decimal(10,2) DEFAULT NULL,
  `year_built` int(10) UNSIGNED DEFAULT NULL,
  `orientation` enum('north','south','east','west','northeast','northwest','southeast','southwest') DEFAULT NULL,
  `conservation_status` varchar(100) DEFAULT NULL,
  `energy_certificate` varchar(10) DEFAULT NULL,
  `furnished` tinyint(1) DEFAULT 0,
  `owner_id` int(10) UNSIGNED DEFAULT NULL,
  `agent_id` int(10) UNSIGNED DEFAULT NULL,
  `second_agent_id` int(10) UNSIGNED DEFAULT NULL,
  `office_id` int(10) UNSIGNED DEFAULT NULL,
  `views_count` int(10) UNSIGNED DEFAULT 0,
  `inquiries_count` int(10) UNSIGNED DEFAULT 0,
  `favorites_count` int(10) UNSIGNED DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `publish_on_website` tinyint(1) DEFAULT 0,
  `publish_on_homepage` tinyint(1) DEFAULT 0,
  `publish_portals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`publish_portals`)),
  `video_url` varchar(500) DEFAULT NULL,
  `virtual_tour_url` varchar(500) DEFAULT NULL,
  `expected_date` date DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `origin_agency` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `published_at` timestamp NULL DEFAULT NULL,
  `sold_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `properties`
--

INSERT INTO `properties` (`id`, `reference`, `title`, `description`, `property_type_id`, `operation_type`, `status`, `price`, `currency`, `deposit`, `community_fees`, `ibi_annual`, `price_per_sqm`, `country`, `state_province`, `city`, `zone`, `postal_code`, `address`, `latitude`, `longitude`, `bedrooms`, `bathrooms`, `garage`, `garage_spaces`, `elevator`, `floor_number`, `total_floors`, `useful_area`, `built_area`, `plot_area`, `year_built`, `orientation`, `conservation_status`, `energy_certificate`, `furnished`, `owner_id`, `agent_id`, `second_agent_id`, `office_id`, `views_count`, `inquiries_count`, `favorites_count`, `featured`, `publish_on_website`, `publish_on_homepage`, `publish_portals`, `video_url`, `virtual_tour_url`, `expected_date`, `internal_notes`, `origin_agency`, `created_at`, `updated_at`, `published_at`, `sold_at`) VALUES
(1, '#68DF53EA73AA8', 'Apartamento En Venta En Bella Vista', '<p>Apartamento nuevo a estrenar . Con :Sala ,Comedor ,Balcon ,Cocina concepto abierto, area de lavado, Habitacion de servicio completa, dos Habitaciones ,principal con Walking Closet y Baño ,Baño para Habitacion secundaria y visitas ,Closet para ropa blanca y dos Parqueos paralelos techados . Planta full,Ascensor ,areas Sociales con Jacuzzy , terraza techada y una destechada,salon para eventos ,Gas comun, servicio de seguridad y conserje . Ubicado cercano al Parque Botanico y a la Avenida Carlos Perez Ricart.</p>', 3, 'sale', 'available', 160000.00, 'USD', NULL, NULL, NULL, NULL, 'República Dominicana', 'Distrito Nacional', 'Santo Domingo', 'Bella Vista', '', 'Calle 1era #123', 18.48634010, -69.83905111, 3, 2.0, 1, 2, 0, 2, 0, 300.00, 250.00, 230.00, 2023, 'east', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 0, 0, 0, 1, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-03 08:47:07', '2025-10-04 09:11:38', NULL, NULL),
(2, '#68DF566631A8F', 'Apartamento En Venta', '<p>Apartamento nuevo a estrenar . Con :Sala ,Comedor ,Balcon ,Cocina concepto abierto, area de lavado, Habitacion de servicio completa, dos Habitaciones ,principal con Walking Closet y Baño ,Baño para Habitacion secundaria y visitas ,Closet para ropa blanca y dos Parqueos paralelos techados . Planta full,Ascensor ,areas Sociales con Jacuzzy , terraza techada y una destechada,salon para eventos ,Gas comun, servicio de seguridad y conserje . Ubicado cercano al Parque Botanico y a la Avenida Carlos Perez Ricart.</p>', 3, 'sale', 'available', 160000.00, 'USD', NULL, NULL, NULL, NULL, 'República Dominicana', 'Distrito nacional', 'Santo Domingo', 'Bella Vista', '', 'Calle 1era #123', 18.48610000, -69.93120000, 3, 2.0, 1, 2, 0, 2, 1, 300.00, 250.00, 230.00, 2024, 'east', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 0, 0, 0, 1, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-03 08:54:44', '2025-10-05 03:10:37', NULL, NULL),
(3, '#68E0B28349FA6', 'Apartamento En Venta (Copia)', '<p>Apartamento nuevo a estrenar . Con :Sala ,Comedor ,Balcon ,Cocina concepto abierto, area de lavado, Habitacion de servicio completa, dos Habitaciones ,principal con Walking Closet y Baño ,Baño para Habitacion secundaria y visitas ,Closet para ropa blanca y dos Parqueos paralelos techados . Planta full,Ascensor ,areas Sociales con Jacuzzy , terraza techada y una destechada,salon para eventos ,Gas comun, servicio de seguridad y conserje . Ubicado cercano al Parque Botanico y a la Avenida Carlos Perez Ricart.</p>', 3, 'sale', 'available', 160000.00, 'USD', NULL, NULL, NULL, NULL, 'República Dominicana', 'Distrito nacional', 'Santo Domingo', 'Bella Vista', '', 'Calle 1era #123', 18.48610000, -69.93120000, 3, 2.0, 1, 2, 0, 2, 1, 300.00, 250.00, 230.00, 2024, 'east', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 0, 0, 0, 1, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-04 09:37:07', '2025-10-28 04:15:33', NULL, NULL),
(4, '#MIA001A', 'Luxury Waterfront Apartment in Brickell', '<p>Stunning waterfront apartment in the heart of Brickell. Floor-to-ceiling windows with breathtaking bay views. Modern Italian kitchen, marble bathrooms, and smart home technology. Building amenities include infinity pool, spa, fitness center, and 24/7 concierge.</p>', 3, 'sale', 'available', 850000.00, 'USD', NULL, 650.00, 2500.00, 850.00, 'República Dominicana', 'Florida', 'Miami', 'Brickell', '33131', '1000 Brickell Plaza, Apt 3501', 25.76168000, -80.19179000, 2, 2.0, 1, 2, 1, 35, 45, 1000.00, 1200.00, 0.00, 2021, 'east', '', 'A', 1, NULL, 2, NULL, 1, 15, 3, 2, 1, 1, 1, NULL, '', '', NULL, 'Premium unit', NULL, '2025-10-05 03:25:46', '2025-10-26 05:17:27', NULL, '2025-10-01 22:30:00'),
(5, '#MIA002A', 'Modern Condo in Wynwood Arts District', '<p>Contemporary 1-bedroom loft in trendy Wynwood. Exposed concrete, high ceilings, and modern finishes. Walking distance to galleries, restaurants, and nightlife. Pet-friendly building with rooftop terrace.</p>', 3, 'rent', 'available', 2800.00, 'USD', 5600.00, 350.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Wynwood', '33127', '245 NW 26th Street, Unit 412', 25.80157000, -80.19953000, 1, 1.0, 1, 1, 1, 4, 6, 750.00, 850.00, 0.00, 2019, 'north', '', 'A', 0, NULL, 2, NULL, 1, 8, 1, 0, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-26 05:17:52', NULL, NULL),
(6, '#MIA003V', 'Beachfront Villa in Miami Beach', '<p>Magnificent 5-bedroom villa directly on the beach. Private pool, outdoor kitchen, and direct beach access. Completely renovated with luxury finishes throughout. Perfect for entertaining with spacious living areas and lush tropical landscaping.</p>', 4, 'sale', 'sold', 4500000.00, 'USD', NULL, 1200.00, 8500.00, 1125.00, 'USA', 'Florida', 'Miami', 'Miami Beach', '33139', '5234 Collins Avenue', 25.82397000, -80.12260000, 5, 4.5, 1, 3, 0, NULL, 2, 4000.00, 4500.00, 6000.00, 2020, 'east', 'Excelente', 'A+', 1, NULL, 1, 2, 1, 25, 5, 3, 1, 1, 1, NULL, 'https://youtube.com/watch?v=example', '', NULL, 'Exclusive beachfront', NULL, '2025-10-05 03:25:46', '2025-10-28 01:59:17', NULL, NULL),
(7, '#MIA004C', 'Charming House in Coral Gables', '<p>Beautiful Mediterranean-style home in prestigious Coral Gables. Original features with modern updates. Spacious rooms, wooden floors, and a lovely backyard with pool. Located in excellent school district.</p>', 2, 'sale', 'reserved', 1250000.00, 'USD', NULL, 0.00, 4200.00, 625.00, 'USA', 'Florida', 'Miami', 'Coral Gables', '33134', '1425 Venetia Avenue', 25.74511000, -80.25849000, 4, 3.0, 1, 2, 0, NULL, 1, 2000.00, 2400.00, 4500.00, 1955, 'south', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 12, 2, 1, 0, 1, 1, NULL, '', '', NULL, 'Historic area', NULL, '2025-10-05 03:25:46', '2025-10-13 04:34:39', NULL, NULL),
(8, '#MIA005A', 'Penthouse in Downtown Miami', '<p>Exclusive penthouse with panoramic city views. Private rooftop terrace with jacuzzi. Chef\'s kitchen, wine cellar, and home theater. Building offers valet parking, concierge, and resort-style amenities.</p>', 5, 'sale', 'available', 2800000.00, 'USD', NULL, 1500.00, 6000.00, 1400.00, 'USA', 'Florida', 'Miami', 'Downtown', '33130', '851 NE 1st Avenue, PH-01', 25.78135000, -80.18670000, 3, 3.5, 1, 3, 1, 48, 50, 2000.00, 2500.00, NULL, 2022, 'southeast', 'Nuevo', 'A+', 1, NULL, 1, NULL, 1, 18, 4, 2, 1, 1, 1, NULL, '', 'https://virtualtour.com/example', NULL, 'Luxury penthouse', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(9, '#MIA006A', 'Studio Apartment in South Beach', '<p>Cozy studio in the heart of South Beach. Fully furnished with modern amenities. Steps from the beach, restaurants, and Ocean Drive. Perfect for young professionals or seasonal rental.</p>', 3, 'rent', 'rented', 1850.00, 'USD', 3700.00, 280.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'South Beach', '33139', '1560 Ocean Drive, Apt 205', 25.78644000, -80.13005000, 0, 1.0, 0, 0, 1, 2, 8, 450.00, 500.00, 0.00, 2018, 'east', 'Buen estado', 'B', 1, NULL, 2, NULL, 1, 22, 5, 1, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-28 01:36:12', NULL, NULL),
(10, '#MIA007C', 'Family Home in Coconut Grove', '<p>Spacious 4-bedroom home in family-friendly Coconut Grove. Large backyard with covered patio, updated kitchen, and hurricane shutters. Close to top-rated schools and parks.</p>', 2, 'sale', 'reserved', 980000.00, 'USD', NULL, 0.00, 3800.00, 490.00, 'USA', 'Florida', 'Miami', 'Coconut Grove', '33133', '3245 Aviation Avenue', 25.72822000, -80.24284000, 4, 2.5, 1, 2, 0, NULL, 1, 2000.00, 2300.00, 5200.00, 1998, 'north', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 9, 1, 0, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-13 04:34:39', NULL, NULL),
(11, '#MIA008L', 'Prime Retail Space in Design District', '<p>High-visibility retail space in Miami Design District. Perfect for boutique, gallery, or restaurant. High ceilings, glass storefront, and excellent foot traffic. Surrounded by luxury brands.</p>', 8, 'rent', 'available', 8500.00, 'USD', 17000.00, 1200.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Design District', '33137', '140 NE 39th Street, Suite 101', 25.81215000, -80.19198000, 0, 1.0, 0, 0, 0, 1, 2, 1500.00, 1800.00, 0.00, 2017, '', '', 'A', 0, NULL, 2, NULL, 1, 14, 3, 0, 0, 1, 0, NULL, '', '', NULL, 'Commercial prime location', NULL, '2025-10-05 03:25:46', '2025-10-26 05:17:15', NULL, '2025-10-09 00:00:00'),
(12, '#MIA009A', 'Luxury Condo in Edgewater', '<p>Brand new 2-bedroom corner unit with water views. Italian cabinetry, quartz countertops, and spa-like bathrooms. Amenities include pool, gym, theater room, and business center.</p>', 3, 'sale', 'available', 725000.00, 'USD', NULL, 550.00, 2200.00, 725.00, 'USA', 'Florida', 'Miami', 'Edgewater', '33137', '788 NE 23rd Street, Unit 1208', 25.79778000, -80.18892000, 2, 2.0, 1, 1, 1, 12, 35, 1000.00, 1150.00, NULL, 2023, 'northeast', 'Nuevo', 'A+', 0, NULL, 3, NULL, 1, 6, 0, 0, 1, 1, 1, NULL, '', '', NULL, 'New construction', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(13, '#MIA010V', 'Waterfront Estate in Key Biscayne', '<p>Spectacular waterfront estate with private dock. 6 bedrooms, home office, gym, and wine cellar. Infinity pool overlooking the bay. Smart home automation throughout.</p>', 4, 'sale', 'available', 6800000.00, 'USD', NULL, 2500.00, 12000.00, 1360.00, 'República Dominicana', 'Florida', 'Miami', 'Key Biscayne', '33149', '456 Harbor Drive', 25.69233000, -80.16330000, 6, 5.5, 1, 4, 0, 0, 2, 5000.00, 6200.00, 10000.00, 2021, 'east', '', '', 1, NULL, 2, 2, 1, 31, 7, 4, 1, 1, 1, NULL, 'https://youtube.com/watch?v=example2', 'https://virtualtour.com/example2', NULL, 'Ultra luxury waterfront', NULL, '2025-10-05 03:25:46', '2025-10-28 04:15:05', NULL, NULL),
(14, '#MIA011A', 'Cozy Apartment in Little Havana', '<p>Affordable 1-bedroom apartment in vibrant Little Havana. Recently updated with new appliances and flooring. Walking distance to Calle Ocho, cafes, and public transportation.</p>', 3, 'rent', 'rented', 1450.00, 'USD', 2900.00, 200.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Little Havana', '33135', '1542 SW 8th Street, Apt 3B', 25.76505000, -80.21807000, 1, 1.0, 0, 1, 0, 3, 4, 650.00, 700.00, 0.00, 2005, 'west', 'Buen estado', 'C', 0, NULL, 2, NULL, 1, 17, 4, 2, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-29 17:00:05', NULL, NULL),
(15, '#MIA012D', 'Spacious Duplex in Midtown', '<p>Modern 2-story duplex in trendy Midtown Miami. Open floor plan, gourmet kitchen, and private rooftop terrace. Walking distance to shops, restaurants, and entertainment.</p>', 7, 'sale', 'available', 675000.00, 'USD', NULL, 425.00, 2100.00, 675.00, 'USA', 'Florida', 'Miami', 'Midtown', '33137', '3451 NE 1st Avenue, Unit C', 25.80679000, -80.19234000, 2, 2.5, 1, 2, 0, NULL, 2, 1000.00, 1200.00, NULL, 2019, 'south', 'Excelente', 'A', 0, NULL, 3, NULL, 1, 10, 2, 1, 0, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(16, '#MIA013O', 'Modern Office Space in Brickell', '<p>Class A office space in prestigious Brickell tower. Floor-to-ceiling windows, raised floors, and modern HVAC. Building offers parking, security, and conference facilities.</p>', 9, 'rent', 'available', 6200.00, 'USD', 12400.00, 950.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Brickell', '33131', '1221 Brickell Avenue, Suite 1550', 25.76089000, -80.19156000, 0, 2.0, 0, 3, 1, 15, 40, 1200.00, 1400.00, 0.00, 2020, '', '', 'A', 0, NULL, 2, NULL, 1, 8, 2, 0, 0, 1, 0, NULL, '', '', NULL, 'Professional office', NULL, '2025-10-05 03:25:46', '2025-10-28 04:15:17', NULL, NULL),
(17, '#MIA014C', 'Historic Home in Buena Vista', '<p>Charming historic home with original architectural details. Hardwood floors, vintage tile, and modern kitchen. Large lot with mature trees and covered porch. Great investment opportunity.</p>', 2, 'sale', 'available', 565000.00, 'USD', NULL, 0.00, 2800.00, 377.00, 'USA', 'Florida', 'Miami', 'Buena Vista', '33127', '4520 NE 2nd Avenue', 25.81568000, -80.19156000, 3, 2.0, 1, 1, 0, NULL, 1, 1500.00, 1800.00, 4000.00, 1940, 'north', 'Para reformar', 'D', 0, NULL, 2, NULL, 1, 5, 1, 0, 0, 1, 0, NULL, '', '', NULL, 'Fixer-upper opportunity', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(18, '#MIA015A', 'Bay View Apartment in North Bay Village', '<p>Beautiful 2-bedroom with stunning bay views. Tile floors, updated kitchen, and spacious balcony. Community pool, tennis courts, and boat dock access.</p>', 3, 'rent', 'available', 2650.00, 'USD', 5300.00, 380.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'North Bay Village', '33141', '7904 East Drive, Apt 602', 25.84685000, -80.15234000, 2, 2.0, 1, 1, 1, 6, 12, 1100.00, 1250.00, 0.00, 2010, 'east', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 11, 2, 1, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-28 04:14:51', NULL, NULL),
(19, '#MIA016V', 'Contemporary Villa in Pinecrest', '<p>Stunning modern villa on oversized lot. Open concept living, chef\'s kitchen, and luxurious master suite. Resort-style pool, summer kitchen, and three-car garage. A-rated schools nearby.</p>', 4, 'sale', 'available', 2150000.00, 'USD', NULL, 0.00, 7500.00, 860.00, 'USA', 'Florida', 'Miami', 'Pinecrest', '33156', '12450 SW 77th Avenue', 25.66234000, -80.31445000, 5, 4.0, 1, 3, 0, NULL, 1, 2500.00, 3200.00, 12000.00, 2022, 'south', 'Nuevo', 'A+', 0, NULL, 1, NULL, 1, 7, 1, 0, 1, 1, 1, NULL, '', '', NULL, 'Premium Pinecrest', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(20, '#MIA017A', 'Penthouse Loft in Arts & Entertainment District', '<p>Ultra-modern penthouse loft with soaring ceilings. Industrial chic design, chef\'s kitchen, and private terrace. Heart of the cultural district near museums and theaters.</p>', 5, 'sale', 'available', 1450000.00, 'USD', NULL, 750.00, 4200.00, 966.00, 'USA', 'Florida', 'Miami', 'Arts & Entertainment', '33132', '151 SE 1st Street, PH-2', 25.77456000, -80.19012000, 2, 2.5, 1, 2, 1, 15, 15, 1500.00, 1800.00, NULL, 2018, 'north', 'Excelente', 'A', 1, NULL, 2, NULL, 1, 13, 3, 1, 1, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(21, '#MIA018C', 'Renovated Bungalow in Morningside', '<p>Completely renovated 2-bedroom bungalow. New kitchen, bathrooms, roof, and impact windows. Hardwood floors and modern finishes. Quiet tree-lined street.</p>', 2, 'sale', 'available', 695000.00, 'USD', NULL, 0.00, 2900.00, 579.00, 'USA', 'Florida', 'Miami', 'Morningside', '33138', '5830 NE 6th Avenue', 25.83456000, -80.19234000, 2, 2.0, 1, 1, 0, NULL, 1, 1200.00, 1500.00, 3500.00, 1948, 'east', 'Excelente', 'B', 0, NULL, 3, NULL, 1, 4, 0, 0, 0, 1, 0, NULL, '', '', NULL, 'Charming renovation', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(22, '#MIA019A', 'Luxury Apartment in Fisher Island', '<p>Exclusive apartment on prestigious Fisher Island. Ocean and city views, marble floors, and European kitchen. Access to private beach club, golf course, and spa.</p>', 3, 'sale', 'available', 3200000.00, 'USD', NULL, 2800.00, 9500.00, 1600.00, 'USA', 'Florida', 'Miami', 'Fisher Island', '33109', '7192 Fisher Island Drive, Unit 7192', 25.76234000, -80.14567000, 3, 3.5, 1, 2, 1, 25, 30, 2000.00, 2500.00, NULL, 2020, 'southeast', 'Excelente', 'A+', 1, NULL, 1, 2, 1, 20, 4, 2, 1, 1, 1, NULL, '', '', NULL, 'Ultra exclusive', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(23, '#MIA020L', 'Restaurant Space in Aventura', '<p>Fully equipped restaurant space in busy Aventura mall area. Commercial kitchen, dining area for 80, and outdoor patio. High traffic location with ample parking.</p>', 8, 'rent', 'available', 12000.00, 'USD', 24000.00, 1800.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Aventura', '33180', '19575 Biscayne Boulevard', 25.96234000, -80.14234000, 0, 2.0, 0, 10, 0, 1, 1, 2500.00, 3000.00, 0.00, 2015, '', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 19, 5, 1, 0, 1, 0, NULL, '', '', NULL, 'Turn-key restaurant', NULL, '2025-10-05 03:25:46', '2025-10-28 04:14:38', NULL, NULL),
(24, '#MIA021A', 'Waterfront Condo in Sunny Isles', '<p>Direct oceanfront 2-bedroom condo. Wrap-around balcony with sunrise views. Fully renovated with designer finishes. Five-star amenities including beach service.</p>', 3, 'sale', 'available', 1580000.00, 'USD', NULL, 1200.00, 4800.00, 1053.00, 'USA', 'Florida', 'Miami', 'Sunny Isles Beach', '33160', '18201 Collins Avenue, Unit 1501', 25.94567000, -80.12234000, 2, 2.0, 1, 2, 1, 15, 40, 1500.00, 1800.00, NULL, 2019, 'east', 'Excelente', 'A', 1, NULL, 2, NULL, 1, 16, 3, 2, 1, 1, 1, NULL, '', '', NULL, 'Ocean views', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(25, '#MIA022C', 'Single Family Home in Palmetto Bay', '<p>Well-maintained 3-bedroom home in family neighborhood. Updated kitchen and bathrooms, tile roof, and hurricane protection. Large fenced yard with pool.</p>', 2, 'rent', 'available', 3500.00, 'USD', 7000.00, 0.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Palmetto Bay', '33157', '8745 SW 168th Street', 25.61234000, -80.32456000, 3, 2.0, 1, 2, 0, 0, 1, 1800.00, 2100.00, 6000.00, 2008, 'south', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 12, 3, 1, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-26 05:18:17', NULL, NULL),
(26, '#MIA023A', 'Smart Condo in Dadeland', '<p>Tech-enabled 1-bedroom near Dadeland Mall. Smart locks, thermostat, and lighting. Modern kitchen with stainless appliances. Easy access to Metrorail.</p>', 3, 'rent', 'available', 1950.00, 'USD', 3900.00, 320.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Dadeland', '33156', '8888 SW 72nd Avenue, Unit 205', 25.68956000, -80.30789000, 1, 1.0, 1, 1, 1, 2, 8, 750.00, 850.00, 0.00, 2021, 'north', '', 'A', 0, NULL, 2, NULL, 1, 8, 1, 0, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-26 05:18:25', NULL, NULL),
(27, '#MIA024V', 'Mediterranean Villa in Coral Gables', '<p>Classic Mediterranean estate on tree-lined street. Original details, updated systems, and guest house. Lush gardens, pool, and outdoor living areas.</p>', 4, 'sale', 'available', 3750000.00, 'USD', NULL, 0.00, 10500.00, 938.00, 'USA', 'Florida', 'Miami', 'Coral Gables', '33134', '945 Alhambra Circle', 25.74789000, -80.26234000, 5, 4.5, 1, 3, 0, NULL, 2, 4000.00, 5200.00, 15000.00, 1926, 'south', 'Excelente', 'A', 0, NULL, 1, 2, 1, 14, 2, 1, 1, 1, 1, NULL, '', '', NULL, 'Historic estate', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(28, '#MIA025O', 'Medical Office in Miami Lakes', '<p>Medical office suite ready for practice. Multiple exam rooms, reception, and private offices. Ample parking and near hospitals. Ideal for doctors or dentists.</p>', 9, 'rent', 'available', 4800.00, 'USD', 9600.00, 650.00, NULL, NULL, 'USA', 'Florida', 'Miami', 'Miami Lakes', '33014', '15400 NW 77th Court, Suite 200', 25.91234000, -80.32567000, 0, 3.0, 0, 8, 1, 2, 3, 1800.00, 2000.00, NULL, 2016, NULL, 'Excelente', 'A', 0, NULL, 1, NULL, 1, 6, 1, 0, 0, 1, 0, NULL, '', '', NULL, 'Medical ready', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(29, '#MIA026A', 'Luxury High-Rise in Icon Brickell', '<p>Sophisticated 2-bedroom in Icon Brickell. Breathtaking views, European appliances, and spa bathrooms. World-class amenities including infinity pools and full-service spa.</p>', 3, 'sale', 'available', 1125000.00, 'USD', NULL, 980.00, 3800.00, 900.00, 'República Dominicana', 'Florida', 'Miami', 'Brickell', '33131', '495 Brickell Avenue, Unit 3812', 25.76678000, -80.19023000, 2, 2.5, 1, 2, 1, 38, 58, 1250.00, 1500.00, 0.00, 2008, 'east', '', 'A', 0, NULL, 2, NULL, 1, 17, 4, 2, 1, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-26 05:16:47', NULL, '2025-09-30 23:00:00'),
(30, '#MIA027C', 'Tropical Retreat in South Miami', '<p>Private tropical oasis with pool and outdoor kitchen. Updated 4-bedroom home with open layout. Large lot with fruit trees and covered terrace. Minutes from Metrorail.</p>', 2, 'sale', 'available', 875000.00, 'USD', NULL, 0.00, 3500.00, 583.00, 'USA', 'Florida', 'Miami', 'South Miami', '33143', '6234 SW 63rd Avenue', 25.70456000, -80.28901000, 4, 3.0, 1, 2, 0, NULL, 1, 1500.00, 2000.00, 8000.00, 2005, 'west', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 9, 1, 0, 0, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(31, '#MIA028A', 'Penthouse at Paramount Miami Worldcenter', '<p>Stunning penthouse at Paramount. Panoramic city and bay views from every room. Italian kitchen, smart home technology, and private elevator. Exclusive Sky Lounge access.</p>', 5, 'sale', 'available', 3500000.00, 'USD', NULL, 2200.00, 8900.00, 1458.00, 'USA', 'Florida', 'Miami', 'Downtown', '33132', '851 NE 1st Avenue, PH-5502', 25.78234000, -80.18567000, 3, 3.5, 1, 3, 1, 55, 60, 2400.00, 2900.00, NULL, 2019, 'northeast', 'Nuevo', 'A+', 1, NULL, 1, NULL, 1, 23, 5, 3, 1, 1, 1, NULL, 'https://youtube.com/watch?v=example3', '', NULL, 'Ultra luxury penthouse', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(32, '#MIA029L', 'Warehouse Space in Allapattah', '<p>Industrial warehouse with office space. High ceilings, loading dock, and plenty of storage. Fenced yard and secured parking. Perfect for distribution or light manufacturing.</p>', 11, 'rent', 'available', 7500.00, 'USD', 15000.00, 500.00, NULL, NULL, 'USA', 'Florida', 'Miami', 'Allapattah', '33142', '2134 NW 21st Street', 25.79567000, -80.22890000, 0, 2.0, 0, 15, 0, 1, 1, 5000.00, 5500.00, NULL, 2010, NULL, 'Buen estado', 'C', 0, NULL, 1, NULL, 1, 5, 0, 0, 0, 1, 0, NULL, '', '', NULL, 'Industrial space', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(33, '#MIA030A', 'Beachfront Apartment in Surfside', '<p>Direct ocean views from this beautifully updated 2-bedroom. Marble floors, gourmet kitchen, and spa bathroom. Building offers beach service, pool, and fitness center.</p>', 3, 'sale', 'available', 1350000.00, 'USD', NULL, 850.00, 4200.00, 1125.00, 'USA', 'Florida', 'Miami', 'Surfside', '33154', '9559 Collins Avenue, Unit 802', 25.87890000, -80.12156000, 2, 2.0, 1, 1, 1, 8, 12, 1200.00, 1400.00, NULL, 2017, 'east', 'Excelente', 'A', 0, NULL, 2, NULL, 1, 15, 3, 2, 1, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(34, '#MIA031C', 'Contemporary Home in Hammocks', '<p>Modern 3-bedroom home in gated community. Open floor plan, impact windows, and updated kitchen. Community pool, playground, and clubhouse. A-rated schools.</p>', 2, 'rent', 'available', 2950.00, 'USD', 5900.00, 285.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Hammocks', '33196', '14520 SW 152nd Terrace', 25.64789000, -80.43567000, 3, 2.5, 1, 2, 0, 0, 1, 1650.00, 1900.00, 4000.00, 2015, 'south', '', 'A', 0, NULL, 2, NULL, 1, 11, 2, 1, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-26 05:18:32', NULL, NULL),
(35, '#MIA032V', 'Waterfront Modern Villa in Venetian Islands', '<p>Architectural masterpiece on Venetian Islands. Floor-to-ceiling glass, infinity pool, and 100ft of waterfront. Private dock for yacht. Smart home with premium finishes throughout.</p>', 4, 'sale', 'available', 8950000.00, 'USD', NULL, 1500.00, 15000.00, 1492.00, 'USA', 'Florida', 'Miami', 'Venetian Islands', '33139', '15 West San Marino Drive', 25.78901000, -80.15234000, 6, 6.0, 1, 4, 0, NULL, 2, 6000.00, 7500.00, 12000.00, 2022, 'east', 'Nuevo', 'A+', 1, NULL, 1, 2, 1, 28, 6, 4, 1, 1, 1, NULL, 'https://youtube.com/watch?v=example4', 'https://virtualtour.com/example3', NULL, 'Architectural gem', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(36, '#MIA033A', 'Affordable Studio in Flagami', '<p>Clean and bright studio apartment. Updated kitchen and bathroom, tile floors, and ceiling fans. Close to shopping, dining, and public transportation.</p>', 3, 'rent', 'available', 1250.00, 'USD', 2500.00, 150.00, NULL, NULL, 'USA', 'Florida', 'Miami', 'Flagami', '33144', '6840 SW 8th Street, Apt 12', 25.76456000, -80.30123000, 0, 1.0, 0, 1, 0, 1, 3, 400.00, 450.00, NULL, 2012, 'north', 'Buen estado', 'C', 0, NULL, 2, NULL, 1, 14, 3, 2, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(37, '#MIA034O', 'Executive Office in Coral Gables', '<p>Prestigious office space in Miracle Mile area. Elegant reception, private offices, and conference room. On-site parking and 24/7 access. Perfect for professional services.</p>', 9, 'rent', 'available', 5500.00, 'USD', 11000.00, 720.00, NULL, NULL, 'USA', 'Florida', 'Miami', 'Coral Gables', '33134', '2222 Ponce de Leon Boulevard, Suite 300', 25.75234000, -80.25678000, 0, 2.0, 0, 5, 1, 3, 8, 1500.00, 1700.00, NULL, 2014, NULL, 'Excelente', 'A', 0, NULL, 1, NULL, 1, 7, 1, 0, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(38, '#MIA035A', 'Garden Apartment in Coral Way', '<p>Charming 1-bedroom with private patio. Wood floors, updated kitchen, and abundant natural light. Quiet residential area near shops and restaurants.</p>', 3, 'rent', 'available', 1650.00, 'USD', 3300.00, 200.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Coral Way', '33145', '2545 SW 37th Avenue, Unit 1', 25.74123000, -80.25890000, 1, 1.0, 0, 1, 0, 1, 2, 700.00, 800.00, 0.00, 2009, 'south', 'Buen estado', 'B', 0, NULL, 2, NULL, 1, 9, 2, 0, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-26 05:18:39', NULL, NULL),
(39, '#MIA036C', 'Pool Home in Westchester', '<p>Spacious 3-bedroom pool home. Updated kitchen with granite counters, tile throughout, and screened patio. Large yard with room for expansion. Great schools.</p>', 2, 'sale', 'available', 545000.00, 'USD', NULL, 0.00, 2600.00, 363.00, 'USA', 'Florida', 'Miami', 'Westchester', '33155', '9245 SW 24th Terrace', 25.74567000, -80.35234000, 3, 2.0, 1, 2, 0, NULL, 1, 1500.00, 1800.00, 6500.00, 1975, 'west', 'Buen estado', 'C', 0, NULL, 2, NULL, 1, 6, 1, 0, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(40, '#MIA037A', 'Luxury Loft in Wynwood', '<p>Industrial-chic loft in heart of Wynwood. Exposed brick, concrete floors, and floor-to-ceiling windows. Walking distance to galleries, breweries, and street art.</p>', 3, 'sale', 'available', 485000.00, 'USD', NULL, 380.00, 1800.00, 646.00, 'USA', 'Florida', 'Miami', 'Wynwood', '33127', '315 NW 27th Street, Unit 501', 25.80234000, -80.19890000, 1, 1.0, 1, 1, 1, 5, 6, 750.00, 900.00, NULL, 2020, 'north', 'Excelente', 'A', 0, NULL, 3, NULL, 1, 12, 2, 1, 1, 1, 1, NULL, '', '', NULL, 'Urban loft', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(41, '#MIA038V', 'Estate Home in Pinecrest', '<p>Grand estate on 1+ acre lot. 5 bedrooms plus guest house, theater room, and wine cellar. Resort pool with waterfall, summer kitchen, and tennis court.</p>', 4, 'sale', 'available', 4250000.00, 'USD', NULL, 0.00, 11000.00, 850.00, 'USA', 'Florida', 'Miami', 'Pinecrest', '33156', '11550 SW 82nd Avenue', 25.66789000, -80.32234000, 5, 5.5, 1, 3, 0, NULL, 2, 5000.00, 6200.00, 45000.00, 2018, 'south', 'Excelente', 'A+', 0, NULL, 1, 2, 1, 16, 3, 2, 1, 1, 1, NULL, '', '', NULL, 'Luxury estate', NULL, '2025-10-05 03:25:46', '2025-10-13 03:33:02', NULL, NULL),
(42, '#MIA039L', 'Boutique Retail in Lincoln Road', '<p>Prime retail space on iconic Lincoln Road. High foot traffic, outdoor seating area, and modern interior. Perfect for fashion, accessories, or café.</p>', 8, 'rent', 'available', 15000.00, 'USD', 30000.00, 2500.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'South Beach', '33139', '925 Lincoln Road', 25.79123000, -80.13567000, 0, 1.0, 0, 0, 0, 1, 1, 1200.00, 1400.00, 0.00, 2016, '', '', 'A', 0, NULL, 2, NULL, 1, 21, 6, 2, 0, 1, 0, NULL, '', '', NULL, 'Premium retail', NULL, '2025-10-05 03:25:46', '2025-10-28 04:14:17', NULL, NULL),
(43, '#MIA040A', 'Bay Harbor Islands Condo', '<p>Updated 2-bedroom in prestigious Bay Harbor Islands. Marble floors, renovated kitchen and baths, and large balcony. Walk to shops, beach, and A+ schools.</p>', 3, 'sale', 'available', 625000.00, 'USD', NULL, 520.00, 2400.00, 625.00, 'USA', 'Florida', 'Miami', 'Bay Harbor Islands', '33154', '1045 Kane Concourse, Unit 415', 25.88567000, -80.13234000, 2, 2.0, 1, 1, 1, 4, 8, 1000.00, 1200.00, NULL, 2013, 'east', 'Excelente', 'B', 0, NULL, 2, NULL, 1, 10, 2, 1, 0, 1, 1, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(44, '#MIA041C', 'Ranch Home in Southwest Miami', '<p>Single-story ranch on corner lot. 3 bedrooms, 2 baths, and large family room. Updated kitchen, tile roof, and covered carport. Fruit trees and patio.</p>', 2, 'rent', 'available', 2750.00, 'USD', 5500.00, 0.00, NULL, NULL, 'USA', 'Florida', 'Miami', 'Southwest Miami', '33165', '10845 SW 72nd Street', 25.70234000, -80.36789000, 3, 2.0, 0, 2, 0, NULL, 1, 1400.00, 1650.00, 5500.00, 1982, 'east', 'Buen estado', 'C', 0, NULL, 3, NULL, 1, 8, 1, 0, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(45, '#MIA042A', 'Penthouse in Brickell Heights', '<p>Sky-high penthouse with 360-degree views. Private rooftop with pool and summer kitchen. Designer finishes, automated shades, and wine room. Ultimate luxury living.</p>', 5, 'sale', 'available', 4750000.00, 'USD', NULL, 2100.00, 10200.00, 1583.00, 'USA', 'Florida', 'Miami', 'Brickell', '33131', '1155 Brickell Bay Drive, PH-4901', 25.76234000, -80.18901000, 4, 4.5, 1, 3, 1, 49, 50, 3000.00, 3600.00, NULL, 2021, 'southeast', 'Nuevo', 'A+', 1, NULL, 1, 2, 1, 25, 5, 3, 1, 1, 1, NULL, 'https://youtube.com/watch?v=example5', 'https://virtualtour.com/example4', NULL, 'Ultimate penthouse', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(46, '#MIA043O', 'Tech Office in Wynwood', '<p>Modern tech-ready office space. Open layout, high-speed fiber, and modern HVAC. Exposed ceilings, polished concrete floors. Perfect for startups and creative companies.</p>', 9, 'rent', 'available', 3800.00, 'USD', 7600.00, 450.00, NULL, NULL, 'USA', 'Florida', 'Miami', 'Wynwood', '33127', '250 NW 23rd Street, Suite 200', 25.79890000, -80.19567000, 0, 2.0, 0, 4, 1, 2, 3, 1000.00, 1200.00, NULL, 2019, NULL, 'Excelente', 'A', 0, NULL, 1, NULL, 1, 6, 1, 0, 0, 1, 0, NULL, '', '', NULL, 'Tech-ready space', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(47, '#MIA044C', 'Updated Home in Shenandoah', '<p>Beautifully updated 3-bedroom in historic Shenandoah. New kitchen and baths, original hardwood floors, and impact windows. Covered porch and fenced yard.</p>', 2, 'sale', 'available', 725000.00, 'USD', NULL, 0.00, 3100.00, 604.00, 'USA', 'Florida', 'Miami', 'Shenandoah', '33145', '1545 SW 14th Street', 25.75890000, -80.21456000, 3, 2.0, 1, 1, 0, NULL, 1, 1200.00, 1600.00, 4200.00, 1938, 'north', 'Excelente', 'B', 0, NULL, 2, NULL, 1, 7, 1, 0, 0, 1, 1, NULL, '', '', NULL, 'Historic charm', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(48, '#MIA045A', 'Oceanfront at Jade Beach', '<p>Luxury oceanfront residence at Jade Beach. Flow-through unit with sunrise and sunset views. Private elevator, summer kitchen on balcony, and smart home features.</p>', 3, 'sale', 'available', 2950000.00, 'USD', NULL, 1800.00, 7500.00, 1475.00, 'USA', 'Florida', 'Miami', 'Sunny Isles Beach', '33160', '17001 Collins Avenue, Unit 2001', 25.94234000, -80.12089000, 3, 3.5, 1, 2, 1, 20, 51, 2000.00, 2400.00, NULL, 2009, 'east', 'Excelente', 'A', 1, NULL, 1, NULL, 1, 19, 4, 3, 1, 1, 1, NULL, '', '', NULL, 'Oceanfront luxury', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(49, '#MIA046V', 'Modern Estate in Coconut Grove', '<p>Contemporary estate steps from the water. Clean lines, impact glass, and smart home technology. Pool, outdoor kitchen, and rooftop deck. Walk to marinas and restaurants.</p>', 4, 'sale', 'available', 5650000.00, 'USD', NULL, 0.00, 12500.00, 1130.00, 'USA', 'Florida', 'Miami', 'Coconut Grove', '33133', '3567 Main Highway', 25.72567000, -80.24890000, 5, 5.0, 1, 3, 0, NULL, 3, 5000.00, 6500.00, 8000.00, 2023, 'east', 'Nuevo', 'A+', 0, NULL, 1, 2, 1, 13, 2, 1, 1, 1, 1, NULL, '', '', NULL, 'Waterfront modern', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(50, '#MIA047L', 'Showroom in Miami Design District', '<p>High-end showroom with floor-to-ceiling glass. Modern HVAC, LED lighting, and polished floors. Perfect for furniture, art, or luxury retail. Valet parking available.</p>', 8, 'rent', 'available', 18000.00, 'USD', 36000.00, 3200.00, NULL, NULL, 'USA', 'Florida', 'Miami', 'Design District', '33137', '3841 NE 2nd Avenue', 25.81456000, -80.19123000, 0, 1.0, 0, 8, 0, 1, 2, 3000.00, 3500.00, NULL, 2020, NULL, 'Excelente', 'A+', 0, NULL, 1, NULL, 1, 11, 3, 1, 0, 1, 0, NULL, '', '', NULL, 'Luxury showroom', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(51, '#MIA048A', 'Waterfront Condo in Williams Island', '<p>Exclusive island living at Williams Island. Marina and Intracoastal views, marble throughout, and gourmet kitchen. Private beach club, spa, tennis, and 24-hour security.</p>', 3, 'sale', 'available', 1875000.00, 'USD', NULL, 1450.00, 5200.00, 1042.00, 'USA', 'Florida', 'Miami', 'Williams Island', '33160', '5000 Island Estates Drive, Unit 1504', 25.93456000, -80.13678000, 3, 3.0, 1, 2, 1, 15, 25, 1800.00, 2200.00, NULL, 2015, 'southeast', 'Excelente', 'A', 1, NULL, 2, NULL, 1, 14, 3, 2, 1, 1, 1, NULL, '', '', NULL, 'Island paradise', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(52, '#MIA049C', 'Townhouse in Doral', '<p>Modern 3-story townhouse in gated Doral community. Open layout, chef\'s kitchen, and master suite with balcony. Community pool, gym, and A-rated schools nearby.</p>', 2, 'rent', 'available', 3250.00, 'USD', 6500.00, 425.00, NULL, NULL, 'República Dominicana', 'Florida', 'Miami', 'Doral', '33178', '10450 NW 82nd Terrace', 25.87234000, -80.35890000, 3, 2.5, 1, 2, 0, 0, 3, 1800.00, 2100.00, 2500.00, 2019, 'south', '', 'A', 0, NULL, 2, NULL, 1, 9, 2, 1, 0, 1, 0, NULL, '', '', NULL, '', NULL, '2025-10-05 03:25:46', '2025-10-26 05:22:56', NULL, NULL),
(53, '#MIA050A', 'Art Deco Gem in South Beach', '<p>Beautifully restored Art Deco apartment. Original details, modern kitchen and bath, and private balcony. Steps to beach, dining, and nightlife. Historic district.</p>', 3, 'sale', 'available', 585000.00, 'USD', NULL, 420.00, 2100.00, 731.00, 'USA', 'Florida', 'Miami', 'South Beach', '33139', '1234 Ocean Drive, Apt 305', 25.78123000, -80.13012000, 1, 1.0, 0, 0, 1, 3, 5, 800.00, 950.00, NULL, 1939, 'east', 'Excelente', 'B', 1, NULL, 2, NULL, 1, 16, 4, 2, 1, 1, 1, NULL, '', '', NULL, 'Art Deco classic', NULL, '2025-10-05 03:25:46', '2025-10-05 03:25:46', NULL, NULL),
(54, '#MIA051V', 'Gated Estate in Country Club of Miami', '<p>Magnificent estate behind gates. 6 bedrooms including separate guest suite, home theater, and gym. Pool with spa, outdoor kitchen, and covered loggia. Private and serene.</p>', 4, 'sale', 'available', 3150000.00, 'USD', NULL, 0.00, 9200.00, 787.00, 'República Dominicana', 'Florida', 'Miami', 'Country Club of Miami', '33186', 'SW 124th Avenue', 25.65123000, -80.39567000, 6, 5.0, 1, 3, 0, 0, 2, 4000.00, 5200.00, 18000.00, 2017, 'south', '', 'A', 0, NULL, 2, 2, 1, 11, 2, 1, 1, 1, 1, NULL, '', '', NULL, 'Private gated estate', NULL, '2025-10-05 03:25:46', '2025-10-13 03:33:11', NULL, NULL),
(55, '#MIA052A', 'Jennifer', '<p>Architectural masterpiece by Bjarke Ingels. Twisted towers design, 10-foot ceilings, and European kitchen. Private marina, beach club, and personalized concierge services.</p>', 3, 'sale', 'available', 2750000.00, 'USD', NULL, 1950.00, 6800.00, 1375.00, 'República Dominicana', 'Florida', 'Miami', 'Coconut Grove', '33133', '2669 South Bayshore Drive, Unit 901N', 25.72234000, -80.23456000, 3, 3.5, 1, 2, 1, 9, 20, 2000.00, 2500.00, 0.00, 2016, 'northeast', '', '', 1, NULL, 2, NULL, 1, 20, 4, 3, 1, 1, 1, NULL, '', '', NULL, 'Iconic architecture', NULL, '2025-10-05 03:25:46', '2026-01-19 14:44:03', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_alerts`
--

CREATE TABLE `property_alerts` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `alert_name` varchar(255) DEFAULT NULL,
  `property_type_id` int(10) UNSIGNED DEFAULT NULL,
  `operation_type` enum('sale','rent','vacation_rent') DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zone` varchar(150) DEFAULT NULL,
  `price_min` decimal(15,2) DEFAULT NULL,
  `price_max` decimal(15,2) DEFAULT NULL,
  `bedrooms_min` int(10) UNSIGNED DEFAULT NULL,
  `bedrooms_max` int(10) UNSIGNED DEFAULT NULL,
  `features_desired` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features_desired`)),
  `frequency` enum('immediate','daily','weekly') DEFAULT 'daily',
  `is_active` tinyint(1) DEFAULT 1,
  `last_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_documents`
--

CREATE TABLE `property_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_url` varchar(500) NOT NULL,
  `document_path` varchar(500) DEFAULT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_features`
--

CREATE TABLE `property_features` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `feature_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_images`
--

CREATE TABLE `property_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `is_main` tinyint(1) DEFAULT 0,
  `display_order` int(10) UNSIGNED DEFAULT 0,
  `alt_text` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `width` int(10) UNSIGNED DEFAULT NULL,
  `height` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `property_images`
--

INSERT INTO `property_images` (`id`, `property_id`, `image_url`, `image_path`, `thumbnail_url`, `is_main`, `display_order`, `alt_text`, `title`, `file_size`, `width`, `height`, `created_at`, `updated_at`) VALUES
(1, 1, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df554b2014e.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df554b2014e.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 1, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df554b20e42.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df554b20e42.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3, 1, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df554b2176d.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df554b2176d.jpg', NULL, 0, 2, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(4, 1, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df554b21d7f.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df554b21d7f.jpg', NULL, 0, 3, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(5, 2, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df57141fa27.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df57141fa27.jpg', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(6, 2, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df5714207df.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df5714207df.jpg', NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 2, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df571420dfa.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df571420dfa.jpg', NULL, 0, 2, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(8, 2, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df571421392.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df571421392.jpg', NULL, 0, 3, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(9, 3, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df57141fa27.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df57141fa27.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(10, 3, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df5714207df.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df5714207df.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(11, 3, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df571420dfa.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df571420dfa.jpg', NULL, 0, 2, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(12, 3, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/68df571421392.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/68df571421392.jpg', NULL, 0, 3, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(13, 4, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(14, 4, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(15, 4, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', NULL, 0, 2, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(16, 5, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(17, 5, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(18, 6, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(19, 6, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(20, 6, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', NULL, 0, 2, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(21, 7, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(22, 7, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(23, 8, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(24, 8, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(25, 9, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(26, 9, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(27, 10, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(28, 10, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(29, 10, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', NULL, 0, 2, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(30, 11, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(31, 11, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(32, 12, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(33, 12, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(34, 13, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(35, 13, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(36, 13, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', NULL, 0, 2, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(37, 14, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(38, 14, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(39, 15, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(40, 15, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(41, 16, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(42, 17, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(43, 18, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(44, 19, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(45, 20, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(46, 21, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(47, 22, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(48, 23, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(49, 24, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(50, 25, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(51, 26, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(52, 27, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(53, 28, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(54, 29, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(55, 30, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(56, 31, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(57, 32, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(58, 33, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(59, 34, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(60, 35, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(61, 36, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(62, 37, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(63, 38, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(64, 39, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(65, 40, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(66, 41, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(67, 42, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(68, 43, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(69, 44, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(70, 45, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(71, 46, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/09.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(72, 47, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/10.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(73, 48, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/01.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(74, 49, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/02.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(75, 50, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/03.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(76, 51, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/04.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(77, 52, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/05.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(78, 53, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/06.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(79, 54, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/07.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(80, 55, 'https://mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', '/home/lnuazoql/mmlabstudio.com/jafinvestments44/uploads/properties/08.jpg', NULL, 1, 0, NULL, NULL, NULL, NULL, NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_owners`
--

CREATE TABLE `property_owners` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `document_id` varchar(100) DEFAULT NULL,
  `document_type` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_secondary` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_types`
--

CREATE TABLE `property_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `display_order` int(10) UNSIGNED DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `property_types`
--

INSERT INTO `property_types` (`id`, `name`, `slug`, `icon`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Piso', 'piso', 'fa-building', 1, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(2, 'Casa', 'casa', 'fa-home', 2, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(3, 'Apartamento', 'apartamento', 'fa-door-open', 3, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(4, 'Villa', 'villa', 'fa-hotel', 4, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(5, 'Ático', 'atico', 'fa-city', 5, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(6, 'Chalet', 'chalet', 'fa-house-user', 6, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(7, 'Dúplex', 'duplex', 'fa-layer-group', 7, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(8, 'Local Comercial', 'local-comercial', 'fa-store', 8, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(9, 'Oficina', 'oficina', 'fa-briefcase', 9, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(10, 'Terreno', 'terreno', 'fa-map', 10, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(11, 'Nave Industrial', 'nave-industrial', 'fa-industry', 11, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(12, 'Garaje', 'garaje', 'fa-warehouse', 12, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(13, 'Trastero', 'trastero', 'fa-box', 13, 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_videos`
--

CREATE TABLE `property_videos` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `video_url` varchar(500) NOT NULL,
  `video_type` enum('youtube','vimeo','file','other') DEFAULT 'youtube',
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `property_views`
--

CREATE TABLE `property_views` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `property_views`
--

INSERT INTO `property_views` (`id`, `property_id`, `user_id`, `client_id`, `ip_address`, `user_agent`, `referrer`, `viewed_at`) VALUES
(11, 55, NULL, NULL, '148.101.6.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, '2025-10-28 01:49:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `restoration_projects`
--

CREATE TABLE `restoration_projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a properties',
  `project_name` varchar(255) NOT NULL,
  `project_reference` varchar(50) NOT NULL,
  `restoration_type` enum('Completa','Parcial','Remodelación Interior','Remodelación Exterior','Ampliación','Mantenimiento Mayor') NOT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a clients',
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `estimated_start_date` date DEFAULT NULL,
  `estimated_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `estimated_duration` int(11) DEFAULT NULL COMMENT 'días',
  `description` text DEFAULT NULL,
  `project_status` enum('Planificación','En Progreso','Pausado','Completado','Cancelado') DEFAULT 'Planificación',
  `overall_progress` decimal(5,2) DEFAULT 0.00 COMMENT '0-100%',
  `initial_property_cost` decimal(15,2) DEFAULT 0.00,
  `total_budget` decimal(15,2) DEFAULT 0.00,
  `budget_labor` decimal(15,2) DEFAULT 0.00,
  `budget_materials` decimal(15,2) DEFAULT 0.00,
  `budget_equipment` decimal(15,2) DEFAULT 0.00,
  `budget_permits` decimal(15,2) DEFAULT 0.00,
  `budget_professional_services` decimal(15,2) DEFAULT 0.00,
  `budget_contingency` decimal(15,2) DEFAULT 0.00,
  `budget_other` decimal(15,2) DEFAULT 0.00,
  `tolerance_margin` decimal(5,2) DEFAULT 10.00 COMMENT 'porcentaje',
  `total_spent` decimal(15,2) DEFAULT 0.00 COMMENT 'calculado automáticamente',
  `total_investment` decimal(15,2) DEFAULT 0.00 COMMENT 'initial_cost + total_spent',
  `is_published` tinyint(1) DEFAULT 0,
  `published_property_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a properties cuando se publica',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `permissions`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'administrador', 'Administrador', 'Acceso total al sistema', '{\"dashboard\": [\"view\"], \"properties\": [\"view\", \"create\", \"edit\", \"delete\"], \"clients\": [\"view\", \"create\", \"edit\", \"delete\"], \"users\": [\"view\", \"create\", \"edit\", \"delete\"], \"reports\": [\"view\", \"export\"], \"settings\": [\"view\", \"edit\"]}', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(2, 'agente_ventas', 'Agente de Ventas', 'Gestión de propiedades y clientes de venta', '{\"dashboard\": [\"view\"], \"properties\": [\"view\", \"create\", \"edit\"], \"clients\": [\"view\", \"create\", \"edit\"], \"reports\": [\"view\"]}', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(3, 'agente_alquiler', 'Agente de Alquiler', 'Gestión de propiedades y clientes de alquiler', '{\"dashboard\": [\"view\"], \"properties\": [\"view\", \"create\", \"edit\"], \"clients\": [\"view\", \"create\", \"edit\"], \"reports\": [\"view\"]}', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(4, 'coordinador', 'Coordinador', 'Supervisión de equipos y reportes', '{\"dashboard\": [\"view\"], \"properties\": [\"view\", \"edit\"], \"clients\": [\"view\", \"edit\"], \"users\": [\"view\"], \"reports\": [\"view\", \"export\"]}', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(5, 'usuario_lectura', 'Usuario de Solo Lectura', 'Visualización sin edición', '{\"dashboard\": [\"view\"], \"properties\": [\"view\"], \"clients\": [\"view\"], \"reports\": [\"view\"]}', 1, '2025-10-02 23:31:37', '2025-10-02 23:31:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sales_transactions`
--

CREATE TABLE `sales_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction_code` varchar(100) DEFAULT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `property_previous_status` varchar(50) DEFAULT NULL COMMENT 'Estado previo de la propiedad',
  `client_id` int(10) UNSIGNED NOT NULL,
  `agent_id` int(10) UNSIGNED NOT NULL,
  `second_agent_id` int(10) UNSIGNED DEFAULT NULL,
  `office_id` int(10) UNSIGNED DEFAULT NULL,
  `transaction_type` enum('sale','rent','vacation_rent') NOT NULL,
  `sale_price` decimal(15,2) NOT NULL,
  `original_price` decimal(15,2) DEFAULT NULL,
  `commission_percentage` decimal(5,2) DEFAULT NULL,
  `commission_amount` decimal(15,2) DEFAULT NULL,
  `agent_commission` decimal(15,2) DEFAULT NULL,
  `second_agent_commission` decimal(15,2) DEFAULT NULL,
  `contract_date` date DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `move_in_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `cancelled_reason` text DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `completed_by` int(10) UNSIGNED DEFAULT NULL,
  `payment_status` enum('pending','partial','completed','overdue') DEFAULT 'pending',
  `balance_pending` decimal(15,2) DEFAULT 0.00,
  `has_pending_invoice` tinyint(1) DEFAULT 0 COMMENT 'Indica si tiene factura pendiente',
  `last_invoice_date` date DEFAULT NULL COMMENT 'Fecha última factura generada',
  `next_invoice_date` date DEFAULT NULL COMMENT 'Fecha próxima factura (alquileres)',
  `payment_method` varchar(100) DEFAULT NULL,
  `financing_type` enum('cash','bank_loan','owner_financing','mixed') DEFAULT 'cash',
  `bank_name` varchar(150) DEFAULT NULL,
  `loan_amount` decimal(15,2) DEFAULT NULL,
  `down_payment` decimal(15,2) DEFAULT NULL,
  `monthly_payment` decimal(15,2) DEFAULT NULL COMMENT 'Para alquileres',
  `rent_payment_day` tinyint(2) DEFAULT NULL COMMENT 'Día del mes para pago de alquiler (1-31)',
  `rent_duration_months` int(10) UNSIGNED DEFAULT NULL COMMENT 'Duración del contrato',
  `rent_end_date` date DEFAULT NULL,
  `last_invoice_generated_date` date DEFAULT NULL COMMENT 'Fecha de la última factura generada automáticamente',
  `warranty_amount` decimal(15,2) DEFAULT NULL COMMENT 'Fianza/Garantía',
  `tax_amount` decimal(15,2) DEFAULT NULL COMMENT 'Impuestos (ITBIS, etc)',
  `notary_fees` decimal(15,2) DEFAULT NULL COMMENT 'Gastos notariales',
  `other_fees` decimal(15,2) DEFAULT NULL COMMENT 'Otros gastos',
  `total_transaction_cost` decimal(15,2) DEFAULT NULL COMMENT 'Costo total',
  `deposit_paid` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `contract_file_url` varchar(500) DEFAULT NULL,
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Documentos asociados' CHECK (json_valid(`documents`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sales_transactions`
--

INSERT INTO `sales_transactions` (`id`, `transaction_code`, `property_id`, `property_previous_status`, `client_id`, `agent_id`, `second_agent_id`, `office_id`, `transaction_type`, `sale_price`, `original_price`, `commission_percentage`, `commission_amount`, `agent_commission`, `second_agent_commission`, `contract_date`, `closing_date`, `move_in_date`, `status`, `cancelled_reason`, `cancelled_by`, `cancelled_at`, `completed_by`, `payment_status`, `balance_pending`, `has_pending_invoice`, `last_invoice_date`, `next_invoice_date`, `payment_method`, `financing_type`, `bank_name`, `loan_amount`, `down_payment`, `monthly_payment`, `rent_payment_day`, `rent_duration_months`, `rent_end_date`, `last_invoice_generated_date`, `warranty_amount`, `tax_amount`, `notary_fees`, `other_fees`, `total_transaction_cost`, `deposit_paid`, `notes`, `contract_file_url`, `documents`, `created_at`, `updated_at`) VALUES
(53, 'REN-2025-0001', 9, NULL, 2, 2, NULL, 1, 'rent', 22200.00, NULL, 0.00, 0.00, NULL, NULL, '2025-10-27', NULL, '2025-10-27', 'completed', NULL, NULL, NULL, NULL, 'partial', 11100.00, 1, '2026-03-01', '2026-04-01', '', 'cash', '', NULL, NULL, 1850.00, 1, 12, '2026-10-27', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, '', NULL, NULL, '2025-10-28 01:34:20', '2025-10-28 11:40:00'),
(54, 'SAL-2025-0001', 6, NULL, 1, 2, NULL, NULL, 'sale', 4500000.00, 4500000.00, 5.00, 225000.00, NULL, NULL, '2025-10-27', '2025-10-30', NULL, 'completed', NULL, NULL, NULL, NULL, 'completed', 0.00, 0, NULL, NULL, '', 'cash', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, '', NULL, NULL, '2025-10-28 01:58:13', '2025-10-28 02:00:40'),
(55, 'REN-2025-0002', 23, NULL, 23, 2, NULL, NULL, 'rent', 36000.00, NULL, 0.00, 0.00, NULL, NULL, '2025-10-30', NULL, '2025-10-27', 'completed', NULL, NULL, NULL, NULL, 'pending', 36000.00, 0, '2025-11-01', '2025-12-01', '', 'cash', '', NULL, NULL, 12000.00, 1, 3, '2026-01-27', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, '', NULL, NULL, '2025-10-28 03:55:56', '2025-10-28 18:12:29'),
(56, 'REN-2025-0003', 14, NULL, 1, 2, NULL, NULL, 'rent', 17400.00, 17400.00, 10.00, 145.00, NULL, NULL, '2025-10-29', NULL, NULL, 'in_progress', NULL, NULL, NULL, NULL, 'pending', 17400.00, 0, NULL, NULL, '', 'cash', '', NULL, NULL, 1450.00, NULL, 12, '0000-00-00', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, '', NULL, NULL, '2025-10-29 17:00:05', '2025-10-30 04:11:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sale_payments`
--

CREATE TABLE `sale_payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `payment_amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `payment_reference` varchar(150) DEFAULT NULL COMMENT 'Número de cheque, transferencia, etc',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sale_timeline`
--

CREATE TABLE `sale_timeline` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `event_type` enum('created','status_changed','payment_received','document_uploaded','note_added','cancelled','completed','other') NOT NULL,
  `event_title` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sale_timeline`
--

INSERT INTO `sale_timeline` (`id`, `transaction_id`, `event_type`, `event_title`, `event_description`, `old_value`, `new_value`, `user_id`, `created_at`) VALUES
(35, 53, 'created', 'Transacción Creada', 'La transacción ha sido registrada en el sistema', NULL, '{\"status\":\"in_progress\",\"sale_price\":22200}', 3, '2025-10-28 01:34:20'),
(36, 53, 'status_changed', 'Estado de Transacción Actualizado', 'Estado cambió de \'in_progress\' a \'completed\'. Estado de propiedad actualizado a \'rented\'', 'in_progress', 'completed', 3, '2025-10-28 01:36:12'),
(37, 53, 'other', 'Transacción Actualizada', 'Se modificaron los siguientes campos: agent_id, status', '{\"agent_id\":3,\"status\":\"in_progress\"}', '{\"agent_id\":\"2\",\"status\":\"completed\"}', 3, '2025-10-28 01:36:12'),
(38, 53, 'other', 'Transacción Actualizada', 'Se modificaron los siguientes campos: office_id', '{\"office_id\":null}', '{\"office_id\":\"1\"}', 1, '2025-10-28 01:36:22'),
(39, 54, 'created', 'Transacción Creada', 'La transacción ha sido registrada en el sistema', NULL, '{\"status\":\"in_progress\",\"sale_price\":4500000}', 1, '2025-10-28 01:58:13'),
(40, 54, 'status_changed', 'Estado de Transacción Actualizado', 'Estado cambió de \'in_progress\' a \'completed\'. Estado de propiedad actualizado a \'sold\'', 'in_progress', 'completed', 1, '2025-10-28 01:59:17'),
(41, 54, 'other', 'Transacción Actualizada', 'Se modificaron los siguientes campos: status', '{\"status\":\"in_progress\"}', '{\"status\":\"completed\"}', 1, '2025-10-28 01:59:17'),
(42, 55, 'created', 'Transacción Creada', 'La transacción ha sido registrada en el sistema', NULL, '{\"status\":\"in_progress\",\"sale_price\":36000}', 1, '2025-10-28 03:55:56'),
(43, 55, 'status_changed', 'Estado de Transacción Actualizado', 'Estado cambió de \'in_progress\' a \'completed\'. Estado de propiedad actualizado a \'rented\'', 'in_progress', 'completed', 1, '2025-10-28 04:03:13'),
(44, 55, 'other', 'Transacción Actualizada', 'Se modificaron los siguientes campos: agent_id, status', '{\"agent_id\":1,\"status\":\"in_progress\"}', '{\"agent_id\":\"2\",\"status\":\"completed\"}', 1, '2025-10-28 04:03:13'),
(45, 55, 'other', 'Transacción Actualizada', 'Se modificaron los siguientes campos: contract_date, balance_pending', '{\"contract_date\":\"2025-10-27\",\"balance_pending\":\"0.00\"}', '{\"contract_date\":\"2025-10-30\",\"balance_pending\":36000}', 1, '2025-10-28 17:18:04'),
(46, 56, 'other', 'Transacción Actualizada', 'Se modificaron los siguientes campos: agent_id, payment_status', '{\"agent_id\":1,\"payment_status\":\"pending\"}', '{\"agent_id\":\"2\",\"payment_status\":\"completed\"}', 1, '2025-10-30 04:02:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `data_type` enum('string','integer','boolean','json','text') DEFAULT 'string',
  `category` varchar(100) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `data_type`, `category`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'company_name', 'Jaf Investments', 'string', 'general', 'Nombre de la empresa', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(2, 'company_email', 'info@lnuazoql_jafinvestments.com', 'string', 'general', 'Email principal de la empresa', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(3, 'company_phone', '+1 (809) 555-0100', 'string', 'general', 'Teléfono principal', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(4, 'company_address', 'Avenida Principal 123, Santo Domingo, República Dominicana', 'string', 'general', 'Dirección de la empresa', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(5, 'default_currency', 'USD', 'string', 'general', 'Moneda predeterminada', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(6, 'default_language', 'en', 'string', 'general', 'Idioma predeterminado', 0, '2025-10-02 23:31:37', '2025-10-16 19:18:15'),
(7, 'properties_per_page', '12', 'integer', 'website', 'Propiedades por página en el sitio web', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(8, 'enable_property_views_tracking', 'true', 'boolean', 'features', 'Habilitar seguimiento de vistas', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(9, 'enable_email_notifications', 'true', 'boolean', 'notifications', 'Habilitar notificaciones por email', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(10, 'google_maps_api_key', 'AIzaSyB--Uo9OmdphFGGcJJIkY4R9lVg1xVnZOg', 'string', 'integrations', 'API Key de Google Maps', 0, '2025-10-02 23:31:37', '2025-10-09 04:36:55'),
(11, 'facebook_url', '', 'string', 'social', 'URL de Facebook', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(12, 'instagram_url', '', 'string', 'social', 'URL de Instagram', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(13, 'twitter_url', '', 'string', 'social', 'URL de Twitter', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(14, 'linkedin_url', '', 'string', 'social', 'URL de LinkedIn', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(15, 'youtube_url', '', 'string', 'social', 'URL de YouTube', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(16, 'whatsapp_number', '', 'string', 'social', 'Número de WhatsApp Business', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(17, 'timezone', 'America/Santo_Domingo', 'string', 'general', 'Zona horaria', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(18, 'date_format', 'd/m/Y', 'string', 'general', 'Formato de fecha', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37'),
(19, 'time_format', 'H:i', 'string', 'general', 'Formato de hora', 0, '2025-10-02 23:31:37', '2025-10-02 23:31:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasks`
--

CREATE TABLE `tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `task_type` enum('call','meeting','visit','follow_up','administrative','email','other') NOT NULL,
  `priority` enum('high','medium','low') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `assigned_to` int(10) UNSIGNED NOT NULL,
  `related_client_id` int(10) UNSIGNED DEFAULT NULL,
  `related_property_id` int(10) UNSIGNED DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `reminder_minutes` int(10) UNSIGNED DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone_office` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(500) DEFAULT NULL,
  `document_id` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `office_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `position` varchar(150) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `commission_sale` decimal(5,2) DEFAULT 0.00,
  `commission_rent` decimal(5,2) DEFAULT 0.00,
  `biography` text DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `facebook_url` varchar(500) DEFAULT NULL,
  `twitter_url` varchar(500) DEFAULT NULL,
  `instagram_url` varchar(500) DEFAULT NULL,
  `whatsapp_business` varchar(50) DEFAULT NULL,
  `language` varchar(5) DEFAULT 'en',
  `show_in_public_website` tinyint(1) DEFAULT 0,
  `total_sales` int(10) UNSIGNED DEFAULT 0,
  `total_rentals` int(10) UNSIGNED DEFAULT 0,
  `total_transaction_value` decimal(15,2) DEFAULT 0.00,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `phone`, `phone_office`, `profile_picture`, `document_id`, `date_of_birth`, `address`, `role_id`, `office_id`, `status`, `position`, `specialization`, `license_number`, `commission_sale`, `commission_rent`, `biography`, `linkedin_url`, `facebook_url`, `twitter_url`, `instagram_url`, `whatsapp_business`, `language`, `show_in_public_website`, `total_sales`, `total_rentals`, `total_transaction_value`, `average_rating`, `email_verified_at`, `remember_token`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'orthiis1982@gmail.com', '$2y$10$MtBzI1F931yaEFKAZddVMui.KML5Nw2VBGgXrBj9bZ0bbl85bdoJa', 'Miguel Angel', 'Ortega', '+1 (809) 555-0101', '', NULL, NULL, NULL, NULL, 1, 1, 'active', NULL, NULL, NULL, 0.00, 0.00, '', '', '', '', '', '', 'es', 0, 0, 0, 0.00, 0.00, NULL, NULL, '2025-11-04 11:26:51', '2025-10-02 23:31:37', '2025-11-04 10:26:51'),
(2, 'seller', 'seller@gmail.com', '$2y$10$MtBzI1F931yaEFKAZddVMui.KML5Nw2VBGgXrBj9bZ0bbl85bdoJa', 'Seller', '', '809-555-5555', NULL, NULL, NULL, NULL, NULL, 2, 1, 'active', '', '', NULL, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, 'en', 0, 0, 0, 0.00, 0.00, NULL, NULL, NULL, '2025-10-03 04:37:33', '2025-10-17 15:38:20'),
(3, 'mahler', 'mahlerlb@gmail.com', '$2y$10$MtBzI1F931yaEFKAZddVMui.KML5Nw2VBGgXrBj9bZ0bbl85bdoJa', 'Mahler', 'Bonifacio', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 'active', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, 'es', 0, 0, 0, 0.00, 0.00, NULL, NULL, '2026-04-09 00:54:35', '2025-10-04 11:52:49', '2026-04-09 00:54:35'),
(4, 'recepcionista', 'recepcionista@gmail.com', '$2y$10$MtBzI1F931yaEFKAZddVMui.KML5Nw2VBGgXrBj9bZ0bbl85bdoJa', 'Recepcionista', '', NULL, NULL, NULL, NULL, NULL, NULL, 5, 1, 'active', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, 'en', 0, 0, 0, 0.00, 0.00, NULL, NULL, '2025-10-12 13:45:23', '2025-10-04 14:36:02', '2025-10-17 15:39:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `website_visitors`
--

CREATE TABLE `website_visitors` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `device` varchar(100) DEFAULT NULL,
  `operating_system` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `landing_page` varchar(500) DEFAULT NULL,
  `pages_visited` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pages_visited`)),
  `total_pages` int(10) UNSIGNED DEFAULT 1,
  `time_spent` int(10) UNSIGNED DEFAULT NULL COMMENT 'Tiempo en segundos',
  `first_visit` timestamp NULL DEFAULT current_timestamp(),
  `last_visit` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `work_attendance`
--

CREATE TABLE `work_attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `contractor_id` int(10) UNSIGNED NOT NULL,
  `work_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `total_hours` decimal(4,2) DEFAULT NULL COMMENT 'calculado',
  `break_time` decimal(4,2) DEFAULT 0.00 COMMENT 'horas de descanso',
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `daily_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_published` (`published_at`),
  ADD KEY `idx_featured` (`is_featured`);

--
-- Indices de la tabla `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_client_id` (`related_client_id`),
  ADD KEY `related_property_id` (`related_property_id`),
  ADD KEY `idx_start` (`start_datetime`),
  ADD KEY `idx_end` (`end_datetime`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`event_type`);

--
-- Indices de la tabla `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_city_name` (`name`,`country`);

--
-- Indices de la tabla `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_client_type` (`client_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_clients_portal` (`portal_active`,`email`),
  ADD KEY `idx_clients_payment_day` (`payment_day`);

--
-- Indices de la tabla `client_favorite_properties`
--
ALTER TABLE `client_favorite_properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_client_property` (`client_id`,`property_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_property` (`property_id`);

--
-- Indices de la tabla `client_interactions`
--
ALTER TABLE `client_interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_date` (`interaction_date`),
  ADD KEY `idx_type` (`interaction_type`);

--
-- Indices de la tabla `client_property_comments`
--
ALTER TABLE `client_property_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_property` (`client_id`,`property_id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_cpc_property` (`property_id`);

--
-- Indices de la tabla `client_property_documents`
--
ALTER TABLE `client_property_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_transaction` (`transaction_id`);

--
-- Indices de la tabla `contractors`
--
ALTER TABLE `contractors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`contractor_type`),
  ADD KEY `idx_availability` (`availability`);

--
-- Indices de la tabla `contractor_documents`
--
ALTER TABLE `contractor_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contractor` (`contractor_id`);

--
-- Indices de la tabla `contractor_evaluations`
--
ALTER TABLE `contractor_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_contractor` (`contractor_id`);

--
-- Indices de la tabla `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_related_entity` (`related_entity_type`,`related_entity_id`),
  ADD KEY `idx_parent` (`parent_document_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_folder` (`folder_id`),
  ADD KEY `idx_documents_starred` (`is_starred`),
  ADD KEY `idx_documents_shared` (`is_shared`),
  ADD KEY `idx_documents_city` (`city_id`),
  ADD KEY `idx_documents_property` (`property_id`);

--
-- Indices de la tabla `document_activity`
--
ALTER TABLE `document_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `document_categories`
--
ALTER TABLE `document_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_parent` (`parent_id`);

--
-- Indices de la tabla `document_comments`
--
ALTER TABLE `document_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_parent` (`parent_comment_id`);

--
-- Indices de la tabla `document_folders`
--
ALTER TABLE `document_folders`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `document_permissions`
--
ALTER TABLE `document_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_role` (`role_id`),
  ADD KEY `idx_granted_by` (`granted_by`);

--
-- Indices de la tabla `document_shares`
--
ALTER TABLE `document_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_shared_by` (`shared_by`),
  ADD KEY `idx_email` (`shared_with_email`);

--
-- Indices de la tabla `document_tracking`
--
ALTER TABLE `document_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`);

--
-- Indices de la tabla `document_versions`
--
ALTER TABLE `document_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`);

--
-- Indices de la tabla `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `idx_to_email` (`to_email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sent` (`sent_at`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_name` (`template_name`),
  ADD KEY `idx_name` (`template_name`),
  ADD KEY `idx_type` (`template_type`);

--
-- Indices de la tabla `expense_attachments`
--
ALTER TABLE `expense_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expense` (`expense_id`);

--
-- Indices de la tabla `expense_types`
--
ALTER TABLE `expense_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_type_name` (`type_name`);

--
-- Indices de la tabla `features`
--
ALTER TABLE `features`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indices de la tabla `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_email` (`email`);

--
-- Indices de la tabla `inquiry_responses`
--
ALTER TABLE `inquiry_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inquiry_id` (`inquiry_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_material_type` (`material_type_id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_movement_date` (`movement_date`);

--
-- Indices de la tabla `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD UNIQUE KEY `unique_period` (`transaction_id`,`period_year`,`period_month`),
  ADD UNIQUE KEY `unique_monthly_invoice` (`client_id`,`property_id`,`transaction_id`,`period_month`,`period_year`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `fk_invoice_agent` (`agent_id`),
  ADD KEY `fk_invoice_created_by` (`created_by`),
  ADD KEY `idx_invoices_recurring` (`is_recurring`,`next_invoice_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_invoice_type` (`invoice_type`),
  ADD KEY `idx_period` (`period_month`,`period_year`),
  ADD KEY `idx_client_status` (`client_id`,`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_billing_period` (`billing_period`),
  ADD KEY `idx_payment_day` (`payment_day`),
  ADD KEY `idx_period_display` (`period_display`);

--
-- Indices de la tabla `invoice_generation_log`
--
ALTER TABLE `invoice_generation_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_generation_type` (`generation_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice` (`invoice_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `fk_ip_created_by` (`created_by`),
  ADD KEY `idx_payment_method` (`payment_method`);

--
-- Indices de la tabla `material_inventory`
--
ALTER TABLE `material_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_material_type` (`material_type_id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indices de la tabla `material_types`
--
ALTER TABLE `material_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_material_name` (`material_name`),
  ADD KEY `idx_category` (`category`);

--
-- Indices de la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_type` (`notification_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indices de la tabla `phase_tasks`
--
ALTER TABLE `phase_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phase` (`phase_id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_depends` (`depends_on`);

--
-- Indices de la tabla `project_contractors`
--
ALTER TABLE `project_contractors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_contractor` (`contractor_id`),
  ADD KEY `idx_phase` (`phase_id`);

--
-- Indices de la tabla `project_documents`
--
ALTER TABLE `project_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_category` (`document_category`),
  ADD KEY `idx_phase` (`phase_id`);

--
-- Indices de la tabla `project_expenses`
--
ALTER TABLE `project_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_expense_type` (`expense_type_id`),
  ADD KEY `idx_material_type` (`material_type_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_phase` (`phase_id`),
  ADD KEY `idx_expense_date` (`expense_date`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indices de la tabla `project_phases`
--
ALTER TABLE `project_phases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_assigned` (`assigned_to`);

--
-- Indices de la tabla `project_photos`
--
ALTER TABLE `project_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_category` (`photo_category`),
  ADD KEY `idx_phase` (`phase_id`);

--
-- Indices de la tabla `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `second_agent_id` (`second_agent_id`),
  ADD KEY `office_id` (`office_id`),
  ADD KEY `idx_reference` (`reference`),
  ADD KEY `idx_type` (`property_type_id`),
  ADD KEY `idx_operation` (`operation_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_bedrooms` (`bedrooms`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_featured` (`featured`),
  ADD KEY `idx_published` (`publish_on_website`),
  ADD KEY `idx_location` (`city`,`zone`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indices de la tabla `property_alerts`
--
ALTER TABLE `property_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_type_id` (`property_type_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_frequency` (`frequency`);

--
-- Indices de la tabla `property_documents`
--
ALTER TABLE `property_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_property` (`property_id`);

--
-- Indices de la tabla `property_features`
--
ALTER TABLE `property_features`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_property_feature` (`property_id`,`feature_id`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_feature` (`feature_id`);

--
-- Indices de la tabla `property_images`
--
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_main` (`is_main`),
  ADD KEY `idx_order` (`display_order`);

--
-- Indices de la tabla `property_owners`
--
ALTER TABLE `property_owners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_email` (`email`);

--
-- Indices de la tabla `property_types`
--
ALTER TABLE `property_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_order` (`display_order`);

--
-- Indices de la tabla `property_videos`
--
ALTER TABLE `property_videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_property` (`property_id`);

--
-- Indices de la tabla `property_views`
--
ALTER TABLE `property_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`);

--
-- Indices de la tabla `restoration_projects`
--
ALTER TABLE `restoration_projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_project_reference` (`project_reference`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`project_status`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`);

--
-- Indices de la tabla `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_code` (`transaction_code`),
  ADD KEY `second_agent_id` (`second_agent_id`),
  ADD KEY `idx_transaction_code` (`transaction_code`),
  ADD KEY `idx_property` (`property_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`transaction_type`),
  ADD KEY `idx_date` (`closing_date`),
  ADD KEY `idx_financing` (`financing_type`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_office` (`office_id`),
  ADD KEY `cancelled_by` (`cancelled_by`),
  ADD KEY `completed_by` (`completed_by`),
  ADD KEY `idx_transactions_type_status` (`transaction_type`,`status`),
  ADD KEY `idx_has_pending_invoice` (`has_pending_invoice`),
  ADD KEY `idx_next_invoice_date` (`next_invoice_date`),
  ADD KEY `idx_rent_payment_day` (`rent_payment_day`);

--
-- Indices de la tabla `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_date` (`payment_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indices de la tabla `sale_timeline`
--
ALTER TABLE `sale_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_type` (`event_type`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`),
  ADD KEY `idx_category` (`category`);

--
-- Indices de la tabla `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_client_id` (`related_client_id`),
  ADD KEY `related_property_id` (`related_property_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_type` (`task_type`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_office` (`office_id`),
  ADD KEY `idx_language` (`language`);

--
-- Indices de la tabla `website_visitors`
--
ALTER TABLE `website_visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_first_visit` (`first_visit`);

--
-- Indices de la tabla `work_attendance`
--
ALTER TABLE `work_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_contractor` (`contractor_id`),
  ADD KEY `idx_work_date` (`work_date`),
  ADD KEY `idx_phase` (`phase_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT de la tabla `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `client_favorite_properties`
--
ALTER TABLE `client_favorite_properties`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `client_interactions`
--
ALTER TABLE `client_interactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `client_property_comments`
--
ALTER TABLE `client_property_comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `client_property_documents`
--
ALTER TABLE `client_property_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `contractors`
--
ALTER TABLE `contractors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contractor_documents`
--
ALTER TABLE `contractor_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contractor_evaluations`
--
ALTER TABLE `contractor_evaluations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `document_activity`
--
ALTER TABLE `document_activity`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `document_comments`
--
ALTER TABLE `document_comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `document_folders`
--
ALTER TABLE `document_folders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `document_permissions`
--
ALTER TABLE `document_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `document_shares`
--
ALTER TABLE `document_shares`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `document_tracking`
--
ALTER TABLE `document_tracking`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `document_versions`
--
ALTER TABLE `document_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `expense_attachments`
--
ALTER TABLE `expense_attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `expense_types`
--
ALTER TABLE `expense_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `features`
--
ALTER TABLE `features`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `inquiry_responses`
--
ALTER TABLE `inquiry_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT de la tabla `invoice_generation_log`
--
ALTER TABLE `invoice_generation_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT de la tabla `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `material_inventory`
--
ALTER TABLE `material_inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `material_types`
--
ALTER TABLE `material_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de la tabla `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `offices`
--
ALTER TABLE `offices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `phase_tasks`
--
ALTER TABLE `phase_tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `project_contractors`
--
ALTER TABLE `project_contractors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `project_documents`
--
ALTER TABLE `project_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `project_expenses`
--
ALTER TABLE `project_expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `project_phases`
--
ALTER TABLE `project_phases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `project_photos`
--
ALTER TABLE `project_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de la tabla `property_alerts`
--
ALTER TABLE `property_alerts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `property_documents`
--
ALTER TABLE `property_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `property_features`
--
ALTER TABLE `property_features`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=353;

--
-- AUTO_INCREMENT de la tabla `property_images`
--
ALTER TABLE `property_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT de la tabla `property_owners`
--
ALTER TABLE `property_owners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `property_types`
--
ALTER TABLE `property_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `property_videos`
--
ALTER TABLE `property_videos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `property_views`
--
ALTER TABLE `property_views`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `restoration_projects`
--
ALTER TABLE `restoration_projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `sales_transactions`
--
ALTER TABLE `sales_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT de la tabla `sale_payments`
--
ALTER TABLE `sale_payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `sale_timeline`
--
ALTER TABLE `sale_timeline`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `website_visitors`
--
ALTER TABLE `website_visitors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `work_attendance`
--
ALTER TABLE `work_attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`related_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `calendar_events_ibfk_2` FOREIGN KEY (`related_property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `calendar_events_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `client_favorite_properties`
--
ALTER TABLE `client_favorite_properties`
  ADD CONSTRAINT `client_favorite_properties_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `client_favorite_properties_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `client_interactions`
--
ALTER TABLE `client_interactions`
  ADD CONSTRAINT `client_interactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `client_interactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `client_interactions_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `client_property_comments`
--
ALTER TABLE `client_property_comments`
  ADD CONSTRAINT `fk_cpc_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpc_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpc_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cpc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `client_property_documents`
--
ALTER TABLE `client_property_documents`
  ADD CONSTRAINT `fk_cpd_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpd_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cpd_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_doc_category` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_doc_parent` FOREIGN KEY (`parent_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_doc_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documents_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_documents_folder` FOREIGN KEY (`folder_id`) REFERENCES `document_folders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_documents_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `document_activity`
--
ALTER TABLE `document_activity`
  ADD CONSTRAINT `fk_activity_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `document_comments`
--
ALTER TABLE `document_comments`
  ADD CONSTRAINT `fk_comment_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comment_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `document_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `document_permissions`
--
ALTER TABLE `document_permissions`
  ADD CONSTRAINT `fk_perm_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perm_granter` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_perm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `document_shares`
--
ALTER TABLE `document_shares`
  ADD CONSTRAINT `fk_share_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_share_user` FOREIGN KEY (`shared_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `inquiries_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `inquiries_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `inquiries_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `inquiry_responses`
--
ALTER TABLE `inquiry_responses`
  ADD CONSTRAINT `inquiry_responses_ibfk_1` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inquiry_responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoice_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoice_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoice_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoice_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_invoice_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD CONSTRAINT `fk_ip_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ip_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`property_type_id`) REFERENCES `property_types` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `properties_ibfk_2` FOREIGN KEY (`owner_id`) REFERENCES `property_owners` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `properties_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `properties_ibfk_4` FOREIGN KEY (`second_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `properties_ibfk_5` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `property_alerts`
--
ALTER TABLE `property_alerts`
  ADD CONSTRAINT `property_alerts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `property_alerts_ibfk_2` FOREIGN KEY (`property_type_id`) REFERENCES `property_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `property_documents`
--
ALTER TABLE `property_documents`
  ADD CONSTRAINT `property_documents_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `property_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `property_features`
--
ALTER TABLE `property_features`
  ADD CONSTRAINT `property_features_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `property_features_ibfk_2` FOREIGN KEY (`feature_id`) REFERENCES `features` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `property_images`
--
ALTER TABLE `property_images`
  ADD CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `property_videos`
--
ALTER TABLE `property_videos`
  ADD CONSTRAINT `property_videos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `property_views`
--
ALTER TABLE `property_views`
  ADD CONSTRAINT `property_views_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `property_views_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `property_views_ibfk_3` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `sales_transactions`
--
ALTER TABLE `sales_transactions`
  ADD CONSTRAINT `sales_transactions_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_transactions_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_transactions_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_transactions_ibfk_4` FOREIGN KEY (`second_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_transactions_ibfk_5` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_transactions_ibfk_6` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_transactions_ibfk_7` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `sale_payments`
--
ALTER TABLE `sale_payments`
  ADD CONSTRAINT `sale_payments_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sale_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `sale_timeline`
--
ALTER TABLE `sale_timeline`
  ADD CONSTRAINT `sale_timeline_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sales_transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sale_timeline_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`related_client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`related_property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

DELIMITER $$
--
-- Eventos
--
CREATE EVENT `update_overdue_invoices` ON SCHEDULE EVERY 1 DAY STARTS '2025-10-23 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE `invoices` 
    SET `status` = 'overdue' 
    WHERE `status` = 'pending' 
    AND `due_date` < CURDATE();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

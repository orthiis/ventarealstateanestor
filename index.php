<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Obtener propiedades destacadas (Featured = 1)
$featuredProperties = db()->select(
    "SELECT p.*, pt.name as type_name,
     (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image
     FROM properties p
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     WHERE p.publish_on_website = 1 AND p.status = 'available' AND p.featured = 1
     ORDER BY p.created_at DESC
     LIMIT 8"
);

// Obtener agentes destacados
$agents = db()->select(
    "SELECT u.id, u.first_name, u.last_name, u.profile_picture, u.phone, u.email,
     r.display_name as position,
     (SELECT COUNT(*) FROM properties WHERE agent_id = u.id AND status != 'deleted') as properties_count,
     (SELECT COUNT(*) FROM sales_transactions WHERE agent_id = u.id AND status = 'completed') as sales_count
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     WHERE u.role_id IN (2, 3) AND u.status = 'active'
     ORDER BY sales_count DESC, properties_count DESC
     LIMIT 6"
);

// Obtener tipos de propiedad para el buscador
$propertyTypes = db()->select("SELECT * FROM property_types WHERE is_active = 1 ORDER BY display_order");

// Obtener ciudades disponibles
$cities = db()->select("SELECT DISTINCT city FROM properties WHERE publish_on_website = 1 AND status = 'available' ORDER BY city");

// Configuración de la empresa
$companyName = db()->selectOne("SELECT setting_value FROM system_settings WHERE setting_key = 'company_name'")['setting_value'] ?? 'Jaf Investments';
$companyPhone = db()->selectOne("SELECT setting_value FROM system_settings WHERE setting_key = 'company_phone'")['setting_value'] ?? '';
$companyEmail = db()->selectOne("SELECT setting_value FROM system_settings WHERE setting_key = 'company_email'")['setting_value'] ?? '';
$companyAddress = db()->selectOne("SELECT setting_value FROM system_settings WHERE setting_key = 'company_address'")['setting_value'] ?? '';

// Reseñas de clientes para el slider
$reviews = [
    ['name' => 'John Smith', 'text' => 'Excellent service! Found my dream home in just 2 weeks.', 'rating' => 5, 'avatar' => 'assets/images/avatar-1.jpg'],
    ['name' => 'Sarah Johnson', 'text' => 'Professional team, highly recommended for property investment.', 'rating' => 5, 'avatar' => 'assets/images/avatar-2.jpg'],
    ['name' => 'Michael Brown', 'text' => 'Best real estate experience I\'ve ever had. Thank you!', 'rating' => 5, 'avatar' => 'assets/images/avatar-3.jpg'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $companyName; ?> - Experience Real Estate Never Before">
    <title><?php echo $companyName; ?> - Real Estate Excellence</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <style>
        :root {
            --primary: #EF4444;
            --dark: #1F2937;
            --gray: #6B7280;
            --light-gray: #F9FAFB;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--dark);
            overflow-x: hidden;
        }

        /* ============ HEADER ============ */
        .top-bar {
            background: #111827;
            color: white;
            padding: 10px 0;
            font-size: 14px;
        }

        .top-bar a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
        }

        .navbar {
            background: rgba(0, 0, 0, 0.9) !important;
            backdrop-filter: blur(10px);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
        }
        
        .navbar-brand img {
            transition: all 0.3s;
        }
        
        .navbar-brand:hover img {
            transform: scale(1.05);
        }

        .navbar-nav .nav-link {
            color: white !important;
            margin: 0 15px;
            font-weight: 500;
            transition: color 0.3s;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary) !important;
        }

        .btn-add-listing {
            background: var(--primary);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }

        .btn-add-listing:hover {
            background: #DC2626;
            transform: translateY(-2px);
        }

        /* ============ HERO SECTION ============ */
        .hero-section {
            position: relative;
            height: 100vh;
            min-height: 700px;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), 
                        url('assets/images/hero-bg.jpg') center/cover;
            display: flex;
            align-items: center;
            color: white;
        }

        .hero-content h1 {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero-content h1 .text-danger {
            color: var(--primary) !important;
        }

        .btn-explore {
            background: var(--primary);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            border: none;
            font-size: 16px;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .btn-explore:hover {
            background: #DC2626;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        /* Trustpilot Reviews */
        .trustpilot-box {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
        }

        .trustpilot-avatars {
            display: flex;
        }

        .trustpilot-avatars img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 3px solid white;
            margin-left: -15px;
        }

        .trustpilot-avatars img:first-child {
            margin-left: 0;
        }

        .trustpilot-badge {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-radius: 12px;
        }

        .stars {
            color: #FFD700;
            font-size: 18px;
        }

        /* Video Play Button */
        .video-play-btn {
            position: absolute;
            top: 50%;
            right: 10%;
            transform: translateY(-50%);
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.7); }
            50% { box-shadow: 0 0 0 20px rgba(255,255,255,0); }
        }

        .video-play-btn i {
            font-size: 30px;
            color: var(--primary);
            margin-left: 5px;
        }

        .video-text {
            position: absolute;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* ============ SEARCH BOX ============ */
        .search-wrapper {
            position: relative;
            margin-top: -80px;
            z-index: 100;
        }

        .search-box {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            padding: 40px;
        }

        .search-tabs {
            border-bottom: 2px solid #E5E7EB;
            margin-bottom: 30px;
        }

        .search-tabs .nav-link {
            color: var(--gray);
            font-weight: 600;
            padding: 15px 30px;
            border: none;
            position: relative;
        }

        .search-tabs .nav-link.active {
            color: var(--primary);
            background: transparent;
        }

        .search-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
        }

        .search-box .form-select,
        .search-box .form-control {
            border: 1px solid #E5E7EB;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box .form-select:focus,
        .search-box .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .btn-search {
            background: var(--primary);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            width: 100%;
        }

        .btn-search:hover {
            background: #DC2626;
        }

        .advanced-search-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Price Range Slider */
        .price-range {
            padding: 10px 0;
        }

        .price-values {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: var(--dark);
        }

        /* ============ PROPERTIES SECTION ============ */
        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title .subtitle {
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
        }

        .property-filter-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 25px;
            border: 2px solid #E5E7EB;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-tab.active,
        .filter-tab:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .property-card {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            background: white;
            margin-bottom: 30px;
        }

        .property-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .property-image {
            position: relative;
            height: 280px;
            overflow: hidden;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .property-card:hover .property-image img {
            transform: scale(1.1);
        }

        .property-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--primary);
            color: white;
            padding: 6px 15px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }

        .property-actions {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
        }

        .property-info {
            padding: 25px;
        }

        .property-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .property-location {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .property-features {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px 0;
            border-top: 1px solid #E5E7EB;
            border-bottom: 1px solid #E5E7EB;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--gray);
            font-size: 14px;
        }

        .property-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .property-price {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
        }

        .btn-view-more {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view-more:hover {
            gap: 10px;
        }

        /* ============ WHY CHOOSE US ============ */
        .why-choose-section {
            background: var(--dark);
            color: white;
            padding: 100px 0;
        }

        .service-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
        }

        .service-card:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-10px);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .service-icon i {
            font-size: 32px;
            color: var(--primary);
        }

        .service-card h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .service-card p {
            color: #D1D5DB;
            margin-bottom: 20px;
        }

        .btn-service {
            border: 2px solid white;
            color: white;
            padding: 10px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-service:hover {
            background: white;
            color: var(--dark);
        }

        /* ============ AGENTS SECTION ============ */
        .agents-section {
            padding: 100px 0;
            background: var(--light-gray);
        }

        .agent-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .agent-card:hover {
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            transform: translateY(-10px);
        }

        .agent-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            overflow: hidden;
            border: 5px solid #F3F4F6;
        }

        .agent-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .agent-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .agent-position {
            color: var(--gray);
            margin-bottom: 20px;
        }

        .agent-social {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .social-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #E5E7EB;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.3s;
        }

        .social-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .swiper-button-next,
        .swiper-button-prev {
            color: var(--primary);
            background: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .swiper-button-next::after,
        .swiper-button-prev::after {
            font-size: 20px;
        }

        /* ============ CTA SECTION ============ */
        .cta-section {
            padding: 50px 0;
            background: var(--light-gray);
            text-align: center;
        }

        .cta-text {
            color: var(--gray);
            margin-bottom: 20px;
        }

        .btn-find-agent {
            background: var(--dark);
            color: white;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-find-agent:hover {
            background: var(--primary);
            color: white;
        }

        /* ============ FOOTER ============ */
        .footer {
            background: #0F172A;
            color: white;
            padding: 80px 0 30px;
        }

        .footer h5 {
            font-size: 18px;
            margin-bottom: 25px;
            font-weight: 700;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #94A3B8;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-about p {
            color: #94A3B8;
            line-height: 1.8;
        }

        .contact-info {
            margin-top: 20px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: #94A3B8;
        }

        .contact-item i {
            width: 40px;
            height: 40px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .instagram-gallery {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .instagram-gallery img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            transition: transform 0.3s;
        }

        .instagram-gallery img:hover {
            transform: scale(1.1);
        }

        .footer-map {
            border-radius: 12px;
            overflow: hidden;
            height: 200px;
        }

        .footer-map iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 50px;
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: var(--primary);
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 991px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }

            .video-play-btn {
                right: 50%;
                transform: translate(50%, -50%);
            }

            .search-box {
                padding: 30px 20px;
            }

            .navbar-collapse {
                background: rgba(0,0,0,0.95);
                padding: 20px;
                margin-top: 15px;
                border-radius: 10px;
            }
        }

        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2rem;
            }

            .section-title h2 {
                font-size: 1.8rem;
            }

            .property-filter-tabs {
                overflow-x: auto;
                justify-content: flex-start;
            }

            .instagram-gallery {
                grid-template-columns: repeat(3, 1fr);
            }

            .footer-bottom {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .trustpilot-box {
                flex-direction: column;
                align-items: flex-start;
            }

            .property-features {
                flex-direction: column;
                gap: 10px;
            }

            .instagram-gallery {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 1000;
        }

        .scroll-top.active {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            background: #DC2626;
        }
    </style>
</head>
<body>
    
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-envelope me-2"></i><?php echo $companyEmail; ?>
                    <i class="fas fa-phone ms-4 me-2"></i><?php echo $companyPhone; ?>
                </div>
                <div>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="ms-3"><i class="fas fa-globe me-2"></i>English</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.png" alt="Jaf Investments" style="max-height: 60px; width: auto;">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Home</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php">Home v1</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Property</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="propiedades-publicas.php">All Properties</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Agencies</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="agentes-publicos.php">Our Agents</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Pages</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">FAQ</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Blog</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Blog List</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact Us</a></li>
                </ul>
                
                <div class="d-flex gap-3 align-items-center">
                    <button class="btn-add-listing">
                        <i class="fas fa-plus-circle me-2"></i>Add Listing
                    </button>
                    <button class="btn btn-link text-white">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-7">
                    <div class="hero-content">
                        <h1>
                            Experience Real Estate <br>
                            <span class="text-danger">Never Before</span>
                        </h1>
                        
                        <button class="btn-explore">
                            Explore Property
                        </button>

                        <!-- Trustpilot Reviews -->
                        <div class="trustpilot-box">
                            <div class="d-flex align-items-center">
                                <img src="assets/images/trustpilot.png" alt="Trustpilot" style="height: 30px;">
                            </div>
                            
                            <div class="trustpilot-avatars">
                                <?php foreach(array_slice($reviews, 0, 3) as $review): ?>
                                    <img src="<?php echo $review['avatar']; ?>" alt="<?php echo $review['name']; ?>">
                                <?php endforeach; ?>
                                <div class="avatar-more" style="width: 45px; height: 45px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; border: 3px solid white; margin-left: -15px;">+89k</div>
                            </div>
                            
                            <div class="trustpilot-badge">
                                <div class="stars mb-1">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                </div>
                                <div style="font-size: 13px;">
                                    <strong>4.5</strong> - 19k+ clients
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Video Play Button -->
        <div class="video-play-btn" data-bs-toggle="modal" data-bs-target="#videoModal">
            <svg style="position: absolute; width: 100%; height: 100%;">
                <text x="50%" y="30%" text-anchor="middle" fill="currentColor" font-size="10" font-weight="600" letter-spacing="3">
                    <textPath href="#circlePath" startOffset="25%">
                        HOME • REAL ESTATE • 
                    </textPath>
                </text>
                <defs>
                    <path id="circlePath" d="M 60,60 m -40,0 a 40,40 0 1,1 80,0 a 40,40 0 1,1 -80,0"/>
                </defs>
            </svg>
            <i class="fas fa-play"></i>
        </div>

        <!-- Scroll Indicators -->
        <div style="position: absolute; right: 50px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 15px;">
            <div style="width: 10px; height: 10px; background: white; border-radius: 50%; opacity: 0.5;"></div>
            <div style="width: 10px; height: 30px; background: var(--primary); border-radius: 5px;"></div>
            <div style="width: 10px; height: 10px; background: white; border-radius: 50%; opacity: 0.5;"></div>
        </div>

        <!-- Pagination -->
        <div style="position: absolute; bottom: 50px; left: 50%; transform: translateX(-50%); color: white; font-size: 18px; font-weight: 600;">
            <span style="color: var(--primary);">01</span> <span style="opacity: 0.5;">/ 03</span>
        </div>

        <div style="position: absolute; bottom: 50px; right: 50px; display: flex; gap: 20px; color: white;">
            <button style="background: none; border: none; color: white; font-weight: 600; cursor: pointer;">prev</button>
            <button style="background: none; border: none; color: var(--primary); font-weight: 600; cursor: pointer;">Next</button>
        </div>
    </section>

    <!-- Search Box -->
    <div class="search-wrapper">
        <div class="container">
            <div class="search-box">
                <!-- Tabs -->
                <ul class="nav nav-tabs search-tabs border-0">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#buy">Buy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#sell">Sell</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#rent">Rent</a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Buy Tab -->
                    <div class="tab-pane fade show active" id="buy">
                        <form action="propiedades-publicas.php" method="GET">
                            <input type="hidden" name="operation" value="sale">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Property Type</label>
                                    <select name="type" class="form-select">
                                        <option value="">Select Property Type</option>
                                        <?php foreach($propertyTypes as $type): ?>
                                            <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Room Type</label>
                                    <select name="bedrooms" class="form-select">
                                        <option value="">Select Room Type</option>
                                        <option value="1">1 Bedroom</option>
                                        <option value="2">2 Bedrooms</option>
                                        <option value="3">3 Bedrooms</option>
                                        <option value="4">4+ Bedrooms</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Min Area (SqFt)</label>
                                    <select name="area_min" class="form-select">
                                        <option value="">Select Min Area</option>
                                        <option value="500">500 sq ft</option>
                                        <option value="1000">1000 sq ft</option>
                                        <option value="1500">1500 sq ft</option>
                                        <option value="2000">2000 sq ft</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Max Area (SqFt)</label>
                                    <select name="area_max" class="form-select">
                                        <option value="">Select Max Area</option>
                                        <option value="1000">1000 sq ft</option>
                                        <option value="2000">2000 sq ft</option>
                                        <option value="3000">3000 sq ft</option>
                                        <option value="5000">5000+ sq ft</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Max of Bedrooms</label>
                                    <select name="bedrooms_max" class="form-select">
                                        <option value="">Select Max Bedrooms</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                        <option value="5">5+</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Max of Bathrooms</label>
                                    <select name="bathrooms_max" class="form-select">
                                        <option value="">Select Max Bathrooms</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4+</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Location</label>
                                    <select name="city" class="form-select">
                                        <option value="">Select Location</option>
                                        <?php foreach($cities as $city): ?>
                                            <option value="<?php echo $city['city']; ?>"><?php echo $city['city']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Price Range</label>
                                    <div class="price-range">
                                        <div class="price-values">
                                            <span>$125000</span>
                                            <span class="text-muted">-</span>
                                            <span>$825000</span>
                                        </div>
                                        <input type="range" class="form-range" min="0" max="1000000" value="500000">
                                    </div>
                                </div>

                                <div class="col-12 d-flex justify-content-between align-items-center">
                                    <a href="#" class="advanced-search-link">
                                        Advanced Search <i class="fas fa-sliders-h"></i>
                                    </a>
                                    <button type="submit" class="btn-search" style="width: auto; padding: 12px 50px;">
                                        <i class="fas fa-search me-2"></i> Search Property
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Sell Tab -->
                    <div class="tab-pane fade" id="sell">
                        <div class="text-center py-5">
                            <i class="fas fa-home text-danger" style="font-size: 48px;"></i>
                            <h4 class="mt-3">Want to Sell Your Property?</h4>
                            <p class="text-muted">Contact us to get the best price for your property</p>
                            <a href="contacto.php" class="btn-search" style="display: inline-block; width: auto; padding: 12px 40px;">Contact Us Now</a>
                        </div>
                    </div>

                    <!-- Rent Tab -->
                    <div class="tab-pane fade" id="rent">
                        <form action="propiedades-publicas.php" method="GET">
                            <input type="hidden" name="operation" value="rent">
                            <!-- Same form fields as Buy tab -->
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Property Type</label>
                                    <select name="type" class="form-select">
                                        <option value="">All Types</option>
                                        <?php foreach($propertyTypes as $type): ?>
                                            <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Location</label>
                                    <select name="city" class="form-select">
                                        <option value="">All Locations</option>
                                        <?php foreach($cities as $city): ?>
                                            <option value="<?php echo $city['city']; ?>"><?php echo $city['city']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Bedrooms</label>
                                    <select name="bedrooms" class="form-select">
                                        <option value="">Any</option>
                                        <option value="1">1+</option>
                                        <option value="2">2+</option>
                                        <option value="3">3+</option>
                                        <option value="4">4+</option>
                                    </select>
                                </div>

                                <div class="col-12 text-end">
                                    <button type="submit" class="btn-search" style="width: auto; padding: 12px 50px;">
                                        <i class="fas fa-search me-2"></i> Search Property
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Properties Section -->
    <section class="py-5 mt-5">
        <div class="container">
            <div class="section-title">
                <div class="subtitle">Popular Properties</div>
                <h2>Best Properties Sale</h2>
            </div>

            <!-- Filter Tabs -->
            <div class="property-filter-tabs">
                <button class="filter-tab active" data-filter="all">View All</button>
                <button class="filter-tab" data-filter="apartment">Apartment</button>
                <button class="filter-tab" data-filter="commercial">Commercial</button>
                <button class="filter-tab" data-filter="land">Land Or Plot</button>
                <button class="filter-tab" data-filter="farm">Farm</button>
            </div>

            <!-- Properties Grid -->
            <div class="row">
                <?php foreach($featuredProperties as $property): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="property-card">
                        <div class="property-image">
                            <img src="<?php echo $property['main_image'] ?? 'assets/images/no-image.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($property['title']); ?>">
                            
                            <div class="property-badge">
                                For <?php echo $property['operation_type'] === 'sale' ? 'Sale' : 'Rent'; ?>
                            </div>

                            <div class="property-actions">
                                <div class="action-btn">
                                    <i class="far fa-heart"></i>
                                </div>
                                <div class="action-btn">
                                    <i class="fas fa-share-alt"></i>
                                </div>
                            </div>
                        </div>

                        <div class="property-info">
                            <h3 class="property-title">
                                <?php echo truncate($property['title'], 40); ?>
                            </h3>

                            <div class="property-location">
                                <i class="fas fa-map-marker-alt text-danger"></i>
                                <?php echo htmlspecialchars($property['city']); ?>
                            </div>

                            <div class="property-features">
                                <div class="feature-item">
                                    <i class="fas fa-bed"></i>
                                    Bed <?php echo $property['bedrooms']; ?>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-bath"></i>
                                    Bath <?php echo $property['bathrooms']; ?>
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-ruler-combined"></i>
                                    <?php echo $property['built_area']; ?> sqft
                                </div>
                            </div>

                            <div class="property-footer">
                                <div class="property-price">
                                    <?php echo formatPrice($property['price'], $property['currency']); ?>
                                </div>
                                <a href="detalle-propiedad.php?id=<?php echo $property['id']; ?>" class="btn-view-more">
                                    View More <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="why-choose-section">
        <div class="container">
            <div class="row align-items-center mb-5">
                <div class="col-lg-8">
                    <div class="subtitle" style="color: var(--primary);">Why Choose Us</div>
                    <h2 class="display-4 fw-bold">Trusted By 100+ Million Buyers</h2>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="d-inline-flex align-items-center gap-3">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/a/a2/Trustpilot_Logo_%282022%29.svg" alt="Trustpilot" style="height: 35px;">
                        <div class="trustpilot-avatars">
                            <img src="assets/images/avatar-1.jpg" alt="">
                            <img src="assets/images/avatar-2.jpg" alt="">
                            <img src="assets/images/avatar-3.jpg" alt="">
                            <div class="avatar-more" style="width: 45px; height: 45px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; border: 3px solid white; margin-left: -15px;">+89k</div>
                        </div>
                        <div>
                            <div class="stars" style="color: #FFD700;">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="mt-1">
                                <strong>4.5</strong> - 19k+ clients
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <h3>Find your home</h3>
                        <p>Altus cedo tantillus video patrocinor valeo carus subseco vestrum credo virtus.</p>
                        <a href="propiedades-publicas.php" class="btn-service">Find A Home</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h3>Sell a Property</h3>
                        <p>Tantillus certe patrocinor video adipisci valeo carus. Subseco vestrum taedium.</p>
                        <a href="contacto.php" class="btn-service">Sell A Home</a>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <h3>Rent a Home</h3>
                        <p>Velox surgo clarus tantillus confido carus video lumen cedo virtus spes decerno.</p>
                        <a href="propiedades-publicas.php?operation=rent" class="btn-service">Rent A Home</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Agents Section -->
    <section class="agents-section">
        <div class="container">
            <div class="section-title">
                <div class="subtitle">Team Members</div>
                <h2>Meet Our Pillar Agents</h2>
            </div>

            <div class="swiper agentsSwiper">
                <div class="swiper-wrapper">
                    <?php foreach($agents as $agent): ?>
                    <div class="swiper-slide">
                        <div class="agent-card">
                            <div class="agent-avatar">
                                <img src="<?php echo $agent['profile_picture'] ?? 'assets/images/default-avatar.png'; ?>" 
                                     alt="<?php echo $agent['first_name']; ?>">
                            </div>
                            <h4 class="agent-name">
                                <?php echo $agent['first_name'] . ' ' . $agent['last_name']; ?>
                            </h4>
                            <div class="agent-position">
                                <?php echo $agent['position'] ?? 'Real Estate Agent'; ?>
                            </div>
                            <div class="agent-social">
                                <?php if($agent['facebook_url']): ?>
                                <a href="<?php echo $agent['facebook_url']; ?>" class="social-btn" target="_blank">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <?php endif; ?>
                                <?php if($agent['twitter_url']): ?>
                                <a href="<?php echo $agent['twitter_url']; ?>" class="social-btn" target="_blank">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <?php endif; ?>
                                <?php if($agent['instagram_url']): ?>
                                <a href="<?php echo $agent['instagram_url']; ?>" class="social-btn" target="_blank">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <?php endif; ?>
                                <?php if($agent['linkedin_url']): ?>
                                <a href="<?php echo $agent['linkedin_url']; ?>" class="social-btn" target="_blank">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>

            <!-- CTA Text -->
            <div class="cta-section">
                <p class="cta-text">
                    Explore Property agents are here to help with all your buying, renting and selling goals. 
                    Find the home of your dreams with an expert you can trust. <a href="#" style="color: var(--primary);">Let's chat</a>
                </p>
                <a href="agentes-publicos.php" class="btn-find-agent">
                    Find Your Location Agent <i class="fas fa-search"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <!-- About -->
                <div class="col-lg-3">
                    <h5>About Pillar</h5>
                    <div class="footer-about">
                        <p>Pillar is a luxury to the resilience, adaptability, Spacious modern villa living room with centrally placed swimming pool blending indooroutdoor.</p>
                    </div>

                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <div style="color: white; font-weight: 600;"><?php echo $companyPhone; ?></div>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <div style="color: white; font-weight: 600;"><?php echo $companyEmail; ?></div>
                            </div>
                        </div>

                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <div style="color: white; font-weight: 600;"><?php echo $companyAddress; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Featured Houses -->
                <div class="col-lg-2">
                    <h5>Featured Houses</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-home text-danger"></i> #Villa</a></li>
                        <li><a href="#"><i class="fas fa-building text-danger"></i> #Commercial</a></li>
                        <li><a href="#"><i class="fas fa-home text-danger"></i> #Farm Houses</a></li>
                        <li><a href="#"><i class="fas fa-door-open text-danger"></i> #Apartments</a></li>
                        <li><a href="#"><i class="fas fa-door-closed text-danger"></i> #Apartments</a></li>
                    </ul>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-2">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Strategy Services</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Management</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Privacy & Policy</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Sitemap</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Term & Conditions</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div class="col-lg-2">
                    <h5>Support</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Help Center</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> FAQs</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Contact Us</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Ticket Support</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right text-danger"></i> Live Chat</a></li>
                    </ul>
                </div>

                <!-- Location & Gallery -->
                <div class="col-lg-3">
                    <h5>Pillar Location</h5>
                    <div class="footer-map">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3784.3056437654067!2d-69.93315!3d18.48634!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTjCsDI5JzEwLjgiTiA2OcKwNTUnNTkuMyJX!5e0!3m2!1sen!2sdo!4v1234567890" allowfullscreen="" loading="lazy"></iframe>
                    </div>

                    <div class="mt-4">
                        <h5>@pillar on Instagram</h5>
                        <div class="subtitle" style="color: var(--primary); font-size: 12px; margin-bottom: 15px;">Nice Gallery</div>
                        <div class="instagram-gallery">
                            <?php 
                            $galleryImages = array_slice($featuredProperties, 0, 8);
                            foreach($galleryImages as $img): 
                            ?>
                                <img src="<?php echo $img['main_image'] ?? 'assets/images/no-image.jpg'; ?>" alt="Gallery">
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div>
                    <div class="mb-3">
                        <a href="index.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-home me-2" style="font-size: 24px; color: var(--primary);"></i>
                            <span style="font-size: 24px; font-weight: 700;">PILLER</span>
                            <div style="font-size: 12px; color: #94A3B8; margin-left: 36px;">Real Estate Solution</div>
                        </a>
                    </div>
                    <div style="color: #94A3B8;">
                        Copyright © 2025 <span style="color: var(--primary);">Pillar</span>. All Rights Reserved.
                    </div>
                </div>

                <div>
                    <div style="color: #94A3B8; margin-bottom: 15px;">Social Media:</div>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top -->
    <div class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Video Modal -->
    <div class="modal fade" id="videoModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-body p-0">
                    <button type="button" class="btn-close btn-close-white position-absolute end-0 me-3 mt-3" data-bs-dismiss="modal" style="z-index: 1;"></button>
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/gwLHR8oHAIU" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <script>
        // Agents Swiper
        const agentsSwiper = new Swiper('.agentsSwiper', {
            slidesPerView: 1,
            spaceBetween: 30,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: 2,
                },
                768: {
                    slidesPerView: 3,
                },
                1024: {
                    slidesPerView: 4,
                },
            }
        });

        // Property Filter Tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                // Aquí puedes agregar lógica de filtrado AJAX si lo necesitas
            });
        });

        // Scroll to Top
        const scrollTopBtn = document.getElementById('scrollTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('active');
            } else {
                scrollTopBtn.classList.remove('active');
            }
        });

        scrollTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Favorite Button
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (icon.classList.contains('far')) {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    this.style.background = 'var(--primary)';
                    this.style.color = 'white';
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    this.style.background = 'white';
                    this.style.color = '';
                }
            });
        });

        // Price Range Slider
        document.querySelectorAll('input[type="range"]').forEach(slider => {
            slider.addEventListener('input', function() {
                const parent = this.closest('.price-range');
                const values = parent.querySelectorAll('.price-values span');
                const max = parseInt(this.max);
                const value = parseInt(this.value);
                
                values[0].textContent = '$' + (value / 2).toLocaleString();
                values[2].textContent = '$' + value.toLocaleString();
            });
        });

        // Stop video when modal closes
        document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() {
            const iframe = this.querySelector('iframe');
            iframe.src = iframe.src;
        });
    </script>
</body>
</html>
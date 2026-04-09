<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Obtener ID de la propiedad
$propertyId = $_GET['id'] ?? null;

if (!$propertyId) {
    redirect('propiedades-publicas.php');
}

// Obtener datos de la propiedad
$property = db()->selectOne(
    "SELECT p.*, pt.name as type_name,
     CONCAT(u.first_name, ' ', u.last_name) as agent_name,
     u.phone as agent_phone, u.email as agent_email,
     u.profile_picture as agent_photo
     FROM properties p
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     LEFT JOIN users u ON p.agent_id = u.id
     WHERE p.id = ?",
    [$propertyId]
);

if (!$property) {
    redirect('propiedades-publicas.php');
}

// Obtener imágenes de la propiedad
$propertyImages = db()->select(
    "SELECT * FROM property_images 
     WHERE property_id = ? 
     ORDER BY is_main DESC, display_order ASC",
    [$propertyId]
);

// Obtener características de la propiedad
$propertyFeatures = db()->select(
    "SELECT f.* FROM features f
     INNER JOIN property_features pf ON f.id = pf.feature_id
     WHERE pf.property_id = ?
     ORDER BY f.category, f.display_order",
    [$propertyId]
);

// Agrupar características por categoría
$featuresByCategory = [];
foreach ($propertyFeatures as $feature) {
    $category = $feature['category'] ?? 'Other';
    $featuresByCategory[$category][] = $feature;
}

// Propiedades destacadas para el sidebar
$featuredListings = db()->select(
    "SELECT p.*, pt.name as type_name,
     (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image
     FROM properties p
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     WHERE p.publish_on_website = 1 AND p.status = 'available' AND p.id != ?
     ORDER BY p.featured DESC, p.created_at DESC
     LIMIT 4",
    [$propertyId]
);

// Incrementar contador de visitas
db()->insert('property_views', [
    'property_id' => $propertyId,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'viewed_at' => date('Y-m-d H:i:s')
]);

$pageTitle = $property['title'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($property['title']); ?> - Jaf Investments</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.1/css/lightgallery-bundle.min.css">
    
    <style>
        :root {
            --primary: #EF4444;
            --dark: #1F2937;
            --gray: #6B7280;
            --light-gray: #F9FAFB;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--dark);
        }

        /* Top Bar */
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

        /* Navbar */
        .navbar {
            background: white !important;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark) !important;
        }

        .navbar-nav .nav-link {
            color: var(--dark) !important;
            margin: 0 15px;
            font-weight: 500;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary) !important;
        }

        .btn-add-listing {
            background: var(--dark);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
        }

        /* Breadcrumb */
        .breadcrumb-section {
            background: var(--light-gray);
            padding: 30px 0;
        }

        .breadcrumb {
            background: transparent;
            margin: 0;
        }

        .breadcrumb-item a {
            color: var(--gray);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: var(--dark);
        }

        /* Main Gallery */
        .main-gallery {
            margin: 40px 0;
        }

        .main-image-container {
            position: relative;
            height: 500px;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .main-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: var(--primary);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
        }

        .gallery-thumbnails {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .thumbnail-item {
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
        }

        .thumbnail-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .thumbnail-item:hover img {
            transform: scale(1.1);
        }

        .thumbnail-item.view-all {
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Property Header */
        .property-header {
            margin: 40px 0;
        }

        .property-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
        }

        .meta-badge {
            background: var(--primary);
            color: white;
            padding: 6px 15px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .meta-date {
            color: var(--gray);
            font-size: 14px;
        }

        .property-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .property-location {
            color: var(--gray);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .property-price {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin: 20px 0;
        }

        .property-specs {
            display: flex;
            gap: 30px;
            padding: 20px 0;
            border-top: 2px solid #E5E7EB;
            border-bottom: 2px solid #E5E7EB;
        }

        .spec-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .spec-icon {
            width: 40px;
            height: 40px;
            background: #F3F4F6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .btn-favorite {
            background: white;
            border: 2px solid #E5E7EB;
            color: var(--gray);
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-favorite:hover,
        .btn-favorite.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Property Description */
        .property-description {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .property-description p {
            line-height: 1.8;
            color: var(--gray);
        }

        /* Property Highlights */
        .property-highlights {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .highlights-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .highlight-item {
            text-align: center;
            padding: 20px;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .highlight-item:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .highlight-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            background: #FEF2F2;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .highlight-icon i {
            font-size: 28px;
            color: var(--primary);
        }

        .highlight-label {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }

        .highlight-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }

        /* Gallery Section */
        .amazing-gallery {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .gallery-grid-item {
            height: 250px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .gallery-grid-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .gallery-grid-item:hover img {
            transform: scale(1.1);
        }

        .gallery-grid-item:first-child {
            grid-column: span 2;
            grid-row: span 2;
            height: 520px;
        }

        /* Features & Amenities */
        .features-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .feature-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
        }

        .feature-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }

        /* Floor Plan */
        .floor-plan-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .floor-plan-tabs {
            display: flex;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .floor-tab {
            padding: 10px 25px;
            border: 2px solid #E5E7EB;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .floor-tab.active,
        .floor-tab:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .floor-plan-content {
            display: flex;
            gap: 40px;
            margin-top: 30px;
        }

        .floor-plan-image {
            flex: 1;
        }

        .floor-plan-image img {
            width: 100%;
            border-radius: 12px;
        }

        .floor-plan-details {
            flex: 1;
        }

        /* Location Map */
        .location-section {
            background: white;
            border-radius: 16px;
            padding: 40px;
            margin: 30px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .map-container {
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 30px;
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Sidebar */
        .sidebar {
            position: sticky;
            top: 100px;
        }

        .sidebar-widget {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .sidebar-widget h4 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        /* Search Widget */
        .search-widget .input-group {
            background: var(--light-gray);
            border-radius: 8px;
            overflow: hidden;
        }

        .search-widget input {
            background: transparent;
            border: none;
            padding: 12px 16px;
        }

        .search-widget button {
            background: transparent;
            border: none;
            color: var(--gray);
        }

        /* Featured Listings Widget */
        .featured-listing-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #E5E7EB;
        }

        .featured-listing-item:last-child {
            border-bottom: none;
        }

        .featured-listing-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
        }

        .featured-listing-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .featured-listing-info {
            flex: 1;
        }

        .featured-listing-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
            margin-bottom: 5px;
        }

        .featured-listing-meta {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .featured-listing-price {
            color: var(--primary);
            font-weight: 700;
            font-size: 16px;
        }

        /* Contact Form Widget */
        .contact-form-widget .form-control {
            border: 1px solid #E5E7EB;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            padding: 12px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            width: 100%;
        }

        .btn-submit:hover {
            background: #DC2626;
        }

        /* Banner Widget */
        .banner-widget {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('assets/images/banner-bg.jpg') center/cover;
            color: white;
            text-align: center;
            padding: 60px 30px;
            border-radius: 16px;
        }

        .banner-widget h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .banner-widget p {
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .btn-banner {
            background: var(--primary);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
        }

        /* Footer */
        .footer {
            background: #0F172A;
            color: white;
            padding: 80px 0 30px;
            margin-top: 80px;
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
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 991px) {
            .highlights-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .gallery-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .floor-plan-content {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .property-title {
                font-size: 1.8rem;
            }

            .property-price {
                font-size: 2rem;
            }

            .property-specs {
                flex-direction: column;
                gap: 15px;
            }

            .highlights-grid,
            .features-grid {
                grid-template-columns: 1fr;
            }

            .gallery-grid {
                grid-template-columns: 1fr;
            }

            .gallery-grid-item:first-child {
                grid-column: span 1;
                grid-row: span 1;
                height: 250px;
            }

            .gallery-thumbnails {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-envelope me-2"></i>info@jafinvestments.com
                    <i class="fas fa-phone ms-4 me-2"></i>+1 (809) 456 789 012
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
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-home me-2 text-danger"></i>PILLER
                <div style="font-size: 12px; font-weight: 400;">Real Estate Solution</div>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="propiedades-publicas.php">Property</a></li>
                    <li class="nav-item"><a class="nav-link" href="agentes-publicos.php">Agencies</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pages">Pages</a></li>
                    <li class="nav-item"><a class="nav-link" href="#blog">Blog</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact Us</a></li>
                </ul>
                
                <div class="d-flex gap-3 align-items-center">
                    <button class="btn-add-listing">
                        <i class="fas fa-plus-circle me-2"></i>Add Listing
                    </button>
                    <button class="btn btn-link text-dark">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <section class="breadcrumb-section">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="propiedades-publicas.php">Property Listing</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($property['title']); ?></li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <div class="row">
            <!-- Main Column -->
            <div class="col-lg-8">
                <!-- Main Gallery -->
                <div class="main-gallery">
                    <div class="main-image-container" id="mainImage">
                        <?php 
                        $mainImage = $propertyImages[0]['image_url'] ?? 'assets/images/no-image.jpg';
                        ?>
                        <img src="<?php echo $mainImage; ?>" alt="<?php echo htmlspecialchars($property['title']); ?>">
                        <span class="gallery-badge">For <?php echo ucfirst($property['operation_type']); ?></span>
                    </div>

                    <div class="gallery-thumbnails">
                        <?php 
                        $displayImages = array_slice($propertyImages, 0, 3);
                        foreach($displayImages as $index => $image): 
                        ?>
                            <div class="thumbnail-item" onclick="changeMainImage('<?php echo $image['image_url']; ?>')">
                                <img src="<?php echo $image['image_url']; ?>" alt="Thumbnail <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if(count($propertyImages) > 4): ?>
                        <div class="thumbnail-item view-all" data-bs-toggle="modal" data-bs-target="#galleryModal">
                            <div>
                                <i class="fas fa-images fa-2x mb-2"></i>
                                <div>View All (<?php echo count($propertyImages); ?>)</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Property Header -->
                <div class="property-header">
                    <div class="property-meta">
                        <span class="meta-badge">Featured</span>
                        <span class="meta-date">
                            <i class="far fa-calendar me-2"></i>
                            <?php echo date('d M, Y', strtotime($property['created_at'])); ?>
                        </span>
                        <span class="meta-date">
                            <i class="far fa-comment me-2"></i>
                            No Comments
                        </span>
                        <button class="btn-favorite ms-auto">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>

                    <h1 class="property-title"><?php echo htmlspecialchars($property['title']); ?></h1>
                    
                    <div class="property-location">
                        <i class="fas fa-map-marker-alt text-danger"></i>
                        <?php echo htmlspecialchars($property['address'] . ', ' . $property['city']); ?>
                    </div>

                    <div class="property-price">
                        <?php echo formatPrice($property['price'], $property['currency']); ?>
                    </div>

                    <div class="property-specs">
                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700;">Bed <?php echo $property['bedrooms']; ?></div>
                            </div>
                        </div>

                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="fas fa-bath"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700;">Bath <?php echo $property['bathrooms']; ?></div>
                            </div>
                        </div>

                        <div class="spec-item">
                            <div class="spec-icon">
                                <i class="fas fa-ruler-combined"></i>
                            </div>
                            <div>
                                <div style="font-weight: 700;"><?php echo $property['built_area']; ?> sqft</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- About This Property -->
                <div class="property-description">
                    <h2 class="section-title">About This Property</h2>
                    <div>
                        <?php echo $property['description'] ?: 'No description available.'; ?>
                    </div>
                </div>

                <!-- Property Highlights -->
                <div class="property-highlights">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="section-title mb-0">Property Highlights</h2>
                        <span class="text-primary" style="cursor: pointer;">
                            <i class="fas fa-home me-2"></i>House for sale
                        </span>
                    </div>

                    <div class="highlights-grid">
                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="highlight-value">ATCH</div>
                            <div class="highlight-label">Property ID</div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="highlight-value">Type</div>
                            <div class="highlight-label"><?php echo $property['type_name']; ?></div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-bath"></i>
                            </div>
                            <div class="highlight-value">Baths</div>
                            <div class="highlight-label"><?php echo $property['bathrooms']; ?></div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            <div class="highlight-value">Bedroom</div>
                            <div class="highlight-label"><?php echo $property['bedrooms']; ?></div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="highlight-value">Parking</div>
                            <div class="highlight-label"><?php echo $property['garage_spaces'] ?: 'No'; ?></div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-square"></i>
                            </div>
                            <div class="highlight-value">HOA</div>
                            <div class="highlight-label">$250 Per Month</div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-swimming-pool"></i>
                            </div>
                            <div class="highlight-value">Swimming</div>
                            <div class="highlight-label">Pool</div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div class="highlight-value">Sq Yard</div>
                            <div class="highlight-label"><?php echo $property['plot_area'] ?: 'N/A'; ?></div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div class="highlight-value">Elevator</div>
                            <div class="highlight-label"><?php echo $property['elevator'] ? 'Yes' : 'No'; ?></div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-fire"></i>
                            </div>
                            <div class="highlight-value">Fireplace</div>
                            <div class="highlight-label">Yes</div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="highlight-value">Built In</div>
                            <div class="highlight-label"><?php echo $property['year_built'] ?: 'N/A'; ?></div>
                        </div>

                        <div class="highlight-item">
                            <div class="highlight-icon">
                                <i class="fas fa-ruler"></i>
                            </div>
                            <div class="highlight-value">Lot Size</div>
                            <div class="highlight-label"><?php echo $property['plot_area']; ?> sqft</div>
                        </div>
                    </div>
                </div>

                <!-- From Amazing Gallery -->
                <div class="amazing-gallery">
                    <h2 class="section-title">From Amazing Gallery</h2>
                    
                    <div class="gallery-grid" id="lightgallery">
                        <?php foreach(array_slice($propertyImages, 0, 6) as $image): ?>
                        <a href="<?php echo $image['image_url']; ?>" class="gallery-grid-item">
                            <img src="<?php echo $image['image_url']; ?>" alt="Gallery Image">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Features & Amenities -->
                <div class="features-section">
                    <h2 class="section-title">Features & amenities</h2>
                    
                    <div class="features-grid">
                        <?php 
                        $allFeatures = [
                            'Air conditioning', 'Barbecue', 'Dryer', 'Gym', 'Laundry', 
                            'Outdoor Shower', 'Barbeque', 'Lawn', 'Microwave', 'Refrigerator',
                            'Sauna', 'Swimming Pool', 'TV Cable', 'Washer', 'WiFi',
                            '24x7 Security', 'Basketball court', 'Fireplace', 'Library',
                            'Window Coverings', 'WiFi', 'Pets'
                        ];
                        
                        foreach($allFeatures as $feature):
                            $isChecked = false;
                            foreach($propertyFeatures as $pf) {
                                if(stripos($pf['name'], $feature) !== false) {
                                    $isChecked = true;
                                    break;
                                }
                            }
                        ?>
                        <div class="feature-checkbox">
                            <input type="checkbox" <?php echo $isChecked ? 'checked' : ''; ?> disabled>
                            <label><?php echo $feature; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Floor Plan -->
                <div class="floor-plan-section">
                    <h2 class="section-title">Floor Plan</h2>
                    
                    <div class="floor-plan-tabs">
                        <button class="floor-tab active">First Floor</button>
                        <button class="floor-tab">Second Floor</button>
                        <button class="floor-tab">Third Floor</button>
                        <button class="floor-tab">Top Garden</button>
                    </div>

                    <div class="floor-plan-content">
                        <div class="floor-plan-image">
                            <img src="assets/images/floor-plan.png" alt="Floor Plan">
                        </div>
                        <div class="floor-plan-details">
                            <h4>First Floor</h4>
                            <p class="text-muted">
                                Donec porttitor euismod dignissim. Nullam a lacinia ipsum, nec dignissim purus. 
                                Nulla convallis ipsum molestie nibh malesuada, ac malesuada leo volutpat.
                            </p>
                            <p class="text-muted mt-3">
                                Quisque eget tortor lobortis, facilisis metus eu, elementum est. Nunc sit amet 
                                erat quis ex convallis suscipit. Nam hendrerit, velit ut aliquam euismod, nibh 
                                tortor pellentesque.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="location-section">
                    <h2 class="section-title">Location</h2>
                    
                    <div class="map-container">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3784.3056437654067!2d<?php echo $property['longitude']; ?>!3d<?php echo $property['latitude']; ?>!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zM!5e0!3m2!1sen!2sdo!4v1234567890" 
                            allowfullscreen="" 
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar">
                    <!-- Search Widget -->
                    <div class="sidebar-widget search-widget">
                        <h4>Search</h4>
                        <form action="propiedades-publicas.php" method="GET">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Enter Keyword">
                                <button type="submit" class="btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Featured Listings -->
                    <div class="sidebar-widget">
                        <h4>Featured Listings</h4>
                        
                        <?php foreach($featuredListings as $listing): ?>
                        <div class="featured-listing-item">
                            <div class="featured-listing-image">
                                <img src="<?php echo $listing['main_image'] ?? 'assets/images/no-image.jpg'; ?>" alt="">
                            </div>
                            <div class="featured-listing-info">
                                <div class="featured-listing-title">
                                    <?php echo truncate($listing['title'], 30); ?>
                                </div>
                                <div class="featured-listing-meta">
                                    <span><i class="fas fa-bed"></i> <?php echo $listing['bedrooms']; ?></span>
                                    <span><i class="fas fa-bath"></i> <?php echo $listing['bathrooms']; ?></span>
                                    <span><i class="fas fa-ruler-combined"></i> <?php echo $listing['built_area']; ?> sqft</span>
                                </div>
                                <div class="featured-listing-price">
                                    <?php echo formatPrice($listing['price'], $listing['currency']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Contact Form -->
                    <div class="sidebar-widget contact-form-widget">
                        <h4>Contact Us</h4>
                        
                        <form id="contactForm">
                            <input type="text" name="name" class="form-control" placeholder="Your Name" required>
                            <input type="email" name="email" class="form-control" placeholder="Your Email" required>
                            <input type="tel" name="phone" class="form-control" placeholder="Your Phone" required>
                            <input type="text" name="number" class="form-control" placeholder="Your Number" required>
                            <button type="submit" class="btn-submit">
                                Submit
                            </button>
                        </form>
                    </div>

                    <!-- Banner -->
                    <div class="sidebar-widget p-0">
                        <div class="banner-widget">
                            <h3>We can help you to find real estate agency</h3>
                            <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore</p>
                            <a href="agentes-publicos.php" class="btn-banner">
                                Contact With Agent
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gallery Modal -->
    <div class="modal fade" id="galleryModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Property Gallery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <?php foreach($propertyImages as $image): ?>
                        <div class="col-md-4">
                            <img src="<?php echo $image['image_url']; ?>" class="img-fluid rounded" alt="">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3">
                    <h5>About Pillar</h5>
                    <p style="color: #94A3B8;">Pillar is a luxury to the resilience, adaptability, Spacious modern villa living room with centrally placed swimming pool.</p>
                </div>
                <div class="col-lg-3">
                    <h5>Featured Houses</h5>
                    <ul class="footer-links">
                        <li><a href="#">#Villa</a></li>
                        <li><a href="#">#Commercial</a></li>
                        <li><a href="#">#Farm Houses</a></li>
                        <li><a href="#">#Apartments</a></li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#">Strategy Services</a></li>
                        <li><a href="#">Management</a></li>
                        <li><a href="#">Privacy & Policy</a></li>
                        <li><a href="#">Term & Conditions</a></li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h5>Support</h5>
                    <ul class="footer-links">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">FAQs</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Live Chat</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.1/lightgallery.min.js"></script>
    
    <script>
        // Change main image
        function changeMainImage(imageSrc) {
            document.querySelector('#mainImage img').src = imageSrc;
        }

        // Favorite button
        document.querySelector('.btn-favorite').addEventListener('click', function() {
            this.classList.toggle('active');
            const icon = this.querySelector('i');
            if(icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
            }
        });

        // Floor plan tabs
        document.querySelectorAll('.floor-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.floor-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Light Gallery
        lightGallery(document.getElementById('lightgallery'), {
            selector: '.gallery-grid-item',
            thumbnail: true,
            animateThumb: true,
            showThumbByDefault: true
        });

        // Contact Form
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for your interest! We will contact you soon.');
            this.reset();
        });
    </script>
</body>
</html>
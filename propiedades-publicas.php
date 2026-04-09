<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Filtros
$operation = $_GET['operation'] ?? '';
$type = $_GET['type'] ?? '';
$city = $_GET['city'] ?? '';
$bedrooms = $_GET['bedrooms'] ?? '';
$priceMin = $_GET['price_min'] ?? '';
$priceMax = $_GET['price_max'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = $_GET['page'] ?? 1;

// Construir query
$where = ["publish_on_website = 1", "status = 'available'"];
$params = [];

if (!empty($operation)) {
    $where[] = "operation_type = ?";
    $params[] = $operation;
}

if (!empty($type)) {
    $where[] = "property_type_id = ?";
    $params[] = $type;
}

if (!empty($city)) {
    $where[] = "city = ?";
    $params[] = $city;
}

if (!empty($bedrooms)) {
    if ($bedrooms == '4') {
        $where[] = "bedrooms >= 4";
    } else {
        $where[] = "bedrooms = ?";
        $params[] = $bedrooms;
    }
}

if (!empty($priceMin)) {
    $where[] = "price >= ?";
    $params[] = $priceMin;
}

if (!empty($priceMax)) {
    $where[] = "price <= ?";
    $params[] = $priceMax;
}

if (!empty($search)) {
    $where[] = "(title LIKE ? OR address LIKE ? OR city LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $where);

// Si no hay propiedades con publish_on_website, mostrar todas disponibles
if (empty($whereClause)) {
    $whereClause = "status IN ('available', 'draft')";
}

// Orden
$orderBy = match($sort) {
    'price_asc' => 'price ASC',
    'price_desc' => 'price DESC',
    'oldest' => 'created_at ASC',
    default => 'created_at DESC'
};

// Contar total
$totalProperties = db()->count('properties', $whereClause, $params);

// Paginación
$itemsPerPage = 9;
$pagination = paginate($totalProperties, $itemsPerPage, $page);

// Obtener propiedades
$properties = db()->select(
    "SELECT p.*, pt.name as type_name,
     (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image
     FROM properties p
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     WHERE {$whereClause}
     ORDER BY {$orderBy}
     LIMIT {$pagination['items_per_page']} OFFSET {$pagination['offset']}",
    $params
);

// Si no hay propiedades, mostrar todas
if (empty($properties)) {
    $properties = db()->select(
        "SELECT p.*, pt.name as type_name,
         (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image
         FROM properties p
         LEFT JOIN property_types pt ON p.property_type_id = pt.id
         WHERE status IN ('available', 'draft')
         ORDER BY created_at DESC
         LIMIT {$pagination['items_per_page']} OFFSET {$pagination['offset']}"
    );
    $totalProperties = db()->count('properties', "status IN ('available', 'draft')");
    $pagination = paginate($totalProperties, $itemsPerPage, $page);
}

// Obtener tipos de propiedad
$propertyTypes = db()->select("SELECT * FROM property_types WHERE is_active = 1 ORDER BY display_order");

// Obtener ciudades disponibles
$cities = db()->select("SELECT DISTINCT city FROM properties WHERE city IS NOT NULL AND city != '' ORDER BY city");

$pageTitle = 'Property Listing';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Listing - Jaf Investments</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            background: var(--light-gray);
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
            margin: 0 8px;
        }

        .top-bar i {
            font-size: 12px;
        }

        /* Navbar */
        .navbar {
            background: white !important;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark) !important;
        }

        .navbar-brand small {
            display: block;
            font-size: 12px;
            font-weight: 400;
            color: var(--gray);
        }

        .navbar-nav .nav-link {
            color: var(--dark) !important;
            margin: 0 15px;
            font-weight: 500;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: var(--primary) !important;
        }

        .navbar-nav .nav-link i.fa-chevron-down {
            font-size: 10px;
            margin-left: 5px;
        }

        .btn-add-listing {
            background: var(--dark);
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-listing:hover {
            background: var(--primary);
            color: white;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 40px 0;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0;
        }

        .view-toggle {
            display: flex;
            gap: 10px;
        }

        .view-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #E5E7EB;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-btn.active,
        .view-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .sort-select {
            border: 1px solid #E5E7EB;
            padding: 10px 40px 10px 15px;
            border-radius: 8px;
            background: white;
            font-weight: 500;
            cursor: pointer;
        }

        /* Property Card */
        .property-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            margin-bottom: 30px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .property-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }

        .property-image {
            position: relative;
            height: 250px;
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
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            z-index: 2;
        }

        .property-badge.featured {
            background: #10B981;
        }

        .property-badge.hot-offer {
            background: #F59E0B;
        }

        .btn-favorite {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 2;
            transition: all 0.3s;
        }

        .btn-favorite:hover,
        .btn-favorite.active {
            background: var(--primary);
            color: white;
        }

        .property-info {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .property-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .property-location {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .property-location i {
            color: var(--primary);
        }

        .property-features {
            display: flex;
            gap: 20px;
            padding: 15px 0;
            margin: 15px 0;
            border-top: 1px solid #E5E7EB;
            border-bottom: 1px solid #E5E7EB;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--gray);
            font-size: 14px;
        }

        .feature-item i {
            color: var(--dark);
        }

        .property-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
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
            transition: gap 0.3s;
        }

        .btn-view-more:hover {
            gap: 10px;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin: 50px 0;
        }

        .pagination {
            display: flex;
            gap: 10px;
            list-style: none;
        }

        .page-item {
            margin: 0;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 45px;
            height: 45px;
            padding: 0 15px;
            border: 1px solid #E5E7EB;
            background: white;
            color: var(--dark);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
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

        .footer-about p {
            color: #94A3B8;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .footer-contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            color: #94A3B8;
        }

        .footer-contact-item i {
            width: 40px;
            height: 40px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
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

        .footer-links a i {
            color: var(--primary);
            font-size: 12px;
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

        .app-download {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .app-download img {
            height: 45px;
            border-radius: 8px;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .page-header {
                padding: 30px 0;
            }

            .page-title {
                font-size: 2rem;
            }

            .navbar-collapse {
                background: white;
                padding: 20px;
                margin-top: 15px;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            .navbar-nav .nav-link {
                margin: 10px 0;
            }
        }

        @media (max-width: 768px) {
            .property-features {
                flex-direction: column;
                gap: 10px;
            }

            .footer-bottom {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--gray);
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 15px;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:info@jafinvestments.com">info@jafinvestments.com</a>
                    <i class="fas fa-phone ms-3"></i>
                    <a href="tel:+18094567890">+1 (809) 456 789 012</a>
                </div>
                <div>
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="ms-3">
                        <i class="fas fa-globe me-1"></i>English
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-home me-2 text-danger"></i>PILLER
                <small>Real Estate Solution</small>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            Home <i class="fas fa-chevron-down"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="propiedades-publicas.php">
                            Property <i class="fas fa-chevron-down"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="agentes-publicos.php">
                            Agencies <i class="fas fa-chevron-down"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            Pages <i class="fas fa-chevron-down"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            Blog <i class="fas fa-chevron-down"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact Us</a>
                    </li>
                </ul>
                
                <div class="d-flex gap-3 align-items-center">
                    <button class="btn-add-listing">
                        <i class="fas fa-plus-circle"></i>
                        Add Listing
                    </button>
                    <button class="btn btn-link text-dark p-0">
                        <i class="fas fa-bars fs-5"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">Property Listing</h1>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="view-toggle">
                        <button class="view-btn" id="gridView">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button class="view-btn active" id="listView">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                    <select class="sort-select" onchange="location.href='?sort='+this.value+'&<?php echo http_build_query(array_diff_key($_GET, ['sort' => ''])); ?>'">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Default Sorting</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
            </div>
        </div>
    </section>

    <!-- Properties Grid -->
    <section class="properties-section">
        <div class="container">
            <?php if(empty($properties)): ?>
                <div class="empty-state">
                    <i class="fas fa-home"></i>
                    <h3>No Properties Found</h3>
                    <p>Try adjusting your search filters or browse all properties</p>
                    <a href="propiedades-publicas.php" class="btn btn-primary">View All Properties</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($properties as $property): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="property-card">
                            <div class="property-image">
                                <?php 
                                $imageSrc = $property['main_image'] ?? 'assets/images/no-image.jpg';
                                ?>
                                <img src="<?php echo $imageSrc; ?>" 
                                     alt="<?php echo htmlspecialchars($property['title']); ?>"
                                     onerror="this.src='assets/images/no-image.jpg'">
                                
                                <span class="property-badge">
                                    For <?php echo $property['operation_type'] === 'sale' ? 'Sale' : 'Rent'; ?>
                                </span>

                                <button class="btn-favorite" onclick="toggleFavorite(this)">
                                    <i class="far fa-heart"></i>
                                </button>
                            </div>

                            <div class="property-info">
                                <h3 class="property-title">
                                    <a href="detalle-propiedad.php?id=<?php echo $property['id']; ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo htmlspecialchars($property['title']); ?>
                                    </a>
                                </h3>

                                <div class="property-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($property['city'] ?? 'N/A'); ?>
                                </div>

                                <div class="property-features">
                                    <div class="feature-item">
                                        <i class="fas fa-bed"></i>
                                        Bed <?php echo $property['bedrooms'] ?? 0; ?>
                                    </div>
                                    <div class="feature-item">
                                        <i class="fas fa-bath"></i>
                                        Bath <?php echo $property['bathrooms'] ?? 0; ?>
                                    </div>
                                    <div class="feature-item">
                                        <i class="fas fa-ruler-combined"></i>
                                        <?php echo $property['built_area'] ?? 0; ?> sqft
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

                <!-- Pagination -->
                <?php if($pagination['total_pages'] > 1): ?>
                <div class="pagination-wrapper">
                    <ul class="pagination">
                        <!-- Previous Button -->
                        <?php if($pagination['current_page'] > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        </li>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php 
                        $start = max(1, $pagination['current_page'] - 1);
                        $end = min($pagination['total_pages'], $pagination['current_page'] + 1);
                        
                        for($i = $start; $i <= $end; $i++): 
                        ?>
                        <li class="page-item <?php echo $i == $pagination['current_page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <!-- Next Button -->
                        <?php if($pagination['current_page'] < $pagination['total_pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                Next <i class="fas fa-chevron-right ms-1"></i>
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">
                                Next <i class="fas fa-chevron-right ms-1"></i>
                            </span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <!-- About Pillar -->
                <div class="col-lg-3">
                    <h5>About Pillar</h5>
                    <div class="footer-about">
                        <p>Pillar is a luxury to the resilience, adaptability, Spacious modern villa living room with centrally placed swimming pool blending indooroutdoor.</p>
                    </div>

                    <div class="footer-contact-item">
                        <i class="fas fa-phone"></i>
                        <div>
                            <div style="color: white; font-weight: 600;">+1 (123) 456 789 012</div>
                        </div>
                    </div>

                    <div class="footer-contact-item">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <div style="color: white; font-weight: 600;">infomall123@domain.com</div>
                        </div>
                    </div>

                    <div class="footer-contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <div style="color: white; font-weight: 600;">West 2nd lane, Inner circular road, New York City</div>
                        </div>
                    </div>
                </div>

                <!-- Featured Houses -->
                <div class="col-lg-2">
                    <h5>Featured Houses</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-home"></i> #Villa</a></li>
                        <li><a href="#"><i class="fas fa-building"></i> #Commercial</a></li>
                        <li><a href="#"><i class="fas fa-home"></i> #Farm Houses</a></li>
                        <li><a href="#"><i class="fas fa-door-open"></i> #Apartments</a></li>
                        <li><a href="#"><i class="fas fa-door-closed"></i> #Apartments</a></li>
                    </ul>
                </div>

                <!-- Quick Links -->
                <div class="col-lg-2">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Strategy Services</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Management</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Privacy & Policy</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Sitemap</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Term & Conditions</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div class="col-lg-2">
                    <h5>Support</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Help Center</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> FAQs</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Ticket Support</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Live Chat</a></li>
                    </ul>
                </div>

                <!-- Pillar Location -->
                <div class="col-lg-3">
                    <h5>Pillar Location</h5>
                    <div class="footer-map">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3784.3056437654067!2d-69.93315!3d18.48634!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTjCsDI5JzEwLjgiTiA2OcKwNTUnNTkuMyJX!5e0!3m2!1sen!2sdo!4v1234567890" allowfullscreen="" loading="lazy"></iframe>
                    </div>

                    <div class="mt-4">
                        <div style="color: var(--primary); font-size: 12px; font-weight: 600; margin-bottom: 10px;">
                            Need to Home<br>buy or sell?
                        </div>
                        <div class="app-download">
                            <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/3/3c/Download_on_the_App_Store_Badge.svg/320px-Download_on_the_App_Store_Badge.svg.png" alt="App Store"></a>
                            <a href="#"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/7/78/Google_Play_Store_badge_EN.svg/320px-Google_Play_Store_badge_EN.svg.png" alt="Google Play"></a>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // View Toggle
        document.getElementById('gridView').addEventListener('click', function() {
            document.getElementById('gridView').classList.add('active');
            document.getElementById('listView').classList.remove('active');
            // Aquí puedes agregar lógica para cambiar a vista de grid
        });

        document.getElementById('listView').addEventListener('click', function() {
            document.getElementById('listView').classList.add('active');
            document.getElementById('gridView').classList.remove('active');
            // Aquí puedes agregar lógica para cambiar a vista de lista
        });

        // Toggle Favorite
        function toggleFavorite(button) {
            const icon = button.querySelector('i');
            button.classList.toggle('active');
            
            if(icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas');
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
            }
        }
    </script>
</body>
</html>
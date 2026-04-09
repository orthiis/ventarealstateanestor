<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('menu.properties');
$currentUser = getCurrentUser();

// ========== FILTROS Y BÚSQUEDA ==========
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$cityFilter = $_GET['city'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// ========== CONSTRUIR QUERY CON FILTROS ==========
$whereConditions = ["p.status != 'deleted'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(p.reference LIKE ? OR p.title LIKE ? OR p.address LIKE ? OR p.city LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($statusFilter)) {
    $whereConditions[] = "p.status = ?";
    $params[] = $statusFilter;
}

if (!empty($typeFilter)) {
    $whereConditions[] = "p.property_type_id = ?";
    $params[] = $typeFilter;
}

if (!empty($cityFilter)) {
    $whereConditions[] = "p.city = ?";
    $params[] = $cityFilter;
}

if (!empty($minPrice)) {
    $whereConditions[] = "p.price >= ?";
    $params[] = $minPrice;
}

if (!empty($maxPrice)) {
    $whereConditions[] = "p.price <= ?";
    $params[] = $maxPrice;
}

$whereClause = implode(' AND ', $whereConditions);

// Contar total de registros
$totalProperties = (int)db()->selectValue(
    "SELECT COUNT(*) FROM properties p WHERE $whereClause",
    $params
);

$totalPages = ceil($totalProperties / $perPage);

// Obtener propiedades
$properties = db()->select(
    "SELECT p.*, 
     pt.name as property_type_name,
     (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image,
     (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as total_images,
     CONCAT(u.first_name, ' ', u.last_name) as agent_name
     FROM properties p
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     LEFT JOIN users u ON p.agent_id = u.id
     WHERE $whereClause
     ORDER BY p.$sortBy $sortOrder
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// ========== OBTENER DATOS PARA FILTROS ==========
$propertyTypes = db()->select("SELECT * FROM property_types ORDER BY name ASC");

$cities = db()->select(
    "SELECT DISTINCT city FROM properties 
     WHERE city IS NOT NULL AND city != '' AND status != 'deleted'
     ORDER BY city ASC"
);

// ========== ESTADÍSTICAS RÁPIDAS ==========
$stats = [
    'total' => (int)db()->selectValue("SELECT COUNT(*) FROM properties WHERE status != 'deleted'"),
    'available' => (int)db()->selectValue("SELECT COUNT(*) FROM properties WHERE status = 'available'"),
    'sold' => (int)db()->selectValue("SELECT COUNT(*) FROM properties WHERE status = 'sold'"),
    'rented' => (int)db()->selectValue("SELECT COUNT(*) FROM properties WHERE status = 'rented'"),
    'reserved' => (int)db()->selectValue("SELECT COUNT(*) FROM properties WHERE status = 'reserved'")
];

include 'header.php';
include 'sidebar.php';
?>

<style>
    :root {
        --primary: #667eea;
        --purple: #764ba2;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
    }

    .properties-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: calc(100vh - 80px);
    }

    /* Page Header */
    .page-header-modern {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-title-modern {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 5px 0;
    }

    .page-subtitle-modern {
        color: #718096;
        margin: 0;
        font-size: 14px;
    }

    /* Quick Stats */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
        transition: all 0.3s ease;
        border-left: 4px solid;
    }

    .stat-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .stat-box.total { border-left-color: var(--primary); }
    .stat-box.available { border-left-color: var(--success); }
    .stat-box.sold { border-left-color: var(--danger); }
    .stat-box.rented { border-left-color: var(--info); }
    .stat-box.reserved { border-left-color: var(--warning); }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 13px;
        color: #718096;
        font-weight: 500;
    }

    /* Filters Card */
    .filters-card {
        background: white;
        padding: 15px;
        border-radius: 14px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .filters-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .filters-title {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin: 0;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
        margin-bottom: 8px;
        display: block;
    }

    .form-control {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: #f9fafb;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-select {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        background: #f9fafb;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .form-select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .filters-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    /* Buttons */
    .btn-modern {
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-primary-modern {
        background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
        color: white;
    }

    .btn-primary-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }

    .btn-outline-modern {
        background: white;
        border: 2px solid #e5e7eb;
        color: #4b5563;
    }

    .btn-outline-modern:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }

    /* Properties Grid */
    .properties-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .property-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        position: relative;
    }

    .property-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.15);
    }

    .property-image {
        width: 100%;
        height: 220px;
        object-fit: cover;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        position: relative;
    }

    .property-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .property-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        backdrop-filter: blur(10px);
    }

    .badge-available {
        background: rgba(16, 185, 129, 0.9);
        color: white;
    }

    .badge-sold {
        background: rgba(239, 68, 68, 0.9);
        color: white;
    }

    .badge-rented {
        background: rgba(59, 130, 246, 0.9);
        color: white;
    }

    .badge-reserved {
        background: rgba(245, 158, 11, 0.9);
        color: white;
    }

    .property-images-count {
        position: absolute;
        bottom: 15px;
        right: 15px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        backdrop-filter: blur(10px);
    }

    .property-content {
        padding: 20px;
    }

    .property-reference {
        font-size: 12px;
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 5px;
    }

    .property-title {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 10px 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .property-type {
        display: inline-block;
        padding: 4px 10px;
        background: #f3f4f6;
        color: #6b7280;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .property-location {
        color: #718096;
        font-size: 14px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .property-specs {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f3f4f6;
    }

    .spec-item {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #6b7280;
        font-size: 13px;
    }

    .spec-item i {
        color: var(--primary);
    }

    .property-price {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 15px;
    }

    .property-footer {
        display: flex;
        gap: 10px;
    }

    .property-footer .btn-modern {
        flex: 1;
        justify-content: center;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .empty-state i {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 20px;
    }

    .empty-state h3 {
        font-size: 20px;
        font-weight: 700;
        color: #4b5563;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #9ca3af;
        margin-bottom: 20px;
    }

    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 30px;
    }

    .pagination {
        display: flex;
        gap: 5px;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .page-item {
        margin: 0;
    }

    .page-link {
        padding: 10px 15px;
        border-radius: 8px;
        background: white;
        border: 2px solid #e5e7eb;
        color: #4b5563;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .page-link:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f9fafb;
    }

    .page-item.active .page-link {
        background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
        color: white;
        border-color: var(--primary);
    }

    .page-item.disabled .page-link {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Results Info */
    .results-info {
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .results-text {
        color: #6b7280;
        font-size: 14px;
    }

    .results-text strong {
        color: #2d3748;
        font-weight: 700;
    }

    .view-toggle {
        display: flex;
        gap: 5px;
    }

    .view-btn {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        border: 2px solid #e5e7eb;
        background: white;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .view-btn:hover,
    .view-btn.active {
        border-color: var(--primary);
        color: var(--primary);
        background: #f9fafb;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .properties-container {
            padding: 20px;
        }

        .quick-stats {
            grid-template-columns: repeat(3, 1fr);
        }

        .properties-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
    }

    @media (max-width: 768px) {
        .properties-container {
            padding: 15px;
        }

        .page-header-modern {
            padding: 20px;
            flex-direction: column;
            align-items: flex-start;
        }

        .page-title-modern {
            font-size: 22px;
        }

        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .stat-box {
            padding: 15px;
        }

        .stat-value {
            font-size: 24px;
        }

        .stat-label {
            font-size: 11px;
        }

        .filters-card {
            padding: 20px;
        }

        .filters-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .filters-actions {
            flex-direction: column;
        }

        .filters-actions .btn-modern {
            width: 100%;
            justify-content: center;
        }

        .properties-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .property-image {
            height: 200px;
        }

        .property-price {
            font-size: 20px;
        }

        .results-info {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        .pagination-container {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 480px) {
        .properties-container {
            padding: 10px;
        }

        .page-header-modern {
            padding: 15px;
        }

        .page-title-modern {
            font-size: 20px;
        }

        .quick-stats {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .filters-card {
            padding: 15px;
        }

        .property-content {
            padding: 15px;
        }

        .property-title {
            font-size: 16px;
        }

        .property-footer {
            flex-direction: column;
        }
    }
</style>

<div class="properties-container">
    
    <!-- Page Header -->
    <div class="page-header-modern">
        <div style="flex: 1;">
            <h1 class="page-title-modern">
                <i class="fas fa-home" style="color: var(--primary);"></i>
                <?php echo __('properties.title'); ?>
            </h1>
            <p class="page-subtitle-modern">
                <?php echo __('properties.subtitle'); ?>
            </p>
        </div>
        <div>
            <a href="nueva-propiedad.php" class="btn-modern btn-primary-modern">
                <i class="fas fa-plus"></i>
                <?php echo __('properties.add_new'); ?>
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-box total">
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label"><?php echo __('properties.stats.total'); ?></div>
        </div>
        <div class="stat-box available">
            <div class="stat-value"><?php echo number_format($stats['available']); ?></div>
            <div class="stat-label"><?php echo __('properties.stats.available'); ?></div>
        </div>
        <div class="stat-box sold">
            <div class="stat-value"><?php echo number_format($stats['sold']); ?></div>
            <div class="stat-label"><?php echo __('properties.stats.sold'); ?></div>
        </div>
        <div class="stat-box rented">
            <div class="stat-value"><?php echo number_format($stats['rented']); ?></div>
            <div class="stat-label"><?php echo __('properties.stats.rented'); ?></div>
        </div>
        <div class="stat-box reserved">
            <div class="stat-value"><?php echo number_format($stats['reserved']); ?></div>
            <div class="stat-label"><?php echo __('properties.stats.reserved'); ?></div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="filters-card">
        <div class="filters-header">
            <h3 class="filters-title">
                <i class="fas fa-filter"></i>
                <?php echo __('properties.filters.title'); ?>
            </h3>
            <?php if (!empty($search) || !empty($statusFilter) || !empty($typeFilter) || !empty($cityFilter) || !empty($minPrice) || !empty($maxPrice)): ?>
            <a href="propiedades.php" class="btn-modern btn-outline-modern btn-sm">
                <i class="fas fa-times"></i>
                <?php echo __('properties.filters.clear'); ?>
            </a>
            <?php endif; ?>
        </div>

        <form method="GET" action="propiedades.php" id="filtersForm">
            <div class="filters-grid">
                <!-- Search -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.search'); ?></label>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="<?php echo __('properties.filters.search_placeholder'); ?>"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.status'); ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?php echo __('properties.filters.all'); ?></option>
                        <option value="available" <?php echo $statusFilter === 'available' ? 'selected' : ''; ?>>
                            <?php echo __('properties.status.available'); ?>
                        </option>
                        <option value="sold" <?php echo $statusFilter === 'sold' ? 'selected' : ''; ?>>
                            <?php echo __('properties.status.sold'); ?>
                        </option>
                        <option value="rented" <?php echo $statusFilter === 'rented' ? 'selected' : ''; ?>>
                            <?php echo __('properties.status.rented'); ?>
                        </option>
                        <option value="reserved" <?php echo $statusFilter === 'reserved' ? 'selected' : ''; ?>>
                            <?php echo __('properties.status.reserved'); ?>
                        </option>
                    </select>
                </div>

                <!-- Property Type -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.type'); ?></label>
                    <select name="type" class="form-select">
                        <option value=""><?php echo __('properties.filters.all'); ?></option>
                        <?php foreach ($propertyTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $typeFilter == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- City -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.city'); ?></label>
                    <select name="city" class="form-select">
                        <option value=""><?php echo __('properties.filters.all_cities'); ?></option>
                        <?php foreach ($cities as $city): ?>
                        <option value="<?php echo htmlspecialchars($city['city']); ?>" <?php echo $cityFilter === $city['city'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($city['city']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Min Price -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.min_price'); ?></label>
                    <input type="number" 
                           name="min_price" 
                           class="form-control" 
                           placeholder="$0"
                           value="<?php echo htmlspecialchars($minPrice); ?>">
                </div>

                <!-- Max Price -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.max_price'); ?></label>
                    <input type="number" 
                           name="max_price" 
                           class="form-control" 
                           placeholder="$999,999,999"
                           value="<?php echo htmlspecialchars($maxPrice); ?>">
                </div>

                <!-- Sort By -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.sort_by'); ?></label>
                    <select name="sort" class="form-select">
                        <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>
                            <?php echo __('properties.filters.sort.newest'); ?>
                        </option>
                        <option value="price" <?php echo $sortBy === 'price' ? 'selected' : ''; ?>>
                            <?php echo __('properties.filters.sort.price'); ?>
                        </option>
                        <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>
                            <?php echo __('properties.filters.sort.title'); ?>
                        </option>
                        <option value="reference" <?php echo $sortBy === 'reference' ? 'selected' : ''; ?>>
                            <?php echo __('properties.filters.sort.reference'); ?>
                        </option>
                    </select>
                </div>

                <!-- Order -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('properties.filters.order-'); ?></label>
                    <select name="order" class="form-select">
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>
                            <?php echo __('properties.filters.order.desc'); ?>
                        </option>
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>
                            <?php echo __('properties.filters.order.asc'); ?>
                        </option>
                    </select>
                </div>
            </div>

            <div class="filters-actions">
                <button type="submit" class="btn-modern btn-primary-modern">
                    <i class="fas fa-search"></i>
                    <?php echo __('properties.filters.apply'); ?>
                </button>
                <a href="propiedades.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-redo"></i>
                    <?php echo __('properties.filters.reset'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <?php if ($totalProperties > 0): ?>
    <div class="results-info">
        <div class="results-text">
            <?php echo __('properties.results.showing'); ?> 
            <strong><?php echo min($offset + 1, $totalProperties); ?></strong> 
            <?php echo __('properties.results.to'); ?> 
            <strong><?php echo min($offset + $perPage, $totalProperties); ?></strong> 
            <?php echo __('properties.results.of'); ?> 
            <strong><?php echo number_format($totalProperties); ?></strong> 
            <?php echo __('properties.results.properties'); ?>
        </div>
        <div class="view-toggle">
            <button class="view-btn active" title="<?php echo __('properties.view.grid'); ?>">
                <i class="fas fa-th"></i>
            </button>
            <button class="view-btn" title="<?php echo __('properties.view.list'); ?>">
                <i class="fas fa-list"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Properties Grid -->
    <?php if (empty($properties)): ?>
    <div class="empty-state">
        <i class="fas fa-home"></i>
        <h3><?php echo __('properties.empty.title'); ?></h3>
        <p><?php echo __('properties.empty.description'); ?></p>
        <a href="nueva-propiedad.php" class="btn-modern btn-primary-modern">
            <i class="fas fa-plus"></i>
            <?php echo __('properties.empty.add_first'); ?>
        </a>
    </div>
    <?php else: ?>
    <div class="properties-grid">
        <?php foreach ($properties as $property): ?>
        <div class="property-card">
            <!-- Image -->
            <div class="property-image">
                <?php if ($property['main_image']): ?>
                <img src="<?php echo htmlspecialchars($property['main_image']); ?>" 
                     alt="<?php echo htmlspecialchars($property['title']); ?>">
                <?php else: ?>
                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 48px;">
                    <i class="fas fa-home"></i>
                </div>
                <?php endif; ?>

                <!-- Status Badge -->
                <?php 
                $statusLabels = [
                    'available' => __('properties.status.available'),
                    'sold' => __('properties.status.sold'),
                    'rented' => __('properties.status.rented'),
                    'reserved' => __('properties.status.reserved')
                ];
                $statusLabel = $statusLabels[$property['status']] ?? ucfirst($property['status']);
                ?>
                <span class="property-badge badge-<?php echo $property['status']; ?>">
                    <?php echo $statusLabel; ?>
                </span>

                <!-- Images Count -->
                <?php if ($property['total_images'] > 0): ?>
                <span class="property-images-count">
                    <i class="fas fa-camera"></i> <?php echo $property['total_images']; ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="property-content">
                <div class="property-reference">
                    <?php echo __('properties.card.reference'); ?>: <?php echo htmlspecialchars($property['reference']); ?>
                </div>

                <h3 class="property-title" title="<?php echo htmlspecialchars($property['title']); ?>">
                    <?php echo htmlspecialchars($property['title']); ?>
                </h3>

                <span class="property-type">
                    <?php echo htmlspecialchars($property['property_type_name']); ?>
                </span>

                <div class="property-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($property['city'] ?? __('properties.card.no_location')); ?>
                </div>

                <div class="property-specs">
                    <?php if ($property['bedrooms']): ?>
                    <div class="spec-item">
                        <i class="fas fa-bed"></i>
                        <span><?php echo $property['bedrooms']; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($property['bathrooms']): ?>
                    <div class="spec-item">
                        <i class="fas fa-bath"></i>
                        <span><?php echo $property['bathrooms']; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($property['area']): ?>
                    <div class="spec-item">
                        <i class="fas fa-ruler-combined"></i>
                        <span><?php echo number_format($property['area']); ?> m²</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="property-price">
                    $<?php echo number_format($property['price'], 0); ?>
                </div>

                <div class="property-footer">
                    <a href="ver-propiedad.php?id=<?php echo $property['id']; ?>" 
                       class="btn-modern btn-outline-modern btn-sm">
                        <i class="fas fa-eye"></i>
                        <?php echo __('properties.card.view'); ?>
                    </a>
                    <a href="editar-propiedad.php?id=<?php echo $property['id']; ?>" 
                       class="btn-modern btn-primary-modern btn-sm">
                        <i class="fas fa-edit"></i>
                        <?php echo __('properties.card.edit'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-container">
        <nav>
            <ul class="pagination">
                <!-- Previous -->
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <?php
                    $prevUrl = http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)]));
                    ?>
                    <a class="page-link" href="?<?php echo $prevUrl; ?>">
                        <i class="fas fa-chevron-left"></i>
                        <span class="d-none d-sm-inline"><?php echo __('properties.pagination.previous'); ?></span>
                    </a>
                </li>

                <!-- Page Numbers -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1): ?>
                    <li class="page-item">
                        <?php $firstUrl = http_build_query(array_merge($_GET, ['page' => 1])); ?>
                        <a class="page-link" href="?<?php echo $firstUrl; ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php $pageUrl = http_build_query(array_merge($_GET, ['page' => $i])); ?>
                        <a class="page-link" href="?<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <?php $lastUrl = http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>
                        <a class="page-link" href="?<?php echo $lastUrl; ?>"><?php echo $totalPages; ?></a>
                    </li>
                <?php endif; ?>

                <!-- Next -->
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <?php $nextUrl = http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>
                    <a class="page-link" href="?<?php echo $nextUrl; ?>">
                        <span class="d-none d-sm-inline"><?php echo __('properties.pagination.next'); ?></span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
// View Toggle (Grid/List)
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Aquí podrías agregar lógica para cambiar entre vista de cuadrícula y lista
    });
});

// Confirmación de eliminación
function confirmDelete(propertyId, propertyTitle) {
    if (confirm('<?php echo __('properties.confirm_delete'); ?>\n\n' + propertyTitle)) {
        window.location.href = 'eliminar-propiedad.php?id=' + propertyId;
    }
}
</script>

<?php include 'footer.php'; ?>
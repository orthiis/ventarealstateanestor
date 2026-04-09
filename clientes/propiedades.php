<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';
require_once 'includes/functions.php';

session_start();
requireClientLogin();

$pageTitle = 'Mis Propiedades';
$currentClient = getCurrentClient();
$clientId = $currentClient['id'];

// ============================================================
// FILTROS Y BÚSQUEDA
// ============================================================
$filterType = $_GET['type'] ?? 'all'; // all, sale, rent, vacation_rent
$filterPaymentStatus = $_GET['payment_status'] ?? 'all';
$filterPropertyType = $_GET['property_type'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'recent'; // recent, oldest, price_high, price_low, title
$viewMode = $_GET['view'] ?? 'grid'; // grid, list

// ============================================================
// CONSTRUIR QUERY CON FILTROS
// ============================================================
$where = ['st.client_id = ?'];
$params = [$clientId];

// Filtro por tipo de transacción
if ($filterType !== 'all') {
    $where[] = 'st.transaction_type = ?';
    $params[] = $filterType;
}

// Filtro por estado de pago
if ($filterPaymentStatus !== 'all') {
    $where[] = 'st.payment_status = ?';
    $params[] = $filterPaymentStatus;
}

// Filtro por tipo de propiedad
if ($filterPropertyType !== 'all') {
    $where[] = 'p.property_type_id = ?';
    $params[] = $filterPropertyType;
}

// Búsqueda
if (!empty($searchQuery)) {
    $where[] = '(p.title LIKE ? OR p.address LIKE ? OR p.city LIKE ? OR p.reference LIKE ?)';
    $searchTerm = '%' . $searchQuery . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

// Ordenamiento
$orderBy = 'st.created_at DESC';
switch ($sortBy) {
    case 'oldest':
        $orderBy = 'st.created_at ASC';
        break;
    case 'price_high':
        $orderBy = 'st.sale_price DESC';
        break;
    case 'price_low':
        $orderBy = 'st.sale_price ASC';
        break;
    case 'title':
        $orderBy = 'p.title ASC';
        break;
    case 'recent':
    default:
        $orderBy = 'st.created_at DESC';
        break;
}

// ============================================================
// OBTENER PROPIEDADES CON INFORMACIÓN COMPLETA
// ============================================================
$properties = db()->select("
    SELECT st.*,
           p.id as property_id,
           p.reference as property_reference,
           p.title as property_title,
           p.address as property_address,
           p.city as property_city,
           p.state_province as property_state,
           p.price as property_price,
           p.bedrooms,
           p.bathrooms,
           p.area_total,
           p.area_built,
           p.parking_spaces,
           p.year_built,
           p.description as property_description,
           p.status as property_status,
           pt.name as property_type,
           pt.icon as property_type_icon,
           (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as property_image,
           (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as total_images,
           CONCAT(u.first_name, ' ', u.last_name) as agent_name,
           u.phone as agent_phone,
           u.email as agent_email,
           u.profile_picture as agent_picture,
           
           -- Contadores relacionados
           (SELECT COUNT(*) FROM client_property_documents WHERE property_id = p.id AND client_id = st.client_id) as document_count,
           (SELECT COUNT(*) FROM client_property_comments WHERE property_id = p.id AND client_id = st.client_id AND sender_type = 'admin' AND is_read = 0) as unread_messages,
           (SELECT COUNT(*) FROM invoices WHERE property_id = p.id AND client_id = st.client_id) as invoice_count,
           (SELECT COUNT(*) FROM invoices WHERE property_id = p.id AND client_id = st.client_id AND status IN ('pending', 'partial', 'overdue')) as pending_invoices,
           
           -- Última actividad
           (SELECT MAX(upload_date) FROM client_property_documents WHERE property_id = p.id AND client_id = st.client_id) as last_document_date,
           (SELECT MAX(created_at) FROM client_property_comments WHERE property_id = p.id AND client_id = st.client_id) as last_comment_date
           
    FROM sales_transactions st
    INNER JOIN properties p ON st.property_id = p.id
    LEFT JOIN property_types pt ON p.property_type_id = pt.id
    LEFT JOIN users u ON st.agent_id = u.id
    WHERE {$whereClause}
    AND st.status IN ('completed', 'in_progress')
    ORDER BY {$orderBy}
", $params);

// ============================================================
// ESTADÍSTICAS DE PROPIEDADES
// ============================================================
$propertyStats = db()->selectOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN st.transaction_type = 'sale' THEN 1 ELSE 0 END) as total_sales,
        SUM(CASE WHEN st.transaction_type IN ('rent', 'vacation_rent') THEN 1 ELSE 0 END) as total_rentals,
        SUM(CASE WHEN st.payment_status = 'completed' THEN 1 ELSE 0 END) as fully_paid,
        SUM(CASE WHEN st.payment_status IN ('pending', 'partial') THEN 1 ELSE 0 END) as pending_payment
    FROM sales_transactions st
    WHERE st.client_id = ?
    AND st.status IN ('completed', 'in_progress')
", [$clientId]);

// ============================================================
// OBTENER TIPOS DE PROPIEDAD PARA FILTROS
// ============================================================
$propertyTypes = db()->select("
    SELECT DISTINCT pt.id, pt.name
    FROM property_types pt
    INNER JOIN properties p ON pt.id = p.property_type_id
    INNER JOIN sales_transactions st ON p.id = st.property_id
    WHERE st.client_id = ?
    ORDER BY pt.name
", [$clientId]);

include 'includes/header.php';
?>

<style>
    .filter-bar {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 1.5rem;
    }
    
    .property-card-advanced {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.3s;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: 100%;
        border: 2px solid transparent;
    }
    
    .property-card-advanced:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        border-color: var(--primary-color);
    }
    
    .property-image-container {
        position: relative;
        height: 220px;
        overflow: hidden;
        background: #f3f4f6;
    }
    
    .property-image-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }
    
    .property-card-advanced:hover .property-image-container img {
        transform: scale(1.1);
    }
    
    .property-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 2;
    }
    
    .property-gallery-badge {
        position: absolute;
        bottom: 12px;
        left: 12px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        z-index: 2;
    }
    
    .property-stats-bar {
        display: flex;
        gap: 1rem;
        padding: 0.75rem 1rem;
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
    }
    
    .property-stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
    }
    
    .quick-action-btn {
        padding: 0.5rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: white;
        transition: all 0.2s;
        cursor: pointer;
        font-size: 0.9rem;
    }
    
    .quick-action-btn:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .alert-indicator {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 2;
    }
    
    .property-list-view {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
    }
    
    .property-list-view:hover {
        box-shadow: 0 8px 16px rgba(0,0,0,0.12);
    }
    
    .view-toggle-btn {
        padding: 0.5rem 1rem;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .view-toggle-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        border: 2px solid white;
    }
    
    .property-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin: 0.75rem 0;
    }
    
    .feature-tag {
        background: #f3f4f6;
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    
    .search-box {
        position: relative;
    }
    
    .search-box input {
        padding-left: 2.5rem;
    }
    
    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-building me-2"></i> Mis Propiedades</h1>
            <p class="text-muted mb-0">
                Gestiona todas tus propiedades compradas y alquiladas
            </p>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group">
                <a href="?view=grid<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                   class="view-toggle-btn <?php echo $viewMode === 'grid' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> Grid
                </a>
                <a href="?view=list<?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>" 
                   class="view-toggle-btn <?php echo $viewMode === 'list' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Lista
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas Rápidas -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 bg-primary bg-opacity-10 border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted d-block">Total Propiedades</small>
                        <h3 class="mb-0 text-primary"><?php echo $propertyStats['total']; ?></h3>
                    </div>
                    <i class="fas fa-building fa-2x text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 bg-success bg-opacity-10 border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted d-block">Compradas</small>
                        <h3 class="mb-0 text-success"><?php echo $propertyStats['total_sales']; ?></h3>
                    </div>
                    <i class="fas fa-home fa-2x text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 bg-info bg-opacity-10 border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted d-block">Alquiladas</small>
                        <h3 class="mb-0 text-info"><?php echo $propertyStats['total_rentals']; ?></h3>
                    </div>
                    <i class="fas fa-key fa-2x text-info opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 bg-warning bg-opacity-10 border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted d-block">Pago Pendiente</small>
                        <h3 class="mb-0 text-warning"><?php echo $propertyStats['pending_payment']; ?></h3>
                    </div>
                    <i class="fas fa-exclamation-circle fa-2x text-warning opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Barra de Filtros y Búsqueda -->
<div class="filter-bar">
    <form method="GET" class="row g-3">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
        
        <!-- Búsqueda -->
        <div class="col-md-4">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" 
                       name="search" 
                       class="form-control" 
                       placeholder="Buscar por título, dirección, ciudad..." 
                       value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
        </div>
        
        <!-- Tipo de Transacción -->
        <div class="col-md-2">
            <select name="type" class="form-select">
                <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>Todos los tipos</option>
                <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>>Compradas</option>
                <option value="rent" <?php echo $filterType === 'rent' ? 'selected' : ''; ?>>Alquiladas</option>
                <option value="vacation_rent" <?php echo $filterType === 'vacation_rent' ? 'selected' : ''; ?>>Alquiler Vacacional</option>
            </select>
        </div>
        
        <!-- Estado de Pago -->
        <div class="col-md-2">
            <select name="payment_status" class="form-select">
                <option value="all" <?php echo $filterPaymentStatus === 'all' ? 'selected' : ''; ?>>Estado de Pago</option>
                <option value="completed" <?php echo $filterPaymentStatus === 'completed' ? 'selected' : ''; ?>>Completado</option>
                <option value="pending" <?php echo $filterPaymentStatus === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="partial" <?php echo $filterPaymentStatus === 'partial' ? 'selected' : ''; ?>>Parcial</option>
                <option value="overdue" <?php echo $filterPaymentStatus === 'overdue' ? 'selected' : ''; ?>>Atrasado</option>
            </select>
        </div>
        
        <!-- Tipo de Propiedad -->
        <div class="col-md-2">
            <select name="property_type" class="form-select">
                <option value="all" <?php echo $filterPropertyType === 'all' ? 'selected' : ''; ?>>Tipo de Propiedad</option>
                <?php foreach ($propertyTypes as $type): ?>
                    <option value="<?php echo $type['id']; ?>" <?php echo $filterPropertyType == $type['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Ordenar -->
        <div class="col-md-2">
            <select name="sort" class="form-select">
                <option value="recent" <?php echo $sortBy === 'recent' ? 'selected' : ''; ?>>Más reciente</option>
                <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Más antiguo</option>
                <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Título A-Z</option>
                <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Precio alto</option>
                <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Precio bajo</option>
            </select>
        </div>
        
        <!-- Botones -->
        <div class="col-12">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter me-2"></i> Aplicar Filtros
            </button>
            <a href="<?php echo url('clientes/propiedades.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-times me-2"></i> Limpiar
            </a>
            <span class="ms-3 text-muted">
                <strong><?php echo count($properties); ?></strong> propiedad(es) encontrada(s)
            </span>
        </div>
    </form>
</div>

<!-- Resultados -->
<?php if (empty($properties)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-home fa-4x text-muted mb-4"></i>
            <h4>No se encontraron propiedades</h4>
            <p class="text-muted mb-4">
                <?php if (!empty($searchQuery) || $filterType !== 'all' || $filterPaymentStatus !== 'all'): ?>
                    Intenta ajustar los filtros de búsqueda.
                <?php else: ?>
                    No tienes propiedades asignadas aún. Cuando realices una compra o alquiler, aparecerán aquí.
                <?php endif; ?>
            </p>
            <a href="<?php echo url('clientes/propiedades.php'); ?>" class="btn btn-primary">
                <i class="fas fa-redo me-2"></i> Ver Todas las Propiedades
            </a>
        </div>
    </div>
<?php else: ?>
    
    <?php if ($viewMode === 'grid'): ?>
        <!-- VISTA EN GRID -->
        <div class="row">
            <?php foreach ($properties as $property): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="property-card-advanced">
                        <!-- Imagen -->
                        <div class="property-image-container">
                            <!-- Alertas/Notificaciones -->
                            <?php if ($property['unread_messages'] > 0): ?>
                                <div class="alert-indicator">
                                    <span class="badge bg-danger">
                                        <i class="fas fa-envelope"></i> <?php echo $property['unread_messages']; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($property['pending_invoices'] > 0): ?>
                                <div class="alert-indicator" style="top: <?php echo $property['unread_messages'] > 0 ? '48px' : '12px'; ?>;">
                                    <span class="badge bg-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Factura pendiente
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badge de tipo -->
                            <div class="property-badge">
                                <?php echo getTransactionTypeBadge($property['transaction_type']); ?>
                            </div>
                            
                            <!-- Imagen principal -->
                            <?php if ($property['property_image']): ?>
                                <img src="<?php echo htmlspecialchars($property['property_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($property['property_title']); ?>">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i class="fas fa-image fa-4x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badge de galería -->
                            <?php if ($property['total_images'] > 1): ?>
                                <div class="property-gallery-badge">
                                    <i class="fas fa-images me-1"></i> <?php echo $property['total_images']; ?> fotos
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contenido -->
                        <div class="p-3">
                            <!-- Título y Referencia -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0"><?php echo htmlspecialchars($property['property_title']); ?></h5>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($property['property_reference']); ?></span>
                            </div>
                            
                            <!-- Ubicación -->
                            <p class="text-muted mb-2">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($property['property_address']); ?>, 
                                <?php echo htmlspecialchars($property['property_city']); ?>
                            </p>
                            
                            <!-- Tipo de propiedad -->
                            <?php if ($property['property_type']): ?>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo htmlspecialchars($property['property_type']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Features -->
                            <div class="property-features">
                                <?php if ($property['bedrooms']): ?>
                                    <span class="feature-tag">
                                        <i class="fas fa-bed text-primary"></i> <?php echo $property['bedrooms']; ?> Habs
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($property['bathrooms']): ?>
                                    <span class="feature-tag">
                                        <i class="fas fa-bath text-info"></i> <?php echo $property['bathrooms']; ?> Baños
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($property['area_total']): ?>
                                    <span class="feature-tag">
                                        <i class="fas fa-ruler-combined text-success"></i> <?php echo number_format($property['area_total'], 0); ?>m²
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($property['parking_spaces']): ?>
                                    <span class="feature-tag">
                                        <i class="fas fa-car text-warning"></i> <?php echo $property['parking_spaces']; ?> Parq
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <hr class="my-3">
                            
                            <!-- Información de Transacción -->
                            <div class="mb-3">
                                <?php if ($property['transaction_type'] == 'sale'): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">Precio de Venta</small>
                                        <strong class="text-success fs-5">$<?php echo number_format($property['sale_price'], 2); ?></strong>
                                    </div>
                                    <?php if ($property['closing_date']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-check me-1"></i>
                                            Cerrado: <?php echo date('d/m/Y', strtotime($property['closing_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">Renta Mensual</small>
                                        <strong class="text-primary fs-5">$<?php echo number_format($property['monthly_payment'], 2); ?></strong>
                                    </div>
                                    <?php if ($property['rent_end_date']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-times me-1"></i>
                                            Vence: <?php echo date('d/m/Y', strtotime($property['rent_end_date'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Estado de Pago -->
                            <div class="mb-3">
                                <?php
                                $paymentBadges = [
                                    'pending' => '<span class="badge bg-warning w-100">Pago Pendiente</span>',
                                    'partial' => '<span class="badge bg-info w-100">Pago Parcial</span>',
                                    'completed' => '<span class="badge bg-success w-100">Pago Completo</span>',
                                    'overdue' => '<span class="badge bg-danger w-100">Pago Atrasado</span>',
                                ];
                                echo $paymentBadges[$property['payment_status']] ?? '<span class="badge bg-secondary w-100">Desconocido</span>';
                                ?>
                            </div>
                            
                            <!-- Botón Principal -->
                            <a href="<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $property['property_id']; ?>" 
                               class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-eye me-2"></i> Ver Detalles Completos
                            </a>
                            
                            <!-- Acciones Rápidas -->
                            <div class="d-flex gap-2">
                                <button class="quick-action-btn flex-fill position-relative" 
                                        onclick="window.location.href='<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $property['property_id']; ?>#documentos'"
                                        title="Documentos">
                                    <i class="fas fa-folder-open"></i>
                                    <?php if ($property['document_count'] > 0): ?>
                                        <span class="notification-badge"><?php echo $property['document_count']; ?></span>
                                    <?php endif; ?>
                                </button>
                                
                                <button class="quick-action-btn flex-fill position-relative" 
                                        onclick="window.location.href='<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $property['property_id']; ?>#mensajes'"
                                        title="Mensajes">
                                    <i class="fas fa-comments"></i>
                                    <?php if ($property['unread_messages'] > 0): ?>
                                        <span class="notification-badge"><?php echo $property['unread_messages']; ?></span>
                                    <?php endif; ?>
                                </button>
                                
                                <button class="quick-action-btn flex-fill position-relative" 
                                        onclick="window.location.href='<?php echo url('clientes/facturas.php'); ?>?property=<?php echo $property['property_id']; ?>'"
                                        title="Facturas">
                                    <i class="fas fa-file-invoice"></i>
                                    <?php if ($property['pending_invoices'] > 0): ?>
                                        <span class="notification-badge"><?php echo $property['pending_invoices']; ?></span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Barra de Stats -->
                        <div class="property-stats-bar">
                            <div class="property-stat-item text-muted">
                                <i class="fas fa-file"></i>
                                <span><?php echo $property['document_count']; ?> docs</span>
                            </div>
                            <div class="property-stat-item text-muted">
                                <i class="fas fa-comment"></i>
                                <span><?php echo $property['unread_messages']; ?> mensajes</span>
                            </div>
                            <div class="property-stat-item text-muted">
                                <i class="fas fa-receipt"></i>
                                <span><?php echo $property['invoice_count']; ?> facturas</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    <?php else: ?>
        <!-- VISTA EN LISTA -->
        <?php foreach ($properties as $property): ?>
            <div class="property-list-view">
                <div class="row align-items-center">
                    <!-- Imagen -->
                    <div class="col-md-2">
                        <?php if ($property['property_image']): ?>
                            <img src="<?php echo htmlspecialchars($property['property_image']); ?>" 
                                 class="img-fluid rounded" 
                                 style="height: 120px; width: 100%; object-fit: cover;"
                                 alt="<?php echo htmlspecialchars($property['property_title']); ?>">
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 120px;">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Información Principal -->
                    <div class="col-md-5">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <h5 class="mb-0"><?php echo htmlspecialchars($property['property_title']); ?></h5>
                            <?php echo getTransactionTypeBadge($property['transaction_type']); ?>
                            <?php if ($property['unread_messages'] > 0): ?>
                                <span class="badge bg-danger">
                                    <?php echo $property['unread_messages']; ?> nuevo(s)
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="text-muted mb-2">
                            <i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo htmlspecialchars($property['property_address']); ?>, 
                            <?php echo htmlspecialchars($property['property_city']); ?>
                        </p>
                        
                        <div class="d-flex gap-3 mb-2">
                            <?php if ($property['bedrooms']): ?>
                                <span class="text-muted small">
                                    <i class="fas fa-bed me-1"></i> <?php echo $property['bedrooms']; ?> Habitaciones
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($property['bathrooms']): ?>
                                <span class="text-muted small">
                                    <i class="fas fa-bath me-1"></i> <?php echo $property['bathrooms']; ?> Baños
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($property['area_total']): ?>
                                <span class="text-muted small">
                                    <i class="fas fa-ruler-combined me-1"></i> <?php echo number_format($property['area_total'], 0); ?>m²
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($property['property_reference']); ?></span>
                            <?php if ($property['property_type']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($property['property_type']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Información Financiera -->
                    <div class="col-md-2 text-center">
                        <?php if ($property['transaction_type'] == 'sale'): ?>
                            <small class="text-muted d-block mb-1">Precio de Venta</small>
                            <h4 class="text-success mb-0">$<?php echo number_format($property['sale_price'], 0); ?></h4>
                        <?php else: ?>
                            <small class="text-muted d-block mb-1">Renta Mensual</small>
                            <h4 class="text-primary mb-0">$<?php echo number_format($property['monthly_payment'], 0); ?></h4>
                        <?php endif; ?>
                        
                        <div class="mt-2">
                            <?php
                            $paymentBadges = [
                                'pending' => '<span class="badge bg-warning">Pendiente</span>',
                                'partial' => '<span class="badge bg-info">Parcial</span>',
                                'completed' => '<span class="badge bg-success">Completo</span>',
                                'overdue' => '<span class="badge bg-danger">Atrasado</span>',
                            ];
                            echo $paymentBadges[$property['payment_status']] ?? '<span class="badge bg-secondary">N/A</span>';
                            ?>
                        </div>
                    </div>
                    
                    <!-- Estadísticas -->
                    <div class="col-md-2 text-center">
                        <div class="d-flex flex-column gap-2">
                            <div>
                                <i class="fas fa-file text-muted me-1"></i>
                                <strong><?php echo $property['document_count']; ?></strong>
                                <small class="text-muted d-block">Documentos</small>
                            </div>
                            <div>
                                <i class="fas fa-receipt text-muted me-1"></i>
                                <strong><?php echo $property['invoice_count']; ?></strong>
                                <small class="text-muted d-block">Facturas</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Acciones -->
                    <div class="col-md-1 text-end">
                        <a href="<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $property['property_id']; ?>" 
                           class="btn btn-primary btn-sm mb-2 w-100">
                            <i class="fas fa-eye"></i>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm w-100 dropdown-toggle" 
                                    type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $property['property_id']; ?>#documentos">
                                        <i class="fas fa-folder-open me-2"></i> Documentos
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $property['property_id']; ?>#mensajes">
                                        <i class="fas fa-comments me-2"></i> Mensajes
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo url('clientes/facturas.php'); ?>?property=<?php echo $property['property_id']; ?>">
                                        <i class="fas fa-file-invoice me-2"></i> Facturas
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
<?php endif; ?>

<?php
$additionalScripts = <<<'HTML'
<script>
// Auto-submit form on select change (opcional)
document.querySelectorAll('.filter-bar select').forEach(select => {
    select.addEventListener('change', function() {
        // Opcional: auto-submit
        // this.closest('form').submit();
    });
});

// Confirm before actions
function confirmAction(message) {
    return confirm(message || '¿Estás seguro de realizar esta acción?');
}

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>
HTML;

include 'includes/footer.php';
?>
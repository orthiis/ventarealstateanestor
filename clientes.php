<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('clients.title');
$currentUser = getCurrentUser();

// ========== FILTROS Y BÚSQUEDA ==========
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$agentFilter = $_GET['agent'] ?? '';
$sourceFilter = $_GET['source'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// ========== CONSTRUIR QUERY CON FILTROS ==========
$whereConditions = ["c.status != 'deleted'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(c.reference LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($typeFilter)) {
    $whereConditions[] = "c.client_type = ?";
    $params[] = $typeFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "c.status = ?";
    $params[] = $statusFilter;
}

if (!empty($agentFilter)) {
    $whereConditions[] = "c.agent_id = ?";
    $params[] = $agentFilter;
}

if (!empty($sourceFilter)) {
    $whereConditions[] = "c.source = ?";
    $params[] = $sourceFilter;
}

$whereClause = implode(' AND ', $whereConditions);

// Contar total de registros
$totalClients = (int)db()->selectValue(
    "SELECT COUNT(*) FROM clients c WHERE $whereClause",
    $params
);

$totalPages = ceil($totalClients / $perPage);

// Obtener clientes
$clients = db()->select(
    "SELECT c.*, 
     CONCAT(u.first_name, ' ', u.last_name) as agent_name
     FROM clients c
     LEFT JOIN users u ON c.agent_id = u.id
     WHERE $whereClause
     ORDER BY c.$sortBy $sortOrder
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// ========== OBTENER DATOS PARA FILTROS ==========
$agents = db()->select("SELECT id, first_name, last_name FROM users WHERE role IN ('admin', 'agent') AND status = 'active' ORDER BY first_name ASC");

$sources = db()->select(
    "SELECT DISTINCT source FROM clients 
     WHERE source IS NOT NULL AND source != '' AND status != 'deleted'
     ORDER BY source ASC"
);

// ========== ESTADÍSTICAS RÁPIDAS ==========
$stats = [
    'total' => (int)db()->selectValue("SELECT COUNT(*) FROM clients WHERE status != 'deleted'"),
    'active' => (int)db()->selectValue("SELECT COUNT(*) FROM clients WHERE status = 'active'"),
    'leads' => (int)db()->selectValue("SELECT COUNT(*) FROM clients WHERE status = 'lead'"),
    'buyers' => (int)db()->selectValue("SELECT COUNT(*) FROM clients WHERE client_type = 'buyer' OR client_type = 'both'"),
    'sellers' => (int)db()->selectValue("SELECT COUNT(*) FROM clients WHERE client_type = 'seller' OR client_type = 'both'")
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

    .clients-container {
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
    .stat-box.active { border-left-color: var(--success); }
    .stat-box.leads { border-left-color: var(--warning); }
    .stat-box.buyers { border-left-color: var(--info); }
    .stat-box.sellers { border-left-color: var(--purple); }

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
        padding: 25px;
        border-radius: 16px;
        margin-bottom: 25px;
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

    /* Clients Grid */
    .clients-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .client-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        position: relative;
    }

    .client-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.15);
    }

    .client-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 25px 20px;
        text-align: center;
        position: relative;
    }

    .client-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: white;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 700;
        margin: 0 auto 15px;
        border: 4px solid rgba(255, 255, 255, 0.3);
    }

    .client-name {
        color: white;
        font-size: 20px;
        font-weight: 700;
        margin: 0 0 5px 0;
    }

    .client-reference {
        color: rgba(255, 255, 255, 0.9);
        font-size: 12px;
        font-weight: 600;
    }

    .client-badge {
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

    .badge-active {
        background: rgba(16, 185, 129, 0.9);
        color: white;
    }

    .badge-inactive {
        background: rgba(107, 114, 128, 0.9);
        color: white;
    }

    .badge-lead {
        background: rgba(245, 158, 11, 0.9);
        color: white;
    }

    .client-type-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        background: rgba(255, 255, 255, 0.25);
        color: white;
        backdrop-filter: blur(10px);
    }

    .client-content {
        padding: 20px;
    }

    .client-info-item {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        font-size: 14px;
        color: #4b5563;
    }

    .client-info-item i {
        width: 20px;
        color: var(--primary);
    }

    .client-info-item a {
        color: #4b5563;
        text-decoration: none;
    }

    .client-info-item a:hover {
        color: var(--primary);
    }

    .client-footer {
        padding: 15px 20px;
        border-top: 1px solid #f3f4f6;
        display: flex;
        gap: 10px;
    }

    .client-footer .btn-modern {
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

    /* Responsive */
    @media (max-width: 1024px) {
        .clients-container {
            padding: 20px;
        }

        .quick-stats {
            grid-template-columns: repeat(3, 1fr);
        }

        .clients-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
    }

    @media (max-width: 768px) {
        .clients-container {
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

        .clients-grid {
            grid-template-columns: 1fr;
            gap: 15px;
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
        .clients-container {
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

        .client-content {
            padding: 15px;
        }
    }
</style>

<div class="clients-container">
    
    <!-- Page Header -->
    <div class="page-header-modern">
        <div style="flex: 1;">
            <h1 class="page-title-modern">
                <i class="fas fa-users" style="color: var(--primary);"></i>
                <?php echo __('clients.title'); ?>
            </h1>
            <p class="page-subtitle-modern">
                <?php echo __('clients.subtitle'); ?>
            </p>
        </div>
        <div>
            <a href="nuevo-cliente.php" class="btn-modern btn-primary-modern">
                <i class="fas fa-user-plus"></i>
                <?php echo __('clients.add_client'); ?>
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="stat-box total">
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label"><?php echo __('clients.stats.total'); ?></div>
        </div>
        <div class="stat-box active">
            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
            <div class="stat-label"><?php echo __('clients.stats.active'); ?></div>
        </div>
        <div class="stat-box leads">
            <div class="stat-value"><?php echo number_format($stats['leads']); ?></div>
            <div class="stat-label"><?php echo __('clients.stats.leads'); ?></div>
        </div>
        <div class="stat-box buyers">
            <div class="stat-value"><?php echo number_format($stats['buyers']); ?></div>
            <div class="stat-label"><?php echo __('clients.stats.buyers'); ?></div>
        </div>
        <div class="stat-box sellers">
            <div class="stat-value"><?php echo number_format($stats['sellers']); ?></div>
            <div class="stat-label"><?php echo __('clients.stats.sellers'); ?></div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="filters-card">
        <div class="filters-header">
            <h3 class="filters-title">
                <i class="fas fa-filter"></i>
                <?php echo __('clients.filters.title'); ?>
            </h3>
            <?php if (!empty($search) || !empty($typeFilter) || !empty($statusFilter) || !empty($agentFilter) || !empty($sourceFilter)): ?>
            <a href="clientes.php" class="btn-modern btn-outline-modern btn-sm">
                <i class="fas fa-times"></i>
                <?php echo __('clients.filters.clear'); ?>
            </a>
            <?php endif; ?>
        </div>

        <form method="GET" action="clientes.php" id="filtersForm">
            <div class="filters-grid">
                <!-- Search -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('clients.filters.search'); ?></label>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="<?php echo __('clients.filters.search_placeholder'); ?>"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <!-- Type -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('clients.filters.type'); ?></label>
                    <select name="type" class="form-select">
                        <option value=""><?php echo __('clients.filters.all'); ?></option>
                        <option value="buyer" <?php echo $typeFilter === 'buyer' ? 'selected' : ''; ?>>
                            <?php echo __('clients.type.buyer'); ?>
                        </option>
                        <option value="seller" <?php echo $typeFilter === 'seller' ? 'selected' : ''; ?>>
                            <?php echo __('clients.type.seller'); ?>
                        </option>
                        <option value="renter" <?php echo $typeFilter === 'renter' ? 'selected' : ''; ?>>
                            <?php echo __('clients.type.renter'); ?>
                        </option>
                        <option value="investor" <?php echo $typeFilter === 'investor' ? 'selected' : ''; ?>>
                            <?php echo __('clients.type.investor'); ?>
                        </option>
                        <option value="both" <?php echo $typeFilter === 'both' ? 'selected' : ''; ?>>
                            <?php echo __('clients.type.both'); ?>
                        </option>
                    </select>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('clients.filters.status'); ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?php echo __('clients.filters.all'); ?></option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>
                            <?php echo __('clients.status.active'); ?>
                        </option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>
                            <?php echo __('clients.status.inactive'); ?>
                        </option>
                        <option value="lead" <?php echo $statusFilter === 'lead' ? 'selected' : ''; ?>>
                            <?php echo __('clients.status.lead'); ?>
                        </option>
                    </select>
                </div>

                <!-- Agent -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('clients.filters.agent'); ?></label>
                    <select name="agent" class="form-select">
                        <option value=""><?php echo __('clients.filters.all'); ?></option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>" <?php echo $agentFilter == $agent['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Source -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('clients.filters.source'); ?></label>
                    <select name="source" class="form-select">
                        <option value=""><?php echo __('clients.filters.all'); ?></option>
                        <?php foreach ($sources as $source): ?>
                        <option value="<?php echo htmlspecialchars($source['source']); ?>" <?php echo $sourceFilter === $source['source'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($source['source']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sort By -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('clients.filters.sort_by'); ?></label>
                    <select name="sort" class="form-select">
                        <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>
                            <?php echo __('clients.filters.sort.newest'); ?>
                        </option>
                        <option value="first_name" <?php echo $sortBy === 'first_name' ? 'selected' : ''; ?>>
                            <?php echo __('clients.filters.sort.name'); ?>
                        </option>
                        <option value="last_contact" <?php echo $sortBy === 'last_contact' ? 'selected' : ''; ?>>
                            <?php echo __('clients.filters.sort.last_contact'); ?>
                        </option>
                    </select>
                </div>

                <!-- Order -->
                <div class="form-group">
                    <label class="form-label"><?php echo __('clients.filters.order'); ?></label>
                    <select name="order" class="form-select">
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>
                            <?php echo __('clients.filters.order.desc'); ?>
                        </option>
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>
                            <?php echo __('clients.filters.order.asc'); ?>
                        </option>
                    </select>
                </div>
            </div>

            <div class="filters-actions">
                <button type="submit" class="btn-modern btn-primary-modern">
                    <i class="fas fa-search"></i>
                    <?php echo __('clients.filters.apply'); ?>
                </button>
                <a href="clientes.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-redo"></i>
                    <?php echo __('clients.filters.reset'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Results Info -->
    <?php if ($totalClients > 0): ?>
    <div class="results-info">
        <div class="results-text">
            <?php echo __('clients.results.showing'); ?> 
            <strong><?php echo min($offset + 1, $totalClients); ?></strong> 
            <?php echo __('clients.results.to'); ?> 
            <strong><?php echo min($offset + $perPage, $totalClients); ?></strong> 
            <?php echo __('clients.results.of'); ?> 
            <strong><?php echo number_format($totalClients); ?></strong> 
            <?php echo __('clients.results.clients'); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Clients Grid -->
    <?php if (empty($clients)): ?>
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <h3><?php echo __('clients.empty.title'); ?></h3>
        <p><?php echo __('clients.empty.description'); ?></p>
        <a href="nuevo-cliente.php" class="btn-modern btn-primary-modern">
            <i class="fas fa-user-plus"></i>
            <?php echo __('clients.empty.add_first'); ?>
        </a>
    </div>
    <?php else: ?>
    <div class="clients-grid">
        <?php foreach ($clients as $client): ?>
        <div class="client-card">
            <!-- Header -->
            <div class="client-header">
                <!-- Avatar -->
                <div class="client-avatar">
                    <?php 
                    $initials = '';
                    if ($client['first_name']) {
                        $initials .= strtoupper(substr($client['first_name'], 0, 1));
                    }
                    if ($client['last_name']) {
                        $initials .= strtoupper(substr($client['last_name'], 0, 1));
                    }
                    echo $initials ?: '?';
                    ?>
                </div>

                <h3 class="client-name">
                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                </h3>
                <div class="client-reference">
                    <?php echo __('clients.card.reference'); ?>: <?php echo htmlspecialchars($client['reference']); ?>
                </div>

                <!-- Status Badge -->
                <?php 
                $statusLabels = [
                    'active' => __('clients.status.active'),
                    'inactive' => __('clients.status.inactive'),
                    'lead' => __('clients.status.lead')
                ];
                $statusLabel = $statusLabels[$client['status']] ?? ucfirst($client['status']);
                ?>
                <span class="client-badge badge-<?php echo $client['status']; ?>">
                    <?php echo $statusLabel; ?>
                </span>

                <!-- Type Badge -->
                <?php if ($client['client_type']): 
                $typeLabels = [
                    'buyer' => __('clients.type.buyer'),
                    'seller' => __('clients.type.seller'),
                    'renter' => __('clients.type.renter'),
                    'investor' => __('clients.type.investor'),
                    'both' => __('clients.type.both')
                ];
                $typeLabel = $typeLabels[$client['client_type']] ?? ucfirst($client['client_type']);
                ?>
                <span class="client-type-badge">
                    <?php echo $typeLabel; ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="client-content">
                <?php if ($client['email']): ?>
                <div class="client-info-item">
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>">
                        <?php echo htmlspecialchars($client['email']); ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($client['phone']): ?>
                <div class="client-info-item">
                    <i class="fas fa-phone"></i>
                    <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>">
                        <?php echo htmlspecialchars($client['phone']); ?>
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($client['agent_name']): ?>
                <div class="client-info-item">
                    <i class="fas fa-user-tie"></i>
                    <span><?php echo htmlspecialchars($client['agent_name']); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($client['source']): ?>
                <div class="client-info-item">
                    <i class="fas fa-bullhorn"></i>
                    <span><?php echo htmlspecialchars($client['source']); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($client['last_contact']): ?>
                <div class="client-info-item">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo __('clients.card.last_contact'); ?>: <?php echo date('d/m/Y', strtotime($client['last_contact'])); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="client-footer">
                <a href="ver-cliente.php?id=<?php echo $client['id']; ?>" 
                   class="btn-modern btn-outline-modern btn-sm">
                    <i class="fas fa-eye"></i>
                    <?php echo __('clients.card.view'); ?>
                </a>
                <a href="editar-cliente.php?id=<?php echo $client['id']; ?>" 
                   class="btn-modern btn-primary-modern btn-sm">
                    <i class="fas fa-edit"></i>
                    <?php echo __('clients.card.edit'); ?>
                </a>
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
                        <span class="d-none d-sm-inline"><?php echo __('clients.pagination.previous'); ?></span>
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
                        <span class="d-none d-sm-inline"><?php echo __('clients.pagination.next'); ?></span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('projects.title', [], 'Obras y Restauración');
$currentUser = getCurrentUser();

// Filtros
$filterStatus = $_GET['status'] ?? 'all';
$filterSearch = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Obtener estadísticas generales
$stats = [];

// Total de proyectos activos
$stats['active_projects'] = (int)db()->selectValue(
    "SELECT COUNT(*) FROM restoration_projects WHERE project_status IN ('Planificación', 'En Progreso')"
);

// Inversión total en curso
$stats['total_investment'] = (float)db()->selectValue(
    "SELECT SUM(total_investment) FROM restoration_projects WHERE project_status IN ('Planificación', 'En Progreso')"
) ?: 0;

// Total gastado
$stats['total_spent'] = (float)db()->selectValue(
    "SELECT SUM(total_spent) FROM restoration_projects WHERE project_status IN ('Planificación', 'En Progreso')"
) ?: 0;

// Total presupuesto
$stats['total_budget'] = (float)db()->selectValue(
    "SELECT SUM(total_budget) FROM restoration_projects WHERE project_status IN ('Planificación', 'En Progreso')"
) ?: 0;

// Proyectos completados este mes
$stats['completed_this_month'] = (int)db()->selectValue(
    "SELECT COUNT(*) FROM restoration_projects 
     WHERE project_status = 'Completado' 
     AND MONTH(actual_end_date) = MONTH(CURRENT_DATE()) 
     AND YEAR(actual_end_date) = YEAR(CURRENT_DATE())"
);

// Alertas de sobrecostos
$stats['cost_overrun_alerts'] = (int)db()->selectValue(
    "SELECT COUNT(*) FROM restoration_projects 
     WHERE project_status IN ('Planificación', 'En Progreso')
     AND total_spent > (total_budget * (1 + tolerance_margin / 100))"
);

// Alertas de retrasos
$stats['delay_alerts'] = (int)db()->selectValue(
    "SELECT COUNT(*) FROM restoration_projects 
     WHERE project_status = 'En Progreso'
     AND estimated_end_date < CURDATE()
     AND actual_end_date IS NULL"
);

// Distribución de proyectos por estado
$statusDistribution = db()->select(
    "SELECT project_status, COUNT(*) as count 
     FROM restoration_projects 
     GROUP BY project_status
     ORDER BY 
        CASE project_status
            WHEN 'En Progreso' THEN 1
            WHEN 'Planificación' THEN 2
            WHEN 'Pausado' THEN 3
            WHEN 'Completado' THEN 4
            WHEN 'Cancelado' THEN 5
        END"
);

// Construir query base
$whereConditions = ["1=1"];
$params = [];

// Aplicar filtro de estado
if ($filterStatus !== 'all') {
    $whereConditions[] = "rp.project_status = ?";
    $params[] = $filterStatus;
}

// Aplicar filtro de búsqueda
if (!empty($filterSearch)) {
    $whereConditions[] = "(rp.project_name LIKE ? OR rp.project_reference LIKE ? OR rp.address LIKE ?)";
    $searchParam = "%{$filterSearch}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Aplicar filtro de fechas
if (!empty($filterDateFrom)) {
    $whereConditions[] = "rp.estimated_start_date >= ?";
    $params[] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $whereConditions[] = "rp.estimated_start_date <= ?";
    $params[] = $filterDateTo;
}

$whereClause = implode(" AND ", $whereConditions);

// Contar total de registros
$totalRecords = (int)db()->selectValue(
    "SELECT COUNT(*) 
     FROM restoration_projects rp
     WHERE $whereClause",
    $params
);

$totalPages = ceil($totalRecords / $perPage);

// Obtener proyectos con paginación
$projects = db()->select(
    "SELECT rp.*, 
     p.reference as property_reference,
     p.title as property_title,
     CONCAT(c.first_name, ' ', c.last_name) as client_name,
     CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
     (SELECT COUNT(*) FROM project_expenses WHERE project_id = rp.id) as expense_count,
     (SELECT COUNT(*) FROM project_phases WHERE project_id = rp.id) as phase_count
     FROM restoration_projects rp
     LEFT JOIN properties p ON rp.property_id = p.id
     LEFT JOIN clients c ON rp.client_id = c.id
     LEFT JOIN users u ON rp.created_by = u.id
     WHERE $whereClause
     ORDER BY rp.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Calcular datos adicionales para cada proyecto
foreach ($projects as &$project) {
    $project['budget_remaining'] = $project['total_budget'] - $project['total_spent'];
    $project['budget_percentage_used'] = $project['total_budget'] > 0 
        ? ($project['total_spent'] / $project['total_budget']) * 100 
        : 0;
    
    $project['has_cost_overrun'] = $project['total_spent'] > ($project['total_budget'] * (1 + $project['tolerance_margin'] / 100));
    
    $project['has_delay'] = ($project['project_status'] == 'En Progreso' && 
                             $project['estimated_end_date'] && 
                             strtotime($project['estimated_end_date']) < time() && 
                             !$project['actual_end_date']);
    
    if ($project['has_delay']) {
        $today = new DateTime();
        $estimatedEnd = new DateTime($project['estimated_end_date']);
        $project['days_delayed'] = $today->diff($estimatedEnd)->days;
    } else {
        $project['days_delayed'] = 0;
    }
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    :root {
        --primary: #667eea;
        --primary-dark: #5a67d8;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --purple: #8b5cf6;
    }

    .obras-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    /* Page Header */
    .page-header-obras {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .page-header-obras::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .page-title-obras {
        font-size: 32px;
        font-weight: 700;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 15px;
        position: relative;
        z-index: 1;
    }

    .page-title-obras i {
        font-size: 36px;
    }

    .page-subtitle-obras {
        font-size: 16px;
        opacity: 0.9;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .btn-primary-obras {
        background: white;
        color: var(--primary);
        padding: 14px 28px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 1;
    }

    .btn-primary-obras:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        color: var(--primary-dark);
    }

    /* Stats Grid */
    .stats-grid-obras {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card-obras {
        background: white;
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card-obras::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .stat-card-obras.primary::before {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }

    .stat-card-obras.success::before {
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    }

    .stat-card-obras.info::before {
        background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
    }

    .stat-card-obras.warning::before {
        background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
    }

    .stat-card-obras.danger::before {
        background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
    }

    .stat-card-obras:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }

    .stat-icon-obras {
        width: 64px;
        height: 64px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }

    .stat-card-obras.primary .stat-icon-obras {
        background: #ede9fe;
        color: #7c3aed;
    }

    .stat-card-obras.success .stat-icon-obras {
        background: #d1fae5;
        color: #065f46;
    }

    .stat-card-obras.info .stat-icon-obras {
        background: #dbeafe;
        color: #1e40af;
    }

    .stat-card-obras.warning .stat-icon-obras {
        background: #fef3c7;
        color: #92400e;
    }

    .stat-card-obras.danger .stat-icon-obras {
        background: #fee2e2;
        color: #991b1b;
    }

    .stat-content-obras {
        flex: 1;
    }

    .stat-value-obras {
        font-size: 28px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 6px;
    }

    .stat-card-obras.primary .stat-value-obras {
        color: #7c3aed;
    }

    .stat-card-obras.success .stat-value-obras {
        color: #065f46;
    }

    .stat-card-obras.info .stat-value-obras {
        color: #1e40af;
    }

    .stat-card-obras.warning .stat-value-obras {
        color: #92400e;
    }

    .stat-card-obras.danger .stat-value-obras {
        color: #991b1b;
    }

    .stat-label-obras {
        font-size: 13px;
        color: #6b7280;
        font-weight: 600;
    }

    .stat-change {
        font-size: 12px;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .stat-change.positive {
        color: var(--success);
    }

    .stat-change.negative {
        color: var(--danger);
    }

    /* Filters Section */
    .filters-section-obras {
        background: white;
        padding: 24px;
        border-radius: 16px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .filters-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-label {
        font-weight: 600;
        font-size: 13px;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-input, .filter-select {
        padding: 10px 14px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .filter-input:focus, .filter-select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .filter-actions {
        display: flex;
        gap: 10px;
    }

    .btn-filter {
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-filter-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-filter-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-filter-secondary {
        background: white;
        border: 2px solid #e5e7eb;
        color: #6b7280;
    }

    .btn-filter-secondary:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    /* Status Tabs */
    .status-tabs-obras {
        display: flex;
        gap: 10px;
        margin-bottom: 24px;
        overflow-x: auto;
        padding-bottom: 10px;
    }

    .status-tab {
        padding: 12px 24px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        background: white;
        color: #6b7280;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-tab:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f9fafb;
    }

    .status-tab.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: var(--primary);
        color: white;
    }

    .status-tab-badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.2);
    }

    .status-tab.active .status-tab-badge {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Projects Grid */
    .projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }

    .project-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .project-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }

    .project-header {
        padding: 20px;
        border-bottom: 1px solid #f3f4f6;
    }

    .project-ref {
        font-size: 12px;
        font-weight: 700;
        color: var(--primary);
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .project-name {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 8px 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .project-client {
        font-size: 13px;
        color: #6b7280;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .project-body {
        padding: 20px;
        flex: 1;
    }

    .project-info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        font-size: 13px;
    }

    .project-info-label {
        color: #6b7280;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .project-info-value {
        font-weight: 600;
        color: #1f2937;
    }

    .project-progress {
        margin: 16px 0;
    }

    .project-progress-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13px;
    }

    .project-progress-label span:first-child {
        color: #6b7280;
        font-weight: 600;
    }

    .project-progress-label span:last-child {
        color: var(--primary);
        font-weight: 700;
    }

    .project-progress-bar {
        height: 10px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
    }

    .project-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        transition: width 0.5s ease;
    }

    .project-alerts {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .project-alert {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .project-alert.warning {
        background: #fef3c7;
        color: #92400e;
    }

    .project-alert.danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .project-footer {
        padding: 16px 20px;
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .project-status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .project-status-badge.planning {
        background: #dbeafe;
        color: #1e40af;
    }

    .project-status-badge.in-progress {
        background: #d1fae5;
        color: #065f46;
    }

    .project-status-badge.paused {
        background: #fef3c7;
        color: #92400e;
    }

    .project-status-badge.completed {
        background: #e0e7ff;
        color: #4338ca;
    }

    .project-status-badge.cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .project-actions {
        display: flex;
        gap: 8px;
    }

    .project-action-btn {
        width: 36px;
        height: 36px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .project-action-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f9fafb;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .empty-state-icon {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 20px;
    }

    .empty-state-title {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 8px;
    }

    .empty-state-text {
        color: #6b7280;
        margin-bottom: 24px;
    }

    /* Pagination */
    .pagination-obras {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 30px;
    }

    .pagination-btn {
        padding: 10px 16px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 10px;
        color: #6b7280;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .pagination-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f9fafb;
    }

    .pagination-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: var(--primary);
        color: white;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .obras-container {
            padding: 15px;
        }

        .page-header-obras {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }

        .page-title-obras {
            font-size: 24px;
        }

        .projects-grid {
            grid-template-columns: 1fr;
        }

        .stats-grid-obras {
            grid-template-columns: 1fr;
        }

        .filters-row {
            grid-template-columns: 1fr;
        }

        .status-tabs-obras {
            flex-wrap: nowrap;
            overflow-x: auto;
        }
    }
</style>

<div class="obras-container">
    
    <!-- Page Header -->
    <div class="page-header-obras">
        <div style="flex: 1;">
            <h1 class="page-title-obras">
                <i class="fas fa-hard-hat"></i>
                <?php echo __('projects.title', [], 'Gestión de Obras y Restauración'); ?>
            </h1>
            <p class="page-subtitle-obras">
                <?php echo __('projects.subtitle', [], 'Control completo de proyectos de restauración y gastos'); ?>
            </p>
        </div>
        <a href="crear-obra.php" class="btn-primary-obras">
            <i class="fas fa-plus"></i>
            <?php echo __('projects.new_project', [], 'Nuevo Proyecto'); ?>
        </a>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid-obras">
        <!-- Proyectos Activos -->
        <div class="stat-card-obras primary">
            <div class="stat-icon-obras">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-content-obras">
                <div class="stat-value-obras"><?php echo number_format($stats['active_projects']); ?></div>
                <div class="stat-label-obras"><?php echo __('active_projects', [], 'Proyectos Activos'); ?></div>
            </div>
        </div>
        
        <!-- Inversión Total -->
        <div class="stat-card-obras success">
            <div class="stat-icon-obras">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content-obras">
                <div class="stat-value-obras">$<?php echo number_format($stats['total_investment'], 0); ?></div>
                <div class="stat-label-obras"><?php echo __('total_investment', [], 'Inversión Total en Curso'); ?></div>
            </div>
        </div>
        
        <!-- Total Gastado -->
        <div class="stat-card-obras info">
            <div class="stat-icon-obras">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content-obras">
                <div class="stat-value-obras">$<?php echo number_format($stats['total_spent'], 0); ?></div>
                <div class="stat-label-obras"><?php echo __('total_spent', [], 'Total Gastado'); ?></div>
                <?php if ($stats['total_budget'] > 0): ?>
                    <div class="stat-change <?php echo $stats['total_spent'] <= $stats['total_budget'] ? 'positive' : 'negative'; ?>">
                        <i class="fas fa-<?php echo $stats['total_spent'] <= $stats['total_budget'] ? 'check' : 'exclamation'; ?>-circle"></i>
                        <?php echo number_format(($stats['total_spent'] / $stats['total_budget']) * 100, 1); ?>% 
                        <?php echo __('of_budget', [], 'del presupuesto'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completados Este Mes -->
        <div class="stat-card-obras warning">
            <div class="stat-icon-obras">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="stat-content-obras">
                <div class="stat-value-obras"><?php echo $stats['completed_this_month']; ?></div>
                <div class="stat-label-obras"><?php echo __('completed_this_month', [], 'Completados Este Mes'); ?></div>
            </div>
        </div>

        <!-- Alertas de Sobrecosto -->
        <div class="stat-card-obras danger">
            <div class="stat-icon-obras">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content-obras">
                <div class="stat-value-obras"><?php echo $stats['cost_overrun_alerts']; ?></div>
                <div class="stat-label-obras"><?php echo __('cost_overruns', [], 'Sobrecostos'); ?></div>
            </div>
        </div>

        <!-- Alertas de Retrasos -->
        <div class="stat-card-obras danger">
            <div class="stat-icon-obras">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content-obras">
                <div class="stat-value-obras"><?php echo $stats['delay_alerts']; ?></div>
                <div class="stat-label-obras"><?php echo __('delays', [], 'Retrasos'); ?></div>
            </div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="status-tabs-obras">
        <a href="?status=all" class="status-tab <?php echo $filterStatus === 'all' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i>
            <?php echo __('all', [], 'Todos'); ?>
            <span class="status-tab-badge"><?php echo $totalRecords; ?></span>
        </a>
        <?php foreach ($statusDistribution as $statusItem): 
            $statusKey = strtolower(str_replace(' ', '-', $statusItem['project_status']));
        ?>
        <a href="?status=<?php echo urlencode($statusItem['project_status']); ?>" 
           class="status-tab <?php echo $filterStatus === $statusItem['project_status'] ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($statusItem['project_status']); ?>
            <span class="status-tab-badge"><?php echo $statusItem['count']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filters Section -->
    <div class="filters-section-obras">
        <form method="GET" action="obras.php">
            <div class="filters-row">
                <div class="filter-group">
                    <label class="filter-label">
                        <i class="fas fa-search"></i>
                        <?php echo __('search', [], 'Buscar'); ?>
                    </label>
                    <input type="text" 
                           name="search" 
                           class="filter-input" 
                           placeholder="<?php echo __('search_placeholder', [], 'Nombre, referencia o dirección...'); ?>"
                           value="<?php echo htmlspecialchars($filterSearch); ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label">
                        <i class="fas fa-calendar"></i>
                        <?php echo __('from_date', [], 'Desde Fecha'); ?>
                    </label>
                    <input type="date" 
                           name="date_from" 
                           class="filter-input"
                           value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>

                <div class="filter-group">
                    <label class="filter-label">
                        <i class="fas fa-calendar"></i>
                        <?php echo __('to_date', [], 'Hasta Fecha'); ?>
                    </label>
                    <input type="date" 
                           name="date_to" 
                           class="filter-input"
                           value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>

                <div class="filter-group" style="justify-content: flex-end;">
                    <label class="filter-label" style="opacity: 0;">.</label>
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter btn-filter-primary">
                            <i class="fas fa-filter"></i>
                            <?php echo __('filter', [], 'Filtrar'); ?>
                        </button>
                        <a href="obras.php" class="btn-filter btn-filter-secondary">
                            <i class="fas fa-times"></i>
                            <?php echo __('clear', [], 'Limpiar'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Projects Grid -->
    <?php if (empty($projects)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fas fa-hard-hat"></i>
        </div>
        <h3 class="empty-state-title">
            <?php echo __('no_projects', [], 'No hay proyectos'); ?>
        </h3>
        <p class="empty-state-text">
            <?php if (!empty($filterSearch) || $filterStatus !== 'all'): ?>
                <?php echo __('no_projects_filters', [], 'No se encontraron proyectos con los filtros aplicados'); ?>
            <?php else: ?>
                <?php echo __('start_first_project', [], 'Comienza creando tu primer proyecto de restauración'); ?>
            <?php endif; ?>
        </p>
        <a href="crear-obra.php" class="btn-primary-obras">
            <i class="fas fa-plus"></i>
            <?php echo __('create_first_project', [], 'Crear Primer Proyecto'); ?>
        </a>
    </div>
    <?php else: ?>
    <div class="projects-grid">
        <?php foreach ($projects as $project): 
            // Determinar clase de estado
            $statusClass = 'planning';
            switch ($project['project_status']) {
                case 'En Progreso':
                    $statusClass = 'in-progress';
                    break;
                case 'Pausado':
                    $statusClass = 'paused';
                    break;
                case 'Completado':
                    $statusClass = 'completed';
                    break;
                case 'Cancelado':
                    $statusClass = 'cancelled';
                    break;
            }
        ?>
        <div class="project-card">
            <div class="project-header">
                <div class="project-ref">
                    <i class="fas fa-hashtag"></i>
                    <?php echo htmlspecialchars($project['project_reference']); ?>
                </div>
                <h3 class="project-name">
                    <?php echo htmlspecialchars($project['project_name']); ?>
                </h3>
                <?php if ($project['client_name']): ?>
                <div class="project-client">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($project['client_name']); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="project-body">
                <div class="project-info-row">
                    <span class="project-info-label">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo __('start_date', [], 'Inicio'); ?>:
                    </span>
                    <span class="project-info-value">
                        <?php echo $project['estimated_start_date'] ? date('d/m/Y', strtotime($project['estimated_start_date'])) : '-'; ?>
                    </span>
                </div>

                <div class="project-info-row">
                    <span class="project-info-label">
                        <i class="fas fa-dollar-sign"></i>
                        <?php echo __('projects.budget', [], 'Presupuesto'); ?>:
                    </span>
                    <span class="project-info-value">
                        $<?php echo number_format($project['total_budget'], 0); ?>
                    </span>
                </div>

                <div class="project-info-row">
                    <span class="project-info-label">
                        <i class="fas fa-receipt"></i>
                        <?php echo __('projects.spent', [], 'Gastado'); ?>:
                    </span>
                    <span class="project-info-value" style="color: <?php echo $project['has_cost_overrun'] ? 'var(--danger)' : 'var(--success)'; ?>">
                        $<?php echo number_format($project['total_spent'], 0); ?>
                    </span>
                </div>

                <!-- Progress Bar -->
                <div class="project-progress">
                    <div class="project-progress-label">
                        <span><?php echo __('projects.progress', [], 'Progreso'); ?></span>
                        <span><?php echo number_format($project['overall_progress'], 1); ?>%</span>
                    </div>
                    <div class="project-progress-bar">
                        <div class="project-progress-fill" style="width: <?php echo $project['overall_progress']; ?>%"></div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($project['has_cost_overrun'] || $project['has_delay']): ?>
                <div class="project-alerts">
                    <?php if ($project['has_cost_overrun']): ?>
                    <div class="project-alert danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo __('budget_exceeded', [], 'Sobrecosto'); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($project['has_delay']): ?>
                    <div class="project-alert warning">
                        <i class="fas fa-clock"></i>
                        <?php echo $project['days_delayed']; ?> <?php echo __('days_delay', [], 'días de retraso'); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="project-footer">
                <span class="project-status-badge <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($project['project_status']); ?>
                </span>

                <div class="project-actions">
                    <a href="ver-obra.php?id=<?php echo $project['id']; ?>" 
                       class="project-action-btn"
                       title="<?php echo __('view', [], 'Ver'); ?>">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="editar-obra.php?id=<?php echo $project['id']; ?>" 
                       class="project-action-btn"
                       title="<?php echo __('edit', [], 'Editar'); ?>">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="registrar-gasto.php?project_id=<?php echo $project['id']; ?>" 
                       class="project-action-btn"
                       title="<?php echo __('add_expense', [], 'Registrar Gasto'); ?>">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-obras">
        <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($filterSearch); ?>" 
           class="pagination-btn">
            <i class="fas fa-chevron-left"></i>
            <?php echo __('previous', [], 'Anterior'); ?>
        </a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($filterSearch); ?>" 
           class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($filterStatus); ?>&search=<?php echo urlencode($filterSearch); ?>" 
           class="pagination-btn">
            <?php echo __('next', [], 'Siguiente'); ?>
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>

<script>
// Confirmación de eliminación
function confirmDelete(projectId, projectName) {
    Swal.fire({
        title: '<?php echo __('confirm_delete', [], '¿Está seguro?'); ?>',
        text: `<?php echo __('delete_project_confirm', [], 'Se eliminará el proyecto'); ?> "${projectName}"`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<?php echo __('yes_delete', [], 'Sí, eliminar'); ?>',
        cancelButtonText: '<?php echo __('cancel', [], 'Cancelar'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `eliminar-obra.php?id=${projectId}`;
        }
    });
}

// Auto-submit de filtros con delay
let filterTimeout;
document.querySelectorAll('.filter-input, .filter-select').forEach(input => {
    input.addEventListener('input', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
});
</script>

<?php include 'footer.php'; ?>
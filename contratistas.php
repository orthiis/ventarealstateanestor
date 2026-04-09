<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Gestión de Contratistas';
$currentPage = 'obras.php';

// Filtros
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all';
$filterSearch = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construir query
$whereConditions = ["1=1"];
$params = [];

if ($filterType !== 'all') {
    $whereConditions[] = "contractor_type = ?";
    $params[] = $filterType;
}

if (!empty($filterSearch)) {
    $whereConditions[] = "(name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $searchParam = "%{$filterSearch}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($filterStatus !== 'all') {
    $whereConditions[] = "is_active = ?";
    $params[] = $filterStatus === 'active' ? 1 : 0;
}

$whereClause = implode(" AND ", $whereConditions);

// Contar total
$totalRecords = (int)db()->selectValue(
    "SELECT COUNT(*) FROM contractors WHERE $whereClause",
    $params
);

$totalPages = ceil($totalRecords / $perPage);

// Obtener contratistas
$contractors = db()->select(
    "SELECT c.*,
     (SELECT COUNT(*) FROM project_contractors WHERE contractor_id = c.id) as projects_count
     FROM contractors c
     WHERE $whereClause
     ORDER BY c.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Estadísticas
$stats = [];
$stats['total'] = (int)db()->selectValue("SELECT COUNT(*) FROM contractors WHERE is_active = 1");
$stats['by_type'] = db()->select(
    "SELECT contractor_type, COUNT(*) as count 
     FROM contractors 
     WHERE is_active = 1
     GROUP BY contractor_type
     ORDER BY count DESC"
);

include 'header.php';
include 'sidebar.php';
?>

<style>
    .contratistas-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .page-header-contratistas {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .stats-grid-contratistas {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .stat-card-contratistas {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card-contratistas::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: #667eea;
    }
    
    .stat-label-contratistas {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    
    .stat-value-contratistas {
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
    }
    
    .filters-section-contratistas {
        background: white;
        padding: 20px 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
    }
    
    .filter-row-contratistas {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }
    
    .filter-group-contratistas {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .filter-label-contratistas {
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
    }
    
    .filter-input-contratistas {
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    
    .filter-input-contratistas:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .filter-actions-contratistas {
        display: flex;
        gap: 12px;
    }
    
    .btn-primary-contratistas {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    
    .btn-primary-contratistas:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .btn-secondary-contratistas {
        background: #f3f4f6;
        color: #4b5563;
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary-contratistas:hover {
        background: #e5e7eb;
    }
    
    .contractors-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    
    .contractor-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .contractor-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: linear-gradient(180deg, #667eea, #764ba2);
    }
    
    .contractor-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    .contractor-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }
    
    .contractor-name {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
    }
    
    .contractor-type {
        font-size: 13px;
        color: #6b7280;
        font-weight: 600;
    }
    
    .contractor-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .contractor-status.active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .contractor-status.inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .contractor-info {
        margin-bottom: 16px;
    }
    
    .contractor-info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 0;
        color: #4b5563;
        font-size: 14px;
    }
    
    .contractor-info-item i {
        color: #667eea;
        width: 16px;
    }
    
    .contractor-stats {
        display: flex;
        justify-content: space-between;
        padding-top: 16px;
        border-top: 1px solid #e5e7eb;
        margin-bottom: 16px;
    }
    
    .contractor-stat {
        text-align: center;
    }
    
    .contractor-stat-value {
        font-size: 20px;
        font-weight: 700;
        color: #667eea;
    }
    
    .contractor-stat-label {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        font-weight: 600;
    }
    
    .contractor-actions {
        display: flex;
        gap: 8px;
    }
    
    .contractor-actions a {
        flex: 1;
        padding: 10px;
        border-radius: 8px;
        text-align: center;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .btn-view {
        background: #f3f4f6;
        color: #4b5563;
    }
    
    .btn-view:hover {
        background: #e5e7eb;
    }
    
    .btn-edit {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .btn-edit:hover {
        background: #bfdbfe;
    }
    
    .empty-state-contratistas {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .empty-icon-contratistas {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 20px;
    }
    
    .empty-title-contratistas {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 8px;
    }
    
    .empty-text-contratistas {
        color: #6b7280;
        margin-bottom: 24px;
    }
    
    .pagination-contratistas {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 24px;
    }
    
    .pagination-contratistas a,
    .pagination-contratistas span {
        padding: 10px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .pagination-contratistas a {
        background: white;
        color: #4b5563;
        border: 1px solid #e5e7eb;
    }
    
    .pagination-contratistas a:hover {
        background: #f3f4f6;
    }
    
    .pagination-contratistas .active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
    }
    
    .pagination-contratistas .disabled {
        background: #f9fafb;
        color: #d1d5db;
        cursor: not-allowed;
        border: 1px solid #e5e7eb;
    }
    
    @media (max-width: 768px) {
        .contratistas-container {
            padding: 16px;
        }
        
        .page-header-contratistas {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .stats-grid-contratistas {
            grid-template-columns: 1fr;
        }
        
        .filter-row-contratistas {
            grid-template-columns: 1fr;
        }
        
        .filter-actions-contratistas {
            flex-direction: column;
            width: 100%;
        }
        
        .filter-actions-contratistas button,
        .filter-actions-contratistas a {
            width: 100%;
            justify-content: center;
        }
        
        .contractors-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="contratistas-container">
    
    <!-- Page Header -->
    <div class="page-header-contratistas">
        <div>
            <h1 style="font-size: 28px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-users-cog"></i>
                Gestión de Contratistas
            </h1>
            <p style="color: #6b7280; margin: 0; font-size: 14px;">
                Administra proveedores y contratistas de tus proyectos
            </p>
        </div>
        <a href="crear-contratista.php" class="btn-primary-contratistas">
            <i class="fas fa-plus"></i>
            Nuevo Contratista
        </a>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid-contratistas">
        <div class="stat-card-contratistas">
            <div class="stat-label-contratistas">Total Activos</div>
            <div class="stat-value-contratistas"><?php echo number_format($stats['total']); ?></div>
        </div>
        
        <?php foreach (array_slice($stats['by_type'], 0, 4) as $type): ?>
            <div class="stat-card-contratistas">
                <div class="stat-label-contratistas"><?php echo htmlspecialchars($type['contractor_type']); ?></div>
                <div class="stat-value-contratistas"><?php echo number_format($type['count']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="filters-section-contratistas">
        <form method="GET" action="contratistas.php" id="filterForm">
            <div class="filter-row-contratistas">
                <div class="filter-group-contratistas">
                    <label class="filter-label-contratistas">Tipo de Contratista</label>
                    <select name="type" class="filter-input-contratistas">
                        <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>Todos los Tipos</option>
                        <option value="Arquitecto" <?php echo $filterType === 'Arquitecto' ? 'selected' : ''; ?>>Arquitecto</option>
                        <option value="Ingeniero" <?php echo $filterType === 'Ingeniero' ? 'selected' : ''; ?>>Ingeniero</option>
                        <option value="Albañil" <?php echo $filterType === 'Albañil' ? 'selected' : ''; ?>>Albañil</option>
                        <option value="Electricista" <?php echo $filterType === 'Electricista' ? 'selected' : ''; ?>>Electricista</option>
                        <option value="Plomero" <?php echo $filterType === 'Plomero' ? 'selected' : ''; ?>>Plomero</option>
                        <option value="Pintor" <?php echo $filterType === 'Pintor' ? 'selected' : ''; ?>>Pintor</option>
                        <option value="Carpintero" <?php echo $filterType === 'Carpintero' ? 'selected' : ''; ?>>Carpintero</option>
                        <option value="Proveedor" <?php echo $filterType === 'Proveedor' ? 'selected' : ''; ?>>Proveedor</option>
                        <option value="Otro" <?php echo $filterType === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>
                
                <div class="filter-group-contratistas">
                    <label class="filter-label-contratistas">Búsqueda</label>
                    <input type="text" 
                           name="search" 
                           class="filter-input-contratistas" 
                           placeholder="Buscar por nombre, empresa, teléfono..."
                           value="<?php echo htmlspecialchars($filterSearch); ?>">
                </div>
                
                <div class="filter-group-contratistas">
                    <label class="filter-label-contratistas">Estado</label>
                    <select name="status" class="filter-input-contratistas">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>Todos</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Activos</option>
                        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions-contratistas">
                <button type="submit" class="btn-primary-contratistas">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
                <a href="contratistas.php" class="btn-secondary-contratistas">
                    <i class="fas fa-redo"></i>
                    Limpiar Filtros
                </a>
            </div>
        </form>
    </div>
    
    <!-- Contractors Grid -->
    <?php if (empty($contractors)): ?>
        <div class="empty-state-contratistas">
            <div class="empty-icon-contratistas">
                <i class="fas fa-users-cog"></i>
            </div>
            <h3 class="empty-title-contratistas">No hay contratistas registrados</h3>
            <p class="empty-text-contratistas">
                <?php if (!empty($filterSearch) || $filterType !== 'all' || $filterStatus !== 'all'): ?>
                    No se encontraron contratistas con los filtros aplicados.
                <?php else: ?>
                    Comienza agregando tu primer contratista o proveedor.
                <?php endif; ?>
            </p>
            <a href="crear-contratista.php" class="btn-primary-contratistas">
                <i class="fas fa-plus"></i>
                Agregar Primer Contratista
            </a>
        </div>
    <?php else: ?>
        <div class="contractors-grid">
            <?php foreach ($contractors as $contractor): ?>
                <div class="contractor-card">
                    <div class="contractor-header">
                        <div>
                            <div class="contractor-name"><?php echo htmlspecialchars($contractor['name']); ?></div>
                            <div class="contractor-type"><?php echo htmlspecialchars($contractor['contractor_type']); ?></div>
                        </div>
                        <span class="contractor-status <?php echo $contractor['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $contractor['is_active'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </div>
                    
                    <?php if ($contractor['company_name']): ?>
                        <div style="font-size: 13px; color: #6b7280; margin-bottom: 12px;">
                            <i class="fas fa-building" style="color: #667eea;"></i>
                            <?php echo htmlspecialchars($contractor['company_name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="contractor-info">
                        <?php if ($contractor['phone']): ?>
                            <div class="contractor-info-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($contractor['phone']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($contractor['email']): ?>
                            <div class="contractor-info-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($contractor['email']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($contractor['address']): ?>
                            <div class="contractor-info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars(mb_strimwidth($contractor['address'], 0, 40, '...')); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="contractor-stats">
                        <div class="contractor-stat">
                            <div class="contractor-stat-value"><?php echo $contractor['projects_count']; ?></div>
                            <div class="contractor-stat-label">Proyectos</div>
                        </div>
                        <div class="contractor-stat">
                            <div class="contractor-stat-value">
                                <?php 
                                if ($contractor['rating']) {
                                    echo number_format($contractor['rating'], 1);
                                    echo '<i class="fas fa-star" style="font-size: 14px; margin-left: 2px; color: #f59e0b;"></i>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </div>
                            <div class="contractor-stat-label">Calificación</div>
                        </div>
                    </div>
                    
                    <div class="contractor-actions">
                        <a href="ver-contratista.php?id=<?php echo $contractor['id']; ?>" class="btn-view">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                        <a href="editar-contratista.php?id=<?php echo $contractor['id']; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-contratistas">
                <?php
                $queryString = http_build_query(array_merge($_GET, ['page' => '']));
                $queryString = rtrim($queryString, '=');
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="?<?php echo $queryString; ?>=<?php echo ($page - 1); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">
                        <i class="fas fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                if ($startPage > 1): ?>
                    <a href="?<?php echo $queryString; ?>=1">1</a>
                    <?php if ($startPage > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo $queryString; ?>=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?<?php echo $queryString; ?>=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo $queryString; ?>=<?php echo ($page + 1); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
</div>

<?php include 'footer.php'; ?>
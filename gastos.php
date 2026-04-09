<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Gestión de Gastos';
$currentPage = 'obras.php';

// Filtros
$filterProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$filterExpenseType = isset($_GET['expense_type']) ? (int)$_GET['expense_type'] : 0;
$filterPaymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Obtener proyectos para el filtro
$projects = db()->select(
    "SELECT id, project_reference, project_name 
     FROM restoration_projects 
     ORDER BY created_at DESC"
);

// Obtener tipos de gastos
$expenseTypes = db()->select(
    "SELECT id, type_name 
     FROM expense_types 
     WHERE is_active = 1
     ORDER BY display_order, type_name"
);

// Construir query base
$whereConditions = ["1=1"];
$params = [];

if ($filterProjectId > 0) {
    $whereConditions[] = "pe.project_id = ?";
    $params[] = $filterProjectId;
}

if ($filterExpenseType > 0) {
    $whereConditions[] = "pe.expense_type_id = ?";
    $params[] = $filterExpenseType;
}

if ($filterPaymentStatus !== 'all') {
    $whereConditions[] = "pe.payment_status = ?";
    $params[] = $filterPaymentStatus;
}

if (!empty($filterDateFrom)) {
    $whereConditions[] = "pe.expense_date >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $whereConditions[] = "pe.expense_date <= ?";
    $params[] = $filterDateTo;
}

$whereClause = implode(" AND ", $whereConditions);

// Contar total
$totalRecords = (int)db()->selectValue(
    "SELECT COUNT(*) 
     FROM project_expenses pe
     WHERE $whereClause",
    $params
);

$totalPages = ceil($totalRecords / $perPage);

// Obtener gastos
$expenses = db()->select(
    "SELECT pe.*, 
     et.type_name,
     mt.material_name,
     rp.project_name,
     rp.project_reference,
     c.name as supplier_name,
     CONCAT(u.first_name, ' ', u.last_name) as created_by_name
     FROM project_expenses pe
     LEFT JOIN expense_types et ON pe.expense_type_id = et.id
     LEFT JOIN material_types mt ON pe.material_type_id = mt.id
     LEFT JOIN restoration_projects rp ON pe.project_id = rp.id
     LEFT JOIN contractors c ON pe.supplier_id = c.id
     LEFT JOIN users u ON pe.created_by = u.id
     WHERE $whereClause
     ORDER BY pe.expense_date DESC, pe.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Calcular totales
$totals = db()->selectOne(
    "SELECT 
     SUM(total_amount) as total_amount,
     SUM(CASE WHEN payment_status = 'Pagado' THEN total_amount ELSE 0 END) as paid_amount,
     SUM(CASE WHEN payment_status = 'Pendiente' THEN total_amount ELSE 0 END) as pending_amount
     FROM project_expenses pe
     WHERE $whereClause",
    $params
);

include 'header.php';
include 'sidebar.php';
?>

<style>
    .gastos-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .page-header-gastos {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .stats-grid-gastos {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .stat-card-gastos {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card-gastos::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .stat-card-gastos.primary::before { background: #667eea; }
    .stat-card-gastos.success::before { background: #10b981; }
    .stat-card-gastos.warning::before { background: #f59e0b; }
    
    .stat-label-gastos {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    
    .stat-value-gastos {
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
    }
    
    .filters-section-gastos {
        background: white;
        padding: 20px 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
    }
    
    .filter-row-gastos {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }
    
    .filter-group-gastos {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .filter-label-gastos {
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
    }
    
    .filter-input-gastos {
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    
    .filter-input-gastos:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .filter-actions-gastos {
        display: flex;
        gap: 12px;
    }
    
    .btn-primary-gastos {
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
    
    .btn-primary-gastos:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .btn-secondary-gastos {
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
    
    .btn-secondary-gastos:hover {
        background: #e5e7eb;
    }
    
    .card-gastos {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .card-header-gastos {
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-title-gastos {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .table-gastos {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .table-gastos thead th {
        background: #f9fafb;
        padding: 14px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #6b7280;
        border-bottom: 2px solid #e5e7eb;
        white-space: nowrap;
    }
    
    .table-gastos tbody td {
        padding: 16px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
        color: #1f2937;
        vertical-align: middle;
    }
    
    .table-gastos tbody tr:hover {
        background: #f9fafb;
    }
    
    .badge-gastos {
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }
    
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    
    .empty-state-gastos {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-icon-gastos {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 20px;
    }
    
    .empty-title-gastos {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 8px;
    }
    
    .empty-text-gastos {
        color: #6b7280;
        margin-bottom: 24px;
    }
    
    .pagination-gastos {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 24px;
        padding: 20px;
    }
    
    .pagination-gastos a,
    .pagination-gastos span {
        padding: 10px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .pagination-gastos a {
        background: white;
        color: #4b5563;
        border: 1px solid #e5e7eb;
    }
    
    .pagination-gastos a:hover {
        background: #f3f4f6;
    }
    
    .pagination-gastos .active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
    }
    
    .pagination-gastos .disabled {
        background: #f9fafb;
        color: #d1d5db;
        cursor: not-allowed;
        border: 1px solid #e5e7eb;
    }
    
    @media (max-width: 768px) {
        .gastos-container {
            padding: 16px;
        }
        
        .page-header-gastos {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .stats-grid-gastos {
            grid-template-columns: 1fr;
        }
        
        .filter-row-gastos {
            grid-template-columns: 1fr;
        }
        
        .filter-actions-gastos {
            flex-direction: column;
            width: 100%;
        }
        
        .filter-actions-gastos button,
        .filter-actions-gastos a {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="gastos-container">
    
    <!-- Page Header -->
    <div class="page-header-gastos">
        <div>
            <h1 style="font-size: 28px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-file-invoice-dollar"></i>
                Gestión de Gastos
            </h1>
            <p style="color: #6b7280; margin: 0; font-size: 14px;">
                Control de gastos de proyectos de restauración
            </p>
        </div>
        <a href="registrar-gasto.php<?php echo $filterProjectId ? '?project_id=' . $filterProjectId : ''; ?>" class="btn-primary-gastos">
            <i class="fas fa-plus"></i>
            Registrar Gasto
        </a>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid-gastos">
        <div class="stat-card-gastos primary">
            <div class="stat-label-gastos">Total de Gastos</div>
            <div class="stat-value-gastos">$<?php echo number_format($totals['total_amount'] ?? 0, 0); ?></div>
        </div>
        
        <div class="stat-card-gastos success">
            <div class="stat-label-gastos">Gastos Pagados</div>
            <div class="stat-value-gastos">$<?php echo number_format($totals['paid_amount'] ?? 0, 0); ?></div>
        </div>
        
        <div class="stat-card-gastos warning">
            <div class="stat-label-gastos">Gastos Pendientes</div>
            <div class="stat-value-gastos">$<?php echo number_format($totals['pending_amount'] ?? 0, 0); ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-section-gastos">
        <form method="GET" action="gastos.php" id="filterForm">
            <div class="filter-row-gastos">
                <div class="filter-group-gastos">
                    <label class="filter-label-gastos">Proyecto</label>
                    <select name="project_id" class="filter-input-gastos">
                        <option value="0">Todos los Proyectos</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $filterProjectId == $project['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['project_reference'] . ' - ' . $project['project_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group-gastos">
                    <label class="filter-label-gastos">Tipo de Gasto</label>
                    <select name="expense_type" class="filter-input-gastos">
                        <option value="0">Todos los Tipos</option>
                        <?php foreach ($expenseTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo $filterExpenseType == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group-gastos">
                    <label class="filter-label-gastos">Estado de Pago</label>
                    <select name="payment_status" class="filter-input-gastos">
                        <option value="all" <?php echo $filterPaymentStatus === 'all' ? 'selected' : ''; ?>>Todos los Estados</option>
                        <option value="Pagado" <?php echo $filterPaymentStatus === 'Pagado' ? 'selected' : ''; ?>>Pagado</option>
                        <option value="Pendiente" <?php echo $filterPaymentStatus === 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="Pago Parcial" <?php echo $filterPaymentStatus === 'Pago Parcial' ? 'selected' : ''; ?>>Pago Parcial</option>
                        <option value="Atrasado" <?php echo $filterPaymentStatus === 'Atrasado' ? 'selected' : ''; ?>>Atrasado</option>
                    </select>
                </div>
                
                <div class="filter-group-gastos">
                    <label class="filter-label-gastos">Fecha Desde</label>
                    <input type="date" name="date_from" class="filter-input-gastos" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                
                <div class="filter-group-gastos">
                    <label class="filter-label-gastos">Fecha Hasta</label>
                    <input type="date" name="date_to" class="filter-input-gastos" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
            </div>
            
            <div class="filter-actions-gastos">
                <button type="submit" class="btn-primary-gastos">
                    <i class="fas fa-search"></i>
                    Buscar
                </button>
                <a href="gastos.php" class="btn-secondary-gastos">
                    <i class="fas fa-redo"></i>
                    Limpiar Filtros
                </a>
            </div>
        </form>
    </div>
    
    <!-- Table -->
    <div class="card-gastos">
        <div class="card-header-gastos">
            <h2 class="card-title-gastos">
                <i class="fas fa-list"></i>
                Listado de Gastos
            </h2>
            <span class="badge-gastos badge-info">
                <?php echo number_format($totalRecords); ?> gasto<?php echo $totalRecords !== 1 ? 's' : ''; ?>
            </span>
        </div>
        <div style="padding: 0;">
            <?php if (empty($expenses)): ?>
                <div class="empty-state-gastos">
                    <div class="empty-icon-gastos">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3 class="empty-title-gastos">No hay gastos registrados</h3>
                    <p class="empty-text-gastos">
                        <?php if ($filterProjectId || $filterExpenseType || $filterPaymentStatus !== 'all'): ?>
                            No se encontraron gastos con los filtros aplicados.
                        <?php else: ?>
                            Comienza registrando el primer gasto del proyecto.
                        <?php endif; ?>
                    </p>
                    <a href="registrar-gasto.php<?php echo $filterProjectId ? '?project_id=' . $filterProjectId : ''; ?>" class="btn-primary-gastos">
                        <i class="fas fa-plus"></i>
                        Registrar Primer Gasto
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-gastos">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Proyecto</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Proveedor</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Factura</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                    <td>
                                        <div style="max-width: 150px;">
                                            <div style="font-size: 13px; font-weight: 600;">
                                                <?php echo htmlspecialchars($expense['project_reference']); ?>
                                            </div>
                                            <small style="color: #6b7280;">
                                                <?php echo htmlspecialchars(mb_strimwidth($expense['project_name'], 0, 25, '...')); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($expense['type_name']); ?></td>
                                    <td>
                                        <div style="max-width: 200px;">
                                            <?php echo htmlspecialchars(mb_strimwidth($expense['description'], 0, 50, '...')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small style="color: #6b7280;">
                                            <?php echo $expense['supplier_name'] ? htmlspecialchars($expense['supplier_name']) : '-'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; font-size: 15px;">
                                            $<?php echo number_format($expense['total_amount'], 2); ?>
                                        </div>
                                        <?php if ($expense['quantity']): ?>
                                            <small style="color: #6b7280;">
                                                <?php echo number_format($expense['quantity'], 2); ?> <?php echo htmlspecialchars($expense['unit_of_measure']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'Pagado' => 'success',
                                            'Pendiente' => 'warning',
                                            'Pago Parcial' => 'info',
                                            'Atrasado' => 'danger'
                                        ];
                                        ?>
                                        <span class="badge-gastos badge-<?php echo $statusClass[$expense['payment_status']] ?? 'info'; ?>">
                                            <?php echo htmlspecialchars($expense['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $attachments = db()->select(
                                            "SELECT * FROM expense_attachments WHERE expense_id = ?",
                                            [$expense['id']]
                                        );
                                        if (!empty($attachments)):
                                        ?>
                                            <a href="<?php echo htmlspecialchars($attachments[0]['file_path']); ?>" 
                                               target="_blank"
                                               title="Ver factura">
                                                <i class="fas fa-file-pdf" style="color: #ef4444; font-size: 18px;"></i>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #d1d5db;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 4px;">
                                            <a href="ver-gasto.php?id=<?php echo $expense['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="editar-gasto.php?id=<?php echo $expense['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-gastos">
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
    </div>
    
</div>

<?php include 'footer.php'; ?>
<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('dashboard.title', [], 'Dashboard');
$currentUser = getCurrentUser();

// Año seleccionado para filtros
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$currentYear = date('Y');
$currentMonth = date('Y-m');

// ========== 1. TOTAL DE PROPIEDADES ==========
$totalProperties = (int)db()->selectValue(
    "SELECT COUNT(*) FROM properties WHERE status NOT IN ('deleted')"
);

// ========== 2. CLIENTES ACTIVOS ==========
$activeClients = (int)db()->selectValue(
    "SELECT COUNT(*) FROM clients WHERE is_active = 1"
);

// ========== 3. TRANSACCIONES DEL MES ==========
$monthTransactions = (int)db()->selectValue(
    "SELECT COUNT(*) FROM sales_transactions 
     WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
     AND status != 'cancelled'",
    [$currentMonth]
);

// ========== 4. CONSULTAS PENDIENTES ==========
$pendingInquiries = (int)db()->selectValue(
    "SELECT COUNT(*) FROM inquiries WHERE status = 'pending'"
);

// ========== 5. VENTAS VS ALQUILERES POR MES DEL AÑO SELECCIONADO ==========
$salesByMonth = array_fill(1, 12, 0);
$rentsByMonth = array_fill(1, 12, 0);

// Consulta de VENTAS - más flexible
$salesData = db()->select(
    "SELECT MONTH(COALESCE(closing_date, contract_date, created_at)) as month, COUNT(*) as count
     FROM sales_transactions
     WHERE YEAR(COALESCE(closing_date, contract_date, created_at)) = ?
     AND transaction_type = 'sale'
     AND status != 'cancelled'
     GROUP BY MONTH(COALESCE(closing_date, contract_date, created_at))",
    [$selectedYear]
);

foreach ($salesData as $row) {
    if ($row['month']) {
        $salesByMonth[(int)$row['month']] = (int)$row['count'];
    }
}

// Consulta de ALQUILERES - más flexible
$rentsData = db()->select(
    "SELECT MONTH(COALESCE(closing_date, contract_date, created_at)) as month, COUNT(*) as count
     FROM sales_transactions
     WHERE YEAR(COALESCE(closing_date, contract_date, created_at)) = ?
     AND transaction_type IN ('rent')
     AND status != 'cancelled'
     GROUP BY MONTH(COALESCE(closing_date, contract_date, created_at))",
    [$selectedYear]
);

foreach ($rentsData as $row) {
    if ($row['month']) {
        $rentsByMonth[(int)$row['month']] = (int)$row['count'];
    }
}

// Años disponibles para selector
$availableYears = db()->select(
    "SELECT DISTINCT YEAR(closing_date) as year 
     FROM sales_transactions 
     WHERE closing_date IS NOT NULL 
     ORDER BY year DESC"
);

// ========== 6. ÚLTIMOS 10 CLIENTES ==========
$recentClients = db()->select(
    "SELECT id, first_name, last_name, email, phone_mobile, created_at
     FROM clients
     WHERE is_active = 1
     ORDER BY created_at DESC
     LIMIT 10"
);

// ========== 7. RESUMEN FINANCIERO DEL MES ==========
// VENTAS: Suma del precio de venta solo para transacciones tipo 'sale'
$totalSales = (float)db()->selectValue(
    "SELECT COALESCE(SUM(sale_price), 0) 
     FROM sales_transactions 
     WHERE DATE_FORMAT(COALESCE(closing_date, contract_date, created_at), '%Y-%m') = ? 
     AND transaction_type = 'sale' 
     AND status != 'cancelled'",
    [$currentMonth]
);

// ALQUILERES: Suma del pago mensual para transacciones tipo 'rent' o 'vacation_rent'
$totalRentals = (float)db()->selectValue(
    "SELECT COALESCE(SUM(monthly_payment), 0) 
     FROM sales_transactions 
     WHERE DATE_FORMAT(COALESCE(closing_date, contract_date, created_at), '%Y-%m') = ?
     AND transaction_type IN ('rent', 'vacation_rent') 
     AND status != 'cancelled'",
    [$currentMonth]
);

// COMISIONES: Suma de todas las comisiones del mes
$totalCommissions = (float)db()->selectValue(
    "SELECT COALESCE(SUM(commission_amount), 0) 
     FROM sales_transactions 
     WHERE DATE_FORMAT(COALESCE(closing_date, contract_date, created_at), '%Y-%m') = ? 
     AND status != 'cancelled'",
    [$currentMonth]
);

// ========== 8. DOCUMENTOS RECIENTES ==========
$recentDocuments = db()->select(
    "SELECT d.*, u.first_name, u.last_name, dc.name as category_name
     FROM documents d
     LEFT JOIN users u ON d.uploaded_by = u.id
     LEFT JOIN document_categories dc ON d.category_id = dc.id
     ORDER BY d.created_at DESC
     LIMIT 10"
);

// ========== 9. TAREAS PRÓXIMAS ==========
$upcomingTasks = db()->select(
    "SELECT t.*, 
     CONCAT(u1.first_name, ' ', u1.last_name) as created_by_name,
     CONCAT(u2.first_name, ' ', u2.last_name) as assigned_to_name
     FROM tasks t
     LEFT JOIN users u1 ON t.created_by = u1.id
     LEFT JOIN users u2 ON t.assigned_to = u2.id
     WHERE t.status IN ('pending', 'in_progress')
     AND t.due_date >= CURDATE()
     ORDER BY t.due_date ASC
     LIMIT 10"
);

// ========== 10. NOTIFICACIONES RECIENTES ==========
$recentNotifications = db()->select(
    "SELECT * FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 10",
    [$currentUser['id']]
);

$unreadCount = (int)db()->selectValue(
    "SELECT COUNT(*) FROM notifications 
     WHERE user_id = ? AND is_read = 0",
    [$currentUser['id']]
);

// ========== 11. ACTIVIDAD RECIENTE ==========
$recentActivity = db()->select(
    "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
     FROM activity_log a
     LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC
     LIMIT 15"
);

// ========== 12. DISTRIBUCIÓN POR TIPO DE PROPIEDAD ==========
$propertyTypes = db()->select(
    "SELECT pt.name, COUNT(p.id) as count
     FROM property_types pt
     LEFT JOIN properties p ON p.property_type_id = pt.id AND p.status NOT IN ('deleted')
     GROUP BY pt.id, pt.name
     HAVING count > 0
     ORDER BY count DESC"
);

// ========== 13. AGENTES DESTACADOS DEL MES ==========
$topAgents = db()->select(
    "SELECT u.id, u.first_name, u.last_name, u.profile_picture,
     COUNT(st.id) as transaction_count,
     SUM(st.agent_commission) as total_commission
     FROM users u
     INNER JOIN sales_transactions st ON st.agent_id = u.id
     WHERE DATE_FORMAT(st.closing_date, '%Y-%m') = ?
     AND st.status = 'completed'
     GROUP BY u.id
     ORDER BY total_commission DESC
     LIMIT 5",
    [$currentMonth]
);

// ========== 14. PROPIEDADES MÁS VISTAS DEL MES ==========
$mostViewed = db()->select(
    "SELECT p.*, pt.name as type_name,
     (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image,
     (SELECT COUNT(*) FROM property_views WHERE property_id = p.id AND DATE_FORMAT(viewed_at, '%Y-%m') = ?) as views_count
     FROM properties p
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     WHERE p.status NOT IN ('deleted', 'sold')
     HAVING views_count > 0
     ORDER BY views_count DESC
     LIMIT 5",
    [$currentMonth]
);

// ========== 15. INGRESOS MENSUALES (ÚLTIMOS 12 MESES) ==========
$monthlyIncome = [];
for($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $income = db()->selectOne(
        "SELECT SUM(sale_price) as total
         FROM sales_transactions
         WHERE DATE_FORMAT(closing_date, '%Y-%m') = ?
         AND status = 'completed'",
        [$month]
    );
    $monthlyIncome[] = [
        'month' => date('M Y', strtotime($month . '-01')),
        'month_num' => date('n', strtotime($month . '-01')),
        'total' => $income['total'] ?? 0
    ];
}

// ========== 16. PORCENTAJE DE PROPIEDADES POR ESTADO ==========
$propertyStatus = db()->select(
    "SELECT 
        status,
        COUNT(*) as count,
        (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM properties WHERE status NOT IN ('deleted'))) as percentage
     FROM properties
     WHERE status NOT IN ('deleted')
     GROUP BY status"
);

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

    .dashboard-container {
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

    /* Stats Cards Grid */
    .stats-grid-modern {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card-modern {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .stat-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    .stat-card-modern.purple { border-left: 4px solid var(--purple); }
    .stat-card-modern.blue { border-left: 4px solid var(--info); }
    .stat-card-modern.green { border-left: 4px solid var(--success); }
    .stat-card-modern.orange { border-left: 4px solid var(--warning); }

    .stat-value-modern {
        font-size: 32px;
        font-weight: 700;
        color: #2d3748;
        line-height: 1;
        margin-bottom: 8px;
    }

    .stat-label-modern {
        font-size: 13px;
        color: #718096;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-icon-modern {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: white;
        flex-shrink: 0;
    }

    .stat-card-modern.purple .stat-icon-modern { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .stat-card-modern.blue .stat-icon-modern { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
    .stat-card-modern.green .stat-icon-modern { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .stat-card-modern.orange .stat-icon-modern { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }

    /* Dashboard Grid Layout */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 25px;
    }

    /* Cards */
    .card-modern {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        padding: 25px;
        transition: all 0.3s ease;
    }

    .card-modern:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .card-header-modern {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .card-title-modern {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin: 0;
    }

    .card-subtitle-modern {
        font-size: 13px;
        color: #9ca3af;
        margin: 4px 0 0 0;
    }

    .btn-modern {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        border: none;
    }

    .btn-outline-modern {
        background: white;
        color: #667eea;
        border: 2px solid #e5e7eb;
    }

    .btn-outline-modern:hover {
        border-color: #667eea;
        background: #f5f3ff;
    }

    /* Charts */
    .chart-container {
        position: relative;
        height: 300px;
    }

    .chart-container-small {
        position: relative;
        height: 220px;
    }

    /* Year Selector */
    .year-selector {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .year-selector select {
        padding: 8px 32px 8px 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        background: white url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") no-repeat right 0.5rem center/1.5em 1.5em;
        appearance: none;
        transition: all 0.3s;
    }

    .year-selector select:hover {
        border-color: #667eea;
    }

    /* Scrollable Lists */
    .scrollable-list {
        max-height: 400px;
        overflow-y: auto;
        margin: -10px -15px;
        padding: 10px 15px;
    }

    .scrollable-list::-webkit-scrollbar {
        width: 6px;
    }

    .scrollable-list::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .scrollable-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .scrollable-list::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* List Items */
    .list-item-modern {
        padding: 15px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.3s;
        cursor: pointer;
    }

    .list-item-modern:last-child {
        border-bottom: none;
    }

    .list-item-modern:hover {
        background: #f9fafb;
    }

    .avatar-circle {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
        flex-shrink: 0;
    }

    /* Empty States */
    .empty-state-modern {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }

    .empty-state-modern i {
        font-size: 48px;
        margin-bottom: 12px;
        opacity: 0.5;
    }

    .empty-state-modern p {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }

    /* Badges */
    .badge-modern {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .badge-purple { background: #ede9fe; color: #6b21a8; }

    /* Responsive */
    @media (max-width: 1400px) {
        .stats-grid-modern {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 15px;
        }

        .stats-grid-modern {
            grid-template-columns: 1fr;
        }

        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .page-title-modern {
            font-size: 22px;
        }

        .stat-value-modern {
            font-size: 24px;
        }

        .chart-container {
            height: 250px;
        }
    }
</style>

<div class="dashboard-container">

    <!-- ========== ENCABEZADO DE PÁGINA ========== -->
    <div class="page-header-modern">
        <div>
            <h1 class="page-title-modern">
                <?php echo __('dashboard.title', [], 'Dashboard'); ?> 👋
            </h1>
            <p class="page-subtitle-modern">
                <?php echo __('dashboard.welcome', [], 'Welcome'); ?>, <?php echo $currentUser['first_name']; ?>. 
                <?php echo __('dashboard.subtitle', [], 'Here is your general summary'); ?>.
            </p>
        </div>
        <div>
            <button class="btn-modern btn-outline-modern" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                <span class="d-none d-sm-inline"><?php echo __('dashboard.refresh', [], 'Update'); ?></span>
            </button>
        </div>
    </div>

    <!-- ========== TARJETAS DE ESTADÍSTICAS ========== -->
    <div class="stats-grid-modern">
        <div class="stat-card-modern purple">
            <div>
                <div class="stat-value-modern"><?php echo number_format($totalProperties); ?></div>
                <div class="stat-label-modern"><?php echo __('dashboard.total_properties', [], 'Total Properties'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-home"></i>
            </div>
        </div>

        <div class="stat-card-modern blue">
            <div>
                <div class="stat-value-modern"><?php echo number_format($activeClients); ?></div>
                <div class="stat-label-modern"><?php echo __('dashboard.active_clients', [], 'Active Clients'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-users"></i>
            </div>
        </div>

        <div class="stat-card-modern green">
            <div>
                <div class="stat-value-modern"><?php echo number_format($monthTransactions); ?></div>
                <div class="stat-label-modern"><?php echo __('dashboard.month_transactions', [], 'Transactions of the Month'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>

        <div class="stat-card-modern orange">
            <div>
                <div class="stat-value-modern"><?php echo number_format($pendingInquiries); ?></div>
                <div class="stat-label-modern"><?php echo __('dashboard.pending_inquiries', [], 'Pending Queries'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-envelope"></i>
            </div>
        </div>
    </div>

    <!-- ========== FILA 1: Ventas vs Alquileres | Últimos Clientes ========== -->
    <div class="dashboard-grid">
        
        <!-- 1. GRÁFICA: PROPIEDADES VENDIDAS VS ALQUILADAS -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div style="flex: 1; min-width: 0;">
                    <h3 class="card-title-modern"><?php echo __('dashboard.properties_sold_vs_rented', [], 'Propiedades Vendidas vs. Alquiladas'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.monthly_comparison', [], 'Comparativa mensual'); ?></p>
                </div>
                <div class="year-selector">
                    <label style="font-size: 13px; color: #6b7280; font-weight: 600;"><?php echo __('dashboard.year', [], 'Año'); ?>:</label>
                    <select id="yearSelector" onchange="changeYear(this.value)">
                        <?php foreach($availableYears as $year): ?>
                        <option value="<?php echo $year['year']; ?>" <?php echo $year['year'] == $selectedYear ? 'selected' : ''; ?>>
                            <?php echo $year['year']; ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if(empty($availableYears)): ?>
                        <option value="<?php echo $currentYear; ?>" selected><?php echo $currentYear; ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="salesVsRentalsChart"></canvas>
            </div>
        </div>

        <!-- 2. ÚLTIMOS 10 CLIENTES REGISTRADOS -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.recent_clients', [], 'Últimos Clientes'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.recently_registered', [], 'Registrados recientemente'); ?></p>
                </div>
                <a href="clientes.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-users"></i>
                </a>
            </div>

            <?php if(empty($recentClients)): ?>
            <div class="empty-state-modern">
                <i class="fas fa-user-plus"></i>
                <p><?php echo __('dashboard.no_clients', [], 'No hay clientes'); ?></p>
            </div>
            <?php else: ?>
            <div class="scrollable-list">
                <?php foreach($recentClients as $client): ?>
                <div class="list-item-modern">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <?php echo strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)); ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; color: #2d3748; font-size: 14px;">
                                <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                            </div>
                            <small style="color: #9ca3af; font-size: 12px;">
                                <?php echo htmlspecialchars($client['email']); ?>
                            </small>
                        </div>
                        <div style="text-align: right;">
                            <small style="color: #9ca3af; font-size: 11px;">
                                <?php echo date('d/m/Y', strtotime($client['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
    <!-- ========== FILA 2: Resumen Financiero | Documentos Recientes ========== -->
    <div class="dashboard-grid">
        
        <!-- 3. RESUMEN FINANCIERO (GRÁFICO CIRCULAR) -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.financial_summary', [], 'Resumen Financiero del Mes'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo date('F Y'); ?></p>
                </div>
                <a href="reportes.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-chart-pie"></i>
                </a>
            </div>
            
            <div class="row" style="margin-bottom: 20px;">
                <div class="col-4 text-center">
                    <div class="stat-value" style="color: var(--success); font-size: 24px;">
                        $<?php echo number_format($totalSales, 0); ?>
                    </div>
                    <div class="stat-label"><?php echo __('dashboard.sales', [], 'Ventas'); ?></div>
                </div>
                <div class="col-4 text-center">
                    <div class="stat-value" style="color: var(--info); font-size: 24px;">
                        $<?php echo number_format($totalRentals, 0); ?>
                    </div>
                    <div class="stat-label"><?php echo __('dashboard.rentals', [], 'Alquileres'); ?></div>
                </div>
                <div class="col-4 text-center">
                    <div class="stat-value" style="color: var(--warning); font-size: 24px;">
                        $<?php echo number_format($totalCommissions, 0); ?>
                    </div>
                    <div class="stat-label"><?php echo __('dashboard.commissions', [], 'Comisiones'); ?></div>
                </div>
            </div>

            <div class="chart-container-small">
                <canvas id="financialSummaryChart"></canvas>
            </div>
        </div>

        <!-- 4. DOCUMENTOS RECIENTES -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.recent_documents', [], 'Documentos Recientes'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.last_uploaded_files', [], 'Últimos archivos subidos'); ?></p>
                </div>
                <a href="documentos.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-folder"></i>
                </a>
            </div>

            <?php if(empty($recentDocuments)): ?>
            <div class="empty-state-modern">
                <i class="fas fa-file"></i>
                <p><?php echo __('dashboard.no_documents', [], 'No hay documentos'); ?></p>
            </div>
            <?php else: ?>
            <div class="scrollable-list">
                <?php foreach($recentDocuments as $doc): ?>
                <div class="list-item-modern">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-circle" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; color: #2d3748; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($doc['document_name']); ?>
                            </div>
                            <small style="color: #9ca3af; font-size: 12px;">
                                <?php echo htmlspecialchars($doc['category_name'] ?? 'Sin categoría'); ?> • 
                                <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                            </small>
                        </div>
                        <div style="text-align: right;">
                            <small style="color: #9ca3af; font-size: 11px;">
                                <?php echo date('d/m/Y', strtotime($doc['uploaded_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ========== FILA 3: Tareas Próximas | Notificaciones ========== -->
    <div class="dashboard-grid">
        
        <!-- 5. TAREAS PRÓXIMAS -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.upcoming_tasks', [], 'Tareas Próximas'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.pending_in_progress', [], 'Pending and in progress'); ?></p>
                </div>
                <a href="tareas.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-tasks"></i>
                </a>
            </div>

            <?php if(empty($upcomingTasks)): ?>
            <div class="empty-state-modern">
                <i class="fas fa-check-circle"></i>
                <p><?php echo __('dashboard.no_tasks', [], 'No hay tareas pendientes'); ?></p>
            </div>
            <?php else: ?>
            <div class="scrollable-list">
                <?php foreach($upcomingTasks as $task): ?>
                <div class="list-item-modern">
                    <div class="d-flex align-items-start gap-3">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; color: #2d3748; font-size: 14px; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span class="badge-modern badge-<?php echo $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                    <?php 
                                    $priorities = [
                                        'high' => __('priority_high', [], 'Alta'),
                                        'medium' => __('priority_medium', [], 'Media'),
                                        'low' => __('priority_low', [], 'Baja')
                                    ];
                                    echo $priorities[$task['priority']] ?? $task['priority'];
                                    ?>
                                </span>
                                <small style="color: #9ca3af; font-size: 12px;">
                                    <i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($task['due_date'])); ?>
                                </small>
                                <small style="color: #9ca3af; font-size: 12px;">
                                    <i class="far fa-user"></i> <?php echo htmlspecialchars($task['assigned_to_name']); ?>
                                </small>
                            </div>
                        </div>
                        <button class="btn-modern btn-outline-modern" 
                                onclick="completeTask(<?php echo $task['id']; ?>)" 
                                style="padding: 6px 12px; font-size: 12px;">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 6. NOTIFICACIONES RECIENTES -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern">
                        <?php echo __('dashboard.notifications', [], 'Notificaciones'); ?>
                        <?php if($unreadCount > 0): ?>
                        <span class="badge-modern badge-danger" style="margin-left: 8px;">
                            <?php echo $unreadCount; ?>
                        </span>
                        <?php endif; ?>
                    </h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.recent_updates', [], 'Recent Updates'); ?></p>
                </div>
                <button class="btn-modern btn-outline-modern" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i>
                </button>
            </div>

            <?php if(empty($recentNotifications)): ?>
            <div class="empty-state-modern">
                <i class="fas fa-bell"></i>
                <p><?php echo __('dashboard.no_notifications', [], 'No hay notificaciones'); ?></p>
            </div>
            <?php else: ?>
            <div class="scrollable-list">
                <?php foreach($recentNotifications as $notif): ?>
                <div class="list-item-modern" style="<?php echo $notif['is_read'] ? 'opacity: 0.6;' : ''; ?>">
                    <div class="d-flex align-items-start gap-3">
                        <div class="avatar-circle" style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); width: 35px; height: 35px; font-size: 14px;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 600; color: #2d3748; font-size: 13px; margin-bottom: 2px;">
                                <?php echo htmlspecialchars($notif['title']); ?>
                            </div>
                            <small style="color: #6b7280; font-size: 12px;">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </small>
                            <div style="margin-top: 4px;">
                                <small style="color: #9ca3af; font-size: 11px;">
                                    <i class="far fa-clock"></i> <?php echo timeAgo($notif['created_at']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ========== FILA 4: Actividad Reciente | Distribución por Tipo ========== -->
    <div class="dashboard-grid">
        
        <!-- 7. ACTIVIDAD RECIENTE DEL SISTEMA -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.recent_activity', [], 'Actividad Reciente'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.system_activity', [], 'Actividad del sistema'); ?></p>
                </div>
            </div>

            <?php if(empty($recentActivity)): ?>
            <div class="empty-state-modern">
                <i class="fas fa-history"></i>
                <p><?php echo __('dashboard.no_activity', [], 'No hay actividad'); ?></p>
            </div>
            <?php else: ?>
            <div class="scrollable-list">
                <?php foreach($recentActivity as $activity): ?>
                <div class="list-item-modern">
                    <div class="d-flex align-items-start gap-3">
                        <div class="avatar-circle" style="background: <?php 
                            echo $activity['action'] === 'create' ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 
                                ($activity['action'] === 'update' ? 'linear-gradient(135deg, #3b82f6 0%, #1e40af 100%)' : 
                                'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)');
                        ?>; width: 35px; height: 35px; font-size: 12px;">
                            <i class="fas fa-<?php 
                                echo $activity['action'] === 'create' ? 'plus' : 
                                    ($activity['action'] === 'update' ? 'edit' : 'trash');
                            ?>"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13px; color: #2d3748;">
                                <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                <?php 
                                $actions = [
                                    'create' => __('action_created', [], 'creó'),
                                    'update' => __('action_updated', [], 'actualizó'),
                                    'delete' => __('action_deleted', [], 'eliminó')
                                ];
                                echo $actions[$activity['action']] ?? $activity['action'];
                                ?>
                                <span style="color: #6b7280;"><?php echo htmlspecialchars($activity['description']); ?></span>
                            </div>
                            <small style="color: #9ca3af; font-size: 11px;">
                                <i class="far fa-clock"></i> <?php echo timeAgo($activity['created_at']); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 8. DISTRIBUCIÓN POR TIPO DE PROPIEDAD -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.property_type_distribution', [], 'Distribución por Tipo'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.property_types', [], 'Tipos de propiedad'); ?></p>
                </div>
            </div>

            <?php if(empty($propertyTypes)): ?>
            <div class="empty-state-modern">
                <i class="fas fa-chart-pie"></i>
                <p><?php echo __('no_data', [], 'No hay datos'); ?></p>
            </div>
            <?php else: ?>
            <div class="chart-container-small">
                <canvas id="propertyTypesChart"></canvas>
            </div>
            <div style="margin-top: 20px;">
                <?php 
                $colors = ['#667eea', '#764ba2', '#10b981', '#3b82f6', '#f59e0b', '#ef4444'];
                foreach($propertyTypes as $i => $type): 
                ?>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 12px; height: 12px; border-radius: 3px; background: <?php echo $colors[$i % count($colors)]; ?>; flex-shrink: 0;"></div>
                        <span style="font-size: 13px; color: #4b5563; font-weight: 600;">
                            <?php echo htmlspecialchars($type['name']); ?>
                        </span>
                    </div>
                    <span style="font-weight: 700; color: #2d3748;">
                        <?php echo $type['count']; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ========== FILA 5: Agentes Destacados ========== -->
    <?php if(!empty($topAgents)): ?>
    <div class="card-modern" style="margin-bottom: 25px;">
        <div class="card-header-modern">
            <div>
                <h3 class="card-title-modern">
                    <i class="fas fa-star" style="color: #f59e0b;"></i>
                    <?php echo __('dashboard.top_agents', [], 'Agentes Destacados del Mes'); ?>
                </h3>
                <p class="card-subtitle-modern"><?php echo __('dashboard.highest_commissions', [], 'Mayor comisión generada'); ?></p>
            </div>
            <a href="agentes.php" class="btn-modern btn-outline-modern">
                <?php echo __('dashboard.view_all', [], 'View All'); ?>
            </a>
        </div>

        <div class="row" style="margin-top: 10px;">
            <?php foreach($topAgents as $index => $agent): ?>
            <div class="col-md-4 col-lg mb-3">
                <div class="card" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 12px; padding: 20px; text-align: center; transition: all 0.3s;">
                    <?php if($agent['profile_picture']): ?>
                    <img src="<?php echo htmlspecialchars($agent['profile_picture']); ?>" 
                         alt="<?php echo htmlspecialchars($agent['first_name']); ?>"
                         style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin: 0 auto 15px; border: 4px solid <?php echo $index === 0 ? '#fbbf24' : ($index === 1 ? '#d1d5db' : '#cd7f32'); ?>;">
                    <?php else: ?>
                    <div style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 24px; border: 4px solid <?php echo $index === 0 ? '#fbbf24' : ($index === 1 ? '#d1d5db' : '#cd7f32'); ?>;">
                        <?php echo strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($index < 3): ?>
                    <div style="position: absolute; top: 10px; right: 10px; font-size: 24px;">
                        <?php echo $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉'); ?>
                    </div>
                    <?php endif; ?>
                    
                    <h5 style="font-weight: 700; font-size: 16px; margin-bottom: 4px; color: #2d3748;">
                        <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                    </h5>
                    <div style="font-size: 24px; font-weight: 700; color: var(--success); margin: 10px 0;">
                        $<?php echo number_format($agent['total_commission'], 0); ?>
                    </div>
                    <small style="color: #9ca3af; font-size: 12px;">
                        <?php echo $agent['transaction_count']; ?> <?php echo __('dashboard.transactions', [], 'transactions'); ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========== FILA 6: Propiedades Más Vistas ========== -->
    <?php if(!empty($mostViewed)): ?>
    <div class="card-modern" style="margin-bottom: 25px;">
        <div class="card-header-modern">
            <div>
                <h3 class="card-title-modern">
                    <i class="fas fa-eye" style="color: #3b82f6;"></i>
                    <?php echo __('dashboard.most_viewed_properties', [], 'Propiedades Más Vistas'); ?>
                </h3>
                <p class="card-subtitle-modern"><?php echo __('dashboard.month_views', [], 'Vistas del mes'); ?></p>
            </div>
            <a href="propiedades.php" class="btn-modern btn-outline-modern">
                <?php echo __('dashboard.view_all', [], 'View All'); ?>
            </a>
        </div>

        <div class="scrollable-list">
            <?php foreach($mostViewed as $prop): ?>
            <div class="list-item-modern">
                <div class="d-flex align-items-center gap-3">
                    <?php if($prop['main_image']): ?>
                    <img src="<?php echo htmlspecialchars($prop['main_image']); ?>" 
                         alt="<?php echo htmlspecialchars($prop['title']); ?>"
                         style="width: 70px; height: 70px; border-radius: 10px; object-fit: cover; flex-shrink: 0;">
                    <?php else: ?>
                    <div style="width: 70px; height: 70px; border-radius: 10px; background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fas fa-home" style="font-size: 24px; color: #9ca3af;"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: #2d3748; font-size: 14px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($prop['title']); ?>
                        </div>
                        <small style="color: #9ca3af; font-size: 12px;">
                            <?php echo htmlspecialchars($prop['type_name'] ?? 'N/A'); ?> • 
                            $<?php echo number_format($prop['price'], 0); ?>
                        </small>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 700; color: var(--primary);">
                            <i class="fas fa-eye"></i> <?php echo number_format($prop['views_count']); ?>
                        </div>
                        <small class="d-none d-sm-block" style="color: #9ca3af; font-size: 11px;">
                            <?php echo __('dashboard.views', [], 'views'); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========== FILA 7: Ingresos Mensuales | Estado Propiedades ========== -->
    <div class="dashboard-grid">
        
        <!-- 15. INGRESOS MENSUALES (GRÁFICA DE BARRAS) -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.monthly_income', [], 'Ingresos Mensuales'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('dashboard.last_12_months', [], 'Últimos 12 meses'); ?></p>
                </div>
                <a href="reportes.php" class="btn-modern btn-outline-modern">
                    <i class="fas fa-chart-bar"></i>
                </a>
            </div>

            <div class="chart-container">
                <canvas id="monthlyIncomeChart"></canvas>
            </div>
        </div>

        <!-- 16. PORCENTAJE DE PROPIEDADES POR ESTADO (GRÁFICA CIRCULAR) -->
        <div class="card-modern">
            <div class="card-header-modern">
                <div>
                    <h3 class="card-title-modern"><?php echo __('dashboard.property_status', [], 'Estado de Propiedades'); ?></h3>
                    <p class="card-subtitle-modern"><?php echo __('distribution_by_status', [], 'Distribución por estado'); ?></p>
                </div>
            </div>

            <?php if(empty($propertyStatus)): ?>
            <div class="empty-state-modern">
                <i class="fas fa-chart-pie"></i>
                <p><?php echo __('no_data', [], 'No hay datos'); ?></p>
            </div>
            <?php else: ?>
            <div class="chart-container-small">
                <canvas id="propertyStatusChart"></canvas>
            </div>
            <div style="margin-top: 20px;">
                <?php foreach($propertyStatus as $status): ?>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 12px; height: 12px; border-radius: 3px; background: <?php 
                            echo $status['status'] === 'available' ? 'var(--success)' : 
                                ($status['status'] === 'reserved' ? 'var(--warning)' : 
                                ($status['status'] === 'sold' ? 'var(--danger)' : 'var(--info)')); 
                        ?>; flex-shrink: 0;"></div>
                        <span style="font-size: 13px; color: #4b5563; font-weight: 600;">
                            <?php 
                            $statusTranslations = [
                                'available' => __('properties.stats.available', [], 'Available'),
                                'reserved' => __('properties.stats.reserved', [], 'Reserved'),
                                'sold' => __('properties.stats.sold', [], 'Sold'),
                                'rented' => __('properties.stats.rented', [], 'Rented')
                            ];
                            echo $statusTranslations[$status['status']] ?? ucfirst($status['status']);
                            ?>
                        </span>
                    </div>
                    <div>
                        <span style="font-weight: 700; color: #2d3748;">
                            <?php echo number_format($status['percentage'], 1); ?>%
                        </span>
                        <span style="color: #9ca3af; font-size: 12px;">
                            (<?php echo $status['count']; ?>)
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ========== FUNCIONES DE UTILIDAD ==========
function changeYear(year) {
    window.location.href = 'dashboard.php?year=' + year;
}

function completeTask(taskId) {
    if(confirm('<?php echo __('confirm_complete_task', [], '¿Marcar esta tarea como completada?'); ?>')) {
        fetch('ajax/task-actions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'complete', id: taskId})
        })
        .then(res => res.json())
        .then(result => {
            if(result.success) {
                location.reload();
            } else {
                alert(result.message || '<?php echo __('error', [], 'Error'); ?>');
            }
        });
    }
}

function markAllAsRead() {
    fetch('ajax/notification-actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'mark_all_read'})
    })
    .then(res => res.json())
    .then(result => {
        if(result.success) {
            location.reload();
        }
    });
}

// ========== GRÁFICAS CON CHART.JS ==========

// Configuración responsive para gráficas
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = false;

// 1. GRÁFICA: VENTAS VS ALQUILERES
const salesVsRentalsCtx = document.getElementById('salesVsRentalsChart').getContext('2d');
new Chart(salesVsRentalsCtx, {
    type: 'line',
    data: {
        labels: ['<?php echo currentLanguage() === 'es' ? 'Ene' : 'Jan'; ?>', '<?php echo currentLanguage() === 'es' ? 'Feb' : 'Feb'; ?>', '<?php echo currentLanguage() === 'es' ? 'Mar' : 'Mar'; ?>', '<?php echo currentLanguage() === 'es' ? 'Abr' : 'Apr'; ?>', '<?php echo currentLanguage() === 'es' ? 'May' : 'May'; ?>', '<?php echo currentLanguage() === 'es' ? 'Jun' : 'Jun'; ?>', '<?php echo currentLanguage() === 'es' ? 'Jul' : 'Jul'; ?>', '<?php echo currentLanguage() === 'es' ? 'Ago' : 'Aug'; ?>', '<?php echo currentLanguage() === 'es' ? 'Sep' : 'Sep'; ?>', '<?php echo currentLanguage() === 'es' ? 'Oct' : 'Oct'; ?>', '<?php echo currentLanguage() === 'es' ? 'Nov' : 'Nov'; ?>', '<?php echo currentLanguage() === 'es' ? 'Dic' : 'Dec'; ?>'],
        datasets: [{
            label: '<?php echo __('dashboard.sales', [], 'Ventas'); ?>',
            data: <?php echo json_encode(array_values($salesByMonth)); ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: '<?php echo __('dashboard.rentals', [], 'Alquileres'); ?>',
            data: <?php echo json_encode(array_values($rentsByMonth)); ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: window.innerWidth < 768 ? 'bottom' : 'top',
                labels: {
                    boxWidth: 15,
                    font: {
                        size: window.innerWidth < 768 ? 11 : 12
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
                    font: {
                        size: window.innerWidth < 768 ? 10 : 12
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: window.innerWidth < 768 ? 10 : 12
                    }
                }
            }
        }
    }
});

// 3. RESUMEN FINANCIERO
const financialCtx = document.getElementById('financialSummaryChart').getContext('2d');
new Chart(financialCtx, {
    type: 'doughnut',
    data: {
        labels: ['<?php echo __('dashboard.sales', [], 'Ventas'); ?>', '<?php echo __('dashboard.rentals', [], 'Alquileres'); ?>', '<?php echo __('dashboard.commissions', [], 'Comisiones'); ?>'],
        datasets: [{
            data: [<?php echo $totalSales; ?>, <?php echo $totalRentals; ?>, <?php echo $totalCommissions; ?>],
            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: window.innerWidth < 768 ? 'bottom' : 'right',
                labels: {
                    boxWidth: 15,
                    font: {
                        size: window.innerWidth < 768 ? 11 : 12
                    }
                }
            }
        }
    }
});

// 7. DISTRIBUCIÓN POR TIPO DE PROPIEDAD
<?php if(!empty($propertyTypes)): ?>
const propertyTypesCtx = document.getElementById('propertyTypesChart').getContext('2d');
new Chart(propertyTypesCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($propertyTypes, 'name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($propertyTypes, 'count')); ?>,
            backgroundColor: ['#667eea', '#764ba2', '#10b981', '#3b82f6', '#f59e0b', '#ef4444']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: window.innerWidth < 768 ? 'bottom' : 'right',
                labels: {
                    boxWidth: 15,
                    font: {
                        size: window.innerWidth < 768 ? 11 : 12
                    }
                }
            }
        }
    }
});
<?php endif; ?>

// 15. INGRESOS MENSUALES (GRÁFICA DE BARRAS)
const monthlyIncomeCtx = document.getElementById('monthlyIncomeChart').getContext('2d');
new Chart(monthlyIncomeCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_map(function($item) { 
            $months_es = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $months_en = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $lang = '<?php echo currentLanguage(); ?>';
            return $lang === 'es' ? $months_es[$item['month_num'] - 1] : $months_en[$item['month_num'] - 1];
        }, $monthlyIncome)); ?>,
        datasets: [{
            label: '<?php echo __('income', [], 'Ingresos'); ?>',
            data: <?php echo json_encode(array_column($monthlyIncome, 'total')); ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: '#667eea',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    },
                    font: {
                        size: window.innerWidth < 768 ? 10 : 12
                    }
                }
            },
            x: {
                ticks: {
                    font: {
                        size: window.innerWidth < 768 ? 10 : 12
                    }
                }
            }
        }
    }
});

// 16. ESTADO DE PROPIEDADES (GRÁFICA CIRCULAR)
<?php if(!empty($propertyStatus)): ?>
const propertyStatusCtx = document.getElementById('propertyStatusChart').getContext('2d');
new Chart(propertyStatusCtx, {
    type: 'pie',
    data: {
        labels: <?php 
        $statusLabels = array_map(function($s) {
            $translations = [
                'available' => __('properties.stats.available', [], 'Available'),
                'reserved' => __('properties.stats.reserved', [], 'Reserved'),
                'sold' => __('properties.stats.sold', [], 'Sold'),
                'rented' => __('properties.stats.rented', [], 'Rented')
            ];
            return $translations[$s['status']] ?? ucfirst($s['status']);
        }, $propertyStatus);
        echo json_encode($statusLabels);
        ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($propertyStatus, 'count')); ?>,
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: window.innerWidth < 768 ? 'bottom' : 'right',
                labels: {
                    boxWidth: 15,
                    font: {
                        size: window.innerWidth < 768 ? 11 : 12
                    },
                    padding: 12
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>
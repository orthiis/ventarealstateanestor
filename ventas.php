<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('sales.title', [], 'Sales & Rentals');
$currentUser = getCurrentUser();

// Filtros
$filterType = $_GET['type'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$filterPayment = $_GET['payment'] ?? 'all';
$filterAgent = $_GET['agent'] ?? '';
$filterYear = $_GET['year'] ?? date('Y');
$search = $_GET['search'] ?? '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Actualizar estados de pago basados en facturas
db()->query("
    UPDATE sales_transactions st
    LEFT JOIN (
        SELECT transaction_id,
               SUM(total_amount) as total_invoiced,
               SUM(amount_paid) as total_paid,
               SUM(balance_due) as total_balance,
               COUNT(CASE WHEN status IN ('pending', 'overdue') THEN 1 END) as pending_count,
               COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
               MAX(CASE WHEN status IN ('pending', 'overdue') THEN 1 ELSE 0 END) as has_pending
        FROM invoices
        GROUP BY transaction_id
    ) inv ON st.id = inv.transaction_id
    SET 
        st.has_pending_invoice = COALESCE(inv.has_pending, 0),
        st.payment_status = CASE
            WHEN st.balance_pending <= 0 THEN 'completed'
            WHEN inv.total_paid > 0 AND inv.total_balance > 0 THEN 'partial'
            WHEN inv.pending_count > 0 THEN 'pending'
            WHEN st.balance_pending > 0 AND st.balance_pending < st.sale_price THEN 'partial'
            WHEN st.balance_pending > 0 THEN 'pending'
            ELSE 'completed'
        END
");

// Construir query con filtros
$where = ['1=1'];
$params = [];

if ($filterType !== 'all') {
    $where[] = 'st.transaction_type = ?';
    $params[] = $filterType;
}

if ($filterStatus !== 'all') {
    $where[] = 'st.status = ?';
    $params[] = $filterStatus;
}

if ($filterPayment !== 'all') {
    $where[] = 'st.payment_status = ?';
    $params[] = $filterPayment;
}

if (!empty($filterAgent)) {
    $where[] = 'st.agent_id = ?';
    $params[] = $filterAgent;
}

if ($filterYear !== 'all') {
    $where[] = 'YEAR(COALESCE(st.contract_date, st.created_at)) = ?';
    $params[] = $filterYear;
}

if (!empty($search)) {
    $where[] = '(st.transaction_code LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR p.reference LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $where);

// Total de registros
$totalTransactions = (int)db()->selectValue("
    SELECT COUNT(*) 
    FROM sales_transactions st
    INNER JOIN clients c ON st.client_id = c.id
    INNER JOIN properties p ON st.property_id = p.id
    WHERE {$whereClause}
", $params);

$totalPages = ceil($totalTransactions / $perPage);

// Obtener transacciones
$transactions = db()->select("
    SELECT st.*,
           CONCAT(c.first_name, ' ', c.last_name) as client_name,
           c.reference as client_reference,
           c.phone_mobile as client_phone,
           p.reference as property_reference,
           p.title as property_title,
           p.address as property_address,
           CONCAT(u.first_name, ' ', u.last_name) as agent_name,
           (SELECT COUNT(*) FROM invoices WHERE transaction_id = st.id) as invoice_count,
           (SELECT COUNT(*) FROM invoices WHERE transaction_id = st.id AND status IN ('pending', 'overdue')) as pending_invoices,
           (SELECT SUM(total_amount) FROM invoices WHERE transaction_id = st.id) as total_invoiced,
           (SELECT SUM(amount_paid) FROM invoices WHERE transaction_id = st.id) as total_paid_invoices
    FROM sales_transactions st
    INNER JOIN clients c ON st.client_id = c.id
    INNER JOIN properties p ON st.property_id = p.id
    LEFT JOIN users u ON st.agent_id = u.id
    WHERE {$whereClause}
    ORDER BY st.created_at DESC, st.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Estadísticas generales
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN transaction_type = 'sale' THEN 1 ELSE 0 END) as total_sales,
        SUM(CASE WHEN transaction_type IN ('rent', 'vacation_rent') THEN 1 ELSE 0 END) as total_rentals,
        SUM(CASE WHEN transaction_type = 'sale' THEN sale_price ELSE 0 END) as total_sales_amount,
        SUM(CASE WHEN transaction_type IN ('rent', 'vacation_rent') THEN monthly_payment ELSE 0 END) as total_monthly_rent,
        SUM(commission_amount) as total_commissions,
        SUM(balance_pending) as total_pending,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
        COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as paid_count,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payment_count,
        COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_payment_count
    FROM sales_transactions
    WHERE YEAR(COALESCE(contract_date, created_at)) = ?
", [$filterYear]);

// Datos para gráficas
$monthlyData = db()->select("
    SELECT 
        MONTH(COALESCE(contract_date, created_at)) as month,
        transaction_type,
        COUNT(*) as count,
        SUM(sale_price) as total_amount
    FROM sales_transactions
    WHERE YEAR(COALESCE(contract_date, created_at)) = ?
    GROUP BY MONTH(COALESCE(contract_date, created_at)), transaction_type
    ORDER BY month
", [$filterYear]);

// Procesar datos mensuales para gráficas
$monthlySales = array_fill(1, 12, 0);
$monthlyRentals = array_fill(1, 12, 0);
$monthlySalesAmount = array_fill(1, 12, 0);

foreach ($monthlyData as $data) {
    $month = (int)$data['month'];
    if ($data['transaction_type'] === 'sale') {
        $monthlySales[$month] = (int)$data['count'];
        $monthlySalesAmount[$month] = (float)$data['total_amount'];
    } else {
        $monthlyRentals[$month] = (int)$data['count'];
    }
}

// Top agentes
$topAgents = db()->select("
    SELECT 
        CONCAT(u.first_name, ' ', u.last_name) as agent_name,
        COUNT(*) as transaction_count,
        SUM(st.sale_price) as total_sales,
        SUM(st.commission_amount) as total_commission
    FROM sales_transactions st
    INNER JOIN users u ON st.agent_id = u.id
    WHERE YEAR(COALESCE(st.contract_date, st.created_at)) = ?
    GROUP BY st.agent_id
    ORDER BY total_sales DESC
    LIMIT 5
", [$filterYear]);

// Años disponibles
$years = db()->select("
    SELECT DISTINCT YEAR(COALESCE(contract_date, created_at)) as year 
    FROM sales_transactions 
    ORDER BY year DESC
");

// Agentes para filtro
$agents = db()->select("
    SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
    FROM users u
    INNER JOIN sales_transactions st ON st.agent_id = u.id
    ORDER BY u.first_name
");

include 'header.php';
include 'sidebar.php';
?>

<style>
:root {
    --primary: #667eea;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
}

.sales-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* Page Header */
.page-header-sales {
    background: white;
    padding: 25px 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title-sales {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 5px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title-sales i {
    color: var(--primary);
}

.page-subtitle-sales {
    color: #718096;
    margin: 0;
    font-size: 14px;
}

/* Stats Grid */
.stats-grid-sales {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card-sales {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 4px solid;
    transition: all 0.3s;
}

.stat-card-sales:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.stat-card-sales.total { border-color: var(--primary); }
.stat-card-sales.sales { border-color: var(--success); }
.stat-card-sales.rentals { border-color: #8b5cf6; }
.stat-card-sales.commissions { border-color: var(--warning); }
.stat-card-sales.pending { border-color: var(--danger); }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.stat-label {
    font-size: 13px;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.stat-card-sales.total .stat-icon { background: rgba(102, 126, 234, 0.1); color: var(--primary); }
.stat-card-sales.sales .stat-icon { background: rgba(16, 185, 129, 0.1); color: var(--success); }
.stat-card-sales.rentals .stat-icon { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
.stat-card-sales.commissions .stat-icon { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.stat-card-sales.pending .stat-icon { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.stat-count {
    font-size: 13px;
    color: #a0aec0;
}

/* Charts Section */
.charts-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.chart-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chart-title {
    font-size: 18px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-title i {
    color: var(--primary);
}

.chart-container {
    position: relative;
    height: 300px;
}

/* Filters */
.filters-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filter-group label {
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 5px;
    display: block;
}

.filter-group select,
.filter-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Sales Table */
.sales-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
}

.sales-table {
    width: 100%;
    border-collapse: collapse;
}

.sales-table thead {
    background: #f8f9fa;
}

.sales-table th {
    padding: 15px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    border-bottom: 2px solid #e5e7eb;
}

.sales-table td {
    padding: 15px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 14px;
    color: #4a5568;
}

.sales-table tbody tr:hover {
    background: #f8f9fa;
}

/* Transaction Code */
.transaction-code {
    font-weight: 600;
    color: var(--primary);
    font-family: 'Courier New', monospace;
}

/* Type Badge */
.type-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.type-badge.sale { background: rgba(16, 185, 129, 0.1); color: #059669; }
.type-badge.rent { background: rgba(139, 92, 246, 0.1); color: #7c3aed; }
.type-badge.vacation_rent { background: rgba(59, 130, 246, 0.1); color: #2563eb; }

/* Status Badge */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-badge.completed { background: rgba(16, 185, 129, 0.1); color: #059669; }
.status-badge.in_progress { background: rgba(245, 158, 11, 0.1); color: #d97706; }
.status-badge.pending { background: rgba(107, 114, 128, 0.1); color: #6b7280; }
.status-badge.cancelled { background: rgba(239, 68, 68, 0.1); color: #dc2626; }

/* Payment Status Badge */
.payment-status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.payment-status-badge.completed { background: rgba(16, 185, 129, 0.1); color: #059669; }
.payment-status-badge.paid { background: rgba(16, 185, 129, 0.1); color: #059669; } /* Mantener por compatibilidad */
.payment-status-badge.partial { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
.payment-status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #d97706; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.btn-action.view { background: rgba(59, 130, 246, 0.1); color: #2563eb; }
.btn-action.view:hover { background: #2563eb; color: white; }
.btn-action.edit { background: rgba(245, 158, 11, 0.1); color: #d97706; }
.btn-action.edit:hover { background: #d97706; color: white; }
.btn-action.delete { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
.btn-action.delete:hover { background: #dc2626; color: white; }
.btn-action.invoice { background: rgba(16, 185, 129, 0.1); color: #059669; }
.btn-action.invoice:hover { background: #059669; color: white; }

/* Buttons */
.btn-modern {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-outline {
    background: white;
    border: 2px solid #e5e7eb;
    color: #4a5568;
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Empty State */
.empty-state {
    padding: 60px 20px;
    text-align: center;
}

.empty-state-icon {
    font-size: 64px;
    color: #cbd5e0;
    margin-bottom: 20px;
}

.empty-state-title {
    font-size: 20px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
}

/* Pagination */
.pagination-container {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #e5e7eb;
}

.pagination-info {
    font-size: 14px;
    color: #718096;
}

.pagination {
    display: flex;
    gap: 5px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.pagination .page-item .page-link {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    color: #4a5568;
    text-decoration: none;
    font-size: 14px;
}

.pagination .page-item.active .page-link {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Responsive */
@media (max-width: 1200px) {
    .charts-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sales-container {
        padding: 15px;
    }
    
    .stats-grid-sales,
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header-sales {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<div class="sales-container">
    <!-- Page Header -->
    <div class="page-header-sales">
        <div>
            <h1 class="page-title-sales">
                <i class="fas fa-chart-line"></i>
                <?php echo __('sales.title', [], 'Sales & Rentals'); ?>
            </h1>
            <p class="page-subtitle-sales">
                <?php echo __('sales.subtitle', [], 'Manage all property transactions'); ?>
            </p>
        </div>
        <div>
            <a href="nueva-venta.php" class="btn-modern btn-primary">
                <i class="fas fa-plus"></i>
                <?php echo __('sales.new_transaction', [], 'New Transaction'); ?>
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid-sales">
        <div class="stat-card-sales total">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('sales.stats.total', [], 'Total Transactions'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $stats['total_transactions']; ?></div>
            <div class="stat-count">
                <?php echo $stats['total_sales']; ?> <?php echo __('sales.sales', [], 'sales'); ?> | 
                <?php echo $stats['total_rentals']; ?> <?php echo __('sales.rentals', [], 'rentals'); ?>
            </div>
        </div>

        <div class="stat-card-sales sales">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('sales.stats.sales_amount', [], 'Sales Amount'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_sales_amount'], 0); ?></div>
            <div class="stat-count"><?php echo __('sales.from_sales', [], 'From property sales'); ?></div>
        </div>

        <div class="stat-card-sales rentals">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('sales.stats.monthly_rent', [], 'Monthly Rent'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-home"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_monthly_rent'], 0); ?></div>
            <div class="stat-count"><?php echo __('sales.from_rentals', [], 'From active rentals'); ?></div>
        </div>

        <div class="stat-card-sales commissions">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('sales.stats.commissions', [], 'Commissions'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_commissions'], 0); ?></div>
            <div class="stat-count"><?php echo __('sales.earned', [], 'Total earned'); ?></div>
        </div>

        <div class="stat-card-sales pending">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('sales.stats.pending', [], 'Pending Balance'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_pending'], 0); ?></div>
            <div class="stat-count"><?php echo $stats['pending_payment_count']; ?> <?php echo __('sales.pending_payments', [], 'pending payments'); ?></div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-card">
            <div class="chart-title">
                <i class="fas fa-chart-bar"></i>
                <?php echo __('sales.monthly_transactions', [], 'Monthly Transactions'); ?>
            </div>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-title">
                <i class="fas fa-users"></i>
                <?php echo __('sales.top_agents', [], 'Top Agents'); ?>
            </div>
            <div style="padding: 10px 0;">
                <?php if (!empty($topAgents)): ?>
                    <?php foreach ($topAgents as $agent): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($agent['agent_name']); ?></span>
                            <span style="font-weight: 700; color: var(--primary);">$<?php echo number_format($agent['total_sales'], 0); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #718096; margin-bottom: 8px;">
                            <span><?php echo $agent['transaction_count']; ?> <?php echo __('sales.transactions', [], 'transactions'); ?></span>
                            <span><?php echo __('sales.commission', [], 'Commission'); ?>: $<?php echo number_format($agent['total_commission'], 0); ?></span>
                        </div>
                        <div style="height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%); width: <?php echo min(100, ($agent['total_sales'] / max(array_column($topAgents, 'total_sales'))) * 100); ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #a0aec0; padding: 20px;">
                        <?php echo __('sales.no_agents_data', [], 'No agent data available'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="ventas.php">
            <div class="filters-grid">
                <div class="filter-group">
                    <label><?php echo __('sales.filter.type', [], 'Type'); ?></label>
                    <select name="type" class="form-select">
                        <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>><?php echo __('sales.type.sale', [], 'Sale'); ?></option>
                        <option value="rent" <?php echo $filterType === 'rent' ? 'selected' : ''; ?>><?php echo __('sales.type.rent', [], 'Rental'); ?></option>
                        <option value="vacation_rent" <?php echo $filterType === 'vacation_rent' ? 'selected' : ''; ?>><?php echo __('sales.type.vacation', [], 'Vacation'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('sales.filter.status', [], 'Status'); ?></label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>><?php echo __('sales.status.completed', [], 'Completed'); ?></option>
                        <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>><?php echo __('sales.status.in_progress', [], 'In Progress'); ?></option>
                        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>><?php echo __('sales.status.pending', [], 'Pending'); ?></option>
                        <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>><?php echo __('sales.status.cancelled', [], 'Cancelled'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('sales.filter.payment', [], 'Payment'); ?></label>
                    <select name="payment" class="form-select">
                        <option value="all" <?php echo $filterPayment === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <option value="completed" <?php echo $filterPayment === 'completed' ? 'selected' : ''; ?>><?php echo __('sales.filter.paid', [], 'Paid'); ?></option>
                        <option value="partial" <?php echo $filterPayment === 'partial' ? 'selected' : ''; ?>><?php echo __('sales.filter.partial', [], 'Partial'); ?></option>
                        <option value="pending" <?php echo $filterPayment === 'pending' ? 'selected' : ''; ?>><?php echo __('sales.filter.pending', [], 'Pending'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('sales.filter.agent', [], 'Agent'); ?></label>
                    <select name="agent" class="form-select">
                        <option value=""><?php echo __('all', [], 'All'); ?></option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>" <?php echo $filterAgent == $agent['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('sales.filter.year', [], 'Year'); ?></label>
                    <select name="year" class="form-select">
                        <option value="all"><?php echo __('all', [], 'All'); ?></option>
                        <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['year']; ?>" <?php echo $filterYear == $y['year'] ? 'selected' : ''; ?>>
                            <?php echo $y['year']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('search', [], 'Search'); ?></label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="<?php echo __('sales.search_placeholder', [], 'Code, client, property...'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                <button type="submit" class="btn-modern btn-primary">
                    <i class="fas fa-search"></i>
                    <?php echo __('sales.filter.apply_filters', [], 'Apply Filters'); ?>
                </button>
                <a href="ventas.php" class="btn-modern btn-outline">
                    <i class="fas fa-redo"></i>
                    <?php echo __('sales.filter.clear_filters', [], 'Clear'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Sales Table -->
    <div class="sales-table-container">
        <div class="table-header">
            <div class="table-title">
                <?php echo __('sales.transaction_list', [], 'Transaction List'); ?>
                <span style="color: #a0aec0; font-weight: normal; font-size: 14px;">
                    (<?php echo $totalTransactions; ?> <?php echo __('sales.table.records', [], 'records'); ?>)
                </span>
            </div>
        </div>

        <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="empty-state-title">
                <?php echo __('sales.no_transactions', [], 'No transactions found'); ?>
            </div>
        </div>
        <?php else: ?>
        <table class="sales-table">
            <thead>
                <tr>
                    <th><?php echo __('sales.table.code', [], 'Code'); ?></th>
                    <th><?php echo __('sales.table.type', [], 'Type'); ?></th>
                    <th><?php echo __('sales.table.client', [], 'Client'); ?></th>
                    <th><?php echo __('sales.table.property', [], 'Property'); ?></th>
                    <th><?php echo __('sales.table.agent', [], 'Agent'); ?></th>
                    <th class="text-end"><?php echo __('sales.table.amount', [], 'Amount'); ?></th>
                    <th class="text-end"><?php echo __('sales.table.balance', [], 'Balance'); ?></th>
                    <th><?php echo __('sales.table.invoices', [], 'Invoices'); ?></th>
                    <th><?php echo __('sales.table.payment', [], 'Payment'); ?></th>
                    <th><?php echo __('sales.table.status', [], 'Status'); ?></th>
                    <th class="text-center"><?php echo __('actions', [], 'Actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td>
                        <span class="transaction-code"><?php echo htmlspecialchars($trans['transaction_code']); ?></span>
                    </td>
                    <td>
                        <span class="type-badge <?php echo $trans['transaction_type']; ?>">
                            <?php 
                            $typeLabels = [
                                'sale' => __('sales.type.sale', [], 'Sale'),
                                'rent' => __('sales.type.rent', [], 'Rental'),
                                'vacation_rent' => __('sales.type.vacation', [], 'Vacation')
                            ];
                            echo $typeLabels[$trans['transaction_type']] ?? $trans['transaction_type'];
                            ?>
                        </span>
                    </td>
                    <td>
                        <div>
                            <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($trans['client_name']); ?></div>
                            <div style="font-size: 12px; color: #a0aec0;"><?php echo htmlspecialchars($trans['client_reference']); ?></div>
                        </div>
                    </td>
                    <td>
                        <div>
                            <div style="font-weight: 600; color: #2d3748; font-size: 13px;"><?php echo htmlspecialchars($trans['property_reference']); ?></div>
                            <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars(substr($trans['property_title'], 0, 30)); ?>...</div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($trans['agent_name'] ?: '-'); ?></td>
                    <td class="text-end">
                        <strong style="color: var(--primary);">$<?php echo number_format($trans['sale_price'], 2); ?></strong>
                    </td>
                    <td class="text-end">
                        <strong style="color: <?php echo $trans['balance_pending'] > 0 ? 'var(--warning)' : 'var(--success)'; ?>;">
                            $<?php echo number_format($trans['balance_pending'], 2); ?>
                        </strong>
                    </td>
                    <td>
                        <div style="font-size: 13px;">
                            <div><?php echo $trans['invoice_count']; ?> <?php echo __('sales.total', [], 'total'); ?></div>
                            <?php if ($trans['pending_invoices'] > 0): ?>
                            <div style="color: var(--warning); font-weight: 600;">
                                <?php echo $trans['pending_invoices']; ?> <?php echo __('sales.pending', [], 'pending'); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="payment-status-badge <?php echo $trans['payment_status']; ?>">
                            <i class="fas fa-<?php 
                                echo $trans['payment_status'] === 'completed' ? 'check-circle' : 
                                    ($trans['payment_status'] === 'partial' ? 'clock' : 'hourglass-half'); 
                            ?>"></i>
                            <?php 
                            $paymentLabels = [
                                'completed' => __('sales.payment.paid', [], 'Paid'),
                                'partial' => __('sales.payment.partial', [], 'Partial'),
                                'pending' => __('sales.payment.pending', [], 'Pending')
                            ];
                            echo $paymentLabels[$trans['payment_status']] ?? $trans['payment_status'];
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $trans['status']; ?>">
                            <?php 
                            $statusLabels = [
                                'completed' => __('sales.status.completed', [], 'Completed'),
                                'in_progress' => __('sales.status.in_progress', [], 'In Progress'),
                                'pending' => __('sales.status.pending', [], 'Pending'),
                                'cancelled' => __('sales.status.cancelled', [], 'Cancelled')
                            ];
                            echo $statusLabels[$trans['status']] ?? $trans['status'];
                            ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action view" 
                                    onclick="window.location.href='ver-venta.php?id=<?php echo $trans['id']; ?>'" 
                                    title="<?php echo __('view', [], 'View'); ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-action invoice" 
                                    onclick="window.location.href='facturacion.php?transaction=<?php echo $trans['id']; ?>'" 
                                    title="<?php echo __('sales.invoices', [], 'Invoices'); ?>">
                                <i class="fas fa-file-invoice"></i>
                            </button>
                            <button class="btn-action edit" 
                                    onclick="window.location.href='editar-venta.php?id=<?php echo $trans['id']; ?>'" 
                                    title="<?php echo __('edit', [], 'Edit'); ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($currentUser['role']['name'] === 'administrador'): ?>
                            <button class="btn-action delete" 
                                    onclick="deleteTransaction(<?php echo $trans['id']; ?>)" 
                                    title="<?php echo __('delete', [], 'Delete'); ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                <?php echo __('showing', [], 'Showing'); ?> <?php echo (($page - 1) * $perPage) + 1; ?> - <?php echo min($page * $perPage, $totalTransactions); ?> 
                <?php echo __('of', [], 'of'); ?> <?php echo $totalTransactions; ?>
            </div>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [
            {
                label: '<?php echo __('sales.sales', [], 'Sales'); ?>',
                data: <?php echo json_encode(array_values($monthlySales)); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
                borderRadius: 8
            },
            {
                label: '<?php echo __('sales.rentals', [], 'Rentals'); ?>',
                data: <?php echo json_encode(array_values($monthlyRentals)); ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.8)',
                borderColor: 'rgba(139, 92, 246, 1)',
                borderWidth: 2,
                borderRadius: 8
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 15,
                    font: {
                        size: 13,
                        weight: 600
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 8,
                titleFont: {
                    size: 14,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                },
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Delete Transaction
function deleteTransaction(id) {
    if (!confirm('<?php echo __('sales.delete_confirm', [], 'Are you sure you want to delete this transaction? This action cannot be undone.'); ?>')) {
        return;
    }
    
    fetch('actions/venta-actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ <?php echo __('sales.deleted_successfully', [], 'Transaction deleted successfully'); ?>');
            location.reload();
        } else {
            alert('❌ <?php echo __('error', [], 'Error'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo __('sales.delete_error', [], 'Error deleting transaction'); ?>');
    });
}
</script>

<?php include 'footer.php'; ?>
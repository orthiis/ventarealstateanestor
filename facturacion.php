<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('invoicing.title', [], 'Invoicing');
$currentUser = getCurrentUser();

// Filtros
$filterStatus = $_GET['status'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';
$filterYear = $_GET['year'] ?? date('Y');
$filterMonth = $_GET['month'] ?? 'all';
$filterClient = $_GET['client'] ?? '';
$search = $_GET['search'] ?? '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Construir query con filtros
$where = ['1=1'];
$params = [];

if ($filterStatus !== 'all') {
    $where[] = 'i.status = ?';
    $params[] = $filterStatus;
}

if ($filterType !== 'all') {
    $where[] = 'i.invoice_type = ?';
    $params[] = $filterType;
}

if ($filterYear !== 'all') {
    $where[] = 'YEAR(i.invoice_date) = ?';
    $params[] = $filterYear;
}

if ($filterMonth !== 'all') {
    $where[] = 'MONTH(i.invoice_date) = ?';
    $params[] = $filterMonth;
}

if (!empty($filterClient)) {
    $where[] = 'i.client_id = ?';
    $params[] = $filterClient;
}

if (!empty($search)) {
    $where[] = '(i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR p.reference LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $where);

// Total de registros
$totalInvoices = (int)db()->selectValue("
    SELECT COUNT(*) 
    FROM invoices i
    INNER JOIN clients c ON i.client_id = c.id
    INNER JOIN properties p ON i.property_id = p.id
    WHERE {$whereClause}
", $params);

$totalPages = ceil($totalInvoices / $perPage);

// Obtener facturas
$invoices = db()->select("
    SELECT i.*,
           CONCAT(c.first_name, ' ', c.last_name) as client_name,
           c.reference as client_reference,
           c.email as client_email,
           c.phone_mobile as client_phone,
           p.reference as property_reference,
           p.title as property_title,
           st.transaction_code,
           st.transaction_type,
           CONCAT(u.first_name, ' ', u.last_name) as agent_name
    FROM invoices i
    INNER JOIN clients c ON i.client_id = c.id
    INNER JOIN properties p ON i.property_id = p.id
    INNER JOIN sales_transactions st ON i.transaction_id = st.id
    LEFT JOIN users u ON i.agent_id = u.id
    WHERE {$whereClause}
    ORDER BY i.invoice_number DESC, i.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Obtener años disponibles
$years = db()->select("SELECT DISTINCT YEAR(invoice_date) as year FROM invoices ORDER BY year DESC");

// Obtener clientes para filtro
$clients = db()->select("
    SELECT DISTINCT c.id, CONCAT(c.first_name, ' ', c.last_name) as name
    FROM clients c
    INNER JOIN invoices i ON i.client_id = c.id
    ORDER BY c.first_name
");

// Estadísticas
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status IN ('pending', 'partial') THEN balance_due ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = 'overdue' THEN balance_due ELSE 0 END) as total_overdue,
        SUM(CASE WHEN invoice_type = 'rent' AND status = 'paid' THEN total_amount ELSE 0 END) as rent_income,
        SUM(CASE WHEN invoice_type = 'sale' AND status = 'paid' THEN total_amount ELSE 0 END) as sale_income,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
    FROM invoices
    WHERE YEAR(invoice_date) = ?
", [$filterYear]);

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
    --dark: #1f2937;
}

.invoicing-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* ============ HEADER ============ */
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
    gap: 20px;
}

.page-title-modern {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 5px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title-modern i {
    color: var(--primary);
}

.page-subtitle-modern {
    color: #718096;
    margin: 0;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* ============ STATS CARDS ============ */
.stats-grid-invoicing {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-invoicing {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border-left: 4px solid;
}

.stat-card-invoicing:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.stat-card-invoicing.total { border-color: var(--primary); }
.stat-card-invoicing.paid { border-color: var(--success); }
.stat-card-invoicing.pending { border-color: var(--warning); }
.stat-card-invoicing.overdue { border-color: var(--danger); }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.stat-label {
    font-size: 13px;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.stat-card-invoicing.total .stat-icon {
    background: #ede9fe;
    color: var(--primary);
}

.stat-card-invoicing.paid .stat-icon {
    background: #d1fae5;
    color: var(--success);
}

.stat-card-invoicing.pending .stat-icon {
    background: #fef3c7;
    color: var(--warning);
}

.stat-card-invoicing.overdue .stat-icon {
    background: #fee2e2;
    color: var(--danger);
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin: 10px 0 5px 0;
}

.stat-description {
    font-size: 12px;
    color: #a0aec0;
}

/* ============ FILTERS ============ */
.filters-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.form-group-filter {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label-filter {
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control-filter {
    padding: 10px 15px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control-filter:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filters-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* ============ INVOICES TABLE ============ */
.invoices-table-container {
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

.table-actions {
    display: flex;
    gap: 10px;
}

.invoices-table {
    width: 100%;
    border-collapse: collapse;
}

.invoices-table thead {
    background: #f8f9fa;
}

.invoices-table th {
    padding: 15px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e5e7eb;
}

.invoices-table td {
    padding: 15px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 14px;
    color: #4a5568;
}

.invoices-table tbody tr {
    transition: all 0.2s;
}

.invoices-table tbody tr:hover {
    background: #f8f9fa;
}

/* Invoice Number */
.invoice-number {
    font-weight: 600;
    color: var(--primary);
    font-family: 'Courier New', monospace;
}

/* Client Info */
.client-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.client-name {
    font-weight: 600;
    color: #2d3748;
}

.client-reference {
    font-size: 12px;
    color: #a0aec0;
}

/* Property Info */
.property-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.property-reference {
    font-weight: 600;
    color: #2d3748;
    font-size: 13px;
}

.property-title {
    font-size: 12px;
    color: #718096;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}

/* Status Badges */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-badge.paid {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.pending {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.partial {
    background: #dbeafe;
    color: #1e40af;
}

.status-badge.overdue {
    background: #fee2e2;
    color: #991b1b;
}

.status-badge.cancelled {
    background: #f3f4f6;
    color: #4b5563;
}

/* Type Badges */
.type-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.type-badge.rent {
    background: #ede9fe;
    color: #6d28d9;
}

.type-badge.sale {
    background: #d1fae5;
    color: #065f46;
}

/* Action Buttons */
.action-btn {
    padding: 6px 10px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    background: none;
    color: #718096;
}

.action-btn:hover {
    background: #f8f9fa;
    color: var(--primary);
}

.action-btn.danger:hover {
    background: #fee2e2;
    color: var(--danger);
}

/* ============ PAGINATION ============ */
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
    transition: all 0.3s;
}

.pagination .page-item.active .page-link {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .page-item .page-link:hover {
    background: #f8f9fa;
    border-color: var(--primary);
}

/* ============ BUTTONS ============ */
.btn-modern {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
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

/* ============ EMPTY STATE ============ */
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

.empty-state-text {
    color: #718096;
    margin-bottom: 25px;
}

/* ============ MODALS ============ */
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-header {
    border-bottom: 1px solid #e5e7eb;
    padding: 20px 25px;
}

.modal-title {
    font-size: 20px;
    font-weight: 600;
    color: #2d3748;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid #e5e7eb;
    padding: 20px 25px;
}

/* Responsive */
@media (max-width: 768px) {
    .invoicing-container {
        padding: 15px;
    }
    
    .stats-grid-invoicing {
        grid-template-columns: 1fr;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .invoices-table {
        font-size: 12px;
    }
    
    .invoices-table th,
    .invoices-table td {
        padding: 10px 8px;
    }
}
</style>

<div class="invoicing-container">
    <!-- Page Header -->
    <div class="page-header-modern">
        <div>
            <h1 class="page-title-modern">
                <i class="fas fa-file-invoice-dollar"></i>
                <?php echo __('invoicing.title', [], 'Invoicing'); ?>
            </h1>
            <p class="page-subtitle-modern">
                <?php echo __('invoicing.subtitle', [], 'Sales and rental invoice management'); ?>
            </p>
        </div>
        <div class="header-actions">
            <button class="btn-modern btn-success" onclick="openGenerateRentModal()">
                <i class="fas fa-magic"></i>
                <?php echo __('invoicing.generate_rent_invoices', [], 'Generate Rental Invoices'); ?>
            </button>
            <button class="btn-modern btn-primary" onclick="openGenerateSaleModal()">
                <i class="fas fa-plus"></i>
                <?php echo __('invoicing.generate_sale_invoice', [], 'Generate Sale Invoice'); ?>
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo $_SESSION['flash_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php 
    unset($_SESSION['flash_message'], $_SESSION['flash_type']); 
    endif; 
    ?>

    <!-- Statistics -->
    <div class="stats-grid-invoicing">
        <div class="stat-card-invoicing total">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('invoicing.total', [], 'Total'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $stats['total_invoices']; ?></div>
            <div class="stat-description"><?php echo __('invoicing.total_invoices', [], 'Total Invoices'); ?></div>
        </div>

        <div class="stat-card-invoicing paid">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('paid', [], 'Paid'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_paid'], 2); ?></div>
            <div class="stat-description"><?php echo $stats['paid_count']; ?> <?php echo __('invoicing.paid_invoices', [], 'paid invoices'); ?></div>
        </div>

        <div class="stat-card-invoicing pending">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('pending', [], 'Pending'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_pending'], 2); ?></div>
            <div class="stat-description"><?php echo $stats['pending_count']; ?> <?php echo __('invoicing.pending_invoices', [], 'pending invoices'); ?></div>
        </div>

        <div class="stat-card-invoicing overdue">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('overdue', [], 'Overdue'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_overdue'], 2); ?></div>
            <div class="stat-description"><?php echo $stats['overdue_count']; ?> <?php echo __('invoicing.overdue_invoices', [], 'overdue invoices'); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="form-group-filter">
                    <label class="form-label-filter"><?php echo __('search', [], 'Search'); ?></label>
                    <input type="text" name="search" class="form-control-filter" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo __('invoicing.search_placeholder', [], 'Invoice #, client, property...'); ?>">
                </div>

                <div class="form-group-filter">
                    <label class="form-label-filter"><?php echo __('status', [], 'Status'); ?></label>
                    <select name="status" class="form-control-filter">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>><?php echo __('paid', [], 'Paid'); ?></option>
                        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>><?php echo __('pending', [], 'Pending'); ?></option>
                        <option value="partial" <?php echo $filterStatus === 'partial' ? 'selected' : ''; ?>><?php echo __('partial', [], 'Partial'); ?></option>
                        <option value="overdue" <?php echo $filterStatus === 'overdue' ? 'selected' : ''; ?>><?php echo __('overdue', [], 'Overdue'); ?></option>
                    </select>
                </div>

                <div class="form-group-filter">
                    <label class="form-label-filter"><?php echo __('type', [], 'Type'); ?></label>
                    <select name="type" class="form-control-filter">
                        <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <option value="rent" <?php echo $filterType === 'rent' ? 'selected' : ''; ?>><?php echo __('rent', [], 'Rent'); ?></option>
                        <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>><?php echo __('sale', [], 'Sale'); ?></option>
                    </select>
                </div>

                <div class="form-group-filter">
                    <label class="form-label-filter"><?php echo __('year', [], 'Year'); ?></label>
                    <select name="year" class="form-control-filter">
                        <option value="all" <?php echo $filterYear === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['year']; ?>" <?php echo $filterYear == $y['year'] ? 'selected' : ''; ?>>
                            <?php echo $y['year']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group-filter">
                    <label class="form-label-filter"><?php echo __('month', [], 'Month'); ?></label>
                    <select name="month" class="form-control-filter">
                        <option value="all" <?php echo $filterMonth === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <?php 
                        $months = [
                            1 => __('january', [], 'January'),
                            2 => __('february', [], 'February'),
                            3 => __('march', [], 'March'),
                            4 => __('april', [], 'April'),
                            5 => __('may', [], 'May'),
                            6 => __('june', [], 'June'),
                            7 => __('july', [], 'July'),
                            8 => __('august', [], 'August'),
                            9 => __('september', [], 'September'),
                            10 => __('october', [], 'October'),
                            11 => __('november', [], 'November'),
                            12 => __('december', [], 'December')
                        ];
                        foreach ($months as $num => $name): 
                        ?>
                        <option value="<?php echo $num; ?>" <?php echo $filterMonth == $num ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group-filter">
                    <label class="form-label-filter"><?php echo __('client', [], 'Client'); ?></label>
                    <select name="client" class="form-control-filter">
                        <option value=""><?php echo __('all', [], 'All'); ?></option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $filterClient == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filters-actions">
                <button type="submit" class="btn-modern btn-primary">
                    <i class="fas fa-search"></i>
                    <?php echo __('filter', [], 'Filter'); ?>
                </button>
                <a href="facturacion.php" class="btn-modern btn-outline">
                    <i class="fas fa-redo"></i>
                    <?php echo __('reset', [], 'Reset'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Invoices Table -->
    <div class="invoices-table-container">
        <div class="table-header">
            <h3 class="table-title">
                <i class="fas fa-list me-2"></i>
                <?php echo __('invoicing.invoice_list', [], 'Invoice List'); ?>
            </h3>
        </div>

        <?php if (count($invoices) > 0): ?>
        <table class="invoices-table">
            <thead>
                <tr>
                    <th><?php echo __('invoice', [], 'Invoice'); ?></th>
                    <th><?php echo __('client', [], 'Client'); ?></th>
                    <th><?php echo __('property', [], 'Property'); ?></th>
                    <th><?php echo __('type', [], 'Type'); ?></th>
                    <th><?php echo __('date', [], 'Date'); ?></th>
                    <th><?php echo __('due_date', [], 'Due Date'); ?></th>
                    <th><?php echo __('total', [], 'Total'); ?></th>
                    <th><?php echo __('balance', [], 'Balance'); ?></th>
                    <th><?php echo __('status', [], 'Status'); ?></th>
                    <th><?php echo __('actions', [], 'Actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td>
                        <span class="invoice-number"><?php echo htmlspecialchars($inv['invoice_number']); ?></span>
                    </td>
                    <td>
                        <div class="client-info">
                            <span class="client-name"><?php echo htmlspecialchars($inv['client_name']); ?></span>
                            <span class="client-reference"><?php echo htmlspecialchars($inv['client_reference']); ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="property-info">
                            <span class="property-reference"><?php echo htmlspecialchars($inv['property_reference']); ?></span>
                            <span class="property-title" title="<?php echo htmlspecialchars($inv['property_title']); ?>">
                                <?php echo htmlspecialchars($inv['property_title']); ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="type-badge <?php echo $inv['invoice_type']; ?>">
                            <?php echo $inv['invoice_type'] === 'rent' ? __('rent', [], 'Rent') : __('sale', [], 'Sale'); ?>
                        </span>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($inv['invoice_date'])); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($inv['due_date'])); ?></td>
                    <td>
                        <strong style="color: var(--primary);">$<?php echo number_format($inv['total_amount'], 2); ?></strong>
                    </td>
                    <td>
                        <strong style="color: <?php echo $inv['balance_due'] > 0 ? 'var(--warning)' : 'var(--success)'; ?>;">
                            $<?php echo number_format($inv['balance_due'], 2); ?>
                        </strong>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $inv['status']; ?>">
                            <i class="fas fa-<?php 
                                echo $inv['status'] === 'paid' ? 'check-circle' : 
                                    ($inv['status'] === 'partial' ? 'clock' : 
                                    ($inv['status'] === 'overdue' ? 'exclamation-triangle' : 'hourglass-half')); 
                            ?>"></i>
                            <?php 
                            $statusLabels = [
                                'paid' => __('paid', [], 'Paid'),
                                'pending' => __('pending', [], 'Pending'),
                                'partial' => __('partial', [], 'Partial'),
                                'overdue' => __('overdue', [], 'Overdue'),
                                'cancelled' => __('cancelled', [], 'Cancelled')
                            ];
                            echo $statusLabels[$inv['status']] ?? $inv['status'];
                            ?>
                        </span>
                    </td>
                    <td>
                        <button class="action-btn" onclick="viewInvoice(<?php echo $inv['id']; ?>)" title="<?php echo __('view', [], 'View'); ?>">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'cancelled'): ?>
                        <button class="action-btn" onclick="registerPayment(<?php echo $inv['id']; ?>)" title="<?php echo __('register_payment', [], 'Register Payment'); ?>">
                            <i class="fas fa-dollar-sign"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($inv['status'] === 'pending'): ?>
                        <button class="action-btn danger" onclick="deleteInvoice(<?php echo $inv['id']; ?>)" title="<?php echo __('delete', [], 'Delete'); ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                <?php echo __('showing', [], 'Showing'); ?> <?php echo ($offset + 1); ?> - <?php echo min($offset + $perPage, $totalInvoices); ?> 
                <?php echo __('of', [], 'of'); ?> <?php echo $totalInvoices; ?> <?php echo __('results', [], 'results'); ?>
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
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <h3 class="empty-state-title"><?php echo __('invoicing.no_invoices', [], 'No invoices found'); ?></h3>
            <p class="empty-state-text"><?php echo __('invoicing.no_invoices_text', [], 'Try adjusting your filters or create a new invoice'); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Generate Rent Invoices -->
<div class="modal fade" id="generateRentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-magic text-primary me-2"></i>
                    <?php echo __('invoicing.generate_rent_invoices', [], 'Generate Rental Invoices'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php echo __('invoicing.generate_info', [], 'Invoices will be generated based on payment dates configured for each client'); ?>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <?php echo __('invoicing.generation_mode', [], 'Generation Mode'); ?>
                    </label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="generation_mode" id="mode_all" value="all" checked>
                        <label class="form-check-label" for="mode_all">
                            <?php echo __('invoicing.generate_all', [], 'Generate for ALL clients with active rentals'); ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="generation_mode" id="mode_selective" value="selective">
                        <label class="form-check-label" for="mode_selective">
                            <?php echo __('invoicing.generate_selective', [], 'Select specific clients'); ?>
                        </label>
                    </div>
                </div>

                <div id="selectiveClients" style="display: none;">
                    <label class="form-label fw-bold">
                        <?php echo __('invoicing.select_clients', [], 'Select Clients'); ?>
                    </label>
                    <div id="clientsCheckboxList" style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">
                        <!-- Se llenará con AJAX -->
                    </div>
                </div>

                <div id="generationProgress" style="display: none; margin-top: 20px;">
                    <div class="progress" style="height: 25px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             style="width: 0%">0%</div>
                    </div>
                    <div id="progressText" class="text-center mt-2" style="color: #718096;"></div>
                </div>

                <div id="generationResults" style="display: none; margin-top: 20px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <?php echo __('cancel', [], 'Cancel'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="btnConfirmGeneration">
                    <i class="fas fa-magic me-2"></i>
                    <?php echo __('invoicing.start_generation', [], 'Start Generation'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Generate Sale Invoice -->
<div class="modal fade" id="generateSaleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus text-primary me-2"></i>
                    <?php echo __('invoicing.generate_sale_invoice', [], 'Generate Sale Invoice'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="saleInvoiceForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('invoicing.select_sale', [], 'Select Sale Transaction'); ?></label>
                        <select class="form-control" name="transaction_id" id="saleTransactionSelect" required>
                            <option value="">-- <?php echo __('select', [], 'Select'); ?> --</option>
                        </select>
                    </div>

                    <div id="saleDetails" style="display: none;">
                        <div class="alert alert-light mb-3">
                            <div class="mb-2"><strong><?php echo __('client', [], 'Client'); ?>:</strong> <span id="saleClient"></span></div>
                            <div class="mb-2"><strong><?php echo __('property', [], 'Property'); ?>:</strong> <span id="saleProperty"></span></div>
                            <div class="mb-2"><strong><?php echo __('total', [], 'Total'); ?>:</strong> <span id="saleTotal"></span></div>
                            <div><strong><?php echo __('balance', [], 'Balance'); ?>:</strong> <span id="saleBalance" class="text-danger"></span></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __('invoicing.invoice_amount', [], 'Invoice Amount'); ?> *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="invoice_amount" 
                                       step="0.01" min="0.01" required id="invoiceAmount">
                            </div>
                            <small class="text-muted"><?php echo __('invoicing.can_invoice_partial', [], 'You can invoice the total or a partial amount'); ?></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __('invoicing.due_days', [], 'Days to Due Date'); ?></label>
                            <input type="number" class="form-control" name="due_days" value="30" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __('notes', [], 'Notes'); ?></label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?php echo __('cancel', [], 'Cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>
                        <?php echo __('invoicing.generate', [], 'Generate Invoice'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Register Payment -->
<div class="modal fade" id="registerPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-dollar-sign text-success me-2"></i>
                    <?php echo __('invoicing.register_payment', [], 'Register Payment'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentForm">
                <input type="hidden" name="invoice_id" id="paymentInvoiceId">
                <div class="modal-body">
                    <div id="paymentInvoiceDetails"></div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payment_date', [], 'Payment Date'); ?> *</label>
                        <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payment_amount', [], 'Payment Amount'); ?> *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="payment_amount" id="paymentAmount"
                                   step="0.01" min="0.01" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payment_method', [], 'Payment Method'); ?> *</label>
                        <select class="form-control" name="payment_method" required>
                            <option value="cash"><?php echo __('cash', [], 'Cash'); ?></option>
                            <option value="bank_transfer"><?php echo __('bank_transfer', [], 'Bank Transfer'); ?></option>
                            <option value="check"><?php echo __('check', [], 'Check'); ?></option>
                            <option value="credit_card"><?php echo __('credit_card', [], 'Credit Card'); ?></option>
                            <option value="debit_card"><?php echo __('debit_card', [], 'Debit Card'); ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payment_reference', [], 'Payment Reference'); ?></label>
                        <input type="text" class="form-control" name="payment_reference" 
                               placeholder="<?php echo __('check_number_transfer_id', [], 'Check number, transfer ID, etc.'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('notes', [], 'Notes'); ?></label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <?php echo __('cancel', [], 'Cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>
                        <?php echo __('invoicing.register', [], 'Register Payment'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modals
let generateRentModal, generateSaleModal, registerPaymentModal;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modals
    generateRentModal = new bootstrap.Modal(document.getElementById('generateRentModal'));
    generateSaleModal = new bootstrap.Modal(document.getElementById('generateSaleModal'));
    registerPaymentModal = new bootstrap.Modal(document.getElementById('registerPaymentModal'));

    // Generation mode toggle
    document.querySelectorAll('input[name="generation_mode"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('selectiveClients').style.display = 
                this.value === 'selective' ? 'block' : 'none';
            
            if (this.value === 'selective') {
                loadRentalClients();
            }
        });
    });

    // Confirm generation button
    document.getElementById('btnConfirmGeneration').addEventListener('click', function() {
        startRentGeneration();
    });

    // Sale transaction select change
    document.getElementById('saleTransactionSelect').addEventListener('change', function() {
        if (this.value) {
            loadSaleDetails(this.value);
        } else {
            document.getElementById('saleDetails').style.display = 'none';
        }
    });

    // Sale invoice form submit
    document.getElementById('saleInvoiceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        generateSaleInvoiceSubmit();
    });

    // Payment form submit
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitPayment();
    });
});

// Open generate rent modal
function openGenerateRentModal() {
    generateRentModal.show();
}

// Open generate sale modal
function openGenerateSaleModal() {
    // Load pending sales first
    fetch('ajax/get-pending-sales.php')
        .then(response => response.json())
        .then(data => {
            const select = document.getElementById('saleTransactionSelect');
            if (data.success && data.sales && data.sales.length > 0) {
                select.innerHTML = '<option value="">-- <?php echo __('select', [], 'Select'); ?> --</option>' +
                    data.sales.map(sale => `
                        <option value="${sale.id}" 
                                data-client="${sale.client_name}"
                                data-property="${sale.property_reference}"
                                data-total="${sale.sale_price}"
                                data-balance="${sale.balance_pending}">
                            ${sale.transaction_code} - ${sale.client_name} - $${parseFloat(sale.balance_pending).toFixed(2)}
                        </option>
                    `).join('');
            } else {
                select.innerHTML = '<option value=""><?php echo __('invoicing.no_pending_sales', [], 'No sales pending invoicing'); ?></option>';
            }
            generateSaleModal.show();
        })
        .catch(error => {
            console.error('Error loading sales:', error);
            alert('<?php echo __('error_loading_data', [], 'Error loading data'); ?>');
        });
}

// Load rental clients for selective generation
function loadRentalClients() {
    fetch('ajax/get-rental-clients.php')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('clientsCheckboxList');
            if (data.success && data.clients && data.clients.length > 0) {
                container.innerHTML = data.clients.map(client => `
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="${client.transaction_id}" 
                               id="client_${client.transaction_id}" name="selected_clients[]">
                        <label class="form-check-label" for="client_${client.transaction_id}">
                            <strong>${client.client_name}</strong> - ${client.property_reference}
                            <br><small class="text-muted"><?php echo __('invoicing.pays_day', [], 'Pays day'); ?> ${client.payment_day} | $${parseFloat(client.monthly_payment).toFixed(2)}/<?php echo __('invoicing.month', [], 'month'); ?></small>
                        </label>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted"><?php echo __('invoicing.no_pending_rentals', [], 'No clients with pending rental invoicing'); ?></p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo __('error_loading_clients', [], 'Error loading clients'); ?>');
        });
}

// Start rent generation
function startRentGeneration() {
    const mode = document.querySelector('input[name="generation_mode"]:checked').value;
    let clientIds = [];
    
    if (mode === 'selective') {
        clientIds = Array.from(document.querySelectorAll('input[name="selected_clients[]"]:checked'))
            .map(cb => cb.value);
        
        if (clientIds.length === 0) {
            alert('<?php echo __('invoicing.select_at_least_one', [], 'You must select at least one client'); ?>');
            return;
        }
    }

    // Disable button and show progress
    document.getElementById('btnConfirmGeneration').disabled = true;
    document.getElementById('generationProgress').style.display = 'block';

    const formData = new FormData();
    formData.append('mode', mode);
    formData.append('client_ids', JSON.stringify(clientIds));

    fetch('ajax/generar-facturas-alquiler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('generationProgress').style.display = 'none';
        document.getElementById('generationResults').style.display = 'block';

        if (data.success) {
            document.getElementById('generationResults').innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong><?php echo __('invoicing.generation_completed', [], 'Generation completed!'); ?></strong><br>
                    ${data.generated} <?php echo __('invoicing.invoices_generated', [], 'invoice(s) generated successfully'); ?>.
                    ${data.errors > 0 ? `<br><span class="text-warning">${data.errors} <?php echo __('errors_found', [], 'error(s) found'); ?>.</span>` : ''}
                </div>
            `;
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            document.getElementById('generationResults').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo __('error', [], 'Error'); ?>: ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('generationResults').innerHTML = `
            <div class="alert alert-danger"><?php echo __('invoicing.generation_error', [], 'Error generating invoices'); ?></div>
        `;
    })
    .finally(() => {
        document.getElementById('btnConfirmGeneration').disabled = false;
    });
}

// Load sale details
function loadSaleDetails(transactionId) {
    const select = document.getElementById('saleTransactionSelect');
    const selected = select.options[select.selectedIndex];
    
    if (!selected || !selected.dataset.client) {
        return;
    }
    
    document.getElementById('saleClient').textContent = selected.dataset.client;
    document.getElementById('saleProperty').textContent = selected.dataset.property;
    document.getElementById('saleTotal').textContent = '$' + parseFloat(selected.dataset.total).toFixed(2);
    document.getElementById('saleBalance').textContent = '$' + parseFloat(selected.dataset.balance).toFixed(2);
    document.getElementById('invoiceAmount').value = selected.dataset.balance;
    document.getElementById('invoiceAmount').max = selected.dataset.balance;
    
    document.getElementById('saleDetails').style.display = 'block';
}

// Generate sale invoice submit
function generateSaleInvoiceSubmit() {
    const formData = new FormData(document.getElementById('saleInvoiceForm'));
    
    fetch('ajax/generar-factura-venta.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ <?php echo __('invoicing.invoice_generated_success', [], 'Invoice generated successfully'); ?>');
            generateSaleModal.hide();
            location.reload();
        } else {
            alert('❌ <?php echo __('error', [], 'Error'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo __('invoicing.invoice_generation_error', [], 'Error generating invoice'); ?>');
    });
}

// View invoice
function viewInvoice(invoiceId) {
    window.location.href = 'ver-factura.php?id=' + invoiceId;
}

// Register payment
function registerPayment(invoiceId) {
    document.getElementById('paymentInvoiceId').value = invoiceId;
    
    // Load invoice details
    fetch('ajax/get-invoice-details.php?id=' + invoiceId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const inv = data.invoice;
                document.getElementById('paymentInvoiceDetails').innerHTML = `
                    <div class="alert alert-light mb-3">
                        <div class="mb-2"><strong><?php echo __('invoice', [], 'Invoice'); ?>:</strong> ${inv.invoice_number}</div>
                        <div class="mb-2"><strong><?php echo __('client', [], 'Client'); ?>:</strong> ${inv.client_name}</div>
                        <div class="mb-2"><strong><?php echo __('total', [], 'Total'); ?>:</strong> $${parseFloat(inv.total_amount).toFixed(2)}</div>
                        <div class="mb-2"><strong><?php echo __('paid', [], 'Paid'); ?>:</strong> $${parseFloat(inv.amount_paid).toFixed(2)}</div>
                        <div><strong><?php echo __('balance', [], 'Balance'); ?>:</strong> <span class="text-danger">$${parseFloat(inv.balance_due).toFixed(2)}</span></div>
                    </div>
                `;
                document.getElementById('paymentAmount').value = inv.balance_due;
                document.getElementById('paymentAmount').max = inv.balance_due;
                
                registerPaymentModal.show();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('<?php echo __('error_loading_data', [], 'Error loading data'); ?>');
        });
}

// Submit payment
function submitPayment() {
    const formData = new FormData(document.getElementById('paymentForm'));
    
    fetch('ajax/procesar-pago-factura.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ <?php echo __('invoicing.payment_registered_success', [], 'Payment registered successfully'); ?>');
            registerPaymentModal.hide();
            location.reload();
        } else {
            alert('❌ <?php echo __('error', [], 'Error'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo __('invoicing.payment_registration_error', [], 'Error registering payment'); ?>');
    });
}

// Delete invoice
function deleteInvoice(invoiceId) {
    if (!confirm('<?php echo __('invoicing.delete_confirm', [], 'Are you sure you want to delete this invoice? This action cannot be undone.'); ?>')) {
        return;
    }
    
    fetch('ajax/delete-invoice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'invoice_id=' + invoiceId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ <?php echo __('invoicing.invoice_deleted_success', [], 'Invoice deleted successfully'); ?>');
            location.reload();
        } else {
            alert('❌ <?php echo __('error', [], 'Error'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo __('invoicing.invoice_deletion_error', [], 'Error deleting invoice'); ?>');
    });
}
</script>

<?php include 'footer.php'; ?>
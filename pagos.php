<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('payments.title', [], 'Payments');
$currentUser = getCurrentUser();

// Filtros
$filterType = $_GET['type'] ?? 'all';
$filterMethod = $_GET['method'] ?? 'all';
$filterYear = $_GET['year'] ?? date('Y');
$filterMonth = $_GET['month'] ?? 'all';
$search = $_GET['search'] ?? '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Construir query
$where = ['1=1'];
$params = [];

if ($filterType !== 'all') {
    $where[] = 'i.invoice_type = ?';
    $params[] = $filterType;
}

if ($filterMethod !== 'all') {
    $where[] = 'ip.payment_method = ?';
    $params[] = $filterMethod;
}

if ($filterYear !== 'all') {
    $where[] = 'YEAR(ip.payment_date) = ?';
    $params[] = $filterYear;
}

if ($filterMonth !== 'all') {
    $where[] = 'MONTH(ip.payment_date) = ?';
    $params[] = $filterMonth;
}

if (!empty($search)) {
    $where[] = '(i.invoice_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR ip.payment_reference LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $where);

// Total de pagos
$totalPayments = (int)db()->selectValue("
    SELECT COUNT(*) 
    FROM invoice_payments ip
    INNER JOIN invoices i ON ip.invoice_id = i.id
    INNER JOIN clients c ON i.client_id = c.id
    WHERE {$whereClause}
", $params);

$totalPages = ceil($totalPayments / $perPage);

// Obtener pagos
$payments = db()->select("
    SELECT ip.*,
           i.invoice_number,
           i.invoice_type,
           i.total_amount as invoice_total,
           CONCAT(c.first_name, ' ', c.last_name) as client_name,
           c.reference as client_reference,
           p.reference as property_reference,
           st.transaction_code,
           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM invoice_payments ip
    INNER JOIN invoices i ON ip.invoice_id = i.id
    INNER JOIN clients c ON i.client_id = c.id
    INNER JOIN properties p ON i.property_id = p.id
    INNER JOIN sales_transactions st ON i.transaction_id = st.id
    LEFT JOIN users u ON ip.created_by = u.id
    WHERE {$whereClause}
    ORDER BY ip.payment_date DESC, ip.id DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Estadísticas
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total_payments,
        SUM(ip.payment_amount) as total_amount,
        SUM(CASE WHEN i.invoice_type = 'rent' THEN ip.payment_amount ELSE 0 END) as rent_payments,
        SUM(CASE WHEN i.invoice_type = 'sale' THEN ip.payment_amount ELSE 0 END) as sale_payments,
        COUNT(CASE WHEN ip.payment_method = 'cash' THEN 1 END) as cash_count,
        COUNT(CASE WHEN ip.payment_method = 'bank_transfer' THEN 1 END) as transfer_count,
        COUNT(CASE WHEN ip.payment_method = 'check' THEN 1 END) as check_count
    FROM invoice_payments ip
    INNER JOIN invoices i ON ip.invoice_id = i.id
    WHERE YEAR(ip.payment_date) = ?
", [$filterYear]);

// Años disponibles
$years = db()->select("SELECT DISTINCT YEAR(payment_date) as year FROM invoice_payments ORDER BY year DESC");

// Meses para el filtro
$months = [
    1 => __('months.january', [], 'January'),
    2 => __('months.february', [], 'February'),
    3 => __('months.march', [], 'March'),
    4 => __('months.april', [], 'April'),
    5 => __('months.may', [], 'May'),
    6 => __('months.june', [], 'June'),
    7 => __('months.july', [], 'July'),
    8 => __('months.august', [], 'August'),
    9 => __('months.september', [], 'September'),
    10 => __('months.october', [], 'October'),
    11 => __('months.november', [], 'November'),
    12 => __('months.december', [], 'December')
];

include 'header.php';
include 'sidebar.php';
?>

<style>
:root {
    --primary: #667eea;
    --success: #10b981;
    --info: #3b82f6;
}

.payments-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

.page-header-modern {
    background: white;
    padding: 25px 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
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
    color: var(--success);
}

.page-subtitle-modern {
    color: #718096;
    margin: 0;
    font-size: 14px;
}

/* Stats */
.stats-grid-payments {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-payment {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 4px solid;
}

.stat-card-payment.total { border-color: var(--success); }
.stat-card-payment.rent { border-color: #8b5cf6; }
.stat-card-payment.sale { border-color: var(--info); }

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
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.stat-card-payment.total .stat-icon {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.stat-card-payment.rent .stat-icon {
    background: rgba(139, 92, 246, 0.1);
    color: #8b5cf6;
}

.stat-card-payment.sale .stat-icon {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
}

.stat-count {
    font-size: 12px;
    color: #a0aec0;
    margin-top: 5px;
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

/* Payments Table */
.payments-table-container {
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

.payments-table {
    width: 100%;
    border-collapse: collapse;
}

.payments-table thead {
    background: #f8f9fa;
}

.payments-table th {
    padding: 15px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
    text-transform: uppercase;
    border-bottom: 2px solid #e5e7eb;
}

.payments-table td {
    padding: 15px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 14px;
    color: #4a5568;
}

.payments-table tbody tr:hover {
    background: #f8f9fa;
}

/* Payment Badge */
.payment-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.payment-badge.rent {
    background: rgba(139, 92, 246, 0.1);
    color: #7c3aed;
}

.payment-badge.sale {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

/* Method Badge */
.method-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
}

/* Amount */
.payment-amount {
    font-size: 16px;
    font-weight: 700;
    color: var(--success);
}

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
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
    color: white;
}

.btn-outline {
    background: white;
    border: 2px solid #e5e7eb;
    color: #4a5568;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
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

.empty-state-text {
    color: #718096;
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
@media (max-width: 768px) {
    .payments-container {
        padding: 15px;
    }
    
    .stats-grid-payments,
    .filters-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="payments-container">
    <!-- Page Header -->
    <div class="page-header-modern">
        <div>
            <h1 class="page-title-modern">
                <i class="fas fa-receipt"></i>
                <?php echo __('payments.title', [], 'Payments'); ?>
            </h1>
            <p class="page-subtitle-modern">
                <?php echo __('payments.subtitle', [], 'View all payments made in the system'); ?>
            </p>
        </div>
        <div>
            <button class="btn-modern btn-primary" onclick="exportPayments()">
                <i class="fas fa-download"></i>
                <?php echo __('payments.export', [], 'Export'); ?>
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid-payments">
        <div class="stat-card-payment total">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('payments.stats.total', [], 'Total Collected'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['total_amount'], 2); ?></div>
            <div class="stat-count"><?php echo $stats['total_payments']; ?> <?php echo __('payments.payments', [], 'payments'); ?></div>
        </div>

        <div class="stat-card-payment rent">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('payments.stats.rentals', [], 'Rental Payments'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-home"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['rent_payments'], 2); ?></div>
        </div>

        <div class="stat-card-payment sale">
            <div class="stat-header">
                <span class="stat-label"><?php echo __('payments.stats.sales', [], 'Sale Payments'); ?></span>
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['sale_payments'], 2); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="pagos.php">
            <div class="filters-grid">
                <div class="filter-group">
                    <label><?php echo __('payments.filter.type', [], 'Transaction Type'); ?></label>
                    <select name="type" class="form-select">
                        <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <option value="rent" <?php echo $filterType === 'rent' ? 'selected' : ''; ?>><?php echo __('payments.type.rent', [], 'Rental'); ?></option>
                        <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>><?php echo __('payments.type.sale', [], 'Sale'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('payments.filter.method', [], 'Payment Method'); ?></label>
                    <select name="method" class="form-select">
                        <option value="all" <?php echo $filterMethod === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <option value="cash" <?php echo $filterMethod === 'cash' ? 'selected' : ''; ?>><?php echo __('payment_method.cash', [], 'Cash'); ?></option>
                        <option value="bank_transfer" <?php echo $filterMethod === 'bank_transfer' ? 'selected' : ''; ?>><?php echo __('payment_method.bank_transfer', [], 'Bank Transfer'); ?></option>
                        <option value="check" <?php echo $filterMethod === 'check' ? 'selected' : ''; ?>><?php echo __('payment_method.check', [], 'Check'); ?></option>
                        <option value="credit_card" <?php echo $filterMethod === 'credit_card' ? 'selected' : ''; ?>><?php echo __('payment_method.credit_card', [], 'Credit Card'); ?></option>
                        <option value="debit_card" <?php echo $filterMethod === 'debit_card' ? 'selected' : ''; ?>><?php echo __('payment_method.debit_card', [], 'Debit Card'); ?></option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('payments.filter.year', [], 'Year'); ?></label>
                    <select name="year" class="form-select">
                        <option value="all" <?php echo $filterYear === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $filterYear == $year['year'] ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('payments.filter.month', [], 'Month'); ?></label>
                    <select name="month" class="form-select">
                        <option value="all" <?php echo $filterMonth === 'all' ? 'selected' : ''; ?>><?php echo __('all', [], 'All'); ?></option>
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $filterMonth == $num ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label><?php echo __('search', [], 'Search'); ?></label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="<?php echo __('payments.search_placeholder', [], 'Invoice, client, reference...'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 15px;">
                <button type="submit" class="btn-modern btn-primary">
                    <i class="fas fa-search"></i>
                    <?php echo __('payments.filter.apply_filters', [], 'Apply Filters'); ?>
                </button>
                <a href="pagos.php" class="btn-modern btn-outline">
                    <i class="fas fa-redo"></i>
                    <?php echo __('payments.filter.clear_filters', [], 'Clear'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Payments Table -->
    <div class="payments-table-container">
        <div class="table-header">
            <div class="table-title">
                <?php echo __('payments.payment_list', [], 'Payment List'); ?>
                <span style="color: #a0aec0; font-weight: normal; font-size: 14px;">
                    (<?php echo $totalPayments; ?> <?php echo __('records', [], 'records'); ?>)
                </span>
            </div>
        </div>

        <?php if (empty($payments)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="empty-state-title">
                <?php echo __('payments.no_payments', [], 'No payments found'); ?>
            </div>
            <div class="empty-state-text">
                <?php echo __('payments.no_payments_text', [], 'No payments were found with the applied filters'); ?>
            </div>
        </div>
        <?php else: ?>
        <table class="payments-table">
            <thead>
                <tr>
                    <th><?php echo __('payments.table.date', [], 'Date'); ?></th>
                    <th><?php echo __('payments.table.invoice', [], 'Invoice'); ?></th>
                    <th><?php echo __('payments.table.client', [], 'Client'); ?></th>
                    <th><?php echo __('payments.table.property', [], 'Property'); ?></th>
                    <th><?php echo __('payments.table.type', [], 'Type'); ?></th>
                    <th><?php echo __('payments.table.method', [], 'Method'); ?></th>
                    <th><?php echo __('payments.table.reference', [], 'Reference'); ?></th>
                    <th class="text-end"><?php echo __('payments.table.amount', [], 'Amount'); ?></th>
                    <th><?php echo __('payments.table.registered_by', [], 'Registered By'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                    <td>
                        <a href="ver-factura.php?id=<?php echo $payment['invoice_id']; ?>" class="transaction-code">
                            <?php echo htmlspecialchars($payment['invoice_number']); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                    <td><?php echo htmlspecialchars($payment['property_reference']); ?></td>
                    <td>
                        <span class="payment-badge <?php echo $payment['invoice_type']; ?>">
                            <?php echo __('payments.type.' . $payment['invoice_type'], [], ucfirst($payment['invoice_type'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="method-badge">
                            <?php echo __('payment_method.' . $payment['payment_method'], [], ucwords(str_replace('_', ' ', $payment['payment_method']))); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($payment['payment_reference'] ?: '-'); ?></td>
                    <td class="text-end">
                        <span class="payment-amount">$<?php echo number_format($payment['payment_amount'], 2); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($payment['created_by_name'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                <?php echo __('showing', [], 'Showing'); ?> <?php echo (($page - 1) * $perPage) + 1; ?> - <?php echo min($page * $perPage, $totalPayments); ?> 
                <?php echo __('of', [], 'of'); ?> <?php echo $totalPayments; ?> <?php echo __('payments.payments', [], 'payments'); ?>
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

<script>
function exportPayments() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = 'ajax/export-payments.php?' + params.toString();
}
</script>

<?php include 'footer.php'; ?>
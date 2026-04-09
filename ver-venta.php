<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$currentUser = getCurrentUser();
$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verificar que se proporcionó un ID válido
if (!$transactionId) {
    header('Location: ventas.php');
    exit;
}

// Obtener datos completos de la transacción
$transaction = db()->selectOne("
    SELECT st.*,
           -- Datos de la propiedad
           p.reference as property_reference,
           p.title as property_title,
           p.address as property_address,
           p.city as property_city,
           p.zone as property_zone,
           p.bedrooms as property_bedrooms,
           p.bathrooms as property_bathrooms,
           COALESCE(p.built_area, p.useful_area) as property_size,
           pt.name as property_type_name,
           (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as property_image,
           
           -- Datos del cliente
           CONCAT(c.first_name, ' ', c.last_name) as client_name,
           c.reference as client_reference,
           c.email as client_email,
           c.phone_mobile as client_phone,
           c.document_id as client_document,
           
           -- Datos del agente
           CONCAT(u.first_name, ' ', u.last_name) as agent_name,
           u.email as agent_email,
           u.phone as agent_phone,
           u.profile_picture as agent_picture,
           
           -- Datos del segundo agente (si existe)
           CONCAT(u2.first_name, ' ', u2.last_name) as second_agent_name,
           
           -- Datos de oficina (si existe)
           o.name as office_name,
           o.address as office_address
           
    FROM sales_transactions st
    INNER JOIN properties p ON st.property_id = p.id
    LEFT JOIN property_types pt ON p.property_type_id = pt.id
    INNER JOIN clients c ON st.client_id = c.id
    INNER JOIN users u ON st.agent_id = u.id
    LEFT JOIN users u2 ON st.second_agent_id = u2.id
    LEFT JOIN offices o ON st.office_id = o.id
    WHERE st.id = ?
", [$transactionId]);

// Verificar que existe la transacción
if (!$transaction) {
    $_SESSION['flash_message'] = __('sales.view.not_found');
    $_SESSION['flash_type'] = 'error';
    header('Location: ventas.php');
    exit;
}

// ============================================
// OBTENER TODOS LOS PAGOS (sale_payments + invoice_payments)
// ============================================

// Pagos directos de la transacción (sale_payments)
$directPayments = db()->select("
    SELECT sp.*,
           'direct' as payment_source,
           NULL as invoice_id,
           NULL as invoice_number,
           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM sale_payments sp
    LEFT JOIN users u ON sp.created_by = u.id
    WHERE sp.transaction_id = ?
    ORDER BY sp.payment_date DESC
", [$transactionId]);

// Pagos a través de facturas (invoice_payments)
$invoicePayments = db()->select("
    SELECT ip.id,
           ip.payment_date,
           ip.payment_amount,
           ip.payment_method,
           ip.payment_reference,
           ip.notes,
           ip.created_at,
           'invoice' as payment_source,
           ip.invoice_id,
           i.invoice_number,
           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM invoice_payments ip
    INNER JOIN invoices i ON ip.invoice_id = i.id
    LEFT JOIN users u ON ip.created_by = u.id
    WHERE i.transaction_id = ?
    ORDER BY ip.payment_date DESC
", [$transactionId]);

// Combinar todos los pagos
$payments = array_merge($directPayments, $invoicePayments);

// Ordenar por fecha descendente
usort($payments, function($a, $b) {
    return strtotime($b['payment_date']) - strtotime($a['payment_date']);
});

// Obtener timeline de eventos
$timeline = db()->select("
    SELECT st.*,
           CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM sale_timeline st
    LEFT JOIN users u ON st.user_id = u.id
    WHERE st.transaction_id = ?
    ORDER BY st.created_at DESC
", [$transactionId]);

// Calcular totales de pagos
$totalPaid = 0;
foreach ($payments as $payment) {
    $totalPaid += $payment['payment_amount'];
}

$pageTitle = __('sales.view.title') . ' - ' . $transaction['transaction_code'];

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

.transaction-view-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* Header */
.transaction-header {
    background: white;
    padding: 25px 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.header-info h1 {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 5px 0;
}

.transaction-code {
    font-size: 18px;
    color: var(--primary);
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn-action {
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
    background: linear-gradient(135deg, var(--primary), #764ba2);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #e5e7eb;
    color: #4a5568;
}

.btn-secondary:hover {
    background: #d1d5db;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #059669;
}

/* Status Badges */
.status-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.badge-in-progress {
    background: #dbeafe;
    color: #1e40af;
}

.badge-completed {
    background: #d1fae5;
    color: #065f46;
}

.badge-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.badge-sale {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}

.badge-rent {
    background: linear-gradient(135deg, #e9d5ff, #d8b4fe);
    color: #6b21a8;
}

/* Grid Layout */
.content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}

/* Cards */
.info-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header i {
    font-size: 24px;
    color: var(--primary);
}

.card-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
}

/* Property Card with Image */
.property-showcase {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.property-image-large {
    width: 200px;
    height: 150px;
    border-radius: 12px;
    object-fit: cover;
    flex-shrink: 0;
}

.property-details {
    flex: 1;
}

.property-title {
    font-size: 20px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 8px;
}

.property-reference {
    font-size: 14px;
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 10px;
}

.property-meta {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.property-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #4a5568;
}

.property-meta-item i {
    color: var(--primary);
}

/* Info Rows */
.info-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 14px;
}

.info-value {
    color: #2d3748;
    font-weight: 600;
    font-size: 14px;
    text-align: right;
}

.info-value.highlight {
    color: var(--success);
    font-size: 18px;
}

/* Contact Card */
.contact-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
    margin-bottom: 15px;
}

.contact-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    flex-shrink: 0;
}

.contact-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 5px 0;
}

.contact-detail {
    font-size: 13px;
    color: #6b7280;
    margin: 3px 0;
}

/* Financial Summary */
.financial-summary {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 2px solid #86efac;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.summary-item:last-child {
    margin-bottom: 0;
    padding-top: 12px;
    border-top: 2px solid #86efac;
}

.summary-label {
    font-size: 14px;
    color: #065f46;
    font-weight: 600;
}

.summary-value {
    font-size: 16px;
    font-weight: 700;
    color: #065f46;
}

.summary-total {
    font-size: 20px !important;
}

/* Payments Table */
.payments-table {
    width: 100%;
    border-collapse: collapse;
}

.payments-table thead {
    background: #f8f9fa;
}

.payments-table th {
    padding: 12px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #4a5568;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.payments-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
    color: #2d3748;
}

.payments-table tbody tr:hover {
    background: #f8f9fa;
}

.payments-table tfoot {
    background: #f8f9fa;
    font-weight: 700;
}

.payments-table tfoot td {
    padding: 15px 12px;
    border-top: 2px solid #e5e7eb;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #9ca3af;
}

/* Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
    padding-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--primary);
    border: 2px solid white;
    box-shadow: 0 0 0 2px var(--primary);
}

.timeline-date {
    font-size: 12px;
    color: #9ca3af;
    margin-bottom: 5px;
}

.timeline-title {
    font-size: 15px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 3px;
}

.timeline-description {
    font-size: 13px;
    color: #6b7280;
}

.timeline-user {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 5px;
}

/* Responsive */
@media (max-width: 1200px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .transaction-view-container {
        padding: 15px;
    }
    
    .header-top {
        flex-direction: column;
        gap: 15px;
    }
    
    .property-showcase {
        flex-direction: column;
    }
    
    .property-image-large {
        width: 100%;
        height: 200px;
    }
    
    .property-meta {
        flex-wrap: wrap;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .btn-action {
        flex: 1;
        justify-content: center;
    }
}

/* Print Styles */
@media print {
    .sidebar, .header-actions, .btn-action {
        display: none !important;
    }
    
    .transaction-view-container {
        padding: 0;
    }
    
    .info-card {
        break-inside: avoid;
    }
}
</style>

<div class="transaction-view-container">
    <!-- Header -->
    <div class="transaction-header">
        <div class="header-top">
            <div class="header-info">
                <h1><?php echo __('sales.view.title'); ?></h1>
                <div class="transaction-code">
                    <i class="fas fa-barcode"></i>
                    <?php echo htmlspecialchars($transaction['transaction_code']); ?>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="ventas.php" class="btn-action btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo __('sales.view.back'); ?>
                </a>
                <button onclick="window.print()" class="btn-action btn-secondary">
                    <i class="fas fa-print"></i>
                    <?php echo __('sales.view.print'); ?>
                </button>
                <a href="editar-venta.php?id=<?php echo $transactionId; ?>" class="btn-action btn-primary">
                    <i class="fas fa-edit"></i>
                    <?php echo __('sales.view.edit'); ?>
                </a>
            </div>
        </div>
        
        <div class="status-badges">
            <?php
            // Badge de tipo de transacción
            $typeClass = $transaction['transaction_type'] === 'sale' ? 'badge-sale' : 'badge-rent';
            $typeIcon = $transaction['transaction_type'] === 'sale' ? 'fa-hand-holding-usd' : 'fa-home';
            $typeText = __('sales.view.type_display.' . $transaction['transaction_type']);
            ?>
            <span class="badge <?php echo $typeClass; ?>">
                <i class="fas <?php echo $typeIcon; ?>"></i>
                <?php echo $typeText; ?>
            </span>
            
            <?php
            // Badge de estado
            $statusBadges = [
                'pending' => ['class' => 'badge-pending', 'icon' => 'fa-clock'],
                'in_progress' => ['class' => 'badge-in-progress', 'icon' => 'fa-spinner'],
                'completed' => ['class' => 'badge-completed', 'icon' => 'fa-check-circle'],
                'cancelled' => ['class' => 'badge-cancelled', 'icon' => 'fa-times-circle']
            ];
            
            $statusInfo = $statusBadges[$transaction['status']] ?? $statusBadges['pending'];
            $statusText = __('sales.view.status_display.' . $transaction['status']);
            ?>
            <span class="badge <?php echo $statusInfo['class']; ?>">
                <i class="fas <?php echo $statusInfo['icon']; ?>"></i>
                <?php echo $statusText; ?>
            </span>
            
            <span class="badge badge-in-progress">
                <i class="fas fa-money-bill-wave"></i>
                <?php echo __('sales.view.payment_display.' . $transaction['payment_status']); ?>
            </span>
            
            <span class="badge badge-pending">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?>
            </span>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- Left Column -->
        <div>
            <!-- Property Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-home"></i>
                    <h3><?php echo __('sales.view.sections.property_info'); ?></h3>
                </div>
                
                <div class="property-showcase">
                    <img src="<?php echo $transaction['property_image'] ?? 'assets/img/no-image.jpg'; ?>" 
                         alt="<?php echo htmlspecialchars($transaction['property_title']); ?>" 
                         class="property-image-large">
                    
                    <div class="property-details">
                        <div class="property-title">
                            <?php echo htmlspecialchars($transaction['property_title']); ?>
                        </div>
                        <div class="property-reference">
                            <?php echo __('sales.view.contact.reference'); ?>: <?php echo htmlspecialchars($transaction['property_reference']); ?>
                        </div>
                        <div style="font-size: 14px; color: #6b7280; margin-bottom: 10px;">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($transaction['property_address'] . ', ' . $transaction['property_city']); ?>
                        </div>
                        
                        <div class="property-meta">
                            <div class="property-meta-item">
                                <i class="fas fa-th-large"></i>
                                <span><?php echo $transaction['property_type_name']; ?></span>
                            </div>
                            <div class="property-meta-item">
                                <i class="fas fa-bed"></i>
                                <span><?php echo $transaction['property_bedrooms']; ?> <?php echo __('sales.view.property.bedrooms'); ?></span>
                            </div>
                            <div class="property-meta-item">
                                <i class="fas fa-bath"></i>
                                <span><?php echo $transaction['property_bathrooms']; ?> <?php echo __('sales.view.property.bathrooms'); ?></span>
                            </div>
                            <div class="property-meta-item">
                                <i class="fas fa-ruler-combined"></i>
                                <span><?php echo number_format($transaction['property_size'], 0); ?> m²</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <a href="ver-propiedad.php?id=<?php echo $transaction['property_id']; ?>" class="btn-action btn-secondary" style="width: 100%; justify-content: center;">
                    <i class="fas fa-external-link-alt"></i>
                    <?php echo __('sales.view.property.view_details'); ?>
                </a>
            </div>

            <!-- Financial Details -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-dollar-sign"></i>
                    <h3><?php echo __('sales.view.sections.financial_details'); ?></h3>
                </div>
                
                <div class="financial-summary">
                    <?php if ($transaction['transaction_type'] === 'sale'): ?>
                        <div class="summary-item">
                            <span class="summary-label"><?php echo __('sales.view.financial.original_price'); ?>:</span>
                            <span class="summary-value">$<?php echo number_format($transaction['original_price'] ?? $transaction['sale_price'], 2); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label"><?php echo __('sales.view.financial.sale_price'); ?>:</span>
                            <span class="summary-value">$<?php echo number_format($transaction['sale_price'], 2); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="summary-item">
                            <span class="summary-label"><?php echo __('sales.view.financial.monthly_payment'); ?>:</span>
                            <span class="summary-value">$<?php echo number_format($transaction['monthly_payment'], 2); ?>/<?php echo __('sales.view.financial.month'); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label"><?php echo __('sales.view.financial.duration'); ?>:</span>
                            <span class="summary-value"><?php echo $transaction['rent_duration_months']; ?> <?php echo __('sales.view.financial.months'); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label"><?php echo __('sales.view.financial.total_contract'); ?>:</span>
                            <span class="summary-value">$<?php echo number_format($transaction['sale_price'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-item">
                        <span class="summary-label"><?php echo __('sales.view.financial.commission'); ?> (<?php echo $transaction['commission_percentage']; ?>%):</span>
                        <span class="summary-value">$<?php echo number_format($transaction['commission_amount'], 2); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label summary-total"><?php echo __('sales.view.financial.total_paid'); ?>:</span>
                        <span class="summary-value summary-total">$<?php echo number_format($totalPaid, 2); ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label summary-total"><?php echo __('sales.view.financial.balance_pending'); ?>:</span>
                        <span class="summary-value summary-total">$<?php echo number_format($transaction['balance_pending'], 2); ?></span>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <div class="info-row">
                        <span class="info-label"><?php echo __('sales.view.financial.contract_date'); ?>:</span>
                        <span class="info-value">
                            <?php echo $transaction['contract_date'] ? date('d/m/Y', strtotime($transaction['contract_date'])) : 'N/A'; ?>
                        </span>
                    </div>
                    
                    <?php if ($transaction['transaction_type'] === 'sale'): ?>
                        <?php if ($transaction['closing_date']): ?>
                        <div class="info-row">
                            <span class="info-label"><?php echo __('sales.view.financial.closing_date'); ?>:</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($transaction['closing_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($transaction['financing_type']): ?>
                        <div class="info-row">
                            <span class="info-label"><?php echo __('sales.view.financial.financing_type'); ?>:</span>
                            <span class="info-value">
                                <?php echo __('sales.view.financing.' . $transaction['financing_type']); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($transaction['bank_name']): ?>
                        <div class="info-row">
                            <span class="info-label"><?php echo __('sales.view.financial.bank_name'); ?>:</span>
                            <span class="info-value"><?php echo htmlspecialchars($transaction['bank_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($transaction['loan_amount']): ?>
                        <div class="info-row">
                            <span class="info-label"><?php echo __('sales.view.financial.loan_amount'); ?>:</span>
                            <span class="info-value">$<?php echo number_format($transaction['loan_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($transaction['down_payment']): ?>
                        <div class="info-row">
                            <span class="info-label"><?php echo __('sales.view.financial.down_payment'); ?>:</span>
                            <span class="info-value">$<?php echo number_format($transaction['down_payment'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($transaction['payment_method']): ?>
                    <div class="info-row">
                        <span class="info-label"><?php echo __('sales.view.financial.payment_method'); ?>:</span>
                        <span class="info-value"><?php echo htmlspecialchars($transaction['payment_method']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payments -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-receipt"></i>
                    <h3><?php echo __('sales.view.sections.payments'); ?></h3>
                </div>
                
                <?php if (!empty($payments)): ?>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th><?php echo __('sales.view.payments_table.date'); ?></th>
                                <th><?php echo __('sales.view.payments_table.amount'); ?></th>
                                <th><?php echo __('sales.view.payments_table.method'); ?></th>
                                <th><?php echo __('sales.view.payments_table.reference'); ?></th>
                                <th><?php echo __('sales.view.payments_table.source'); ?></th>
                                <th><?php echo __('sales.view.payments_table.registered_by'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                <td style="font-weight: 700; color: var(--success);">
                                    $<?php echo number_format($payment['payment_amount'], 2); ?>
                                </td>
                                <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_reference'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($payment['payment_source'] === 'invoice'): ?>
                                        <span style="background: #dbeafe; color: #1e40af; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                            <?php echo __('sales.view.payments_table.invoice_payment'); ?>
                                        </span>
                                        <div style="font-size: 11px; color: #6b7280; margin-top: 3px;">
                                            <?php echo htmlspecialchars($payment['invoice_number']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                            <?php echo __('sales.view.payments_table.direct_payment'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <?php echo htmlspecialchars($payment['created_by_name'] ?? 'Sistema'); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: 700;">
                                <td><?php echo __('sales.view.payments_table.total_paid_label'); ?></td>
                                <td style="color: var(--success); font-size: 18px;">
                                    $<?php echo number_format($totalPaid, 2); ?>
                                </td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; color: #cbd5e0;"></i>
                        <div style="font-size: 18px; font-weight: 600; color: #4a5568; margin-bottom: 8px;">
                            <?php echo __('sales.view.payments_table.no_payments'); ?>
                        </div>
                        <div style="font-size: 14px; color: #9ca3af;">
                            <?php echo __('sales.view.payments_table.no_payments_yet'); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <a href="facturacion.php?client_id=<?php echo $transaction['client_id']; ?>&transaction_id=<?php echo $transactionId; ?>" 
                   class="btn-action btn-success" 
                   style="width: 100%; justify-content: center; margin-top: 15px; text-decoration: none;">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <?php echo __('sales.view.payments_table.view_invoices'); ?>
                </a>
            </div>

            <!-- Timeline -->
            <?php if (!empty($timeline)): ?>
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h3><?php echo __('sales.view.sections.timeline'); ?></h3>
                </div>
                
                <div class="timeline">
                    <?php foreach ($timeline as $event): ?>
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?>
                        </div>
                        <div class="timeline-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        <?php if ($event['event_description']): ?>
                        <div class="timeline-description">
                            <?php echo htmlspecialchars($event['event_description']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($event['user_name']): ?>
                        <div class="timeline-user">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($event['user_name']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Additional Notes -->
            <?php if ($transaction['notes']): ?>
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-sticky-note"></i>
                    <h3><?php echo __('sales.view.sections.additional_notes'); ?></h3>
                </div>
                
                <div style="color: #4a5568; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($transaction['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Client Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-user"></i>
                    <h3><?php echo __('sales.view.sections.client_info'); ?></h3>
                </div>
                
                <div class="contact-card">
                    <div class="contact-avatar">
                        <?php echo strtoupper(substr($transaction['client_name'], 0, 2)); ?>
                    </div>
                    <div class="contact-info">
                        <h4><?php echo htmlspecialchars($transaction['client_name']); ?></h4>
                        <div class="contact-detail">
                            <i class="fas fa-tag"></i>
                            <?php echo __('sales.view.contact.reference'); ?>: <?php echo htmlspecialchars($transaction['client_reference']); ?>
                        </div>
                        <div class="contact-detail">
                            <i class="fas fa-phone"></i>
                            <?php echo __('sales.view.contact.phone'); ?>: <?php echo htmlspecialchars($transaction['client_phone'] ?? 'N/A'); ?>
                        </div>
                        <div class="contact-detail">
                            <i class="fas fa-envelope"></i>
                            <?php echo __('sales.view.contact.email'); ?>: <?php echo htmlspecialchars($transaction['client_email']); ?>
                        </div>
                        <?php if ($transaction['client_document']): ?>
                        <div class="contact-detail">
                            <i class="fas fa-id-card"></i>
                            <?php echo __('sales.view.contact.document'); ?>: <?php echo htmlspecialchars($transaction['client_document']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <a href="ver-cliente.php?id=<?php echo $transaction['client_id']; ?>" 
                   class="btn-action btn-secondary" 
                   style="width: 100%; justify-content: center; text-decoration: none;">
                    <i class="fas fa-external-link-alt"></i>
                    <?php echo __('view'); ?> <?php echo __('sales.client'); ?>
                </a>
            </div>

            <!-- Agent Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-user-tie"></i>
                    <h3><?php echo __('sales.view.sections.agent_info'); ?></h3>
                </div>
                
                <div class="contact-card">
                    <div class="contact-avatar">
                        <?php if ($transaction['agent_picture']): ?>
                            <img src="<?php echo $transaction['agent_picture']; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php echo strtoupper(substr($transaction['agent_name'], 0, 2)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="contact-info">
                        <h4><?php echo htmlspecialchars($transaction['agent_name']); ?></h4>
                        <div class="contact-detail">
                            <i class="fas fa-phone"></i>
                            <?php echo __('sales.view.contact.phone'); ?>: <?php echo htmlspecialchars($transaction['agent_phone'] ?? 'N/A'); ?>
                        </div>
                        <div class="contact-detail">
                            <i class="fas fa-envelope"></i>
                            <?php echo __('sales.view.contact.email'); ?>: <?php echo htmlspecialchars($transaction['agent_email']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3><?php echo __('sales.view.sections.additional_info'); ?></h3>
                </div>
                
                <?php if ($transaction['office_name']): ?>
                <div class="info-row">
                    <span class="info-label"><?php echo __('sales.view.additional.office'); ?>:</span>
                    <span class="info-value"><?php echo htmlspecialchars($transaction['office_name']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($transaction['second_agent_name']): ?>
                <div class="info-row">
                    <span class="info-label"><?php echo __('sales.view.additional.second_agent'); ?>:</span>
                    <span class="info-value"><?php echo htmlspecialchars($transaction['second_agent_name']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label"><?php echo __('sales.view.additional.created_date'); ?>:</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></span>
                </div>
                
                <?php if ($transaction['updated_at']): ?>
                <div class="info-row">
                    <span class="info-label"><?php echo __('sales.view.additional.last_update'); ?>:</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($transaction['updated_at'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Funcionalidades adicionales pueden agregarse aquí
console.log('Ver venta cargado correctamente');
</script>

<?php include 'footer.php'; ?>
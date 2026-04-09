<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoiceId === 0) {
    $_SESSION['flash_message'] = __('invoice.invalid_id', [], 'Invalid invoice ID');
    $_SESSION['flash_type'] = 'danger';
    header('Location: facturacion.php');
    exit;
}

// Obtener factura con todos los datos
$invoice = db()->selectOne("
    SELECT i.*,
           CONCAT(c.first_name, ' ', c.last_name) as client_name,
           c.reference as client_reference,
           c.email as client_email,
           c.phone_mobile as client_phone,
           c.address as client_address,
           p.reference as property_reference,
           p.title as property_title,
           p.address as property_address,
           st.transaction_code,
           st.transaction_type,
           CONCAT(u.first_name, ' ', u.last_name) as agent_name,
           u.email as agent_email
    FROM invoices i
    INNER JOIN clients c ON i.client_id = c.id
    INNER JOIN properties p ON i.property_id = p.id
    INNER JOIN sales_transactions st ON i.transaction_id = st.id
    LEFT JOIN users u ON i.agent_id = u.id
    WHERE i.id = ?
", [$invoiceId]);

if (!$invoice) {
    $_SESSION['flash_message'] = __('invoice.not_found', [], 'Invoice not found');
    $_SESSION['flash_type'] = 'danger';
    header('Location: facturacion.php');
    exit;
}

// Obtener pagos de esta factura
$payments = db()->select("
    SELECT ip.*,
           CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM invoice_payments ip
    LEFT JOIN users u ON ip.created_by = u.id
    WHERE ip.invoice_id = ?
    ORDER BY ip.payment_date DESC, ip.id DESC
", [$invoiceId]);

$pageTitle = __('invoice.view_title', [], 'Invoice') . ' - ' . $invoice['invoice_number'];
$currentUser = getCurrentUser();

include 'header.php';
include 'sidebar.php';
?>

<style>
:root {
    --primary: #667eea;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
}

.invoice-view-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* Action Bar */
.invoice-action-bar {
    background: white;
    padding: 20px 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

/* Invoice Card */
.invoice-document {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

/* Invoice Header */
.invoice-header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px 50px;
}

.invoice-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.company-details h1 {
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.company-details p {
    margin: 4px 0;
    opacity: 0.95;
    font-size: 15px;
}

.invoice-meta-section {
    text-align: right;
}

.invoice-number-display {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 15px;
    letter-spacing: -0.5px;
}

.invoice-status-badge {
    display: inline-block;
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

/* Invoice Body */
.invoice-body-section {
    padding: 50px;
}

/* Billing Section */
.billing-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 50px;
}

.billing-box {
    padding: 25px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}

.billing-box h3 {
    font-size: 13px;
    text-transform: uppercase;
    color: #64748b;
    margin: 0 0 15px 0;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.billing-name {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
}

.billing-detail {
    color: #475569;
    margin: 8px 0;
    font-size: 14px;
    line-height: 1.6;
}

.billing-detail strong {
    color: #334155;
    font-weight: 600;
}

/* Invoice Info Grid */
.invoice-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 25px;
    margin-bottom: 50px;
    padding: 30px;
    background: #f8fafc;
    border-radius: 12px;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 12px;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 8px;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

/* Items Table */
.items-table-wrapper {
    margin-bottom: 40px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}

.invoice-items-table {
    width: 100%;
    border-collapse: collapse;
}

.invoice-items-table thead {
    background: #f1f5f9;
}

.invoice-items-table th {
    padding: 18px 20px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #cbd5e1;
}

.invoice-items-table td {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    font-size: 15px;
}

.invoice-items-table tbody tr:last-child td {
    border-bottom: none;
}

.item-description {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 6px;
}

.item-details {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
}

/* Totals Section */
.totals-section {
    max-width: 450px;
    margin-left: auto;
    padding: 25px;
    background: #f8fafc;
    border-radius: 12px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    font-size: 15px;
    color: #475569;
}

.total-line-value {
    font-weight: 600;
    color: #0f172a;
}

.total-line.discount {
    color: var(--success);
}

.total-line.discount .total-line-value {
    color: var(--success);
}

.total-line.late-fee {
    color: var(--danger);
}

.total-line.late-fee .total-line-value {
    color: var(--danger);
}

.total-line.grand-total {
    border-top: 2px solid #cbd5e1;
    padding-top: 20px;
    margin-top: 15px;
    font-size: 22px;
    font-weight: 700;
    color: var(--primary);
}

.total-line.paid-amount {
    color: var(--success);
    font-weight: 600;
}

.total-line.balance-due {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    padding: 18px;
    border-radius: 10px;
    margin-top: 15px;
    font-size: 20px;
    font-weight: 700;
    color: #92400e;
}

/* Payments History */
.payments-history-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 40px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}

.section-header i {
    font-size: 24px;
    color: var(--success);
}

.section-title {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.payments-history-table {
    width: 100%;
    border-collapse: collapse;
}

.payments-history-table thead {
    background: #f8fafc;
}

.payments-history-table th {
    padding: 15px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
}

.payments-history-table td {
    padding: 18px 15px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}

.payments-history-table tbody tr:hover {
    background: #f8fafc;
}

.payment-amount-display {
    font-size: 17px;
    font-weight: 700;
    color: var(--success);
}

.payment-method-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: #dbeafe;
    color: #1e40af;
}

.no-payments-message {
    text-align: center;
    padding: 40px;
    color: #64748b;
    font-size: 15px;
}

/* Buttons */
.btn-modern {
    padding: 12px 24px;
    border-radius: 10px;
    border: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-back {
    background: white;
    border: 2px solid #e2e8f0;
    color: #475569;
}

.btn-back:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateX(-3px);
}

.btn-success {
    background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
}

/* Print Styles */
@media print {
    .invoice-action-bar,
    .payments-history-section,
    .sidebar,
    header,
    .navbar {
        display: none !important;
    }
    
    .invoice-view-container {
        padding: 0;
        background: white;
    }
    
    .invoice-document {
        box-shadow: none;
        border-radius: 0;
    }
    
    .invoice-body-section {
        padding: 30px;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .invoice-view-container {
        padding: 15px;
    }
    
    .invoice-header-content {
        flex-direction: column;
        gap: 25px;
    }
    
    .invoice-meta-section {
        text-align: left;
    }
    
    .invoice-body-section {
        padding: 25px;
    }
    
    .billing-section {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .invoice-info-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
    
    .invoice-action-bar {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<div class="invoice-view-container">
    <!-- Action Bar -->
    <div class="invoice-action-bar">
        <div>
            <a href="facturacion.php" class="btn-modern btn-back">
                <i class="fas fa-arrow-left"></i>
                <?php echo __('back', [], 'Back to Invoices'); ?>
            </a>
        </div>
        <div class="action-buttons">
            <?php if ($invoice['status'] !== 'paid' && $invoice['status'] !== 'cancelled'): ?>
            <button class="btn-modern btn-success" onclick="openPaymentModal()">
                <i class="fas fa-dollar-sign"></i>
                <?php echo __('invoice.register_payment', [], 'Register Payment'); ?>
            </button>
            <?php endif; ?>
            
            <button class="btn-modern btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i>
                <?php echo __('print', [], 'Print'); ?>
            </button>
            
            <button class="btn-modern btn-primary" onclick="downloadPDF()">
                <i class="fas fa-file-pdf"></i>
                <?php echo __('download_pdf', [], 'Download PDF'); ?>
            </button>
            
            <?php if ($currentUser['role']['name'] === 'administrador' && $invoice['status'] !== 'paid'): ?>
            <button class="btn-modern btn-danger" onclick="cancelInvoice()">
                <i class="fas fa-times"></i>
                <?php echo __('cancel', [], 'Cancel Invoice'); ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoice Document -->
    <div class="invoice-document">
        <!-- Header -->
        <div class="invoice-header-section">
            <div class="invoice-header-content">
                <div class="company-details">
                    <h1>JAF INVESTMENTS</h1>
                    <p><?php echo __('company.tagline', [], 'Real Estate Excellence'); ?></p>
                    <p><?php echo __('company.location', [], 'Santo Domingo, Dominican Republic'); ?></p>
                    <p><?php echo __('phone', [], 'Phone'); ?>: +1 (809) 555-0100 | <?php echo __('email', [], 'Email'); ?>: info@jafinvestments.com</p>
                </div>
                <div class="invoice-meta-section">
                    <div class="invoice-number-display">
                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                    </div>
                    <div class="invoice-status-badge">
                        <?php 
                        $statusTranslations = [
                            'pending' => __('invoice.status.pending', [], 'Pending'),
                            'partial' => __('invoice.status.partial', [], 'Partial'),
                            'paid' => __('invoice.status.paid', [], 'Paid'),
                            'overdue' => __('invoice.status.overdue', [], 'Overdue'),
                            'cancelled' => __('invoice.status.cancelled', [], 'Cancelled')
                        ];
                        echo $statusTranslations[$invoice['status']] ?? ucfirst($invoice['status']);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="invoice-body-section">
            <!-- Billing Information -->
            <div class="billing-section">
                <div class="billing-box">
                    <h3><?php echo __('invoice.bill_to', [], 'Bill To'); ?></h3>
                    <div class="billing-name"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                    <div class="billing-detail">
                        <strong><?php echo __('client.reference', [], 'Client ID'); ?>:</strong> 
                        <?php echo htmlspecialchars($invoice['client_reference']); ?>
                    </div>
                    <?php if ($invoice['client_email']): ?>
                    <div class="billing-detail">
                        <strong><?php echo __('email', [], 'Email'); ?>:</strong> 
                        <?php echo htmlspecialchars($invoice['client_email']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['client_phone']): ?>
                    <div class="billing-detail">
                        <strong><?php echo __('phone', [], 'Phone'); ?>:</strong> 
                        <?php echo htmlspecialchars($invoice['client_phone']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($invoice['client_address']): ?>
                    <div class="billing-detail">
                        <strong><?php echo __('address', [], 'Address'); ?>:</strong> 
                        <?php echo htmlspecialchars($invoice['client_address']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="billing-box">
                    <h3><?php echo __('invoice.property_details', [], 'Property Details'); ?></h3>
                    <div class="billing-name"><?php echo htmlspecialchars($invoice['property_reference']); ?></div>
                    <div class="billing-detail"><?php echo htmlspecialchars($invoice['property_title']); ?></div>
                    <?php if ($invoice['property_address']): ?>
                    <div class="billing-detail"><?php echo htmlspecialchars($invoice['property_address']); ?></div>
                    <?php endif; ?>
                    <?php if ($invoice['agent_name']): ?>
                    <div class="billing-detail" style="margin-top: 15px;">
                        <strong><?php echo __('agent', [], 'Managed By'); ?>:</strong><br>
                        <?php echo htmlspecialchars($invoice['agent_name']); ?>
                        <?php if ($invoice['agent_email']): ?>
                        <br><?php echo htmlspecialchars($invoice['agent_email']); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Invoice Information -->
            <div class="invoice-info-grid">
                <div class="info-item">
                    <div class="info-label"><?php echo __('invoice.date', [], 'Invoice Date'); ?></div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($invoice['invoice_date'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><?php echo __('invoice.due_date', [], 'Due Date'); ?></div>
                    <div class="info-value"><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><?php echo __('invoice.type', [], 'Type'); ?></div>
                    <div class="info-value">
                        <?php 
                        echo $invoice['invoice_type'] === 'rent' 
                            ? __('invoice.type.rent', [], 'Rental') 
                            : __('invoice.type.sale', [], 'Sale'); 
                        ?>
                    </div>
                </div>
                <?php if ($invoice['billing_period']): ?>
                <div class="info-item">
                    <div class="info-label"><?php echo __('invoice.period', [], 'Billing Period'); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($invoice['billing_period']); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items Table -->
            <div class="items-table-wrapper">
                <table class="invoice-items-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;"><?php echo __('invoice.description', [], 'Description'); ?></th>
                            <th class="text-end"><?php echo __('invoice.amount', [], 'Amount'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="item-description">
                                    <?php 
                                    if ($invoice['invoice_type'] === 'rent') {
                                        echo __('invoice.item.monthly_rent', [], 'Monthly Rental Payment');
                                        if ($invoice['billing_period']) {
                                            echo ' - ' . htmlspecialchars($invoice['billing_period']);
                                        }
                                    } else {
                                        echo __('invoice.item.property_sale', [], 'Property Sale Payment');
                                    }
                                    ?>
                                </div>
                                <div class="item-details">
                                    <?php echo htmlspecialchars($invoice['property_reference']); ?> - 
                                    <?php echo htmlspecialchars($invoice['property_title']); ?>
                                    <?php if ($invoice['notes']): ?>
                                    <br><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <strong style="font-size: 16px;">$<?php echo number_format($invoice['subtotal'], 2); ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="totals-section">
                <div class="total-line">
                    <span><?php echo __('invoice.subtotal', [], 'Subtotal'); ?>:</span>
                    <span class="total-line-value">$<?php echo number_format($invoice['subtotal'], 2); ?></span>
                </div>

                <?php if ($invoice['discount_amount'] > 0): ?>
                <div class="total-line discount">
                    <span><?php echo __('invoice.discount', [], 'Discount'); ?> (<?php echo $invoice['discount_percentage']; ?>%):</span>
                    <span class="total-line-value">-$<?php echo number_format($invoice['discount_amount'], 2); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($invoice['tax_amount'] > 0): ?>
                <div class="total-line">
                    <span><?php echo __('invoice.tax', [], 'Tax'); ?> (<?php echo $invoice['tax_percentage']; ?>%):</span>
                    <span class="total-line-value">$<?php echo number_format($invoice['tax_amount'], 2); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($invoice['late_fee'] > 0): ?>
                <div class="total-line late-fee">
                    <span><?php echo __('invoice.late_fee', [], 'Late Fee'); ?>:</span>
                    <span class="total-line-value">$<?php echo number_format($invoice['late_fee'], 2); ?></span>
                </div>
                <?php endif; ?>

                <div class="total-line grand-total">
                    <span><?php echo __('invoice.total', [], 'Total Amount'); ?>:</span>
                    <span>$<?php echo number_format($invoice['total_amount'], 2); ?></span>
                </div>

                <?php if ($invoice['amount_paid'] > 0): ?>
                <div class="total-line paid-amount">
                    <span><?php echo __('invoice.amount_paid', [], 'Amount Paid'); ?>:</span>
                    <span class="total-line-value">$<?php echo number_format($invoice['amount_paid'], 2); ?></span>
                </div>
                <?php endif; ?>

                <?php if ($invoice['balance_due'] > 0): ?>
                <div class="total-line balance-due">
                    <span><?php echo __('invoice.balance_due', [], 'Balance Due'); ?>:</span>
                    <span>$<?php echo number_format($invoice['balance_due'], 2); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <?php if (!empty($payments)): ?>
    <div class="payments-history-section">
        <div class="section-header">
            <i class="fas fa-history"></i>
            <h2 class="section-title"><?php echo __('invoice.payment_history', [], 'Payment History'); ?></h2>
        </div>

        <table class="payments-history-table">
            <thead>
                <tr>
                    <th><?php echo __('payments.date', [], 'Date'); ?></th>
                    <th><?php echo __('payments.amount', [], 'Amount'); ?></th>
                    <th><?php echo __('payments.method', [], 'Method'); ?></th>
                    <th><?php echo __('payments.reference', [], 'Reference'); ?></th>
                    <th><?php echo __('payments.registered_by', [], 'Registered By'); ?></th>
                    <th><?php echo __('notes', [], 'Notes'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                    <td>
                        <span class="payment-amount-display">$<?php echo number_format($payment['payment_amount'], 2); ?></span>
                    </td>
                    <td>
                        <span class="payment-method-badge">
                            <?php echo __('payment_method.' . $payment['payment_method'], [], ucwords(str_replace('_', ' ', $payment['payment_method']))); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($payment['payment_reference'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($payment['created_by_name'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($payment['notes'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="payments-history-section">
        <div class="section-header">
            <i class="fas fa-history"></i>
            <h2 class="section-title"><?php echo __('invoice.payment_history', [], 'Payment History'); ?></h2>
        </div>
        <div class="no-payments-message">
            <i class="fas fa-info-circle" style="font-size: 48px; color: #cbd5e0; margin-bottom: 15px; display: block;"></i>
            <?php echo __('invoice.no_payments_yet', [], 'No payments have been registered for this invoice yet'); ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Register Payment -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-dollar-sign text-success me-2"></i>
                    <?php echo __('invoice.register_payment', [], 'Register Payment'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentForm">
                <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <div class="mb-2">
                            <strong><?php echo __('invoice.number', [], 'Invoice'); ?>:</strong> 
                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo __('invoice.total', [], 'Total'); ?>:</strong> 
                            $<?php echo number_format($invoice['total_amount'], 2); ?>
                        </div>
                        <div class="mb-2">
                            <strong><?php echo __('invoice.amount_paid', [], 'Paid'); ?>:</strong> 
                            $<?php echo number_format($invoice['amount_paid'], 2); ?>
                        </div>
                        <div>
                            <strong><?php echo __('invoice.balance_due', [], 'Balance'); ?>:</strong> 
                            <span class="text-danger fw-bold">$<?php echo number_format($invoice['balance_due'], 2); ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payments.payment_date', [], 'Payment Date'); ?> *</label>
                        <input type="date" class="form-control" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payments.payment_amount', [], 'Payment Amount'); ?> *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" name="payment_amount" 
                                   step="0.01" min="0.01" max="<?php echo $invoice['balance_due']; ?>" 
                                   value="<?php echo $invoice['balance_due']; ?>" required>
                        </div>
                        <small class="text-muted">
                            <?php echo __('payments.max_amount', [], 'Maximum'); ?>: $<?php echo number_format($invoice['balance_due'], 2); ?>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payments.payment_method', [], 'Payment Method'); ?> *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash"><?php echo __('payment_method.cash', [], 'Cash'); ?></option>
                            <option value="bank_transfer"><?php echo __('payment_method.bank_transfer', [], 'Bank Transfer'); ?></option>
                            <option value="check"><?php echo __('payment_method.check', [], 'Check'); ?></option>
                            <option value="credit_card"><?php echo __('payment_method.credit_card', [], 'Credit Card'); ?></option>
                            <option value="debit_card"><?php echo __('payment_method.debit_card', [], 'Debit Card'); ?></option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('payments.reference', [], 'Reference'); ?></label>
                        <input type="text" class="form-control" name="payment_reference" 
                               placeholder="<?php echo __('payments.reference_placeholder', [], 'Check #, Transfer #, etc.'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo __('notes', [], 'Notes'); ?></label>
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="<?php echo __('payments.notes_placeholder', [], 'Additional payment notes...'); ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo __('cancel', [], 'Cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>
                        <?php echo __('payments.register', [], 'Register Payment'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let paymentModal;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal
    const modalElement = document.getElementById('paymentModal');
    if (modalElement) {
        paymentModal = new bootstrap.Modal(modalElement);
    }

    // Payment form submit
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitPayment();
        });
    }
});

function openPaymentModal() {
    if (paymentModal) {
        paymentModal.show();
    }
}

function submitPayment() {
    const formData = new FormData(document.getElementById('paymentForm'));
    const submitBtn = document.querySelector('#paymentForm button[type="submit"]');
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i><?php echo __('processing', [], 'Processing'); ?>...';
    
    fetch('ajax/procesar-pago-factura.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ <?php echo __('payments.success', [], 'Payment registered successfully'); ?>');
            location.reload();
        } else {
            alert('❌ <?php echo __('error', [], 'Error'); ?>: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check me-2"></i><?php echo __('payments.register', [], 'Register Payment'); ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo __('payments.error', [], 'Error registering payment'); ?>');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check me-2"></i><?php echo __('payments.register', [], 'Register Payment'); ?>';
    });
}

function downloadPDF() {
    window.location.href = 'ajax/generate-invoice-pdf.php?id=<?php echo $invoice['id']; ?>';
}

function cancelInvoice() {
    if (!confirm('<?php echo __('invoice.cancel_confirm', [], 'Are you sure you want to cancel this invoice? This action cannot be undone.'); ?>')) {
        return;
    }
    
    fetch('actions/facturacion-actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=cancel_invoice&invoice_id=<?php echo $invoice['id']; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ <?php echo __('invoice.cancelled_success', [], 'Invoice cancelled successfully'); ?>');
            location.reload();
        } else {
            alert('❌ <?php echo __('error', [], 'Error'); ?>: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php echo __('invoice.cancel_error', [], 'Error cancelling invoice'); ?>');
    });
}
</script>

<?php include 'footer.php'; ?>
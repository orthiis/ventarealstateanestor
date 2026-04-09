<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Detalle de Transacción';
$currentUser = getCurrentUser();

// Obtener ID de la transacción
$transactionId = $_GET['id'] ?? 0;

// Obtener datos completos de la transacción
$transaction = db()->selectOne("
    SELECT st.*,
    p.reference as property_reference,
    p.title as property_title,
    p.address as property_address,
    p.city as property_city,
    p.status as property_current_status,
    pt.name as property_type,
    CONCAT(c.first_name, ' ', c.last_name) as client_name,
    c.email as client_email,
    c.phone as client_phone,
    c.reference as client_reference,
    CONCAT(u.first_name, ' ', u.last_name) as agent_name,
    u.email as agent_email,
    u.phone as agent_phone,
    u.profile_picture as agent_picture,
    CONCAT(u2.first_name, ' ', u2.last_name) as second_agent_name,
    o.name as office_name,
    (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as property_image
    FROM sales_transactions st
    INNER JOIN properties p ON st.property_id = p.id
    INNER JOIN clients c ON st.client_id = c.id
    INNER JOIN users u ON st.agent_id = u.id
    LEFT JOIN users u2 ON st.second_agent_id = u2.id
    LEFT JOIN property_types pt ON p.property_type_id = pt.id
    LEFT JOIN offices o ON st.office_id = o.id
    WHERE st.id = ?
", [$transactionId]);

if (!$transaction) {
    setFlashMessage('error', 'Transacción no encontrada');
    redirect('ventas.php');
}

// Obtener timeline
$timeline = db()->select("
    SELECT st.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM sale_timeline st
    LEFT JOIN users u ON st.user_id = u.id
    WHERE st.transaction_id = ?
    ORDER BY st.created_at DESC
", [$transactionId]);

// Obtener pagos registrados
$payments = db()->select("
    SELECT sp.*, CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM sale_payments sp
    LEFT JOIN users u ON sp.created_by = u.id
    WHERE sp.transaction_id = ?
    ORDER BY sp.payment_date DESC
", [$transactionId]);

// Calcular totales de pagos
$totalPaid = array_sum(array_column($payments, 'payment_amount'));
$balancePending = $transaction['sale_price'] - $totalPaid;

// Obtener agentes para asignar
$agents = db()->select("
    SELECT id, CONCAT(first_name, ' ', last_name) as name
    FROM users
    WHERE status = 'active' AND role_id IN (2, 3)
    ORDER BY first_name
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

    .sale-detail-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    /* Header */
    .detail-header {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 20px;
    }

    .header-info h1 {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 8px 0;
    }

    .header-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 14px;
        color: #718096;
    }

    .header-meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* Status Badges */
    .status-badge {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-pending { background: #fef3c7; color: #92400e; }
    .status-in-progress { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }

    /* Main Grid */
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
        margin-bottom: 25px;
    }

    /* Card */
    .card-modern {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-header h5 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-body {
        padding: 25px;
    }

    /* Property Card */
    .property-showcase {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .property-showcase-img {
        width: 150px;
        height: 150px;
        border-radius: 12px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .property-showcase-info h4 {
        margin: 0 0 8px 0;
        font-size: 20px;
        font-weight: 700;
        color: #2d3748;
    }

    .property-showcase-info p {
        margin: 4px 0;
        color: #718096;
        font-size: 14px;
    }

    /* Info Row */
    .info-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .info-item label {
        display: block;
        font-size: 12px;
        color: #9ca3af;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .info-item .value {
        font-size: 16px;
        font-weight: 600;
        color: #2d3748;
    }

    .info-item .value.large {
        font-size: 24px;
        color: var(--primary);
    }

    .info-item .value.success {
        color: var(--success);
    }

    /* Client/Agent Card */
    .person-card {
        display: flex;
        gap: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 12px;
        margin-bottom: 15px;
    }

    .person-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .person-avatar.placeholder {
        background: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #4a5568;
        font-size: 20px;
    }

    .person-info h6 {
        margin: 0 0 6px 0;
        font-size: 16px;
        font-weight: 600;
        color: #2d3748;
    }

    .person-info p {
        margin: 2px 0;
        font-size: 13px;
        color: #718096;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Timeline */
    .timeline {
        position: relative;
        padding-left: 40px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 25px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -29px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: white;
        border: 3px solid var(--primary);
    }

    .timeline-item.payment::before {
        border-color: var(--success);
    }

    .timeline-item.cancelled::before {
        border-color: var(--danger);
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
    }

    .timeline-content h6 {
        margin: 0 0 6px 0;
        font-size: 14px;
        font-weight: 600;
        color: #2d3748;
    }

    .timeline-content p {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #718096;
    }

    .timeline-content .timeline-meta {
        font-size: 12px;
        color: #9ca3af;
    }

    /* Payment Table */
    .payment-table {
        width: 100%;
        border-collapse: collapse;
    }

    .payment-table thead th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        color: #4a5568;
        border-bottom: 2px solid #e2e8f0;
    }

    .payment-table tbody td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
        color: #2d3748;
    }

    .payment-table tbody tr:hover {
        background: #f8f9fa;
    }

    /* Summary Box */
    .summary-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 12px;
        margin-top: 20px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 15px;
    }

    .summary-item.total {
        border-top: 2px solid rgba(255,255,255,0.3);
        padding-top: 12px;
        margin-top: 12px;
        font-size: 18px;
        font-weight: 700;
    }

    /* Buttons */
    .btn-modern {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary { background: var(--primary); color: white; }
    .btn-success { background: var(--success); color: white; }
    .btn-warning { background: var(--warning); color: white; }
    .btn-danger { background: var(--danger); color: white; }
    .btn-outline { background: white; border: 2px solid #e2e8f0; color: #4a5568; }

    .btn-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        overflow-y: auto;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        padding: 20px 25px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h5 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #2d3748;
    }

    .modal-body {
        padding: 25px;
    }

    .modal-footer {
        padding: 20px 25px;
        border-top: 2px solid #f0f0f0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 24px;
        color: #9ca3af;
        cursor: pointer;
        line-height: 1;
    }

    /* Form */
    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #4a5568;
        font-size: 14px;
    }

    .form-control, .form-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .sale-detail-container {
            padding: 15px;
        }

        .detail-header {
            padding: 20px;
            flex-direction: column;
        }

        .header-info h1 {
            font-size: 22px;
        }

        .header-actions {
            width: 100%;
        }

        .header-actions .btn-modern {
            flex: 1;
            justify-content: center;
        }

        .property-showcase {
            flex-direction: column;
        }

        .property-showcase-img {
            width: 100%;
            height: 200px;
        }

        .info-row {
            grid-template-columns: 1fr;
        }

        .card-body {
            padding: 20px;
        }

        .payment-table {
            font-size: 12px;
        }

        .payment-table thead th,
        .payment-table tbody td {
            padding: 8px;
        }

        .timeline {
            padding-left: 30px;
        }

        .modal-content {
            max-width: 100%;
            margin: 20px;
        }
    }

    @media (max-width: 480px) {
        .header-info h1 {
            font-size: 20px;
        }

        .status-badge {
            font-size: 11px;
            padding: 6px 12px;
        }

        .person-card {
            flex-direction: column;
            text-align: center;
        }

        .person-avatar {
            margin: 0 auto;
        }

        .summary-box {
            padding: 20px;
        }

        .summary-item {
            font-size: 13px;
        }

        .summary-item.total {
            font-size: 16px;
        }
    }
</style>

<div class="sale-detail-container">
    
    <!-- Header -->
    <div class="detail-header">
        <div class="header-info">
            <h1>
                <i class="fas fa-file-invoice-dollar" style="color: var(--primary);"></i>
                Transacción #<?php echo $transaction['transaction_code'] ?? $transaction['id']; ?>
            </h1>
            <div class="header-meta">
                <span>
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?>
                </span>
                <span>
                    <?php
                    $typeLabels = [
                        'sale' => '<i class="fas fa-home"></i> Venta',
                        'rent' => '<i class="fas fa-key"></i> Alquiler',
                        'vacation_rent' => '<i class="fas fa-umbrella-beach"></i> Alq. Vacacional'
                    ];
                    echo $typeLabels[$transaction['transaction_type']] ?? $transaction['transaction_type'];
                    ?>
                </span>
                <?php
                $statusLabels = [
                    'pending' => 'Pendiente',
                    'in_progress' => 'En Progreso',
                    'completed' => 'Completado',
                    'cancelled' => 'Cancelado'
                ];
                $statusClass = 'status-' . $transaction['status'];
                ?>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <i class="fas fa-circle" style="font-size: 8px;"></i>
                    <?php echo $statusLabels[$transaction['status']]; ?>
                </span>
            </div>
        </div>
        <div class="header-actions">
            <button onclick="openPaymentModal()" class="btn-modern btn-success">
                <i class="fas fa-plus"></i> Agregar Pago
            </button>
            <button onclick="openStatusModal()" class="btn-modern btn-warning">
                <i class="fas fa-edit"></i> Cambiar Estado
            </button>
            <a href="editar-venta.php?id=<?php echo $transaction['id']; ?>" class="btn-modern btn-primary">
                <i class="fas fa-pen"></i> Editar
            </a>
            <a href="ventas.php" class="btn-modern btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="detail-grid">
        
        <!-- Left Column -->
        <div>
            <!-- Property Info -->
            <div class="card-modern" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-building" style="color: var(--success);"></i>
                        Información de la Propiedad
                    </h5>
                </div>
                <div class="card-body">
                    <div class="property-showcase">
                        <img src="<?php echo $transaction['property_image'] ?? 'assets/img/no-image.jpg'; ?>" 
                             alt="" class="property-showcase-img">
                        <div class="property-showcase-info">
                            <h4><?php echo htmlspecialchars($transaction['property_reference']); ?></h4>
                            <p><strong><?php echo htmlspecialchars($transaction['property_title']); ?></strong></p>
                            <p>
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($transaction['property_address'] . ', ' . $transaction['property_city']); ?>
                            </p>
                            <p>
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($transaction['property_type'] ?? 'N/A'); ?>
                            </p>
                            <a href="ver-propiedad.php?id=<?php echo $transaction['property_id']; ?>" 
                               class="btn-modern btn-outline btn-sm" style="margin-top: 10px;">
                                <i class="fas fa-external-link-alt"></i> Ver Propiedad
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Details -->
            <div class="card-modern" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-dollar-sign" style="color: var(--success);"></i>
                        Detalles Financieros
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <div class="info-item">
                            <label>Precio de Venta/Alquiler</label>
                            <div class="value large">$<?php echo number_format($transaction['sale_price'], 2); ?></div>
                        </div>
                        <?php if ($transaction['original_price']): ?>
                        <div class="info-item">
                            <label>Precio Original</label>
                            <div class="value">$<?php echo number_format($transaction['original_price'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label>Comisión (<?php echo $transaction['commission_percentage']; ?>%)</label>
                            <div class="value success">$<?php echo number_format($transaction['commission_amount'], 2); ?></div>
                        </div>
                    </div>

                    <?php if ($transaction['transaction_type'] !== 'sale'): ?>
                    <hr style="margin: 20px 0; border-color: #e2e8f0;">
                    <h6 style="margin-bottom: 15px; font-weight: 600;">Detalles de Alquiler</h6>
                    <div class="info-row">
                        <?php if ($transaction['monthly_payment']): ?>
                        <div class="info-item">
                            <label>Pago Mensual</label>
                            <div class="value">$<?php echo number_format($transaction['monthly_payment'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($transaction['rent_duration_months']): ?>
                        <div class="info-item">
                            <label>Duración</label>
                            <div class="value"><?php echo $transaction['rent_duration_months']; ?> meses</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($transaction['warranty_amount']): ?>
                        <div class="info-item">
                            <label>Fianza/Garantía</label>
                            <div class="value">$<?php echo number_format($transaction['warranty_amount'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <hr style="margin: 20px 0; border-color: #e2e8f0;">
                    <h6 style="margin-bottom: 15px; font-weight: 600;">Costos Adicionales</h6>
                    <div class="info-row">
                        <?php if ($transaction['tax_amount']): ?>
                        <div class="info-item">
                            <label>Impuestos</label>
                            <div class="value">$<?php echo number_format($transaction['tax_amount'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($transaction['notary_fees']): ?>
                        <div class="info-item">
                            <label>Gastos Notariales</label>
                            <div class="value">$<?php echo number_format($transaction['notary_fees'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($transaction['other_fees']): ?>
                        <div class="info-item">
                            <label>Otros Gastos</label>
                            <div class="value">$<?php echo number_format($transaction['other_fees'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($transaction['total_transaction_cost']): ?>
                        <div class="info-item">
                            <label>Costo Total</label>
                            <div class="value large">$<?php echo number_format($transaction['total_transaction_cost'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="summary-box">
                        <div class="summary-item">
                            <span>Precio de Venta:</span>
                            <strong>$<?php echo number_format($transaction['sale_price'], 2); ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Total Pagado:</span>
                            <strong>$<?php echo number_format($totalPaid, 2); ?></strong>
                        </div>
                        <div class="summary-item total">
                            <span>Saldo Pendiente:</span>
                            <strong>$<?php echo number_format($balancePending, 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments History -->
            <div class="card-modern" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-credit-card" style="color: var(--success);"></i>
                        Historial de Pagos (<?php echo count($payments); ?>)
                    </h5>
                    <button onclick="openPaymentModal()" class="btn-modern btn-success btn-sm">
                        <i class="fas fa-plus"></i> Agregar
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                    <p style="text-align: center; color: #9ca3af; padding: 40px 0;">
                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                        No hay pagos registrados
                    </p>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Referencia</th>
                                    <th>Registrado por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><strong style="color: var(--success);">$<?php echo number_format($payment['payment_amount'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_reference'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['created_by_name']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card-modern">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-history" style="color: var(--info);"></i>
                        Línea de Tiempo
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($timeline as $event): ?>
                        <div class="timeline-item <?php echo $event['event_type']; ?>">
                            <div class="timeline-content">
                                <h6><?php echo htmlspecialchars($event['event_title']); ?></h6>
                                <?php if ($event['event_description']): ?>
                                <p><?php echo htmlspecialchars($event['event_description']); ?></p>
                                <?php endif; ?>
                                <div class="timeline-meta">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?>
                                    <?php if ($event['user_name']): ?>
                                    • Por <?php echo htmlspecialchars($event['user_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Client Info -->
            <div class="card-modern" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-user" style="color: var(--warning);"></i>
                        Cliente
                    </h5>
                </div>
                <div class="card-body">
                    <div class="person-card">
                        <div class="person-avatar placeholder">
                            <?php echo strtoupper(substr($transaction['client_name'], 0, 2)); ?>
                        </div>
                        <div class="person-info">
                            <h6><?php echo htmlspecialchars($transaction['client_name']); ?></h6>
                            <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($transaction['client_reference']); ?></p>
                            <?php if ($transaction['client_email']): ?>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($transaction['client_email']); ?></p>
                            <?php endif; ?>
                            <?php if ($transaction['client_phone']): ?>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($transaction['client_phone']); ?></p>
                            <?php endif; ?>
                            <a href="ver-cliente.php?id=<?php echo $transaction['client_id']; ?>" 
                               class="btn-modern btn-outline btn-sm" style="margin-top: 10px;">
                                <i class="fas fa-external-link-alt"></i> Ver Cliente
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Info -->
            <div class="card-modern" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-user-tie" style="color: var(--primary);"></i>
                        Agente Principal
                    </h5>
                </div>
                <div class="card-body">
                    <div class="person-card">
                        <?php if ($transaction['agent_picture']): ?>
                        <img src="<?php echo USER_IMAGES_URL . $transaction['agent_picture']; ?>" 
                             alt="" class="person-avatar">
                        <?php else: ?>
                        <div class="person-avatar placeholder">
                            <?php echo strtoupper(substr($transaction['agent_name'], 0, 2)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="person-info">
                            <h6><?php echo htmlspecialchars($transaction['agent_name']); ?></h6>
                            <?php if ($transaction['agent_email']): ?>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($transaction['agent_email']); ?></p>
                            <?php endif; ?>
                            <?php if ($transaction['agent_phone']): ?>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($transaction['agent_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($transaction['second_agent_name']): ?>
            <!-- Second Agent -->
            <div class="card-modern" style="margin-bottom: 25px;">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-user-tie" style="color: var(--info);"></i>
                        Agente Secundario
                    </h5>
                </div>
                <div class="card-body">
                    <div class="person-card">
                        <div class="person-avatar placeholder">
                            <?php echo strtoupper(substr($transaction['second_agent_name'], 0, 2)); ?>
                        </div>
                        <div class="person-info">
                            <h6><?php echo htmlspecialchars($transaction['second_agent_name']); ?></h6>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Additional Info -->
            <div class="card-modern">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-info-circle" style="color: var(--info);"></i>
                        Información Adicional
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-item" style="margin-bottom: 15px;">
                        <label>Financiamiento</label>
                        <div class="value">
                            <?php
                            $financingLabels = [
                                'cash' => 'Efectivo/Contado',
                                'bank_loan' => 'Préstamo Bancario',
                                'owner_financing' => 'Financiamiento del Propietario',
                                'mixed' => 'Mixto'
                            ];
                            echo $financingLabels[$transaction['financing_type']] ?? $transaction['financing_type'];
                            ?>
                        </div>
                    </div>

                    <?php if ($transaction['bank_name']): ?>
                    <div class="info-item" style="margin-bottom: 15px;">
                        <label>Banco</label>
                        <div class="value"><?php echo htmlspecialchars($transaction['bank_name']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transaction['office_name']): ?>
                    <div class="info-item" style="margin-bottom: 15px;">
                        <label>Oficina</label>
                        <div class="value"><?php echo htmlspecialchars($transaction['office_name']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transaction['contract_date']): ?>
                    <div class="info-item" style="margin-bottom: 15px;">
                        <label>Fecha de Contrato</label>
                        <div class="value"><?php echo date('d/m/Y', strtotime($transaction['contract_date'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transaction['closing_date']): ?>
                    <div class="info-item" style="margin-bottom: 15px;">
                        <label>Fecha de Cierre</label>
                        <div class="value"><?php echo date('d/m/Y', strtotime($transaction['closing_date'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transaction['notes']): ?>
                    <div class="info-item">
                        <label>Notas</label>
                        <div class="value" style="font-size: 14px; font-weight: 400; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($transaction['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Modal: Agregar Pago -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-credit-card"></i> Registrar Nuevo Pago</h5>
            <button class="close-modal" onclick="closePaymentModal()">&times;</button>
        </div>
        <form id="paymentForm">
            <div class="modal-body">
                <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Fecha de Pago</label>
                    <input type="date" name="payment_date" class="form-control" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Monto</label>
                    <input type="number" name="payment_amount" class="form-control" 
                           step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Método de Pago</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="">Selecciona...</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Transferencia">Transferencia Bancaria</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Tarjeta">Tarjeta de Crédito/Débito</option>
                        <option value="Otro">Otro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Referencia/Número</label>
                    <input type="text" name="payment_reference" class="form-control" 
                           placeholder="Número de cheque, transacción, etc.">
                </div>

                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Información adicional..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closePaymentModal()" class="btn-modern btn-outline">
                    Cancelar
                </button>
                <button type="submit" class="btn-modern btn-success">
                    <i class="fas fa-save"></i> Guardar Pago
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Cambiar Estado -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-edit"></i> Cambiar Estado de Transacción</h5>
            <button class="close-modal" onclick="closeStatusModal()">&times;</button>
        </div>
        <form id="statusForm">
            <div class="modal-body">
                <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                
                <div class="form-group">
                    <label class="form-label">Nuevo Estado</label>
                    <select name="status" class="form-select" required>
                        <option value="pending" <?php echo $transaction['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="in_progress" <?php echo $transaction['status'] === 'in_progress' ? 'selected' : ''; ?>>En Progreso</option>
                        <option value="completed" <?php echo $transaction['status'] === 'completed' ? 'selected' : ''; ?>>Completado</option>
                        <option value="cancelled" <?php echo $transaction['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>

                <div class="form-group" id="cancelReasonField" style="display: none;">
                    <label class="form-label">Razón de Cancelación</label>
                    <textarea name="cancelled_reason" class="form-control" rows="3" 
                              placeholder="Explica por qué se cancela la transacción..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Estado de Pago</label>
                    <select name="payment_status" class="form-select" required>
                        <option value="pending" <?php echo $transaction['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="partial" <?php echo $transaction['payment_status'] === 'partial' ? 'selected' : ''; ?>>Pago Parcial</option>
                        <option value="completed" <?php echo $transaction['payment_status'] === 'completed' ? 'selected' : ''; ?>>Pagado Completamente</option>
                        <option value="overdue" <?php echo $transaction['payment_status'] === 'overdue' ? 'selected' : ''; ?>>Atrasado</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeStatusModal()" class="btn-modern btn-outline">
                    Cancelar
                </button>
                <button type="submit" class="btn-modern btn-primary">
                    <i class="fas fa-save"></i> Actualizar Estado
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ============ MODALS ============
function openPaymentModal() {
    document.getElementById('paymentModal').classList.add('active');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('active');
    document.getElementById('paymentForm').reset();
}

function openStatusModal() {
    document.getElementById('statusModal').classList.add('active');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('active');
    document.getElementById('statusForm').reset();
}

// Mostrar/ocultar campo de razón de cancelación
document.querySelector('select[name="status"]').addEventListener('change', function() {
    const cancelReasonField = document.getElementById('cancelReasonField');
    if (this.value === 'cancelled') {
        cancelReasonField.style.display = 'block';
    } else {
        cancelReasonField.style.display = 'none';
    }
});

// ============ SUBMIT: AGREGAR PAGO ============
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'add_payment');
    
    fetch('ajax/venta-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Pago registrado exitosamente');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo registrar el pago'));
        }
    })
    .catch(error => {
        alert('❌ Error de conexión');
        console.error(error);
    });
});

// ============ SUBMIT: CAMBIAR ESTADO ============
document.getElementById('statusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'change_status');
    
    fetch('ajax/venta-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Estado actualizado exitosamente');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo actualizar el estado'));
        }
    })
    .catch(error => {
        alert('❌ Error de conexión');
        console.error(error);
    });
});

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const paymentModal = document.getElementById('paymentModal');
    const statusModal = document.getElementById('statusModal');
    
    if (event.target === paymentModal) {
        closePaymentModal();
    }
    if (event.target === statusModal) {
        closeStatusModal();
    }
}
</script>

<?php include 'footer.php'; ?>
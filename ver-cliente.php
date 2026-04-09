<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Detalle del Cliente';
$currentUser = getCurrentUser();

// Obtener ID del cliente
$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($clientId === 0) {
    setFlashMessage('error', 'ID de cliente no válido');
    redirect('clientes.php');
    exit;
}

// Obtener datos del cliente con información del agente
$client = db()->selectOne("SELECT * FROM clients WHERE id = ?", [$clientId]);

if (!$client) {
    setFlashMessage('error', 'Cliente no encontrado');
    redirect('clientes.php');
    exit;
}

// Obtener información del agente por separado
$client['agent_name'] = null;
$client['agent_email'] = null;
$client['agent_phone'] = null;
$client['agent_picture'] = null;

if (!empty($client['agent_id'])) {
    $agent = db()->selectOne(
        "SELECT CONCAT(first_name, ' ', last_name) as agent_name,
         email as agent_email,
         phone as agent_phone,
         profile_picture as agent_picture
         FROM users 
         WHERE id = ?",
        [$client['agent_id']]
    );
    
    if ($agent) {
        $client['agent_name'] = $agent['agent_name'];
        $client['agent_email'] = $agent['agent_email'];
        $client['agent_phone'] = $agent['agent_phone'];
        $client['agent_picture'] = $agent['agent_picture'];
    }
}

// Obtener propiedades que el cliente tiene (compradas o alquiladas)
$clientProperties = db()->select(
    "SELECT st.*,
     p.reference as property_reference,
     p.title as property_title,
     p.address as property_address,
     p.city as property_city,
     p.bedrooms, p.bathrooms, p.built_area,
     pt.name as property_type_name,
     (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image,
     CONCAT(u.first_name, ' ', u.last_name) as agent_name
     FROM sales_transactions st
     INNER JOIN properties p ON st.property_id = p.id
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     LEFT JOIN users u ON st.agent_id = u.id
     WHERE st.client_id = ?
     ORDER BY st.created_at DESC",
    [$clientId]
);

// Obtener facturas del cliente
$clientInvoices = db()->select(
    "SELECT i.*,
     p.reference as property_reference,
     p.title as property_title,
     st.transaction_code
     FROM invoices i
     LEFT JOIN properties p ON i.property_id = p.id
     LEFT JOIN sales_transactions st ON i.transaction_id = st.id
     WHERE i.client_id = ?
     ORDER BY i.created_at DESC
     LIMIT 50",
    [$clientId]
);

// Separar facturas pendientes y pagadas
$pendingInvoices = array_filter($clientInvoices, function($inv) {
    return in_array($inv['status'], ['pending', 'overdue']);
});

$paidInvoices = array_filter($clientInvoices, function($inv) {
    return $inv['status'] === 'paid';
});

// Obtener historial de interacciones
$interactions = db()->select(
    "SELECT ci.*, 
     CONCAT(u.first_name, ' ', u.last_name) as user_name
     FROM client_interactions ci
     LEFT JOIN users u ON ci.user_id = u.id
     WHERE ci.client_id = ?
     ORDER BY ci.interaction_date DESC, ci.created_at DESC
     LIMIT 10",
    [$clientId]
);

// Decodificar arrays JSON
$interestPropertyTypes = json_decode($client['property_type_interest'] ?? '[]', true);
$interestLocations = json_decode($client['locations_interest'] ?? '[]', true);

// Obtener nombres de tipos de propiedad
$propertyTypeNames = [];
if (!empty($interestPropertyTypes) && is_array($interestPropertyTypes)) {
    $propertyTypeNames = db()->select(
        "SELECT name FROM property_types WHERE id IN (" . implode(',', array_map('intval', $interestPropertyTypes)) . ")"
    );
}

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

    .view-client-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: calc(100vh - 80px);
    }

    /* Header Card */
    .client-header-card {
        background: linear-gradient(135deg, var(--primary) 0%, #5568d3 100%);
        color: white;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .client-header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .client-avatar-large {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: white;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 700;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .client-header-info h1 {
        font-size: 32px;
        font-weight: 700;
        margin: 0 0 8px 0;
    }

    .client-header-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .client-header-badge {
        background: rgba(255,255,255,0.2);
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .client-header-actions {
        display: flex;
        gap: 10px;
    }

    /* Info Cards */
    .info-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .info-card-title {
        font-size: 18px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-card-title i {
        color: var(--primary);
        font-size: 20px;
    }

    .info-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 15px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .info-label {
        font-size: 12px;
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-value {
        font-size: 15px;
        color: #2d3748;
        font-weight: 500;
    }

    .info-value.empty {
        color: #cbd5e0;
        font-style: italic;
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-badge.lead {
        background: #e0e7ff;
        color: #3730a3;
    }

    .status-badge.contacted {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-badge.qualified {
        background: #fef3c7;
        color: #92400e;
    }

    .status-badge.closed {
        background: #d1fae5;
        color: #065f46;
    }

    /* Property Cards */
    .property-card-mini {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        margin-bottom: 15px;
    }

    .property-card-mini:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        transform: translateY(-2px);
    }

    .property-card-mini-image {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: #f3f4f6;
    }

    .property-card-mini-content {
        padding: 15px;
    }

    .property-card-mini-title {
        font-size: 16px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 8px;
    }

    .property-card-mini-details {
        display: flex;
        gap: 15px;
        color: #6b7280;
        font-size: 13px;
        margin-bottom: 10px;
    }

    .property-card-mini-price {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 10px;
    }

    .property-card-mini-type {
        display: inline-block;
        padding: 4px 10px;
        background: #f3f4f6;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: #4b5563;
        margin-bottom: 10px;
    }

    /* Invoice Table */
    .invoice-table {
        width: 100%;
        border-collapse: collapse;
    }

    .invoice-table th {
        background: #f9fafb;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        color: #4b5563;
        font-size: 13px;
        border-bottom: 2px solid #e5e7eb;
    }

    .invoice-table td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 14px;
    }

    .invoice-table tr:hover {
        background: #f9fafb;
    }

    .invoice-number {
        font-weight: 600;
        color: var(--primary);
    }

    .invoice-status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }

    .invoice-status-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .invoice-status-badge.overdue {
        background: #fee2e2;
        color: #991b1b;
    }

    .invoice-status-badge.paid {
        background: #d1fae5;
        color: #065f46;
    }

    .invoice-status-badge.partial {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Buttons */
    .btn-modern {
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-white {
        background: white;
        color: var(--primary);
    }

    .btn-white:hover {
        background: #f3f4f6;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: #5568d3;
    }

    .btn-success {
        background: var(--success);
        color: white;
    }

    .btn-info {
        background: var(--info);
        color: white;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        color: #cbd5e0;
    }

    .empty-state-title {
        font-size: 18px;
        font-weight: 600;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .empty-state-text {
        font-size: 14px;
        color: #9ca3af;
    }

    /* Grid Layout */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -10px;
    }

    .col-lg-8 { flex: 0 0 66.666%; max-width: 66.666%; padding: 0 10px; }
    .col-lg-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 10px; }
    .col-md-6 { flex: 0 0 50%; max-width: 50%; padding: 0 10px; }
    .col-12 { flex: 0 0 100%; max-width: 100%; padding: 0 10px; }

    @media (max-width: 992px) {
        .col-lg-8, .col-lg-4, .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    /* Interaction Timeline */
    .interaction-item {
        padding: 12px;
        border-left: 3px solid var(--primary);
        margin-bottom: 12px;
        background: #f9fafb;
        border-radius: 0 8px 8px 0;
    }

    .interaction-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
    }

    .interaction-type {
        font-weight: 600;
        color: #2d3748;
        font-size: 14px;
    }

    .interaction-date {
        font-size: 12px;
        color: #9ca3af;
    }

    .interaction-notes {
        font-size: 13px;
        color: #6b7280;
    }

    /* Transaction Type Badge */
    .transaction-type-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }

    .transaction-type-badge.sale {
        background: #d1fae5;
        color: #065f46;
    }

    .transaction-type-badge.rent {
        background: #dbeafe;
        color: #1e40af;
    }

    .transaction-type-badge.vacation_rent {
        background: #fef3c7;
        color: #92400e;
    }
</style>

<div class="view-client-container">
    <!-- Client Header -->
    <div class="client-header-card">
        <div class="client-header-content">
            <div style="display: flex; gap: 20px; align-items: center;">
                <div class="client-avatar-large">
                    <?php echo strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)); ?>
                </div>
                <div class="client-header-info">
                    <h1><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h1>
                    <div class="client-header-meta">
                        <span class="client-header-badge">
                            <i class="fas fa-hashtag"></i>
                            REF: <?php echo htmlspecialchars($client['reference']); ?>
                        </span>
                        <span class="client-header-badge">
                            <i class="fas fa-user"></i>
                            <?php 
                            $typeLabels = [
                                'buyer' => 'Comprador',
                                'seller' => 'Vendedor',
                                'tenant' => 'Arrendatario',
                                'landlord' => 'Arrendador',
                                'investor' => 'Inversor',
                                'other' => 'Otro'
                            ];
                            echo $typeLabels[$client['client_type']] ?? $client['client_type'];
                            ?>
                        </span>
                        <?php if ($client['email']): ?>
                        <a href="mailto:<?php echo $client['email']; ?>" class="client-header-badge" style="text-decoration: none; color: white;">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($client['email']); ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($client['phone_mobile']): ?>
                        <a href="tel:<?php echo $client['phone_mobile']; ?>" class="client-header-badge" style="text-decoration: none; color: white;">
                            <i class="fas fa-phone"></i>
                            <?php echo htmlspecialchars($client['phone_mobile']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="client-header-actions">
                <a href="editar-cliente.php?id=<?php echo $client['id']; ?>" class="btn-modern btn-white">
                    <i class="fas fa-edit"></i>
                    Editar
                </a>
                <a href="clientes.php" class="btn-modern btn-white">
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Column -->
        <div class="col-lg-8">
            
            <!-- Información Personal -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-user"></i>
                    Información Personal
                </h3>
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">Documento</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['document_id'] ?: 'No especificado'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tipo de Documento</span>
                        <span class="info-value"><?php echo htmlspecialchars(strtoupper($client['document_type'] ?? 'N/A')); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Nacimiento</span>
                        <span class="info-value"><?php echo $client['date_of_birth'] ? date('d/m/Y', strtotime($client['date_of_birth'])) : 'No especificada'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nacionalidad</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['nationality'] ?: 'No especificada'); ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label">Email Secundario</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['email_secondary'] ?: 'No especificado'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono Fijo</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['phone_home'] ?: 'No especificado'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ciudad</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['city'] ?: 'No especificada'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">País</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['country'] ?: 'No especificado'); ?></span>
                    </div>
                </div>
                <?php if ($client['address']): ?>
                <div class="info-row">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">Dirección Completa</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['address']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Propiedades del Cliente -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-home"></i>
                    Propiedades del Cliente
                </h3>
                <?php if (empty($clientProperties)): ?>
                <div class="empty-state">
                    <i class="fas fa-home"></i>
                    <div class="empty-state-title">Sin Propiedades</div>
                    <div class="empty-state-text">Este cliente aún no tiene propiedades asignadas</div>
                </div>
                <?php else: ?>
                    <?php foreach ($clientProperties as $prop): ?>
                    <div class="property-card-mini">
                        <?php if ($prop['main_image']): ?>
                        <img src="<?php echo htmlspecialchars($prop['main_image']); ?>" 
                             alt="<?php echo htmlspecialchars($prop['property_title']); ?>" 
                             class="property-card-mini-image">
                        <?php else: ?>
                        <div class="property-card-mini-image" style="display: flex; align-items: center; justify-content: center; color: #9ca3af;">
                            <i class="fas fa-image" style="font-size: 48px;"></i>
                        </div>
                        <?php endif; ?>
                        <div class="property-card-mini-content">
                            <span class="transaction-type-badge <?php echo $prop['transaction_type']; ?>">
                                <?php 
                                $transTypeLabels = [
                                    'sale' => 'Venta',
                                    'rent' => 'Alquiler',
                                    'vacation_rent' => 'Alquiler Vacacional'
                                ];
                                echo $transTypeLabels[$prop['transaction_type']] ?? $prop['transaction_type'];
                                ?>
                            </span>
                            <div class="property-card-mini-title">
                                <?php echo htmlspecialchars($prop['property_title']); ?>
                            </div>
                            <div style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($prop['property_address']); ?>
                            </div>
                            <div class="property-card-mini-details">
                                <span><i class="fas fa-bed"></i> <?php echo $prop['bedrooms']; ?> hab.</span>
                                <span><i class="fas fa-bath"></i> <?php echo $prop['bathrooms']; ?> baños</span>
                                <span><i class="fas fa-ruler-combined"></i> <?php echo number_format($prop['built_area']); ?> m²</span>
                            </div>
                            <div class="property-card-mini-price">
                                $<?php echo number_format($prop['sale_price'], 2); ?>
                                <?php if ($prop['transaction_type'] !== 'sale'): ?>
                                <span style="font-size: 13px; color: #6b7280; font-weight: 400;">/mes</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 10px; font-size: 12px; color: #6b7280;">
                                <span><strong>Código:</strong> <?php echo htmlspecialchars($prop['transaction_code']); ?></span>
                                <span><strong>REF:</strong> <?php echo htmlspecialchars($prop['property_reference']); ?></span>
                            </div>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb;">
                                <a href="ver-venta.php?id=<?php echo $prop['id']; ?>" class="btn-modern btn-primary" style="width: 100%; justify-content: center;">
                                    <i class="fas fa-eye"></i>
                                    Ver Transacción
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Facturas Pendientes -->
            <?php if (!empty($pendingInvoices)): ?>
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                    Facturas Pendientes
                </h3>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Propiedad</th>
                            <th>Período</th>
                            <th>Monto</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingInvoices as $invoice): ?>
                        <tr>
                            <td>
                                <a href="ver-factura.php?id=<?php echo $invoice['id']; ?>" class="invoice-number">
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($invoice['property_reference']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['period_display'] ?? 'N/A'); ?></td>
                            <td style="font-weight: 600;">$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                            <td>
                                <span class="invoice-status-badge <?php echo $invoice['status']; ?>">
                                    <?php 
                                    $statusLabels = [
                                        'pending' => 'Pendiente',
                                        'overdue' => 'Vencida',
                                        'paid' => 'Pagada',
                                        'partial' => 'Parcial'
                                    ];
                                    echo $statusLabels[$invoice['status']] ?? $invoice['status'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="ver-factura.php?id=<?php echo $invoice['id']; ?>" class="btn-modern btn-info" style="padding: 6px 12px; font-size: 12px;">
                                    <i class="fas fa-eye"></i>
                                    Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Historial de Facturas -->
            <?php if (!empty($paidInvoices)): ?>
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-file-invoice-dollar"></i>
                    Historial de Facturas
                </h3>
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Propiedad</th>
                            <th>Período</th>
                            <th>Monto</th>
                            <th>Fecha Pago</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $displayedInvoices = array_slice($paidInvoices, 0, 10);
                        foreach ($displayedInvoices as $invoice): 
                        ?>
                        <tr>
                            <td>
                                <a href="ver-factura.php?id=<?php echo $invoice['id']; ?>" class="invoice-number">
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($invoice['property_reference']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['period_display'] ?? 'N/A'); ?></td>
                            <td style="font-weight: 600;">$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td><?php echo $invoice['paid_date'] ? date('d/m/Y', strtotime($invoice['paid_date'])) : '-'; ?></td>
                            <td>
                                <span class="invoice-status-badge paid">
                                    Pagada
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($paidInvoices) > 10): ?>
                <div style="text-align: center; padding: 15px;">
                    <a href="facturas.php?client_id=<?php echo $client['id']; ?>" class="btn-modern btn-primary">
                        Ver Todas las Facturas (<?php echo count($paidInvoices); ?>)
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            
            <!-- Estado y Comercial -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-briefcase"></i>
                    Información Comercial
                </h3>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Estado del Cliente</span>
                    <span class="status-badge <?php echo $client['status']; ?>">
                        <?php 
                        $statusLabels = [
                            'lead' => 'Prospecto',
                            'contacted' => 'Contactado',
                            'qualified' => 'Calificado',
                            'proposal_sent' => 'Propuesta Enviada',
                            'negotiation' => 'En Negociación',
                            'closed' => 'Cerrado',
                            'lost' => 'Perdido'
                        ];
                        echo $statusLabels[$client['status']] ?? $client['status'];
                        ?>
                    </span>
                </div>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Registro</span>
                    <span class="status-badge <?php echo $client['is_active'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle" style="font-size: 8px;"></i>
                        <?php echo $client['is_active'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Fuente</span>
                    <span class="info-value">
                        <?php 
                        $sourceLabels = [
                            'website' => 'Sitio Web',
                            'referral' => 'Referido',
                            'call' => 'Llamada',
                            'portal' => 'Portal',
                            'social_media' => 'Redes Sociales',
                            'walk_in' => 'Visita Directa',
                            'other' => 'Otro'
                        ];
                        echo $sourceLabels[$client['source'] ?? 'website'] ?? 'No especificado';
                        ?>
                    </span>
                </div>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Prioridad</span>
                    <span class="info-value">
                        <?php 
                        $priorityLabels = [
                            'low' => 'Baja',
                            'medium' => 'Media',
                            'high' => 'Alta',
                            'urgent' => 'Urgente'
                        ];
                        echo $priorityLabels[$client['priority'] ?? 'medium'] ?? 'Media';
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Día de Pago</span>
                    <span class="info-value">
                        <?php echo $client['payment_day'] ? 'Día ' . $client['payment_day'] : 'No especificado'; ?>
                    </span>
                </div>
            </div>

            <!-- Agente Asignado -->
            <?php if ($client['agent_id']): ?>
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-user-tie"></i>
                    Agente Asignado
                </h3>
                <div style="text-align: center;">
                    <?php if ($client['agent_picture']): ?>
                    <img src="<?php echo htmlspecialchars($client['agent_picture']); ?>" 
                         alt="<?php echo htmlspecialchars($client['agent_name']); ?>"
                         style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
                    <?php else: ?>
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; margin-bottom: 10px;">
                        <?php echo strtoupper(substr($client['agent_name'], 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                    <div style="font-size: 16px; font-weight: 600; color: #2d3748; margin-bottom: 8px;">
                        <?php echo htmlspecialchars($client['agent_name']); ?>
                    </div>
                    <?php if ($client['agent_email']): ?>
                    <a href="mailto:<?php echo $client['agent_email']; ?>" style="font-size: 13px; color: #6b7280; display: block; margin-bottom: 4px;">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($client['agent_email']); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($client['agent_phone']): ?>
                    <a href="tel:<?php echo $client['agent_phone']; ?>" style="font-size: 13px; color: #6b7280; display: block;">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($client['agent_phone']); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Preferencias -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-heart"></i>
                    Preferencias
                </h3>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Presupuesto</span>
                    <span class="info-value">
                        <?php if ($client['budget_min'] || $client['budget_max']): ?>
                            $<?php echo number_format($client['budget_min'] ?? 0, 0); ?> - 
                            $<?php echo number_format($client['budget_max'] ?? 0, 0); ?>
                        <?php else: ?>
                            <span class="empty">No especificado</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Habitaciones Deseadas</span>
                    <span class="info-value"><?php echo $client['bedrooms_desired'] ?? 'No especificado'; ?></span>
                </div>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Baños Deseados</span>
                    <span class="info-value"><?php echo $client['bathrooms_desired'] ?? 'No especificado'; ?></span>
                </div>
                <?php if (!empty($propertyTypeNames)): ?>
                <div class="info-item" style="margin-bottom: 15px;">
                    <span class="info-label">Tipos de Propiedad</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                        <?php foreach ($propertyTypeNames as $type): ?>
                        <span style="background: #f3f4f6; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                            <?php echo htmlspecialchars($type['name']); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($interestLocations) && is_array($interestLocations)): ?>
                <div class="info-item">
                    <span class="info-label">Ubicaciones de Interés</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px;">
                        <?php foreach ($interestLocations as $location): ?>
                        <span style="background: #e0e7ff; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; color: var(--primary);">
                            <?php echo htmlspecialchars($location); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Última Actividad -->
            <?php if (!empty($interactions)): ?>
            <div class="info-card">
                <h3 class="info-card-title">
                    <i class="fas fa-history"></i>
                    Últimas Interacciones
                </h3>
                <?php foreach (array_slice($interactions, 0, 5) as $interaction): ?>
                <div class="interaction-item">
                    <div class="interaction-header">
                        <span class="interaction-type">
                            <?php 
                            $interactionTypes = [
                                'call' => 'Llamada',
                                'email' => 'Email',
                                'meeting' => 'Reunión',
                                'visit' => 'Visita',
                                'note' => 'Nota'
                            ];
                            echo $interactionTypes[$interaction['interaction_type']] ?? $interaction['interaction_type'];
                            ?>
                        </span>
                        <span class="interaction-date">
                            <?php echo date('d/m/Y', strtotime($interaction['interaction_date'])); ?>
                        </span>
                    </div>
                    <div class="interaction-notes">
                        <?php echo htmlspecialchars(substr($interaction['notes'], 0, 100)); ?>
                        <?php if (strlen($interaction['notes']) > 100): ?>...<?php endif; ?>
                    </div>
                    <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">
                        Por: <?php echo htmlspecialchars($interaction['user_name']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Información del Sistema -->
            <div class="info-card" style="background: #f9fafb;">
                <h3 class="info-card-title">
                    <i class="fas fa-info-circle"></i>
                    Información del Sistema
                </h3>
                <div class="info-item" style="margin-bottom: 12px;">
                    <span class="info-label">Fecha de Registro</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($client['created_at'])); ?></span>
                </div>
                <div class="info-item" style="margin-bottom: 12px;">
                    <span class="info-label">Última Modificación</span>
                    <span class="info-value">
                        <?php echo $client['updated_at'] ? date('d/m/Y H:i', strtotime($client['updated_at'])) : 'Nunca'; ?>
                    </span>
                </div>
                <?php if ($client['last_contact_date']): ?>
                <div class="info-item" style="margin-bottom: 12px;">
                    <span class="info-label">Último Contacto</span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($client['last_contact_date'])); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="info-label">Acceso al Portal</span>
                    <span class="info-value">
                        <?php if ($client['portal_active']): ?>
                            <span style="color: var(--success); font-weight: 600;">
                                <i class="fas fa-check-circle"></i> Activo
                            </span>
                            <?php if ($client['last_login']): ?>
                            <br><small style="color: #9ca3af;">Último acceso: <?php echo date('d/m/Y H:i', strtotime($client['last_login'])); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #9ca3af;">
                                <i class="fas fa-times-circle"></i> Desactivado
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
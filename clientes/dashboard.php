<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';
require_once 'includes/functions.php';

session_start();
requireClientLogin();

$pageTitle = 'Dashboard';
$currentClient = getCurrentClient();

// ============================================================
// OBTENER TODAS LAS ESTADÍSTICAS Y DATOS
// ============================================================

// 1. ESTADÍSTICAS GENERALES
$stats = db()->selectOne("
    SELECT 
        COUNT(DISTINCT st.id) as total_properties,
        SUM(CASE WHEN st.transaction_type = 'sale' AND st.status IN ('completed', 'in_progress') THEN 1 ELSE 0 END) as properties_purchased,
        SUM(CASE WHEN st.transaction_type IN ('rent', 'vacation_rent') AND st.status IN ('completed', 'in_progress') THEN 1 ELSE 0 END) as properties_rented,
        (SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'paid') as invoices_paid,
        (SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status IN ('pending', 'partial')) as invoices_pending,
        (SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'overdue') as invoices_overdue,
        (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = ? AND status = 'paid') as total_paid,
        (SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE client_id = ? AND status IN ('pending', 'partial', 'overdue')) as total_due,
        (SELECT COUNT(*) FROM client_property_documents WHERE client_id = ?) as total_documents,
        (SELECT COUNT(*) FROM client_property_comments WHERE client_id = ? AND sender_type = 'client') as total_messages_sent,
        (SELECT COUNT(*) FROM client_property_comments WHERE client_id = ? AND sender_type = 'admin' AND is_read = 0) as unread_messages
    FROM sales_transactions st
    WHERE st.client_id = ?
", [
    $currentClient['id'], $currentClient['id'], $currentClient['id'], 
    $currentClient['id'], $currentClient['id'], $currentClient['id'],
    $currentClient['id'], $currentClient['id'], $currentClient['id']
]);

// 2. PROPIEDADES DEL CLIENTE (Compradas y Alquiladas) - CONSULTA CORREGIDA
$properties = db()->select("
    SELECT 
        st.id as transaction_id,
        st.transaction_code,
        st.transaction_type,
        st.status as transaction_status,
        st.sale_price,
        st.monthly_payment,
        st.contract_date,
        st.closing_date,
        st.move_in_date,
        st.rent_end_date,
        st.rent_duration_months,
        st.payment_status,
        st.balance_pending,
        p.id as property_id,
        p.reference as property_reference,
        p.title as property_title,
        p.address as property_address,
        p.city as property_city,
        p.state_province,
        p.bedrooms,
        p.bathrooms,
        p.built_area,
        p.garage_spaces,
        pt.name as property_type,
        (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as property_image,
        CONCAT(u.first_name, ' ', u.last_name) as agent_name,
        u.email as agent_email,
        u.phone as agent_phone,
        u.profile_picture as agent_picture,
        (SELECT COUNT(*) FROM client_property_documents WHERE client_id = st.client_id AND property_id = p.id) as document_count,
        (SELECT COUNT(*) FROM client_property_comments WHERE client_id = st.client_id AND property_id = p.id AND sender_type = 'admin' AND is_read = 0) as unread_comment_count,
        (SELECT invoice_number FROM invoices WHERE transaction_id = st.id ORDER BY invoice_date DESC LIMIT 1) as last_invoice,
        (SELECT status FROM invoices WHERE transaction_id = st.id ORDER BY invoice_date DESC LIMIT 1) as last_invoice_status
    FROM sales_transactions st
    INNER JOIN properties p ON st.property_id = p.id
    LEFT JOIN property_types pt ON p.property_type_id = pt.id
    LEFT JOIN users u ON st.agent_id = u.id
    WHERE st.client_id = ? 
    AND st.status IN ('completed', 'in_progress')
    ORDER BY 
        CASE 
            WHEN st.transaction_type IN ('rent', 'vacation_rent') THEN 1 
            WHEN st.transaction_type = 'sale' THEN 2 
        END,
        st.created_at DESC
", [$currentClient['id']]);

// 3. FACTURAS RECIENTES (últimas 5)
$recentInvoices = db()->select("
    SELECT 
        i.*,
        p.reference as property_reference,
        p.title as property_title,
        st.transaction_type
    FROM invoices i
    INNER JOIN properties p ON i.property_id = p.id
    INNER JOIN sales_transactions st ON i.transaction_id = st.id
    WHERE i.client_id = ?
    ORDER BY i.invoice_date DESC, i.id DESC
    LIMIT 5
", [$currentClient['id']]);

// 4. PRÓXIMOS PAGOS (Facturas pendientes que vencen pronto)
$upcomingPayments = db()->select("
    SELECT 
        i.*,
        p.reference as property_reference,
        p.title as property_title,
        st.transaction_type,
        DATEDIFF(i.due_date, CURDATE()) as days_until_due
    FROM invoices i
    INNER JOIN properties p ON i.property_id = p.id
    INNER JOIN sales_transactions st ON i.transaction_id = st.id
    WHERE i.client_id = ?
    AND i.status IN ('pending', 'partial', 'overdue')
    ORDER BY i.due_date ASC
    LIMIT 3
", [$currentClient['id']]);

// 5. MENSAJES RECIENTES (últimos 5 comentarios)
$recentMessages = db()->select("
    SELECT 
        cpc.*,
        p.reference as property_reference,
        p.title as property_title,
        CONCAT(u.first_name, ' ', u.last_name) as admin_name,
        u.profile_picture as admin_picture
    FROM client_property_comments cpc
    INNER JOIN properties p ON cpc.property_id = p.id
    LEFT JOIN users u ON cpc.user_id = u.id
    WHERE cpc.client_id = ?
    ORDER BY cpc.created_at DESC
    LIMIT 5
", [$currentClient['id']]);

// 6. DOCUMENTOS RECIENTES (últimos 4)
$recentDocuments = db()->select("
    SELECT 
        cpd.*,
        p.reference as property_reference,
        p.title as property_title
    FROM client_property_documents cpd
    INNER JOIN properties p ON cpd.property_id = p.id
    WHERE cpd.client_id = ?
    AND cpd.is_visible_to_client = 1
    ORDER BY cpd.upload_date DESC
    LIMIT 4
", [$currentClient['id']]);

// 7. ACTIVIDAD RECIENTE (Timeline de eventos)
$recentActivity = [];

// Agregar facturas pagadas recientes
$paidInvoices = db()->select("
    SELECT 'invoice_paid' as type, i.paid_date as date, 
           CONCAT('Factura ', i.invoice_number, ' pagada') as description,
           i.total_amount as amount
    FROM invoices i
    WHERE i.client_id = ? AND i.status = 'paid' AND i.paid_date IS NOT NULL
    ORDER BY i.paid_date DESC
    LIMIT 3
", [$currentClient['id']]);
$recentActivity = array_merge($recentActivity, $paidInvoices);

// Agregar documentos subidos
$uploadedDocs = db()->select("
    SELECT 'document_uploaded' as type, cpd.upload_date as date,
           CONCAT('Documento subido: ', cpd.document_name) as description,
           NULL as amount
    FROM client_property_documents cpd
    WHERE cpd.client_id = ? AND cpd.uploaded_by_client = 1
    ORDER BY cpd.upload_date DESC
    LIMIT 3
", [$currentClient['id']]);
$recentActivity = array_merge($recentActivity, $uploadedDocs);

// Agregar mensajes enviados
$sentMessages = db()->select("
    SELECT 'message_sent' as type, cpc.created_at as date,
           CONCAT('Mensaje enviado sobre ', p.reference) as description,
           NULL as amount
    FROM client_property_comments cpc
    INNER JOIN properties p ON cpc.property_id = p.id
    WHERE cpc.client_id = ? AND cpc.sender_type = 'client'
    ORDER BY cpc.created_at DESC
    LIMIT 3
", [$currentClient['id']]);
$recentActivity = array_merge($recentActivity, $sentMessages);

// Ordenar actividad por fecha
usort($recentActivity, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentActivity = array_slice($recentActivity, 0, 5);

// 8. ALERTAS IMPORTANTES
$alerts = [];

// Facturas vencidas
if ($stats['invoices_overdue'] > 0) {
    $overdueAmount = db()->selectOne("
        SELECT COALESCE(SUM(balance_due), 0) as amount
        FROM invoices 
        WHERE client_id = ? AND status = 'overdue'
    ", [$currentClient['id']])['amount'];
    
    $alerts[] = [
        'type' => 'danger',
        'icon' => 'fa-exclamation-triangle',
        'title' => 'Facturas Vencidas',
        'message' => "Tienes {$stats['invoices_overdue']} factura(s) vencida(s) por un total de $" . number_format($overdueAmount, 2),
        'action_url' => '/clientes/facturas.php?status=overdue',
        'action_text' => 'Ver Facturas'
    ];
}

// Facturas por vencer pronto (próximos 7 días)
$dueSoon = db()->selectOne("
    SELECT COUNT(*) as count, COALESCE(SUM(balance_due), 0) as amount
    FROM invoices 
    WHERE client_id = ? 
    AND status IN ('pending', 'partial')
    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
", [$currentClient['id']]);

if ($dueSoon['count'] > 0) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-clock',
        'title' => 'Pagos Próximos',
        'message' => "Tienes {$dueSoon['count']} pago(s) que vencen en los próximos 7 días por $" . number_format($dueSoon['amount'], 2),
        'action_url' => '/clientes/facturas.php?status=pending',
        'action_text' => 'Ver Pagos'
    ];
}

// Mensajes sin leer
if ($stats['unread_messages'] > 0) {
    $alerts[] = [
        'type' => 'info',
        'icon' => 'fa-envelope',
        'title' => 'Mensajes Nuevos',
        'message' => "Tienes {$stats['unread_messages']} mensaje(s) sin leer de tu agente",
        'action_url' => '/clientes/propiedades.php',
        'action_text' => 'Ver Mensajes'
    ];
}

// Contratos de alquiler próximos a vencer (30 días)
$expiringRentals = db()->select("
    SELECT p.title, p.reference, st.rent_end_date,
           DATEDIFF(st.rent_end_date, CURDATE()) as days_left
    FROM sales_transactions st
    INNER JOIN properties p ON st.property_id = p.id
    WHERE st.client_id = ?
    AND st.transaction_type IN ('rent', 'vacation_rent')
    AND st.status IN ('completed', 'in_progress')
    AND st.rent_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
", [$currentClient['id']]);

if (!empty($expiringRentals)) {
    foreach ($expiringRentals as $rental) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'fa-calendar-times',
            'title' => 'Contrato por Vencer',
            'message' => "El contrato de {$rental['reference']} vence en {$rental['days_left']} días",
            'action_url' => '/clientes/propiedades.php',
            'action_text' => 'Ver Detalles'
        ];
    }
}

include 'includes/header.php';
?>

<style>
    .welcome-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }
    
    .stat-card-dashboard {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
        height: 100%;
        border-left: 4px solid;
    }
    
    .stat-card-dashboard:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .property-card-dashboard {
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: 100%;
    }
    
    .property-card-dashboard:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .property-status-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 2;
    }
    
    .property-type-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 2;
    }
    
    .alert-card {
        border-left-width: 4px;
        border-left-style: solid;
    }
    
    .timeline-item {
        position: relative;
        padding-left: 2.5rem;
        padding-bottom: 1.5rem;
    }
    
    .timeline-item:last-child {
        padding-bottom: 0;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 0.5rem;
        top: 0.5rem;
        bottom: -1rem;
        width: 2px;
        background: #e5e7eb;
    }
    
    .timeline-item:last-child::before {
        display: none;
    }
    
    .timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
        background: white;
        border: 2px solid;
        z-index: 1;
    }
    
    .quick-action-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .quick-action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .quick-action-card i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
    }
</style>

<!-- Welcome Section -->
<div class="welcome-section">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-hand-wave me-2"></i>
                ¡Bienvenido de nuevo, <?php echo htmlspecialchars($currentClient['first_name']); ?>!
            </h1>
            <p class="mb-3 opacity-90">
                <?php
                $hour = date('G');
                if ($hour < 12) {
                    echo "Buenos días. ";
                } elseif ($hour < 18) {
                    echo "Buenas tardes. ";
                } else {
                    echo "Buenas noches. ";
                }
                ?>
                Aquí está el resumen de tu cuenta.
            </p>
            <div class="d-flex gap-3 flex-wrap">
                <div>
                    <small class="opacity-75">Cliente desde:</small>
                    <div><strong><?php echo date('d/m/Y', strtotime($currentClient['created_at'])); ?></strong></div>
                </div>
                <?php if ($currentClient['agent_name']): ?>
                <div>
                    <small class="opacity-75">Tu agente:</small>
                    <div><strong><?php echo htmlspecialchars($currentClient['agent_name']); ?></strong></div>
                </div>
                <?php endif; ?>
                <?php if ($currentClient['last_login']): ?>
                <div>
                    <small class="opacity-75">Último acceso:</small>
                    <div><strong><?php echo date('d/m/Y H:i', strtotime($currentClient['last_login'])); ?></strong></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex flex-column gap-2">
                <a href="<?php echo url('clientes/propiedades.php'); ?>" class="btn btn-light btn-lg">
                    <i class="fas fa-building me-2"></i> Ver Propiedades
                </a>
                <a href="<?php echo url('clientes/facturas.php'); ?>" class="btn btn-outline-light">
                    <i class="fas fa-file-invoice-dollar me-2"></i> Ver Facturas
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Alerts Section -->
<?php if (!empty($alerts)): ?>
<div class="row mb-4">
    <div class="col-12">
        <h5 class="mb-3"><i class="fas fa-bell me-2 text-warning"></i> Alertas Importantes</h5>
        <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show alert-card mb-3" role="alert">
                <div class="d-flex align-items-start">
                    <i class="fas <?php echo $alert['icon']; ?> fa-2x me-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1"><?php echo $alert['title']; ?></h6>
                        <p class="mb-2"><?php echo $alert['message']; ?></p>
                        <a href="<?php echo $alert['action_url']; ?>" class="btn btn-sm btn-<?php echo $alert['type']; ?>">
                            <?php echo $alert['action_text']; ?> <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card-dashboard" style="border-left-color: #3b82f6;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Total Propiedades</small>
                    <h2 class="mb-0 text-primary"><?php echo $stats['total_properties'] ?? 0; ?></h2>
                </div>
                <i class="fas fa-building fa-2x text-primary opacity-25"></i>
            </div>
            <div class="d-flex gap-2 mt-2">
                <?php if ($stats['properties_purchased'] > 0): ?>
                    <span class="badge bg-success"><?php echo $stats['properties_purchased']; ?> Compradas</span>
                <?php endif; ?>
                <?php if ($stats['properties_rented'] > 0): ?>
                    <span class="badge bg-info"><?php echo $stats['properties_rented']; ?> Alquiladas</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-dashboard" style="border-left-color: #10b981;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Total Pagado</small>
                    <h3 class="mb-0 text-success">$<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h3>
                </div>
                <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
            </div>
            <small class="text-muted">
                <?php echo $stats['invoices_paid'] ?? 0; ?> factura(s) pagadas
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-dashboard" style="border-left-color: #f59e0b;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Saldo Pendiente</small>
                    <h3 class="mb-0 text-warning">$<?php echo number_format($stats['total_due'] ?? 0, 2); ?></h3>
                </div>
                <i class="fas fa-clock fa-2x text-warning opacity-25"></i>
            </div>
            <small class="text-muted">
                <?php echo ($stats['invoices_pending'] ?? 0) + ($stats['invoices_overdue'] ?? 0); ?> factura(s) pendientes
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-dashboard" style="border-left-color: #06b6d4;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Documentos</small>
                    <h2 class="mb-0 text-info"><?php echo $stats['total_documents'] ?? 0; ?></h2>
                </div>
                <i class="fas fa-folder-open fa-2x text-info opacity-25"></i>
            </div>
            <?php if ($stats['unread_messages'] > 0): ?>
                <span class="badge bg-danger"><?php echo $stats['unread_messages']; ?> mensajes nuevos</span>
            <?php else: ?>
                <small class="text-muted">Todo al día</small>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row">
    <!-- Left Column - Properties & Payments -->
    <div class="col-lg-8">
        
        <!-- Properties Section -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-building me-2 text-primary"></i> Mis Propiedades</h5>
                <a href="<?php echo url('clientes/propiedades.php'); ?>" class="btn btn-sm btn-outline-primary">
                    Ver Todas (<?php echo count($properties); ?>)
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($properties)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-home fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No tienes propiedades asignadas</h5>
                        <p class="text-muted">Tus propiedades aparecerán aquí una vez que completes una transacción.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach (array_slice($properties, 0, 4) as $property): ?>
                            <div class="col-md-6">
                                <div class="card property-card-dashboard">
                                    <div class="position-relative">
                                        <?php if ($property['property_image']): ?>
                                            <img src="<?php echo htmlspecialchars($property['property_image']); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($property['property_title']); ?>"
                                                 style="height: 180px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="property-status-badge">
                                            <?php echo getTransactionTypeBadge($property['transaction_type']); ?>
                                        </div>
                                        
                                        <?php if ($property['property_type']): ?>
                                        <div class="property-type-badge">
                                            <span class="badge bg-dark"><?php echo htmlspecialchars($property['property_type']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0"><?php echo htmlspecialchars($property['property_title']); ?></h6>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($property['property_reference']); ?></span>
                                        </div>
                                        
                                        <p class="text-muted small mb-2">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($property['property_address']); ?>, 
                                            <?php echo htmlspecialchars($property['property_city']); ?>
                                        </p>
                                        
                                        <!-- Property Details -->
                                        <div class="row g-2 mb-3">
                                            <?php if ($property['bedrooms']): ?>
                                            <div class="col-4">
                                                <small class="text-muted"><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?></small>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($property['bathrooms']): ?>
                                            <div class="col-4">
                                                <small class="text-muted"><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?></small>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($property['built_area']): ?>
                                            <div class="col-4">
                                                <small class="text-muted"><i class="fas fa-ruler-combined"></i> <?php echo number_format($property['built_area']); ?>m²</small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <small class="text-muted d-block">
                                                    <?php echo $property['transaction_type'] == 'sale' ? 'Precio' : 'Renta Mensual'; ?>
                                                </small>
                                                <strong class="text-primary fs-5">
                                                    $<?php echo number_format($property['transaction_type'] == 'sale' ? $property['sale_price'] : $property['monthly_payment'], 2); ?>
                                                </strong>
                                            </div>
                                            <?php if ($property['transaction_type'] != 'sale' && $property['rent_end_date']): ?>
                                            <div class="text-end">
                                                <small class="text-muted d-block">Vence</small>
                                                <small><strong><?php echo date('d/m/Y', strtotime($property['rent_end_date'])); ?></strong></small>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Quick Stats -->
                                        <div class="d-flex gap-2 mb-3">
                                            <?php if ($property['document_count'] > 0): ?>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-file"></i> <?php echo $property['document_count']; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($property['unread_comment_count'] > 0): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-comment"></i> <?php echo $property['unread_comment_count']; ?> nuevos
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($property['last_invoice_status']): ?>
                                                <?php
                                                $invoiceStatusColors = [
                                                    'paid' => 'success',
                                                    'pending' => 'warning',
                                                    'overdue' => 'danger',
                                                    'partial' => 'info'
                                                ];
                                                $statusColor = $invoiceStatusColors[$property['last_invoice_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $statusColor; ?>">
                                                    <i class="fas fa-file-invoice"></i> 
                                                    <?php echo ucfirst($property['last_invoice_status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <a href="<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $property['property_id']; ?>" 
                                           class="btn btn-primary w-100">
                                            <i class="fas fa-eye me-2"></i> Ver Detalles Completos
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($properties) > 4): ?>
                    <div class="text-center mt-3">
                        <a href="<?php echo url('clientes/propiedades.php'); ?>" class="btn btn-outline-primary">
                            Ver todas las <?php echo count($properties); ?> propiedades <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Payments -->
        <?php if (!empty($upcomingPayments)): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-calendar-check me-2 text-warning"></i> Próximos Pagos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Factura</th>
                                <th>Propiedad</th>
                                <th>Monto</th>
                                <th>Vence</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingPayments as $payment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($payment['invoice_number']); ?></strong></td>
                                <td>
                                    <div><?php echo htmlspecialchars($payment['property_title']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($payment['property_reference']); ?></small>
                                </td>
                                <td><strong class="text-danger">$<?php echo number_format($payment['balance_due'], 2); ?></strong></td>
                                <td>
                                    <?php
                                    $daysUntilDue = $payment['days_until_due'];
                                    $dueClass = '';
                                    if ($daysUntilDue < 0) {
                                        $dueClass = 'text-danger fw-bold';
                                        $dueText = 'Vencida (' . abs($daysUntilDue) . ' días)';
                                    } elseif ($daysUntilDue <= 3) {
                                        $dueClass = 'text-danger';
                                        $dueText = 'En ' . $daysUntilDue . ' día(s)';
                                    } elseif ($daysUntilDue <= 7) {
                                        $dueClass = 'text-warning';
                                        $dueText = 'En ' . $daysUntilDue . ' días';
                                    } else {
                                        $dueText = date('d/m/Y', strtotime($payment['due_date']));
                                    }
                                    ?>
                                    <span class="<?php echo $dueClass; ?>"><?php echo $dueText; ?></span>
                                </td>
                                <td><?php echo getInvoiceStatusBadge($payment['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo url('clientes/facturas.php'); ?>" class="btn btn-outline-primary">
                        Ver Todas las Facturas <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Documents -->
        <?php if (!empty($recentDocuments)): ?>
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-folder-open me-2 text-info"></i> Documentos Recientes</h5>
                <a href="<?php echo url('clientes/documentos.php'); ?>" class="btn btn-sm btn-outline-info">Ver Todos</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($recentDocuments as $doc): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center p-2 border rounded">
                                <div class="me-3">
                                    <i class="fas <?php echo getFileIcon($doc['file_type']); ?> fa-2x"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($doc['property_reference']); ?> • 
                                        <?php echo formatFileSize($doc['file_size']); ?>
                                    </small>
                                </div>
                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   target="_blank">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Right Column - Sidebar -->
    <div class="col-lg-4">
        
        <!-- Agent Info -->
        <?php if ($currentClient['agent_name']): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user-tie me-2 text-info"></i> Tu Agente</h5>
            </div>
            <div class="card-body text-center">
                <?php if ($currentClient['agent_picture']): ?>
                    <img src="<?php echo htmlspecialchars($currentClient['agent_picture']); ?>" 
                         class="rounded-circle mb-3" 
                         style="width: 80px; height: 80px; object-fit: cover;" 
                         alt="Agente">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-4x text-muted mb-3"></i>
                <?php endif; ?>
                <h6 class="mb-2"><?php echo htmlspecialchars($currentClient['agent_name']); ?></h6>
                <?php if ($currentClient['agent_email']): ?>
                    <p class="mb-1 small">
                        <i class="fas fa-envelope me-1"></i>
                        <a href="mailto:<?php echo htmlspecialchars($currentClient['agent_email']); ?>">
                            <?php echo htmlspecialchars($currentClient['agent_email']); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if ($currentClient['agent_phone']): ?>
                    <p class="mb-3 small">
                        <i class="fas fa-phone me-1"></i>
                        <a href="tel:<?php echo htmlspecialchars($currentClient['agent_phone']); ?>">
                            <?php echo htmlspecialchars($currentClient['agent_phone']); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <a href="mailto:<?php echo htmlspecialchars($currentClient['agent_email']); ?>" class="btn btn-sm btn-primary w-100">
                    <i class="fas fa-envelope me-2"></i> Contactar
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-bolt me-2 text-warning"></i> Acciones Rápidas</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="<?php echo url('clientes/propiedades.php'); ?>" class="quick-action-card text-decoration-none">
                            <i class="fas fa-building text-primary"></i>
                            <div class="small fw-bold">Ver Propiedades</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo url('clientes/facturas.php'); ?>" class="quick-action-card text-decoration-none">
                            <i class="fas fa-file-invoice-dollar text-success"></i>
                            <div class="small fw-bold">Mis Facturas</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo url('clientes/documentos.php'); ?>" class="quick-action-card text-decoration-none">
                            <i class="fas fa-folder-open text-info"></i>
                            <div class="small fw-bold">Documentos</div>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo url('clientes/perfil.php'); ?>" class="quick-action-card text-decoration-none">
                            <i class="fas fa-user-cog text-warning"></i>
                            <div class="small fw-bold">Mi Perfil</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Messages -->
        <?php if (!empty($recentMessages)): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-comments me-2 text-primary"></i> Mensajes Recientes</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($recentMessages, 0, 4) as $msg): ?>
                        <a href="<?php echo url('clientes/propiedad_detalle.php'); ?>?id=<?php echo $msg['property_id']; ?>#mensajes" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex align-items-start">
                                <div class="me-2">
                                    <?php if ($msg['sender_type'] == 'admin'): ?>
                                        <i class="fas fa-user-tie text-primary"></i>
                                    <?php else: ?>
                                        <i class="fas fa-user text-secondary"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <small class="fw-bold">
                                            <?php echo $msg['sender_type'] == 'admin' ? htmlspecialchars($msg['admin_name']) : 'Tú'; ?>
                                        </small>
                                        <small class="text-muted">
                                            <?php echo date('d/m H:i', strtotime($msg['created_at'])); ?>
                                        </small>
                                    </div>
                                    <small class="text-muted d-block mb-1"><?php echo htmlspecialchars($msg['property_reference']); ?></small>
                                    <small><?php echo htmlspecialchars(substr($msg['message'], 0, 60)) . (strlen($msg['message']) > 60 ? '...' : ''); ?></small>
                                    <?php if ($msg['sender_type'] == 'admin' && !$msg['is_read']): ?>
                                        <span class="badge bg-danger ms-2">Nuevo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Activity Timeline -->
        <?php if (!empty($recentActivity)): ?>
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-history me-2 text-secondary"></i> Actividad Reciente</h5>
            </div>
            <div class="card-body">
                <?php foreach ($recentActivity as $activity): ?>
                    <div class="timeline-item">
                        <?php
                        $iconClass = '';
                        $iconColor = '';
                        switch ($activity['type']) {
                            case 'invoice_paid':
                                $iconClass = 'fa-check-circle';
                                $iconColor = 'border-success text-success';
                                break;
                            case 'document_uploaded':
                                $iconClass = 'fa-file-upload';
                                $iconColor = 'border-info text-info';
                                break;
                            case 'message_sent':
                                $iconClass = 'fa-comment';
                                $iconColor = 'border-primary text-primary';
                                break;
                            default:
                                $iconClass = 'fa-circle';
                                $iconColor = 'border-secondary text-secondary';
                        }
                        ?>
                        <div class="timeline-icon <?php echo $iconColor; ?>">
                            <i class="fas <?php echo $iconClass; ?>"></i>
                        </div>
                        <div>
                            <small class="text-muted d-block"><?php echo date('d/m/Y H:i', strtotime($activity['date'])); ?></small>
                            <div><?php echo htmlspecialchars($activity['description']); ?></div>
                            <?php if (isset($activity['amount']) && $activity['amount']): ?>
                                <small class="text-success fw-bold">$<?php echo number_format($activity['amount'], 2); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>
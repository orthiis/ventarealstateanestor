<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';
require_once 'includes/functions.php';

session_start();
requireClientLogin();

$pageTitle = 'Mis Facturas';
$currentClient = getCurrentClient();

// Filtros
$filterStatus = $_GET['status'] ?? 'all';
$filterYear = $_GET['year'] ?? date('Y');
$filterType = $_GET['type'] ?? 'all';

// Construir query con filtros
$where = ['i.client_id = ?'];
$params = [$currentClient['id']];

if ($filterStatus !== 'all') {
    $where[] = 'i.status = ?';
    $params[] = $filterStatus;
}

if ($filterYear !== 'all') {
    $where[] = 'YEAR(i.invoice_date) = ?';
    $params[] = $filterYear;
}

if ($filterType !== 'all') {
    $where[] = 'i.invoice_type = ?';
    $params[] = $filterType;
}

$whereClause = implode(' AND ', $where);

// Obtener facturas
$invoices = db()->select("
    SELECT 
        i.*,
        p.reference as property_reference,
        p.title as property_title,
        p.address as property_address,
        st.transaction_code,
        st.transaction_type
    FROM invoices i
    INNER JOIN properties p ON i.property_id = p.id
    INNER JOIN sales_transactions st ON i.transaction_id = st.id
    WHERE {$whereClause}
    ORDER BY i.invoice_date DESC, i.id DESC
", $params);

// Obtener años disponibles para el filtro
$years = db()->select("
    SELECT DISTINCT YEAR(invoice_date) as year 
    FROM invoices 
    WHERE client_id = ? 
    ORDER BY year DESC
", [$currentClient['id']]);

// Calcular totales
$totalPaid = 0;
$totalPending = 0;
$totalOverdue = 0;
$totalAmount = 0;

foreach ($invoices as $invoice) {
    $totalAmount += $invoice['total_amount'];
    if ($invoice['status'] == 'paid') {
        $totalPaid += $invoice['total_amount'];
    } elseif ($invoice['status'] == 'overdue') {
        $totalOverdue += $invoice['balance_due'];
    } elseif ($invoice['status'] == 'pending' || $invoice['status'] == 'partial') {
        $totalPending += $invoice['balance_due'];
    }
}

// Estadísticas generales
$stats = db()->selectOne("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status IN ('pending', 'partial') THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_paid_amount,
        SUM(CASE WHEN status IN ('pending', 'partial', 'overdue') THEN balance_due ELSE 0 END) as total_due_amount
    FROM invoices 
    WHERE client_id = ?
", [$currentClient['id']]);

include 'includes/header.php';
?>

<style>
    .stat-card-facturas {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
        height: 100%;
        border-left: 4px solid;
    }
    
    .stat-card-facturas:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .invoice-row {
        transition: all 0.3s;
    }
    
    .invoice-row:hover {
        background-color: #f8fafc;
        cursor: pointer;
    }
    
    .filter-card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .invoice-detail-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 12px 12px 0 0;
    }
    
    .payment-timeline-item {
        position: relative;
        padding-left: 2rem;
        padding-bottom: 1.5rem;
    }
    
    .payment-timeline-item:last-child {
        padding-bottom: 0;
    }
    
    .payment-timeline-item::before {
        content: '';
        position: absolute;
        left: 0.5rem;
        top: 0.5rem;
        bottom: -1rem;
        width: 2px;
        background: #e5e7eb;
    }
    
    .payment-timeline-item:last-child::before {
        display: none;
    }
    
    .payment-timeline-icon {
        position: absolute;
        left: 0;
        top: 0;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        background: white;
        border: 2px solid #10b981;
        color: #10b981;
        z-index: 1;
    }
</style>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-file-invoice-dollar me-2"></i> Mis Facturas</h1>
            <p class="text-muted mb-0">Historial completo de pagos y facturas pendientes</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo url('clientes/dashboard.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Volver al Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card-facturas" style="border-left-color: #3b82f6;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Total Facturas</small>
                    <h2 class="mb-0 text-primary"><?php echo $stats['total_invoices'] ?? 0; ?></h2>
                </div>
                <i class="fas fa-file-invoice fa-2x text-primary opacity-25"></i>
            </div>
            <small class="text-muted">
                <?php echo $stats['paid_count'] ?? 0; ?> pagadas • 
                <?php echo ($stats['pending_count'] ?? 0) + ($stats['overdue_count'] ?? 0); ?> pendientes
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-facturas" style="border-left-color: #10b981;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Total Pagado</small>
                    <h3 class="mb-0 text-success">$<?php echo number_format($stats['total_paid_amount'] ?? 0, 2); ?></h3>
                </div>
                <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
            </div>
            <small class="text-success">
                <?php echo $stats['paid_count'] ?? 0; ?> factura(s) completadas
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-facturas" style="border-left-color: #f59e0b;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Pendiente de Pago</small>
                    <h3 class="mb-0 text-warning">$<?php echo number_format($totalPending, 2); ?></h3>
                </div>
                <i class="fas fa-clock fa-2x text-warning opacity-25"></i>
            </div>
            <small class="text-muted">
                <?php echo $stats['pending_count'] ?? 0; ?> factura(s) pendientes
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-facturas" style="border-left-color: #ef4444;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Facturas Vencidas</small>
                    <h3 class="mb-0 text-danger">$<?php echo number_format($totalOverdue, 2); ?></h3>
                </div>
                <i class="fas fa-exclamation-triangle fa-2x text-danger opacity-25"></i>
            </div>
            <small class="text-danger">
                <?php echo $stats['overdue_count'] ?? 0; ?> factura(s) atrasadas
            </small>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-card">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-bold">
                <i class="fas fa-filter me-1"></i> Estado
            </label>
            <select name="status" class="form-select">
                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>Todos los Estados</option>
                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="partial" <?php echo $filterStatus === 'partial' ? 'selected' : ''; ?>>Pago Parcial</option>
                <option value="paid" <?php echo $filterStatus === 'paid' ? 'selected' : ''; ?>>Pagada</option>
                <option value="overdue" <?php echo $filterStatus === 'overdue' ? 'selected' : ''; ?>>Vencida</option>
                <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label class="form-label fw-bold">
                <i class="fas fa-tag me-1"></i> Tipo
            </label>
            <select name="type" class="form-select">
                <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>Todos los Tipos</option>
                <option value="rent" <?php echo $filterType === 'rent' ? 'selected' : ''; ?>>Alquiler</option>
                <option value="sale" <?php echo $filterType === 'sale' ? 'selected' : ''; ?>>Venta</option>
                <option value="maintenance" <?php echo $filterType === 'maintenance' ? 'selected' : ''; ?>>Mantenimiento</option>
                <option value="other" <?php echo $filterType === 'other' ? 'selected' : ''; ?>>Otro</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label class="form-label fw-bold">
                <i class="fas fa-calendar me-1"></i> Año
            </label>
            <select name="year" class="form-select">
                <option value="all" <?php echo $filterYear === 'all' ? 'selected' : ''; ?>>Todos los Años</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo $year['year']; ?>" <?php echo $filterYear == $year['year'] ? 'selected' : ''; ?>>
                        <?php echo $year['year']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-search me-2"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            Listado de Facturas
            <?php if ($filterStatus !== 'all' || $filterYear !== 'all' || $filterType !== 'all'): ?>
                <span class="badge bg-primary"><?php echo count($invoices); ?> resultados</span>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($invoices)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">No hay facturas</h5>
                <p class="text-muted">
                    <?php if ($filterStatus !== 'all' || $filterYear !== 'all' || $filterType !== 'all'): ?>
                        No se encontraron facturas con los filtros seleccionados.
                    <?php else: ?>
                        No tienes facturas registradas aún.
                    <?php endif; ?>
                </p>
                <?php if ($filterStatus !== 'all' || $filterYear !== 'all' || $filterType !== 'all'): ?>
                    <a href="<?php echo url('clientes/facturas.php'); ?>" class="btn btn-outline-primary mt-3">
                        <i class="fas fa-times me-2"></i> Limpiar Filtros
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Número</th>
                            <th>Propiedad</th>
                            <th>Tipo</th>
                            <th>Fecha Emisión</th>
                            <th>Vencimiento</th>
                            <th class="text-end">Monto Total</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Saldo</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="invoice-row" onclick="viewInvoice(<?php echo $invoice['id']; ?>)">
                                <td>
                                    <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                    <?php if ($invoice['period_month'] && $invoice['period_year']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php 
                                            $months = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                                            echo $months[$invoice['period_month']] . ' ' . $invoice['period_year'];
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($invoice['property_title']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($invoice['property_reference']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $typeBadges = [
                                        'rent' => '<span class="badge bg-primary"><i class="fas fa-key me-1"></i>Alquiler</span>',
                                        'sale' => '<span class="badge bg-success"><i class="fas fa-shopping-cart me-1"></i>Venta</span>',
                                        'maintenance' => '<span class="badge bg-info"><i class="fas fa-tools me-1"></i>Mantenimiento</span>',
                                        'other' => '<span class="badge bg-secondary"><i class="fas fa-file me-1"></i>Otro</span>',
                                    ];
                                    echo $typeBadges[$invoice['invoice_type']] ?? '';
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></td>
                                <td>
                                    <?php 
                                    $dueDate = strtotime($invoice['due_date']);
                                    $today = strtotime(date('Y-m-d'));
                                    $isOverdue = $dueDate < $today && $invoice['status'] != 'paid';
                                    $daysUntilDue = floor(($dueDate - $today) / 86400);
                                    ?>
                                    <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo date('d/m/Y', $dueDate); ?>
                                    </span>
                                    <?php if ($invoice['status'] != 'paid'): ?>
                                        <br>
                                        <small class="<?php echo $isOverdue ? 'text-danger' : 'text-muted'; ?>">
                                            <?php 
                                            if ($isOverdue) {
                                                echo '(' . abs($daysUntilDue) . ' días vencida)';
                                            } elseif ($daysUntilDue <= 7) {
                                                echo '(Vence en ' . $daysUntilDue . ' días)';
                                            }
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong>
                                </td>
                                <td class="text-end">
                                    <span class="text-success">$<?php echo number_format($invoice['amount_paid'], 2); ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if ($invoice['balance_due'] > 0): ?>
                                        <strong class="text-danger">$<?php echo number_format($invoice['balance_due'], 2); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">$0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getInvoiceStatusBadge($invoice['status']); ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" onclick="event.stopPropagation();">
                                        <button class="btn btn-outline-primary" 
                                                onclick="viewInvoice(<?php echo $invoice['id']; ?>)" 
                                                title="Ver Detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="<?php echo url('clientes/ajax/download_invoice.php'); ?>?id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-outline-success" 
                                           title="Descargar PDF"
                                           target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Totales Filtrados:</th>
                            <th class="text-end">$<?php echo number_format($totalAmount, 2); ?></th>
                            <th class="text-end">$<?php echo number_format($totalPaid, 2); ?></th>
                            <th class="text-end">$<?php echo number_format($totalPending + $totalOverdue, 2); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Invoice Detail Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="invoiceDetail">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando detalles de la factura...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additionalScripts = <<<HTML
<script>
function viewInvoice(invoiceId) {
    const modal = new bootstrap.Modal(document.getElementById('invoiceModal'));
    modal.show();
    
    const detailDiv = document.getElementById('invoiceDetail');
    detailDiv.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3 text-muted">Cargando detalles de la factura...</p>
        </div>
    `;
    
    fetch('/clientes/ajax/get_invoice_detail.php?id=' + invoiceId)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            detailDiv.innerHTML = data.html;
        } else {
            detailDiv.innerHTML = '<div class="alert alert-danger m-4">Error al cargar la factura: ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        detailDiv.innerHTML = '<div class="alert alert-danger m-4">Error al cargar la factura. Por favor, intenta nuevamente.</div>';
    });
}

// Resaltar facturas vencidas
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.invoice-row');
    rows.forEach(row => {
        const statusBadge = row.querySelector('.badge');
        if (statusBadge && statusBadge.textContent.includes('Vencida')) {
            row.style.backgroundColor = '#fef2f2';
        }
    });
});
</script>
HTML;

include 'includes/footer.php';
?>
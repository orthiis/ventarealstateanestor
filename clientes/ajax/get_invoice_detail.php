<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../functions.php';
require_once '../includes/functions.php';

session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$currentClient = getCurrentClient();
$invoiceId = $_GET['id'] ?? 0;

try {
    // Obtener factura
    $invoice = db()->selectOne("
        SELECT i.*,
               p.reference as property_reference,
               p.title as property_title,
               p.address as property_address,
               st.transaction_code,
               st.transaction_type,
               CONCAT(u.first_name, ' ', u.last_name) as agent_name
        FROM invoices i
        INNER JOIN properties p ON i.property_id = p.id
        INNER JOIN sales_transactions st ON i.transaction_id = st.id
        LEFT JOIN users u ON i.agent_id = u.id
        WHERE i.id = ? AND i.client_id = ?
    ", [$invoiceId, $currentClient['id']]);
    
    if (!$invoice) {
        throw new Exception('Factura no encontrada');
    }
    
    // Obtener pagos de la factura
    $payments = db()->select("
        SELECT * FROM invoice_payments 
        WHERE invoice_id = ? 
        ORDER BY payment_date DESC
    ", [$invoiceId]);
    
    // Generar HTML
    ob_start();
    ?>
    <div class="invoice-detail">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-6">
                <h4 class="mb-1"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h4>
                <p class="text-muted mb-0">
                    <?php echo getInvoiceStatusBadge($invoice['status']); ?>
                </p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></p>
                <p class="mb-0"><strong>Vencimiento:</strong> <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></p>
            </div>
        </div>
        
        <hr>
        
        <!-- Property Info -->
        <div class="mb-4">
            <h6 class="text-muted mb-2">Propiedad</h6>
            <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['property_title']); ?></strong></p>
            <p class="text-muted mb-0 small">
                <?php echo htmlspecialchars($invoice['property_address']); ?><br>
                Ref: <?php echo htmlspecialchars($invoice['property_reference']); ?>
            </p>
        </div>
        
        <!-- Invoice Details -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Concepto</th>
                        <th class="text-end">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <?php 
                            if ($invoice['invoice_type'] == 'rent') {
                                echo 'Alquiler - ';
                                if ($invoice['period_month'] && $invoice['period_year']) {
                                    $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                                              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                                    echo $months[$invoice['period_month']] . ' ' . $invoice['period_year'];
                                }
                            } else {
                                echo 'Venta de Propiedad';
                            }
                            ?>
                        </td>
                        <td class="text-end">$<?php echo number_format($invoice['subtotal'], 2); ?></td>
                    </tr>
                    
                    <?php if ($invoice['discount_amount'] > 0): ?>
                    <tr>
                        <td>Descuento (<?php echo $invoice['discount_percentage']; ?>%)</td>
                        <td class="text-end text-success">-$<?php echo number_format($invoice['discount_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($invoice['tax_amount'] > 0): ?>
                    <tr>
                        <td>ITBIS (<?php echo $invoice['tax_percentage']; ?>%)</td>
                        <td class="text-end">$<?php echo number_format($invoice['tax_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($invoice['late_fee'] > 0): ?>
                    <tr>
                        <td class="text-danger">Cargo por Mora</td>
                        <td class="text-end text-danger">$<?php echo number_format($invoice['late_fee'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="table-light">
                        <th>Total</th>
                        <th class="text-end">$<?php echo number_format($invoice['total_amount'], 2); ?></th>
                    </tr>
                    
                    <?php if ($invoice['amount_paid'] > 0): ?>
                    <tr class="table-success">
                        <td>Pagado</td>
                        <td class="text-end">$<?php echo number_format($invoice['amount_paid'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($invoice['balance_due'] > 0): ?>
                    <tr class="table-warning">
                        <th>Saldo Pendiente</th>
                        <th class="text-end text-danger">$<?php echo number_format($invoice['balance_due'], 2); ?></th>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Payments History -->
        <?php if (!empty($payments)): ?>
        <div class="mb-4">
            <h6 class="text-muted mb-3">Historial de Pagos</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th class="text-end">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_reference'] ?? 'N/A'); ?></td>
                            <td class="text-end">$<?php echo number_format($payment['payment_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notes -->
        <?php if ($invoice['notes']): ?>
        <div class="alert alert-info">
            <strong>Notas:</strong><br>
            <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
        </div>
        <?php endif; ?>
        
        <!-- Agent Info -->
        <?php if ($invoice['agent_name']): ?>
        <div class="border-top pt-3 mt-3">
            <small class="text-muted">
                <strong>Agente:</strong> <?php echo htmlspecialchars($invoice['agent_name']); ?>
            </small>
        </div>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
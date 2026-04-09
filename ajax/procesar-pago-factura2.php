<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$currentUser = getCurrentUser();
$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
$paymentAmount = (float)($_POST['payment_amount'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? 'cash';
$paymentReference = trim($_POST['payment_reference'] ?? '');
$notes = trim($_POST['notes'] ?? '');

$response = [
    'success' => false,
    'message' => ''
];

try {
    if ($invoiceId === 0 || $paymentAmount <= 0) {
        throw new Exception(__('payment.invalid_data', [], 'Invalid payment data'));
    }
    
    db()->beginTransaction();
    
    // ==========================================
    // OBTENER FACTURA CON DATOS DE TRANSACCIÓN
    // ==========================================
    $invoice = db()->selectOne("
        SELECT i.*, 
               st.id as transaction_id, 
               st.balance_pending as transaction_balance,
               st.transaction_type as invoice_type,
               st.rent_payment_day as payment_day
        FROM invoices i
        INNER JOIN sales_transactions st ON i.transaction_id = st.id
        WHERE i.id = ?
    ", [$invoiceId]);
    
    if (!$invoice) {
        throw new Exception(__('invoice.not_found', [], 'Invoice not found'));
    }
    
    // Verificar que no esté pagada o cancelada
    if ($invoice['status'] === 'paid') {
        throw new Exception(__('invoice.already_paid', [], 'Invoice is already paid'));
    }
    
    if ($invoice['status'] === 'cancelled') {
        throw new Exception(__('invoice.is_cancelled', [], 'Invoice is cancelled'));
    }
    
    // Verificar que el monto no exceda el saldo
    if ($paymentAmount > $invoice['balance_due']) {
        throw new Exception(__('payment.exceeds_balance', [], 'Payment amount exceeds invoice balance'));
    }
    
    // ==========================================
    // REGISTRAR PAGO EN INVOICE_PAYMENTS
    // ==========================================
    $paymentId = db()->insert('invoice_payments', [
        'invoice_id' => $invoiceId,
        'payment_date' => $paymentDate,
        'payment_amount' => $paymentAmount,
        'payment_method' => $paymentMethod,
        'payment_reference' => $paymentReference,
        'notes' => $notes,
        'created_by' => $currentUser['id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if (!$paymentId) {
        throw new Exception(__('payment.registration_failed', [], 'Failed to register payment'));
    }
    
    // ==========================================
    // ACTUALIZAR FACTURA (INVOICES)
    // ==========================================
    $newAmountPaid = $invoice['amount_paid'] + $paymentAmount;
    $newBalance = $invoice['balance_due'] - $paymentAmount;
    
    // Determinar nuevo estado de la factura
    $newStatus = 'pending';
    if ($newBalance <= 0.01) {
        $newStatus = 'paid';
    } elseif ($newAmountPaid > 0) {
        $newStatus = 'partial';
    } elseif (strtotime($invoice['due_date']) < time()) {
        $newStatus = 'overdue';
    }
    
    $updateData = [
        'amount_paid' => $newAmountPaid,
        'balance_due' => max(0, $newBalance),
        'status' => $newStatus,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Si la factura está completamente pagada, registrar detalles del pago
    if ($newStatus === 'paid') {
        $updateData['paid_date'] = $paymentDate;
        $updateData['payment_method'] = $paymentMethod;
        $updateData['payment_reference'] = $paymentReference;
    }
    
    db()->update('invoices', $updateData, ['id' => $invoiceId]);
    
    // ==========================================
    // ACTUALIZAR SALES_TRANSACTIONS
    // ==========================================
    $transactionBalance = $invoice['transaction_balance'] - $paymentAmount;
    $transactionStatus = ($transactionBalance <= 0.01) ? 'paid' : 'partial';
    
    // Preparar datos de actualización para la transacción
    $transactionUpdate = [
        'balance_pending' => max(0, $transactionBalance),
        'payment_status' => $transactionStatus,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Si la factura está pagada completamente
    if ($newStatus === 'paid') {
        $transactionUpdate['has_pending_invoice'] = 0;
        
        // Diferenciar entre VENTA y ALQUILER
        if ($invoice['invoice_type'] === 'rent' || $invoice['invoice_type'] === 'vacation_rent') {
            // ===== PARA ALQUILERES =====
            // Calcular próxima fecha de factura
            $paymentDay = $invoice['payment_day'] ?? 25;
            $nextMonth = new DateTime($paymentDate);
            $nextMonth->modify('+1 month');
            
            // Ajustar si el día de pago es mayor que los días del mes
            $daysInNextMonth = (int)$nextMonth->format('t');
            $adjustedDay = min($paymentDay, $daysInNextMonth);
            $nextMonth->setDate(
                (int)$nextMonth->format('Y'),
                (int)$nextMonth->format('m'),
                $adjustedDay
            );
            
            $transactionUpdate['next_invoice_date'] = $nextMonth->format('Y-m-d');
            $transactionUpdate['last_invoice_date'] = $invoice['invoice_date'];
            
            // Para alquileres, si todas las facturas están pagadas, payment_status vuelve a pending
            // para permitir la generación de la siguiente factura mensual
            $transactionUpdate['payment_status'] = 'pending';
            
        } else {
            // ===== PARA VENTAS =====
            // Si el saldo de la transacción es 0, la venta está completamente pagada
            if ($transactionBalance <= 0.01) {
                $transactionUpdate['payment_status'] = 'paid';
                // Asegurar que el status sea 'completed' si la venta está totalmente pagada
                $transactionUpdate['status'] = 'completed';
            }
        }
    } else if ($newStatus === 'partial') {
        // Si el pago es parcial
        $transactionUpdate['has_pending_invoice'] = 1;
        $transactionUpdate['payment_status'] = 'partial';
    }
    
    // Ejecutar actualización de sales_transactions
    db()->update('sales_transactions', $transactionUpdate, ['id' => $invoice['transaction_id']]);
    
    // Commit de la transacción
    db()->commit();
    
    $response['success'] = true;
    $response['message'] = __('payment.registered_successfully', [], 'Payment registered successfully');
    $response['new_balance'] = $newBalance;
    $response['new_status'] = $newStatus;
    $response['transaction_status'] = $transactionStatus;
    
} catch (Exception $e) {
    db()->rollBack();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$currentUser = getCurrentUser();
$transactionId = (int)($_POST['transaction_id'] ?? 0);
$invoiceAmount = (float)($_POST['invoice_amount'] ?? 0);
$dueDays = (int)($_POST['due_days'] ?? 30);
$notes = trim($_POST['notes'] ?? '');

$response = [
    'success' => false,
    'message' => '',
    'invoice_id' => null
];

try {
    if ($transactionId === 0 || $invoiceAmount <= 0) {
        throw new Exception('Invalid data provided');
    }
    
    db()->beginTransaction();
    
    // Obtener transacción
    $transaction = db()->selectOne("
        SELECT st.*,
               CONCAT(c.first_name, ' ', c.last_name) as client_name,
               c.email as client_email,
               p.title as property_title,
               p.reference as property_reference
        FROM sales_transactions st
        INNER JOIN clients c ON st.client_id = c.id
        INNER JOIN properties p ON st.property_id = p.id
        WHERE st.id = ? AND st.transaction_type = 'sale'
    ", [$transactionId]);
    
    if (!$transaction) {
        throw new Exception('Transaction not found');
    }
    
    // Verificar que el monto no exceda el saldo pendiente
    if ($invoiceAmount > $transaction['balance_pending']) {
        throw new Exception('Invoice amount exceeds pending balance: $' . number_format($transaction['balance_pending'], 2));
    }
    
    // Calcular fechas
    $today = new DateTime();
    $invoiceDate = $today->format('Y-m-d');
    
    $dueDateTime = clone $today;
    $dueDateTime->modify("+{$dueDays} days");
    $dueDate = $dueDateTime->format('Y-m-d');
    
    // Calcular montos
    $subtotal = $invoiceAmount;
    $taxPercentage = 18.00; // ITBIS
    $taxAmount = $subtotal * ($taxPercentage / 100);
    $total = $subtotal + $taxAmount;
    
    // Generar número de factura
    $currentYear = date('Y');
    $lastInvoiceNumber = db()->selectValue("
        SELECT MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)) 
        FROM invoices 
        WHERE invoice_number LIKE ?
    ", ["INV-{$currentYear}-%"]) ?: 0;
    
    $invoiceNumber = 'INV-' . $currentYear . '-' . str_pad($lastInvoiceNumber + 1, 4, '0', STR_PAD_LEFT);
    
    // Crear factura
    $invoiceId = db()->insert('invoices', [
        'invoice_number' => $invoiceNumber,
        'client_id' => $transaction['client_id'],
        'property_id' => $transaction['property_id'],
        'transaction_id' => $transaction['id'],
        'agent_id' => $transaction['agent_id'],
        'invoice_type' => 'sale',
        'invoice_date' => $invoiceDate,
        'due_date' => $dueDate,
        'subtotal' => $subtotal,
        'tax_percentage' => $taxPercentage,
        'tax_amount' => $taxAmount,
        'total_amount' => $total,
        'balance_due' => $total,
        'amount_paid' => 0,
        'status' => 'pending',
        'notes' => $notes,
        'auto_generated' => 0,
        'created_by' => $currentUser['id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if (!$invoiceId) {
        throw new Exception('Failed to create invoice');
    }
    
    // Actualizar transacción
    db()->update('sales_transactions', [
        'has_pending_invoice' => 1,
        'last_invoice_date' => $invoiceDate,
        'payment_status' => 'partial'
    ], ['id' => $transaction['id']]);
    
    db()->commit();
    
    $response['success'] = true;
    $response['message'] = 'Invoice created successfully: ' . $invoiceNumber;
    $response['invoice_id'] = $invoiceId;
    $response['invoice_number'] = $invoiceNumber;
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    $response['message'] = $e->getMessage();
    error_log("Error in generar-factura-venta.php: " . $e->getMessage());
}

echo json_encode($response);
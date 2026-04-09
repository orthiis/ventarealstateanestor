<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$invoiceId = (int)($_GET['id'] ?? 0);

try {
    if ($invoiceId === 0) {
        throw new Exception('Invalid invoice ID');
    }
    
    $invoice = db()->selectOne("
        SELECT i.*,
               CONCAT(c.first_name, ' ', c.last_name) as client_name,
               c.email as client_email,
               c.phone_mobile as client_phone,
               p.reference as property_reference,
               p.title as property_title,
               st.transaction_code,
               st.transaction_type
        FROM invoices i
        INNER JOIN clients c ON i.client_id = c.id
        INNER JOIN properties p ON i.property_id = p.id
        INNER JOIN sales_transactions st ON i.transaction_id = st.id
        WHERE i.id = ?
    ", [$invoiceId]);
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    echo json_encode([
        'success' => true,
        'invoice' => $invoice
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

try {
    // Obtener ventas con balance pendiente que necesitan facturación
    $sales = db()->select("
        SELECT st.id,
               st.transaction_code,
               st.sale_price,
               st.balance_pending,
               CONCAT(c.first_name, ' ', c.last_name) as client_name,
               c.email as client_email,
               p.reference as property_reference,
               p.title as property_title,
               (SELECT COUNT(*) FROM invoices WHERE transaction_id = st.id) as invoice_count,
               (SELECT SUM(total_amount) FROM invoices WHERE transaction_id = st.id) as total_invoiced
        FROM sales_transactions st
        INNER JOIN clients c ON st.client_id = c.id
        INNER JOIN properties p ON st.property_id = p.id
        WHERE st.transaction_type = 'sale'
        AND st.status IN ('pending', 'in_progress', 'completed')
        AND st.balance_pending > 0
        ORDER BY st.created_at DESC
    ");
    
    echo json_encode([
        'success' => true,
        'sales' => $sales
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
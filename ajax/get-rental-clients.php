<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

try {
    // Obtener clientes con alquileres activos que necesitan facturación
    $clients = db()->select("
        SELECT st.id as transaction_id,
               st.client_id,
               st.monthly_payment,
               st.rent_payment_day,
               st.rent_duration_months,
               CONCAT(c.first_name, ' ', c.last_name) as client_name,
               p.reference as property_reference,
               p.title as property_title,
               (SELECT COUNT(*) FROM invoices WHERE transaction_id = st.id) as invoice_count,
               (SELECT COUNT(*) FROM invoices WHERE transaction_id = st.id AND status IN ('pending', 'overdue', 'partial')) as pending_count
        FROM sales_transactions st
        INNER JOIN clients c ON st.client_id = c.id
        INNER JOIN properties p ON st.property_id = p.id
        WHERE st.transaction_type IN ('rent', 'vacation_rent')
        AND st.status IN ('completed', 'in_progress')
        AND (st.rent_end_date IS NULL OR st.rent_end_date >= CURDATE())
        AND (
            st.rent_duration_months IS NULL 
            OR (SELECT COUNT(*) FROM invoices WHERE transaction_id = st.id) < st.rent_duration_months
        )
        ORDER BY c.first_name, c.last_name
    ");
    
    // Filtrar solo los que no tienen facturas pendientes
    $clientsFiltered = array_filter($clients, function($client) {
        return $client['pending_count'] == 0;
    });
    
    // Agregar día de pago si no existe
    foreach ($clientsFiltered as &$client) {
        if (empty($client['rent_payment_day'])) {
            $client['payment_day'] = 25;
        } else {
            $client['payment_day'] = $client['rent_payment_day'];
        }
        
        // Agregar progreso de facturas
        if ($client['rent_duration_months']) {
            $client['progress'] = $client['invoice_count'] . '/' . $client['rent_duration_months'];
        } else {
            $client['progress'] = $client['invoice_count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'clients' => array_values($clientsFiltered)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
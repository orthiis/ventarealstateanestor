<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

// Obtener filtros
$filterType = $_GET['type'] ?? 'all';
$filterMethod = $_GET['method'] ?? 'all';
$filterYear = $_GET['year'] ?? date('Y');
$filterMonth = $_GET['month'] ?? 'all';

$where = ['1=1'];
$params = [];

if ($filterType !== 'all') {
    $where[] = 'i.invoice_type = ?';
    $params[] = $filterType;
}

if ($filterMethod !== 'all') {
    $where[] = 'ip.payment_method = ?';
    $params[] = $filterMethod;
}

if ($filterYear !== 'all') {
    $where[] = 'YEAR(ip.payment_date) = ?';
    $params[] = $filterYear;
}

if ($filterMonth !== 'all') {
    $where[] = 'MONTH(ip.payment_date) = ?';
    $params[] = $filterMonth;
}

$whereClause = implode(' AND ', $where);

$payments = db()->select("
    SELECT ip.payment_date,
           i.invoice_number,
           CONCAT(c.first_name, ' ', c.last_name) as client_name,
           p.reference as property_reference,
           i.invoice_type,
           ip.payment_method,
           ip.payment_reference,
           ip.payment_amount,
           CONCAT(u.first_name, ' ', u.last_name) as registered_by
    FROM invoice_payments ip
    INNER JOIN invoices i ON ip.invoice_id = i.id
    INNER JOIN clients c ON i.client_id = c.id
    INNER JOIN properties p ON i.property_id = p.id
    LEFT JOIN users u ON ip.created_by = u.id
    WHERE {$whereClause}
    ORDER BY ip.payment_date DESC
", $params);

// Generar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Headers
fputcsv($output, [
    'Date',
    'Invoice',
    'Client',
    'Property',
    'Type',
    'Method',
    'Reference',
    'Amount',
    'Registered By'
]);

// Data
foreach ($payments as $payment) {
    fputcsv($output, [
        $payment['payment_date'],
        $payment['invoice_number'],
        $payment['client_name'],
        $payment['property_reference'],
        $payment['invoice_type'],
        $payment['payment_method'],
        $payment['payment_reference'],
        $payment['payment_amount'],
        $payment['registered_by']
    ]);
}

fclose($output);
exit;
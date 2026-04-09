<?php
/**
 * =============================================================================
 * GENERAR FACTURAS DE ALQUILER
 * =============================================================================
 * Genera facturas de alquiler para clientes con contratos activos.
 * 
 * Reglas de Negocio:
 * - Solo genera 1 factura siguiente por cliente
 * - No genera si tiene facturas pendientes de pago
 * - No genera si ya completó todas las facturas del contrato
 * - Genera la siguiente factura independientemente del mes actual
 * - La fecha de factura es el día de pago configurado del cliente
 * - La fecha de vencimiento es un mes después menos un día
 * 
 * @version 3.0
 * @date    2025-10-27
 * =============================================================================
 */

require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$currentUser = getCurrentUser();
$mode = $_POST['mode'] ?? 'all';
$clientIds = json_decode($_POST['client_ids'] ?? '[]', true);

$response = [
    'success' => false,
    'message' => '',
    'generated' => 0,
    'errors' => 0,
    'details' => []
];

try {
    db()->beginTransaction();
    
    $today = new DateTime();
    $currentMonth = (int)$today->format('n');
    $currentYear = (int)$today->format('Y');
    
    // =============================================================================
    // CONSTRUIR QUERY SEGÚN EL MODO
    // =============================================================================
    $where = "st.transaction_type IN ('rent', 'vacation_rent') 
              AND st.status IN ('completed', 'in_progress')
              AND (st.rent_end_date IS NULL OR st.rent_end_date >= CURDATE())";
    
    $params = [];
    
    if ($mode === 'selective' && !empty($clientIds)) {
        $placeholders = str_repeat('?,', count($clientIds) - 1) . '?';
        $where .= " AND st.id IN ($placeholders)";
        $params = $clientIds;
    }
    
    // Obtener alquileres elegibles
    $rentals = db()->select("
        SELECT st.*,
               c.email as client_email,
               c.reference as client_reference,
               CONCAT(c.first_name, ' ', c.last_name) as client_name,
               p.title as property_title,
               p.reference as property_reference
        FROM sales_transactions st
        INNER JOIN clients c ON st.client_id = c.id
        INNER JOIN properties p ON st.property_id = p.id
        WHERE {$where}
        ORDER BY st.client_id
    ", $params);
    
    foreach ($rentals as $rental) {
        try {
            // =============================================================================
            // PASO 1: CONTAR FACTURAS YA GENERADAS
            // =============================================================================
            $invoiceCount = (int)db()->selectValue("
                SELECT COUNT(*) 
                FROM invoices 
                WHERE transaction_id = ?
            ", [$rental['id']]);
            
            // =============================================================================
            // VALIDACIÓN 1: Verificar si ya alcanzó el límite de facturas
            // =============================================================================
            if ($rental['rent_duration_months'] && $invoiceCount >= $rental['rent_duration_months']) {
                // ✅ Ya generó todas las facturas del contrato
                
                // Verificar si todas las facturas están pagadas para marcar como completado
                $unpaidCount = (int)db()->selectValue("
                    SELECT COUNT(*) 
                    FROM invoices 
                    WHERE transaction_id = ? 
                    AND status IN ('pending', 'overdue', 'partial')
                ", [$rental['id']]);
                
                if ($unpaidCount == 0) {
                    // Todas las facturas están pagadas - marcar como completado
                    db()->update('sales_transactions', [
                        'status' => 'completed',
                        'payment_status' => 'completed',
                        'has_pending_invoice' => 0
                    ], ['id' => $rental['id']]);
                }
                
                $response['details'][] = [
                    'client' => $rental['client_name'],
                    'status' => 'skipped',
                    'reason' => 'Contract completed - All invoices generated (' . $invoiceCount . '/' . $rental['rent_duration_months'] . ')'
                ];
                continue;
            }
            
            // =============================================================================
            // PASO 2: OBTENER ÚLTIMA FACTURA GENERADA
            // =============================================================================
            $lastInvoice = db()->selectOne("
                SELECT * 
                FROM invoices 
                WHERE transaction_id = ? 
                ORDER BY period_year DESC, period_month DESC 
                LIMIT 1
            ", [$rental['id']]);
            
            // =============================================================================
            // VALIDACIÓN 2: Verificar si tiene facturas pendientes de pago
            // =============================================================================
            if ($lastInvoice && in_array($lastInvoice['status'], ['pending', 'overdue', 'partial'])) {
                $response['details'][] = [
                    'client' => $rental['client_name'],
                    'status' => 'skipped',
                    'reason' => 'Has unpaid invoice: ' . $lastInvoice['invoice_number'] . ' (' . $lastInvoice['status'] . ')'
                ];
                continue;
            }
            
            // =============================================================================
            // PASO 3: CALCULAR EL MES SIGUIENTE A FACTURAR
            // =============================================================================
            $invoiceMonth = $currentMonth;
            $invoiceYear = $currentYear;
            
            if ($lastInvoice && $lastInvoice['status'] === 'paid') {
                // ✅ La última factura está pagada - generar la SIGUIENTE
                $lastDate = new DateTime($lastInvoice['period_year'] . '-' . $lastInvoice['period_month'] . '-01');
                $lastDate->modify('+1 month');
                $invoiceMonth = (int)$lastDate->format('n');
                $invoiceYear = (int)$lastDate->format('Y');
                
            } elseif (!$lastInvoice) {
                // Primera factura: usar fecha del contrato
                $contractDate = new DateTime($rental['move_in_date'] ?? $rental['contract_date'] ?? 'now');
                $invoiceMonth = (int)$contractDate->format('n');
                $invoiceYear = (int)$contractDate->format('Y');
            }
            
            // =============================================================================
            // VALIDACIÓN 3: Verificar si ya existe factura para ese período
            // =============================================================================
            $existingInvoice = db()->selectOne("
                SELECT id FROM invoices 
                WHERE transaction_id = ? 
                AND period_year = ? 
                AND period_month = ?
            ", [$rental['id'], $invoiceYear, $invoiceMonth]);
            
            if ($existingInvoice) {
                $response['details'][] = [
                    'client' => $rental['client_name'],
                    'status' => 'skipped',
                    'reason' => 'Invoice already exists for period: ' . $invoiceMonth . '/' . $invoiceYear
                ];
                continue;
            }
            
            // =============================================================================
            // PASO 4: CALCULAR FECHAS DE LA FACTURA
            // =============================================================================
            
            // Obtener día de pago configurado
            $paymentDay = (int)($rental['rent_payment_day'] ?? 25);
            if (empty($paymentDay) || $paymentDay < 1 || $paymentDay > 31) {
                // Si no hay día configurado, usar día del contrato
                $contractDate = new DateTime($rental['move_in_date'] ?? $rental['contract_date'] ?? 'now');
                $paymentDay = (int)$contractDate->format('d');
            }
            
            // Crear fecha base del mes a facturar
            $invoiceDateObj = new DateTime($invoiceYear . '-' . $invoiceMonth . '-01');
            
            // Ajustar día para meses con menos días (incluye febrero)
            $lastDayOfMonth = (int)$invoiceDateObj->format('t');
            $actualDay = min($paymentDay, $lastDayOfMonth);
            
            // FECHA DE LA FACTURA: día de pago del mes correspondiente
            $invoiceDateObj->setDate($invoiceYear, $invoiceMonth, $actualDay);
            $invoiceDate = $invoiceDateObj->format('Y-m-d');
            
            // FECHA DE VENCIMIENTO: un mes después menos un día
            $dueDateObj = clone $invoiceDateObj;
            $dueDateObj->modify('+1 month');
            $dueDateObj->modify('-1 day');
            $dueDate = $dueDateObj->format('Y-m-d');
            
            // Período de facturación
            $periodStart = $invoiceYear . '-' . str_pad($invoiceMonth, 2, '0', STR_PAD_LEFT) . '-01';
            $periodEndObj = new DateTime($periodStart);
            $periodEnd = $periodEndObj->format('Y-m-t');
            
            // Formato de período (ej: Dec/2025)
            $monthNames = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
            ];
            $billingPeriod = $monthNames[$invoiceMonth] . '/' . $invoiceYear;
            
            // =============================================================================
            // PASO 5: CALCULAR MONTOS
            // =============================================================================
            $subtotal = $rental['monthly_payment'];
            $taxPercentage = 0.00; // Sin ITBIS para alquileres
            $taxAmount = 0.00;
            $total = $subtotal;
            
            // Verificar si está vencido (solo si la fecha de vencimiento ya pasó)
            $status = (strtotime($dueDate) < time()) ? 'overdue' : 'pending';
            
            // =============================================================================
            // PASO 6: GENERAR NÚMERO DE FACTURA
            // =============================================================================
            $lastInvoiceNumber = db()->selectValue("
                SELECT MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)) 
                FROM invoices 
                WHERE invoice_number LIKE ?
            ", ["INV-{$invoiceYear}-%"]) ?: 0;
            
            $invoiceNumber = 'INV-' . $invoiceYear . '-' . str_pad($lastInvoiceNumber + 1, 4, '0', STR_PAD_LEFT);
            
            // =============================================================================
            // PASO 7: CREAR FACTURA
            // =============================================================================
            $invoiceId = db()->insert('invoices', [
                'invoice_number' => $invoiceNumber,
                'client_id' => $rental['client_id'],
                'property_id' => $rental['property_id'],
                'transaction_id' => $rental['id'],
                'agent_id' => $rental['agent_id'],
                'invoice_type' => 'rent',
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'period_month' => $invoiceMonth,
                'period_year' => $invoiceYear,
                'billing_period' => $billingPeriod,
                'payment_day' => $paymentDay,
                'subtotal' => $subtotal,
                'tax_percentage' => $taxPercentage,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'balance_due' => $total,
                'amount_paid' => 0,
                'status' => $status,
                'is_recurring' => 1,
                'auto_generated' => 1,
                'generation_date' => date('Y-m-d H:i:s'),
                'created_by' => $currentUser['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($invoiceId) {
                // Actualizar sales_transactions
                db()->update('sales_transactions', [
                    'has_pending_invoice' => 1,
                    'last_invoice_date' => $invoiceDate,
                    'payment_status' => 'pending'
                ], ['id' => $rental['id']]);
                
                $response['generated']++;
                $response['details'][] = [
                    'client' => $rental['client_name'],
                    'invoice' => $invoiceNumber,
                    'period' => $billingPeriod,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'amount' => $total,
                    'payment_day' => $paymentDay,
                    'status' => 'success',
                    'message' => 'Invoice generated successfully for ' . $billingPeriod
                ];
            }
            
        } catch (Exception $e) {
            $response['errors']++;
            $response['details'][] = [
                'client' => $rental['client_name'] ?? 'Unknown',
                'status' => 'error',
                'reason' => $e->getMessage()
            ];
        }
    }
    
    // =============================================================================
    // REGISTRAR EN LOG
    // =============================================================================
    db()->insert('invoice_generation_log', [
        'generation_type' => 'rent',
        'total_generated' => $response['generated'],
        'total_errors' => $response['errors'],
        'success_count' => $response['generated'],
        'failed_count' => $response['errors'],
        'generated_by' => $currentUser['id'],
        'client_ids' => json_encode($clientIds),
        'clients_processed' => json_encode(array_column($response['details'], 'client')),
        'errors_log' => json_encode(array_filter($response['details'], function($d) {
            return $d['status'] === 'error';
        })),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    db()->commit();
    
    $response['success'] = true;
    
    if ($response['generated'] > 0) {
        $response['message'] = sprintf(
            '✅ %d invoice(s) generated successfully',
            $response['generated']
        );
    } else {
        $response['message'] = 'No invoices were generated. All clients are up to date or have pending invoices.';
    }
    
    if ($response['errors'] > 0) {
        $response['message'] .= sprintf(' | ⚠️ %d error(s) occurred', $response['errors']);
    }
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    $response['success'] = false;
    $response['message'] = '❌ Error: ' . $e->getMessage();
    error_log("Error in generar-facturas-alquiler.php: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT);
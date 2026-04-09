<?php
/**
 * =============================================================================
 * PROCESAR PAGO DE FACTURA
 * =============================================================================
 * Este archivo procesa los pagos de facturas tanto de ventas como de alquileres.
 * 
 * Funcionalidades:
 * - Registra pagos totales o parciales
 * - Actualiza el estado de la factura
 * - Actualiza el balance de la transacción
 * - Marca como "completed" cuando se paga la última factura de un contrato
 * - Previene pagos duplicados
 * - Calcula fechas de próxima factura para alquileres
 * 
 * @author  JAF Investments CRM
 * @version 2.0
 * @date    2025-10-27
 * =============================================================================
 */

require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

// =============================================================================
// OBTENER DATOS DEL FORMULARIO
// =============================================================================
$currentUser = getCurrentUser();
$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
$paymentAmount = (float)($_POST['payment_amount'] ?? 0);
$paymentMethod = trim($_POST['payment_method'] ?? 'cash');
$paymentReference = trim($_POST['payment_reference'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Respuesta por defecto
$response = [
    'success' => false,
    'message' => '',
    'new_balance' => 0,
    'new_status' => '',
    'transaction_payment_status' => '',
    'transaction_status' => ''
];

try {
    // =============================================================================
    // VALIDACIONES INICIALES
    // =============================================================================
    if ($invoiceId === 0) {
        throw new Exception('Invalid invoice ID');
    }
    
    if ($paymentAmount <= 0) {
        throw new Exception('Payment amount must be greater than zero');
    }
    
    if (empty($paymentMethod)) {
        throw new Exception('Payment method is required');
    }
    
    // Iniciar transacción de base de datos
    db()->beginTransaction();
    
    // =============================================================================
    // PASO 1: OBTENER INFORMACIÓN COMPLETA DE LA FACTURA Y TRANSACCIÓN
    // =============================================================================
    $invoice = db()->selectOne("
        SELECT 
            i.*, 
            st.id as transaction_id, 
            st.balance_pending as transaction_balance,
            st.transaction_type,
            st.status as transaction_status,
            st.payment_status as transaction_payment_status,
            st.rent_end_date,
            st.rent_payment_day,
            st.rent_duration_months,
            st.sale_price,
            CONCAT(c.first_name, ' ', c.last_name) as client_name,
            p.reference as property_reference
        FROM invoices i
        INNER JOIN sales_transactions st ON i.transaction_id = st.id
        INNER JOIN clients c ON i.client_id = c.id
        INNER JOIN properties p ON i.property_id = p.id
        WHERE i.id = ?
    ", [$invoiceId]);
    
    if (!$invoice) {
        throw new Exception('Invoice not found (ID: ' . $invoiceId . ')');
    }
    
    // =============================================================================
    // PASO 2: VALIDACIONES DE ESTADO DE LA FACTURA
    // =============================================================================
    
    // Prevenir pagos duplicados
    if ($invoice['status'] === 'paid') {
        throw new Exception('This invoice is already fully paid. Cannot register duplicate payment.');
    }
    
    // No permitir pagos a facturas canceladas
    if ($invoice['status'] === 'cancelled') {
        throw new Exception('Cannot register payment for a cancelled invoice');
    }
    
    // Verificar que el monto no exceda el saldo pendiente
    if ($paymentAmount > $invoice['balance_due']) {
        throw new Exception(
            'Payment amount ($' . number_format($paymentAmount, 2) . 
            ') exceeds invoice balance ($' . number_format($invoice['balance_due'], 2) . ')'
        );
    }
    
    // =============================================================================
    // PASO 3: REGISTRAR EL PAGO EN LA TABLA invoice_payments
    // =============================================================================
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
        throw new Exception('Failed to register payment in database');
    }
    
    // =============================================================================
    // PASO 4: ACTUALIZAR LA FACTURA (tabla invoices)
    // =============================================================================
    $newAmountPaid = $invoice['amount_paid'] + $paymentAmount;
    $newBalance = $invoice['balance_due'] - $paymentAmount;
    
    // Determinar el nuevo estado de la factura
    $newInvoiceStatus = 'pending';
    
    if ($newBalance <= 0.01) {
        // ✅ FACTURA COMPLETAMENTE PAGADA
        $newInvoiceStatus = 'paid';
    } elseif ($newAmountPaid > 0 && $newBalance > 0) {
        // ⚠️ PAGO PARCIAL
        $newInvoiceStatus = 'partial';
    } elseif (strtotime($invoice['due_date']) < time()) {
        // ⏰ VENCIDA
        $newInvoiceStatus = 'overdue';
    }
    
    // Preparar datos de actualización de la factura
    $invoiceUpdateData = [
        'amount_paid' => $newAmountPaid,
        'balance_due' => max(0, $newBalance),
        'status' => $newInvoiceStatus,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Si la factura está pagada completamente, agregar campos adicionales
    if ($newInvoiceStatus === 'paid') {
        $invoiceUpdateData['paid_date'] = $paymentDate;
        $invoiceUpdateData['payment_method'] = $paymentMethod;
        $invoiceUpdateData['payment_reference'] = $paymentReference;
    }
    
    // Ejecutar actualización de factura
    db()->update('invoices', $invoiceUpdateData, 'id = ?', [$invoiceId]);
    
    // =============================================================================
    // PASO 5: ACTUALIZAR LA TRANSACCIÓN (tabla sales_transactions)
    // =============================================================================
    
    // Calcular nuevo balance de la transacción
    $newTransactionBalance = $invoice['transaction_balance'] - $paymentAmount;
    
    // Preparar datos base de actualización
    $transactionUpdateData = [
        'balance_pending' => max(0, $newTransactionBalance),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // =============================================================================
    // LÓGICA SEGÚN SI LA FACTURA FUE PAGADA COMPLETAMENTE
    // =============================================================================
    
    if ($newInvoiceStatus === 'paid') {
        // ✅ ✅ ✅ LA FACTURA FUE PAGADA COMPLETAMENTE ✅ ✅ ✅
        
        $transactionUpdateData['has_pending_invoice'] = 0;
        
        // -------------------------------------------------------------------------
        // 🏠 PARA VENTAS (sale)
        // -------------------------------------------------------------------------
        if ($invoice['transaction_type'] === 'sale') {
            
            if ($newTransactionBalance <= 0.01) {
                // ✅ VENTA COMPLETAMENTE PAGADA
                $transactionUpdateData['payment_status'] = 'completed';
                $transactionUpdateData['status'] = 'completed';
            } else {
                // ⚠️ Todavía hay balance pendiente en otras facturas
                $transactionUpdateData['payment_status'] = 'partial';
                
                if ($invoice['transaction_status'] === 'pending') {
                    $transactionUpdateData['status'] = 'in_progress';
                }
            }
        }
        
        // -------------------------------------------------------------------------
        // 🏘️ PARA ALQUILERES (rent o vacation_rent)
        // -------------------------------------------------------------------------
        elseif (in_array($invoice['transaction_type'], ['rent', 'vacation_rent'])) {
            
            // La factura mensual está pagada
            $transactionUpdateData['payment_status'] = 'completed';
            
            // Contar cuántas facturas se han generado en total
            $totalInvoicesGenerated = (int)db()->selectValue("
                SELECT COUNT(*) 
                FROM invoices 
                WHERE transaction_id = ?
            ", [$invoice['transaction_id']]);
            
            // Contar cuántas facturas están pagadas
            $totalInvoicesPaid = (int)db()->selectValue("
                SELECT COUNT(*) 
                FROM invoices 
                WHERE transaction_id = ? 
                AND status = 'paid'
            ", [$invoice['transaction_id']]);
            
            // ✅ VERIFICAR SI SE COMPLETÓ EL CONTRATO
            if ($invoice['rent_duration_months'] && 
                $totalInvoicesPaid >= $invoice['rent_duration_months']) {
                // ✅ ✅ ✅ COMPLETÓ TODAS LAS FACTURAS DEL CONTRATO ✅ ✅ ✅
                $transactionUpdateData['status'] = 'completed';
                $transactionUpdateData['payment_status'] = 'completed';
                $transactionUpdateData['has_pending_invoice'] = 0;
                
            } else {
                // ⏭️ Todavía quedan facturas por generar/pagar
                
                // Calcular próxima fecha de factura
                $paymentDay = (int)($invoice['rent_payment_day'] ?? 25);
                
                $nextMonth = new DateTime($invoice['period_year'] . '-' . $invoice['period_month'] . '-01');
                $nextMonth->modify('+1 month');
                
                // Ajustar día del mes (para meses con menos días como febrero)
                $daysInNextMonth = (int)$nextMonth->format('t');
                $actualDay = min($paymentDay, $daysInNextMonth);
                
                $nextMonth->setDate(
                    (int)$nextMonth->format('Y'), 
                    (int)$nextMonth->format('m'), 
                    $actualDay
                );
                
                $transactionUpdateData['next_invoice_date'] = $nextMonth->format('Y-m-d');
                $transactionUpdateData['last_invoice_date'] = $invoice['invoice_date'];
                
                // Actualizar el status de la transacción si estaba pendiente
                if ($invoice['transaction_status'] === 'pending') {
                    $transactionUpdateData['status'] = 'in_progress';
                }
                
                // Verificar si el contrato ha terminado por fecha
                if ($invoice['rent_end_date'] && 
                    strtotime($invoice['rent_end_date']) < time() && 
                    $newTransactionBalance <= 0.01) {
                    $transactionUpdateData['status'] = 'completed';
                    $transactionUpdateData['payment_status'] = 'completed';
                }
            }
        }
        
    } else {
        // ⚠️ ⚠️ ⚠️ LA FACTURA TIENE PAGO PARCIAL ⚠️ ⚠️ ⚠️
        
        $transactionUpdateData['has_pending_invoice'] = 1;
        
        if ($newTransactionBalance <= 0.01) {
            // El balance total de la transacción está pagado
            $transactionUpdateData['payment_status'] = 'completed';
            
            if ($invoice['transaction_type'] === 'sale') {
                $transactionUpdateData['status'] = 'completed';
            }
        } else {
            // Hay pagos parciales
            $transactionUpdateData['payment_status'] = 'partial';
            
            if ($invoice['transaction_type'] === 'sale' && 
                $invoice['transaction_status'] === 'pending') {
                $transactionUpdateData['status'] = 'in_progress';
            }
        }
    }
    
    // =============================================================================
    // PASO 6: EJECUTAR ACTUALIZACIÓN DE LA TRANSACCIÓN
    // =============================================================================
    db()->update('sales_transactions', $transactionUpdateData, 'id = ?', [$invoice['transaction_id']]);
    
    // =============================================================================
    // PASO 7: CONFIRMAR TRANSACCIÓN Y RESPONDER
    // =============================================================================
    db()->commit();
    
    $response['success'] = true;
    $response['message'] = 'Payment registered successfully';
    $response['new_balance'] = max(0, $newBalance);
    $response['new_status'] = $newInvoiceStatus;
    $response['transaction_payment_status'] = $transactionUpdateData['payment_status'];
    $response['transaction_status'] = $transactionUpdateData['status'] ?? $invoice['transaction_status'];
    
    // Mensaje adicional si se completó el contrato
    if (isset($transactionUpdateData['status']) && $transactionUpdateData['status'] === 'completed') {
        $response['contract_completed'] = true;
        $response['message'] .= ' - Contract has been marked as completed.';
    }
    
} catch (Exception $e) {
    // Revertir cambios si hay error
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Log del error para debugging
    error_log("❌ Error in procesar-pago-factura.php: " . $e->getMessage() . " | Invoice ID: " . $invoiceId);
}

// =============================================================================
// ENVIAR RESPUESTA JSON
// =============================================================================
echo json_encode($response, JSON_PRETTY_PRINT);
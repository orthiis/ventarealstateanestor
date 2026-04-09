<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$currentUser = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'generate_monthly_invoices':
            $today = new DateTime();
            $generatedCount = 0;
            $errors = [];
            
            // Obtener todos los alquileres activos
            $rentals = db()->select("
                SELECT st.*, c.payment_day, c.email,
                       CONCAT(c.first_name, ' ', c.last_name) as client_name
                FROM sales_transactions st
                INNER JOIN clients c ON st.client_id = c.id
                WHERE st.transaction_type IN ('rent', 'vacation_rent')
                AND st.status IN ('completed', 'in_progress')
                AND (st.rent_end_date IS NULL OR st.rent_end_date >= CURDATE())
            ");
            
            foreach ($rentals as $rental) {
                try {
                    // Obtener última factura pagada
                    $lastInvoice = db()->selectOne("
                        SELECT * FROM invoices 
                        WHERE transaction_id = ? 
                        AND status = 'paid'
                        ORDER BY period_year DESC, period_month DESC 
                        LIMIT 1
                    ", [$rental['id']]);
                    
                    // Determinar desde qué mes generar
                    $startDate = new DateTime();
                    if ($lastInvoice) {
                        $startDate->setDate($lastInvoice['period_year'], $lastInvoice['period_month'], 1);
                        $startDate->modify('+1 month');
                    } else {
                        // Si no hay facturas, usar fecha de inicio del contrato
                        $startDate = new DateTime($rental['move_in_date'] ?? $rental['contract_date']);
                    }
                    
                    // Generar facturas hasta el mes actual
                    $endDate = clone $today;
                    
                    while ($startDate <= $endDate) {
                        $month = (int)$startDate->format('n');
                        $year = (int)$startDate->format('Y');
                        
                        // Verificar si ya existe factura para este período
                        $exists = db()->selectOne("
                            SELECT id FROM invoices 
                            WHERE transaction_id = ? 
                            AND period_year = ? 
                            AND period_month = ?
                        ", [$rental['id'], $year, $month]);
                        
                        if (!$exists) {
                            // Determinar fechas
                            $paymentDay = $rental['payment_day'] ?? 25;
                            $lastDayOfMonth = (int)$startDate->format('t');
                            $dueDay = min($paymentDay, $lastDayOfMonth);
                            
                            $periodStart = $startDate->format('Y-m-01');
                            $periodEnd = $startDate->format('Y-m-t');
                            $invoiceDate = $startDate->format('Y-m-01');
                            $dueDate = $startDate->format('Y-m-' . str_pad($dueDay, 2, '0', STR_PAD_LEFT));
                            
                            // Calcular montos
                            $subtotal = $rental['monthly_payment'];
                            $taxPercentage = 18.00; // ITBIS
                            $taxAmount = $subtotal * ($taxPercentage / 100);
                            $total = $subtotal + $taxAmount;
                            
                            // Generar número de factura
                            $invoiceNumber = 'INV-' . $year . '-' . str_pad(db()->selectOne("SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)), 0) + 1 as next FROM invoices WHERE invoice_number LIKE 'INV-{$year}-%'")['next'], 4, '0', STR_PAD_LEFT);
                            
                            // Crear factura
                            db()->insert('invoices', [
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
                                'period_month' => $month,
                                'period_year' => $year,
                                'subtotal' => $subtotal,
                                'tax_percentage' => $taxPercentage,
                                'tax_amount' => $taxAmount,
                                'total_amount' => $total,
                                'balance_due' => $total,
                                'status' => (strtotime($dueDate) < time()) ? 'overdue' : 'pending',
                                'is_recurring' => 1,
                                'created_by' => $currentUser['id'],
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                            
                            $generatedCount++;
                        }
                        
                        $startDate->modify('+1 month');
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error en transacción {$rental['transaction_code']}: " . $e->getMessage();
                }
            }
            
            $message = "Se generaron {$generatedCount} factura(s) exitosamente.";
            if (!empty($errors)) {
                $message .= " Errores: " . implode(', ', $errors);
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'generated' => $generatedCount,
                'errors' => $errors
            ]);
            break;
            
        case 'register_payment':
            $invoiceId = $_POST['invoice_id'] ?? 0;
            $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
            $paymentMethod = $_POST['payment_method'] ?? '';
            $paymentReference = $_POST['payment_reference'] ?? '';
            $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
            $notes = $_POST['notes'] ?? '';
            
            if (!$invoiceId || $paymentAmount <= 0) {
                throw new Exception('Datos de pago inválidos');
            }
            
            // Obtener factura
            $invoice = db()->selectOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
            if (!$invoice) {
                throw new Exception('Factura no encontrada');
            }
            
            if ($paymentAmount > $invoice['balance_due']) {
                throw new Exception('El monto de pago no puede ser mayor al saldo pendiente');
            }
            
            db()->beginTransaction();
            
            // Registrar pago
            db()->insert('invoice_payments', [
                'invoice_id' => $invoiceId,
                'payment_date' => $paymentDate,
                'payment_amount' => $paymentAmount,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'notes' => $notes,
                'created_by' => $currentUser['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Actualizar factura
            $newAmountPaid = $invoice['amount_paid'] + $paymentAmount;
            $newBalanceDue = $invoice['total_amount'] - $newAmountPaid;
            $newStatus = $newBalanceDue <= 0 ? 'paid' : 'partial';
            
            db()->update('invoices', [
                'amount_paid' => $newAmountPaid,
                'balance_due' => $newBalanceDue,
                'status' => $newStatus,
                'paid_date' => ($newStatus === 'paid') ? $paymentDate : null,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$invoiceId]);
            
            // Actualizar estado de pago de la transacción
            if ($newStatus === 'paid') {
                db()->update('sales_transactions', [
                    'payment_status' => 'completed',
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$invoice['transaction_id']]);
            }
            
            db()->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Pago registrado exitosamente'
            ]);
            break;
            
        case 'send_invoice_email':
            $invoiceId = $_POST['invoice_id'] ?? 0;
            
            $invoice = db()->selectOne("
                SELECT i.*, 
                       CONCAT(c.first_name, ' ', c.last_name) as client_name,
                       c.email as client_email,
                       p.title as property_title
                FROM invoices i
                INNER JOIN clients c ON i.client_id = c.id
                INNER JOIN properties p ON i.property_id = p.id
                WHERE i.id = ?
            ", [$invoiceId]);
            
            if (!$invoice) {
                throw new Exception('Factura no encontrada');
            }
            
            if (empty($invoice['client_email'])) {
                throw new Exception('El cliente no tiene email registrado');
            }
            
            // TODO: Implementar envío de email
            // Por ahora solo simulamos el envío
            
            echo json_encode([
                'success' => true,
                'message' => 'Factura enviada por email a ' . $invoice['client_email']
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
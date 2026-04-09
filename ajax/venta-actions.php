<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$currentUser = getCurrentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$response = [
    'success' => false,
    'message' => ''
];

try {
    switch ($action) {
        case 'create':
            $transactionType = $_POST['transaction_type'] ?? '';
            $propertyId = (int)($_POST['property_id'] ?? 0);
            $clientId = (int)($_POST['client_id'] ?? 0);
            $agentId = (int)($_POST['agent_id'] ?? 0);
            
            if (empty($transactionType) || $propertyId === 0 || $clientId === 0 || $agentId === 0) {
                throw new Exception(__('sales.missing_required_fields', [], 'Missing required fields'));
            }
            
            db()->beginTransaction();
            
            // Generar código de transacción
            $prefix = strtoupper(substr($transactionType, 0, 4));
            $year = date('Y');
            $lastNumber = db()->selectValue("
                SELECT MAX(CAST(SUBSTRING(transaction_code, -4) AS UNSIGNED))
                FROM sales_transactions
                WHERE transaction_code LIKE ?
            ", ["{$prefix}-{$year}-%"]) ?: 0;
            
            $transactionCode = $prefix . '-' . $year . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            
            // Datos comunes
            $data = [
                'transaction_code' => $transactionCode,
                'property_id' => $propertyId,
                'client_id' => $clientId,
                'agent_id' => $agentId,
                'transaction_type' => $transactionType,
                'status' => $_POST['status'] ?? 'in_progress',
                'notes' => $_POST['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $currentUser['id']
            ];
            
            if ($transactionType === 'sale') {
                $salePrice = (float)($_POST['sale_price'] ?? 0);
                $data['sale_price'] = $salePrice;
                $data['original_price'] = (float)($_POST['original_price'] ?? $salePrice);
                $data['commission_percentage'] = (float)($_POST['commission_percentage'] ?? 0);
                $data['commission_amount'] = (float)($_POST['commission_amount'] ?? 0);
                $data['financing_method'] = $_POST['financing_method'] ?? null;
                $data['contract_date'] = $_POST['contract_date'] ?? date('Y-m-d');
                $data['closing_date'] = $_POST['closing_date'] ?? null;
                $data['balance_pending'] = $salePrice - ((float)($_POST['initial_payment'] ?? 0));
                $data['payment_status'] = $data['balance_pending'] <= 0 ? 'completed' : 'pending';
            } else {
                $monthlyPayment = (float)($_POST['monthly_payment'] ?? 0);
                $data['sale_price'] = $monthlyPayment;
                $data['monthly_payment'] = $monthlyPayment;
                $data['rent_duration_months'] = (int)($_POST['rent_duration_months'] ?? 12);
                $data['commission_amount'] = (float)($_POST['commission_amount_rent'] ?? $monthlyPayment);
                $data['contract_date'] = $_POST['contract_date_rent'] ?? date('Y-m-d');
                $data['move_in_date'] = $_POST['move_in_date'] ?? date('Y-m-d');
                $data['rent_end_date'] = $_POST['rent_end_date'] ?? null;
                $data['rent_payment_day'] = (int)($_POST['rent_payment_day'] ?? 1);
                $data['balance_pending'] = $monthlyPayment;
                $data['payment_status'] = 'pending';
                
                // Calcular fecha de fin si no se proporcionó
                if (empty($data['rent_end_date'])) {
                    $moveIn = new DateTime($data['move_in_date']);
                    $moveIn->modify('+' . $data['rent_duration_months'] . ' months');
                    $data['rent_end_date'] = $moveIn->format('Y-m-d');
                }
                
                // Calcular próxima fecha de factura
                $nextInvoice = new DateTime($data['move_in_date']);
                $nextInvoice->setDate($nextInvoice->format('Y'), $nextInvoice->format('m'), $data['rent_payment_day']);
                if ($nextInvoice < new DateTime()) {
                    $nextInvoice->modify('+1 month');
                }
                $data['next_invoice_date'] = $nextInvoice->format('Y-m-d');
            }
            
            $transactionId = db()->insert('sales_transactions', $data);
            
            if (!$transactionId) {
                throw new Exception(__('sales.creation_failed', [], 'Failed to create transaction'));
            }
            
            // Actualizar estado de propiedad
            $newStatus = ($transactionType === 'sale') ? 'sold' : 'rented';
            db()->update('properties', [
                'availability_status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $propertyId]);
            
            db()->commit();
            
            $response['success'] = true;
            $response['message'] = __('sales.created_successfully', [], 'Transaction created successfully');
            $response['transaction_id'] = $transactionId;
            $response['transaction_code'] = $transactionCode;
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($currentUser['role']['name'] !== 'administrador') {
                throw new Exception(__('unauthorized', [], 'Unauthorized'));
            }
            
            if ($id === 0) {
                throw new Exception(__('invalid_id', [], 'Invalid ID'));
            }
            
            db()->beginTransaction();
            
            // Obtener transacción
            $transaction = db()->selectOne("SELECT * FROM sales_transactions WHERE id = ?", [$id]);
            
            if (!$transaction) {
                throw new Exception(__('transaction_not_found', [], 'Transaction not found'));
            }
            
            // No permitir eliminar si tiene facturas pagadas
            $hasPaidInvoices = db()->selectOne("
                SELECT COUNT(*) as count 
                FROM invoices 
                WHERE transaction_id = ? AND status = 'paid'
            ", [$id])['count'] > 0;
            
            if ($hasPaidInvoices) {
                throw new Exception(__('sales.has_paid_invoices', [], 'Cannot delete transaction with paid invoices'));
            }
            
            // Eliminar facturas pendientes
            db()->delete('invoices', ['transaction_id' => $id]);
            
            // Eliminar transacción
            db()->delete('sales_transactions', ['id' => $id]);
            
            // Restaurar estado de propiedad
            db()->update('properties', [
                'availability_status' => 'available',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $transaction['property_id']]);
            
            db()->commit();
            
            $response['success'] = true;
            $response['message'] = __('sales.deleted_successfully', [], 'Transaction deleted successfully');
            break;
            
        default:
            throw new Exception(__('invalid_action', [], 'Invalid action'));
    }
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
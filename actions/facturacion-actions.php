<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$currentUser = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$response = [
    'success' => false,
    'message' => ''
];

try {
    switch ($action) {
        case 'delete_invoice':
            $invoiceId = (int)($_POST['invoice_id'] ?? 0);
            
            if ($currentUser['role']['name'] !== 'administrador') {
                throw new Exception(__('unauthorized', [], 'Unauthorized action'));
            }
            
            if ($invoiceId === 0) {
                throw new Exception(__('invoice.invalid_id', [], 'Invalid invoice ID'));
            }
            
            db()->beginTransaction();
            
            // Obtener factura
            $invoice = db()->selectOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
            
            if (!$invoice) {
                throw new Exception(__('invoice.not_found', [], 'Invoice not found'));
            }
            
            // No permitir eliminar facturas pagadas
            if ($invoice['status'] === 'paid') {
                throw new Exception(__('invoice.cannot_delete_paid', [], 'Cannot delete paid invoices'));
            }
            
            // Eliminar pagos asociados
            db()->delete('invoice_payments', ['invoice_id' => $invoiceId]);
            
            // Eliminar factura
            db()->delete('invoices', ['id' => $invoiceId]);
            
            // Actualizar transacción
            db()->update('sales_transactions', [
                'has_pending_invoice' => 0
            ], ['id' => $invoice['transaction_id']]);
            
            db()->commit();
            
            $response['success'] = true;
            $response['message'] = __('invoice.deleted_successfully', [], 'Invoice deleted successfully');
            break;
            
        case 'cancel_invoice':
            $invoiceId = (int)($_POST['invoice_id'] ?? 0);
            
            if ($currentUser['role']['name'] !== 'administrador') {
                throw new Exception(__('unauthorized', [], 'Unauthorized action'));
            }
            
            if ($invoiceId === 0) {
                throw new Exception(__('invoice.invalid_id', [], 'Invalid invoice ID'));
            }
            
            db()->beginTransaction();
            
            $invoice = db()->selectOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
            
            if (!$invoice) {
                throw new Exception(__('invoice.not_found', [], 'Invoice not found'));
            }
            
            if ($invoice['status'] === 'paid') {
                throw new Exception(__('invoice.cannot_cancel_paid', [], 'Cannot cancel paid invoices'));
            }
            
            // Cancelar factura
            db()->update('invoices', [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_reason' => 'Cancelled by administrator',
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $invoiceId]);
            
            // Actualizar transacción
            db()->update('sales_transactions', [
                'has_pending_invoice' => 0
            ], ['id' => $invoice['transaction_id']]);
            
            db()->commit();
            
            $response['success'] = true;
            $response['message'] = __('invoice.cancelled_successfully', [], 'Invoice cancelled successfully');
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
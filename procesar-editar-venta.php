<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();
        
        $transactionId = $_POST['transaction_id'] ?? 0;
        
        // Obtener transacción actual
        $currentTransaction = db()->selectOne("SELECT * FROM sales_transactions WHERE id = ?", [$transactionId]);
        if (!$currentTransaction) {
            throw new Exception("Transacción no encontrada");
        }
        
        // Verificar permisos
        if ($currentUser['role']['name'] !== 'administrador' && 
            $currentTransaction['agent_id'] != $currentUser['id'] && 
            $currentTransaction['second_agent_id'] != $currentUser['id']) {
            throw new Exception("No tienes permisos para editar esta transacción");
        }
        
        // Validar campos requeridos
        $requiredFields = ['client_id', 'agent_id', 'sale_price', 'status', 'payment_status'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo {$field} es requerido");
            }
        }
        
        // Calcular saldo pendiente
        $salePrice = floatval($_POST['sale_price']);
        $depositPaid = floatval($_POST['deposit_paid'] ?? 0);
        $balancePending = $salePrice - $depositPaid;
        
        // Preparar datos para actualizar
        $updateData = [
            'client_id' => $_POST['client_id'],
            'agent_id' => $_POST['agent_id'],
            'second_agent_id' => !empty($_POST['second_agent_id']) ? $_POST['second_agent_id'] : null,
            'office_id' => !empty($_POST['office_id']) ? $_POST['office_id'] : null,
            'sale_price' => $salePrice,
            'original_price' => !empty($_POST['original_price']) ? floatval($_POST['original_price']) : null,
            'commission_percentage' => !empty($_POST['commission_percentage']) ? floatval($_POST['commission_percentage']) : null,
            'commission_amount' => !empty($_POST['commission_amount']) ? floatval($_POST['commission_amount']) : null,
            'contract_date' => !empty($_POST['contract_date']) ? $_POST['contract_date'] : null,
            'closing_date' => !empty($_POST['closing_date']) ? $_POST['closing_date'] : null,
            'move_in_date' => !empty($_POST['move_in_date']) ? $_POST['move_in_date'] : null,
            'status' => $_POST['status'],
            'payment_method' => $_POST['payment_method'] ?? null,
            'deposit_paid' => $depositPaid,
            'notes' => $_POST['notes'] ?? null,
            
            // Campos de financiamiento
            'financing_type' => $_POST['financing_type'] ?? 'cash',
            'bank_name' => $_POST['bank_name'] ?? null,
            'loan_amount' => !empty($_POST['loan_amount']) ? floatval($_POST['loan_amount']) : null,
            'down_payment' => !empty($_POST['down_payment']) ? floatval($_POST['down_payment']) : null,
            'monthly_payment' => !empty($_POST['monthly_payment']) ? floatval($_POST['monthly_payment']) : null,
            'rent_duration_months' => !empty($_POST['rent_duration_months']) ? intval($_POST['rent_duration_months']) : null,
            'rent_end_date' => $_POST['rent_end_date'] ?? null,
            'warranty_amount' => !empty($_POST['warranty_amount']) ? floatval($_POST['warranty_amount']) : null,
            'tax_amount' => !empty($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : null,
            'notary_fees' => !empty($_POST['notary_fees']) ? floatval($_POST['notary_fees']) : null,
            'other_fees' => !empty($_POST['other_fees']) ? floatval($_POST['other_fees']) : null,
            'total_transaction_cost' => !empty($_POST['total_transaction_cost']) ? floatval($_POST['total_transaction_cost']) : null,
            'payment_status' => $_POST['payment_status'],
            'balance_pending' => $balancePending
        ];
        
        // Manejo de archivo de contrato
        if (!empty($_FILES['contract_file']['name'])) {
            $uploadDir = DOCUMENTS_PATH;
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = 'contract_' . $currentTransaction['transaction_code'] . '_' . time() . '.pdf';
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $filePath)) {
                $updateData['contract_file_url'] = 'uploads/documents/' . $fileName;
                
                // Eliminar archivo anterior
                if ($currentTransaction['contract_file_url'] && file_exists(ROOT_PATH . '/' . $currentTransaction['contract_file_url'])) {
                    unlink(ROOT_PATH . '/' . $currentTransaction['contract_file_url']);
                }
            }
        }
        
        // Actualizar transacción
        db()->update('sales_transactions', $updateData, 'id = ?', [$transactionId]);
        
        // ✅ ACTUALIZAR ESTADO DE PROPIEDAD SI CAMBIÓ - CORREGIDO
        if ($currentTransaction['status'] !== $_POST['status']) {
            $property = db()->selectOne("SELECT * FROM properties WHERE id = ?", [$currentTransaction['property_id']]);
            $newPropertyStatus = $property['status'];
            
            if ($_POST['status'] === 'completed') {
                if ($currentTransaction['transaction_type'] === 'sale') {
                    $newPropertyStatus = 'sold';
                } elseif ($currentTransaction['transaction_type'] === 'rent' || $currentTransaction['transaction_type'] === 'vacation_rent') {
                    $newPropertyStatus = 'rented';
                }
            } elseif ($_POST['status'] === 'in_progress') {
                $newPropertyStatus = 'reserved';
            } elseif ($_POST['status'] === 'cancelled') {
                $newPropertyStatus = $currentTransaction['property_previous_status'] ?? 'available';
            }
            
            // ✅ SINTAXIS CORRECTA PARA ACTUALIZAR LA PROPIEDAD
            db()->update('properties', [
                'status' => $newPropertyStatus
            ], 'id = ?', [$currentTransaction['property_id']]);
            
            // Registrar en timeline
            try {
                db()->insert('sale_timeline', [
                    'transaction_id' => $transactionId,
                    'event_type' => 'status_changed',
                    'event_title' => 'Estado de Transacción Actualizado',
                    'event_description' => "Estado cambió de '{$currentTransaction['status']}' a '{$_POST['status']}'. Estado de propiedad actualizado a '{$newPropertyStatus}'",
                    'old_value' => $currentTransaction['status'],
                    'new_value' => $_POST['status'],
                    'user_id' => $currentUser['id']
                ]);
            } catch (Exception $e) {
                // Continuar si no existe tabla
            }
        }
        
        // Registrar cambios en timeline
        $changes = [];
        foreach ($updateData as $key => $value) {
            if ($currentTransaction[$key] != $value) {
                $changes[] = $key;
            }
        }
        
        if (!empty($changes)) {
            try {
                db()->insert('sale_timeline', [
                    'transaction_id' => $transactionId,
                    'event_type' => 'other',
                    'event_title' => 'Transacción Actualizada',
                    'event_description' => 'Se modificaron los siguientes campos: ' . implode(', ', $changes),
                    'old_value' => json_encode(array_intersect_key((array)$currentTransaction, array_flip($changes))),
                    'new_value' => json_encode(array_intersect_key($updateData, array_flip($changes))),
                    'user_id' => $currentUser['id']
                ]);
            } catch (Exception $e) {
                // Continuar si no existe tabla
            }
        }
        
        // Log de actividad
        try {
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'sale_transaction',
                'entity_id' => $transactionId,
                'description' => "Actualizó transacción {$currentTransaction['transaction_code']}",
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {
            // Continuar si no existe tabla
        }
        
        db()->commit();
        
        setFlashMessage('success', '✅ Transacción actualizada exitosamente');
        redirect('ver-venta.php?id=' . $transactionId);
        
    } catch (Exception $e) {
        db()->rollback();
        setFlashMessage('error', '❌ Error: ' . $e->getMessage());
        redirect('editar-venta.php?id=' . ($transactionId ?? 0));
    }
} else {
    redirect('ventas.php');
}
<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Habilitar errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

requireLogin();

$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();
        
        // DEBUG: Log de inicio
        error_log("=== INICIO PROCESO VENTA ===");
        error_log("POST data: " . print_r($_POST, true));
        
        // Validar campos requeridos
        $requiredFields = ['transaction_type', 'property_id', 'client_id', 'agent_id', 'sale_price', 'status', 'payment_status'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo {$field} es requerido");
            }
        }
        
        error_log("Validación de campos requeridos: OK");
        
        // Obtener datos de la propiedad
        $property = db()->selectOne("SELECT * FROM properties WHERE id = ?", [$_POST['property_id']]);
        if (!$property) {
            throw new Exception("Propiedad no encontrada");
        }
        
        error_log("Propiedad encontrada: " . $property['reference']);
        
        // Generar código de transacción único
        $transactionCode = 'TRX-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        error_log("Código generado: " . $transactionCode);
        
        // Calcular saldo pendiente
        $salePrice = floatval($_POST['sale_price']);
        $depositPaid = floatval($_POST['deposit_paid'] ?? 0);
        $balancePending = $salePrice - $depositPaid;
        
        error_log("Sale Price: $salePrice, Deposit: $depositPaid, Balance: $balancePending");
        
        // Preparar datos para insertar
        $transactionData = [
            'transaction_code' => $transactionCode,
            'property_id' => intval($_POST['property_id']),
            'property_previous_status' => $property['status'],
            'client_id' => intval($_POST['client_id']),
            'agent_id' => intval($_POST['agent_id']),
            'second_agent_id' => !empty($_POST['second_agent_id']) ? intval($_POST['second_agent_id']) : null,
            'office_id' => !empty($_POST['office_id']) ? intval($_POST['office_id']) : null,
            'transaction_type' => $_POST['transaction_type'],
            'sale_price' => $salePrice,
            'original_price' => !empty($_POST['original_price']) ? floatval($_POST['original_price']) : null,
            'commission_percentage' => !empty($_POST['commission_percentage']) ? floatval($_POST['commission_percentage']) : null,
            'commission_amount' => !empty($_POST['commission_amount']) ? floatval($_POST['commission_amount']) : null,
            'contract_date' => !empty($_POST['contract_date']) ? $_POST['contract_date'] : null,
            'closing_date' => !empty($_POST['closing_date']) ? $_POST['closing_date'] : null,
            'move_in_date' => !empty($_POST['move_in_date']) ? $_POST['move_in_date'] : null,
            'status' => $_POST['status'],
            'payment_status' => $_POST['payment_status'],
            'balance_pending' => $balancePending,
            'payment_method' => !empty($_POST['payment_method']) ? $_POST['payment_method'] : null,
            'financing_type' => !empty($_POST['financing_type']) ? $_POST['financing_type'] : 'cash',
            'bank_name' => !empty($_POST['bank_name']) ? $_POST['bank_name'] : null,
            'loan_amount' => !empty($_POST['loan_amount']) ? floatval($_POST['loan_amount']) : null,
            'down_payment' => !empty($_POST['down_payment']) ? floatval($_POST['down_payment']) : null,
            'monthly_payment' => !empty($_POST['monthly_payment']) ? floatval($_POST['monthly_payment']) : null,
            'rent_duration_months' => !empty($_POST['rent_duration_months']) ? intval($_POST['rent_duration_months']) : null,
            'rent_end_date' => !empty($_POST['rent_end_date']) ? $_POST['rent_end_date'] : null,
            'warranty_amount' => !empty($_POST['warranty_amount']) ? floatval($_POST['warranty_amount']) : null,
            'tax_amount' => !empty($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : null,
            'notary_fees' => !empty($_POST['notary_fees']) ? floatval($_POST['notary_fees']) : null,
            'other_fees' => !empty($_POST['other_fees']) ? floatval($_POST['other_fees']) : null,
            'total_transaction_cost' => !empty($_POST['total_transaction_cost']) ? floatval($_POST['total_transaction_cost']) : null,
            'deposit_paid' => $depositPaid,
            'notes' => !empty($_POST['notes']) ? $_POST['notes'] : null
        ];
        
        error_log("Transaction Data preparado:");
        error_log(print_r($transactionData, true));
        
        // Manejo de archivo de contrato
        if (!empty($_FILES['contract_file']['name'])) {
            error_log("Procesando archivo de contrato...");
            $uploadDir = DOCUMENTS_PATH;
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
                error_log("Directorio creado: " . $uploadDir);
            }
            
            $fileName = 'contract_' . $transactionCode . '_' . time() . '.pdf';
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['contract_file']['tmp_name'], $filePath)) {
                $transactionData['contract_file_url'] = 'uploads/documents/' . $fileName;
                error_log("Archivo subido: " . $transactionData['contract_file_url']);
            } else {
                error_log("ERROR al subir archivo");
            }
        }
        
        // Insertar transacción
        error_log("Intentando insertar transacción...");
        
        try {
            $transactionId = db()->insert('sales_transactions', $transactionData);
            error_log("INSERT exitoso. Transaction ID: " . $transactionId);
        } catch (PDOException $e) {
            error_log("ERROR PDO en INSERT: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            throw new Exception("Error de base de datos al crear transacción: " . $e->getMessage());
        }
        
        if (!$transactionId || $transactionId == 0) {
            throw new Exception("Error: No se obtuvo ID de la transacción insertada");
        }
        
        error_log("Transacción insertada con ID: " . $transactionId);
        
        // ✅ ACTUALIZAR ESTADO DE LA PROPIEDAD - CORREGIDO
        $newPropertyStatus = $property['status'];
        
        if ($_POST['status'] === 'completed') {
            if ($_POST['transaction_type'] === 'sale') {
                $newPropertyStatus = 'sold';
            } elseif ($_POST['transaction_type'] === 'rent' || $_POST['transaction_type'] === 'vacation_rent') {
                $newPropertyStatus = 'rented';
            }
        } elseif ($_POST['status'] === 'in_progress') {
            $newPropertyStatus = 'reserved';
        } elseif ($_POST['status'] === 'pending') {
            $newPropertyStatus = 'reserved';
        }
        
        error_log("Actualizando propiedad de '{$property['status']}' a '{$newPropertyStatus}'");
        
        try {
            // ✅ SINTAXIS CORRECTA
            $propertyUpdated = db()->update('properties', [
                'status' => $newPropertyStatus
            ], 'id = ?', [$_POST['property_id']]);
            
            error_log("Propiedad actualizada: " . ($propertyUpdated ? 'SI' : 'NO'));
        } catch (PDOException $e) {
            error_log("ERROR PDO en UPDATE propiedad: " . $e->getMessage());
            throw new Exception("Error al actualizar el estado de la propiedad: " . $e->getMessage());
        }
        
        // Registrar pago inicial si hay depósito
        if ($depositPaid > 0) {
            try {
                db()->insert('sale_payments', [
                    'transaction_id' => $transactionId,
                    'payment_date' => $_POST['contract_date'] ?? date('Y-m-d'),
                    'payment_amount' => $depositPaid,
                    'payment_method' => $_POST['payment_method'] ?? 'Efectivo',
                    'payment_reference' => 'Depósito inicial',
                    'notes' => 'Pago inicial registrado al crear la transacción',
                    'created_by' => $currentUser['id']
                ]);
                error_log("Pago inicial registrado");
            } catch (Exception $e) {
                error_log("Error al registrar pago inicial (no crítico): " . $e->getMessage());
            }
        }
        
        // Registrar en timeline
        try {
            db()->insert('sale_timeline', [
                'transaction_id' => $transactionId,
                'event_type' => 'created',
                'event_title' => 'Transacción Creada',
                'event_description' => 'La transacción ha sido registrada en el sistema',
                'new_value' => json_encode([
                    'status' => $_POST['status'],
                    'sale_price' => $salePrice
                ]),
                'user_id' => $currentUser['id']
            ]);
            
            if ($depositPaid > 0) {
                db()->insert('sale_timeline', [
                    'transaction_id' => $transactionId,
                    'event_type' => 'payment_received',
                    'event_title' => 'Depósito Inicial Recibido',
                    'event_description' => 'Se registró el depósito inicial de $' . number_format($depositPaid, 2),
                    'user_id' => $currentUser['id']
                ]);
            }
            
            error_log("Timeline registrado");
        } catch (Exception $e) {
            error_log("Error en timeline (no crítico): " . $e->getMessage());
        }
        
        // Log de actividad
        try {
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'create',
                'entity_type' => 'sale_transaction',
                'entity_id' => $transactionId,
                'description' => "Creó transacción {$transactionCode} - Propiedad: {$property['reference']} - Estado: {$newPropertyStatus}",
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            error_log("Activity log registrado");
        } catch (Exception $e) {
            error_log("Error en activity_log (no crítico): " . $e->getMessage());
        }
        
        db()->commit();
        error_log("=== COMMIT EXITOSO ===");
        
        setFlashMessage('success', '✅ Transacción creada exitosamente. ID: ' . $transactionId . ' - Estado de propiedad: ' . strtoupper($newPropertyStatus));
        redirect('ventas.php');
        
    } catch (Exception $e) {
        db()->rollback();
        error_log("=== ROLLBACK EJECUTADO ===");
        error_log("ERROR: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        setFlashMessage('error', '❌ Error al crear transacción: ' . $e->getMessage());
        
        // En desarrollo, mostrar error completo
        if (defined('DEBUG') && DEBUG === true) {
            die("ERROR COMPLETO: " . $e->getMessage() . "<br><br>Trace:<br>" . nl2br($e->getTraceAsString()));
        }
        
        redirect('nueva-venta.php');
    }
} else {
    redirect('nueva-venta.php');
}
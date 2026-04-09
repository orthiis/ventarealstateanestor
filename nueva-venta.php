<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$currentUser = getCurrentUser();

// ============================================
// AJAX HANDLER - Manejo de peticiones AJAX
// ============================================
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['ajax_action'];
    $response = ['success' => false, 'message' => '', 'data' => []];
    
    try {
        switch ($action) {
            // ============================================
            // CARGAR PROPIEDADES DISPONIBLES
            // ============================================
            case 'load_properties':
                $transactionType = $_POST['transaction_type'] ?? 'sale';
                
                // CORRECCIÓN: Usar las columnas correctas de la BD
                $properties = db()->select("
                    SELECT p.id,
                           p.reference,
                           p.title,
                           p.description,
                           p.property_type_id,
                           p.operation_type,
                           p.status,
                           p.price,
                           p.currency,
                           p.city,
                           p.zone,
                           p.address,
                           p.bedrooms,
                           p.bathrooms,
                           p.garage_spaces,
                           COALESCE(p.built_area, p.useful_area, 0) as size_sqm,
                           p.built_area,
                           p.useful_area,
                           pt.name as type_name,
                           (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image
                    FROM properties p
                    LEFT JOIN property_types pt ON p.property_type_id = pt.id
                    WHERE p.status IN ('available', 'reserved')
                    AND p.operation_type = ?
                    ORDER BY p.created_at DESC
                    LIMIT 200
                ", [$transactionType]);
                
                $response['success'] = true;
                $response['data'] = $properties;
                $response['count'] = count($properties);
                $response['debug'] = [
                    'transaction_type' => $transactionType,
                    'sql_operation_type' => $transactionType,
                    'properties_found' => count($properties)
                ];
                break;
            
            // ============================================
            // BUSCAR CLIENTES
            // ============================================
            case 'search_clients':
                $search = trim($_POST['search'] ?? '');
                
                if (empty($search)) {
                    $clients = db()->select("
                        SELECT id,
                               CONCAT(first_name, ' ', last_name) as full_name,
                               reference,
                               email,
                               phone_mobile,
                               client_type
                        FROM clients
                        WHERE status != 'deleted'
                        ORDER BY created_at DESC
                        LIMIT 50
                    ");
                } else {
                    $searchTerm = "%{$search}%";
                    $clients = db()->select("
                        SELECT id,
                               CONCAT(first_name, ' ', last_name) as full_name,
                               reference,
                               email,
                               phone_mobile,
                               client_type
                        FROM clients
                        WHERE status != 'deleted'
                        AND (
                            first_name LIKE ? OR
                            last_name LIKE ? OR
                            email LIKE ? OR
                            phone_mobile LIKE ? OR
                            reference LIKE ?
                        )
                        ORDER BY first_name
                        LIMIT 50
                    ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                }
                
                $response['success'] = true;
                $response['data'] = $clients;
                $response['count'] = count($clients);
                break;
            
            // ============================================
            // CARGAR AGENTES
            // ============================================
            case 'load_agents':
                $agents = db()->select("
                    SELECT u.id,
                           u.first_name,
                           u.last_name,
                           CONCAT(u.first_name, ' ', u.last_name) as full_name,
                           u.email,
                           u.profile_picture,
                           r.display_name as role_name
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.status = 'active'
                    AND u.role_id IN (1, 2, 3)
                    ORDER BY u.first_name
                ");
                
                $response['success'] = true;
                $response['data'] = $agents;
                break;
            
            // ============================================
            // CREAR TRANSACCIÓN
            // ============================================
            case 'create_transaction':
                db()->beginTransaction();
                
                $data = json_decode($_POST['transaction_data'], true);
                
                // Validar datos requeridos
                if (!isset($data['property_id']) || !isset($data['client_id']) || !isset($data['agent_id'])) {
                    throw new Exception('Faltan datos requeridos para crear la transacción');
                }
                
                // Generar código de transacción
                $prefix = strtoupper(substr($data['transaction_type'], 0, 3));
                $year = date('Y');
                
                $lastNumber = db()->selectValue("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(transaction_code, -4) AS UNSIGNED)), 0)
                    FROM sales_transactions
                    WHERE transaction_code LIKE ?
                ", ["{$prefix}-{$year}-%"]) ?: 0;
                
                $transactionCode = "{$prefix}-{$year}-" . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                
                // Preparar datos base
                $insertData = [
                    'transaction_code' => $transactionCode,
                    'property_id' => (int)$data['property_id'],
                    'client_id' => (int)$data['client_id'],
                    'agent_id' => (int)$data['agent_id'],
                    'transaction_type' => $data['transaction_type'],
                    'status' => 'in_progress',
                    'payment_status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Campos específicos según tipo
                if ($data['transaction_type'] === 'sale') {
                    $salePrice = (float)($data['sale_price'] ?? 0);
                    $commission = (float)($data['commission_percentage'] ?? 5);
                    
                    if ($salePrice <= 0) {
                        throw new Exception('El precio de venta debe ser mayor a 0');
                    }
                    
                    $insertData = array_merge($insertData, [
                        'sale_price' => $salePrice,
                        'original_price' => $salePrice,
                        'commission_percentage' => $commission,
                        'commission_amount' => $salePrice * ($commission / 100),
                        'balance_pending' => $salePrice,
                        'contract_date' => date('Y-m-d'),
                        'closing_date' => !empty($data['closing_date']) ? $data['closing_date'] : null
                    ]);
                } else {
                    // Alquiler
                    $monthlyPayment = (float)($data['monthly_payment'] ?? 0);
                    $duration = (int)($data['rent_duration_months'] ?? 12);
                    $commission = (float)($data['commission_percentage'] ?? 10);
                    
                    if ($monthlyPayment <= 0) {
                        throw new Exception('El pago mensual debe ser mayor a 0');
                    }
                    
                    $totalContract = $monthlyPayment * $duration;
                    
                    $insertData = array_merge($insertData, [
                        'monthly_payment' => $monthlyPayment,
                        'rent_duration_months' => $duration,
                        'sale_price' => $totalContract,
                        'original_price' => $totalContract,
                        'commission_percentage' => $commission,
                        'commission_amount' => $monthlyPayment * ($commission / 100),
                        'balance_pending' => $totalContract,
                        'contract_date' => date('Y-m-d'),
                        'move_in_date' => !empty($data['move_in_date']) ? $data['move_in_date'] : null
                    ]);
                }
                
                // Insertar transacción
                $transactionId = db()->insert('sales_transactions', $insertData);
                
                // Actualizar estado de la propiedad
                $newPropertyStatus = ($data['transaction_type'] === 'sale') ? 'sold' : 'rented';
                db()->update('properties', 
                    ['status' => $newPropertyStatus],
                    'id = ?',
                    [$data['property_id']]
                );
                
                db()->commit();
                
                $response['success'] = true;
                $response['message'] = 'Transacción creada exitosamente';
                $response['data'] = [
                    'transaction_id' => $transactionId,
                    'transaction_code' => $transactionCode
                ];
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollback();
        }
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        error_log('Error en AJAX nueva-venta.php: ' . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}

// ============================================
// HTML - Formulario Principal
// ============================================
$pageTitle = __('sales.new.page_title');

include 'header.php';
include 'sidebar.php';
?>

<style>
:root {
    --primary: #667eea;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
}

.new-sale-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* Progress Steps */
.progress-steps {
    background: white;
    padding: 30px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.steps-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.progress-line {
    position: absolute;
    top: 20px;
    left: 0;
    height: 4px;
    background: var(--primary);
    transition: width 0.3s;
    z-index: 1;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 2;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e5e7eb;
    color: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    transition: all 0.3s;
}

.step.active .step-circle {
    background: var(--primary);
    color: white;
}

.step.completed .step-circle {
    background: var(--success);
    color: white;
}

.step-label {
    margin-top: 8px;
    font-size: 12px;
    color: #6b7280;
    font-weight: 600;
}

/* Form Card */
.form-card {
    background: white;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.form-step {
    display: none;
}

.form-step.active {
    display: block;
}

.form-title {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 8px;
}

.form-subtitle {
    color: #718096;
    margin-bottom: 30px;
}

/* Type Selection */
.type-selection {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.type-option {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 30px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.type-option:hover {
    border-color: var(--primary);
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.type-option.selected {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
}

.type-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.type-option.sale .type-icon { color: var(--success); }
.type-option.rent .type-icon { color: var(--primary); }

.type-name {
    font-size: 20px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 8px;
}

.type-description {
    color: #718096;
    font-size: 14px;
}

/* Alert Info */
.alert-info {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 25px;
    display: flex;
    align-items: start;
    gap: 12px;
}

.alert-info i {
    color: #3b82f6;
    font-size: 20px;
    margin-top: 2px;
}

/* Property Grid */
.property-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.property-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.property-card:hover {
    border-color: var(--primary);
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.property-card.selected {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}

.property-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
    background: #e5e7eb;
}

.property-body {
    padding: 15px;
}

.property-title {
    font-size: 16px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.property-ref {
    font-size: 12px;
    color: #718096;
    margin-bottom: 10px;
}

.property-features {
    display: flex;
    gap: 15px;
    font-size: 13px;
    color: #4a5568;
    margin-bottom: 12px;
}

.property-features span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.property-price {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
}

/* Client Search */
.client-search input {
    width: 100%;
    padding: 12px 15px 12px 45px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s;
}

.client-search input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Client List */
.client-list {
    margin-top: 20px;
    max-height: 500px;
    overflow-y: auto;
}

.client-item {
    padding: 15px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.client-item:hover {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.05);
}

.client-item.selected {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.1);
}

.client-name {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.client-details {
    font-size: 13px;
    color: #718096;
}

/* Agent Grid */
.agent-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.agent-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.agent-card:hover {
    border-color: var(--primary);
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.agent-card.selected {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.05);
}

.agent-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 700;
    margin: 0 auto 15px;
}

.agent-name {
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.agent-role {
    font-size: 13px;
    color: #718096;
    margin-bottom: 5px;
}

/* Form Row */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.form-group {
    margin-bottom: 0;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Summary */
.summary-section {
    background: #f9fafb;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.summary-title {
    font-size: 16px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    color: #718096;
    font-size: 14px;
}

.summary-value {
    font-weight: 600;
    color: #2d3748;
}

/* Navigation Buttons */
.form-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
}

.btn-nav {
    padding: 12px 30px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-back {
    background: #f3f4f6;
    color: #4a5568;
}

.btn-back:hover {
    background: #e5e7eb;
}

.btn-next {
    background: linear-gradient(135deg, var(--primary), #764ba2);
    color: white;
    margin-left: auto;
}

.btn-next:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-next:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-submit {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
    margin-left: auto;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
}

.loading-overlay.active {
    opacity: 1;
    visibility: visible;
}

.loading-content {
    text-align: center;
}

.spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.2);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-text {
    color: white;
    font-size: 18px;
    font-weight: 600;
}

/* Custom Modal */
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
}

.custom-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.custom-modal {
    background: white;
    border-radius: 16px;
    padding: 40px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    transform: scale(0.9);
    transition: all 0.3s;
}

.custom-modal-overlay.active .custom-modal {
    transform: scale(1);
}

.modal-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 36px;
}

.modal-icon-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.modal-icon-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.modal-icon-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 10px;
}

.modal-message {
    color: #718096;
    margin-bottom: 10px;
    font-size: 15px;
}

.code-display {
    background: #f3f4f6;
    padding: 15px;
    border-radius: 10px;
    margin: 20px 0;
}

.code-label {
    font-size: 13px;
    color: #718096;
    margin-bottom: 5px;
}

.code-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

.modal-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}

.modal-btn {
    padding: 12px 30px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.modal-btn-secondary {
    background: #f3f4f6;
    color: #4a5568;
}

.modal-btn-secondary:hover {
    background: #e5e7eb;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.modal-btn-confirm {
    background: linear-gradient(135deg, var(--primary), #764ba2);
    color: white;
}

.modal-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.modal-btn-success {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
}

.modal-btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
}

.modal-btn-primary {
    background: linear-gradient(135deg, var(--primary), #764ba2);
    color: white;
}

.modal-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

/* Animación de confetti para éxito */
@keyframes confetti-fall {
    0% {
        transform: translateY(-100vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .new-sale-container {
        padding: 15px;
    }
    
    .form-card {
        padding: 20px;
    }
    
    .property-grid,
    .agent-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .custom-modal {
        padding: 30px 20px;
    }
    
    .modal-title {
        font-size: 20px;
    }
    
    .modal-message {
        font-size: 14px;
    }
    
    .modal-buttons {
        flex-direction: column;
    }
    
    .modal-btn {
        max-width: 100%;
    }
    
    .code-value {
        font-size: 20px;
    }
}
</style>

<div class="new-sale-container">
    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="steps-bar">
            <div class="progress-line" id="progressLine"></div>
            <div class="step active" data-step="1">
                <div class="step-circle">1</div>
                <div class="step-label"><?php echo __('sales.new.steps.type'); ?></div>
            </div>
            <div class="step" data-step="2">
                <div class="step-circle">2</div>
                <div class="step-label"><?php echo __('sales.new.steps.property'); ?></div>
            </div>
            <div class="step" data-step="3">
                <div class="step-circle">3</div>
                <div class="step-label"><?php echo __('sales.new.steps.client'); ?></div>
            </div>
            <div class="step" data-step="4">
                <div class="step-circle">4</div>
                <div class="step-label"><?php echo __('sales.new.steps.agent'); ?></div>
            </div>
            <div class="step" data-step="5">
                <div class="step-circle">5</div>
                <div class="step-label"><?php echo __('sales.new.steps.details'); ?></div>
            </div>
            <div class="step" data-step="6">
                <div class="step-circle">6</div>
                <div class="step-label"><?php echo __('sales.new.steps.summary'); ?></div>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <!-- STEP 1: Transaction Type -->
        <div class="form-step active" id="step1">
            <h2 class="form-title"><?php echo __('sales.new.step1.title'); ?></h2>
            <p class="form-subtitle"><?php echo __('sales.new.step1.subtitle'); ?></p>
            
            <div class="type-selection">
                <div class="type-option sale" data-type="sale">
                    <div class="type-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="type-name"><?php echo __('sales.new.step1.sale_title'); ?></div>
                    <div class="type-description"><?php echo __('sales.new.step1.sale_description'); ?></div>
                </div>
                
                <div class="type-option rent" data-type="rent">
                    <div class="type-icon"><i class="fas fa-home"></i></div>
                    <div class="type-name"><?php echo __('sales.new.step1.rent_title'); ?></div>
                    <div class="type-description"><?php echo __('sales.new.step1.rent_description'); ?></div>
                </div>
            </div>
        </div>

        <!-- STEP 2: Property Selection -->
        <div class="form-step" id="step2">
            <h2 class="form-title"><?php echo __('sales.new.step2.title'); ?></h2>
            <p class="form-subtitle"><?php echo __('sales.new.step2.subtitle'); ?></p>
            
            <div class="alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong><?php echo __('sales.new.step2.info_title'); ?></strong><br>
                    <small><?php echo __('sales.new.step2.info_message'); ?></small>
                </div>
            </div>
            
            <div class="property-grid" id="propertyGrid">
                <!-- Properties will be loaded here via JavaScript -->
            </div>
        </div>

        <!-- STEP 3: Client Selection -->
        <div class="form-step" id="step3">
            <h2 class="form-title"><?php echo __('sales.new.step3.title'); ?></h2>
            <p class="form-subtitle"><?php echo __('sales.new.step3.subtitle'); ?></p>
            
            <div class="client-search">
                <div style="position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af;"></i>
                    <input type="text" id="clientSearch" placeholder="<?php echo __('sales.new.step3.search_placeholder'); ?>">
                </div>
            </div>
            
            <div class="client-list" id="clientList">
                <!-- Clients will be loaded here via JavaScript -->
            </div>
        </div>

        <!-- STEP 4: Agent Selection -->
        <div class="form-step" id="step4">
            <h2 class="form-title"><?php echo __('sales.new.step4.title'); ?></h2>
            <p class="form-subtitle"><?php echo __('sales.new.step4.subtitle'); ?></p>
            
            <div class="agent-grid" id="agentGrid">
                <!-- Agents will be loaded here via JavaScript -->
            </div>
        </div>

        <!-- STEP 5: Financial Details -->
        <div class="form-step" id="step5">
            <h2 class="form-title"><?php echo __('sales.new.step5.title'); ?></h2>
            <p class="form-subtitle"><?php echo __('sales.new.step5.subtitle'); ?></p>
            
            <div id="saleDetails">
                <h4 style="margin-bottom: 20px;"><?php echo __('sales.new.step5.sale_info'); ?></h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.new.step5.sale_price'); ?></label>
                        <input type="number" class="form-control" id="salePrice" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.new.step5.commission'); ?></label>
                        <input type="number" class="form-control" id="commissionPercentage" value="5" step="0.1">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.new.step5.closing_date'); ?></label>
                        <input type="date" class="form-control" id="closingDate">
                    </div>
                </div>
            </div>
            
            <div id="rentalDetails" style="display: none;">
                <h4 style="margin-bottom: 20px;"><?php echo __('sales.new.step5.rental_info'); ?></h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.new.step5.monthly_payment'); ?></label>
                        <input type="number" class="form-control" id="monthlyPayment" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.new.step5.duration'); ?></label>
                        <input type="number" class="form-control" id="rentDuration" value="12">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.new.step5.start_date'); ?></label>
                        <input type="date" class="form-control" id="moveInDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.new.step5.commission'); ?></label>
                        <input type="number" class="form-control" id="rentCommission" value="10" step="0.1">
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 6: Summary -->
        <div class="form-step" id="step6">
            <h2 class="form-title"><?php echo __('sales.new.step6.title'); ?></h2>
            <p class="form-subtitle"><?php echo __('sales.new.step6.subtitle'); ?></p>
            
            <div id="summaryContent">
                <!-- Summary will be generated here -->
            </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="form-navigation">
            <button type="button" class="btn-nav btn-back" id="btnBack" style="display: none;">
                <i class="fas fa-arrow-left"></i>
                <?php echo __('sales.new.navigation.back'); ?>
            </button>
            
            <button type="button" class="btn-nav btn-next" id="btnNext" disabled>
                <?php echo __('sales.new.navigation.next'); ?>
                <i class="fas fa-arrow-right"></i>
            </button>
            
            <button type="button" class="btn-nav btn-submit" id="btnSubmit" style="display: none;">
                <i class="fas fa-check"></i>
                <?php echo __('sales.new.navigation.submit'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <div class="loading-text"><?php echo __('sales.new.processing'); ?></div>
    </div>
</div>

<!-- Modal de Confirmación -->
<div class="custom-modal-overlay" id="confirmModal">
    <div class="custom-modal">
        <div class="modal-icon modal-icon-warning">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="modal-title"><?php echo __('sales.new.modals.confirm_title'); ?></h3>
        <p class="modal-message"><?php echo __('sales.new.modals.confirm_message'); ?></p>
        <p style="font-size: 13px; color: #718096; margin-top: 10px;">
            <?php echo __('sales.new.modals.confirm_warning'); ?>
        </p>
        <div class="modal-buttons">
            <button type="button" class="modal-btn modal-btn-secondary" onclick="closeConfirmModal()">
                <i class="fas fa-times"></i>
                <?php echo __('sales.new.modals.confirm_no'); ?>
            </button>
            <button type="button" class="modal-btn modal-btn-confirm" onclick="confirmSubmit()">
                <i class="fas fa-check"></i>
                <?php echo __('sales.new.modals.confirm_yes'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal de Éxito -->
<div class="custom-modal-overlay" id="successModal">
    <div class="custom-modal">
        <div class="modal-icon modal-icon-success">
            <i class="fas fa-check"></i>
        </div>
        <h3 class="modal-title"><?php echo __('sales.new.modals.success_title'); ?></h3>
        <p class="modal-message"><?php echo __('sales.new.modals.success_message'); ?></p>
        
        <div class="code-display">
            <div class="code-label"><?php echo __('sales.new.modals.success_code'); ?></div>
            <div class="code-value" id="transactionCode">---</div>
        </div>
        
        <div class="modal-buttons">
            <button type="button" class="modal-btn modal-btn-secondary" onclick="goToList()">
                <i class="fas fa-list"></i>
                <?php echo __('sales.new.modals.success_back'); ?>
            </button>
            <button type="button" class="modal-btn modal-btn-success" onclick="goToTransaction()">
                <i class="fas fa-eye"></i>
                <?php echo __('sales.new.modals.success_view'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Modal de Error -->
<div class="custom-modal-overlay" id="errorModal">
    <div class="custom-modal">
        <div class="modal-icon modal-icon-error">
            <i class="fas fa-times"></i>
        </div>
        <h3 class="modal-title"><?php echo __('sales.new.modals.error_title'); ?></h3>
        <p class="modal-message" id="errorMessage"><?php echo __('sales.new.modals.error_message'); ?></p>
        <div class="modal-buttons">
            <button type="button" class="modal-btn modal-btn-primary" onclick="closeErrorModal()">
                <i class="fas fa-arrow-left"></i>
                <?php echo __('sales.new.modals.error_back'); ?>
            </button>
        </div>
    </div>
</div>

<script>
// ============================================
// TRANSLATIONS FOR JAVASCRIPT
// ============================================
const translations = {
    processing: '<?php echo __('sales.new.processing'); ?>',
    loading: '<?php echo __('sales.new.loading'); ?>',
    noProperties: '<?php echo __('sales.new.step2.no_properties'); ?>',
    noPropertiesMessage: '<?php echo __('sales.new.step2.no_properties_message'); ?>',
    available: '<?php echo __('sales.new.step2.available'); ?>',
    bedrooms: '<?php echo __('sales.new.step2.bedrooms'); ?>',
    bathrooms: '<?php echo __('sales.new.step2.bathrooms'); ?>',
    noClients: '<?php echo __('sales.new.step3.no_clients'); ?>',
    noAgents: '<?php echo __('sales.new.step4.no_agents'); ?>',
    noAgentsMessage: '<?php echo __('sales.new.step4.no_agents_message'); ?>',
    activeProperties: '<?php echo __('sales.new.step4.active_properties'); ?>',
    transactionType: '<?php echo __('sales.new.step6.transaction_type'); ?>',
    propertyInfo: '<?php echo __('sales.new.step6.property_info'); ?>',
    propertyReference: '<?php echo __('sales.new.step6.property_reference'); ?>',
    propertyAddress: '<?php echo __('sales.new.step6.property_address'); ?>',
    clientInfo: '<?php echo __('sales.new.step6.client_info'); ?>',
    clientName: '<?php echo __('sales.new.step6.client_name'); ?>',
    clientPhone: '<?php echo __('sales.new.step6.client_phone'); ?>',
    clientEmail: '<?php echo __('sales.new.step6.client_email'); ?>',
    agentInfo: '<?php echo __('sales.new.step6.agent_info'); ?>',
    agentName: '<?php echo __('sales.new.step6.agent_name'); ?>',
    financialDetails: '<?php echo __('sales.new.step6.financial_details'); ?>',
    price: '<?php echo __('sales.new.step6.price'); ?>',
    commission: '<?php echo __('sales.new.step6.commission'); ?>',
    commissionAmount: '<?php echo __('sales.new.step6.commission_amount'); ?>',
    monthlyPayment: '<?php echo __('sales.new.step6.monthly_payment'); ?>',
    duration: '<?php echo __('sales.new.step6.duration'); ?>',
    months: '<?php echo __('sales.new.step6.months'); ?>',
    totalContract: '<?php echo __('sales.new.step6.total_contract'); ?>',
    dates: '<?php echo __('sales.new.step6.dates'); ?>',
    closingDate: '<?php echo __('sales.new.step6.closing_date'); ?>',
    startDate: '<?php echo __('sales.new.step6.start_date'); ?>',
    sale: '<?php echo __('sales.type.sale'); ?>',
    rent: '<?php echo __('sales.type.rent'); ?>',
    errorCreating: '<?php echo __('sales.new.messages.error_creating'); ?>',
    connectionError: '<?php echo __('sales.new.messages.connection_error'); ?>',
};

// ============================================
// STATE MANAGEMENT
// ============================================
const state = {
    currentStep: 1,
    totalSteps: 6,
    transactionData: {
        transaction_type: null,
        property_id: null,
        property_data: null,
        client_id: null,
        client_data: null,
        agent_id: null,
        agent_data: null
    }
};

// ============================================
// VARIABLES GLOBALES PARA MODALES
// ============================================
let currentTransactionId = null;

// ============================================
// MODAL FUNCTIONS
// ============================================
function openConfirmModal() {
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
}

function openSuccessModal(transactionId, transactionCode) {
    currentTransactionId = transactionId;
    document.getElementById('transactionCode').textContent = transactionCode;
    document.getElementById('successModal').classList.add('active');
    createConfetti();
}

function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('active');
}

function openErrorModal(message) {
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorModal').classList.add('active');
}

function closeErrorModal() {
    document.getElementById('errorModal').classList.remove('active');
}

function goToTransaction() {
    if (currentTransactionId) {
        window.location.href = 'ver-venta.php?id=' + currentTransactionId;
    }
}

function goToList() {
    window.location.href = 'ventas.php';
}

function createConfetti() {
    const colors = ['#667eea', '#764ba2', '#10b981', '#f59e0b', '#ef4444'];
    
    for (let i = 0; i < 50; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.top = '-10px';
            confetti.style.opacity = '1';
            confetti.style.zIndex = '9999';
            confetti.style.borderRadius = '50%';
            confetti.style.pointerEvents = 'none';
            confetti.style.animation = `confetti-fall ${2 + Math.random() * 2}s linear`;
            
            document.body.appendChild(confetti);
            
            setTimeout(() => {
                confetti.remove();
            }, 4000);
        }, i * 30);
    }
}

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando formulario nueva-venta.php');
    initializeEventListeners();
    updateProgressBar();
});

function initializeEventListeners() {
    // Type selection
    document.querySelectorAll('.type-option').forEach(option => {
        option.addEventListener('click', function() {
            selectTransactionType(this.dataset.type);
        });
    });
    
    // Client search with debounce
    let searchTimeout;
    const clientSearchInput = document.getElementById('clientSearch');
    if (clientSearchInput) {
        clientSearchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchClients(e.target.value);
            }, 300);
        });
    }
    
    // Navigation buttons
    document.getElementById('btnBack').addEventListener('click', prevStep);
    document.getElementById('btnNext').addEventListener('click', nextStep);
    document.getElementById('btnSubmit').addEventListener('click', submitTransaction);
}

// ============================================
// STEP 1: TRANSACTION TYPE
// ============================================
function selectTransactionType(type) {
    console.log('📋 Tipo de transacción seleccionado:', type);
    
    document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('selected'));
    event.target.closest('.type-option').classList.add('selected');
    
    state.transactionData.transaction_type = type;
    document.getElementById('btnNext').disabled = false;
    
    // Load properties for next step
    loadProperties(type);
}

// ============================================
// STEP 2: PROPERTY SELECTION
// ============================================
async function loadProperties(type) {
    console.log('🏠 Cargando propiedades para:', type);
    
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'load_properties');
        formData.append('transaction_type', type);
        
        const response = await fetch('nueva-venta.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('📦 Respuesta de propiedades:', data);
        
        if (data.success) {
            displayProperties(data.data);
        } else {
            console.error('❌ Error al cargar propiedades:', data.message);
            alert('Error al cargar propiedades: ' + data.message);
        }
    } catch (error) {
        console.error('❌ Error en loadProperties:', error);
        alert('Error de conexión al cargar propiedades');
    }
}

function displayProperties(properties) {
    const grid = document.getElementById('propertyGrid');
    
    console.log('🎨 Mostrando propiedades:', properties.length);
    
    if (!properties || properties.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                <div style="font-size: 64px; color: #cbd5e0; margin-bottom: 20px;">
                    <i class="fas fa-home"></i>
                </div>
                <div style="font-size: 20px; font-weight: 600; color: #2d3748; margin-bottom: 10px;">
                    ${translations.noProperties}
                </div>
                <div style="color: #718096; margin-bottom: 20px;">
                    ${translations.noPropertiesMessage}
                </div>
                <a href="propiedades.php" class="btn-modern btn-primary" style="text-decoration: none;">
                    <i class="fas fa-plus"></i>
                    Agregar Propiedad
                </a>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = properties.map(prop => {
        const price = prop.price > 0 
            ? '$' + parseFloat(prop.price).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})
            : 'Precio no establecido';
        
        const priceDisplay = (prop.operation_type === 'rent' || prop.operation_type === 'vacation_rent') 
            ? `${price}/mes`
            : price;
        
        const propJsonEscaped = JSON.stringify(prop).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        const imageUrl = prop.main_image || 'uploads/properties/default.jpg';
        const area = prop.size_sqm || 0;
        
        return `
            <div class="property-card" onclick="selectProperty(${prop.id}, this)" data-prop='${propJsonEscaped}'>
                <img src="${imageUrl}" class="property-image" alt="${prop.title}">
                <div class="property-body">
                    <div class="property-ref">${prop.reference} • ${translations.available}</div>
                    <div class="property-title">${prop.title}</div>
                    <div class="property-features">
                        <span><i class="fas fa-bed"></i> ${prop.bedrooms || 0} ${translations.bedrooms}</span>
                        <span><i class="fas fa-bath"></i> ${prop.bathrooms || 0} ${translations.bathrooms}</span>
                        <span><i class="fas fa-ruler-combined"></i> ${area}m²</span>
                    </div>
                    <div class="property-price">${priceDisplay}</div>
                </div>
            </div>
        `;
    }).join('');
}

function selectProperty(id, element) {
    console.log('🏘️ Propiedad seleccionada:', id);
    
    document.querySelectorAll('.property-card').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');
    
    const propData = JSON.parse(element.getAttribute('data-prop'));
    
    state.transactionData.property_id = id;
    state.transactionData.property_data = propData;
    
    document.getElementById('btnNext').disabled = false;
    
    console.log('✅ Propiedad guardada en estado:', propData);
}

// ============================================
// STEP 3: CLIENT SELECTION
// ============================================
async function searchClients(search = '') {
    console.log('👤 Buscando clientes:', search);
    
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'search_clients');
        formData.append('search', search);
        
        const response = await fetch('nueva-venta.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('📦 Respuesta de clientes:', data);
        
        if (data.success) {
            displayClients(data.data);
        }
    } catch (error) {
        console.error('❌ Error en searchClients:', error);
    }
}

function displayClients(clients) {
    const list = document.getElementById('clientList');
    
    if (!clients || clients.length === 0) {
        list.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #9ca3af;">
                <i class="fas fa-user-slash" style="font-size: 48px; margin-bottom: 15px;"></i>
                <div>${translations.noClients}</div>
            </div>
        `;
        return;
    }
    
    list.innerHTML = clients.map(client => {
        const clientJsonEscaped = JSON.stringify(client).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        
        return `
        <div class="client-item" onclick="selectClient(${client.id}, this)" data-client='${clientJsonEscaped}'>
            <div class="client-info">
                <div class="client-name">${client.full_name}</div>
                <div class="client-details">
                    ${client.reference} • ${client.email}${client.phone_mobile ? ' • ' + client.phone_mobile : ''}
                </div>
            </div>
            <i class="fas fa-chevron-right" style="color: #cbd5e0;"></i>
        </div>
    `}).join('');
}

function selectClient(id, element) {
    console.log('👤 Cliente seleccionado:', id);
    
    document.querySelectorAll('.client-item').forEach(item => item.classList.remove('selected'));
    element.classList.add('selected');
    
    const clientData = JSON.parse(element.getAttribute('data-client'));
    
    state.transactionData.client_id = id;
    state.transactionData.client_data = clientData;
    document.getElementById('btnNext').disabled = false;
    
    console.log('✅ Cliente guardado en estado:', clientData);
}

// ============================================
// STEP 4: AGENT SELECTION
// ============================================
async function loadAgents() {
    console.log('👨‍💼 Cargando agentes');
    
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'load_agents');
        
        const response = await fetch('nueva-venta.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('📦 Respuesta de agentes:', data);
        
        if (data.success) {
            displayAgents(data.data);
        }
    } catch (error) {
        console.error('❌ Error en loadAgents:', error);
    }
}

function displayAgents(agents) {
    const grid = document.getElementById('agentGrid');
    
    if (!agents || agents.length === 0) {
        grid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px;">
                <div style="font-size: 64px; color: #cbd5e0; margin-bottom: 20px;">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div style="font-size: 20px; font-weight: 600; color: #2d3748; margin-bottom: 10px;">
                    ${translations.noAgents}
                </div>
                <div style="color: #718096;">
                    ${translations.noAgentsMessage}
                </div>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = agents.map(agent => {
        const initials = agent.first_name.charAt(0) + agent.last_name.charAt(0);
        const agentJsonEscaped = JSON.stringify(agent).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        
        return `
            <div class="agent-card" onclick="selectAgent(${agent.id}, this)" data-agent='${agentJsonEscaped}'>
                <div class="agent-avatar">${initials}</div>
                <div class="agent-name">${agent.full_name}</div>
                <div class="agent-role">${agent.role_name}</div>
            </div>
        `;
    }).join('');
}

function selectAgent(id, element) {
    console.log('👨‍💼 Agente seleccionado:', id);
    
    document.querySelectorAll('.agent-card').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');
    
    const agentData = JSON.parse(element.getAttribute('data-agent'));
    
    state.transactionData.agent_id = id;
    state.transactionData.agent_data = agentData;
    document.getElementById('btnNext').disabled = false;
    
    console.log('✅ Agente guardado en estado:', agentData);
}

// ============================================
// STEP 5: FINANCIAL DETAILS
// ============================================
function setupFinancialFields() {
    console.log('💰 Configurando campos financieros');
    
    const isSale = state.transactionData.transaction_type === 'sale';
    
    document.getElementById('saleDetails').style.display = isSale ? 'block' : 'none';
    document.getElementById('rentalDetails').style.display = isSale ? 'none' : 'block';
    
    // Pre-llenar precio si está disponible
    if (state.transactionData.property_data && state.transactionData.property_data.price) {
        if (isSale) {
            document.getElementById('salePrice').value = state.transactionData.property_data.price;
        } else {
            document.getElementById('monthlyPayment').value = state.transactionData.property_data.price;
        }
    }
    
    // Validar campos
    validateFinancialFields();
    
    // Add event listeners
    if (isSale) {
        document.getElementById('salePrice').addEventListener('input', validateFinancialFields);
        document.getElementById('commissionPercentage').addEventListener('input', validateFinancialFields);
    } else {
        document.getElementById('monthlyPayment').addEventListener('input', validateFinancialFields);
        document.getElementById('rentDuration').addEventListener('input', validateFinancialFields);
        document.getElementById('rentCommission').addEventListener('input', validateFinancialFields);
    }
}

function validateFinancialFields() {
    const isSale = state.transactionData.transaction_type === 'sale';
    let isValid = false;
    
    if (isSale) {
        const salePrice = parseFloat(document.getElementById('salePrice').value) || 0;
        const commission = parseFloat(document.getElementById('commissionPercentage').value) || 0;
        isValid = salePrice > 0 && commission >= 0;
        
        state.transactionData.sale_price = salePrice;
        state.transactionData.commission_percentage = commission;
        state.transactionData.closing_date = document.getElementById('closingDate').value;
    } else {
        const monthlyPayment = parseFloat(document.getElementById('monthlyPayment').value) || 0;
        const duration = parseInt(document.getElementById('rentDuration').value) || 0;
        const commission = parseFloat(document.getElementById('rentCommission').value) || 0;
        isValid = monthlyPayment > 0 && duration > 0 && commission >= 0;
        
        state.transactionData.monthly_payment = monthlyPayment;
        state.transactionData.rent_duration_months = duration;
        state.transactionData.commission_percentage = commission;
        state.transactionData.move_in_date = document.getElementById('moveInDate').value;
    }
    
    document.getElementById('btnNext').disabled = !isValid;
}

// ============================================
// STEP 6: SUMMARY
// ============================================
function generateSummary() {
    console.log('📋 Generando resumen');
    
    const { transaction_type, property_data, client_data, agent_data } = state.transactionData;
    const isSale = transaction_type === 'sale';
    
    let financialHTML = '';
    
    if (isSale) {
        const salePrice = parseFloat(state.transactionData.sale_price) || 0;
        const commission = parseFloat(state.transactionData.commission_percentage) || 0;
        const commissionAmount = salePrice * (commission / 100);
        
        financialHTML = `
            <div class="summary-item">
                <span class="summary-label">${translations.price}:</span>
                <span class="summary-value">$${salePrice.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.commission}:</span>
                <span class="summary-value">${commission}% ($${commissionAmount.toLocaleString('en-US', {minimumFractionDigits: 2})})</span>
            </div>
            ${state.transactionData.closing_date ? `
            <div class="summary-item">
                <span class="summary-label">${translations.closingDate}:</span>
                <span class="summary-value">${state.transactionData.closing_date}</span>
            </div>
            ` : ''}
        `;
    } else {
        const monthlyPayment = parseFloat(state.transactionData.monthly_payment) || 0;
        const duration = parseInt(state.transactionData.rent_duration_months) || 0;
        const commission = parseFloat(state.transactionData.commission_percentage) || 0;
        const commissionAmount = monthlyPayment * (commission / 100);
        const totalContract = monthlyPayment * duration;
        
        financialHTML = `
            <div class="summary-item">
                <span class="summary-label">${translations.monthlyPayment}:</span>
                <span class="summary-value">$${monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2})}/mes</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.duration}:</span>
                <span class="summary-value">${duration} ${translations.months}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.totalContract}:</span>
                <span class="summary-value">$${totalContract.toLocaleString('en-US', {minimumFractionDigits: 2})}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.commission}:</span>
                <span class="summary-value">${commission}% ($${commissionAmount.toLocaleString('en-US', {minimumFractionDigits: 2})})</span>
            </div>
            ${state.transactionData.move_in_date ? `
            <div class="summary-item">
                <span class="summary-label">${translations.startDate}:</span>
                <span class="summary-value">${state.transactionData.move_in_date}</span>
            </div>
            ` : ''}
        `;
    }
    
    const summaryHTML = `
        <div class="summary-section">
            <div class="summary-title">
                <i class="fas fa-file-invoice"></i>
                ${translations.transactionType}
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.transactionType}:</span>
                <span class="summary-value">${isSale ? translations.sale : translations.rent}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="summary-title">
                <i class="fas fa-home"></i>
                ${translations.propertyInfo}
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.propertyReference}:</span>
                <span class="summary-value">${property_data.reference}</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.propertyAddress}:</span>
                <span class="summary-value">${property_data.address || property_data.city}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="summary-title">
                <i class="fas fa-user"></i>
                ${translations.clientInfo}
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.clientName}:</span>
                <span class="summary-value">${client_data.full_name}</span>
            </div>
            ${client_data.phone_mobile ? `
            <div class="summary-item">
                <span class="summary-label">${translations.clientPhone}:</span>
                <span class="summary-value">${client_data.phone_mobile}</span>
            </div>
            ` : ''}
            <div class="summary-item">
                <span class="summary-label">${translations.clientEmail}:</span>
                <span class="summary-value">${client_data.email}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="summary-title">
                <i class="fas fa-user-tie"></i>
                ${translations.agentInfo}
            </div>
            <div class="summary-item">
                <span class="summary-label">${translations.agentName}:</span>
                <span class="summary-value">${agent_data.full_name}</span>
            </div>
        </div>
        
        <div class="summary-section">
            <div class="summary-title">
                <i class="fas fa-dollar-sign"></i>
                ${translations.financialDetails}
            </div>
            ${financialHTML}
        </div>
    `;
    
    document.getElementById('summaryContent').innerHTML = summaryHTML;
}

// ============================================
// NAVIGATION
// ============================================
function validateCurrentStep() {
    switch (state.currentStep) {
        case 1:
            return state.transactionData.transaction_type !== null;
        case 2:
            return state.transactionData.property_id !== null;
        case 3:
            return state.transactionData.client_id !== null;
        case 4:
            return state.transactionData.agent_id !== null;
        case 5:
            return true;
        case 6:
            return true;
        default:
            return false;
    }
}

function nextStep() {
    console.log('➡️ Avanzando al siguiente paso desde:', state.currentStep);
    
    if (state.currentStep < state.totalSteps) {
        state.currentStep++;
        showStep(state.currentStep);
        
        // Load data for specific steps
        if (state.currentStep === 3) {
            searchClients();
        } else if (state.currentStep === 4) {
            loadAgents();
        } else if (state.currentStep === 5) {
            setupFinancialFields();
        } else if (state.currentStep === 6) {
            generateSummary();
        }
    }
}

function prevStep() {
    console.log('⬅️ Retrocediendo al paso anterior desde:', state.currentStep);
    
    if (state.currentStep > 1) {
        state.currentStep--;
        showStep(state.currentStep);
    }
}

function showStep(step) {
    console.log('📍 Mostrando paso:', step);
    
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
    
    // Show current step
    document.getElementById('step' + step).classList.add('active');
    
    // Update step indicators
    document.querySelectorAll('.step').forEach((s, i) => {
        s.classList.remove('active', 'completed');
        if (i + 1 < step) {
            s.classList.add('completed');
        } else if (i + 1 === step) {
            s.classList.add('active');
        }
    });
    
    // Update buttons
    document.getElementById('btnBack').style.display = step > 1 ? 'block' : 'none';
    document.getElementById('btnNext').style.display = step < state.totalSteps ? 'block' : 'none';
    document.getElementById('btnSubmit').style.display = step === state.totalSteps ? 'block' : 'none';
    
    // Validate and enable/disable next button
    document.getElementById('btnNext').disabled = !validateCurrentStep();
    
    // Update progress bar
    updateProgressBar();
}

function updateProgressBar() {
    const progress = ((state.currentStep - 1) / (state.totalSteps - 1)) * 100;
    document.getElementById('progressLine').style.width = progress + '%';
}

// ============================================
// SUBMIT TRANSACTION
// ============================================
async function submitTransaction() {
    openConfirmModal();
}

async function confirmSubmit() {
    closeConfirmModal();
    
    document.getElementById('loadingOverlay').classList.add('active');
    
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'create_transaction');
        formData.append('transaction_data', JSON.stringify(state.transactionData));
        
        console.log('📤 Enviando transacción:', state.transactionData);
        
        const response = await fetch('nueva-venta.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        console.log('📦 Respuesta del servidor:', data);
        
        document.getElementById('loadingOverlay').classList.remove('active');
        
        if (data.success) {
            openSuccessModal(data.data.transaction_id, data.data.transaction_code);
        } else {
            openErrorModal(data.message || translations.errorCreating);
        }
    } catch (error) {
        console.error('❌ Error al crear transacción:', error);
        document.getElementById('loadingOverlay').classList.remove('active');
        openErrorModal(translations.connectionError);
    }
}

// ============================================
// CERRAR MODALES AL HACER CLICK FUERA
// ============================================
document.addEventListener('click', function(e) {
    if (e.target.id === 'confirmModal') {
        closeConfirmModal();
    }
    if (e.target.id === 'successModal') {
        closeSuccessModal();
        goToTransaction();
    }
    if (e.target.id === 'errorModal') {
        closeErrorModal();
    }
});

console.log('✅ Script nueva-venta.php cargado correctamente');
</script>

<?php include 'footer.php'; ?>
<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$currentUser = getCurrentUser();

// ============================================
// AJAX HANDLERS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $response = ['success' => false, 'message' => '', 'data' => null];
        
        switch ($_POST['ajax_action']) {
            case 'search_clients':
                $search = $_POST['search'] ?? '';
                $clients = db()->select("
                    SELECT id,
                           CONCAT(first_name, ' ', last_name) as full_name,
                           reference,
                           email,
                           phone_mobile
                    FROM clients
                    WHERE is_active = 1
                    AND (CONCAT(first_name, ' ', last_name) LIKE ?
                         OR email LIKE ?
                         OR phone_mobile LIKE ?
                         OR reference LIKE ?)
                    ORDER BY first_name
                    LIMIT 50
                ", ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
                
                $response['success'] = true;
                $response['data'] = $clients;
                break;
                
            case 'get_client':
                $clientId = $_POST['client_id'] ?? 0;
                $client = db()->selectOne("
                    SELECT id,
                           CONCAT(first_name, ' ', last_name) as full_name,
                           reference,
                           email,
                           phone_mobile
                    FROM clients
                    WHERE id = ?
                ", [$clientId]);
                
                $response['success'] = true;
                $response['client'] = $client;
                break;
        }
        
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// Obtener ID de la transacción
$transactionId = $_GET['id'] ?? 0;

// Obtener datos de la transacción
$transaction = db()->selectOne("SELECT * FROM sales_transactions WHERE id = ?", [$transactionId]);

if (!$transaction) {
    setFlashMessage('error', __('sales.edit.transaction_not_found', [], 'Transacción no encontrada'));
    redirect('ventas.php');
}

// Verificar permisos
if ($currentUser['role']['name'] !== 'administrador' && 
    $transaction['agent_id'] != $currentUser['id'] && 
    $transaction['second_agent_id'] != $currentUser['id']) {
    setFlashMessage('error', __('sales.edit.no_permission', [], 'No tienes permisos para editar esta transacción'));
    redirect('ver-venta.php?id=' . $transactionId);
}

// Obtener clientes activos
$clients = db()->select("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, reference, email, phone
    FROM clients
    WHERE is_active = 1
    ORDER BY first_name
    LIMIT 200
");

// Obtener agentes activos
$agents = db()->select("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, profile_picture
    FROM users
    WHERE status = 'active' AND role_id IN (2, 3)
    ORDER BY first_name
");

// Obtener oficinas
$offices = db()->select("
    SELECT id, name, address
    FROM offices
    WHERE is_active = 1
    ORDER BY name
");

// Obtener info de la propiedad
$property = db()->selectOne("
    SELECT p.*, pt.name as type_name,
    (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image
    FROM properties p
    LEFT JOIN property_types pt ON p.property_type_id = pt.id
    WHERE p.id = ?
", [$transaction['property_id']]);

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

    .edit-sale-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    .page-header-modern {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .page-title-modern {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 5px 0;
    }

    .page-subtitle-modern {
        color: #718096;
        margin: 0;
        font-size: 14px;
    }

    .form-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 25px;
    }

    .form-card-header {
        padding: 20px 30px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .form-card-header h5 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
    }

    .form-card-body {
        padding: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #4a5568;
        font-size: 14px;
    }

    .form-label.required::after {
        content: ' *';
        color: var(--danger);
    }

    .form-control, .form-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-control:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-control:disabled {
        background: #f8f9fa;
        cursor: not-allowed;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .property-info-box {
        background: #f8f9fa;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .property-info-img {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .property-info-text h6 {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: #2d3748;
    }

    .property-info-text p {
        margin: 4px 0;
        font-size: 13px;
        color: #718096;
    }

    .input-group {
        display: flex;
        align-items: center;
    }

    .input-group-text {
        padding: 12px 16px;
        background: #f8f9fa;
        border: 2px solid #e2e8f0;
        border-right: none;
        border-radius: 8px 0 0 8px;
        font-weight: 600;
        color: #4a5568;
    }

    .input-group .form-control {
        border-radius: 0 8px 8px 0;
    }

    .calculated-field {
        background: #f0fdf4;
        border-color: var(--success);
        font-weight: 700;
        color: var(--success);
    }

    .btn-modern {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.3s;
    }

    .btn-success-modern {
        background: linear-gradient(135deg, var(--success), #059669);
        color: white;
    }

    .btn-success-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-outline-modern {
        background: white;
        border: 2px solid #e2e8f0;
        color: #4a5568;
    }

    .btn-outline-modern:hover {
        background: #f8f9fa;
        border-color: #cbd5e0;
    }

    .btn-group-modern {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .alert-warning {
        background: #fef3c7;
        border: 2px solid #fbbf24;
        border-radius: 8px;
        padding: 15px;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 20px;
        color: #92400e;
    }

    .alert-warning i {
        color: #f59e0b;
        font-size: 18px;
        margin-top: 2px;
    }
    
    /* Client Search Styles */
    .client-search-wrapper {
        position: relative;
    }
    
    .client-results-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        margin-top: 5px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        display: none;
    }
    
    .client-results-list.show {
        display: block;
    }
    
    .client-result-item {
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.2s;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .client-result-item:hover {
        background: #f9fafb;
    }
    
    .client-result-item:last-child {
        border-bottom: none;
    }
    
    .client-result-name {
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
    }
    
    .client-result-details {
        font-size: 13px;
        color: #6b7280;
    }
    

    /* Responsive */
    @media (max-width: 768px) {
        .edit-sale-container {
            padding: 15px;
        }

        .page-header-modern {
            padding: 20px;
        }

        .page-title-modern {
            font-size: 22px;
        }

        .form-card-body {
            padding: 20px;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .property-info-box {
            flex-direction: column;
            text-align: center;
        }

        .property-info-img {
            margin: 0 auto;
        }

        .btn-group-modern {
            flex-direction: column;
        }

        .btn-modern {
            width: 100%;
        }

        .input-group {
            flex-direction: column;
        }

        .input-group-text {
            width: 100%;
            border: 2px solid #e2e8f0;
            border-radius: 8px 8px 0 0;
        }

        .input-group .form-control {
            border-radius: 0 0 8px 8px;
        }
    }

    @media (max-width: 480px) {
        .page-title-modern {
            font-size: 20px;
        }

        .form-card-header h5 {
            font-size: 16px;
        }
    }
</style>

<div class="edit-sale-container">
    
    <!-- Page Header -->
    <div class="page-header-modern">
        <h1 class="page-title-modern">
            <i class="fas fa-edit" style="color: var(--primary);"></i>
            <?php echo __('sales.edit.title'); ?> #<?php echo $transaction['transaction_code'] ?? $transaction['id']; ?>
        </h1>
        <p class="page-subtitle-modern">
            <?php echo __('sales.edit.subtitle'); ?>
        </p>
    </div>

    <form action="procesar-editar-venta.php" method="POST" id="editSaleForm" enctype="multipart/form-data">
        <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
        
        <!-- Propiedad (No editable, solo vista) -->
        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-building" style="color: var(--success); font-size: 20px;"></i>
                <h5><?php echo __('sales.edit.sections.property'); ?></h5>
            </div>
            <div class="form-card-body">
                <div class="alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong><?php echo __('sales.edit.note'); ?>:</strong> <?php echo __('sales.edit.property_locked'); ?>
                    </div>
                </div>
                <div class="property-info-box">
                    <img src="<?php echo $property['main_image'] ?? 'assets/img/no-image.jpg'; ?>" 
                         alt="" class="property-info-img">
                    <div class="property-info-text">
                        <h6><?php echo htmlspecialchars($property['reference']); ?></h6>
                        <p><strong><?php echo htmlspecialchars($property['title']); ?></strong></p>
                        <p><?php echo htmlspecialchars($property['type_name']); ?> • <?php echo htmlspecialchars($property['city']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Cliente -->
        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-user" style="color: #f59e0b; font-size: 20px;"></i>
                <h5><?php echo __('sales.edit.sections.client_info'); ?></h5>
            </div>
            <div class="form-card-body">
                <div class="form-group">
                    <label class="form-label required"><?php echo __('sales.client'); ?></label>
                    <div class="client-search-wrapper">
                        <div style="position: relative; margin-bottom: 15px;">
                            <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af;"></i>
                            <input type="text" id="clientSearchInput" class="form-control" placeholder="<?php echo __('sales.edit.search_client_placeholder'); ?>" style="padding-left: 40px;">
                        </div>
                        
                        <input type="hidden" name="client_id" id="selectedClientId" value="<?php echo $transaction['client_id']; ?>" required>
                        
                        <div id="clientSelectedDisplay" style="display: none; padding: 15px; background: #f3f4f6; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong id="selectedClientName"></strong><br>
                                    <small id="selectedClientDetails" style="color: #6b7280;"></small>
                                </div>
                                <button type="button" onclick="clearClientSelection()" class="btn btn-sm" style="background: #ef4444; color: white;">
                                    <i class="fas fa-times"></i> <?php echo __('sales.edit.change'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="client-results-list" id="clientResultsList"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Agente y Oficina -->
        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-user-tie" style="color: #3b82f6; font-size: 20px;"></i>
                <h5><?php echo __('sales.edit.sections.agent_info'); ?></h5>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required"><?php echo __('sales.agent'); ?></label>
                        <select name="agent_id" class="form-select" required>
                            <option value=""><?php echo __('sales.edit.select_agent'); ?></option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>" 
                                    <?php echo ($transaction['agent_id'] == $agent['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($agent['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.second_agent'); ?></label>
                        <select name="second_agent_id" class="form-select">
                            <option value=""><?php echo __('none'); ?></option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?php echo $agent['id']; ?>" 
                                    <?php echo ($transaction['second_agent_id'] == $agent['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($agent['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.office'); ?></label>
                        <select name="office_id" class="form-select">
                            <option value=""><?php echo __('sales.edit.select_office'); ?></option>
                            <?php foreach ($offices as $office): ?>
                            <option value="<?php echo $office['id']; ?>" 
                                    <?php echo ($transaction['office_id'] == $office['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($office['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalles Financieros -->
        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-dollar-sign" style="color: var(--success); font-size: 20px;"></i>
                <h5><?php echo __('sales.edit.sections.financial_details'); ?></h5>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required"><?php echo __('sales.edit.sale_rental_price'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="sale_price" id="salePrice" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['sale_price']; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.original_price'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="original_price" id="originalPrice" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['original_price']; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><?php echo __('sales.commission_percentage'); ?></label>
                        <div class="input-group">
                            <input type="number" name="commission_percentage" id="commissionPercentage" 
                                   class="form-control" step="0.01" min="0" max="100" 
                                   value="<?php echo $transaction['commission_percentage']; ?>" required>
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.commission_amount'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="commission_amount" id="commissionAmount" 
                                   class="form-control calculated-field" step="0.01" 
                                   value="<?php echo $transaction['commission_amount']; ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Campos específicos para ALQUILER -->
                <?php if ($transaction['transaction_type'] === 'rent' || $transaction['transaction_type'] === 'vacation_rent'): ?>
                <hr style="margin: 25px 0; border-color: #e2e8f0;">
                <h6 style="color: #2d3748; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-calendar-alt"></i> <?php echo __('sales.edit.rental_details'); ?>
                </h6>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.monthly_payment'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="monthly_payment" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['monthly_payment']; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.duration_months'); ?></label>
                        <input type="number" name="rent_duration_months" class="form-control" 
                               min="1" value="<?php echo $transaction['rent_duration_months']; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.rental_end_date'); ?></label>
                        <input type="date" name="rent_end_date" class="form-control" 
                               value="<?php echo $transaction['rent_end_date'] ?? ''; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.warranty_amount'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="warranty_amount" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['warranty_amount']; ?>">
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Gastos Adicionales -->
                <hr style="margin: 25px 0; border-color: #e2e8f0;">
                <h6 style="color: #2d3748; margin-bottom: 20px; font-weight: 600;">
                    <i class="fas fa-file-invoice-dollar"></i> <?php echo __('sales.edit.additional_costs'); ?>
                </h6>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.tax_amount'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="tax_amount" id="taxAmount" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['tax_amount']; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.notary_fees'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="notary_fees" id="notaryFees" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['notary_fees']; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.other_fees'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="other_fees" id="otherFees" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $transaction['other_fees']; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.edit.total_cost'); ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="total_transaction_cost" id="totalCost" 
                                   class="form-control calculated-field" 
                                   value="<?php echo $transaction['total_transaction_cost']; ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('sales.deposit_paid'); ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="deposit_paid" class="form-control" 
                               step="0.01" min="0" value="<?php echo $transaction['deposit_paid']; ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Financiamiento -->
        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-credit-card" style="color: #8b5cf6; font-size: 20px;"></i>
                <h5><?php echo __('sales.edit.sections.financing'); ?></h5>
            </div>
            <div class="form-card-body">
                <div class="form-group">
                    <label class="form-label required"><?php echo __('sales.edit.financing_type'); ?></label>
                    <select name="financing_type" id="financingType" class="form-select" required>
                        <option value="cash" <?php echo $transaction['financing_type'] === 'cash' ? 'selected' : ''; ?>>
                            <?php echo __('sales.edit.financing.cash'); ?>
                        </option>
                        <option value="bank_loan" <?php echo $transaction['financing_type'] === 'bank_loan' ? 'selected' : ''; ?>>
                            <?php echo __('sales.edit.financing.bank_loan'); ?>
                        </option>
                        <option value="owner_financing" <?php echo $transaction['financing_type'] === 'owner_financing' ? 'selected' : ''; ?>>
                            <?php echo __('sales.edit.financing.owner_financing'); ?>
                        </option>
                        <option value="mixed" <?php echo $transaction['financing_type'] === 'mixed' ? 'selected' : ''; ?>>
                            <?php echo __('sales.edit.financing.mixed'); ?>
                        </option>
                    </select>
                </div>

                <div id="loanFields" style="<?php echo ($transaction['financing_type'] === 'bank_loan' || $transaction['financing_type'] === 'mixed') ? '' : 'display: none;'; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><?php echo __('sales.edit.bank_name'); ?></label>
                            <input type="text" name="bank_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($transaction['bank_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?php echo __('sales.edit.loan_amount'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="loan_amount" class="form-control" 
                                       step="0.01" min="0" value="<?php echo $transaction['loan_amount']; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?php echo __('sales.edit.down_payment'); ?></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="down_payment" class="form-control" 
                                       step="0.01" min="0" value="<?php echo $transaction['down_payment']; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('sales.edit.payment_method'); ?></label>
                    <input type="text" name="payment_method" class="form-control" 
                           value="<?php echo htmlspecialchars($transaction['payment_method'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Fechas y Contrato -->
        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-calendar" style="color: #f59e0b; font-size: 20px;"></i>
                <h5><?php echo __('sales.edit.sections.dates'); ?></h5>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.contract_date'); ?></label>
                        <input type="date" name="contract_date" class="form-control" 
                               value="<?php echo $transaction['contract_date']; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.closing_date'); ?></label>
                        <input type="date" name="closing_date" class="form-control" 
                               value="<?php echo $transaction['closing_date']; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?php echo __('sales.move_in_date'); ?></label>
                        <input type="date" name="move_in_date" class="form-control" 
                               value="<?php echo $transaction['move_in_date']; ?>">
                    </div>
                </div>

                <?php if ($transaction['contract_file_url']): ?>
                <div style="background: #f0fdf4; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                    <i class="fas fa-file-pdf" style="color: var(--success);"></i>
                    <strong><?php echo __('sales.edit.current_contract'); ?>:</strong>
                    <a href="<?php echo $transaction['contract_file_url']; ?>" target="_blank" 
                       style="color: var(--primary); margin-left: 10px;">
                        <?php echo __('sales.edit.view_contract'); ?>
                    </a>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label"><?php echo __('sales.edit.update_contract'); ?></label>
                    <input type="file" name="contract_file" class="form-control" accept=".pdf">
                    <small style="color: #718096; margin-top: 5px; display: block;">
                        <?php echo __('sales.edit.keep_current_contract'); ?>
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label"><?php echo __('sales.edit.additional_notes'); ?></label>
                    <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Estado -->
        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-clipboard-check" style="color: var(--success); font-size: 20px;"></i>
                <h5><?php echo __('sales.edit.sections.status'); ?></h5>
            </div>
            <div class="form-card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label required"><?php echo __('sales.edit.status'); ?></label>
                        <select name="status" class="form-select" required>
                            <option value="pending" <?php echo $transaction['status'] === 'pending' ? 'selected' : ''; ?>>
                                <?php echo __('sales.status.pending'); ?>
                            </option>
                            <option value="in_progress" <?php echo $transaction['status'] === 'in_progress' ? 'selected' : ''; ?>>
                                <?php echo __('sales.status.in_progress'); ?>
                            </option>
                            <option value="completed" <?php echo $transaction['status'] === 'completed' ? 'selected' : ''; ?>>
                                <?php echo __('sales.status.completed'); ?>
                            </option>
                            <option value="cancelled" <?php echo $transaction['status'] === 'cancelled' ? 'selected' : ''; ?>>
                                <?php echo __('sales.status.cancelled'); ?>
                            </option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><?php echo __('sales.edit.payment_status'); ?></label>
                        <select name="payment_status" class="form-select" required>
                            <option value="pending" <?php echo $transaction['payment_status'] === 'pending' ? 'selected' : ''; ?>>
                                <?php echo __('sales.payment_status.pending'); ?>
                            </option>
                            <option value="partial" <?php echo $transaction['payment_status'] === 'partial' ? 'selected' : ''; ?>>
                                <?php echo __('sales.payment_status.partial'); ?>
                            </option>
                            <option value="completed" <?php echo $transaction['payment_status'] === 'completed' ? 'selected' : ''; ?>>
                                <?php echo __('sales.payment_status.completed'); ?>
                            </option>
                            <option value="overdue" <?php echo $transaction['payment_status'] === 'overdue' ? 'selected' : ''; ?>>
                                <?php echo __('sales.payment_status.overdue'); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="form-card">
            <div class="form-card-body">
                <div class="btn-group-modern">
                    <button type="submit" class="btn-modern btn-success-modern">
                        <i class="fas fa-save"></i>
                        <?php echo __('save_changes'); ?>
                    </button>
                    <a href="ver-venta.php?id=<?php echo $transaction['id']; ?>" class="btn-modern btn-outline-modern">
                        <i class="fas fa-times"></i>
                        <?php echo __('cancel'); ?>
                    </a>
                </div>
            </div>
        </div>

    </form>

</div>

<script>
// ============ TIPO DE FINANCIAMIENTO ============
document.getElementById('financingType').addEventListener('change', function() {
    const loanFields = document.getElementById('loanFields');
    if (this.value === 'bank_loan' || this.value === 'mixed') {
        loanFields.style.display = 'block';
    } else {
        loanFields.style.display = 'none';
    }
});

// ============ CÁLCULO DE COMISIÓN ============
function calculateCommission() {
    const salePrice = parseFloat(document.getElementById('salePrice').value) || 0;
    const commissionPercentage = parseFloat(document.getElementById('commissionPercentage').value) || 0;
    const commissionAmount = (salePrice * commissionPercentage) / 100;
    
    document.getElementById('commissionAmount').value = commissionAmount.toFixed(2);
    calculateTotalCost();
}

document.getElementById('salePrice').addEventListener('input', calculateCommission);
document.getElementById('commissionPercentage').addEventListener('input', calculateCommission);

// ============ CÁLCULO DE COSTO TOTAL ============
function calculateTotalCost() {
    const salePrice = parseFloat(document.getElementById('salePrice').value) || 0;
    const taxAmount = parseFloat(document.getElementById('taxAmount').value) || 0;
    const notaryFees = parseFloat(document.getElementById('notaryFees').value) || 0;
    const otherFees = parseFloat(document.getElementById('otherFees').value) || 0;
    
    const totalCost = salePrice + taxAmount + notaryFees + otherFees;
    document.getElementById('totalCost').value = totalCost.toFixed(2);
}

document.getElementById('taxAmount').addEventListener('input', calculateTotalCost);
document.getElementById('notaryFees').addEventListener('input', calculateTotalCost);
document.getElementById('otherFees').addEventListener('input', calculateTotalCost);

// Variables para el buscador de clientes
let clientSearchTimeout = null;
let currentClientId = <?php echo $transaction['client_id']; ?>;

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    // Cargar el cliente seleccionado actual
    if (currentClientId) {
        loadCurrentClient();
    }
    
    // Event listener para el buscador
    document.getElementById('clientSearchInput').addEventListener('input', function(e) {
        clearTimeout(clientSearchTimeout);
        clientSearchTimeout = setTimeout(() => {
            searchClients(e.target.value);
        }, 300);
    });
    
    // Mostrar lista al hacer focus
    document.getElementById('clientSearchInput').addEventListener('focus', function() {
        if (this.value.trim() === '') {
            searchClients('');
        }
    });
});

// Cargar cliente actual
async function loadCurrentClient() {
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'get_client');
        formData.append('client_id', currentClientId);
        
        const response = await fetch('editar-venta.php?id=<?php echo $transactionId; ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.client) {
            displaySelectedClient(data.client);
        }
    } catch (error) {
        console.error('Error al cargar cliente:', error);
    }
}

// Buscar clientes
async function searchClients(search) {
    try {
        const formData = new FormData();
        formData.append('ajax_action', 'search_clients');
        formData.append('search', search);
        
        const response = await fetch('editar-venta.php?id=<?php echo $transactionId; ?>', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayClientResults(data.data);
        }
    } catch (error) {
        console.error('Error al buscar clientes:', error);
    }
}

// Mostrar resultados de búsqueda
function displayClientResults(clients) {
    const resultsList = document.getElementById('clientResultsList');
    
    if (!clients || clients.length === 0) {
        resultsList.innerHTML = '<div style="padding: 15px; text-align: center; color: #9ca3af;"><?php echo __('sales.edit.no_clients_found'); ?></div>';
        resultsList.classList.add('show');
        return;
    }
    
    resultsList.innerHTML = clients.map(client => `
        <div class="client-result-item" onclick="selectClient(${client.id}, '${escapeHtml(client.full_name)}', '${escapeHtml(client.reference)}', '${escapeHtml(client.email)}', '${escapeHtml(client.phone_mobile || '')}')">
            <div class="client-result-name">${escapeHtml(client.full_name)}</div>
            <div class="client-result-details">${escapeHtml(client.reference)} • ${escapeHtml(client.email)}${client.phone_mobile ? ' • ' + escapeHtml(client.phone_mobile) : ''}</div>
        </div>
    `).join('');
    
    resultsList.classList.add('show');
}

// Seleccionar cliente
function selectClient(id, name, reference, email, phone) {
    currentClientId = id;
    document.getElementById('selectedClientId').value = id;
    document.getElementById('clientResultsList').classList.remove('show');
    document.getElementById('clientSearchInput').value = '';
    
    displaySelectedClient({ id, full_name: name, reference, email, phone_mobile: phone });
}

// Mostrar cliente seleccionado
function displaySelectedClient(client) {
    document.getElementById('selectedClientName').textContent = client.full_name;
    document.getElementById('selectedClientDetails').innerHTML = `${client.reference} • ${client.email}${client.phone_mobile ? ' • ' + client.phone_mobile : ''}`;
    document.getElementById('clientSelectedDisplay').style.display = 'block';
}

// Limpiar selección
function clearClientSelection() {
    currentClientId = null;
    document.getElementById('selectedClientId').value = '';
    document.getElementById('clientSelectedDisplay').style.display = 'none';
    document.getElementById('clientSearchInput').value = '';
    document.getElementById('clientSearchInput').focus();
}

// Función auxiliar para escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Ocultar resultados al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.client-search-wrapper')) {
        document.getElementById('clientResultsList').style.display = 'none';
    }
});
</script>

<?php include 'footer.php'; ?>
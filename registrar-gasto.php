<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('register_expense', [], 'Registrar Gasto');
$currentUser = getCurrentUser();

// Obtener proyecto si viene del parámetro
$preselectedProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// Obtener proyectos activos
$projects = db()->select(
    "SELECT id, project_reference, project_name, total_budget, total_spent
     FROM restoration_projects 
     WHERE project_status IN ('Planificación', 'En Progreso')
     ORDER BY created_at DESC"
);

// Si hay proyecto preseleccionado, obtener sus detalles
$selectedProject = null;
if ($preselectedProjectId > 0) {
    $selectedProject = db()->selectOne(
        "SELECT * FROM restoration_projects WHERE id = ?",
        [$preselectedProjectId]
    );
}

// Obtener tipos de gastos
$expenseTypes = db()->select(
    "SELECT id, type_name, icon, category
     FROM expense_types 
     WHERE is_active = 1
     ORDER BY display_order, type_name"
);

// Obtener tipos de materiales
$materialTypes = db()->select(
    "SELECT id, material_name, unit_of_measure, category
     FROM material_types 
     WHERE is_active = 1
     ORDER BY display_order, material_name"
);

// Obtener proveedores/contratistas
$suppliers = db()->select(
    "SELECT id, name, contractor_type, contact_person, phone, email
     FROM contractors 
     WHERE is_active = 1
     ORDER BY name"
);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    try {
        // Validaciones
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
        $invoiceReference = trim($_POST['invoice_reference'] ?? '');
        $expenseTypeId = !empty($_POST['expense_type_id']) ? (int)$_POST['expense_type_id'] : 0;
        $materialTypeId = !empty($_POST['material_type_id']) ? (int)$_POST['material_type_id'] : null;
        $supplierId = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $quantity = !empty($_POST['quantity']) ? (float)$_POST['quantity'] : null;
        $unitOfMeasure = trim($_POST['unit_of_measure'] ?? '');
        $unitPrice = !empty($_POST['unit_price']) ? (float)$_POST['unit_price'] : 0;
        $taxPercentage = !empty($_POST['tax_percentage']) ? (float)$_POST['tax_percentage'] : 0;
        $paymentMethod = $_POST['payment_method'] ?? 'Efectivo';
        $paymentStatus = $_POST['payment_status'] ?? 'Pendiente';
        $notes = trim($_POST['notes'] ?? '');
        
        // Validaciones básicas
        if ($projectId <= 0) {
            $errors[] = __('select_project', [], 'Debe seleccionar un proyecto');
        }
        
        if ($expenseTypeId <= 0) {
            $errors[] = __('select_expense_type', [], 'Debe seleccionar un tipo de gasto');
        }
        
        if (empty($description)) {
            $errors[] = __('description_required', [], 'La descripción es requerida');
        }
        
        // Calcular montos
        $subtotal = $quantity && $unitPrice ? ($quantity * $unitPrice) : $unitPrice;
        $taxAmount = ($subtotal * $taxPercentage) / 100;
        $totalAmount = $subtotal + $taxAmount;
        
        if (empty($errors)) {
            db()->beginTransaction();
            
            // Insertar gasto
            $expenseId = db()->insert('project_expenses', [
                'project_id' => $projectId,
                'expense_date' => $expenseDate,
                'invoice_reference' => $invoiceReference,
                'expense_type_id' => $expenseTypeId,
                'material_type_id' => $materialTypeId,
                'supplier_id' => $supplierId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_of_measure' => $unitOfMeasure,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_percentage' => $taxPercentage,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'notes' => $notes,
                'created_by' => $currentUser['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Actualizar total gastado del proyecto
            db()->query(
                "UPDATE restoration_projects 
                 SET total_spent = total_spent + ?,
                     updated_at = NOW()
                 WHERE id = ?",
                [$totalAmount, $projectId]
            );
            
            // Registrar en log de actividad
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'create',
                'entity_type' => 'project_expense',
                'entity_id' => $expenseId,
                'description' => "Gasto registrado: {$description} - $" . number_format($totalAmount, 2),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Subir archivo si existe
            if (!empty($_FILES['receipt_file']['name'])) {
                $upload = uploadFile($_FILES['receipt_file'], DOCUMENTS_PATH);
                if ($upload['success']) {
                    db()->update('project_expenses', [
                        'receipt_file_path' => $upload['filepath']
                    ], 'id = ?', [$expenseId]);
                }
            }
            
            db()->commit();
            
            setFlashMessage('success', __('expense_registered', [], 'Gasto registrado exitosamente'));
            redirect('ver-obra.php?id=' . $projectId);
            
        }
        
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $errors[] = __('error_registering_expense', [], 'Error al registrar el gasto') . ': ' . $e->getMessage();
    }
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    :root {
        --primary: #667eea;
        --primary-dark: #5a67d8;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --purple: #8b5cf6;
    }

    .expense-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    /* Page Header */
    .page-header-expense {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .page-header-expense::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .page-title-expense {
        font-size: 28px;
        font-weight: 700;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 1;
    }

    .page-subtitle-expense {
        font-size: 14px;
        opacity: 0.9;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .btn-back-expense {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
    }

    .btn-back-expense:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    /* Project Info Card */
    .project-info-card {
        background: white;
        padding: 24px;
        border-radius: 16px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid var(--primary);
    }

    .project-info-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .project-info-title {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .project-info-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        background: #ede9fe;
        color: #7c3aed;
    }

    .project-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
    }

    .project-info-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .project-info-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
    }

    .project-info-value {
        font-size: 16px;
        color: #1f2937;
        font-weight: 700;
    }

    /* Form Card */
    .form-card-expense {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .form-section-expense {
        padding: 28px;
        border-bottom: 1px solid #e5e7eb;
    }

    .form-section-expense:last-child {
        border-bottom: none;
    }

    .form-section-title {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f3f4f6;
    }

    .form-section-title i {
        color: var(--primary);
        font-size: 20px;
    }

    .form-row-expense {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group-expense {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-label-expense {
        font-weight: 600;
        color: #374151;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-label-expense.required::after {
        content: '*';
        color: var(--danger);
        margin-left: 2px;
    }

    .form-label-expense i {
        color: var(--primary);
        font-size: 14px;
    }

    .form-input-expense, .form-select-expense, .form-textarea-expense {
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-input-expense:focus, 
    .form-select-expense:focus, 
    .form-textarea-expense:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .form-textarea-expense {
        resize: vertical;
        min-height: 80px;
    }

    .form-help-expense {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }

    /* Calculator Section */
    .calculator-card {
        background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        padding: 24px;
        border-radius: 12px;
        border: 2px solid #e5e7eb;
    }

    .calculator-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .calculator-row:last-child {
        border-bottom: none;
        padding-top: 16px;
        margin-top: 8px;
        border-top: 2px solid #e5e7eb;
    }

    .calculator-label {
        font-size: 14px;
        color: #6b7280;
        font-weight: 600;
    }

    .calculator-value {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
    }

    .calculator-row:last-child .calculator-label {
        font-size: 16px;
        color: #1f2937;
    }

    .calculator-row:last-child .calculator-value {
        font-size: 24px;
        color: var(--primary);
    }

    /* File Upload */
    .file-upload-area {
        border: 2px dashed #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .file-upload-area:hover {
        border-color: var(--primary);
        background: #f9fafb;
    }

    .file-upload-icon {
        font-size: 48px;
        color: #d1d5db;
        margin-bottom: 12px;
    }

    .file-upload-text {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .file-upload-hint {
        font-size: 12px;
        color: #9ca3af;
    }

    .file-upload-input {
        display: none;
    }

    /* Alert Box */
    .alert-expense {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-left: 4px solid;
    }

    .alert-expense.error {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }

    .alert-expense.warning {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #92400e;
    }

    .alert-expense.info {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }

    /* Action Buttons */
    .form-actions {
        padding: 24px 28px;
        background: #f9fafb;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .btn-submit-expense {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }

    .btn-submit-expense:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-cancel-expense {
        background: white;
        border: 2px solid #e5e7eb;
        color: #6b7280;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-cancel-expense:hover {
        border-color: var(--danger);
        color: var(--danger);
        background: #fef2f2;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .expense-container {
            padding: 15px;
        }

        .page-header-expense {
            flex-direction: column;
            text-align: center;
            gap: 16px;
        }

        .form-row-expense {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn-submit-expense,
        .btn-cancel-expense {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="expense-container">
    
    <!-- Page Header -->
    <div class="page-header-expense">
        <div>
            <h1 class="page-title-expense">
                <i class="fas fa-receipt"></i>
                <?php echo __('register_expense', [], 'Registrar Gasto'); ?>
            </h1>
            <p class="page-subtitle-expense">
                <?php echo __('register_expense_subtitle', [], 'Registra un nuevo gasto para el proyecto seleccionado'); ?>
            </p>
        </div>
        <a href="<?php echo $preselectedProjectId > 0 ? 'ver-obra.php?id=' . $preselectedProjectId : 'obras.php'; ?>" 
           class="btn-back-expense">
            <i class="fas fa-arrow-left"></i>
            <?php echo __('back', [], 'Volver'); ?>
        </a>
    </div>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
    <div class="alert-expense error">
        <i class="fas fa-exclamation-circle" style="font-size: 20px;"></i>
        <div>
            <strong><?php echo __('errors_found', [], 'Se encontraron errores'); ?>:</strong>
            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Project Info (if preselected) -->
    <?php if ($selectedProject): ?>
    <div class="project-info-card">
        <div class="project-info-header">
            <div class="project-info-title">
                <i class="fas fa-hard-hat"></i>
                <?php echo htmlspecialchars($selectedProject['project_name']); ?>
            </div>
            <span class="project-info-badge">
                <?php echo htmlspecialchars($selectedProject['project_reference']); ?>
            </span>
        </div>
        <div class="project-info-grid">
            <div class="project-info-item">
                <span class="project-info-label"><?php echo __('projects.budget', [], 'Presupuesto'); ?></span>
                <span class="project-info-value">$<?php echo number_format($selectedProject['total_budget'], 2); ?></span>
            </div>
            <div class="project-info-item">
                <span class="project-info-label"><?php echo __('projects.spent', [], 'Gastado'); ?></span>
                <span class="project-info-value" style="color: var(--danger);">
                    $<?php echo number_format($selectedProject['total_spent'], 2); ?>
                </span>
            </div>
            <div class="project-info-item">
                <span class="project-info-label"><?php echo __('available', [], 'Disponible'); ?></span>
                <span class="project-info-value" style="color: var(--success);">
                    $<?php echo number_format($selectedProject['total_budget'] - $selectedProject['total_spent'], 2); ?>
                </span>
            </div>
            <div class="project-info-item">
                <span class="project-info-label"><?php echo __('budget_used', [], '% Utilizado'); ?></span>
                <span class="project-info-value">
                    <?php 
                    $percentUsed = $selectedProject['total_budget'] > 0 
                        ? ($selectedProject['total_spent'] / $selectedProject['total_budget']) * 100 
                        : 0;
                    echo number_format($percentUsed, 1); 
                    ?>%
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" enctype="multipart/form-data" id="expenseForm">
        <div class="form-card-expense">
            
            <!-- Section: Basic Info -->
            <div class="form-section-expense">
                <h3 class="form-section-title">
                    <i class="fas fa-info-circle"></i>
                    <?php echo __('basic_info', [], 'Información Básica'); ?>
                </h3>

                <div class="form-row-expense">
                    <div class="form-group-expense">
                        <label class="form-label-expense required">
                            <i class="fas fa-hard-hat"></i>
                            <?php echo __('project', [], 'Proyecto'); ?>
                        </label>
                        <select name="project_id" 
                                id="projectSelect" 
                                class="form-select-expense" 
                                required
                                onchange="updateProjectInfo(this.value)">
                            <option value=""><?php echo __('select_project', [], 'Seleccione un proyecto'); ?></option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" 
                                    <?php echo $preselectedProjectId == $project['id'] ? 'selected' : ''; ?>
                                    data-budget="<?php echo $project['total_budget']; ?>"
                                    data-spent="<?php echo $project['total_spent']; ?>">
                                <?php echo htmlspecialchars($project['project_reference'] . ' - ' . $project['project_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense required">
                            <i class="fas fa-calendar"></i>
                            <?php echo __('expense_date', [], 'Fecha del Gasto'); ?>
                        </label>
                        <input type="date" 
                               name="expense_date" 
                               class="form-input-expense" 
                               value="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-file-invoice"></i>
                            <?php echo __('invoice_reference', [], 'Referencia de Factura'); ?>
                        </label>
                        <input type="text" 
                               name="invoice_reference" 
                               class="form-input-expense" 
                               placeholder="<?php echo __('invoice_ref_placeholder', [], 'Ej: FAC-2025-001'); ?>">
                    </div>
                </div>
            </div>

            <!-- Section: Expense Details -->
            <div class="form-section-expense">
                <h3 class="form-section-title">
                    <i class="fas fa-clipboard-list"></i>
                    <?php echo __('expense_details', [], 'Detalles del Gasto'); ?>
                </h3>

                <div class="form-row-expense">
                    <div class="form-group-expense">
                        <label class="form-label-expense required">
                            <i class="fas fa-tag"></i>
                            <?php echo __('expense_type', [], 'Tipo de Gasto'); ?>
                        </label>
                        <select name="expense_type_id" class="form-select-expense" required>
                            <option value=""><?php echo __('select_type', [], 'Seleccione un tipo'); ?></option>
                            <?php foreach ($expenseTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['type_name']); ?>
                                <?php if ($type['category']): ?>
                                - <?php echo htmlspecialchars($type['category']); ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-cubes"></i>
                            <?php echo __('material_type', [], 'Tipo de Material'); ?>
                        </label>
                        <select name="material_type_id" 
                                id="materialSelect" 
                                class="form-select-expense"
                                onchange="updateUnitOfMeasure(this)">
                            <option value=""><?php echo __('select_material', [], 'Seleccione un material (opcional)'); ?></option>
                            <?php foreach ($materialTypes as $material): ?>
                            <option value="<?php echo $material['id']; ?>"
                                    data-unit="<?php echo htmlspecialchars($material['unit_of_measure']); ?>">
                                <?php echo htmlspecialchars($material['material_name']); ?>
                                (<?php echo htmlspecialchars($material['unit_of_measure']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-truck"></i>
                            <?php echo __('supplier', [], 'Proveedor/Contratista'); ?>
                        </label>
                        <select name="supplier_id" class="form-select-expense">
                            <option value=""><?php echo __('select_supplier', [], 'Seleccione un proveedor (opcional)'); ?></option>
                            <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['name']); ?>
                                <?php if ($supplier['contractor_type']): ?>
                                - <?php echo htmlspecialchars($supplier['contractor_type']); ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row-expense">
                    <div class="form-group-expense" style="grid-column: 1 / -1;">
                        <label class="form-label-expense required">
                            <i class="fas fa-align-left"></i>
                            <?php echo __('description', [], 'Descripción'); ?>
                        </label>
                        <textarea name="description" 
                                  class="form-textarea-expense" 
                                  placeholder="<?php echo __('expense_description_placeholder', [], 'Describe detalladamente el gasto realizado...'); ?>"
                                  required></textarea>
                    </div>
                </div>
            </div>

            <!-- Section: Amounts -->
            <div class="form-section-expense">
                <h3 class="form-section-title">
                    <i class="fas fa-calculator"></i>
                    <?php echo __('amounts_calculation', [], 'Montos y Cálculo'); ?>
                </h3>

                <div class="form-row-expense">
                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-sort-numeric-up"></i>
                            <?php echo __('quantity', [], 'Cantidad'); ?>
                        </label>
                        <input type="number" 
                               name="quantity" 
                               id="quantity"
                               class="form-input-expense" 
                               step="0.01"
                               min="0"
                               placeholder="0.00"
                               oninput="calculateTotal()">
                        <span class="form-help-expense">
                            <?php echo __('quantity_help', [], 'Opcional: cantidad de unidades'); ?>
                        </span>
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-ruler"></i>
                            <?php echo __('unit_of_measure', [], 'Unidad de Medida'); ?>
                        </label>
                        <input type="text" 
                               name="unit_of_measure" 
                               id="unitOfMeasure"
                               class="form-input-expense" 
                               placeholder="<?php echo __('unit_placeholder', [], 'Ej: m², unidades, kg'); ?>">
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense required">
                            <i class="fas fa-dollar-sign"></i>
                            <?php echo __('unit_price', [], 'Precio Unitario'); ?>
                        </label>
                        <input type="number" 
                               name="unit_price" 
                               id="unitPrice"
                               class="form-input-expense" 
                               step="0.01"
                               min="0"
                               placeholder="0.00"
                               required
                               oninput="calculateTotal()">
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-percentage"></i>
                            <?php echo __('tax_percentage', [], 'Impuesto (%)'); ?>
                        </label>
                        <input type="number" 
                               name="tax_percentage" 
                               id="taxPercentage"
                               class="form-input-expense" 
                               step="0.01"
                               min="0"
                               max="100"
                               value="18"
                               placeholder="18"
                               oninput="calculateTotal()">
                    </div>
                </div>

                <!-- Calculator -->
                <div class="calculator-card">
                    <div class="calculator-row">
                        <span class="calculator-label"><?php echo __('subtotal', [], 'Subtotal'); ?>:</span>
                        <span class="calculator-value" id="displaySubtotal">$0.00</span>
                    </div>
                    <div class="calculator-row">
                        <span class="calculator-label"><?php echo __('tax', [], 'Impuesto'); ?>:</span>
                        <span class="calculator-value" id="displayTax">$0.00</span>
                    </div>
                    <div class="calculator-row">
                        <span class="calculator-label"><?php echo __('total', [], 'TOTAL'); ?>:</span>
                        <span class="calculator-value" id="displayTotal">$0.00</span>
                    </div>
                </div>
            </div>

            <!-- Section: Payment -->
            <div class="form-section-expense">
                <h3 class="form-section-title">
                    <i class="fas fa-credit-card"></i>
                    <?php echo __('payment_info', [], 'Información de Pago'); ?>
                </h3>

                <div class="form-row-expense">
                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-money-bill-wave"></i>
                            <?php echo __('payment_method', [], 'Método de Pago'); ?>
                        </label>
                        <select name="payment_method" class="form-select-expense">
                            <option value="Efectivo"><?php echo __('cash', [], 'Efectivo'); ?></option>
                            <option value="Transferencia"><?php echo __('transfer', [], 'Transferencia'); ?></option>
                            <option value="Cheque"><?php echo __('check', [], 'Cheque'); ?></option>
                            <option value="Tarjeta Crédito"><?php echo __('credit_card', [], 'Tarjeta de Crédito'); ?></option>
                            <option value="Tarjeta Débito"><?php echo __('debit_card', [], 'Tarjeta de Débito'); ?></option>
                        </select>
                    </div>

                    <div class="form-group-expense">
                        <label class="form-label-expense">
                            <i class="fas fa-check-circle"></i>
                            <?php echo __('payment_status', [], 'Estado de Pago'); ?>
                        </label>
                        <select name="payment_status" class="form-select-expense">
                            <option value="Pendiente"><?php echo __('pending', [], 'Pendiente'); ?></option>
                            <option value="Pagado"><?php echo __('paid', [], 'Pagado'); ?></option>
                            <option value="Parcial"><?php echo __('partial', [], 'Parcial'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-row-expense">
                    <div class="form-group-expense" style="grid-column: 1 / -1;">
                        <label class="form-label-expense">
                            <i class="fas fa-sticky-note"></i>
                            <?php echo __('notes', [], 'Notas Adicionales'); ?>
                        </label>
                        <textarea name="notes" 
                                  class="form-textarea-expense" 
                                  placeholder="<?php echo __('notes_placeholder', [], 'Observaciones o notas adicionales sobre este gasto...'); ?>"></textarea>
                    </div>
                </div>
            </div>

            <!-- Section: Receipt -->
            <div class="form-section-expense">
                <h3 class="form-section-title">
                    <i class="fas fa-file-upload"></i>
                    <?php echo __('receipt_file', [], 'Comprobante/Factura'); ?>
                </h3>

                <div class="file-upload-area" onclick="document.getElementById('receiptFile').click()">
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text">
                        <?php echo __('click_to_upload', [], 'Haz clic para subir un archivo'); ?>
                    </div>
                    <div class="file-upload-hint">
                        <?php echo __('file_format_hint', [], 'PDF, JPG, PNG - Máximo 10MB'); ?>
                    </div>
                    <input type="file" 
                           name="receipt_file" 
                           id="receiptFile"
                           class="file-upload-input"
                           accept=".pdf,.jpg,.jpeg,.png"
                           onchange="displayFileName(this)">
                </div>
                <div id="fileNameDisplay" style="margin-top: 12px; font-size: 14px; color: var(--success); font-weight: 600;"></div>
            </div>

        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="<?php echo $preselectedProjectId > 0 ? 'ver-obra.php?id=' . $preselectedProjectId : 'obras.php'; ?>" 
               class="btn-cancel-expense">
                <i class="fas fa-times"></i>
                <?php echo __('cancel', [], 'Cancelar'); ?>
            </a>
            <button type="submit" class="btn-submit-expense">
                <i class="fas fa-save"></i>
                <?php echo __('register_expense', [], 'Registrar Gasto'); ?>
            </button>
        </div>
    </form>

</div>

<script>
// Calculate total amount
function calculateTotal() {
    const quantity = parseFloat(document.getElementById('quantity').value) || 0;
    const unitPrice = parseFloat(document.getElementById('unitPrice').value) || 0;
    const taxPercentage = parseFloat(document.getElementById('taxPercentage').value) || 0;
    
    let subtotal = quantity > 0 ? (quantity * unitPrice) : unitPrice;
    let taxAmount = (subtotal * taxPercentage) / 100;
    let total = subtotal + taxAmount;
    
    document.getElementById('displaySubtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('displayTax').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('displayTotal').textContent = '$' + total.toFixed(2);
}

// Update unit of measure when material is selected
function updateUnitOfMeasure(select) {
    const selectedOption = select.options[select.selectedIndex];
    const unit = selectedOption.getAttribute('data-unit');
    if (unit) {
        document.getElementById('unitOfMeasure').value = unit;
    }
}

// Display selected file name
function displayFileName(input) {
    const display = document.getElementById('fileNameDisplay');
    if (input.files && input.files[0]) {
        display.innerHTML = '<i class="fas fa-check-circle"></i> ' + input.files[0].name;
    }
}

// Update project info (optional feature)
function updateProjectInfo(projectId) {
    if (!projectId) return;
    
    const select = document.getElementById('projectSelect');
    const option = select.options[select.selectedIndex];
    const budget = parseFloat(option.getAttribute('data-budget')) || 0;
    const spent = parseFloat(option.getAttribute('data-spent')) || 0;
    
    // You can update UI to show project budget info
    console.log('Project Budget:', budget, 'Spent:', spent);
}

// Form validation before submit
document.getElementById('expenseForm').addEventListener('submit', function(e) {
    const total = parseFloat(document.getElementById('displayTotal').textContent.replace('$', ''));
    
    if (total <= 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: '<?php echo __('error', [], 'Error'); ?>',
            text: '<?php echo __('total_must_be_greater', [], 'El monto total debe ser mayor a cero'); ?>'
        });
        return false;
    }
    
    // Show loading
    Swal.fire({
        title: '<?php echo __('saving', [], 'Guardando...'); ?>',
        text: '<?php echo __('please_wait', [], 'Por favor espera'); ?>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
});

// Initialize calculator on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php include 'footer.php'; ?>
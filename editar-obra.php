<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('projects.edit_project', [], 'Editar Proyecto');
$currentUser = getCurrentUser();

// Obtener ID del proyecto
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    setFlashMessage('error', __('project_not_found', [], 'Proyecto no encontrado'));
    redirect('obras.php');
}

// Obtener datos del proyecto
$project = db()->selectOne(
    "SELECT * FROM restoration_projects WHERE id = ?",
    [$projectId]
);

if (!$project) {
    setFlashMessage('error', __('project_not_found', [], 'Proyecto no encontrado'));
    redirect('obras.php');
}

// Verificar permisos (solo admin o creador del proyecto)
if ($currentUser['role']['name'] !== 'administrador' && $project['created_by'] != $currentUser['id']) {
    setFlashMessage('error', __('no_permissions', [], 'No tienes permisos para editar este proyecto'));
    redirect('obras.php');
}

// Obtener propiedades disponibles
$properties = db()->select(
    "SELECT id, reference, title, address 
     FROM properties 
     ORDER BY reference DESC"
);

// Obtener clientes
$clients = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
     FROM clients 
     WHERE is_active = 1
     ORDER BY first_name, last_name"
);

// Obtener total de gastos del proyecto
$projectExpenses = db()->selectOne(
    "SELECT 
        COUNT(*) as expense_count,
        COALESCE(SUM(total_amount), 0) as total_expenses
     FROM project_expenses 
     WHERE project_id = ?",
    [$projectId]
);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    try {
        // Validaciones
        $projectName = trim($_POST['project_name'] ?? '');
        $projectReference = trim($_POST['project_reference'] ?? '');
        $restorationType = $_POST['restoration_type'] ?? '';
        $propertyId = !empty($_POST['property_id']) ? (int)$_POST['property_id'] : null;
        $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
        $address = trim($_POST['address'] ?? '');
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $estimatedStartDate = $_POST['estimated_start_date'] ?? null;
        $estimatedEndDate = $_POST['estimated_end_date'] ?? null;
        $actualStartDate = $_POST['actual_start_date'] ?? null;
        $actualEndDate = $_POST['actual_end_date'] ?? null;
        $estimatedDuration = !empty($_POST['estimated_duration']) ? (int)$_POST['estimated_duration'] : null;
        $description = trim($_POST['description'] ?? '');
        $projectStatus = $_POST['project_status'] ?? 'Planificación';
        $overallProgress = !empty($_POST['overall_progress']) ? (float)$_POST['overall_progress'] : 0;
        
        // Presupuestos
        $initialPropertyCost = !empty($_POST['initial_property_cost']) ? (float)$_POST['initial_property_cost'] : 0;
        $budgetLabor = !empty($_POST['budget_labor']) ? (float)$_POST['budget_labor'] : 0;
        $budgetMaterials = !empty($_POST['budget_materials']) ? (float)$_POST['budget_materials'] : 0;
        $budgetEquipment = !empty($_POST['budget_equipment']) ? (float)$_POST['budget_equipment'] : 0;
        $budgetPermits = !empty($_POST['budget_permits']) ? (float)$_POST['budget_permits'] : 0;
        $budgetProfessional = !empty($_POST['budget_professional_services']) ? (float)$_POST['budget_professional_services'] : 0;
        $budgetContingency = !empty($_POST['budget_contingency']) ? (float)$_POST['budget_contingency'] : 0;
        $budgetOther = !empty($_POST['budget_other']) ? (float)$_POST['budget_other'] : 0;
        $toleranceMargin = !empty($_POST['tolerance_margin']) ? (float)$_POST['tolerance_margin'] : 10;
        
        // Calcular totales
        $totalBudget = $budgetLabor + $budgetMaterials + $budgetEquipment + 
                       $budgetPermits + $budgetProfessional + $budgetContingency + $budgetOther;
        $totalInvestment = $initialPropertyCost + $totalBudget;
        
        // Validaciones básicas
        if (empty($projectName)) {
            $errors[] = __('project_name_required', [], 'El nombre del proyecto es requerido');
        }
        
        if (empty($projectReference)) {
            $errors[] = __('project_reference_required', [], 'La referencia del proyecto es requerida');
        }
        
        if (empty($restorationType)) {
            $errors[] = __('restoration_type_required', [], 'El tipo de restauración es requerido');
        }
        
        // Verificar referencia única (excluyendo el proyecto actual)
        $existingRef = db()->selectValue(
            "SELECT id FROM restoration_projects WHERE project_reference = ? AND id != ?",
            [$projectReference, $projectId]
        );
        if ($existingRef) {
            $errors[] = __('reference_already_exists', [], 'Ya existe un proyecto con esa referencia');
        }
        
        if (empty($errors)) {
            db()->beginTransaction();
            
            db()->update('restoration_projects', [
                'property_id' => $propertyId,
                'project_name' => $projectName,
                'project_reference' => $projectReference,
                'restoration_type' => $restorationType,
                'client_id' => $clientId,
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'estimated_start_date' => $estimatedStartDate,
                'estimated_end_date' => $estimatedEndDate,
                'actual_start_date' => $actualStartDate,
                'actual_end_date' => $actualEndDate,
                'estimated_duration' => $estimatedDuration,
                'description' => $description,
                'project_status' => $projectStatus,
                'overall_progress' => $overallProgress,
                'initial_property_cost' => $initialPropertyCost,
                'total_budget' => $totalBudget,
                'budget_labor' => $budgetLabor,
                'budget_materials' => $budgetMaterials,
                'budget_equipment' => $budgetEquipment,
                'budget_permits' => $budgetPermits,
                'budget_professional_services' => $budgetProfessional,
                'budget_contingency' => $budgetContingency,
                'budget_other' => $budgetOther,
                'tolerance_margin' => $toleranceMargin,
                'total_investment' => $totalInvestment,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$projectId]);
            
            // Registrar en log de actividad
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'restoration_project',
                'entity_id' => $projectId,
                'description' => "Proyecto '{$projectName}' actualizado",
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            db()->commit();
            
            setFlashMessage('success', __('project_updated', [], 'Proyecto actualizado exitosamente'));
            redirect('ver-obra.php?id=' . $projectId);
            
        }
        
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        $errors[] = __('error_updating_project', [], 'Error al actualizar el proyecto') . ': ' . $e->getMessage();
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

    .edit-project-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    /* Page Header */
    .page-header-edit {
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

    .page-header-edit::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .page-title-edit {
        font-size: 28px;
        font-weight: 700;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 1;
    }

    .page-subtitle-edit {
        font-size: 14px;
        opacity: 0.9;
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .header-actions {
        display: flex;
        gap: 12px;
        position: relative;
        z-index: 1;
    }

    .btn-header {
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    .btn-view {
        background: white;
        color: var(--primary);
    }

    .btn-view:hover {
        background: #f3f4f6;
        color: var(--primary-dark);
    }

    /* Stats Bar */
    .stats-bar-edit {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-item-edit {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .stat-icon-edit {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-content-edit {
        flex: 1;
    }

    .stat-label-edit {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .stat-value-edit {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
    }

    /* Alert Box */
    .alert-edit {
        padding: 16px 20px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        border-left: 4px solid;
    }

    .alert-edit.error {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }

    .alert-edit.warning {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #92400e;
    }

    .alert-edit.info {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }

    /* Form Card */
    .form-card-edit {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 24px;
    }

    /* Tabs */
    .tabs-edit {
        display: flex;
        background: linear-gradient(to bottom, #f8f9fa 0%, white 100%);
        border-bottom: 2px solid #e5e7eb;
        padding: 0 24px;
        gap: 8px;
        overflow-x: auto;
    }

    .tab-edit {
        padding: 16px 24px;
        border: none;
        background: transparent;
        color: #6b7280;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        border-bottom: 3px solid transparent;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-edit:hover {
        color: var(--primary);
        background: rgba(102, 126, 234, 0.05);
    }

    .tab-edit.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        background: rgba(102, 126, 234, 0.05);
    }

    .tab-content-edit {
        display: none;
        padding: 28px;
    }

    .tab-content-edit.active {
        display: block;
    }

    /* Form Elements */
    .form-section-edit {
        margin-bottom: 28px;
    }

    .form-section-title-edit {
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

    .form-section-title-edit i {
        color: var(--primary);
    }

    .form-row-edit {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group-edit {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-label-edit {
        font-weight: 600;
        color: #374151;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-label-edit.required::after {
        content: '*';
        color: var(--danger);
    }

    .form-label-edit i {
        color: var(--primary);
    }

    .form-input-edit, .form-select-edit, .form-textarea-edit {
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-input-edit:focus, 
    .form-select-edit:focus, 
    .form-textarea-edit:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .form-textarea-edit {
        resize: vertical;
        min-height: 100px;
    }

    .form-help-edit {
        font-size: 12px;
        color: #6b7280;
    }

    /* Budget Calculator */
    .budget-calculator {
        background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        padding: 24px;
        border-radius: 12px;
        border: 2px solid #e5e7eb;
    }

    .budget-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .budget-row:last-child {
        border-bottom: none;
        padding-top: 16px;
        margin-top: 8px;
        border-top: 2px solid #e5e7eb;
    }

    .budget-label {
        font-size: 14px;
        color: #6b7280;
        font-weight: 600;
    }

    .budget-value {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
    }

    .budget-row:last-child .budget-label {
        font-size: 16px;
        color: #1f2937;
    }

    .budget-row:last-child .budget-value {
        font-size: 24px;
        color: var(--primary);
    }

    /* Progress Bar */
    .progress-section {
        margin: 20px 0;
    }

    .progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
    }

    .progress-bar-container {
        height: 12px;
        background: #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        transition: width 0.5s ease;
    }

    /* Form Actions */
    .form-actions-edit {
        padding: 24px 28px;
        background: #f9fafb;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        border-top: 2px solid #e5e7eb;
    }

    .btn-submit-edit {
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

    .btn-submit-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-cancel-edit {
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

    .btn-cancel-edit:hover {
        border-color: var(--danger);
        color: var(--danger);
        background: #fef2f2;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .edit-project-container {
            padding: 15px;
        }

        .page-header-edit {
            flex-direction: column;
            text-align: center;
            gap: 16px;
        }

        .header-actions {
            width: 100%;
            flex-direction: column;
        }

        .btn-header {
            justify-content: center;
        }

        .form-row-edit {
            grid-template-columns: 1fr;
        }

        .tabs-edit {
            padding: 0 12px;
        }

        .tab-content-edit {
            padding: 20px 16px;
        }

        .form-actions-edit {
            flex-direction: column;
        }

        .btn-submit-edit,
        .btn-cancel-edit {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="edit-project-container">
    
    <!-- Page Header -->
    <div class="page-header-edit">
        <div>
            <h1 class="page-title-edit">
                <i class="fas fa-edit"></i>
                <?php echo __('projects.edit_project', [], 'Editar Proyecto'); ?>
            </h1>
            <p class="page-subtitle-edit">
                <?php echo htmlspecialchars($project['project_reference'] . ' - ' . $project['project_name']); ?>
            </p>
        </div>
        <div class="header-actions">
            <a href="obras.php" class="btn-header btn-back">
                <i class="fas fa-arrow-left"></i>
                <?php echo __('back_to_list', [], 'Volver al Listado'); ?>
            </a>
            <a href="ver-obra.php?id=<?php echo $projectId; ?>" class="btn-header btn-view">
                <i class="fas fa-eye"></i>
                <?php echo __('view', [], 'Ver Proyecto'); ?>
            </a>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar-edit">
        <div class="stat-item-edit">
            <div class="stat-icon-edit" style="background: #dbeafe; color: #1e40af;">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-content-edit">
                <div class="stat-label-edit"><?php echo __('projects.budget', [], 'Presupuesto'); ?></div>
                <div class="stat-value-edit">$<?php echo number_format($project['total_budget'], 2); ?></div>
            </div>
        </div>

        <div class="stat-item-edit">
            <div class="stat-icon-edit" style="background: #fee2e2; color: #991b1b;">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content-edit">
                <div class="stat-label-edit"><?php echo __('projects.spent', [], 'Gastado'); ?></div>
                <div class="stat-value-edit">$<?php echo number_format($project['total_spent'], 2); ?></div>
            </div>
        </div>

        <div class="stat-item-edit">
            <div class="stat-icon-edit" style="background: #d1fae5; color: #065f46;">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-content-edit">
                <div class="stat-label-edit"><?php echo __('available', [], 'Disponible'); ?></div>
                <div class="stat-value-edit">
                    $<?php echo number_format($project['total_budget'] - $project['total_spent'], 2); ?>
                </div>
            </div>
        </div>

        <div class="stat-item-edit">
            <div class="stat-icon-edit" style="background: #fef3c7; color: #92400e;">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-content-edit">
                <div class="stat-label-edit"><?php echo __('expenses', [], 'Gastos'); ?></div>
                <div class="stat-value-edit"><?php echo $projectExpenses['expense_count']; ?></div>
            </div>
        </div>
    </div>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
    <div class="alert-edit error">
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

    <!-- Form -->
    <form method="POST" id="editProjectForm">
        <div class="form-card-edit">
            
            <!-- Tabs -->
            <div class="tabs-edit">
                <button type="button" class="tab-edit active" onclick="switchTab(0)">
                    <i class="fas fa-info-circle"></i>
                    <?php echo __('basic_info', [], 'Información Básica'); ?>
                </button>
                <button type="button" class="tab-edit" onclick="switchTab(1)">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo __('dates_progress', [], 'Fechas y Progreso'); ?>
                </button>
                <button type="button" class="tab-edit" onclick="switchTab(2)">
                    <i class="fas fa-dollar-sign"></i>
                    <?php echo __('budget', [], 'Presupuesto'); ?>
                </button>
                <button type="button" class="tab-edit" onclick="switchTab(3)">
                    <i class="fas fa-cog"></i>
                    <?php echo __('advanced', [], 'Avanzado'); ?>
                </button>
            </div>

            <!-- Tab Content 1: Basic Info -->
            <div class="tab-content-edit active" id="tab-0">
                <div class="form-section-edit">
                    <h3 class="form-section-title-edit">
                        <i class="fas fa-file-alt"></i>
                        <?php echo __('basic_info', [], 'Información Básica'); ?>
                    </h3>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit required">
                                <i class="fas fa-signature"></i>
                                <?php echo __('projects.project_name', [], 'Nombre del Proyecto'); ?>
                            </label>
                            <input type="text" 
                                   name="project_name" 
                                   class="form-input-edit" 
                                   value="<?php echo htmlspecialchars($project['project_name']); ?>"
                                   required>
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit required">
                                <i class="fas fa-hashtag"></i>
                                <?php echo __('reference', [], 'Referencia'); ?>
                            </label>
                            <input type="text" 
                                   name="project_reference" 
                                   class="form-input-edit" 
                                   value="<?php echo htmlspecialchars($project['project_reference']); ?>"
                                   required>
                            <span class="form-help-edit">
                                <?php echo __('unique_reference', [], 'Referencia única para identificar el proyecto'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit required">
                                <i class="fas fa-tools"></i>
                                <?php echo __('restoration_type', [], 'Tipo de Restauración'); ?>
                            </label>
                            <select name="restoration_type" class="form-select-edit" required>
                                <option value=""><?php echo __('select_type', [], 'Seleccione un tipo'); ?></option>
                                <?php
                                $types = [
                                    'Completa' => __('full_restoration', [], 'Restauración Completa'),
                                    'Parcial' => __('partial_restoration', [], 'Restauración Parcial'),
                                    'Remodelación Interior' => __('interior_remodeling', [], 'Remodelación Interior'),
                                    'Remodelación Exterior' => __('exterior_remodeling', [], 'Remodelación Exterior'),
                                    'Ampliación' => __('expansion', [], 'Ampliación'),
                                    'Mantenimiento Mayor' => __('major_maintenance', [], 'Mantenimiento Mayor')
                                ];
                                foreach ($types as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" 
                                            <?php echo $project['restoration_type'] === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-flag"></i>
                                <?php echo __('projects.status', [], 'Estado del Proyecto'); ?>
                            </label>
                            <select name="project_status" class="form-select-edit">
                                <?php
                                $statuses = [
                                    'Planificación' => __('planning', [], 'Planificación'),
                                    'En Progreso' => __('in_progress', [], 'En Progreso'),
                                    'Pausado' => __('paused', [], 'Pausado'),
                                    'Completado' => __('completed', [], 'Completado'),
                                    'Cancelado' => __('cancelled', [], 'Cancelado')
                                ];
                                foreach ($statuses as $value => $label):
                                ?>
                                    <option value="<?php echo $value; ?>" 
                                            <?php echo $project['project_status'] === $value ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-home"></i>
                                <?php echo __('associated_property', [], 'Propiedad Asociada'); ?>
                            </label>
                            <select name="property_id" class="form-select-edit">
                                <option value=""><?php echo __('select_property_optional', [], 'Seleccione una propiedad (opcional)'); ?></option>
                                <?php foreach ($properties as $property): ?>
                                <option value="<?php echo $property['id']; ?>" 
                                        <?php echo $project['property_id'] == $property['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($property['reference'] . ' - ' . $property['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-user"></i>
                                <?php echo __('client_owner', [], 'Cliente/Propietario'); ?>
                            </label>
                            <select name="client_id" class="form-select-edit">
                                <option value=""><?php echo __('select_client_optional', [], 'Seleccione un cliente (opcional)'); ?></option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"
                                        <?php echo $project['client_id'] == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-edit">
                        <div class="form-group-edit" style="grid-column: 1 / -1;">
                            <label class="form-label-edit">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo __('construction_address', [], 'Dirección de la Obra'); ?>
                            </label>
                            <input type="text" 
                                   name="address" 
                                   class="form-input-edit" 
                                   value="<?php echo htmlspecialchars($project['address'] ?? ''); ?>"
                                   placeholder="<?php echo __('full_address', [], 'Dirección completa'); ?>">
                        </div>
                    </div>

                    <div class="form-row-edit">
                        <div class="form-group-edit" style="grid-column: 1 / -1;">
                            <label class="form-label-edit">
                                <i class="fas fa-align-left"></i>
                                <?php echo __('description', [], 'Descripción'); ?>
                            </label>
                            <textarea name="description" 
                                      class="form-textarea-edit"
                                      placeholder="<?php echo __('project_description_placeholder', [], 'Describe los detalles del proyecto de restauración...'); ?>"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content 2: Dates & Progress -->
            <div class="tab-content-edit" id="tab-1">
                <div class="form-section-edit">
                    <h3 class="form-section-title-edit">
                        <i class="fas fa-calendar-check"></i>
                        <?php echo __('estimated_dates', [], 'Fechas Estimadas'); ?>
                    </h3>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-calendar-day"></i>
                                <?php echo __('start_date', [], 'Fecha de Inicio'); ?>
                            </label>
                            <input type="date" 
                                   name="estimated_start_date" 
                                   class="form-input-edit"
                                   value="<?php echo $project['estimated_start_date']; ?>">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-calendar-day"></i>
                                <?php echo __('end_date', [], 'Fecha de Fin'); ?>
                            </label>
                            <input type="date" 
                                   name="estimated_end_date" 
                                   class="form-input-edit"
                                   value="<?php echo $project['estimated_end_date']; ?>">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-clock"></i>
                                <?php echo __('duration_days', [], 'Duración (días)'); ?>
                            </label>
                            <input type="number" 
                                   name="estimated_duration" 
                                   class="form-input-edit"
                                   value="<?php echo $project['estimated_duration']; ?>"
                                   min="0"
                                   placeholder="0">
                        </div>
                    </div>
                </div>

                <div class="form-section-edit">
                    <h3 class="form-section-title-edit">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo __('actual_dates', [], 'Fechas Reales'); ?>
                    </h3>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-play-circle"></i>
                                <?php echo __('actual_start_date', [], 'Inicio Real'); ?>
                            </label>
                            <input type="date" 
                                   name="actual_start_date" 
                                   class="form-input-edit"
                                   value="<?php echo $project['actual_start_date']; ?>">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-stop-circle"></i>
                                <?php echo __('actual_end_date', [], 'Fin Real'); ?>
                            </label>
                            <input type="date" 
                                   name="actual_end_date" 
                                   class="form-input-edit"
                                   value="<?php echo $project['actual_end_date']; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section-edit">
                    <h3 class="form-section-title-edit">
                        <i class="fas fa-tasks"></i>
                        <?php echo __('projects.progress', [], 'Progreso'); ?>
                    </h3>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-percentage"></i>
                                <?php echo __('overall_progress', [], 'Progreso General (%)'); ?>
                            </label>
                            <input type="number" 
                                   name="overall_progress" 
                                   id="progressInput"
                                   class="form-input-edit"
                                   value="<?php echo $project['overall_progress']; ?>"
                                   min="0"
                                   max="100"
                                   step="0.1"
                                   placeholder="0"
                                   oninput="updateProgressBar()">
                        </div>
                    </div>

                    <div class="progress-section">
                        <div class="progress-label">
                            <span><?php echo __('visual_progress', [], 'Progreso Visual'); ?></span>
                            <span id="progressDisplay"><?php echo number_format($project['overall_progress'], 1); ?>%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" 
                                 id="progressBar" 
                                 style="width: <?php echo $project['overall_progress']; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content 3: Budget -->
            <div class="tab-content-edit" id="tab-2">
                <div class="form-section-edit">
                    <h3 class="form-section-title-edit">
                        <i class="fas fa-dollar-sign"></i>
                        <?php echo __('budget_breakdown', [], 'Desglose de Presupuesto'); ?>
                    </h3>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-home"></i>
                                <?php echo __('initial_property_cost', [], 'Costo Inicial Propiedad'); ?>
                            </label>
                            <input type="number" 
                                   name="initial_property_cost" 
                                   class="form-input-edit"
                                   value="<?php echo $project['initial_property_cost']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-users"></i>
                                <?php echo __('projects.labor', [], 'Mano de Obra'); ?>
                            </label>
                            <input type="number" 
                                   name="budget_labor" 
                                   class="form-input-edit budget-input"
                                   value="<?php echo $project['budget_labor']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-cubes"></i>
                                <?php echo __('projects.materials', [], 'Materiales'); ?>
                            </label>
                            <input type="number" 
                                   name="budget_materials" 
                                   class="form-input-edit budget-input"
                                   value="<?php echo $project['budget_materials']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-tools"></i>
                                <?php echo __('equipment', [], 'Equipos'); ?>
                            </label>
                            <input type="number" 
                                   name="budget_equipment" 
                                   class="form-input-edit budget-input"
                                   value="<?php echo $project['budget_equipment']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-file-contract"></i>
                                <?php echo __('permits', [], 'Permisos'); ?>
                            </label>
                            <input type="number" 
                                   name="budget_permits" 
                                   class="form-input-edit budget-input"
                                   value="<?php echo $project['budget_permits']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-user-tie"></i>
                                <?php echo __('professional_services', [], 'Servicios Profesionales'); ?>
                            </label>
                            <input type="number" 
                                   name="budget_professional_services" 
                                   class="form-input-edit budget-input"
                                   value="<?php echo $project['budget_professional_services']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-shield-alt"></i>
                                <?php echo __('contingency', [], 'Contingencia'); ?>
                            </label>
                            <input type="number" 
                                   name="budget_contingency" 
                                   class="form-input-edit budget-input"
                                   value="<?php echo $project['budget_contingency']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-ellipsis-h"></i>
                                <?php echo __('other', [], 'Otros'); ?>
                            </label>
                            <input type="number" 
                                   name="budget_other" 
                                   class="form-input-edit budget-input"
                                   value="<?php echo $project['budget_other']; ?>"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00"
                                   oninput="calculateBudgetTotal()">
                        </div>
                    </div>

                    <!-- Budget Calculator -->
                    <div class="budget-calculator">
                        <div class="budget-row">
                            <span class="budget-label"><?php echo __('construction_budget', [], 'Presupuesto de Construcción'); ?>:</span>
                            <span class="budget-value" id="displayConstructionBudget">$0.00</span>
                        </div>
                        <div class="budget-row">
                            <span class="budget-label"><?php echo __('initial_property_cost', [], 'Costo Inicial Propiedad'); ?>:</span>
                            <span class="budget-value" id="displayPropertyCost">$0.00</span>
                        </div>
                        <div class="budget-row">
                            <span class="budget-label"><?php echo __('total_investment', [], 'INVERSIÓN TOTAL'); ?>:</span>
                            <span class="budget-value" id="displayTotalInvestment">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content 4: Advanced -->
            <div class="tab-content-edit" id="tab-3">
                <div class="form-section-edit">
                    <h3 class="form-section-title-edit">
                        <i class="fas fa-sliders-h"></i>
                        <?php echo __('advanced_settings', [], 'Configuración Avanzada'); ?>
                    </h3>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-percentage"></i>
                                <?php echo __('tolerance_margin', [], 'Margen de Tolerancia (%)'); ?>
                            </label>
                            <input type="number" 
                                   name="tolerance_margin" 
                                   class="form-input-edit"
                                   value="<?php echo $project['tolerance_margin']; ?>"
                                   step="0.1"
                                   min="0"
                                   max="100"
                                   placeholder="10">
                            <span class="form-help-edit">
                                <?php echo __('tolerance_help', [], 'Porcentaje permitido de sobrecosto antes de generar alerta'); ?>
                            </span>
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-map-pin"></i>
                                <?php echo __('latitude', [], 'Latitud'); ?>
                            </label>
                            <input type="number" 
                                   name="latitude" 
                                   class="form-input-edit"
                                   value="<?php echo $project['latitude']; ?>"
                                   step="0.000001"
                                   placeholder="18.4861">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-map-pin"></i>
                                <?php echo __('longitude', [], 'Longitud'); ?>
                            </label>
                            <input type="number" 
                                   name="longitude" 
                                   class="form-input-edit"
                                   value="<?php echo $project['longitude']; ?>"
                                   step="0.000001"
                                   placeholder="-69.9312">
                        </div>
                    </div>
                </div>

                <div class="form-section-edit">
                    <h3 class="form-section-title-edit">
                        <i class="fas fa-info-circle"></i>
                        <?php echo __('system_info', [], 'Información del Sistema'); ?>
                    </h3>

                    <div class="form-row-edit">
                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-calendar-plus"></i>
                                <?php echo __('created_at', [], 'Fecha de Creación'); ?>
                            </label>
                            <input type="text" 
                                   class="form-input-edit" 
                                   value="<?php echo date('d/m/Y H:i', strtotime($project['created_at'])); ?>"
                                   disabled
                                   style="background: #f3f4f6; cursor: not-allowed;">
                        </div>

                        <div class="form-group-edit">
                            <label class="form-label-edit">
                                <i class="fas fa-calendar-check"></i>
                                <?php echo __('last_update', [], 'Última Actualización'); ?>
                            </label>
                            <input type="text" 
                                   class="form-input-edit" 
                                   value="<?php echo $project['updated_at'] ? date('d/m/Y H:i', strtotime($project['updated_at'])) : '-'; ?>"
                                   disabled
                                   style="background: #f3f4f6; cursor: not-allowed;">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Form Actions -->
        <div class="form-actions-edit">
            <a href="ver-obra.php?id=<?php echo $projectId; ?>" class="btn-cancel-edit">
                <i class="fas fa-times"></i>
                <?php echo __('cancel', [], 'Cancelar'); ?>
            </a>
            <button type="submit" class="btn-submit-edit">
                <i class="fas fa-save"></i>
                <?php echo __('save_changes', [], 'Guardar Cambios'); ?>
            </button>
        </div>
    </form>

</div>

<script>
// Tab switching
let currentTab = 0;

function switchTab(tabIndex) {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab-edit').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content-edit').forEach(content => content.classList.remove('active'));
    
    // Add active class to selected tab and content
    document.querySelectorAll('.tab-edit')[tabIndex].classList.add('active');
    document.getElementById(`tab-${tabIndex}`).classList.add('active');
    
    currentTab = tabIndex;
}

// Budget calculator
function calculateBudgetTotal() {
    const propertyInput = document.querySelector('input[name="initial_property_cost"]');
    const propertyCost = parseFloat(propertyInput?.value || 0);
    
    let constructionBudget = 0;
    document.querySelectorAll('.budget-input').forEach(input => {
        constructionBudget += parseFloat(input.value || 0);
    });
    
    const totalInvestment = propertyCost + constructionBudget;
    
    document.getElementById('displayConstructionBudget').textContent = '$' + constructionBudget.toFixed(2);
    document.getElementById('displayPropertyCost').textContent = '$' + propertyCost.toFixed(2);
    document.getElementById('displayTotalInvestment').textContent = '$' + totalInvestment.toFixed(2);
}

// Progress bar updater
function updateProgressBar() {
    const progress = parseFloat(document.getElementById('progressInput').value || 0);
    document.getElementById('progressBar').style.width = progress + '%';
    document.getElementById('progressDisplay').textContent = progress.toFixed(1) + '%';
}

// Form validation
document.getElementById('editProjectForm').addEventListener('submit', function(e) {
    const projectName = document.querySelector('input[name="project_name"]').value.trim();
    const projectReference = document.querySelector('input[name="project_reference"]').value.trim();
    const restorationType = document.querySelector('select[name="restoration_type"]').value;
    
    if (!projectName || !projectReference || !restorationType) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: '<?php echo __('error', [], 'Error'); ?>',
            text: '<?php echo __('fill_required_fields', [], 'Por favor completa todos los campos requeridos'); ?>'
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

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateBudgetTotal();
    updateProgressBar();
});
</script>

<?php include 'footer.php'; ?>
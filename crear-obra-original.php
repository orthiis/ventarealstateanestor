<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Crear Nuevo Proyecto de Restauración';
$currentPage = 'obras.php';

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
     WHERE status = 'active'
     ORDER BY first_name, last_name"
);

// Generar referencia automática
$lastReference = db()->selectValue(
    "SELECT project_reference 
     FROM restoration_projects 
     ORDER BY id DESC 
     LIMIT 1"
);

if ($lastReference && preg_match('/^OBRA-(\d+)$/', $lastReference, $matches)) {
    $nextNumber = intval($matches[1]) + 1;
} else {
    $nextNumber = 1;
}
$suggestedReference = 'OBRA-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
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
    $estimatedDuration = !empty($_POST['estimated_duration']) ? (int)$_POST['estimated_duration'] : null;
    $description = trim($_POST['description'] ?? '');
    
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
    
    $totalBudget = $budgetLabor + $budgetMaterials + $budgetEquipment + $budgetPermits + 
                   $budgetProfessional + $budgetContingency + $budgetOther;
    
    // Validar campos requeridos
    if (empty($projectName)) {
        $errors[] = "El nombre del proyecto es obligatorio";
    }
    if (empty($projectReference)) {
        $errors[] = "La referencia del proyecto es obligatoria";
    }
    if (empty($restorationType)) {
        $errors[] = "El tipo de restauración es obligatorio";
    }
    
    // Verificar referencia única
    $existingRef = db()->selectValue(
        "SELECT id FROM restoration_projects WHERE project_reference = ?",
        [$projectReference]
    );
    if ($existingRef) {
        $errors[] = "Ya existe un proyecto con esa referencia";
    }
    
    if (empty($errors)) {
        try {
            $projectId = db()->insert('restoration_projects', [
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
                'estimated_duration' => $estimatedDuration,
                'description' => $description,
                'project_status' => 'Planificación',
                'overall_progress' => 0,
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
                'total_spent' => 0,
                'total_investment' => $initialPropertyCost,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Registrar en log de actividad
            db()->insert('activity_log', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'create',
                'entity_type' => 'restoration_project',
                'entity_id' => $projectId,
                'description' => "Proyecto de restauración '{$projectName}' creado",
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $_SESSION['success_message'] = "Proyecto creado exitosamente";
            header("Location: ver-obra.php?id={$projectId}");
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Error al crear el proyecto: " . $e->getMessage();
        }
    }
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    /* Container principal - SIN margin-left */
    .crear-obra-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .form-card-crear {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .form-header-crear {
        padding: 24px 32px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .form-header-crear h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .form-body-crear {
        padding: 32px;
    }
    
    .form-section-crear {
        margin-bottom: 36px;
    }
    
    .form-section-title-crear {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-section-title-crear i {
        color: #667eea;
    }
    
    .form-row-crear {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-group-crear {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .form-group-crear.full-width {
        grid-column: 1 / -1;
    }
    
    .form-label-crear {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }
    
    .form-label-crear.required::after {
        content: '*';
        color: #ef4444;
        margin-left: 4px;
    }
    
    .form-input-crear,
    .form-select-crear,
    .form-textarea-crear {
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
        font-family: 'Inter', sans-serif;
        width: 100%;
    }
    
    .form-input-crear:focus,
    .form-select-crear:focus,
    .form-textarea-crear:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-textarea-crear {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-help-crear {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    
    .budget-summary-crear {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 2px solid #3b82f6;
        border-radius: 12px;
        padding: 24px;
        margin-top: 20px;
    }
    
    .budget-summary-title-crear {
        font-size: 16px;
        font-weight: 700;
        color: #1e40af;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .budget-item-crear {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #bfdbfe;
    }
    
    .budget-item-crear:last-child {
        border-bottom: none;
        margin-top: 8px;
        padding-top: 16px;
        border-top: 2px solid #3b82f6;
        font-weight: 700;
        font-size: 18px;
    }
    
    .budget-label-crear {
        color: #1e40af;
        font-weight: 500;
    }
    
    .budget-value-crear {
        color: #1e3a8a;
        font-weight: 700;
    }
    
    .alert-crear {
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    
    .alert-danger-crear {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    
    .alert-danger-crear i {
        color: #dc2626;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .form-actions-crear {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding-top: 24px;
        border-top: 1px solid #e5e7eb;
    }
    
    .btn-primary-crear {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 14px 32px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-primary-crear:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .btn-secondary-crear {
        background: #f3f4f6;
        color: #4b5563;
        padding: 14px 32px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary-crear:hover {
        background: #e5e7eb;
        color: #4b5563;
    }
    
    @media (max-width: 768px) {
        .crear-obra-container {
            padding: 16px;
        }
        
        .form-body-crear {
            padding: 20px;
        }
        
        .form-row-crear {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .form-actions-crear {
            flex-direction: column-reverse;
        }
        
        .form-actions-crear button,
        .form-actions-crear a {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="crear-obra-container">
    
    <div class="form-card-crear">
        <div class="form-header-crear">
            <h2>
                <i class="fas fa-plus-circle"></i>
                Crear Nuevo Proyecto de Restauración
            </h2>
        </div>
        
        <div class="form-body-crear">
            
            <?php if (!empty($errors)): ?>
                <div class="alert-crear alert-danger-crear">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Se encontraron los siguientes errores:</strong>
                        <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="createProjectForm">
                
                <!-- Información General -->
                <div class="form-section-crear">
                    <h3 class="form-section-title-crear">
                        <i class="fas fa-info-circle"></i>
                        Información General
                    </h3>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear">
                            <label class="form-label-crear required">Nombre del Proyecto</label>
                            <input type="text" 
                                   name="project_name" 
                                   class="form-input-crear" 
                                   placeholder="Ej: Restauración Casa Colonial Centro"
                                   value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear required">Referencia del Proyecto</label>
                            <input type="text" 
                                   name="project_reference" 
                                   class="form-input-crear" 
                                   placeholder="Ej: OBRA-0001"
                                   value="<?php echo htmlspecialchars($_POST['project_reference'] ?? $suggestedReference); ?>"
                                   required>
                            <small class="form-help-crear">Referencia única para identificar el proyecto</small>
                        </div>
                    </div>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear">
                            <label class="form-label-crear required">Tipo de Restauración</label>
                            <select name="restoration_type" class="form-select-crear" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Completa" <?php echo ($_POST['restoration_type'] ?? '') === 'Completa' ? 'selected' : ''; ?>>Restauración Completa</option>
                                <option value="Parcial" <?php echo ($_POST['restoration_type'] ?? '') === 'Parcial' ? 'selected' : ''; ?>>Restauración Parcial</option>
                                <option value="Remodelación Interior" <?php echo ($_POST['restoration_type'] ?? '') === 'Remodelación Interior' ? 'selected' : ''; ?>>Remodelación Interior</option>
                                <option value="Remodelación Exterior" <?php echo ($_POST['restoration_type'] ?? '') === 'Remodelación Exterior' ? 'selected' : ''; ?>>Remodelación Exterior</option>
                                <option value="Ampliación" <?php echo ($_POST['restoration_type'] ?? '') === 'Ampliación' ? 'selected' : ''; ?>>Ampliación</option>
                                <option value="Mantenimiento Mayor" <?php echo ($_POST['restoration_type'] ?? '') === 'Mantenimiento Mayor' ? 'selected' : ''; ?>>Mantenimiento Mayor</option>
                            </select>
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Propiedad Asociada</label>
                            <select name="property_id" class="form-select-crear">
                                <option value="">Seleccione una propiedad (opcional)</option>
                                <?php foreach ($properties as $property): ?>
                                    <option value="<?php echo $property['id']; ?>" 
                                            <?php echo ($_POST['property_id'] ?? '') == $property['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($property['reference'] . ' - ' . $property['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear">
                            <label class="form-label-crear">Cliente/Propietario</label>
                            <select name="client_id" class="form-select-crear">
                                <option value="">Seleccione un cliente (opcional)</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>"
                                            <?php echo ($_POST['client_id'] ?? '') == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Dirección de la Obra</label>
                            <input type="text" 
                                   name="address" 
                                   class="form-input-crear" 
                                   placeholder="Dirección completa"
                                   value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group-crear full-width">
                        <label class="form-label-crear">Descripción del Proyecto</label>
                        <textarea name="description" 
                                  class="form-textarea-crear" 
                                  placeholder="Describe los detalles del proyecto de restauración..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Fechas y Duración -->
                <div class="form-section-crear">
                    <h3 class="form-section-title-crear">
                        <i class="fas fa-calendar-alt"></i>
                        Fechas y Duración
                    </h3>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear">
                            <label class="form-label-crear">Fecha de Inicio Estimada</label>
                            <input type="date" 
                                   name="estimated_start_date" 
                                   class="form-input-crear"
                                   value="<?php echo htmlspecialchars($_POST['estimated_start_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Fecha de Finalización Estimada</label>
                            <input type="date" 
                                   name="estimated_end_date" 
                                   class="form-input-crear"
                                   value="<?php echo htmlspecialchars($_POST['estimated_end_date'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Duración Estimada (días)</label>
                            <input type="number" 
                                   name="estimated_duration" 
                                   class="form-input-crear" 
                                   min="1"
                                   placeholder="Ej: 90"
                                   value="<?php echo htmlspecialchars($_POST['estimated_duration'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Presupuesto -->
                <div class="form-section-crear">
                    <h3 class="form-section-title-crear">
                        <i class="fas fa-money-bill-wave"></i>
                        Presupuesto del Proyecto
                    </h3>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear full-width">
                            <label class="form-label-crear">Costo Inicial de la Propiedad</label>
                            <input type="number" 
                                   name="initial_property_cost" 
                                   class="form-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['initial_property_cost'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                            <small class="form-help-crear">Monto pagado por la compra de la propiedad</small>
                        </div>
                    </div>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear">
                            <label class="form-label-crear">Presupuesto Mano de Obra</label>
                            <input type="number" 
                                   name="budget_labor" 
                                   class="form-input-crear budget-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['budget_labor'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Presupuesto Materiales</label>
                            <input type="number" 
                                   name="budget_materials" 
                                   class="form-input-crear budget-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['budget_materials'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Presupuesto Equipos</label>
                            <input type="number" 
                                   name="budget_equipment" 
                                   class="form-input-crear budget-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['budget_equipment'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                        </div>
                    </div>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear">
                            <label class="form-label-crear">Presupuesto Permisos</label>
                            <input type="number" 
                                   name="budget_permits" 
                                   class="form-input-crear budget-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['budget_permits'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Presupuesto Servicios Profesionales</label>
                            <input type="number" 
                                   name="budget_professional_services" 
                                   class="form-input-crear budget-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['budget_professional_services'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Presupuesto Contingencia</label>
                            <input type="number" 
                                   name="budget_contingency" 
                                   class="form-input-crear budget-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['budget_contingency'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                        </div>
                    </div>
                    
                    <div class="form-row-crear">
                        <div class="form-group-crear">
                            <label class="form-label-crear">Presupuesto Otros</label>
                            <input type="number" 
                                   name="budget_other" 
                                   class="form-input-crear budget-input-crear" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['budget_other'] ?? ''); ?>"
                                   onchange="calculateTotalCrear()">
                        </div>
                        
                        <div class="form-group-crear">
                            <label class="form-label-crear">Margen de Tolerancia (%)</label>
                            <input type="number" 
                                   name="tolerance_margin" 
                                   class="form-input-crear" 
                                   min="0"
                                   max="100"
                                   step="0.1"
                                   placeholder="10"
                                   value="<?php echo htmlspecialchars($_POST['tolerance_margin'] ?? '10'); ?>">
                            <small class="form-help-crear">Porcentaje permitido de sobrecosto</small>
                        </div>
                    </div>
                    
                    <!-- Resumen de Presupuesto -->
                    <div class="budget-summary-crear">
                        <div class="budget-summary-title-crear">
                            <i class="fas fa-calculator"></i>
                            Resumen de Presupuesto
                        </div>
                        <div class="budget-item-crear">
                            <span class="budget-label-crear">Costo Inicial Propiedad:</span>
                            <span class="budget-value-crear" id="summaryInitialCrear">$0.00</span>
                        </div>
                        <div class="budget-item-crear">
                            <span class="budget-label-crear">Presupuesto Restauración:</span>
                            <span class="budget-value-crear" id="summaryRestorationCrear">$0.00</span>
                        </div>
                        <div class="budget-item-crear">
                            <span class="budget-label-crear">Inversión Total Estimada:</span>
                            <span class="budget-value-crear" id="summaryTotalCrear">$0.00</span>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de Acción -->
                <div class="form-actions-crear">
                    <a href="obras.php" class="btn-secondary-crear">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary-crear">
                        <i class="fas fa-save"></i>
                        Crear Proyecto
                    </button>
                </div>
                
            </form>
        </div>
    </div>
    
</div>

<script>
function calculateTotalCrear() {
    // Obtener valores
    const initialCost = parseFloat(document.querySelector('[name="initial_property_cost"]').value) || 0;
    const labor = parseFloat(document.querySelector('[name="budget_labor"]').value) || 0;
    const materials = parseFloat(document.querySelector('[name="budget_materials"]').value) || 0;
    const equipment = parseFloat(document.querySelector('[name="budget_equipment"]').value) || 0;
    const permits = parseFloat(document.querySelector('[name="budget_permits"]').value) || 0;
    const professional = parseFloat(document.querySelector('[name="budget_professional_services"]').value) || 0;
    const contingency = parseFloat(document.querySelector('[name="budget_contingency"]').value) || 0;
    const other = parseFloat(document.querySelector('[name="budget_other"]').value) || 0;
    
    // Calcular totales
    const restorationBudget = labor + materials + equipment + permits + professional + contingency + other;
    const totalInvestment = initialCost + restorationBudget;
    
    // Actualizar resumen
    document.getElementById('summaryInitialCrear').textContent = '$' + initialCost.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('summaryRestorationCrear').textContent = '$' + restorationBudget.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('summaryTotalCrear').textContent = '$' + totalInvestment.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Calcular al cargar la página
document.addEventListener('DOMContentLoaded', calculateTotalCrear);

// Validar fechas
document.querySelector('[name="estimated_start_date"]').addEventListener('change', function() {
    const endDate = document.querySelector('[name="estimated_end_date"]');
    if (this.value && endDate.value && this.value > endDate.value) {
        alert('La fecha de inicio no puede ser posterior a la fecha de finalización');
        this.value = '';
    }
});

document.querySelector('[name="estimated_end_date"]').addEventListener('change', function() {
    const startDate = document.querySelector('[name="estimated_start_date"]');
    if (this.value && startDate.value && this.value < startDate.value) {
        alert('La fecha de finalización no puede ser anterior a la fecha de inicio');
        this.value = '';
    }
});
</script>

<?php include 'footer.php'; ?>
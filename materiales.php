<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Gestión de Materiales';
$currentPage = 'obras.php';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create_material_type') {
            $materialName = trim($_POST['material_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $unitOfMeasure = trim($_POST['unit_of_measure'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (!empty($materialName)) {
                db()->insert('material_types', [
                    'material_name' => $materialName,
                    'category' => $category,
                    'unit_of_measure' => $unitOfMeasure,
                    'description' => $description,
                    'is_active' => 1,
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $_SESSION['success_message'] = "Tipo de material creado exitosamente";
            }
        }
        
        if ($action === 'toggle_status') {
            $id = (int)$_POST['id'];
            $currentStatus = (int)db()->selectValue("SELECT is_active FROM material_types WHERE id = ?", [$id]);
            db()->update('material_types', $id, ['is_active' => $currentStatus ? 0 : 1]);
            $_SESSION['success_message'] = "Estado actualizado exitosamente";
        }
        
        header("Location: materiales.php");
        exit;
    }
}

// Obtener materiales
$materialTypes = db()->select(
    "SELECT mt.*,
     CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
     (SELECT COUNT(*) FROM project_expenses WHERE material_type_id = mt.id) as usage_count
     FROM material_types mt
     LEFT JOIN users u ON mt.created_by = u.id
     ORDER BY mt.category, mt.display_order, mt.material_name"
);

// Agrupar por categoría
$categorizedMaterials = [];
foreach ($materialTypes as $material) {
    $category = $material['category'] ?: 'Sin Categoría';
    if (!isset($categorizedMaterials[$category])) {
        $categorizedMaterials[$category] = [];
    }
    $categorizedMaterials[$category][] = $material;
}

// Estadísticas
$stats = [];
$stats['total'] = count($materialTypes);
$stats['active'] = count(array_filter($materialTypes, function($m) { return $m['is_active']; }));
$stats['categories'] = count($categorizedMaterials);

include 'header.php';
include 'sidebar.php';
?>

<style>
    .materiales-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .page-header-materiales {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .stats-grid-materiales {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .stat-card-materiales {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card-materiales::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: #667eea;
    }
    
    .stat-label-materiales {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    
    .stat-value-materiales {
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
    }
    
    .btn-primary-materiales {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    
    .btn-primary-materiales:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .category-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        overflow: hidden;
    }
    
    .category-header {
        padding: 20px 24px;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border-bottom: 2px solid #e5e7eb;
    }
    
    .category-title {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .category-title i {
        color: #667eea;
    }
    
    .materials-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .materials-table thead th {
        background: #f9fafb;
        padding: 12px 24px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #6b7280;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .materials-table tbody td {
        padding: 16px 24px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
        color: #1f2937;
    }
    
    .materials-table tbody tr:hover {
        background: #f9fafb;
    }
    
    .material-name {
        font-weight: 600;
        color: #1f2937;
    }
    
    .material-unit {
        font-size: 13px;
        color: #6b7280;
        background: #f3f4f6;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
    }
    
    .material-status {
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .material-status.active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .material-status.inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s ease;
    }
    
    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
    }
    
    .modal-header {
        padding: 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    
    .modal-close:hover {
        background: #f3f4f6;
        color: #1f2937;
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .form-group-material {
        margin-bottom: 20px;
    }
    
    .form-label-material {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }
    
    .form-input-material,
    .form-select-material,
    .form-textarea-material {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
        font-family: 'Inter', sans-serif;
    }
    
    .form-input-material:focus,
    .form-select-material:focus,
    .form-textarea-material:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-textarea-material {
        resize: vertical;
        min-height: 80px;
    }
    
    .modal-footer {
        padding: 20px 24px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }
    
    .btn-secondary-material {
        background: #f3f4f6;
        color: #4b5563;
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-secondary-material:hover {
        background: #e5e7eb;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 768px) {
        .materiales-container {
            padding: 16px;
        }
        
        .page-header-materiales {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .stats-grid-materiales {
            grid-template-columns: 1fr;
        }
        
        .materials-table {
            font-size: 13px;
        }
        
        .materials-table thead th,
        .materials-table tbody td {
            padding: 12px;
        }
    }
</style>

<div class="materiales-container">
    
    <!-- Page Header -->
    <div class="page-header-materiales">
        <div>
            <h1 style="font-size: 28px; font-weight: 700; color: #1f2937; margin: 0 0 4px 0; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-boxes"></i>
                Gestión de Materiales
            </h1>
            <p style="color: #6b7280; margin: 0; font-size: 14px;">
                Administra los tipos de materiales utilizados en proyectos
            </p>
        </div>
        <button onclick="openModal()" class="btn-primary-materiales">
            <i class="fas fa-plus"></i>
            Nuevo Material
        </button>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid-materiales">
        <div class="stat-card-materiales">
            <div class="stat-label-materiales">Total Materiales</div>
            <div class="stat-value-materiales"><?php echo $stats['total']; ?></div>
        </div>
        
        <div class="stat-card-materiales">
            <div class="stat-label-materiales">Activos</div>
            <div class="stat-value-materiales"><?php echo $stats['active']; ?></div>
        </div>
        
        <div class="stat-card-materiales">
            <div class="stat-label-materiales">Categorías</div>
            <div class="stat-value-materiales"><?php echo $stats['categories']; ?></div>
        </div>
    </div>
    
    <!-- Materials by Category -->
    <?php foreach ($categorizedMaterials as $category => $materials): ?>
        <div class="category-section">
            <div class="category-header">
                <h2 class="category-title">
                    <i class="fas fa-layer-group"></i>
                    <?php echo htmlspecialchars($category); ?>
                </h2>
            </div>
            
            <table class="materials-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Unidad de Medida</th>
                        <th>Descripción</th>
                        <th>Veces Usado</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $material): ?>
                        <tr>
                            <td>
                                <div class="material-name"><?php echo htmlspecialchars($material['material_name']); ?></div>
                            </td>
                            <td>
                                <span class="material-unit"><?php echo htmlspecialchars($material['unit_of_measure']); ?></span>
                            </td>
                            <td>
                                <small style="color: #6b7280;">
                                    <?php echo htmlspecialchars(mb_strimwidth($material['description'], 0, 50, '...')); ?>
                                </small>
                            </td>
                            <td>
                                <strong><?php echo $material['usage_count']; ?></strong> veces
                            </td>
                            <td>
                                <span class="material-status <?php echo $material['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $material['is_active'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Cambiar el estado de este material?');">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $material['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Cambiar Estado">
                                        <i class="fas fa-toggle-<?php echo $material['is_active'] ? 'on' : 'off'; ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
    
</div>

<!-- Modal Crear Material -->
<div id="materialModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Nuevo Tipo de Material</h3>
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_material_type">
            
            <div class="modal-body">
                <div class="form-group-material">
                    <label class="form-label-material">Nombre del Material *</label>
                    <input type="text" 
                           name="material_name" 
                           class="form-input-material" 
                           placeholder="Ej: Cemento Portland"
                           required>
                </div>
                
                <div class="form-group-material">
                    <label class="form-label-material">Categoría</label>
                    <select name="category" class="form-select-material">
                        <option value="">Seleccione una categoría</option>
                        <option value="Construcción">Construcción</option>
                        <option value="Acabados">Acabados</option>
                        <option value="Eléctrico">Eléctrico</option>
                        <option value="Plomería">Plomería</option>
                        <option value="Carpintería">Carpintería</option>
                        <option value="Pintura">Pintura</option>
                        <option value="Ferretería">Ferretería</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>
                
                <div class="form-group-material">
                    <label class="form-label-material">Unidad de Medida *</label>
                    <input type="text" 
                           name="unit_of_measure" 
                           class="form-input-material" 
                           placeholder="Ej: Saco, m², kg, galón, unidad"
                           required>
                </div>
                
                <div class="form-group-material">
                    <label class="form-label-material">Descripción</label>
                    <textarea name="description" 
                              class="form-textarea-material" 
                              placeholder="Descripción opcional del material..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary-material" onclick="closeModal()">
                    Cancelar
                </button>
                <button type="submit" class="btn-primary-materiales">
                    <i class="fas fa-save"></i>
                    Crear Material
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('materialModal').classList.add('active');
}

function closeModal() {
    document.getElementById('materialModal').classList.remove('active');
}

// Cerrar modal al hacer clic fuera
document.getElementById('materialModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include 'footer.php'; ?>
<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Detalle del Proyecto';
$currentPage = 'obras.php';

// Obtener ID del proyecto
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    $_SESSION['error_message'] = "ID de proyecto no válido";
    header("Location: obras.php");
    exit;
}

// Obtener datos del proyecto con manejo de errores
try {
    $project = db()->selectOne(
        "SELECT rp.*, 
         p.reference as property_reference,
         p.title as property_title,
         CONCAT(c.first_name, ' ', c.last_name) as client_name,
         c.email as client_email,
         c.phone as client_phone,
         CONCAT(u.first_name, ' ', u.last_name) as created_by_name
         FROM restoration_projects rp
         LEFT JOIN properties p ON rp.property_id = p.id
         LEFT JOIN clients c ON rp.client_id = c.id
         LEFT JOIN users u ON rp.created_by = u.id
         WHERE rp.id = ?",
        [$projectId]
    );
} catch (Exception $e) {
    error_log("Error al obtener proyecto: " . $e->getMessage());
    $project = null;
}

if (!$project) {
    $_SESSION['error_message'] = "Proyecto no encontrado";
    header("Location: obras.php");
    exit;
}

// Calcular métricas
$project['budget_remaining'] = $project['total_budget'] - $project['total_spent'];
$project['budget_percentage_used'] = $project['total_budget'] > 0 
    ? ($project['total_spent'] / $project['total_budget']) * 100 
    : 0;
$project['has_cost_overrun'] = $project['total_spent'] > ($project['total_budget'] * (1 + $project['tolerance_margin'] / 100));
$project['has_delay'] = ($project['project_status'] == 'En Progreso' && 
                         $project['estimated_end_date'] && 
                         strtotime($project['estimated_end_date']) < time() && 
                         !$project['actual_end_date']);

// Obtener fases del proyecto
try {
    $phases = db()->select(
        "SELECT * FROM project_phases 
         WHERE project_id = ? 
         ORDER BY phase_order ASC, created_at ASC",
        [$projectId]
    );
} catch (Exception $e) {
    error_log("Error al obtener fases: " . $e->getMessage());
    $phases = [];
}

// Obtener gastos recientes
try {
    $expenses = db()->select(
        "SELECT pe.*, 
         et.type_name,
         mt.material_name,
         c.name as supplier_name
         FROM project_expenses pe
         LEFT JOIN expense_types et ON pe.expense_type_id = et.id
         LEFT JOIN material_types mt ON pe.material_type_id = mt.id
         LEFT JOIN contractors c ON pe.supplier_id = c.id
         WHERE pe.project_id = ?
         ORDER BY pe.expense_date DESC, pe.created_at DESC
         LIMIT 10",
        [$projectId]
    );
} catch (Exception $e) {
    error_log("Error al obtener gastos: " . $e->getMessage());
    $expenses = [];
}

// Obtener contratistas asignados
try {
    $contractors = db()->select(
        "SELECT pc.*, c.name, c.contractor_type, c.phone, c.email
         FROM project_contractors pc
         JOIN contractors c ON pc.contractor_id = c.id
         WHERE pc.project_id = ?
         ORDER BY pc.created_at DESC",
        [$projectId]
    );
} catch (Exception $e) {
    error_log("Error al obtener contratistas: " . $e->getMessage());
    $contractors = [];
}

// Obtener documentos
try {
    $documents = db()->select(
        "SELECT * FROM project_documents 
         WHERE project_id = ? 
         ORDER BY uploaded_at DESC 
         LIMIT 5",
        [$projectId]
    );
} catch (Exception $e) {
    error_log("Error al obtener documentos: " . $e->getMessage());
    $documents = [];
}

// Obtener fotos
try {
    $photos = db()->select(
        "SELECT * FROM project_photos 
         WHERE project_id = ? 
         ORDER BY photo_date DESC, display_order ASC 
         LIMIT 8",
        [$projectId]
    );
} catch (Exception $e) {
    error_log("Error al obtener fotos: " . $e->getMessage());
    $photos = [];
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    .ver-obra-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .project-header {
        background: white;
        padding: 24px 32px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
    }
    
    .project-title-section {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    
    .project-title-main {
        flex: 1;
    }
    
    .project-reference {
        font-size: 14px;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .project-name {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 8px 0;
    }
    
    .project-type {
        color: #6b7280;
        font-size: 16px;
    }
    
    .project-actions {
        display: flex;
        gap: 12px;
    }
    
    .btn-edit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    
    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .btn-back {
        background: #f3f4f6;
        color: #4b5563;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    
    .btn-back:hover {
        background: #e5e7eb;
        color: #4b5563;
    }
    
    .btn-danger {
        background: #ef4444;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .btn-danger:hover {
        background: #dc2626;
        color: white;
    }
    
    .status-badges {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .status-badge-large {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .status-badge-large.planificacion { background: #f3f4f6; color: #4b5563; }
    .status-badge-large.en-progreso { background: #dbeafe; color: #1e40af; }
    .status-badge-large.pausado { background: #fef3c7; color: #92400e; }
    .status-badge-large.completado { background: #d1fae5; color: #065f46; }
    .status-badge-large.cancelado { background: #fee2e2; color: #991b1b; }
    
    .alert-badge-large {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .alert-badge-large.cost { background: #fee2e2; color: #991b1b; }
    .alert-badge-large.delay { background: #fef3c7; color: #92400e; }
    
    .stats-grid-ver {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card-ver {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card-ver::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    
    .stat-card-ver.primary::before { background: #667eea; }
    .stat-card-ver.success::before { background: #10b981; }
    .stat-card-ver.warning::before { background: #f59e0b; }
    .stat-card-ver.danger::before { background: #ef4444; }
    .stat-card-ver.info::before { background: #3b82f6; }
    
    .stat-label-ver {
        font-size: 12px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    
    .stat-value-ver {
        font-size: 28px;
        font-weight: 700;
        color: #1f2937;
    }
    
    .stat-subtext {
        font-size: 13px;
        color: #6b7280;
        margin-top: 4px;
    }
    
    .progress-section {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
    }
    
    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    
    .progress-title {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
    }
    
    .progress-percentage {
        font-size: 32px;
        font-weight: 700;
        color: #667eea;
    }
    
    .progress-bar-large {
        height: 24px;
        background: #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 12px;
        transition: width 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 12px;
        color: white;
        font-weight: 600;
        font-size: 14px;
    }
    
    .card-ver {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        overflow: hidden;
    }
    
    .card-header-ver {
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .card-title-ver {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .card-body-ver {
        padding: 24px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .info-label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
    }
    
    .info-value {
        font-size: 15px;
        color: #1f2937;
        font-weight: 500;
    }
    
    .info-value a {
        color: #667eea;
        text-decoration: none;
    }
    
    .info-value a:hover {
        text-decoration: underline;
    }
    
    .phase-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .phase-item {
        padding: 16px;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .phase-item:last-child {
        border-bottom: none;
    }
    
    .phase-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .phase-icon.pending { background: #f3f4f6; color: #6b7280; }
    .phase-icon.in-progress { background: #dbeafe; color: #1e40af; }
    .phase-icon.completed { background: #d1fae5; color: #065f46; }
    .phase-icon.blocked { background: #fee2e2; color: #991b1b; }
    
    .phase-content {
        flex: 1;
    }
    
    .phase-name {
        font-weight: 600;
        font-size: 15px;
        color: #1f2937;
        margin-bottom: 8px;
    }
    
    .phase-progress-bar {
        height: 6px;
        background: #e5e7eb;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .phase-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        border-radius: 3px;
    }
    
    .phase-info {
        display: flex;
        justify-content: space-between;
        margin-top: 6px;
        font-size: 12px;
        color: #6b7280;
    }
    
    .table-ver {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .table-ver thead th {
        background: #f9fafb;
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #6b7280;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .table-ver tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
        color: #1f2937;
    }
    
    .table-ver tbody tr:hover {
        background: #f9fafb;
    }
    
    .badge-ver {
        padding: 4px 10px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        display: inline-block;
    }
    
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    
    .photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 12px;
    }
    
    .photo-item {
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        position: relative;
    }
    
    .photo-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .photo-item:hover img {
        transform: scale(1.1);
    }
    
    .photo-category {
        position: absolute;
        top: 8px;
        left: 8px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .empty-state-ver {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-icon-ver {
        font-size: 48px;
        color: #d1d5db;
        margin-bottom: 16px;
    }
    
    .empty-text-ver {
        color: #6b7280;
        font-size: 14px;
    }
    
    .btn-outline-primary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
        padding: 6px 14px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    
    .btn-outline-primary:hover {
        background: #667eea;
        color: white;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    @media (max-width: 768px) {
        .ver-obra-container {
            padding: 16px;
        }
        
        .project-header {
            padding: 20px;
        }
        
        .project-title-section {
            flex-direction: column;
            gap: 16px;
        }
        
        .project-name {
            font-size: 24px;
        }
        
        .project-actions {
            width: 100%;
            flex-direction: column;
        }
        
        .project-actions a,
        .project-actions button {
            width: 100%;
            justify-content: center;
        }
        
        .stats-grid-ver {
            grid-template-columns: 1fr;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .photo-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="ver-obra-container">
    
    <!-- Project Header -->
    <div class="project-header">
        <div class="project-title-section">
            <div class="project-title-main">
                <div class="project-reference">#<?php echo htmlspecialchars($project['project_reference']); ?></div>
                <h1 class="project-name"><?php echo htmlspecialchars($project['project_name']); ?></h1>
                <div class="project-type"><?php echo htmlspecialchars($project['restoration_type']); ?></div>
            </div>
            <div class="project-actions">
                <a href="editar-obra.php?id=<?php echo $project['id']; ?>" class="btn-edit">
                    <i class="fas fa-edit"></i>
                    Editar Proyecto
                </a>
                <a href="obras.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </a>
            </div>
        </div>
        
        <div class="status-badges">
            <?php
            $statusClasses = [
                'Planificación' => 'planificacion',
                'En Progreso' => 'en-progreso',
                'Pausado' => 'pausado',
                'Completado' => 'completado',
                'Cancelado' => 'cancelado'
            ];
            $statusClass = $statusClasses[$project['project_status']] ?? 'planificacion';
            ?>
            <span class="status-badge-large <?php echo $statusClass; ?>">
                <i class="fas fa-circle"></i>
                <?php echo htmlspecialchars($project['project_status']); ?>
            </span>
            
            <?php if ($project['has_cost_overrun']): ?>
                <span class="alert-badge-large cost">
                    <i class="fas fa-exclamation-triangle"></i>
                    Sobrecosto Detectado
                </span>
            <?php endif; ?>
            
            <?php if ($project['has_delay']): ?>
                <span class="alert-badge-large delay">
                    <i class="fas fa-clock"></i>
                    Proyecto Retrasado
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="progress-section">
        <div class="progress-header">
            <div class="progress-title">Progreso General del Proyecto</div>
            <div class="progress-percentage"><?php echo number_format($project['overall_progress'], 1); ?>%</div>
        </div>
        <div class="progress-bar-large">
            <div class="progress-fill" style="width: <?php echo $project['overall_progress']; ?>%"></div>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid-ver">
        <div class="stat-card-ver primary">
            <div class="stat-label-ver">Inversión Total</div>
            <div class="stat-value-ver">$<?php echo number_format($project['total_investment'], 0); ?></div>
            <div class="stat-subtext">Costo inicial + Gastos</div>
        </div>
        
        <div class="stat-card-ver <?php echo $project['budget_percentage_used'] > 90 ? 'danger' : 'success'; ?>">
            <div class="stat-label-ver">Presupuesto Utilizado</div>
            <div class="stat-value-ver">$<?php echo number_format($project['total_spent'], 0); ?></div>
            <div class="stat-subtext">
                <?php echo number_format($project['budget_percentage_used'], 1); ?>% de $<?php echo number_format($project['total_budget'], 0); ?>
            </div>
        </div>
        
        <div class="stat-card-ver <?php echo $project['budget_remaining'] >= 0 ? 'info' : 'danger'; ?>">
            <div class="stat-label-ver">Presupuesto Restante</div>
            <div class="stat-value-ver">$<?php echo number_format($project['budget_remaining'], 0); ?></div>
            <div class="stat-subtext">
                <?php echo $project['budget_remaining'] >= 0 ? 'Disponible' : 'Sobrecosto'; ?>
            </div>
        </div>
        
        <div class="stat-card-ver warning">
            <div class="stat-label-ver">Duración</div>
            <div class="stat-value-ver">
                <?php 
                if ($project['estimated_duration']) {
                    echo $project['estimated_duration'] . ' días';
                } else {
                    echo 'N/A';
                }
                ?>
            </div>
            <div class="stat-subtext">
                <?php if ($project['estimated_start_date'] && $project['estimated_end_date']): ?>
                    <?php echo date('d/m/Y', strtotime($project['estimated_start_date'])); ?> - 
                    <?php echo date('d/m/Y', strtotime($project['estimated_end_date'])); ?>
                <?php else: ?>
                    No definido
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            
            <!-- Información General -->
            <div class="card-ver">
                <div class="card-header-ver">
                    <h2 class="card-title-ver">
                        <i class="fas fa-info-circle"></i>
                        Información General
                    </h2>
                </div>
                <div class="card-body-ver">
                    <div class="info-grid">
                        <?php if ($project['property_reference']): ?>
                        <div class="info-item">
                            <div class="info-label">Propiedad Asociada</div>
                            <div class="info-value">
                                <a href="ver-propiedad.php?id=<?php echo $project['property_id']; ?>">
                                    <?php echo htmlspecialchars($project['property_reference']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($project['client_name']): ?>
                        <div class="info-item">
                            <div class="info-label">Cliente</div>
                            <div class="info-value"><?php echo htmlspecialchars($project['client_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($project['address']): ?>
                        <div class="info-item">
                            <div class="info-label">Dirección</div>
                            <div class="info-value"><?php echo htmlspecialchars($project['address']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-label">Creado Por</div>
                            <div class="info-value"><?php echo htmlspecialchars($project['created_by_name']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Fecha de Creación</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($project['created_at'])); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Última Actualización</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($project['updated_at'])); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($project['description']): ?>
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                        <div class="info-label" style="margin-bottom: 12px;">Descripción del Proyecto</div>
                        <div class="info-value" style="line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Fases del Proyecto -->
            <div class="card-ver">
                <div class="card-header-ver">
                    <h2 class="card-title-ver">
                        <i class="fas fa-tasks"></i>
                        Fases del Proyecto
                    </h2>
                    <a href="fases.php?project_id=<?php echo $project['id']; ?>" class="btn-outline-primary btn-sm">
                        Gestionar Fases
                    </a>
                </div>
                <div class="card-body-ver" style="padding: 0;">
                    <?php if (empty($phases)): ?>
                        <div class="empty-state-ver">
                            <div class="empty-icon-ver">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="empty-text-ver">No hay fases definidas para este proyecto</div>
                        </div>
                    <?php else: ?>
                        <ul class="phase-list">
                            <?php foreach ($phases as $phase): ?>
                                <?php
                                $phaseIconClass = [
                                    'Pendiente' => 'pending',
                                    'En Progreso' => 'in-progress',
                                    'Completada' => 'completed',
                                    'Bloqueada' => 'blocked'
                                ];
                                $iconClass = $phaseIconClass[$phase['phase_status']] ?? 'pending';
                                ?>
                                <li class="phase-item">
                                    <div class="phase-icon <?php echo $iconClass; ?>">
                                        <i class="fas fa-<?php 
                                            echo $phase['phase_status'] == 'Completada' ? 'check' : 
                                                 ($phase['phase_status'] == 'En Progreso' ? 'spinner' : 
                                                 ($phase['phase_status'] == 'Bloqueada' ? 'lock' : 'circle'));
                                        ?>"></i>
                                    </div>
                                    <div class="phase-content">
                                        <div class="phase-name"><?php echo htmlspecialchars($phase['phase_name']); ?></div>
                                        <div class="phase-progress-bar">
                                            <div class="phase-progress-fill" style="width: <?php echo $phase['progress_percentage']; ?>%"></div>
                                        </div>
                                        <div class="phase-info">
                                            <span><?php echo number_format($phase['progress_percentage'], 0); ?>% Completado</span>
                                            <span>
                                                $<?php echo number_format($phase['actual_spent'], 0); ?> de 
                                                $<?php echo number_format($phase['budget_allocated'], 0); ?>
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Gastos Recientes -->
            <div class="card-ver">
                <div class="card-header-ver">
                    <h2 class="card-title-ver">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Gastos Recientes
                    </h2>
                    <a href="gastos.php?project_id=<?php echo $project['id']; ?>" class="btn-outline-primary btn-sm">
                        Ver Todos
                    </a>
                </div>
                <div class="card-body-ver" style="padding: 0;">
                    <?php if (empty($expenses)): ?>
                        <div class="empty-state-ver">
                            <div class="empty-icon-ver">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="empty-text-ver">No hay gastos registrados</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-ver">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Monto</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($expense['type_name']); ?></td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars(mb_strimwidth($expense['description'], 0, 40, '...')); ?>
                                                </small>
                                            </td>
                                            <td class="fw-bold">$<?php echo number_format($expense['total_amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'Pagado' => 'success',
                                                    'Pendiente' => 'warning',
                                                    'Pago Parcial' => 'info',
                                                    'Atrasado' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge-ver badge-<?php echo $statusClass[$expense['payment_status']] ?? 'info'; ?>">
                                                    <?php echo htmlspecialchars($expense['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
        <div class="col-lg-4">
            
            <!-- Presupuesto -->
            <div class="card-ver">
                <div class="card-header-ver">
                    <h2 class="card-title-ver">
                        <i class="fas fa-calculator"></i>
                        Desglose de Presupuesto
                    </h2>
                </div>
                <div class="card-body-ver">
                    <?php
                    $budgetItems = [
                        'Mano de Obra' => $project['budget_labor'],
                        'Materiales' => $project['budget_materials'],
                        'Equipos' => $project['budget_equipment'],
                        'Permisos' => $project['budget_permits'],
                        'Servicios Prof.' => $project['budget_professional_services'],
                        'Contingencia' => $project['budget_contingency'],
                        'Otros' => $project['budget_other']
                    ];
                    ?>
                    <?php foreach ($budgetItems as $label => $amount): ?>
                        <?php if ($amount > 0): ?>
                            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6;">
                                <span style="color: #6b7280;"><?php echo $label; ?></span>
                                <span style="font-weight: 600;">$<?php echo number_format($amount, 0); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <div style="display: flex; justify-content: space-between; padding: 16px 0; margin-top: 8px; border-top: 2px solid #e5e7eb; font-weight: 700; font-size: 16px;">
                        <span>Total Presupuestado</span>
                        <span style="color: #667eea;">$<?php echo number_format($project['total_budget'], 0); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Contratistas -->
            <div class="card-ver">
                <div class="card-header-ver">
                    <h2 class="card-title-ver">
                        <i class="fas fa-users-cog"></i>
                        Contratistas Asignados
                    </h2>
                </div>
                <div class="card-body-ver">
                    <?php if (empty($contractors)): ?>
                        <div class="empty-state-ver">
                            <div class="empty-icon-ver">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <div class="empty-text-ver">No hay contratistas asignados</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contractors as $contractor): ?>
                            <div style="padding: 12px 0; border-bottom: 1px solid #f3f4f6;">
                                <div style="font-weight: 600; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($contractor['name']); ?>
                                </div>
                                <div style="font-size: 13px; color: #6b7280;">
                                    <?php echo htmlspecialchars($contractor['contractor_type']); ?>
                                </div>
                                <?php if ($contractor['phone']): ?>
                                    <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($contractor['phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Fotos del Proyecto -->
            <?php if (!empty($photos)): ?>
            <div class="card-ver">
                <div class="card-header-ver">
                    <h2 class="card-title-ver">
                        <i class="fas fa-camera"></i>
                        Fotos del Proyecto
                    </h2>
                    <a href="fotos.php?project_id=<?php echo $project['id']; ?>" class="btn-outline-primary btn-sm">
                        Ver Todas
                    </a>
                </div>
                <div class="card-body-ver">
                    <div class="photo-grid">
                        <?php foreach ($photos as $photo): ?>
                            <div class="photo-item">
                                <img src="<?php echo htmlspecialchars($photo['file_path']); ?>" alt="Foto del proyecto">
                                <div class="photo-category"><?php echo htmlspecialchars($photo['photo_category']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<?php include 'footer.php'; ?>
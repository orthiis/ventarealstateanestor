<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Gestión de Fases del Proyecto';
$currentPage = 'obras.php';

// Obtener ID del proyecto
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if (!$projectId) {
    header("Location: obras.php");
    exit;
}

// Obtener datos del proyecto
$project = db()->selectOne(
    "SELECT * FROM restoration_projects WHERE id = ?",
    [$projectId]
);

if (!$project) {
    $_SESSION['error_message'] = "Proyecto no encontrado";
    header("Location: obras.php");
    exit;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_phase') {
        $phaseName = trim($_POST['phase_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $estimatedStartDate = $_POST['estimated_start_date'] ?? null;
        $estimatedEndDate = $_POST['estimated_end_date'] ?? null;
        $budgetAllocated = !empty($_POST['budget_allocated']) ? (float)$_POST['budget_allocated'] : 0;
        $phaseOrder = !empty($_POST['phase_order']) ? (int)$_POST['phase_order'] : 0;
        
        if (!empty($phaseName)) {
            db()->insert('project_phases', [
                'project_id' => $projectId,
                'phase_name' => $phaseName,
                'description' => $description,
                'estimated_start_date' => $estimatedStartDate,
                'estimated_end_date' => $estimatedEndDate,
                'budget_allocated' => $budgetAllocated,
                'phase_order' => $phaseOrder,
                'phase_status' => 'Pendiente',
                'progress_percentage' => 0,
                'actual_spent' => 0,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Fase creada exitosamente";
        }
    }
    
    if ($action === 'update_phase') {
        $phaseId = (int)$_POST['phase_id'];
        $phaseStatus = $_POST['phase_status'] ?? '';
        $progressPercentage = !empty($_POST['progress_percentage']) ? (float)$_POST['progress_percentage'] : 0;
        $actualStartDate = $_POST['actual_start_date'] ?? null;
        $actualEndDate = $_POST['actual_end_date'] ?? null;
        
        db()->update('project_phases', $phaseId, [
            'phase_status' => $phaseStatus,
            'progress_percentage' => $progressPercentage,
            'actual_start_date' => $actualStartDate,
            'actual_end_date' => $actualEndDate,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Actualizar progreso general del proyecto
        $avgProgress = (float)db()->selectValue(
            "SELECT AVG(progress_percentage) FROM project_phases WHERE project_id = ?",
            [$projectId]
        );
        
        db()->update('restoration_projects', $projectId, [
            'overall_progress' => $avgProgress,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $_SESSION['success_message'] = "Fase actualizada exitosamente";
    }
    
    if ($action === 'delete_phase') {
        $phaseId = (int)$_POST['phase_id'];
        db()->delete('project_phases', $phaseId);
        $_SESSION['success_message'] = "Fase eliminada exitosamente";
    }
    
    if ($action === 'create_task') {
        $phaseId = (int)$_POST['phase_id'];
        $taskName = trim($_POST['task_name'] ?? '');
        $description = trim($_POST['task_description'] ?? '');
        $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
        $dueDate = $_POST['due_date'] ?? null;
        
        if (!empty($taskName)) {
            db()->insert('phase_tasks', [
                'phase_id' => $phaseId,
                'project_id' => $projectId,
                'task_name' => $taskName,
                'description' => $description,
                'assigned_to' => $assignedTo,
                'due_date' => $dueDate,
                'task_status' => 'Pendiente',
                'is_completed' => 0,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $_SESSION['success_message'] = "Tarea creada exitosamente";
        }
    }
    
    if ($action === 'toggle_task') {
        $taskId = (int)$_POST['task_id'];
        $currentStatus = (int)db()->selectValue("SELECT is_completed FROM phase_tasks WHERE id = ?", [$taskId]);
        
        db()->update('phase_tasks', $taskId, [
            'is_completed' => $currentStatus ? 0 : 1,
            'task_status' => $currentStatus ? 'Pendiente' : 'Completada',
            'completed_at' => $currentStatus ? null : date('Y-m-d H:i:s')
        ]);
        
        $_SESSION['success_message'] = "Estado de tarea actualizado";
    }
    
    header("Location: fases.php?project_id={$projectId}");
    exit;
}

// Obtener fases del proyecto
$phases = db()->select(
    "SELECT ph.*,
     CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
     (SELECT COUNT(*) FROM phase_tasks WHERE phase_id = ph.id) as task_count,
     (SELECT COUNT(*) FROM phase_tasks WHERE phase_id = ph.id AND is_completed = 1) as completed_tasks
     FROM project_phases ph
     LEFT JOIN users u ON ph.created_by = u.id
     WHERE ph.project_id = ?
     ORDER BY ph.phase_order, ph.created_at",
    [$projectId]
);

// Obtener tareas por fase
$tasksByPhase = [];
foreach ($phases as $phase) {
    $tasks = db()->select(
        "SELECT pt.*,
         CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
         FROM phase_tasks pt
         LEFT JOIN users u ON pt.assigned_to = u.id
         WHERE pt.phase_id = ?
         ORDER BY pt.is_completed, pt.due_date, pt.created_at",
        [$phase['id']]
    );
    $tasksByPhase[$phase['id']] = $tasks;
}

// Obtener usuarios para asignar
$users = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
     FROM users 
     WHERE is_active = 1
     ORDER BY first_name, last_name"
);

include 'header.php';
include 'sidebar.php';
?>

<style>
    .fases-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .page-header-fases {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
    }
    
    .project-info-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }
    
    .project-reference {
        font-size: 14px;
        color: #6b7280;
        font-weight: 600;
    }
    
    .project-name-fases {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
        margin: 4px 0 8px 0;
    }
    
    .project-progress-bar {
        height: 8px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 8px;
    }
    
    .project-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transition: width 0.3s ease;
    }
    
    .project-progress-text {
        font-size: 13px;
        color: #6b7280;
        font-weight: 600;
    }
    
    .btn-primary-fases {
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
    
    .btn-primary-fases:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .btn-secondary-fases {
        background: #f3f4f6;
        color: #4b5563;
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary-fases:hover {
        background: #e5e7eb;
    }
    
    .phases-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .phase-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .phase-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    
    .phase-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    
    .phase-header:hover {
        background: #f9fafb;
    }
    
    .phase-header-left {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .phase-order-badge {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 18px;
        flex-shrink: 0;
    }
    
    .phase-info {
        flex: 1;
    }
    
    .phase-name {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
    }
    
    .phase-dates {
        font-size: 13px;
        color: #6b7280;
    }
    
    .phase-header-right {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .phase-status-badge {
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .phase-status-badge.pendiente { background: #f3f4f6; color: #4b5563; }
    .phase-status-badge.en-progreso { background: #dbeafe; color: #1e40af; }
    .phase-status-badge.completada { background: #d1fae5; color: #065f46; }
    .phase-status-badge.bloqueada { background: #fee2e2; color: #991b1b; }
    
    .phase-progress-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: conic-gradient(
            #667eea 0deg,
            #667eea calc(var(--progress) * 3.6deg),
            #e5e7eb calc(var(--progress) * 3.6deg),
            #e5e7eb 360deg
        );
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    
    .phase-progress-circle::before {
        content: '';
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: white;
        position: absolute;
    }
    
    .phase-progress-value {
        position: relative;
        z-index: 1;
        font-size: 12px;
        font-weight: 700;
        color: #1f2937;
    }
    
    .phase-toggle {
        background: none;
        border: none;
        font-size: 20px;
        color: #6b7280;
        cursor: pointer;
        padding: 4px;
        transition: all 0.2s ease;
    }
    
    .phase-toggle:hover {
        color: #1f2937;
    }
    
    .phase-body {
        display: none;
        padding: 24px;
        background: #f9fafb;
    }
    
    .phase-body.active {
        display: block;
    }
    
    .phase-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
        padding: 20px;
        background: white;
        border-radius: 8px;
    }
    
    .phase-detail-item {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .phase-detail-label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
    }
    
    .phase-detail-value {
        font-size: 15px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .phase-actions {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .tasks-section {
        background: white;
        border-radius: 8px;
        padding: 20px;
    }
    
    .tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    
    .tasks-title {
        font-size: 16px;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .task-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .task-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border-bottom: 1px solid #f3f4f6;
        transition: background 0.2s ease;
    }
    
    .task-item:hover {
        background: #f9fafb;
    }
    
    .task-item:last-child {
        border-bottom: none;
    }
    
    .task-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: #667eea;
    }
    
    .task-content {
        flex: 1;
    }
    
    .task-name {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 4px;
    }
    
    .task-item.completed .task-name {
        text-decoration: line-through;
        color: #9ca3af;
    }
    
    .task-meta {
        font-size: 12px;
        color: #6b7280;
    }
    
    .empty-state-fases {
        text-align: center;
        padding: 40px 20px;
    }
    
    .empty-icon-fases {
        font-size: 48px;
        color: #d1d5db;
        margin-bottom: 16px;
    }
    
    .empty-text-fases {
        color: #6b7280;
        font-size: 14px;
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
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .form-group-fase {
        margin-bottom: 20px;
    }
    
    .form-label-fase {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }
    
    .form-input-fase,
    .form-select-fase,
    .form-textarea-fase {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
        font-family: 'Inter', sans-serif;
    }
    
    .form-input-fase:focus,
    .form-select-fase:focus,
    .form-textarea-fase:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-textarea-fase {
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
    
    @media (max-width: 768px) {
        .fases-container {
            padding: 16px;
        }
        
        .phase-header-left {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .phase-header-right {
            flex-direction: column;
            align-items: flex-end;
        }
        
        .phase-details {
            grid-template-columns: 1fr;
        }
        
        .phase-actions {
            flex-direction: column;
        }
    }
</style>

<div class="fases-container">
    
    <!-- Page Header -->
    <div class="page-header-fases">
        <div class="project-info-header">
            <div style="flex: 1;">
                <div class="project-reference">#<?php echo htmlspecialchars($project['project_reference']); ?></div>
                <h1 class="project-name-fases"><?php echo htmlspecialchars($project['project_name']); ?></h1>
                <div class="project-progress-bar">
                    <div class="project-progress-fill" style="width: <?php echo $project['overall_progress']; ?>%"></div>
                </div>
                <div class="project-progress-text">
                    Progreso General: <?php echo number_format($project['overall_progress'], 1); ?>%
                </div>
            </div>
            <div style="display: flex; gap: 12px; align-items: flex-start;">
                <button onclick="openPhaseModal()" class="btn-primary-fases">
                    <i class="fas fa-plus"></i>
                    Nueva Fase
                </button>
                <a href="ver-obra.php?id=<?php echo $projectId; ?>" class="btn-secondary-fases">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Proyecto
                </a>
            </div>
        </div>
    </div>
    
    <!-- Phases List -->
    <?php if (empty($phases)): ?>
        <div class="phase-card">
            <div class="empty-state-fases">
                <div class="empty-icon-fases">
                    <i class="fas fa-tasks"></i>
                </div>
                <p class="empty-text-fases">No hay fases definidas para este proyecto</p>
                <button onclick="openPhaseModal()" class="btn-primary-fases" style="margin-top: 16px;">
                    <i class="fas fa-plus"></i>
                    Crear Primera Fase
                </button>
            </div>
        </div>
    <?php else: ?>
        <ul class="phases-list">
            <?php foreach ($phases as $index => $phase): ?>
                <li class="phase-card">
                    <div class="phase-header" onclick="togglePhase(<?php echo $phase['id']; ?>)">
                        <div class="phase-header-left">
                            <div class="phase-order-badge"><?php echo $phase['phase_order'] ?: ($index + 1); ?></div>
                            <div class="phase-info">
                                <div class="phase-name"><?php echo htmlspecialchars($phase['phase_name']); ?></div>
                                <div class="phase-dates">
                                    <?php if ($phase['estimated_start_date'] && $phase['estimated_end_date']): ?>
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('d/m/Y', strtotime($phase['estimated_start_date'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($phase['estimated_end_date'])); ?>
                                    <?php else: ?>
                                        <i class="fas fa-calendar"></i> Fechas no definidas
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="phase-header-right">
                            <?php
                            $statusClass = [
                                'Pendiente' => 'pendiente',
                                'En Progreso' => 'en-progreso',
                                'Completada' => 'completada',
                                'Bloqueada' => 'bloqueada'
                            ];
                            ?>
                            <span class="phase-status-badge <?php echo $statusClass[$phase['phase_status']] ?? 'pendiente'; ?>">
                                <?php echo htmlspecialchars($phase['phase_status']); ?>
                            </span>
                            <div class="phase-progress-circle" style="--progress: <?php echo $phase['progress_percentage']; ?>">
                                <span class="phase-progress-value"><?php echo number_format($phase['progress_percentage'], 0); ?>%</span>
                            </div>
                            <button type="button" class="phase-toggle" onclick="event.stopPropagation();">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="phase-body" id="phase-<?php echo $phase['id']; ?>">
                        <!-- Phase Details -->
                        <div class="phase-details">
                            <div class="phase-detail-item">
                                <div class="phase-detail-label">Presupuesto Asignado</div>
                                <div class="phase-detail-value">$<?php echo number_format($phase['budget_allocated'], 0); ?></div>
                            </div>
                            <div class="phase-detail-item">
                                <div class="phase-detail-label">Gastado</div>
                                <div class="phase-detail-value">$<?php echo number_format($phase['actual_spent'], 0); ?></div>
                            </div>
                            <div class="phase-detail-item">
                                <div class="phase-detail-label">Tareas Totales</div>
                                <div class="phase-detail-value"><?php echo $phase['task_count']; ?></div>
                            </div>
                            <div class="phase-detail-item">
                                <div class="phase-detail-label">Tareas Completadas</div>
                                <div class="phase-detail-value"><?php echo $phase['completed_tasks']; ?></div>
                            </div>
                        </div>
                        
                        <?php if ($phase['description']): ?>
                            <div style="background: white; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                                <div style="font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 8px;">
                                    Descripción
                                </div>
                                <div style="color: #4b5563;">
                                    <?php echo nl2br(htmlspecialchars($phase['description'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Phase Actions -->
                        <div class="phase-actions">
                            <button onclick="openUpdatePhaseModal(<?php echo $phase['id']; ?>, '<?php echo htmlspecialchars($phase['phase_status']); ?>', <?php echo $phase['progress_percentage']; ?>)" class="btn-secondary-fases">
                                <i class="fas fa-edit"></i>
                                Actualizar Estado
                            </button>
                            <button onclick="openTaskModal(<?php echo $phase['id']; ?>)" class="btn-secondary-fases">
                                <i class="fas fa-plus"></i>
                                Nueva Tarea
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar esta fase y todas sus tareas?');">
                                <input type="hidden" name="action" value="delete_phase">
                                <input type="hidden" name="phase_id" value="<?php echo $phase['id']; ?>">
                                <button type="submit" class="btn-secondary-fases" style="color: #ef4444;">
                                    <i class="fas fa-trash"></i>
                                    Eliminar Fase
                                </button>
                            </form>
                        </div>
                        
                        <!-- Tasks Section -->
                        <div class="tasks-section">
                            <div class="tasks-header">
                                <h3 class="tasks-title">
                                    <i class="fas fa-check-circle"></i>
                                    Tareas de la Fase
                                </h3>
                                <span style="font-size: 13px; color: #6b7280; font-weight: 600;">
                                    <?php echo $phase['completed_tasks']; ?> de <?php echo $phase['task_count']; ?> completadas
                                </span>
                            </div>
                            
                            <?php if (empty($tasksByPhase[$phase['id']])): ?>
                                <div class="empty-state-fases" style="padding: 30px 20px;">
                                    <div class="empty-icon-fases" style="font-size: 36px;">
                                        <i class="fas fa-clipboard-list"></i>
                                    </div>
                                    <p class="empty-text-fases">No hay tareas en esta fase</p>
                                </div>
                            <?php else: ?>
                                <ul class="task-list">
                                    <?php foreach ($tasksByPhase[$phase['id']] as $task): ?>
                                        <li class="task-item <?php echo $task['is_completed'] ? 'completed' : ''; ?>">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_task">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="checkbox" 
                                                       class="task-checkbox" 
                                                       <?php echo $task['is_completed'] ? 'checked' : ''; ?>
                                                       onchange="this.form.submit()">
                                            </form>
                                            <div class="task-content">
                                                <div class="task-name"><?php echo htmlspecialchars($task['task_name']); ?></div>
                                                <div class="task-meta">
                                                    <?php if ($task['assigned_to_name']): ?>
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($task['assigned_to_name']); ?>
                                                    <?php endif; ?>
                                                    <?php if ($task['due_date']): ?>
                                                        <?php if ($task['assigned_to_name']): ?> • <?php endif; ?>
                                                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($task['due_date'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
</div>

<!-- Modal Create Phase -->
<div id="phaseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Nueva Fase del Proyecto</h3>
            <button class="modal-close" onclick="closePhaseModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_phase">
            
            <div class="modal-body">
                <div class="form-group-fase">
                    <label class="form-label-fase">Nombre de la Fase *</label>
                    <input type="text" name="phase_name" class="form-input-fase" placeholder="Ej: Demolición" required>
                </div>
                
                <div class="form-group-fase">
                    <label class="form-label-fase">Descripción</label>
                    <textarea name="description" class="form-textarea-fase" placeholder="Describe los trabajos de esta fase..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group-fase">
                        <label class="form-label-fase">Fecha Inicio Estimada</label>
                        <input type="date" name="estimated_start_date" class="form-input-fase">
                    </div>
                    
                    <div class="form-group-fase">
                        <label class="form-label-fase">Fecha Fin Estimada</label>
                        <input type="date" name="estimated_end_date" class="form-input-fase">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group-fase">
                        <label class="form-label-fase">Presupuesto Asignado ($)</label>
                        <input type="number" name="budget_allocated" class="form-input-fase" min="0" step="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="form-group-fase">
                        <label class="form-label-fase">Orden</label>
                        <input type="number" name="phase_order" class="form-input-fase" min="0" placeholder="0">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary-fases" onclick="closePhaseModal()">Cancelar</button>
                <button type="submit" class="btn-primary-fases">
                    <i class="fas fa-save"></i>
                    Crear Fase
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Update Phase -->
<div id="updatePhaseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Actualizar Estado de Fase</h3>
            <button class="modal-close" onclick="closeUpdatePhaseModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_phase">
            <input type="hidden" name="phase_id" id="updatePhaseId">
            
            <div class="modal-body">
                <div class="form-group-fase">
                    <label class="form-label-fase">Estado de la Fase</label>
                    <select name="phase_status" class="form-select-fase" id="updatePhaseStatus">
                        <option value="Pendiente">Pendiente</option>
                        <option value="En Progreso">En Progreso</option>
                        <option value="Completada">Completada</option>
                        <option value="Bloqueada">Bloqueada</option>
                    </select>
                </div>
                
                <div class="form-group-fase">
                    <label class="form-label-fase">Progreso (%)</label>
                    <input type="number" name="progress_percentage" id="updatePhaseProgress" class="form-input-fase" min="0" max="100" step="0.1">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group-fase">
                        <label class="form-label-fase">Fecha Inicio Real</label>
                        <input type="date" name="actual_start_date" class="form-input-fase">
                    </div>
                    
                    <div class="form-group-fase">
                        <label class="form-label-fase">Fecha Fin Real</label>
                        <input type="date" name="actual_end_date" class="form-input-fase">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary-fases" onclick="closeUpdatePhaseModal()">Cancelar</button>
                <button type="submit" class="btn-primary-fases">
                    <i class="fas fa-save"></i>
                    Actualizar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Create Task -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Nueva Tarea</h3>
            <button class="modal-close" onclick="closeTaskModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="create_task">
            <input type="hidden" name="phase_id" id="taskPhaseId">
            
            <div class="modal-body">
                <div class="form-group-fase">
                    <label class="form-label-fase">Nombre de la Tarea *</label>
                    <input type="text" name="task_name" class="form-input-fase" placeholder="Ej: Limpiar escombros" required>
                </div>
                
                <div class="form-group-fase">
                    <label class="form-label-fase">Descripción</label>
                    <textarea name="task_description" class="form-textarea-fase" placeholder="Describe la tarea..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group-fase">
                        <label class="form-label-fase">Asignar a</label>
                        <select name="assigned_to" class="form-select-fase">
                            <option value="">Sin asignar</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-fase">
                        <label class="form-label-fase">Fecha Límite</label>
                        <input type="date" name="due_date" class="form-input-fase">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary-fases" onclick="closeTaskModal()">Cancelar</button>
                <button type="submit" class="btn-primary-fases">
                    <i class="fas fa-save"></i>
                    Crear Tarea
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle phase body
function togglePhase(phaseId) {
    const body = document.getElementById('phase-' + phaseId);
    body.classList.toggle('active');
    
    const toggle = body.previousElementSibling.querySelector('.phase-toggle i');
    if (body.classList.contains('active')) {
        toggle.classList.remove('fa-chevron-down');
        toggle.classList.add('fa-chevron-up');
    } else {
        toggle.classList.remove('fa-chevron-up');
        toggle.classList.add('fa-chevron-down');
    }
}

// Modals
function openPhaseModal() {
    document.getElementById('phaseModal').classList.add('active');
}

function closePhaseModal() {
    document.getElementById('phaseModal').classList.remove('active');
}

function openUpdatePhaseModal(phaseId, status, progress) {
    document.getElementById('updatePhaseId').value = phaseId;
    document.getElementById('updatePhaseStatus').value = status;
    document.getElementById('updatePhaseProgress').value = progress;
    document.getElementById('updatePhaseModal').classList.add('active');
}

function closeUpdatePhaseModal() {
    document.getElementById('updatePhaseModal').classList.remove('active');
}

function openTaskModal(phaseId) {
    document.getElementById('taskPhaseId').value = phaseId;
    document.getElementById('taskModal').classList.add('active');
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('active');
}

// Close modals on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include 'footer.php'; ?>
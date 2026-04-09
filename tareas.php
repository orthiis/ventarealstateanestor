<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('tasks.title', [], 'Gestión de Tareas');
$currentUser = getCurrentUser();

// Obtener filtros
$filterStatus = $_GET['status'] ?? 'all';
$filterPriority = $_GET['priority'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';
$filterAssigned = $_GET['assigned'] ?? 'all';
$filterDue = $_GET['due'] ?? 'all';

// Construir query
$where = ["1=1"];
$params = [];

// Si no es admin, solo ver sus tareas o las que creó
if ($currentUser['role']['name'] !== 'administrador') {
    $where[] = "(assigned_to = ? OR created_by = ?)";
    $params[] = $currentUser['id'];
    $params[] = $currentUser['id'];
}

// Filtro por estado
if ($filterStatus !== 'all') {
    $where[] = "status = ?";
    $params[] = $filterStatus;
}

// Filtro por prioridad
if ($filterPriority !== 'all') {
    $where[] = "priority = ?";
    $params[] = $filterPriority;
}

// Filtro por tipo
if ($filterType !== 'all') {
    $where[] = "task_type = ?";
    $params[] = $filterType;
}

// Filtro por asignado
if ($filterAssigned !== 'all') {
    $where[] = "assigned_to = ?";
    $params[] = $filterAssigned;
}

// Filtro por vencimiento
if ($filterDue === 'today') {
    $where[] = "DATE(due_date) = CURDATE()";
} elseif ($filterDue === 'overdue') {
    $where[] = "due_date < NOW() AND status != 'completed'";
} elseif ($filterDue === 'week') {
    $where[] = "due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
}

$whereClause = implode(' AND ', $where);

// Obtener tareas
$tasks = db()->select(
    "SELECT t.*, 
     CONCAT(u.first_name, ' ', u.last_name) as assigned_name,
     CONCAT(creator.first_name, ' ', creator.last_name) as creator_name,
     c.first_name as client_name, c.last_name as client_lastname,
     p.reference as property_ref, p.title as property_title
     FROM tasks t
     LEFT JOIN users u ON t.assigned_to = u.id
     LEFT JOIN users creator ON t.created_by = creator.id
     LEFT JOIN clients c ON t.related_client_id = c.id
     LEFT JOIN properties p ON t.related_property_id = p.id
     WHERE {$whereClause}
     ORDER BY 
        CASE t.status 
            WHEN 'pending' THEN 1 
            WHEN 'in_progress' THEN 2 
            WHEN 'completed' THEN 3 
            WHEN 'cancelled' THEN 4 
        END,
        CASE t.priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
        END,
        t.due_date ASC",
    $params
);

// Estadísticas
$stats = [
    'total' => db()->count('tasks', $whereClause, $params),
    'pending' => db()->count('tasks', $whereClause . " AND status = 'pending'", array_merge($params, ['pending'])),
    'in_progress' => db()->count('tasks', $whereClause . " AND status = 'in_progress'", array_merge($params, ['in_progress'])),
    'completed' => db()->count('tasks', $whereClause . " AND status = 'completed'", array_merge($params, ['completed'])),
    'overdue' => db()->count('tasks', $whereClause . " AND due_date < NOW() AND status != 'completed'", $params)
];

// Obtener usuarios para filtro
$users = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name 
     FROM users 
     WHERE status = 'active' 
     ORDER BY first_name"
);

include 'header.php';
include 'sidebar.php';
?>

<style>
    :root {
        --primary: #667eea;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
    }

    .tasks-container {
        padding: 30px;
        background: #f8f9fa;
    }

    /* Header Moderno */
    .page-header-modern {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-title-modern {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 5px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-subtitle-modern {
        color: #718096;
        margin: 0;
        font-size: 14px;
    }

    .btn-add-task {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-add-task:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        text-align: center;
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        margin: 10px 0;
    }

    .stat-label {
        font-size: 13px;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Filter Bar */
    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .filter-group {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    /* Task Card */
    .task-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        border-left: 4px solid #e2e8f0;
        transition: all 0.3s;
        cursor: pointer;
    }

    .task-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .task-card.priority-high {
        border-left-color: #ef4444;
    }

    .task-card.priority-medium {
        border-left-color: #f59e0b;
    }

    .task-card.priority-low {
        border-left-color: #10b981;
    }

    .task-card.completed {
        opacity: 0.7;
        background: #f9fafb;
    }

    .task-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }

    .task-title {
        font-size: 18px;
        font-weight: 600;
        color: #2d3748;
        margin: 0 0 8px 0;
    }

    .task-completed .task-title {
        text-decoration: line-through;
        color: #9ca3af;
    }

    .task-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge-task {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge-priority-high { background: #fee2e2; color: #991b1b; }
    .badge-priority-medium { background: #fef3c7; color: #78350f; }
    .badge-priority-low { background: #d1fae5; color: #065f46; }

    .badge-status-pending { background: #fef3c7; color: #78350f; }
    .badge-status-in_progress { background: #dbeafe; color: #1e40af; }
    .badge-status-completed { background: #d1fae5; color: #065f46; }
    .badge-status-cancelled { background: #fee2e2; color: #991b1b; }

    .badge-type { background: #e0e7ff; color: #4338ca; }

    .task-meta {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 14px;
        color: #6b7280;
        margin-top: 15px;
    }

    .task-meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .task-meta-item i {
        color: #9ca3af;
        font-size: 13px;
    }

    .task-actions {
        display: flex;
        gap: 8px;
    }

    .btn-task-action {
        padding: 6px 12px;
        border-radius: 8px;
        border: 2px solid #e5e7eb;
        background: white;
        color: #4b5563;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }

    .btn-task-action:hover {
        border-color: #667eea;
        color: #667eea;
        background: #f9fafb;
    }

    .overdue-badge {
        background: #fee2e2;
        color: #991b1b;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .tasks-container {
            padding: 15px;
        }

        .page-header-modern {
            flex-direction: column;
            align-items: flex-start;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }

        .filter-group .form-select {
            width: 100% !important;
        }

        .task-header {
            flex-direction: column;
            gap: 10px;
        }

        .task-actions {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>

<div class="main-content">
    <div class="tasks-container">
        
        <!-- Page Header -->
        <div class="page-header-modern">
            <div>
                <h2 class="page-title-modern">
                    <i class="fas fa-tasks" style="color: #667eea;"></i>
                    <?php echo __('tasks.title', [], 'Gestión de Tareas'); ?>
                </h2>
                <p class="page-subtitle-modern">
                    <?php echo __('tasks.subtitle', [], 'Organiza tus actividades'); ?>
                </p>
            </div>
            <a href="nueva-tarea.php" class="btn-add-task">
                <i class="fas fa-plus"></i> 
                <?php echo __('tasks.new_task', [], 'Nueva Tarea'); ?>
            </a>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card" style="border-top: 4px solid #667eea;">
                <div class="stat-label"><?php echo __('total', [], 'Total'); ?> <?php echo __('tasks.title', [], 'Tareas'); ?></div>
                <div class="stat-number" style="color: #667eea;"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #f59e0b;">
                <div class="stat-label"><?php echo __('tasks.pending', [], 'Pendientes'); ?></div>
                <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #3b82f6;">
                <div class="stat-label"><?php echo __('tasks.in_progress', [], 'En Progreso'); ?></div>
                <div class="stat-number" style="color: #3b82f6;"><?php echo $stats['in_progress']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #10b981;">
                <div class="stat-label"><?php echo __('tasks.completed', [], 'Completadas'); ?></div>
                <div class="stat-number" style="color: #10b981;"><?php echo $stats['completed']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #ef4444;">
                <div class="stat-label"><?php echo __('tasks.overdue', [], 'Vencidas'); ?></div>
                <div class="stat-number" style="color: #ef4444;"><?php echo $stats['overdue']; ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-bar">
            <form method="GET" class="filter-group">
                <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all"><?php echo __('all_statuses', [], 'Todos los estados'); ?></option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>><?php echo __('tasks.pending', [], 'Pendiente'); ?></option>
                    <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>><?php echo __('tasks.in_progress', [], 'En Progreso'); ?></option>
                    <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>><?php echo __('tasks.completed', [], 'Completada'); ?></option>
                    <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>><?php echo __('tasks.cancelled', [], 'Cancelada'); ?></option>
                </select>

                <select name="priority" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all"><?php echo __('all_priorities', [], 'Todas las prioridades'); ?></option>
                    <option value="high" <?php echo $filterPriority === 'high' ? 'selected' : ''; ?>><?php echo __('tasks.high', [], 'Alta'); ?></option>
                    <option value="medium" <?php echo $filterPriority === 'medium' ? 'selected' : ''; ?>><?php echo __('tasks.medium', [], 'Media'); ?></option>
                    <option value="low" <?php echo $filterPriority === 'low' ? 'selected' : ''; ?>><?php echo __('tasks.low', [], 'Baja'); ?></option>
                </select>

                <select name="type" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all"><?php echo __('all_types', [], 'Todos los tipos'); ?></option>
                    <option value="call" <?php echo $filterType === 'call' ? 'selected' : ''; ?>><?php echo __('tasks.call', [], 'Llamada'); ?></option>
                    <option value="meeting" <?php echo $filterType === 'meeting' ? 'selected' : ''; ?>><?php echo __('tasks.meeting', [], 'Reunión'); ?></option>
                    <option value="visit" <?php echo $filterType === 'visit' ? 'selected' : ''; ?>><?php echo __('tasks.visit', [], 'Visita'); ?></option>
                    <option value="follow_up" <?php echo $filterType === 'follow_up' ? 'selected' : ''; ?>><?php echo __('tasks.follow_up', [], 'Seguimiento'); ?></option>
                    <option value="administrative" <?php echo $filterType === 'administrative' ? 'selected' : ''; ?>><?php echo __('administrative', [], 'Administrativa'); ?></option>
                    <option value="email" <?php echo $filterType === 'email' ? 'selected' : ''; ?>><?php echo __('tasks.email', [], 'Email'); ?></option>
                    <option value="other" <?php echo $filterType === 'other' ? 'selected' : ''; ?>><?php echo __('tasks.other', [], 'Otro'); ?></option>
                </select>

                <select name="due" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all"><?php echo __('all_due_dates', [], 'Todos los vencimientos'); ?></option>
                    <option value="today" <?php echo $filterDue === 'today' ? 'selected' : ''; ?>><?php echo __('calendar.today', [], 'Hoy'); ?></option>
                    <option value="overdue" <?php echo $filterDue === 'overdue' ? 'selected' : ''; ?>><?php echo __('tasks.overdue', [], 'Vencidas'); ?></option>
                    <option value="week" <?php echo $filterDue === 'week' ? 'selected' : ''; ?>><?php echo __('this_week', [], 'Esta semana'); ?></option>
                </select>

                <?php if ($currentUser['role']['name'] === 'administrador'): ?>
                <select name="assigned" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all"><?php echo __('all_users', [], 'Todos los usuarios'); ?></option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filterAssigned == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <?php if ($filterStatus !== 'all' || $filterPriority !== 'all' || $filterType !== 'all' || $filterAssigned !== 'all' || $filterDue !== 'all'): ?>
                <a href="tareas.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> <?php echo __('clear_filters', [], 'Limpiar filtros'); ?>
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Listado de Tareas -->
        <?php if (empty($tasks)): ?>
            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
                <i class="fas fa-tasks" style="font-size: 64px; color: #e2e8f0; margin-bottom: 20px;"></i>
                <h4 style="color: #6b7280;"><?php echo __('tasks.no_tasks', [], 'No hay tareas'); ?></h4>
                <p style="color: #9ca3af;"><?php echo __('create_first_task', [], 'Crea tu primera tarea para comenzar'); ?></p>
                <a href="nueva-tarea.php" class="btn-add-task mt-3">
                    <i class="fas fa-plus"></i> <?php echo __('tasks.new_task', [], 'Nueva Tarea'); ?>
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): 
                $isOverdue = strtotime($task['due_date']) < time() && $task['status'] !== 'completed';
                $priorityClass = 'priority-' . $task['priority'];
                $statusClass = 'status-' . $task['status'];
                $completedClass = $task['status'] === 'completed' ? 'completed' : '';
                
                // Traducciones de prioridad
                $priorityLabels = [
                    'high' => __('tasks.high', [], 'Alta'),
                    'medium' => __('tasks.medium', [], 'Media'),
                    'low' => __('tasks.low', [], 'Baja')
                ];
                
                // Traducciones de estado
                $statusLabels = [
                    'pending' => __('tasks.pending', [], 'Pendiente'),
                    'in_progress' => __('tasks.in_progress', [], 'En Progreso'),
                    'completed' => __('tasks.completed', [], 'Completada'),
                    'cancelled' => __('tasks.cancelled', [], 'Cancelada')
                ];
                
                // Traducciones de tipo
                $typeLabels = [
                    'call' => __('tasks.call', [], 'Llamada'),
                    'meeting' => __('tasks.meeting', [], 'Reunión'),
                    'visit' => __('tasks.visit', [], 'Visita'),
                    'follow_up' => __('tasks.follow_up', [], 'Seguimiento'),
                    'administrative' => __('administrative', [], 'Administrativa'),
                    'email' => __('tasks.email', [], 'Email'),
                    'other' => __('tasks.other', [], 'Otro')
                ];
            ?>
            <div class="task-card <?php echo $priorityClass . ' ' . $statusClass . ' ' . $completedClass; ?>" 
                 onclick="window.location.href='ver-tarea.php?id=<?php echo $task['id']; ?>'">
                
                <div class="task-header">
                    <div style="flex: 1;">
                        <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                        
                        <div class="task-badges">
                            <span class="badge-task badge-priority-<?php echo $task['priority']; ?>">
                                <?php echo $priorityLabels[$task['priority']]; ?>
                            </span>
                            <span class="badge-task badge-status-<?php echo $task['status']; ?>">
                                <?php echo $statusLabels[$task['status']]; ?>
                            </span>
                            <?php if ($task['task_type']): ?>
                            <span class="badge-task badge-type">
                                <?php echo $typeLabels[$task['task_type']] ?? $task['task_type']; ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($isOverdue): ?>
                            <span class="overdue-badge">
                                <i class="fas fa-exclamation-circle"></i> <?php echo __('tasks.overdue', [], 'Vencida'); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="task-actions" onclick="event.stopPropagation();">
                        <a href="ver-tarea.php?id=<?php echo $task['id']; ?>" class="btn-task-action">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="editar-tarea.php?id=<?php echo $task['id']; ?>" class="btn-task-action">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                </div>

                <?php if ($task['description']): ?>
                <div style="color: #6b7280; font-size: 14px; margin-bottom: 15px; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars(substr($task['description'], 0, 200))); ?>
                    <?php if (strlen($task['description']) > 200): ?>...<?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="task-meta">
                    <div class="task-meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($task['assigned_name'] ?? __('unassigned', [], 'Sin asignar')); ?></span>
                    </div>
                    
                    <div class="task-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php echo date('d/m/Y H:i', strtotime($task['due_date'])); ?></span>
                    </div>

                    <?php if ($task['client_name']): ?>
                    <div class="task-meta-item">
                        <i class="fas fa-user-tie"></i>
                        <span><?php echo htmlspecialchars($task['client_name'] . ' ' . $task['client_lastname']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($task['property_ref']): ?>
                    <div class="task-meta-item">
                        <i class="fas fa-home"></i>
                        <span><?php echo htmlspecialchars($task['property_ref']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
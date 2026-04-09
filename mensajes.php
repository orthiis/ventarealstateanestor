<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Mensajes y Consultas';
$currentUser = getCurrentUser();

// Obtener filtros
$filterStatus = $_GET['status'] ?? 'all';
$filterAssigned = $_GET['assigned'] ?? 'all';
$filterDate = $_GET['date'] ?? 'all';

// Construir query
$where = ["1=1"];
$params = [];

// Si no es admin, solo ver sus consultas asignadas
if ($currentUser['role']['name'] !== 'administrador') {
    $where[] = "(assigned_to = ? OR assigned_to IS NULL)";
    $params[] = $currentUser['id'];
}

// Filtro por estado
if ($filterStatus !== 'all') {
    $where[] = "status = ?";
    $params[] = $filterStatus;
}

// Filtro por asignado
if ($filterAssigned !== 'all') {
    if ($filterAssigned === 'unassigned') {
        $where[] = "assigned_to IS NULL";
    } else {
        $where[] = "assigned_to = ?";
        $params[] = $filterAssigned;
    }
}

// Filtro por fecha
if ($filterDate === 'today') {
    $where[] = "DATE(created_at) = CURDATE()";
} elseif ($filterDate === 'week') {
    $where[] = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filterDate === 'month') {
    $where[] = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$whereClause = implode(' AND ', $where);

// Obtener consultas
$inquiries = db()->select(
    "SELECT i.*, 
     CONCAT(u.first_name, ' ', u.last_name) as assigned_name,
     p.reference as property_ref, 
     p.title as property_title,
     c.id as client_id,
     CONCAT(c.first_name, ' ', c.last_name) as client_name
     FROM inquiries i
     LEFT JOIN users u ON i.assigned_to = u.id
     LEFT JOIN properties p ON i.property_id = p.id
     LEFT JOIN clients c ON i.client_id = c.id
     WHERE {$whereClause}
     ORDER BY 
        CASE i.status 
            WHEN 'new' THEN 1 
            WHEN 'read' THEN 2 
            WHEN 'replied' THEN 3 
            WHEN 'converted' THEN 4
            WHEN 'archived' THEN 5 
        END,
        i.created_at DESC",
    $params
);

// Estadísticas
$stats = [
    'total' => db()->count('inquiries', $whereClause, $params),
    'new' => db()->count('inquiries', $whereClause . " AND status = 'new'", array_merge($params, ['new'])),
    'read' => db()->count('inquiries', $whereClause . " AND status = 'read'", array_merge($params, ['read'])),
    'replied' => db()->count('inquiries', $whereClause . " AND status = 'replied'", array_merge($params, ['replied'])),
    'converted' => db()->count('inquiries', $whereClause . " AND status = 'converted'", array_merge($params, ['converted'])),
    'unassigned' => db()->count('inquiries', $whereClause . " AND assigned_to IS NULL", $params)
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

    .messages-container {
        padding: 30px;
        background: #f8f9fa;
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

    .message-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        border-left: 4px solid #e2e8f0;
        transition: all 0.3s;
        cursor: pointer;
        position: relative;
    }

    .message-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .message-card.new {
        border-left-color: #10b981;
        background: #f0fdf4;
    }

    .message-card.read {
        border-left-color: #3b82f6;
    }

    .message-card.replied {
        border-left-color: #8b5cf6;
    }

    .message-card.converted {
        border-left-color: #f59e0b;
    }

    .message-card.archived {
        border-left-color: #6b7280;
        opacity: 0.7;
    }

    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }

    .message-sender {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .unread-badge {
        width: 10px;
        height: 10px;
        background: #10b981;
        border-radius: 50%;
        display: inline-block;
    }

    .message-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-new {
        background: #d1fae5;
        color: #065f46;
    }

    .status-read {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-replied {
        background: #ede9fe;
        color: #5b21b6;
    }

    .status-converted {
        background: #fef3c7;
        color: #92400e;
    }

    .status-archived {
        background: #f3f4f6;
        color: #4b5563;
    }

    .message-subject {
        font-size: 14px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 8px;
    }

    .message-preview {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .message-meta {
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
        font-size: 13px;
        color: #9ca3af;
    }

    .message-meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .message-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        margin: 10px 0;
    }

    .stat-label {
        color: #6b7280;
        font-size: 14px;
    }

    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .filter-group {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="messages-container">
        
        <!-- Header -->
        <div class="page-header-modern">
            <h2 class="page-title-modern">
                <i class="fas fa-envelope" style="color: #667eea;"></i> Mensajes y Consultas
            </h2>
            <p class="page-subtitle-modern">Gestiona las consultas recibidas desde la página web</p>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card" style="border-top: 4px solid #667eea;">
                <div class="stat-label">Total Consultas</div>
                <div class="stat-number" style="color: #667eea;"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #10b981;">
                <div class="stat-label">Nuevas</div>
                <div class="stat-number" style="color: #10b981;"><?php echo $stats['new']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #3b82f6;">
                <div class="stat-label">Leídas</div>
                <div class="stat-number" style="color: #3b82f6;"><?php echo $stats['read']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #8b5cf6;">
                <div class="stat-label">Respondidas</div>
                <div class="stat-number" style="color: #8b5cf6;"><?php echo $stats['replied']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #f59e0b;">
                <div class="stat-label">Convertidas</div>
                <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['converted']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #ef4444;">
                <div class="stat-label">Sin Asignar</div>
                <div class="stat-number" style="color: #ef4444;"><?php echo $stats['unassigned']; ?></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filter-bar">
            <form method="GET" class="filter-group">
                <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all">Todos los estados</option>
                    <option value="new" <?php echo $filterStatus === 'new' ? 'selected' : ''; ?>>Nuevas</option>
                    <option value="read" <?php echo $filterStatus === 'read' ? 'selected' : ''; ?>>Leídas</option>
                    <option value="replied" <?php echo $filterStatus === 'replied' ? 'selected' : ''; ?>>Respondidas</option>
                    <option value="converted" <?php echo $filterStatus === 'converted' ? 'selected' : ''; ?>>Convertidas</option>
                    <option value="archived" <?php echo $filterStatus === 'archived' ? 'selected' : ''; ?>>Archivadas</option>
                </select>

                <select name="assigned" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all">Todos los agentes</option>
                    <option value="unassigned" <?php echo $filterAssigned === 'unassigned' ? 'selected' : ''; ?>>Sin asignar</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filterAssigned == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <select name="date" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="all">Todas las fechas</option>
                    <option value="today" <?php echo $filterDate === 'today' ? 'selected' : ''; ?>>Hoy</option>
                    <option value="week" <?php echo $filterDate === 'week' ? 'selected' : ''; ?>>Última semana</option>
                    <option value="month" <?php echo $filterDate === 'month' ? 'selected' : ''; ?>>Último mes</option>
                </select>

                <?php if ($filterStatus !== 'all' || $filterAssigned !== 'all' || $filterDate !== 'all'): ?>
                <a href="mensajes.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Limpiar filtros
                </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Listado de Mensajes -->
        <?php if (empty($inquiries)): ?>
            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
                <i class="fas fa-envelope-open" style="font-size: 64px; color: #e2e8f0; margin-bottom: 20px;"></i>
                <h4 style="color: #6b7280;">No hay consultas</h4>
                <p style="color: #9ca3af;">Las consultas desde la web aparecerán aquí</p>
            </div>
        <?php else: ?>
            <?php foreach ($inquiries as $inquiry): 
                $statusClass = 'status-' . $inquiry['status'];
            ?>
            <div class="message-card <?php echo $inquiry['status']; ?>" 
                 onclick="window.location.href='ver-mensaje.php?id=<?php echo $inquiry['id']; ?>'">
                
                <div class="message-header">
                    <div class="message-sender">
                        <?php if ($inquiry['status'] === 'new'): ?>
                            <span class="unread-badge"></span>
                        <?php endif; ?>
                        <i class="fas fa-user-circle" style="font-size: 20px; color: #667eea;"></i>
                        <?php echo htmlspecialchars($inquiry['name']); ?>
                    </div>
                    
                    <div class="message-badges">
                        <span class="badge-status <?php echo $statusClass; ?>">
                            <?php 
                            $statusLabels = [
                                'new' => 'Nueva',
                                'read' => 'Leída',
                                'replied' => 'Respondida',
                                'converted' => 'Convertida',
                                'archived' => 'Archivada'
                            ];
                            echo $statusLabels[$inquiry['status']];
                            ?>
                        </span>
                    </div>
                </div>

                <?php if ($inquiry['subject']): ?>
                <div class="message-subject">
                    <strong>Asunto:</strong> <?php echo htmlspecialchars($inquiry['subject']); ?>
                </div>
                <?php endif; ?>

                <div class="message-preview">
                    <?php echo htmlspecialchars($inquiry['message']); ?>
                </div>

                <div class="message-meta">
                    <div class="message-meta-item">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($inquiry['email']); ?>
                    </div>

                    <?php if ($inquiry['phone']): ?>
                    <div class="message-meta-item">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($inquiry['phone']); ?>
                    </div>
                    <?php endif; ?>

                    <div class="message-meta-item">
                        <i class="fas fa-calendar"></i>
                        <?php echo timeAgo($inquiry['created_at']); ?>
                    </div>

                    <?php if ($inquiry['property_ref']): ?>
                    <div class="message-meta-item">
                        <i class="fas fa-home"></i>
                        <?php echo htmlspecialchars($inquiry['property_ref']); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($inquiry['assigned_name']): ?>
                    <div class="message-meta-item">
                        <i class="fas fa-user-tag"></i>
                        Asignado a: <?php echo htmlspecialchars($inquiry['assigned_name']); ?>
                    </div>
                    <?php else: ?>
                    <div class="message-meta-item" style="color: #ef4444;">
                        <i class="fas fa-exclamation-circle"></i>
                        Sin asignar
                    </div>
                    <?php endif; ?>

                    <?php if ($inquiry['client_name']): ?>
                    <div class="message-meta-item" style="color: #10b981;">
                        <i class="fas fa-check-circle"></i>
                        Cliente: <?php echo htmlspecialchars($inquiry['client_name']); ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="message-actions" onclick="event.stopPropagation();">
                    <a href="ver-mensaje.php?id=<?php echo $inquiry['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> Ver
                    </a>
                    
                    <?php if ($inquiry['status'] !== 'converted'): ?>
                    <button class="btn btn-sm btn-success" onclick="quickAssign(<?php echo $inquiry['id']; ?>)">
                        <i class="fas fa-user-plus"></i> Asignar
                    </button>
                    <?php endif; ?>

                    <?php if ($inquiry['status'] === 'new'): ?>
                    <button class="btn btn-sm btn-info" onclick="markAsRead(<?php echo $inquiry['id']; ?>)">
                        <i class="fas fa-check"></i> Marcar leída
                    </button>
                    <?php endif; ?>

                    <?php if ($inquiry['status'] !== 'archived'): ?>
                    <button class="btn btn-sm btn-outline-secondary" onclick="archiveMessage(<?php echo $inquiry['id']; ?>)">
                        <i class="fas fa-archive"></i> Archivar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function markAsRead(id) {
    fetch('ajax/mensaje-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al actualizar');
        }
    });
}

function quickAssign(id) {
    const userId = prompt('ID del usuario a asignar (o cancela para asignarte a ti):');
    
    fetch('ajax/mensaje-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=assign&id=${id}&user_id=${userId || ''}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al asignar');
        }
    });
}

function archiveMessage(id) {
    if (!confirm('¿Archivar esta consulta?')) return;
    
    fetch('ajax/mensaje-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=archive&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al archivar');
        }
    });
}
</script>

<?php include 'footer.php'; ?>
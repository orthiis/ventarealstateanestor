<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('menu.users', [], 'Gestión de Usuarios');
$currentUser = getCurrentUser();

// Solo administradores pueden ver esta página
if ($currentUser['role']['name'] !== 'administrador') {
    setFlashMessage('error', __('no_permission', [], 'No tienes permisos para acceder a esta página'));
    redirect('dashboard.php');
}

// Obtener usuarios
$users = db()->select(
    "SELECT u.*, r.display_name as role_name, o.name as office_name
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN offices o ON u.office_id = o.id
     ORDER BY u.created_at DESC"
);

// Estadísticas
$stats = [
    'total' => count($users),
    'active' => count(array_filter($users, fn($u) => $u['status'] === 'active')),
    'inactive' => count(array_filter($users, fn($u) => $u['status'] !== 'active')),
    'admins' => count(array_filter($users, fn($u) => $u['role_id'] == 1))
];

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

.users-container {
    padding: 30px;
    background: #f8f9fa;
}

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

.stats-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card-modern {
    background: white;
    padding: 25px;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.stat-card-modern.purple { border-left: 4px solid #8b5cf6; }
.stat-card-modern.green { border-left: 4px solid #10b981; }
.stat-card-modern.red { border-left: 4px solid #ef4444; }
.stat-card-modern.blue { border-left: 4px solid #3b82f6; }

.stat-value-modern {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
}

.stat-label-modern {
    font-size: 13px;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
}

.stat-icon-modern {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.table-card-modern {
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-header-modern {
    padding: 20px 25px;
    border-bottom: 2px solid #f3f4f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-title-modern {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
}

.table-modern {
    width: 100%;
    margin: 0;
}

.table-modern thead {
    background: #f8f9fa;
}

.table-modern thead th {
    padding: 15px 20px;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.table-modern tbody td {
    padding: 18px 20px;
    border-top: 1px solid #f3f4f6;
    vertical-align: middle;
    font-size: 14px;
    color: #4b5563;
}

.table-modern tbody tr:hover {
    background: #f9fafb;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.badge-modern {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-active { background: #d1fae5; color: #065f46; }
.badge-inactive { background: #fee2e2; color: #991b1b; }

.btn-modern {
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-outline-modern {
    background: white;
    border: 2px solid #e5e7eb;
    color: #4b5563;
}

.btn-outline-modern:hover {
    border-color: #667eea;
    color: #667eea;
}
</style>

<div class="users-container">
    
    <div class="page-header-modern">
        <div>
            <h2 class="page-title-modern">
                <i class="fas fa-user-cog" style="color: #667eea;"></i>
                <?php echo __('menu.users', [], 'Gestión de Usuarios'); ?>
            </h2>
            <p class="page-subtitle-modern">
                <?php echo __('users.subtitle', [], 'Administra los usuarios del sistema'); ?>
            </p>
        </div>
        <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#newUserModal">
            <i class="fas fa-plus"></i>
            <?php echo __('users.add_user', [], 'Añadir Usuario'); ?>
        </button>
    </div>

    <div class="stats-grid-modern">
        <div class="stat-card-modern purple">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label-modern"><?php echo __('total', [], 'Total'); ?> <?php echo __('menu.users', [], 'Usuarios'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #f3e8ff; color: #8b5cf6;">
                <i class="fas fa-users"></i>
            </div>
        </div>
        
        <div class="stat-card-modern green">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-label-modern"><?php echo __('active', [], 'Activos'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #d1fae5; color: #10b981;">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
        
        <div class="stat-card-modern red">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['inactive']); ?></div>
                <div class="stat-label-modern"><?php echo __('inactive', [], 'Inactivos'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #fee2e2; color: #ef4444;">
                <i class="fas fa-user-times"></i>
            </div>
        </div>
        
        <div class="stat-card-modern blue">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['admins']); ?></div>
                <div class="stat-label-modern"><?php echo __('users.admins', [], 'Administradores'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #dbeafe; color: #3b82f6;">
                <i class="fas fa-user-shield"></i>
            </div>
        </div>
    </div>

    <div class="table-card-modern">
        <div class="table-header-modern">
            <h5 class="table-title-modern">
                <i class="fas fa-list me-2"></i>
                <?php echo __('users.list', [], 'Listado de Usuarios'); ?> (<?php echo count($users); ?>)
            </h5>
        </div>

        <div style="overflow-x: auto;">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo __('user', [], 'User'); ?></th>
                        <th><?php echo __('name', [], 'Name'); ?></th>
                        <th><?php echo __('email', [], 'Email'); ?></th>
                        <th><?php echo __('role', [], 'Rol'); ?></th>
                        <th><?php echo __('office', [], 'Office'); ?></th>
                        <th><?php echo __('status', [], 'Status'); ?></th>
                        <th><?php echo __('last_login', [], 'Last Access'); ?></th>
                        <th><?php echo __('actions', [], 'Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?php echo $user['profile_picture'] ?? 'assets/images/default-avatar.png'; ?>" 
                                     class="user-avatar" 
                                     alt="<?php echo htmlspecialchars($user['username']); ?>">
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['office_name'] ?? '-'); ?></td>
                        <td>
                            <span class="badge-modern badge-<?php echo $user['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo $user['status'] === 'active' ? __('active', [], 'Activo') : __('inactive', [], 'Inactivo'); ?>
                            </span>
                        </td>
                        <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-'; ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="editar-usuario.php?id=<?php echo $user['id']; ?>" class="btn-modern btn-outline-modern" title="<?php echo __('edit', [], 'Editar'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>
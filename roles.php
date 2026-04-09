<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Gestión de Roles';
$currentUser = getCurrentUser();

if ($currentUser['role']['name'] !== 'administrador') {
    setFlashMessage('error', 'No tienes permisos');
    redirect('dashboard.php');
}

$roles = db()->select("SELECT * FROM roles ORDER BY name");

include 'header.php';
include 'sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Gestión de Roles y Permisos</h1>
            <p class="text-muted mb-0">Administra los roles del sistema</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRoleModal">
            <i class="fas fa-plus me-2"></i> Añadir Rol
        </button>
    </div>

    <div class="row g-4">
        <?php foreach ($roles as $role): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?php echo $role['display_name']; ?></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php echo $role['description']; ?></p>
                    
                    <h6 class="fw-bold mb-2">Permisos:</h6>
                    <div class="permissions-list">
                        <?php 
                        $permissions = json_decode($role['permissions'], true);
                        if ($permissions):
                            foreach ($permissions as $module => $actions):
                        ?>
                        <div class="mb-2">
                            <strong class="text-primary"><?php echo ucfirst($module); ?>:</strong>
                            <br>
                            <small class="text-muted">
                                <?php echo implode(', ', $actions); ?>
                            </small>
                        </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button class="btn btn-sm btn-outline-primary me-2" onclick="editRole(<?php echo $role['id']; ?>)">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <?php if ($role['name'] !== 'administrador'): ?>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteRole(<?php echo $role['id']; ?>)">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';
require_once 'includes/functions.php';

session_start();
requireClientLogin();

$pageTitle = 'Mi Perfil';
$currentClient = getCurrentClient();

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $updateData = [
            'first_name' => sanitizeInput($_POST['first_name']),
            'last_name' => sanitizeInput($_POST['last_name']),
            'phone_mobile' => sanitizeInput($_POST['phone_mobile']),
            'phone_home' => sanitizeInput($_POST['phone_home'] ?? ''),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'city' => sanitizeInput($_POST['city'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        db()->update('clients', $updateData, 'id = ?', [$currentClient['id']]);
        
        $_SESSION['flash_message'] = 'Perfil actualizado correctamente';
        $_SESSION['flash_type'] = 'success';
        header('Location: /clientes/perfil.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error al actualizar perfil: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validar contraseña actual
        if (!password_verify($currentPassword, $currentClient['password'])) {
            throw new Exception('La contraseña actual es incorrecta');
        }
        
        // Validar nueva contraseña
        if (strlen($newPassword) < 8) {
            throw new Exception('La nueva contraseña debe tener al menos 8 caracteres');
        }
        
        if ($newPassword !== $confirmPassword) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        // Actualizar contraseña
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        db()->update('clients', 
            ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
            'id = ?', 
            [$currentClient['id']]
        );
        
        $_SESSION['flash_message'] = 'Contraseña actualizada correctamente';
        $_SESSION['flash_type'] = 'success';
        header('Location: /clientes/perfil.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error al cambiar contraseña: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

include 'includes/header.php';
?>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php 
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    ?>
<?php endif; ?>

<div class="page-header">
    <h1><i class="fas fa-user-circle me-2"></i> Mi Perfil</h1>
    <p class="text-muted mb-0">Gestiona tu información personal y configuración</p>
</div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user me-2 text-primary"></i> Información Personal</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['email']); ?>" disabled>
                            <small class="text-muted">El email no puede ser modificado</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Referencia de Cliente</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['reference']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Teléfono Móvil *</label>
                            <input type="tel" name="phone_mobile" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['phone_mobile']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono Casa</label>
                            <input type="tel" name="phone_home" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['phone_home'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Dirección</label>
                        <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($currentClient['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="city" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado/Provincia</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($currentClient['state_province'] ?? ''); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-lock me-2 text-warning"></i> Cambiar Contraseña</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Contraseña Actual *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña *</label>
                        <input type="password" name="new_password" class="form-control" minlength="8" required>
                        <small class="text-muted">Mínimo 8 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar Nueva Contraseña *</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i> Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Account Info -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Información de Cuenta</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Cliente desde</small>
                    <strong><?php echo date('d/m/Y', strtotime($currentClient['created_at'])); ?></strong>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted d-block">Último acceso</small>
                    <strong>
                        <?php 
                        echo $currentClient['last_login'] 
                            ? date('d/m/Y H:i', strtotime($currentClient['last_login']))
                            : 'Nunca';
                        ?>
                    </strong>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted d-block">Tipo de Cliente</small>
                    <strong><?php echo ucfirst($currentClient['client_type']); ?></strong>
                </div>
                
                <?php if ($currentClient['payment_day']): ?>
                <div class="mb-0">
                    <small class="text-muted d-block">Día de Pago</small>
                    <strong>Día <?php echo $currentClient['payment_day']; ?> de cada mes</strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Agent Contact -->
        <?php if ($currentClient['agent_name']): ?>
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Tu Agente</h5>
            </div>
            <div class="card-body text-center">
                <i class="fas fa-user-circle fa-4x text-muted mb-3"></i>
                <h6 class="mb-2"><?php echo htmlspecialchars($currentClient['agent_name']); ?></h6>
                <?php if ($currentClient['agent_email']): ?>
                    <p class="mb-1 small">
                        <i class="fas fa-envelope me-1"></i>
                        <a href="mailto:<?php echo htmlspecialchars($currentClient['agent_email']); ?>">
                            <?php echo htmlspecialchars($currentClient['agent_email']); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if ($currentClient['agent_phone']): ?>
                    <p class="mb-0 small">
                        <i class="fas fa-phone me-1"></i>
                        <a href="tel:<?php echo htmlspecialchars($currentClient['agent_phone']); ?>">
                            <?php echo htmlspecialchars($currentClient['agent_phone']); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
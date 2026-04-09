<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('user.my_profile', [], 'Mi Perfil');
$currentUser = getCurrentUser();

// Obtener estadísticas del usuario
$userStats = [
    'total_properties' => (int)db()->selectValue(
        "SELECT COUNT(*) FROM properties WHERE agent_id = ?",
        [$currentUser['id']]
    ),
    'active_properties' => (int)db()->selectValue(
        "SELECT COUNT(*) FROM properties WHERE agent_id = ? AND status = 'available'",
        [$currentUser['id']]
    ),
    'total_clients' => (int)db()->selectValue(
        "SELECT COUNT(*) FROM clients WHERE assigned_agent_id = ?",
        [$currentUser['id']]
    ),
    'pending_tasks' => (int)db()->selectValue(
        "SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('pending', 'in_progress')",
        [$currentUser['id']]
    ),
    'completed_sales' => (int)db()->selectValue(
        "SELECT COUNT(*) FROM sales_transactions WHERE agent_id = ? AND status = 'completed'",
        [$currentUser['id']]
    ),
    'total_commission' => (float)db()->selectValue(
        "SELECT COALESCE(SUM(agent_commission), 0) FROM sales_transactions WHERE agent_id = ? AND status = 'completed'",
        [$currentUser['id']]
    )
];

// Obtener actividad reciente
$recentActivity = db()->select(
    "SELECT * FROM activity_log 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 10",
    [$currentUser['id']]
);

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_profile') {
        try {
            $updateData = [
                'first_name' => sanitize($_POST['first_name']),
                'last_name' => sanitize($_POST['last_name']),
                'phone' => sanitize($_POST['phone']),
                'phone_office' => sanitize($_POST['phone_office'] ?? ''),
                'biography' => sanitize($_POST['biography'] ?? ''),
                'linkedin_url' => sanitize($_POST['linkedin_url'] ?? ''),
                'facebook_url' => sanitize($_POST['facebook_url'] ?? ''),
                'instagram_url' => sanitize($_POST['instagram_url'] ?? ''),
                'twitter_url' => sanitize($_POST['twitter_url'] ?? ''),
                'whatsapp_business' => sanitize($_POST['whatsapp_business'] ?? '')
            ];
            
            // Actualizar foto de perfil si se subió
            if (!empty($_FILES['profile_picture']['name'])) {
                $upload = uploadFile($_FILES['profile_picture'], USER_IMAGES_PATH);
                if ($upload['success']) {
                    $updateData['profile_picture'] = USER_IMAGES_URL . $upload['filename'];
                    
                    // Eliminar foto anterior si existe
                    if ($currentUser['profile_picture']) {
                        deleteFile($currentUser['profile_picture']);
                    }
                }
            }
            
            db()->update('users', $updateData, 'id = ?', [$currentUser['id']]);
            
            setFlashMessage('success', __('profile_updated', [], 'Perfil actualizado correctamente'));
            redirect('perfil.php');
            
        } catch (Exception $e) {
            setFlashMessage('error', __('error_updating_profile', [], 'Error al actualizar el perfil'));
        }
    }
    
    // Cambiar contraseña
    elseif ($_POST['action'] === 'change_password') {
        try {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Verificar contraseña actual
            if (!verifyPassword($currentPassword, $currentUser['password'])) {
                throw new Exception(__('incorrect_current_password', [], 'La contraseña actual es incorrecta'));
            }
            
            // Verificar que las contraseñas coincidan
            if ($newPassword !== $confirmPassword) {
                throw new Exception(__('passwords_dont_match', [], 'Las contraseñas no coinciden'));
            }
            
            // Validar longitud mínima
            if (strlen($newPassword) < 8) {
                throw new Exception(__('password_too_short', [], 'La contraseña debe tener al menos 8 caracteres'));
            }
            
            // Actualizar contraseña
            db()->update('users', [
                'password' => hashPassword($newPassword)
            ], 'id = ?', [$currentUser['id']]);
            
            setFlashMessage('success', __('password_changed', [], 'Contraseña cambiada correctamente'));
            redirect('perfil.php');
            
        } catch (Exception $e) {
            setFlashMessage('error', $e->getMessage());
        }
    }
    
    // Cambiar idioma
    elseif ($_POST['action'] === 'change_language') {
        try {
            $language = $_POST['language'];
            
            if (!in_array($language, ['en', 'es'])) {
                throw new Exception(__('invalid_language', [], 'Idioma no válido'));
            }
            
            db()->update('users', [
                'language' => $language
            ], 'id = ?', [$currentUser['id']]);
            
            $_SESSION['user_language'] = $language;
            
            setFlashMessage('success', __('language_changed', [], 'Idioma cambiado correctamente'));
            redirect('perfil.php');
            
        } catch (Exception $e) {
            setFlashMessage('error', $e->getMessage());
        }
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

    .profile-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    /* Header Profile */
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .profile-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .profile-header-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 30px;
    }

    .profile-avatar-large {
        width: 120px;
        height: 120px;
        border-radius: 20px;
        object-fit: cover;
        border: 5px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .profile-info h2 {
        font-size: 32px;
        font-weight: 700;
        margin: 0 0 8px 0;
    }

    .profile-role {
        font-size: 16px;
        opacity: 0.9;
        margin-bottom: 15px;
    }

    .profile-badges {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .profile-badge {
        padding: 6px 14px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Stats Grid */
    .stats-grid-profile {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card-profile {
        background: white;
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card-profile::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary) 0%, var(--purple) 100%);
    }

    .stat-card-profile:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }

    .stat-icon-profile {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 12px;
    }

    .stat-value-profile {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .stat-label-profile {
        font-size: 13px;
        color: #6b7280;
        font-weight: 600;
    }

    /* Tabs Navigation */
    .profile-tabs {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .profile-tabs .nav-tabs {
        border-bottom: none;
        padding: 15px 25px 0 25px;
        gap: 8px;
        background: linear-gradient(to bottom, #f8f9fa 0%, white 100%);
        display: flex;
        flex-wrap: wrap;
    }

    .profile-tabs .nav-link {
        border: none;
        color: #6b7280;
        font-weight: 600;
        font-size: 14px;
        padding: 14px 24px;
        border-radius: 12px 12px 0 0;
        background: transparent;
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .profile-tabs .nav-link i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .profile-tabs .nav-link:hover {
        color: var(--primary);
        background: rgba(102, 126, 234, 0.08);
    }

    .profile-tabs .nav-link:hover i {
        transform: scale(1.1);
    }

    .profile-tabs .nav-link.active {
        color: white;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .profile-tabs .tab-content {
        padding: 35px;
        background: white;
    }

    /* Form Cards */
    .form-card-profile {
        background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        padding: 28px;
        border-radius: 16px;
        margin-bottom: 28px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .form-card-title {
        font-size: 19px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e5e7eb;
    }

    .form-card-title i {
        color: var(--primary);
        font-size: 20px;
    }

    .form-label-profile {
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .form-control-profile {
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        padding: 12px 16px;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    .form-control-profile:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    /* Avatar Upload */
    .avatar-upload-area {
        display: flex;
        align-items: center;
        gap: 24px;
        padding: 24px;
        background: white;
        border-radius: 12px;
        border: 2px dashed #e5e7eb;
        transition: all 0.3s ease;
    }

    .avatar-upload-area:hover {
        border-color: var(--primary);
        background: #f9fafb;
    }

    .avatar-preview {
        width: 100px;
        height: 100px;
        border-radius: 16px;
        object-fit: cover;
        border: 3px solid #e5e7eb;
    }

    .avatar-upload-text h6 {
        font-weight: 600;
        margin-bottom: 8px;
        color: #1f2937;
    }

    .avatar-upload-text p {
        font-size: 13px;
        color: #6b7280;
        margin: 0;
    }

    /* Language Selector */
    .language-option {
        padding: 16px 20px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .language-option:hover {
        border-color: var(--primary);
        background: #f9fafb;
    }

    .language-option.active {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    }

    .language-flag {
        font-size: 32px;
    }

    .language-info h6 {
        margin: 0;
        font-weight: 600;
        color: #1f2937;
    }

    .language-info p {
        margin: 0;
        font-size: 13px;
        color: #6b7280;
    }

    /* Activity Feed */
    .activity-item {
        padding: 16px;
        border-left: 3px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: all 0.3s ease;
    }

    .activity-item:hover {
        border-left-color: var(--primary);
        background: #f9fafb;
        transform: translateX(4px);
    }

    .activity-time {
        font-size: 12px;
        color: #9ca3af;
    }

    .activity-description {
        color: #374151;
        margin: 4px 0 0 0;
    }

    /* Buttons */
    .btn-save-profile {
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
    }

    .btn-save-profile:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .btn-secondary-profile {
        background: white;
        border: 2px solid #e5e7eb;
        color: #374151;
        padding: 14px 32px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s ease;
    }

    .btn-secondary-profile:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f9fafb;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .profile-container {
            padding: 15px;
        }

        .profile-header-content {
            flex-direction: column;
            text-align: center;
        }

        .profile-info h2 {
            font-size: 24px;
        }

        .profile-tabs .tab-content {
            padding: 20px 15px;
        }

        .stats-grid-profile {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="profile-container">
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-header-content">
            <?php if($currentUser['profile_picture']): ?>
            <img src="<?php echo htmlspecialchars($currentUser['profile_picture']); ?>" 
                 alt="<?php echo htmlspecialchars($currentUser['first_name']); ?>"
                 class="profile-avatar-large">
            <?php else: ?>
            <div class="profile-avatar-large" style="background: rgba(255, 255, 255, 0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 32px;">
                <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
            </div>
            <?php endif; ?>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h2>
                <div class="profile-role">
                    <i class="fas fa-briefcase me-2"></i>
                    <?php echo htmlspecialchars($currentUser['role']['display_name'] ?? __('user', [], 'Usuario')); ?>
                </div>
                <div class="profile-badges">
                    <span class="profile-badge">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($currentUser['email']); ?>
                    </span>
                    <?php if($currentUser['phone']): ?>
                    <span class="profile-badge">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($currentUser['phone']); ?>
                    </span>
                    <?php endif; ?>
                    <span class="profile-badge">
                        <i class="fas fa-calendar"></i>
                        <?php echo __('member_since', [], 'Miembro desde'); ?> <?php echo date('M Y', strtotime($currentUser['created_at'])); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid-profile">
        <div class="stat-card-profile">
            <div class="stat-icon-profile" style="background: #dbeafe; color: #1e40af;">
                <i class="fas fa-home"></i>
            </div>
            <div class="stat-value-profile" style="color: #1e40af;">
                <?php echo $userStats['total_properties']; ?>
            </div>
            <div class="stat-label-profile">
                <?php echo __('my_properties', [], 'Mis Propiedades'); ?>
            </div>
        </div>

        <div class="stat-card-profile">
            <div class="stat-icon-profile" style="background: #d1fae5; color: #065f46;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value-profile" style="color: #065f46;">
                <?php echo $userStats['active_properties']; ?>
            </div>
            <div class="stat-label-profile">
                <?php echo __('active_properties', [], 'Propiedades Activas'); ?>
            </div>
        </div>

        <div class="stat-card-profile">
            <div class="stat-icon-profile" style="background: #fef3c7; color: #92400e;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value-profile" style="color: #92400e;">
                <?php echo $userStats['total_clients']; ?>
            </div>
            <div class="stat-label-profile">
                <?php echo __('my_clients', [], 'Mis Clientes'); ?>
            </div>
        </div>

        <div class="stat-card-profile">
            <div class="stat-icon-profile" style="background: #fee2e2; color: #991b1b;">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-value-profile" style="color: #991b1b;">
                <?php echo $userStats['pending_tasks']; ?>
            </div>
            <div class="stat-label-profile">
                <?php echo __('pending_tasks', [], 'Tareas Pendientes'); ?>
            </div>
        </div>

        <div class="stat-card-profile">
            <div class="stat-icon-profile" style="background: #e0e7ff; color: #4338ca;">
                <i class="fas fa-handshake"></i>
            </div>
            <div class="stat-value-profile" style="color: #4338ca;">
                <?php echo $userStats['completed_sales']; ?>
            </div>
            <div class="stat-label-profile">
                <?php echo __('completed_sales', [], 'Ventas Completadas'); ?>
            </div>
        </div>

        <div class="stat-card-profile">
            <div class="stat-icon-profile" style="background: #fce7f3; color: #9f1239;">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-value-profile" style="color: #9f1239; font-size: 20px;">
                $<?php echo number_format($userStats['total_commission'], 0); ?>
            </div>
            <div class="stat-label-profile">
                <?php echo __('total_commissions', [], 'Comisiones Totales'); ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="profile-tabs">
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">
                    <i class="fas fa-user"></i>
                    <?php echo __('personal_info', [], 'Información Personal'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                    <i class="fas fa-lock"></i>
                    <?php echo __('user.security', [], 'Seguridad'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button">
                    <i class="fas fa-cog"></i>
                    <?php echo __('user.preferences', [], 'Preferencias'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                    <i class="fas fa-history"></i>
                    <?php echo __('activity', [], 'Actividad'); ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="profileTabContent">
            
            <!-- TAB: INFORMACIÓN PERSONAL -->
            <div class="tab-pane fade show active" id="info">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <!-- Foto de Perfil -->
                    <div class="form-card-profile">
                        <h5 class="form-card-title">
                            <i class="fas fa-camera"></i>
                            <?php echo __('profile_picture', [], 'Foto de Perfil'); ?>
                        </h5>

                        <div class="avatar-upload-area">
                            <img src="<?php echo $currentUser['profile_picture'] ?? 'assets/images/default-avatar.png'; ?>" 
                                 alt="Avatar" 
                                 class="avatar-preview"
                                 id="avatarPreview">
                            
                            <div class="avatar-upload-text">
                                <h6><?php echo __('upload_new_photo', [], 'Subir Nueva Foto'); ?></h6>
                                <p><?php echo __('max_size_5mb', [], 'Máximo 5MB. Formatos: JPG, PNG, WEBP'); ?></p>
                                <input type="file" 
                                       name="profile_picture" 
                                       class="form-control mt-2" 
                                       accept="image/*"
                                       onchange="previewAvatar(this)">
                            </div>
                        </div>
                    </div>

                    <!-- Información Básica -->
                    <div class="form-card-profile">
                        <h5 class="form-card-title">
                            <i class="fas fa-user-circle"></i>
                            <?php echo __('basic_info', [], 'Información Básica'); ?>
                        </h5>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fas fa-user"></i>
                                    <?php echo __('first_name', [], 'Nombre'); ?>
                                </label>
                                <input type="text" 
                                       name="first_name" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fas fa-user"></i>
                                    <?php echo __('last_name', [], 'Apellidos'); ?>
                                </label>
                                <input type="text" 
                                       name="last_name" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo __('email', [], 'Email'); ?>
                                </label>
                                <input type="email" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                                       disabled
                                       style="background: #f3f4f6; cursor: not-allowed;">
                                <small class="text-muted">
                                    <?php echo __('email_cannot_be_changed', [], 'El email no puede ser modificado'); ?>
                                </small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fas fa-phone"></i>
                                    <?php echo __('phone', [], 'Teléfono Móvil'); ?>
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fas fa-phone-office"></i>
                                    <?php echo __('office_phone', [], 'Teléfono de Oficina'); ?>
                                </label>
                                <input type="tel" 
                                       name="phone_office" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['phone_office'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fab fa-whatsapp"></i>
                                    <?php echo __('whatsapp', [], 'WhatsApp Business'); ?>
                                </label>
                                <input type="tel" 
                                       name="whatsapp_business" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['whatsapp_business'] ?? ''); ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label-profile">
                                    <i class="fas fa-align-left"></i>
                                    <?php echo __('biography', [], 'Biografía'); ?>
                                </label>
                                <textarea name="biography" 
                                          class="form-control form-control-profile" 
                                          rows="4"><?php echo htmlspecialchars($currentUser['biography'] ?? ''); ?></textarea>
                                <small class="text-muted">
                                    <?php echo __('bio_description', [], 'Breve descripción profesional que aparecerá en tu perfil público'); ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Redes Sociales -->
                    <div class="form-card-profile">
                        <h5 class="form-card-title">
                            <i class="fas fa-share-alt"></i>
                            <?php echo __('social_media', [], 'Redes Sociales'); ?>
                        </h5>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fab fa-linkedin" style="color: #0A66C2;"></i>
                                    LinkedIn
                                </label>
                                <input type="url" 
                                       name="linkedin_url" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['linkedin_url'] ?? ''); ?>"
                                       placeholder="https://linkedin.com/in/tu-perfil">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fab fa-facebook" style="color: #1877F2;"></i>
                                    Facebook
                                </label>
                                <input type="url" 
                                       name="facebook_url" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['facebook_url'] ?? ''); ?>"
                                       placeholder="https://facebook.com/tu-perfil">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fab fa-instagram" style="color: #E4405F;"></i>
                                    Instagram
                                </label>
                                <input type="url" 
                                       name="instagram_url" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['instagram_url'] ?? ''); ?>"
                                       placeholder="https://instagram.com/tu-perfil">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fab fa-twitter" style="color: #1DA1F2;"></i>
                                    Twitter / X
                                </label>
                                <input type="url" 
                                       name="twitter_url" 
                                       class="form-control form-control-profile" 
                                       value="<?php echo htmlspecialchars($currentUser['twitter_url'] ?? ''); ?>"
                                       placeholder="https://twitter.com/tu-perfil">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn-save-profile">
                            <i class="fas fa-save"></i>
                            <?php echo __('save_changes', [], 'Guardar Cambios'); ?>
                        </button>
                        <button type="button" class="btn-secondary-profile" onclick="window.location.reload()">
                            <i class="fas fa-times"></i>
                            <?php echo __('cancel', [], 'Cancelar'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- TAB: SEGURIDAD -->
            <div class="tab-pane fade" id="security">
                <form method="POST" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-card-profile">
                        <h5 class="form-card-title">
                            <i class="fas fa-key"></i>
                            <?php echo __('user.change_password', [], 'Cambiar Contraseña'); ?>
                        </h5>

                        <div class="alert alert-info" style="border-left: 4px solid var(--info); background: #dbeafe; border-radius: 10px; padding: 16px;">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo __('password_requirements_text', [], 'La contraseña debe tener al menos 8 caracteres e incluir mayúsculas, minúsculas y números'); ?>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label-profile">
                                    <i class="fas fa-lock"></i>
                                    <?php echo __('user.current_password', [], 'Contraseña Actual'); ?>
                                </label>
                                <input type="password" 
                                       name="current_password" 
                                       class="form-control form-control-profile" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fas fa-lock"></i>
                                    <?php echo __('user.new_password', [], 'Nueva Contraseña'); ?>
                                </label>
                                <input type="password" 
                                       name="new_password" 
                                       class="form-control form-control-profile" 
                                       id="newPassword"
                                       minlength="8"
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-profile">
                                    <i class="fas fa-lock"></i>
                                    <?php echo __('user.confirm_password', [], 'Confirmar Contraseña'); ?>
                                </label>
                                <input type="password" 
                                       name="confirm_password" 
                                       class="form-control form-control-profile" 
                                       id="confirmPassword"
                                       minlength="8"
                                       required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-save-profile">
                        <i class="fas fa-shield-alt"></i>
                        <?php echo __('change_password', [], 'Cambiar Contraseña'); ?>
                    </button>
                </form>
            </div>

            <!-- TAB: PREFERENCIAS -->
            <div class="tab-pane fade" id="preferences">
                <form method="POST">
                    <input type="hidden" name="action" value="change_language">
                    
                    <div class="form-card-profile">
                        <h5 class="form-card-title">
                            <i class="fas fa-globe"></i>
                            <?php echo __('user.language', [], 'Idioma del Sistema'); ?>
                        </h5>

                        <p class="mb-4" style="color: #6b7280;">
                            <?php echo __('select_preferred_language', [], 'Selecciona tu idioma preferido para la interfaz del sistema'); ?>
                        </p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="language-option <?php echo currentLanguage() === 'es' ? 'active' : ''; ?>">
                                    <input type="radio" 
                                           name="language" 
                                           value="es" 
                                           <?php echo currentLanguage() === 'es' ? 'checked' : ''; ?>
                                           style="display: none;">
                                    <span class="language-flag">🇪🇸</span>
                                    <div class="language-info">
                                        <h6>Español</h6>
                                        <p><?php echo __('spanish_description', [], 'Interfaz en español'); ?></p>
                                    </div>
                                    <?php if(currentLanguage() === 'es'): ?>
                                    <i class="fas fa-check-circle ms-auto" style="color: var(--success); font-size: 20px;"></i>
                                    <?php endif; ?>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <label class="language-option <?php echo currentLanguage() === 'en' ? 'active' : ''; ?>">
                                    <input type="radio" 
                                           name="language" 
                                           value="en" 
                                           <?php echo currentLanguage() === 'en' ? 'checked' : ''; ?>
                                           style="display: none;">
                                    <span class="language-flag">🇺🇸</span>
                                    <div class="language-info">
                                        <h6>English</h6>
                                        <p><?php echo __('english_description', [], 'English interface'); ?></p>
                                    </div>
                                    <?php if(currentLanguage() === 'en'): ?>
                                    <i class="fas fa-check-circle ms-auto" style="color: var(--success); font-size: 20px;"></i>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-save-profile">
                        <i class="fas fa-check"></i>
                        <?php echo __('save_preferences', [], 'Guardar Preferencias'); ?>
                    </button>
                </form>
            </div>

            <!-- TAB: ACTIVIDAD -->
            <div class="tab-pane fade" id="activity">
                <div class="form-card-profile">
                    <h5 class="form-card-title">
                        <i class="fas fa-clock"></i>
                        <?php echo __('recent_activity', [], 'Actividad Reciente'); ?>
                    </h5>

                    <?php if(empty($recentActivity)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #d1d5db;"></i>
                        <p class="mt-3 text-muted">
                            <?php echo __('no_recent_activity', [], 'No hay actividad reciente'); ?>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="activity-feed">
                        <?php foreach($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                    <p class="activity-description">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </p>
                                </div>
                                <span class="activity-time">
                                    <?php echo timeAgo($activity['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
// Preview de avatar
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Validación de contraseñas
document.getElementById('changePasswordForm')?.addEventListener('submit', function(e) {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: '<?php echo __('error', [], 'Error'); ?>',
            text: '<?php echo __('passwords_dont_match', [], 'Las contraseñas no coinciden'); ?>'
        });
        return false;
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: '<?php echo __('error', [], 'Error'); ?>',
            text: '<?php echo __('password_too_short', [], 'La contraseña debe tener al menos 8 caracteres'); ?>'
        });
        return false;
    }
});

// Selección de idioma con radio buttons
document.querySelectorAll('.language-option').forEach(option => {
    option.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
        
        document.querySelectorAll('.language-option').forEach(opt => {
            opt.classList.remove('active');
        });
        this.classList.add('active');
    });
});
</script>

<?php include 'footer.php'; ?>
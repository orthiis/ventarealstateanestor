<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('settings.title', [], 'Configuración del Sistema');
$currentUser = getCurrentUser();

// Solo administradores pueden acceder
if ($currentUser['role']['name'] !== 'administrador') {
    setFlashMessage('error', __('no_permissions', [], 'No tienes permisos para acceder a esta sección'));
    redirect('dashboard.php');
}

// Obtener todas las configuraciones actuales
$settings = [];
$settingsFromDB = db()->select("SELECT * FROM system_settings ORDER BY category, display_order");
foreach ($settingsFromDB as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Obtener tipos de propiedad
$propertyTypes = db()->select("SELECT * FROM property_types ORDER BY display_order");

// Obtener características
$features = db()->select("SELECT * FROM features ORDER BY category, display_order");

// Obtener oficinas
$offices = db()->select("SELECT * FROM offices ORDER BY name");

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

    .config-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }

    /* Page Header */
    .page-header-modern {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        color: white;
    }

    .page-title-modern {
        font-size: 32px;
        font-weight: 700;
        color: white;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .page-title-modern i {
        font-size: 36px;
        opacity: 0.9;
    }

    .page-subtitle-modern {
        color: rgba(255, 255, 255, 0.9);
        margin: 0;
        font-size: 16px;
        font-weight: 400;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card-config {
        background: white;
        padding: 25px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        gap: 15px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card-config::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary) 0%, var(--purple) 100%);
    }

    .stat-card-config:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }

    .stat-icon-config {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .stat-number-config {
        font-size: 32px;
        font-weight: 700;
        line-height: 1;
    }

    .stat-label-config {
        font-size: 14px;
        color: #6b7280;
        font-weight: 600;
    }

    /* Alert Config */
    .alert-config {
        border: none;
        border-radius: 12px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
    }

    /* Elegant Tabs */
    .config-tabs {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .nav-tabs {
        border-bottom: none;
        padding: 15px 25px 0 25px;
        gap: 8px;
        background: linear-gradient(to bottom, #f8f9fa 0%, white 100%);
        display: flex;
        flex-wrap: wrap;
    }

    .nav-tabs .nav-link {
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

    .nav-tabs .nav-link i {
        font-size: 16px;
        transition: transform 0.3s ease;
    }

    .nav-tabs .nav-link:hover {
        color: var(--primary);
        background: rgba(102, 126, 234, 0.08);
    }

    .nav-tabs .nav-link:hover i {
        transform: scale(1.1);
    }

    .nav-tabs .nav-link.active {
        color: white;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .nav-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-top: 8px solid #764ba2;
    }

    .tab-content {
        padding: 35px;
        background: white;
    }

    /* Config Section */
    .config-section {
        background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        padding: 28px;
        border-radius: 16px;
        margin-bottom: 28px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .config-section-title {
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

    .config-section-title i {
        color: var(--primary);
        font-size: 20px;
    }

    /* Form Elements */
    .form-group-modern {
        margin-bottom: 24px;
    }

    .form-label-modern {
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .form-label-modern .badge {
        font-size: 10px;
        padding: 4px 10px;
        border-radius: 20px;
    }

    .form-label-modern i {
        font-size: 16px;
    }

    .form-control, .form-select {
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        padding: 12px 16px;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .form-text {
        font-size: 13px;
        color: #6b7280;
        margin-top: 6px;
        display: block;
    }

    /* Buttons */
    .btn-save-config {
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

    .btn-save-config:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .btn-save-config:active {
        transform: translateY(0);
    }

    /* Feature Item */
    .feature-item {
        background: white;
        padding: 18px;
        border-radius: 12px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 2px solid #f3f4f6;
        transition: all 0.3s ease;
    }

    .feature-item:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        transform: translateX(4px);
    }

    .feature-info {
        display: flex;
        align-items: center;
        gap: 14px;
        flex: 1;
    }

    .feature-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    /* Switch Toggle */
    .switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .4s;
        border-radius: 28px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    input:checked + .slider {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    input:checked + .slider:before {
        transform: translateX(24px);
    }

    /* Table Styling */
    .table {
        margin-bottom: 0;
    }

    .table tr td {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
    }

    .table tr:last-child td {
        border-bottom: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .config-container {
            padding: 15px;
        }

        .nav-tabs {
            padding: 10px 15px 0 15px;
        }

        .nav-tabs .nav-link {
            padding: 10px 16px;
            font-size: 13px;
        }

        .tab-content {
            padding: 20px 15px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .page-title-modern {
            font-size: 24px;
        }

        .config-section {
            padding: 20px;
        }
    }
</style>

<div class="config-container">
    
    <!-- Header -->
    <div class="page-header-modern">
        <h2 class="page-title-modern">
            <i class="fas fa-cog"></i>
            <?php echo __('settings.title', [], 'Configuración del Sistema'); ?>
        </h2>
        <p class="page-subtitle-modern">
            <?php echo __('settings.subtitle', [], 'Administra todas las configuraciones de tu CRM inmobiliario'); ?>
        </p>
    </div>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card-config">
            <div class="stat-icon-config" style="background: #dbeafe; color: #1e40af;">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-number-config" style="color: #1e40af;">
                <?php echo count($propertyTypes); ?>
            </div>
            <div class="stat-label-config">
                <?php echo __('settings.property_types', [], 'Tipos de Propiedad'); ?>
            </div>
        </div>

        <div class="stat-card-config">
            <div class="stat-icon-config" style="background: #d1fae5; color: #065f46;">
                <i class="fas fa-check-square"></i>
            </div>
            <div class="stat-number-config" style="color: #065f46;">
                <?php echo count($features); ?>
            </div>
            <div class="stat-label-config">
                <?php echo __('settings.features', [], 'Características'); ?>
            </div>
        </div>

        <div class="stat-card-config">
            <div class="stat-icon-config" style="background: #fef3c7; color: #92400e;">
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="stat-number-config" style="color: #92400e;">
                <?php echo count($offices); ?>
            </div>
            <div class="stat-label-config">
                <?php echo __('offices', [], 'Office'); ?>
            </div>
        </div>

        <div class="stat-card-config">
            <div class="stat-icon-config" style="background: #fee2e2; color: #991b1b;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number-config" style="color: #991b1b;">
                <?php echo db()->count('users', 'status = ?', ['active']); ?>
            </div>
            <div class="stat-label-config">
                <?php echo __('active_users', [], 'Active Users'); ?>
            </div>
        </div>
    </div>

    <!-- Alert -->
    <div class="alert alert-warning alert-config mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div>
            <strong><?php echo __('important', [], 'Importante'); ?>:</strong> 
            <?php echo __('settings_warning', [], 'Configuration changes will affect the entire system. Make changes with caution.'); ?>.
        </div>
    </div>

    <!-- Tabs -->
    <div class="config-tabs">
        <ul class="nav nav-tabs" id="configTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                    <i class="fas fa-building"></i>
                    <?php echo __('settings.general', [], 'General'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button">
                    <i class="fas fa-envelope"></i>
                    <?php echo __('email', [], 'Email'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="propiedades-tab" data-bs-toggle="tab" data-bs-target="#propiedades" type="button">
                    <i class="fas fa-home"></i>
                    <?php echo __('properties', [], 'Propiedades'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="notificaciones-tab" data-bs-toggle="tab" data-bs-target="#notificaciones" type="button">
                    <i class="fas fa-bell"></i>
                    <?php echo __('settings.notifications', [], 'Notificaciones'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="integraciones-tab" data-bs-toggle="tab" data-bs-target="#integraciones" type="button">
                    <i class="fas fa-puzzle-piece"></i>
                    <?php echo __('settings.integrations', [], 'Integraciones'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="seguridad-tab" data-bs-toggle="tab" data-bs-target="#seguridad" type="button">
                    <i class="fas fa-shield-alt"></i>
                    <?php echo __('user.security', [], 'Seguridad'); ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="avanzado-tab" data-bs-toggle="tab" data-bs-target="#avanzado" type="button">
                    <i class="fas fa-cogs"></i>
                    <?php echo __('settings.advanced', [], 'Avanzado'); ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="configTabContent">
            
            <!-- ==================== TAB: GENERAL ==================== -->
            <div class="tab-pane fade show active" id="general">
                <form id="formGeneral">
                    
                    <!-- Información de la Empresa -->
                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-building"></i>
                            <?php echo __('settings.company_info', [], 'Información de la Empresa'); ?>
                        </h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('settings.company_name', [], 'Nombre de la Empresa'); ?>
                                    </label>
                                    <input type="text" class="form-control" name="company_name" 
                                           value="<?php echo $settings['company_name'] ?? 'Jaf Investments'; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('tagline', [], 'Eslogan/Tagline'); ?>
                                    </label>
                                    <input type="text" class="form-control" name="company_tagline" 
                                           value="<?php echo $settings['company_tagline'] ?? ''; ?>" 
                                           placeholder="<?php echo __('your_partner_real_estate', [], 'Tu socio en bienes raíces'); ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo __('settings.company_email', [], 'Email de Contacto'); ?>
                                    </label>
                                    <input type="email" class="form-control" name="company_email" 
                                           value="<?php echo $settings['company_email'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fas fa-phone"></i>
                                        <?php echo __('settings.company_phone', [], 'Teléfono Principal'); ?>
                                    </label>
                                    <input type="text" class="form-control" name="company_phone" 
                                           value="<?php echo $settings['company_phone'] ?? ''; ?>" 
                                           placeholder="+1 809 555 5555">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo __('settings.company_address', [], 'Dirección de la Empresa'); ?>
                                    </label>
                                    <textarea class="form-control" name="company_address" rows="2"><?php echo $settings['company_address'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Redes Sociales -->
                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-share-alt"></i>
                            <?php echo __('social_media', [], 'Redes Sociales'); ?>
                        </h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fab fa-facebook" style="color: #1877F2;"></i> Facebook
                                    </label>
                                    <input type="url" class="form-control" name="facebook_url" 
                                           value="<?php echo $settings['facebook_url'] ?? ''; ?>" 
                                           placeholder="https://facebook.com/tupagina">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fab fa-instagram" style="color: #E4405F;"></i> Instagram
                                    </label>
                                    <input type="url" class="form-control" name="instagram_url" 
                                           value="<?php echo $settings['instagram_url'] ?? ''; ?>" 
                                           placeholder="https://instagram.com/tupagina">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fab fa-twitter" style="color: #1DA1F2;"></i> Twitter / X
                                    </label>
                                    <input type="url" class="form-control" name="twitter_url" 
                                           value="<?php echo $settings['twitter_url'] ?? ''; ?>" 
                                           placeholder="https://twitter.com/tupagina">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fab fa-linkedin" style="color: #0A66C2;"></i> LinkedIn
                                    </label>
                                    <input type="url" class="form-control" name="linkedin_url" 
                                           value="<?php echo $settings['linkedin_url'] ?? ''; ?>" 
                                           placeholder="https://linkedin.com/company/tupagina">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fab fa-youtube" style="color: #FF0000;"></i> YouTube
                                    </label>
                                    <input type="url" class="form-control" name="youtube_url" 
                                           value="<?php echo $settings['youtube_url'] ?? ''; ?>" 
                                           placeholder="https://youtube.com/@tupagina">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fab fa-whatsapp" style="color: #25D366;"></i> WhatsApp Business
                                    </label>
                                    <input type="text" class="form-control" name="whatsapp_number" 
                                           value="<?php echo $settings['whatsapp_number'] ?? ''; ?>" 
                                           placeholder="+1 809 555 5555">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn-save-config">
                            <i class="fas fa-save"></i>
                            <?php echo __('settings.save_settings', [], 'Guardar Configuración General'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ==================== TAB: EMAIL ==================== -->
            <div class="tab-pane fade" id="email">
                <form id="formEmail">
                    
                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-server"></i>
                            <?php echo __('settings.email_settings', [], 'Configuración SMTP'); ?>
                        </h5>

                        <div class="alert alert-info alert-config mb-4" style="background: #dbeafe; border-left-color: #3b82f6;">
                            <i class="fas fa-info-circle"></i>
                            <div><?php echo __('smtp_info', [], 'Configura el servidor SMTP para enviar emails desde el sistema'); ?></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('smtp_host', [], 'Host SMTP'); ?>
                                    </label>
                                    <input type="text" class="form-control" name="smtp_host" 
                                           value="<?php echo $settings['smtp_host'] ?? ''; ?>" 
                                           placeholder="smtp.gmail.com">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('port', [], 'Puerto'); ?>
                                    </label>
                                    <input type="number" class="form-control" name="smtp_port" 
                                           value="<?php echo $settings['smtp_port'] ?? '587'; ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('encryption', [], 'Encriptación'); ?>
                                    </label>
                                    <select class="form-select" name="smtp_encryption">
                                        <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('username', [], 'Usuario SMTP'); ?>
                                    </label>
                                    <input type="text" class="form-control" name="smtp_username" 
                                           value="<?php echo $settings['smtp_username'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('password', [], 'Contraseña SMTP'); ?>
                                    </label>
                                    <input type="password" class="form-control" name="smtp_password" 
                                           value="<?php echo $settings['smtp_password'] ?? ''; ?>" 
                                           placeholder="••••••••">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('from_email', [], 'Email Remitente'); ?>
                                    </label>
                                    <input type="email" class="form-control" name="smtp_from_email" 
                                           value="<?php echo $settings['smtp_from_email'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('from_name', [], 'Nombre Remitente'); ?>
                                    </label>
                                    <input type="text" class="form-control" name="smtp_from_name" 
                                           value="<?php echo $settings['smtp_from_name'] ?? 'Jaf Investments'; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary" onclick="testEmailConfig()">
                                <i class="fas fa-paper-plane me-2"></i>
                                <?php echo __('send_test_email', [], 'Enviar Email de Prueba'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn-save-config">
                            <i class="fas fa-save"></i>
                            <?php echo __('save_email_config', [], 'Guardar Configuración de Email'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ==================== TAB: PROPIEDADES ==================== -->
            <div class="tab-pane fade" id="propiedades">
                
                <!-- Tipos de Propiedad -->
                <div class="config-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="config-section-title mb-0">
                            <i class="fas fa-building"></i>
                            <?php echo __('settings.property_types', [], 'Tipos de Propiedad'); ?>
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNewType">
                            <i class="fas fa-plus me-2"></i>
                            <?php echo __('new_type', [], 'Nuevo Tipo'); ?>
                        </button>
                    </div>

                    <div class="row">
                        <?php foreach ($propertyTypes as $type): ?>
                        <div class="col-md-6">
                            <div class="feature-item">
                                <div class="feature-info">
                                    <div class="feature-icon" style="background: #e0e7ff; color: #4338ca;">
                                        <i class="fas fa-home"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #1f2937;">
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #6b7280;">
                                            <?php echo __('order', [], 'Orden'); ?>: <?php echo $type['display_order']; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editPropertyType(<?php echo $type['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <label class="switch">
                                        <input type="checkbox" <?php echo $type['is_active'] ? 'checked' : ''; ?> 
                                               onchange="togglePropertyType(<?php echo $type['id']; ?>, this.checked)">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Características -->
                <div class="config-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="config-section-title mb-0">
                            <i class="fas fa-check-square"></i>
                            <?php echo __('settings.features', [], 'Características de Propiedades'); ?>
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNewFeature">
                            <i class="fas fa-plus me-2"></i>
                            <?php echo __('new_feature', [], 'Nueva Característica'); ?>
                        </button>
                    </div>

                    <div class="row">
                        <?php 
                        $categories = array_unique(array_column($features, 'category'));
                        foreach ($categories as $category): 
                            $categoryFeatures = array_filter($features, fn($f) => $f['category'] === $category);
                        ?>
                        <div class="col-md-12 mb-3">
                            <h6 style="color: #6b7280; font-size: 14px; font-weight: 700; text-transform: uppercase; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb;">
                                <i class="fas fa-folder-open me-2" style="color: var(--primary);"></i>
                                <?php echo htmlspecialchars($category ?? __('other', [], 'Otras')); ?>
                            </h6>

                            <div class="row">
                                <?php foreach ($categoryFeatures as $feature): ?>
                                <div class="col-md-6">
                                    <div class="feature-item">
                                        <div class="feature-info">
                                            <div class="feature-icon" style="background: #d1fae5; color: #065f46;">
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <div style="font-weight: 600; color: #1f2937;">
                                                <?php echo htmlspecialchars($feature['name']); ?>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editFeature(<?php echo $feature['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <label class="switch">
                                                <input type="checkbox" <?php echo $feature['is_active'] ? 'checked' : ''; ?> 
                                                       onchange="toggleFeature(<?php echo $feature['id']; ?>, this.checked)">
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Oficinas -->
                <div class="config-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="config-section-title mb-0">
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo __('offices', [], 'Oficinas'); ?>
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNewOffice">
                            <i class="fas fa-plus me-2"></i>
                            <?php echo __('new_office', [], 'Nueva Oficina'); ?>
                        </button>
                    </div>

                    <div class="row">
                        <?php foreach ($offices as $office): ?>
                        <div class="col-md-6">
                            <div class="feature-item">
                                <div class="feature-info">
                                    <div class="feature-icon" style="background: #fed7aa; color: #92400e;">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: #1f2937;">
                                            <?php echo htmlspecialchars($office['name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #6b7280;">
                                            <?php echo htmlspecialchars($office['address'] ?? 'Sin dirección'); ?>
                                        </div>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="editOffice(<?php echo $office['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- ==================== TAB: NOTIFICACIONES ==================== -->
            <div class="tab-pane fade" id="notificaciones">
                <form id="formNotifications">
                    
                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-bell"></i>
                            <?php echo __('settings.notifications', [], 'Configuración de Notificaciones'); ?>
                        </h5>

                        <div class="feature-item mb-3">
                            <div class="feature-info">
                                <div class="feature-icon" style="background: #dbeafe; color: #1e40af;">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">
                                        <?php echo __('email_notifications', [], 'Notificaciones por Email'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo __('send_important_emails', [], 'Enviar notificaciones importantes por email'); ?>
                                    </div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enable_email_notifications" 
                                       <?php echo ($settings['enable_email_notifications'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="feature-item mb-3">
                            <div class="feature-info">
                                <div class="feature-icon" style="background: #fed7aa; color: #92400e;">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">
                                        <?php echo __('task_notifications', [], 'Notificaciones de Tareas'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo __('notify_task_assignments', [], 'Notificar cuando se asignen tareas'); ?>
                                    </div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notify_task_assignments" 
                                       <?php echo ($settings['notify_task_assignments'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="feature-item mb-3">
                            <div class="feature-info">
                                <div class="feature-icon" style="background: #d1fae5; color: #065f46;">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">
                                        <?php echo __('new_client_notifications', [], 'Notificaciones de Nuevos Clientes'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo __('notify_new_clients', [], 'Notificar cuando se registre un nuevo cliente'); ?>
                                    </div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notify_new_clients" 
                                       <?php echo ($settings['notify_new_clients'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="feature-item">
                            <div class="feature-info">
                                <div class="feature-icon" style="background: #fce7f3; color: #9f1239;">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">
                                        <?php echo __('transaction_notifications', [], 'Notificaciones de Transacciones'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo __('notify_transactions', [], 'Notificar sobre nuevas transacciones y pagos'); ?>
                                    </div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notify_transactions" 
                                       <?php echo ($settings['notify_transactions'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn-save-config">
                            <i class="fas fa-save"></i>
                            <?php echo __('settings.save_settings', [], 'Guardar Configuración'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ==================== TAB: INTEGRACIONES ==================== -->
            <div class="tab-pane fade" id="integraciones">
                <form id="formIntegrations">
                    
                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-map-marked-alt"></i>
                            <?php echo __('google_maps_api', [], 'Google Maps API'); ?>
                        </h5>

                        <div class="form-group-modern">
                            <label class="form-label-modern">
                                <?php echo __('settings.api_keys', [], 'API Key de Google Maps'); ?>
                            </label>
                            <input type="text" class="form-control" name="google_maps_api_key" 
                                   value="<?php echo $settings['google_maps_api_key'] ?? ''; ?>" 
                                   placeholder="AIzaSyB...">
                            <div class="form-text">
                                <?php echo __('get_api_key', [], 'Obtén tu API key en'); ?>: 
                                <a href="https://console.cloud.google.com/google/maps-apis" target="_blank">Google Cloud Console</a>
                            </div>
                        </div>
                    </div>

                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-globe"></i>
                            <?php echo __('real_estate_portals', [], 'Portales Inmobiliarios'); ?>
                        </h5>

                        <div class="alert alert-info alert-config" style="background: #dbeafe; border-left-color: #3b82f6;">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <?php echo __('configure_portals_api', [], 'Configura las credenciales de API para publicar automáticamente en portales externos'); ?>
                            </div>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">
                                <span class="badge bg-secondary"><?php echo __('coming_soon', [], 'Próximamente'); ?></span>
                                Idealista API Key
                            </label>
                            <input type="text" class="form-control" disabled 
                                   placeholder="<?php echo __('integration_coming_soon', [], 'Integración próximamente'); ?>">
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn-save-config">
                            <i class="fas fa-save"></i>
                            <?php echo __('save_integrations', [], 'Guardar Integraciones'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ==================== TAB: SEGURIDAD ==================== -->
            <div class="tab-pane fade" id="seguridad">
                <form id="formSecurity">
                    
                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-lock"></i>
                            <?php echo __('user.security', [], 'Configuración de Seguridad'); ?>
                        </h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('session_timeout', [], 'Tiempo de Sesión (minutos)'); ?>
                                    </label>
                                    <input type="number" class="form-control" name="session_timeout" 
                                           value="<?php echo $settings['session_timeout'] ?? '30'; ?>">
                                    <div class="form-text">
                                        <?php echo __('session_timeout_info', [], 'Tiempo de inactividad antes de cerrar sesión automáticamente'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('password_min_length', [], 'Longitud Mínima de Contraseña'); ?>
                                    </label>
                                    <input type="number" class="form-control" name="password_min_length" 
                                           value="<?php echo $settings['password_min_length'] ?? '8'; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('max_login_attempts', [], 'Intentos Máximos de Login'); ?>
                                    </label>
                                    <input type="number" class="form-control" name="max_login_attempts" 
                                           value="<?php echo $settings['max_login_attempts'] ?? '5'; ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <?php echo __('lockout_duration', [], 'Duración de Bloqueo (minutos)'); ?>
                                    </label>
                                    <input type="number" class="form-control" name="lockout_duration" 
                                           value="<?php echo $settings['lockout_duration'] ?? '15'; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="config-section">
                        <h5 class="config-section-title">
                            <i class="fas fa-shield-alt"></i>
                            <?php echo __('security_options', [], 'Opciones de Seguridad'); ?>
                        </h5>

                        <div class="feature-item mb-3">
                            <div class="feature-info">
                                <div class="feature-icon" style="background: #d1fae5; color: #065f46;">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">
                                        <?php echo __('require_strong_password', [], 'Requerir Contraseñas Fuertes'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo __('password_requirements', [], 'Mayúsculas, minúsculas, números y símbolos'); ?>
                                    </div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="require_strong_password" 
                                       <?php echo ($settings['require_strong_password'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="feature-item mb-3">
                            <div class="feature-info">
                                <div class="feature-icon" style="background: #dbeafe; color: #1e40af;">
                                    <i class="fas fa-key"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">
                                        <?php echo __('two_factor_auth', [], 'Autenticación de Dos Factores'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo __('2fa_description', [], 'Seguridad adicional con código de verificación'); ?>
                                    </div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="enable_2fa" 
                                       <?php echo ($settings['enable_2fa'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="feature-item">
                            <div class="feature-info">
                                <div class="feature-icon" style="background: #fed7aa; color: #92400e;">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #1f2937;">
                                        <?php echo __('log_user_activity', [], 'Registrar Actividad de Usuarios'); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo __('track_actions', [], 'Rastrear todas las acciones de los usuarios'); ?>
                                    </div>
                                </div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="log_user_activity" 
                                       <?php echo ($settings['log_user_activity'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn-save-config">
                            <i class="fas fa-save"></i>
                            <?php echo __('settings.save_settings', [], 'Guardar Configuración'); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- ==================== TAB: AVANZADO ==================== -->
            <div class="tab-pane fade" id="avanzado">
                
                <div class="config-section">
                    <h5 class="config-section-title">
                        <i class="fas fa-database"></i>
                        <?php echo __('database_maintenance', [], 'Mantenimiento de Base de Datos'); ?>
                    </h5>

                    <div class="alert alert-warning alert-config">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong><?php echo __('caution', [], 'Precaución'); ?>:</strong> 
                            <?php echo __('maintenance_warning', [], 'Estas acciones pueden afectar el rendimiento del sistema'); ?>
                        </div>
                    </div>

                    <div class="d-flex gap-3 flex-wrap">
                        <button class="btn btn-outline-primary" onclick="optimizeDatabase()">
                            <i class="fas fa-tools me-2"></i>
                            <?php echo __('optimize_database', [], 'Optimizar Base de Datos'); ?>
                        </button>
                        <button class="btn btn-outline-success" onclick="backupDatabase()">
                            <i class="fas fa-download me-2"></i>
                            <?php echo __('create_backup', [], 'Crear Respaldo'); ?>
                        </button>
                        <button class="btn btn-outline-danger" onclick="clearCache()">
                            <i class="fas fa-trash me-2"></i>
                            <?php echo __('clear_cache', [], 'Limpiar Caché'); ?>
                        </button>
                    </div>
                </div>

                <div class="config-section">
                    <h5 class="config-section-title">
                        <i class="fas fa-info-circle"></i>
                        <?php echo __('system_info', [], 'Información del Sistema'); ?>
                    </h5>

                    <table class="table">
                        <tr>
                            <td style="font-weight: 600; width: 250px;">
                                <?php echo __('crm_version', [], 'Versión del CRM'); ?>:
                            </td>
                            <td>1.0.0</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">
                                <?php echo __('php_version', [], 'Versión de PHP'); ?>:
                            </td>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">
                                <?php echo __('web_server', [], 'Servidor Web'); ?>:
                            </td>
                            <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">
                                <?php echo __('database', [], 'Base de Datos'); ?>:
                            </td>
                            <td>
                                MySQL <?php echo db()->selectOne("SELECT VERSION() as version")['version'] ?? 'N/A'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">
                                <?php echo __('max_upload_size', [], 'Tamaño Máximo de Subida'); ?>:
                            </td>
                            <td><?php echo ini_get('upload_max_filesize'); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">
                                <?php echo __('memory_limit', [], 'Límite de Memoria'); ?>:
                            </td>
                            <td><?php echo ini_get('memory_limit'); ?></td>
                        </tr>
                    </table>
                </div>

            </div>

        </div>
    </div>

</div>

<script>
// ==================== GUARDAR CONFIGURACIÓN ====================
document.getElementById('formGeneral')?.addEventListener('submit', function(e) {
    e.preventDefault();
    saveConfiguration('general', new FormData(this));
});

document.getElementById('formEmail')?.addEventListener('submit', function(e) {
    e.preventDefault();
    saveConfiguration('email', new FormData(this));
});

document.getElementById('formNotifications')?.addEventListener('submit', function(e) {
    e.preventDefault();
    saveConfiguration('notifications', new FormData(this));
});

document.getElementById('formIntegrations')?.addEventListener('submit', function(e) {
    e.preventDefault();
    saveConfiguration('integrations', new FormData(this));
});

document.getElementById('formSecurity')?.addEventListener('submit', function(e) {
    e.preventDefault();
    saveConfiguration('security', new FormData(this));
});

// Función genérica para guardar configuración
function saveConfiguration(section, formData) {
    formData.append('section', section);
    
    fetch('ajax/save-configuration.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '<?php echo __('saved', [], '¡Guardado!'); ?>',
                text: '<?php echo __('config_saved', [], 'Configuración guardada exitosamente'); ?>',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '<?php echo __('error', [], 'Error'); ?>',
                text: data.message || '<?php echo __('could_not_save', [], 'No se pudo guardar la configuración'); ?>'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: '<?php echo __('error', [], 'Error'); ?>',
            text: '<?php echo __('error_occurred', [], 'Ocurrió un error al guardar'); ?>'
        });
    });
}

// Toggle tipo de propiedad
function togglePropertyType(id, isActive) {
    fetch('ajax/toggle-property-type.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&is_active=${isActive ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('<?php echo __('error_updating', [], 'Error al actualizar'); ?>');
        }
    });
}

// Toggle característica
function toggleFeature(id, isActive) {
    fetch('ajax/toggle-feature.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${id}&is_active=${isActive ? 1 : 0}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('<?php echo __('error_updating', [], 'Error al actualizar'); ?>');
        }
    });
}

// Test email configuration
function testEmailConfig() {
    Swal.fire({
        title: '<?php echo __('sending_test_email', [], 'Enviando email de prueba...'); ?>',
        text: '<?php echo __('please_wait', [], 'Por favor espera'); ?>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('ajax/test-email.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '<?php echo __('email_sent', [], '¡Email enviado!'); ?>',
                text: '<?php echo __('check_inbox', [], 'Revisa tu bandeja de entrada'); ?>'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: '<?php echo __('error', [], 'Error'); ?>',
                text: data.message || '<?php echo __('could_not_send', [], 'No se pudo enviar el email'); ?>'
            });
        }
    });
}

// Optimizar base de datos
function optimizeDatabase() {
    Swal.fire({
        title: '<?php echo __('optimize_db_confirm', [], '¿Optimizar base de datos?'); ?>',
        text: '<?php echo __('process_may_take_minutes', [], 'Este proceso puede tomar unos minutos'); ?>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<?php echo __('yes_optimize', [], 'Sí, optimizar'); ?>',
        cancelButtonText: '<?php echo __('cancel', [], 'Cancelar'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<?php echo __('optimizing', [], 'Optimizando...'); ?>',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax/optimize-database.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo __('optimization_complete', [], '¡Optimización completada!'); ?>',
                        text: data.message
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo __('error', [], 'Error'); ?>',
                        text: data.message
                    });
                }
            });
        }
    });
}

// Crear respaldo
function backupDatabase() {
    Swal.fire({
        title: '<?php echo __('create_backup_confirm', [], '¿Crear respaldo de la base de datos?'); ?>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: '<?php echo __('yes_create', [], 'Sí, crear'); ?>',
        cancelButtonText: '<?php echo __('cancel', [], 'Cancelar'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: '<?php echo __('creating_backup', [], 'Creando respaldo...'); ?>',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax/backup-database.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo __('backup_created', [], '¡Respaldo creado!'); ?>',
                        text: '<?php echo __('backup_downloaded', [], 'El respaldo ha sido descargado'); ?>'
                    });
                    
                    // Descargar archivo
                    if (data.file) {
                        window.location.href = data.file;
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo __('error', [], 'Error'); ?>',
                        text: data.message
                    });
                }
            });
        }
    });
}

// Limpiar caché
function clearCache() {
    Swal.fire({
        title: '<?php echo __('clear_cache_confirm', [], '¿Limpiar caché del sistema?'); ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<?php echo __('yes_clear', [], 'Sí, limpiar'); ?>',
        cancelButtonText: '<?php echo __('cancel', [], 'Cancelar'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/clear-cache.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo __('cache_cleared', [], '¡Caché limpiado!'); ?>',
                        text: '<?php echo __('cache_cleared_successfully', [], 'El caché ha sido limpiado exitosamente'); ?>'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo __('error', [], 'Error'); ?>',
                        text: data.message
                    });
                }
            });
        }
    });
}

// Editar tipo de propiedad
function editPropertyType(id) {
    Swal.fire({
        title: '<?php echo __('edit_property_type', [], 'Editar Tipo de Propiedad'); ?>',
        text: '<?php echo __('feature_coming_soon', [], 'Funcionalidad próximamente'); ?>',
        icon: 'info'
    });
}

// Editar característica
function editFeature(id) {
    Swal.fire({
        title: '<?php echo __('edit_feature', [], 'Editar Característica'); ?>',
        text: '<?php echo __('feature_coming_soon', [], 'Funcionalidad próximamente'); ?>',
        icon: 'info'
    });
}

// Editar oficina
function editOffice(id) {
    Swal.fire({
        title: '<?php echo __('edit_office', [], 'Editar Oficina'); ?>',
        text: '<?php echo __('feature_coming_soon', [], 'Funcionalidad próximamente'); ?>',
        icon: 'info'
    });
}
</script>

<?php include 'footer.php'; ?>
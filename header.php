<?php
if (!isset($pageTitle)) {
    $pageTitle = 'CRM Inmobiliario';
}
?>
<!DOCTYPE html>
<html lang="<?php echo currentLanguage() === 'es' ? 'es' : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    
<style>
    :root {
        --sidebar-width: 260px;
        --header-height: 70px;
        --primary: #667eea;
        --purple: #764ba2;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --success: #10b981;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #f8f9fa;
        margin: 0;
        padding: 0;
    }

    /* ========== HEADER MODERNO ========== */
    .header-modern {
        position: fixed;
        top: 0;
        left: var(--sidebar-width);
        right: 0;
        height: var(--header-height);
        background: white;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        z-index: 100;
        display: flex;
        align-items: center;
        padding: 0 30px;
        gap: 20px;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .header-brand {
        font-size: 20px;
        font-weight: 700;
        color: var(--primary);
    }

    /* ========== SEARCH BAR ========== */
    .header-search {
        flex: 1;
        max-width: 500px;
        position: relative;
    }

    .header-search i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 16px;
    }

    .header-search input {
        width: 100%;
        padding: 10px 15px 10px 45px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: #f9fafb;
    }

    .header-search input:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    /* ========== ACTIONS ========== */
    .header-actions {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-left: auto;
    }

    .notification-bell {
        position: relative;
    }

    .notification-bell button {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        background: white;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .notification-bell button:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: #f9fafb;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }

    .notification-badge.badge-warning {
        background: #f59e0b;
    }

    .notification-badge.badge-info {
        background: #3b82f6;
    }

    .notification-badge.badge-success {
        background: #10b981;
    }

    .notification-badge.pulse {
        animation: pulse 2s infinite;
    }

    /* ========== CUSTOM DROPDOWN (JavaScript Puro) ========== */
    .custom-dropdown {
        position: relative;
    }

    .custom-dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
    }

    .custom-dropdown-toggle:hover {
        border-color: var(--primary);
        background: #f9fafb;
        color: var(--primary);
    }

    .custom-dropdown-toggle i {
        font-size: 16px;
    }

    .custom-dropdown-menu {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: white;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        border-radius: 12px;
        min-width: 220px;
        padding: 8px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .custom-dropdown.active .custom-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .custom-dropdown-item {
        padding: 10px 15px;
        border-radius: 8px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: #374151;
        text-decoration: none;
        cursor: pointer;
    }

    .custom-dropdown-item:hover {
        background: #f3f4f6;
        color: var(--primary);
    }

    .custom-dropdown-item i {
        width: 20px;
        color: var(--primary);
        font-size: 16px;
    }

    .custom-dropdown-item.active-language {
        background: #ede9fe;
        color: var(--primary);
        font-weight: 600;
    }

    .custom-dropdown-divider {
        height: 1px;
        background: #e5e7eb;
        margin: 8px 0;
    }

    .custom-dropdown-item.text-danger {
        color: #ef4444 !important;
    }

    .custom-dropdown-item.text-danger i {
        color: #ef4444;
    }

    .custom-dropdown-item.text-danger:hover {
        background: #fee2e2;
        color: #dc2626 !important;
    }

    /* ========== USER DROPDOWN ========== */
    .user-dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 5px 10px 5px 5px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .user-dropdown-toggle:hover {
        border-color: var(--primary);
        background: #f9fafb;
    }

    .user-avatar-header {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        object-fit: cover;
    }

    .user-info-header {
        display: none;
        text-align: left;
    }

    @media (min-width: 992px) {
        .user-info-header {
            display: block;
        }
    }

    .user-info-name {
        font-weight: 600;
        color: #1f2937;
        font-size: 14px;
        line-height: 1.2;
    }

    .user-info-role {
        font-size: 12px;
        color: #6b7280;
    }

    /* ========== CONTENT WRAPPER ========== */
    .content-wrapper {
        margin-left: var(--sidebar-width);
        margin-top: var(--header-height);
        padding: 0;
        min-height: calc(100vh - var(--header-height));
        transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 768px) {
        :root {
            --sidebar-width: 0px;
        }
        
        .header-modern {
            left: 0;
            right: 0;
            padding: 0 15px;
            padding-left: 70px;
        }
        
        .content-wrapper {
            margin-left: 0;
        }
        
        .header-search {
            display: none;
        }
        
        .header-actions {
            gap: 10px;
        }
        
        .notification-bell button {
            width: 38px;
            height: 38px;
            font-size: 16px;
        }

        .custom-dropdown-toggle {
            padding: 8px 12px;
            font-size: 13px;
        }
    }

    @media (max-width: 480px) {
        .header-modern {
            padding: 0 10px;
            padding-left: 70px;
        }
        
        .header-actions {
            gap: 8px;
        }
        
        .user-dropdown-toggle {
            padding: 5px;
        }
        
        .notification-bell button {
            width: 36px;
            height: 36px;
        }

        .language-text {
            display: none;
        }
    }

    @media (min-width: 769px) and (max-width: 1024px) {
        :root {
            --sidebar-width: 240px;
        }
    }

    @media (min-width: 1440px) {
        :root {
            --sidebar-width: 280px;
        }
    }

    /* ========== ANIMACIONES ========== */
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
    }
</style>
    
</head>
<body>

<!-- Header Modern -->
<div class="header-modern">
    
    <!-- Search Bar -->
    <div class="header-search position-relative">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="<?php echo __('search_placeholder', [], 'Buscar propiedades, clientes, documentos...'); ?>" id="globalSearch">
    </div>

    <!-- Actions -->
    <div class="header-actions">
        
        <!-- Inquiries/Consultas -->
        <div class="notification-bell">
            <button onclick="window.location.href='mensajes.php'" title="<?php echo __('inquiries', [], 'Consultas'); ?>">
                <i class="fas fa-envelope"></i>
                <?php 
                $newInquiriesCount = (int)db()->selectValue(
                    "SELECT COUNT(*) FROM inquiries 
                     WHERE status = 'new'"
                );
                if($newInquiriesCount > 0): 
                ?>
                <span class="notification-badge badge-info"><?php echo $newInquiriesCount; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- New Clients Today -->
        <div class="notification-bell">
            <button onclick="window.location.href='clientes.php'" title="<?php echo __('new_clients_today', [], 'Nuevos Clientes Hoy'); ?>">
                <i class="fas fa-user-plus"></i>
                <?php 
                $newClientsToday = (int)db()->selectValue(
                    "SELECT COUNT(*) FROM clients 
                     WHERE DATE(created_at) = CURDATE()"
                );
                if($newClientsToday > 0): 
                ?>
                <span class="notification-badge badge-success"><?php echo $newClientsToday; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- New Properties -->
        <div class="notification-bell">
            <button onclick="window.location.href='propiedades.php'" title="<?php echo __('new_properties_week', [], 'Propiedades Nuevas (últimos 7 días)'); ?>">
                <i class="fas fa-home"></i>
                <?php 
                $newPropertiesWeek = (int)db()->selectValue(
                    "SELECT COUNT(*) FROM properties 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                );
                if($newPropertiesWeek > 0): 
                ?>
                <span class="notification-badge badge-success"><?php echo $newPropertiesWeek; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- Tasks -->
        <div class="notification-bell">
            <button onclick="window.location.href='tareas.php'" title="<?php echo __('pending_tasks', [], 'Tareas Pendientes'); ?>">
                <i class="fas fa-tasks"></i>
                <?php 
                $pendingTasksCount = (int)db()->selectValue(
                    "SELECT COUNT(*) FROM tasks 
                     WHERE assigned_to = ? AND status IN ('pending', 'in_progress')",
                    [$currentUser['id']]
                );
                if($pendingTasksCount > 0): 
                ?>
                <span class="notification-badge"><?php echo $pendingTasksCount; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- Calendar -->
        <div class="notification-bell">
            <button onclick="window.location.href='calendario.php'" title="<?php echo __('today_events', [], 'Eventos de Hoy'); ?>">
                <i class="fas fa-calendar-alt"></i>
                <?php 
                $todayEventsCount = (int)db()->selectValue(
                    "SELECT COUNT(*) FROM calendar_events 
                     WHERE DATE(start_datetime) = CURDATE() AND status = 'scheduled'"
                );
                if($todayEventsCount > 0): 
                ?>
                <span class="notification-badge badge-warning"><?php echo $todayEventsCount; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- Documents -->
        <div class="notification-bell">
            <button onclick="window.location.href='documentos.php'" title="<?php echo __('recent_documents', [], 'Documentos Recientes (últimos 3 días)'); ?>">
                <i class="fas fa-file-alt"></i>
                <?php 
                $recentDocuments = (int)db()->selectValue(
                    "SELECT COUNT(*) FROM documents 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) 
                     AND status = 'active'"
                );
                if($recentDocuments > 0): 
                ?>
                <span class="notification-badge badge-info"><?php echo $recentDocuments; ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- Notifications -->
        <div class="notification-bell">
            <button onclick="toggleNotifications()" title="<?php echo __('notifications.notifications', [], 'Notificaciones'); ?>">
                <i class="fas fa-bell"></i>
                <?php 
                $unreadCount = (int)db()->selectValue(
                    "SELECT COUNT(*) FROM notifications 
                     WHERE user_id = ? AND is_read = 0",
                    [$currentUser['id']]
                );
                if($unreadCount > 0): 
                ?>
                <span class="notification-badge pulse"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Language Selector (Custom Dropdown) -->
        <div class="custom-dropdown" id="languageDropdown">
            <div class="custom-dropdown-toggle">
                <i class="fas fa-globe"></i>
                <span class="language-text"><?php echo currentLanguage() === 'es' ? 'ES' : 'EN'; ?></span>
                <i class="fas fa-chevron-down" style="font-size: 10px; color: #9ca3af;"></i>
            </div>
            
            <div class="custom-dropdown-menu">
                <a class="custom-dropdown-item <?php echo currentLanguage() === 'es' ? 'active-language' : ''; ?>" 
                   href="ajax/change-language.php?lang=es">
                    <i class="fas fa-flag"></i>
                    <span>Español</span>
                    <?php if(currentLanguage() === 'es'): ?>
                    <i class="fas fa-check ms-auto" style="color: var(--success);"></i>
                    <?php endif; ?>
                </a>
                <a class="custom-dropdown-item <?php echo currentLanguage() === 'en' ? 'active-language' : ''; ?>" 
                   href="ajax/change-language.php?lang=en">
                    <i class="fas fa-flag"></i>
                    <span>English</span>
                    <?php if(currentLanguage() === 'en'): ?>
                    <i class="fas fa-check ms-auto" style="color: var(--success);"></i>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- User Dropdown (Custom Dropdown) -->
        <div class="custom-dropdown" id="userDropdown">
            <div class="custom-dropdown-toggle user-dropdown-toggle">
                <?php if($currentUser['profile_picture']): ?>
                <img src="<?php echo htmlspecialchars($currentUser['profile_picture']); ?>" 
                     alt="<?php echo htmlspecialchars($currentUser['first_name']); ?>"
                     class="user-avatar-header">
                <?php else: ?>
                <div class="user-avatar-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px;">
                    <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
                </div>
                <?php endif; ?>
                <div class="user-info-header">
                    <div class="user-info-name"><?php echo htmlspecialchars($currentUser['first_name']); ?></div>
                    <div class="user-info-role"><?php echo htmlspecialchars($currentUser['role']['display_name'] ?? __('user', [], 'Usuario')); ?></div>
                </div>
                <i class="fas fa-chevron-down" style="color: #9ca3af; font-size: 12px;"></i>
            </div>
            
            <div class="custom-dropdown-menu">
                <a class="custom-dropdown-item" href="perfil.php">
                    <i class="fas fa-user"></i>
                    <span><?php echo __('user.my_profile', [], 'Mi Perfil'); ?></span>
                </a>
                <a class="custom-dropdown-item" href="configuracion.php">
                    <i class="fas fa-cog"></i>
                    <span><?php echo __('menu.settings', [], 'Configuración'); ?></span>
                </a>
                <div class="custom-dropdown-divider"></div>
                <a class="custom-dropdown-item text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span><?php echo __('logout', [], 'Cerrar Sesión'); ?></span>
                </a>
            </div>
        </div>

    </div>
</div>

<!-- Content Wrapper -->
<div class="content-wrapper">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ==================== CUSTOM DROPDOWN IMPLEMENTATION ====================
// Esta implementación NO depende de Bootstrap

document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando custom dropdowns...');
    
    // Obtener todos los dropdowns
    const dropdowns = document.querySelectorAll('.custom-dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.custom-dropdown-toggle');
        const menu = dropdown.querySelector('.custom-dropdown-menu');
        
        // Click en el toggle para abrir/cerrar
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Cerrar otros dropdowns
            dropdowns.forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('active');
                }
            });
            
            // Toggle este dropdown
            dropdown.classList.toggle('active');
            
            console.log('Dropdown toggled:', dropdown.id, 'Active:', dropdown.classList.contains('active'));
        });
    });
    
    // Cerrar dropdowns al hacer click fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.custom-dropdown')) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
    
    // Cerrar dropdown al hacer click en un item (excepto si tiene submenú)
    document.querySelectorAll('.custom-dropdown-item').forEach(item => {
        item.addEventListener('click', function() {
            const dropdown = this.closest('.custom-dropdown');
            if (dropdown) {
                dropdown.classList.remove('active');
            }
        });
    });
    
    // Cerrar con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
    
    console.log('Custom dropdowns inicializados:', dropdowns.length);
});

// Función para notificaciones
function toggleNotifications() {
    window.location.href = 'notificaciones.php';
}

// BÃºsqueda global
document.getElementById('globalSearch')?.addEventListener('input', function(e) {
    const query = e.target.value;
    if(query.length >= 3) {
        console.log('Buscando:', query);
    }
});

// Flash messages (CORREGIDO) ✅
<?php if(isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])): ?>
    Swal.fire({
        icon: '<?php echo htmlspecialchars($_SESSION['flash_message']['type'] ?? 'info'); ?>',
        title: '<?php 
            if ($_SESSION['flash_message']['type'] === 'error') {
                echo 'Error';
            } elseif ($_SESSION['flash_message']['type'] === 'success') {
                echo '¡Éxito!';
            } elseif ($_SESSION['flash_message']['type'] === 'warning') {
                echo 'Advertencia';
            } else {
                echo 'Información';
            }
        ?>',
        text: '<?php echo htmlspecialchars($_SESSION['flash_message']['message']); ?>',
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    <?php 
    unset($_SESSION['flash_message']);
    ?>
<?php endif; ?>
</script>
</body>
</html>
</script>
</body>
</html>
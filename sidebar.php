<?php
// Obtener la página actual
$currentPage = basename($_SERVER['PHP_SELF']);

// ========== CONTADORES DINÁMICOS ==========
try {
    // Consultas/Leads pendientes
    $pendingInquiries = (int)db()->selectValue(
        "SELECT COUNT(*) FROM inquiries WHERE status IN ('new', 'read')"
    );
    
    // Tareas pendientes del usuario actual
    $pendingTasks = (int)db()->selectValue(
        "SELECT COUNT(*) FROM tasks 
         WHERE assigned_to = ? AND status IN ('pending', 'in_progress')",
        [$currentUser['id']]
    );
    
    // Visitas de hoy
    $todayVisits = (int)db()->selectValue(
        "SELECT COUNT(*) FROM calendar_events 
         WHERE DATE(start_datetime) = CURDATE() 
         AND event_type = 'visit' 
         AND status = 'scheduled'"
    );
    
    // Notificaciones no leídas
    $unreadNotifications = (int)db()->selectValue(
        "SELECT COUNT(*) FROM notifications 
         WHERE user_id = ? AND is_read = 0",
        [$currentUser['id']]
    );
    
    // Documentos pendientes de revisión
    $pendingDocuments = (int)db()->selectValue(
        "SELECT COUNT(*) FROM documents 
         WHERE status = 'pending_review'"
    );
    
    // Proyectos activos con alertas
    $activeProjectsAlerts = (int)db()->selectValue(
        "SELECT COUNT(*) FROM restoration_projects 
         WHERE project_status IN ('Planificación', 'En Progreso')"
    );
    
    // Facturas pendientes de pago
    $pendingInvoices = (int)db()->selectValue(
        "SELECT COUNT(*) FROM invoices 
         WHERE status IN ('pending', 'partial', 'overdue')"
    );
    
    // Pagos realizados hoy
    $todayPayments = (int)db()->selectValue(
        "SELECT COUNT(*) FROM invoice_payments 
         WHERE DATE(payment_date) = CURDATE()"
    );
    
} catch (Exception $e) {
    error_log("Error cargando contadores sidebar: " . $e->getMessage());
    $pendingInquiries = 0;
    $pendingTasks = 0;
    $todayVisits = 0;
    $unreadNotifications = 0;
    $pendingDocuments = 0;
    $activeProjectsAlerts = 0;
}
?>

<!-- Overlay para móviles -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Mobile Toggle Button - NUEVA POSICIÓN -->
<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    
    <!-- Logo Section -->
    <div class="sidebar-logo">
        <div class="text-center">
            <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>" class="logo-img">
        </div>
    </div>
    
    <!-- User Profile Section -->
    <div class="user-profile-section">
        <div class="d-flex align-items-center gap-3">
            <?php if($currentUser['profile_picture']): ?>
            <img src="<?php echo htmlspecialchars($currentUser['profile_picture']); ?>" 
                 alt="<?php echo htmlspecialchars($currentUser['first_name']); ?>"
                 class="user-avatar">
            <?php else: ?>
            <div class="user-avatar-placeholder">
                <?php echo strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)); ?>
            </div>
            <?php endif; ?>
            <div class="flex-grow-1 user-info">
                <h6 class="user-name">
                    <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                </h6>
                <small class="user-role">
                    <?php echo htmlspecialchars($currentUser['role']['display_name'] ?? __('user', [], 'Usuario')); ?>
                </small>
            </div>
        </div>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.dashboard', [], 'Dashboard'); ?></span>
                </a>
            </li>
            
            <!-- Propiedades -->
            <li class="nav-item">
                <a href="propiedades.php" class="nav-link <?php echo in_array($currentPage, ['propiedades.php', 'crear-propiedad.php', 'editar-propiedad.php', 'ver-propiedad.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-home nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.properties', [], 'Propiedades'); ?></span>
                </a>
            </li>
            
            <!-- Clientes -->
            <li class="nav-item">
                <a href="clientes.php" class="nav-link <?php echo in_array($currentPage, ['clientes.php', 'crear-cliente.php', 'editar-cliente.php', 'ver-cliente.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-users nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.clients', [], 'Clientes'); ?></span>
                </a>
            </li>
            
            <!-- Calendario -->
            <li class="nav-item">
                <a href="calendario.php" class="nav-link <?php echo in_array($currentPage, ['calendario.php', 'nuevo-evento.php', 'editar-evento.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.calendar', [], 'Calendario'); ?></span>
                    <?php if($todayVisits > 0): ?>
                    <span class="nav-badge badge-warning"><?php echo $todayVisits; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Tareas -->
            <li class="nav-item">
                <a href="tareas.php" class="nav-link <?php echo in_array($currentPage, ['tareas.php', 'nueva-tarea.php', 'editar-tarea.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-tasks nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.tasks', [], 'Tareas'); ?></span>
                    <?php if($pendingTasks > 0): ?>
                    <span class="nav-badge badge-purple"><?php echo $pendingTasks; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Documentos -->
            <li class="nav-item">
                <a href="documentos.php" class="nav-link <?php echo in_array($currentPage, ['documentos.php', 'subir-documento.php', 'ver-documento.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.documents', [], 'Documentos'); ?></span>
                    <?php if($pendingDocuments > 0): ?>
                    <span class="nav-badge badge-info"><?php echo $pendingDocuments; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-divider">
                <span><?php echo __('menu.system', [], 'Gestión'); ?></span>
            </li>
            
            <!-- Usuarios (Solo Admin) -->
            <?php if($currentUser['role']['name'] === 'administrador'): ?>
            <li class="nav-item">
                <a href="usuarios.php" class="nav-link <?php echo in_array($currentPage, ['usuarios.php', 'crear-usuario.php', 'editar-usuario.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.users', [], 'Usuarios'); ?></span>
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Agentes -->
            <li class="nav-item">
                <a href="agentes.php" class="nav-link <?php echo in_array($currentPage, ['agentes.php', 'ver-agente.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.agents', [], 'Agentes'); ?></span>
                </a>
            </li>
            
            <!-- Consultas -->
            <li class="nav-item">
                <a href="consultas.php" class="nav-link <?php echo $currentPage === 'consultas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.inquiries', [], 'Consultas'); ?></span>
                    <?php if($pendingInquiries > 0): ?>
                    <span class="nav-badge badge-danger pulse"><?php echo $pendingInquiries; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Obras y Restauración -->
            <li class="nav-item">
                <a href="obras.php" class="nav-link <?php echo in_array($currentPage, ['obras.php', 'crear-obra.php', 'editar-obra.php', 'ver-obra.php', 'registrar-gasto.php', 'contratistas.php', 'materiales.php', 'fases.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-hard-hat nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.projects', [], 'Obras y Restauración'); ?></span>
                    <?php if($activeProjectsAlerts > 0): ?>
                    <span class="nav-badge badge-warning pulse"><?php echo $activeProjectsAlerts; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Transacciones -->
            <li class="nav-item">
                <a href="transacciones.php" class="nav-link <?php echo $currentPage === 'transacciones.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice-dollar nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.transactions', [], 'Transacciones'); ?></span>
                </a>
            </li>
            
            
            
            <!-- Ventas y Alquileres -->
            <li class="nav-item">
                <a href="ventas.php" class="nav-link <?php echo in_array($currentPage, ['ventas.php', 'nueva-venta.php', 'editar-venta.php', 'ver-venta.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-usd nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.sales_rentals', [], 'Sales and Rentals'); ?></span>
                    <?php
                    // Opcional: Mostrar contador de transacciones pendientes
                    $pendingTransactions = (int)db()->selectValue("SELECT COUNT(*) FROM sales_transactions WHERE status = 'pending'");
                    if ($pendingTransactions > 0):
                    ?>
                    <span class="nav-badge badge-warning"><?php echo $pendingTransactions; ?></span>
                    <?php endif; ?>
                </a>
            </li> 
            
            <!-- Facturación -->
            <li class="nav-item">
                <a href="facturacion.php" class="nav-link <?php echo in_array($currentPage, ['facturacion.php', 'ver-factura.php', 'crear-factura.php', 'editar-factura.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-file-invoice nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.invoicing', [], 'Facturación'); ?></span>
                    <?php if($pendingInvoices > 0): ?>
                    <span class="nav-badge badge-danger pulse"><?php echo $pendingInvoices; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Pagos -->
            <li class="nav-item">
                <a href="pagos.php" class="nav-link <?php echo in_array($currentPage, ['pagos.php', 'ver-pago.php', 'procesar-pago-factura.php']) ? 'active' : ''; ?>">
                    <i class="fas fa-money-check-alt nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.payments', [], 'Payments'); ?></span>
                    <?php if($todayPayments > 0): ?>
                    <span class="nav-badge badge-success"><?php echo $todayPayments; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-divider">
                <span><?php echo __('menu.reports', [], 'Análisis'); ?></span>
            </li>
            
            <!-- Reportes -->
            <li class="nav-item">
                <a href="reportes.php" class="nav-link <?php echo $currentPage === 'reportes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.reports', [], 'Reportes'); ?></span>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-divider">
                <span><?php echo __('menu.system', [], 'Sistema'); ?></span>
            </li>
            
            <!-- Configuración -->
            <li class="nav-item">
                <a href="configuracion.php" class="nav-link <?php echo $currentPage === 'configuracion.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog nav-icon"></i>
                    <span class="nav-text"><?php echo __('menu.settings', [], 'Configuración'); ?></span>
                </a>
            </li>
            
        </ul>
        
        <!-- Logout Button -->
        <div class="sidebar-footer">
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span><?php echo __('logout', [], 'Cerrar Sesión'); ?></span>
            </a>
        </div>
    </nav>
</aside>

<style>
:root {
    --sidebar-width: 260px;
    --sidebar-bg: #ffffff;
    --sidebar-text: #4a5568;
    --sidebar-hover: #f3f4f6;
    --sidebar-active: #3b82f6;
    --sidebar-active-bg: #eff6ff;
    --primary: #667eea;
    --purple: #764ba2;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --success: #10b981;
}

/* ========== SIDEBAR PRINCIPAL ========== */
.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: var(--sidebar-bg);
    border-right: 1px solid #e5e7eb;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1000;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: #f1f3f5;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* ========== LOGO ========== */
.sidebar-logo {
    padding: 25px 20px;
    border-bottom: 1px solid #f1f3f5;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    flex-shrink: 0;
}

.logo-img {
    max-width: 180px;
    height: auto;
    display: block;
    margin: 0 auto;
}

/* ========== USER PROFILE ========== */
.user-profile-section {
    padding: 20px;
    border-bottom: 1px solid #f1f3f5;
    background: #f8f9fa;
    flex-shrink: 0;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.user-avatar-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 18px;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.user-name {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
    line-height: 1.3;
}

.user-role {
    color: #718096;
    font-size: 12px;
    display: block;
    margin-top: 2px;
}

/* ========== NAVIGATION ========== */
.sidebar-nav {
    flex: 1;
    padding: 20px 15px;
    padding-bottom: 80px;
}

.nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 4px;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: 10px;
    color: var(--sidebar-text);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    font-weight: 500;
    font-size: 14px;
}

.nav-link:hover {
    background: var(--sidebar-hover);
    color: var(--primary);
    transform: translateX(2px);
}

.nav-link.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white !important;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.nav-link.active .nav-icon {
    color: white !important;
}

.nav-icon {
    width: 20px;
    font-size: 16px;
    margin-right: 12px;
    flex-shrink: 0;
}

.nav-text {
    flex: 1;
}

/* ========== BADGES ========== */
.nav-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
    margin-left: auto;
}

.badge-danger {
    background: #ef4444;
    color: white;
}

.badge-warning {
    background: #f59e0b;
    color: white;
}

.badge-info {
    background: #3b82f6;
    color: white;
}

.badge-purple {
    background: #8b5cf6;
    color: white;
}

.badge-success {
    background: #10b981;
    color: white;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}

/* ========== DIVIDERS ========== */
.nav-divider {
    padding: 15px 16px 8px 16px;
    margin-top: 10px;
}

.nav-divider span {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #9ca3af;
}

/* ========== FOOTER ========== */
.sidebar-footer {
    padding: 15px;
    border-top: 1px solid #f1f3f5;
    background: white;
}

.btn-logout {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 12px;
    background: white;
    border: 2px solid #fee2e2;
    color: #ef4444;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
}

.btn-logout:hover {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* ========== MOBILE TOGGLE BUTTON - NUEVA POSICIÓN ========== */
.mobile-menu-toggle {
    display: none;
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 1100;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 10px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.mobile-menu-toggle:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
}

.mobile-menu-toggle:active {
    transform: scale(0.95);
}

/* Cuando el sidebar está abierto, mover el botón a la derecha */
.sidebar.show ~ .mobile-menu-toggle {
    left: calc(280px + 10px);
}

.hamburger-line {
    width: 24px;
    height: 3px;
    background: white;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.mobile-menu-toggle.active .hamburger-line:nth-child(1) {
    transform: rotate(45deg) translate(7px, 7px);
}

.mobile-menu-toggle.active .hamburger-line:nth-child(2) {
    opacity: 0;
}

.mobile-menu-toggle.active .hamburger-line:nth-child(3) {
    transform: rotate(-45deg) translate(7px, -7px);
}

/* ========== OVERLAY ========== */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .mobile-menu-toggle {
        display: flex;
    }
    
    .sidebar.show {
        transform: translateX(0);
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
    }
    
    .sidebar-overlay.show {
        display: block;
        opacity: 1;
    }
    
    .sidebar {
        width: 280px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: 85%;
        max-width: 300px;
    }
    
    .logo-img {
        max-width: 150px;
    }
    
    .user-profile-section {
        padding: 15px;
    }
    
    .user-avatar,
    .user-avatar-placeholder {
        width: 45px;
        height: 45px;
        font-size: 16px;
    }
    
    .user-name {
        font-size: 13px;
    }
    
    .user-role {
        font-size: 11px;
    }
    
    .sidebar-nav {
        padding: 15px 12px;
    }
    
    .nav-link {
        padding: 10px 14px;
        font-size: 13px;
    }
    
    .nav-icon {
        font-size: 15px;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    :root {
        --sidebar-width: 240px;
    }
    
    .sidebar {
        width: var(--sidebar-width);
    }
    
    .logo-img {
        max-width: 150px;
    }
}

@media (min-width: 1440px) {
    :root {
        --sidebar-width: 280px;
    }
    
    .sidebar {
        width: var(--sidebar-width);
    }
    
    .logo-img {
        max-width: 200px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            this.classList.toggle('active');
            
            if (sidebar.classList.contains('show')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            if (mobileToggle) {
                mobileToggle.classList.remove('active');
            }
            document.body.style.overflow = '';
        });
    }
    
    if (window.innerWidth <= 768) {
        const navLinks = sidebar.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                if (mobileToggle) {
                    mobileToggle.classList.remove('active');
                }
                document.body.style.overflow = '';
            });
        });
    }
    
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                if (mobileToggle) {
                    mobileToggle.classList.remove('active');
                }
                document.body.style.overflow = '';
            }
        }, 250);
    });
    
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>
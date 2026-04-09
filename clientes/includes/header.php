<?php
if (!isset($_SESSION['client_id'])) {
    header('Location: ' . url('login-clientes.php'));
    exit;
}

$currentClient = getCurrentClient();
if (!$currentClient) {
    session_destroy();
    header('Location: ' . url('login-clientes.php'));
    exit;
}

// Obtener notificaciones no leídas
$unreadMessages = db()->selectOne("
    SELECT COUNT(*) as count 
    FROM client_property_comments 
    WHERE client_id = ? 
    AND sender_type = 'admin' 
    AND is_read = 0
", [$currentClient['id']])['count'] ?? 0;

$pendingInvoices = db()->selectOne("
    SELECT COUNT(*) as count 
    FROM invoices 
    WHERE client_id = ? 
    AND status IN ('pending', 'overdue')
", [$currentClient['id']])['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Portal de Clientes'; ?> - JAF Investments</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            position: relative;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white !important;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .property-card {
            position: relative;
            overflow: hidden;
        }
        
        .property-card img {
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .property-card:hover img {
            transform: scale(1.1);
        }
        
        .chat-container {
            height: 500px;
            overflow-y: auto;
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
        }
        
        .message {
            margin-bottom: 1rem;
            display: flex;
        }
        
        .message.client {
            justify-content: flex-end;
        }
        
        .message.admin {
            justify-content: flex-start;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            position: relative;
        }
        
        .message.client .message-bubble {
            background: var(--primary-color);
            color: white;
        }
        
        .message.admin .message-bubble {
            background: white;
            color: #1e293b;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo url('clientes/dashboard.php'); ?>">
                <i class="fas fa-home me-2"></i>JAF Investments
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                           href="<?php echo url('clientes/dashboard.php'); ?>">
                            <i class="fas fa-th-large me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'propiedades.php' ? 'active' : ''; ?>" 
                           href="<?php echo url('clientes/propiedades.php'); ?>">
                            <i class="fas fa-building me-1"></i> Mis Propiedades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'facturas.php' ? 'active' : ''; ?>" 
                           href="<?php echo url('clientes/facturas.php'); ?>">
                            <i class="fas fa-file-invoice-dollar me-1"></i> Facturas
                            <?php if ($pendingInvoices > 0): ?>
                                <span class="notification-badge"><?php echo $pendingInvoices; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'documentos.php' ? 'active' : ''; ?>" 
                           href="<?php echo url('clientes/documentos.php'); ?>">
                            <i class="fas fa-folder-open me-1"></i> Documentos
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i> 
                            <?php echo htmlspecialchars($currentClient['first_name']); ?>
                            <?php if ($unreadMessages > 0): ?>
                                <span class="notification-badge"><?php echo $unreadMessages; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo url('clientes/perfil.php'); ?>">
                                    <i class="fas fa-user me-2"></i> Mi Perfil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo url('logout-clientes.php'); ?>">
                                    <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesion
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
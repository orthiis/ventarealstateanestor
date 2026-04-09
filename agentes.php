<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('menu.agents', [], 'Agentes Inmobiliarios');
$currentUser = getCurrentUser();

// Obtener agentes
$agents = db()->select(
    "SELECT u.*, r.display_name as role_name, o.name as office_name,
     (SELECT COUNT(*) FROM properties WHERE agent_id = u.id AND status != 'deleted') as properties_count,
     (SELECT COUNT(*) FROM clients WHERE agent_id = u.id) as clients_count,
     (SELECT COUNT(*) FROM sales_transactions WHERE agent_id = u.id AND status = 'completed') as sales_count
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN offices o ON u.office_id = o.id
     WHERE u.role_id IN (2, 3)
     ORDER BY u.created_at DESC"
);

// Estadísticas generales
$stats = [
    'total' => count($agents),
    'active' => count(array_filter($agents, fn($a) => $a['status'] === 'active')),
    'properties' => array_sum(array_column($agents, 'properties_count')),
    'sales' => array_sum(array_column($agents, 'sales_count'))
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
    --info: #3b82f6;
    --purple: #8b5cf6;
}

.agents-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* Header */
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

/* Stats */
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

.stat-card-modern.purple { border-left: 4px solid var(--purple); }
.stat-card-modern.green { border-left: 4px solid var(--success); }
.stat-card-modern.blue { border-left: 4px solid var(--info); }
.stat-card-modern.orange { border-left: 4px solid var(--warning); }

.stat-value-modern {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
    line-height: 1;
    margin-bottom: 8px;
}

.stat-label-modern {
    font-size: 13px;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon-modern {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    opacity: 0.8;
}

.stat-card-modern.purple .stat-icon-modern { background: #f3e8ff; color: var(--purple); }
.stat-card-modern.green .stat-icon-modern { background: #d1fae5; color: var(--success); }
.stat-card-modern.blue .stat-icon-modern { background: #dbeafe; color: var(--info); }
.stat-card-modern.orange .stat-icon-modern { background: #fed7aa; color: var(--warning); }

/* Agents Grid */
.agents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 25px;
}

.agent-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
}

.agent-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.15);
}

.agent-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 30px 20px 80px;
    text-align: center;
    position: relative;
}

.agent-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 5px solid white;
    object-fit: cover;
    position: absolute;
    bottom: -60px;
    left: 50%;
    transform: translateX(-50%);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    background: white;
}

.status-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.inactive {
    background: #fee2e2;
    color: #991b1b;
}

.agent-body {
    padding: 70px 20px 20px;
    text-align: center;
}

.agent-name {
    font-size: 20px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.agent-role {
    color: #718096;
    font-size: 14px;
    margin-bottom: 15px;
}

.agent-contact {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}

.contact-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #4b5563;
    text-decoration: none;
    transition: all 0.3s;
}

.contact-item:hover {
    color: var(--primary);
}

.contact-item i {
    width: 20px;
    text-align: center;
    color: var(--primary);
}

.agent-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    padding: 20px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
    display: block;
}

.stat-text {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.agent-actions {
    padding: 15px 20px;
    display: flex;
    gap: 10px;
}

.btn-modern {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.empty-state i {
    font-size: 64px;
    color: #e5e7eb;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #6b7280;
    font-size: 18px;
    margin-bottom: 10px;
}

.empty-state p {
    color: #9ca3af;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .agents-container {
        padding: 15px;
    }
    
    .page-header-modern {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .stats-grid-modern {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .agents-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="agents-container">
    
    <!-- Header -->
    <div class="page-header-modern">
        <div>
            <h2 class="page-title-modern">
                <i class="fas fa-user-tie" style="color: var(--purple);"></i>
                <?php echo __('menu.agents', [], 'Agentes Inmobiliarios'); ?>
            </h2>
            <p class="page-subtitle-modern">
                <?php echo __('agents.subtitle', [], 'Gestión del equipo de agentes'); ?>
            </p>
        </div>
        <a href="usuarios.php?action=new&type=agent" class="btn-modern btn-primary-modern">
            <i class="fas fa-plus"></i>
            <?php echo __('agents.add_agent', [], 'Add Agent'); ?>
        </a>
    </div>

    <!-- Stats -->
    <div class="stats-grid-modern">
        <div class="stat-card-modern purple">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label-modern"><?php echo __('total', [], 'Total'); ?> <?php echo __('menu.agents', [], 'Agentes'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-users"></i>
            </div>
        </div>
        
        <div class="stat-card-modern green">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-label-modern"><?php echo __('active', [], 'Activos'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-user-check"></i>
            </div>
        </div>
        
        <div class="stat-card-modern blue">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['properties']); ?></div>
                <div class="stat-label-modern"><?php echo __('properties.title', [], 'Propiedades'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-home"></i>
            </div>
        </div>
        
        <div class="stat-card-modern orange">
            <div>
                <div class="stat-value-modern"><?php echo number_format($stats['sales']); ?></div>
                <div class="stat-label-modern"><?php echo __('sales', [], 'Ventas'); ?></div>
            </div>
            <div class="stat-icon-modern">
                <i class="fas fa-handshake"></i>
            </div>
        </div>
    </div>

    <!-- Agents Grid -->
    <?php if(empty($agents)): ?>
    <div class="empty-state">
        <i class="fas fa-user-tie"></i>
        <h3><?php echo __('agents.no_agents', [], 'No hay agentes registrados'); ?></h3>
        <p><?php echo __('agents.add_first', [], 'Añade tu primer agente para comenzar'); ?></p>
        <a href="usuarios.php?action=new&type=agent" class="btn-modern btn-primary-modern">
            <i class="fas fa-plus"></i>
            <?php echo __('agents.add_agent', [], 'Añadir Agente'); ?>
        </a>
    </div>
    <?php else: ?>
    <div class="agents-grid">
        <?php foreach ($agents as $agent): ?>
        <div class="agent-card">
            <div class="agent-header">
                <span class="status-badge <?php echo $agent['status'] === 'active' ? 'active' : 'inactive'; ?>">
                    <?php echo $agent['status'] === 'active' ? __('active', [], 'Activo') : __('inactive', [], 'Inactivo'); ?>
                </span>
                <img src="<?php echo $agent['profile_picture'] ?? 'assets/images/default-avatar.png'; ?>" 
                     alt="<?php echo htmlspecialchars($agent['first_name']); ?>" 
                     class="agent-avatar">
            </div>

            <div class="agent-body">
                <h3 class="agent-name">
                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                </h3>
                <p class="agent-role"><?php echo htmlspecialchars($agent['role_name'] ?? __('agent', [], 'Agente')); ?></p>

                <div class="agent-contact">
                    <?php if ($agent['phone_mobile']): ?>
                    <a href="tel:<?php echo $agent['phone_mobile']; ?>" class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span><?php echo htmlspecialchars($agent['phone_mobile']); ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($agent['email']): ?>
                    <a href="mailto:<?php echo $agent['email']; ?>" class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($agent['email']); ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($agent['office_name']): ?>
                    <div class="contact-item">
                        <i class="fas fa-building"></i>
                        <span><?php echo htmlspecialchars($agent['office_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="agent-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($agent['properties_count']); ?></span>
                    <span class="stat-text"><?php echo __('properties.title', [], 'Propiedades'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($agent['clients_count']); ?></span>
                    <span class="stat-text"><?php echo __('clients.title', [], 'Clientes'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($agent['sales_count']); ?></span>
                    <span class="stat-text"><?php echo __('sales', [], 'Ventas'); ?></span>
                </div>
            </div>

            <div class="agent-actions">
                <a href="ver-agente.php?id=<?php echo $agent['id']; ?>" class="btn-modern btn-primary-modern">
                    <i class="fas fa-eye"></i>
                    <?php echo __('view', [], 'Ver'); ?>
                </a>
                <a href="editar-usuario.php?id=<?php echo $agent['id']; ?>" class="btn-modern btn-outline-modern">
                    <i class="fas fa-edit"></i>
                    <?php echo __('edit', [], 'Editar'); ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
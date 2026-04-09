<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('hr.title', [], 'Human Resources');
$currentUser = getCurrentUser();

// Verificar permisos - Solo admin y RRHH pueden acceder
$allowedRoles = ['administrador', 'rrhh'];
if (!in_array($currentUser['role']['name'], $allowedRoles)) {
    setFlashMessage('error', __('no_permissions', [], 'You do not have permissions to access this section'));
    redirect('dashboard.php');
}

// =====================================================
// OBTENER ESTADÍSTICAS DEL MÓDULO DE RRHH
// =====================================================

try {
    // Total de empleados
    $totalEmployees = db()->selectValue("
        SELECT COUNT(*) FROM users u
        INNER JOIN hr_employee_data ed ON u.id = ed.user_id
        WHERE ed.employment_status = 'active'
    ") ?? 0;
    
    // Empleados inactivos
    $inactiveEmployees = db()->selectValue("
        SELECT COUNT(*) FROM users u
        INNER JOIN hr_employee_data ed ON u.id = ed.user_id
        WHERE ed.employment_status != 'active'
    ") ?? 0;
    
    // Empleados nuevos este mes
    $newEmployeesMonth = db()->selectValue("
        SELECT COUNT(*) FROM hr_employee_data
        WHERE hire_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ") ?? 0;
    
    // Departamentos activos
    $totalDepartments = db()->selectValue("
        SELECT COUNT(*) FROM hr_departments WHERE is_active = 1
    ") ?? 0;
    
    // Próximos cumpleaños (próximos 30 días)
    $upcomingBirthdays = db()->select("
        SELECT u.id, u.first_name, u.last_name, u.profile_picture, u.date_of_birth,
               DATEDIFF(
                   DATE_ADD(u.date_of_birth, INTERVAL YEAR(CURDATE()) - YEAR(u.date_of_birth) + 
                   IF(DAYOFYEAR(u.date_of_birth) < DAYOFYEAR(CURDATE()), 1, 0) YEAR), 
                   CURDATE()
               ) as days_until_birthday
        FROM users u
        INNER JOIN hr_employee_data ed ON u.id = ed.user_id
        WHERE ed.employment_status = 'active' 
        AND u.date_of_birth IS NOT NULL
        HAVING days_until_birthday BETWEEN 0 AND 30
        ORDER BY days_until_birthday ASC
        LIMIT 5
    ");
    
    // Ausencias actuales
    $currentAbsences = db()->select("
        SELECT l.*, u.first_name, u.last_name, lt.name as leave_type_name, lt.color
        FROM hr_leaves l
        INNER JOIN users u ON l.user_id = u.id
        INNER JOIN hr_leave_types lt ON l.leave_type_id = lt.id
        WHERE l.status = 'approved'
        AND CURDATE() BETWEEN l.start_date AND l.end_date
        ORDER BY l.end_date ASC
    ");
    
    // Próximos pagos de nómina
    $upcomingPayrolls = db()->select("
        SELECT p.*, 
               (SELECT COUNT(*) FROM hr_payrolls WHERE period_id = p.id) as employee_count
        FROM hr_payroll_periods p
        WHERE p.payment_date >= CURDATE()
        ORDER BY p.payment_date ASC
        LIMIT 3
    ");
    
    // Contratos próximos a vencer (30 días)
    $expiringContracts = db()->select("
        SELECT c.*, u.first_name, u.last_name, u.email
        FROM hr_contracts c
        INNER JOIN users u ON c.user_id = u.id
        WHERE c.status = 'active'
        AND c.end_date IS NOT NULL
        AND c.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY c.end_date ASC
    ");
    
    // Evaluaciones pendientes
    $pendingEvaluations = db()->selectValue("
        SELECT COUNT(*) FROM hr_evaluations
        WHERE status = 'draft'
    ") ?? 0;
    
    // Total de nómina del mes actual
    $currentMonthPayroll = db()->selectValue("
        SELECT COALESCE(SUM(total_net), 0)
        FROM hr_payroll_periods
        WHERE MONTH(start_date) = MONTH(CURDATE())
        AND YEAR(start_date) = YEAR(CURDATE())
        AND status = 'paid'
    ") ?? 0;
    
    // Ausencias pendientes de aprobación
    $pendingLeaves = db()->selectValue("
        SELECT COUNT(*) FROM hr_leaves WHERE status = 'pending'
    ") ?? 0;
    
    // Comisiones pendientes
    $pendingCommissions = db()->selectValue("
        SELECT COALESCE(SUM(commission_amount), 0)
        FROM hr_commissions
        WHERE status = 'pending'
    ") ?? 0;
    
    // Empleados por departamento
    $employeesByDepartment = db()->select("
        SELECT d.name, d.id, COUNT(ed.id) as employee_count
        FROM hr_departments d
        LEFT JOIN hr_employee_data ed ON d.id = ed.department_id AND ed.employment_status = 'active'
        WHERE d.is_active = 1
        GROUP BY d.id, d.name
        ORDER BY employee_count DESC
        LIMIT 5
    ");
    
} catch (Exception $e) {
    logError('Error loading HR dashboard: ' . $e->getMessage());
    $totalEmployees = $inactiveEmployees = $newEmployeesMonth = $totalDepartments = 0;
    $pendingLeaves = $pendingCommissions = $pendingEvaluations = 0;
    $currentMonthPayroll = 0;
    $upcomingBirthdays = $currentAbsences = $upcomingPayrolls = $expiringContracts = $employeesByDepartment = [];
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
        --indigo: #6366f1;
        --pink: #ec4899;
        --orange: #f97316;
    }

    .hr-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: calc(100vh - 80px);
    }

    /* Page Header */
    .page-header-modern {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .page-header-modern::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }

    .page-title-modern {
        font-size: 32px;
        font-weight: 700;
        color: white;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 15px;
        position: relative;
        z-index: 1;
    }

    .page-title-modern i {
        font-size: 36px;
        opacity: 0.9;
    }

    .page-subtitle-modern {
        color: rgba(255, 255, 255, 0.9);
        margin: 0;
        font-size: 15px;
        position: relative;
        z-index: 1;
    }

    /* Stats Cards */
    .stats-grid-modern {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card-modern {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid #f1f3f5;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stat-card-modern:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-card-modern.blue { border-left: 4px solid var(--info); }
    .stat-card-modern.green { border-left: 4px solid var(--success); }
    .stat-card-modern.orange { border-left: 4px solid var(--warning); }
    .stat-card-modern.purple { border-left: 4px solid var(--purple); }
    .stat-card-modern.red { border-left: 4px solid var(--danger); }
    .stat-card-modern.indigo { border-left: 4px solid var(--indigo); }
    .stat-card-modern.pink { border-left: 4px solid var(--pink); }

    .stat-value-modern {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 5px;
    }

    .stat-label-modern {
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }

    .stat-icon-modern {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }

    /* Quick Actions Grid */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .quick-action-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
        border: 2px solid #f1f3f5;
        cursor: pointer;
    }

    .quick-action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        border-color: var(--primary);
    }

    .quick-action-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        margin: 0 auto 15px;
    }

    .quick-action-card.blue .quick-action-icon {
        background: #dbeafe;
        color: #3b82f6;
    }

    .quick-action-card.green .quick-action-icon {
        background: #d1fae5;
        color: #10b981;
    }

    .quick-action-card.orange .quick-action-icon {
        background: #fed7aa;
        color: #f97316;
    }

    .quick-action-card.purple .quick-action-icon {
        background: #e9d5ff;
        color: #8b5cf6;
    }

    .quick-action-card.pink .quick-action-icon {
        background: #fce7f3;
        color: #ec4899;
    }

    .quick-action-card.indigo .quick-action-icon {
        background: #e0e7ff;
        color: #6366f1;
    }

    .quick-action-title {
        font-size: 15px;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
    }

    /* Cards */
    .table-card-modern {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        border: 1px solid #f1f3f5;
    }

    .table-header-modern {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f3f5;
    }

    .table-title-modern {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Lists */
    .list-group-modern {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .list-item-modern {
        padding: 15px;
        border-bottom: 1px solid #f1f3f5;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s;
    }

    .list-item-modern:last-child {
        border-bottom: none;
    }

    .list-item-modern:hover {
        background: #f9fafb;
    }

    .list-item-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .list-item-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
    }

    .list-item-avatar-placeholder {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
    }

    .list-item-info h6 {
        margin: 0 0 3px 0;
        font-size: 15px;
        font-weight: 600;
        color: #1f2937;
    }

    .list-item-info small {
        font-size: 13px;
        color: #6b7280;
    }

    /* Badges */
    .badge-modern {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning {
        background: #fed7aa;
        color: #92400e;
    }

    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-info {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-purple {
        background: #e9d5ff;
        color: #6b21a8;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .hr-container {
            padding: 15px;
        }

        .page-header-modern {
            padding: 20px;
        }

        .page-title-modern {
            font-size: 24px;
        }

        .stats-grid-modern {
            grid-template-columns: 1fr;
        }

        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- Main Content - SIN el div content-wrapper adicional -->
<div class="hr-container">

    <!-- Page Header -->
    <div class="page-header-modern">
        <div>
            <h1 class="page-title-modern">
                <i class="fas fa-users-cog"></i>
                <?php echo __('hr.title', [], 'Human Resources'); ?>
            </h1>
            <p class="page-subtitle-modern">
                <?php echo __('hr.subtitle', [], 'Complete management of employees, payroll, absences and evaluations'); ?>
            </p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid-modern">
        <div class="stat-card-modern blue">
            <div>
                <div class="stat-value-modern"><?php echo number_format($totalEmployees); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.active_employees', [], 'Active Employees'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #dbeafe; color: #3b82f6;">
                <i class="fas fa-user-check"></i>
            </div>
        </div>

        <div class="stat-card-modern green">
            <div>
                <div class="stat-value-modern"><?php echo number_format($newEmployeesMonth); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.new_this_month', [], 'New This Month'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #d1fae5; color: #10b981;">
                <i class="fas fa-user-plus"></i>
            </div>
        </div>

        <div class="stat-card-modern orange">
            <div>
                <div class="stat-value-modern"><?php echo number_format($totalDepartments); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.departments', [], 'Departments'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #fed7aa; color: #f97316;">
                <i class="fas fa-sitemap"></i>
            </div>
        </div>

        <div class="stat-card-modern purple">
            <div>
                <div class="stat-value-modern"><?php echo number_format($pendingLeaves); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.pending_leaves', [], 'Pending Absences'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #e9d5ff; color: #8b5cf6;">
                <i class="fas fa-calendar-times"></i>
            </div>
        </div>

        <div class="stat-card-modern red">
            <div>
                <div class="stat-value-modern">$<?php echo number_format($currentMonthPayroll, 2); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.monthly_payroll', [], 'Monthly Payroll'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #fee2e2; color: #ef4444;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
        </div>

        <div class="stat-card-modern indigo">
            <div>
                <div class="stat-value-modern">$<?php echo number_format($pendingCommissions, 2); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.pending_commissions', [], 'Pending Commissions'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #e0e7ff; color: #6366f1;">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
        </div>

        <div class="stat-card-modern pink">
            <div>
                <div class="stat-value-modern"><?php echo number_format($pendingEvaluations); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.pending_evaluations', [], 'Pending Evaluations'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #fce7f3; color: #ec4899;">
                <i class="fas fa-star"></i>
            </div>
        </div>

        <div class="stat-card-modern orange">
            <div>
                <div class="stat-value-modern"><?php echo number_format($inactiveEmployees); ?></div>
                <div class="stat-label-modern"><?php echo __('hr.inactive_employees', [], 'Inactive Employees'); ?></div>
            </div>
            <div class="stat-icon-modern" style="background: #fed7aa; color: #f97316;">
                <i class="fas fa-user-slash"></i>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="table-card-modern">
        <div class="table-header-modern">
            <h5 class="table-title-modern">
                <i class="fas fa-bolt"></i>
                <?php echo __('hr.quick_access', [], 'Quick Access'); ?>
            </h5>
        </div>

        <div class="quick-actions-grid">
            <a href="empleados.php" class="quick-action-card blue">
                <div class="quick-action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.employees', [], 'Employees'); ?></h6>
            </a>

            <a href="nomina.php" class="quick-action-card green">
                <div class="quick-action-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.payroll', [], 'Payroll'); ?></h6>
            </a>

            <a href="vacaciones.php" class="quick-action-card orange">
                <div class="quick-action-icon">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.vacations', [], 'Vacations'); ?></h6>
            </a>

            <a href="evaluacion.php" class="quick-action-card purple">
                <div class="quick-action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.evaluation', [], 'Evaluation'); ?></h6>
            </a>

            <a href="contratos.php" class="quick-action-card pink">
                <div class="quick-action-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.contracts', [], 'Contracts'); ?></h6>
            </a>

            <a href="beneficios.php" class="quick-action-card indigo">
                <div class="quick-action-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.benefits', [], 'Benefits'); ?></h6>
            </a>

            <a href="reportesrrhh.php" class="quick-action-card blue">
                <div class="quick-action-icon">
                    <i class="fas fa-file-chart-line"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.reports', [], 'Reports'); ?></h6>
            </a>

            <a href="configrrhh.php" class="quick-action-card green">
                <div class="quick-action-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h6 class="quick-action-title"><?php echo __('hr.settings', [], 'Settings'); ?></h6>
            </a>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-md-6">
            
            <!-- Upcoming Birthdays -->
            <div class="table-card-modern">
                <div class="table-header-modern">
                    <h5 class="table-title-modern">
                        <i class="fas fa-birthday-cake"></i>
                        <?php echo __('hr.upcoming_birthdays', [], 'Upcoming Birthdays'); ?>
                    </h5>
                </div>
                
                <?php if(count($upcomingBirthdays) > 0): ?>
                <ul class="list-group-modern">
                    <?php foreach($upcomingBirthdays as $birthday): ?>
                    <li class="list-item-modern">
                        <div class="list-item-content">
                            <?php if($birthday['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($birthday['profile_picture']); ?>" 
                                 alt="" class="list-item-avatar">
                            <?php else: ?>
                            <div class="list-item-avatar-placeholder">
                                <?php echo strtoupper(substr($birthday['first_name'], 0, 1) . substr($birthday['last_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                            <div class="list-item-info">
                                <h6><?php echo htmlspecialchars($birthday['first_name'] . ' ' . $birthday['last_name']); ?></h6>
                                <small>
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('F d', strtotime($birthday['date_of_birth'])); ?>
                                </small>
                            </div>
                        </div>
                        <span class="badge-modern badge-info">
                            <?php 
                            $days = $birthday['days_until_birthday'];
                            echo $days == 0 ? __('hr.today', [], 'Today') : "$days " . __('days', [], 'days');
                            ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-birthday-cake"></i>
                    <p><?php echo __('hr.no_upcoming_birthdays', [], 'No upcoming birthdays'); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Current Absences -->
            <div class="table-card-modern">
                <div class="table-header-modern">
                    <h5 class="table-title-modern">
                        <i class="fas fa-calendar-times"></i>
                        <?php echo __('hr.current_absences', [], 'Current Absences'); ?>
                    </h5>
                </div>
                
                <?php if(count($currentAbsences) > 0): ?>
                <ul class="list-group-modern">
                    <?php foreach($currentAbsences as $absence): ?>
                    <li class="list-item-modern">
                        <div class="list-item-content">
                            <div class="list-item-info">
                                <h6><?php echo htmlspecialchars($absence['first_name'] . ' ' . $absence['last_name']); ?></h6>
                                <small>
                                    <span class="badge-modern" style="background: <?php echo $absence['color']; ?>20; color: <?php echo $absence['color']; ?>;">
                                        <?php echo htmlspecialchars($absence['leave_type_name']); ?>
                                    </span>
                                </small>
                            </div>
                        </div>
                        <small><?php echo date('M d', strtotime($absence['start_date'])); ?> - <?php echo date('M d', strtotime($absence['end_date'])); ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p><?php echo __('hr.no_current_absences', [], 'No current absences'); ?></p>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Right Column -->
        <div class="col-md-6">
            
            <!-- Upcoming Payrolls -->
            <div class="table-card-modern">
                <div class="table-header-modern">
                    <h5 class="table-title-modern">
                        <i class="fas fa-money-check-alt"></i>
                        <?php echo __('hr.upcoming_payrolls', [], 'Upcoming Payrolls'); ?>
                    </h5>
                </div>
                
                <?php if(count($upcomingPayrolls) > 0): ?>
                <ul class="list-group-modern">
                    <?php foreach($upcomingPayrolls as $payroll): ?>
                    <li class="list-item-modern">
                        <div class="list-item-content">
                            <div class="list-item-info">
                                <h6><?php echo htmlspecialchars($payroll['period_name']); ?></h6>
                                <small>
                                    <i class="fas fa-users"></i>
                                    <?php echo $payroll['employee_count']; ?> <?php echo __('hr.employees', [], 'employees'); ?>
                                </small>
                            </div>
                        </div>
                        <div class="text-end">
                            <div style="font-size: 13px; font-weight: 600; color: #10b981;">
                                $<?php echo number_format($payroll['total_net'], 2); ?>
                            </div>
                            <small style="color: #6b7280;">
                                <?php echo date('M d, Y', strtotime($payroll['payment_date'])); ?>
                            </small>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-money-check-alt"></i>
                    <p><?php echo __('hr.no_upcoming_payrolls', [], 'No upcoming payrolls'); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Expiring Contracts -->
            <div class="table-card-modern">
                <div class="table-header-modern">
                    <h5 class="table-title-modern">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo __('hr.expiring_contracts', [], 'Expiring Contracts'); ?>
                    </h5>
                </div>
                
                <?php if(count($expiringContracts) > 0): ?>
                <ul class="list-group-modern">
                    <?php foreach($expiringContracts as $contract): ?>
                    <li class="list-item-modern">
                        <div class="list-item-content">
                            <div class="list-item-info">
                                <h6><?php echo htmlspecialchars($contract['first_name'] . ' ' . $contract['last_name']); ?></h6>
                                <small><?php echo htmlspecialchars($contract['email']); ?></small>
                            </div>
                        </div>
                        <span class="badge-modern badge-warning">
                            <?php echo date('M d, Y', strtotime($contract['end_date'])); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p><?php echo __('hr.no_expiring_contracts', [], 'No expiring contracts'); ?></p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Employees by Department -->
    <?php if(count($employeesByDepartment) > 0): ?>
    <div class="table-card-modern">
        <div class="table-header-modern">
            <h5 class="table-title-modern">
                <i class="fas fa-sitemap"></i>
                <?php echo __('hr.employees_by_department', [], 'Employees by Department'); ?>
            </h5>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo __('hr.department', [], 'Department'); ?></th>
                        <th class="text-center"><?php echo __('hr.employees', [], 'Employees'); ?></th>
                        <th class="text-end"><?php echo __('hr.percentage', [], 'Percentage'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employeesByDepartment as $dept): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                        <td class="text-center">
                            <span class="badge-modern badge-info"><?php echo $dept['employee_count']; ?></span>
                        </td>
                        <td class="text-end">
                            <?php 
                            $percentage = $totalEmployees > 0 ? ($dept['employee_count'] / $totalEmployees) * 100 : 0;
                            echo number_format($percentage, 1); 
                            ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
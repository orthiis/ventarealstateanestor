<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('hr.employees', [], 'Employees');
$currentUser = getCurrentUser();

// Verificar permisos
$allowedRoles = ['administrador', 'rrhh'];
if (!in_array($currentUser['role']['name'], $allowedRoles)) {
    setFlashMessage('error', __('no_permissions', [], 'You do not have permissions'));
    redirect('recursoshumanos.php');
}

// Filtros
$searchTerm = $_GET['search'] ?? '';
$departmentFilter = $_GET['department'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

// Construir query
$where = ["1=1"];
$params = [];

if (!empty($searchTerm)) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR ed.employee_code LIKE ?)";
    $searchParam = "%{$searchTerm}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($departmentFilter)) {
    $where[] = "ed.department_id = ?";
    $params[] = $departmentFilter;
}

if (!empty($statusFilter)) {
    $where[] = "ed.employment_status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

// Contar total
$totalEmployees = db()->selectValue("
    SELECT COUNT(DISTINCT u.id) 
    FROM users u
    INNER JOIN hr_employee_data ed ON u.id = ed.user_id
    WHERE {$whereClause}
", $params);

// Paginación
$totalPages = ceil($totalEmployees / $perPage);
$offset = ($page - 1) * $perPage;

// Obtener empleados
$employees = db()->select("
    SELECT u.*, 
           ed.employee_code, ed.department_id, ed.hire_date, ed.employment_status,
           ed.base_salary, ed.salary_type, ed.contract_type,
           d.name as department_name,
           r.display_name as role_name
    FROM users u
    INNER JOIN hr_employee_data ed ON u.id = ed.user_id
    LEFT JOIN hr_departments d ON ed.department_id = d.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE {$whereClause}
    ORDER BY u.first_name ASC, u.last_name ASC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Obtener departamentos para filtro
$departments = db()->select("SELECT * FROM hr_departments WHERE is_active = 1 ORDER BY name");

// Estadísticas
$stats = [
    'active' => db()->selectValue("SELECT COUNT(*) FROM hr_employee_data WHERE employment_status = 'active'") ?? 0,
    'on_leave' => db()->selectValue("SELECT COUNT(*) FROM hr_employee_data WHERE employment_status = 'on_leave'") ?? 0,
    'suspended' => db()->selectValue("SELECT COUNT(*) FROM hr_employee_data WHERE employment_status = 'suspended'") ?? 0,
    'terminated' => db()->selectValue("SELECT COUNT(*) FROM hr_employee_data WHERE employment_status = 'terminated'") ?? 0,
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

    .employees-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: calc(100vh - 80px);
    }

    .page-header-modern {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 30px 40px;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-title-modern {
        font-size: 32px;
        font-weight: 700;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .page-subtitle-modern {
        color: rgba(255, 255, 255, 0.9);
        margin: 0;
        font-size: 15px;
    }

    .btn-add-modern {
        background: white;
        color: var(--primary);
        padding: 12px 24px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }

    .btn-add-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255,255,255,0.3);
        color: var(--primary);
    }

    /* Stats Cards */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        border-left: 4px solid;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .stat-card.active { border-color: var(--success); }
    .stat-card.on-leave { border-color: var(--warning); }
    .stat-card.suspended { border-color: var(--info); }
    .stat-card.terminated { border-color: var(--danger); }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 14px;
        color: #6b7280;
    }

    /* Filters Card */
    .filters-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }

    .filters-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 15px;
        align-items: end;
    }

    .form-group-modern {
        margin-bottom: 0;
    }

    .form-label-modern {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }

    .form-control-modern {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s;
    }

    .form-control-modern:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .btn-filter {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-filter:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
    }

    /* Table Card */
    .table-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f3f5;
    }

    .table-title {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }

    .table-modern {
        width: 100%;
        border-collapse: collapse;
    }

    .table-modern thead th {
        background: #f9fafb;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e5e7eb;
    }

    .table-modern tbody td {
        padding: 15px 12px;
        border-bottom: 1px solid #f1f3f5;
        font-size: 14px;
        color: #374151;
    }

    .table-modern tbody tr:hover {
        background: #f9fafb;
    }

    .employee-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .employee-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
    }

    .employee-avatar-placeholder {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--purple));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
    }

    .employee-details h6 {
        margin: 0 0 3px 0;
        font-size: 15px;
        font-weight: 600;
        color: #1f2937;
    }

    .employee-details small {
        color: #6b7280;
        font-size: 13px;
    }

    .badge-status {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .badge-status.active {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-status.on-leave {
        background: #fed7aa;
        color: #92400e;
    }

    .badge-status.suspended {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-status.terminated {
        background: #fee2e2;
        color: #991b1b;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        font-size: 14px;
    }

    .btn-action.view {
        background: #dbeafe;
        color: #3b82f6;
    }

    .btn-action.edit {
        background: #fef3c7;
        color: #f59e0b;
    }

    .btn-action.delete {
        background: #fee2e2;
        color: #ef4444;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    /* Pagination */
    .pagination-modern {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 25px;
    }

    .pagination-btn {
        padding: 8px 16px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
        color: #374151;
    }

    .pagination-btn:hover:not(:disabled) {
        border-color: var(--primary);
        color: var(--primary);
    }

    .pagination-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #9ca3af;
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 20px;
        margin-bottom: 10px;
        color: #6b7280;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .filters-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .employees-container {
            padding: 15px;
        }

        .page-header-modern {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }

        .table-modern {
            font-size: 12px;
        }

        .action-buttons {
            flex-direction: column;
        }
    }
</style>

<div class="employees-container">
    
    <!-- Page Header -->
    <div class="page-header-modern">
        <div>
            <h1 class="page-title-modern">
                <i class="fas fa-users"></i>
                <?php echo __('hr.employees', [], 'Employees'); ?>
            </h1>
            <p class="page-subtitle-modern">
                <?php echo __('hr.employees_subtitle', [], 'Manage all company employees'); ?>
            </p>
        </div>
        <button class="btn-add-modern" onclick="window.location.href='crear-empleado.php'">
            <i class="fas fa-plus"></i>
            <?php echo __('hr.add_employee', [], 'Add Employee'); ?>
        </button>
    </div>

    <!-- Stats Row -->
    <div class="stats-row">
        <div class="stat-card active">
            <div class="stat-value" style="color: var(--success);"><?php echo $stats['active']; ?></div>
            <div class="stat-label"><?php echo __('hr.active', [], 'Active'); ?></div>
        </div>
        <div class="stat-card on-leave">
            <div class="stat-value" style="color: var(--warning);"><?php echo $stats['on_leave']; ?></div>
            <div class="stat-label"><?php echo __('hr.on_leave', [], 'On Leave'); ?></div>
        </div>
        <div class="stat-card suspended">
            <div class="stat-value" style="color: var(--info);"><?php echo $stats['suspended']; ?></div>
            <div class="stat-label"><?php echo __('hr.suspended', [], 'Suspended'); ?></div>
        </div>
        <div class="stat-card terminated">
            <div class="stat-value" style="color: var(--danger);"><?php echo $stats['terminated']; ?></div>
            <div class="stat-label"><?php echo __('hr.terminated', [], 'Terminated'); ?></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="">
            <div class="filters-grid">
                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="fas fa-search"></i>
                        <?php echo __('search', [], 'Search'); ?>
                    </label>
                    <input type="text" name="search" class="form-control-modern" 
                           placeholder="<?php echo __('hr.search_placeholder', [], 'Name, email, employee code...'); ?>"
                           value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="fas fa-sitemap"></i>
                        <?php echo __('hr.department', [], 'Department'); ?>
                    </label>
                    <select name="department" class="form-control-modern">
                        <option value=""><?php echo __('all', [], 'All'); ?></option>
                        <?php foreach($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $departmentFilter == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group-modern">
                    <label class="form-label-modern">
                        <i class="fas fa-filter"></i>
                        <?php echo __('status', [], 'Status'); ?>
                    </label>
                    <select name="status" class="form-control-modern">
                        <option value=""><?php echo __('all', [], 'All'); ?></option>
                        <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>><?php echo __('hr.active', [], 'Active'); ?></option>
                        <option value="on_leave" <?php echo $statusFilter == 'on_leave' ? 'selected' : ''; ?>><?php echo __('hr.on_leave', [], 'On Leave'); ?></option>
                        <option value="suspended" <?php echo $statusFilter == 'suspended' ? 'selected' : ''; ?>><?php echo __('hr.suspended', [], 'Suspended'); ?></option>
                        <option value="terminated" <?php echo $statusFilter == 'terminated' ? 'selected' : ''; ?>><?php echo __('hr.terminated', [], 'Terminated'); ?></option>
                    </select>
                </div>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i>
                    <?php echo __('filter', [], 'Filter'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Employees Table -->
    <div class="table-card">
        <div class="table-header">
            <h5 class="table-title">
                <?php echo __('hr.employee_list', [], 'Employee List'); ?> (<?php echo $totalEmployees; ?>)
            </h5>
        </div>

        <?php if(count($employees) > 0): ?>
        <div class="table-responsive">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th><?php echo __('hr.employee', [], 'Employee'); ?></th>
                        <th><?php echo __('hr.employee_code', [], 'Code'); ?></th>
                        <th><?php echo __('hr.department', [], 'Department'); ?></th>
                        <th><?php echo __('hr.role', [], 'Role'); ?></th>
                        <th><?php echo __('hr.hire_date', [], 'Hire Date'); ?></th>
                        <th><?php echo __('hr.salary', [], 'Salary'); ?></th>
                        <th><?php echo __('status', [], 'Status'); ?></th>
                        <th class="text-center"><?php echo __('actions', [], 'Actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $employee): ?>
                    <tr>
                        <td>
                            <div class="employee-info">
                                <?php if($employee['profile_picture']): ?>
                                <img src="<?php echo htmlspecialchars($employee['profile_picture']); ?>" 
                                     alt="" class="employee-avatar">
                                <?php else: ?>
                                <div class="employee-avatar-placeholder">
                                    <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div class="employee-details">
                                    <h6><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h6>
                                    <small><?php echo htmlspecialchars($employee['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><strong><?php echo htmlspecialchars($employee['employee_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($employee['department_name'] ?? __('hr.no_department', [], 'No Department')); ?></td>
                        <td><?php echo htmlspecialchars($employee['role_name']); ?></td>
                        <td><?php echo $employee['hire_date'] ? date('M d, Y', strtotime($employee['hire_date'])) : '-'; ?></td>
                        <td><strong>$<?php echo number_format($employee['base_salary'], 2); ?></strong></td>
                        <td>
                            <span class="badge-status <?php echo $employee['employment_status']; ?>">
                                <?php echo __('' . $employee['employment_status'], [], ucfirst(str_replace('_', ' ', $employee['employment_status']))); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action view" onclick="viewEmployee(<?php echo $employee['id']; ?>)" 
                                        title="<?php echo __('view', [], 'View'); ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-action edit" onclick="editEmployee(<?php echo $employee['id']; ?>)"
                                        title="<?php echo __('edit', [], 'Edit'); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action delete" onclick="deleteEmployee(<?php echo $employee['id']; ?>)"
                                        title="<?php echo __('delete', [], 'Delete'); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if($totalPages > 1): ?>
        <div class="pagination-modern">
            <?php if($page > 1): ?>
            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($searchTerm); ?>&department=<?php echo $departmentFilter; ?>&status=<?php echo $statusFilter; ?>" 
               class="pagination-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for($i = 1; $i <= $totalPages; $i++): ?>
                <?php if($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&department=<?php echo $departmentFilter; ?>&status=<?php echo $statusFilter; ?>" 
                   class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php elseif($i == $page - 3 || $i == $page + 3): ?>
                <span class="pagination-btn" disabled>...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if($page < $totalPages): ?>
            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($searchTerm); ?>&department=<?php echo $departmentFilter; ?>&status=<?php echo $statusFilter; ?>" 
               class="pagination-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <h3><?php echo __('hr.no_employees', [], 'No employees found'); ?></h3>
            <p><?php echo __('hr.no_employees_desc', [], 'Try adjusting your filters or add a new employee'); ?></p>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function viewEmployee(id) {
    window.location.href = 'ver-empleado.php?id=' + id;
}

function editEmployee(id) {
    window.location.href = 'editar-empleado.php?id=' + id;
}

function deleteEmployee(id) {
    Swal.fire({
        title: '<?php echo __('are_you_sure', [], 'Are you sure?'); ?>',
        text: '<?php echo __('hr.delete_employee_confirm', [], 'This employee will be deactivated'); ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<?php echo __('yes_delete', [], 'Yes, delete'); ?>',
        cancelButtonText: '<?php echo __('cancel', [], 'Cancel'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/delete-employee.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({
                        title: '<?php echo __('deleted', [], 'Deleted!'); ?>',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: '<?php echo __('error', [], 'Error'); ?>',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }
    });
}
</script>

<?php include 'footer.php'; ?>
<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Nueva Tarea';
$currentUser = getCurrentUser();

// Obtener usuarios para asignar
$users = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name, profile_picture
     FROM users 
     WHERE status = 'active' 
     ORDER BY first_name"
);

// Obtener clientes activos
$clients = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name, reference
     FROM clients 
     WHERE is_active = 1 
     ORDER BY first_name 
     LIMIT 100"
);

// Obtener propiedades disponibles
$properties = db()->select(
    "SELECT id, reference, title, city
     FROM properties 
     WHERE status IN ('available', 'reserved') 
     ORDER BY created_at DESC 
     LIMIT 100"
);

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
    }

    .tasks-container {
        padding: 30px;
        background: #f8f9fa;
    }

    /* Header Moderno */
    .page-header-modern {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .page-title-modern {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 5px 0;
    }

    .breadcrumb {
        background: transparent;
        padding: 0;
        margin-bottom: 15px;
    }

    .form-modern {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-section {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e5e7eb;
    }

    .form-section:last-child {
        border-bottom: none;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title i {
        color: #667eea;
    }

    .form-label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 10px 15px;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .required::after {
        content: '*';
        color: #ef4444;
        margin-left: 4px;
    }

    .priority-selector {
        display: flex;
        gap: 10px;
    }

    .priority-option {
        flex: 1;
        padding: 15px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }

    .priority-option:hover {
        border-color: #667eea;
    }

    .priority-option input[type="radio"] {
        display: none;
    }

    .priority-option input[type="radio"]:checked + .priority-label {
        font-weight: 600;
    }

    .priority-option.high {
        border-color: #ef4444;
        background: #fee2e2;
    }

    .priority-option.medium {
        border-color: #f59e0b;
        background: #fef3c7;
    }

    .priority-option.low {
        border-color: #10b981;
        background: #d1fae5;
    }

    .priority-option.high input[type="radio"]:checked + .priority-label {
        color: #991b1b;
    }

    .priority-option.medium input[type="radio"]:checked + .priority-label {
        color: #92400e;
    }

    .priority-option.low input[type="radio"]:checked + .priority-label {
        color: #065f46;
    }

    .btn-primary-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-primary-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="tasks-container">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="tareas.php">Tareas</a></li>
                <li class="breadcrumb-item active">Nueva Tarea</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="page-header-modern">
            <h2 class="page-title-modern">
                <i class="fas fa-plus-circle" style="color: #667eea;"></i> Nueva Tarea
            </h2>
        </div>

        <!-- Formulario -->
        <form id="taskForm" class="form-modern">
            
            <!-- Información Básica -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Información Básica
                </h5>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label required">Título de la tarea</label>
                        <input type="text" class="form-control" name="title" required 
                               placeholder="Ej: Llamar al cliente para agendar visita">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="description" rows="4" 
                                  placeholder="Describe los detalles de la tarea..."></textarea>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Tipo de tarea</label>
                        <select class="form-select" name="task_type" required>
                            <option value="">Seleccionar tipo</option>
                            <option value="call">📞 Llamada</option>
                            <option value="meeting">🤝 Reunión</option>
                            <option value="visit">🏠 Visita a propiedad</option>
                            <option value="follow_up">📋 Seguimiento</option>
                            <option value="email">📧 Email</option>
                            <option value="administrative">📄 Administrativa</option>
                            <option value="other">📌 Otro</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Asignar a</label>
                        <select class="form-select" name="assigned_to" required>
                            <option value="">Seleccionar usuario</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user['id'] == $currentUser['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Prioridad y Fecha -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-flag"></i>
                    Prioridad y Vencimiento
                </h5>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label required">Prioridad</label>
                        <div class="priority-selector">
                            <label class="priority-option high">
                                <input type="radio" name="priority" value="high" required>
                                <div class="priority-label">
                                    <i class="fas fa-exclamation-circle"></i><br>
                                    Alta
                                </div>
                            </label>
                            <label class="priority-option medium">
                                <input type="radio" name="priority" value="medium" checked required>
                                <div class="priority-label">
                                    <i class="fas fa-minus-circle"></i><br>
                                    Media
                                </div>
                            </label>
                            <label class="priority-option low">
                                <input type="radio" name="priority" value="low" required>
                                <div class="priority-label">
                                    <i class="fas fa-arrow-down"></i><br>
                                    Baja
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha y hora de vencimiento</label>
                        <input type="datetime-local" class="form-control" name="due_date">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Recordatorio</label>
                        <select class="form-select" name="reminder_minutes">
                            <option value="">Sin recordatorio</option>
                            <option value="15">15 minutos antes</option>
                            <option value="30">30 minutos antes</option>
                            <option value="60">1 hora antes</option>
                            <option value="1440">1 día antes</option>
                            <option value="2880">2 días antes</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Relaciones -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-link"></i>
                    Relacionar con
                </h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cliente</label>
                        <select class="form-select" name="related_client_id" id="clientSelect">
                            <option value="">Sin cliente relacionado</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>">
                                <?php echo htmlspecialchars($client['reference'] . ' - ' . $client['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Propiedad</label>
                        <select class="form-select" name="related_property_id" id="propertySelect">
                            <option value="">Sin propiedad relacionada</option>
                            <?php foreach ($properties as $property): ?>
                            <option value="<?php echo $property['id']; ?>">
                                <?php echo htmlspecialchars($property['reference'] . ' - ' . $property['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="d-flex gap-2 justify-content-end">
                <a href="tareas.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary-gradient">
                    <i class="fas fa-save"></i> Crear Tarea
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('taskForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    
    fetch('ajax/task-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Tarea creada exitosamente');
            window.location.href = 'tareas.php';
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo crear la tarea'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al crear la tarea');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Mejorar los selectores con búsqueda
document.addEventListener('DOMContentLoaded', function() {
    const clientSelect = document.getElementById('clientSelect');
    const propertySelect = document.getElementById('propertySelect');
    
    // Agregar búsqueda simple
    [clientSelect, propertySelect].forEach(select => {
        select.addEventListener('focus', function() {
            this.size = Math.min(this.options.length, 10);
        });
        
        select.addEventListener('blur', function() {
            this.size = 1;
        });
    });
});
</script>

<?php include 'footer.php'; ?>
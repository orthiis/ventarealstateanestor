<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Nuevo Evento';
$currentUser = getCurrentUser();

// Obtener fecha de inicio si viene desde el calendario
$startDate = $_GET['start'] ?? '';

// Obtener usuarios para asignar
$users = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name
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

    .calendar-container {
        padding: 30px;
        background: #f8f9fa;
    }

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

    .event-type-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .event-type-option {
        padding: 20px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }

    .event-type-option:hover {
        border-color: #667eea;
        transform: translateY(-2px);
    }

    .event-type-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }

    .event-type-option input[type="radio"]:checked + .type-content {
        color: #667eea;
        font-weight: 600;
    }

    .event-type-option input[type="radio"]:checked ~ .event-type-option {
        border-color: #667eea;
        background: #f0f4ff;
    }

    .event-type-option.checked {
        border-color: #667eea;
        background: #f0f4ff;
    }

    .type-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }

    .color-picker-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 10px;
    }

    .color-option {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        cursor: pointer;
        border: 3px solid transparent;
        transition: all 0.2s;
    }

    .color-option:hover {
        transform: scale(1.1);
    }

    .color-option.selected {
        border-color: #1f2937;
        transform: scale(1.15);
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
        color: white;
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="calendar-container">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="calendario.php">Calendario</a></li>
                <li class="breadcrumb-item active">Nuevo Evento</li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="page-header-modern">
            <h2 class="page-title-modern">
                <i class="fas fa-calendar-plus" style="color: #667eea;"></i> Nuevo Evento
            </h2>
        </div>

        <!-- Formulario -->
        <form id="eventForm" class="form-modern">
            
            <!-- Información Básica -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Información del Evento
                </h5>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label required">Título del evento</label>
                        <input type="text" class="form-control" name="title" required 
                               placeholder="Ej: Visita a propiedad en Piantini">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="description" rows="3" 
                                  placeholder="Detalles adicionales del evento..."></textarea>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label required">Tipo de evento</label>
                        <div class="event-type-selector">
                            <label class="event-type-option">
                                <input type="radio" name="event_type" value="visit" required>
                                <div class="type-content">
                                    <div class="type-icon">🏠</div>
                                    <div>Visita</div>
                                </div>
                            </label>
                            <label class="event-type-option">
                                <input type="radio" name="event_type" value="meeting" required>
                                <div class="type-content">
                                    <div class="type-icon">🤝</div>
                                    <div>Reunión</div>
                                </div>
                            </label>
                            <label class="event-type-option">
                                <input type="radio" name="event_type" value="call" required>
                                <div class="type-content">
                                    <div class="type-icon">📞</div>
                                    <div>Llamada</div>
                                </div>
                            </label>
                            <label class="event-type-option">
                                <input type="radio" name="event_type" value="signing" required>
                                <div class="type-content">
                                    <div class="type-icon">✍️</div>
                                    <div>Firma</div>
                                </div>
                            </label>
                            <label class="event-type-option">
                                <input type="radio" name="event_type" value="deadline" required>
                                <div class="type-content">
                                    <div class="type-icon">⏰</div>
                                    <div>Fecha Límite</div>
                                </div>
                            </label>
                            <label class="event-type-option">
                                <input type="radio" name="event_type" value="other" required>
                                <div class="type-content">
                                    <div class="type-icon">📌</div>
                                    <div>Otro</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fecha y Hora -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-clock"></i>
                    Fecha y Duración
                </h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Fecha y hora de inicio</label>
                        <input type="datetime-local" class="form-control" name="start_datetime" 
                               value="<?php echo $startDate ? date('Y-m-d\TH:i', strtotime($startDate)) : ''; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Fecha y hora de fin</label>
                        <input type="datetime-local" class="form-control" name="end_datetime" required>
                    </div>

                    <div class="col-md-12 mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="all_day" id="allDayCheck">
                            <label class="form-check-label" for="allDayCheck">
                                Evento de todo el día
                            </label>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" class="form-control" name="location" 
                               placeholder="Ej: Oficina principal, Zoom, etc.">
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

            <!-- Participantes y Relaciones -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-users"></i>
                    Participantes y Relaciones
                </h5>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cliente relacionado</label>
                        <select class="form-select" name="related_client_id">
                            <option value="">Sin cliente relacionado</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>">
                                <?php echo htmlspecialchars($client['reference'] . ' - ' . $client['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Propiedad relacionada</label>
                        <select class="form-select" name="related_property_id">
                            <option value="">Sin propiedad relacionada</option>
                            <?php foreach ($properties as $property): ?>
                            <option value="<?php echo $property['id']; ?>">
                                <?php echo htmlspecialchars($property['reference'] . ' - ' . $property['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Asistentes</label>
                        <select class="form-select" name="attendees[]" multiple size="5">
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user['id'] == $currentUser['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Mantén presionado Ctrl (Cmd en Mac) para seleccionar múltiples usuarios</small>
                    </div>
                </div>
            </div>

            <!-- Color del Evento -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-palette"></i>
                    Color del Evento
                </h5>

                <div class="color-picker-grid">
                    <div class="color-option" style="background: #10b981;" data-color="#10b981"></div>
                    <div class="color-option" style="background: #3b82f6;" data-color="#3b82f6"></div>
                    <div class="color-option" style="background: #f59e0b;" data-color="#f59e0b"></div>
                    <div class="color-option" style="background: #ef4444;" data-color="#ef4444"></div>
                    <div class="color-option" style="background: #8b5cf6;" data-color="#8b5cf6"></div>
                    <div class="color-option" style="background: #ec4899;" data-color="#ec4899"></div>
                    <div class="color-option" style="background: #14b8a6;" data-color="#14b8a6"></div>
                    <div class="color-option selected" style="background: #667eea;" data-color="#667eea"></div>
                    <div class="color-option" style="background: #06b6d4;" data-color="#06b6d4"></div>
                    <div class="color-option" style="background: #84cc16;" data-color="#84cc16"></div>
                    <div class="color-option" style="background: #f97316;" data-color="#f97316"></div>
                    <div class="color-option" style="background: #6b7280;" data-color="#6b7280"></div>
                </div>
                <input type="hidden" name="color" id="selectedColor" value="#667eea">
            </div>

            <!-- Botones -->
            <div class="d-flex gap-2 justify-content-end">
                <a href="calendario.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary-gradient">
                    <i class="fas fa-save"></i> Crear Evento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Manejo de selección de tipo de evento
document.querySelectorAll('.event-type-option input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.event-type-option').forEach(opt => {
            opt.classList.remove('checked');
        });
        if (this.checked) {
            this.closest('.event-type-option').classList.add('checked');
        }
    });
});

// Manejo de selección de color
document.querySelectorAll('.color-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.color-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        this.classList.add('selected');
        document.getElementById('selectedColor').value = this.dataset.color;
    });
});

// Envío del formulario
document.getElementById('eventForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create');
    
    // Convertir array de asistentes a JSON
    const attendees = formData.getAll('attendees[]');
    formData.delete('attendees[]');
    formData.append('attendees', JSON.stringify(attendees));
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    
    fetch('ajax/calendar-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Evento creado exitosamente');
            window.location.href = 'calendario.php';
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo crear el evento'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al crear el evento');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Sincronizar fechas
document.querySelector('[name="start_datetime"]').addEventListener('change', function() {
    const endInput = document.querySelector('[name="end_datetime"]');
    if (!endInput.value) {
        const startDate = new Date(this.value);
        startDate.setHours(startDate.getHours() + 1);
        endInput.value = startDate.toISOString().slice(0, 16);
    }
});
</script>

<?php include 'footer.php'; ?>
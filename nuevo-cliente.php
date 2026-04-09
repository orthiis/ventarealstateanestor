<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Crear Nuevo Cliente';
$currentUser = getCurrentUser();

// Obtener agentes para el formulario
$agents = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name 
     FROM users 
     WHERE status = 'active' AND role_id IN (SELECT id FROM roles WHERE name IN ('administrador', 'agente'))
     ORDER BY first_name"
);

// Obtener tipos de propiedad
$propertyTypes = db()->select("SELECT * FROM property_types WHERE is_active = 1 ORDER BY name");

// Obtener ciudades
$cities = db()->select("SELECT DISTINCT city FROM properties WHERE city IS NOT NULL AND city != '' ORDER BY city");

include 'header.php';
include 'sidebar.php';
?>

<style>
    :root {
        --primary: #667eea;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
    }

    .create-client-container {
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
    }

    .page-title-modern {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 5px 0;
    }

    .page-subtitle-modern {
        color: #718096;
        margin: 0;
        font-size: 14px;
    }

    /* Form Card */
    .form-card-modern {
        background: white;
        border-radius: 16px;
        padding: 30px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .form-section-title {
        font-size: 18px;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f3f4f6;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-section-title i {
        color: var(--primary);
        font-size: 20px;
    }

    /* Form Elements */
    .form-label-modern {
        font-weight: 600;
        color: #4b5563;
        font-size: 14px;
        margin-bottom: 8px;
        display: block;
    }

    .required-field::after {
        content: " *";
        color: var(--danger);
        font-weight: 700;
    }

    .form-input-modern {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .form-input-modern:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-select-modern {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
        cursor: pointer;
    }

    .form-select-modern:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-textarea-modern {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
        resize: vertical;
        min-height: 100px;
    }

    .form-textarea-modern:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    /* Input Group */
    .input-group-modern {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 14px;
    }

    .input-group-modern .form-input-modern {
        padding-left: 40px;
    }

    /* Checkboxes Modern */
    .checkbox-group-modern {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
    }

    .checkbox-item-modern {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 15px;
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .checkbox-item-modern:hover {
        background: #f3f4f6;
        border-color: var(--primary);
    }

    .checkbox-item-modern input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .checkbox-item-modern label {
        margin: 0;
        cursor: pointer;
        font-size: 14px;
        color: #4b5563;
    }

    /* Help Text */
    .help-text-modern {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 5px;
    }

    /* Buttons */
    .btn-modern {
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-primary-modern {
        background: var(--primary);
        color: white;
    }

    .btn-primary-modern:hover {
        background: #5568d3;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary-modern {
        background: #6b7280;
        color: white;
    }

    .btn-secondary-modern:hover {
        background: #4b5563;
    }

    .btn-success-modern {
        background: var(--success);
        color: white;
    }

    .btn-success-modern:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    /* Form Actions */
    .form-actions-modern {
        background: white;
        padding: 20px 30px;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        position: sticky;
        bottom: 20px;
        z-index: 10;
    }

    /* Grid Layout */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -10px;
    }

    .col-12 { flex: 0 0 100%; max-width: 100%; padding: 0 10px; margin-bottom: 20px; }
    .col-md-6 { flex: 0 0 50%; max-width: 50%; padding: 0 10px; margin-bottom: 20px; }
    .col-md-4 { flex: 0 0 33.333%; max-width: 33.333%; padding: 0 10px; margin-bottom: 20px; }
    .col-md-3 { flex: 0 0 25%; max-width: 25%; padding: 0 10px; margin-bottom: 20px; }

    @media (max-width: 768px) {
        .col-md-6, .col-md-4, .col-md-3 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    .g-3 {
        margin: 0 -10px;
    }
</style>

<div class="create-client-container">
    <!-- Header -->
    <div class="page-header-modern">
        <div>
            <h1 class="page-title-modern">
                <i class="fas fa-user-plus" style="color: var(--primary);"></i>
                Crear Nuevo Cliente
            </h1>
            <p class="page-subtitle-modern">
                Complete la información del cliente para agregarlo al sistema
            </p>
        </div>
        <div>
            <a href="clientes.php" class="btn-modern btn-secondary-modern">
                <i class="fas fa-arrow-left"></i>
                Volver al Listado
            </a>
        </div>
    </div>

    <form id="clientForm" action="actions/cliente-actions.php" method="POST">
        <input type="hidden" name="action" value="create">

        <!-- Información Personal -->
        <div class="form-card-modern">
            <h3 class="form-section-title">
                <i class="fas fa-user"></i>
                Información Personal
            </h3>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-modern required-field">Nombre</label>
                    <input type="text" name="first_name" class="form-input-modern" 
                           placeholder="Ingrese el nombre" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required-field">Apellidos</label>
                    <input type="text" name="last_name" class="form-input-modern" 
                           placeholder="Ingrese los apellidos" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Tipo de Documento</label>
                    <select name="document_type" class="form-select-modern">
                        <option value="cedula">Cédula</option>
                        <option value="passport">Pasaporte</option>
                        <option value="rnc">RNC</option>
                        <option value="other">Otro</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Documento de Identidad</label>
                    <div class="input-group-modern">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="document_id" class="form-input-modern" 
                               placeholder="Ej: 001-1234567-8">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Fecha de Nacimiento</label>
                    <input type="date" name="birth_date" class="form-input-modern">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Nacionalidad</label>
                    <input type="text" name="nationality" class="form-input-modern" 
                           value="Dominicano/a" placeholder="Ej: Dominicano/a">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required-field">Email Principal</label>
                    <div class="input-group-modern">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-input-modern" 
                               placeholder="ejemplo@correo.com" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Email Secundario</label>
                    <div class="input-group-modern">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email_secondary" class="form-input-modern" 
                               placeholder="ejemplo2@correo.com">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required-field">Teléfono Móvil</label>
                    <div class="input-group-modern">
                        <i class="fas fa-mobile-alt input-icon"></i>
                        <input type="tel" name="phone_mobile" class="form-input-modern" 
                               placeholder="(809) 123-4567" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Teléfono Fijo</label>
                    <div class="input-group-modern">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone_home" class="form-input-modern" 
                               placeholder="(809) 123-4567">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Ciudad</label>
                    <input type="text" name="city" class="form-input-modern" 
                           placeholder="Santo Domingo" list="citiesList">
                    <datalist id="citiesList">
                        <?php foreach ($cities as $city): ?>
                        <option value="<?php echo htmlspecialchars($city['city']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Provincia/Estado</label>
                    <input type="text" name="state_province" class="form-input-modern" 
                           placeholder="Distrito Nacional">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">País</label>
                    <input type="text" name="country" class="form-input-modern" 
                           value="República Dominicana">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Código Postal</label>
                    <input type="text" name="postal_code" class="form-input-modern" 
                           placeholder="10101">
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Dirección Actual Completa</label>
                    <textarea name="address" class="form-textarea-modern" 
                              placeholder="Calle, número, sector, referencias..."></textarea>
                </div>
            </div>
        </div>

        <!-- Información de Interés -->
        <div class="form-card-modern">
            <h3 class="form-section-title">
                <i class="fas fa-heart"></i>
                Información de Interés
            </h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label-modern required-field">Tipo de Cliente</label>
                    <select name="client_type" class="form-select-modern" required>
                        <option value="">Seleccionar...</option>
                        <option value="buyer">Comprador</option>
                        <option value="seller">Vendedor</option>
                        <option value="tenant">Arrendatario</option>
                        <option value="landlord">Arrendador</option>
                        <option value="investor">Inversor</option>
                        <option value="other">Otro</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">¿Qué busca?</label>
                    <select name="interest_operation" class="form-select-modern">
                        <option value="">Seleccionar...</option>
                        <option value="buy">Comprar</option>
                        <option value="rent">Alquilar</option>
                        <option value="sell">Vender</option>
                        <option value="rent_out">Arrendar su propiedad</option>
                        <option value="invest">Invertir</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Prioridad</label>
                    <select name="priority" class="form-select-modern">
                        <option value="low">Baja</option>
                        <option value="medium" selected>Media</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Presupuesto Mínimo</label>
                    <div class="input-group-modern">
                        <i class="fas fa-dollar-sign input-icon"></i>
                        <input type="number" name="budget_min" class="form-input-modern" 
                               placeholder="50000" step="1000">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Presupuesto Máximo</label>
                    <div class="input-group-modern">
                        <i class="fas fa-dollar-sign input-icon"></i>
                        <input type="number" name="budget_max" class="form-input-modern" 
                               placeholder="200000" step="1000">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Habitaciones Deseadas</label>
                    <input type="number" name="bedrooms_desired" class="form-input-modern" 
                           min="0" max="20" placeholder="3">
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Baños Deseados</label>
                    <input type="number" name="bathrooms_desired" class="form-input-modern" 
                           min="0" max="10" step="0.5" placeholder="2">
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Día de Pago (1-31)</label>
                    <div class="input-group-modern">
                        <i class="fas fa-calendar-day input-icon"></i>
                        <input type="number" name="payment_day" class="form-input-modern" 
                               min="1" max="31" placeholder="Ej: 5">
                    </div>
                    <div class="help-text-modern">
                        Día del mes preferido para realizar pagos (para alquileres)
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Tipos de Propiedad de Interés</label>
                    <div class="checkbox-group-modern">
                        <?php foreach ($propertyTypes as $type): ?>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="interest_property_types[]" 
                                   value="<?php echo $type['id']; ?>" 
                                   id="propType<?php echo $type['id']; ?>">
                            <label for="propType<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['name']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Ubicaciones de Interés</label>
                    <div class="checkbox-group-modern">
                        <?php foreach ($cities as $city): ?>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="interest_locations[]" 
                                   value="<?php echo htmlspecialchars($city['city']); ?>" 
                                   id="city<?php echo md5($city['city']); ?>">
                            <label for="city<?php echo md5($city['city']); ?>">
                                <?php echo htmlspecialchars($city['city']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Características Deseadas</label>
                    <textarea name="desired_features" class="form-textarea-modern" 
                              placeholder="Piscina, terraza, garaje, jardín, etc."></textarea>
                </div>
            </div>
        </div>

        <!-- Información Comercial -->
        <div class="form-card-modern">
            <h3 class="form-section-title">
                <i class="fas fa-briefcase"></i>
                Información Comercial
            </h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label-modern">Estado del Cliente</label>
                    <select name="status" class="form-select-modern">
                        <option value="lead" selected>Prospecto</option>
                        <option value="contacted">Contactado</option>
                        <option value="qualified">Calificado</option>
                        <option value="proposal_sent">Propuesta Enviada</option>
                        <option value="negotiation">En Negociación</option>
                        <option value="closed">Cerrado</option>
                        <option value="lost">Perdido</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Fuente</label>
                    <select name="source" class="form-select-modern">
                        <option value="website" selected>Sitio Web</option>
                        <option value="referral">Referido</option>
                        <option value="call">Llamada</option>
                        <option value="portal">Portal Inmobiliario</option>
                        <option value="social_media">Redes Sociales</option>
                        <option value="walk_in">Visita Directa</option>
                        <option value="other">Otro</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern required-field">Agente Asignado</label>
                    <select name="agent_id" class="form-select-modern" required>
                        <option value="">Seleccionar agente...</option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>">
                            <?php echo htmlspecialchars($agent['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Método de Contacto Preferido</label>
                    <select name="preferred_contact_method" class="form-select-modern">
                        <option value="phone">Teléfono</option>
                        <option value="email" selected>Email</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="in_person">En Persona</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Mejor Hora para Contactar</label>
                    <select name="best_contact_time" class="form-select-modern">
                        <option value="">Cualquier hora</option>
                        <option value="morning">Mañana (8AM - 12PM)</option>
                        <option value="afternoon">Tarde (12PM - 5PM)</option>
                        <option value="evening">Noche (5PM - 8PM)</option>
                        <option value="weekend">Fin de Semana</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Notas Internas</label>
                    <textarea name="internal_notes" class="form-textarea-modern" 
                              placeholder="Notas privadas sobre el cliente..."></textarea>
                    <div class="help-text-modern">
                        Estas notas son privadas y no serán visibles para el cliente
                    </div>
                </div>
            </div>
        </div>

        <!-- Acceso al Portal (Opcional) -->
        <div class="form-card-modern">
            <h3 class="form-section-title">
                <i class="fas fa-key"></i>
                Acceso al Portal del Cliente (Opcional)
            </h3>

            <div class="row g-3">
                <div class="col-12">
                    <div class="checkbox-item-modern" style="display: inline-flex;">
                        <input type="checkbox" name="portal_active" value="1" 
                               id="portalActive" onchange="togglePortalPassword()">
                        <label for="portalActive">
                            Activar acceso al portal del cliente
                        </label>
                    </div>
                    <div class="help-text-modern">
                        Permitir que el cliente acceda a un portal privado para ver sus propiedades y documentos
                    </div>
                </div>

                <div id="portalPasswordSection" style="display: none; width: 100%;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-modern">Contraseña del Portal</label>
                            <div class="input-group-modern">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password" class="form-input-modern" 
                                       placeholder="Mínimo 8 caracteres" id="portalPassword">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-modern">Confirmar Contraseña</label>
                            <div class="input-group-modern">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password_confirm" class="form-input-modern" 
                                       placeholder="Repetir contraseña" id="portalPasswordConfirm">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="form-actions-modern">
            <a href="clientes.php" class="btn-modern btn-secondary-modern">
                <i class="fas fa-times"></i>
                Cancelar
            </a>
            <button type="submit" class="btn-modern btn-success-modern" id="btnSubmit">
                <i class="fas fa-check"></i>
                Crear Cliente
            </button>
        </div>
    </form>
</div>

<script>
// Toggle password section
function togglePortalPassword() {
    const checkbox = document.getElementById('portalActive');
    const section = document.getElementById('portalPasswordSection');
    const passwordInput = document.getElementById('portalPassword');
    const confirmInput = document.getElementById('portalPasswordConfirm');
    
    if (checkbox.checked) {
        section.style.display = 'block';
        passwordInput.required = true;
        confirmInput.required = true;
    } else {
        section.style.display = 'none';
        passwordInput.required = false;
        confirmInput.required = false;
        passwordInput.value = '';
        confirmInput.value = '';
    }
}

// Form submission
document.getElementById('clientForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('btnSubmit');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    
    // Validate passwords if portal is active
    const portalActive = document.getElementById('portalActive').checked;
    if (portalActive) {
        const password = document.getElementById('portalPassword').value;
        const confirm = document.getElementById('portalPasswordConfirm').value;
        
        if (password !== confirm) {
            alert('Las contraseñas no coinciden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Crear Cliente';
            return;
        }
        
        if (password.length < 8) {
            alert('La contraseña debe tener al menos 8 caracteres');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Crear Cliente';
            return;
        }
    }
    
    const formData = new FormData(this);
    
    fetch('actions/cliente-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Cliente creado exitosamente');
            window.location.href = 'ver-cliente.php?id=' + data.client_id;
        } else {
            alert('❌ Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Crear Cliente';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al crear el cliente');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Crear Cliente';
    });
});
</script>

<?php include 'footer.php'; ?>
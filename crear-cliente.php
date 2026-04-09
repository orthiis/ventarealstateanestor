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
        background: #f9fafb;
        padding: 12px 16px;
        border-radius: 10px;
        border: 2px solid #e5e7eb;
        transition: all 0.3s ease;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .checkbox-item-modern:hover {
        border-color: var(--primary);
        background: #f3f4f6;
    }

    .checkbox-item-modern input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .checkbox-item-modern label {
        cursor: pointer;
        margin: 0;
        font-size: 14px;
        color: #4b5563;
        font-weight: 500;
    }

    /* Help Text */
    .help-text-modern {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 5px;
    }

    /* Form Actions */
    .form-actions-modern {
        background: white;
        padding: 20px 30px;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        bottom: 20px;
        z-index: 100;
    }

    .btn-modern {
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .btn-primary-modern {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary-modern {
        background: #f3f4f6;
        color: #6b7280;
    }

    .btn-secondary-modern:hover {
        background: #e5e7eb;
    }

    /* Loading State */
    .btn-loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .btn-loading::after {
        content: "";
        width: 14px;
        height: 14px;
        margin-left: 8px;
        border: 2px solid transparent;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Range Input */
    .range-inputs {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 10px;
        align-items: center;
    }

    .range-separator {
        color: #9ca3af;
        font-weight: 600;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .create-client-container {
            padding: 15px;
        }

        .page-header-modern {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .form-card-modern {
            padding: 20px;
        }

        .form-actions-modern {
            flex-direction: column;
            gap: 10px;
        }

        .checkbox-group-modern {
            flex-direction: column;
        }
    }
</style>

<div class="create-client-container">
    
    <!-- Page Header -->
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

                <div class="col-md-4">
                    <label class="form-label-modern">Nacionalidad</label>
                    <input type="text" name="nationality" class="form-input-modern" 
                           placeholder="Ej: Dominicana">
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
                               placeholder="otro@correo.com">
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
                               placeholder="(809) 987-6543">
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Dirección Actual Completa</label>
                    <textarea name="address" class="form-textarea-modern" 
                              placeholder="Calle, número, sector, ciudad..."></textarea>
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
                    <label class="form-label-modern">Presupuesto</label>
                    <div class="range-inputs">
                        <input type="number" name="budget_min" class="form-input-modern" 
                               placeholder="Mínimo">
                        <span class="range-separator">—</span>
                        <input type="number" name="budget_max" class="form-input-modern" 
                               placeholder="Máximo">
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Tipos de Propiedad de Interés</label>
                    <div class="checkbox-group-modern">
                        <?php foreach($propertyTypes as $type): ?>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="interest_property_types[]" 
                                   value="<?php echo $type['id']; ?>" 
                                   id="type_<?php echo $type['id']; ?>">
                            <label for="type_<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['name']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Ubicaciones de Interés</label>
                    <div class="checkbox-group-modern">
                        <?php foreach($cities as $city): ?>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="interest_locations[]" 
                                   value="<?php echo $city['city']; ?>" 
                                   id="city_<?php echo md5($city['city']); ?>">
                            <label for="city_<?php echo md5($city['city']); ?>">
                                <?php echo htmlspecialchars($city['city']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Habitaciones Deseadas</label>
                    <select name="desired_bedrooms" class="form-select-modern">
                        <option value="">No especificado</option>
                        <option value="1">1 habitación</option>
                        <option value="2">2 habitaciones</option>
                        <option value="3">3 habitaciones</option>
                        <option value="4">4 habitaciones</option>
                        <option value="5">5+ habitaciones</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Baños Deseados</label>
                    <select name="desired_bathrooms" class="form-select-modern">
                        <option value="">No especificado</option>
                        <option value="1">1 baño</option>
                        <option value="2">2 baños</option>
                        <option value="3">3 baños</option>
                        <option value="4">4+ baños</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Características Deseadas</label>
                    <div class="checkbox-group-modern">
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="desired_features[]" value="parking" id="feat_parking">
                            <label for="feat_parking">Estacionamiento</label>
                        </div>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="desired_features[]" value="elevator" id="feat_elevator">
                            <label for="feat_elevator">Ascensor</label>
                        </div>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="desired_features[]" value="pool" id="feat_pool">
                            <label for="feat_pool">Piscina</label>
                        </div>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="desired_features[]" value="garden" id="feat_garden">
                            <label for="feat_garden">Jardín</label>
                        </div>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="desired_features[]" value="terrace" id="feat_terrace">
                            <label for="feat_terrace">Terraza</label>
                        </div>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="desired_features[]" value="security" id="feat_security">
                            <label for="feat_security">Seguridad 24/7</label>
                        </div>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="desired_features[]" value="gym" id="feat_gym">
                            <label for="feat_gym">Gimnasio</label>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Necesidades Especiales</label>
                    <textarea name="special_requirements" class="form-textarea-modern" 
                              placeholder="Describa cualquier necesidad especial del cliente..."></textarea>
                </div>
            </div>
        </div>

        <!-- Información de Gestión -->
        <div class="form-card-modern">
            <h3 class="form-section-title">
                <i class="fas fa-cogs"></i>
                Información de Gestión
            </h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label-modern required-field">Estado</label>
                    <select name="status" class="form-select-modern" required>
                        <option value="lead">Lead</option>
                        <option value="contacted">Contactado</option>
                        <option value="qualified">Cualificado</option>
                        <option value="proposal">Propuesta Enviada</option>
                        <option value="negotiation">Negociación</option>
                        <option value="closed">Cerrado</option>
                        <option value="lost">Perdido</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern required-field">Prioridad</label>
                    <select name="priority" class="form-select-modern" required>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                        <option value="low">Baja</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Fuente</label>
                    <select name="source" class="form-select-modern">
                        <option value="web">Sitio Web</option>
                        <option value="referral">Referido</option>
                        <option value="call">Llamada</option>
                        <option value="portal">Portal Inmobiliario</option>
                        <option value="social">Redes Sociales</option>
                        <option value="email">Email</option>
                        <option value="walk_in">Visita en Oficina</option>
                        <option value="other">Otro</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required-field">Agente Asignado</label>
                    <select name="agent_id" class="form-select-modern" required>
                        <option value="">Seleccionar agente...</option>
                        <?php foreach($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>" 
                                <?php echo $agent['id'] == $currentUser['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Fecha Estimada de Decisión</label>
                    <input type="date" name="estimated_decision_date" class="form-input-modern">
                    <div class="help-text-modern">
                        ¿Cuándo espera tomar una decisión?
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Probabilidad de Cierre (%)</label>
                    <input type="number" name="closing_probability" class="form-input-modern" 
                           min="0" max="100" value="50" placeholder="50">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Método de Contacto Preferido</label>
                    <select name="preferred_contact_method" class="form-select-modern">
                        <option value="phone">Teléfono</option>
                        <option value="email">Email</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="in_person">En Persona</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Notas Internas</label>
                    <textarea name="internal_notes" class="form-textarea-modern" 
                              placeholder="Notas privadas sobre el cliente que solo verá el equipo interno..."></textarea>
                    <div class="help-text-modern">
                        Estas notas son privadas y no serán visibles para el cliente
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions-modern">
            <div>
                <button type="button" onclick="window.location.href='clientes.php'" 
                        class="btn-modern btn-secondary-modern">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="save_and_continue" value="1" 
                        class="btn-modern btn-secondary-modern">
                    <i class="fas fa-save"></i>
                    Guardar y Crear Otro
                </button>
                <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                    <i class="fas fa-check"></i>
                    Guardar Cliente
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('clientForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;

    const formData = new FormData(this);
    
    fetch('actions/cliente-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showNotification('success', data.message);
            
            // Si se presionó "Guardar y Crear Otro"
            if(formData.get('save_and_continue')) {
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                setTimeout(() => {
                    window.location.href = 'ver-cliente.php?id=' + data.client_id;
                }, 1500);
            }
        } else {
            showNotification('error', data.message);
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Ocurrió un error al guardar el cliente');
        submitBtn.classList.remove('btn-loading');
        submitBtn.disabled = false;
    });
});

function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 10px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${message}
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 3000);
}
</script>

<?php include 'footer.php'; ?>
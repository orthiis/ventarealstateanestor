<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Editar Cliente';
$currentUser = getCurrentUser();

// Obtener ID del cliente
$clientId = $_GET['id'] ?? 0;

// Obtener datos del cliente
$client = db()->selectOne(
    "SELECT * FROM clients WHERE id = ?",
    [$clientId]
);

if (!$client) {
    setFlashMessage('error', 'Cliente no encontrado');
    redirect('clientes.php');
}

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

// Decodificar arrays JSON
$interestPropertyTypes = json_decode($client['property_type_interest'] ?? '[]', true);
$interestLocations = json_decode($client['locations_interest'] ?? '[]', true);

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

    .edit-client-container {
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
        gap: 20px;
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

    .client-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        background: #e0e7ff;
        color: var(--primary);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        margin-top: 8px;
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

    .form-input-modern:disabled {
        background: #e5e7eb;
        cursor: not-allowed;
        color: #6b7280;
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

    .checkbox-item-modern input[type="checkbox"]:checked + label {
        color: var(--primary);
        font-weight: 600;
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

    /* Status Badge */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.inactive {
        background: #fee2e2;
        color: #991b1b;
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

    .btn-info-modern {
        background: var(--info);
        color: white;
    }

    .btn-info-modern:hover {
        background: #2563eb;
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
        gap: 15px;
        position: sticky;
        bottom: 20px;
        z-index: 10;
    }

    .form-actions-left {
        display: flex;
        gap: 10px;
    }

    .form-actions-right {
        display: flex;
        gap: 10px;
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

        .form-actions-modern {
            flex-direction: column;
        }

        .form-actions-left,
        .form-actions-right {
            width: 100%;
            justify-content: center;
        }
    }

    .g-3 {
        margin: 0 -10px;
    }

    /* Info Card */
    .info-card-gray {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
    }
</style>

<div class="edit-client-container">
    <!-- Header -->
    <div class="page-header-modern">
        <div>
            <h1 class="page-title-modern">
                <i class="fas fa-user-edit" style="color: var(--primary);"></i>
                Editar Cliente
            </h1>
            <p class="page-subtitle-modern">
                <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
            </p>
            <span class="client-badge">
                <i class="fas fa-hashtag"></i> Cliente #<?php echo $client['id']; ?> • 
                REF: <?php echo htmlspecialchars($client['reference']); ?>
            </span>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="ver-cliente.php?id=<?php echo $client['id']; ?>" class="btn-modern btn-info-modern">
                <i class="fas fa-eye"></i>
                Ver Detalle
            </a>
            <a href="clientes.php" class="btn-modern btn-secondary-modern">
                <i class="fas fa-arrow-left"></i>
                Volver
            </a>
        </div>
    </div>

    <form id="clientForm" action="actions/cliente-actions.php" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo $client['id']; ?>">

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
                           value="<?php echo htmlspecialchars($client['first_name']); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required-field">Apellidos</label>
                    <input type="text" name="last_name" class="form-input-modern" 
                           value="<?php echo htmlspecialchars($client['last_name']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Tipo de Documento</label>
                    <select name="document_type" class="form-select-modern">
                        <option value="cedula" <?php echo ($client['document_type'] ?? 'cedula') === 'cedula' ? 'selected' : ''; ?>>Cédula</option>
                        <option value="passport" <?php echo ($client['document_type'] ?? '') === 'passport' ? 'selected' : ''; ?>>Pasaporte</option>
                        <option value="rnc" <?php echo ($client['document_type'] ?? '') === 'rnc' ? 'selected' : ''; ?>>RNC</option>
                        <option value="other" <?php echo ($client['document_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Documento de Identidad</label>
                    <div class="input-group-modern">
                        <i class="fas fa-id-card input-icon"></i>
                        <input type="text" name="document_id" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($client['document_id'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Fecha de Nacimiento</label>
                    <input type="date" name="birth_date" class="form-input-modern"
                           value="<?php echo $client['date_of_birth'] ?? ''; ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Nacionalidad</label>
                    <input type="text" name="nationality" class="form-input-modern" 
                           value="<?php echo htmlspecialchars($client['nationality'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required-field">Email Principal</label>
                    <div class="input-group-modern">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($client['email']); ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Email Secundario</label>
                    <div class="input-group-modern">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" name="email_secondary" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($client['email_secondary'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required-field">Teléfono Móvil</label>
                    <div class="input-group-modern">
                        <i class="fas fa-mobile-alt input-icon"></i>
                        <input type="tel" name="phone_mobile" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($client['phone_mobile']); ?>" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Teléfono Fijo</label>
                    <div class="input-group-modern">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone_home" class="form-input-modern" 
                               value="<?php echo htmlspecialchars($client['phone_home'] ?? ''); ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Ciudad</label>
                    <input type="text" name="city" class="form-input-modern" 
                           value="<?php echo htmlspecialchars($client['city'] ?? ''); ?>" 
                           list="citiesList">
                    <datalist id="citiesList">
                        <?php foreach ($cities as $city): ?>
                        <option value="<?php echo htmlspecialchars($city['city']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Provincia/Estado</label>
                    <input type="text" name="state_province" class="form-input-modern" 
                           value="<?php echo htmlspecialchars($client['state_province'] ?? ''); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">País</label>
                    <input type="text" name="country" class="form-input-modern" 
                           value="<?php echo htmlspecialchars($client['country'] ?? 'República Dominicana'); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Código Postal</label>
                    <input type="text" name="postal_code" class="form-input-modern" 
                           value="<?php echo htmlspecialchars($client['postal_code'] ?? ''); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Dirección Actual Completa</label>
                    <textarea name="address" class="form-textarea-modern"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
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
                        <option value="buyer" <?php echo $client['client_type'] === 'buyer' ? 'selected' : ''; ?>>Comprador</option>
                        <option value="seller" <?php echo $client['client_type'] === 'seller' ? 'selected' : ''; ?>>Vendedor</option>
                        <option value="tenant" <?php echo $client['client_type'] === 'tenant' ? 'selected' : ''; ?>>Arrendatario</option>
                        <option value="landlord" <?php echo $client['client_type'] === 'landlord' ? 'selected' : ''; ?>>Arrendador</option>
                        <option value="investor" <?php echo $client['client_type'] === 'investor' ? 'selected' : ''; ?>>Inversor</option>
                        <option value="other" <?php echo $client['client_type'] === 'other' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">¿Qué busca?</label>
                    <select name="interest_operation" class="form-select-modern">
                        <option value="">Seleccionar...</option>
                        <option value="buy" <?php echo ($client['interest_operation'] ?? '') === 'buy' ? 'selected' : ''; ?>>Comprar</option>
                        <option value="rent" <?php echo ($client['interest_operation'] ?? '') === 'rent' ? 'selected' : ''; ?>>Alquilar</option>
                        <option value="sell" <?php echo ($client['interest_operation'] ?? '') === 'sell' ? 'selected' : ''; ?>>Vender</option>
                        <option value="rent_out" <?php echo ($client['interest_operation'] ?? '') === 'rent_out' ? 'selected' : ''; ?>>Arrendar su propiedad</option>
                        <option value="invest" <?php echo ($client['interest_operation'] ?? '') === 'invest' ? 'selected' : ''; ?>>Invertir</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Prioridad</label>
                    <select name="priority" class="form-select-modern">
                        <option value="low" <?php echo ($client['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Baja</option>
                        <option value="medium" <?php echo ($client['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Media</option>
                        <option value="high" <?php echo ($client['priority'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>Alta</option>
                        <option value="urgent" <?php echo ($client['priority'] ?? 'medium') === 'urgent' ? 'selected' : ''; ?>>Urgente</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Presupuesto Mínimo</label>
                    <div class="input-group-modern">
                        <i class="fas fa-dollar-sign input-icon"></i>
                        <input type="number" name="budget_min" class="form-input-modern" 
                               value="<?php echo $client['budget_min'] ?? ''; ?>" 
                               step="1000">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Presupuesto Máximo</label>
                    <div class="input-group-modern">
                        <i class="fas fa-dollar-sign input-icon"></i>
                        <input type="number" name="budget_max" class="form-input-modern" 
                               value="<?php echo $client['budget_max'] ?? ''; ?>" 
                               step="1000">
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Habitaciones Deseadas</label>
                    <input type="number" name="bedrooms_desired" class="form-input-modern" 
                           min="0" max="20" 
                           value="<?php echo htmlspecialchars($client['bedrooms_desired'] ?? ''); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Baños Deseados</label>
                    <input type="number" name="bathrooms_desired" class="form-input-modern" 
                           min="0" max="10" step="0.5"
                           value="<?php echo htmlspecialchars($client['bathrooms_desired'] ?? ''); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Día de Pago (1-31)</label>
                    <div class="input-group-modern">
                        <i class="fas fa-calendar-day input-icon"></i>
                        <input type="number" name="payment_day" class="form-input-modern" 
                               min="1" max="31" 
                               value="<?php echo htmlspecialchars($client['payment_day'] ?? ''); ?>"
                               placeholder="Ej: 5">
                    </div>
                    <div class="help-text-modern">
                        Día del mes preferido para realizar pagos (para alquileres)
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Tipos de Propiedad de Interés</label>
                    <div class="checkbox-group-modern">
                        <?php 
                        $interestPropertyTypes = is_array($interestPropertyTypes) ? $interestPropertyTypes : [];
                        foreach ($propertyTypes as $type): 
                        ?>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="interest_property_types[]" 
                                   value="<?php echo $type['id']; ?>" 
                                   id="propType<?php echo $type['id']; ?>"
                                   <?php echo in_array($type['id'], $interestPropertyTypes) ? 'checked' : ''; ?>>
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
                        <?php 
                        $interestLocations = is_array($interestLocations) ? $interestLocations : [];
                        foreach ($cities as $city): 
                        ?>
                        <div class="checkbox-item-modern">
                            <input type="checkbox" name="interest_locations[]" 
                                   value="<?php echo htmlspecialchars($city['city']); ?>" 
                                   id="city<?php echo md5($city['city']); ?>"
                                   <?php echo in_array($city['city'], $interestLocations) ? 'checked' : ''; ?>>
                            <label for="city<?php echo md5($city['city']); ?>">
                                <?php echo htmlspecialchars($city['city']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Características Deseadas</label>
                    <textarea name="desired_features" class="form-textarea-modern"><?php echo htmlspecialchars($client['must_have_features'] ?? ''); ?></textarea>
                    <div class="help-text-modern">
                        Ej: Piscina, terraza, garaje, jardín, etc.
                    </div>
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
                <div class="col-md-3">
                    <label class="form-label-modern">Estado del Cliente</label>
                    <select name="status" class="form-select-modern">
                        <option value="lead" <?php echo $client['status'] === 'lead' ? 'selected' : ''; ?>>Prospecto</option>
                        <option value="contacted" <?php echo $client['status'] === 'contacted' ? 'selected' : ''; ?>>Contactado</option>
                        <option value="qualified" <?php echo $client['status'] === 'qualified' ? 'selected' : ''; ?>>Calificado</option>
                        <option value="proposal_sent" <?php echo $client['status'] === 'proposal_sent' ? 'selected' : ''; ?>>Propuesta Enviada</option>
                        <option value="negotiation" <?php echo $client['status'] === 'negotiation' ? 'selected' : ''; ?>>En Negociación</option>
                        <option value="closed" <?php echo $client['status'] === 'closed' ? 'selected' : ''; ?>>Cerrado</option>
                        <option value="lost" <?php echo $client['status'] === 'lost' ? 'selected' : ''; ?>>Perdido</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-modern">Fuente</label>
                    <select name="source" class="form-select-modern">
                        <option value="website" <?php echo ($client['source'] ?? 'website') === 'website' ? 'selected' : ''; ?>>Sitio Web</option>
                        <option value="referral" <?php echo ($client['source'] ?? '') === 'referral' ? 'selected' : ''; ?>>Referido</option>
                        <option value="call" <?php echo ($client['source'] ?? '') === 'call' ? 'selected' : ''; ?>>Llamada</option>
                        <option value="portal" <?php echo ($client['source'] ?? '') === 'portal' ? 'selected' : ''; ?>>Portal Inmobiliario</option>
                        <option value="social_media" <?php echo ($client['source'] ?? '') === 'social_media' ? 'selected' : ''; ?>>Redes Sociales</option>
                        <option value="walk_in" <?php echo ($client['source'] ?? '') === 'walk_in' ? 'selected' : ''; ?>>Visita Directa</option>
                        <option value="other" <?php echo ($client['source'] ?? '') === 'other' ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-modern required-field">Agente Asignado</label>
                    <select name="agent_id" class="form-select-modern" required>
                        <option value="">Seleccionar agente...</option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?php echo $agent['id']; ?>" 
                                <?php echo $client['agent_id'] == $agent['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($agent['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-modern">Estado del Registro</label>
                    <select name="is_active" class="form-select-modern">
                        <option value="1" <?php echo $client['is_active'] == 1 ? 'selected' : ''; ?>>Activo</option>
                        <option value="0" <?php echo $client['is_active'] == 0 ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Método de Contacto Preferido</label>
                    <select name="preferred_contact_method" class="form-select-modern">
                        <option value="phone" <?php echo ($client['preferred_contact_method'] ?? 'email') === 'phone' ? 'selected' : ''; ?>>Teléfono</option>
                        <option value="email" <?php echo ($client['preferred_contact_method'] ?? 'email') === 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="whatsapp" <?php echo ($client['preferred_contact_method'] ?? '') === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                        <option value="in_person" <?php echo ($client['preferred_contact_method'] ?? '') === 'in_person' ? 'selected' : ''; ?>>En Persona</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Mejor Hora para Contactar</label>
                    <select name="best_contact_time" class="form-select-modern">
                        <option value="" <?php echo empty($client['best_contact_time']) ? 'selected' : ''; ?>>Cualquier hora</option>
                        <option value="morning" <?php echo ($client['best_contact_time'] ?? '') === 'morning' ? 'selected' : ''; ?>>Mañana (8AM - 12PM)</option>
                        <option value="afternoon" <?php echo ($client['best_contact_time'] ?? '') === 'afternoon' ? 'selected' : ''; ?>>Tarde (12PM - 5PM)</option>
                        <option value="evening" <?php echo ($client['best_contact_time'] ?? '') === 'evening' ? 'selected' : ''; ?>>Noche (5PM - 8PM)</option>
                        <option value="weekend" <?php echo ($client['best_contact_time'] ?? '') === 'weekend' ? 'selected' : ''; ?>>Fin de Semana</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Notas Internas</label>
                    <textarea name="internal_notes" class="form-textarea-modern"><?php echo htmlspecialchars($client['internal_notes'] ?? ''); ?></textarea>
                    <div class="help-text-modern">
                        Estas notas son privadas y no serán visibles para el cliente
                    </div>
                </div>
            </div>
        </div>

        <!-- Acceso al Portal -->
        <div class="form-card-modern">
            <h3 class="form-section-title">
                <i class="fas fa-key"></i>
                Acceso al Portal del Cliente
            </h3>

            <div class="row g-3">
                <div class="col-12">
                    <div class="checkbox-item-modern" style="display: inline-flex;">
                        <input type="checkbox" name="portal_active" value="1" 
                               id="portalActive" 
                               <?php echo $client['portal_active'] == 1 ? 'checked' : ''; ?>
                               onchange="togglePortalPassword()">
                        <label for="portalActive">
                            Activar acceso al portal del cliente
                        </label>
                    </div>
                    <div class="help-text-modern">
                        Permitir que el cliente acceda a un portal privado para ver sus propiedades y documentos
                    </div>
                </div>

                <div id="portalPasswordSection" style="display: <?php echo $client['portal_active'] == 1 ? 'block' : 'none'; ?>; width: 100%;">
                    <div class="row g-3">
                        <div class="col-12">
                            <div style="padding: 12px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; margin-bottom: 15px;">
                                <i class="fas fa-info-circle" style="color: #f59e0b;"></i>
                                <strong>Nota:</strong> Deje los campos de contraseña vacíos si no desea cambiarla. Solo complete si desea establecer una nueva contraseña.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-modern">Nueva Contraseña (dejar vacío para no cambiar)</label>
                            <div class="input-group-modern">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password" class="form-input-modern" 
                                       placeholder="Mínimo 8 caracteres" id="portalPassword">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-modern">Confirmar Nueva Contraseña</label>
                            <div class="input-group-modern">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" name="password_confirm" class="form-input-modern" 
                                       placeholder="Repetir contraseña" id="portalPasswordConfirm">
                            </div>
                        </div>

                        <?php if ($client['last_login']): ?>
                        <div class="col-12">
                            <div style="padding: 10px; background: #f9fafb; border-radius: 8px; font-size: 13px;">
                                <i class="fas fa-clock" style="color: #6b7280;"></i>
                                <strong>Último acceso al portal:</strong> 
                                <?php echo date('d/m/Y H:i', strtotime($client['last_login'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="form-card-modern info-card-gray">
            <h3 class="form-section-title">
                <i class="fas fa-info-circle"></i>
                Información del Sistema
            </h3>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label-modern">Referencia del Cliente</label>
                    <input type="text" class="form-input-modern" 
                           value="<?php echo htmlspecialchars($client['reference']); ?>" 
                           readonly disabled>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Fecha de Registro</label>
                    <input type="text" class="form-input-modern" 
                           value="<?php echo date('d/m/Y H:i', strtotime($client['created_at'])); ?>" 
                           readonly disabled>
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Última Modificación</label>
                    <input type="text" class="form-input-modern" 
                           value="<?php echo $client['updated_at'] ? date('d/m/Y H:i', strtotime($client['updated_at'])) : 'Nunca'; ?>" 
                           readonly disabled>
                </div>

                <?php if ($client['last_contact_date']): ?>
                <div class="col-md-6">
                    <label class="form-label-modern">Última Fecha de Contacto</label>
                    <input type="text" class="form-input-modern" 
                           value="<?php echo date('d/m/Y H:i', strtotime($client['last_contact_date'])); ?>" 
                           readonly disabled>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <label class="form-label-modern">Estado Actual</label>
                    <span class="status-badge <?php echo $client['is_active'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo $client['is_active'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="form-actions-modern">
            <div class="form-actions-left">
                <a href="ver-cliente.php?id=<?php echo $client['id']; ?>" class="btn-modern btn-info-modern">
                    <i class="fas fa-eye"></i>
                    Ver Detalle Completo
                </a>
            </div>
            <div class="form-actions-right">
                <a href="clientes.php" class="btn-modern btn-secondary-modern">
                    <i class="fas fa-times"></i>
                    Cancelar
                </a>
                <button type="submit" class="btn-modern btn-success-modern" id="btnSubmit">
                    <i class="fas fa-save"></i>
                    Guardar Cambios
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Toggle password section
function togglePortalPassword() {
    const checkbox = document.getElementById('portalActive');
    const section = document.getElementById('portalPasswordSection');
    
    if (checkbox.checked) {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
        document.getElementById('portalPassword').value = '';
        document.getElementById('portalPasswordConfirm').value = '';
    }
}

// Form submission
document.getElementById('clientForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('btnSubmit');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    // Validate passwords if provided
    const password = document.getElementById('portalPassword').value;
    const confirm = document.getElementById('portalPasswordConfirm').value;
    
    if (password || confirm) {
        if (password !== confirm) {
            alert('Las contraseñas no coinciden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
            return;
        }
        
        if (password.length < 8) {
            alert('La contraseña debe tener al menos 8 caracteres');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
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
            alert('✅ Cliente actualizado exitosamente');
            window.location.href = 'ver-cliente.php?id=<?php echo $client['id']; ?>';
        } else {
            alert('❌ Error: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al actualizar el cliente');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
    });
});
</script>

<?php include 'footer.php'; ?>
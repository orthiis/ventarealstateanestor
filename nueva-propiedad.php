<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Añadir Nueva Propiedad';
$currentUser = getCurrentUser();

// Obtener datos para los selectores
$propertyTypes = db()->select("SELECT * FROM property_types WHERE is_active = 1 ORDER BY display_order");
$features = db()->select("SELECT * FROM features WHERE is_active = 1 ORDER BY category, display_order");
$agents = db()->select("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role_id IN (2,3) AND status = 'active'");
$offices = db()->select("SELECT * FROM offices WHERE is_active = 1");

// Agrupar características por categoría
$featuresByCategory = [];
foreach ($features as $feature) {
    $category = $feature['category'] ?? 'Otras';
    $featuresByCategory[$category][] = $feature;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();
        
        // Datos básicos
        $propertyData = [
            'reference' => sanitize($_POST['reference']),
            'title' => sanitize($_POST['title']),
            'description' => $_POST['description'],
            'property_type_id' => (int)$_POST['property_type_id'],
            'operation_type' => sanitize($_POST['operation_type']),
            'status' => sanitize($_POST['status']),
            'price' => (float)$_POST['price'],
            'currency' => sanitize($_POST['currency']),
            'country' => sanitize($_POST['country']),
            'state_province' => sanitize($_POST['state_province'] ?? ''),
            'city' => sanitize($_POST['city']),
            'zone' => sanitize($_POST['zone'] ?? ''),
            'postal_code' => sanitize($_POST['postal_code'] ?? ''),
            'address' => sanitize($_POST['address']),
            'latitude' => (float)($_POST['latitude'] ?? 0),
            'longitude' => (float)($_POST['longitude'] ?? 0),
            'bedrooms' => (int)($_POST['bedrooms'] ?? 0),
            'bathrooms' => (float)($_POST['bathrooms'] ?? 0),
            'garage' => isset($_POST['garage']) ? 1 : 0,
            'garage_spaces' => (int)($_POST['garage_spaces'] ?? 0),
            'elevator' => isset($_POST['elevator']) ? 1 : 0,
            'floor_number' => (int)($_POST['floor_number'] ?? null),
            'total_floors' => (int)($_POST['total_floors'] ?? null),
            'useful_area' => (float)($_POST['useful_area'] ?? null),
            'built_area' => (float)($_POST['built_area'] ?? null),
            'plot_area' => (float)($_POST['plot_area'] ?? null),
            'year_built' => (int)($_POST['year_built'] ?? null),
            'orientation' => sanitize($_POST['orientation'] ?? null),
            'conservation_status' => sanitize($_POST['conservation_status'] ?? null),
            'energy_certificate' => sanitize($_POST['energy_certificate'] ?? null),
            'furnished' => isset($_POST['furnished']) ? 1 : 0,
            'agent_id' => (int)$_POST['agent_id'],
            'office_id' => (int)($_POST['office_id'] ?? null),
            'publish_on_website' => isset($_POST['publish_on_website']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'video_url' => sanitize($_POST['video_url'] ?? null),
            'virtual_tour_url' => sanitize($_POST['virtual_tour_url'] ?? null),
            'internal_notes' => sanitize($_POST['internal_notes'] ?? null),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $propertyId = db()->insert('properties', $propertyData);
        
        // Guardar características
        if (!empty($_POST['features'])) {
            foreach ($_POST['features'] as $featureId) {
                db()->insert('property_features', [
                    'property_id' => $propertyId,
                    'feature_id' => (int)$featureId
                ]);
            }
        }
        
        // Guardar imágenes
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp_name) {
                if (is_uploaded_file($tmp_name)) {
                    $upload = uploadFile($tmp_name, $_FILES['images']['name'][$i], PROPERTY_IMAGES_PATH);
                    if ($upload) {
                        db()->insert('property_images', [
                            'property_id' => $propertyId,
                            'image_url' => PROPERTY_IMAGES_URL . $upload['filename'],
                            'image_path' => $upload['filepath'],
                            'is_main' => ($i === 0) ? 1 : 0,
                            'display_order' => $i
                        ]);
                    }
                }
            }
        }
        
        logActivity('create', 'property', $propertyId, "Propiedad {$propertyData['reference']} creada");
        
        db()->commit();
        
        setFlashMessage('success', 'Propiedad creada exitosamente');
        redirect('propiedades.php');
        
    } catch (Exception $e) {
        db()->rollback();
        $error = DEBUG_MODE ? $e->getMessage() : 'Error al crear la propiedad';
        setFlashMessage('error', $error);
    }
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    :root {
        --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .property-form-container {
        padding: 30px;
        background: #f8f9fa;
    }

    .page-header-modern {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    /* Modern Tabs */
    .nav-tabs-modern {
        background: white;
        border-radius: 16px;
        padding: 15px 20px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        border: none;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .nav-tabs-modern .nav-item {
        margin: 0;
    }

    .nav-tabs-modern .nav-link {
        border: 2px solid transparent;
        border-radius: 10px;
        padding: 12px 24px;
        font-weight: 600;
        color: #718096;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }

    .nav-tabs-modern .nav-link:hover {
        background: #f3f4f6;
        color: #667eea;
    }

    .nav-tabs-modern .nav-link.active {
        background: var(--gradient-primary);
        color: white;
        border-color: transparent;
    }

    .nav-tabs-modern .nav-link.has-error::after {
        content: '';
        position: absolute;
        top: 8px;
        right: 8px;
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        animation: pulse 1s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Form Card */
    .form-card {
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }

    .form-section-title {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f3f5;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-section-title i {
        color: #667eea;
    }

    /* Modern Form Controls */
    .form-label {
        font-weight: 600;
        color: #4a5568;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 14px;
        transition: all 0.3s;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-control.is-invalid, .form-select.is-invalid {
        border-color: #ef4444;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 16px;
        padding-right: 40px;
    }

    .invalid-feedback {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: #ef4444;
        font-weight: 500;
    }

    /* Checkbox & Radio */
    .form-check {
        padding: 12px;
        border-radius: 10px;
        transition: all 0.2s;
    }

    .form-check:hover {
        background: #f9fafb;
    }

    .form-check-input {
        width: 20px;
        height: 20px;
        border: 2px solid #d1d5db;
        cursor: pointer;
    }

    .form-check-input:checked {
        background-color: #667eea;
        border-color: #667eea;
    }

    /* Image Upload */
    .image-dropzone {
        border: 2px dashed #d1d5db;
        border-radius: 16px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: #fafbfc;
    }

    .image-dropzone:hover {
        border-color: #667eea;
        background: #f7fafc;
    }

    .image-dropzone.dragover {
        border-color: #667eea;
        background: #ede9fe;
        transform: scale(1.02);
    }

    .image-preview {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }

    .image-preview-item {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        aspect-ratio: 4/3;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }

    .image-preview-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }

    .image-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-preview-item .remove-image {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #ef4444;
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        opacity: 0;
    }

    .image-preview-item:hover .remove-image {
        opacity: 1;
    }

    .image-preview-item .remove-image:hover {
        background: #dc2626;
        transform: scale(1.1);
    }

    .main-image-badge {
        position: absolute;
        top: 8px;
        left: 8px;
        background: #667eea;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    /* Map Styles */
    #map {
        z-index: 1;
        position: relative;
    }

    .leaflet-container {
        font-family: inherit;
        border-radius: 12px;
    }

    .leaflet-popup-content-wrapper {
        border-radius: 8px;
    }

    /* Action Buttons */
    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        padding-top: 20px;
        border-top: 2px solid #f1f3f5;
        margin-top: 30px;
    }

    .btn-modern {
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary-modern {
        background: var(--gradient-primary);
        color: white;
    }

    .btn-primary-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary-modern {
        background: #f3f4f6;
        color: #4a5568;
    }

    .btn-secondary-modern:hover {
        background: #e5e7eb;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .nav-tabs-modern {
            overflow-x: auto;
        }
    }
</style>

<div class="property-form-container">
    <!-- Page Header -->
    <div class="page-header-modern">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 style="font-size: 24px; font-weight: 700; color: #2d3748; margin: 0;">
                    <i class="fas fa-plus-circle" style="color: #667eea;"></i> Nueva Propiedad
                </h1>
                <p style="color: #718096; font-size: 14px; margin: 5px 0 0;">
                    Completa todos los campos requeridos para crear la propiedad
                </p>
            </div>
            <button type="button" class="btn-modern btn-secondary-modern" onclick="window.location.href='propiedades.php'">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="propertyForm">
        <!-- Modern Tabs -->
        <ul class="nav nav-tabs-modern" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="basicInfo-tab" data-bs-toggle="tab" href="#basicInfo" role="tab">
                    <i class="fas fa-info-circle"></i> Datos Básicos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="location-tab" data-bs-toggle="tab" href="#location" role="tab">
                    <i class="fas fa-map-marker-alt"></i> Ubicación
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="details-tab" data-bs-toggle="tab" href="#details" role="tab">
                    <i class="fas fa-list"></i> Detalles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="features-tab" data-bs-toggle="tab" href="#features" role="tab">
                    <i class="fas fa-star"></i> Características
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="media-tab" data-bs-toggle="tab" href="#media" role="tab">
                    <i class="fas fa-images"></i> Multimedia
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="publication-tab" data-bs-toggle="tab" href="#publication" role="tab">
                    <i class="fas fa-globe"></i> Publicación
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Datos Básicos -->
            <div class="tab-pane fade show active" id="basicInfo" role="tabpanel">
                <div class="form-card">
                    <h3 class="form-section-title">
                        <i class="fas fa-info-circle"></i> Información Básica
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Referencia <span class="text-danger">*</span></label>
                            <input type="text" name="reference" class="form-control" required 
                                   placeholder="ej: #E11" value="<?php echo '#' . strtoupper(uniqid()); ?>">
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Inmueble <span class="text-danger">*</span></label>
                            <select name="property_type_id" class="form-select" required>
                                <option value="">Seleccione el tipo</option>
                                <?php foreach ($propertyTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un tipo de propiedad</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Operación <span class="text-danger">*</span></label>
                            <select name="operation_type" class="form-select" required>
                                <option value="">Seleccione la operación</option>
                                <option value="sale">Venta</option>
                                <option value="rent">Alquiler</option>
                                <option value="vacation_rent">Alquiler Vacacional</option>
                            </select>
                            <div class="invalid-feedback">Seleccione el tipo de operación</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Estado <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="available" selected>Disponible</option>
                                <option value="reserved">Reservado</option>
                                <option value="rented">Alquilado</option>
                                <option value="sold">Vendido</option>
                                <option value="draft">Borrador</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Precio <span class="text-danger">*</span></label>
                            <input type="number" name="price" class="form-control" required step="0.01" min="0"
                                   placeholder="0.00">
                            <div class="invalid-feedback">Ingrese un precio válido</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Moneda</label>
                            <select name="currency" class="form-select">
                                <option value="USD" selected>USD</option>
                                <option value="EUR">EUR</option>
                                <option value="DOP">DOP</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Título <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required 
                                   placeholder="ej: Apartamento moderno en el centro" maxlength="255">
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Descripción <span class="text-danger">*</span></label>
                            <textarea name="description" id="editor" class="form-control" rows="8" required 
                                      placeholder="Describe las características principales de la propiedad..."></textarea>
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Agente Responsable <span class="text-danger">*</span></label>
                            <select name="agent_id" class="form-select" required>
                                <option value="">Seleccione un agente</option>
                                <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>" <?php echo $agent['id'] == $currentUser['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($agent['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Seleccione un agente</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ubicación -->
            <div class="tab-pane fade" id="location" role="tabpanel">
                <div class="form-card">
                    <h3 class="form-section-title">
                        <i class="fas fa-map-marker-alt"></i> Ubicación de la Propiedad
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">País <span class="text-danger">*</span></label>
                            <input type="text" name="country" class="form-control" required 
                                   placeholder="República Dominicana" value="República Dominicana">
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Provincia/Estado</label>
                            <input type="text" name="state_province" class="form-control" 
                                   placeholder="Santo Domingo">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" name="city" class="form-control" required 
                                   placeholder="Santo Domingo">
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Zona</label>
                            <input type="text" name="zone" class="form-control" 
                                   placeholder="Piantini">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Código Postal</label>
                            <input type="text" name="postal_code" class="form-control" 
                                   placeholder="10001">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Dirección Completa <span class="text-danger">*</span></label>
                            <input type="text" name="address" id="address" class="form-control" required 
                                   placeholder="Calle Principal #123, Edificio Torre A">
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Ubicación en el Mapa</label>
                            <div id="map" style="height: 400px; border-radius: 12px; border: 2px solid #e5e7eb;"></div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Haz clic en el mapa para marcar la ubicación exacta o arrastra el marcador
                            </small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Latitud</label>
                            <input type="number" name="latitude" id="latitude" class="form-control" step="0.0000001" 
                                   placeholder="18.4861" readonly>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Longitud</label>
                            <input type="number" name="longitude" id="longitude" class="form-control" step="0.0000001" 
                                   placeholder="-69.9312" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles -->
            <div class="tab-pane fade" id="details" role="tabpanel">
                <div class="form-card">
                    <h3 class="form-section-title">
                        <i class="fas fa-list"></i> Detalles de la Propiedad
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Habitaciones <span class="text-danger">*</span></label>
                            <input type="number" name="bedrooms" class="form-control" required min="0" value="0">
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Baños <span class="text-danger">*</span></label>
                            <input type="number" name="bathrooms" class="form-control" required step="0.5" min="0" value="0">
                            <div class="invalid-feedback">Este campo es requerido</div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Plazas de Garaje</label>
                            <input type="number" name="garage_spaces" class="form-control" min="0" value="0">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Piso/Planta</label>
                            <input type="number" name="floor_number" class="form-control" min="0">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Superficie Útil (m²)</label>
                            <input type="number" name="useful_area" class="form-control" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Superficie Construida (m²)</label>
                            <input type="number" name="built_area" class="form-control" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Superficie de Parcela (m²)</label>
                            <input type="number" name="plot_area" class="form-control" step="0.01" min="0">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Año de Construcción</label>
                            <input type="number" name="year_built" class="form-control" min="1900" max="<?php echo date('Y'); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Orientación</label>
                            <select name="orientation" class="form-select">
                                <option value="">Seleccione</option>
                                <option value="norte">Norte</option>
                                <option value="sur">Sur</option>
                                <option value="este">Este</option>
                                <option value="oeste">Oeste</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Estado de Conservación</label>
                            <select name="conservation_status" class="form-select">
                                <option value="">Seleccione</option>
                                <option value="new">Nuevo</option>
                                <option value="good">Buen estado</option>
                                <option value="to_reform">A reformar</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Certificado Energético</label>
                            <select name="energy_certificate" class="form-select">
                                <option value="">No especificado</option>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                                <option value="E">E</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-flex gap-4 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="garage" id="garage">
                                    <label class="form-check-label" for="garage">
                                        <i class="fas fa-car me-2"></i> Tiene Garaje
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="elevator" id="elevator">
                                    <label class="form-check-label" for="elevator">
                                        <i class="fas fa-arrow-up me-2"></i> Tiene Ascensor
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="furnished" id="furnished">
                                    <label class="form-check-label" for="furnished">
                                        <i class="fas fa-couch me-2"></i> Amueblado
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Características -->
            <div class="tab-pane fade" id="features" role="tabpanel">
                <div class="form-card">
                    <h3 class="form-section-title">
                        <i class="fas fa-star"></i> Características Adicionales
                    </h3>
                    
                    <?php if(empty($featuresByCategory)): ?>
                    <p class="text-muted">No hay características disponibles. Por favor, añade características desde el panel de administración.</p>
                    <?php else: ?>
                    <?php foreach ($featuresByCategory as $category => $categoryFeatures): ?>
                    <div class="mb-4">
                        <h6 style="color: #667eea; font-weight: 600; margin-bottom: 15px;">
                            <?php echo htmlspecialchars($category); ?>
                        </h6>
                        <div class="row g-2">
                            <?php foreach ($categoryFeatures as $feature): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" 
                                           value="<?php echo $feature['id']; ?>" 
                                           id="feature-<?php echo $feature['id']; ?>">
                                    <label class="form-check-label" for="feature-<?php echo $feature['id']; ?>">
                                        <?php if($feature['icon']): ?>
                                        <i class="<?php echo $feature['icon']; ?> me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($feature['name']); ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Multimedia -->
            <div class="tab-pane fade" id="media" role="tabpanel">
                <div class="form-card">
                    <h3 class="form-section-title">
                        <i class="fas fa-images"></i> Imágenes y Multimedia
                    </h3>
                    
                    <div class="mb-4">
                        <label class="form-label">Imágenes de la Propiedad</label>
                        <div class="image-dropzone" id="imageDropzone">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #d1d5db; margin-bottom: 15px;"></i>
                            <h5 style="color: #4a5568; margin-bottom: 10px;">Arrastra imágenes aquí o haz clic para seleccionar</h5>
                            <p style="color: #9ca3af; font-size: 14px; margin: 0;">Soporta: JPG, PNG, WEBP (max 10MB cada una)</p>
                        </div>
                        <input type="file" name="images[]" id="imageInput" multiple accept="image/*" style="display: none;">
                    </div>

                    <div id="imagePreview" class="image-preview"></div>

                    <div class="row g-3 mt-4">
                        <div class="col-md-6">
                            <label class="form-label">URL de Video (YouTube, Vimeo)</label>
                            <input type="url" name="video_url" class="form-control" 
                                   placeholder="https://youtube.com/watch?v=...">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">URL de Tour Virtual</label>
                            <input type="url" name="virtual_tour_url" class="form-control" 
                                   placeholder="https://...">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Publicación -->
            <div class="tab-pane fade" id="publication" role="tabpanel">
                <div class="form-card">
                    <h3 class="form-section-title">
                        <i class="fas fa-globe"></i> Opciones de Publicación
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check" style="padding: 20px; background: #f7fafc; border-radius: 12px;">
                                <input class="form-check-input" type="checkbox" name="publish_on_website" id="publish_on_website" checked>
                                <label class="form-check-label" for="publish_on_website">
                                    <div style="font-weight: 600; color: #2d3748;">Publicar en el sitio web</div>
                                    <small style="color: #718096;">La propiedad será visible en tu sitio web público</small>
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check" style="padding: 20px; background: #f7fafc; border-radius: 12px;">
                                <input class="form-check-input" type="checkbox" name="featured" id="featured">
                                <label class="form-check-label" for="featured">
                                    <div style="font-weight: 600; color: #2d3748;">Destacar propiedad</div>
                                    <small style="color: #718096;">Aparecerá en la sección de propiedades destacadas</small>
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notas Internas</label>
                            <textarea name="internal_notes" class="form-control" rows="4" 
                                      placeholder="Notas privadas que solo verá el equipo interno..."></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Oficina</label>
                            <select name="office_id" class="form-select">
                                <option value="">Sin asignar</option>
                                <?php foreach ($offices as $office): ?>
                                <option value="<?php echo $office['id']; ?>"><?php echo htmlspecialchars($office['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn-modern btn-secondary-modern" onclick="window.location.href='propiedades.php'">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" class="btn-modern btn-primary-modern">
                <i class="fas fa-save"></i> Guardar Propiedad
            </button>
        </div>
    </form>
</div>

<!-- CKEditor -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<script>
// ============ CKEDITOR ============
let editorInstance;
ClassicEditor
    .create(document.querySelector('#editor'), {
        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo']
    })
    .then(editor => {
        editorInstance = editor;
    })
    .catch(error => {
        console.error(error);
    });

// ============ VALIDACIÓN DEL FORMULARIO ============
document.getElementById('propertyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Guardar contenido del editor
    if (editorInstance) {
        document.querySelector('#editor').value = editorInstance.getData();
    }
    
    // Validar campos
    const tabs = ['basicInfo', 'location', 'details', 'features', 'media', 'publication'];
    let firstErrorTab = null;
    
    // Remover clase de error de tabs
    document.querySelectorAll('.nav-link').forEach(tab => {
        tab.classList.remove('has-error');
    });
    
    // Verificar campos requeridos por pestaña
    tabs.forEach(tabId => {
        const tabPane = document.getElementById(tabId);
        const requiredFields = tabPane.querySelectorAll('[required]');
        let hasError = false;
        
        requiredFields.forEach(field => {
            if (!field.value || (field.type === 'checkbox' && !field.checked && field.hasAttribute('required'))) {
                hasError = true;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (hasError && !firstErrorTab) {
            firstErrorTab = tabId;
            // Marcar tab con error
            document.querySelector(`a[href="#${tabId}"]`).classList.add('has-error');
        }
    });
    
    // Si hay errores, ir a la primera pestaña con error
    if (firstErrorTab) {
        const tab = new bootstrap.Tab(document.querySelector(`a[href="#${firstErrorTab}"]`));
        tab.show();
        
        // Scroll al primer campo con error
        setTimeout(() => {
            const firstError = document.getElementById(firstErrorTab).querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }, 100);
        
        Swal.fire({
            icon: 'warning',
            title: 'Campos incompletos',
            text: 'Por favor completa todos los campos requeridos marcados en rojo',
            confirmButtonColor: '#667eea'
        });
        
        return false;
    }
    
    // Si todo está bien, enviar formulario
    this.submit();
});

// ============ IMAGE DROPZONE ============
const dropzone = document.getElementById('imageDropzone');
const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');

dropzone.addEventListener('click', () => imageInput.click());

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    const dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(file => dt.items.add(file));
    imageInput.files = dt.files;
    handleFiles(dt.files);
});

imageInput.addEventListener('change', (e) => {
    handleFiles(e.target.files);
});

function handleFiles(files) {
    imagePreview.innerHTML = '';
    Array.from(files).forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'image-preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    ${index === 0 ? '<div class="main-image-badge"><i class="fas fa-star me-1"></i>Principal</div>' : ''}
                    <button type="button" class="remove-image" onclick="removeImage(this, ${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                imagePreview.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    });
}

function removeImage(btn, index) {
    btn.closest('.image-preview-item').remove();
    const dt = new DataTransfer();
    const files = Array.from(imageInput.files);
    files.splice(index, 1);
    files.forEach(file => dt.items.add(file));
    imageInput.files = dt.files;
}

// ============ MAPA INTERACTIVO CON LEAFLET ============
let map, marker;

function initMap() {
    // Coordenadas de Santo Domingo por defecto
    const defaultLat = 18.4861;
    const defaultLng = -69.9312;
    
    // Crear mapa con Leaflet (gratuito)
    map = L.map('map').setView([defaultLat, defaultLng], 13);
    
    // Añadir capa de OpenStreetMap (gratuito)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Añadir marcador arrastrable
    marker = L.marker([defaultLat, defaultLng], {
        draggable: true
    }).addTo(map);
    
    // Actualizar coordenadas cuando se arrastra el marcador
    marker.on('dragend', function(e) {
        const position = marker.getLatLng();
        document.getElementById('latitude').value = position.lat.toFixed(7);
        document.getElementById('longitude').value = position.lng.toFixed(7);
    });
    
    // Añadir marcador al hacer clic en el mapa
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        document.getElementById('latitude').value = e.latlng.lat.toFixed(7);
        document.getElementById('longitude').value = e.latlng.lng.toFixed(7);
    });
    
    // Establecer coordenadas iniciales
    document.getElementById('latitude').value = defaultLat;
    document.getElementById('longitude').value = defaultLng;
}

// Cargar Leaflet CSS
const leafletCSS = document.createElement('link');
leafletCSS.rel = 'stylesheet';
leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
leafletCSS.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
leafletCSS.crossOrigin = '';
document.head.appendChild(leafletCSS);

// Cargar Leaflet JS
const leafletJS = document.createElement('script');
leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
leafletJS.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
leafletJS.crossOrigin = '';
leafletJS.onload = function() {
    // Inicializar el mapa cuando se cargue Leaflet y el tab de ubicación esté visible
    const locationTab = document.querySelector('a[href="#location"]');
    locationTab.addEventListener('shown.bs.tab', function() {
        setTimeout(() => {
            if (!map) {
                initMap();
            } else {
                map.invalidateSize();
            }
        }, 100);
    });
};
document.head.appendChild(leafletJS);

// Buscar dirección en el mapa (geocoding)
document.getElementById('address')?.addEventListener('blur', function() {
    const address = this.value;
    if (address.length > 5 && map && marker) {
        const query = encodeURIComponent(address);
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}&limit=1`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    
                    map.setView([lat, lon], 16);
                    marker.setLatLng([lat, lon]);
                    document.getElementById('latitude').value = lat.toFixed(7);
                    document.getElementById('longitude').value = lon.toFixed(7);
                }
            })
            .catch(err => console.log('Geocoding error:', err));
    }
});

// ============ VALIDACIÓN EN TIEMPO REAL ============
document.querySelectorAll('[required]').forEach(field => {
    field.addEventListener('blur', function() {
        if (!this.value) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    field.addEventListener('input', function() {
        if (this.value) {
            this.classList.remove('is-invalid');
        }
    });
});
</script>

<?php include 'footer.php'; ?>
<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('properties.edit', [], 'Editar Propiedad');
$currentUser = getCurrentUser();

// Obtener ID de la propiedad
$propertyId = $_GET['id'] ?? null;

if (!$propertyId) {
    setFlashMessage('error', __('properties.not_found', [], 'Propiedad no encontrada'));
    redirect('propiedades.php');
}

// Obtener datos de la propiedad
$property = db()->selectOne("SELECT * FROM properties WHERE id = ?", [$propertyId]);

if (!$property) {
    setFlashMessage('error', __('properties.not_found', [], 'Propiedad no encontrada'));
    redirect('propiedades.php');
}

// Verificar permisos
if ($currentUser['role']['name'] !== 'administrador' && $property['agent_id'] != $currentUser['id']) {
    setFlashMessage('error', __('no_permission', [], 'No tienes permisos para editar esta propiedad'));
    redirect('propiedades.php');
}

// Obtener imágenes de la propiedad
$propertyImages = db()->select("SELECT * FROM property_images WHERE property_id = ? ORDER BY display_order", [$propertyId]);

// Obtener características de la propiedad
$propertyFeatures = db()->select("SELECT feature_id FROM property_features WHERE property_id = ?", [$propertyId]);
$selectedFeatures = array_column($propertyFeatures, 'feature_id');

// Obtener datos para los selectores
$propertyTypes = db()->select("SELECT * FROM property_types WHERE is_active = 1 ORDER BY display_order");
$features = db()->select("SELECT * FROM features WHERE is_active = 1 ORDER BY category, display_order");
$agents = db()->select("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role_id IN (2,3) AND status = 'active'");
$offices = db()->select("SELECT * FROM offices WHERE is_active = 1");

// Agrupar características por categoría
$featuresByCategory = [];
foreach ($features as $feature) {
    $category = $feature['category'] ?? __('others', [], 'Otras');
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
            'office_id' => !empty($_POST['office_id']) ? (int)$_POST['office_id'] : null,
            'publish_on_website' => isset($_POST['publish_on_website']) ? 1 : 0,
            'publish_on_homepage' => isset($_POST['publish_on_homepage']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'video_url' => sanitize($_POST['video_url'] ?? ''),
            'virtual_tour_url' => sanitize($_POST['virtual_tour_url'] ?? ''),
            'internal_notes' => sanitize($_POST['internal_notes'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Actualizar propiedad
        db()->update('properties', $propertyData, 'id = :id', ['id' => $propertyId]);
        
        // Actualizar características
        db()->delete('property_features', 'property_id = ?', [$propertyId]);
        if (!empty($_POST['features'])) {
            foreach ($_POST['features'] as $featureId) {
                db()->insert('property_features', [
                    'property_id' => $propertyId,
                    'feature_id' => (int)$featureId
                ]);
            }
        }
        
        // Procesar nuevas imágenes
        if (!empty($_FILES['images']['name'][0])) {
            $filesCount = count($_FILES['images']['name']);
            $lastOrder = count($propertyImages);
            
            for ($i = 0; $i < $filesCount; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['images']['name'][$i],
                        'type' => $_FILES['images']['type'][$i],
                        'tmp_name' => $_FILES['images']['tmp_name'][$i],
                        'error' => $_FILES['images']['error'][$i],
                        'size' => $_FILES['images']['size'][$i]
                    ];
                    
                    $upload = uploadFile($file, PROPERTY_IMAGES_PATH, ALLOWED_IMAGE_TYPES);
                    
                    if ($upload['success']) {
                        $imageData = [
                            'property_id' => $propertyId,
                            'image_url' => PROPERTY_IMAGES_URL . $upload['filename'],
                            'image_path' => $upload['filepath'],
                            'is_main' => 0,
                            'display_order' => $lastOrder + $i
                        ];
                        db()->insert('property_images', $imageData);
                    }
                }
            }
        }
        
        logActivity('update', 'property', $propertyId, __('properties.updated_log', ['reference' => $propertyData['reference']], "Propiedad {$propertyData['reference']} actualizada"));
        
        db()->commit();
        
        setFlashMessage('success', __('properties.update_success', [], 'Propiedad actualizada exitosamente'));
        redirect('propiedades.php');
        
    } catch (Exception $e) {
        db()->rollback();
        $error = DEBUG_MODE ? $e->getMessage() : __('properties.update_error', [], 'Error al actualizar la propiedad');
        setFlashMessage('error', $error);
    }
}

include 'header.php';
include 'sidebar.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0"><?php echo __('properties.edit', [], 'Editar Propiedad'); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="dashboard.php"><?php echo __('home', [], 'Inicio'); ?></a></li>
                    <li class="breadcrumb-item"><a href="propiedades.php"><?php echo __('properties.title', [], 'Propiedades'); ?></a></li>
                    <li class="breadcrumb-item active"><?php echo $property['reference']; ?></li>
                </ol>
            </nav>
        </div>
        <div>
            <span class="badge bg-<?php 
                echo $property['status'] === 'available' ? 'success' : 
                    ($property['status'] === 'sold' ? 'secondary' : 
                    ($property['status'] === 'rented' ? 'info' : 'warning')); 
            ?> fs-6">
                <?php echo __('properties.status_' . $property['status'], [], ucfirst($property['status'])); ?>
            </span>
        </div>
    </div>

    <?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="propertyForm">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-4 sticky-top bg-white" style="top: 64px; z-index: 50;">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#basicInfo">
                    <i class="fas fa-info-circle me-2"></i> <?php echo __('properties.basic_info', [], 'Basic Info'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#location">
                    <i class="fas fa-map-marker-alt me-2"></i> <?php echo __('properties.location', [], 'Location'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#details">
                    <i class="fas fa-list me-2"></i> <?php echo __('properties.details', [], 'Details'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#features">
                    <i class="fas fa-star me-2"></i> <?php echo __('properties.features', [], 'Features'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#media">
                    <i class="fas fa-images me-2"></i> <?php echo __('properties.multimedia', [], 'Multimedia'); ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#publication">
                    <i class="fas fa-globe me-2"></i> <?php echo __('properties.publication', [], 'Publication'); ?>
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Datos Básicos -->
            <div class="tab-pane fade show active" id="basicInfo">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <?php echo __('properties.basic_info', [], 'Información Básica'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('properties.reference', [], 'Referencia'); ?> <span class="text-danger">*</span></label>
                                <input type="text" name="reference" class="form-control" required 
                                       value="<?php echo htmlspecialchars($property['reference']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('properties.property_type', [], 'Tipo de Inmueble'); ?> <span class="text-danger">*</span></label>
                                <select name="property_type_id" class="form-select" required>
                                    <option value=""><?php echo __('select_type', [], 'Seleccione el tipo'); ?></option>
                                    <?php foreach ($propertyTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            <?php echo $type['id'] == $property['property_type_id'] ? 'selected' : ''; ?>>
                                        <?php echo $type['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('properties.operation', [], 'Operación'); ?> <span class="text-danger">*</span></label>
                                <select name="operation_type" class="form-select" required>
                                    <option value=""><?php echo __('properties.select_operation', [], 'Seleccione la operación'); ?></option>
                                    <option value="sale" <?php echo $property['operation_type'] === 'sale' ? 'selected' : ''; ?>><?php echo __('properties.sale', [], 'Venta'); ?></option>
                                    <option value="rent" <?php echo $property['operation_type'] === 'rent' ? 'selected' : ''; ?>><?php echo __('properties.rent', [], 'Alquiler'); ?></option>
                                    <option value="vacation_rent" <?php echo $property['operation_type'] === 'vacation_rent' ? 'selected' : ''; ?>><?php echo __('properties.vacation_rent', [], 'Alquiler Vacacional'); ?></option>
                                    <option value="transfer" <?php echo $property['operation_type'] === 'transfer' ? 'selected' : ''; ?>><?php echo __('properties.transfer', [], 'Traspaso'); ?></option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('status', [], 'Estado'); ?> <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="draft" <?php echo $property['status'] === 'draft' ? 'selected' : ''; ?>><?php echo __('properties.status_draft', [], 'Borrador'); ?></option>
                                    <option value="available" <?php echo $property['status'] === 'available' ? 'selected' : ''; ?>><?php echo __('properties.status_available', [], 'Disponible'); ?></option>
                                    <option value="reserved" <?php echo $property['status'] === 'reserved' ? 'selected' : ''; ?>><?php echo __('properties.status_reserved', [], 'Reservado'); ?></option>
                                    <option value="rented" <?php echo $property['status'] === 'rented' ? 'selected' : ''; ?>><?php echo __('properties.status_rented', [], 'Alquilado'); ?></option>
                                    <option value="sold" <?php echo $property['status'] === 'sold' ? 'selected' : ''; ?>><?php echo __('properties.status_sold', [], 'Vendido'); ?></option>
                                    <option value="retired" <?php echo $property['status'] === 'retired' ? 'selected' : ''; ?>><?php echo __('properties.status_retired', [], 'Retirado'); ?></option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold"><?php echo __('properties.title', [], 'Título'); ?> <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" required 
                                       value="<?php echo htmlspecialchars($property['title']); ?>"
                                       placeholder="<?php echo __('properties.title_placeholder', [], 'ej: Charming Beach House'); ?>" maxlength="255">
                                <small class="text-muted"><?php echo __('properties.title_hint', [], 'Máximo 70 caracteres recomendado para SEO'); ?></small>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold"><?php echo __('description', [], 'Descripción'); ?> <span class="text-danger">*</span></label>
                                <textarea name="description" id="editor" style="display: none;"><?php echo $property['description']; ?></textarea>
                                <div id="editor-container" style="min-height: 300px; border: 1px solid #E5E7EB; border-radius: 8px;"></div>
                                <small class="text-muted d-block mt-2"><?php echo __('properties.description_hint', [], 'Describe las características principales de la propiedad'); ?></small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"><?php echo __('price', [], 'Precio'); ?> <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select name="currency" class="form-select" style="max-width: 100px;">
                                        <option value="USD" <?php echo $property['currency'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                                        <option value="EUR" <?php echo $property['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                        <option value="DOP" <?php echo $property['currency'] === 'DOP' ? 'selected' : ''; ?>>DOP</option>
                                    </select>
                                    <input type="number" name="price" class="form-control" required 
                                           value="<?php echo $property['price']; ?>"
                                           placeholder="0.00" step="0.01" min="0">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"><?php echo __('properties.responsible_agent', [], 'Agente Responsable'); ?> <span class="text-danger">*</span></label>
                                <select name="agent_id" class="form-select" required>
                                    <option value=""><?php echo __('properties.select_agent', [], 'Seleccione agente'); ?></option>
                                    <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo $agent['id']; ?>" 
                                            <?php echo $agent['id'] == $property['agent_id'] ? 'selected' : ''; ?>>
                                        <?php echo $agent['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label fw-semibold"><?php echo __('office', [], 'Oficina'); ?></label>
                                <select name="office_id" class="form-select">
                                    <option value=""><?php echo __('properties.select_office', [], 'Seleccione oficina'); ?></option>
                                    <?php foreach ($offices as $office): ?>
                                    <option value="<?php echo $office['id']; ?>"
                                            <?php echo $office['id'] == $property['office_id'] ? 'selected' : ''; ?>>
                                        <?php echo $office['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ubicación -->
            <div class="tab-pane fade" id="location">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                            <?php echo __('properties.location_map', [], 'Ubicación y Mapa'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('country', [], 'País'); ?> <span class="text-danger">*</span></label>
                                <select name="country" class="form-select" required>
                                    <option value="República Dominicana" <?php echo $property['country'] === 'República Dominicana' ? 'selected' : ''; ?>><?php echo __('dominican_republic', [], 'República Dominicana'); ?></option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('state_province', [], 'Provincia/Estado'); ?></label>
                                <input type="text" name="state_province" class="form-control" 
                                       value="<?php echo htmlspecialchars($property['state_province']); ?>"
                                       placeholder="<?php echo __('properties.state_placeholder', [], 'ej: Nacional'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('city', [], 'Ciudad'); ?> <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required 
                                       value="<?php echo htmlspecialchars($property['city']); ?>"
                                       placeholder="<?php echo __('properties.city_placeholder', [], 'ej: Santo Domingo'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('zone', [], 'Zona'); ?></label>
                                <input type="text" name="zone" class="form-control" 
                                       value="<?php echo htmlspecialchars($property['zone']); ?>"
                                       placeholder="<?php echo __('properties.zone_placeholder', [], 'ej: Piantini'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('postal_code', [], 'Código Postal'); ?></label>
                                <input type="text" name="postal_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($property['postal_code']); ?>"
                                       placeholder="<?php echo __('properties.postal_placeholder', [], 'ej: 10135'); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('address', [], 'Dirección Completa'); ?> <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control" required 
                                       value="<?php echo htmlspecialchars($property['address']); ?>"
                                       placeholder="<?php echo __('properties.address_placeholder', [], 'ej: Calle Principal #123'); ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold"><?php echo __('properties.map_location', [], 'Ubicación en el Mapa'); ?></label>
                                <div id="map" style="height: 400px; border-radius: 12px;" class="border"></div>
                                <input type="hidden" name="latitude" id="latitude" value="<?php echo $property['latitude']; ?>">
                                <input type="hidden" name="longitude" id="longitude" value="<?php echo $property['longitude']; ?>">
                                <small class="text-muted d-block mt-2"><?php echo __('properties.map_hint', [], 'Haz clic en el mapa para marcar la ubicación exacta'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles -->
            <div class="tab-pane fade" id="details">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-list text-primary me-2"></i>
                            <?php echo __('properties.property_details', [], 'Detalles de la Propiedad'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-bed me-1"></i> <?php echo __('properties.bedrooms', [], 'Habitaciones'); ?>
                                </label>
                                <input type="number" name="bedrooms" class="form-control" min="0" 
                                       value="<?php echo $property['bedrooms']; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-bath me-1"></i> <?php echo __('properties.bathrooms', [], 'Baños'); ?>
                                </label>
                                <input type="number" name="bathrooms" class="form-control" min="0" step="0.5" 
                                       value="<?php echo $property['bathrooms']; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.useful_area', [], 'Superficie Útil'); ?> (m²)</label>
                                <input type="number" name="useful_area" class="form-control" min="0" step="0.01"
                                       value="<?php echo $property['useful_area']; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.built_area', [], 'Superficie Construida'); ?> (m²)</label>
                                <input type="number" name="built_area" class="form-control" min="0" step="0.01"
                                       value="<?php echo $property['built_area']; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.plot_area', [], 'Superficie Parcela'); ?> (m²)</label>
                                <input type="number" name="plot_area" class="form-control" min="0" step="0.01"
                                       value="<?php echo $property['plot_area']; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.floor', [], 'Planta'); ?></label>
                                <input type="number" name="floor_number" class="form-control"
                                       value="<?php echo $property['floor_number']; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.building_height', [], 'Altura del Edificio'); ?></label>
                                <input type="number" name="total_floors" class="form-control" min="1"
                                       value="<?php echo $property['total_floors']; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.year_built', [], 'Año de Construcción'); ?></label>
                                <input type="number" name="year_built" class="form-control" 
                                       value="<?php echo $property['year_built']; ?>"
                                       min="1900" max="<?php echo date('Y') + 5; ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.orientation', [], 'Orientación'); ?></label>
                                <select name="orientation" class="form-select">
                                    <option value=""><?php echo __('select', [], 'Seleccione'); ?></option>
                                    <option value="north" <?php echo $property['orientation'] === 'north' ? 'selected' : ''; ?>><?php echo __('properties.north', [], 'Norte'); ?></option>
                                    <option value="south" <?php echo $property['orientation'] === 'south' ? 'selected' : ''; ?>><?php echo __('properties.south', [], 'Sur'); ?></option>
                                    <option value="east" <?php echo $property['orientation'] === 'east' ? 'selected' : ''; ?>><?php echo __('properties.east', [], 'Este'); ?></option>
                                    <option value="west" <?php echo $property['orientation'] === 'west' ? 'selected' : ''; ?>><?php echo __('properties.west', [], 'Oeste'); ?></option>
                                    <option value="northeast" <?php echo $property['orientation'] === 'northeast' ? 'selected' : ''; ?>><?php echo __('properties.northeast', [], 'Noreste'); ?></option>
                                    <option value="northwest" <?php echo $property['orientation'] === 'northwest' ? 'selected' : ''; ?>><?php echo __('properties.northwest', [], 'Noroeste'); ?></option>
                                    <option value="southeast" <?php echo $property['orientation'] === 'southeast' ? 'selected' : ''; ?>><?php echo __('properties.southeast', [], 'Sureste'); ?></option>
                                    <option value="southwest" <?php echo $property['orientation'] === 'southwest' ? 'selected' : ''; ?>><?php echo __('properties.southwest', [], 'Suroeste'); ?></option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.conservation_status', [], 'Estado de Conservación'); ?></label>
                                <select name="conservation_status" class="form-select">
                                    <option value=""><?php echo __('select', [], 'Seleccione'); ?></option>
                                    <option value="Nuevo" <?php echo $property['conservation_status'] === 'Nuevo' ? 'selected' : ''; ?>><?php echo __('properties.new', [], 'Nuevo'); ?></option>
                                    <option value="Como nuevo" <?php echo $property['conservation_status'] === 'Como nuevo' ? 'selected' : ''; ?>><?php echo __('properties.like_new', [], 'Como nuevo'); ?></option>
                                    <option value="Buen estado" <?php echo $property['conservation_status'] === 'Buen estado' ? 'selected' : ''; ?>><?php echo __('properties.good_condition', [], 'Buen estado'); ?></option>
                                    <option value="A reformar" <?php echo $property['conservation_status'] === 'A reformar' ? 'selected' : ''; ?>><?php echo __('properties.to_renovate', [], 'A reformar'); ?></option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.energy_certificate', [], 'Certificado Energético'); ?></label>
                                <select name="energy_certificate" class="form-select">
                                    <option value=""><?php echo __('select', [], 'Seleccione'); ?></option>
                                    <?php for($i = ord('A'); $i <= ord('G'); $i++): ?>
                                    <option value="<?php echo chr($i); ?>" <?php echo $property['energy_certificate'] === chr($i) ? 'selected' : ''; ?>>
                                        <?php echo chr($i); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold"><?php echo __('properties.garage_spaces', [], 'Plazas de Garaje'); ?></label>
                                <input type="number" name="garage_spaces" class="form-control" min="0" 
                                       value="<?php echo $property['garage_spaces']; ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="garage" id="garage"
                                           <?php echo $property['garage'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="garage"><?php echo __('properties.has_garage', [], 'Tiene Garaje'); ?></label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="elevator" id="elevator"
                                           <?php echo $property['elevator'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="elevator"><?php echo __('properties.has_elevator', [], 'Tiene Ascensor'); ?></label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="furnished" id="furnished"
                                           <?php echo $property['furnished'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="furnished"><?php echo __('properties.furnished', [], 'Amueblado'); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Características -->
            <div class="tab-pane fade" id="features">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-star text-primary me-2"></i>
                            <?php echo __('properties.property_features', [], 'Características de la Propiedad'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <?php foreach ($featuresByCategory as $category => $categoryFeatures): ?>
                            <div class="col-md-6 col-lg-4">
                                <h6 class="fw-bold mb-3 pb-2 border-bottom"><?php echo $category; ?></h6>
                                <?php foreach ($categoryFeatures as $feature): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" 
                                           name="features[]" value="<?php echo $feature['id']; ?>" 
                                           id="feature_<?php echo $feature['id']; ?>"
                                           <?php echo in_array($feature['id'], $selectedFeatures) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feature_<?php echo $feature['id']; ?>">
                                        <?php echo $feature['name']; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multimedia -->
            <div class="tab-pane fade" id="media">
                <!-- Imágenes Actuales -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-images text-primary me-2"></i>
                            <?php echo __('properties.current_images', [], 'Imágenes Actuales'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($propertyImages)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-image fa-3x mb-3"></i>
                            <p><?php echo __('properties.no_images', [], 'No hay imágenes cargadas'); ?></p>
                        </div>
                        <?php else: ?>
                        <div class="row g-3" id="currentImagesGrid">
                            <?php foreach ($propertyImages as $image): ?>
                            <div class="col-md-3" id="image_<?php echo $image['id']; ?>">
                                <div class="position-relative">
                                    <img src="<?php echo $image['image_url']; ?>" 
                                         class="img-fluid rounded" 
                                         style="height: 200px; object-fit: cover; width: 100%;">
                                    
                                    <button type="button" 
                                            class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" 
                                            onclick="deleteImage(<?php echo $image['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    
                                    <?php if ($image['is_main']): ?>
                                    <span class="badge bg-primary position-absolute bottom-0 start-0 m-2"><?php echo __('properties.main_image', [], 'Principal'); ?></span>
                                    <?php else: ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-primary position-absolute bottom-0 start-0 m-2"
                                            onclick="setMainImage(<?php echo $image['id']; ?>)">
                                        <?php echo __('properties.set_as_main', [], 'Marcar como principal'); ?>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Nuevas Imágenes -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-upload text-primary me-2"></i>
                            <?php echo __('properties.add_new_images', [], 'Agregar Nuevas Imágenes'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="dropzone border-2 border-dashed rounded p-5 text-center" id="imageDropzone">
                            <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                            <p class="mb-2"><?php echo __('properties.drag_drop_images', [], 'Arrastra y suelta las imágenes aquí o'); ?> <strong><?php echo __('properties.click_select', [], 'haz clic para seleccionar'); ?></strong></p>
                            <small class="text-muted"><?php echo __('properties.image_requirements', [], 'JPG, PNG o WEBP. Máximo 10MB por imagen.'); ?></small>
                            <input type="file" name="images[]" multiple accept="image/*" class="d-none" id="imageInput">
                        </div>
                        <div id="imagePreview" class="row g-3 mt-3"></div>
                    </div>
                </div>

                <!-- Videos -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-video text-primary me-2"></i>
                            <?php echo __('properties.videos_tours', [], 'Videos y Tours Virtuales'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('properties.video_url', [], 'URL de Video (YouTube/Vimeo)'); ?></label>
                                <input type="url" name="video_url" class="form-control" 
                                       value="<?php echo htmlspecialchars($property['video_url']); ?>"
                                       placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-semibold"><?php echo __('properties.virtual_tour_url', [], 'URL de Tour Virtual 360°'); ?></label>
                                <input type="url" name="virtual_tour_url" class="form-control" 
                                       value="<?php echo htmlspecialchars($property['virtual_tour_url']); ?>"
                                       placeholder="https://...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Publicación -->
            <div class="tab-pane fade" id="publication">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-globe text-primary me-2"></i>
                            <?php echo __('properties.publication_settings', [], 'Configuración de Publicación'); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="publish_on_website" 
                                       id="publish_on_website"
                                       <?php echo $property['publish_on_website'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="publish_on_website">
                                    <?php echo __('properties.publish_on_website', [], 'Publicar en sitio web'); ?>
                                </label>
                            </div>
                            
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="publish_on_homepage" 
                                       id="publish_on_homepage"
                                       <?php echo $property['publish_on_homepage'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="publish_on_homepage">
                                    <?php echo __('properties.publish_on_homepage', [], 'Publicar en portada web'); ?>
                                </label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="featured" 
                                       id="featured"
                                       <?php echo $property['featured'] ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="featured">
                                    <?php echo __('properties.featured_property', [], 'Propiedad destacada'); ?>
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label fw-semibold"><?php echo __('properties.internal_notes', [], 'Notas Internas'); ?></label>
                            <textarea name="internal_notes" class="form-control" rows="4" 
                                      placeholder="<?php echo __('properties.internal_notes_placeholder', [], 'Notas privadas sobre esta propiedad...'); ?>"><?php echo htmlspecialchars($property['internal_notes']); ?></textarea>
                            <small class="text-muted"><?php echo __('properties.internal_notes_hint', [], 'Estas notas no serán visibles públicamente'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card border-0 shadow-sm sticky-bottom bg-white">
            <div class="card-body">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <a href="propiedades.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i> <?php echo __('cancel', [], 'Cancelar'); ?>
                        </a>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash me-2"></i> <?php echo __('properties.delete_property', [], 'Eliminar Propiedad'); ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> <?php echo __('save_changes', [], 'Guardar Cambios'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- CKEditor 5 -->
<script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>

<script>
// ============ CKEDITOR ============
let editorInstance;
const editorLanguage = '<?php echo currentLanguage(); ?>' === 'es' ? 'es' : 'en';

ClassicEditor
    .create(document.querySelector('#editor-container'), {
        toolbar: {
            items: [
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'link', 'bulletedList', 'numberedList', '|',
                'alignment', '|',
                'indent', 'outdent', '|',
                'blockQuote', 'insertTable', '|',
                'undo', 'redo'
            ]
        },
        language: editorLanguage,
        table: {
            contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
        }
    })
    .then(editor => {
        editorInstance = editor;
        
        // Cargar contenido existente
        editor.setData(document.querySelector('#editor').value);
        
        // Sincronizar con textarea
        editor.model.document.on('change:data', () => {
            document.querySelector('#editor').value = editor.getData();
        });
        
        const editorElement = document.querySelector('.ck-editor__editable');
        if (editorElement) {
            editorElement.style.minHeight = '250px';
            editorElement.style.maxHeight = '350px';
            editorElement.style.overflowY = 'auto';
        }
    })
    .catch(error => {
        console.error('Error al cargar CKEditor:', error);
    });

// Sincronizar antes de enviar
document.getElementById('propertyForm').addEventListener('submit', function(e) {
    if (editorInstance) {
        document.querySelector('#editor').value = editorInstance.getData();
    }
});

// ============ IMAGE DROPZONE ============
const dropzone = document.getElementById('imageDropzone');
const imageInput = document.getElementById('imageInput');
const imagePreview = document.getElementById('imagePreview');

dropzone.addEventListener('click', () => imageInput.click());

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('border-primary', 'bg-light');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('border-primary', 'bg-light');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('border-primary', 'bg-light');
    handleFiles(e.dataTransfer.files);
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
                const col = document.createElement('div');
                col.className = 'col-md-3';
                col.innerHTML = `
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-fluid rounded" 
                             style="height: 200px; object-fit: cover; width: 100%;">
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2" 
                                onclick="this.closest('.col-md-3').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                imagePreview.appendChild(col);
            };
            reader.readAsDataURL(file);
        }
    });
}

// ============ DELETE IMAGE ============
function deleteImage(imageId) {
    Swal.fire({
        title: '<?php echo __('properties.confirm_delete_image', [], '¿Eliminar imagen?'); ?>',
        text: '<?php echo __('action_cannot_undone', [], 'Esta acción no se puede deshacer'); ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<?php echo __('yes_delete', [], 'Sí, eliminar'); ?>',
        cancelButtonText: '<?php echo __('cancel', [], 'Cancelar'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/delete-image.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: imageId})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('image_' + imageId).remove();
                    Swal.fire('<?php echo __('deleted', [], '¡Eliminada!'); ?>', '<?php echo __('properties.image_deleted', [], 'La imagen ha sido eliminada'); ?>', 'success');
                } else {
                    Swal.fire('<?php echo __('error', [], 'Error'); ?>', data.message || '<?php echo __('properties.image_delete_error', [], 'No se pudo eliminar la imagen'); ?>', 'error');
                }
            })
            .catch(error => {
                Swal.fire('<?php echo __('error', [], 'Error'); ?>', '<?php echo __('connection_error', [], 'Error de conexión'); ?>', 'error');
            });
        }
    });
}

// ============ SET MAIN IMAGE ============
function setMainImage(imageId) {
    fetch('ajax/set-main-image.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            image_id: imageId,
            property_id: <?php echo $propertyId; ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            Swal.fire('<?php echo __('error', [], 'Error'); ?>', data.message, 'error');
        }
    });
}

// ============ DELETE PROPERTY ============
function confirmDelete() {
    Swal.fire({
        title: '<?php echo __('properties.confirm_delete_property', [], '¿Eliminar propiedad?'); ?>',
        text: '<?php echo __('action_cannot_undone', [], 'Esta acción no se puede deshacer'); ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<?php echo __('yes_delete', [], 'Sí, eliminar'); ?>',
        cancelButtonText: '<?php echo __('cancel', [], 'Cancelar'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/delete-property.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: <?php echo $propertyId; ?>})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('<?php echo __('deleted', [], '¡Eliminada!'); ?>', '<?php echo __('properties.property_deleted', [], 'La propiedad ha sido eliminada'); ?>', 'success')
                        .then(() => window.location.href = 'propiedades.php');
                } else {
                    Swal.fire('<?php echo __('error', [], 'Error'); ?>', data.message, 'error');
                }
            });
        }
    });
}

// ============ LEAFLET MAP ============
let map, marker;
function initMap() {
    const lat = <?php echo $property['latitude'] ?: 18.4861; ?>;
    const lng = <?php echo $property['longitude'] ?: -69.9312; ?>;
    
    map = L.map('map').setView([lat, lng], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    marker = L.marker([lat, lng], {
        draggable: true
    }).addTo(map);
    
    marker.on('dragend', function(e) {
        const position = marker.getLatLng();
        document.getElementById('latitude').value = position.lat;
        document.getElementById('longitude').value = position.lng;
    });
    
    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        document.getElementById('latitude').value = e.latlng.lat;
        document.getElementById('longitude').value = e.latlng.lng;
    });
}

// Cargar Leaflet
const leafletCSS = document.createElement('link');
leafletCSS.rel = 'stylesheet';
leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
document.head.appendChild(leafletCSS);

const leafletJS = document.createElement('script');
leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
leafletJS.onload = initMap;
document.head.appendChild(leafletJS);

console.log('✅ Editar propiedad cargado correctamente');
</script>

<?php include 'footer.php'; ?>
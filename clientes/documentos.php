<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';
require_once 'includes/functions.php';

session_start();
requireClientLogin();

$pageTitle = 'Mis Documentos';
$currentClient = getCurrentClient();

// Filtros
$filterProperty = $_GET['property'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'date_desc';

// Construir query con filtros
$where = ['cpd.client_id = ?', 'cpd.is_visible_to_client = 1'];
$params = [$currentClient['id']];

if ($filterProperty !== 'all') {
    $where[] = 'cpd.property_id = ?';
    $params[] = $filterProperty;
}

if ($filterType !== 'all') {
    $where[] = 'cpd.file_type = ?';
    $params[] = $filterType;
}

$whereClause = implode(' AND ', $where);

// Determinar orden
$orderBy = 'cpd.upload_date DESC';
switch ($sortBy) {
    case 'date_asc':
        $orderBy = 'cpd.upload_date ASC';
        break;
    case 'name_asc':
        $orderBy = 'cpd.document_name ASC';
        break;
    case 'name_desc':
        $orderBy = 'cpd.document_name DESC';
        break;
    case 'size_asc':
        $orderBy = 'cpd.file_size ASC';
        break;
    case 'size_desc':
        $orderBy = 'cpd.file_size DESC';
        break;
}

// Obtener todos los documentos con filtros
$documents = db()->select("
    SELECT 
        cpd.*,
        p.reference as property_reference,
        p.title as property_title,
        p.address as property_address,
        st.transaction_code,
        st.transaction_type,
        CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
    FROM client_property_documents cpd
    INNER JOIN properties p ON cpd.property_id = p.id
    INNER JOIN sales_transactions st ON cpd.transaction_id = st.id
    LEFT JOIN users u ON cpd.uploaded_by_user_id = u.id
    WHERE {$whereClause}
    ORDER BY {$orderBy}
", $params);

// Agrupar documentos por propiedad
$documentsByProperty = [];
$totalSize = 0;
$documentTypes = [];

foreach ($documents as $doc) {
    $propertyId = $doc['property_id'];
    if (!isset($documentsByProperty[$propertyId])) {
        $documentsByProperty[$propertyId] = [
            'property_reference' => $doc['property_reference'],
            'property_title' => $doc['property_title'],
            'property_address' => $doc['property_address'],
            'transaction_type' => $doc['transaction_type'],
            'documents' => [],
            'total_size' => 0
        ];
    }
    $documentsByProperty[$propertyId]['documents'][] = $doc;
    $documentsByProperty[$propertyId]['total_size'] += $doc['file_size'];
    $totalSize += $doc['file_size'];
    
    // Contar tipos de archivo
    $type = strtolower($doc['file_type']);
    if (!isset($documentTypes[$type])) {
        $documentTypes[$type] = 0;
    }
    $documentTypes[$type]++;
}

// Obtener lista de propiedades para el filtro
$properties = db()->select("
    SELECT DISTINCT 
        p.id,
        p.reference,
        p.title,
        st.transaction_type
    FROM client_property_documents cpd
    INNER JOIN properties p ON cpd.property_id = p.id
    INNER JOIN sales_transactions st ON cpd.transaction_id = st.id
    WHERE cpd.client_id = ?
    ORDER BY p.reference
", [$currentClient['id']]);

// Estadísticas
$stats = [
    'total_documents' => count($documents),
    'total_size' => $totalSize,
    'properties_count' => count($documentsByProperty),
    'uploaded_by_me' => array_filter($documents, function($doc) { return $doc['uploaded_by_client'] == 1; }),
    'uploaded_by_admin' => array_filter($documents, function($doc) { return $doc['uploaded_by_client'] == 0; })
];

include 'includes/header.php';
?>

<style>
    .stat-card-docs {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
        height: 100%;
        border-left: 4px solid;
    }
    
    .stat-card-docs:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    
    .document-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.25rem;
        transition: all 0.3s;
        background: white;
        height: 100%;
    }
    
    .document-card:hover {
        border-color: var(--primary-color);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateY(-3px);
    }
    
    .document-icon-large {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #f3f4f6;
        font-size: 2rem;
    }
    
    .property-section {
        margin-bottom: 2rem;
    }
    
    .property-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
    }
    
    .filter-card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .view-toggle {
        display: flex;
        gap: 0.5rem;
    }
    
    .view-toggle button {
        padding: 0.5rem 1rem;
        border: 1px solid #e5e7eb;
        background: white;
        cursor: pointer;
        transition: all 0.3s;
        border-radius: 8px;
    }
    
    .view-toggle button.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .document-preview-thumb {
        width: 100%;
        height: 120px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .quick-actions {
        display: flex;
        gap: 0.5rem;
    }
</style>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1><i class="fas fa-folder-open me-2"></i> Mis Documentos</h1>
            <p class="text-muted mb-0">Todos tus documentos organizados por propiedad</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?php echo url('clientes/dashboard.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Volver al Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card-docs" style="border-left-color: #3b82f6;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Total Documentos</small>
                    <h2 class="mb-0 text-primary"><?php echo $stats['total_documents']; ?></h2>
                </div>
                <i class="fas fa-file fa-2x text-primary opacity-25"></i>
            </div>
            <small class="text-muted">
                En <?php echo $stats['properties_count']; ?> propiedad(es)
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-docs" style="border-left-color: #10b981;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Subidos por Ti</small>
                    <h2 class="mb-0 text-success"><?php echo count($stats['uploaded_by_me']); ?></h2>
                </div>
                <i class="fas fa-upload fa-2x text-success opacity-25"></i>
            </div>
            <small class="text-success">
                Tus archivos
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-docs" style="border-left-color: #06b6d4;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Compartidos</small>
                    <h2 class="mb-0 text-info"><?php echo count($stats['uploaded_by_admin']); ?></h2>
                </div>
                <i class="fas fa-share-alt fa-2x text-info opacity-25"></i>
            </div>
            <small class="text-muted">
                Por administración
            </small>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="stat-card-docs" style="border-left-color: #f59e0b;">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <small class="text-muted d-block mb-1">Espacio Usado</small>
                    <h3 class="mb-0 text-warning"><?php echo formatFileSize($stats['total_size']); ?></h3>
                </div>
                <i class="fas fa-database fa-2x text-warning opacity-25"></i>
            </div>
            <small class="text-muted">
                Total almacenado
            </small>
        </div>
    </div>
</div>

<!-- Filters and View Options -->
<div class="filter-card">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-bold">
                <i class="fas fa-building me-1"></i> Propiedad
            </label>
            <select name="property" class="form-select">
                <option value="all" <?php echo $filterProperty === 'all' ? 'selected' : ''; ?>>Todas las Propiedades</option>
                <?php foreach ($properties as $prop): ?>
                    <option value="<?php echo $prop['id']; ?>" <?php echo $filterProperty == $prop['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($prop['reference'] . ' - ' . $prop['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label class="form-label fw-bold">
                <i class="fas fa-file-alt me-1"></i> Tipo
            </label>
            <select name="type" class="form-select">
                <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>Todos los Tipos</option>
                <option value="pdf" <?php echo $filterType === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                <option value="jpg" <?php echo $filterType === 'jpg' ? 'selected' : ''; ?>>Imágenes</option>
                <option value="doc" <?php echo $filterType === 'doc' ? 'selected' : ''; ?>>Documentos</option>
                <option value="xls" <?php echo $filterType === 'xls' ? 'selected' : ''; ?>>Excel</option>
            </select>
        </div>
        
        <div class="col-md-3">
            <label class="form-label fw-bold">
                <i class="fas fa-sort me-1"></i> Ordenar por
            </label>
            <select name="sort" class="form-select">
                <option value="date_desc" <?php echo $sortBy === 'date_desc' ? 'selected' : ''; ?>>Más Reciente</option>
                <option value="date_asc" <?php echo $sortBy === 'date_asc' ? 'selected' : ''; ?>>Más Antiguo</option>
                <option value="name_asc" <?php echo $sortBy === 'name_asc' ? 'selected' : ''; ?>>Nombre A-Z</option>
                <option value="name_desc" <?php echo $sortBy === 'name_desc' ? 'selected' : ''; ?>>Nombre Z-A</option>
                <option value="size_asc" <?php echo $sortBy === 'size_asc' ? 'selected' : ''; ?>>Tamaño Menor</option>
                <option value="size_desc" <?php echo $sortBy === 'size_desc' ? 'selected' : ''; ?>>Tamaño Mayor</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-search me-2"></i> Filtrar
            </button>
        </div>
        
        <div class="col-md-2">
            <?php if ($filterProperty !== 'all' || $filterType !== 'all' || $sortBy !== 'date_desc'): ?>
                <a href="<?php echo url('clientes/documentos.php'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times me-2"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Documents Display -->
<?php if (empty($documents)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-folder-open fa-4x text-muted mb-4"></i>
            <h4>No hay documentos disponibles</h4>
            <p class="text-muted">
                <?php if ($filterProperty !== 'all' || $filterType !== 'all'): ?>
                    No se encontraron documentos con los filtros seleccionados.
                <?php else: ?>
                    Los documentos que subas o que te compartan aparecerán aquí.
                <?php endif; ?>
            </p>
            <?php if ($filterProperty !== 'all' || $filterType !== 'all'): ?>
                <a href="<?php echo url('clientes/documentos.php'); ?>" class="btn btn-outline-primary mt-3">
                    <i class="fas fa-times me-2"></i> Limpiar Filtros
                </a>
            <?php else: ?>
                <a href="<?php echo url('clientes/propiedades.php'); ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-building me-2"></i> Ver Mis Propiedades
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    
    <!-- View Toggle -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <?php echo count($documents); ?> documento(s) encontrado(s)
        </h5>
        <div class="view-toggle">
            <button class="active" id="gridViewBtn" onclick="switchView('grid')">
                <i class="fas fa-th-large"></i> Cuadrícula
            </button>
            <button id="listViewBtn" onclick="switchView('list')">
                <i class="fas fa-list"></i> Lista
            </button>
        </div>
    </div>
    
    <!-- Grid View (Default) -->
    <div id="gridView">
        <?php foreach ($documentsByProperty as $propertyId => $propertyData): ?>
            <div class="property-section">
                <div class="property-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <i class="fas fa-building me-2"></i>
                                <?php echo htmlspecialchars($propertyData['property_title']); ?>
                            </h5>
                            <p class="mb-0 opacity-75">
                                <small>
                                    <?php echo htmlspecialchars($propertyData['property_reference']); ?> • 
                                    <?php echo htmlspecialchars($propertyData['property_address']); ?> • 
                                    <?php echo count($propertyData['documents']); ?> documento(s) • 
                                    <?php echo formatFileSize($propertyData['total_size']); ?>
                                </small>
                            </p>
                        </div>
                        <?php echo getTransactionTypeBadge($propertyData['transaction_type']); ?>
                    </div>
                </div>
                
                <div class="row g-3">
                    <?php foreach ($propertyData['documents'] as $doc): ?>
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <div class="document-card">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="document-icon-large me-3">
                                        <i class="fas <?php echo getFileIcon($doc['file_type']); ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                        <small class="text-muted d-block">
                                            <?php echo strtoupper($doc['file_type']); ?> • 
                                            <?php echo formatFileSize($doc['file_size']); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <?php if ($doc['document_description']): ?>
                                    <p class="small text-muted mb-3">
                                        <?php echo htmlspecialchars(substr($doc['document_description'], 0, 80)) . (strlen($doc['document_description']) > 80 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="border-top pt-3 mb-3">
                                    <small class="text-muted d-block">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($doc['upload_date'])); ?>
                                    </small>
                                    <?php if ($doc['uploaded_by_client']): ?>
                                        <small class="text-success d-block mt-1">
                                            <i class="fas fa-user me-1"></i> Subido por ti
                                        </small>
                                    <?php elseif ($doc['uploaded_by_name']): ?>
                                        <small class="text-info d-block mt-1">
                                            <i class="fas fa-user-tie me-1"></i> 
                                            Compartido por: <?php echo htmlspecialchars($doc['uploaded_by_name']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="quick-actions">
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                       class="btn btn-sm btn-primary flex-grow-1" 
                                       target="_blank">
                                        <i class="fas fa-eye me-1"></i> Ver
                                    </a>
                                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       download
                                       title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- List View (Hidden by default) -->
    <div id="listView" style="display: none;">
        <?php foreach ($documentsByProperty as $propertyId => $propertyData): ?>
            <div class="property-section">
                <div class="property-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <i class="fas fa-building me-2"></i>
                                <?php echo htmlspecialchars($propertyData['property_title']); ?>
                            </h5>
                            <p class="mb-0 opacity-75">
                                <small>
                                    <?php echo htmlspecialchars($propertyData['property_reference']); ?> • 
                                    <?php echo count($propertyData['documents']); ?> documento(s)
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Tipo</th>
                                    <th>Tamaño</th>
                                    <th>Subido Por</th>
                                    <th>Fecha</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($propertyData['documents'] as $doc): ?>
                                    <tr>
                                        <td class="text-center">
                                            <i class="fas <?php echo getFileIcon($doc['file_type']); ?> fa-2x"></i>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo $doc['document_description'] ? htmlspecialchars(substr($doc['document_description'], 0, 50)) . (strlen($doc['document_description']) > 50 ? '...' : '') : '-'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo strtoupper($doc['file_type']); ?></span>
                                        </td>
                                        <td><?php echo formatFileSize($doc['file_size']); ?></td>
                                        <td>
                                            <?php if ($doc['uploaded_by_client']): ?>
                                                <span class="badge bg-success"><i class="fas fa-user me-1"></i>Tú</span>
                                            <?php elseif ($doc['uploaded_by_name']): ?>
                                                <small class="text-info">
                                                    <i class="fas fa-user-tie me-1"></i>
                                                    <?php echo htmlspecialchars($doc['uploaded_by_name']); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('d/m/Y H:i', strtotime($doc['upload_date'])); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                   class="btn btn-outline-primary" 
                                                   target="_blank"
                                                   title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                                   class="btn btn-outline-success" 
                                                   download
                                                   title="Descargar">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
<?php endif; ?>

<!-- Document Types Summary -->
<?php if (!empty($documentTypes)): ?>
<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i> Tipos de Documentos</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($documentTypes as $type => $count): ?>
                <div class="col-md-3 col-6">
                    <div class="text-center p-3 border rounded">
                        <i class="fas <?php echo getFileIcon($type); ?> fa-3x mb-2"></i>
                        <div>
                            <strong><?php echo strtoupper($type); ?></strong>
                            <br>
                            <small class="text-muted"><?php echo $count; ?> archivo(s)</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$additionalScripts = <<<HTML
<script>
// Switch between grid and list view
function switchView(view) {
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    
    if (view === 'grid') {
        gridView.style.display = 'block';
        listView.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
        localStorage.setItem('documentView', 'grid');
    } else {
        gridView.style.display = 'none';
        listView.style.display = 'block';
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
        localStorage.setItem('documentView', 'list');
    }
}

// Load saved view preference
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('documentView');
    if (savedView === 'list') {
        switchView('list');
    }
});

// Prevent accidental navigation
window.addEventListener('beforeunload', function(e) {
    // Only if user is uploading
    const uploadForms = document.querySelectorAll('form[enctype="multipart/form-data"]');
    let isUploading = false;
    
    uploadForms.forEach(form => {
        if (form.querySelector('input[type="file"]')?.files.length > 0) {
            isUploading = true;
        }
    });
    
    if (isUploading) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>
HTML;

include 'includes/footer.php';
?>
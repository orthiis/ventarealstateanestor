<?php
/**
 * Funciones específicas para el portal de clientes
 */

// Verificar si el cliente está autenticado
function requireClientLogin() {
    if (!isset($_SESSION['client_id'])) {
        header('Location: /login-clientes.php');
        exit;
    }
}

// Obtener datos del cliente actual
function getCurrentClient() {
    if (!isset($_SESSION['client_id'])) {
        return null;
    }
    
    $client = db()->selectOne("
        SELECT c.*, 
               CONCAT(c.first_name, ' ', c.last_name) as full_name,
               CONCAT(u.first_name, ' ', u.last_name) as agent_name,
               u.email as agent_email,
               u.phone as agent_phone
        FROM clients c
        LEFT JOIN users u ON c.agent_id = u.id
        WHERE c.id = ? AND c.portal_active = 1
    ", [$_SESSION['client_id']]);
    
    return $client;
}

// Obtener propiedades del cliente (compradas y/o alquiladas)
function getClientProperties($clientId) {
    return db()->select("
        SELECT st.*,
               p.id as property_id,
               p.reference as property_reference,
               p.title as property_title,
               p.address as property_address,
               p.city as property_city,
               p.price as property_price,
               pt.name as property_type,
               (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as property_image,
               CONCAT(u.first_name, ' ', u.last_name) as agent_name
        FROM sales_transactions st
        INNER JOIN properties p ON st.property_id = p.id
        LEFT JOIN property_types pt ON p.property_type_id = pt.id
        LEFT JOIN users u ON st.agent_id = u.id
        WHERE st.client_id = ? 
        AND st.status IN ('completed', 'in_progress')
        ORDER BY st.created_at DESC
    ", [$clientId]);
}

// Obtener una propiedad específica del cliente
function getClientProperty($clientId, $propertyId) {
    return db()->selectOne("
        SELECT st.*,
               p.*,
               p.id as property_id,
               pt.name as property_type,
               (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as main_image,
               CONCAT(u.first_name, ' ', u.last_name) as agent_name,
               u.email as agent_email,
               u.phone as agent_phone,
               u.profile_picture as agent_picture
        FROM sales_transactions st
        INNER JOIN properties p ON st.property_id = p.id
        LEFT JOIN property_types pt ON p.property_type_id = pt.id
        LEFT JOIN users u ON st.agent_id = u.id
        WHERE st.client_id = ? AND p.id = ?
        AND st.status IN ('completed', 'in_progress')
        LIMIT 1
    ", [$clientId, $propertyId]);
}

// Obtener documentos de una propiedad del cliente
function getPropertyDocuments($clientId, $propertyId) {
    return db()->select("
        SELECT cpd.*,
               CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
        FROM client_property_documents cpd
        LEFT JOIN users u ON cpd.uploaded_by_user_id = u.id
        WHERE cpd.client_id = ? 
        AND cpd.property_id = ?
        AND cpd.is_visible_to_client = 1
        ORDER BY cpd.upload_date DESC
    ", [$clientId, $propertyId]);
}

// Obtener comentarios/chat de una propiedad
function getPropertyComments($clientId, $propertyId) {
    return db()->select("
        SELECT cpc.*,
               CONCAT(u.first_name, ' ', u.last_name) as admin_name,
               u.profile_picture as admin_picture
        FROM client_property_comments cpc
        LEFT JOIN users u ON cpc.user_id = u.id
        WHERE cpc.client_id = ? 
        AND cpc.property_id = ?
        ORDER BY cpc.created_at ASC
    ", [$clientId, $propertyId]);
}

// Obtener facturas del cliente
function getClientInvoices($clientId, $filters = []) {
    $where = ['i.client_id = ?'];
    $params = [$clientId];
    
    if (!empty($filters['status'])) {
        $where[] = 'i.status = ?';
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['year'])) {
        $where[] = 'i.period_year = ?';
        $params[] = $filters['year'];
    }
    
    $whereClause = implode(' AND ', $where);
    
    return db()->select("
        SELECT i.*,
               p.reference as property_reference,
               p.title as property_title,
               p.address as property_address,
               st.transaction_code
        FROM invoices i
        INNER JOIN properties p ON i.property_id = p.id
        INNER JOIN sales_transactions st ON i.transaction_id = st.id
        WHERE {$whereClause}
        ORDER BY i.invoice_date DESC
    ", $params);
}

// Obtener estadísticas del cliente
function getClientStats($clientId) {
    return db()->selectOne("
        SELECT 
            COUNT(DISTINCT st.id) as total_properties,
            SUM(CASE WHEN st.transaction_type = 'sale' THEN 1 ELSE 0 END) as properties_purchased,
            SUM(CASE WHEN st.transaction_type IN ('rent', 'vacation_rent') THEN 1 ELSE 0 END) as properties_rented,
            (SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'paid') as invoices_paid,
            (SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'pending') as invoices_pending,
            (SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status = 'overdue') as invoices_overdue,
            (SELECT SUM(total_amount) FROM invoices WHERE client_id = ? AND status = 'paid') as total_paid,
            (SELECT SUM(balance_due) FROM invoices WHERE client_id = ? AND status IN ('pending', 'partial', 'overdue')) as total_due,
            (SELECT COUNT(*) FROM client_property_documents WHERE client_id = ?) as total_documents,
            (SELECT COUNT(*) FROM client_property_comments WHERE client_id = ? AND sender_type = 'client') as total_messages
        FROM sales_transactions st
        WHERE st.client_id = ?
    ", [$clientId, $clientId, $clientId, $clientId, $clientId, $clientId, $clientId, $clientId]);
}

// Marcar comentarios como leídos
function markCommentsAsRead($clientId, $propertyId) {
    db()->query("
        UPDATE client_property_comments 
        SET is_read = 1, read_at = NOW()
        WHERE client_id = ? 
        AND property_id = ?
        AND sender_type = 'admin'
        AND is_read = 0
    ", [$clientId, $propertyId]);
}

// Formatear tamaño de archivo
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Obtener icono según tipo de archivo
function getFileIcon($fileType) {
    $icons = [
        'pdf' => 'fa-file-pdf text-danger',
        'jpg' => 'fa-file-image text-primary',
        'jpeg' => 'fa-file-image text-primary',
        'png' => 'fa-file-image text-primary',
        'gif' => 'fa-file-image text-primary',
        'doc' => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'xls' => 'fa-file-excel text-success',
        'xlsx' => 'fa-file-excel text-success',
        'zip' => 'fa-file-archive text-warning',
        'rar' => 'fa-file-archive text-warning',
    ];
    
    return $icons[strtolower($fileType)] ?? 'fa-file text-secondary';
}

// Obtener badge de estado de factura
function getInvoiceStatusBadge($status) {
    $badges = [
        'draft' => '<span class="badge bg-secondary">Borrador</span>',
        'pending' => '<span class="badge bg-warning">Pendiente</span>',
        'partial' => '<span class="badge bg-info">Pago Parcial</span>',
        'paid' => '<span class="badge bg-success">Pagada</span>',
        'overdue' => '<span class="badge bg-danger">Vencida</span>',
        'cancelled' => '<span class="badge bg-dark">Cancelada</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">Desconocido</span>';
}

// Obtener badge de tipo de transacción
function getTransactionTypeBadge($type) {
    $badges = [
        'sale' => '<span class="badge bg-success"><i class="fas fa-shopping-cart me-1"></i>Venta</span>',
        'rent' => '<span class="badge bg-primary"><i class="fas fa-key me-1"></i>Alquiler</span>',
        'vacation_rent' => '<span class="badge bg-info"><i class="fas fa-umbrella-beach me-1"></i>Alquiler Vacacional</span>',
    ];
    
    return $badges[$type] ?? '<span class="badge bg-secondary">Desconocido</span>';
}

// Sanitizar entrada
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
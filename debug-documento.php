<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$documentId = $_GET['id'] ?? 0;

echo "<h1>Debug de Documento ID: $documentId</h1>";

// Test 1: Verificar conexión a BD
echo "<h3>1. Test de Conexión a Base de Datos</h3>";
try {
    $connection = db();
    echo "✅ Conexión exitosa<br>";
} catch(Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
    die();
}

// Test 2: Verificar si existe la tabla documents
echo "<h3>2. Verificar tabla 'documents'</h3>";
try {
    $tableCheck = db()->query("SHOW TABLES LIKE 'documents'");
    if($tableCheck) {
        echo "✅ La tabla 'documents' existe<br>";
    } else {
        echo "❌ La tabla 'documents' NO existe<br>";
    }
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// Test 3: Ver todos los documentos en la BD
echo "<h3>3. Documentos en la Base de Datos</h3>";
try {
    $allDocs = db()->select("SELECT id, document_name, file_path, status FROM documents LIMIT 10");
    if(empty($allDocs)) {
        echo "⚠️ No hay documentos en la base de datos<br>";
    } else {
        echo "✅ Total de documentos: " . count($allDocs) . "<br>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Ruta</th><th>Estado</th><th>Acción</th></tr>";
        foreach($allDocs as $doc) {
            echo "<tr>";
            echo "<td>" . $doc['id'] . "</td>";
            echo "<td>" . htmlspecialchars($doc['document_name']) . "</td>";
            echo "<td>" . htmlspecialchars($doc['file_path']) . "</td>";
            echo "<td>" . htmlspecialchars($doc['status']) . "</td>";
            echo "<td><a href='ver-documento.php?id=" . $doc['id'] . "'>Ver</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch(Exception $e) {
    echo "❌ Error al consultar documentos: " . $e->getMessage() . "<br>";
}

// Test 4: Buscar el documento específico
echo "<h3>4. Buscar Documento ID: $documentId</h3>";
try {
    $query = "SELECT d.*, 
     dc.name as category_name, dc.color as category_color, dc.icon as category_icon,
     CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name,
     u.email as uploader_email
     FROM documents d
     LEFT JOIN document_categories dc ON d.category_id = dc.id
     LEFT JOIN users u ON d.uploaded_by = u.id
     WHERE d.id = ?";
    
    echo "Query: " . str_replace('?', $documentId, $query) . "<br><br>";
    
    $document = db()->selectOne($query, [$documentId]);
    
    if($document) {
        echo "✅ Documento encontrado<br>";
        echo "<pre>";
        print_r($document);
        echo "</pre>";
    } else {
        echo "❌ Documento con ID $documentId NO encontrado<br>";
    }
} catch(Exception $e) {
    echo "❌ Error en la consulta: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 5: Verificar estructura de la tabla
echo "<h3>5. Estructura de la tabla 'documents'</h3>";
try {
    $columns = db()->query("DESCRIBE documents");
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
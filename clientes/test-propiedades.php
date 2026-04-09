<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';
require_once 'includes/functions.php';

session_start();
requireClientLogin();

$currentClient = getCurrentClient();

echo "<h1>Diagnóstico de Propiedades - Cliente ID: {$currentClient['id']}</h1>";
echo "<hr>";

// 1. Verificar cliente
echo "<h2>1. Información del Cliente</h2>";
echo "<pre>";
print_r($currentClient);
echo "</pre>";

// 2. Verificar transacciones del cliente
echo "<h2>2. Transacciones del Cliente (sales_transactions)</h2>";
$transactions = db()->select("SELECT * FROM sales_transactions WHERE client_id = ?", [$currentClient['id']]);
echo "<p><strong>Total transacciones encontradas: " . count($transactions) . "</strong></p>";
if (!empty($transactions)) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Código</th><th>Property ID</th><th>Tipo</th><th>Estado</th><th>Fecha Contrato</th></tr>";
    foreach ($transactions as $t) {
        echo "<tr>";
        echo "<td>{$t['id']}</td>";
        echo "<td>{$t['transaction_code']}</td>";
        echo "<td>{$t['property_id']}</td>";
        echo "<td>{$t['transaction_type']}</td>";
        echo "<td><strong>{$t['status']}</strong></td>";
        echo "<td>{$t['contract_date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ NO SE ENCONTRARON TRANSACCIONES para client_id = {$currentClient['id']}</p>";
}

// 3. Verificar propiedades vinculadas
echo "<h2>3. Propiedades Vinculadas</h2>";
if (!empty($transactions)) {
    foreach ($transactions as $t) {
        $property = db()->selectOne("SELECT * FROM properties WHERE id = ?", [$t['property_id']]);
        if ($property) {
            echo "<p>✅ Propiedad ID {$t['property_id']}: <strong>{$property['title']}</strong> (Ref: {$property['reference']})</p>";
        } else {
            echo "<p style='color: red;'>❌ Propiedad ID {$t['property_id']} NO EXISTE en la tabla properties</p>";
        }
    }
} else {
    echo "<p style='color: orange;'>⚠️ No hay transacciones para verificar propiedades</p>";
}

// 4. Consulta completa del dashboard
echo "<h2>4. Consulta Completa del Dashboard</h2>";
$properties = db()->select("
    SELECT 
        st.id as transaction_id,
        st.transaction_code,
        st.transaction_type,
        st.status as transaction_status,
        st.sale_price,
        st.monthly_payment,
        p.id as property_id,
        p.reference as property_reference,
        p.title as property_title,
        p.address as property_address
    FROM sales_transactions st
    INNER JOIN properties p ON st.property_id = p.id
    WHERE st.client_id = ? 
    AND st.status IN ('completed', 'in_progress')
    ORDER BY st.created_at DESC
", [$currentClient['id']]);

echo "<p><strong>Total propiedades encontradas con status 'completed' o 'in_progress': " . count($properties) . "</strong></p>";
if (!empty($properties)) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Transaction ID</th><th>Código</th><th>Property ID</th><th>Título</th><th>Tipo</th><th>Estado</th></tr>";
    foreach ($properties as $p) {
        echo "<tr>";
        echo "<td>{$p['transaction_id']}</td>";
        echo "<td>{$p['transaction_code']}</td>";
        echo "<td>{$p['property_id']}</td>";
        echo "<td>{$p['property_title']}</td>";
        echo "<td>{$p['transaction_type']}</td>";
        echo "<td>{$p['transaction_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ NO SE ENCONTRARON PROPIEDADES con la consulta del dashboard</p>";
}

// 5. Solución sugerida
echo "<h2>5. Solución</h2>";
if (empty($transactions)) {
    echo "<p style='color: red;'><strong>PROBLEMA:</strong> El cliente no tiene transacciones asignadas.</p>";
    echo "<p><strong>SOLUCIÓN:</strong> Ejecuta este SQL para crear una transacción de prueba:</p>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";
    echo "-- Asignar una propiedad al cliente\n";
    echo "INSERT INTO sales_transactions \n";
    echo "(transaction_code, property_id, client_id, agent_id, transaction_type, \n";
    echo " sale_price, status, contract_date, created_at) \n";
    echo "VALUES \n";
    echo "('TRX-TEST-{$currentClient['id']}', 4, {$currentClient['id']}, 1, 'sale', \n";
    echo " 850000.00, 'completed', CURDATE(), NOW());\n";
    echo "</pre>";
} elseif (empty($properties)) {
    echo "<p style='color: red;'><strong>PROBLEMA:</strong> Las transacciones existen pero tienen estado incorrecto.</p>";
    echo "<p><strong>SOLUCIÓN:</strong> Actualiza el estado de las transacciones:</p>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";
    echo "UPDATE sales_transactions \n";
    echo "SET status = 'completed' \n";
    echo "WHERE client_id = {$currentClient['id']};\n";
    echo "</pre>";
}

echo "<hr>";
echo "<p><a href='/clientes/dashboard.php'>← Volver al Dashboard</a></p>";
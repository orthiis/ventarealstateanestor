<?php
require_once '../config.php';
require_once '../database.php';

// Obtener estructura de la tabla properties
$columns = db()->select("SHOW COLUMNS FROM properties");

echo "<h2>Columnas de la tabla 'properties':</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Columna</th><th>Tipo</th></tr>";
foreach ($columns as $col) {
    echo "<tr><td><strong>{$col['Field']}</strong></td><td>{$col['Type']}</td></tr>";
}
echo "</table>";
?>
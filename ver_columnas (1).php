<?php
// ver_columnas.php
// Muestra los nombres exactos de las columnas de la tabla 'produccion_turnos'
header('Content-Type: text/html; charset=utf-8');
include 'conexion.php';

echo "<h1>🔍 Diagnóstico de Columnas: produccion_turnos</h1><hr>";

$tabla = "produccion_turnos";
$sql = "SHOW COLUMNS FROM $tabla";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Campo (Exacto)</th><th>Tipo</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='color:blue; font-weight:bold;'>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>Error: La tabla '$tabla' no existe o no se pudo leer.</p>";
}
?>
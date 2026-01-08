<?php
// ver_estructura.php
// Muestra todas las tablas y sus columnas para identificar basura
header('Content-Type: text/html; charset=utf-8');
include 'conexion.php';

echo "<h1>🔍 Radiografía de tu Base de Datos</h1>";
echo "<p>Base de datos: <b>$base_datos</b></p><hr>";

$tablas = $conn->query("SHOW TABLES");

if ($tablas) {
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse; width:100%;'>";
    while ($fila = $tablas->fetch_array()) {
        $tabla = $fila[0];
        echo "<tr style='background:#f0f0f0;'><td colspan='3'><h3>📂 Tabla: <span style='color:#0056b3'>$tabla</span></h3></td></tr>";
        
        // Ver columnas
        $cols = $conn->query("SHOW COLUMNS FROM $tabla");
        echo "<tr><th>Campo</th><th>Tipo</th><th>Extra</th></tr>";
        while ($c = $cols->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $c['Field'] . "</td>";
            echo "<td>" . $c['Type'] . "</td>";
            echo "<td>" . $c['Extra'] . "</td>";
            echo "</tr>";
        }
        
        // Ver cantidad de datos
        $count = $conn->query("SELECT COUNT(*) FROM $tabla")->fetch_array()[0];
        echo "<tr><td colspan='3' style='text-align:right; color:green;'><b>Total Registros: $count</b></td></tr>";
    }
    echo "</table>";
}
?>
<?php
include 'conexion.php';

// Recibir datos del formulario (Nombres coinciden con tus tablas)
$orden = $_POST['NumeroOrden'];
$fecha = $_POST['Fecha'];
$referencia = $_POST['Referencia'];
$producto = $_POST['Producto'];
$cantidad = $_POST['Cantidad'];
$maquina = $_POST['Maquina'];
$observaciones = $_POST['Observaciones'];

// Prevenir duplicados de ID (Si intentan meter la misma orden dos veces)
$check = $conn->query("SELECT NumeroOrden FROM produccion_master WHERE NumeroOrden = '$orden'");
if ($check->num_rows > 0) {
    die("<h2 style='color:red; text-align:center'>Error: El Número de Orden $orden ya existe.</h2><center><a href='index.html'>Volver</a></center>");
}

// Insertar en la tabla produccion_master
$sql = "INSERT INTO produccion_master (NumeroOrden, Fecha, Referencia, Producto, Cantidad, Maquina, Observaciones) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssiss", $orden, $fecha, $referencia, $producto, $cantidad, $maquina, $observaciones);

if ($stmt->execute()) {
    echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
    echo "<h1 style='color:green'>✅ Orden #$orden Guardada</h1>";
    echo "<p>Se ha registrado la producción correctamente.</p>";
    echo "<a href='index.html' style='padding:10px; background:#0056b3; color:white; text-decoration:none; border-radius:5px;'>Nuevo Registro</a>";
    echo "<br><br><a href='ver_registros.php'>Ver Reportes</a>";
    echo "</div>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>

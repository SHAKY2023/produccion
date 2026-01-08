<?php
// normalizar_db.php
// HERRAMIENTA FINAL: Estandariza todo a minúsculas (snake_case) para evitar errores
header('Content-Type: text/html; charset=utf-8');
include 'conexion.php';

echo "<h1>🔧 Normalización de Base de Datos (A Minúsculas)</h1><hr>";

$tabla = "produccion_turnos";

// Verificar si existe la tabla
$check = $conn->query("SHOW TABLES LIKE '$tabla'");
if($check->num_rows == 0) {
    // Si no existe, la creamos bien desde cero
    $sql_create = "CREATE TABLE $tabla (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_orden VARCHAR(50),
        numero_turno INT,
        fecha DATE,
        operario_id INT,
        hora_inicio TIME,
        hora_final TIME,
        tiempo_total_seg INT,
        ciclo_real DECIMAL(10,2),
        contador_inicial INT,
        contador_final INT,
        unidades_fisicas INT,
        unidades_malas INT,
        unidades_buenas INT,
        sobrante_inicio INT,
        sobrante_final INT,
        peso_bolsa_unitario DECIMAL(10,2),
        cantidad_bolsas INT,
        consumo_bolsa DECIMAL(10,2),
        diferencia_unidades INT,
        observaciones TEXT,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if($conn->query($sql_create)) echo "<p style='color:green'>✅ Tabla creada correctamente con minúsculas.</p>";
    else echo "<p style='color:red'>❌ Error creando tabla: ".$conn->error."</p>";
} else {
    echo "<p>La tabla <b>$tabla</b> existe. Normalizando columnas...</p>";
    
    // LISTA DE CAMBIOS (De Mayúscula a Minúscula si existen)
    $cambios = [
        "NumeroOrden" => "numero_orden VARCHAR(50)",
        "NumeroTurno" => "numero_turno INT",
        "Fecha" => "fecha DATE",
        "Operario_id" => "operario_id INT",
        // Agrega aquí si tienes otras variantes raras
    ];

    // 1. Renombrar columnas mal escritas
    foreach ($cambios as $old => $new_def) {
        $check_col = $conn->query("SHOW COLUMNS FROM $tabla LIKE '$old'");
        if($check_col->num_rows > 0) {
            $conn->query("ALTER TABLE $tabla CHANGE $old $new_def");
            echo "✅ Se renombró <b>$old</b> a minúsculas.<br>";
        }
    }

    // 2. Asegurar que existan las columnas (en minúscula)
    $columnas_necesarias = [
        "numero_orden" => "VARCHAR(50)",
        "numero_turno" => "INT",
        "fecha" => "DATE",
        "operario_id" => "INT",
        "hora_inicio" => "TIME",
        "hora_final" => "TIME",
        "tiempo_total_seg" => "INT",
        "ciclo_real" => "DECIMAL(10,2)",
        "contador_inicial" => "INT",
        "contador_final" => "INT",
        "unidades_fisicas" => "INT",
        "unidades_malas" => "INT",
        "unidades_buenas" => "INT",
        "sobrante_inicio" => "INT",
        "sobrante_final" => "INT",
        "peso_bolsa_unitario" => "DECIMAL(10,2)",
        "cantidad_bolsas" => "INT",
        "consumo_bolsa" => "DECIMAL(10,2)",
        "diferencia_unidades" => "INT",
        "observaciones" => "TEXT"
    ];

    foreach ($columnas_necesarias as $col => $def) {
        $check_c = $conn->query("SHOW COLUMNS FROM $tabla LIKE '$col'");
        if($check_c->num_rows == 0) {
            $conn->query("ALTER TABLE $tabla ADD COLUMN $col $def");
            echo "✅ Se agregó columna faltante: <b>$col</b>.<br>";
        }
    }
}

echo "<hr><h2 style='color:green'>✨ Base de Datos Normalizada</h2>";
echo "<p>Ahora todas las columnas están en minúscula. Intenta guardar el turno nuevamente.</p>";
echo "<a href='gestion_turno.php' style='background:#0056b3; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Volver a Gestionar Turno</a>";
?>
<?php
// ajustar_tablas.php
// Herramienta para borrar tablas viejas y crear las definitivas con nombres correctos
header('Content-Type: text/html; charset=utf-8');
include 'conexion.php';

echo "<h1>🧹 Limpieza y Configuración de Tablas</h1><hr>";

// 1. BORRAR TABLAS VIEJAS (Si existen)
$tablas_borrar = ['turnos_paquetes', 'turnos_registros'];

foreach ($tablas_borrar as $tabla) {
    $sql = "DROP TABLE IF EXISTS $tabla";
    if ($conn->query($sql)) {
        echo "<p style='color:green'>🗑️ Tabla vieja <b>$tabla</b> eliminada correctamente.</p>";
    } else {
        echo "<p style='color:red'>❌ Error borrando $tabla: " . $conn->error . "</p>";
    }
}

echo "<hr>";

// 2. CREAR TABLAS NUEVAS (DEFINITIVAS)

// A. PRODUCCION_TURNOS (Encabezado)
$sql_turnos = "CREATE TABLE IF NOT EXISTS produccion_turnos (
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

if ($conn->query($sql_turnos)) {
    echo "<p style='color:blue'>✅ Tabla <b>produccion_turnos</b> creada/verificada.</p>";
} else {
    echo "<p style='color:red'>❌ Error creando produccion_turnos: " . $conn->error . "</p>";
}

// B. PRODUCCION_TURNOS_PAQUETES (Detalle de Paquetes Fabricados)
// Usamos este nombre para no confundirla con 'produccion_paquetes' que es la del plan de la orden
$sql_paqs = "CREATE TABLE IF NOT EXISTS produccion_turnos_paquetes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_turno INT NOT NULL,
    numero_paquete INT,
    color VARCHAR(50),
    peso DECIMAL(10,2),
    FOREIGN KEY (id_turno) REFERENCES produccion_turnos(id) ON DELETE CASCADE
)";

if ($conn->query($sql_paqs)) {
    echo "<p style='color:blue'>✅ Tabla <b>produccion_turnos_paquetes</b> creada/verificada.</p>";
} else {
    echo "<p style='color:red'>❌ Error creando produccion_turnos_paquetes: " . $conn->error . "</p>";
}

echo "<br><br><a href='gestion_turno.php' style='background:#0056b3; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ir a Gestionar Turno</a>";
?>
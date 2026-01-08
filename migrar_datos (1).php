<?php
include 'conexion.php';

echo "<h1>🚀 Iniciando Migración de Datos (Intento 2)...</h1>";

// 1. CAMBIAMOS EL OBJETIVO: BUSCAR EN LA TABLA 'seguridad'
// En tu sistema viejo, 'auditor' eran logs, 'seguridad' son los usuarios.
$tabla_vieja = 'seguridad';

$check = $conn->query("SHOW TABLES LIKE '$tabla_vieja'");
if($check->num_rows == 0) {
    echo "❌ Error: No encuentro la tabla '$tabla_vieja'. <br>";
    echo "🔍 Listando tablas disponibles en tu base de datos:<br><ul>";
    $tablas = $conn->query("SHOW TABLES");
    while($t = $tablas->fetch_array()) { echo "<li>" . $t[0] . "</li>"; }
    echo "</ul>";
    die("Por favor verifica el nombre de la tabla en la lista de arriba.");
}

// 2. OBTENER LOS USUARIOS VIEJOS
$sql_viejo = "SELECT * FROM $tabla_vieja";
$resultado = $conn->query($sql_viejo);

if ($resultado->num_rows > 0) {
    echo "<ul>";
    while($fila = $resultado->fetch_assoc()) {
        // INTENTAMOS DETECTAR LOS NOMBRES DE LAS COLUMNAS AUTOMÁTICAMENTE
        // (Porque a veces se llaman 'Nombre', 'Nombres', 'Usuario', 'Nick', etc.)
        
        $nombre = isset($fila['Nombre']) ? $fila['Nombre'] : (isset($fila['Nombres']) ? $fila['Nombres'] : 'Desconocido');
        $usuario = isset($fila['Usuario']) ? $fila['Usuario'] : (isset($fila['Nick']) ? $fila['Nick'] : 'user_'.$fila['Id']);
        
        // Buscamos la clave en columnas comunes
        $clave_plana = '';
        if(isset($fila['Clave'])) $clave_plana = $fila['Clave'];
        elseif(isset($fila['Password'])) $clave_plana = $fila['Password'];
        elseif(isset($fila['Contrasena'])) $clave_plana = $fila['Contrasena'];
        
        $perfil = isset($fila['Perfil']) ? $fila['Perfil'] : 'OPERARIO';

        // Mapeo de Roles
        $rol_viejo = strtoupper($perfil);
        $rol_nuevo = (strpos($rol_viejo, 'ADMIN') !== false) ? 'ADMIN' : 'OPERARIO';

        // --- ENCRIPTACIÓN DE SEGURIDAD ---
        if (!empty($clave_plana)) {
            $clave_segura = password_hash($clave_plana, PASSWORD_DEFAULT);

            // 3. INSERTAR EN LA TABLA NUEVA 'usuarios'
            $sql_insert = "INSERT IGNORE INTO usuarios (nombre, usuario, password, rol) 
                           VALUES ('$nombre', '$usuario', '$clave_segura', '$rol_nuevo')";

            if ($conn->query($sql_insert)) {
                echo "<li>✅ Usuario <b>$usuario</b> ($nombre) migrado correctamente.</li>";
            } else {
                echo "<li>⚠️ Error con $usuario: " . $conn->error . "</li>";
            }
        } else {
            echo "<li>⚠️ Saltando registro sin contraseña detectada.</li>";
        }
    }
    echo "</ul>";
    echo "<h2>✨ ¡Migración Completada! Intenta hacer Login ahora.</h2>";
} else {
    echo "La tabla '$tabla_vieja' existe pero está vacía.";
}
?>
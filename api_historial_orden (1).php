<?php
// api_historial.php
// Devuelve las filas de la tabla de turnos para una orden específica
// CORRECCIÓN: Alineado con ver_registros.php (Usa OperarioId)

header('Content-Type: text/html; charset=utf-8');
// Activar errores para depuración si es necesario
// error_reporting(E_ALL); ini_set('display_errors', 1);

include 'conexion.php';

if (isset($_GET['orden'])) {
    $orden = $conn->real_escape_string($_GET['orden']);
    
    // Buscamos los turnos de esta orden en 'produccion_turnos'
    // IMPORTANTE: Usamos T.OperarioId (PascalCase) para coincidir con ver_registros.php
    $sql = "SELECT T.*, U.nombre as NombreOperario 
            FROM produccion_turnos T 
            LEFT JOIN usuarios U ON T.OperarioId = U.id 
            WHERE T.NumeroOrden = '$orden' 
            ORDER BY T.Fecha DESC, T.HoraInicio DESC LIMIT 10";
            
    $res = $conn->query($sql);
    
    if ($res && $res->num_rows > 0) {
        while($row = $res->fetch_assoc()) {
            // Formatear turno
            $nomTurno = "T-" . $row['NumeroTurno'];
            if($row['NumeroTurno'] == 1) $nomTurno = "Mañana";
            if($row['NumeroTurno'] == 2) $nomTurno = "Tarde";
            if($row['NumeroTurno'] == 3) $nomTurno = "Noche";
            
            // Semáforo simple para indicar calidad
            $estiloFila = "";
            if(isset($row['Malas']) && $row['Malas'] > 0) {
                // Un poco rojo si hubo malas
                // $estiloFila = "background-color: #fff5f5;"; 
            }

            echo "<tr style='$estiloFila'>";
            // Fecha corta
            echo "<td>" . date("d/M", strtotime($row['Fecha'])) . "</td>";
            echo "<td>" . $nomTurno . "</td>";
            echo "<td><b>#" . $row['NumeroOrden'] . "</b></td>";
            
            // Nombre operario o ID si no se encuentra
            $nombreOp = !empty($row['NombreOperario']) ? $row['NombreOperario'] : "Op.".$row['OperarioId'];
            echo "<td>" . substr($nombreOp, 0, 15) . "</td>";
            
            echo "<td style='color:green; font-weight:bold; text-align:center;'>" . number_format($row['Buenas']) . "</td>";
            echo "<td style='text-align:center;'><a href='gestion_turno.php?editar=" . $row['id'] . "' style='color:#ffc107; font-size:1.2em; text-decoration:none;' title='Corregir'>&#9998;</a></td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='6' style='padding:20px; color:#999; font-style:italic; text-align:center;'>No hay turnos registrados para la Orden #$orden.</td></tr>";
        // Si hay error SQL, lo ponemos como comentario HTML para que puedas verlo en 'Inspeccionar Elemento'
        if (!$res) {
            echo "<!-- Error SQL: " . $conn->error . " -->";
        }
    }
}
?>
<?php
// api_orden.php
// UBICACIÓN: public_html/sistema/api_orden.php
// MEJORA: Trae la HORA FINAL anterior para encadenar turnos

header('Content-Type: application/json; charset=utf-8');
include 'conexion.php';

$response = ['success' => false, 'msj' => 'Orden no encontrada'];

if (isset($_GET['orden'])) {
    $orden = $conn->real_escape_string($_GET['orden']);
    
    // 1. Buscamos en produccion_master
    $sql = "SELECT Producto, Referencia, Cantidad, TiempoEstimado, PesoUnidad 
            FROM produccion_master 
            WHERE NumeroOrden = '$orden' LIMIT 1";
            
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $data_orden = $result->fetch_assoc();
        
        // 2. Buscar colores
        $colores = [];
        $q_col = $conn->query("SELECT Color FROM produccion_paquetes WHERE NumeroOrden = '$orden'");
        if ($q_col) {
            while($c = $q_col->fetch_assoc()) {
                if(!empty($c['Color'])) $colores[] = $c['Color'];
            }
        }
        $colores = array_unique($colores);
        
        // 3. BUSCAR ÚLTIMO TURNO (CONTINUIDAD)
        $ultimo_contador = 0;
        $ultimo_sobrante = 0;
        $ultima_hora_fin = ""; // Variable para la hora
        
        $sql_last = "SELECT ContadorFinal, SobrantesFinal, HoraFinal 
                     FROM produccion_turnos 
                     WHERE NumeroOrden = '$orden' 
                     ORDER BY Fecha DESC, HoraInicio DESC LIMIT 1";
        $res_last = $conn->query($sql_last);
        
        if ($res_last && $res_last->num_rows > 0) {
            $last_data = $res_last->fetch_assoc();
            $ultimo_contador = intval($last_data['ContadorFinal']);
            $ultimo_sobrante = intval($last_data['SobrantesFinal']);
            // Formato HH:MM para el input type="time"
            if (!empty($last_data['HoraFinal'])) {
                $ultima_hora_fin = substr($last_data['HoraFinal'], 0, 5);
            }
        }
        
        // 4. Datos técnicos
        $ciclo = 0;
        if(floatval($data_orden['Cantidad']) > 0) {
            $ciclo = floatval($data_orden['TiempoEstimado']) / floatval($data_orden['Cantidad']);
        }
        
        // Datos extra
        $ref = $data_orden['Referencia'];
        $empaque = 0;
        $bolsa_size = "N/A";
        $imagen_url = "imagenes_productos/sin_imagen.png"; 
        
        // Buscar Imagen física
        $posibles_ext = ['.jpg', '.png', '.jpeg', '.gif', '.JPG', '.PNG'];
        $encontro_img = false;
        foreach ($posibles_ext as $ext) {
            $ruta_prueba = "imagenes_productos/" . $ref . $ext;
            if (file_exists($ruta_prueba)) {
                $imagen_url = $ruta_prueba;
                $encontro_img = true;
                break;
            }
        }

        $q_p = $conn->query("SELECT Ciclo, Empaque, Bolsa, Imagen FROM productos WHERE Referencia = '$ref' LIMIT 1");
        if($q_p && $q_p->num_rows > 0) {
            $row_p = $q_p->fetch_assoc();
            if($ciclo == 0) $ciclo = floatval($row_p['Ciclo']);
            $empaque = intval($row_p['Empaque']);
            if (!empty($row_p['Bolsa'])) $bolsa_size = $row_p['Bolsa'];
            
            if (!$encontro_img && !empty($row_p['Imagen'])) {
                $ruta_bd = "imagenes_productos/" . $row_p['Imagen'];
                if (file_exists($ruta_bd)) {
                    $imagen_url = $ruta_bd;
                }
            }
        }

        $response = [
            'success' => true,
            'producto' => $data_orden['Producto'],
            'referencia' => $data_orden['Referencia'],
            'ciclo' => number_format($ciclo, 2, '.', ''),
            'empaque' => $empaque,
            'bolsa' => $bolsa_size, 
            'peso_unidad' => floatval($data_orden['PesoUnidad']),
            'imagen' => $imagen_url, 
            'colores' => array_values($colores),
            // DATOS DE CONTINUIDAD:
            'ultimo_contador' => $ultimo_contador,
            'ultimo_sobrante' => $ultimo_sobrante,
            'ultima_hora' => $ultima_hora_fin // Nueva variable
        ];
    }
}

echo json_encode($response);
?>
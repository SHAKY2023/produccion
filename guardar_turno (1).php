<?php
// guardar_turno.php
// VERSIÓN BLINDADA: Validación y manejo de errores mejorado

header('Content-Type: text/html; charset=utf-8');
// Para producción, oculta errores visuales y usa logs
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/errores_sistema.log');

include 'auth.php';
include 'conexion.php';

// Funciones de limpieza
function cleanFloat($val) { return isset($val) && is_numeric($val) ? floatval($val) : 0; }
function cleanInt($val) { return isset($val) && is_numeric($val) ? intval($val) : 0; }
function cleanStr($conn, $val) { return isset($val) ? $conn->real_escape_string($val) : ''; }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Verificar CSRF
    verificarToken();

    $id_turno = cleanInt($_POST['id_turno']);

    // Datos Básicos
    $orden = "";
    if(!empty($_POST['orden'])) $orden = cleanStr($conn, $_POST['orden']);
    elseif(!empty($_POST['orden_select'])) $orden = cleanStr($conn, $_POST['orden_select']);

    $turno = cleanInt($_POST['turno']);
    $fecha = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');
    $obs = cleanStr($conn, $_POST['observaciones']);
    $operario_id = $_SESSION['usuario_id'];
    
    // Tiempos
    $h_inicio = isset($_POST['h_inicio']) ? $_POST['h_inicio'] : '00:00';
    $h_final = isset($_POST['h_final']) ? $_POST['h_final'] : '00:00';
    $total_seg = cleanFloat($_POST['total_seg']);
    $ciclo_real = cleanFloat($_POST['ciclo_real']);

    // Contadores
    $cont_ini = cleanFloat($_POST['cont_ini']);
    $cont_fin = cleanFloat($_POST['cont_fin']);
    $fisicas = isset($_POST['u_fisicas']) ? cleanFloat($_POST['u_fisicas']) : ($cont_fin - $cont_ini);
    
    // Malas
    $total_malas_desglose = 0;
    if (isset($_POST['motivos_cant']) && is_array($_POST['motivos_cant'])) {
        foreach($_POST['motivos_cant'] as $cant) {
            $total_malas_desglose += intval($cant);
        }
    }
    $malas_manual = cleanFloat($_POST['malas']);
    $malas = ($total_malas_desglose > 0) ? $total_malas_desglose : $malas_manual;
    
    $sob_ini = cleanFloat($_POST['sob_ini']);
    $sob_fin = cleanFloat($_POST['sob_fin']);
    
    // Buenas
    $buenas = ($fisicas - $malas) + $sob_ini - $sob_fin;
    
    // Bolsas
    $peso_bolsa_u = cleanFloat($_POST['peso_bolsa_unitario']);
    $cant_bolsas = cleanInt($_POST['cantidad_bolsas']);
    $consumo_bolsa = cleanFloat($_POST['consumo_bolsa']);
    $diferencia = cleanFloat($_POST['diferencia']); 
    
    // Nuevos campos (si se usan en el formulario, aquí no se usaron en el último form de gestion_turno)
    $color_prod = ''; // Ya no se usa en la tabla maestra
    $peso_paq = 0;    // Ya no se usa en la tabla maestra

    if (empty($orden) || empty($fecha)) {
        die("<h2 style='color:red'>Error: Faltan datos obligatorios.</h2>");
    }

    try {
        // 1. GUARDAR/ACTUALIZAR TURNO
        if ($id_turno > 0) {
            $sql = "UPDATE produccion_turnos SET 
                    NumeroOrden=?, NumeroTurno=?, Fecha=?, OperarioId=?,
                    HoraInicio=?, HoraFinal=?, TiempoTotalSeg=?, CicloReal=?,
                    ContadorInicio=?, ContadorFinal=?, UnidadesFisicas=?,
                    Malas=?, Buenas=?,
                    SobrantesInicio=?, SobrantesFinal=?,
                    PesoBolsaUnitario=?, CantidadBolsas=?, PesoBolsaUsado=?,
                    DiferenciaUnidades=?, Observaciones=?
                    WHERE id=?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisissddiiiiiiidddisi", 
                $orden, $turno, $fecha, $operario_id,
                $h_inicio, $h_final, $total_seg, $ciclo_real,
                $cont_ini, $cont_fin, $fisicas,
                $malas, $buenas,
                $sob_ini, $sob_fin,
                $peso_bolsa_u, $cant_bolsas, $consumo_bolsa,
                $diferencia, $obs, $id_turno
            );
            $stmt->execute();
            $accion_log = "EDITAR_TURNO";
        } else {
            $sql = "INSERT INTO produccion_turnos 
            (NumeroOrden, NumeroTurno, Fecha, OperarioId,
             HoraInicio, HoraFinal, TiempoTotalSeg, CicloReal,
             ContadorInicio, ContadorFinal, UnidadesFisicas, Malas, Buenas, 
             SobrantesInicio, SobrantesFinal, 
             PesoBolsaUnitario, CantidadBolsas, PesoBolsaUsado, 
             DiferenciaUnidades, Observaciones) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
             
             $stmt = $conn->prepare($sql);
             $stmt->bind_param("sisissddiiiiiiidddis", 
                $orden, $turno, $fecha, $operario_id,
                $h_inicio, $h_final, $total_seg, $ciclo_real,
                $cont_ini, $cont_fin, $fisicas,
                $malas, $buenas,
                $sob_ini, $sob_fin, 
                $peso_bolsa_u, $cant_bolsas, $consumo_bolsa, 
                $diferencia, $obs
             );
             $stmt->execute();
             $id_turno = $conn->insert_id;
             $accion_log = "CREAR_TURNO";
        }
        
        registrar_log($conn, $accion_log, "Turno ID $id_turno para Orden #$orden.");

        // 2. GUARDAR PAQUETES
        $conn->query("DELETE FROM produccion_turnos_paquetes WHERE id_turno = $id_turno");
        if (isset($_POST['paq_color']) && is_array($_POST['paq_color'])) {
            $paq_colores = $_POST['paq_color'];
            $paq_pesos = $_POST['paq_peso'];
            $sql_paqs = "INSERT INTO produccion_turnos_paquetes (id_turno, NumeroPaquete, Color, Peso) VALUES ";
            $valores = [];
            for ($i = 0; $i < count($paq_colores); $i++) {
                $num = $i + 1;
                $col = $conn->real_escape_string($paq_colores[$i]);
                $pes = floatval($paq_pesos[$i]);
                if (!empty($col)) {
                    $valores[] = "($id_turno, $num, '$col', '$pes')";
                }
            }
            if (count($valores) > 0) {
                $conn->query($sql_paqs . implode(", ", $valores));
            }
        }

        // 3. GUARDAR CALIDAD
        $conn->query("DELETE FROM produccion_malas WHERE id_turno = $id_turno");
        if (isset($_POST['motivos_id']) && is_array($_POST['motivos_id'])) {
            $motivos_ids = $_POST['motivos_id'];
            $motivos_cants = $_POST['motivos_cant'];
            $sql_desp = "INSERT INTO produccion_malas (id_turno, NumeroOrden, NumeroTurno, id_concepto, Cantidad) VALUES ";
            $valores_d = [];
            for ($j = 0; $j < count($motivos_ids); $j++) {
                $id_con = intval($motivos_ids[$j]);
                $cant_con = intval($motivos_cants[$j]);
                if ($id_con > 0 && $cant_con > 0) {
                    $valores_d[] = "($id_turno, '$orden', '$turno', $id_con, $cant_con)";
                }
            }
            if (count($valores_d) > 0) {
                $conn->query($sql_desp . implode(", ", $valores_d));
            }
        }

        header("Location: ver_registros.php?mensaje=guardado");
        
    } catch (Exception $e) {
        error_log("Error SQL: " . $e->getMessage());
        echo "<h2 style='color:red'>Error al guardar. Intente nuevamente.</h2>";
        echo "<p>Detalle técnico (solo admin): " . $e->getMessage() . "</p>";
        echo "<a href='gestion_turno.php'>Volver</a>";
    }
}
?>
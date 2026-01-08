<?php
// public_html/sistema/gestion_turno.php - Versión: v3.25 (FEATURE: TRIGGERS AUTOMÁTICOS DE ESTADO)
// Fecha: 13-12-2025 -> Act: 06-01-2026
// MEJORAS ACUMULADAS:
// ... (versiones anteriores)
// 15. MEJORA v3.23: Auditoría y Creación dinámica.
// 16. FIX v3.24: Limpieza de auto-schema.
// 17. MEJORA v3.25: Triggers automáticos para cambiar estado de orden (Pendiente->Prod, Meta->Finalizada).

// --- INICIO DE BUFFER (Vital para redirecciones) ---
ob_start();

// --------------------------------------------------------------------------
// 0. MICRO-CONTROLADOR AJAX
// --------------------------------------------------------------------------
if (isset($_POST['ajax_action'])) {
    include 'conexion.php';
    
    // ACCIÓN 1: CREAR CAUSA DE TIEMPO
    if ($_POST['ajax_action'] == 'crear_causa') {
        $nueva_causa = trim($_POST['nombre_causa']);
        $respuesta = ['status' => 'error', 'msg' => 'Nombre vacío'];
        
        if (!empty($nueva_causa)) {
            $check = $conn->query("SELECT id FROM conceptos_parada WHERE Concepto LIKE '$nueva_causa'");
            if ($check && $check->num_rows > 0) {
                $row = $check->fetch_assoc();
                $respuesta = ['status' => 'ok', 'id' => $row['id'], 'text' => $nueva_causa];
            } else {
                $stmt = $conn->prepare("INSERT INTO conceptos_parada (Concepto) VALUES (?)");
                $stmt->bind_param("s", $nueva_causa);
                if ($stmt->execute()) {
                    $respuesta = ['status' => 'ok', 'id' => $stmt->insert_id, 'text' => $nueva_causa];
                } else {
                    $respuesta = ['msg' => 'Error SQL: ' . $conn->error];
                }
            }
        }
        ob_clean(); header('Content-Type: application/json'); echo json_encode($respuesta); exit;
    }
    
    // ACCIÓN 2: CREAR DEFECTO DE CALIDAD
    if ($_POST['ajax_action'] == 'crear_defecto') {
        $nuevo_defecto = trim($_POST['nombre_defecto']);
        $respuesta = ['status' => 'error', 'msg' => 'Nombre vacío'];
        
        if (!empty($nuevo_defecto)) {
            $check = $conn->query("SELECT id FROM conceptos_malas WHERE Concepto LIKE '$nuevo_defecto'");
            if ($check && $check->num_rows > 0) {
                $row = $check->fetch_assoc();
                $respuesta = ['status' => 'ok', 'id' => $row['id'], 'text' => $nuevo_defecto];
            } else {
                $stmt = $conn->prepare("INSERT INTO conceptos_malas (Concepto) VALUES (?)");
                $stmt->bind_param("s", $nuevo_defecto);
                if ($stmt->execute()) {
                    $respuesta = ['status' => 'ok', 'id' => $stmt->insert_id, 'text' => $nuevo_defecto];
                } else {
                    $respuesta = ['msg' => 'Error SQL: ' . $conn->error];
                }
            }
        }
        ob_clean(); header('Content-Type: application/json'); echo json_encode($respuesta); exit;
    }
}

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'auth.php';
include 'conexion.php';

// --- AUTO-REPARACIÓN DE ESTRUCTURA (DB FIXES) ---
$check_legacy = $conn->query("SHOW COLUMNS FROM conceptos_parada LIKE 'Registro'");
if ($check_legacy && $check_legacy->num_rows > 0) {
    $conn->query("ALTER TABLE conceptos_parada CHANGE COLUMN Registro id INT AUTO_INCREMENT");
}
$check_cm = $conn->query("SHOW COLUMNS FROM produccion_turnos LIKE 'CicloMaquina'");
if ($check_cm && $check_cm->num_rows == 0) {
    $conn->query("ALTER TABLE produccion_turnos ADD COLUMN CicloMaquina DECIMAL(10,2) DEFAULT 0 AFTER CicloReal");
}

// Tablas Auxiliares
$conn->query("CREATE TABLE IF NOT EXISTS produccion_paquetes (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB");
$cols_paq = ['NumeroOrden' => 'VARCHAR(50)', 'NumeroTurno' => 'INT', 'Cantidad' => 'INT', 'Peso' => 'DECIMAL(10,2)', 'Color' => 'VARCHAR(50)'];
foreach($cols_paq as $col => $def) { if(!$conn->query("SELECT $col FROM produccion_paquetes LIMIT 1")) $conn->query("ALTER TABLE produccion_paquetes ADD COLUMN $col $def"); }

$conn->query("CREATE TABLE IF NOT EXISTS produccion_detenciones (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB");
$cols_det = ['id_turno' => 'INT', 'id_causa' => 'INT', 'minutos' => 'DECIMAL(10,2)'];
foreach($cols_det as $col => $def) { if(!$conn->query("SELECT $col FROM produccion_detenciones LIMIT 1")) $conn->query("ALTER TABLE produccion_detenciones ADD COLUMN $col $def"); }

$conn->query("CREATE TABLE IF NOT EXISTS produccion_malas (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB");
$cols_mal = ['id_turno' => 'INT', 'id_concepto' => 'INT', 'Cantidad' => 'INT'];
foreach($cols_mal as $col => $def) { if(!$conn->query("SELECT $col FROM produccion_malas LIMIT 1")) $conn->query("ALTER TABLE produccion_malas ADD COLUMN $col $def"); }

$conn->query("CREATE TABLE IF NOT EXISTS conceptos_parada (id INT AUTO_INCREMENT PRIMARY KEY, Concepto VARCHAR(100)) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS conceptos_malas (id INT AUTO_INCREMENT PRIMARY KEY, Concepto VARCHAR(100)) ENGINE=InnoDB");

// ----------------------------------------------------

$mensaje = "";
$orden_id = isset($_REQUEST['orden']) ? $conn->real_escape_string($_REQUEST['orden']) : '';
$id_nuevo_turno = 0;

// --- VARIABLES EDICIÓN ---
$is_editing = false;
$edit_data = [];
$paquetes_edit = [];
$malas_edit = [];
$tiempos_edit = [];

if (isset($_GET['editar'])) {
    $id_edit = intval($_GET['editar']);
    $res_e = $conn->query("SELECT * FROM produccion_turnos WHERE id = $id_edit");
    if ($res_e && $res_e->num_rows > 0) {
        $edit_data = $res_e->fetch_assoc();
        $orden_id = $edit_data['NumeroOrden'];
        $is_editing = true;
        
        $res_pq = $conn->query("SELECT * FROM produccion_paquetes WHERE NumeroTurno = $id_edit");
        while($p = $res_pq->fetch_assoc()) $paquetes_edit[] = $p;
        
        $res_ml = $conn->query("SELECT * FROM produccion_malas WHERE id_turno = $id_edit");
        while($m = $res_ml->fetch_assoc()) $malas_edit[] = $m;
        
        $res_tm = $conn->query("SELECT * FROM produccion_detenciones WHERE id_turno = $id_edit");
        while($t = $res_tm->fetch_assoc()) $tiempos_edit[] = $t;
    }
}

// --- CARGAR LISTAS ---
$lista_conceptos = [];
$res_conc = $conn->query("SELECT * FROM conceptos_malas ORDER BY Concepto ASC");
if($res_conc) { while($cc = $res_conc->fetch_assoc()) { $lista_conceptos[] = $cc; } }

$lista_paradas = [];
$res_par = $conn->query("SELECT * FROM conceptos_parada ORDER BY Concepto ASC");
if($res_par) { while($pp = $res_par->fetch_assoc()) { $lista_paradas[] = $pp; } }

$lista_operarios = [];
$res_op = $conn->query("SELECT id, nombre, usuario FROM usuarios ORDER BY nombre ASC");
if($res_op) { while($u = $res_op->fetch_assoc()) { $lista_operarios[] = $u; } }

// --- 1. PROCESAR GUARDADO CON TRANSACCIÓN ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['guardar_turno']) || isset($_POST['hidden_submit']))) {
    $orden_post = $_POST['num_orden'];
    
    // Validación Meta
    $sql_check = "SELECT M.Cantidad, M.Estado, (SELECT COALESCE(SUM(Buenas),0) FROM produccion_turnos WHERE NumeroOrden = '$orden_post') as Hecho FROM produccion_master M WHERE M.NumeroOrden = '$orden_post'";
    $res_check = $conn->query($sql_check);
    $check = $res_check->fetch_assoc();
    
    $meta_check = intval($check['Cantidad'] ?? 0);
    $hecho_check = intval($check['Hecho'] ?? 0);
    $estado_actual = $check['Estado'] ?? 'Pendiente';
    
    $es_update = isset($_POST['id_turno_editar']) && !empty($_POST['id_turno_editar']);
    
    if (!$es_update && $hecho_check >= $meta_check && $meta_check > 0) {
        $mensaje = "<div class='alert error'>⛔ <b>ORDEN CERRADA:</b> Meta cumplida.</div>";
    } else {
        $fecha = $_POST['fecha'];
        $turno_val = intval($_POST['turno_id']); 
        $numero_turno = ($turno_val >= 1 && $turno_val <= 3) ? $turno_val : 1;
        $operario_id = isset($_POST['operario_id']) ? intval($_POST['operario_id']) : 0;
        
        $h_inicio = $_POST['h_inicio'];
        $h_final = $_POST['h_final'];
        $ciclo_maquina = floatval($_POST['ciclo_maquina']); 
        $ciclo_real = floatval($_POST['ciclo_real']);        
        $total_seg = intval($_POST['total_seg']);
        
        $cont_ini = intval($_POST['cont_ini']);
        $cont_fin = intval($_POST['cont_fin']);
        $fisicas = $cont_fin - $cont_ini; 
        $malas = intval($_POST['malas']);
        $buenas = ($fisicas - $malas); 
        $sob_ini = intval($_POST['sob_ini']);
        $sob_fin = intval($_POST['sob_fin']);
        $peso_bolsa = floatval($_POST['peso_bolsa_unitario']);
        $cant_bolsas = intval($_POST['cantidad_bolsas']);
        $peso_bolsa_usado = floatval($_POST['consumo_bolsa']); 
        $obs = $conn->real_escape_string($_POST['observaciones']);
        
        // --- INICIO DE BLINDAJE: TRANSACCIÓN ---
        $conn->begin_transaction();
        
        try {
            if ($es_update) {
                // --- UPDATE ---
                $id_turno = intval($_POST['id_turno_editar']);
                $editor_id = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
                $fecha_edicion = date('Y-m-d H:i:s');
                
                $campos_update = "NumeroOrden='$orden_post', NumeroTurno=$numero_turno, Fecha='$fecha', Operariold=$operario_id, HoraInicio='$h_inicio', HoraFinal='$h_final', TiempoTotalSeg=$total_seg, CicloReal=$ciclo_real, CicloMaquina='$ciclo_maquina', ContadorInicio=$cont_ini, ContadorFinal=$cont_fin, UnidadesFisicas=$fisicas, Buenas=$buenas, Malas=$malas, SobrantesInicio=$sob_ini, SobrantesFinal=$sob_fin, PesoBolsaUnitario=$peso_bolsa, CantidadBolsas=$cant_bolsas, PesoBolsaUsado=$peso_bolsa_usado, Observaciones='$obs', ultimo_editor=$editor_id, fecha_edicion='$fecha_edicion'";
                
                if (!$conn->query("UPDATE produccion_turnos SET $campos_update WHERE id=$id_turno")) {
                    throw new Exception("Error al actualizar turno: " . $conn->error);
                }
                
                $conn->query("DELETE FROM produccion_paquetes WHERE NumeroTurno=$id_turno");
                $conn->query("DELETE FROM produccion_malas WHERE id_turno=$id_turno");
                $conn->query("DELETE FROM produccion_detenciones WHERE id_turno=$id_turno");
                
                $msg_status = "updated";
                
            } else {
                // --- INSERT ---
                $sql_flood = "SELECT id FROM produccion_turnos WHERE NumeroOrden = '$orden_post' AND Fecha = '$fecha' AND NumeroTurno = $numero_turno AND Operariold = $operario_id AND HoraInicio = '$h_inicio' AND ContadorFinal = $cont_fin LIMIT 1";
                $res_flood = $conn->query($sql_flood);
                if ($res_flood && $res_flood->num_rows > 0) throw new Exception("SILENT_DUPLICATE");

                $campos_sql = "NumeroOrden, NumeroTurno, Fecha, Operariold, HoraInicio, HoraFinal, TiempoTotalSeg, CicloReal, CicloMaquina, ContadorInicio, ContadorFinal, UnidadesFisicas, Buenas, Malas, SobrantesInicio, SobrantesFinal, PesoBolsaUnitario, CantidadBolsas, PesoBolsaUsado, Observaciones";
                $valores_sql = "'$orden_post', $numero_turno, '$fecha', $operario_id, '$h_inicio', '$h_final', $total_seg, $ciclo_real, '$ciclo_maquina', $cont_ini, $cont_fin, $fisicas, $buenas, $malas, $sob_ini, $sob_fin, $peso_bolsa, $cant_bolsas, $peso_bolsa_usado, '$obs'";

                if (!$conn->query("INSERT INTO produccion_turnos ($campos_sql) VALUES ($valores_sql)")) {
                    throw new Exception("Error al insertar turno: " . $conn->error);
                }
                $id_turno = $conn->insert_id;
                $msg_status = "saved";
            }
            
            $id_nuevo_turno = $id_turno; 
            
            // Paquetes
            if (isset($_POST['paq_color']) && is_array($_POST['paq_color'])) {
                $stmt_paq = $conn->prepare("INSERT INTO produccion_paquetes (NumeroOrden, NumeroTurno, Cantidad, Peso, Color) VALUES (?, ?, ?, ?, ?)");
                foreach ($_POST['paq_color'] as $k => $col) {
                    $p_peso = floatval($_POST['paq_peso'][$k] ?? 0);
                    $cant_p = intval($_POST['empaque_std']);
                    
                    if(!empty($col) && $p_peso > 0 && $cant_p > 1) {
                        $stmt_paq->bind_param("siids", $orden_post, $id_turno, $cant_p, $p_peso, $col);
                        if (!$stmt_paq->execute()) throw new Exception("Error al insertar paquete.");
                    }
                }
            }

            // Malas
            if ($malas > 0 && isset($_POST['motivos_id'])) {
                $stmt_malas = $conn->prepare("INSERT INTO produccion_malas (id_turno, id_concepto, Cantidad) VALUES (?, ?, ?)");
                foreach ($_POST['motivos_id'] as $k => $mid) {
                    $mcant = intval($_POST['motivos_cant'][$k]);
                    if ($mid > 0 && $mcant > 0) {
                        $stmt_malas->bind_param("iii", $id_turno, $mid, $mcant);
                        if (!$stmt_malas->execute()) throw new Exception("Error al insertar calidad.");
                    }
                }
            }

            // Tiempos
            if (isset($_POST['detencion_id'])) {
                $stmt_det = $conn->prepare("INSERT INTO produccion_detenciones (id_turno, id_causa, minutos) VALUES (?, ?, ?)");
                foreach ($_POST['detencion_id'] as $k => $did) {
                    $dmin = floatval($_POST['detencion_min'][$k]);
                    if ($did > 0 && $dmin > 0) {
                        $stmt_det->bind_param("iid", $id_turno, $did, $dmin);
                        if (!$stmt_det->execute()) throw new Exception("Error al insertar tiempos.");
                    }
                }
            }
            
            // --- TRIGGER AUTOMÁTICO DE ESTADOS (MEJORA v3.25) ---
            $change_msg = "";
            
            // 1. INICIO: Si estaba Pendiente, pasar a En Producción
            if ($estado_actual == 'Pendiente') {
                $conn->query("UPDATE produccion_master SET Estado = 'En Produccion' WHERE NumeroOrden = '$orden_post'");
                $change_msg .= "&st=started";
            }
            
            // 2. CIERRE: Si cumplió meta, pasar a Finalizada
            // Recalcular total acumulado incluyendo este nuevo turno
            $sql_new_total = "SELECT SUM(Buenas) as T_Buenas FROM produccion_turnos WHERE NumeroOrden = '$orden_post'";
            $row_new = $conn->query($sql_new_total)->fetch_assoc();
            $new_total_hecho = intval($row_new['T_Buenas']);
            
            if ($meta_check > 0 && $new_total_hecho >= $meta_check && $estado_actual != 'Finalizada') {
                $conn->query("UPDATE produccion_master SET Estado = 'Finalizada' WHERE NumeroOrden = '$orden_post'");
                $change_msg = "&st=finished"; // Sobrescribe started si pasa ambas cosas a la vez
            }
            // -----------------------------------------------------
            
            $conn->commit();
            header("Location: gestion_turno.php?orden=" . $orden_post . "&status=" . $msg_status . "&new_id=" . $id_turno . $change_msg);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            if ($e->getMessage() == "SILENT_DUPLICATE") {
                header("Location: gestion_turno.php?orden=" . $orden_post . "&status=saved");
                exit;
            } else {
                $mensaje = "<div class='alert error'>❌ Error: " . $e->getMessage() . "</div>";
            }
        }
    }
    $orden_id = $orden_post;
}

// --- MENSAJE FLASH ---
if(isset($_GET['status'])) {
    $nid = isset($_GET['new_id']) ? intval($_GET['new_id']) : 0;
    $extra_msg = "";
    
    // Feedback de cambio de estado (v3.25)
    if(isset($_GET['st'])) {
        if($_GET['st'] == 'started') $extra_msg = "<br>🚀 <b>¡Orden Iniciada!</b> Estado cambiado a 'En Producción'.";
        if($_GET['st'] == 'finished') $extra_msg = "<br>🏆 <b>¡Felicidades!</b> Meta cumplida. Orden Finalizada.";
    }

    if($_GET['status'] == 'saved') $mensaje = "<div class='alert success'>✅ <b>ÉXITO:</b> Turno <b>#$nid</b> registrado correctamente.$extra_msg</div>";
    if($_GET['status'] == 'updated') $mensaje = "<div class='alert success'>✅ <b>ACTUALIZADO:</b> Turno <b>#$nid</b> modificado correctamente.</div>";
}

// --- 2. SELECCIÓN DE ORDEN ---
if (empty($orden_id)) {
    $lista_busqueda = [];
    $sql_data = "SELECT NumeroOrden, Producto, Referencia FROM produccion_master ORDER BY NumeroOrden DESC LIMIT 100";
    $res_data = $conn->query($sql_data);
    if($res_data) { while($r = $res_data->fetch_assoc()) {
            $lista_busqueda[] = [ 'id' => $r['NumeroOrden'], 'display' => "<strong>#" . $r['NumeroOrden'] . "</strong> - " . $r['Producto'], 'search_text' => strtolower($r['NumeroOrden'] . " " . $r['Producto']) ];
    } }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Orden - Gestión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding-bottom: 50px; }
        .header-bar { background: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container-select { padding: 40px 20px; max-width: 600px; margin: 0 auto; width:100%; position: relative; }
        .card { background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 30px; text-align: center; }
        h2 { color: #333; margin-bottom: 20px; }
        .search-wrapper { position: relative; width: 100%; margin-bottom: 15px; }
        .input-orden { padding: 15px 15px 15px 45px; font-size: 1.1em; width: 100%; border: 2px solid #ddd; border-radius: 8px; box-sizing: border-box; transition:0.3s; }
        .input-orden:focus { border-color: #0056b3; outline: none; box-shadow: 0 0 0 3px rgba(0,86,179,0.1); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; font-size: 1.2em; }
        .results-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 0 0 8px 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-height: 250px; overflow-y: auto; z-index: 100; text-align: left; display: none; }
        .result-item { padding: 12px 15px; border-bottom: 1px solid #f1f1f1; cursor: pointer; transition: 0.2s; font-size: 0.95em; color: #444; }
        .result-item:hover { background-color: #f0f7ff; color: #0056b3; }
        .btn-go { background: #0056b3; color: white; border: none; padding: 12px 25px; font-size: 1.1em; border-radius: 8px; cursor: pointer; width: 100%; font-weight: bold; transition:0.3s; }
        .btn-go:hover { background: #004494; }
        .recent-list { margin-top: 40px; text-align: left; }
        .recent-list h4 { color: #666; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        .recent-item-row { display: flex; justify-content: space-between; align-items: center; padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: 0.2s; color: #555; text-decoration: none; }
        .recent-item-row:hover { background-color: #f8f9fa; color: #0056b3; transform: translateX(5px); }
        .recent-icon { background: #e9ecef; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #666; font-size: 0.8em; margin-right: 10px; }
    </style>
    <script>
        const dbOrdenes = <?php echo json_encode($lista_busqueda); ?>;
        function filtrarOrdenes(input) {
            const val = input.value.toLowerCase().trim();
            const lista = document.getElementById('lista_resultados');
            lista.innerHTML = ''; 
            if (val.length === 0) { lista.style.display = 'none'; return; }
            const matches = dbOrdenes.filter(o => o.search_text.includes(val));
            if (matches.length > 0) {
                matches.forEach(m => {
                    const div = document.createElement('div');
                    div.className = 'result-item';
                    div.innerHTML = m.display;
                    div.onclick = function() { window.location.href = 'gestion_turno.php?orden=' + m.id; };
                    lista.appendChild(div);
                });
                lista.style.display = 'block';
            } else {
                lista.innerHTML = '<div class="result-item" style="cursor:default; color:#999;">No se encontraron coincidencias.</div>';
                lista.style.display = 'block';
            }
        }
        document.addEventListener('click', function(e) {
            if (!document.querySelector('.search-wrapper').contains(e.target)) { document.getElementById('lista_resultados').style.display = 'none'; }
        });
    </script>
</head>
<body>
    <div class="header-bar">
        <div><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
        <a href="dashboard.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-house"></i> Inicio</a>
    </div>
    <div class="container-select">
        <div class="card">
            <h2><i class="fa-solid fa-clipboard-user"></i> Gestión de Turnos</h2>
            <p style="color:#666; margin-bottom:20px;">Busca la orden por <b>Número, Producto o Referencia</b>:</p>
            <form action="gestion_turno.php" method="GET" autocomplete="off">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" name="orden" class="input-orden" placeholder="Ej: 6480, Maceta, Ref-50..." oninput="filtrarOrdenes(this)" required autofocus>
                    <div id="lista_resultados" class="results-dropdown"></div>
                </div>
                <button type="submit" class="btn-go">Ir a la Orden <i class="fa-solid fa-arrow-right"></i></button>
            </form>
            <div class="recent-list">
                <h4><i class="fa-solid fa-clock-rotate-left"></i> Órdenes Activas</h4>
                <?php $count = 0; foreach($lista_busqueda as $item): if($count >= 6) break; ?>
                    <a href="gestion_turno.php?orden=<?php echo $item['id']; ?>" class="recent-item-row">
                        <div style="display:flex; align-items:center;"><div class="recent-icon"><i class="fa-solid fa-hashtag"></i></div><div><?php echo $item['display']; ?></div></div>
                        <i class="fa-solid fa-chevron-right" style="font-size:0.8em; color:#ccc;"></i>
                    </a>
                <?php $count++; endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php exit; }

// --- 3. CARGAR DATOS ---
$sql_master = "SELECT M.*, P.Empaque, P.Ciclo, P.Peso FROM produccion_master M LEFT JOIN productos P ON M.Referencia = P.Referencia WHERE M.NumeroOrden = '$orden_id'";
$res_master = $conn->query($sql_master);
if (!$res_master || $res_master->num_rows == 0) die("Orden no encontrada.");
$orden = $res_master->fetch_assoc();

// --- VALIDACIÓN DE EMPAQUE (Evitar 1u fantasmas) ---
$empaque_std = intval($orden['Empaque']); 
$aviso_empaque = "";
if($empaque_std <= 0) {
    $empaque_std = 1; 
    $aviso_empaque = "<div style='background:#fff3cd; color:#856404; padding:5px; font-size:0.8em; border-radius:3px; margin-top:5px;'><i class='fa-solid fa-triangle-exclamation'></i> OJO: Empaque no definido en producto. Se usará 1.</div>";
}
$peso_producto_g = floatval($orden['Peso']); 
$ciclo_std = floatval($orden['Ciclo']);

// --- NUEVO: CÁLCULOS KPI ACUMULADOS ---
$sql_kpi = "SELECT SUM(Buenas) as T_Buenas, SUM(Malas) as T_Malas, SUM(TiempoTotalSeg) as T_Seg FROM produccion_turnos WHERE NumeroOrden = '$orden_id'";
$row_kpi = $conn->query($sql_kpi)->fetch_assoc();
$total_hecho = intval($row_kpi['T_Buenas']);
$total_malas = intval($row_kpi['T_Malas']);
// -------------------------------------

$meta_num = (float)$orden['Cantidad'];
$porcentaje = ($meta_num > 0) ? round(($total_hecho / $meta_num) * 100) : 0;

// KPI Calidad
$total_prod = $total_hecho + $total_malas;
$tasa_calidad = ($total_prod > 0) ? round(($total_hecho / $total_prod) * 100, 1) : 100;

$orden_finalizada = ($total_hecho >= $meta_num && $meta_num > 0);
$disabled_attr = $orden_finalizada ? "disabled" : "";
$style_block = $orden_finalizada ? "opacity: 0.6; pointer-events: none; background-color: #f8f9fa;" : "";
$aviso_final = $orden_finalizada ? "<div style='background:#d4edda; color:#155724; border:1px solid #c3e6cb; padding:15px; border-radius:5px; margin-bottom:20px; font-weight:bold; text-align:center;'><i class='fa-solid fa-circle-check'></i> ¡ORDEN FINALIZADA!</div>" : "";

$sql_prev = "SELECT ContadorFinal, SobrantesFinal, HoraFinal FROM produccion_turnos WHERE NumeroOrden = '$orden_id' ORDER BY id DESC LIMIT 1";
$res_prev = $conn->query($sql_prev);
$cont_sugerido = 0; $sob_sugerido = 0; $hora_sugerida = "";
if($res_prev && $res_prev->num_rows > 0) {
    $fila_prev = $res_prev->fetch_assoc();
    $cont_sugerido = $fila_prev['ContadorFinal'];
    $sob_sugerido = isset($fila_prev['SobrantesFinal']) ? $fila_prev['SobrantesFinal'] : 0; 
    $hora_sugerida = isset($fila_prev['HoraFinal']) ? $fila_prev['HoraFinal'] : ''; 
}

// Filtro Colores
$colores_orden = [];
$sql_mezclas = "SELECT Color FROM produccion_mezclas WHERE NumeroOrden = '$orden_id'";
$res_mezclas = $conn->query($sql_mezclas);
if($res_mezclas && $res_mezclas->num_rows > 0) {
    while($m = $res_mezclas->fetch_assoc()) $colores_orden[] = $m['Color'];
    $lista_colores_final = $colores_orden;
} else {
    $lista_colores_global = [];
    $res_col = $conn->query("SELECT Color FROM colores ORDER BY Color ASC");
    if($res_col) { while($c = $res_col->fetch_assoc()) $lista_colores_global[] = $c['Color']; }
    $lista_colores_final = $lista_colores_global;
}

// --- HISTORIAL (CORREGIDO CON AUDITORÍA Y TRAZABILIDAD) ---
// FIX v3.23: Se une con la tabla usuarios (Alias Editor) para ver quién editó.
$sql_historial = "SELECT T.*, U.nombre as NombreOperario, E.nombre as NombreEditor, P.Ciclo as CicloStd,
                  (SELECT GROUP_CONCAT(CONCAT(cantidad, 'u ', Color) SEPARATOR '<br>') 
                   FROM produccion_paquetes WHERE NumeroTurno = T.id AND NumeroOrden = T.NumeroOrden AND Cantidad > 1) as DetallePaquetes,
                  (SELECT COUNT(*) FROM produccion_paquetes WHERE NumeroTurno = T.id AND NumeroOrden = T.NumeroOrden AND Cantidad > 1) as NumPaquetes,
                  (SELECT GROUP_CONCAT(CONCAT(CD.Concepto, ': ', PD.minutos, 'm') SEPARATOR '<br>')
                   FROM produccion_detenciones PD 
                   LEFT JOIN conceptos_parada CD ON PD.id_causa = CD.id 
                   WHERE PD.id_turno = T.id) as DetalleTiempos,
                   T.CicloReal 
                  FROM produccion_turnos T 
                  LEFT JOIN usuarios U ON T.Operariold = U.id
                  LEFT JOIN usuarios E ON T.ultimo_editor = E.id
                  LEFT JOIN produccion_master M ON T.NumeroOrden = M.NumeroOrden
                  LEFT JOIN productos P ON M.Referencia = P.Referencia
                  WHERE T.NumeroOrden = '$orden_id' 
                  ORDER BY T.Fecha DESC, T.id DESC";

$res_historial = $conn->query($sql_historial);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turno Orden #<?php echo $orden_id; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Select2 (Buscador) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Librería HTML2PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- Librería CHART.JS (NUEVO) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding-bottom: 50px; }
        .header-bar { background: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
        .card-header { background: #fff; padding: 15px; border-bottom: 1px solid #eee; font-weight: bold; color: #333; display:flex; justify-content:space-between; }
        .card-body { padding: 20px; }

        .progress-bg { background: #e9ecef; height: 10px; border-radius: 5px; overflow: hidden; margin: 10px 0; }
        .progress-fill { background: #28a745; height: 100%; width: <?php echo $porcentaje; ?>%; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
        @media(max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
        
        label { display: block; font-size: 0.85em; font-weight: 600; color: #555; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input[readonly] { background: #e9ecef; color: #555; font-weight: bold; cursor: not-allowed; }
        
        .caja-destacada { background: #e3f2fd; padding: 15px; border-radius: 5px; border: 1px solid #bbdefb; margin-top: 15px; }
        .titulo-seccion { color: #0056b3; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-bottom: 15px; margin-top: 20px; }

        .tabla-paquetes { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .tabla-paquetes th { background: #f8f9fa; padding: 8px; text-align: left; font-size: 0.85em; }
        .tabla-paquetes td { padding: 5px; border-bottom: 1px solid #eee; }
        .fila-paquete { animation: fadeIn 0.5s; }
        
        .btn-save { background: #28a745; color: white; border: none; padding: 12px; width: 100%; border-radius: 5px; font-weight: bold; font-size: 1.1em; cursor: pointer; margin-top: 20px; }
        .btn-save:hover { background: #218838; }
        .btn-save:disabled { background: #ccc; cursor: not-allowed; }

        /* Estilos de Modales */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; }
        .modal-contenido { background: white; width: 90%; max-width: 600px; padding: 25px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; max-height:90vh; overflow-y:auto; }
        .close-btn { position: absolute; right: 15px; top: 10px; font-size: 24px; cursor: pointer; color: #aaa; }
        .row-desglose { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; background: #f9f9f9; padding: 10px; border-radius: 5px; }
        
        /* Select2 Estilos */
        .select2-container { width: 100% !important; flex: 2; }
        .select2-container .select2-selection--single { height: 38px; border: 1px solid #ccc; display: flex; align-items: center; }
        .btn-plus-new { background: #17a2b8; color: white; border: none; width: 35px; height: 35px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2em; font-weight: bold; }
        
        .select-motivo { flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .input-cantidad-motivo { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; text-align: center; font-weight: bold; }
        .btn-del-row { background: #dc3545; color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; }
        .resumen-validacion { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; border: 1px solid #ddd; }
        .fila-val { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.95em; }
        .val-error { color: #dc3545; font-weight: bold; display: none; margin-top: 5px; }
        .btn-add-row { background: #0056b3; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.9em; }
        .btn-confirmar { width: 100%; padding: 12px; border: none; border-radius: 5px; font-weight: bold; font-size: 1em; margin-top: 15px; cursor: pointer; transition: 0.3s; background: #ccc; color: #666; cursor: not-allowed; }
        .btn-confirmar.active { background: #28a745; color: white; cursor: pointer; }
        #btn_desglose, #btn_desglose_tiempos { background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85em; padding: 0 10px; transition: 0.3s; display: flex; align-items: center; justify-content: center; }
        .input-group { display: flex; gap: 5px; }
        
        .hist-table { width: 100%; border-collapse: collapse; font-size: 0.85em; white-space: nowrap; }
        .hist-table th { background: #f1f3f5; padding: 8px; text-align: left; color: #555; border-bottom: 2px solid #ddd; }
        .hist-table td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 0.75em; background: #eee; font-weight: bold; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .edit-banner { background: #fff3cd; color: #856404; padding: 15px; text-align: center; border: 1px solid #ffeeba; margin-bottom: 20px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .audit-info { color:#856404; font-size:0.7em; margin-top:5px; font-style:italic; }

        /* Estilos PDF */
        #pdf-template { background: white; padding: 30px; border: 1px solid #ccc; width: 750px; display: none; font-family: 'Helvetica', sans-serif; }
        .pdf-header { text-align: center; border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 20px; }
        .pdf-header h1 { margin: 0; color: #0056b3; font-size: 24px; }
        .pdf-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
        .pdf-table th, .pdf-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .pdf-table th { background-color: #f2f2f2; font-weight: bold; }
        .pdf-section { margin-bottom: 15px; }
        .pdf-section h3 { font-size: 14px; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; color: #333; }
        .firma-box { margin-top: 50px; display: flex; justify-content: space-around; }
        .firma-line { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 5px; font-size: 12px; }

        /* Estilos KPI Cards */
        .kpi-row { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 15px; }
        .kpi-card { flex: 1; background: #f8f9fa; padding: 15px; border-radius: 5px; text-align: center; border: 1px solid #eee; min-width: 150px; }
        .kpi-val { font-size: 1.5em; font-weight: bold; color: #0056b3; }
        .kpi-label { font-size: 0.85em; color: #666; text-transform: uppercase; }
        .chart-box { height: 200px; position: relative; margin-top: 10px; }
    </style>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        const empaqueStd = <?php echo $empaque_std; ?>; 
        const pesoProdGram = <?php echo $peso_producto_g ?: 0; ?>; 
        const cicloStd = <?php echo $ciclo_std > 0 ? $ciclo_std : 9999; ?>; 
        const listaColores = <?php echo json_encode($lista_colores_final); ?>;
        const listaConceptos = <?php echo json_encode($lista_conceptos); ?>;
        const listaParadas = <?php echo json_encode($lista_paradas); ?>;
        
        // --- DATA DE EDICIÓN ---
        const editPackages = <?php echo json_encode($paquetes_edit); ?>;

        $(document).ready(function() {
            // Inicializar Select2 en selectores estáticos
            $('.select-search').select2();
            
            // --- KPI CHARTS (NUEVO) ---
            const ctxQ = document.getElementById('chartQuality');
            if(ctxQ) {
                new Chart(ctxQ, {
                    type: 'doughnut',
                    data: {
                        labels: ['Buenas', 'Malas'],
                        datasets: [{
                            data: [<?php echo $total_hecho; ?>, <?php echo $total_malas; ?>],
                            backgroundColor: ['#28a745', '#dc3545']
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
                });
            }
        });

        function calcularProduccion() {
            let hIni = document.getElementsByName('h_inicio')[0].value;
            let hFin = document.getElementsByName('h_final')[0].value;
            
            // Sugerir Turno basado en hora (Solo si no es edición o si el campo está vacío)
            if(hIni && !document.getElementsByName('id_turno_editar')[0].value) {
                let hora = parseInt(hIni.split(':')[0]);
                let turnoSel = document.getElementsByName('turno_id')[0];
                if(hora >= 6 && hora < 14) turnoSel.value = "1";
                else if(hora >= 14 && hora < 22) turnoSel.value = "2";
                else turnoSel.value = "3";
            }
            
            if(hIni && hFin) {
                let d1 = new Date("2000-01-01 " + hIni);
                let d2 = new Date("2000-01-01 " + hFin);
                if (d2 < d1) d2.setDate(d2.getDate() + 1);
                let diffSeg = (d2 - d1) / 1000;
                document.getElementsByName('total_seg')[0].value = diffSeg;
            }

            let contIni = parseInt(document.getElementById('cont_ini').value) || 0;
            let contFin = parseInt(document.getElementById('cont_fin').value) || 0;
            let malas = parseInt(document.getElementById('malas').value) || 0;
            let sobIni = parseInt(document.getElementById('sob_ini').value) || 0;

            let fisicas = contFin - contIni;
            if (fisicas < 0) fisicas = 0; 
            document.getElementById('fisicas').value = fisicas;
            
            // --- CÁLCULO DE CICLOS Y DIFERENCIA ---
            let seg = parseInt(document.getElementsByName('total_seg')[0].value) || 0;
            let cicloRealInput = document.getElementById('ciclo_real_input'); 
            let cicloMaqInput = document.getElementById('ciclo_maquina_input'); 
            
            // Calculamos ciclo sistema (auto)
            let cicloSys = 0;
            if(fisicas > 0 && seg > 0) {
                cicloSys = (seg / fisicas);
                cicloRealInput.value = cicloSys.toFixed(2);
            } else {
                cicloRealInput.value = 0;
            }
            
            // Calcular Diferencia en Minutos
            let cicloMan = parseFloat(cicloMaqInput.value) || 0;
            let diffMinutos = 0;
            
            if(cicloMan > 0 && cicloSys > 0) {
                let diffSegundosTotal = Math.abs((cicloSys - cicloMan) * fisicas);
                diffMinutos = Math.round(diffSegundosTotal / 60);
            }
            
            document.getElementById('minutos_diferencia').value = diffMinutos;
            validarBotonTiempos(diffMinutos);
            // -------------------------------------

            validarBotonDesglose(malas);

            let netoTurno = fisicas - malas;
            if (netoTurno < 0) netoTurno = 0;
            let totalDisponible = netoTurno + sobIni;
            let numPaquetes = Math.floor(totalDisponible / empaqueStd);
            let sobFin = totalDisponible % empaqueStd;

            document.getElementById('num_paquetes_display').value = numPaquetes;
            document.getElementById('sob_fin').value = sobFin;
            
            let pesoBolsa = parseFloat(document.getElementsByName('peso_bolsa_unitario')[0].value) || 0;
            let cantBolsas = parseInt(document.getElementsByName('cantidad_bolsas')[0].value) || 0;
            let consumo = (pesoBolsa * cantBolsas) / 1000; 
            document.getElementsByName('consumo_bolsa')[0].value = consumo.toFixed(3);

            generarFilasPaquetes(numPaquetes);
        }

        function generarFilasPaquetes(cantidad) {
            const tbody = document.getElementById('body_paquetes');
            // CORRECCIÓN: Verifica si hay filas de paquetes REALES
            if (tbody.querySelectorAll('.fila-paquete').length === cantidad) return; 
            
            tbody.innerHTML = ''; 
            if (cantidad > 0) {
                let options = '<option value="">- Seleccionar -</option>'; 
                listaColores.forEach(c => { options += `<option value="${c}">${c}</option>`; }); 
                let pesoSugerido = (empaqueStd * pesoProdGram) / 1000;
                pesoSugerido = pesoSugerido > 0 ? pesoSugerido.toFixed(2) : "";
                
                for (let i = 1; i <= cantidad; i++) {
                    let tr = document.createElement('tr');
                    tr.className = 'fila-paquete';
                    
                    // Lógica para recuperar datos guardados en edición
                    let savedColor = '';
                    let savedWeight = pesoSugerido;
                    if(editPackages.length >= i) {
                        savedColor = editPackages[i-1].Color;
                        savedWeight = editPackages[i-1].Peso;
                    }
                    
                    // Reconstruir options con selected
                    let rowOptions = '<option value="">- Seleccionar -</option>'; 
                    listaColores.forEach(c => { 
                        let sel = (c === savedColor) ? 'selected' : '';
                        rowOptions += `<option value="${c}" ${sel}>${c}</option>`; 
                    });

                    tr.innerHTML = `<td style="font-weight:bold; vertical-align:middle;">📦 Paq #${i}</td><td><select name="paq_color[]" required style="width:100%; border:1px solid #0056b3; padding:5px;">${rowOptions}</select></td><td><div style="display:flex; align-items:center; gap:5px;"><input type="number" step="0.01" min="0.01" name="paq_peso[]" value="${savedWeight}" required style="width:80px; text-align:center; font-weight:bold; border:1px solid #28a745; padding:5px;"><span style="font-size:0.8em; color:#666;">Kg (${empaqueStd}u)</span></div></td>`;
                    tbody.appendChild(tr);
                }
            } else { tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#999; padding:10px;">Sin paquetes completos.</td></tr>'; }
        }

        // --- VALIDACIONES SCRAP ---
        function validarBotonDesglose(totalMalas) {
            let btn = document.getElementById('btn_desglose');
            let inputs = document.querySelectorAll('.input-cantidad-motivo');
            let suma = 0; inputs.forEach(i => suma += parseInt(i.value) || 0);
            if(totalMalas > 0) {
                if(suma === totalMalas) { btn.style.background = "#28a745"; btn.innerHTML = '<i class="fa-solid fa-check"></i> OK'; } 
                else { btn.style.background = "#dc3545"; btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Desglosar'; }
            } else { btn.style.background = "#6c757d"; btn.innerHTML = 'Sin Malas'; }
        }
        function abrirModalMalas() {
            let total = parseInt(document.getElementById('malas').value) || 0;
            if(total <= 0) { alert("Ingresa cantidad de malas."); return; }
            document.getElementById('modal_calidad').style.display = 'flex';
            document.getElementById('txt_total_declarado').innerText = total;
            // Solo agregar fila vacía si no hay filas (al editar ya habrá filas)
            if(document.getElementById('contenedor_filas_malas').children.length === 0) agregarFilaMotivo(); 
            validarSumaModal();
        }
        function agregarFilaMotivo() {
            let div = document.createElement('div'); div.className = 'row-desglose';
            let opts = '<option value="">- Motivo / Defecto -</option>';
            listaConceptos.forEach(c => { opts += `<option value="${c.id}">${c.Concepto}</option>`; });
            // FIX v3.20: onchange y validación estricta
            div.innerHTML = `<select name="motivos_id[]" class="select-motivo" required onchange="validarSumaModal()">${opts}</select><button type="button" class="btn-plus-new" onclick="crearNuevoDefecto(this.previousElementSibling)" title="Crear nuevo defecto">+</button><input type="number" name="motivos_cant[]" class="input-cantidad-motivo" placeholder="Cant." oninput="validarSumaModal()"><button type="button" class="btn-del-row" onclick="this.parentElement.remove(); validarSumaModal();">×</button>`;
            document.getElementById('contenedor_filas_malas').appendChild(div);
        }
        
        function crearNuevoDefecto(selectElement) {
            let nombre = prompt("📝 Ingrese el nombre del NUEVO Defecto de Calidad:");
            if(nombre && nombre.trim() !== "") {
                $.post('gestion_turno.php', {ajax_action:'crear_defecto', nombre_defecto:nombre}, function(r){
                    if(r.status==='ok') {
                        let opt = new Option(r.text, r.id, true, true);
                        $(selectElement).append(opt).trigger('change');
                        listaConceptos.push({id:r.id, Concepto:r.text}); // Actualizar local
                        alert("✅ Defecto creado y seleccionado.");
                        validarSumaModal();
                    } else alert(r.msg);
                }, 'json');
            }
        }

        function validarSumaModal() {
            let total = parseInt(document.getElementById('malas').value) || 0;
            let suma = 0; 
            let todosTienenMotivo = true; // Flag de validación
            
            // Validar Cantidades
            document.querySelectorAll('.input-cantidad-motivo').forEach(inp => suma += parseInt(inp.value) || 0);
            
            // FIX v3.20: Validar que todos los SELECT tengan valor
            document.querySelectorAll('.select-motivo').forEach(sel => {
                if(sel.value === "") { 
                    todosTienenMotivo = false; 
                    sel.style.border = "2px solid #dc3545"; // Feedback visual
                } else {
                    sel.style.border = "1px solid #ccc";
                }
            });
            
            document.getElementById('txt_total_desglosado').innerText = suma;
            let btn = document.getElementById('btn_confirmar_modal');
            let error = document.getElementById('msg_error_suma');
            
            // Condición compuesta: Suma correcta AND Motivos seleccionados
            if(suma === total && todosTienenMotivo) { 
                btn.disabled = false; btn.classList.add('active'); error.style.display = 'none'; 
            } else { 
                btn.disabled = true; btn.classList.remove('active'); error.style.display = 'block';
                if(!todosTienenMotivo) error.innerHTML = "⚠️ Selecciona un motivo en todas las filas.";
                else error.innerHTML = "⚠️ La suma no coincide.";
            }
        }
        function cerrarModalConfirmar() { document.getElementById('modal_calidad').style.display = 'none'; validarBotonDesglose(parseInt(document.getElementById('malas').value) || 0); }

        // --- VALIDACIONES TIEMPOS (NUEVO + SELECT2) ---
        function validarBotonTiempos(totalMinutos) {
            let btn = document.getElementById('btn_desglose_tiempos');
            let inputs = document.querySelectorAll('.input-minutos-detencion');
            let suma = 0; inputs.forEach(i => suma += parseFloat(i.value) || 0);
            if(totalMinutos > 0) {
                if(Math.round(suma) === totalMinutos) { btn.style.background = "#28a745"; btn.innerHTML = '<i class="fa-solid fa-check"></i> OK'; } 
                else { btn.style.background = "#dc3545"; btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Desglosar'; }
                btn.disabled = false;
            } else { 
                btn.style.background = "#6c757d"; btn.innerHTML = 'Sin Diferencia'; 
                btn.disabled = false; 
            }
        }
        function abrirModalTiempos() {
            let total = parseFloat(document.getElementById('minutos_diferencia').value) || 0;
            document.getElementById('modal_tiempos').style.display = 'flex';
            document.getElementById('txt_total_tiempos').innerText = total;
            if(document.getElementById('contenedor_filas_tiempos').children.length === 0) agregarFilaTiempo(); 
            validarSumaTiempos();
        }
        
        function agregarFilaTiempo() {
            let idUnique = 'sel_' + Date.now(); // ID único para inicializar Select2
            let container = document.getElementById('contenedor_filas_tiempos');
            
            let div = document.createElement('div'); div.className = 'row-desglose';
            let opts = '<option value="">Buscar causa...</option>';
            listaParadas.forEach(p => { opts += `<option value="${p.id}">${p.Concepto}</option>`; });
            
            // FIX v3.20: onchange para validar Select2
            div.innerHTML = `
                <div style="flex:2; display:flex; gap:5px; align-items:center;">
                    <select name="detencion_id[]" id="${idUnique}" style="width:100%" class="select-causa" onchange="validarSumaTiempos()">${opts}</select>
                    <button type="button" class="btn-plus-new" onclick="crearNuevaCausa('${idUnique}')" title="Crear nueva causa">+</button>
                </div>
                <input type="number" step="1" name="detencion_min[]" class="input-minutos-detencion" placeholder="Min." oninput="validarSumaTiempos()">
                <button type="button" class="btn-del-row" onclick="this.parentElement.remove(); validarSumaTiempos();">×</button>
            `;
            container.appendChild(div);
            
            // Inicializar Select2 con evento onchange
            $('#'+idUnique).select2({ dropdownParent: $('#modal_tiempos'), width: '100%' });
            $('#'+idUnique).on('select2:select', function (e) { validarSumaTiempos(); });
        }
        
        function crearNuevaCausa(selectId) {
            let nombre = prompt("📝 Ingrese el nombre de la NUEVA Causa de Parada:");
            if(nombre && nombre.trim() !== "") {
                $.post('gestion_turno.php', {ajax_action:'crear_causa', nombre_causa:nombre}, function(r){
                    if(r.status==='ok') {
                        let opt = new Option(r.text, r.id, true, true);
                        $('#'+selectId).append(opt).trigger('change');
                        listaParadas.push({id:r.id, Concepto:r.text}); // Actualizar local
                        alert("✅ Causa creada y seleccionada.");
                    } else alert(r.msg);
                }, 'json');
            }
        }

        function validarSumaTiempos() {
            let total = parseFloat(document.getElementById('minutos_diferencia').value) || 0;
            let suma = 0; 
            let todosTienenCausa = true;

            document.querySelectorAll('.input-minutos-detencion').forEach(inp => suma += parseFloat(inp.value) || 0);
            document.getElementById('txt_desglose_tiempos').innerText = suma.toFixed(0);
            
            // FIX v3.20: Validar Selects de Causas
            document.querySelectorAll('.select-causa').forEach(sel => {
                 if(sel.value === "") todosTienenCausa = false;
            });

            let btn = document.getElementById('btn_confirmar_tiempos');
            let error = document.getElementById('msg_error_tiempos');
            
            if(total > 0) {
                if(Math.round(suma) === Math.round(total) && todosTienenCausa) { 
                    btn.disabled = false; btn.classList.add('active'); error.style.display = 'none'; 
                } 
                else { 
                    btn.disabled = true; btn.classList.remove('active'); error.style.display = 'block'; 
                    if(!todosTienenCausa) error.innerHTML = "⚠️ Selecciona una causa.";
                    else error.innerHTML = "⚠️ La suma no coincide.";
                }
            } else {
                btn.disabled = false; btn.classList.add('active'); error.style.display = 'none';
            }
        }
        function cerrarModalTiempos() { document.getElementById('modal_tiempos').style.display = 'none'; validarBotonTiempos(parseFloat(document.getElementById('minutos_diferencia').value) || 0); }

        function validarEnvio() {
            // Validar Ciclo
            let ciclo = parseFloat(document.getElementById('ciclo_maquina_input').value) || 0;
            if(ciclo <= 0.01) { alert("⚠️ El Ciclo Máquina (Manual) es OBLIGATORIO y debe ser mayor a 0."); return false; }
            
            // Validar Operario
            let op = document.getElementsByName('operario_id')[0].value;
            if(!op || op == 0) { alert("⚠️ Debes seleccionar un OPERARIO."); return false; }
            
            let malas = parseInt(document.getElementById('malas').value) || 0;
            if(malas > 0) {
                let sumaM = 0; document.querySelectorAll('.input-cantidad-motivo').forEach(inp => sumaM += parseInt(inp.value) || 0);
                if(sumaM !== malas) { alert("⚠️ Calidad: Debes desglosar las unidades malas."); abrirModalMalas(); return false; }
            }
            let mins = parseFloat(document.getElementById('minutos_diferencia').value) || 0;
            if(mins > 0) {
                let sumaT = 0; document.querySelectorAll('.input-minutos-detencion').forEach(inp => sumaT += parseFloat(inp.value) || 0);
                if(Math.round(sumaT) !== Math.round(mins)) { alert("⚠️ Tiempos: Debes justificar los minutos de diferencia."); abrirModalTiempos(); return false; }
            }
            
            // FIX v3.13: Deshabilitar botón para evitar doble clic
            const btn = document.querySelector('.btn-save');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
            
            return true;
        }

        // FUNCIÓN GENERAR PDF
        function generarPDF() {
            const element = document.getElementById('pdf-template');
            element.style.display = 'block'; // Mostrar temporalmente
            const opt = {
                margin:       10,
                filename:     'Reporte_Orden_<?php echo $orden_id; ?>_Turno_<?php echo $id_nuevo_turno; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().from(element).set(opt).save().then(() => {
                element.style.display = 'none'; // Ocultar de nuevo
            });
        }
    </script>
</head>
<body>

<div class="header-bar">
    <div><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <a href="gestion_turno.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
</div>

<div class="container">
    <div class="card">
        <div class="card-body">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0; color:#0056b3;">Orden #<?php echo $orden['NumeroOrden']; ?></h2>
                    <span style="color:#555;"><?php echo $orden['Producto']; ?> (Ref: <?php echo $orden['Referencia']; ?>)</span>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:2em; font-weight:bold; color:#28a745;"><?php echo $porcentaje; ?>%</div>
                    <small>Progreso</small>
                </div>
            </div>
            <div class="progress-bg"><div class="progress-fill"></div></div>
            
            <!-- KPI CARDS + GRÁFICAS (NUEVO v3.19) -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <div class="kpi-val"><?php echo number_format($total_hecho); ?></div>
                    <div class="kpi-label">✅ Buenas Acum.</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-val" style="color:#dc3545;"><?php echo number_format($total_malas); ?></div>
                    <div class="kpi-label">❌ Malas (Scrap)</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-val"><?php echo $tasa_calidad; ?>%</div>
                    <div class="kpi-label">⭐ Calidad</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-val"><?php echo number_format($ciclo_std, 2); ?>s</div>
                    <div class="kpi-label">⏱️ Ciclo Std</div>
                </div>
            </div>
            
            <div style="margin-top:20px; display:flex; gap:20px;">
                <div style="flex:1; border:1px solid #eee; border-radius:5px; padding:10px;">
                    <h5 style="margin:0 0 10px 0; text-align:center;">Calidad Global</h5>
                    <div class="chart-box"><canvas id="chartQuality"></canvas></div>
                </div>
                <!-- Espacio para futuras gráficas OEE -->
            </div>
        </div>
    </div>

    <!-- AVISO DE EDICIÓN -->
    <?php if($is_editing): ?>
        <div class="edit-banner">
            <div><i class="fa-solid fa-pen"></i> <b>MODO EDICIÓN:</b> Estás modificando el Turno <b>#<?php echo $id_edit; ?></b>.</div>
            <a href="gestion_turno.php?orden=<?php echo $orden_id; ?>" style="color:#856404; font-weight:bold;">Cancelar</a>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO -->
    <div class="card" style="margin-bottom: 25px;">
        <div class="card-header">
            <span><i class="fa-solid fa-pen-to-square"></i> Registrar Turno</span>
            <span><?php echo date('Y-m-d'); ?></span>
        </div>
        <div class="card-body" style="<?php echo $style_block; ?>">
            <?php echo $aviso_final; ?>
            <?php echo $mensaje; ?>
            <form method="POST" autocomplete="off" onsubmit="return validarEnvio()">
                <!-- FIX v3.21: INPUT HIDDEN PARA DETECTAR POST -->
                <input type="hidden" name="hidden_submit" value="1">
                <input type="hidden" name="id_turno_editar" value="<?php echo $is_editing ? $edit_data['id'] : ''; ?>">
                
                <input type="hidden" name="num_orden" value="<?php echo $orden_id; ?>">
                <input type="hidden" name="empaque_std" value="<?php echo $empaque_std; ?>">
                
                <div class="form-grid">
                    <div><label>Fecha</label><input type="date" name="fecha" value="<?php echo $is_editing ? $edit_data['Fecha'] : date('Y-m-d'); ?>" required <?php echo $disabled_attr; ?>></div>
                    <div>
                        <label>Turno (Se guarda ID 1, 2 ó 3)</label>
                        <select name="turno_id" required <?php echo $disabled_attr; ?>>
                            <?php 
                                $sel_t = $is_editing ? $edit_data['NumeroTurno'] : '';
                            ?>
                            <option value="1" <?php if($sel_t==1) echo 'selected'; ?>>🌅 Mañana (1)</option>
                            <option value="2" <?php if($sel_t==2) echo 'selected'; ?>>☀️ Tarde (2)</option>
                            <option value="3" <?php if($sel_t==3) echo 'selected'; ?>>🌙 Noche (3)</option>
                        </select>
                    </div>
                </div>

                <!-- SELECTOR DE OPERARIO NUEVO -->
                <div class="form-grid">
                    <div>
                        <label style="color:#0056b3; font-weight:bold;"><i class="fa-solid fa-user"></i> Operario Responsable</label>
                        <select name="operario_id" class="select-search" required style="border:2px solid #0056b3; background:#f0f7ff; width:100%;" <?php echo $disabled_attr; ?>>
                            <option value="">- Seleccione Operario -</option>
                            <?php foreach($lista_operarios as $op): 
                                // Si editamos, seleccionar el del turno. Si no, sesión.
                                $selected = '';
                                if ($is_editing) {
                                    if ($edit_data['Operariold'] == $op['id']) $selected = 'selected';
                                } else {
                                    if (isset($_SESSION['id']) && $_SESSION['id'] == $op['id']) $selected = 'selected';
                                }
                            ?>
                                <option value="<?php echo $op['id']; ?>" <?php echo $selected; ?>><?php echo $op['nombre']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="titulo-seccion">⏱️ Tiempos y Ciclos</div>
                <div class="form-grid">
                    <div><label>Hora Inicio</label><input type="time" name="h_inicio" value="<?php echo $is_editing ? $edit_data['HoraInicio'] : $hora_sugerida; ?>" required <?php echo $disabled_attr; ?> oninput="calcularProduccion()"></div>
                    <div><label>Hora Final</label><input type="time" name="h_final" value="<?php echo $is_editing ? $edit_data['HoraFinal'] : ''; ?>" required <?php echo $disabled_attr; ?> oninput="calcularProduccion()"></div>
                    
                    <div>
                        <label style="color:#d63384; font-weight:bold;">Ciclo Máquina (Manual) *</label>
                        <input type="number" step="0.01" name="ciclo_maquina" id="ciclo_maquina_input" value="<?php echo $is_editing ? $edit_data['CicloMaquina'] : ''; ?>" required <?php echo $disabled_attr; ?> oninput="calcularProduccion()" placeholder="> 0.01">
                    </div>
                    <div>
                        <label>Ciclo Sistema (Auto)</label>
                        <input type="number" step="0.01" name="ciclo_real" id="ciclo_real_input" value="<?php echo $is_editing ? $edit_data['CicloReal'] : ''; ?>" readonly style="background:#e9ecef;">
                    </div>
                    
                    <div><label>Tiempo Total (s)</label><input type="number" name="total_seg" value="<?php echo $is_editing ? $edit_data['TiempoTotalSeg'] : '0'; ?>" readonly <?php echo $disabled_attr; ?>></div>
                    
                    <!-- DIFERENCIA MINUTOS (TIPO SCRAP) -->
                    <div>
                        <label style="color:#dc3545;">Diferencia (Minutos)</label>
                        <div class="input-group">
                            <input type="number" id="minutos_diferencia" readonly style="background:#e9ecef; font-weight:bold; color:#dc3545;">
                            <button type="button" id="btn_desglose_tiempos" onclick="abrirModalTiempos()" title="Justificar tiempos" <?php echo $disabled_attr; ?>>Sin Diferencia</button>
                        </div>
                    </div>
                </div>

                <div class="titulo-seccion">⚙️ Contadores y Calidad</div>
                <div class="form-grid">
                    <div><label style="color:#0056b3;">Contador Inicial</label><input type="number" id="cont_ini" name="cont_ini" value="<?php echo $is_editing ? $edit_data['ContadorInicio'] : $cont_sugerido; ?>" required <?php echo $disabled_attr; ?> oninput="calcularProduccion()"></div>
                    <div><label style="color:#0056b3;">Contador Final</label><input type="number" id="cont_fin" name="cont_fin" value="<?php echo $is_editing ? $edit_data['ContadorFinal'] : ''; ?>" required <?php echo $disabled_attr; ?> oninput="calcularProduccion()"></div>
                    <div><label>Físicas</label><input type="number" id="fisicas" readonly></div>
                    
                    <div>
                        <label style="color:#dc3545;">Malas / Scrap</label>
                        <div class="input-group">
                            <input type="number" id="malas" name="malas" value="<?php echo $is_editing ? $edit_data['Malas'] : '0'; ?>" <?php echo $disabled_attr; ?> oninput="calcularProduccion()">
                            <button type="button" id="btn_desglose" onclick="abrirModalMalas()" title="Desglosar motivos" <?php echo $disabled_attr; ?>>Sin Malas</button>
                        </div>
                    </div>
                </div>

                <div class="caja-destacada">
                    <div class="titulo-seccion" style="margin-top:0; border:none;">
                        📦 Empaque <span class="info-empaque">Std: <?php echo $empaque_std; ?></span>
                        <?php echo $aviso_empaque; ?>
                    </div>
                    <div class="form-grid">
                        <div><label>Sobrante Ant. (+)</label><input type="number" id="sob_ini" name="sob_ini" value="<?php echo $is_editing ? $edit_data['SobrantesInicio'] : $sob_sugerido; ?>" style="background:#fff3cd;" <?php echo $disabled_attr; ?> oninput="calcularProduccion()"></div>
                        <div><label>Sobrante Act. (Auto)</label><input type="number" id="sob_fin" name="sob_fin" readonly style="font-weight:bold;"></div>
                    </div>
                    <div style="margin-bottom:10px; font-weight:bold; color:#0056b3;">
                        Paquetes a Registrar: <input type="text" id="num_paquetes_display" readonly style="width:50px; text-align:center; border:none; background:transparent; font-size:1.2em;">
                    </div>
                    <table class="tabla-paquetes">
                        <thead><tr><th>Paquete</th><th>Color (Obligatorio)</th><th>Peso Kg (Verificar)</th></tr></thead>
                        <tbody id="body_paquetes"><tr><td colspan="3" style="text-align:center; color:#999; padding:10px;">Calculando...</td></tr></tbody>
                    </table>
                </div>
                
                <div class="titulo-seccion">🛍️ Bolsa</div>
                <div class="form-grid">
                    <div><label>Peso Bolsa (g)</label><input type="number" step="0.1" name="peso_bolsa_unitario" value="<?php echo $is_editing ? $edit_data['PesoBolsaUnitario'] : ''; ?>" placeholder="Ej: 5.5" <?php echo $disabled_attr; ?> oninput="calcularProduccion()"></div>
                    <div><label>Cant. Bolsas</label><input type="number" name="cantidad_bolsas" value="<?php echo $is_editing ? $edit_data['CantidadBolsas'] : ''; ?>" placeholder="Unds" <?php echo $disabled_attr; ?> oninput="calcularProduccion()"></div>
                    <div><label>Consumo (Kg)</label><input type="number" step="0.001" name="consumo_bolsa" readonly></div>
                </div>

                <div style="margin-top:15px;"><label>Observaciones</label><textarea name="observaciones" rows="2" placeholder="Agrega cualquier explicación adicional aquí..." <?php echo $disabled_attr; ?>><?php echo $is_editing ? $edit_data['Observaciones'] : ''; ?></textarea></div>
                <button type="submit" name="guardar_turno" class="btn-save" <?php echo $disabled_attr; ?>>GUARDAR TURNO</button>
            </form>
        </div>
    </div>

    <!-- HISTORIAL -->
    <div class="card">
        <div class="card-header"><i class="fa-solid fa-clock-rotate-left"></i> Historial Reciente</div>
        <div class="card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="hist-table">
                    <thead>
                        <tr>
                            <th>Fecha / Turno</th>
                            <th>Info Operario</th>
                            <th>Horas</th>
                            <th>Contadores</th>
                            <th>Paquetes</th>
                            <th>Prod</th>
                            <th>Ciclos (Sys/Std)</th>
                            <th>Tiempos/Demoras</th>
                            <th>Obs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_historial && $res_historial->num_rows > 0): 
                            while($row = $res_historial->fetch_assoc()): 
                                $t_num = $row['NumeroTurno'];
                                $t_txt = ($t_num == 1) ? 'Mañana' : (($t_num == 2) ? 'Tarde' : 'Noche');
                                $txt_paqs = !empty($row['DetallePaquetes']) ? $row['DetallePaquetes'] : "-";
                                $total_paqs = $row['NumPaquetes'];
                                
                                $c_real = floatval($row['CicloReal']);
                                // AHORA USAMOS CICLO ESTÁNDAR DEL PRODUCTO EN LUGAR DE CICLO MÁQUINA
                                $c_std = isset($row['CicloStd']) ? floatval($row['CicloStd']) : 0;
                                
                                $txt_demoras = "";
                                if(isset($row['DetalleTiempos']) && !empty($row['DetalleTiempos'])) {
                                    $txt_demoras = "<div style='color:#dc3545; font-size:0.8em;'>".$row['DetalleTiempos']."</div>";
                                }
                                
                                // AUDITORÍA VISUAL (v3.23)
                                $audit_html = "";
                                if (!empty($row['ultimo_editor']) && !empty($row['fecha_edicion'])) {
                                    $audit_html = "<div class='audit-info'>✏️ Editado por: " . ($row['NombreEditor'] ?? 'ID:'.$row['ultimo_editor']) . "</div>";
                                }
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:bold;"><?php echo date('d/m', strtotime($row['Fecha'])); ?></div>
                                    <span class="badge"><?php echo $t_txt; ?> (#<?php echo $row['id']; ?>)</span>
                                </td>
                                <td>
                                    <div style="font-size:0.8em; color:#555;">Op: <?php echo !empty($row['NombreOperario']) ? $row['NombreOperario'] : (isset($row['Operariold']) ? "ID: ".$row['Operariold'] : '-'); ?></div>
                                    <?php echo $audit_html; ?>
                                </td>
                                <td style="font-size:0.85em;"><?php echo substr($row['HoraInicio'],0,5) . ' - ' . substr($row['HoraFinal'],0,5); ?></td>
                                <td style="font-size:0.85em;"><?php echo $row['ContadorInicio'] . ' / ' . $row['ContadorFinal']; ?></td>
                                <td>
                                    <?php if($total_paqs > 0): ?>
                                        <div style="font-size:0.9em; font-weight:bold;"><?php echo $total_paqs; ?> Paqs</div>
                                        <div style="font-size:0.75em; color:#666; margin-top:3px;"><?php echo $txt_paqs; ?></div>
                                    <?php else: ?> - <?php endif; ?>
                                </td>
                                <td>
                                    <div style="color:green; font-weight:bold; font-size:0.9em;">+<?php echo number_format($row['Buenas']); ?></div>
                                    <?php if($row['Malas'] > 0) echo "<div style='color:red; font-size:0.8em;'>-".$row['Malas']." Malas</div>"; ?>
                                </td>
                                <td>
                                    <div style="font-size:0.85em;">
                                        Sys: <b><?php echo number_format($c_real, 2); ?></b><br>
                                        Std: <b><?php echo number_format($c_std, 2); ?></b>
                                    </div>
                                </td>
                                <td><?php echo $txt_demoras; ?></td>
                                <td style="font-size:0.8em; font-style:italic; max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo $row['Observaciones']; ?>">
                                    <?php echo $row['Observaciones']; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="9" style="text-align:center; padding:20px; color:#999;">Sin registros para esta orden.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE CALIDAD -->
<div id="modal_calidad" class="modal">
    <div class="modal-contenido">
        <span class="close-btn" onclick="document.getElementById('modal_calidad').style.display='none'">&times;</span>
        <h3 style="color:#dc3545; margin-top:0;">Desglose de Calidad</h3>
        <p style="font-size:0.9em; color:#666;">Justifica las unidades reportadas como malas.</p>
        <div class="resumen-validacion">
            <div class="fila-val"><span>Total Declarado:</span> <strong id="txt_total_declarado">0</strong></div>
            <div class="fila-val"><span>Total Desglosado:</span> <strong id="txt_total_desglosado">0</strong></div>
            <div id="msg_error_suma" class="val-error">⚠️ La suma no coincide.</div>
        </div>
        <div style="margin: 15px 0; max-height:250px; overflow-y:auto;">
            <div id="contenedor_filas_malas">
                <!-- RENDERIZADO PHP SI ES EDICIÓN -->
                <?php if ($is_editing && !empty($malas_edit)): ?>
                    <?php foreach($malas_edit as $me): ?>
                        <div class="row-desglose">
                            <select name="motivos_id[]" class="select-motivo" required onchange="validarSumaModal()">
                                <option value="">- Motivo -</option>
                                <?php foreach($lista_conceptos as $c): $sel = ($c['id'] == $me['id_concepto'])?'selected':''; ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $sel; ?>><?php echo $c['Concepto']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="motivos_cant[]" class="input-cantidad-motivo" value="<?php echo $me['Cantidad']; ?>" oninput="validarSumaModal()">
                            <button type="button" class="btn-del-row" onclick="this.parentElement.remove(); validarSumaModal();">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn-add-row" onclick="agregarFilaMotivo()">+ Agregar Motivo</button>
        <button type="button" id="btn_confirmar_modal" class="btn-confirmar" onclick="cerrarModalConfirmar()" disabled>CONFIRMAR</button>
    </div>
</div>

<!-- MODAL DE TIEMPOS -->
<div id="modal_tiempos" class="modal">
    <div class="modal-contenido">
        <span class="close-btn" onclick="document.getElementById('modal_tiempos').style.display='none'">&times;</span>
        <h3 style="color:#dc3545; margin-top:0;">Justificación de Diferencia</h3>
        <p style="font-size:0.9em; color:#666;">Desglosa los minutos de diferencia entre ciclos.</p>
        <div class="resumen-validacion">
            <div class="fila-val"><span>Minutos Diferencia:</span> <strong id="txt_total_tiempos">0</strong></div>
            <div class="fila-val"><span>Minutos Justificados:</span> <strong id="txt_desglose_tiempos">0</strong></div>
            <div id="msg_error_tiempos" class="val-error">⚠️ La suma no coincide.</div>
        </div>
        <div id="contenedor_filas_tiempos" style="margin: 15px 0; max-height:300px; overflow-y:auto;">
             <!-- RENDERIZADO PHP SI ES EDICIÓN -->
             <?php if ($is_editing && !empty($tiempos_edit)): ?>
                <?php foreach($tiempos_edit as $te): $uid = uniqid(); ?>
                    <div class="row-desglose">
                        <div style="flex:2; display:flex; gap:5px; align-items:center;">
                            <select name="detencion_id[]" id="sel_<?php echo $uid; ?>" style="width:100%" class="select-causa" onchange="validarSumaTiempos()">
                                <?php foreach($lista_paradas as $p): $sel = ($p['id'] == $te['id_causa'])?'selected':''; ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo $sel; ?>><?php echo $p['Concepto']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="number" name="detencion_min[]" class="input-minutos-detencion" value="<?php echo $te['minutos']; ?>" oninput="validarSumaTiempos()">
                        <button type="button" class="btn-del-row" onclick="this.parentElement.remove(); validarSumaTiempos();">×</button>
                        <script> $(document).ready(function() { $('#sel_<?php echo $uid; ?>').select2({ dropdownParent: $('#modal_tiempos'), width: '100%' }); }); </script>
                    </div>
                <?php endforeach; ?>
             <?php endif; ?>
        </div>
        <div style="margin-top:15px; display:flex; gap:10px;">
            <button type="button" class="btn-add-row" onclick="agregarFilaTiempo()">+ Agregar Causa</button>
            <button type="button" id="btn_confirmar_tiempos" class="btn-confirmar" onclick="cerrarModalTiempos()" disabled style="margin-top:0; flex:1;">CONFIRMAR</button>
        </div>
    </div>
</div>

<!-- PLANTILLA INVISIBLE PARA PDF -->
<?php if($id_nuevo_turno > 0 && isset($row_recibo) && $row_recibo): ?>
<div id="pdf-template">
    <div class="pdf-header">
        <h1>INARPLAS - Comprobante de Producción</h1>
        <div style="font-size:14px; margin-top:5px;">CONTROL DE PISO Y TURNOS</div>
    </div>
    <div class="pdf-section">
        <h3>Información General</h3>
        <table class="pdf-table">
            <tr>
                <td width="25%"><b>Orden #:</b> <?php echo $orden['NumeroOrden']; ?></td>
                <td width="50%"><b>Producto:</b> <?php echo $orden['Producto']; ?></td>
                <td width="25%"><b>Fecha:</b> <?php echo $row_recibo['Fecha']; ?></td>
            </tr>
            <tr>
                <td><b>Turno:</b> <?php echo ($row_recibo['NumeroTurno']==1?'Mañana':($row_recibo['NumeroTurno']==2?'Tarde':'Noche')); ?></td>
                <td><b>Horario:</b> <?php echo substr($row_recibo['HoraInicio'],0,5).' - '.substr($row_recibo['HoraFinal'],0,5); ?></td>
                <td><b>Operario ID:</b> <?php echo $row_recibo['Operariold']; ?></td>
            </tr>
        </table>
    </div>
    <div class="pdf-section">
        <h3>Métricas de Producción</h3>
        <table class="pdf-table">
            <tr>
                <th>Contador Inicial</th>
                <th>Contador Final</th>
                <th>Físicas</th>
                <th>Malas</th>
                <th>Buenas (Neto)</th>
            </tr>
            <tr>
                <td align="center"><?php echo $row_recibo['ContadorInicio']; ?></td>
                <td align="center"><?php echo $row_recibo['ContadorFinal']; ?></td>
                <td align="center"><?php echo $row_recibo['UnidadesFisicas']; ?></td>
                <td align="center" style="color:red;"><?php echo $row_recibo['Malas']; ?></td>
                <td align="center" style="color:green; font-weight:bold;"><?php echo $row_recibo['Buenas']; ?></td>
            </tr>
        </table>
    </div>
    <div class="pdf-section">
        <h3>Eficiencia y Ciclos</h3>
        <table class="pdf-table">
            <tr>
                <td><b>Ciclo Estándar:</b> <?php echo $orden['Ciclo']; ?>s</td>
                <td><b>Ciclo Real (Auto):</b> <?php echo $row_recibo['CicloReal']; ?>s</td>
                <td><b>Ciclo Máquina (Manual):</b> <?php echo $row_recibo['CicloMaquina']; ?>s</td>
            </tr>
        </table>
    </div>
    <div class="pdf-section">
        <h3>Detalle de Paquetes</h3>
        <table class="pdf-table">
            <tr>
                <th>#</th>
                <th>Color</th>
                <th>Peso (Kg)</th>
                <th>Cantidad (u)</th>
            </tr>
            <?php 
            $i = 1;
            while($p = $res_paq_r->fetch_assoc()): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo $p['Color']; ?></td>
                <td><?php echo $p['Peso']; ?></td>
                <td><?php echo $p['Cantidad']; ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if($i == 1): ?>
            <tr><td colspan="4" align="center">Sin paquetes registrados.</td></tr>
            <?php endif; ?>
        </table>
    </div>
    <div class="pdf-section">
        <h3>Observaciones</h3>
        <div style="border:1px solid #ddd; padding:10px; min-height:40px; font-size:12px;">
            <?php echo $row_recibo['Observaciones'] ? $row_recibo['Observaciones'] : 'Ninguna.'; ?>
        </div>
    </div>
    <div class="firma-box">
        <div class="firma-line">Firma Operario</div>
        <div class="firma-line">Firma Supervisor / Calidad</div>
    </div>
    <div style="text-align:center; font-size:10px; color:#999; margin-top:20px;">
        Generado automáticamente por Sistema INARPLAS Cloud el <?php echo date('Y-m-d H:i:s'); ?>
    </div>
</div>
<script>
    window.addEventListener('load', function() {
        setTimeout(generarPDF, 1000); 
    });
</script>
<?php endif; ?>

<script>window.onload = function() { calcularProduccion(); };</script>
</body>
</html>
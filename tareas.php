<?php
// public_html/sistema/tareas.php
// Fecha: 24-12-2025 13:00
// DESCRIPCIÓN: Módulo de Tareas con Evidencia Fotográfica y Vista Tipo Gantt Mensual.

// Configuración
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Bogota'); // Ajusta a tu zona horaria

include 'auth.php';
include 'conexion.php';

$mensaje = "";
$error = "";

// Crear carpeta de evidencias si no existe
$dir_evidencias = "uploads/evidencias/";
if (!file_exists($dir_evidencias)) {
    mkdir($dir_evidencias, 0777, true);
}

// --- 1. LÓGICA: CREAR TAREA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_tarea'])) {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $desc = $conn->real_escape_string($_POST['descripcion']);
    $id_user = intval($_POST['id_usuario']);
    $f_inicio = $_POST['fecha_inicio'];
    $f_fin = $_POST['fecha_limite'];
    $prioridad = $_POST['prioridad'];

    $sql = "INSERT INTO tareas (titulo, descripcion, id_usuario_asignado, fecha_inicio, fecha_limite, prioridad) 
            VALUES ('$titulo', '$desc', $id_user, '$f_inicio', '$f_fin', '$prioridad')";
    
    if ($conn->query($sql)) {
        $mensaje = "✅ Tarea asignada correctamente.";
    } else {
        $error = "❌ Error al crear tarea: " . $conn->error;
    }
}

// --- 2. LÓGICA: COMPLETAR TAREA (SUBIR FOTO) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['completar_tarea'])) {
    $id_tarea = intval($_POST['id_tarea_completar']);
    $obs_cierre = $conn->real_escape_string($_POST['obs_cierre']);
    $fecha_actual = date('Y-m-d H:i:s');
    
    // Procesar Imagen
    if (isset($_FILES['foto_evidencia']) && $_FILES['foto_evidencia']['error'] == 0) {
        $ext = pathinfo($_FILES['foto_evidencia']['name'], PATHINFO_EXTENSION);
        $nombre_archivo = "evidencia_" . $id_tarea . "_" . time() . "." . $ext;
        $ruta_destino = $dir_evidencias . $nombre_archivo;
        
        // Validar tipo de imagen
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            if (move_uploaded_file($_FILES['foto_evidencia']['tmp_name'], $ruta_destino)) {
                // Actualizar DB
                $sql_update = "UPDATE tareas SET 
                               estado = 'Completada', 
                               fecha_completado = '$fecha_actual', 
                               foto_evidencia = '$nombre_archivo',
                               observaciones_cierre = '$obs_cierre'
                               WHERE id = $id_tarea";
                
                if ($conn->query($sql_update)) {
                    $mensaje = "✅ ¡Tarea completada! Evidencia guardada.";
                } else {
                    $error = "❌ Error al actualizar base de datos.";
                }
            } else {
                $error = "❌ Error al subir el archivo al servidor.";
            }
        } else {
            $error = "❌ Formato de imagen no permitido (Solo JPG, PNG, WEBP).";
        }
    } else {
        $error = "⚠️ La foto de evidencia es obligatoria.";
    }
}

// --- 3. DATOS PARA LA VISTA ---

// Lista de Usuarios (Para asignar y para el Gantt)
$usuarios = [];
$res_u = $conn->query("SELECT id, nombre FROM usuarios ORDER BY nombre ASC");
if ($res_u) { while ($r = $res_u->fetch_assoc()) $usuarios[] = $r; }

// Filtros de fecha para el Gantt (Por defecto mes actual)
$mes_actual = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$dias_en_mes = date('t', strtotime($mes_actual));
$primer_dia_mes = $mes_actual . "-01";
$ultimo_dia_mes = $mes_actual . "-" . $dias_en_mes;

// Obtener todas las tareas del mes seleccionado
$sql_tareas = "SELECT T.*, U.nombre as Responsable 
               FROM tareas T 
               LEFT JOIN usuarios U ON T.id_usuario_asignado = U.id
               WHERE (T.fecha_inicio <= '$ultimo_dia_mes' AND T.fecha_limite >= '$primer_dia_mes')
               ORDER BY T.fecha_inicio ASC";
$res_tareas = $conn->query($sql_tareas);

$lista_tareas = [];
if ($res_tareas) { while ($t = $res_tareas->fetch_assoc()) $lista_tareas[] = $t; }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Tareas - INARPLAS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding-bottom: 50px; }
        
        /* HEADER & LAYOUT */
        .header-bar { background-color: #0056b3; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; border-top: 4px solid #0056b3; }
        .card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; color: #333; display: flex; justify-content: space-between; align-items: center; }

        /* BOTONES */
        .btn-action { padding: 8px 15px; border-radius: 5px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9em; }
        .btn-blue { background: #0056b3; color: white; }
        .btn-green { background: #28a745; color: white; }
        .btn-check { background: #ffc107; color: #333; } /* Verificar */
        .btn-check:hover { background: #e0a800; }

        /* FORMULARIOS */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 0.9em; color: #555; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }

        /* GANTT / CRONOGRAMA */
        .gantt-container { overflow-x: auto; border: 1px solid #ddd; border-radius: 6px; }
        .gantt-table { width: 100%; border-collapse: collapse; min-width: 800px; font-size: 0.85em; }
        .gantt-table th, .gantt-table td { border: 1px solid #e0e0e0; padding: 0; text-align: center; height: 35px; position: relative; }
        
        .gantt-header-day { background: #f8f9fa; min-width: 30px; font-weight: bold; color: #555; }
        .gantt-user-col { background: #fff; position: sticky; left: 0; z-index: 2; width: 150px; text-align: left !important; padding: 0 10px !important; font-weight: bold; border-right: 2px solid #ddd !important; }
        
        /* BARRAS DE TAREA */
        .task-bar { 
            position: absolute; top: 5px; bottom: 5px; left: 2px; right: 2px; 
            border-radius: 4px; font-size: 0.75em; color: white; display: flex; 
            align-items: center; justify-content: center; overflow: hidden; white-space: nowrap; 
            cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .task-bar:hover { transform: scale(1.05); z-index: 10; }
        
        .st-pendiente { background: #17a2b8; } /* Azul */
        .st-completada { background: #28a745; } /* Verde */
        .st-vencida { background: #dc3545; } /* Rojo */

        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: white; width: 90%; max-width: 500px; padding: 25px; border-radius: 8px; position: relative; }
        .close-btn { position: absolute; right: 15px; top: 10px; font-size: 24px; cursor: pointer; color: #aaa; }

        /* LISTA TAREAS */
        .task-list-item { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #eee; }
        .task-info { flex: 1; }
        .task-status { margin-right: 15px; font-size: 0.8em; font-weight: bold; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="header-bar">
    <div style="font-weight:bold; font-size:1.2em;"><i class="fa-solid fa-list-check"></i> Gestión de Tareas</div>
    <div>
        <a href="dashboard.php" style="color:white; margin-right:15px; text-decoration:none;">Inicio</a>
        <a href="logout.php" style="color:white; text-decoration:none;">Salir</a>
    </div>
</div>

<div class="container">
    
    <?php if($mensaje) echo "<div class='alert success'>$mensaje</div>"; ?>
    <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
        
        <!-- COLUMNA IZQUIERDA: CREAR Y LISTA -->
        <div>
            <!-- FORMULARIO CREAR -->
            <div class="card">
                <h3><i class="fa-solid fa-plus-circle"></i> Asignar Nueva Tarea</h3>
                <form method="POST">
                    <div class="form-grid">
                        <div>
                            <label>Título:</label>
                            <input type="text" name="titulo" required placeholder="Ej: Limpieza Tolva 1">
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <label>Descripción:</label>
                        <textarea name="descripcion" rows="2" placeholder="Detalles..."></textarea>
                    </div>
                    <div class="form-grid" style="margin-top:10px;">
                        <div>
                            <label>Responsable:</label>
                            <select name="id_usuario" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach($usuarios as $u) echo "<option value='{$u['id']}'>{$u['nombre']}</option>"; ?>
                            </select>
                        </div>
                        <div>
                            <label>Prioridad:</label>
                            <select name="prioridad">
                                <option value="Baja">🟢 Baja</option>
                                <option value="Media" selected>🟡 Media</option>
                                <option value="Alta">🔴 Alta</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid" style="margin-top:10px;">
                        <div>
                            <label>Fecha Inicio:</label>
                            <input type="date" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div>
                            <label>Fecha Límite:</label>
                            <input type="date" name="fecha_limite" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="crear_tarea" class="btn-action btn-blue" style="width:100%; margin-top:15px;">Guardar Tarea</button>
                </form>
            </div>

            <!-- LISTA DE MIS TAREAS PENDIENTES (O TODAS SI ES ADMIN) -->
            <div class="card">
                <h3><i class="fa-solid fa-clipboard-list"></i> Pendientes por Verificar</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php 
                    // Filtrar visualmente: Mostrar pendientes primero
                    $pendientes_count = 0;
                    foreach($lista_tareas as $t): 
                        if($t['estado'] != 'Completada'):
                            $pendientes_count++;
                            
                            // Determinar si está vencida
                            $hoy = date('Y-m-d');
                            $es_vencida = ($t['fecha_limite'] < $hoy);
                            $clase_estado = $es_vencida ? "st-vencida" : "st-pendiente";
                            $texto_estado = $es_vencida ? "VENCIDA" : "PENDIENTE";
                    ?>
                        <div class="task-list-item">
                            <div class="task-info">
                                <strong style="color:#333;"><?php echo $t['titulo']; ?></strong><br>
                                <span class="task-status <?php echo $clase_estado; ?>" style="color:white; font-size:0.7em;"><?php echo $texto_estado; ?></span>
                                <small style="color:#666;">
                                    <i class="fa-solid fa-user"></i> <?php echo $t['Responsable']; ?> | 
                                    <i class="fa-solid fa-calendar"></i> <?php echo date('d/m', strtotime($t['fecha_limite'])); ?>
                                </small>
                            </div>
                            <button onclick="abrirModalCompletar(<?php echo $t['id']; ?>, '<?php echo $t['titulo']; ?>')" class="btn-action btn-check" title="Verificar Cumplimiento">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>
                    <?php endif; endforeach; ?>
                    
                    <?php if($pendientes_count == 0): ?>
                        <p style="text-align:center; color:#999; padding:20px;">🎉 No hay tareas pendientes visibles.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CRONOGRAMA MENSUAL (GANTT SIMPLIFICADO) -->
        <div>
            <div class="card">
                <h3>
                    <i class="fa-solid fa-calendar-days"></i> Cronograma de Trabajo
                    <form method="GET" style="display:inline;">
                        <input type="month" name="mes" value="<?php echo $mes_actual; ?>" onchange="this.form.submit()" style="width:auto; padding:5px; font-size:0.9em;">
                    </form>
                </h3>
                
                <div class="gantt-container">
                    <table class="gantt-table">
                        <thead>
                            <tr>
                                <th class="gantt-user-col">Usuario / Día</th>
                                <?php for($d=1; $d<=$dias_en_mes; $d++): ?>
                                    <th class="gantt-header-day"><?php echo $d; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($usuarios as $u): ?>
                            <tr>
                                <td class="gantt-user-col">
                                    <i class="fa-solid fa-user-circle" style="color:#ccc;"></i> <?php echo $u['nombre']; ?>
                                </td>
                                <?php 
                                // Renderizar celdas de días
                                for($d=1; $d<=$dias_en_mes; $d++): 
                                    // Fecha actual de la celda
                                    $fecha_celda = $mes_actual . "-" . str_pad($d, 2, "0", STR_PAD_LEFT);
                                    
                                    // Buscar si hay tareas para este usuario que abarquen este día
                                    $tarea_dia = null;
                                    foreach($lista_tareas as $t) {
                                        if($t['id_usuario_asignado'] == $u['id']) {
                                            if($fecha_celda >= $t['fecha_inicio'] && $fecha_celda <= $t['fecha_limite']) {
                                                $tarea_dia = $t;
                                                break; // Mostrar solo la primera que encuentre para no saturar celda
                                            }
                                        }
                                    }
                                ?>
                                    <td>
                                        <?php if($tarea_dia): 
                                            $clase_bar = "st-pendiente";
                                            if($tarea_dia['estado'] == 'Completada') $clase_bar = "st-completada";
                                            elseif($fecha_celda > $tarea_dia['fecha_limite']) $clase_bar = "st-vencida";
                                            elseif($tarea_dia['prioridad'] == 'Alta') $clase_bar = "st-vencida"; // Usar rojo para alta prioridad visual
                                            
                                            // Solo dibujar nombre si es el día de inicio para no repetir
                                            $mostrar_nombre = ($fecha_celda == $tarea_dia['fecha_inicio']) ? $tarea_dia['titulo'] : "";
                                            $tooltip = $tarea_dia['titulo'] . " (" . $tarea_dia['estado'] . ")";
                                        ?>
                                            <div class="task-bar <?php echo $clase_bar; ?>" title="<?php echo $tooltip; ?>" onclick="verDetalleTarea(<?php echo htmlspecialchars(json_encode($tarea_dia)); ?>)">
                                                <?php echo $mostrar_nombre; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:10px; font-size:0.85em; color:#666; display:flex; gap:15px;">
                    <span style="display:flex; align-items:center;"><div style="width:12px; height:12px; background:#17a2b8; margin-right:5px; border-radius:2px;"></div> Pendiente</span>
                    <span style="display:flex; align-items:center;"><div style="width:12px; height:12px; background:#28a745; margin-right:5px; border-radius:2px;"></div> Completada</span>
                    <span style="display:flex; align-items:center;"><div style="width:12px; height:12px; background:#dc3545; margin-right:5px; border-radius:2px;"></div> Vencida/Alta</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VERIFICACIÓN / COMPLETAR -->
<div id="modal_completar" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="cerrarModalCompletar()">&times;</span>
        <h3 style="color:#0056b3; margin-top:0;">✅ Verificar Cumplimiento</h3>
        <p>Tarea: <b id="lbl_titulo_tarea"></b></p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_tarea_completar" id="id_tarea_completar">
            
            <div class="alert" style="background:#e9ecef; border:1px solid #ddd; font-size:0.9em;">
                <i class="fa-solid fa-camera"></i> <b>Evidencia Requerida:</b><br>
                Sube una foto que demuestre que la tarea fue realizada. Se registrará la fecha y hora actual automáticamente.
            </div>
            
            <div style="margin-bottom:15px;">
                <label>Foto de Prueba:</label>
                <input type="file" name="foto_evidencia" accept="image/*" required>
            </div>
            
            <div style="margin-bottom:15px;">
                <label>Observaciones de Cierre:</label>
                <textarea name="obs_cierre" rows="2" placeholder="Ej: Se realizó limpieza con el químico X..."></textarea>
            </div>
            
            <button type="submit" name="completar_tarea" class="btn-action btn-green" style="width:100%; font-size:1.1em;">
                <i class="fa-solid fa-check-circle"></i> CONFIRMAR TAREA REALIZADA
            </button>
        </form>
    </div>
</div>

<!-- MODAL DETALLE VISUAL -->
<div id="modal_detalle" class="modal">
    <div class="modal-content" style="border-top: 5px solid #17a2b8;">
        <span class="close-btn" onclick="document.getElementById('modal_detalle').style.display='none'">&times;</span>
        <h3 id="det_titulo" style="margin-top:0;"></h3>
        <p id="det_desc" style="color:#666; font-style:italic;"></p>
        <hr style="border:0; border-top:1px solid #eee;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:0.9em; margin-bottom:15px;">
            <div><b>Responsable:</b> <span id="det_resp"></span></div>
            <div><b>Estado:</b> <span id="det_estado"></span></div>
            <div><b>Desde:</b> <span id="det_inicio"></span></div>
            <div><b>Hasta:</b> <span id="det_fin"></span></div>
        </div>
        
        <!-- SI TIENE EVIDENCIA, MOSTRARLA -->
        <div id="box_evidencia" style="display:none; text-align:center; background:#f8f9fa; padding:10px; border-radius:5px;">
            <p style="font-weight:bold; color:#28a745; margin:0 0 5px 0;">📸 Evidencia Cargada:</p>
            <img id="img_evidencia" src="" style="max-width:100%; max-height:200px; border-radius:4px; border:1px solid #ddd;">
            <p id="det_fecha_cierre" style="font-size:0.8em; color:#666; margin-top:5px;"></p>
        </div>
    </div>
</div>

<script>
    function abrirModalCompletar(id, titulo) {
        document.getElementById('id_tarea_completar').value = id;
        document.getElementById('lbl_titulo_tarea').innerText = titulo;
        document.getElementById('modal_completar').style.display = 'flex';
    }
    
    function cerrarModalCompletar() {
        document.getElementById('modal_completar').style.display = 'none';
    }

    function verDetalleTarea(tarea) {
        document.getElementById('det_titulo').innerText = tarea.titulo;
        document.getElementById('det_desc').innerText = tarea.descripcion || "Sin descripción";
        document.getElementById('det_resp').innerText = tarea.Responsable;
        document.getElementById('det_estado').innerText = tarea.estado;
        document.getElementById('det_inicio').innerText = tarea.fecha_inicio;
        document.getElementById('det_fin').innerText = tarea.fecha_limite;
        
        const boxImg = document.getElementById('box_evidencia');
        if(tarea.foto_evidencia) {
            boxImg.style.display = 'block';
            document.getElementById('img_evidencia').src = 'uploads/evidencias/' + tarea.foto_evidencia;
            document.getElementById('det_fecha_cierre').innerText = "Cerrado el: " + tarea.fecha_completado;
        } else {
            boxImg.style.display = 'none';
        }
        
        document.getElementById('modal_detalle').style.display = 'flex';
    }
</script>

</body>
</html>
<?php
// agua.php
// Versión Final: Huella Hídrica, Gráficos, Costos Desglosados y EDICIÓN COMPLETA
header('Content-Type: text/html; charset=utf-8');
include 'auth.php';
include 'conexion.php';

$mensaje = "";
$error = "";

// Variables para el formulario (se llenan si estamos editando)
$edit_id = "";
$edit_fini = "";
$edit_ffin = "";
$edit_m3 = "";
$edit_fact = "";
$edit_aseo = "";
$edit_obs = "";
$modo_edicion = false;

// 1. CARGAR DATOS PARA EDITAR (Si se pulsó el lápiz)
if (isset($_GET['editar'])) {
    $id_edit = intval($_GET['editar']);
    $res_edit = $conn->query("SELECT * FROM control_agua WHERE id=$id_edit");
    if ($res_edit->num_rows > 0) {
        $fila_edit = $res_edit->fetch_assoc();
        $edit_id = $fila_edit['id'];
        $edit_fini = $fila_edit['fecha_inicio'];
        $edit_ffin = $fila_edit['fecha_fin'];
        $edit_m3 = $fila_edit['consumo_m3'];
        $edit_fact = $fila_edit['valor_factura'];
        $edit_aseo = $fila_edit['valor_aseo'];
        $edit_obs = $fila_edit['observaciones'];
        $modo_edicion = true;
    }
}

// 2. PROCESAR EL GUARDADO (CREAR O ACTUALIZAR)
if (isset($_POST['guardar'])) {
    $f_ini = $_POST['f_inicio'];
    $f_fin = $_POST['f_fin'];
    $m3 = floatval($_POST['consumo_m3']);
    $factura = floatval($_POST['valor_factura']);
    $aseo = floatval($_POST['valor_aseo']);
    $obs = $conn->real_escape_string($_POST['observaciones']);
    
    // Si viene un ID oculto, es ACTUALIZACIÓN
    if (!empty($_POST['id_edicion'])) {
        $id_upd = intval($_POST['id_edicion']);
        $sql = "UPDATE control_agua SET 
                fecha_inicio='$f_ini', fecha_fin='$f_fin', consumo_m3='$m3', 
                valor_factura='$factura', valor_aseo='$aseo', observaciones='$obs' 
                WHERE id=$id_upd";
        $msg_exito = "&#9989; Registro actualizado correctamente.";
    } else {
        // Si no, es NUEVO
        $sql = "INSERT INTO control_agua (fecha_inicio, fecha_fin, consumo_m3, valor_factura, valor_aseo, observaciones) 
                VALUES ('$f_ini', '$f_fin', '$m3', '$factura', '$aseo', '$obs')";
        $msg_exito = "&#9989; Nuevo registro guardado.";
    }

    if ($conn->query($sql)) { 
        $mensaje = $msg_exito;
        // Limpiar formulario después de guardar
        $modo_edicion = false; $edit_id=""; $edit_fini=""; $edit_ffin=""; $edit_m3=""; $edit_fact=""; $edit_aseo=""; $edit_obs="";
    } else { 
        $error = "&#10060; Error: " . $conn->error; 
    }
}

// 3. BORRAR
if (isset($_GET['borrar'])) {
    $id = intval($_GET['borrar']);
    $conn->query("DELETE FROM control_agua WHERE id=$id");
    header("Location: agua.php");
}

// 4. OBTENER HISTORIAL
$resultado = $conn->query("SELECT * FROM control_agua ORDER BY fecha_inicio DESC");

// 5. DATOS PARA EL GRÁFICO (AÑO ACTUAL)
$anio_actual = date("Y");
$sql_graf = "SELECT * FROM control_agua ORDER BY fecha_inicio ASC LIMIT 12"; // Mostramos ultimos 12 para tendencia
$res_graf = $conn->query($sql_graf);

$labels_graf = [];
$data_consumo = [];
$data_ahorro = [];
$total_ahorro_anual = 0; 

while($row = $res_graf->fetch_assoc()) {
    $mes_anio = date("M y", strtotime($row['fecha_inicio'])); 
    $labels_graf[] = $mes_anio;
    
    $cons = floatval($row['consumo_m3']);
    $data_consumo[] = $cons;
    
    $ahorro_m3 = max(0, 30 - $cons);
    $data_ahorro[] = $ahorro_m3;
    
    $valor_neto_agua = $row['valor_factura'] - $row['valor_aseo'];
    $valor_m3_real = ($cons > 0) ? ($valor_neto_agua / $cons) : 0;
    
    if (date("Y", strtotime($row['fecha_inicio'])) == date("Y")) {
        $total_ahorro_anual += ($ahorro_m3 * $valor_m3_real);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control Hídrico - INARPLAS</title>
    <link rel="stylesheet" href="estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .layout-agua { display: flex; gap: 20px; flex-wrap: wrap; }
        .col-izq { flex: 1; min-width: 300px; }
        .col-der { flex: 3; min-width: 600px; }
        
        .kpi-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; border-left: 5px solid #28a745; margin-bottom: 20px; }
        .kpi-val { font-size: 2em; font-weight: bold; color: #155724; }
        .kpi-tit { color: #666; text-transform: uppercase; font-size: 0.9em; }
        
        /* Formulario dinámico (cambia color si edita) */
        .card-form { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #007bff; }
        .card-edit { border-top-color: #ffc107; background-color: #fffbf0; } /* Estilo Amarillo para edición */
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        .tabla-datos { width: 100%; border-collapse: collapse; background: white; font-size: 0.85em; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .tabla-datos th, .tabla-datos td { padding: 10px 5px; border-bottom: 1px solid #eee; text-align: center; }
        .tabla-datos th { background: #0056b3; color: white; vertical-align: middle; }
        .tabla-datos tr:hover { background-color: #f1f1f1; }
        
        .costo-fijo { color: #856404; background: #fff3cd; }
        .costo-var { color: #004085; background: #cce5ff; }
        .huella-hidrica { color: #155724; background: #d4edda; font-weight: bold; border-left: 2px solid #28a745; }
        
        .btn-accion { text-decoration: none; font-size: 1.2em; margin: 0 5px; cursor: pointer; border: none; background: none; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; height: 300px; }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="logo">&#128167; INARPLAS Agua</div>
    <div class="user-info"><b><?php echo $_SESSION['nombre']; ?></b> | <a href="dashboard.php" style="color:white;">Volver</a></div>
</div>

<div class="main-container">
    <div class="sidebar">
        <h3>Men&uacute; Principal</h3>
        <ul>
            <li><a href="dashboard.php">&#127968; Inicio</a></li>
            <li><a href="energia.php">&#9889; Control Energ&iacute;a</a></li>
            <li><a href="agua.php" class="active">&#128167; Control Agua</a></li>
            <li><a href="dashboard.php">... Volver al Men&uacute;</a></li>
        </ul>
    </div>

    <div class="content">
        <h2>&#128167; Gesti&oacute;n del Recurso H&iacute;drico</h2>
        
        <?php if($mensaje) echo "<p style='color:green; background:#d4edda; padding:10px;'>$mensaje</p>"; ?>
        <?php if($error) echo "<p style='color:red; background:#f8d7da; padding:10px;'>$error</p>"; ?>

        <div class="layout-agua">
            
            <!-- COLUMNA IZQUIERDA: FORMULARIO -->
            <div class="col-izq">
                <!-- KPI -->
                <div class="kpi-box">
                    <div class="kpi-tit">Ahorro Anual (Agua Lluvia)</div>
                    <div class="kpi-val">$<?php echo number_format($total_ahorro_anual, 0); ?></div>
                    <small>Calculado sobre base de 30m&sup3;/mes</small>
                </div>

                <!-- FORMULARIO INTELIGENTE -->
                <div class="card-form <?php echo ($modo_edicion) ? 'card-edit' : ''; ?>">
                    <h3>
                        <?php echo ($modo_edicion) ? '&#9998; Editando Registro' : '&#128195; Nuevo Recibo'; ?>
                    </h3>
                    
                    <form method="POST" action="agua.php">
                        <input type="hidden" name="id_edicion" value="<?php echo $edit_id; ?>">
                        
                        <div class="form-group">
                            <label>Inicio Lectura:</label>
                            <input type="date" name="f_inicio" required value="<?php echo $edit_fini; ?>">
                        </div>
                        <div class="form-group">
                            <label>Fin Lectura:</label>
                            <input type="date" name="f_fin" required value="<?php echo $edit_ffin; ?>">
                        </div>
                        <div class="form-group">
                            <label>Consumo Mes (m&sup3;):</label>
                            <input type="number" step="0.01" name="consumo_m3" placeholder="Ej: 12" required value="<?php echo $edit_m3; ?>">
                        </div>
                        <div class="form-group">
                            <label>Valor Total Factura ($):</label>
                            <input type="number" name="valor_factura" placeholder="Total a pagar" required value="<?php echo $edit_fact; ?>">
                        </div>
                        <div class="form-group">
                            <label>Valor Aseo/Fijos ($):</label>
                            <input type="number" name="valor_aseo" placeholder="Cargos fijos" value="<?php echo $edit_aseo; ?>">
                        </div>
                        <div class="form-group">
                            <label>Observaciones:</label>
                            <textarea name="observaciones" rows="2"><?php echo $edit_obs; ?></textarea>
                        </div>
                        
                        <?php if($modo_edicion): ?>
                            <div style="display:flex; gap:10px;">
                                <button type="submit" name="guardar" class="btn-save" style="background:#ffc107; color:black; flex:2;">Actualizar Cambios</button>
                                <a href="agua.php" class="btn-save" style="background:#6c757d; text-align:center; text-decoration:none; flex:1;">Cancelar</a>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="guardar" class="btn-save" style="width:100%;">Guardar Registro</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- COLUMNA DERECHA: GRÁFICO Y TABLA -->
            <div class="col-der">
                
                <div class="chart-container">
                    <canvas id="graficoAgua"></canvas>
                </div>

                <h3>&#128202; An&aacute;lisis Detallado</h3>
                <div style="overflow-x:auto;">
                    <table class="tabla-datos">
                        <thead>
                            <tr>
                                <th>Periodo</th>
                                <th>Prod.<br>(Kg)</th>
                                <th>m&sup3;</th>
                                <th>Costo<br>Fijo</th>
                                <th>Costo<br>Var.</th>
                                <th>Ahorro<br>($)</th>
                                <th class="huella-hidrica">Huella<br>(L/Kg)</th>
                                <th>Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $resultado->data_seek(0);
                            while($row = $resultado->fetch_assoc()) { 
                                $fi = $row['fecha_inicio'];
                                $ff = $row['fecha_fin'];
                                $fecha_display = date("M d, y", strtotime($fi)) . " - " . date("M d, y", strtotime($ff));
                                
                                $sql_peso = "SELECT SUM(T.unidades_buenas * M.PesoUnidad) FROM turnos_registros T LEFT JOIN produccion_master M ON T.numero_orden = M.NumeroOrden WHERE T.fecha BETWEEN '$fi' AND '$ff'";
                                $res_peso = $conn->query($sql_peso);
                                $peso_g = ($res_peso) ? floatval($res_peso->fetch_array()[0]) : 0;
                                $peso_kg = $peso_g / 1000; 

                                $factura = floatval($row['valor_factura']);
                                $aseo = floatval($row['valor_aseo']);
                                $agua_pura = $factura - $aseo;
                                $consumo_m3 = floatval($row['consumo_m3']);
                                $valor_m3 = ($consumo_m3 > 0) ? ($agua_pura / $consumo_m3) : 0;
                                $ahorro_m3 = max(0, 30 - $consumo_m3);
                                $dinero_ahorro = $ahorro_m3 * $valor_m3;
                                $litros = $consumo_m3 * 1000;
                                $huella = ($peso_kg > 0) ? ($litros / $peso_kg) : 0;
                            ?>
                            <tr>
                                <td><small><?php echo $fecha_display; ?></small></td>
                                <td><b><?php echo number_format($peso_kg, 0); ?></b></td>
                                <td><?php echo $consumo_m3; ?></td>
                                <td class="costo-fijo">$<?php echo number_format($aseo, 0); ?></td>
                                <td class="costo-var">$<?php echo number_format($agua_pura, 0); ?></td>
                                <td style="color:green;">$<?php echo number_format($dinero_ahorro, 0); ?></td>
                                <td class="huella-hidrica"><?php echo number_format($huella, 2); ?></td>
                                <td>
                                    <!-- BOTÓN EDITAR (LÁPIZ) -->
                                    <a href="agua.php?editar=<?php echo $row['id']; ?>" class="btn-accion" title="Editar" style="color:#ffc107;">&#9998;</a>
                                    
                                    <!-- BOTÓN BORRAR (PAPELERA) -->
                                    <a href="agua.php?borrar=<?php echo $row['id']; ?>" onclick="return confirm('¿Borrar este registro?');" class="btn-accion" title="Eliminar" style="color:#dc3545;">&#128465;</a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('graficoAgua').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($labels_graf); ?>,
            datasets: [
                { label: 'Consumo (m³)', data: <?php echo json_encode($data_consumo); ?>, backgroundColor: 'rgba(54, 162, 235, 0.6)' },
                { label: 'Ahorro (m³)', data: <?php echo json_encode($data_ahorro); ?>, backgroundColor: 'rgba(40, 167, 69, 0.6)' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: { x: { stacked: true }, y: { beginAtZero: true, stacked: true } }
        }
    });
</script>

</body>
</html>
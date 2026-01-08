<?php
// public_html/sistema/energia.php
// Fecha: 13-12-2025 17:15
// DESCRIPCIÓN: Control de Energía COMPLETO.
// CORRECCIÓN: Ajuste en cálculo JS de Factura Real (Factor x120 agregado).

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'auth.php';
include 'conexion.php';

// --- 1. ASEGURAR TABLAS EN BASE DE DATOS ---

// Tabla Diaria
$conn->query("CREATE TABLE IF NOT EXISTS control_energia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_lectura DATE NOT NULL,
    lectura_contador DECIMAL(10,3) NOT NULL,
    consumo_kw DECIMAL(10,3) DEFAULT 0,
    valor_kwh DECIMAL(10,2) DEFAULT 0,
    costo_estimado DECIMAL(10,2) DEFAULT 0,
    contribucion DECIMAL(10,2) DEFAULT 0,
    total_pagar DECIMAL(10,2) DEFAULT 0,
    observaciones TEXT
) ENGINE=InnoDB");

// Tabla Facturas Reales
$conn->query("CREATE TABLE IF NOT EXISTS facturas_energia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mes_facturado VARCHAR(7) NOT NULL,
    fecha_pago DATE,
    lectura_anterior DECIMAL(10,2),
    lectura_actual DECIMAL(10,2),
    consumo_facturado DECIMAL(10,2),
    valor_unitario_kwh DECIMAL(10,2),
    cargo_fijo DECIMAL(10,2) DEFAULT 0,
    costo_energia DECIMAL(10,2),
    alumbrado_publico DECIMAL(10,2) DEFAULT 0,
    contribucion DECIMAL(10,2) DEFAULT 0,
    otros_cargos DECIMAL(10,2) DEFAULT 0,
    total_pagado DECIMAL(12,2),
    observaciones TEXT
) ENGINE=InnoDB");

$mensaje = "";
$error = "";
$calculo_proyeccion = null;

// --- 2. GUARDAR LECTURA DIARIA ---
if (isset($_POST['guardar_lectura'])) {
    $fecha = $_POST['fecha'];
    $lectura = floatval($_POST['lectura']);
    $obs = isset($_POST['observaciones']) ? $_POST['observaciones'] : '';
    
    // Buscar lectura anterior
    $last_reading = 0;
    $stmt_last = $conn->prepare("SELECT lectura_contador FROM control_energia WHERE fecha_lectura < ? ORDER BY fecha_lectura DESC LIMIT 1");
    if ($stmt_last) {
        $stmt_last->bind_param("s", $fecha);
        $stmt_last->execute();
        $lectura_temp = 0;
        $stmt_last->bind_result($lectura_temp); 
        if ($stmt_last->fetch()) { $last_reading = floatval($lectura_temp); }
        $stmt_last->close();
    }
    
    $consumo = ($last_reading > 0 && $lectura >= $last_reading) ? ($lectura - $last_reading) : 0;

    $stmt = $conn->prepare("INSERT INTO control_energia (fecha_lectura, lectura_contador, consumo_kw, observaciones) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sdds", $fecha, $lectura, $consumo, $obs);
        if ($stmt->execute()) { $mensaje = "✅ Lectura diaria registrada. Consumo: <b>$consumo</b>"; } 
        else { $error = "❌ Error SQL: " . $stmt->error; }
        $stmt->close();
    }
}

// --- 3. GUARDAR FACTURA REAL ---
if (isset($_POST['guardar_factura_real'])) {
    $mes = $_POST['mes_factura'];
    $f_pago = $_POST['fecha_pago'];
    $lec_ant = floatval($_POST['lec_ant']);
    $lec_act = floatval($_POST['lec_act']);
    $cons_fact = floatval($_POST['cons_fact']);
    $val_unit = floatval($_POST['val_unit']);
    $c_fijo = floatval($_POST['c_fijo']);
    $c_energia = floatval($_POST['c_energia']);
    $alumbrado = floatval($_POST['alumbrado']);
    $contrib = floatval($_POST['contrib']);
    $otros = floatval($_POST['otros']);
    $total_fac = floatval($_POST['total_fac']);
    $obs_fac = $_POST['obs_factura'];

    $stmt_fac = $conn->prepare("INSERT INTO facturas_energia (mes_facturado, fecha_pago, lectura_anterior, lectura_actual, consumo_facturado, valor_unitario_kwh, cargo_fijo, costo_energia, alumbrado_publico, contribucion, otros_cargos, total_pagado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if($stmt_fac) {
        $stmt_fac->bind_param("ssdddddddddds", $mes, $f_pago, $lec_ant, $lec_act, $cons_fact, $val_unit, $c_fijo, $c_energia, $alumbrado, $contrib, $otros, $total_fac, $obs_fac);
        if($stmt_fac->execute()) {
            $mensaje = "✅ Factura Real del mes <b>$mes</b> guardada correctamente.";
        } else {
            $error = "❌ Error al guardar factura: " . $stmt_fac->error;
        }
        $stmt_fac->close();
    }
}

// --- 4. CALCULADORA PROYECCIÓN ---
if (isset($_POST['calcular_proyeccion'])) {
    $lec_inicio = floatval($_POST['lec_inicio']);
    $lec_fin = floatval($_POST['lec_fin']);
    $precio_kwh = floatval($_POST['precio_simulado']);
    $factor = 120; 
    
    $diferencia = max(0, $lec_fin - $lec_inicio);
    $consumo_real = $diferencia * $factor;
    $costo_base = $consumo_real * $precio_kwh;
    $impuesto = $costo_base * 0.20; 
    $total_pagar = $costo_base + $impuesto;
    
    $calculo_proyeccion = [
        'diferencia' => $diferencia,
        'consumo_real' => $consumo_real,
        'costo_base' => $costo_base,
        'impuesto' => $impuesto,
        'total' => $total_pagar
    ];
}

// --- 5. BORRAR REGISTROS ---
if (isset($_GET['borrar'])) {
    $id = intval($_GET['borrar']);
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'lectura';
    
    if($tipo == 'factura') {
        $conn->query("DELETE FROM facturas_energia WHERE id=$id");
    } else {
        $conn->query("DELETE FROM control_energia WHERE id=$id");
    }
    echo "<script>window.location.href='energia.php';</script>";
    exit();
}

// CONSULTAS PARA TABLAS
$res_lecturas = $conn->query("SELECT * FROM control_energia ORDER BY fecha_lectura DESC LIMIT 50");
$res_facturas = $conn->query("SELECT * FROM facturas_energia ORDER BY mes_facturado DESC LIMIT 12");

// AJAX PRE-LLENADO
$val_ini = ""; $val_fin = "";
if(isset($_GET['fi']) && isset($_GET['ff'])) {
    $fi = $conn->real_escape_string($_GET['fi']); 
    $ff = $conn->real_escape_string($_GET['ff']);
    
    $r1 = $conn->query("SELECT lectura_contador FROM control_energia WHERE fecha_lectura <= '$fi' ORDER BY fecha_lectura DESC LIMIT 1");
    if($r1 && $r1->num_rows > 0) { $row1 = $r1->fetch_assoc(); $val_ini = $row1['lectura_contador']; }
    
    $r2 = $conn->query("SELECT lectura_contador FROM control_energia WHERE fecha_lectura <= '$ff' ORDER BY fecha_lectura DESC LIMIT 1");
    if($r2 && $r2->num_rows > 0) { $row2 = $r2->fetch_assoc(); $val_fin = $row2['lectura_contador']; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control Energía - INARPLAS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding-bottom: 50px; }
        .header-bar { background: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; max-width: 1400px; margin: 0 auto; display: flex; gap: 20px; flex-wrap: wrap; }
        
        .sidebar { width: 250px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); height: fit-content; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar a { text-decoration: none; color: #555; display: block; padding: 10px; border-radius: 5px; transition: 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: #e3f2fd; color: #0056b3; font-weight: bold; }
        
        .content { flex: 1; min-width: 300px; }
        
        /* PANELES */
        .panel-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #ccc; position: relative; }
        .card h3 { margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; font-size: 1.1em; display: flex; align-items: center; gap: 10px; }
        
        /* FORMULARIOS */
        .form-row { display: flex; gap: 10px; margin-bottom: 10px; }
        .form-col { flex: 1; }
        label { display: block; font-weight: 600; margin-bottom: 5px; color: #555; font-size: 0.85em; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        /* BOTONES */
        .btn-save { border: none; padding: 10px; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; width: 100%; margin-top: 10px; }
        .btn-green { background: #28a745; } .btn-green:hover { background: #218838; }
        .btn-yellow { background: #ffc107; color: #333; } 
        .btn-blue { background: #0056b3; } 
        
        /* TABLAS */
        table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
        th { background: #343a40; color: white; padding: 8px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        .badge-pago { background: #d4edda; color: #155724; padding: 3px 6px; border-radius: 4px; font-weight: bold; }
        
        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; width: 95%; max-width: 600px; padding: 25px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto; }
        
        /* CALCULADORA RES */
        .res-box { background: #fff3cd; padding: 10px; border-radius: 5px; border: 1px solid #ffeeba; margin-top: 10px; font-size: 0.9em; }
        .res-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .total-big { font-size: 1.2em; font-weight: bold; color: #0056b3; text-align: right; border-top: 1px solid #ccc; padding-top: 5px; }

        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
    <script>
        function traerLecturas() {
            let f1 = document.getElementById('f_inicio').value;
            let f2 = document.getElementById('f_fin').value;
            if(f1 && f2) {
                window.location.href = "energia.php?fi=" + f1 + "&ff=" + f2;
            }
        }
        function abrirModalFactura() { document.getElementById('modal_factura').style.display = 'flex'; }
        function cerrarModalFactura() { document.getElementById('modal_factura').style.display = 'none'; }
        
        // --- FUNCIÓN DE CÁLCULO AUTOMÁTICO (CORREGIDA) ---
        function calcularFacturaAuto() {
            // Obtener valores (o 0 si están vacíos)
            let lecAnt = parseFloat(document.getElementById('lec_ant').value) || 0;
            let lecAct = parseFloat(document.getElementById('lec_act').value) || 0;
            let valUnit = parseFloat(document.getElementById('val_unit').value) || 0;
            
            // 1. Calcular Diferencia y Consumo Real (Factor 120)
            let diferencia = 0;
            if(lecAct >= lecAnt) {
                diferencia = lecAct - lecAnt;
            }
            
            // Aplicar FACTOR X120
            let consumo = diferencia * 120;
            document.getElementById('cons_fact').value = consumo.toFixed(2);
            
            // 2. Calcular Costo Energía Base
            let costoEnergia = consumo * valUnit;
            document.getElementById('c_energia').value = costoEnergia.toFixed(2);
            
            // 3. Calcular Contribución (20%)
            let contrib = costoEnergia * 0.20;
            document.getElementById('contrib').value = contrib.toFixed(2);
            
            // 4. Calcular Total
            let alumbrado = parseFloat(document.getElementById('alumbrado').value) || 0;
            let cFijo = parseFloat(document.getElementById('c_fijo').value) || 0;
            let otros = parseFloat(document.getElementById('otros').value) || 0;
            
            let total = costoEnergia + contrib + alumbrado + cFijo + otros;
            document.getElementById('total_fac').value = total.toFixed(2);
        }
    </script>
</head>
<body>

<div class="header-bar">
    <div style="font-weight:bold; font-size:1.2em;"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <a href="dashboard.php" style="color:white; text-decoration:none;">Inicio</a>
</div>

<div class="container">
    <nav class="sidebar">
        <h3>Menú Principal</h3>
        <ul>
            <li><a href="dashboard.php">Inicio</a></li>
            <li><a href="ordenes.php">Órdenes</a></li>
            <li><a href="gestion_turno.php">Turnos</a></li>
            <li><a href="energia.php" class="active">Energía</a></li>
            <li><a href="agua.php">Agua</a></li>
        </ul>
    </nav>

    <div class="content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h2 style="margin:0;"><i class="fa-solid fa-bolt"></i> Gestión Energética</h2>
            <!-- BOTÓN AZUL PARA INGRESAR FACTURA REAL -->
            <button onclick="abrirModalFactura()" class="btn-save btn-blue" style="width:auto; padding:10px 20px;">
                <i class="fa-solid fa-file-invoice-dollar"></i> Ingresar Factura Real
            </button>
        </div>
        
        <?php if($mensaje) echo "<div class='alert success'>$mensaje</div>"; ?>
        <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

        <div class="panel-grid">
            <!-- 1. LECTURA DIARIA -->
            <div class="card" style="border-top-color: #28a745;">
                <h3><i class="fa-solid fa-pen"></i> Lectura Diaria Medidor</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-col"><label>Fecha:</label><input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></div>
                        <div class="form-col"><label>Lectura:</label><input type="number" step="0.001" name="lectura" required placeholder="Ej: 10615.062"></div>
                    </div>
                    <div class="form-group"><label>Obs:</label><textarea name="observaciones" rows="1"></textarea></div>
                    <button type="submit" name="guardar_lectura" class="btn-save btn-green">Guardar</button>
                </form>
            </div>

            <!-- 2. CALCULADORA -->
            <div class="card" style="border-top-color: #ffc107;">
                <h3><i class="fa-solid fa-calculator"></i> Calculadora (x120)</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-col"><label>Desde:</label><input type="date" name="f_inicio" id="f_inicio" value="<?php echo isset($_GET['fi']) ? $_GET['fi'] : ''; ?>" onchange="traerLecturas()"></div>
                        <div class="form-col"><label>Hasta:</label><input type="date" name="f_fin" id="f_fin" value="<?php echo isset($_GET['ff']) ? $_GET['ff'] : ''; ?>" onchange="traerLecturas()"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><input type="number" step="0.001" name="lec_inicio" placeholder="Lec. Ini" value="<?php echo $val_ini; ?>" required></div>
                        <div class="form-col"><input type="number" step="0.001" name="lec_fin" placeholder="Lec. Fin" value="<?php echo $val_fin; ?>" required></div>
                    </div>
                    <div class="form-group" style="margin-top:10px;">
                        <label>Precio Estimado kWh ($):</label>
                        <input type="number" step="0.01" name="precio_simulado" value="853.92" required>
                    </div>
                    <button type="submit" name="calcular_proyeccion" class="btn-save btn-yellow">Calcular</button>
                </form>
                
                <?php if ($calculo_proyeccion): ?>
                <div class="res-box">
                    <div class="res-row"><span>Consumo Real:</span> <strong><?php echo number_format($calculo_proyeccion['consumo_real'], 2); ?> kW</strong></div>
                    <div class="res-row"><span>Costo Energía:</span> <span>$<?php echo number_format($calculo_proyeccion['costo_base'], 0); ?></span></div>
                    <div class="res-row"><span>Impuestos:</span> <span>$<?php echo number_format($calculo_proyeccion['impuesto'], 0); ?></span></div>
                    <div class="total-big">$<?php echo number_format($calculo_proyeccion['total'], 0); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- LISTADO DE FACTURAS REALES (NUEVA TABLA) -->
        <div class="card" style="margin-bottom:20px; border-top-color: #0056b3;">
            <h3><i class="fa-solid fa-file-invoice"></i> Historial Facturas Pagadas</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Pago</th>
                            <th>Consumo (kW)</th>
                            <th>Valor Unit.</th>
                            <th>Energía</th>
                            <th>Otros Cargos</th>
                            <th>Total Pagado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($f = $res_facturas->fetch_assoc()) { ?>
                        <tr>
                            <td><b><?php echo $f['mes_facturado']; ?></b></td>
                            <td><?php echo $f['fecha_pago']; ?></td>
                            <td><?php echo number_format($f['consumo_facturado'], 0); ?></td>
                            <td>$<?php echo number_format($f['valor_unitario_kwh'], 2); ?></td>
                            <td>$<?php echo number_format($f['costo_energia'], 0); ?></td>
                            <td>$<?php echo number_format($f['alumbrado_publico'] + $f['contribucion'] + $f['otros_cargos'], 0); ?></td>
                            <td><span class="badge-pago">$<?php echo number_format($f['total_pagado'], 0); ?></span></td>
                            <td><a href="energia.php?borrar=<?php echo $f['id']; ?>&tipo=factura" onclick="return confirm('¿Borrar factura?')" style="color:red;"><i class="fa-solid fa-trash"></i></a></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- LISTADO DIARIO (TABLA ORIGINAL) -->
        <div class="card">
            <h3><i class="fa-solid fa-list"></i> Últimas Lecturas Diarias</h3>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr><th>Fecha</th><th>Lectura</th><th>Diferencia</th><th>Consumo (x120)</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php while($row = $res_lecturas->fetch_assoc()) { 
                            $diff = $row['consumo_kw'];
                            $real = $diff * 120;
                        ?>
                        <tr>
                            <td><?php echo $row['fecha_lectura']; ?></td>
                            <td><b><?php echo number_format($row['lectura_contador'], 3); ?></b></td>
                            <td><?php echo number_format($diff, 3); ?></td>
                            <td style="color:#0056b3;"><?php echo number_format($real, 2); ?> kW</td>
                            <td><a href="energia.php?borrar=<?php echo $row['id']; ?>" onclick="return confirm('¿Borrar lectura?')" style="color:red;"><i class="fa-solid fa-trash"></i></a></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- MODAL INGRESO FACTURA REAL (FORMULARIO DETALLADO) -->
<div id="modal_factura" class="modal">
    <div class="modal-content">
        <span onclick="cerrarModalFactura()" style="float:right; cursor:pointer; font-size:1.5em;">&times;</span>
        <h3 style="margin-top:0; color:#0056b3;">Ingresar Factura Real (Enel/Codensa)</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-col"><label>Mes Facturado:</label><input type="month" name="mes_factura" required></div>
                <div class="form-col"><label>Fecha Pago:</label><input type="date" name="fecha_pago" required></div>
            </div>
            
            <h4 style="margin:10px 0 5px 0; border-bottom:1px solid #eee;">Datos Medidor (Factura)</h4>
            <div class="form-row">
                <div class="form-col"><label>Lec. Anterior:</label><input type="number" step="0.01" name="lec_ant" id="lec_ant" oninput="calcularFacturaAuto()"></div>
                <div class="form-col"><label>Lec. Actual:</label><input type="number" step="0.01" name="lec_act" id="lec_act" oninput="calcularFacturaAuto()"></div>
                <div class="form-col"><label>Consumo kW (Calc):</label><input type="number" step="0.01" name="cons_fact" id="cons_fact" required oninput="calcularFacturaAuto()" readonly style="background:#e9ecef; font-weight:bold;"></div>
            </div>
            
            <h4 style="margin:10px 0 5px 0; border-bottom:1px solid #eee;">Valores ($)</h4>
            <div class="form-row">
                <div class="form-col"><label>Valor Unit. kWh:</label><input type="number" step="0.01" name="val_unit" id="val_unit" required oninput="calcularFacturaAuto()"></div>
                <div class="form-col"><label>Costo Energía (Subt):</label><input type="number" step="0.01" name="c_energia" id="c_energia" required oninput="calcularFacturaAuto()"></div>
            </div>
            <div class="form-row">
                <div class="form-col"><label>Alumbrado Público:</label><input type="number" step="0.01" name="alumbrado" id="alumbrado" value="0" oninput="calcularFacturaAuto()"></div>
                <div class="form-col"><label>Contribución (20%):</label><input type="number" step="0.01" name="contrib" id="contrib" value="0" oninput="calcularFacturaAuto()"></div>
            </div>
            <div class="form-row">
                <div class="form-col"><label>Cargo Fijo:</label><input type="number" step="0.01" name="c_fijo" id="c_fijo" value="0" oninput="calcularFacturaAuto()"></div>
                <div class="form-col"><label>Otros Cargos/Ajustes:</label><input type="number" step="0.01" name="otros" id="otros" value="0" oninput="calcularFacturaAuto()"></div>
            </div>
            
            <div class="form-group" style="margin-top:10px; background:#e8f5e9; padding:10px; border-radius:5px;">
                <label style="color:#155724; font-size:1.1em;">TOTAL PAGADO:</label>
                <input type="number" step="0.01" name="total_fac" id="total_fac" required style="font-weight:bold; font-size:1.2em; color:#155724;">
            </div>
            
            <div class="form-group"><textarea name="obs_factura" placeholder="Observaciones..." rows="2"></textarea></div>
            
            <button type="submit" name="guardar_factura_real" class="btn-save btn-blue">Guardar Factura</button>
        </form>
    </div>
</div>

</body>
</html>
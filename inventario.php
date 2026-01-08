<?php
// public_html/sistema/inventario.php
// Fecha: 05-01-2026
// DESCRIPCIÓN: Módulo de Gestión de Inventarios con Selector de Órdenes.

include 'auth.php';
include 'conexion.php';

$mensaje = "";
$error = "";

// --- 1. REGISTRAR MOVIMIENTO (ENTRADA / SALIDA) ---
if (isset($_POST['guardar_movimiento'])) {
    $id_mat = intval($_POST['id_material']);
    $tipo = $_POST['tipo_movimiento'];
    $cant = floatval($_POST['cantidad']);
    // Si viene del select o del input antiguo, aseguramos capturar el valor
    $orden = isset($_POST['numero_orden']) ? $conn->real_escape_string($_POST['numero_orden']) : '';
    $obs = $conn->real_escape_string($_POST['observaciones']);
    $id_usuario = $_SESSION['id'] ?? 0;
    $fecha_form = $_POST['fecha_movimiento'] . ' ' . date('H:i:s');

    // Obtener saldo actual
    $q_saldo = $conn->query("SELECT saldo FROM materiales WHERE id = $id_mat");
    if ($q_saldo && $q_saldo->num_rows > 0) {
        $row = $q_saldo->fetch_assoc();
        $saldo_ant = floatval($row['saldo']);
        $saldo_nuevo = $saldo_ant;

        // Calcular nuevo saldo
        if ($tipo == 'Entrada') {
            $saldo_nuevo += $cant;
        } elseif ($tipo == 'Salida') {
            $saldo_nuevo -= $cant;
        } elseif ($tipo == 'Ajuste') {
            $saldo_nuevo = $cant;
        }

        // Validar Stock
        if ($saldo_nuevo < 0 && $tipo != 'Ajuste') {
            $error = "❌ Error: Stock insuficiente. Tienes $saldo_ant y intentas sacar $cant.";
        } else {
            // Actualizar Maestro
            $upd = $conn->query("UPDATE materiales SET saldo = $saldo_nuevo WHERE id = $id_mat");
            
            // Insertar Historial
            if ($upd) {
                $stmt = $conn->prepare("INSERT INTO movimientos_inventario (id_material, tipo_movimiento, cantidad, saldo_anterior, saldo_nuevo, numero_orden, fecha_movimiento, id_usuario, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdddssis", $id_mat, $tipo, $cant, $saldo_ant, $saldo_nuevo, $orden, $fecha_form, $id_usuario, $obs);
                
                if ($stmt->execute()) {
                    $mensaje = "✅ Movimiento registrado. Nuevo saldo: <b>" . number_format($saldo_nuevo, 2) . "</b>";
                } else {
                    $error = "❌ Error al guardar historial: " . $stmt->error;
                }
            } else {
                $error = "❌ Error al actualizar saldo: " . $conn->error;
            }
        }
    } else {
        $error = "❌ Material no encontrado.";
    }
}

// --- 2. CREAR NUEVO MATERIAL ---
if (isset($_POST['crear_material'])) {
    $ref = $conn->real_escape_string($_POST['referencia']);
    $desc = $conn->real_escape_string($_POST['descripcion']);
    $min = floatval($_POST['stock_minimo']);
    $ini = floatval($_POST['saldo_inicial']);

    $sql = "INSERT INTO materiales (referencia, descripcion, saldo, stock_minimo) VALUES ('$ref', '$desc', $ini, $min)";
    if ($conn->query($sql)) {
        $mensaje = "✅ Material <b>$desc</b> creado con éxito.";
    } else {
        $error = "❌ Error (posible referencia duplicada): " . $conn->error;
    }
}

// --- CONSULTAS DE VISUALIZACIÓN ---
// A. Listado de Materiales
$sql_mat = "SELECT * FROM materiales ORDER BY referencia ASC";
$res_mat = $conn->query($sql_mat);
$lista_materiales = [];
if($res_mat) {
    while($r = $res_mat->fetch_assoc()) { $lista_materiales[] = $r; }
}

// B. Listado de Órdenes Activas (PARA EL NUEVO SELECTOR)
$lista_ordenes = [];
$check_ord = $conn->query("SHOW TABLES LIKE 'produccion_master'");
if($check_ord && $check_ord->num_rows > 0) {
    // Traemos órdenes que NO estén finalizadas ni canceladas
    $sql_ord = "SELECT NumeroOrden, Producto FROM produccion_master WHERE Estado NOT IN ('Finalizada', 'Cancelada') ORDER BY NumeroOrden DESC";
    $res_ord = $conn->query($sql_ord);
    if($res_ord) {
        while($o = $res_ord->fetch_assoc()) { $lista_ordenes[] = $o; }
    }
}

// C. Historial de Movimientos
$sql_hist = "SELECT M.*, Mat.referencia, Mat.descripcion, U.nombre as Usuario 
             FROM movimientos_inventario M 
             LEFT JOIN materiales Mat ON M.id_material = Mat.id 
             LEFT JOIN usuarios U ON M.id_usuario = U.id 
             ORDER BY M.fecha_movimiento DESC LIMIT 50";
$res_hist = $conn->query($sql_hist);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario - INARPLAS Cloud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
        
        .header-bar { background-color: #0056b3; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; position: fixed; width: 100%; top: 0; z-index: 1000; box-sizing: border-box; }
        .main-wrapper { display: flex; margin-top: 50px; height: calc(100vh - 50px); }
        
        .sidebar { width: 240px; background: white; border-right: 1px solid #ddd; overflow-y: auto; flex-shrink: 0; }
        .sidebar h3 { padding: 15px 20px; margin: 0; color: #999; font-size: 0.8em; text-transform: uppercase; border-bottom: 1px solid #eee; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar a { display: block; padding: 12px 20px; color: #555; text-decoration: none; border-bottom: 1px solid #f0f0f0; }
        .sidebar a:hover, .sidebar a.active { background-color: #e3f2fd; color: #0056b3; font-weight: bold; }

        .content { flex-grow: 1; padding: 20px; overflow-y: auto; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 20px; margin-bottom: 20px; border-top: 4px solid #0056b3; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .btn { padding: 8px 15px; border-radius: 4px; border: none; font-weight: bold; cursor: pointer; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 0.9em; }
        .btn-green { background: #28a745; } .btn-green:hover { background: #218838; }
        .btn-blue { background: #0056b3; } .btn-blue:hover { background: #004494; }
        .btn-orange { background: #fd7e14; } .btn-orange:hover { background: #e36a0e; }
        .btn-gray { background: #6c757d; } .btn-gray:hover { background: #5a6268; }

        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        th { background: #f8f9fa; text-align: left; padding: 10px; color: #555; border-bottom: 2px solid #ddd; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9f9f9; }
        
        .stock-ok { color: #28a745; font-weight: bold; }
        .stock-low { color: #dc3545; font-weight: bold; background: #ffe6e6; padding: 2px 5px; border-radius: 4px; }
        .mov-in { color: #28a745; } .mov-out { color: #dc3545; }
        
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 10px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 0.9em; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background: white; width: 90%; max-width: 500px; padding: 25px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .search-box { padding: 8px; width: 250px; border: 1px solid #ddd; border-radius: 20px; outline: none; }
        
        .select2-container { width: 100% !important; margin-bottom: 10px; }
        .select2-selection { height: 38px !important; display: flex !important; align-items: center; }
    </style>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select-material').select2({ dropdownParent: $('#modal_movimiento') });
            $('.select-orden').select2({ dropdownParent: $('#modal_movimiento') }); // Select2 para Ordenes

            $("#inputBusqueda").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#tablaInventario tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });

        function abrirMovimiento(tipo) {
            $('#tipo_movimiento').val(tipo);
            $('#lbl_tipo').text(tipo);
            
            // Lógica para mostrar/ocultar el selector de orden
            if(tipo == 'Salida') {
                $('#grupo_orden').show();
                $('#btn_guardar_mov').removeClass('btn-green').addClass('btn-orange');
            } else {
                $('#grupo_orden').hide();
                $('#select_orden').val(null).trigger('change'); // Limpiar selección
                $('#btn_guardar_mov').removeClass('btn-orange').addClass('btn-green');
            }
            $('#modal_movimiento').fadeIn();
        }
    </script>
</head>
<body>

<div class="header-bar">
    <div style="font-weight:bold; font-size:1.2em;"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <a href="logout.php" style="color:white; text-decoration:none;">Salir</a>
</div>

<div class="main-wrapper">
    <nav class="sidebar">
        <h3>Menú Principal</h3>
        <ul>
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
            <li><a href="usuarios.php"><i class="fa-solid fa-users"></i> Empleados</a></li>
            <li><a href="maquinas.php"><i class="fa-solid fa-gears"></i> Máquinas</a></li>
            <li><a href="productos.php"><i class="fa-solid fa-box-open"></i> Productos</a></li>
            <li><a href="ordenes.php"><i class="fa-solid fa-clipboard-list"></i> Órdenes</a></li>
            <li><a href="gestion_turno.php"><i class="fa-solid fa-stopwatch"></i> Turnos</a></li>
            <li><a href="tareas.php"><i class="fa-solid fa-list-check"></i> Tareas</a></li>
            <li><a href="inventario.php" class="active"><i class="fa-solid fa-boxes-stacked"></i> Inventario</a></li>
            <li><a href="energia.php"><i class="fa-solid fa-bolt"></i> Energía</a></li>
        </ul>
    </nav>

    <div class="content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0; color:#333;"><i class="fa-solid fa-boxes-stacked"></i> Control de Inventario</h2>
            <div style="display:flex; gap:10px;">
                <a href="importar_inventario.php" class="btn btn-gray"><i class="fa-solid fa-file-import"></i> Importar CSV</a>
                <button onclick="document.getElementById('modal_crear').style.display='flex'" class="btn btn-blue"><i class="fa-solid fa-plus"></i> Nuevo</button>
                <button onclick="abrirMovimiento('Entrada')" class="btn btn-green"><i class="fa-solid fa-arrow-down"></i> Entrada</button>
                <button onclick="abrirMovimiento('Salida')" class="btn btn-orange"><i class="fa-solid fa-arrow-up"></i> Salida</button>
            </div>
        </div>
        
        <?php if($mensaje) echo "<div class='alert success'>$mensaje</div>"; ?>
        <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

        <!-- INVENTARIO ACTUAL -->
        <div class="card">
            <div class="card-header">
                <h3>Stock Actual</h3>
                <input type="text" id="inputBusqueda" class="search-box" placeholder="🔍 Buscar referencia...">
            </div>
            <div style="overflow-x:auto; max-height: 400px;">
                <table id="tablaMateriales">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Descripción</th>
                            <th>Ubicación</th>
                            <th>Saldo</th>
                            <th>Mínimo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tablaInventario">
                        <?php foreach($lista_materiales as $m): 
                            $estado = ($m['saldo'] <= $m['stock_minimo']) ? '<span class="stock-low">⚠️ REORDENAR</span>' : '<span class="stock-ok">OK</span>';
                        ?>
                        <tr>
                            <td><b><?php echo $m['referencia']; ?></b></td>
                            <td><?php echo $m['descripcion']; ?></td>
                            <td><?php echo $m['ubicacion'] ?: '-'; ?></td>
                            <td style="font-size:1.1em;"><b><?php echo number_format($m['saldo'], 2); ?></b></td>
                            <td style="color:#777;"><?php echo number_format($m['stock_minimo'], 0); ?></td>
                            <td><?php echo $estado; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- HISTORIAL KARDEX -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Últimos Movimientos</h3>
            </div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Material</th>
                            <th>Orden #</th>
                            <th>Cant.</th>
                            <th>Saldo Final</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($h = $res_hist->fetch_assoc()): 
                            $classMov = ($h['tipo_movimiento'] == 'Entrada') ? 'mov-in' : (($h['tipo_movimiento'] == 'Salida') ? 'mov-out' : '');
                            $iconMov = ($h['tipo_movimiento'] == 'Entrada') ? 'fa-arrow-down' : (($h['tipo_movimiento'] == 'Salida') ? 'fa-arrow-up' : 'fa-wrench');
                        ?>
                        <tr>
                            <td><?php echo date('d/m H:i', strtotime($h['fecha_movimiento'])); ?></td>
                            <td><b class="<?php echo $classMov; ?>"><i class="fa-solid <?php echo $iconMov; ?>"></i> <?php echo $h['tipo_movimiento']; ?></b></td>
                            <td><?php echo $h['referencia'] . ' - ' . $h['descripcion']; ?></td>
                            <td><?php echo $h['numero_orden'] ? "<b>#".$h['numero_orden']."</b>" : '-'; ?></td>
                            <td><b><?php echo number_format($h['cantidad'], 2); ?></b></td>
                            <td><?php echo number_format($h['saldo_nuevo'], 2); ?></td>
                            <td style="font-size:0.85em; color:#666;"><?php echo $h['Usuario']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CREAR MATERIAL -->
<div id="modal_crear" class="modal">
    <div class="modal-content">
        <span onclick="document.getElementById('modal_crear').style.display='none'" style="float:right; cursor:pointer; font-size:1.5em;">&times;</span>
        <h3 style="color:#0056b3; margin-top:0;">Nuevo Material</h3>
        <form method="POST">
            <label>Referencia:</label>
            <input type="text" name="referencia" required placeholder="Ej: 1-1-10">
            <label>Descripción:</label>
            <input type="text" name="descripcion" required placeholder="Ej: PT SP A NEGRO">
            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label>Saldo Inicial:</label>
                    <input type="number" step="0.01" name="saldo_inicial" value="0">
                </div>
                <div style="flex:1;">
                    <label>Stock Mínimo:</label>
                    <input type="number" step="0.01" name="stock_minimo" value="10">
                </div>
            </div>
            <button type="submit" name="crear_material" class="btn btn-blue" style="width:100%; justify-content:center;">Crear Material</button>
        </form>
    </div>
</div>

<!-- MODAL MOVIMIENTO (ENTRADA/SALIDA) -->
<div id="modal_movimiento" class="modal">
    <div class="modal-content">
        <span onclick="document.getElementById('modal_movimiento').style.display='none'" style="float:right; cursor:pointer; font-size:1.5em;">&times;</span>
        <h3 style="margin-top:0;">Registrar <span id="lbl_tipo"></span></h3>
        <form method="POST">
            <input type="hidden" name="tipo_movimiento" id="tipo_movimiento">
            
            <label>Material:</label>
            <select name="id_material" class="select-material" style="width:100%" required>
                <option value="">Buscar material...</option>
                <?php foreach($lista_materiales as $lm): ?>
                    <option value="<?php echo $lm['id']; ?>">
                        <?php echo $lm['referencia'] . " | " . $lm['descripcion'] . " (Stock: " . $lm['saldo'] . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div style="display:flex; gap:10px; margin-top:10px;">
                <div style="flex:1;">
                    <label>Fecha:</label>
                    <input type="date" name="fecha_movimiento" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div style="flex:1;">
                    <label>Cantidad:</label>
                    <input type="number" step="0.01" name="cantidad" required style="font-weight:bold; font-size:1.1em;">
                </div>
            </div>
            
            <!-- SELECTOR DE ORDEN (NUEVO) -->
            <div id="grupo_orden" style="margin-top:10px; display:none;">
                <label>Asignar a Orden de Producción:</label>
                <select name="numero_orden" id="select_orden" class="select-orden" style="width:100%">
                    <option value="">- Ninguna / General -</option>
                    <?php foreach($lista_ordenes as $lo): ?>
                        <option value="<?php echo $lo['NumeroOrden']; ?>">
                            #<?php echo $lo['NumeroOrden'] . " - " . $lo['Producto']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <label style="margin-top:10px;">Observaciones:</label>
            <textarea name="observaciones" rows="2"></textarea>
            
            <button type="submit" name="guardar_movimiento" id="btn_guardar_mov" class="btn" style="width:100%; justify-content:center; margin-top:10px;">Guardar Movimiento</button>
        </form>
    </div>
</div>

</body>
</html>
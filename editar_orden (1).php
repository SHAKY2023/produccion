<?php
// sistema/editar_orden.php
// Fecha: 04-12-2025 18:45:00
// UBICACIÓN: public_html/sistema/editar_orden.php
// DESCRIPCIÓN: Permite editar una orden existente, recalculando la Meta Total automáticamente.

include 'auth.php';
include 'conexion.php';

$mensaje = "";
$orden_id = isset($_GET['orden']) ? $conn->real_escape_string($_GET['orden']) : '';

// --- PROCESAR EL GUARDADO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_orden'])) {
    $num_orden = $conn->real_escape_string($_POST['num_orden']);
    $producto_nombre = $conn->real_escape_string($_POST['producto_nombre']);
    $referencia = $conn->real_escape_string($_POST['referencia']);
    $fecha = $_POST['fecha'];
    $meta_total = 0; // Se recalcula con los colores

    // 1. Calcular Meta Total basada en los colores enviados
    if (isset($_POST['color_nombre']) && is_array($_POST['color_nombre'])) {
        foreach ($_POST['color_cantidad'] as $cant) {
            $meta_total += intval($cant);
        }
    }

    // 2. Actualizar Maestro
    $sql_update = "UPDATE produccion_master 
                   SET Producto = '$producto_nombre', 
                       Referencia = '$referencia', 
                       Fecha = '$fecha', 
                       Cantidad = $meta_total 
                   WHERE NumeroOrden = '$num_orden'";

    if ($conn->query($sql_update)) {
        // 3. Actualizar Mezclas (Estrategia: Borrar anteriores e insertar nuevas)
        $conn->query("DELETE FROM produccion_mezclas WHERE NumeroOrden = '$num_orden'");

        if (isset($_POST['color_nombre']) && is_array($_POST['color_nombre'])) {
            $stmt_mezcla = $conn->prepare("INSERT INTO produccion_mezclas (NumeroOrden, Color, Cantidad, Peso) VALUES (?, ?, ?, 0)");
            
            $colores = $_POST['color_nombre'];
            $cantidades = $_POST['color_cantidad'];

            for ($i = 0; $i < count($colores); $i++) {
                $nom_col = trim($colores[$i]);
                $cant_col = intval($cantidades[$i]);

                if (!empty($nom_col) && $cant_col > 0) {
                    $stmt_mezcla->bind_param("ssi", $num_orden, $nom_col, $cant_col);
                    $stmt_mezcla->execute();
                }
            }
            $stmt_mezcla->close();
        }
        // MEJORA: Botón de regreso integrado en el mensaje de éxito
        $mensaje = "<div class='alert success' style='display:flex; justify-content:space-between; align-items:center;'>
                        <span>✅ Orden #$num_orden actualizada correctamente.</span>
                        <a href='ordenes.php' style='background:#155724; color:white; padding:5px 15px; text-decoration:none; border-radius:4px; font-weight:bold; font-size:0.9em;'>
                            <i class='fa-solid fa-arrow-left'></i> Volver al Listado
                        </a>
                    </div>";
    } else {
        $mensaje = "<div class='alert error'>❌ Error actualizando: " . $conn->error . "</div>";
    }
}

// --- CARGAR DATOS DE LA ORDEN ---
if (empty($orden_id)) {
    die("No se especificó un número de orden.");
}

// 1. Datos Maestros
$sql_master = "SELECT * FROM produccion_master WHERE NumeroOrden = '$orden_id'";
$res_master = $conn->query($sql_master);
if ($res_master->num_rows == 0) {
    die("La orden no existe.");
}
$orden = $res_master->fetch_assoc();

// 2. Mezclas (Colores)
$sql_mezclas = "SELECT * FROM produccion_mezclas WHERE NumeroOrden = '$orden_id'";
$res_mezclas = $conn->query($sql_mezclas);
$colores_actuales = [];
while($m = $res_mezclas->fetch_assoc()) {
    $colores_actuales[] = $m;
}

// 3. Catálogo de Colores (para el datalist)
$lista_colores = [];
$res_col = $conn->query("SELECT Color FROM colores ORDER BY Color ASC");
if($res_col) { while($c = $res_col->fetch_assoc()) $lista_colores[] = $c['Color']; }

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Orden #<?php echo $orden_id; ?> - INARPLAS Cloud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        .header-bar { background-color: #ffc107; color: #333; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.2rem; font-weight: bold; }
        
        .container { padding: 25px; max-width: 900px; margin: 0 auto; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; border-top: 4px solid #ffc107; }
        .card-header { padding: 15px 20px; background: #fff; border-bottom: 1px solid #eee; font-weight: bold; font-size: 1.1em; color: #333; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 20px; }

        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 0.85em; font-weight: 600; color: #555; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95em; box-sizing: border-box; }
        input:focus { border-color: #ffc107; outline: none; }
        input[readonly] { background-color: #e9ecef; cursor: not-allowed; font-weight: bold; color: #555; }
        
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .btn-warning { background: #ffc107; color: #333; border: none; padding: 12px; width: 100%; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 10px; transition: 0.3s; }
        .btn-warning:hover { background: #e0a800; }
        .btn-back { display:inline-block; margin-bottom:15px; color:#555; text-decoration:none; }

        /* Tabla Colores */
        .tabla-colores { width: 100%; border-collapse: collapse; font-size: 0.9em; margin-top: 5px; }
        .tabla-colores th { text-align: left; padding: 5px; color: #777; font-size: 0.85em; }
        .tabla-colores td { padding: 5px 2px; }
        
        .btn-add-mini { background: #0056b3; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.85em; margin-top: 5px; }
        .btn-del-mini { background: #dc3545; color: white; border: none; width: 30px; height: 38px; border-radius: 4px; cursor: pointer; }

        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>

    <script>
        function agregarFilaColor() {
            const tbody = document.getElementById('body_colores');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="color_nombre[]" list="lista_colores_dl" placeholder="Color" required></td>
                <td><input type="number" name="color_cantidad[]" placeholder="Unds" class="input-cant" required oninput="calcularTotal()"></td>
                <td style="text-align:center;"><button type="button" class="btn-del-mini" onclick="eliminarFila(this)"><i class="fa-solid fa-trash"></i></button></td>
            `;
            tbody.appendChild(row);
        }

        function eliminarFila(btn) {
            btn.closest('tr').remove();
            calcularTotal();
        }

        function calcularTotal() {
            let total = 0;
            // Seleccionamos todos los inputs con la clase 'input-cant'
            const inputs = document.querySelectorAll('.input-cant');
            inputs.forEach(inp => {
                let val = parseInt(inp.value) || 0;
                total += val;
            });
            // Actualizamos el campo Meta Total
            document.getElementById('meta_total').value = total;
        }
    </script>
</head>
<body>

<div class="header-bar">
    <div class="logo"><i class="fa-solid fa-pen-to-square"></i> Edición de Orden</div>
    <div>
        <a href="ordenes.php" style="color:#333; text-decoration:none; font-weight:bold;"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="container">
    <a href="ordenes.php" class="btn-back"><i class="fa-solid fa-chevron-left"></i> Regresar al listado</a>

    <div class="card">
        <div class="card-header">
            <i class="fa-solid fa-file-pen"></i> Editar Datos Maestros - Orden #<?php echo $orden_id; ?>
        </div>
        <div class="card-body">
            <?php echo $mensaje; ?>
            
            <form method="POST" action="editar_orden.php?orden=<?php echo $orden_id; ?>" autocomplete="off">
                <div class="row-2">
                    <div class="form-group">
                        <label>Número de Orden (Solo lectura):</label>
                        <input type="text" name="num_orden" value="<?php echo $orden['NumeroOrden']; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Fecha de Creación:</label>
                        <input type="date" name="fecha" value="<?php echo $orden['Fecha']; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Producto:</label>
                    <input type="text" name="producto_nombre" value="<?php echo $orden['Producto']; ?>" required>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label>Referencia:</label>
                        <input type="text" name="referencia" value="<?php echo $orden['Referencia']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Meta Total (Calculada Automáticamente):</label>
                        <input type="number" id="meta_total" value="<?php echo $orden['Cantidad']; ?>" readonly style="background:#fff3cd; color:#856404; font-size:1.1em;">
                    </div>
                </div>

                <!-- DESGLOSE DE COLORES -->
                <div class="form-group" style="background:#f8f9fa; padding:15px; border-radius:5px; border:1px solid #eee; margin-top:20px;">
                    <label style="color:#333; margin-bottom:10px; border-bottom:2px solid #ffc107; padding-bottom:5px;">
                        <i class="fa-solid fa-palette"></i> Desglose de Producción por Color
                    </label>
                    
                    <datalist id="lista_colores_dl">
                        <?php foreach($lista_colores as $col) echo "<option value='$col'>"; ?>
                    </datalist>

                    <table class="tabla-colores">
                        <thead>
                            <tr>
                                <th width="60%">Color / Material</th>
                                <th width="30%">Unidades a Producir</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody id="body_colores">
                            <?php if(count($colores_actuales) > 0): ?>
                                <?php foreach($colores_actuales as $mezcla): ?>
                                <tr>
                                    <td><input type="text" name="color_nombre[]" list="lista_colores_dl" value="<?php echo $mezcla['Color']; ?>" required></td>
                                    <!-- AQUI ESTA LA MAGIA: oninput="calcularTotal()" -->
                                    <td><input type="number" name="color_cantidad[]" value="<?php echo $mezcla['Cantidad']; ?>" class="input-cant" required oninput="calcularTotal()"></td>
                                    <td style="text-align:center;"><button type="button" class="btn-del-mini" onclick="eliminarFila(this)"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Fila vacía por defecto si no hay colores -->
                                <tr>
                                    <td><input type="text" name="color_nombre[]" list="lista_colores_dl" placeholder="Ej: ROJO" required></td>
                                    <td><input type="number" name="color_cantidad[]" value="0" class="input-cant" required oninput="calcularTotal()"></td>
                                    <td style="text-align:center;"><button type="button" class="btn-del-mini" onclick="eliminarFila(this)"><i class="fa-solid fa-trash"></i></button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn-add-mini" onclick="agregarFilaColor()">+ Agregar Otro Color</button>
                </div>

                <button type="submit" name="actualizar_orden" class="btn-warning">
                    <i class="fa-solid fa-save"></i> Guardar Todos los Cambios
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
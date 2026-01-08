<?php
// sistema/colores.php
// Fecha: 05-12-2025
// DESCRIPCIÓN: Módulo de Administración de Colores con detección automática de ID y corrección de título.

include 'auth.php';
include 'conexion.php';

// --- DETECTAR LLAVE PRIMARIA DINÁMICAMENTE ---
// Esto evita el error "Unknown column 'id'" si tu tabla usa 'id_color', 'Id', etc.
$pk_col = 'id'; // Valor por defecto
$q_pk = $conn->query("SHOW KEYS FROM colores WHERE Key_name = 'PRIMARY'");
if($q_pk && $row_pk = $q_pk->fetch_assoc()) {
    $pk_col = $row_pk['Column_name'];
}

$mensaje = "";
$id_editar = "";
$color_editar = "";

// --- 1. PROCESAR ACCIONES (GUARDAR, EDITAR, ELIMINAR) ---

// ELIMINAR
if (isset($_GET['borrar'])) {
    $id_borrar = intval($_GET['borrar']);
    
    // Verificar si el color está en uso en la tabla de mezclas
    // Nota: Usamos el nombre del color para verificar la relación
    $sql_check = "SELECT id FROM produccion_mezclas WHERE Color = (SELECT Color FROM colores WHERE $pk_col = $id_borrar) LIMIT 1";
    $check = $conn->query($sql_check);
    
    if ($check && $check->num_rows > 0) {
        $mensaje = "<div class='alert error'>⚠️ No se puede borrar este color porque ya se ha usado en órdenes de producción.</div>";
    } else {
        if ($conn->query("DELETE FROM colores WHERE $pk_col = $id_borrar")) {
            $mensaje = "<div class='alert success'>✅ Color eliminado correctamente.</div>";
        } else {
            $mensaje = "<div class='alert error'>❌ Error al eliminar: " . $conn->error . "</div>";
        }
    }
}

// GUARDAR O ACTUALIZAR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_color'])) {
    $nombre_color = strtoupper(trim($conn->real_escape_string($_POST['nombre_color'])));
    $id_actual = isset($_POST['id_color']) ? intval($_POST['id_color']) : 0;

    if (!empty($nombre_color)) {
        if ($id_actual > 0) {
            // ACTUALIZAR
            $sql = "UPDATE colores SET Color='$nombre_color' WHERE $pk_col=$id_actual";
            $msg_ok = "✅ Color actualizado correctamente.";
        } else {
            // INSERTAR NUEVO
            // Verificar duplicados
            $dup = $conn->query("SELECT $pk_col FROM colores WHERE Color='$nombre_color'");
            if ($dup && $dup->num_rows > 0) {
                $mensaje = "<div class='alert error'>⚠️ El color '$nombre_color' ya existe.</div>";
                $sql = ""; // No ejecutar
            } else {
                $sql = "INSERT INTO colores (Color) VALUES ('$nombre_color')";
                $msg_ok = "✅ Nuevo color registrado.";
            }
        }

        if (!empty($sql)) {
            if ($conn->query($sql)) {
                $mensaje = "<div class='alert success'>$msg_ok</div>";
                // Limpiar campos si fue registro nuevo
                if ($id_actual == 0) { $color_editar = ""; }
                // Si fue edición, redirigir para limpiar la URL
                if ($id_actual > 0) { 
                    echo "<script>window.location.href='colores.php';</script>"; 
                    exit;
                }
            } else {
                $mensaje = "<div class='alert error'>❌ Error SQL: " . $conn->error . "</div>";
            }
        }
    } else {
        $mensaje = "<div class='alert error'>⚠️ El nombre del color no puede estar vacío.</div>";
    }
}

// PREPARAR EDICIÓN
if (isset($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $res_edit = $conn->query("SELECT * FROM colores WHERE $pk_col = $id_editar");
    if ($res_edit && $row = $res_edit->fetch_assoc()) {
        $color_editar = $row['Color'];
    }
}

// --- 2. LISTAR COLORES ---
// Obtenemos dinámicamente las columnas para mostrar
$sql_lista = "SELECT * FROM colores ORDER BY Color ASC";
$res_lista = $conn->query($sql_lista);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Colores - INARPLAS Cloud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        .header-bar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 25px; max-width: 1000px; margin: 0 auto; }
        
        .grid-layout { display: grid; grid-template-columns: 1fr 1.5fr; gap: 25px; }
        @media(max-width: 768px) { .grid-layout { grid-template-columns: 1fr; } }

        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 15px; background: #fff; border-bottom: 1px solid #eee; font-weight: bold; color: #333; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 20px; }

        label { display: block; font-weight: 600; color: #555; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        input:focus { border-color: #0056b3; outline: none; }

        .btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; margin-top: 15px; }
        .btn-save:hover { background: #218838; }
        
        .btn-cancel { background: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; display: block; text-align: center; margin-top: 10px; font-size: 0.9em; }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; font-size: 0.95em; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; color: #555; }
        td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f1f1f1; }

        .actions a { margin-right: 10px; text-decoration: none; font-size: 1.1em; }
        .btn-edit-icon { color: #ffc107; }
        .btn-del-icon { color: #dc3545; }

        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="header-bar">
    <div style="font-weight:bold; font-size:1.2em;"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <a href="dashboard.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-house"></i> Volver al Inicio</a>
</div>

<div class="container">
    <!-- TÍTULO CORREGIDO -->
    <h2 style="color:#333; margin-top:0;"><i class="fa-solid fa-palette"></i> Gestión de Color de Materiales</h2>
    
    <div class="grid-layout">
        
        <!-- FORMULARIO (IZQUIERDA) -->
        <div class="card">
            <div class="card-header">
                <?php echo $id_editar ? '<i class="fa-solid fa-pen"></i> Editar Color' : '<i class="fa-solid fa-plus"></i> Nuevo Color'; ?>
            </div>
            <div class="card-body">
                <?php echo $mensaje; ?>
                
                <form method="POST" action="colores.php">
                    <input type="hidden" name="id_color" value="<?php echo $id_editar; ?>">
                    
                    <label>Nombre del Color / Material:</label>
                    <input type="text" name="nombre_color" value="<?php echo htmlspecialchars($color_editar); ?>" placeholder="Ej: AZUL REY, PP NEGRO..." required autofocus autocomplete="off">
                    
                    <button type="submit" name="guardar_color" class="btn-save">
                        <?php echo $id_editar ? 'Actualizar Color' : 'Guardar Color'; ?>
                    </button>
                    
                    <?php if($id_editar): ?>
                        <a href="colores.php" class="btn-cancel">Cancelar Edición</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- LISTADO (DERECHA) -->
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-list"></i> Listado de Colores Disponibles</div>
            <div class="card-body" style="padding:0;">
                <div style="overflow-x:auto; max-height: 500px;">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Color</th>
                                <th style="text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_lista && $res_lista->num_rows > 0): 
                                while($row = $res_lista->fetch_assoc()): 
                                    // Obtenemos el ID dinámicamente usando la columna detectada
                                    $id_actual_fila = $row[$pk_col];
                            ?>
                                <tr>
                                    <td style="color:#777;">#<?php echo $id_actual_fila; ?></td>
                                    <td>
                                        <i class="fa-solid fa-circle" style="font-size:0.5em; color:#0056b3; margin-right:5px; vertical-align:middle;"></i>
                                        <b><?php echo $row['Color']; ?></b>
                                    </td>
                                    <td class="actions" style="text-align:center;">
                                        <a href="colores.php?editar=<?php echo $id_actual_fila; ?>" class="btn-edit-icon" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <a href="colores.php?borrar=<?php echo $id_actual_fila; ?>" class="btn-del-icon" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar este color?');"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="3" style="text-align:center; padding:20px; color:#999;">No hay colores registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
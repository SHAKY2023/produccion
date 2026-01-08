<?php 
// maquinas.php
// Fecha: 05-12-2025 10:45:00
// UBICACIÓN: public_html/sistema/maquinas.php
// CORRECCIÓN: CRUD Completo (Crear, Leer, Actualizar, Borrar) y Buscador

header('Content-Type: text/html; charset=utf-8');
include 'auth.php'; 
include 'conexion.php'; 

$mensaje = "";
$error = "";

// --- DETECTAR NOMBRE DE LA COLUMNA ID (Compatibilidad) ---
$col_id = 'Registro'; 
$check = $conn->query("SHOW COLUMNS FROM maquinas LIKE 'id'");
if($check && $check->num_rows > 0) $col_id = 'id';

// --- 1. CREAR MÁQUINA ---
if (isset($_POST['crear'])) {
    $nombre = $_POST['nombre'];
    $segundos = floatval($_POST['segundos']);
    
    // Insertar
    $stmt = $conn->prepare("INSERT INTO maquinas (Nombre, Segundos) VALUES (?, ?)");
    $stmt->bind_param("sd", $nombre, $segundos);
    
    if($stmt->execute()) { 
        $mensaje = "&#9989; M&aacute;quina agregada correctamente"; 
    } else { 
        $error = "&#10060; Error al crear: " . $stmt->error; 
    }
    $stmt->close();
}

// --- 2. EDITAR MÁQUINA ---
if (isset($_POST['editar'])) {
    $id_editar = intval($_POST['id_editar']);
    $nombre_editar = $_POST['nombre_editar'];
    $segundos_editar = floatval($_POST['segundos_editar']);

    // Actualizar
    // Nota: Usamos la variable $col_id detectada arriba
    $stmt = $conn->prepare("UPDATE maquinas SET Nombre = ?, Segundos = ? WHERE $col_id = ?");
    $stmt->bind_param("sdi", $nombre_editar, $segundos_editar, $id_editar);

    if($stmt->execute()) {
        $mensaje = "&#9989; M&aacute;quina actualizada correctamente";
    } else {
        $error = "&#10060; Error al actualizar: " . $stmt->error;
    }
    $stmt->close();
}

// --- 3. BORRAR MÁQUINA ---
if (isset($_GET['borrar'])) {
    $id_borrar = intval($_GET['borrar']);
    
    $stmt = $conn->prepare("DELETE FROM maquinas WHERE $col_id = ?");
    $stmt->bind_param("i", $id_borrar);
    
    if($stmt->execute()) {
        header("Location: maquinas.php?msg=borrado");
        exit();
    } else {
        $error = "Error al borrar: " . $stmt->error;
    }
    $stmt->close();
}

if(isset($_GET['msg']) && $_GET['msg'] == 'borrado') {
    $mensaje = "&#128465; M&aacute;quina eliminada correctamente.";
}

// --- 4. LISTADO Y BUSCADOR ---
$busqueda = "";
$sql_base = "SELECT * FROM maquinas";

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $busqueda = $conn->real_escape_string($_GET['q']);
    // Filtramos por ID o por Nombre
    $sql_base .= " WHERE Nombre LIKE '%$busqueda%' OR $col_id LIKE '%$busqueda%'";
}

$sql_base .= " ORDER BY Nombre ASC";
$resultado = $conn->query($sql_base);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gesti&oacute;n de M&aacute;quinas - INARPLAS</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        
        /* Layout básico reutilizado */
        .top-bar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.2rem; font-weight: bold; }
        .main-container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #343a40; color: white; padding-top: 20px; flex-shrink: 0; }
        .sidebar h3 { text-align: center; color: #adb5bd; font-size: 0.9rem; uppercase; letter-spacing: 1px; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar ul li a { display: block; padding: 12px 20px; color: #c2c7d0; text-decoration: none; border-bottom: 1px solid #4b545c; transition: 0.3s; }
        .sidebar ul li a:hover, .sidebar ul li a.active { background: #0056b3; color: white; }
        .content { flex-grow: 1; padding: 25px; background: #f4f6f9; }

        /* Estilos específicos de Maquinas */
        .tabla-datos { width: 100%; border-collapse: collapse; background: white; margin-top:20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden; }
        .tabla-datos th, .tabla-datos td { border-bottom: 1px solid #ddd; padding: 12px; text-align: left; }
        .tabla-datos th { background: #0056b3; color: white; }
        .tabla-datos tr:hover { background-color: #f1f1f1; }
        
        .btn-action { padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 0.9em; margin-right: 5px; border: none; cursor: pointer; display: inline-block; }
        .btn-edit { background-color: #ffc107; color: #333; }
        .btn-del { background-color: #dc3545; color: white; }
        .btn-edit:hover, .btn-del:hover { opacity: 0.8; }
        
        .top-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
        .search-box form { display: flex; gap: 5px; }
        .search-box input { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-search { background: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        
        .btn-new { background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 1rem; }
        .btn-new:hover { background: #218838; }

        /* Modales */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-contenido { background: white; margin: 5% auto; padding: 25px; width: 90%; max-width: 400px; border-radius: 8px; border-top: 5px solid #0056b3; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; }
        .close-btn { position: absolute; top: 10px; right: 15px; cursor: pointer; font-size: 1.5em; color: #aaa; }
        .close-btn:hover { color: #333; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { width: 100%; padding: 10px; background: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        .btn-save:hover { background: #004494; }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="logo"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <div class="user-info">
        Hola, <b><?php echo isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario'; ?></b> | <a href="logout.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
    </div>
</div>

<div class="main-container">
    <div class="sidebar">
        <h3>Men&uacute; Principal</h3>
        <ul>
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Inicio</a></li>
            <li><a href="usuarios.php"><i class="fa-solid fa-users"></i> Empleados</a></li>
            <li><a href="maquinas.php" class="active"><i class="fa-solid fa-gears"></i> M&aacute;quinas</a></li>
            <li><a href="productos.php"><i class="fa-solid fa-box"></i> Productos</a></li>
            <li><a href="ordenes.php"><i class="fa-solid fa-clipboard-list"></i> &Oacute;rdenes Prod.</a></li>
            <li><a href="gestion_turno.php"><i class="fa-solid fa-clock"></i> Gestionar Turno</a></li>
            <li><a href="ver_registros.php"><i class="fa-solid fa-chart-bar"></i> Reportes</a></li>
        </ul>
    </div>

    <div class="content">
        <h2><i class="fa-solid fa-gears"></i> Parque de M&aacute;quinas</h2>
        
        <?php 
        if($mensaje) echo "<p style='color:#155724; background:#d4edda; padding:10px; border:1px solid #c3e6cb; border-radius:4px;'>$mensaje</p>"; 
        if($error) echo "<p style='color:#721c24; background:#f8d7da; padding:10px; border:1px solid #f5c6cb; border-radius:4px;'>$error</p>"; 
        ?>

        <div class="top-actions">
            <!-- Botón Crear -->
            <button onclick="document.getElementById('modal-crear').style.display='block'" class="btn-new">
                <i class="fa-solid fa-plus"></i> Nueva M&aacute;quina
            </button>

            <!-- Buscador -->
            <div class="search-box">
                <form method="GET" action="maquinas.php">
                    <input type="text" name="q" placeholder="Buscar máquina..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn-search"><i class="fa-solid fa-search"></i></button>
                    <?php if(!empty($busqueda)): ?>
                        <a href="maquinas.php" style="margin-left:5px; padding:8px; color:#666;" title="Limpiar"><i class="fa-solid fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <table class="tabla-datos">
            <thead>
                <tr>
                    <th width="10%">ID</th>
                    <th width="40%">Nombre de la M&aacute;quina</th>
                    <th width="20%">Ciclo (Segundos)</th>
                    <th width="30%">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($resultado && $resultado->num_rows > 0) {
                    while($fila = $resultado->fetch_assoc()) { 
                        // Obtener ID dinámicamente
                        $id = isset($fila['Registro']) ? $fila['Registro'] : $fila['id'];
                ?>
                <tr>
                    <td><b><?php echo $id; ?></b></td>
                    <td><?php echo htmlspecialchars($fila['Nombre']); ?></td>
                    <td><?php echo number_format($fila['Segundos'], 2); ?> seg</td>
                    <td>
                        <!-- Botón Editar (Llama a JS) -->
                        <button type="button" class="btn-action btn-edit" 
                                onclick="editarMaquina('<?php echo $id; ?>', '<?php echo htmlspecialchars($fila['Nombre'], ENT_QUOTES); ?>', '<?php echo $fila['Segundos']; ?>')">
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>

                        <!-- Botón Borrar (GET directo) -->
                        <a href="maquinas.php?borrar=<?php echo $id; ?>" 
                           onclick="return confirm('¿Está seguro de eliminar la máquina <?php echo htmlspecialchars($fila['Nombre']); ?>?');" 
                           class="btn-action btn-del">
                            <i class="fa-solid fa-trash"></i> Borrar
                        </a>
                    </td>
                </tr>
                <?php 
                    } 
                } else {
                    echo "<tr><td colspan='4' style='text-align:center; color:#777;'>No se encontraron máquinas.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL CREAR -->
<div id="modal-crear" class="modal">
    <div class="modal-contenido">
        <span class="close-btn" onclick="document.getElementById('modal-crear').style.display='none'">&times;</span>
        <h3><i class="fa-solid fa-plus-circle"></i> Agregar M&aacute;quina</h3>
        <form method="POST">
            <div class="form-group">
                <label>Nombre:</label>
                <input type="text" name="nombre" required placeholder="Ej: Extrusora 01">
            </div>
            <div class="form-group">
                <label>Ciclo Est&aacute;ndar (Segundos):</label>
                <input type="number" step="0.01" name="segundos" placeholder="0.00" required>
            </div>
            <button type="submit" name="crear" class="btn-save">Guardar</button>
        </form>
    </div>
</div>

<!-- MODAL EDITAR -->
<div id="modal-editar" class="modal">
    <div class="modal-contenido">
        <span class="close-btn" onclick="document.getElementById('modal-editar').style.display='none'">&times;</span>
        <h3><i class="fa-solid fa-pen-to-square"></i> Editar M&aacute;quina</h3>
        <form method="POST">
            <input type="hidden" name="id_editar" id="edit_id">
            
            <div class="form-group">
                <label>Nombre:</label>
                <input type="text" name="nombre_editar" id="edit_nombre" required>
            </div>
            <div class="form-group">
                <label>Ciclo Est&aacute;ndar (Segundos):</label>
                <input type="number" step="0.01" name="segundos_editar" id="edit_segundos" required>
            </div>
            <button type="submit" name="editar" class="btn-save">Actualizar</button>
        </form>
    </div>
</div>

<script>
    // Función para abrir el modal de edición y rellenar datos
    function editarMaquina(id, nombre, segundos) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre').value = nombre;
        document.getElementById('edit_segundos').value = segundos;
        
        document.getElementById('modal-editar').style.display = 'block';
    }

    // Cerrar modales si se hace clic fuera del contenido
    window.onclick = function(event) {
        var modalCrear = document.getElementById('modal-crear');
        var modalEditar = document.getElementById('modal-editar');
        if (event.target == modalCrear) {
            modalCrear.style.display = "none";
        }
        if (event.target == modalEditar) {
            modalEditar.style.display = "none";
        }
    }
</script>

</body>
</html>
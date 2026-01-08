<?php
// public_html/sistema/productos.php
// Fecha: 13-12-2025 12:00:00
// MEJORA: Agregado Menú Lateral (Sidebar) para navegación rápida.
// INCLUYE: Buscador instantáneo JS, Ordenamiento y Lógica de imágenes blindada.

include 'auth.php';
include 'conexion.php';

// --- LÓGICA DEL BUSCADOR (Server-side Fallback) ---
$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

// Consulta base
$sql = "SELECT * FROM productos WHERE 1=1 ";

if ($busqueda != '') {
    $sql .= " AND (Referencia LIKE '%$busqueda%' OR Nombre LIKE '%$busqueda%' OR Grupo LIKE '%$busqueda%') ";
}

$sql .= " ORDER BY Referencia ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos - INARPLAS Cloud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* HEADER SUPERIOR */
        .header-bar { background-color: #0056b3; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: fixed; width: 100%; top: 0; box-sizing: border-box; }
        .logo { font-size: 1.2rem; font-weight: bold; display: flex; align-items: center; gap: 10px; }
        
        /* LAYOUT PRINCIPAL (Sidebar + Contenido) */
        .main-wrapper { display: flex; margin-top: 50px; /* Altura del header */ height: calc(100vh - 50px); }
        
        /* SIDEBAR (MENÚ LATERAL) */
        .sidebar { width: 250px; background: white; border-right: 1px solid #ddd; padding: 20px 0; overflow-y: auto; flex-shrink: 0; display: flex; flex-direction: column; transition: 0.3s; }
        .sidebar h3 { padding: 0 20px; color: #999; font-size: 0.85em; text-transform: uppercase; margin-bottom: 10px; }
        .sidebar ul { list-style: none; padding: 0; margin: 0; }
        .sidebar li { margin-bottom: 2px; }
        .sidebar a { display: flex; align-items: center; padding: 12px 20px; color: #555; text-decoration: none; font-size: 0.95em; transition: 0.2s; gap: 10px; }
        .sidebar a:hover { background-color: #f0f7ff; color: #0056b3; }
        .sidebar a.active { background-color: #e3f2fd; color: #0056b3; font-weight: bold; border-right: 3px solid #0056b3; }
        .sidebar i { width: 20px; text-align: center; }

        /* CONTENIDO DERECHO */
        .content-area { flex-grow: 1; padding: 30px; overflow-y: auto; }
        
        /* ESTILOS ESPECÍFICOS DE PRODUCTOS */
        .search-box { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
        .search-input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        
        .btn-new { background: #28a745; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-new:hover { background: #218838; transform: translateY(-1px); }

        .table-responsive { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
        
        th { background: #f8f9fa; color: #444; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; cursor: pointer; user-select: none; }
        th:hover { background: #e9ecef; }
        th i { margin-left: 5px; color: #999; }
        
        td { padding: 10px 12px; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        tr:hover { background-color: #f8f9fa; }
        
        .img-thumb { width: 40px; height: 40px; object-fit: contain; border: 1px solid #eee; border-radius: 4px; background: #fff; padding: 2px; }
        
        .actions { white-space: nowrap; }
        .btn-icon { border: none; background: none; cursor: pointer; font-size: 1.1em; padding: 5px; transition: 0.2s; }
        .btn-edit { color: #ffc107; }
        .btn-delete { color: #dc3545; }
        .btn-icon:hover { transform: scale(1.2); }
        
        .badge { background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; font-weight: bold; color: #555; }

        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar a span, .sidebar h3 { display: none; }
            .sidebar a { justify-content: center; padding: 15px 0; }
            .content-area { padding: 15px; }
        }
    </style>
    <script>
        function buscarInstantaneo() {
            var input, filter, table, tr, td, i, j, txtValue;
            input = document.getElementById("inputBuscador");
            filter = input.value.toUpperCase();
            table = document.getElementById("tablaProductos");
            tr = table.getElementsByTagName("tr");
            for (i = 1; i < tr.length; i++) {
                var visible = false;
                td = tr[i].getElementsByTagName("td");
                for (j = 0; j < td.length; j++) {
                    if (td[j]) {
                        txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) { visible = true; break; }
                    }
                }
                tr[i].style.display = visible ? "" : "none";
            }
        }

        function ordenarTabla(n) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            table = document.getElementById("tablaProductos");
            switching = true; dir = "asc";
            while (switching) {
                switching = false; rows = table.rows;
                for (i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    var xVal = x.innerHTML.toLowerCase(), yVal = y.innerHTML.toLowerCase();
                    var xNum = parseFloat(xVal), yNum = parseFloat(yVal);
                    var isNum = !isNaN(xNum) && !isNaN(yNum);
                    if (dir == "asc") {
                        if (isNum ? (xNum > yNum) : (xVal > yVal)) { shouldSwitch = true; break; }
                    } else if (dir == "desc") {
                        if (isNum ? (xNum < yNum) : (xVal < yVal)) { shouldSwitch = true; break; }
                    }
                }
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true; switchcount++;
                } else {
                    if (switchcount == 0 && dir == "asc") { dir = "desc"; switching = true; }
                }
            }
        }
    </script>
</head>
<body>

<!-- HEADER SUPERIOR -->
<div class="header-bar">
    <div class="logo"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <div style="font-size:0.9em;">
        <span style="opacity:0.8; margin-right:10px;">Hola, <?php echo $_SESSION['nombre'] ?? 'Usuario'; ?></span>
        <a href="logout.php" style="color:white; text-decoration:none; font-weight:bold;"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
    </div>
</div>

<!-- CONTENEDOR PRINCIPAL (Flex) -->
<div class="main-wrapper">
    
    <!-- MENÚ LATERAL (Sidebar) -->
    <nav class="sidebar">
        <h3>Menú Principal</h3>
        <ul>
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> <span>Inicio</span></a></li>
            <li><a href="usuarios.php"><i class="fa-solid fa-users"></i> <span>Empleados</span></a></li>
            <li><a href="maquinas.php"><i class="fa-solid fa-gears"></i> <span>Máquinas</span></a></li>
            <li><a href="productos.php" class="active"><i class="fa-solid fa-box-open"></i> <span>Productos</span></a></li>
            <li><a href="ordenes.php"><i class="fa-solid fa-clipboard-list"></i> <span>Órdenes Prod.</span></a></li>
            <li><a href="gestion_turno.php"><i class="fa-solid fa-stopwatch"></i> <span>Gestionar Turno</span></a></li>
            <li><a href="ver_registros.php"><i class="fa-solid fa-table"></i> <span>Reportes</span></a></li>
            <li><a href="energia.php"><i class="fa-solid fa-bolt"></i> <span>Control Energía</span></a></li>
            <li><a href="agua.php"><i class="fa-solid fa-faucet"></i> <span>Control Agua</span></a></li>
        </ul>
    </nav>

    <!-- ÁREA DE CONTENIDO -->
    <main class="content-area">
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0; color:#333;"><i class="fa-solid fa-tags"></i> Catálogo de Productos</h2>
            <a href="nuevo_producto.php" class="btn-new"><i class="fa-solid fa-plus"></i> Nuevo Producto</a>
        </div>

        <!-- BARRA DE BÚSQUEDA -->
        <div class="search-box">
            <i class="fa-solid fa-search" style="color:#999;"></i>
            <input type="text" id="inputBuscador" onkeyup="buscarInstantaneo()" class="search-input" placeholder="Buscar por referencia, nombre, grupo...">
        </div>

        <div class="table-responsive">
            <table id="tablaProductos">
                <thead>
                    <tr>
                        <th width="60">Img</th>
                        <th onclick="ordenarTabla(1)">Ref. <i class="fa-solid fa-sort"></i></th>
                        <th onclick="ordenarTabla(2)">Nombre <i class="fa-solid fa-sort"></i></th>
                        <th onclick="ordenarTabla(3)">Grupo <i class="fa-solid fa-sort"></i></th>
                        <th onclick="ordenarTabla(4)">Peso <i class="fa-solid fa-sort"></i></th>
                        <th onclick="ordenarTabla(5)">Ciclo <i class="fa-solid fa-sort"></i></th>
                        <th onclick="ordenarTabla(6)">Empaque <i class="fa-solid fa-sort"></i></th>
                        <th onclick="ordenarTabla(7)">Bolsa <i class="fa-solid fa-sort"></i></th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            // Datos seguros
                            $id_prod = $row['Codigo'] ?? $row['id'] ?? '';
                            $ref = $row['Referencia'] ?? $row['referencia'] ?? '';
                            $nom = $row['Nombre'] ?? $row['Producto'] ?? '';
                            $grup = $row['Grupo'] ?? '';
                            $peso = number_format((float)($row['Peso'] ?? 0), 1);
                            $tiempo = $row['Ciclo'] ?? $row['TiempoEstimado'] ?? '-';
                            $empaque = $row['Empaque'] ?? 0;
                            $bolsa = $row['Bolsa'] ?? '';
                            
                            // Imagen Robusta
                            $dir_sys = __DIR__ . "/imagenes_productos/";
                            $dir_web = "imagenes_productos/";
                            $img_src = $dir_web . "sin_imagen.png"; 
                            
                            $img_db = trim($row['Imagen'] ?? '');
                            if(!empty($img_db) && file_exists($dir_sys . $img_db)) {
                                $img_src = $dir_web . $img_db;
                            } elseif (file_exists($dir_sys . $ref . ".jpg")) {
                                $img_src = $dir_web . $ref . ".jpg";
                            } elseif (file_exists($dir_sys . $ref . ".png")) {
                                $img_src = $dir_web . $ref . ".png";
                            }
                    ?>
                        <tr>
                            <td style="text-align:center;">
                                <img src="<?php echo $img_src; ?>" class="img-thumb" onerror="this.src='imagenes_productos/sin_imagen.png'">
                            </td>
                            <td><span class="badge"><?php echo $ref; ?></span></td>
                            <td style="font-weight:500;"><?php echo $nom; ?></td>
                            <td style="color:#666; font-size:0.9em;"><?php echo $grup; ?></td>
                            <td><?php echo $peso; ?> g</td>
                            <td><?php echo $tiempo; ?> s</td>
                            <td style="color:#0056b3; font-weight:bold;"><?php echo $empaque; ?></td>
                            <td><?php echo $bolsa; ?></td>
                            <td class="actions" style="text-align:center;">
                                <a href="editar_producto.php?id=<?php echo $id_prod; ?>" class="btn-icon btn-edit" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="eliminar_producto.php?id=<?php echo $id_prod; ?>" class="btn-icon btn-delete" onclick="return confirm('¿Eliminar producto <?php echo $ref; ?>?')" title="Eliminar"><i class="fa-solid fa-trash-can"></i></a>
                            </td>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='9' style='text-align:center; padding:30px; color:#999;'>No hay productos registrados.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>
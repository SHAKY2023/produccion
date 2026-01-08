<?php 
// /public_html/sistema/ver_registros.php - Versión: v3.12 (FIX: DEBUGGING NOMBRE SESIÓN)
// MEJORAS ACUMULADAS:
// ... (versiones anteriores v3.1 - v3.11)
// 11. MEJORA v3.10: Mapeo de roles legacy.
// 12. FIX v3.11: Visualización de datos de sesión.
// 13. FIX v3.12: Corrección de variable de sesión para nombre (nombre vs usuario) y reubicación de debug.

// --- ACTIVAR DEBUGGING (TEMPORAL PARA DIAGNÓSTICO) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

// --- VALIDACIÓN DE DEPENDENCIAS ---
$archivos_requeridos = ['auth.php', 'conexion.php', 'permisos.php'];
foreach ($archivos_requeridos as $archivo) {
    if (!file_exists($archivo)) {
        die("<b>Error Fatal:</b> No se encuentra el archivo requerido: <code>$archivo</code>. Verifique la ruta.");
    }
}

include 'auth.php'; 
include 'conexion.php'; 
require_once 'permisos.php';

// --- INICIALIZACIÓN CSRF (Seguridad) ---
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// --- SEGURIDAD: CONTROL DE ACCESO (ACL) ---
$mi_rol = $_SESSION['rol'] ?? '';

// --- FIX v3.10: MAPEO DE ROLES LEGACY EN MEMORIA ---
$rol_num = intval($mi_rol); // Forzamos a entero para comparación segura

if ($rol_num === 1) {
    $mi_rol = 'ADMIN';
} elseif ($rol_num === 2) {
    $mi_rol = 'OPERARIO';
}

// --- DEBUG SESSION (REUBICADO Y CORREGIDO v3.12) ---
// Ahora mostramos el estado DESPUÉS de la corrección de rol y buscamos la variable correcta para el nombre.
$nombre_usuario = $_SESSION['nombre'] ?? ($_SESSION['usuario'] ?? 'N/A');
$id_usuario = $_SESSION['id'] ?? ($_SESSION['cod'] ?? 'N/A');
$color_debug = ($mi_rol === 'ADMIN') ? '#d4edda' : '#fff3cd'; // Verde si es Admin, Amarillo si no.
$texto_color = ($mi_rol === 'ADMIN') ? '#155724' : '#856404';

echo "<div style='background:$color_debug; color:$texto_color; padding:10px; text-align:center; border-bottom:1px solid #ddd; font-family:monospace;'>";
echo "<b>MODO DEBUG v3.12:</b> ";
echo "Usuario: <b>" . htmlspecialchars($nombre_usuario) . "</b> (ID: $id_usuario) | ";
echo "Rol Original: <code>" . ($_SESSION['rol'] ?? 'NULL') . "</code> | ";
echo "Rol Procesado: <b>" . htmlspecialchars($mi_rol) . "</b>";
echo "</div>";

// --- LÓGICA DE BORRADO (AHORA VIA POST + CSRF) ---
if (isset($_POST['borrar_id'])) {
    // 1. Validar Token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_borrar = "Error de seguridad: Token CSRF inválido.";
        error_log("Seguridad: Fallo CSRF al intentar borrar ID " . $_POST['borrar_id']);
    } 
    // 2. Validar Permisos
    elseif (tiene_acceso($conn, $mi_rol, 'turnos')) {
        $id_borrar = intval($_POST['borrar_id']);
        
        // Integridad referencial manual
        $conn->query("DELETE FROM produccion_paquetes WHERE NumeroTurno = $id_borrar");
        $conn->query("DELETE FROM produccion_malas WHERE id_turno = $id_borrar");
        $conn->query("DELETE FROM produccion_detenciones WHERE id_turno = $id_borrar");
        
        if($conn->query("DELETE FROM produccion_turnos WHERE id = $id_borrar")) {
            header("Location: ver_registros.php?mensaje=eliminado");
            exit;
        } else {
            $error_borrar = "Error al eliminar: " . $conn->error;
            error_log("Error SQL Borrado Turno ID $id_borrar: " . $conn->error); 
        }
    } else {
        $error_borrar = "ACCESO DENEGADO: No tiene permisos para eliminar registros.";
        $usuario_log = $_SESSION['usuario'] ?? 'Desconocido';
        error_log("Seguridad: Acceso denegado a borrado. Usuario: $usuario_log, Rol: $mi_rol, ID Objetivo: " . $_POST['borrar_id']);
    }
}

// --- VARIABLES DE PAGINACIÓN Y FILTRO ---
$registros_por_pagina = 100; 
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Sanitización INMEDIATA de entradas GET para uso directo en SQL
$filtro_busqueda = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : '';
$filtro_desde = isset($_GET['desde']) ? $conn->real_escape_string($_GET['desde']) : '';
$filtro_hasta = isset($_GET['hasta']) ? $conn->real_escape_string($_GET['hasta']) : '';

// --- CONSTRUCCIÓN DE CONSULTA COMPATIBLE (Sin Prepared Statements) ---
// Base SQL
$sql_base_joins = " FROM produccion_turnos T 
                    LEFT JOIN usuarios U ON T.Operariold = U.id 
                    LEFT JOIN produccion_master M ON T.NumeroOrden = M.NumeroOrden
                    LEFT JOIN productos P ON M.Referencia = P.Referencia
                    WHERE 1=1";

// Construcción de condiciones WHERE concatenadas
$sql_conditions = "";

if (!empty($filtro_busqueda)) {
    // $filtro_busqueda ya está sanitizado arriba con real_escape_string
    $sql_conditions .= " AND (T.NumeroOrden LIKE '%$filtro_busqueda%' OR U.nombre LIKE '%$filtro_busqueda%' OR T.id = '$filtro_busqueda')";
}

if (!empty($filtro_desde)) {
    $sql_conditions .= " AND T.Fecha >= '$filtro_desde'";
}

if (!empty($filtro_hasta)) {
    $sql_conditions .= " AND T.Fecha <= '$filtro_hasta'";
}

// 1. CONSULTA DE CONTEO (Compatibilidad Legacy)
$sql_count = "SELECT COUNT(*) as total " . $sql_base_joins . $sql_conditions;
$res_count = $conn->query($sql_count);

if ($res_count === false) {
    die("Error SQL Count: " . $conn->error);
}

$fila_count = $res_count->fetch_assoc();
$total_registros = $fila_count['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// 2. CONSULTA PRINCIPAL (Compatibilidad Legacy)
$sql_final = "SELECT T.*, U.nombre as NombreOperario, P.Ciclo as CicloStd " . 
             $sql_base_joins . 
             $sql_conditions . 
             " ORDER BY T.Fecha DESC, T.HoraInicio DESC LIMIT $offset, $registros_por_pagina";

$resultado = $conn->query($sql_final);

if ($resultado === false) {
    die("Error SQL Final: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Maestro de Producción - INARPLAS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; padding-bottom: 50px; }
        
        .top-bar { background: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .main-container { padding: 20px; max-width: 98%; margin: 0 auto; }
        
        /* ESTILOS TABLA MAESTRA */
        .contenedor-tabla {
            width: 100%;
            overflow-x: auto;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            background: white;
            border: 1px solid #ddd;
        }

        .tabla-reporte { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 0.8em; 
            white-space: nowrap; 
        }
        
        .tabla-reporte th, .tabla-reporte td { padding: 8px 10px; border-bottom: 1px solid #eee; text-align: center; }
        
        .tabla-reporte th { 
            background: #343a40; 
            color: white; 
            text-transform: uppercase; 
            font-size: 0.85em; 
            letter-spacing: 0.5px;
            position: sticky; 
            top: 0;
            z-index: 10;
        }

        .fila-mala { background-color: #fff5f5; }
        .fila-mala td { color: #c00; }
        
        .col-id { font-weight: bold; background: #e9ecef; }
        .col-orden { color: #0056b3; font-weight: bold; }
        .col-buenas { color: green; font-weight: bold; background: #e8f5e9; }
        .col-malas { color: red; font-weight: bold; background: #ffebee; }
        
        .btn-action { text-decoration: none; font-size: 1.1em; margin: 0 5px; transition: 0.2s; border: none; background: none; cursor: pointer; }
        .btn-edit { color: #ffc107; }
        .btn-edit:hover { color: #d39e00; transform: scale(1.2); }
        .btn-del { color: #dc3545; }
        .btn-del:hover { color: #a71d2a; transform: scale(1.2); }

        .barra-filtros { 
            background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; 
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .input-filtro { padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-filtrar { background: #0056b3; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        .btn-nuevo { background: #28a745; color: white; text-decoration: none; padding: 10px 20px; border-radius: 4px; display: flex; align-items: center; gap: 5px; }

        .obs-cell { max-width: 150px; overflow: hidden; text-overflow: ellipsis; cursor: help; }
        
        /* Paginación */
        .paginacion { margin-top: 15px; text-align: right; }
        .paginacion a { display: inline-block; padding: 5px 10px; border: 1px solid #ccc; margin-left: 2px; color: #333; text-decoration: none; border-radius: 3px; background: white; }
        .paginacion a.active { background: #0056b3; color: white; border-color: #0056b3; }
        
        /* Feedback Visual */
        .alert-readonly { background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ffeeba; text-align: center; }
    </style>
</head>
<body>

<div class="top-bar">
    <div style="font-weight:bold; font-size:1.2em;"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <div>
        <span style="margin-right:15px;"><i class="fa-solid fa-user"></i> <?php echo isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre']) : 'Usuario'; ?></span>
        <a href="dashboard.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="main-container">
    
    <!-- SUGERENCIA: Feedback Visual de Modo Lectura -->
    <?php if(!tiene_acceso($conn, $mi_rol, 'turnos') && $mi_rol !== 'ADMIN'): ?>
        <div class="alert-readonly">
            <i class="fa-solid fa-eye"></i> <b>Modo Lectura:</b> Su perfil no tiene permisos para crear, editar o eliminar registros.
        </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h2 style="color:#333; margin:0;"><i class="fa-solid fa-table-list"></i> Maestro de Producción</h2>
        <?php if(tiene_acceso($conn, $mi_rol, 'turnos')): ?>
            <a href="gestion_turno.php" class="btn-nuevo"><i class="fa-solid fa-plus"></i> Nuevo Turno</a>
        <?php endif; ?>
    </div>

    <!-- FILTROS -->
    <form class="barra-filtros" method="GET">
        <div style="flex-grow:1; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-magnifying-glass" style="color:#999;"></i>
            <input type="text" name="q" class="input-filtro" placeholder="Buscar Orden, Operario o ID..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>" style="width:100%;">
        </div>
        <div>
            <label style="font-size:0.9em; font-weight:bold; color:#555;">Desde:</label>
            <input type="date" name="desde" class="input-filtro" value="<?php echo htmlspecialchars($filtro_desde); ?>">
        </div>
        <div>
            <label style="font-size:0.9em; font-weight:bold; color:#555;">Hasta:</label>
            <input type="date" name="hasta" class="input-filtro" value="<?php echo htmlspecialchars($filtro_hasta); ?>">
        </div>
        <button type="submit" class="btn-filtrar">Filtrar</button>
        <a href="ver_registros.php" style="color:#666; text-decoration:none; padding:10px;">Limpiar</a>
    </form>
    
    <?php 
    if(isset($_GET['mensaje']) && $_GET['mensaje']=='eliminado') echo "<div style='background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:5px;'>✅ Registro eliminado correctamente.</div>";
    if(isset($error_borrar)) echo "<div style='background:#f8d7da; color:#721c24; padding:15px; margin-bottom:20px; border-radius:5px;'>❌ $error_borrar</div>";
    ?>

    <!-- TABLA CON SCROLL HORIZONTAL -->
    <div class="contenedor-tabla">
        <table class="tabla-reporte">
            <thead>
                <tr>
                    <th title="Acciones" style="background:#222;">⚙️</th>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Turno</th>
                    <th>Orden</th>
                    <th>Operario</th>
                    <th>H. Inicio</th>
                    <th>H. Final</th>
                    <th>T. Total (s)</th>
                    <th>Ciclo Real</th>
                    <th>Ciclo Maq</th>
                    <th>Cont. Ini</th>
                    <th>Cont. Fin</th>
                    <th>Físicas</th>
                    <th>Buenas</th>
                    <th>Malas</th>
                    <th>Sob. Ini</th>
                    <th>Sob. Fin</th>
                    <th>P. Bolsa</th>
                    <th>Cant. Bolsas</th>
                    <th>Consumo B.</th>
                    <th>Dif. Tiempos</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($resultado && $resultado->num_rows > 0) {
                    while($fila = $resultado->fetch_assoc()) { 
                        $clase = ($fila['Malas'] > 0) ? 'fila-mala' : '';
                        $nomTurno = ($fila['NumeroTurno'] == 1) ? "Mañana" : (($fila['NumeroTurno'] == 2) ? "Tarde" : "Noche");
                        $iconoT = ($fila['NumeroTurno'] == 1) ? "🌅" : (($fila['NumeroTurno'] == 2) ? "☀️" : "🌙");
                        
                        $ciclo_manual = isset($fila['CicloMaquina']) ? floatval($fila['CicloMaquina']) : 0;
                        $ciclo_std = isset($fila['CicloStd']) ? floatval($fila['CicloStd']) : 0;
                        $ciclo_referencia = ($ciclo_manual > 0) ? $ciclo_manual : $ciclo_std;
                ?>
                <tr class="<?php echo $clase; ?>">
                    <!-- COLUMNA ACCIONES -->
                    <td style="background:#f8f9fa; border-right:1px solid #ddd;">
                        <?php if(tiene_acceso($conn, $mi_rol, 'turnos')): ?>
                            <a href="gestion_turno.php?editar=<?php echo $fila['id']; ?>&orden=<?php echo urlencode($fila['NumeroOrden']); ?>" class="btn-action btn-edit" title="Editar Turno"><i class="fa-solid fa-pen"></i></a>
                            
                            <!-- Formulario de Borrado Seguro (POST) -->
                            <form method="POST" action="ver_registros.php" style="display:inline;" onsubmit="return confirm('⚠️ ¿Estás seguro de eliminar el Turno ID <?php echo $fila['id']; ?>?\n\nEsta acción es irreversible.');">
                                <input type="hidden" name="borrar_id" value="<?php echo $fila['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <button type="submit" class="btn-action btn-del" title="Eliminar Turno"><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        <?php else: ?>
                            <span style="color:#ccc; font-size:0.8em;" title="Sin permisos">🔒</span>
                        <?php endif; ?>
                    </td>

                    <!-- 25 COLUMNAS DE DATOS (Con Escapado XSS) -->
                    <td class="col-id"><?php echo $fila['id']; ?></td>
                    <td><?php echo date("d/m/Y", strtotime($fila['Fecha'])); ?></td>
                    <td><?php echo $iconoT . " " . $nomTurno; ?></td>
                    <td class="col-orden"><?php echo htmlspecialchars($fila['NumeroOrden']); ?></td>
                    <td><?php echo htmlspecialchars($fila['NombreOperario']); ?></td>
                    
                    <td><?php echo substr($fila['HoraInicio'], 0, 5); ?></td>
                    <td><?php echo substr($fila['HoraFinal'], 0, 5); ?></td>
                    <td><?php echo number_format($fila['TiempoTotalSeg']); ?></td>
                    
                    <td style="font-weight:bold;"><?php echo number_format($fila['CicloReal'], 2); ?></td>
                    
                    <td>
                        <?php 
                            if ($ciclo_manual > 0) {
                                echo "<b>" . number_format($ciclo_manual, 2) . "</b>";
                            } elseif ($ciclo_std > 0) {
                                echo "<span title='Ciclo Estándar' style='color:#777; font-style:italic;'>🎯 " . number_format($ciclo_std, 2) . "</span>";
                            } else {
                                echo "-";
                            }
                        ?>
                    </td>
                    
                    <td><?php echo number_format($fila['ContadorInicio']); ?></td>
                    <td><?php echo number_format($fila['ContadorFinal']); ?></td>
                    <td style="font-weight:bold;"><?php echo number_format($fila['UnidadesFisicas']); ?></td>
                    
                    <td class="col-buenas"><?php echo number_format($fila['Buenas']); ?></td>
                    <td class="col-malas"><?php echo $fila['Malas']; ?></td>
                    
                    <td><?php echo $fila['SobrantesInicio']; ?></td>
                    <td><?php echo $fila['SobrantesFinal']; ?></td>
                    
                    <td><?php echo $fila['PesoBolsaUnitario']; ?>g</td>
                    <td><?php echo $fila['CantidadBolsas']; ?></td>
                    <td><?php echo $fila['PesoBolsaUsado']; ?> kg</td>
                    
                    <td>
                        <?php 
                            if($ciclo_referencia > 0 && $fila['CicloReal'] > 0) {
                                $unidades = floatval($fila['UnidadesFisicas']);
                                $tiempo_real_total = floatval($fila['TiempoTotalSeg']);
                                $tiempo_teorico_total = $ciclo_referencia * $unidades;
                                $diff_minutos = round(abs($tiempo_real_total - $tiempo_teorico_total) / 60);
                                echo ($diff_minutos > 0) ? "<span style='color:orange; font-weight:bold;'>$diff_minutos min</span>" : "<span style='color:#ccc;'>0</span>";
                            } else { 
                                echo "-"; 
                            }
                        ?>
                    </td>

                    <td class="obs-cell" title="<?php echo htmlspecialchars($fila['Observaciones']); ?>">
                        <?php echo htmlspecialchars($fila['Observaciones']); ?>
                    </td>
                </tr>
                <?php 
                    } 
                } else {
                    echo "<tr><td colspan='25' style='padding:30px; color:#777; text-align:center;'>No hay registros para mostrar.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación Real -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
        <div style="font-size:0.85em; color:#666;">
            Total: <b><?php echo number_format($total_registros); ?></b> registros. Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>.
        </div>
        <div class="paginacion">
            <?php 
            // Generar enlaces de paginación
            $rango = 3;
            $url_base = "ver_registros.php?q=" . urlencode($filtro_busqueda) . "&desde=" . urlencode($filtro_desde) . "&hasta=" . urlencode($filtro_hasta) . "&pag=";
            
            if ($pagina_actual > 1) {
                echo '<a href="' . $url_base . '1">«</a>';
                echo '<a href="' . $url_base . ($pagina_actual - 1) . '">‹</a>';
            }
            
            for ($i = max(1, $pagina_actual - $rango); $i <= min($total_paginas, $pagina_actual + $rango); $i++) {
                $active = ($i == $pagina_actual) ? 'class="active"' : '';
                echo '<a href="' . $url_base . $i . '" ' . $active . '>' . $i . '</a>';
            }
            
            if ($pagina_actual < $total_paginas) {
                echo '<a href="' . $url_base . ($pagina_actual + 1) . '">›</a>';
                echo '<a href="' . $url_base . $total_paginas . '">»</a>';
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>
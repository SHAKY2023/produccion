<?php
// public_html/sistema/dashboard.php - Versión: v3.6 (VISUALIZACIÓN DE ROL)
// MEJORAS:
// 1. Centralización de permisos con 'permisos.php'.
// 2. Fix: Conversión automática de rol numérico (1) a 'ADMIN' para usuarios legacy.
// 3. Fix: Restauración de formato CSS expandido.
// 4. Fix: Corrección de caracter especial 'Ó' en título.
// 5. UX: Se muestra el ROL del usuario en la barra superior (Header).

include 'auth.php';
// REQUERIDO: Conexión para verificar permisos en base de datos
require_once 'conexion.php';
require_once 'permisos.php'; // Usamos la librería centralizada

// Recuperamos el rol
$mi_rol = $_SESSION['rol'] ?? '';

// --- FIX DE COMPATIBILIDAD (IGUAL QUE EN VER_REGISTROS) ---
$rol_num = intval($mi_rol);
if ($rol_num === 1) {
    $mi_rol = 'ADMIN';
} elseif ($rol_num === 2) {
    $mi_rol = 'OPERARIO';
}

// Para compatibilidad con código anterior que usaba $es_admin
$es_admin = ($mi_rol == 'ADMIN');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - INARPLAS Cloud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f4f6f9; 
            margin: 0; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }
        
        /* HEADER */
        .header-bar { 
            background-color: #0056b3; 
            color: white; 
            padding: 10px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: fixed; 
            width: 100%; 
            top: 0; 
            z-index: 1000; 
            box-sizing: border-box; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
        }
        
        .logo { 
            font-size: 1.2rem; 
            font-weight: bold; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        
        /* LAYOUT */
        .main-wrapper { 
            display: flex; 
            margin-top: 50px; 
            height: calc(100vh - 50px); 
        }
        
        /* SIDEBAR */
        .sidebar { 
            width: 240px; 
            background: white; 
            border-right: 1px solid #ddd; 
            overflow-y: auto; 
            flex-shrink: 0; 
            display: flex; 
            flex-direction: column; 
        }
        
        .sidebar h3 { 
            padding: 15px 20px; 
            margin: 0; 
            color: #999; 
            font-size: 0.8em; 
            text-transform: uppercase; 
            border-bottom: 1px solid #eee; 
        }
        
        .sidebar ul { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        
        .sidebar li { 
            margin-bottom: 0; 
        }
        
        .sidebar a { 
            display: flex; 
            align-items: center; 
            padding: 12px 20px; 
            color: #555; 
            text-decoration: none; 
            border-bottom: 1px solid #f0f0f0; 
            transition: 0.2s; 
            font-size: 0.95em; 
        }
        
        .sidebar a:hover { 
            background-color: #f0f7ff; 
            color: #0056b3; 
        }
        
        .sidebar a.active { 
            background-color: #e3f2fd; 
            color: #0056b3; 
            font-weight: bold; 
            border-left: 4px solid #0056b3; 
        }
        
        .sidebar i { 
            width: 25px; 
            text-align: center; 
            margin-right: 10px; 
        }
        
        /* CONTENT */
        .content { 
            flex-grow: 1; 
            padding: 30px; 
            overflow-y: auto; 
        }
        
        /* GRID DE BOTONES */
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .icon-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: #555;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border-bottom: 4px solid transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 140px;
        }
        
        .icon-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
        }
        
        .icon-card i { 
            font-size: 2.5em; 
            margin-bottom: 15px; 
            color: #0056b3; 
        }
        
        .icon-card h3 { 
            margin: 0; 
            font-size: 1.1em; 
            color: #333; 
        }
        
        /* Colores Específicos */
        .card-green { border-bottom-color: #28a745; }
        .card-green i { color: #28a745; }
        
        .card-blue { border-bottom-color: #17a2b8; }
        .card-blue i { color: #17a2b8; }
        
        .card-yellow { border-bottom-color: #ffc107; }
        .card-yellow i { color: #ffc107; }
        
        .card-purple { border-bottom-color: #6f42c1; }
        .card-purple i { color: #6f42c1; }
        
        .card-orange { border-bottom-color: #fd7e14; }
        .card-orange i { color: #fd7e14; }
        
        .card-cyan { border-bottom-color: #0dcaf0; }
        .card-cyan i { color: #0dcaf0; }

        .card-dark { border-bottom-color: #343a40; }
        .card-dark i { color: #343a40; }

        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar h3, .sidebar span, .sidebar li.admin-header { display: none; }
            .sidebar a { justify-content: center; padding: 15px 0; }
            .sidebar i { margin: 0; }
            .content { padding: 15px; }
        }
    </style>
</head>
<body>

<div class="header-bar">
    <div class="logo"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <div>
        <!-- NUEVO: Mostrar Rol con estilo distintivo -->
        <span style="margin-right:15px; font-size:0.85em; background:rgba(255,255,255,0.2); padding:3px 8px; border-radius:4px; font-weight:bold;">
            <i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars($mi_rol); ?>
        </span>
        
        <span style="margin-right:15px; font-size:0.9em;">Hola, <?php echo $_SESSION['nombre'] ?? 'Usuario'; ?></span>
        <a href="logout.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
    </div>
</div>

<div class="main-wrapper">
    <!-- MENÚ LATERAL -->
    <nav class="sidebar">
        <h3>Men&uacute; Principal</h3>
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> <span>Inicio</span></a></li>
            
            <?php if(tiene_acceso($conn, $mi_rol, 'turnos')): ?>
            <li><a href="gestion_turno.php"><i class="fa-solid fa-stopwatch"></i> <span>Gestionar Turno</span></a></li>
            <?php endif; ?>
            
            <li><a href="ordenes_activas.php"><i class="fa-solid fa-list-check"></i> <span>&Oacute;rdenes en Planta</span></a></li>
            
            <?php if($es_admin || tiene_acceso($conn, $mi_rol, 'ordenes') || tiene_acceso($conn, $mi_rol, 'productos') || tiene_acceso($conn, $mi_rol, 'maquinas') || tiene_acceso($conn, $mi_rol, 'usuarios') || tiene_acceso($conn, $mi_rol, 'energia') || tiene_acceso($conn, $mi_rol, 'reportes')): ?>
                <li class="admin-header" style="color:#999; font-size:0.75em; padding:10px 20px 5px; font-weight:bold;">M&Oacute;DULOS</li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'ordenes')): ?>
                <li><a href="ordenes.php"><i class="fa-solid fa-clipboard-list"></i> <span>&Oacute;rdenes Prod.</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'productos')): ?>
                <li><a href="productos.php"><i class="fa-solid fa-box-open"></i> <span>Productos</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'maquinas')): ?>
                <li><a href="maquinas.php"><i class="fa-solid fa-gears"></i> <span>M&aacute;quinas</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'usuarios')): ?>
                <li><a href="usuarios.php"><i class="fa-solid fa-users"></i> <span>Empleados</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'energia')): ?>
                <li><a href="energia.php"><i class="fa-solid fa-bolt"></i> <span>Control Energ&iacute;a</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'reportes')): ?>
                <li><a href="ver_registros.php"><i class="fa-solid fa-table"></i> <span>Reportes</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'analisis')): ?>
                <li><a href="reporte_producto.php"><i class="fa-solid fa-magnifying-glass-chart"></i> <span>An&aacute;lisis Orden</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'historial')): ?>
                <li><a href="historial_producto.php"><i class="fa-solid fa-chart-line"></i> <span>Historial Total</span></a></li>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'agua')): ?>
                <li><a href="agua.php"><i class="fa-solid fa-faucet"></i> <span>Control Agua</span></a></li>
            <?php endif; ?>

            <?php if($es_admin) { ?>
                <li><a href="tareas.php"><i class="fa-solid fa-list-check"></i> <span>Tareas</span></a></li>
                <li><a href="inventario.php"><i class="fa-solid fa-boxes-stacked"></i> <span>Inventario</span></a></li>
                <li><a href="importar_inventario.php"><i class="fa-solid fa-boxes-stacked"></i> <span>importar_inventario</span></a></li>
            <?php } ?>
        </ul>
    </nav>

    <div class="content">
        <h1>Bienvenido al Sistema de Gesti&oacute;n</h1>
        <p style="color:#666; margin-bottom:30px;">Seleccione una opci&oacute;n del men&uacute; o use los accesos directos.</p>
        
        <div class="icon-grid">
            <?php if(tiene_acceso($conn, $mi_rol, 'turnos')): ?>
            <a href="gestion_turno.php" class="icon-card card-green">
                <i class="fa-solid fa-stopwatch"></i>
                <h3>Gestionar Turno</h3>
            </a>
            <?php endif; ?>

            <a href="ordenes_activas.php" class="icon-card card-blue">
                <i class="fa-solid fa-list-check"></i>
                <h3>&Oacute;rdenes en Planta</h3>
            </a>

            <?php if(tiene_acceso($conn, $mi_rol, 'ordenes')): ?>
            <a href="ordenes.php" class="icon-card card-yellow">
                <i class="fa-solid fa-file-circle-plus"></i>
                <h3>&Oacute;rdenes Prod.</h3>
            </a>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'productos')): ?>
            <a href="productos.php" class="icon-card">
                <i class="fa-solid fa-box-open"></i>
                <h3>Productos</h3>
            </a>
            <?php endif; ?>
            
            <?php if(tiene_acceso($conn, $mi_rol, 'maquinas')): ?>
            <a href="maquinas.php" class="icon-card">
                <i class="fa-solid fa-robot"></i>
                <h3>M&aacute;quinas</h3>
            </a>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'usuarios')): ?>
            <a href="usuarios.php" class="icon-card">
                <i class="fa-solid fa-users-gear"></i>
                <h3>Empleados</h3>
            </a>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'energia')): ?>
            <a href="energia.php" class="icon-card card-orange">
                <i class="fa-solid fa-bolt"></i>
                <h3>Control Energ&iacute;a</h3>
            </a>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'reportes')): ?>
            <a href="ver_registros.php" class="icon-card card-blue">
                <i class="fa-solid fa-chart-pie"></i>
                <h3>Reportes</h3>
            </a>
            <?php endif; ?>
            
            <?php if(tiene_acceso($conn, $mi_rol, 'analisis')): ?>
            <a href="reporte_producto.php" class="icon-card card-cyan">
                <i class="fa-solid fa-magnifying-glass-chart"></i>
                <h3>An&aacute;lisis Orden</h3>
            </a>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'historial')): ?>
            <a href="historial_producto.php" class="icon-card card-dark">
                <i class="fa-solid fa-chart-line"></i>
                <h3>Historial Total</h3>
            </a>
            <?php endif; ?>

            <?php if(tiene_acceso($conn, $mi_rol, 'agua')): ?>
            <a href="agua.php" class="icon-card card-cyan">
                <i class="fa-solid fa-water"></i>
                <h3>Control Agua</h3>
            </a>
            <?php endif; ?>
            
            <?php if($es_admin) { ?>
                <a href="tareas.php" class="icon-card card-purple">
                    <i class="fa-solid fa-list-check"></i>
                    <h3>Tareas y Cronograma</h3>
                </a>
                 <a href="inventario.php" class="icon-card card-purple">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <h3>Inventario</h3>
                </a>
                <a href="importar_inventario.php" class="icon-card card-purple">
                    <i class="fa-solid fa-boxes-stacked"></i> Importar CSV</a>
            <?php } ?>
        </div>
    </div>
</div>
</body>
</html>
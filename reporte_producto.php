<?php
// reporte_producto.php
// ENFOQUE: ANÁLISIS DE ORDEN DE PRODUCCIÓN + PROMEDIOS Y RENDIMIENTO
header('Content-Type: text/html; charset=utf-8');
include 'auth.php';
include 'conexion.php';

$orden_info = null;
$stats = null;
$res_turnos = null;
$orden_buscada = "";

// Variables para promedios
$promedio_buenas = 0;
$promedio_malas = 0;
$ciclo_real = 0;
$ciclo_std = 0;

// SI EL USUARIO BUSCÓ UNA ORDEN
if (isset($_GET['orden'])) {
    $orden_buscada = $conn->real_escape_string(trim($_GET['orden']));

    // 1. DATOS MAESTROS DE LA ORDEN
    $sql_master = "SELECT * FROM produccion_master WHERE NumeroOrden = '$orden_buscada' LIMIT 1";
    $res_master = $conn->query($sql_master);

    if ($res_master && $res_master->num_rows > 0) {
        $orden_info = $res_master->fetch_assoc();
        $ref_producto = $orden_info['Referencia']; 

        // Datos técnicos (Ciclo Estándar)
        $res_prod = $conn->query("SELECT * FROM productos WHERE Referencia = '$ref_producto' LIMIT 1");
        $info_prod = ($res_prod->num_rows > 0) ? $res_prod->fetch_assoc() : null;
        $ciclo_std = ($info_prod) ? floatval($info_prod['Ciclo']) : 0;

        // 2. ESTADÍSTICAS ACUMULADAS
        $sql_stats = "
            SELECT 
                COUNT(*) as total_turnos,
                SUM(unidades_buenas) as real_producido,
                SUM(unidades_malas) as total_desperdicio,
                SUM(tiempo_total_seg) as tiempo_total_invertido,
                SUM(consumo_bolsa) as total_bolsa_kg
            FROM turnos_registros 
            WHERE numero_orden = '$orden_buscada'
        ";
        $stats = $conn->query($sql_stats)->fetch_assoc();

        // --- CÁLCULOS DE PROMEDIOS Y EFICIENCIA ---
        if ($stats['total_turnos'] > 0) {
            // Promedio de unidades por turno
            $promedio_buenas = $stats['real_producido'] / $stats['total_turnos'];
            $promedio_malas = $stats['total_desperdicio'] / $stats['total_turnos'];
            
            // Ciclo Real: Cuánto tiempo nos tomó realmente hacer 1 unidad buena
            // Fórmula: Tiempo Total / Unidades Buenas
            if ($stats['real_producido'] > 0) {
                $ciclo_real = $stats['tiempo_total_invertido'] / $stats['real_producido'];
            }
        }

        // 3. LISTADO DE TURNOS
        $res_turnos = $conn->query("SELECT * FROM turnos_registros WHERE numero_orden = '$orden_buscada' ORDER BY fecha DESC, hora_inicio DESC");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Orden - INARPLAS</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        /* Barra de búsqueda */
        .search-bar { background: #0056b3; padding: 20px; color: white; text-align: center; margin-bottom: 20px; border-radius: 0 0 10px 10px; }
        .input-search { padding: 10px; width: 300px; border-radius: 5px; border: none; font-size: 1.2em; text-align: center; font-weight: bold; }
        .btn-search { padding: 10px 20px; background: #ffc107; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1.1em; color: #333; }
        
        /* Panel de Información */
        .info-panel { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; border-left: 5px solid #0056b3; }
        
        /* Títulos de Sección */
        .section-title { font-size: 1.1em; color: #555; border-bottom: 2px solid #eee; padding-bottom: 5px; margin: 20px 0 10px 0; font-weight: bold; }

        /* Contenedor de Indicadores (KPIs) */
        .kpi-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .kpi-box { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; border-top: 3px solid transparent; }
        .kpi-val { font-size: 1.6em; font-weight: bold; color: #333; }
        .kpi-tit { font-size: 0.8em; color: #666; text-transform: uppercase; margin-top: 5px; }
        
        /* Colores de bordes KPI */
        .bd-blue { border-top-color: #0056b3; }
        .bd-green { border-top-color: #28a745; }
        .bd-red { border-top-color: #dc3545; }
        .bd-orange { border-top-color: #fd7e14; }
        
        /* Barra de Progreso */
        .progress-bar { background: #e9ecef; height: 25px; border-radius: 15px; overflow: hidden; margin-top: 10px; border: 1px solid #ccc; position: relative; }
        .progress-fill { height: 100%; background: #28a745; text-align: center; line-height: 25px; color: white; font-weight: bold; font-size: 0.9em; transition: width 1s; }
        
        table { width: 100%; border-collapse: collapse; background: white; font-size: 0.9em; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background: #343a40; color: white; }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="logo">&#127981; INARPLAS Cloud</div>
    <div class="user-info">
        <b><?php echo $_SESSION['nombre']; ?></b> | <a href="dashboard.php" style="color:white;">Volver</a>
    </div>
</div>

<!-- BARRA DE BÚSQUEDA -->
<div class="search-bar">
    <h2>&#128269; Análisis de Orden de Producción</h2>
    <form method="GET">
        <input type="number" name="orden" class="input-search" placeholder="No. Orden (Ej: 5020)" value="<?php echo $orden_buscada; ?>" required autofocus>
        <button type="submit" class="btn-search">Consultar</button>
    </form>
</div>

<div class="main-container" style="display:block; padding: 20px; max-width: 1100px; margin: 0 auto;">

    <?php if ($orden_info): ?>
        
        <!-- 1. FICHA DE LA ORDEN -->
        <div class="info-panel">
            <h2 style="margin-top:0; color:#0056b3; display:inline-block;">Orden #<?php echo $orden_info['NumeroOrden']; ?></h2>
            <span style="float:right; background:#eee; padding:5px 10px; border-radius:5px;">Ref: <b><?php echo $orden_info['Referencia']; ?></b></span>
            <p style="margin-top:10px;">
                <b>Producto:</b> <?php echo $orden_info['Producto']; ?><br>
                <b>Meta Programada:</b> <?php echo number_format($orden_info['Cantidad']); ?> Unidades
            </p>
            
            <?php 
                $porcentaje = ($orden_info['Cantidad'] > 0) ? round(($stats['real_producido'] / $orden_info['Cantidad']) * 100) : 0;
            ?>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo min($porcentaje, 100); ?>%;">
                    Avance: <?php echo $porcentaje; ?>% (<?php echo number_format($stats['real_producido']); ?> / <?php echo number_format($orden_info['Cantidad']); ?>)
                </div>
            </div>
        </div>

        <!-- 2. TOTALES ACUMULADOS -->
        <div class="section-title">&#128202; Totales Acumulados (Suma de <?php echo $stats['total_turnos']; ?> turnos)</div>
        <div class="kpi-container">
            <div class="kpi-box bd-blue">
                <div class="kpi-val"><?php echo number_format($stats['real_producido']); ?></div>
                <div class="kpi-tit">Total Buenas</div>
            </div>
            <div class="kpi-box bd-red">
                <div class="kpi-val" style="color:#dc3545;"><?php echo number_format($stats['total_desperdicio']); ?></div>
                <div class="kpi-tit">Total Malas</div>
            </div>
            <div class="kpi-box bd-blue">
                <div class="kpi-val"><?php echo floor($stats['tiempo_total_invertido'] / 3600); ?>h <?php echo floor(($stats['tiempo_total_invertido'] % 3600) / 60); ?>m</div>
                <div class="kpi-tit">Tiempo Invertido</div>
            </div>
            <div class="kpi-box bd-blue">
                <div class="kpi-val"><?php echo number_format($stats['total_bolsa_kg'], 2); ?> kg</div>
                <div class="kpi-tit">Consumo Bolsa</div>
            </div>
        </div>

        <!-- 3. ANÁLISIS DE PROMEDIOS Y EFICIENCIA (NUEVO) -->
        <div class="section-title">&#9878; Promedios y Rendimiento Real</div>
        <div class="kpi-container">
            <!-- Promedio Buenas -->
            <div class="kpi-box bd-green">
                <div class="kpi-val"><?php echo number_format($promedio_buenas, 0); ?></div>
                <div class="kpi-tit">Promedio Buenas / Turno</div>
            </div>
            
            <!-- Promedio Malas -->
            <div class="kpi-box bd-red">
                <div class="kpi-val"><?php echo number_format($promedio_malas, 0); ?></div>
                <div class="kpi-tit">Promedio Malas / Turno</div>
            </div>

            <!-- Ciclo Real vs Teórico -->
            <div class="kpi-box bd-orange">
                <div class="kpi-val"><?php echo number_format($ciclo_real, 2); ?> seg</div>
                <div class="kpi-tit">Tiempo Real x Unidad</div>
                <small style="color:#666;">(Teórico: <?php echo number_format($ciclo_std, 2); ?>s)</small>
            </div>

            <!-- Comparativa Visual -->
            <div class="kpi-box bd-orange">
                <?php 
                    // Diferencia de ciclo
                    $diff_ciclo = $ciclo_real - $ciclo_std;
                    $color_diff = ($diff_ciclo <= 0) ? 'green' : 'red';
                    $signo = ($diff_ciclo > 0) ? '+' : '';
                ?>
                <div class="kpi-val" style="color:<?php echo $color_diff; ?>;">
                    <?php echo $signo . number_format($diff_ciclo, 2); ?> s
                </div>
                <div class="kpi-tit">Desviación vs Estándar</div>
                <small>(Negativo es bueno)</small>
            </div>
        </div>

        <!-- 4. DESGLOSE DETALLADO -->
        <h3>&#128196; Detalle de Turnos</h3>
        <?php if ($res_turnos->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Turno</th>
                        <th>Horas</th>
                        <th>Buenas</th>
                        <th>Malas</th>
                        <th>Kg Bolsa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = $res_turnos->fetch_assoc()) { 
                        $horas = $t['tiempo_total_seg'] / 3600;
                    ?>
                    <tr>
                        <td><?php echo $t['fecha']; ?></td>
                        <td><?php echo $t['numero_turno']; ?></td>
                        <td><?php echo number_format($horas, 1); ?> h</td>
                        <td><b><?php echo number_format($t['unidades_buenas']); ?></b></td>
                        <td style="color:red;"><?php echo $t['unidades_malas']; ?></td>
                        <td><?php echo $t['consumo_bolsa']; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align:center; color:#666;">A&uacute;n no se han registrado turnos para esta orden.</p>
        <?php endif; ?>

    <?php elseif (isset($_GET['orden'])): ?>
        <div style="text-align:center; padding:40px; color:red; background:white; border-radius:8px;">
            <h3>&#10060; Orden no encontrada</h3>
            <p>Verifica que el n&uacute;mero sea correcto.</p>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:50px; color:#aaa;">
            <div style="font-size:4em;">&#128202;</div>
            <p>Ingresa un n&uacute;mero de orden arriba para ver su análisis completo.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
<?php
// historial_producto.php
// ENFOQUE: HISTORIA COMPLETA DEL PRODUCTO CON TOTALES FINALES
header('Content-Type: text/html; charset=utf-8');
include 'auth.php';
include 'conexion.php';

$info_prod = null;
$stats_globales = null;
$datos_grafico = ['labels' => [], 'data' => []]; 
$filas_historial = [];

// Variables para la fila de totales finales
$gran_total_meta = 0;
$gran_total_real = 0;
$gran_total_malas = 0;
$gran_total_tiempo = 0; // en segundos

// LISTA PARA EL BUSCADOR (JavaScript)
$lista_js = [];
$res_todos = $conn->query("SELECT Referencia, Nombre FROM productos ORDER BY Nombre ASC");
while($p = $res_todos->fetch_assoc()) {
    $lista_js[] = [
        'ref' => trim($p['Referencia']),
        'nom' => $p['Nombre'],
        'busqueda' => strtolower(trim($p['Referencia']) . ' ' . trim($p['Nombre']))
    ];
}

// SI SE SELECCIONÓ UN PRODUCTO
if (isset($_GET['ref'])) {
    $ref = $conn->real_escape_string(urldecode($_GET['ref']));

    // 1. DATOS DEL PRODUCTO
    $sql_prod = "SELECT * FROM productos WHERE Referencia = '$ref' LIMIT 1";
    $res_prod = $conn->query($sql_prod);
    
    if ($res_prod && $res_prod->num_rows > 0) {
        $info_prod = $res_prod->fetch_assoc();

        // 2. ESTADÍSTICAS GLOBALES
        $sql_global = "
            SELECT 
                COUNT(DISTINCT M.NumeroOrden) as num_ordenes,
                SUM(T.unidades_buenas) as total_historico,
                AVG(T.unidades_buenas) as promedio_por_turno,
                SUM(T.unidades_malas) as desperdicio_total,
                SUM(T.tiempo_total_seg) as tiempo_total_global
            FROM produccion_master M
            LEFT JOIN turnos_registros T ON M.NumeroOrden = T.numero_orden
            WHERE M.Referencia = '$ref'
        ";
        $stats_globales = $conn->query($sql_global)->fetch_assoc();

        // 3. LISTADO DETALLADO POR ORDEN
        $sql_historial = "
            SELECT 
                M.NumeroOrden,
                M.Fecha as FechaOrden,
                M.Cantidad as Meta,
                SUM(T.unidades_buenas) as Realizadas,
                SUM(T.unidades_malas) as Malas,
                SUM(T.tiempo_total_seg) as TiempoInv
            FROM produccion_master M
            LEFT JOIN turnos_registros T ON M.NumeroOrden = T.numero_orden
            WHERE M.Referencia = '$ref'
            GROUP BY M.NumeroOrden
            ORDER BY M.Fecha DESC
        ";
        $res_historial = $conn->query($sql_historial);

        // Procesar datos para tabla y gráfico
        while($row = $res_historial->fetch_assoc()) {
            $filas_historial[] = $row;
            
            // Acumular para totales finales
            $gran_total_meta += $row['Meta'];
            $gran_total_real += $row['Realizadas'];
            $gran_total_malas += $row['Malas'];
            $gran_total_tiempo += $row['TiempoInv'];

            // Datos para gráfico
            array_unshift($datos_grafico['labels'], "O.P #" . $row['NumeroOrden']);
            array_unshift($datos_grafico['data'], $row['Realizadas']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial Producto - INARPLAS</title>
    <link rel="stylesheet" href="estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Buscador */
        .buscador-container { position: relative; margin-bottom: 20px; max-width: 600px; margin-left: auto; margin-right: auto; }
        .input-grande { width: 100%; padding: 15px; font-size: 1.1em; border: 2px solid #0056b3; border-radius: 30px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); outline: none; }
        .lista-resultados { position: absolute; top: 100%; left: 20px; right: 20px; background: white; border: 1px solid #ddd; border-radius: 0 0 10px 10px; max-height: 300px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .item-resultado { padding: 12px 20px; border-bottom: 1px solid #eee; cursor: pointer; text-align: left; }
        .item-resultado:hover { background-color: #e9ecef; color: #0056b3; font-weight: bold; }

        /* Tarjetas */
        .grid-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-top: 4px solid #0056b3; }
        .card-num { font-size: 2em; font-weight: bold; color: #333; }
        .card-tit { color: #777; font-size: 0.85em; text-transform: uppercase; letter-spacing: 1px; }

        /* Tabla */
        .history-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .history-table th, .history-table td { padding: 15px; text-align: center; border-bottom: 1px solid #eee; }
        .history-table th { background: #343a40; color: white; font-weight: normal; }
        .history-table tr:hover { background-color: #f8f9fa; }
        
        /* Fila de Totales */
        .fila-totales { background-color: #e2e6ea; font-weight: bold; border-top: 2px solid #0056b3; }
        .fila-totales td { color: #0056b3; font-size: 1.1em; }

        /* Gráfico */
        .chart-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="top-bar">
    <div class="logo">&#127981; INARPLAS Cloud</div>
    <div class="user-info"><b><?php echo $_SESSION['nombre']; ?></b> | <a href="dashboard.php" style="color:white;">Volver</a></div>
</div>

<div class="main-container" style="display:block; padding:30px; max-width:1200px; margin:0 auto;">
    
    <h1 style="text-align:center; color:#0056b3; margin-bottom:10px;">&#128200; Historial de Producci&oacute;n</h1>
    <p style="text-align:center; color:#666; margin-bottom:30px;">Consulta todo lo que se ha fabricado de un producto en el a&ntilde;o.</p>

    <!-- BUSCADOR -->
    <div class="buscador-container">
        <input type="text" id="inputBusqueda" class="input-grande" placeholder="Escribe el nombre o referencia del producto..." autocomplete="off">
        <div id="listaResultados" class="lista-resultados"></div>
    </div>

    <?php if ($info_prod): ?>
        
        <!-- ENCABEZADO PRODUCTO -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <div>
                <h2 style="margin:0; color:#333;"><?php echo $info_prod['Nombre']; ?></h2>
                <span style="color:#666;">Referencia: <b><?php echo $info_prod['Referencia']; ?></b></span>
            </div>
            <div style="text-align:right;">
                <span style="display:block; font-size:0.9em; color:#888;">Ciclo Est&aacute;ndar</span>
                <strong style="font-size:1.2em; color:#0056b3;"><?php echo $info_prod['Ciclo']; ?> seg</strong>
            </div>
        </div>

        <!-- 1. TOTALES ANUALES (KPIs) -->
        <div class="grid-kpi">
            <div class="card">
                <div class="card-num"><?php echo number_format($stats_globales['total_historico']); ?></div>
                <div class="card-tit">Total Unidades (A&ntilde;o)</div>
            </div>
            <div class="card">
                <div class="card-num"><?php echo $stats_globales['num_ordenes']; ?></div>
                <div class="card-tit">&Oacute;rdenes Realizadas</div>
            </div>
            <div class="card" style="border-color:#28a745;">
                <?php 
                    $prom_orden = ($stats_globales['num_ordenes'] > 0) ? ($stats_globales['total_historico'] / $stats_globales['num_ordenes']) : 0;
                ?>
                <div class="card-num" style="color:#28a745;"><?php echo number_format($prom_orden, 0); ?></div>
                <div class="card-tit">Promedio x Orden</div>
            </div>
            <div class="card" style="border-color:#dc3545;">
                <div class="card-num" style="color:#dc3545;"><?php echo number_format($stats_globales['desperdicio_total']); ?></div>
                <div class="card-tit">Total Desperdicio</div>
            </div>
        </div>

        <!-- 2. GRÁFICO DE TENDENCIA -->
        <div class="chart-container">
            <canvas id="graficoHistorial" height="80"></canvas>
        </div>

        <!-- 3. LISTADO DETALLADO CON TOTALES -->
        <h3 style="color:#555; margin-bottom:15px;">&#128196; Detalle de Órdenes (<?php echo date("Y"); ?>)</h3>
        
        <?php if (count($filas_historial) > 0): ?>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Orden (O.P)</th>
                        <th>Meta Planeada</th>
                        <th>Real Producido</th>
                        <th>Malas</th>
                        <th>Tiempo Invertido</th>
                        <th>Ver Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas_historial as $fila) { 
                        $meta = $fila['Meta'];
                        $real = $fila['Realizadas'];
                        
                        // Formato de Tiempo (Horas:Minutos)
                        $seg = $fila['TiempoInv'];
                        $horas = floor($seg / 3600);
                        $mins = floor(($seg % 3600) / 60);
                        $tiempo_str = "{$horas}h {$mins}m";
                    ?>
                    <tr>
                        <td><?php echo $fila['FechaOrden']; ?></td>
                        <td><b>#<?php echo $fila['NumeroOrden']; ?></b></td>
                        <td style="color:#888;"><?php echo number_format($meta); ?></td>
                        <td style="font-weight:bold; font-size:1.1em;"><?php echo number_format($real); ?></td>
                        <td style="color:red;"><?php echo number_format($fila['Malas']); ?></td>
                        <td style="color:#555;"><?php echo $tiempo_str; ?></td>
                        <td>
                            <a href="reporte_producto.php?orden=<?php echo $fila['NumeroOrden']; ?>" style="text-decoration:none; background:#0056b3; color:white; padding:5px 10px; border-radius:4px; font-size:0.8em;">&#128269; Ver</a>
                        </td>
                    </tr>
                    <?php } ?>
                    
                    <!-- FILA DE TOTALES GENERALES -->
                    <tr class="fila-totales">
                        <td colspan="2" style="text-align:right;">TOTALES GLOBALES:</td>
                        <td><?php echo number_format($gran_total_meta); ?></td>
                        <td><?php echo number_format($gran_total_real); ?></td>
                        <td style="color:#b00;"><?php echo number_format($gran_total_malas); ?></td>
                        <td>
                            <?php 
                                $h_total = floor($gran_total_tiempo / 3600);
                                $m_total = floor(($gran_total_tiempo % 3600) / 60);
                                echo "{$h_total}h {$m_total}m";
                            ?>
                        </td>
                        <td>-</td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align:center; padding:20px; background:white; border-radius:10px;">No se encontraron &oacute;rdenes finalizadas para este producto.</p>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
    // LOGICA DEL BUSCADOR
    const productos = <?php echo json_encode($lista_js); ?>;
    const input = document.getElementById('inputBusqueda');
    const lista = document.getElementById('listaResultados');

    input.addEventListener('keyup', function() {
        const texto = this.value.toLowerCase().trim();
        lista.innerHTML = ''; 

        if (texto.length < 2) {
            lista.style.display = 'none';
            return;
        }

        const resultados = productos.filter(p => p.busqueda.includes(texto));

        if (resultados.length > 0) {
            lista.style.display = 'block';
            resultados.slice(0, 10).forEach(p => { 
                const item = document.createElement('div');
                item.className = 'item-resultado';
                item.innerHTML = `<span style="color:#0056b3; font-weight:bold; margin-right:10px;">${p.ref}</span> ${p.nom}`;
                item.onclick = function() {
                    window.location.href = '?ref=' + encodeURIComponent(p.ref);
                };
                lista.appendChild(item);
            });
        } else {
            lista.style.display = 'none';
        }
    });

    document.addEventListener('click', function(e) { if (e.target !== input) lista.style.display = 'none'; });

    // LOGICA DEL GRÁFICO
    <?php if (isset($datos_grafico) && count($datos_grafico['data']) > 0): ?>
    const ctx = document.getElementById('graficoHistorial').getContext('2d');
    new Chart(ctx, {
        type: 'line', // Cambiado a línea para ver tendencia mejor
        data: {
            labels: <?php echo json_encode($datos_grafico['labels']); ?>,
            datasets: [{
                label: 'Unidades Producidas',
                data: <?php echo json_encode($datos_grafico['data']); ?>,
                backgroundColor: 'rgba(0, 86, 179, 0.2)',
                borderColor: 'rgba(0, 86, 179, 1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3 // Curva suave
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, title: { display: true, text: 'Tendencia de Producción' } },
            scales: { y: { beginAtZero: true } }
        }
    });
    <?php endif; ?>
</script>

</body>
</html>
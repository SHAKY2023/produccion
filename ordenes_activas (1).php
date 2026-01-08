<?php
// /public_html/sistema/ordenes_activas.php - Versión: v1.1 (AGREGADO CONSUMO BOLSA)
// MEJORAS:
// 1. Se agregó el cálculo de 'TotalBolsaKg' en la consulta principal.
// 2. Se visualiza el consumo total de bolsa por orden en la tarjeta.

// sistema/ordenes_activas.php
// Fecha: 12-12-2025
// UBICACIÓN: public_html/sistema/ordenes_activas.php
// CORRECCIÓN: Desglose detallado de MATERIAL (Meta vs Real vs Faltante) igual que paquetes.

include 'auth.php';
include 'conexion.php';

// Variables de búsqueda
$busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'en_proceso'; 

// --- 1. CONSULTA PRINCIPAL DE ÓRDENES ---
// MODIFICACIÓN v1.1: Agregado SUM(T.PesoBolsaUsado)
$sql_final = "SELECT M.NumeroOrden, M.Producto, M.Referencia, M.Cantidad as Meta, 
              COALESCE(SUM(T.Buenas), 0) as Realizadas,
              COALESCE(SUM(T.PesoBolsaUsado), 0) as TotalBolsaKg,
              MAX(T.Fecha) as UltimaFecha,
              O.numero_orden as EstaOculta,
              MAX(PROD.Peso) as PesoUnitario,
              MAX(PROD.Empaque) as UnidadesPorPaquete
              FROM produccion_master M
              LEFT JOIN produccion_turnos T ON M.NumeroOrden = T.NumeroOrden
              LEFT JOIN ordenes_ocultas O ON M.NumeroOrden = O.numero_orden
              LEFT JOIN productos PROD ON M.Referencia = PROD.Referencia
              WHERE 1=1 ";

if ($busqueda != '') {
    $sql_final .= " AND (M.NumeroOrden LIKE '%$busqueda%' OR M.Producto LIKE '%$busqueda%' OR M.Referencia LIKE '%$busqueda%') ";
} else {
    $sql_final .= " AND O.numero_orden IS NULL "; 
}

$sql_final .= " GROUP BY M.NumeroOrden ";

if ($busqueda == '' && $filtro_estado == 'en_proceso') {
    $sql_final .= " HAVING (Realizadas < Meta OR Meta IS NULL OR Meta = 0) ";
}

$sql_final .= " ORDER BY M.NumeroOrden DESC LIMIT 100";

$result = $conn->query($sql_final);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Órdenes en Proceso - INARPLAS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        .header-bar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.2rem; font-weight: bold; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        
        .search-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 25px; align-items: center; }
        .search-input { flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .btn-search { background: #0056b3; color: white; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .btn-reset { background: #6c757d; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; text-decoration: none; }
        
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: 0.3s; border-left: 5px solid #0056b3; overflow: hidden; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card-body { padding: 15px; flex-grow: 1; }
        
        .card-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .orden-num { font-size: 1.2em; font-weight: bold; color: #0056b3; }
        .fecha { font-size: 0.8em; color: #777; }
        .producto { font-weight: 600; color: #333; margin-bottom: 5px; display: block; }
        .ref { font-size: 0.85em; color: #666; margin-bottom: 10px; display: block; }
        
        .progress-container { background: #f0f0f0; border-radius: 10px; height: 8px; width: 100%; margin: 10px 0; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 10px; background: linear-gradient(90deg, #0056b3, #28a745); width: 0%; transition: width 0.5s; }
        .stats { display: flex; justify-content: space-between; font-size: 0.85em; color: #555; font-weight: bold; margin-bottom: 15px; }
        
        /* Tablas Internas */
        .mat-title { font-size: 0.85em; font-weight: bold; color: #0056b3; margin-top: 15px; display: block; border-top: 1px solid #eee; padding-top: 8px; }
        .mat-table { width: 100%; border-collapse: collapse; font-size: 0.8em; margin-top: 5px; border: 1px solid #eee; }
        .mat-table th { background: #f8f9fa; padding: 5px; text-align: left; color: #555; border-bottom: 2px solid #ddd; }
        .mat-table td { padding: 5px; border-bottom: 1px solid #eee; color: #333; }
        .col-num { text-align: right; }
        
        .diff-pos { color: green; font-weight: bold; } 
        .diff-neg { color: red; font-weight: bold; } 
        
        .btn-action { display: block; width: 100%; text-align: center; padding: 10px; background: #f8f9fa; color: #0056b3; text-decoration: none; font-weight: bold; border-top: 1px solid #eee; margin-top: auto; }
        .btn-action:hover { background: #e9ecef; }
        
        .badge-completed { background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 0.75em; }
        .badge-archived { background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 4px; font-size: 0.75em; }
        .no-results { grid-column: 1 / -1; text-align: center; padding: 40px; color: #777; }
    </style>
</head>
<body>

<div class="header-bar">
    <div class="logo"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <div>
        <a href="dashboard.php" style="color:white; margin-right:15px; text-decoration:none;"><i class="fa-solid fa-house"></i> Inicio</a>
        <a href="logout.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
    </div>
</div>

<div class="container">
    <h2 style="color:#333; border-bottom: 2px solid #0056b3; padding-bottom:10px;">
        <i class="fa-solid fa-list-check"></i> Órdenes en Proceso
    </h2>

    <form method="GET" action="ordenes_activas.php" class="search-box">
        <input type="text" name="q" class="search-input" placeholder="Buscar por # Orden, Producto o Referencia..." value="<?php echo htmlspecialchars($busqueda); ?>">
        <button type="submit" class="btn-search"><i class="fa-solid fa-search"></i> Buscar</button>
        <?php if($busqueda != ''): ?><a href="ordenes_activas.php" class="btn-reset">Ver Pendientes</a><?php endif; ?>
    </form>

    <div class="grid">
        <?php
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $id_orden = $row['NumeroOrden'];
                $meta_orden = ($row['Meta'] > 0) ? $row['Meta'] : 0;
                $peso_unitario = isset($row['PesoUnitario']) ? floatval($row['PesoUnitario']) : 0;
                $empaque = isset($row['UnidadesPorPaquete']) ? intval($row['UnidadesPorPaquete']) : 1; // Evitar div 0
                if ($empaque <= 0) $empaque = 1;

                // Arrays para consolidar datos
                $datos_colores = []; // Estructura: [Color => ['meta_u'=>0, 'meta_p'=>0, 'real_p'=>0, 'peso_proy'=>0, 'peso_real'=>0]]

                // 1. OBTENER METAS POR COLOR (Desde produccion_mezclas)
                $peso_teorico_total = ($meta_orden * $peso_unitario) / 1000;
                
                $sql_proy = "SELECT Color, Peso, Cantidad FROM produccion_mezclas WHERE NumeroOrden = '$id_orden'";
                $res_proy = $conn->query($sql_proy);
                if($res_proy) {
                    while($mp = $res_proy->fetch_assoc()) {
                        $c = strtoupper(trim($mp['Color']));
                        if(!isset($datos_colores[$c])) {
                            $datos_colores[$c] = ['meta_u' => 0, 'meta_p' => 0, 'real_p' => 0, 'peso_proy' => 0, 'peso_real' => 0];
                        }
                        
                        // Meta Unidades por color
                        $cant_meta = isset($mp['Cantidad']) ? intval($mp['Cantidad']) : 0;
                        $datos_colores[$c]['meta_u'] = $cant_meta;
                        
                        // Meta Paquetes por color (Unidades / Empaque)
                        $datos_colores[$c]['meta_p'] = ceil($cant_meta / $empaque);

                        // Peso Proyectado Material
                        $valor_peso = floatval($mp['Peso']);
                        $datos_colores[$c]['peso_proy'] = ($valor_peso > 0) ? $valor_peso : (($cant_meta * $peso_unitario)/1000);
                    }
                }

                // 2. OBTENER REALIZADOS POR COLOR (Desde produccion_paquetes)
                $sql_real = "SELECT Color, COUNT(*) as NumPaquetes, SUM(Peso) as PesoTotal 
                             FROM produccion_paquetes 
                             WHERE NumeroOrden = '$id_orden' 
                             GROUP BY Color";
                $res_real = $conn->query($sql_real);
                if($res_real) {
                    while($real = $res_real->fetch_assoc()) {
                        $c = strtoupper(trim($real['Color']));
                        if(!isset($datos_colores[$c])) {
                            // Si aparece un color que no estaba en la receta original
                            $datos_colores[$c] = ['meta_u' => 0, 'meta_p' => 0, 'real_p' => 0, 'peso_proy' => 0, 'peso_real' => 0];
                        }
                        $datos_colores[$c]['real_p'] = intval($real['NumPaquetes']);
                        $datos_colores[$c]['peso_real'] = floatval($real['PesoTotal']);
                    }
                }
                ksort($datos_colores);

                // Calcular totales globales de paquetes
                $total_meta_pq = 0;
                $total_real_pq = 0;
                foreach($datos_colores as $d) {
                    $total_meta_pq += $d['meta_p'];
                    $total_real_pq += $d['real_p'];
                }
                $total_faltante_pq = max(0, $total_meta_pq - $total_real_pq);

                // --- ESTADO GENERAL ---
                $real_u = $row['Realizadas'];
                $porcentaje = ($meta_orden > 0) ? min(100, round(($real_u / $meta_orden) * 100)) : 0;
                $es_completada = ($real_u >= $meta_orden && $meta_orden > 0);
                $borde_color = $es_completada ? "#28a745" : "#0056b3";
        ?>
            <div class="card" style="border-left-color: <?php echo $borde_color; ?>;">
                <div class="card-body">
                    <div class="card-header">
                        <span class="orden-num">#<?php echo $row['NumeroOrden']; ?></span>
                        <span class="fecha"><?php echo $row['UltimaFecha'] ? date("d/m", strtotime($row['UltimaFecha'])) : 'Nueva'; ?></span>
                    </div>
                    
                    <span class="producto"><?php echo $row['Producto']; ?></span>
                    <span class="ref">Ref: <?php echo $row['Referencia']; ?> | Empaque: <?php echo $empaque; ?> Unds</span>

                    <?php if($meta_orden > 0): ?>
                    <div class="progress-container"><div class="progress-bar" style="width: <?php echo $porcentaje; ?>%; background: <?php echo $borde_color; ?>;"></div></div>
                    <div class="stats">
                        <span><?php echo number_format($real_u); ?> / <?php echo number_format($meta_orden); ?> Unds</span>
                        <span><?php echo $porcentaje; ?>%</span>
                    </div>
                    <?php endif; ?>

                    <!-- TABLA DETALLADA DE PAQUETES -->
                    <span class="mat-title"><i class="fa-solid fa-boxes-stacked"></i> Control de Paquetes</span>
                    <div style="font-size:0.85em; margin-bottom:5px; display:flex; justify-content:space-between; background:#e3f2fd; padding:5px; border-radius:4px;">
                        <span><strong>Meta:</strong> <?php echo $total_meta_pq; ?> Pq.</span>
                        <span style="color:green"><strong>Hechos:</strong> <?php echo $total_real_pq; ?></span>
                        <span style="color:red"><strong>Faltan:</strong> <?php echo $total_faltante_pq; ?></span>
                    </div>
                    
                    <?php if(!empty($datos_colores)): ?>
                        <table class="mat-table">
                            <thead>
                                <tr>
                                    <th>Color</th>
                                    <th class="col-num">Meta</th>
                                    <th class="col-num">Real</th>
                                    <th class="col-num">Falta</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($datos_colores as $color => $datos): 
                                $falta = max(0, $datos['meta_p'] - $datos['real_p']);
                                $clase_falta = ($falta > 0) ? 'diff-neg' : 'diff-pos';
                                $txt_falta = ($falta > 0) ? $falta : '<i class="fa-solid fa-check"></i>';
                            ?>
                                <tr>
                                    <td><?php echo $color; ?></td>
                                    <td class="col-num" style="color:#666;"><?php echo $datos['meta_p']; ?></td>
                                    <td class="col-num" style="font-weight:bold; color:#0056b3;"><?php echo $datos['real_p']; ?></td>
                                    <td class="col-num <?php echo $clase_falta; ?>"><?php echo $txt_falta; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="font-size:0.8em; color:#999; font-style:italic;">Sin definición de colores.</p>
                    <?php endif; ?>

                    <!-- TABLA RESUMEN MATERIAL -->
                    <span class="mat-title" style="margin-top:10px;"><i class="fa-solid fa-weight-hanging"></i> Material (Kg)</span>
                    
                    <?php 
                    // Calcular totales de material para cabecera
                    $total_meta_kg = 0;
                    $total_real_kg = 0;
                    foreach($datos_colores as $d) {
                        $total_meta_kg += $d['peso_proy'];
                        $total_real_kg += $d['peso_real'];
                    }
                    $total_falta_kg = max(0, $total_meta_kg - $total_real_kg);
                    ?>

                    <div style="font-size:0.85em; margin-bottom:5px; display:flex; justify-content:space-between; background:#fff3cd; padding:5px; border-radius:4px;">
                        <span><strong>Meta:</strong> <?php echo number_format($total_meta_kg, 1); ?> Kg</span>
                        <span style="color:green"><strong>Usado:</strong> <?php echo number_format($total_real_kg, 1); ?></span>
                        <span style="color:red"><strong>Falta:</strong> <?php echo number_format($total_falta_kg, 1); ?></span>
                    </div>

                    <?php if(!empty($datos_colores)): ?>
                        <table class="mat-table">
                            <thead>
                                <tr>
                                    <th>Color</th>
                                    <th class="col-num">Meta</th>
                                    <th class="col-num">Real</th>
                                    <th class="col-num">Falta</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($datos_colores as $color => $datos): 
                                $falta_kg = max(0, $datos['peso_proy'] - $datos['peso_real']);
                                $clase_falta_kg = ($falta_kg > 0.1) ? 'diff-neg' : 'diff-pos'; // Tolerancia pequeña
                                $txt_falta_kg = ($falta_kg > 0.1) ? number_format($falta_kg, 1) : '<i class="fa-solid fa-check"></i>';
                            ?>
                                <tr>
                                    <td><?php echo $color; ?></td>
                                    <td class="col-num" style="color:#666;"><?php echo number_format($datos['peso_proy'], 1); ?></td>
                                    <td class="col-num" style="font-weight:bold; color:#0056b3;"><?php echo number_format($datos['peso_real'], 1); ?></td>
                                    <td class="col-num <?php echo $clase_falta_kg; ?>"><?php echo $txt_falta_kg; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <!-- NUEVO BLOQUE: CONSUMO BOLSA (v1.1) -->
                    <div style="font-size:0.85em; margin-top:8px; border-top:1px dashed #ddd; padding-top:8px; color:#555;">
                        <i class="fa-solid fa-bag-shopping"></i> Bolsa Utilizada: <strong><?php echo number_format($row['TotalBolsaKg'], 3); ?> Kg</strong>
                    </div>

                </div>
                <a href="gestion_turno.php?orden=<?php echo $row['NumeroOrden']; ?>" class="btn-action">Gestionar Producción <i class="fa-solid fa-arrow-right"></i></a>
            </div>
        <?php 
            }
        } else {
            echo "<div class='no-results'><i class='fa-solid fa-box-open' style='font-size:3em;'></i><br>No se encontraron órdenes activas.</div>";
        }
        ?>
    </div>
</div>

</body>
</html>
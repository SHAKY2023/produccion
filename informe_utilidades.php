<?php
// public_html/sistema/informe_utilidades.php
// Fecha: 06-12-2025 12:00
// DESCRIPCIÓN: Informe de Rendimiento y Utilización por Máquina.

include 'auth.php';
include 'conexion.php';

// Filtro de fecha (Por defecto mes actual)
$fecha_ini = isset($_GET['f_ini']) ? $_GET['f_ini'] : date('Y-m-01');
$fecha_fin = isset($_GET['f_fin']) ? $_GET['f_fin'] : date('Y-m-t');

// Consulta de Rendimiento por Máquina
$sql = "SELECT 
            M.Maquina,
            COUNT(T.id) as TotalTurnos,
            SUM(T.UnidadesFisicas) as TotalFisicas,
            SUM(T.Buenas) as TotalBuenas,
            SUM(T.Malas) as TotalMalas,
            SUM(T.TiempoTotalSeg) as TiempoTotalSegundos,
            -- Cálculo de Eficiencia Aproximada (Ciclo Std vs Real)
            AVG(CASE WHEN T.CicloReal > 0 THEN (P.Ciclo / T.CicloReal) * 100 ELSE 0 END) as EficienciaPromedio
        FROM produccion_turnos T
        INNER JOIN produccion_master M ON T.NumeroOrden = M.NumeroOrden
        LEFT JOIN productos P ON M.Referencia = P.Referencia
        WHERE T.Fecha BETWEEN '$fecha_ini' AND '$fecha_fin'
        GROUP BY M.Maquina
        ORDER BY M.Maquina ASC";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Utilidades (Rendimiento) - INARPLAS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        .header-bar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table-data { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table-data th { background: #0056b3; color: white; padding: 10px; text-align: left; }
        .table-data td { padding: 10px; border-bottom: 1px solid #eee; }
        .filter-form { display: flex; gap: 10px; align-items: center; margin-bottom: 15px; }
        .btn { padding: 8px 15px; border-radius: 4px; border: none; cursor: pointer; color: white; font-weight: bold; }
        .btn-blue { background: #0056b3; }
        .progress-bar-bg { background: #eee; height: 8px; border-radius: 4px; width: 100px; }
        .progress-bar-fill { height: 100%; border-radius: 4px; }
    </style>
</head>
<body>

<div class="header-bar">
    <div style="font-weight:bold;"><i class="fa-solid fa-chart-line"></i> Informe de Utilidades (Rendimiento)</div>
    <a href="dashboard.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-house"></i> Inicio</a>
</div>

<div class="container">
    <div class="card">
        <form class="filter-form" method="GET">
            <label>Desde:</label>
            <input type="date" name="f_ini" value="<?php echo $fecha_ini; ?>" required>
            <label>Hasta:</label>
            <input type="date" name="f_fin" value="<?php echo $fecha_fin; ?>" required>
            <button type="submit" class="btn btn-blue">Filtrar</button>
        </form>

        <table class="table-data">
            <thead>
                <tr>
                    <th>Unidad (Máquina)</th>
                    <th>Turnos</th>
                    <th>Producción Total</th>
                    <th>Calidad %</th>
                    <th>Tiempo Trabajado</th>
                    <th>Eficiencia Ciclo</th>
                </tr>
            </thead>
            <tbody>
                <?php if($res && $res->num_rows > 0): 
                    while($row = $res->fetch_assoc()): 
                        $calidad = ($row['TotalFisicas'] > 0) ? ($row['TotalBuenas'] / $row['TotalFisicas']) * 100 : 0;
                        $horas = $row['TiempoTotalSegundos'] / 3600;
                        $efi = $row['EficienciaPromedio'];
                        $colorEfi = ($efi >= 90) ? '#28a745' : (($efi >= 75) ? '#ffc107' : '#dc3545');
                ?>
                <tr>
                    <td><b><?php echo $row['Maquina']; ?></b></td>
                    <td><?php echo $row['TotalTurnos']; ?></td>
                    <td>
                        <?php echo number_format($row['TotalBuenas']); ?> <small style="color:green;">OK</small><br>
                        <small style="color:red;"><?php echo number_format($row['TotalMalas']); ?> Scrap</small>
                    </td>
                    <td><?php echo number_format($calidad, 1); ?>%</td>
                    <td><?php echo number_format($horas, 1); ?> Hrs</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:5px;">
                            <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?php echo min(100, $efi); ?>%; background:<?php echo $colorEfi; ?>;"></div></div>
                            <span><?php echo number_format($efi, 1); ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="6" style="text-align:center; padding:20px;">No hay datos en este rango.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
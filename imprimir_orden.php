<?php
// sistema/imprimir_orden.php
// UBICACIÓN: public_html/sistema/imprimir_orden.php
// DESCRIPCIÓN: Vista de impresión limpia para "Hoja de Ruta" del operario.

include 'auth.php';
include 'conexion.php';

if (!isset($_GET['id'])) die("Error: Falta ID de Orden");
$id = $conn->real_escape_string($_GET['id']);

// 1. Datos Maestros
$master = $conn->query("SELECT * FROM produccion_master WHERE NumeroOrden = '$id'")->fetch_assoc();
if(!$master) die("Orden no encontrada");

// 2. Colores (Mezcla)
$mezclas = $conn->query("SELECT * FROM produccion_mezclas WHERE NumeroOrden = '$id'");

// 3. Imagen del Producto (Opcional, busca en productos por referencia o nombre)
$ref = $master['Referencia'];
$prod_info = $conn->query("SELECT Imagen FROM productos WHERE Referencia = '$ref' LIMIT 1")->fetch_assoc();
$img_src = (!empty($prod_info['Imagen'])) ? "uploads/productos/".$prod_info['Imagen'] : "assets/img/logo_placeholder.png";

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de Ruta #<?php echo $id; ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; color: #000; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; text-transform: uppercase; }
        .meta-box { border: 2px solid #000; padding: 15px; margin-bottom: 20px; display: flex; }
        .meta-img { width: 150px; height: 150px; object-fit: contain; border: 1px solid #ccc; margin-right: 20px; }
        .meta-info { flex: 1; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 18px; }
        .label { font-weight: bold; }
        .big-number { font-size: 32px; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 10px; text-align: left; }
        th { background: #eee; }
        
        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .firma-box { width: 40%; border-top: 1px solid #000; padding-top: 10px; text-align: center; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <button class="no-print" onclick="window.print()" style="padding:10px 20px; font-size:16px; cursor:pointer; margin-bottom:20px;">🖨️ Imprimir Hoja</button>

    <div class="header">
        <div class="title">HOJA DE RUTA DE PRODUCCIÓN</div>
        <div>
            <div>Fecha: <?php echo $master['Fecha']; ?></div>
            <div style="font-size:1.5em; font-weight:bold;">OP #<?php echo $master['NumeroOrden']; ?></div>
        </div>
    </div>

    <!-- Información Principal -->
    <div class="meta-box">
        <!-- Intenta mostrar imagen si existe -->
        <img src="<?php echo $img_src; ?>" class="meta-img" onerror="this.style.display='none'">
        
        <div class="meta-info">
            <div class="row"><span class="label">Producto:</span> <span><?php echo $master['Producto']; ?></span></div>
            <div class="row"><span class="label">Referencia:</span> <span><?php echo $master['Referencia']; ?></span></div>
            
            <?php if($master['Prioridad'] == 1): ?>
                <div class="row" style="margin:15px 0; border:2px dashed #000; padding:5px; text-align:center; font-weight:bold;">⚠️ PEDIDO URGENTE</div>
            <?php endif; ?>

            <div class="row" style="margin-top:20px; align-items:center;">
                <span class="label">META A PRODUCIR:</span> 
                <span class="big-number"><?php echo number_format($master['Cantidad']); ?> Unds</span>
            </div>
        </div>
    </div>

    <!-- Tabla de Mezcla -->
    <h3>🎨 Mezcla / Colores Requeridos</h3>
    <table>
        <thead>
            <tr>
                <th>Material / Color</th>
                <th>Cantidad (Unidades)</th>
                <th>Verificación (Inicio Turno)</th>
            </tr>
        </thead>
        <tbody>
            <?php while($m = $mezclas->fetch_assoc()): ?>
            <tr>
                <td><?php echo $m['Color']; ?></td>
                <td><?php echo number_format($m['Cantidad']); ?></td>
                <td></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div style="margin-top:20px; border:1px solid #000; padding:10px; min-height:80px;">
        <strong>Observaciones:</strong>
    </div>

    <div class="footer">
        <div class="firma-box">Firma Supervisor</div>
        <div class="firma-box">Firma Operario</div>
    </div>

</body>
</html>
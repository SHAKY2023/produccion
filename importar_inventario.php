<?php
// public_html/sistema/importar_inventario.php
// Fecha: 05-01-2026
// DESCRIPCIÓN: Herramienta para cargar inventario inicial desde CSV.

include 'auth.php';
include 'conexion.php';

// Validar Permisos (Solo ADMIN)
if (!isset($_SESSION['rol']) || $_SESSION['rol'] != 'ADMIN') {
    die("<div style='color:red; padding:20px;'>🚫 Acceso Denegado. Solo administradores pueden importar inventario.</div>");
}

$mensaje = "";
$error = "";
$reporte = "";

if (isset($_POST['importar'])) {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
        
        $tmp_name = $_FILES['archivo_csv']['tmp_name'];
        $csvFile = fopen($tmp_name, 'r');
        
        // Saltar la primera línea (Encabezados: REFERENCIA;DESCRIPCION;SALDO)
        fgetcsv($csvFile, 1000, ";"); 

        $insertados = 0;
        $actualizados = 0;
        $errores = 0;
        
        // Iniciar transacción para seguridad
        $conn->begin_transaction();

        try {
            while (($linea = fgetcsv($csvFile, 1000, ";")) !== FALSE) {
                // Verificar que la línea tenga datos (Referencia y Descripción)
                if (empty($linea[0]) || empty($linea[1])) continue;

                $ref = trim($conn->real_escape_string($linea[0]));
                $desc = trim($conn->real_escape_string($linea[1]));
                
                // Limpiar saldo (quitar comas de miles y espacios)
                // Ejemplo: "1,234.00" -> "1234.00"
                $saldo_limpio = str_replace(',', '', $linea[2]); 
                $saldo = floatval($saldo_limpio);

                // 1. Verificar si existe
                $check = $conn->query("SELECT id FROM materiales WHERE referencia = '$ref'");
                
                if ($check->num_rows > 0) {
                    // ACTUALIZAR (Si quieres sobreescribir el saldo inicial)
                    $conn->query("UPDATE materiales SET saldo = $saldo, descripcion = '$desc' WHERE referencia = '$ref'");
                    $actualizados++;
                } else {
                    // INSERTAR NUEVO
                    $sql_ins = "INSERT INTO materiales (referencia, descripcion, saldo, stock_minimo) VALUES ('$ref', '$desc', $saldo, 10)";
                    if($conn->query($sql_ins)) {
                        $insertados++;
                    } else {
                        $errores++;
                    }
                }
            }
            
            $conn->commit();
            fclose($csvFile);
            
            $mensaje = "✅ <b>Proceso Terminado:</b><br>
                        - Nuevos Creados: <b>$insertados</b><br>
                        - Existentes Actualizados: <b>$actualizados</b><br>
                        - Errores: <b>$errores</b>";
                        
        } catch (Exception $e) {
            $conn->rollback();
            $error = "❌ Error Crítico: " . $e->getMessage();
        }
        
    } else {
        $error = "⚠️ Por favor selecciona un archivo CSV válido.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Inventario - INARPLAS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; padding: 20px; }
        .card { background: white; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-top: 5px solid #0056b3; }
        h2 { color: #0056b3; margin-top: 0; }
        .btn { background: #28a745; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 1em; font-weight: bold; width: 100%; margin-top: 15px; }
        .btn:hover { background: #218838; }
        .back-link { display: block; text-align: center; margin-top: 15px; text-decoration: none; color: #666; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-box { background: #e3f2fd; padding: 15px; border-radius: 5px; font-size: 0.9em; color: #0d47a1; margin-bottom: 20px; border: 1px solid #b3d7ff; }
    </style>
</head>
<body>

<div class="card">
    <h2><i class="fa-solid fa-file-csv"></i> Carga Masiva de Inventario</h2>
    
    <?php if($mensaje) echo "<div class='alert success'>$mensaje</div>"; ?>
    <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

    <div class="info-box">
        <b>Instrucciones:</b><br>
        1. El archivo debe ser <b>.CSV</b> separado por <b>punto y coma (;)</b>.<br>
        2. El orden de columnas debe ser: <b>REFERENCIA; DESCRIPCION; SALDO</b>.<br>
        3. La primera fila se ignorará (encabezados).<br>
        4. Si la referencia ya existe, <b>se actualizará su saldo</b>.
    </div>

    <form method="POST" enctype="multipart/form-data">
        <label style="font-weight:bold; display:block; margin-bottom:5px;">Seleccionar Archivo CSV:</label>
        <input type="file" name="archivo_csv" accept=".csv" required style="border: 1px solid #ccc; padding: 10px; width: 100%; border-radius: 4px;">
        
        <button type="submit" name="importar" class="btn">
            <i class="fa-solid fa-upload"></i> Subir e Importar
        </button>
    </form>
    
    <a href="inventario.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver al Inventario</a>
</div>

</body>
</html>
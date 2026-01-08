<?php
// sistema/editar_producto.php
// Fecha: 05-12-2025 23:30:00
// UBICACIÓN: public_html/sistema/editar_producto.php
// DESCRIPCIÓN: Formulario para editar productos existentes y actualizar su imagen.

include 'auth.php';
include 'conexion.php';

$mensaje = "";
$producto = null;

// --- 1. PROCESAR GUARDADO (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar'])) {
    $id_codigo = intval($_POST['id_codigo']); // ID oculto (Llave primaria 'Codigo')
    
    // Recibir y limpiar datos
    $referencia = $conn->real_escape_string($_POST['referencia']);
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $grupo = $conn->real_escape_string($_POST['grupo']);
    $cod_barras = $conn->real_escape_string($_POST['codigobarras']);
    $plu = $conn->real_escape_string($_POST['plu']);
    $marca = $conn->real_escape_string($_POST['marca']);
    
    $peso = floatval($_POST['peso']);
    $ciclo = floatval($_POST['ciclo']);
    $empaque = intval($_POST['empaque']);
    $bolsa = intval($_POST['bolsa']);
    
    // Manejo de Imagen
    $nombre_imagen = $_POST['imagen_actual']; // Por defecto mantenemos la actual
    
    if (isset($_FILES['nueva_imagen']) && $_FILES['nueva_imagen']['error'] == 0) {
        $directorio = "imagenes_productos/";
        if (!is_dir($directorio)) { mkdir($directorio, 0777, true); }
        
        $info = pathinfo($_FILES['nueva_imagen']['name']);
        $ext = $info['extension'];
        // Renombramos la imagen con la referencia para evitar duplicados y mantener orden
        $nuevo_nombre_archivo = $referencia . '.' . $ext;
        $ruta_destino = $directorio . $nuevo_nombre_archivo;
        
        if (move_uploaded_file($_FILES['nueva_imagen']['tmp_name'], $ruta_destino)) {
            $nombre_imagen = $nuevo_nombre_archivo;
        } else {
            $mensaje = "<div class='alert error'>Error al subir la imagen. Verifique permisos.</div>";
        }
    }

    // Actualizar en Base de Datos (Usamos 'Codigo' como WHERE)
    $sql_update = "UPDATE productos SET 
                   Referencia = '$referencia',
                   Nombre = '$nombre',
                   Grupo = '$grupo',
                   Codigobarras = '$cod_barras',
                   Plu = '$plu',
                   Marca = '$marca',
                   Peso = $peso,
                   Ciclo = $ciclo,
                   Empaque = $empaque,
                   Bolsa = $bolsa,
                   Imagen = '$nombre_imagen'
                   WHERE Codigo = $id_codigo";

    if ($conn->query($sql_update)) {
        $mensaje = "<div class='alert success'>✅ Producto actualizado correctamente.</div>";
        // Redireccionar tras 1.5 seg para ver el mensaje
        header("refresh:1.5;url=productos.php");
    } else {
        $mensaje = "<div class='alert error'>❌ Error SQL: " . $conn->error . "</div>";
    }
}

// --- 2. CARGAR DATOS DEL PRODUCTO (GET) ---
if (isset($_GET['id'])) {
    $id_busqueda = intval($_GET['id']); // Este 'id' viene del enlace en productos.php y corresponde a 'Codigo'
    
    $sql_get = "SELECT * FROM productos WHERE Codigo = $id_busqueda";
    $res_get = $conn->query($sql_get);
    
    if ($res_get && $res_get->num_rows > 0) {
        $producto = $res_get->fetch_assoc();
    } else {
        die("<div style='padding:20px; color:red;'>Producto no encontrado. <a href='productos.php'>Volver</a></div>");
    }
} else {
    header("Location: productos.php"); // Si no hay ID, volver al listado
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto - INARPLAS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        .header-bar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 30px; max-width: 900px; margin: 0 auto; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 30px; border-top: 5px solid #ffc107; }
        h2 { color: #333; margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 25px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media(max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; font-size: 0.9em; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1rem; }
        input:focus { border-color: #0056b3; outline: none; }
        
        .section-title { grid-column: 1 / -1; font-size: 1.1em; color: #0056b3; margin-top: 15px; margin-bottom: 10px; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        
        .btn-submit { background: #28a745; color: white; border: none; padding: 12px 25px; font-size: 1.1em; border-radius: 5px; cursor: pointer; margin-right: 10px; }
        .btn-cancel { background: #6c757d; color: white; border: none; padding: 12px 25px; font-size: 1.1em; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-submit:hover { background: #218838; }
        .btn-cancel:hover { background: #5a6268; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .img-preview-container { text-align: center; padding: 10px; border: 1px dashed #ccc; border-radius: 5px; background: #fafafa; }
        .img-preview { max-width: 150px; max-height: 150px; object-fit: contain; margin-bottom: 10px; border: 1px solid #ddd; padding: 2px; background: white; }
    </style>
</head>
<body>

<div class="header-bar">
    <div style="font-weight:bold; font-size:1.2em;"><i class="fa-solid fa-industry"></i> INARPLAS Cloud</div>
    <a href="productos.php" style="color:white; text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Volver al listado</a>
</div>

<div class="container">
    <?php echo $mensaje; ?>

    <div class="card">
        <h2><i class="fa-solid fa-pen-to-square" style="color:#ffc107;"></i> Editar Producto</h2>
        
        <form method="POST" action="editar_producto.php?id=<?php echo $producto['Codigo']; ?>" enctype="multipart/form-data">
            <!-- ID OCULTO PARA EL UPDATE -->
            <input type="hidden" name="id_codigo" value="<?php echo $producto['Codigo']; ?>">
            
            <div class="form-grid">
                <!-- COLUMNA IZQUIERDA -->
                <div>
                    <div class="section-title">Información General</div>
                    
                    <div class="form-group">
                        <label>Referencia (Única):</label>
                        <input type="text" name="referencia" value="<?php echo htmlspecialchars($producto['Referencia']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nombre del Producto:</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['Nombre']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Grupo / Categoría:</label>
                        <input type="text" name="grupo" value="<?php echo htmlspecialchars($producto['Grupo']); ?>" list="lista_grupos">
                        <datalist id="lista_grupos">
                            <option value="ALCANCIAS">
                            <option value="MATERAS">
                            <option value="BANDEJAS">
                            <option value="MACETAS">
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label>Marca:</label>
                        <input type="text" name="marca" value="<?php echo htmlspecialchars($producto['Marca']); ?>">
                    </div>
                </div>

                <!-- COLUMNA DERECHA -->
                <div>
                    <div class="section-title">Códigos Internos</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Código de Barras:</label>
                            <input type="text" name="codigobarras" value="<?php echo htmlspecialchars($producto['Codigobarras']); ?>">
                        </div>
                        <div class="form-group">
                            <label>PLU:</label>
                            <input type="text" name="plu" value="<?php echo htmlspecialchars($producto['Plu']); ?>">
                        </div>
                    </div>

                    <div class="section-title">Datos Técnicos</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Peso (gramos):</label>
                            <input type="number" step="0.01" name="peso" value="<?php echo $producto['Peso']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Ciclo (segundos):</label>
                            <input type="number" step="0.01" name="ciclo" value="<?php echo $producto['Ciclo']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Empaque (Unds):</label>
                            <input type="number" name="empaque" value="<?php echo $producto['Empaque']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Bolsa (Unds):</label>
                            <input type="number" name="bolsa" value="<?php echo $producto['Bolsa']; ?>">
                        </div>
                    </div>
                </div>
                
                <!-- SECCIÓN IMAGEN (ANCHO COMPLETO) -->
                <div style="grid-column: 1 / -1;">
                    <div class="section-title">Imagen del Producto</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Subir Nueva Imagen (Opcional):</label>
                            <input type="file" name="nueva_imagen" accept="image/*">
                            <small style="color:#777;">Formatos: JPG, PNG. Se renombrará automáticamente.</small>
                        </div>
                        
                        <div class="img-preview-container">
                            <label>Imagen Actual:</label><br>
                            <?php 
                                $img_actual = "imagenes_productos/sin_imagen.png";
                                if (!empty($producto['Imagen']) && file_exists("imagenes_productos/" . $producto['Imagen'])) {
                                    $img_actual = "imagenes_productos/" . $producto['Imagen'];
                                }
                            ?>
                            <img src="<?php echo $img_actual; ?>" class="img-preview">
                            <input type="hidden" name="imagen_actual" value="<?php echo $producto['Imagen']; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:25px; text-align:right;">
                <a href="productos.php" class="btn-cancel">Cancelar</a>
                <button type="submit" name="actualizar" class="btn-submit"><i class="fa-solid fa-save"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
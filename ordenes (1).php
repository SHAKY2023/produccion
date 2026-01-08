<?php
// sistema/ordenes.php - Versión: v1.5 (ESTADO INICIAL AUTOMÁTICO)
// Fecha: 06-12-2025 10:30:00 -> Act: 06-01-2026
// UBICACIÓN: public_html/sistema/ordenes.php
// MODIFICACIÓN: Cálculo de tiempo estimado inteligente (Horario Normal vs Continuo 24h).
// MEJORAS ACUMULADAS:
// ... (versiones anteriores v1.3 - v1.4)
// 3. MEJORA v1.4: Seguridad CSRF, Paginación, Filtros.
// 4. MEJORA v1.5: Automatización de estado inicial 'Pendiente' al crear.

// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'auth.php';
include 'conexion.php';

// --- INICIALIZACIÓN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

// --- PROCESAR CAMBIO DE ESTADO (POST + CSRF) ---
$mensaje = "";
$toast_type = ""; 
$toast_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_estado'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $toast_type = "error";
        $toast_msg = "Error de seguridad: Token inválido.";
    } else {
        $oid = $conn->real_escape_string($_POST['oid']);
        $accion = $_POST['accion_estado'];
        
        $nuevo_estado = '';
        $icono_msg = '';
        
        switch ($accion) {
            case 'finalizar': $nuevo_estado = 'Finalizada'; $icono_msg = '✅'; break;
            case 'cancelar':  $nuevo_estado = 'Cancelada';  $icono_msg = '🚫'; break;
            case 'pausar':    $nuevo_estado = 'Pausada';    $icono_msg = '⏸️'; break;
            case 'producir':  $nuevo_estado = 'En Produccion'; $icono_msg = '⚙️'; break;
            case 'pendient':  $nuevo_estado = 'Pendiente';  $icono_msg = '⏳'; break;
        }

        if (!empty($nuevo_estado)) {
            $conn->query("UPDATE produccion_master SET Estado = '$nuevo_estado' WHERE NumeroOrden = '$oid'");
            error_log("Orden #$oid cambiada a $nuevo_estado por usuario ID " . ($_SESSION['id'] ?? 'Unknown'));
            $toast_type = "success";
            $toast_msg = "$icono_msg Orden #$oid marcada como $nuevo_estado.";
        }
    }
}

// --- 0. PROCESAR DUPLICACIÓN ---
$duplicar_data = null;
$duplicar_colores = [];
if (isset($_GET['duplicar'])) {
    $id_dup = $conn->real_escape_string($_GET['duplicar']);
    $res_dup = $conn->query("SELECT * FROM produccion_master WHERE NumeroOrden = '$id_dup'");
    if ($res_dup && $res_dup->num_rows > 0) {
        $duplicar_data = $res_dup->fetch_assoc();
        $res_dup_col = $conn->query("SELECT * FROM produccion_mezclas WHERE NumeroOrden = '$id_dup'");
        while ($dc = $res_dup_col->fetch_assoc()) {
            $duplicar_colores[] = $dc;
        }
    }
}

// --- 1. CARGA DE DATOS AUXILIARES ---
$lista_colores = [];
$res_col = $conn->query("SELECT Color FROM colores ORDER BY Color ASC");
if($res_col) { while($c = $res_col->fetch_assoc()) $lista_colores[] = $c['Color']; }

$lista_maquinas = [];
$check_maq_table = $conn->query("SHOW TABLES LIKE 'maquinas'");
if ($check_maq_table && $check_maq_table->num_rows > 0) {
    $res_maq = $conn->query("SELECT * FROM maquinas ORDER BY Nombre ASC");
    if($res_maq) { 
        while($m = $res_maq->fetch_assoc()) {
            $lista_maquinas[] = isset($m['Nombre']) ? $m['Nombre'] : (isset($m['Maquina']) ? $m['Maquina'] : 'Maquina-'.$m['id']);
        }
    }
}

$lista_productos = [];
$cols_prod = "Referencia, Nombre, Imagen, Peso"; 
$check_ciclo = $conn->query("SHOW COLUMNS FROM productos LIKE 'Ciclo'");
if($check_ciclo && $check_ciclo->num_rows > 0) $cols_prod .= ", Ciclo";
$check_grupo = $conn->query("SHOW COLUMNS FROM productos LIKE 'Grupo'");
if($check_grupo && $check_grupo->num_rows > 0) $cols_prod .= ", Grupo";
$check_emp = $conn->query("SHOW COLUMNS FROM productos LIKE 'Empaque'");
if($check_emp && $check_emp->num_rows > 0) $cols_prod .= ", Empaque";
$check_bol = $conn->query("SHOW COLUMNS FROM productos LIKE 'Bolsa'");
if($check_bol && $check_bol->num_rows > 0) $cols_prod .= ", Bolsa";

$sql_prod = "SELECT $cols_prod FROM productos ORDER BY Nombre ASC";
$res_prod = $conn->query($sql_prod);
$base_url_img = "uploads/productos/"; 

if($res_prod) { 
    while($p = $res_prod->fetch_assoc()) {
        $archivo_img = $p['Imagen'];
        $img = (!empty($archivo_img) && $archivo_img != 'sin_imagen.png') ? $base_url_img . $archivo_img : 'assets/img/no-photo.png';
        
        $vel_hora = 0;
        if (isset($p['Ciclo'])) {
            $ciclo_seg = intval($p['Ciclo']);
            $vel_hora = ($ciclo_seg > 0) ? round(3600 / $ciclo_seg) : 0;
        }
        $peso_u = isset($p['Peso']) ? floatval($p['Peso']) : 0;
        $grupo = isset($p['Grupo']) ? $p['Grupo'] : '-';
        $empaque = isset($p['Empaque']) ? $p['Empaque'] : '-';
        $bolsa = isset($p['Bolsa']) ? $p['Bolsa'] : '-';
        $ciclo_val = isset($p['Ciclo']) ? $p['Ciclo'] : '-';

        $lista_productos[] = [
            'ref' => $p['Referencia'],
            'desc' => $p['Nombre'],
            'img' => $img,
            'vel' => $vel_hora,
            'peso' => $peso_u,
            'grupo' => $grupo,
            'empaque' => $empaque,
            'bolsa' => $bolsa,
            'ciclo' => $ciclo_val,
            'search_text' => strtolower($p['Referencia'] . ' ' . $p['Nombre']) 
        ];
    }
}

$ordenes_existentes = [];
$sql_exist = "SELECT NumeroOrden FROM produccion_master";
$res_exist = $conn->query($sql_exist);
if($res_exist) { while($o = $res_exist->fetch_assoc()) $ordenes_existentes[] = $o['NumeroOrden']; }

// --- 2. PROCESAR CREACIÓN ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['crear_orden'])) {
    $num_orden = $conn->real_escape_string($_POST['num_orden']);
    $producto = $conn->real_escape_string($_POST['producto_nombre']);
    $referencia = $conn->real_escape_string($_POST['referencia']); 
    $fecha = $_POST['fecha'];
    $maquina = isset($_POST['maquina']) ? $conn->real_escape_string($_POST['maquina']) : '';
    
    // MEJORA v1.5: Estado automático 'Pendiente' al crear
    $estado = 'Pendiente'; 
    
    $prioridad = isset($_POST['prioridad']) ? 1 : 0;
    $vel_hora = isset($_POST['velocidad_hora']) ? intval($_POST['velocidad_hora']) : 0;
    $peso_unidad = isset($_POST['peso_unidad']) ? floatval($_POST['peso_unidad']) : 0;
    $material_estimado = isset($_POST['material_estimado_input']) ? floatval($_POST['material_estimado_input']) : 0;

    if(empty($referencia)) $referencia = "REF-MANUAL"; 

    $meta_total = 0;
    
    if(!empty($num_orden) && !empty($producto) && !empty($maquina)) {
        
        $campos_extra = ", CiclosHora, Maquina";
        $valores_extra = ", $vel_hora, '$maquina'";
        
        $check_peso = $conn->query("SHOW COLUMNS FROM produccion_master LIKE 'PesoUnidad'");
        if ($check_peso && $check_peso->num_rows > 0) {
            $campos_extra .= ", PesoUnidad";
            $valores_extra .= ", $peso_unidad";
        }
        
        $check_mat = $conn->query("SHOW COLUMNS FROM produccion_master LIKE 'MaterialEstimado'");
        if ($check_mat && $check_mat->num_rows > 0) {
            $campos_extra .= ", MaterialEstimado";
            $valores_extra .= ", $material_estimado";
        }

        $sql_insert = "INSERT IGNORE INTO produccion_master (NumeroOrden, Producto, Referencia, Cantidad, Fecha, Estado, Prioridad $campos_extra) 
                       VALUES ('$num_orden', '$producto', '$referencia', 0, '$fecha', '$estado', $prioridad $valores_extra)";
        
        if ($conn->query($sql_insert)) {
             if (isset($_POST['color_nombre']) && is_array($_POST['color_nombre'])) {
                $colores = $_POST['color_nombre'];
                $cantidades = $_POST['color_cantidad'];
                $stmt_mezcla = $conn->prepare("INSERT INTO produccion_mezclas (NumeroOrden, Color, Cantidad, Peso) VALUES (?, ?, ?, 0)");

                for ($i = 0; $i < count($colores); $i++) {
                    $nom_col = trim($colores[$i]);
                    $cant_col = intval($cantidades[$i]);
                    if (!empty($nom_col) && $cant_col > 0) {
                        $stmt_mezcla->bind_param("ssi", $num_orden, $nom_col, $cant_col);
                        $stmt_mezcla->execute();
                        $meta_total += $cant_col;
                    }
                }
                $stmt_mezcla->close();
            }
            $conn->query("UPDATE produccion_master SET Cantidad = $meta_total WHERE NumeroOrden = '$num_orden'");
            $toast_type = "success";
            $toast_msg = "¡Orden #$num_orden creada correctamente! Estado: Pendiente.";
            $ordenes_existentes[] = $num_orden;
            
            $duplicar_data = null;
            $duplicar_colores = [];
        } else {
            $toast_type = "error";
            $toast_msg = "Error SQL: " . $conn->error;
        }
    } elseif (empty($maquina)) {
        $toast_type = "error";
        $toast_msg = "Debes seleccionar una Máquina.";
    }
}

// --- 3. LISTADO, FILTROS Y PAGINACIÓN ---
$filtro_busqueda = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$filtro_tab_estado = isset($_GET['tab']) ? $_GET['tab'] : 'activos'; 

$where_sql = " WHERE 1=1 ";
if (!empty($filtro_busqueda)) {
    $where_sql .= " AND (M.NumeroOrden LIKE '%$filtro_busqueda%' OR M.Producto LIKE '%$filtro_busqueda%' OR M.Referencia LIKE '%$filtro_busqueda%') ";
}
if ($filtro_tab_estado == 'activos') {
    $where_sql .= " AND (M.Estado = 'En Produccion' OR M.Estado = 'Pausada') ";
} elseif ($filtro_tab_estado == 'pendientes') {
    $where_sql .= " AND M.Estado = 'Pendiente' ";
} elseif ($filtro_tab_estado == 'finalizadas') {
    $where_sql .= " AND (M.Estado = 'Finalizada' OR M.Estado = 'Cancelada') ";
} 

$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$sql_count = "SELECT COUNT(*) as total FROM produccion_master M $where_sql";
$res_count = $conn->query($sql_count);
$total_registros = $res_count ? $res_count->fetch_assoc()['total'] : 0;
$total_paginas = ceil($total_registros / $registros_por_pagina);

$check_estado = $conn->query("SHOW COLUMNS FROM produccion_master LIKE 'Estado'");
$usar_nuevos_campos = ($check_estado && $check_estado->num_rows > 0);
$check_maq_col = $conn->query("SHOW COLUMNS FROM produccion_master LIKE 'Maquina'");
$usar_maquina = ($check_maq_col && $check_maq_col->num_rows > 0);
$campo_maquina = $usar_maquina ? ", M.Maquina" : ", '' as Maquina";

if ($usar_nuevos_campos) {
    $sql_lista = "SELECT M.NumeroOrden, M.Producto, M.Referencia, M.Cantidad as Meta, M.Estado, M.Prioridad $campo_maquina,
                  (SELECT COALESCE(SUM(Buenas), 0) FROM produccion_turnos WHERE NumeroOrden = M.NumeroOrden) as Avance
                  FROM produccion_master M 
                  $where_sql
                  ORDER BY M.Prioridad DESC, M.NumeroOrden DESC LIMIT $offset, $registros_por_pagina";
} else {
    $sql_lista = "SELECT M.NumeroOrden, M.Producto, M.Referencia, M.Cantidad as Meta, 
                  'Pendiente' as Estado, 0 as Prioridad, '' as Maquina,
                  (SELECT COALESCE(SUM(Buenas), 0) FROM produccion_turnos WHERE NumeroOrden = M.NumeroOrden) as Avance
                  FROM produccion_master M 
                  $where_sql
                  ORDER BY M.NumeroOrden DESC LIMIT $offset, $registros_por_pagina";
}
$res_lista = $conn->query($sql_lista);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Órdenes - INARPLAS Cloud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f6f9; margin: 0; }
        .header-bar { background-color: #0056b3; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.2rem; font-weight: bold; }
        .container { padding: 25px; max-width: 1400px; margin: 0 auto; }
        .grid-layout { display: grid; grid-template-columns: 1fr 1.6fr; gap: 25px; }
        @media(max-width: 1000px) { .grid-layout { grid-template-columns: 1fr; } }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; border-top: 4px solid #0056b3; }
        .card-header { padding: 15px 20px; background: #fff; border-bottom: 1px solid #eee; font-weight: bold; font-size: 1.1em; color: #333; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap:10px; }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 15px; position: relative; }
        label { display: block; font-size: 0.85em; font-weight: 600; color: #555; margin-bottom: 5px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95em; box-sizing: border-box; }
        input:focus, select:focus { border-color: #0056b3; outline: none; }
        input[readonly] { background-color: #e9ecef; cursor: not-allowed; font-weight: bold; color: #0056b3; }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn-green { background: #28a745; color: white; border: none; padding: 12px; width: 100%; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 10px; transition: 0.3s; }
        .btn-green:hover { background: #218838; }
        .tabla-colores { width: 100%; border-collapse: collapse; font-size: 0.9em; margin-top: 5px; }
        .tabla-colores th { text-align: left; padding: 5px; color: #777; font-size: 0.85em; }
        .tabla-colores td { padding: 5px 2px; }
        .btn-add-mini { background: #0056b3; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.85em; margin-top: 5px; }
        .btn-del-mini { background: #dc3545; color: white; border: none; width: 30px; height: 38px; border-radius: 4px; cursor: pointer; }
        .table-container { overflow-x: auto; }
        .main-table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
        .main-table th { background: #0056b3; color: white; padding: 10px; text-align: left; }
        .main-table td { padding: 8px 10px; border-bottom: 1px solid #eee; color: #444; vertical-align: middle; }
        .main-table tr:hover { background-color: #f8f9fa; }
        .actions-cell { display: flex; gap: 5px; justify-content: center; align-items:center; }
        .btn-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; text-decoration: none; color: white; font-size: 0.9em; transition: 0.2s; border:none; cursor:pointer;}
        .btn-edit { background: #ffc107; color: #333; }
        .btn-dup { background: #17a2b8; }
        .btn-print { background: #6c757d; }
        .btn-finish { background: #28a745; }
        .btn-cancel { background: #343a40; }
        .btn-pause { background: #fd7e14; }
        .btn-play { background: #0dcaf0; }
        .btn-icon:hover { opacity: 0.8; }
        .badge-estado { padding: 3px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold; text-transform: uppercase; }
        .est-pendiente { background: #ffeeba; color: #856404; }
        .est-produccion { background: #d4edda; color: #155724; }
        .est-pausada { background: #f8d7da; color: #721c24; }
        .est-finalizada { background: #d6d8d9; color: #383d41; }
        .est-cancelada { background: #343a40; color: #fff; }
        .urgente-flag { color: #dc3545; font-size: 1.1em; margin-right: 5px; }
        .search-box { display: flex; gap: 5px; align-items: center; }
        .search-box input { padding: 6px 10px; font-size: 0.9em; border: 1px solid #ccc; width: 150px; }
        #resultados_busqueda { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; z-index: 1000; display: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-height: 250px; overflow-y: auto; }
        .search-item { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .search-item:hover { background: #f0f7ff; }
        .search-item img { width: 35px; height: 35px; object-fit: cover; border-radius: 4px; }
        .producto-seleccionado { display: none; margin-top: 5px; padding: 8px; background: #e3f2fd; border-radius: 4px; color: #0d47a1; font-size: 0.9em; gap: 10px; align-items: center; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9em; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        #info_producto_detalle { display: none; margin-top: 15px; border: 1px solid #b8daff; background-color: #f1f8ff; border-radius: 6px; padding: 10px; animation: fadeIn 0.5s; }
        .ficha-grid { display: flex; gap: 15px; align-items: center; }
        .ficha-img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; background: white; }
        .ficha-datos { flex: 1; font-size: 0.85em; color: #444; }
        .ficha-datos strong { color: #0056b3; }
        .ficha-fila { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-top: 3px; }
        .box-material { margin-top: 10px; background: #fff3cd; border: 1px solid #ffeeba; padding: 8px; border-radius: 4px; font-weight: bold; color: #856404; text-align: center; font-size: 0.95em; }
        .tabs-header { display:flex; gap:10px; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px; }
        .tab-link { text-decoration:none; padding:8px 15px; border-radius:20px; font-size:0.9em; color:#555; background:#f8f9fa; border:1px solid #ddd; transition:0.2s; }
        .tab-link:hover { background:#e2e6ea; }
        .tab-link.active { background:#0056b3; color:white; border-color:#0056b3; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <script>
        const productosDB = <?php echo json_encode($lista_productos); ?>;
        const ordenesExistentes = <?php echo json_encode($ordenes_existentes); ?>;
        document.addEventListener('DOMContentLoaded', function() {
            const inputOrden = document.querySelector('input[name="num_orden"]');
            const msgValidacion = document.getElementById('msg_orden_error');
            if(inputOrden){
                inputOrden.addEventListener('input', function() {
                    const val = this.value.trim();
                    msgValidacion.style.display = 'none';
                    inputOrden.style.borderColor = '#ddd';
                    inputOrden.style.backgroundColor = '#fff';
                    if (val.length > 0) {
                        if (ordenesExistentes.includes(val)) {
                            inputOrden.style.borderColor = '#dc3545';
                            inputOrden.style.backgroundColor = '#fff8f8';
                            msgValidacion.innerHTML = '<span style="color:#dc3545"><i class="fa-solid fa-triangle-exclamation"></i> Ya existe</span>';
                            msgValidacion.style.display = 'block';
                        } else {
                            inputOrden.style.borderColor = '#28a745';
                            inputOrden.style.backgroundColor = '#f8fff9';
                        }
                    }
                });
            }
            const inputProd = document.getElementById('input_producto');
            const listaRes = document.getElementById('resultados_busqueda');
            const hiddenRef = document.getElementById('ref_producto');
            const hiddenVel = document.getElementById('vel_hora_hidden');
            const hiddenPeso = document.getElementById('peso_unidad_hidden');
            const previewSel = document.getElementById('preview_seleccion');
            if(inputProd){
                inputProd.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    if (query.length < 3) { listaRes.style.display = 'none'; return; }
                    const resultados = productosDB.filter(p => p.search_text.includes(query));
                    listaRes.innerHTML = '';
                    if (resultados.length > 0) {
                        resultados.forEach(p => {
                            const div = document.createElement('div');
                            div.className = 'search-item';
                            div.innerHTML = `<img src="${p.img}"><div><b>${p.ref}</b><br><small>${p.desc}</small></div>`;
                            div.onclick = function() { seleccionarProducto(p); };
                            listaRes.appendChild(div);
                        });
                        listaRes.style.display = 'block';
                    } else {
                        listaRes.innerHTML = '<div style="padding:10px; color:#777;">No encontrado.</div>';
                        listaRes.style.display = 'block';
                    }
                });
                document.addEventListener('click', e => {
                    if (!inputProd.contains(e.target) && !listaRes.contains(e.target)) listaRes.style.display = 'none';
                });
            }
            function seleccionarProducto(p) {
                inputProd.value = p.desc;
                hiddenRef.value = p.ref;
                if(hiddenVel) hiddenVel.value = p.vel; 
                if(hiddenPeso) hiddenPeso.value = p.peso; 
                listaRes.style.display = 'none';
                previewSel.style.display = 'flex';
                previewSel.innerHTML = `<i class="fa-solid fa-check"></i> ${p.ref} - ${p.desc}`;
                mostrarDetallesProducto(p);
                calcularTiempos(); 
            }
        });
        function mostrarDetallesProducto(p) {
            const div = document.getElementById('info_producto_detalle');
            div.innerHTML = `<div class="ficha-grid"><img src="${p.img}" class="ficha-img"><div class="ficha-datos"><div style="border-bottom:1px solid #ddd; padding-bottom:3px; margin-bottom:3px;"><strong>${p.desc}</strong> (Ref: ${p.ref})</div><div class="ficha-fila"><span>📦 Grupo: ${p.grupo}</span><span>⚖️ Peso: <strong>${p.peso} g</strong></span><span>⏱️ Ciclo: ${p.ciclo} s</span><span>🛍️ Empaque: ${p.empaque} u</span></div></div></div><div class="box-material" id="box_material_calc"><i class="fa-solid fa-calculator"></i> Material Estimado: 0 Kg</div>`;
            div.style.display = 'block';
        }
        function calcularTotal() {
            let total = 0;
            document.querySelectorAll('.input-cant').forEach(inp => total += parseInt(inp.value) || 0);
            const metaTotal = document.getElementById('meta_total');
            if(metaTotal) metaTotal.value = total;
            calcularTiempos();
        }
        function calcularTiempos() {
            const metaInput = document.getElementById('meta_total');
            const velInput = document.getElementById('vel_hora_hidden');
            const pesoInput = document.getElementById('peso_unidad_hidden');
            const displayTiempo = document.getElementById('tiempo_estimado');
            const displayMaterial = document.getElementById('box_material_calc');
            const inputMaterial = document.getElementById('material_estimado_input');
            const tipoHorario = document.getElementById('tipo_horario').value;
            const total = parseInt(metaInput.value) || 0;
            const vel = parseInt(velInput.value) || 0;
            const peso = parseFloat(pesoInput.value) || 0;
            if (total > 0 && vel > 0) {
                const horasNecesarias = total / vel;
                const minutosRestantes = Math.ceil(horasNecesarias * 60);
                let fechaCursor = new Date(); 
                let minutosContador = minutosRestantes;
                while(minutosContador > 0) {
                    fechaCursor.setMinutes(fechaCursor.getMinutes() + 1);
                    const diaSemana = fechaCursor.getDay(); 
                    const hora = fechaCursor.getHours();
                    let esLaborable = false;
                    if (tipoHorario === '24h') {
                        if (diaSemana === 1 && hora >= 7) esLaborable = true;
                        else if (diaSemana >= 2 && diaSemana <= 5) esLaborable = true;
                        else if (diaSemana === 6 && hora < 18) esLaborable = true;
                    } else {
                        if (diaSemana === 1 && hora >= 7 && hora < 20) esLaborable = true;
                        else if (diaSemana >= 2 && diaSemana <= 5 && hora >= 6 && hora < 20) esLaborable = true;
                        else if (diaSemana === 6 && hora >= 6 && hora < 18) esLaborable = true;
                    }
                    if(esLaborable) { minutosContador--; }
                    if (fechaCursor.getFullYear() > new Date().getFullYear() + 1) break; 
                }
                const diaFin = fechaCursor.toLocaleDateString('es-ES', { weekday: 'short', day:'numeric', hour: '2-digit', minute:'2-digit' });
                displayTiempo.value = `${horasNecesarias.toFixed(1)} horas trabajo (Termina: ${diaFin})`;
            } else {
                displayTiempo.value = (vel === 0) ? "Falta configurar Ciclos/Hora" : "0 horas";
            }
            if (total > 0 && peso > 0) {
                const materialKg = (total * peso) / 1000;
                const matFormat = materialKg.toLocaleString('es-ES', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                if(displayMaterial) displayMaterial.innerHTML = `<i class="fa-solid fa-calculator"></i> Material Estimado: <strong>${matFormat} Kg</strong>`;
                if(inputMaterial) inputMaterial.value = materialKg.toFixed(2);
            } else {
                if(displayMaterial) displayMaterial.innerHTML = `<i class="fa-solid fa-calculator"></i> Material Estimado: 0 Kg`;
                if(inputMaterial) inputMaterial.value = 0;
            }
        }
        function agregarFilaColor() {
            const tbody = document.getElementById('body_colores');
            const row = document.createElement('tr');
            row.innerHTML = `<td><input type="text" name="color_nombre[]" list="lista_colores_dl" required></td><td><input type="number" name="color_cantidad[]" class="input-cant" required oninput="calcularTotal()"></td><td style="text-align:center;"><button type="button" class="btn-del-mini" onclick="eliminarFila(this)"><i class="fa-solid fa-trash"></i></button></td>`;
            tbody.appendChild(row);
        }
        function eliminarFila(btn) { btn.closest('tr').remove(); calcularTotal(); }
    </script>
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
    <div class="grid-layout">
        
        <!-- FORMULARIO -->
        <div class="card">
            <div class="card-header">
                <?php if($duplicar_data): ?>
                    <span style="color:#17a2b8"><i class="fa-solid fa-copy"></i> Duplicando Orden #<?php echo $duplicar_data['NumeroOrden']; ?></span>
                <?php else: ?>
                    <span><i class="fa-solid fa-plus"></i> Nueva Orden</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php echo $mensaje; ?>
                
                <form method="POST" action="ordenes.php" autocomplete="off">
                    <div class="row-2">
                        <div class="form-group">
                            <label>No. Orden:</label>
                            <input type="text" name="num_orden" placeholder="Ej: 6480" required autofocus>
                            <div id="msg_orden_error" style="font-size:0.8em; margin-top:2px; display:none;"></div>
                        </div>
                        <div class="form-group">
                            <label>Fecha:</label>
                            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row-2" style="background:#f1f8ff; padding:10px; border-radius:5px; margin-bottom:15px; border:1px solid #cce5ff;">
                        <div class="form-group" style="margin-bottom:0;">
                             <label style="color:#0056b3;"><i class="fa-solid fa-gears"></i> Máquina (Obligatorio):</label>
                             <select name="maquina" required>
                                 <option value="">-- Seleccionar --</option>
                                 <?php 
                                    $maq_sel = ($duplicar_data && isset($duplicar_data['Maquina'])) ? $duplicar_data['Maquina'] : '';
                                    foreach($lista_maquinas as $m): 
                                        $selected = ($maq_sel == $m) ? 'selected' : '';
                                 ?>
                                     <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($m); ?></option>
                                 <?php endforeach; ?>
                             </select>
                        </div>

                        <!-- MODIFICACIÓN v1.5: ELIMINADO SELECTOR DE ESTADO. SE FUERZA 'Pendiente' -->
                        <div class="form-group" style="margin-bottom:0; display:flex; align-items:flex-end;">
                             <label style="cursor:pointer; display:flex; align-items:center; font-size:0.9em; margin-bottom:12px;">
                                <input type="checkbox" name="prioridad" value="1" style="width:auto; margin-right:5px;"> 
                                <span style="color:#dc3545; font-weight:bold;"><i class="fa-solid fa-fire"></i> ¡Urgente!</span>
                             </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Producto:</label>
                        <input type="text" name="producto_nombre" id="input_producto" 
                               value="<?php echo $duplicar_data ? $duplicar_data['Producto'] : ''; ?>" 
                               placeholder="Buscar..." required>
                        <div id="resultados_busqueda"></div>
                        <div id="preview_seleccion" class="producto-seleccionado" style="<?php echo $duplicar_data ? 'display:flex' : ''; ?>">
                            <?php if($duplicar_data) echo "<i class='fa-solid fa-check'></i> Copiado: " . $duplicar_data['Producto']; ?>
                        </div>
                        <div id="info_producto_detalle"></div>
                        <input type="hidden" name="referencia" id="ref_producto" value="<?php echo $duplicar_data ? $duplicar_data['Referencia'] : ''; ?>"> 
                        <input type="hidden" name="velocidad_hora" id="vel_hora_hidden" 
                               value="<?php echo ($duplicar_data && isset($duplicar_data['CiclosHora'])) ? $duplicar_data['CiclosHora'] : '0'; ?>">
                        <input type="hidden" name="peso_unidad" id="peso_unidad_hidden" 
                               value="<?php echo ($duplicar_data && isset($duplicar_data['PesoUnidad'])) ? $duplicar_data['PesoUnidad'] : '0'; ?>">
                        <input type="hidden" name="material_estimado_input" id="material_estimado_input" value="0">
                    </div>

                    <div class="form-group" style="background:#f8f9fa; padding:10px; border-radius:5px; border:1px solid #eee;">
                        <label style="color:#0056b3; margin-bottom:10px;"><i class="fa-solid fa-layer-group"></i> Colores:</label>
                        <datalist id="lista_colores_dl"><?php foreach($lista_colores as $col) echo "<option value='$col'>"; ?></datalist>
                        <table class="tabla-colores">
                            <thead><tr><th width="55%">Color</th><th width="35%">Cant.</th><th width="10%"></th></tr></thead>
                            <tbody id="body_colores">
                                <?php if($duplicar_data && count($duplicar_colores) > 0): 
                                    foreach($duplicar_colores as $dc): ?>
                                    <tr>
                                        <td><input type="text" name="color_nombre[]" list="lista_colores_dl" value="<?php echo $dc['Color']; ?>" required></td>
                                        <td><input type="number" name="color_cantidad[]" class="input-cant" value="<?php echo $dc['Cantidad']; ?>" required oninput="calcularTotal()"></td>
                                        <td style="text-align:center;"><button type="button" class="btn-del-mini" onclick="eliminarFila(this)"><i class="fa-solid fa-trash"></i></button></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td><input type="text" name="color_nombre[]" list="lista_colores_dl" required></td>
                                        <td><input type="number" name="color_cantidad[]" class="input-cant" required oninput="calcularTotal()"></td>
                                        <td style="text-align:center;"><button type="button" class="btn-del-mini" onclick="eliminarFila(this)"><i class="fa-solid fa-trash"></i></button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn-add-mini" onclick="agregarFilaColor()">+ Color</button>
                    </div>

                    <div class="row-2">
                        <div class="form-group">
                            <label>Meta Total:</label>
                            <input type="number" name="meta" id="meta_total" value="<?php echo $duplicar_data ? $duplicar_data['Cantidad'] : '0'; ?>" readonly style="font-weight:bold;">
                        </div>
                        <div class="form-group">
                            <label style="display:flex; justify-content:space-between;">
                                <span>Tiempo Estimado:</span>
                                <select id="tipo_horario" onchange="calcularTiempos()" style="width:auto; padding:2px; font-size:0.85em; margin-top:-2px;">
                                    <option value="normal">Turno Normal</option>
                                    <option value="24h">Continuo 24h</option>
                                </select>
                            </label>
                            <input type="text" id="tiempo_estimado" value="Calculando..." readonly style="color:#555; font-size:0.9em;">
                        </div>
                    </div>

                    <button type="submit" name="crear_orden" class="btn-green">Guardar Orden</button>
                </form>
            </div>
        </div>

        <!-- LISTADO -->
        <div class="card">
            <div class="card-header">
                <span><i class="fa-solid fa-list-check"></i> Producción</span>
                <form method="GET" action="ordenes.php" class="search-box">
                    <input type="text" name="q" placeholder="Buscar..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                    <button type="submit"><i class="fa-solid fa-search"></i></button>
                </form>
            </div>
            
            <div class="card-body" style="padding:10px 20px 0 20px;">
                <div class="tabs-header">
                    <a href="ordenes.php?tab=activos" class="tab-link <?php echo ($filtro_tab_estado=='activos')?'active':''; ?>">Activos</a>
                    <a href="ordenes.php?tab=pendientes" class="tab-link <?php echo ($filtro_tab_estado=='pendientes')?'active':''; ?>">Pendientes</a>
                    <a href="ordenes.php?tab=finalizadas" class="tab-link <?php echo ($filtro_tab_estado=='finalizadas')?'active':''; ?>">Finalizadas</a>
                    <a href="ordenes.php?tab=todos" class="tab-link <?php echo ($filtro_tab_estado=='todos')?'active':''; ?>">Todas</a>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="main-table">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>O.P</th>
                                <th>Producto</th>
                                <th>Meta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($res_lista && $res_lista->num_rows > 0): 
                                while($row = $res_lista->fetch_assoc()): 
                                    $est = isset($row['Estado']) ? $row['Estado'] : 'Pendiente';
                                    $clase_estado = 'est-pendiente';
                                    
                                    if ($est == 'En Produccion') $clase_estado = 'est-produccion';
                                    elseif ($est == 'Pausada') $clase_estado = 'est-pausada';
                                    elseif ($est == 'Finalizada') $clase_estado = 'est-finalizada';
                                    elseif ($est == 'Cancelada') $clase_estado = 'est-cancelada';
                                    
                                    $info_maq = "";
                                    if(isset($row['Maquina']) && !empty($row['Maquina'])) {
                                        $info_maq = "<div style='font-size:0.8em; color:#0056b3;'><i class='fa-solid fa-gear'></i> ".$row['Maquina']."</div>";
                                    }
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge-estado <?php echo $clase_estado; ?>"><?php echo $est; ?></span>
                                    </td>
                                    <td>
                                        <?php if(isset($row['Prioridad']) && $row['Prioridad'] == 1) echo '<i class="fa-solid fa-fire urgente-flag" title="Urgente"></i>'; ?>
                                        <b>#<?php echo $row['NumeroOrden']; ?></b>
                                        <?php echo $info_maq; ?>
                                    </td>
                                    <td><?php echo $row['Producto']; ?></td>
                                    <td><?php echo number_format((float)$row['Meta']); ?></td>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="imprimir_orden.php?id=<?php echo $row['NumeroOrden']; ?>" target="_blank" class="btn-icon btn-print" title="Hoja de Ruta"><i class="fa-solid fa-print"></i></a>
                                        <a href="ordenes.php?duplicar=<?php echo $row['NumeroOrden']; ?>" class="btn-icon btn-dup" title="Duplicar"><i class="fa-solid fa-copy"></i></a>
                                        <a href="editar_orden.php?orden=<?php echo $row['NumeroOrden']; ?>" class="btn-icon btn-edit" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                        
                                        <?php if ($est != 'En Produccion' && $est != 'Finalizada' && $est != 'Cancelada'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Iniciar producción?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="oid" value="<?php echo $row['NumeroOrden']; ?>">
                                                <input type="hidden" name="accion_estado" value="producir">
                                                <button type="submit" class="btn-icon btn-play" title="Iniciar"><i class="fa-solid fa-play"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($est == 'En Produccion'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Pausar esta orden?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="oid" value="<?php echo $row['NumeroOrden']; ?>">
                                                <input type="hidden" name="accion_estado" value="pausar">
                                                <button type="submit" class="btn-icon btn-pause" title="Pausar"><i class="fa-solid fa-pause"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($est != 'Finalizada' && $est != 'Cancelada'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Finalizar orden?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="oid" value="<?php echo $row['NumeroOrden']; ?>">
                                                <input type="hidden" name="accion_estado" value="finalizar">
                                                <button type="submit" class="btn-icon btn-finish" title="Finalizar"><i class="fa-solid fa-check"></i></button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿ANULAR orden?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="oid" value="<?php echo $row['NumeroOrden']; ?>">
                                                <input type="hidden" name="accion_estado" value="cancelar">
                                                <button type="submit" class="btn-icon btn-cancel" title="Cancelar"><i class="fa-solid fa-ban"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($est == 'Finalizada' || $est == 'Cancelada'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Reactivar orden?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="oid" value="<?php echo $row['NumeroOrden']; ?>">
                                                <input type="hidden" name="accion_estado" value="pendient">
                                                <button type="submit" class="btn-icon btn-dup" title="Reactivar"><i class="fa-solid fa-rotate-left"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:20px;">Sin resultados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if($total_paginas > 1): ?>
                <div class="card-body" style="padding-top:0;">
                    <div class="pagination">
                        <?php for($i=1; $i<=$total_paginas; $i++): ?>
                            <a href="ordenes.php?pag=<?php echo $i; ?>&q=<?php echo $filtro_busqueda; ?>&tab=<?php echo $filtro_tab_estado; ?>" class="<?php echo ($i == $pagina_actual) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
    <?php if($toast_msg != ""): ?>
        Toastify({
            text: "<?php echo $toast_msg; ?>",
            duration: 3000,
            close: true,
            gravity: "top", 
            position: "right", 
            style: {
                background: "<?php echo ($toast_type == 'success') ? '#28a745' : '#dc3545'; ?>",
            }
        }).showToast();
    <?php endif; ?>
</script>

</body>
</html>
<?php 
// /public_html/sistema/usuarios.php - Versión: v3.1 (GESTIÓN DE PERMISOS / ACL)
// MEJORAS:
// 1. Nueva tabla 'permisos_rol' para definir accesos a botones del Dashboard.
// 2. Interfaz para asignar módulos (checkboxes) a cada Rol.
// 3. Optimizaciones previas mantenidas (Live Search, Avatares, Seguridad).

// --- LISTA MAESTRA DE MÓDULOS DEL SISTEMA (Botones del Dashboard) ---
// Agrega aquí los identificadores de los botones que quieras controlar
$modulos_sistema = [
    'usuarios'      => 'Gestión de Usuarios',
    'maquinas'      => 'Control de Máquinas',
    'productos'     => 'Catálogo de Productos',
    'ordenes'       => 'Órdenes de Producción',
    'turnos'        => 'Gestión de Turnos',
    'reportes'      => 'Reportes Generales',
    'analisis'      => 'Análisis de Producto',
    'historial'     => 'Historial',
    'energia'       => 'Control de Energía',
    'agua'          => 'Control de Agua'
];

// --- ACTIVAR REPORTE DE ERRORES ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

// Verificar archivos requeridos
if (!file_exists('auth.php')) die("Error Fatal: No se encuentra auth.php");
if (!file_exists('conexion.php')) die("Error Fatal: No se encuentra conexion.php");

include 'auth.php'; 
require_once 'conexion.php'; 

// --- 0. CONFIGURACIÓN DE CARPETAS ---
$directorio_avatares = "img/users/";
if (!file_exists($directorio_avatares)) {
    if (!mkdir($directorio_avatares, 0777, true)) {}
}

// --- 0.1 MICRO-CONTROLADOR AJAX (VALIDAR USUARIO) ---
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'check_user') {
    $u_check = $conn->real_escape_string(trim($_POST['username']));
    $sql_c = "SELECT id FROM usuarios WHERE usuario = '$u_check' AND activo = 1";
    $res_c = $conn->query($sql_c);
    if (!$res_c) { echo json_encode(['error' => $conn->error]); exit; }
    echo json_encode(['exists' => ($res_c && $res_c->num_rows > 0)]);
    exit;
}

// --- 0.2 OBTENER PERMISOS VIA AJAX (Para el Modal) ---
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'get_permisos') {
    $rol_nombre = $conn->real_escape_string($_POST['rol']);
    $sql = "SELECT modulo FROM permisos_rol WHERE rol_nombre = '$rol_nombre'";
    $res = $conn->query($sql);
    $permisos = [];
    if ($res) {
        while($r = $res->fetch_assoc()) { $permisos[] = $r['modulo']; }
    }
    echo json_encode($permisos);
    exit;
}

// --- 0.3 BÚSQUEDA EN VIVO ---
if (isset($_GET['ajax_search'])) {
    $busqueda = $conn->real_escape_string(trim($_GET['q']));
    $where_sql = "WHERE activo = 1";
    if (!empty($busqueda)) {
        $where_sql .= " AND (nombre LIKE '%$busqueda%' OR usuario LIKE '%$busqueda%')";
    }
    
    $sql = "SELECT * FROM usuarios $where_sql ORDER BY id DESC LIMIT 20";
    $res = $conn->query($sql);
    
    if ($res && $res->num_rows > 0) {
        $uid_session = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0);
        while($fila = $res->fetch_assoc()) {
            $bg_rol = ($fila['rol'] == 'ADMIN') ? '#cce5ff' : '#fff3cd';
            $avatar = !empty($fila['avatar']) ? $fila['avatar'] : 'default.png';
            $ruta_avatar = $directorio_avatares . $avatar;
            $fecha_reg = isset($fila['fecha_registro']) ? date('d/m/Y', strtotime($fila['fecha_registro'])) : '-';
            
            echo '<tr>';
            echo '<td><img src="'.$ruta_avatar.'" class="avatar-img" onerror="this.src=\'https://via.placeholder.com/40?text=U\'"></td>';
            echo '<td>'.$fila['id'].'</td>';
            echo '<td>'.htmlspecialchars($fila['nombre']).'</td>';
            echo '<td>'.htmlspecialchars($fila['usuario']).'</td>';
            echo '<td><span class="role-badge" style="background:'.$bg_rol.'">'.$fila['rol'].'</span></td>';
            echo '<td>'.$fecha_reg.'</td>';
            echo '<td>';
            echo '<button class="btn-accion btn-editar" onclick="abrirModalEditar('.$fila['id'].', \''.htmlspecialchars($fila['nombre']).'\', \''.htmlspecialchars($fila['rol']).'\')" title="Editar">&#9998;</button>';
            echo '<button class="btn-accion btn-clave" onclick="abrirModalClave('.$fila['id'].', \''.htmlspecialchars($fila['usuario']).'\')" title="Clave">&#128273;</button>';
            if($fila['id'] != $uid_session) {
                echo '<button class="btn-accion btn-borrar" onclick="abrirModalBorrar('.$fila['id'].', \''.htmlspecialchars($fila['nombre']).'\')" title="Borrar">&#128465;</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
    } else {
        echo "<tr><td colspan='7' align='center'>Sin resultados para: <b>".htmlspecialchars($busqueda)."</b></td></tr>";
    }
    exit;
}

// --- AUTO-MIGRACIÓN BD ---
// 1. Logs
$conn->query("CREATE TABLE IF NOT EXISTS logs_sistema (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT, accion VARCHAR(50), detalles TEXT, fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
// 2. Usuarios
$check_col = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'activo'");
if ($check_col->num_rows == 0) $conn->query("ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) DEFAULT 1");
$check_avatar = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'avatar'");
if ($check_avatar->num_rows == 0) $conn->query("ALTER TABLE usuarios ADD COLUMN avatar VARCHAR(100) DEFAULT 'default.png'");
// 3. Roles
$conn->query("CREATE TABLE IF NOT EXISTS `roles` (id int(11) NOT NULL AUTO_INCREMENT, nombre_rol varchar(50) NOT NULL, descripcion varchar(100) DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `nombre_rol` (`nombre_rol`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci");

// 4. PERMISOS (NUEVA TABLA)
$sql_permisos = "CREATE TABLE IF NOT EXISTS `permisos_rol` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rol_nombre` varchar(50) COLLATE utf8_spanish_ci NOT NULL,
  `modulo` varchar(50) COLLATE utf8_spanish_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `rol_nombre` (`rol_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci";
$conn->query($sql_permisos);

// --- INICIALIZACIÓN ---
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
    else $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}

// Helper Auditoría
function registrar_auditoria($conn, $accion, $detalles) {
    $uid = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0);
    try {
        $stmt = $conn->prepare("INSERT INTO logs_sistema (usuario_id, accion, detalles) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $uid, $accion, $detalles);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) { }
}

$toast_type = ""; 
$toast_msg = "";

// --------------------------------------------------------
// LÓGICA DE FORMULARIOS
// --------------------------------------------------------

// A. GESTIÓN DE ROLES (CREAR, EDITAR, ELIMINAR, PERMISOS)
if (isset($_POST['accion_rol'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $toast_type = "error"; $toast_msg = "Error CSRF.";
    } else {
        $accion = $_POST['accion_rol'];
        
        // 1. CREAR ROL
        if ($accion == 'crear') {
            $nuevo_rol = strtoupper(trim($_POST['nombre_rol']));
            $desc_rol = trim($_POST['descripcion_rol']);
            if (!empty($nuevo_rol)) {
                $check = $conn->query("SELECT id FROM roles WHERE nombre_rol = '$nuevo_rol'");
                if($check && $check->num_rows > 0) {
                    $toast_type = "error"; $toast_msg = "El rol ya existe.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO roles (nombre_rol, descripcion) VALUES (?, ?)");
                    $stmt->bind_param("ss", $nuevo_rol, $desc_rol);
                    if($stmt->execute()) {
                        registrar_auditoria($conn, "CREAR_ROL", "Rol: $nuevo_rol");
                        $toast_type = "success"; $toast_msg = "Rol creado.";
                    }
                    $stmt->close();
                }
            }
        }

        // 2. GUARDAR PERMISOS (NUEVO)
        if ($accion == 'guardar_permisos') {
            $rol_target = $_POST['rol_target'];
            $permisos_seleccionados = isset($_POST['permisos']) ? $_POST['permisos'] : [];
            
            // a) Borrar permisos anteriores del rol
            $stmt_del = $conn->prepare("DELETE FROM permisos_rol WHERE rol_nombre = ?");
            $stmt_del->bind_param("s", $rol_target);
            $stmt_del->execute();
            $stmt_del->close();
            
            // b) Insertar nuevos
            if (!empty($permisos_seleccionados)) {
                $stmt_ins = $conn->prepare("INSERT INTO permisos_rol (rol_nombre, modulo) VALUES (?, ?)");
                foreach ($permisos_seleccionados as $mod) {
                    $stmt_ins->bind_param("ss", $rol_target, $mod);
                    $stmt_ins->execute();
                }
                $stmt_ins->close();
            }
            
            registrar_auditoria($conn, "PERMISOS_ROL", "Actualizó permisos para: $rol_target");
            $toast_type = "success"; $toast_msg = "Permisos actualizados para $rol_target";
        }
        
        // 3. ELIMINAR ROL
        if ($accion == 'eliminar') {
            $id_rol_del = intval($_POST['id_rol']);
            $res = $conn->query("SELECT nombre_rol FROM roles WHERE id = $id_rol_del");
            if ($row = $res->fetch_assoc()) {
                $nombre_rol = $row['nombre_rol'];
                if (in_array($nombre_rol, ['ADMIN', 'OPERARIO'])) {
                    $toast_type = "error"; $toast_msg = "No se pueden eliminar roles base.";
                } else {
                    $conn->query("DELETE FROM roles WHERE id = $id_rol_del");
                    // También borrar sus permisos
                    $conn->query("DELETE FROM permisos_rol WHERE rol_nombre = '$nombre_rol'");
                    registrar_auditoria($conn, "ELIMINAR_ROL", "Rol ID: $id_rol_del");
                    $toast_type = "success"; $toast_msg = "Rol eliminado.";
                }
            }
        }

        // 4. EDITAR ROL (Nombre/Desc)
        if ($accion == 'editar') {
            $id_rol_edit = intval($_POST['id_rol']);
            $nuevo_nombre = strtoupper(trim($_POST['nombre_rol_edit']));
            $nueva_desc = trim($_POST['desc_rol_edit']);
            
            $res = $conn->query("SELECT nombre_rol FROM roles WHERE id = $id_rol_edit");
            if ($row = $res->fetch_assoc()) {
                $nombre_anterior = $row['nombre_rol'];
                $stmt = $conn->prepare("UPDATE roles SET nombre_rol=?, descripcion=? WHERE id=?");
                $stmt->bind_param("ssi", $nuevo_nombre, $nueva_desc, $id_rol_edit);
                if($stmt->execute()) {
                    if ($nombre_anterior != $nuevo_nombre) {
                        // Actualizar usuarios y tabla de permisos
                        $conn->query("UPDATE usuarios SET rol='$nuevo_nombre' WHERE rol='$nombre_anterior'");
                        $conn->query("UPDATE permisos_rol SET rol_nombre='$nuevo_nombre' WHERE rol_nombre='$nombre_anterior'");
                    }
                    registrar_auditoria($conn, "EDITAR_ROL", "De $nombre_anterior a $nuevo_nombre");
                    $toast_type = "success"; $toast_msg = "Rol actualizado.";
                }
                $stmt->close();
            }
        }
    }
}

// B. CREAR USUARIO
if (isset($_POST['crear_usuario'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $toast_type = "error"; $toast_msg = "Error CSRF.";
    } else {
        $nombre = trim($_POST['nombre']);
        $user = trim($_POST['usuario']);
        $rol = $_POST['rol'];
        $pass = $_POST['password'];
        $pass_conf = $_POST['password_confirm'];

        if (strlen($pass) < 6) {
            $toast_type = "error"; $toast_msg = "La contraseña debe tener al menos 6 caracteres.";
        } elseif ($pass !== $pass_conf) {
            $toast_type = "error"; $toast_msg = "Las contraseñas no coinciden.";
        } else {
            $check = $conn->query("SELECT id FROM usuarios WHERE usuario = '$user' AND activo = 1");
            if($check && $check->num_rows > 0) {
                $toast_type = "error"; $toast_msg = "El usuario ya existe.";
            } else {
                $nombre_foto = 'default.png';
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                    $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                        $nombre_foto = 'user_' . md5(uniqid()) . '.' . $ext;
                        move_uploaded_file($_FILES['foto']['tmp_name'], $directorio_avatares . $nombre_foto);
                    }
                }
                $pass_hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre, usuario, password, rol, activo, avatar) VALUES (?, ?, ?, ?, 1, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssss", $nombre, $user, $pass_hash, $rol, $nombre_foto);
                    if($stmt->execute()) {
                        registrar_auditoria($conn, "CREAR_USUARIO", "Creó: $user ($rol)");
                        $toast_type = "success"; $toast_msg = "Usuario creado.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// C. ELIMINAR USUARIO
if (isset($_POST['borrar_usuario_confirm'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $toast_type = "error"; $toast_msg = "Error CSRF.";
    } else {
        $id_borrar = intval($_POST['id_borrar']);
        $my_id = isset($_SESSION['id']) ? $_SESSION['id'] : (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0);
        if ($id_borrar != $my_id) {
            $conn->query("UPDATE usuarios SET activo = 0 WHERE id=$id_borrar");
            registrar_auditoria($conn, "ELIMINAR_USUARIO", "ID: $id_borrar");
            $toast_type = "success"; $toast_msg = "Usuario eliminado.";
        } else {
            $toast_type = "error"; $toast_msg = "No puedes eliminarte a ti mismo.";
        }
    }
}

// D. CAMBIAR CONTRASEÑA
if (isset($_POST['cambiar_clave'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $toast_type = "error"; $toast_msg = "Error CSRF.";
    } else {
        $id_user = intval($_POST['id_user_pass']);
        $new_pass = $_POST['new_password'];
        $new_pass_conf = $_POST['new_password_confirm'];
        if (strlen($new_pass) < 6 || $new_pass !== $new_pass_conf) {
            $toast_type = "error"; $toast_msg = "Error en contraseña (mín 6 caracteres y deben coincidir).";
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $id_user);
            $stmt->execute();
            registrar_auditoria($conn, "CAMBIO_CLAVE", "ID: $id_user");
            $toast_type = "success"; $toast_msg = "Contraseña actualizada.";
            $stmt->close();
        }
    }
}

// E. EDITAR USUARIO
if (isset($_POST['editar_usuario'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $toast_type = "error"; $toast_msg = "Error CSRF.";
    } else {
        $id_edit = intval($_POST['id_user_edit']);
        $nombre_edit = trim($_POST['nombre_edit']);
        $rol_edit = $_POST['rol_edit'];
        
        $sql_avatar_part = "";
        $param_types = "ssi";
        $params = [$nombre_edit, $rol_edit, $id_edit];

        if (isset($_FILES['foto_edit']) && $_FILES['foto_edit']['error'] == 0) {
            $ext = pathinfo($_FILES['foto_edit']['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                $nombre_foto = 'user_' . md5(uniqid()) . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_edit']['tmp_name'], $directorio_avatares . $nombre_foto)) {
                    $sql_avatar_part = ", avatar=?";
                    $param_types = "sssi";
                    $params = [$nombre_edit, $rol_edit, $nombre_foto, $id_edit];
                }
            }
        }
        $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, rol=? $sql_avatar_part WHERE id=?");
        if ($stmt) {
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            registrar_auditoria($conn, "EDITAR_PERFIL", "ID: $id_edit");
            $toast_type = "success"; $toast_msg = "Datos actualizados.";
            $stmt->close();
        }
    }
}

// --- OBTENER ROLES Y USUARIOS ---
$roles_db = [];
$res_roles = $conn->query("SELECT * FROM roles ORDER BY nombre_rol ASC");
if ($res_roles) { while ($r = $res_roles->fetch_assoc()) { $roles_db[] = $r; } }
$lista_roles_nombres = empty($roles_db) ? ['ADMIN', 'OPERARIO'] : array_column($roles_db, 'nombre_rol');

$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pag']) ? (int)$_GET['pag'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$busqueda = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : '';
$where_sql = "WHERE activo = 1";
if (!empty($busqueda)) $where_sql .= " AND (nombre LIKE '%$busqueda%' OR usuario LIKE '%$busqueda%')";

$res_count = $conn->query("SELECT COUNT(*) as total FROM usuarios $where_sql");
$total_registros = $res_count ? $res_count->fetch_assoc()['total'] : 0;
$total_paginas = ceil($total_registros / $registros_por_pagina);

$sql = "SELECT * FROM usuarios $where_sql ORDER BY id DESC LIMIT $offset, $registros_por_pagina";
$resultado = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gesti&oacute;n de Usuarios - INARPLAS Cloud</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    
    <style>
        /* Estilos Base */
        .tabla-usuarios { width: 100%; border-collapse: collapse; background: white; margin-top:20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .tabla-usuarios th, .tabla-usuarios td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: middle; }
        .tabla-usuarios th { background: #0056b3; color: white; }
        .tabla-usuarios tr:hover { background-color: #f1f1f1; }
        
        .btn-accion { text-decoration: none; padding: 6px 10px; border-radius: 4px; font-weight: bold; margin-right: 3px; display: inline-block; border:none; cursor:pointer; font-size:0.9rem;}
        .btn-borrar { color: white; background: #dc3545; }
        .btn-clave { color: black; background: #ffc107; }
        .btn-editar { color: white; background: #17a2b8; } 
        .btn-permisos { color: white; background: #6f42c1; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; }
        .modal-contenido { background: white; margin: 2% auto; padding: 25px; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); border-top: 5px solid #0056b3; position: relative; }
        .close-btn { float: right; cursor: pointer; font-weight: bold; font-size: 1.5em; color: #aaa; }

        .search-box { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef; }
        .input-search { flex-grow: 1; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; }
        .btn-search { padding: 10px 20px; background: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer; }
        
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { display: inline-block; padding: 8px 16px; text-decoration: none; color: #0056b3; border: 1px solid #ddd; margin: 0 2px; border-radius: 4px; background: white; }
        .pagination a.active { background-color: #0056b3; color: white; border: 1px solid #0056b3; }
        
        .avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .role-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        
        /* Modal Roles Tabla */
        .table-roles { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.9em; }
        .table-roles th { background: #6f42c1; color: white; padding: 5px; }
        .table-roles td { border: 1px solid #eee; padding: 5px; }

        /* Grid de Checkboxes */
        .permisos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; max-height: 250px; overflow-y: auto; }
        .permiso-item { background: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #eee; display: flex; align-items: center; gap: 8px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    </style>
    
    <script>
        function cerrarModales() {
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
        }

        function abrirModalClave(id, nombre) {
            document.getElementById('id_user_pass').value = id;
            document.getElementById('nombre_user_span').innerText = nombre;
            document.getElementById('modal-clave').style.display = 'block';
        }

        function abrirModalEditar(id, nombre, rol) {
            document.getElementById('id_user_edit').value = id;
            document.getElementById('nombre_edit').value = nombre;
            document.getElementById('rol_edit').value = rol;
            document.getElementById('modal-editar').style.display = 'block';
        }
        
        function abrirModalBorrar(id, nombre) {
            document.getElementById('id_borrar').value = id;
            document.getElementById('nombre_borrar_span').innerText = nombre;
            document.getElementById('modal-borrar').style.display = 'block';
        }

        function validarClave() {
            let p1 = document.getElementById('pass1').value;
            let p2 = document.getElementById('pass2').value;
            let btn = document.getElementById('btn_crear');
            let msg = document.getElementById('msg_pass');
            
            if (p1.length < 6) { msg.innerText = "Mín 6 chars"; msg.style.color = 'red'; return; }
            if (p1 !== p2) { msg.innerText = "No coinciden"; msg.style.color = 'red'; btn.disabled = true; btn.style.opacity = 0.5; } 
            else { msg.innerText = "OK ✓"; msg.style.color = 'green'; btn.disabled = false; btn.style.opacity = 1; }
        }
        
        // Roles CRUD
        function editarRol(id, nombre, desc) {
            let nuevoNombre = prompt("Editar nombre del rol:", nombre);
            if(nuevoNombre) {
                let form = document.createElement('form'); form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="accion_rol" value="editar"><input type="hidden" name="id_rol" value="${id}"><input type="hidden" name="nombre_rol_edit" value="${nuevoNombre}"><input type="hidden" name="desc_rol_edit" value="${desc}"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">`;
                document.body.appendChild(form); form.submit();
            }
        }
        
        function eliminarRol(id) {
            if(confirm("¿Seguro que deseas eliminar este rol?")) {
                let form = document.createElement('form'); form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="accion_rol" value="eliminar"><input type="hidden" name="id_rol" value="${id}"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">`;
                document.body.appendChild(form); form.submit();
            }
        }

        // --- GESTIÓN DE PERMISOS ---
        function abrirPermisos(rolNombre) {
            document.getElementById('rol_target_span').innerText = rolNombre;
            document.getElementById('rol_target').value = rolNombre;
            
            // Resetear checkboxes
            document.querySelectorAll('.chk-permiso').forEach(cb => cb.checked = false);
            
            // Cargar permisos via AJAX
            let formData = new FormData();
            formData.append('ajax_action', 'get_permisos');
            formData.append('rol', rolNombre);
            
            fetch('usuarios.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                data.forEach(mod => {
                    let chk = document.getElementById('chk_' + mod);
                    if(chk) chk.checked = true;
                });
                // Cerrar modal roles y abrir permisos
                document.getElementById('modal-roles').style.display = 'none';
                document.getElementById('modal-permisos').style.display = 'block';
            })
            .catch(err => console.error(err));
        }

        // Live Search
        document.addEventListener("DOMContentLoaded", function() {
            const input = document.getElementById('input_busqueda');
            const tbody = document.getElementById('tabla_body');
            const pagination = document.querySelector('.pagination');
            let timeout = null;
            if(input) {
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    const term = this.value;
                    if(pagination) pagination.style.display = term.length > 0 ? 'none' : 'block';
                    timeout = setTimeout(() => {
                        fetch(`usuarios.php?ajax_search=1&q=${encodeURIComponent(term)}`)
                        .then(response => response.text())
                        .then(html => { tbody.innerHTML = html; })
                    }, 300);
                });
            }
        });
    </script>
</head>
<body>

<div class="top-bar">
    <div class="logo">&#127981; INARPLAS Cloud</div>
    <div class="user-info">Hola, <b><?php echo $_SESSION['nombre']; ?></b> | <a href="logout.php" style="color:white;">Salir</a></div>
</div>

<div class="main-container">
    <div class="sidebar">
        <h3>Men&uacute; Principal</h3>
        <ul>
            <li><a href="dashboard.php">&#127968; Inicio</a></li>
            <li><a href="usuarios.php" class="active">&#128101; Empleados</a></li>
            <li><a href="maquinas.php">&#9881; M&aacute;quinas</a></li>
            <li><a href="productos.php">&#128230; Productos</a></li>
            <li><a href="ordenes.php">&#128221; &Oacute;rdenes Prod.</a></li>
            <li><a href="gestion_turno.php">&#9201; Gestionar Turno</a></li>
            <li><a href="ver_registros.php">&#128202; Reportes</a></li>
        </ul>
    </div>

    <div class="content">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>&#128101; Gesti&oacute;n de Empleados</h2>
            <div style="display:flex; gap:10px;">
                <button onclick="document.getElementById('modal-roles').style.display='block'" class="btn-save" style="width:auto; padding:10px 20px; background:#6f42c1;">&#128188; Roles y Permisos</button>
                <button onclick="document.getElementById('modal-crear').style.display='block'" class="btn-save" style="width:auto; padding:10px 20px;">&#10133; Nuevo Usuario</button>
            </div>
        </div>
        
        <p>Administración de accesos y perfiles.</p>
        
        <form class="search-box" method="GET" onsubmit="return false;">
            <i class="fa-solid fa-magnifying-glass" style="color:#6c757d;"></i>
            <input type="text" id="input_busqueda" name="q" class="input-search" placeholder="Buscar usuario..." value="<?php echo htmlspecialchars($busqueda); ?>" autocomplete="off">
        </form>

        <table class="tabla-usuarios">
            <thead>
                <tr><th width="50">Foto</th><th>ID</th><th>Nombre</th><th>Usuario</th><th>Perfil</th><th>Registrado</th><th>Acciones</th></tr>
            </thead>
            <tbody id="tabla_body">
                <?php 
                if ($resultado && $resultado->num_rows > 0) {
                    while($fila = $resultado->fetch_assoc()) { 
                        $bg_rol = ($fila['rol'] == 'ADMIN') ? '#cce5ff' : '#fff3cd';
                        $avatar = !empty($fila['avatar']) ? $fila['avatar'] : 'default.png';
                        $ruta_avatar = $directorio_avatares . $avatar;
                        $fecha_reg = isset($fila['fecha_registro']) ? date('d/m/Y', strtotime($fila['fecha_registro'])) : '-';
                ?>
                <tr>
                    <td><img src="<?php echo $ruta_avatar; ?>" class="avatar-img" onerror="this.src='https://via.placeholder.com/40?text=U'"></td>
                    <td><?php echo $fila['id']; ?></td>
                    <td><?php echo htmlspecialchars($fila['nombre']); ?></td> 
                    <td><?php echo htmlspecialchars($fila['usuario']); ?></td> 
                    <td><span class="role-badge" style="background:<?php echo $bg_rol; ?>"><?php echo $fila['rol']; ?></span></td>
                    <td><?php echo $fecha_reg; ?></td>
                    <td>
                        <button class="btn-accion btn-editar" onclick="abrirModalEditar(<?php echo $fila['id']; ?>, '<?php echo htmlspecialchars($fila['nombre']); ?>', '<?php echo $fila['rol']; ?>')" title="Editar">&#9998;</button>
                        <button class="btn-accion btn-clave" onclick="abrirModalClave(<?php echo $fila['id']; ?>, '<?php echo htmlspecialchars($fila['usuario']); ?>')" title="Clave">&#128273;</button>
                        <?php if($fila['id'] != (isset($_SESSION['id'])?$_SESSION['id']:0)) { ?>
                            <button class="btn-accion btn-borrar" onclick="abrirModalBorrar(<?php echo $fila['id']; ?>, '<?php echo htmlspecialchars($fila['nombre']); ?>')" title="Borrar">&#128465;</button>
                        <?php } ?>
                    </td>
                </tr>
                <?php } } else { echo "<tr><td colspan='7' align='center'>Sin resultados</td></tr>"; } ?>
            </tbody>
        </table>
        
        <?php if($total_paginas > 1): ?>
        <div class="pagination">
            <?php for($i=1; $i<=$total_paginas; $i++): ?>
                <a href="?pag=<?php echo $i; ?>&q=<?php echo $busqueda; ?>" class="<?php echo ($i == $pagina_actual) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL ROLES -->
<div id="modal-roles" class="modal">
    <div class="modal-contenido" style="border-top-color: #6f42c1; max-width:650px;">
        <span class="close-btn" onclick="document.getElementById('modal-roles').style.display='none'">&times;</span>
        <h3 style="color:#6f42c1;">&#128188; Roles y Permisos</h3>
        
        <form method="POST" style="background:#f8f9fa; padding:15px; border-radius:5px; margin-bottom:15px;">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="accion_rol" value="crear">
            <h4 style="margin-top:0;">Nuevo Rol</h4>
            <div style="display:flex; gap:10px;">
                <input type="text" name="nombre_rol" required placeholder="NOMBRE (Ej: SUPERVISOR)" style="text-transform:uppercase; flex:1;">
                <input type="text" name="descripcion_rol" placeholder="Descripción" style="flex:2;">
                <button type="submit" class="btn-save" style="background:#6f42c1; width:auto;">+</button>
            </div>
        </form>

        <table class="table-roles">
            <tr><th>Nombre</th><th>Descripción</th><th>Permisos</th><th>Acción</th></tr>
            <?php foreach($roles_db as $rol): ?>
            <tr>
                <td><b><?php echo $rol['nombre_rol']; ?></b></td>
                <td><?php echo $rol['descripcion']; ?></td>
                <td align="center">
                    <button type="button" onclick="abrirPermisos('<?php echo $rol['nombre_rol']; ?>')" class="btn-accion btn-permisos" title="Gestionar Accesos">&#128737; Accesos</button>
                </td>
                <td align="center">
                    <button type="button" onclick="editarRol(<?php echo $rol['id']; ?>, '<?php echo $rol['nombre_rol']; ?>', '<?php echo $rol['descripcion']; ?>')" style="cursor:pointer; border:none; background:none; color:blue;">&#9998;</button>
                    <?php if(!in_array($rol['nombre_rol'], ['ADMIN', 'OPERARIO'])): ?>
                    <button type="button" onclick="eliminarRol(<?php echo $rol['id']; ?>)" style="cursor:pointer; border:none; background:none; color:red;">&#10006;</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- MODAL ASIGNAR PERMISOS (NUEVO) -->
<div id="modal-permisos" class="modal">
    <div class="modal-contenido" style="border-top-color: #28a745; max-width:500px;">
        <span class="close-btn" onclick="document.getElementById('modal-permisos').style.display='none'; document.getElementById('modal-roles').style.display='block';">&times;</span>
        <h3 style="color:#28a745;">&#128737; Accesos: <span id="rol_target_span"></span></h3>
        <p>Selecciona qué botones puede ver este perfil en el Dashboard:</p>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="accion_rol" value="guardar_permisos">
            <input type="hidden" name="rol_target" id="rol_target">
            
            <div class="permisos-grid">
                <?php foreach($modulos_sistema as $key => $label): ?>
                <div class="permiso-item">
                    <input type="checkbox" name="permisos[]" value="<?php echo $key; ?>" id="chk_<?php echo $key; ?>" class="chk-permiso">
                    <label for="chk_<?php echo $key; ?>"><?php echo $label; ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            
            <br>
            <div style="display:flex; justify-content:space-between;">
                <button type="button" onclick="document.getElementById('modal-permisos').style.display='none'; document.getElementById('modal-roles').style.display='block';" class="btn-save" style="background:#6c757d; width:auto;">Volver</button>
                <button type="submit" class="btn-save" style="background:#28a745; width:auto;">Guardar Permisos</button>
            </div>
        </form>
    </div>
</div>

<!-- Otros Modales (Crear, Editar, Borrar, Clave) -->
<div id="modal-crear" class="modal">
    <div class="modal-contenido">
        <span class="close-btn" onclick="document.getElementById('modal-crear').style.display='none'">&times;</span>
        <h3>&#10133; Nuevo Usuario</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <label>Nombre Completo:</label><input type="text" name="nombre" required placeholder="Ej: Juan Perez">
            <div class="form-grid">
                <div><label>Usuario (Login):</label><input type="text" name="usuario" required placeholder="Ej: jperez"></div>
                <div><label>Perfil:</label><select name="rol"><?php foreach($lista_roles_nombres as $r): ?><option value="<?php echo $r; ?>"><?php echo $r; ?></option><?php endforeach; ?></select></div>
            </div>
            <label>Foto:</label><input type="file" name="foto" accept="image/*">
            <hr style="margin:15px 0; border:0; border-top:1px solid #eee;">
            <div class="form-grid">
                <div><input type="password" name="password" id="pass1" required placeholder="Clave (Mín 6)" onkeyup="validarClave()"></div>
                <div><input type="password" name="password_confirm" id="pass2" required placeholder="Repetir Clave" onkeyup="validarClave()"></div>
            </div>
            <div id="msg_pass" style="font-size:0.8em; text-align:right; height:15px;"></div>
            <br><button type="submit" name="crear_usuario" id="btn_crear" class="btn-save">Guardar</button>
        </form>
    </div>
</div>

<div id="modal-editar" class="modal">
    <div class="modal-contenido" style="border-top-color: #17a2b8;">
        <span class="close-btn" onclick="document.getElementById('modal-editar').style.display='none'">&times;</span>
        <h3>&#9998; Editar Perfil</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id_user_edit" id="id_user_edit">
            <label>Nombre:</label><input type="text" name="nombre_edit" id="nombre_edit" required>
            <label>Perfil:</label><select name="rol_edit" id="rol_edit"><?php foreach($lista_roles_nombres as $r): ?><option value="<?php echo $r; ?>"><?php echo $r; ?></option><?php endforeach; ?></select>
            <label>Foto:</label><input type="file" name="foto_edit" accept="image/*">
            <br><br><button type="submit" name="editar_usuario" class="btn-save" style="background:#17a2b8;">Actualizar</button>
        </form>
    </div>
</div>

<div id="modal-clave" class="modal">
    <div class="modal-contenido" style="border-top-color: #ffc107;">
        <span class="close-btn" onclick="document.getElementById('modal-clave').style.display='none'">&times;</span>
        <h3>&#128273; Cambiar Clave</h3>
        <p>Usuario: <b id="nombre_user_span">...</b></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id_user_pass" id="id_user_pass">
            <input type="password" name="new_password" required placeholder="Nueva Clave (Mín 6)"><br><br>
            <input type="password" name="new_password_confirm" required placeholder="Repetir Clave">
            <br><br><button type="submit" name="cambiar_clave" class="btn-save" style="background:#ffc107; color:black;">Actualizar</button>
        </form>
    </div>
</div>

<div id="modal-borrar" class="modal">
    <div class="modal-contenido" style="border-top-color: #dc3545;">
        <span class="close-btn" onclick="document.getElementById('modal-borrar').style.display='none'">&times;</span>
        <h3 style="color:#dc3545;">&#128465; Eliminar</h3>
        <p>Se desactivará a: <b id="nombre_borrar_span">...</b></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="id_borrar" id="id_borrar">
            <input type="hidden" name="borrar_usuario_confirm" value="1">
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="button" onclick="document.getElementById('modal-borrar').style.display='none'" class="btn-save" style="background:#6c757d;">Cancelar</button>
                <button type="submit" class="btn-save" style="background:#dc3545;">Eliminar</button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
    <?php if($toast_msg != ""): ?>
        Toastify({ text: "<?php echo $toast_msg; ?>", duration: 3000, close: true, gravity: "top", position: "right", style: { background: "<?php echo ($toast_type == 'success') ? '#28a745' : '#dc3545'; ?>" } }).showToast();
    <?php endif; ?>
</script>
</body>
</html>
<?php
// public_html/sistema/login.php
// Fecha: 11-12-2025 18:45:00
// DESCRIPCIÓN: Login Corregido con Entidades HTML (Solución definitiva a caracteres extraños).

// 1. Configuración de errores y codificación
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

session_start();

// 2. Verificar conexión
if (!file_exists('conexion.php')) {
    die("<h1>Error Cr&iacute;tico</h1><p>Falta el archivo <b>conexion.php</b>.</p>");
}
include 'conexion.php';

// 3. Token de Seguridad (CSRF)
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
}

$error = "";

// 4. Redirección si ya está logueado
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit();
}

// 5. Procesar Formulario de Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validar Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Error de seguridad (Token inv&aacute;lido). Por favor recarga la p&aacute;gina.";
    } else {
        $user = $conn->real_escape_string($_POST['usuario']);
        $pass = $_POST['password'];

        // Consulta Segura
        $sql = "SELECT id, nombre, password, rol FROM usuarios WHERE usuario = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("s", $user);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id_db, $nombre_db, $pass_db, $rol_db);
                $stmt->fetch();

                // Verificar Contraseña (Soporte para Hash y Texto Plano por compatibilidad)
                $login_ok = false;
                if (password_verify($pass, $pass_db)) {
                    $login_ok = true;
                } elseif ($pass == $pass_db) {
                    $login_ok = true;
                }

                if ($login_ok) {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $id_db;
                    $_SESSION['nombre'] = $nombre_db;
                    $_SESSION['rol'] = $rol_db;
                    $_SESSION['ultimo_acceso'] = time();

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "La contrase&ntilde;a es incorrecta.";
                }
            } else {
                $error = "El usuario no existe.";
            }
            $stmt->close();
        } else {
            $error = "Error SQL: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Seguro - INARPLAS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #e9ecef; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; border-top: 5px solid #0056b3; }
        .logo-img { max-width: 280px; height: auto; margin-bottom: 15px; } 
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; font-size: 0.9em; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; font-size: 1rem; transition: 0.3s; }
        input:focus { border-color: #0056b3; outline: none; box-shadow: 0 0 0 3px rgba(0,86,179,0.1); }
        .btn-login { width: 100%; padding: 12px; background: #0056b3; color: white; border: none; border-radius: 5px; font-size: 1.1rem; cursor: pointer; transition: 0.3s; font-weight: bold; }
        .btn-login:hover { background: #004494; transform: translateY(-2px); }
        .error-msg { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #f5c6cb; font-size: 0.9em; text-align: left; display: flex; align-items: center; gap: 10px; }
        .footer { margin-top: 25px; color: #999; font-size: 0.8em; }
    </style>
</head>
<body>

<div class="login-card">
    <!-- Asegúrate de tener la imagen logo_inarplas.jpg en la carpeta -->
    <img src="logo_inarplas.jpg" alt="Logo Inarplas" class="logo-img" onerror="this.style.display='none'; document.getElementById('txt-logo').style.display='block';"> 
    <h2 id="txt-logo" style="margin:0 0 5px 0; color:#0056b3; display:none;">INARPLAS</h2>

    <!-- TEXTOS CORREGIDOS CON ENTIDADES HTML -->
    <h3 style="margin:0 0 5px 0; color:#333;">M&oacute;dulo de Producci&oacute;n</h3>
    <p style="margin:0 0 25px 0; color:#777; font-size:0.95em;">Plataforma de Gesti&oacute;n</p>
    
    <?php if(!empty($error)): ?>
        <div class="error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div class="form-group">
            <label>Usuario</label>
            <div style="position:relative;">
                <input type="text" name="usuario" required autofocus placeholder="Usuario" style="padding-left: 35px;">
                <i class="fa-solid fa-user" style="position:absolute; left:12px; top:14px; color:#aaa;"></i>
            </div>
        </div>
        <div class="form-group">
            <label>Contrase&ntilde;a</label>
            <div style="position:relative;">
                <input type="password" name="password" required placeholder="Contrase&ntilde;a" style="padding-left: 35px;">
                <i class="fa-solid fa-lock" style="position:absolute; left:12px; top:14px; color:#aaa;"></i>
            </div>
        </div>
        <button type="submit" class="btn-login">Ingresar</button>
    </form>
    
    <div class="footer">
        &copy; <?php echo date("Y"); ?> Inarplas S.A.S<br>Acceso restringido.
    </div>
</div>

</body>
</html>
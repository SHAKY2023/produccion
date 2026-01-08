<?php
session_start();

// 1. Borrar todas las variables de sesión
session_unset();

// 2. Destruir la sesión completamente
session_destroy();

// 3. Redirigir al usuario al Login
header("Location: login.php");
exit();
?>
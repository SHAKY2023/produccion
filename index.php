<?php
// index.php (Dentro de la carpeta /sistema)
// Redirige al Login o al Dashboard
session_start();

if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?>
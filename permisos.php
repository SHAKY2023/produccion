<?php
// /public_html/sistema/permisos.php - Versión: v1.0
// DESCRIPCIÓN: Archivo centralizado para funciones de Control de Acceso (ACL).
// USO: Incluir este archivo después de conexion.php en cualquier script que requiera validar permisos.

if (!function_exists('tiene_acceso')) {
    function tiene_acceso($conn, $rol, $modulo) {
        // 1. ADMIN siempre tiene acceso total
        if ($rol == 'ADMIN') return true; 
        
        // 2. Sanitización preventiva (aunque los datos vengan de sesión/código)
        $rol = $conn->real_escape_string($rol);
        $modulo = $conn->real_escape_string($modulo);
        
        // 3. Consulta a la tabla de permisos
        $sql = "SELECT id FROM permisos_rol WHERE rol_nombre = '$rol' AND modulo = '$modulo'";
        $res = $conn->query($sql);
        
        // 4. Retorna verdadero si existe el permiso
        return ($res && $res->num_rows > 0);
    }
}
?>
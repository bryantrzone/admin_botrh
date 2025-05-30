<?php
// auth.php - Middleware de autenticación
// IMPORTANTE: Este archivo debe ser incluido ANTES de cualquier salida HTML

// Solo iniciar sesión si no está ya iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para verificar si el usuario está autenticado
function verificarAutenticacion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Función para verificar si el usuario tiene el rol necesario
function verificarRol($roles_permitidos = []) {
    verificarAutenticacion();
    
    if (!empty($roles_permitidos) && !in_array($_SESSION['rol'], $roles_permitidos)) {
        // Si no tiene permisos, redirigir según su rol
        if ($_SESSION['rol'] == 'rh') {
            header('Location: postulaciones.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }
}

// Función para verificar si es administrador
function esAdministrador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador';
}

// Función para verificar si es RH
function esRH() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'rh';
}

// Función para obtener información del usuario actual
function obtenerUsuarioActual() {
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'],
        'usuario' => $_SESSION['usuario_usuario'],
        'rol' => $_SESSION['rol']
    ];
}

// Función para cerrar sesión
function cerrarSesion() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: login.php');
    exit;
}

// Auto-verificar autenticación si no estamos en páginas públicas
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['login.php', 'logout.php'];

if (!in_array($current_page, $public_pages)) {
    verificarAutenticacion();
    
    // Verificar permisos específicos por página
    $admin_only_pages = [
        'index.php',
        'cuestionarios.php', 
        'preguntas.php',
        'opciones.php',
        'sucursales.php',
        'areas.php',
        'duplicar_cuestionario.php',
        'crear_vacante.php',
        'asignar_cuestionario.php',
        'usuarios.php'
    ];
    
    if (in_array($current_page, $admin_only_pages)) {
        verificarRol(['administrador']);
    }
    
    // Las páginas de RH (postulaciones.php, ver_respuestas.php) son accesibles por ambos roles
}
?>
<?php
// 1. Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: auth/login.php");
    exit;
}

// 3. Generar token CSRF si no existe (seguridad para formularios)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Preparar variables de usuario de forma segura
$nombre = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$rol    = htmlspecialchars($_SESSION['rol'] ?? 'cliente', ENT_QUOTES, 'UTF-8');
$esAdmin = (($_SESSION['rol'] ?? '') === 'administrador');

// 5. Conectar a la base de datos (ajusta la ruta si tu conexion.php está en config/)
require_once __DIR__ . '/../config/conexion.php';
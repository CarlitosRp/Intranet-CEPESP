<?php
// modules/auth/logout.php
require_once __DIR__ . '/../../config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Vaciar todas las variables de sesión
$_SESSION = [];

// Destruir la cookie de sesión (si existe)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login (o al index)
$BASE = rtrim(BASE_URL, '/');
header('Location: ' . $BASE . '/modules/auth/login.php?logout=1');
exit;

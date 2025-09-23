<?php
// modules/uniformes/toggle_equipo.php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
auth_require_login();

$canToggle = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');
if (!$canToggle) {
    http_response_code(403);
    exit('<div style="padding:16px;font-family:system-ui">Sin permiso.</div>');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('<div style="padding:16px;font-family:system-ui">Método no permitido.</div>');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
if (!$token_ok) {
    http_response_code(400);
    exit('<div style="padding:16px;font-family:system-ui">Token inválido.</div>');
}

$id = (int)($_POST['id_equipo'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('<div style="padding:16px;font-family:system-ui">ID inválido.</div>');
}

$cn = db();

// Invertir el estado (1->0, 0->1)
$stmt = mysqli_prepare($cn, "UPDATE equipo SET activo = 1 - activo WHERE id_equipo = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok = mysqli_stmt_execute($stmt);
$aff = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($ok && $aff >= 0) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    header('Location: detalle.php?id=' . urlencode((string)$id) . '&toggled=1');
    exit;
} else {
    http_response_code(500);
    exit('<div style="padding:16px;font-family:system-ui">No se pudo cambiar el estado.</div>');
}

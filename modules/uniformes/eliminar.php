<?php
// modules/uniformes/eliminar.php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
auth_require_login();

// Permisos (ajusta si tus slugs son otros)
$canDelete = auth_has_role('admin') || auth_has_role('inventarios') || auth_has_role('almacen');
if (!$canDelete) {
    http_response_code(403);
    exit('<div style="padding:16px;font-family:system-ui">Sin permiso para eliminar productos.</div>');
}

// Exigir POST
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
    exit('<div style="padding:16px;font-family:system-ui">Token inválido. Recarga la página e inténtalo de nuevo.</div>');
}

$id = isset($_POST['id_equipo']) ? (int)$_POST['id_equipo'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('<div style="padding:16px;font-family:system-ui">ID inválido.</div>');
}

$cn = db();
mysqli_begin_transaction($cn);

try {
    // Si NO usas FK con ON DELETE CASCADE, borramos variantes manualmente
    $stmt1 = mysqli_prepare($cn, "DELETE FROM item_variantes WHERE id_equipo = ?");
    mysqli_stmt_bind_param($stmt1, 'i', $id);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);

    // Borrar el producto
    $stmt2 = mysqli_prepare($cn, "DELETE FROM equipo WHERE id_equipo = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt2, 'i', $id);
    mysqli_stmt_execute($stmt2);
    $aff = mysqli_stmt_affected_rows($stmt2);
    mysqli_stmt_close($stmt2);

    if ($aff < 1) {
        throw new mysqli_sql_exception('No se encontró el producto o ya fue eliminado.', 0);
    }

    mysqli_commit($cn);

    // Regenerar token y redirigir al listado con mensaje
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    header('Location: index.php?deleted=1');
    exit;
} catch (mysqli_sql_exception $ex) {
    mysqli_rollback($cn);
    // Puedes registrar $ex->getMessage() en logs si lo deseas
    http_response_code(500);
    exit('<div style="padding:16px;font-family:system-ui">Error al eliminar: ' . htmlspecialchars($ex->getMessage()) . '</div>');
}

<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$token_ok = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
if (!$token_ok) {
    http_response_code(400);
    exit('Token inválido.');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido.');
}

$stmt = mysqli_prepare($cn, "DELETE FROM entradas WHERE id_entrada = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);

try {
    $ok = mysqli_stmt_execute($stmt);
    $aff = mysqli_affected_rows($cn);
    mysqli_stmt_close($stmt);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    header('Location: ' . $BASE . '/modules/inventario/entradas/index.php?deleted=1');
    exit;
} catch (mysqli_sql_exception $ex) {
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    http_response_code(500);
    exit('No se pudo eliminar.');
}

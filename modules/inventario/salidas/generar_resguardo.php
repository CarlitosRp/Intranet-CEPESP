<?php
// modules/inventario/salidas/generar_resguardo.php
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
    exit('CSRF inválido');
}

$id_salida = (int)($_POST['id_salida'] ?? 0);
if ($id_salida <= 0) {
    http_response_code(400);
    exit('Salida inválida');
}

// 1) ¿Existe salida y tiene detalle?
$S = db_select_all("SELECT id_salida, fecha FROM salidas WHERE id_salida = $id_salida LIMIT 1");
if (!$S || isset($S['_error'])) {
    http_response_code(404);
    exit('Salida no encontrada');
}
$fecha = $S[0]['fecha'];
$anio  = (int)date('Y', strtotime($fecha));

$det = db_select_all("SELECT COUNT(*) AS c FROM salidas_detalle WHERE id_salida = $id_salida");
$tiene = (int)($det[0]['c'] ?? 0);
if ($tiene <= 0) {
    http_response_code(400);
    exit('La salida no tiene partidas');
}

// 2) ¿Ya existe resguardo?
$R = db_select_all("SELECT id_resguardo FROM resguardos WHERE id_salida = $id_salida LIMIT 1");
if ($R && !isset($R['_error']) && count($R) > 0) {
    // Ya existe → ir a imprimir
    $id_res = (int)$R[0]['id_resguardo'];
    header('Location: ' . $BASE . '/modules/resguardos/imprimir.php?id=' . $id_res);
    exit;
}

// 3) Siguiente folio de ese año (LPAD a 5)
$maxRow = db_select_all("SELECT MAX(CAST(folio AS UNSIGNED)) AS mx FROM resguardos WHERE anio = $anio");
$next = (int)($maxRow[0]['mx'] ?? 0) + 1;
$folio = str_pad((string)$next, 5, '0', STR_PAD_LEFT);

// 4) Insertar
$director = null;        // lo puedes llenar fijo o traer de otra tabla/config
$lugar    = 'Hermosillo, Sonora';

$stmt = mysqli_prepare($cn, "
  INSERT INTO resguardos (id_salida, folio, anio, lugar, director)
  VALUES (?, ?, ?, ?, ?)
");
mysqli_stmt_bind_param($stmt, 'isiss', $id_salida, $folio, $anio, $lugar, $director);

try {
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
    }
    $id_res = mysqli_insert_id($cn);
    mysqli_stmt_close($stmt);

    // Rotar CSRF y redirigir a imprimir
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    header('Location: ' . $BASE . '/modules/resguardos/imprimir.php?id=' . $id_res);
    exit;
} catch (mysqli_sql_exception $ex) {
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    http_response_code(500);
    exit('Error al generar resguardo (código ' . (int)$ex->getCode() . ').');
}

<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

auth_require_login();
$cn = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}
if (!csrf_validate()) {
    http_response_code(400);
    exit('CSRF inválido');
}

$id_salida = (int)($_POST['id_salida'] ?? 0);
if ($id_salida <= 0) {
    http_response_code(400);
    exit('Falta id_salida');
}

// Verifica que exista la salida
$S = db_select_all("SELECT id_salida FROM salidas WHERE id_salida = $id_salida LIMIT 1");
if (!$S || isset($S['_error']) || !$S) {
    http_response_code(404);
    exit('Salida no encontrada');
}

// Genera el siguiente folio (5 dígitos)
$RMAX = db_select_all("SELECT MAX(folio) AS maxfolio FROM resguardos");
$nextFolio = (int)($RMAX[0]['maxfolio'] ?? 0) + 1;

// Datos por default (ajústalos si guardas director/lugar en configuración)
$director = 'ING. MARIA DEL CELIA CARMEN PEÑA TORRES';
$lugar    = 'Hermosillo, Sonora.';
$hoy      = date('Y-m-d');
$user     = $_SESSION['auth_user_email'] ?? 'sistema';

// Insertar
$stmt = mysqli_prepare($cn, "INSERT INTO resguardos (folio, id_salida, fecha, director, lugar, creado_por) VALUES (?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iissss', $nextFolio, $id_salida, $hoy, $director, $lugar, $user);

try {
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
    $new_id = (int)mysqli_insert_id($cn);
    mysqli_stmt_close($stmt);

    // Redirige directo a imprimir con id_resguardo
    header('Location: ' . BASE_URL . 'modules/resguardos/imprimir.php?id_resguardo=' . $new_id);
    exit;
} catch (mysqli_sql_exception $ex) {
    if (isset($stmt)) mysqli_stmt_close($stmt);
    http_response_code(500);
    exit('No se pudo crear el resguardo');
}

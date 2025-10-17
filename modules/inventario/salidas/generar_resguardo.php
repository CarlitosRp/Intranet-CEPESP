<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/db.php';

if (!auth_is_logged_in()) {
    header('Location: ' . BASE_URL . '/modules/auth/login.php?next=' . urlencode(BASE_URL . '/modules/inventario/salidas/index.php'));
    exit;
}

// CSRF (opcional, si lo usas en el resto del proyecto)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
        http_response_code(400);
        exit('CSRF inválido');
    }
}

// 1) Tomar id_salida de POST o GET
$id_salida = (int)($_POST['id_salida'] ?? $_GET['id_salida'] ?? 0);
if ($id_salida <= 0) {
    http_response_code(400);
    exit('Falta id_salida');
}

// 2) Si YA existe resguardo para esta salida, redirigir a imprimir
$existe = db_select_all("
  SELECT id_resguardo
  FROM resguardos
  WHERE id_salida = {$id_salida}
  LIMIT 1
");
if ($existe && empty($existe['_error'])) {
    $id_res = (int)$existe[0]['id_resguardo'];
    header('Location: ' . BASE_URL . '/modules/resguardos/imprimir.php?id_resguardo=' . $id_res);
    exit;
}

// 3) Datos por defecto (ajústalos a tu proyecto)
$anio     = (int)date('Y');
$hoy      = date('Y-m-d');
$lugar    = 'Hermosillo, Sonora';              // o desde config
$director = 'ING. MARIA CELIA DEL CARMEN PEÑA TORRES'; // o desde config
$user     = auth_current_user()['email'] ?? 'sistema';

// 4) Obtener siguiente folio del año (sin colisionar)
$next = db_select_all("
  SELECT COALESCE(MAX(folio), 0) + 1 AS next_folio
  FROM resguardos
  WHERE anio = {$anio}
");
$next_folio = (int)($next[0]['next_folio'] ?? 1);

// 5) Insertar resguardo
$cn = db();
$stmt = mysqli_prepare($cn, "INSERT INTO resguardos (id_salida, anio, folio, fecha, lugar, director, creado_por)
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iiissss', $id_salida, $anio, $next_folio, $hoy, $lugar, $director, $user);

try {
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        throw new mysqli_sql_exception(mysqli_error($cn), mysqli_errno($cn));
    }
    $new_id = (int)mysqli_insert_id($cn);
    mysqli_stmt_close($stmt);

    // 6) Redirigir SIEMPRE con el parámetro correcto
    header('Location: ' . BASE_URL . '/modules/resguardos/imprimir.php?id_resguardo=' . $new_id);
    exit;
} catch (mysqli_sql_exception $ex) {
    if (isset($stmt)) mysqli_stmt_close($stmt);
    http_response_code(500);
    exit('No se pudo crear el resguardo (' . (int)$ex->getCode() . ').');
}

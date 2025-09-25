<?php
// modules/inventario/existencias/export_csv.php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
$cn   = db();

// ==== Filtros (mismos que index.php) ====
$q        = trim($_GET['q'] ?? '');
$agrupar  = $_GET['agrupar'] ?? 'producto';
$solo_pos = (isset($_GET['solo_pos']) && $_GET['solo_pos'] === '1');

$where = '1=1';
if ($q !== '') {
    $qEsc = mysqli_real_escape_string($cn, $q);
    $where .= " AND (
    codigo LIKE '%$qEsc%' OR
    descripcion LIKE '%$qEsc%' OR
    modelo LIKE '%$qEsc%' OR
    categoria LIKE '%$qEsc%' OR
    talla LIKE '%$qEsc%'
  )";
}
if ($solo_pos) {
    $where .= " AND existencias > 0";
}

// Usamos la VISTA v_existencias (ver guía en index.php)
$from = "FROM v_existencias";

if ($agrupar === 'talla') {
    $sql = "
    SELECT codigo, descripcion, modelo, categoria, talla, existencias
    $from
    WHERE $where
    ORDER BY descripcion ASC, talla ASC
  ";
    $headers = ['Código', 'Descripción', 'Modelo', 'Categoría', 'Talla', 'Existencias'];
} else {
    $sql = "
    SELECT codigo, descripcion, modelo, categoria, SUM(existencias) AS existencias
    $from
    WHERE $where
    GROUP BY codigo, descripcion, modelo, categoria
    ORDER BY descripcion ASC
  ";
    $headers = ['Código', 'Descripción', 'Modelo', 'Categoría', 'Existencias'];
}

$rows = db_select_all($sql);
if (isset($rows['_error'])) $rows = [];

// ==== Salida CSV ====
$filename = 'existencias_' . $agrupar . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM para Excel en Windows
echo "\xEF\xBB\xBF";

// Encabezados
$f = fopen('php://output', 'w');
fputcsv($f, $headers);

// Filas
foreach ($rows as $r) {
    if ($agrupar === 'talla') {
        fputcsv($f, [
            $r['codigo'],
            $r['descripcion'],
            $r['modelo'],
            $r['categoria'],
            $r['talla'],
            (int)$r['existencias']
        ]);
    } else {
        fputcsv($f, [
            $r['codigo'],
            $r['descripcion'],
            $r['modelo'],
            $r['categoria'],
            (int)$r['existencias']
        ]);
    }
}
fclose($f);
exit;

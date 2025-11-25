<?php
require_once __DIR__ . '/../..//config/config.php';
require_once __DIR__ . '/../..//includes/db.php';

$q        = trim($_GET['q'] ?? '');
$desde    = trim($_GET['desde'] ?? '');
$hasta    = trim($_GET['hasta'] ?? '');
$categ    = trim($_GET['categoria'] ?? '');
$id_emp   = (int)($_GET['id_empleado'] ?? 0);

$W = [];
if ($desde !== '')  $W[] = "DATE(s.fecha) >= '" . db_escape($desde) . "'";
if ($hasta !== '')  $W[] = "DATE(s.fecha) <= '" . db_escape($hasta) . "'";
if ($categ !== '')  $W[] = "eq.categoria = '" . db_escape($categ) . "'";
if ($id_emp > 0)    $W[] = "s.id_empleado = " . (int)$id_emp;
if ($q !== '') {
    $qEsc = db_escape("%$q%");
    $W[] = "(e.nombre_completo LIKE '$qEsc' OR e.no_empleado LIKE '$qEsc' OR eq.descripcion LIKE '$qEsc' OR iv.talla LIKE '$qEsc')";
}
$whereSql = $W ? ('WHERE ' . implode(' AND ', $W)) : '';

$sql = "
SELECT 
  e.no_empleado   AS no_empleado,
  e.nombre_completo AS empleado,
  eq.categoria    AS categoria,
  eq.descripcion  AS producto,
  iv.talla        AS talla,
  SUM(sd.cantidad) AS entregados
FROM salidas_detalle sd
JOIN salidas s         ON s.id_salida   = sd.id_salida
JOIN empleados e       ON e.id_empleado = s.id_empleado
JOIN item_variantes iv ON iv.id_variante= sd.id_variante
JOIN equipo eq         ON eq.id_equipo  = iv.id_equipo
$whereSql
GROUP BY e.no_empleado, e.nombre_completo, eq.categoria, eq.descripcion, iv.talla
ORDER BY e.nombre_completo, eq.categoria, eq.descripcion, iv.talla
";
$rows = db_select_all($sql);

/* Output CSV */
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_uniformes.csv"');
$fh = fopen('php://output', 'w');
fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

fputcsv($fh, ['No. Emp.', 'Empleado', 'Categoría', 'Producto', 'Talla', 'Entregados']);
foreach ($rows as $r) {
    fputcsv($fh, [
        $r['no_empleado'] ?? '',
        $r['empleado'] ?? '',
        $r['categoria'] ?? '',
        $r['producto'] ?? '',
        $r['talla'] ?? '',
        (int)($r['entregados'] ?? 0),
    ]);
}
fclose($fh);
exit;

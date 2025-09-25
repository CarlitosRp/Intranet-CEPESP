<?php
// modules/inventario/existencias/export_xls.php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
$cn   = db();

// ==== Filtros ====
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

// ==== Salida Excel (HTML tabla con MIME de Excel) ====
$filename = 'existencias_' . $agrupar . '_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Excel lee bien tablas HTML simples
?>
<meta charset="UTF-8">
<table border="1" cellspacing="0" cellpadding="4">
    <thead>
        <tr>
            <?php foreach ($headers as $h): ?>
                <th><?= htmlspecialchars($h) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
            <?php if ($agrupar === 'talla'): ?>
                <tr>
                    <td><?= htmlspecialchars($r['codigo']) ?></td>
                    <td><?= htmlspecialchars($r['descripcion']) ?></td>
                    <td><?= htmlspecialchars($r['modelo']) ?></td>
                    <td><?= htmlspecialchars($r['categoria']) ?></td>
                    <td><?= htmlspecialchars($r['talla']) ?></td>
                    <td><?= (int)$r['existencias'] ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td><?= htmlspecialchars($r['codigo']) ?></td>
                    <td><?= htmlspecialchars($r['descripcion']) ?></td>
                    <td><?= htmlspecialchars($r['modelo']) ?></td>
                    <td><?= htmlspecialchars($r['categoria']) ?></td>
                    <td><?= (int)$r['existencias'] ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<?php exit; ?>
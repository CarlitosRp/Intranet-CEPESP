<?php
// modules/resguardos/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
auth_require_login();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/breadcrumbs.php';


$cn   = db();
$BASE = rtrim(BASE_URL, '/');

// ===== Filtros =====
$q      = trim($_GET['q'] ?? '');        // busca por folio (número), empleado, lugar, director
$desde  = trim($_GET['desde'] ?? '');    // YYYY-MM-DD
$hasta  = trim($_GET['hasta'] ?? '');    // YYYY-MM-DD

$where = [];
if ($q !== '') {
    // Si q es número, también probamos contra folio y año
    if (ctype_digit($q)) {
        $num = (int)$q;
        $where[] = "(r.folio = $num OR r.anio = $num OR e.no_empleado = $num)";
    } else {
        $qs = mysqli_real_escape_string($cn, $q);
        $where[] = "(e.nombre_completo LIKE '%$qs%' OR e.no_empleado LIKE '%$qs%' OR r.lugar LIKE '%$qs%' OR r.director LIKE '%$qs%')";
    }
}
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where[] = "r.fecha >= '" . mysqli_real_escape_string($cn, $desde) . "'";
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where[] = "r.fecha <= '" . mysqli_real_escape_string($cn, $hasta) . "'";
}

$W = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ===== Consulta =====
// IMPORTANTE: usamos r.fecha y r.creado_por (ya NO r.creado_en)
$sql = "
SELECT
  r.id_resguardo,
  r.anio,
  r.folio,
  r.fecha,
  r.lugar,
  r.director,
  r.creado_por,
  s.id_salida,
  e.id_empleado,
  e.no_empleado,
  e.nombre_completo
FROM resguardos r
JOIN salidas s   ON s.id_salida = r.id_salida
JOIN empleados e ON e.id_empleado = s.id_empleado
$W
ORDER BY r.anio DESC, r.folio DESC
LIMIT 200
";
$rows = db_select_all($sql);
if (isset($rows['_error'])) {
    $rows = [];
}

//$page_title = 'Resguardos · Listado';

/*render_breadcrumb([
    ['label' => 'Inventario', 'href' => $BASE . '/modules/inventario/existencias/index.php'],
    ['label' => 'Resguardos']
]);*/

?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Listado de Resguardos</h1>
    </div>

    <form class="row g-2 mb-3" method="get" action="index.php">
        <div class="col-md-5">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Buscar por folio, año, empleado, No. emp., lugar o director">
        </div>
        <div class="col-md-2">
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control" placeholder="Desde">
        </div>
        <div class="col-md-2">
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control" placeholder="Hasta">
        </div>
        <div class="col-md-3 d-grid d-md-flex gap-2">
            <button class="btn btn-primary">Filtrar</button>
            <a class="btn btn-outline-secondary" href="index.php">Limpiar</a>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!$rows): ?>
                <div class="text-muted">No hay resguardos que coincidan con el filtro.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:90px;">Año</th>
                                <th style="width:110px;">Folio</th>
                                <th style="width:120px;">Fecha</th>
                                <th>Empleado</th>
                                <th style="width:110px;">No. emp.</th>
                                <th>Lugar</th>
                                <th>Director</th>
                                <th style="width:220px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php $folio_fmt = str_pad((string)$r['folio'], 5, '0', STR_PAD_LEFT); ?>
                                <tr>
                                    <td><?= (int)$r['anio'] ?></td>
                                    <td><span class="badge text-bg-primary"><?= htmlspecialchars($folio_fmt) ?></span></td>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td><?= htmlspecialchars($r['nombre_completo']) ?></td>
                                    <td><?= htmlspecialchars($r['no_empleado']) ?></td>
                                    <td><?= htmlspecialchars($r['lugar'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($r['director'] ?: '—') ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary"
                                            href="<?= htmlspecialchars($BASE . '/modules/resguardos/imprimir.php?id_resguardo=' . (int)$r['id_resguardo']) ?>"
                                            target="_blank" rel="noopener">
                                            Imprimir
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar.php?id=' . (int)$r['id_salida']) ?>">
                                            Ver salida
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0">Mostrando <?= count($rows) ?> registros (máximo 200 por página).</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
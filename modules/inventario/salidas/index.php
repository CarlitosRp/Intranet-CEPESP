<?php
// modules/inventario/salidas/index.php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$page_title = 'Inventario · Salidas';

// ====== Filtros ======
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$emp   = trim($_GET['emp'] ?? '');
$rgdo  = $_GET['rgdo'] ?? 'todos'; // todos | si | no

$pagina = max(1, (int)($_GET['page'] ?? 1));
$pp     = 20;
$offset = ($pagina - 1) * $pp;

$where = '1=1';

// Fechas
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where .= " AND s.fecha >= '" . mysqli_real_escape_string($cn, $desde) . "'";
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where .= " AND s.fecha <= '" . mysqli_real_escape_string($cn, $hasta) . "'";
}

// Empleado (texto)
if ($emp !== '') {
    $empEsc = mysqli_real_escape_string($cn, $emp);
    $where .= " AND (
    e.no_empleado LIKE '%$empEsc%' OR
    e.nombre_completo LIKE '%$empEsc%' OR
   
  )";
}

// Con/ sin resguardo
if ($rgdo === 'si') {
    $where .= " AND r.id_resguardo IS NOT NULL";
}
if ($rgdo === 'no') {
    $where .= " AND r.id_resguardo IS NULL";
}

// ====== Conteo total ======
$sqlCount = "
  SELECT COUNT(*) AS c
  FROM salidas s
  JOIN empleados e ON e.id_empleado = s.id_empleado
  LEFT JOIN (
    SELECT id_salida, MIN(id_resguardo) AS id_resguardo
    FROM resguardos
    GROUP BY id_salida
  ) r ON r.id_salida = s.id_salida
  WHERE $where
";
$crow = db_select_all($sqlCount);
$total = (int)($crow[0]['c'] ?? 0);
$totalPag = max(1, (int)ceil($total / $pp));
if ($pagina > $totalPag) {
    $pagina = $totalPag;
    $offset = ($pagina - 1) * $pp;
}

// ====== Datos ======
$sql = "
  SELECT
    s.id_salida, s.fecha, s.observaciones,
    e.id_empleado, e.no_empleado,
    e.nombre_completo,
    COALESCE(t.total_pzas,0) AS total_pzas,
    r.id_resguardo, r.folio, r.anio
  FROM salidas s
  JOIN empleados e ON e.id_empleado = s.id_empleado
  LEFT JOIN (
    SELECT id_salida, SUM(cantidad) AS total_pzas
    FROM salidas_detalle
    GROUP BY id_salida
  ) t ON t.id_salida = s.id_salida
  LEFT JOIN (
    SELECT id_salida, MIN(id_resguardo) AS id_resguardo, MIN(folio) AS folio, MIN(anio) AS anio
    FROM resguardos
    GROUP BY id_salida
  ) r ON r.id_salida = s.id_salida
  WHERE $where
  ORDER BY s.fecha DESC, s.id_salida DESC
  LIMIT $pp OFFSET $offset
";
$rows = db_select_all($sql);
if (isset($rows['_error'])) $rows = [];

require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Salidas']
]);

// Helper URL
$mk = function ($p) use ($desde, $hasta, $emp, $rgdo) {
    $qs = ['page' => $p];
    if ($desde !== '') $qs['desde'] = $desde;
    if ($hasta !== '') $qs['hasta'] = $hasta;
    if ($emp !== '')   $qs['emp'] = $emp;
    if ($rgdo !== 'todos') $qs['rgdo'] = $rgdo;
    return 'index.php?' . http_build_query($qs);
};
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Salidas</h1>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/crear.php') ?>">Nueva salida</a>
    </div>

    <form class="row g-2 mb-3" method="get" action="index.php">
        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Empleado (nombre / No.)</label>
            <input type="text" name="emp" value="<?= htmlspecialchars($emp) ?>" class="form-control" placeholder="Buscar empleado">
        </div>
        <div class="col-md-2">
            <label class="form-label">Resguardo</label>
            <select name="rgdo" class="form-select">
                <option value="todos" <?= $rgdo === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="si" <?= $rgdo === 'si' ? 'selected' : '' ?>>Con resguardo</option>
                <option value="no" <?= $rgdo === 'no' ? 'selected' : '' ?>>Sin resguardo</option>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-secondary">Aplicar</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!$rows): ?>
                <div class="text-muted">No hay salidas con el filtro actual.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:110px">Fecha</th>
                                <th>Empleado</th>
                                <th class="text-end" style="width:120px">Piezas</th>
                                <th style="width:180px">Resguardo</th>
                                <th style="width:240px" class="text-nowrap">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['nombre_completo']) ?></div>
                                        <?php if (!empty($r['no_empleado'])): ?>
                                            <div class="text-muted small">No. <?= htmlspecialchars($r['no_empleado']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= (int)$r['total_pzas'] ?></td>
                                    <td>
                                        <?php if (!empty($r['id_resguardo'])): ?>
                                            <span class="chip">No. <?= htmlspecialchars(str_pad($r['folio'], 5, '0', STR_PAD_LEFT)) ?>/<?= (int)$r['anio'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sin resguardo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar.php?id=' . (int)$r['id_salida']) ?>">Editar</a>

                                        <?php if (empty($r['id_resguardo']) && (int)$r['total_pzas'] > 0): ?>
                                            <!-- Dentro del loop de filas (usa la variable correcta de la fila, p.ej. $s o $row) -->
                                            <form method="post"
                                                action="<?= BASE_URL ?>/modules/inventario/salidas/generar_resguardo.php"
                                                class="d-inline"
                                                onsubmit="return confirm('¿Generar resguardo para esta salida?');">
                                                <input type="hidden" name="id_salida" value="<?= (int)$r['id_salida'] ?>">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                <button class="btn btn-sm btn-outline-primary">Generar Resguardo</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (!empty($r['id_resguardo'])): ?>
                                            <a class="btn btn-sm btn-success" target="_blank"
                                                href="<?= htmlspecialchars($BASE . '/modules/resguardos/imprimir.php?id=' . (int)$r['id_resguardo']) ?>">
                                                Imprimir
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPag > 1): ?>
                    <nav class="mt-3" aria-label="Paginación">
                        <ul class="pagination pagination-sm">
                            <li class="page-item <?= ($pagina <= 1 ? 'disabled' : '') ?>">
                                <a class="page-link" href="<?= ($pagina <= 1 ? '#' : htmlspecialchars($mk($pagina - 1))) ?>">« Anterior</a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPag; $p++): ?>
                                <li class="page-item <?= ($p === $pagina ? 'active' : '') ?>">
                                    <a class="page-link" href="<?= htmlspecialchars($mk($p)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($pagina >= $totalPag ? 'disabled' : '') ?>">
                                <a class="page-link" href="<?= ($pagina >= $totalPag ? '#' : htmlspecialchars($mk($pagina + 1))) ?>">Siguiente »</a>
                            </li>
                        </ul>
                    </nav>
                    <p class="text-muted small">Mostrando <?= count($rows) ?> de <?= $total ?> salidas.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
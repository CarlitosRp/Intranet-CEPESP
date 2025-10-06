<?php
// modules/resguardos/index.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$page_title = 'Resguardos';

// ================== Filtros ==================
$folio  = trim($_GET['folio'] ?? '');   // texto: 00023
$emp    = trim($_GET['emp'] ?? '');     // nombre o No. empleado
$desde  = $_GET['desde'] ?? '';
$hasta  = $_GET['hasta'] ?? '';
$anio   = trim($_GET['anio'] ?? '');    // ej. 2025 (opcional)

$pagina = max(1, (int)($_GET['page'] ?? 1));
$pp     = 20;
$offset = ($pagina - 1) * $pp;

// WHERE dinámico
$where = '1=1';

if ($folio !== '') {
    $fEsc = mysqli_real_escape_string($cn, $folio);
    // permite buscar por “00023” o parte
    $where .= " AND r.folio LIKE '%$fEsc%'";
}

if ($emp !== '') {
    $eEsc = mysqli_real_escape_string($cn, $emp);
    $where .= " AND (
      e.no_empleado LIKE '%$eEsc%' OR
      e.nombre_completo      LIKE '%$eEsc%'
  )";
}

if ($anio !== '' && preg_match('/^\d{4}$/', $anio)) {
    $where .= " AND r.anio = " . (int)$anio;
}

if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $where .= " AND s.fecha >= '" . mysqli_real_escape_string($cn, $desde) . "'";
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $where .= " AND s.fecha <= '" . mysqli_real_escape_string($cn, $hasta) . "'";
}

// ================== Conteo total ==================
$sqlCount = "
  SELECT COUNT(*) AS c
  FROM resguardos r
  JOIN salidas s   ON s.id_salida = r.id_salida
  JOIN empleados e ON e.id_empleado = s.id_empleado
  WHERE $where
";
$crow = db_select_all($sqlCount);
$totalRows = (int)($crow[0]['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $pp));
if ($pagina > $totalPages) {
    $pagina = $totalPages;
    $offset = ($pagina - 1) * $pp;
}

// ================== Datos ==================
$sql = "
  SELECT
    r.id_resguardo, r.folio, r.anio, r.lugar, r.creado_en, r.director,
    s.id_salida, s.fecha, s.observaciones,
    e.id_empleado, e.no_empleado,
    e.nombre_completo,
    COALESCE(t.total_pzas,0) AS total_pzas
  FROM resguardos r
  JOIN salidas s   ON s.id_salida = r.id_salida
  JOIN empleados e ON e.id_empleado = s.id_empleado
  LEFT JOIN (
    SELECT id_salida, SUM(cantidad) AS total_pzas
    FROM salidas_detalle
    GROUP BY id_salida
  ) t ON t.id_salida = s.id_salida
  WHERE $where
  ORDER BY s.fecha DESC, r.anio DESC, CAST(r.folio AS UNSIGNED) DESC
  LIMIT $pp OFFSET $offset
";
$rows = db_select_all($sql);
if (isset($rows['_error'])) $rows = [];

// =============== Render ===============
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/breadcrumbs.php';

render_breadcrumb([
    ['label' => 'Resguardos']
]);

// Helper paginación
$mk = function ($p) use ($folio, $emp, $desde, $hasta, $anio) {
    $qs = ['page' => $p];
    if ($folio !== '') $qs['folio'] = $folio;
    if ($emp !== '')   $qs['emp'] = $emp;
    if ($desde !== '') $qs['desde'] = $desde;
    if ($hasta !== '') $qs['hasta'] = $hasta;
    if ($anio !== '')  $qs['anio'] = $anio;
    return 'index.php?' . http_build_query($qs);
};
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Resguardos</h1>
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/index.php') ?>">
            ← Salidas
        </a>
    </div>

    <!-- Filtros -->
    <form class="row g-2 mb-3" method="get" action="index.php">
        <div class="col-md-2">
            <label class="form-label">Folio</label>
            <input type="text" name="folio" value="<?= htmlspecialchars($folio) ?>" class="form-control" placeholder="00023">
        </div>
        <div class="col-md-3">
            <label class="form-label">Empleado (nombre / No.)</label>
            <input type="text" name="emp" value="<?= htmlspecialchars($emp) ?>" class="form-control" placeholder="Buscar empleado">
        </div>
        <div class="col-md-2">
            <label class="form-label">Año</label>
            <input type="text" name="anio" value="<?= htmlspecialchars($anio) ?>" class="form-control" placeholder="<?= date('Y') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control">
        </div>
        <div class="col-md-1 d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-secondary">Aplicar</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!$rows): ?>
                <div class="text-muted">No hay resguardos con el filtro actual.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:110px">Fecha</th>
                                <th>Folio</th>
                                <th>Empleado</th>
                                <th class="text-end" style="width:120px">Piezas</th>
                                <th style="width:220px" class="text-nowrap">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['fecha']) ?></td>
                                    <td>
                                        <span class="chip">No. <?= htmlspecialchars(str_pad($r['folio'], 5, '0', STR_PAD_LEFT)) ?>/<?= (int)$r['anio'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($r['nombre_completo']) ?></div>
                                        <?php if (!empty($r['no_empleado'])): ?>
                                            <div class="text-muted small">No. <?= htmlspecialchars($r['no_empleado']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= (int)$r['total_pzas'] ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/inventario/salidas/editar.php?id=' . (int)$r['id_salida']) ?>">
                                            Ver salida
                                        </a>
                                        <a class="btn btn-sm btn-success" target="_blank"
                                            href="<?= htmlspecialchars($BASE . '/modules/resguardos/imprimir.php?id=' . (int)$r['id_resguardo']) ?>">
                                            Imprimir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-3" aria-label="Paginación">
                        <ul class="pagination pagination-sm">
                            <li class="page-item <?= ($pagina <= 1 ? 'disabled' : '') ?>">
                                <a class="page-link" href="<?= ($pagina <= 1 ? '#' : htmlspecialchars($mk($pagina - 1))) ?>">« Anterior</a>
                            </li>
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <li class="page-item <?= ($p === $pagina ? 'active' : '') ?>">
                                    <a class="page-link" href="<?= htmlspecialchars($mk($p)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($pagina >= $totalPages ? 'disabled' : '') ?>">
                                <a class="page-link" href="<?= ($pagina >= $totalPages ? '#' : htmlspecialchars($mk($pagina + 1))) ?>">Siguiente »</a>
                            </li>
                        </ul>
                    </nav>
                    <p class="text-muted small">Mostrando <?= count($rows) ?> de <?= $totalRows ?> resguardos.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
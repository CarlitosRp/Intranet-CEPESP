<?php
// modules/resguardos/reporte_global.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$page_title = 'Resguardos · Reporte global';

// ====== Filtros ======
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$categ = trim($_GET['categoria'] ?? '');

// Catálogo de categorías (para el select)
$cats = db_select_all("
  SELECT DISTINCT categoria
  FROM equipo
  WHERE categoria IS NOT NULL AND categoria <> ''
  ORDER BY categoria
");
if (isset($cats['_error'])) $cats = [];

// WHERE dinámico base
$w = "1=1";
if ($desde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $w .= " AND s.fecha >= '" . mysqli_real_escape_string($cn, $desde) . "'";
}
if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $w .= " AND s.fecha <= '" . mysqli_real_escape_string($cn, $hasta) . "'";
}
if ($categ !== '') {
    $cEsc = mysqli_real_escape_string($cn, $categ);
    $w .= " AND e.categoria = '$cEsc'";
}

// ====== Resumen general ======
$summary = db_select_all("
  SELECT
    COUNT(DISTINCT r.id_resguardo) AS resguardos,
    COALESCE(SUM(d.cantidad), 0)   AS piezas
  FROM resguardos r
  JOIN salidas s         ON s.id_salida = r.id_salida
  JOIN salidas_detalle d ON d.id_salida = s.id_salida
  JOIN item_variantes v  ON v.id_variante = d.id_variante
  JOIN equipo e          ON e.id_equipo   = v.id_equipo
  WHERE $w
");
if (isset($summary['_error']) || !$summary) {
    $summary = [['resguardos' => 0, 'piezas' => 0]];
}

// ====== Totales por categoría ======
$por_categoria = db_select_all("
  SELECT
    e.categoria,
    SUM(d.cantidad) AS piezas
  FROM resguardos r
  JOIN salidas s         ON s.id_salida = r.id_salida
  JOIN salidas_detalle d ON d.id_salida = s.id_salida
  JOIN item_variantes v  ON v.id_variante = d.id_variante
  JOIN equipo e          ON e.id_equipo   = v.id_equipo
  WHERE $w
  GROUP BY e.categoria
  ORDER BY e.categoria ASC
");
if (isset($por_categoria['_error'])) $por_categoria = [];

// ====== Totales por talla (global o filtrado por categoría si se eligió) ======
$por_talla = db_select_all("
  SELECT
    v.talla,
    SUM(d.cantidad) AS piezas
  FROM resguardos r
  JOIN salidas s         ON s.id_salida = r.id_salida
  JOIN salidas_detalle d ON d.id_salida = s.id_salida
  JOIN item_variantes v  ON v.id_variante = d.id_variante
  JOIN equipo e          ON e.id_equipo   = v.id_equipo
  WHERE $w
  GROUP BY v.talla
  ORDER BY v.talla ASC
");
if (isset($por_talla['_error'])) $por_talla = [];

// ====== Export CSV ======
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'reporte_global_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    $out = fopen('php://output', 'w');

    // Encabezado filtros
    fputcsv($out, ['Reporte global de resguardos']);
    $fx = ['Periodo'];
    $fv = [];
    if ($desde !== '') $fv[] = 'Desde: ' . $desde;
    if ($hasta !== '') $fv[] = 'Hasta: ' . $hasta;
    if (!$fv) {
        $fv[] = 'Sin filtro de fechas';
    }
    fputcsv($out, [implode(' | ', $fv)]);
    if ($categ !== '') fputcsv($out, ['Categoría: ' . $categ]);
    fputcsv($out, []);

    // Resumen
    fputcsv($out, ['Resumen']);
    fputcsv($out, ['# Resguardos', 'Piezas']);
    fputcsv($out, [(int)$summary[0]['resguardos'], (int)$summary[0]['piezas']]);
    fputcsv($out, []);

    // Por categoría
    fputcsv($out, ['Totales por categoría']);
    fputcsv($out, ['Categoría', 'Piezas']);
    foreach ($por_categoria as $row) {
        fputcsv($out, [$row['categoria'], (int)$row['piezas']]);
    }
    fputcsv($out, []);

    // Por talla
    fputcsv($out, ($categ !== '') ? ['Totales por talla (solo categoría: ' . $categ . ')'] : ['Totales por talla (global)']);
    fputcsv($out, ['Talla', 'Piezas']);
    foreach ($por_talla as $row) {
        fputcsv($out, [$row['talla'], (int)$row['piezas']]);
    }

    fclose($out);
    exit;
}

// ====== Render ======
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Resguardos', 'href' => $BASE . '/modules/resguardos/index.php'],
    ['label' => 'Reporte global']
]);
?>
<style>
    @media print {

        .navbar,
        .no-print {
            display: none !important;
        }

        body {
            background: #fff;
        }

        .card {
            box-shadow: none !important;
            border: none !important;
        }
    }

    .kpi {
        font-size: 1.3rem;
        font-weight: 700;
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Reporte global</h1>
        <div class="d-flex gap-2 no-print">
            <?php
            $qs = [];
            if ($desde !== '') $qs['desde'] = $desde;
            if ($hasta !== '') $qs['hasta'] = $hasta;
            if ($categ !== '') $qs['categoria'] = $categ;
            $qsc = http_build_query(array_merge($qs, ['export' => 'csv']));
            ?>
            <a class="btn btn-outline-success btn-sm" href="reporte_global.php?<?= htmlspecialchars($qsc) ?>">Exportar Excel (CSV)</a>
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">Imprimir / PDF</button>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get" action="reporte_global.php" class="row g-2 mb-3 no-print">
        <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Categoría (opcional)</label>
            <select name="categoria" class="form-select">
                <option value="">— Todas —</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= htmlspecialchars($c['categoria']) ?>" <?= ($categ === $c['categoria'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($c['categoria']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-secondary">Aplicar</button>
        </div>
    </form>

    <!-- Resumen -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted">Resguardos en el periodo</div>
                    <div class="kpi"><?= (int)$summary[0]['resguardos'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted">Piezas entregadas</div>
                    <div class="kpi"><?= (int)$summary[0]['piezas'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Totales por categoría -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Totales por categoría</h2>
            <?php if (!$por_categoria): ?>
                <div class="text-muted">Sin datos para el periodo.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Categoría</th>
                                <th class="text-end" style="width:140px">Piezas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($por_categoria as $row): ?>
                                <tr>
                                    <td><span class="chip"><?= htmlspecialchars($row['categoria']) ?></span></td>
                                    <td class="text-end"><?= (int)$row['piezas'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Totales por talla -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h6 mb-3">
                Totales por talla
                <?php if ($categ !== ''): ?>
                    <small class="text-muted"> (solo categoría: <?= htmlspecialchars($categ) ?>)</small>
                <?php endif; ?>
            </h2>
            <?php if (!$por_talla): ?>
                <div class="text-muted">Sin datos para el periodo.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:160px">Talla</th>
                                <th class="text-end" style="width:140px">Piezas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($por_talla as $row): ?>
                                <tr>
                                    <td><span class="chip"><?= htmlspecialchars($row['talla']) ?></span></td>
                                    <td class="text-end"><?= (int)$row['piezas'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
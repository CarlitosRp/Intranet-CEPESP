<?php
// modules/resguardos/reporte_categoria.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$page_title = 'Resguardos · Reporte por categoría';

// ====== Filtros ======
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$categ = trim($_GET['categoria'] ?? '');

// Catálogo de categorías
$cats = db_select_all("
  SELECT DISTINCT categoria
  FROM equipo
  WHERE categoria IS NOT NULL AND categoria <> ''
  ORDER BY categoria
");
if (isset($cats['_error'])) $cats = [];

// WHERE dinámico base (se aplica en TODAS las consultas)
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

// ====== Si no hay categoría seleccionada, no ejecutamos queries pesadas ======
$summary = [['resguardos' => 0, 'piezas' => 0]];
$productos = [];
$detalles  = [];

if ($categ !== '') {
    // Resumen del periodo para la categoría
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

    // Totales por producto (equipo)
    $productos = db_select_all("
    SELECT
      e.id_equipo,
      e.codigo,
      e.descripcion,
      e.modelo,
      SUM(d.cantidad) AS piezas
    FROM resguardos r
    JOIN salidas s         ON s.id_salida = r.id_salida
    JOIN salidas_detalle d ON d.id_salida = s.id_salida
    JOIN item_variantes v  ON v.id_variante = d.id_variante
    JOIN equipo e          ON e.id_equipo   = v.id_equipo
    WHERE $w
    GROUP BY e.id_equipo, e.codigo, e.descripcion, e.modelo
    ORDER BY e.descripcion ASC
  ");
    if (isset($productos['_error'])) $productos = [];

    // Detalle por talla de TODOS los productos (lo pre-cargamos para pintar sin N+1)
    $detalles = db_select_all("
    SELECT
      e.id_equipo,
      v.talla,
      SUM(d.cantidad) AS piezas
    FROM resguardos r
    JOIN salidas s         ON s.id_salida = r.id_salida
    JOIN salidas_detalle d ON d.id_salida = s.id_salida
    JOIN item_variantes v  ON v.id_variante = d.id_variante
    JOIN equipo e          ON e.id_equipo   = v.id_equipo
    WHERE $w
    GROUP BY e.id_equipo, v.talla
    ORDER BY e.id_equipo ASC, v.talla ASC
  ");
    if (isset($detalles['_error'])) $detalles = [];
}

// Reindexamos detalles por id_equipo para acceso rápido
$map_det = [];
foreach ($detalles as $dd) {
    $eq = (int)$dd['id_equipo'];
    if (!isset($map_det[$eq])) $map_det[$eq] = [];
    $map_det[$eq][] = $dd; // cada elemento: ['id_equipo'=>.., 'talla'=>.., 'piezas'=>..]
}

// ====== Export CSV ======
if ($categ !== '' && !empty($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'reporte_categoria_' . preg_replace('/\s+/', '_', $categ) . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    $out = fopen('php://output', 'w');

    // Encabezado filtros
    fputcsv($out, ['Reporte por categoría']);
    $fx = [];
    if ($desde !== '') $fx[] = 'Desde: ' . $desde;
    if ($hasta !== '') $fx[] = 'Hasta: ' . $hasta;
    if (!$fx) {
        $fx[] = 'Sin filtro de fechas';
    }
    fputcsv($out, [implode(' | ', $fx)]);
    fputcsv($out, ['Categoría: ' . $categ]);
    fputcsv($out, []);

    // Resumen
    fputcsv($out, ['Resumen']);
    fputcsv($out, ['# Resguardos', 'Piezas']);
    fputcsv($out, [(int)$summary[0]['resguardos'], (int)$summary[0]['piezas']]);
    fputcsv($out, []);

    // Por producto
    fputcsv($out, ['Totales por producto']);
    fputcsv($out, ['Código', 'Descripción', 'Modelo', 'Piezas']);
    foreach ($productos as $p) {
        fputcsv($out, [$p['codigo'], $p['descripcion'], $p['modelo'], (int)$p['piezas']]);
        // detalle por talla de ese producto
        $rows = $map_det[(int)$p['id_equipo']] ?? [];
        if ($rows) {
            fputcsv($out, [' ', '  Detalle por talla', ' ', ' ']);
            fputcsv($out, [' ', 'Talla', ' ', 'Piezas']);
            foreach ($rows as $d) {
                fputcsv($out, [' ', $d['talla'], ' ', (int)$d['piezas']]);
            }
        }
        fputcsv($out, []); // línea en blanco entre productos
    }

    fclose($out);
    exit;
}

// ====== Render ======
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Resguardos', 'href' => $BASE . '/modules/resguardos/index.php'],
    ['label' => 'Reporte por categoría']
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
        font-size: 1.2rem;
        font-weight: 700;
    }

    .subtle {
        color: #6c757d;
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Reporte por categoría</h1>
        <div class="d-flex gap-2 no-print">
            <?php
            $qs = [];
            if ($desde !== '') $qs['desde'] = $desde;
            if ($hasta !== '') $qs['hasta'] = $hasta;
            if ($categ !== '') $qs['categoria'] = $categ;
            $qsc = http_build_query(array_merge($qs, ['export' => 'csv']));
            ?>
            <?php if ($categ !== ''): ?>
                <a class="btn btn-outline-success btn-sm" href="reporte_categoria.php?<?= htmlspecialchars($qsc) ?>">Exportar Excel (CSV)</a>
                <button class="btn btn-outline-primary btn-sm" onclick="window.print()">Imprimir / PDF</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get" action="reporte_categoria.php" class="row g-2 mb-3 no-print">
        <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Categoría <span class="text-danger">*</span></label>
            <select name="categoria" class="form-select" required>
                <option value="">— Selecciona —</option>
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

    <?php if ($categ === ''): ?>
        <div class="alert alert-info">Selecciona una categoría para ver el reporte.</div>
    <?php else: ?>
        <!-- Resumen -->
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted">Categoría</div>
                        <div class="kpi"><?= htmlspecialchars($categ) ?></div>
                        <?php if ($desde !== '' || $hasta !== ''): ?>
                            <div class="subtle mt-1">
                                <?php if ($desde !== '') echo 'Desde: ' . htmlspecialchars($desde); ?>
                                <?php if ($desde !== '' && $hasta !== '') echo ' · '; ?>
                                <?php if ($hasta !== '') echo 'Hasta: ' . htmlspecialchars($hasta); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted">Resguardos</div>
                        <div class="kpi"><?= (int)$summary[0]['resguardos'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-muted">Piezas</div>
                        <div class="kpi"><?= (int)$summary[0]['piezas'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos y detalle por tallas -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 mb-3">Productos y detalle por talla</h2>

                <?php if (!$productos): ?>
                    <div class="text-muted">No hay datos para el periodo/categoría seleccionados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:120px">Código</th>
                                    <th>Descripción</th>
                                    <th style="width:140px">Modelo</th>
                                    <th class="text-end" style="width:120px">Piezas</th>
                                    <th style="width:360px">Tallas (piezas)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['codigo']) ?></td>
                                        <td><?= htmlspecialchars($p['descripcion']) ?></td>
                                        <td><?= htmlspecialchars($p['modelo']) ?></td>
                                        <td class="text-end fw-semibold"><?= (int)$p['piezas'] ?></td>
                                        <td>
                                            <?php
                                            $rows = $map_det[(int)$p['id_equipo']] ?? [];
                                            if (!$rows) {
                                                echo '<span class="text-muted">—</span>';
                                            } else {
                                                // pintamos como chips "Talla (piezas)"
                                                $chips = [];
                                                foreach ($rows as $d) {
                                                    $chips[] = '<span class="chip">' . htmlspecialchars($d['talla']) . '</span> <span class="text-muted">(' . (int)$d['piezas'] . ')</span>';
                                                }
                                                echo implode('  ', $chips);
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
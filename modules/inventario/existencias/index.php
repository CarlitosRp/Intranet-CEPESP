<?php
// modules/inventario/existencias/index.php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

$page_title = 'Inventario · Existencias (netas)';

// ================= Filtros =================
$q        = trim($_GET['q'] ?? '');                // texto: código, descripción, modelo, categoría, talla
$categ    = trim($_GET['categoria'] ?? '');        // categoría exacta (si se elige)
$agrupar  = $_GET['agrupar'] ?? 'producto';        // 'producto' | 'talla'
$mostrar0 = isset($_GET['mostrar0']) ? (int)$_GET['mostrar0'] : 0;  // 0/1

// Paginación simple (si el resultado es grande)
$pagina = max(1, (int)($_GET['page'] ?? 1));
$pp     = 50;
$offset = ($pagina - 1) * $pp;

// ================= Catálogo de categorías (para el <select>) =================
$cats = db_select_all("
  SELECT DISTINCT categoria
  FROM equipo
  WHERE categoria IS NOT NULL AND categoria <> ''
  ORDER BY categoria
");
if (isset($cats['_error'])) $cats = [];

// ================= WHERE base (aplicado a la vista v_existencias_netas VS unión por producto) =================
$whereProd = "1=1";
$whereVar  = "1=1";

// Texto libre
if ($q !== '') {
    $qEsc = mysqli_real_escape_string($cn, $q);
    $frag = " (
    e.descripcion LIKE '%$qEsc%' OR
    e.codigo      LIKE '%$qEsc%' OR
    e.modelo      LIKE '%$qEsc%' OR
    e.categoria   LIKE '%$qEsc%' OR
    v.talla       LIKE '%$qEsc%'
  ) ";
    $whereProd .= " AND $frag";
    $whereVar  .= " AND $frag";
}

// Categoría
if ($categ !== '') {
    $cEsc = mysqli_real_escape_string($cn, $categ);
    $whereProd .= " AND e.categoria = '$cEsc'";
    $whereVar  .= " AND e.categoria = '$cEsc'";
}

// Mostrar o no en cero
if (!$mostrar0) {
    $whereProd .= " AND (COALESCE(ent.cant,0) - COALESCE(sal.cant,0)) > 0";
    $whereVar  .= " AND (COALESCE(ent.cant,0) - COALESCE(sal.cant,0)) > 0";
}

// ================= MODELO DE CONSULTA =================
//
// Para agrupar=producto: sumamos por id_equipo
// Para agrupar=talla   : mostramos por id_variante (talla específica)
//
// NOTA: usamos el mismo cálculo que v_existencias_netas, pero aquí lo reescribimos
// para poder agrupar por producto con SUM, manteniendo consistencia.

if ($agrupar === 'producto') {
    // Conteo total
    $cnt = db_select_all("
    SELECT COUNT(*) AS c
    FROM (
      SELECT e.id_equipo
      FROM item_variantes v
      JOIN equipo e ON e.id_equipo = v.id_equipo
      LEFT JOIN (
        SELECT d.id_variante, SUM(d.cantidad) AS cant
        FROM entradas_detalle d
        GROUP BY d.id_variante
      ) ent ON ent.id_variante = v.id_variante
      LEFT JOIN (
        SELECT d.id_variante, SUM(d.cantidad) AS cant
        FROM salidas_detalle d
        GROUP BY d.id_variante
      ) sal ON sal.id_variante = v.id_variante
      WHERE $whereProd
      GROUP BY e.id_equipo, e.codigo, e.descripcion, e.modelo, e.categoria, e.maneja_talla
    ) T
  ");
    $totalRows = (int)($cnt[0]['c'] ?? 0);

    // Datos
    $rows = db_select_all("
    SELECT
      e.id_equipo,
      e.codigo, e.descripcion, e.modelo, e.categoria, e.maneja_talla,
      SUM(COALESCE(ent.cant,0) - COALESCE(sal.cant,0)) AS existencias
    FROM item_variantes v
    JOIN equipo e ON e.id_equipo = v.id_equipo
    LEFT JOIN (
      SELECT d.id_variante, SUM(d.cantidad) AS cant
      FROM entradas_detalle d
      GROUP BY d.id_variante
    ) ent ON ent.id_variante = v.id_variante
    LEFT JOIN (
      SELECT d.id_variante, SUM(d.cantidad) AS cant
      FROM salidas_detalle d
      GROUP BY d.id_variante
    ) sal ON sal.id_variante = v.id_variante
    WHERE $whereProd
    GROUP BY e.id_equipo, e.codigo, e.descripcion, e.modelo, e.categoria, e.maneja_talla
    ORDER BY e.descripcion ASC
    LIMIT $pp OFFSET $offset
  ");
    if (isset($rows['_error'])) $rows = [];
    $totalPages = max(1, (int)ceil($totalRows / $pp));
} else {
    // agrupar = talla (variante)
    // Conteo total
    $cnt = db_select_all("
    SELECT COUNT(*) AS c
    FROM (
      SELECT v.id_variante
      FROM item_variantes v
      JOIN equipo e ON e.id_equipo = v.id_equipo
      LEFT JOIN (
        SELECT d.id_variante, SUM(d.cantidad) AS cant
        FROM entradas_detalle d
        GROUP BY d.id_variante
      ) ent ON ent.id_variante = v.id_variante
      LEFT JOIN (
        SELECT d.id_variante, SUM(d.cantidad) AS cant
        FROM salidas_detalle d
        GROUP BY d.id_variante
      ) sal ON sal.id_variante = v.id_variante
      WHERE $whereVar
    ) T
  ");
    $totalRows = (int)($cnt[0]['c'] ?? 0);

    // Datos
    $rows = db_select_all("
    SELECT
      v.id_variante,
      e.id_equipo,
      e.codigo, e.descripcion, e.modelo, e.categoria, e.maneja_talla,
      v.talla,
      (COALESCE(ent.cant,0) - COALESCE(sal.cant,0)) AS existencias
    FROM item_variantes v
    JOIN equipo e ON e.id_equipo = v.id_equipo
    LEFT JOIN (
      SELECT d.id_variante, SUM(d.cantidad) AS cant
      FROM entradas_detalle d
      GROUP BY d.id_variante
    ) ent ON ent.id_variante = v.id_variante
    LEFT JOIN (
      SELECT d.id_variante, SUM(d.cantidad) AS cant
      FROM salidas_detalle d
      GROUP BY d.id_variante
    ) sal ON sal.id_variante = v.id_variante
    WHERE $whereVar
    ORDER BY e.descripcion ASC, v.talla ASC
    LIMIT $pp OFFSET $offset
  ");
    if (isset($rows['_error'])) $rows = [];
    $totalPages = max(1, (int)ceil($totalRows / $pp));
}

// ================= Exportar CSV (Excel) =================
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'existencias_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // BOM UTF-8 para que Excel reconozca acentos
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');

    if ($agrupar === 'producto') {
        fputcsv($out, ['Código', 'Modelo', 'Categoría', 'Descripción', 'Maneja talla', 'Existencias']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['codigo'],
                $r['modelo'],
                $r['categoria'],
                $r['descripcion'],
                ((int)$r['maneja_talla'] === 1 ? 'Sí' : 'No'),
                (int)$r['existencias']
            ]);
        }
    } else {
        fputcsv($out, ['Código', 'Modelo', 'Categoría', 'Descripción', 'Talla', 'Existencias']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['codigo'],
                $r['modelo'],
                $r['categoria'],
                $r['descripcion'],
                $r['talla'],
                (int)$r['existencias']
            ]);
        }
    }
    fclose($out);
    exit;
}

// ================== Render ==================
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Existencias']
]);

// Helper URL de paginación manteniendo filtros
$mk = function ($p) use ($q, $categ, $agrupar, $mostrar0) {
    $qs = ['page' => $p];
    if ($q !== '')      $qs['q'] = $q;
    if ($categ !== '')  $qs['categoria'] = $categ;
    if ($agrupar !== 'producto') $qs['agrupar'] = $agrupar;
    if ($mostrar0)    $qs['mostrar0'] = 1;
    return 'index.php?' . http_build_query($qs);
};
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
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Existencias (netas)</h1>
        <div class="d-flex gap-2 no-print">
            <?php
            // Mantener filtros en los enlaces de acción
            $qsBase = [];
            if ($q !== '')      $qsBase['q'] = $q;
            if ($categ !== '')  $qsBase['categoria'] = $categ;
            if ($agrupar !== 'producto') $qsBase['agrupar'] = $agrupar;
            if ($mostrar0)    $qsBase['mostrar0'] = 1;
            $qsCsv  = http_build_query(array_merge($qsBase, ['export' => 'csv']));
            ?>
            <a class="btn btn-outline-success btn-sm" href="index.php?<?= htmlspecialchars($qsCsv) ?>">Exportar Excel (CSV)</a>
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">Imprimir / PDF</button>
        </div>
    </div>

    <!-- Filtros -->
    <form class="row g-2 mb-3 no-print" method="get" action="index.php">
        <div class="col-md-4">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control"
                value="<?= htmlspecialchars($q) ?>"
                placeholder="Código, descripción, modelo, categoría o talla">
        </div>
        <div class="col-md-3">
            <label class="form-label">Categoría</label>
            <select name="categoria" class="form-select">
                <option value="">— Todas —</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= htmlspecialchars($c['categoria']) ?>"
                        <?= ($categ === $c['categoria'] ? 'selected' : '') ?>>
                        <?= htmlspecialchars($c['categoria']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Agrupar</label>
            <select name="agrupar" class="form-select">
                <option value="producto" <?= ($agrupar === 'producto' ? 'selected' : '') ?>>Por producto</option>
                <option value="talla" <?= ($agrupar === 'talla' ? 'selected' : '') ?>>Por talla</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label d-block">Opciones</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="mostrar0" name="mostrar0" value="1" <?= ($mostrar0 ? 'checked' : '') ?>>
                <label class="form-check-label" for="mostrar0">Incluir en cero</label>
            </div>
        </div>
        <div class="col-md-1 d-grid">
            <label class="form-label">&nbsp;</label>
            <button class="btn btn-outline-secondary">Aplicar</button>
        </div>
    </form>

    <!-- Tabla -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (!$rows): ?>
                <div class="text-muted">Sin resultados con el filtro actual.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Modelo</th>
                                <th>Categoría</th>
                                <th>Descripción</th>
                                <?php if ($agrupar === 'talla'): ?>
                                    <th>Talla</th>
                                <?php else: ?>
                                    <th>Maneja Talla</th>
                                <?php endif; ?>
                                <th class="text-end" style="width:140px">Existencias</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['codigo']) ?></td>
                                    <td><?= htmlspecialchars($r['modelo']) ?></td>
                                    <td><span class="chip"><?= htmlspecialchars($r['categoria']) ?></span></td>
                                    <td><?= htmlspecialchars($r['descripcion']) ?></td>
                                    <?php if ($agrupar === 'talla'): ?>
                                        <td><span class="chip"><?= htmlspecialchars($r['talla']) ?></span></td>
                                    <?php else: ?>
                                        <td>
                                            <?php if ((int)$r['maneja_talla'] === 1): ?>
                                                <span class="badge bg-primary">Sí</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <td class="text-end"><?= (int)$r['existencias'] ?></td>
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
                    <p class="text-muted small">Mostrando <?= count($rows) ?> de <?= $totalRows ?> registros.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
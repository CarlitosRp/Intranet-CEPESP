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

/* ====== Filtros ====== */
$q        = trim($_GET['q'] ?? '');                    // texto: código, descripción, modelo, categoría, talla
$agrupar  = $_GET['agrupar'] ?? 'producto';            // producto | talla
$solo_pos = (isset($_GET['solo_pos']) && $_GET['solo_pos'] === '1');   // solo > 0
$pagina   = max(1, (int)($_GET['page'] ?? 1));
$pp       = 25;
$offset   = ($pagina - 1) * $pp;

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

/* ====== SQL base ====== */
/* usamos la vista v_existencias; si no la creaste, reemplaza FROM v_existencias por el SELECT con joins y SUM */
$from = "FROM v_existencias";

/* ====== Conteo y datos según agrupación ====== */
if ($agrupar === 'talla') {
    // una fila por variante
    $sqlCount = "SELECT COUNT(*) AS total $from WHERE $where";
    $sqlData  = "
    SELECT id_variante, id_equipo, codigo, descripcion, modelo, categoria, maneja_talla, talla, existencias
    $from
    WHERE $where
    ORDER BY descripcion ASC, talla ASC
    LIMIT $pp OFFSET $offset
  ";
} else {
    // agrupado por producto (sumar todas las tallas)
    $sqlCount = "
    SELECT COUNT(*) AS total FROM (
      SELECT id_equipo
      $from
      WHERE $where
      GROUP BY id_equipo, codigo, descripcion, modelo, categoria, maneja_talla
    ) AS t
  ";
    $sqlData = "
    SELECT
      id_equipo, codigo, descripcion, modelo, categoria, maneja_talla,
      SUM(existencias) AS existencias
    $from
    WHERE $where
    GROUP BY id_equipo, codigo, descripcion, modelo, categoria, maneja_talla
    ORDER BY descripcion ASC
    LIMIT $pp OFFSET $offset
  ";
}

$cnt = db_select_all($sqlCount);
$total = (int)($cnt[0]['total'] ?? 0);
$totalPag = max(1, (int)ceil($total / $pp));
if ($pagina > $totalPag) {
    $pagina = $totalPag;
    $offset = ($pagina - 1) * $pp;
}

$rows = db_select_all($sqlData);
if (isset($rows['_error'])) $rows = [];

/* ====== Total general (para mostrar abajo) ====== */
$sqlTotalG = "SELECT SUM(existencias) AS total_pzas $from WHERE $where";
$tg = db_select_all($sqlTotalG);
$total_pzas = (int)($tg[0]['total_pzas'] ?? 0);

$page_title = 'Inventario · Existencias';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Existencias']
]);

// helper para armar links de paginación conservando filtros
$mk = function ($p) use ($q, $agrupar, $solo_pos) {
    $qs = ['page' => $p];
    if ($q !== '') $qs['q'] = $q;
    if ($agrupar !== 'producto') $qs['agrupar'] = $agrupar;
    if ($solo_pos) $qs['solo_pos'] = '1';
    return 'index.php?' . http_build_query($qs);
};
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Existencias</h1>
        <div class="d-flex gap-2">
            <?php
            $qs = [];
            if ($q !== '') $qs['q'] = $q;
            if ($agrupar !== 'producto') $qs['agrupar'] = $agrupar;
            if ($solo_pos) $qs['solo_pos'] = '1';
            $qs_str = http_build_query($qs);
            ?>
            <a class="btn btn-outline-secondary btn-sm"
                href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/index.php') ?>">Entradas</a>
            <a class="btn btn-outline-primary btn-sm"
                href="<?= htmlspecialchars($BASE . '/modules/inventario/existencias/export_xls.php' . ($qs_str ? ('?' . $qs_str) : '')) ?>">
                Exportar Excel
            </a>
            <a class="btn btn-outline-success btn-sm"
                href="<?= htmlspecialchars($BASE . '/modules/inventario/existencias/export_csv.php' . ($qs_str ? ('?' . $qs_str) : '')) ?>">
                Exportar CSV
            </a>
            <a class="btn btn-primary btn-sm"
                href="<?= htmlspecialchars($BASE . '/modules/inventario/existencias/imprimir.php' . ($qs_str ? ('?' . $qs_str) : '')) ?>">
                PDF / Imprimir
            </a>
        </div>
    </div>

    <form class="row g-2 mb-3" method="get" action="index.php">
        <div class="col-md-5">
            <input type="text" name="q" class="form-control" placeholder="Buscar por código, descripción, modelo, categoría, talla"
                value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-md-3">
            <select name="agrupar" class="form-select">
                <option value="producto" <?= $agrupar === 'producto' ? 'selected' : '' ?>>Agrupar por producto</option>
                <option value="talla" <?= $agrupar === 'talla' ? 'selected' : '' ?>>Ver por talla (variante)</option>
            </select>
        </div>
        <div class="col-md-2 form-check d-flex align-items-center">
            <input class="form-check-input me-2" type="checkbox" name="solo_pos" value="1" id="solo_pos"
                <?= $solo_pos ? 'checked' : '' ?>>
            <label class="form-check-label" for="solo_pos">Solo existencias &gt; 0</label>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-secondary">Aplicar</button>
        </div>
    </form>

    <?php if ($total === 0): ?>
        <div class="alert alert-light border">Sin movimientos (aún no hay existencias que cumplan el filtro).</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <?php if ($agrupar === 'talla'): ?>
                            <th style="width:120px">Código</th>
                            <th>Descripción</th>
                            <th style="width:140px">Modelo</th>
                            <th style="width:140px">Categoría</th>
                            <th style="width:100px">Talla</th>
                            <th style="width:120px" class="text-end">Existencias</th>
                        <?php else: ?>
                            <th style="width:120px">Código</th>
                            <th>Descripción</th>
                            <th style="width:140px">Modelo</th>
                            <th style="width:140px">Categoría</th>
                            <th style="width:140px" class="text-end">Existencias</th>
                        <?php endif; ?>
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
                                <td class="text-end"><?= (int)$r['existencias'] ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td><?= htmlspecialchars($r['codigo']) ?></td>
                                <td><?= htmlspecialchars($r['descripcion']) ?></td>
                                <td><?= htmlspecialchars($r['modelo']) ?></td>
                                <td><?= htmlspecialchars($r['categoria']) ?></td>
                                <td class="text-end"><?= (int)$r['existencias'] ?></td>
                            </tr>
                        <?php endif; ?>
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
            <p class="text-muted small">Mostrando <?= count($rows) ?> de <?= $total ?> registros.</p>
        <?php endif; ?>

        <div class="mt-2 text-end">
            <span class="fw-semibold">Total general de piezas:</span> <?= (int)$total_pzas ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php
// modules/inventario/entradas/index.php

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

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

// Filtros
$q       = trim($_GET['q'] ?? '');        // proveedor o factura
$f1      = trim($_GET['f1'] ?? '');       // fecha desde (YYYY-MM-DD)
$f2      = trim($_GET['f2'] ?? '');       // fecha hasta
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where = '1=1';
if ($q !== '') {
    $qEsc = mysqli_real_escape_string($cn, $q);
    $where .= " AND (proveedor LIKE '%$qEsc%' OR factura LIKE '%$qEsc%')";
}
if ($f1 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f1)) {
    $where .= " AND fecha >= '" . mysqli_real_escape_string($cn, $f1) . "'";
}
if ($f2 !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f2)) {
    $where .= " AND fecha <= '" . mysqli_real_escape_string($cn, $f2) . "'";
}

// Conteo
$sqlCount = "SELECT COUNT(*) AS total FROM entradas WHERE $where";
$cnt = db_select_all($sqlCount);
$total = (int)($cnt[0]['total'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Datos
$sql = "
  SELECT id_entrada, fecha, proveedor, factura, observaciones, creado_por
  FROM entradas
  WHERE $where
  ORDER BY id_entrada DESC
  LIMIT $perPage OFFSET $offset
";
$rows = db_select_all($sql);
if (isset($rows['_error'])) {
    $rows = [];
}

$page_title = 'Inventario · Entradas';
require_once __DIR__ . '/../../../includes/header.php';
require_once __DIR__ . '/../../../includes/breadcrumbs.php';
render_breadcrumb([
    ['label' => 'Inventario'],
    ['label' => 'Entradas']
]);
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Entradas de inventario</h1>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/entradas/crear.php') ?>">Nueva entrada</a>
    </div>

    <form class="row g-2 mb-3" method="get" action="index.php">
        <div class="col-md-4">
            <input type="text" name="q" class="form-control" placeholder="Proveedor o factura" value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-md-3">
            <input type="date" name="f1" class="form-control" value="<?= htmlspecialchars($f1) ?>" placeholder="Desde">
        </div>
        <div class="col-md-3">
            <input type="date" name="f2" class="form-control" value="<?= htmlspecialchars($f2) ?>" placeholder="Hasta">
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-secondary">Filtrar</button>
        </div>
    </form>

    <?php if ($total === 0): ?>
        <div class="alert alert-light border">Aún no hay entradas.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-white shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th style="width:120px">Fecha</th>
                        <th>Proveedor</th>
                        <th style="width:160px">Factura</th>
                        <th>Observaciones</th>
                        <th style="width:160px">Creado por</th>
                        <th style="width:160px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fecha']) ?></td>
                            <td><?= htmlspecialchars($r['proveedor']) ?></td>
                            <td><?= htmlspecialchars($r['factura']) ?></td>
                            <td><?= htmlspecialchars($r['observaciones']) ?></td>
                            <td><?= htmlspecialchars($r['creado_por']) ?></td>
                            <td class="text-nowrap">
                                <!-- Próximo paso: ver/editar/eliminar -->
                                <a class="btn btn-sm btn-outline-primary disabled" tabindex="-1">Ver</a>
                                <a class="btn btn-sm btn-outline-secondary disabled" tabindex="-1">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-3" aria-label="Paginación">
                <ul class="pagination pagination-sm">
                    <?php
                    $mk = function ($p) use ($q, $f1, $f2) {
                        $qs = ['page' => $p];
                        if ($q !== '')  $qs['q']  = $q;
                        if ($f1 !== '') $qs['f1'] = $f1;
                        if ($f2 !== '') $qs['f2'] = $f2;
                        return 'index.php?' . http_build_query($qs);
                    };
                    ?>
                    <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
                        <a class="page-link" href="<?= ($page <= 1 ? '#' : htmlspecialchars($mk($page - 1))) ?>">« Anterior</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= ($p === $page ? 'active' : '') ?>">
                            <a class="page-link" href="<?= htmlspecialchars($mk($p)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>">
                        <a class="page-link" href="<?= ($page >= $totalPages ? '#' : htmlspecialchars($mk($page + 1))) ?>">Siguiente »</a>
                    </li>
                </ul>
            </nav>
            <p class="text-muted small">Mostrando <?= count($rows) ?> de <?= $total ?> entradas.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php
// modules/inventario/existencias/imprimir.php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

auth_require_login();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$BASE = rtrim(BASE_URL, '/');
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
if ($solo_pos) $where .= " AND existencias > 0";

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

$total_pzas = 0;
foreach ($rows as $r) {
    $total_pzas += (int)$r['existencias'];
}

$page_title = 'Existencias · Imprimir';
require_once __DIR__ . '/../../../includes/header.php';
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
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h1 class="h5 mb-0">Existencias (<?= htmlspecialchars($agrupar) ?>)</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/inventario/existencias/index.php') ?>">← Volver</a>
            <button class="btn btn-primary btn-sm" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
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
            </div>
            <div class="mt-2 text-end">
                <span class="fw-semibold">Total general de piezas:</span> <?= (int)$total_pzas ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
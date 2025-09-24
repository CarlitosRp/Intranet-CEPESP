<?php
// modules/uniformes/catalogo.php (revisado para ocultar tallas cuando maneja_talla=0)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

auth_require_login();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

$BASE = rtrim(BASE_URL, '/');
$cn   = db();

// --------------------- Filtros ---------------------
$q       = isset($_GET['q']) ? trim($_GET['q']) : '';
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12;
$offset  = ($page - 1) * $perPage;

// Construir cláusula WHERE simple (búsqueda)
$where = '1=1';
if ($q !== '') {
    $qEsc = mysqli_real_escape_string($cn, $q);
    // Buscamos en campos del producto y, SOLO SI maneja_talla=1, también por talla (v.talla)
    $where .= " AND (e.codigo LIKE '%$qEsc%' 
                OR e.descripcion LIKE '%$qEsc%' 
                OR e.modelo LIKE '%$qEsc%' 
                OR e.categoria LIKE '%$qEsc%' 
                OR (e.maneja_talla = 1 AND v.talla LIKE '%$qEsc%')
              )";
}

// --------------------- Conteo total (DISTINCT productos) ---------------------
$sqlCount = "
  SELECT COUNT(DISTINCT e.id_equipo) AS total
    FROM equipo e
    LEFT JOIN item_variantes v 
           ON v.id_equipo = e.id_equipo
          AND e.maneja_talla = 1
          AND v.activo = 1
   WHERE $where
";
$cnt = db_select_all($sqlCount);
$totalRows  = (isset($cnt[0]['total']) ? (int)$cnt[0]['total'] : 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// --------------------- Consulta paginada ---------------------
$sql = "
  SELECT
    e.id_equipo,
    e.codigo,
    e.descripcion,
    e.modelo,
    e.categoria,
    e.maneja_talla,
    e.activo,
    GROUP_CONCAT(DISTINCT v.talla ORDER BY v.talla SEPARATOR ', ') AS tallas
  FROM equipo e
  LEFT JOIN item_variantes v 
         ON v.id_equipo = e.id_equipo
        AND e.maneja_talla = 1
        AND v.activo = 1
  WHERE $where
  GROUP BY e.id_equipo, e.codigo, e.descripcion, e.modelo, e.categoria, e.maneja_talla, e.activo
  ORDER BY e.descripcion ASC
  LIMIT $perPage OFFSET $offset
";
$rows = db_select_all($sql);
if (isset($rows['_error'])) {
    $rows = [];
}

?>

<?php
$page_title = 'Uniformes · Catálogo';
require_once __DIR__ . '/../../includes/header.php';

require_once __DIR__ . '/../../includes/breadcrumbs.php';
render_breadcrumb([['label' => 'Catálogo']]);
?>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Uniformes · Catálogo</h1>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($BASE . '/modules/uniformes/crear.php') ?>">Nuevo producto</a>
    </div>

    <!-- Buscador -->
    <form class="row g-2 mb-3" method="get" action="catalogo.php">
        <div class="col-sm-8 col-md-9">
            <input type="text" name="q" class="form-control" placeholder="Buscar por código, descripción, modelo, categoría o talla"
                value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-sm-4 col-md-3 d-grid">
            <button class="btn btn-outline-secondary">Buscar</button>
        </div>
    </form>

    <?php if ($totalRows === 0): ?>
        <div class="alert alert-light border">No se encontraron productos con ese criterio.</div>
    <?php endif; ?>

    <!-- Grid de tarjetas -->
    <div class="row g-3">
        <?php foreach ($rows as $r): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h2 class="h6 mb-2"><?= htmlspecialchars($r['descripcion']) ?></h2>
                            <?php if ((int)$r['activo'] === 0): ?>
                                <span class="badge text-bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-2 text-muted small">
                            <div><strong>Código:</strong> <?= htmlspecialchars($r['codigo']) ?></div>
                            <div><strong>Modelo:</strong> <?= htmlspecialchars($r['modelo']) ?></div>
                            <div><strong>Categoría:</strong> <?= htmlspecialchars($r['categoria']) ?></div>
                        </div>

                        <!-- Mostrar tallas solo si maneja_talla=1 -->
                        <?php if ((int)$r['maneja_talla'] === 1): ?>
                            <?php if (!empty($r['tallas'])): ?>
                                <div class="mb-2">
                                    <?php foreach (explode(',', $r['tallas']) as $t): ?>
                                        <?php $lbl = trim($t);
                                        if ($lbl === '') continue; ?>
                                        <span class="chip"><?= htmlspecialchars($lbl) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted small">Sin tallas activas.</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-muted small">No maneja tallas.</div>
                        <?php endif; ?>

                        <div class="mt-3 d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($BASE . '/modules/uniformes/detalle.php?id=' . (int)$r['id_equipo']) ?>">Ver detalle</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($BASE . '/modules/uniformes/editar.php?id=' . (int)$r['id_equipo']) ?>">Editar</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginación -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Paginación">
            <ul class="pagination pagination-sm">
                <?php
                // Helper simple para mantener q en los enlaces
                $baseUrl = 'catalogo.php';
                $qs = [];
                if ($q !== '') $qs['q'] = $q;
                $mk = function ($p) use ($baseUrl, $qs) {
                    $qs2 = $qs;
                    $qs2['page'] = $p;
                    return $baseUrl . '?' . http_build_query($qs2);
                };
                ?>
                <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
                    <a class="page-link" href="<?= ($page <= 1 ? '#' : htmlspecialchars($mk($page - 1))) ?>" tabindex="-1">« Anterior</a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= ($p === $page ? 'active' : '') ?>">
                        <a class="page-link" href="<?= htmlspecialchars($mk($p)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages ? 'disabled' : '') ?>">
                    <a class="page-link" href="<?= ($page >>= $totalPages ? '#' : htmlspecialchars($mk($page + 1))) ?>">Siguiente »</a>
                </li>
            </ul>
        </nav>
        <p class="text-muted small">Mostrando <?= count($rows) ?> de <?= $totalRows ?> productos.</p>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>